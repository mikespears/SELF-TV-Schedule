<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($auth->isLoggedIn()) {
    adminRedirect('index.php');
}

$error = null;
$setupMode = !$auth->isConfigured();
$clientIp = Security::clientIp();
$lockSeconds = $auth->loginLockRemainingSeconds($clientIp);
$setupTokenRequired = $setupMode && $setupToken->isRequired();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session token. Please try again.';
    } elseif ($auth->isLoginLocked($clientIp)) {
        $lockSeconds = $auth->loginLockRemainingSeconds($clientIp);
        $error = 'Too many failed attempts. Try again in ' . max(1, (int) ceil($lockSeconds / 60)) . ' minutes.';
    } elseif ($setupMode && ($_POST['form'] ?? '') === 'setup') {
        try {
            if (!$setupToken->isRequired()) {
                throw new InvalidArgumentException('Create data/admin/setup.token on the server before running setup.');
            }
            if (!$setupToken->verify($_POST['setup_token'] ?? null)) {
                throw new InvalidArgumentException('Invalid setup token.');
            }

            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');

            if ($password !== $confirm) {
                throw new InvalidArgumentException('Passwords do not match.');
            }

            $auth->getUserStore()->createUser($username, $password);
            $setupToken->consume();

            if ($auth->login($username, $password)) {
                adminFlash('success', 'Admin account created. Welcome!');
                adminRedirect('index.php');
            }
            $error = 'Account created but sign-in failed. Try logging in.';
            $setupMode = false;
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
    } elseif (!$setupMode && $auth->login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        adminRedirect('index.php');
    } elseif (!$setupMode) {
        $error = 'Incorrect username or password.';
        $lockSeconds = $auth->loginLockRemainingSeconds($clientIp);
    }
}

$pageTitle = $setupMode ? 'Create admin account' : 'Admin login';
require __DIR__ . '/../templates/admin/login.php';
