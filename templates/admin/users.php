<?php

declare(strict_types=1);

/** @var list<array{id: string, username: string, created_at: string, disabled: bool}> $adminUsers */
/** @var array{id: string, username: string} $currentUser */
/** @var AdminAuth $auth */
/** @var string|null $error */
?>

<section class="admin-section" id="admin-users">
    <h2>Admin users</h2>
    <p class="hint">Accounts are stored in <code>data/admin/users.json</code> (gitignored). Passwords are bcrypt hashes.</p>

    <?php if ($error !== null): ?>
        <p class="login-error"><?= e($error) ?></p>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr>
                <th>Username</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($adminUsers as $user): ?>
                <?php $isSelf = $user['id'] === $currentUser['id']; ?>
                <tr>
                    <td>
                        <?= e($user['username']) ?>
                        <?php if ($isSelf): ?>
                            <span class="hint">(you)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($user['disabled']) ? 'Disabled' : 'Active' ?></td>
                    <td><?= e($user['created_at']) ?></td>
                    <td>
                        <details class="admin-details">
                            <summary>Manage</summary>
                            <div class="admin-details__body">
                                <form method="post" class="admin-form admin-form--compact">
                                    <?= $auth->csrfField() ?>
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">

                                    <?php if (!$isSelf): ?>
                                        <label>Your current password</label>
                                        <input type="password" name="current_password" autocomplete="current-password" required>
                                    <?php endif; ?>

                                    <label>New password</label>
                                    <input type="password" name="password" autocomplete="new-password" minlength="10" required>

                                    <label>Confirm new password</label>
                                    <input type="password" name="password_confirm" autocomplete="new-password" minlength="10" required>

                                    <div class="admin-actions">
                                        <button type="submit" class="primary">Update password</button>
                                    </div>
                                </form>

                                <?php if (!$isSelf): ?>
                                    <?php if (!empty($user['disabled'])): ?>
                                        <form method="post" class="admin-form admin-form--compact">
                                            <?= $auth->csrfField() ?>
                                            <input type="hidden" name="action" value="enable_user">
                                            <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button type="submit" class="btn">Enable user</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="admin-form admin-form--compact">
                                            <?= $auth->csrfField() ?>
                                            <input type="hidden" name="action" value="disable_user">
                                            <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                            <button type="submit" class="btn">Disable user</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" class="admin-form admin-form--compact" onsubmit="return confirm('Delete this admin user?');">
                                        <?= $auth->csrfField() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                        <button type="submit" class="danger">Delete user</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="admin-section" id="admin-users-add">
    <h2>Add admin user</h2>
    <form method="post" class="admin-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="create_user">

        <label for="new_username">Username</label>
        <input type="text" id="new_username" name="username" autocomplete="off" pattern="[a-z0-9._-]{3,32}" required>

        <label for="new_password">Password</label>
        <input type="password" id="new_password" name="password" autocomplete="new-password" minlength="10" required>

        <label for="new_password_confirm">Confirm password</label>
        <input type="password" id="new_password_confirm" name="password_confirm" autocomplete="new-password" minlength="10" required>

        <p class="hint">Usernames are lowercase letters, numbers, dots, underscores, and hyphens (3–32 characters).</p>

        <div class="admin-actions">
            <button type="submit" class="primary">Create user</button>
        </div>
    </form>
</section>

<p><a href="index.php">&larr; Back to dashboard</a></p>
