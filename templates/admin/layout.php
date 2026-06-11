<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
$cssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/tv.css');
$adminCssVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/admin.css');
$adminJsVer = (int) @filemtime(dirname(__DIR__, 2) . '/assets/admin.js');
$eventTitle = (string) ($config['event_title'] ?? 'Conference');
$currentAdmin = $auth->getCurrentUser();
$signedInAs = is_array($currentAdmin) ? (string) $currentAdmin['username'] : '';
$adminSidebar = !empty($adminSidebar);
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
<body class="page-admin<?= $adminSidebar ? ' page-admin--dashboard' : '' ?>">
    <header class="site-header site-header--index admin-header">
        <?php require __DIR__ . '/../partials/event-logo.php'; ?>
        <div class="admin-header__titles">
            <h1>Schedule admin</h1>
            <p class="admin-header__subtitle"><?= e($eventTitle) ?></p>
        </div>
        <nav class="admin-nav">
            <a href="index.php"<?= $adminSidebar ? ' aria-current="page"' : '' ?>>Dashboard</a>
            <a href="users.php"<?= !$adminSidebar && ($pageTitle ?? '') === 'Admin users' ? ' aria-current="page"' : '' ?>>Users</a>
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
    <div class="admin-shell">
        <?php if ($adminSidebar): ?>
            <?php require __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>
        <main class="admin-main">
            <?php if ($flash !== null): ?>
                <div class="admin-flash admin-flash--<?= e((string) $flash['type']) ?>">
                    <?= e((string) $flash['message']) ?>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
    <script src="../assets/admin.js?v=<?= $adminJsVer ?>"></script>
</body>
</html>
