<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$auth->requireLogin();

$currentUser = $auth->getCurrentUser();
if ($currentUser === null) {
    adminRedirect('login.php');
}

$userStore = $auth->getUserStore();
$flash = adminConsumeFlash();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session token. Changes were not saved.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            match ($action) {
                'create_user' => createAdminUser($userStore),
                'change_password' => changeAdminPassword($userStore, $currentUser['id']),
                'disable_user' => setAdminUserDisabled($userStore, true),
                'enable_user' => setAdminUserDisabled($userStore, false),
                'delete_user' => deleteAdminUser($userStore, $currentUser['id']),
                default => throw new InvalidArgumentException('Unknown action.'),
            };
            adminFlash('success', flashMessageForUserAction($action));
            adminRedirect('users.php');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            logScheduleException($exception);
            $error = 'Could not update users. Check server logs.';
        }
    }
}

$adminUsers = $userStore->listUsersPublic();
$pageTitle = 'Admin users';

ob_start();
require __DIR__ . '/../templates/admin/users.php';
$content = (string) ob_get_clean();

require __DIR__ . '/../templates/admin/layout.php';

/** @param AdminUserStore $store */
function createAdminUser(AdminUserStore $store): void
{
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($password !== $confirm) {
        throw new InvalidArgumentException('Passwords do not match.');
    }

    $store->createUser((string) ($_POST['username'] ?? ''), $password);
}

/** @param AdminUserStore $store */
function changeAdminPassword(AdminUserStore $store, string $actingUserId): void
{
    $userId = (string) ($_POST['user_id'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($password !== $confirm) {
        throw new InvalidArgumentException('Passwords do not match.');
    }

    $target = $store->findById($userId);
    if ($target === null) {
        throw new InvalidArgumentException('User not found.');
    }

    if ($target['id'] === $actingUserId) {
        $actingUser = $store->findById($actingUserId);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        if ($actingUser === null || !$store->verifyPassword($actingUser, $currentPassword)) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }
    }

    $store->updatePassword($userId, $password);

    if ($target['id'] === $actingUserId) {
        $auth->logout();
        adminFlash('success', 'Password updated. Sign in again.');
        adminRedirect('login.php');
    }
}

/** @param AdminUserStore $store */
function setAdminUserDisabled(AdminUserStore $store, bool $disabled): void
{
    $store->setDisabled((string) ($_POST['user_id'] ?? ''), $disabled);
}

/** @param AdminUserStore $store */
function deleteAdminUser(AdminUserStore $store, string $actingUserId): void
{
    $userId = (string) ($_POST['user_id'] ?? '');
    if ($userId === $actingUserId) {
        throw new InvalidArgumentException('You cannot delete your own account while signed in.');
    }

    $store->deleteUser($userId);
}

function flashMessageForUserAction(string $action): string
{
    return match ($action) {
        'create_user' => 'Admin user created.',
        'change_password' => 'Password updated.',
        'disable_user' => 'User disabled.',
        'enable_user' => 'User enabled.',
        'delete_user' => 'User deleted.',
        default => 'Users updated.',
    };
}
