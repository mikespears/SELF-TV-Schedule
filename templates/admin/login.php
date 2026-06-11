<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
$cssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/tv.css');
$adminCssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/admin.css');
$eventTitle = (string) ($config['event_title'] ?? 'Conference');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e($eventTitle) ?></title>
    <link rel="stylesheet" href="../assets/tv.css?v=<?= $cssVer ?>">
    <link rel="stylesheet" href="../assets/admin.css?v=<?= $adminCssVer ?>">
</head>
<body class="page-admin login-page">
    <div class="login-card">
        <?php require __DIR__ . '/../partials/event-logo.php'; ?>
        <h1><?= e($setupMode ? 'Create admin account' : 'Schedule admin') ?></h1>

        <?php if ($setupMode): ?>
            <p class="hint">No admin users exist yet. Create the first account to continue.</p>
            <?php if (!$setupTokenRequired): ?>
                <p class="login-error">First-time setup is not enabled. Your server administrator must enable it before you can create an account.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <p class="login-error"><?= e($error) ?></p>
        <?php elseif ($lockSeconds > 0 && !$setupMode): ?>
            <p class="login-error">Sign-in is temporarily locked. Try again in <?= (int) max(1, ceil($lockSeconds / 60)) ?> minute(s).</p>
        <?php endif; ?>

        <?php if ($setupMode && $setupTokenRequired): ?>
            <form method="post" class="admin-form">
                <?= $auth->csrfField() ?>
                <input type="hidden" name="form" value="setup">

                <label for="setup_token">Setup token</label>
                <input type="password" id="setup_token" name="setup_token" autocomplete="off" required autofocus>

                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" pattern="[a-z0-9._-]{3,32}" required>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>

                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" minlength="10" required>

                <p class="hint">Use the one-time setup token from your server administrator. Password must be at least 10 characters.</p>

                <div class="admin-actions">
                    <button type="submit" class="primary">Create account</button>
                </div>
            </form>
        <?php elseif (!$setupMode): ?>
            <form method="post" class="admin-form">
                <?= $auth->csrfField() ?>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" autocomplete="username" required autofocus<?= $lockSeconds > 0 ? ' disabled' : '' ?>>

                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required<?= $lockSeconds > 0 ? ' disabled' : '' ?>>

                <div class="admin-actions">
                    <button type="submit" class="primary"<?= $lockSeconds > 0 ? ' disabled' : '' ?>>Sign in</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
