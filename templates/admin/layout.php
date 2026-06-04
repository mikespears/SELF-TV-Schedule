<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
$cssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/tv.css');
$adminCssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/admin.css');
$eventTitle = (string) ($config['event_title'] ?? 'Conference');
$currentAdmin = $auth->getCurrentUser();
$signedInAs = is_array($currentAdmin) ? (string) $currentAdmin['username'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — <?= e($eventTitle) ?> Admin</title>
    <link rel="stylesheet" href="../assets/tv.css?v=<?= $cssVer ?>">
    <link rel="stylesheet" href="../assets/admin.css?v=<?= $adminCssVer ?>">
</head>
<body class="page-admin">
    <header class="site-header site-header--index admin-header">
        <?php require __DIR__ . '/../partials/event-logo.php'; ?>
        <div class="admin-header__titles">
            <h1>Schedule admin</h1>
            <p class="admin-header__subtitle"><?= e($eventTitle) ?></p>
        </div>
        <nav class="admin-nav">
            <a href="index.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="../index.php" target="_blank" rel="noopener">Room picker</a>
            <?php if ($signedInAs !== ''): ?>
                <span class="admin-nav__user"><?= e($signedInAs) ?></span>
            <?php endif; ?>
            <form method="post" action="logout.php" class="admin-logout-form">
                <?= $auth->csrfField() ?>
                <button type="submit" class="btn">Log out</button>
            </form>
        </nav>
    </header>
    <main class="admin-main">
        <?php if ($flash !== null): ?>
            <div class="admin-flash admin-flash--<?= e((string) $flash['type']) ?>">
                <?= e((string) $flash['message']) ?>
            </div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
