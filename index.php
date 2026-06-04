<?php

declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';
Security::sendSecurityHeaders();
$refresh = (int) $config['refresh_seconds'];
$timezone = (string) $config['timezone'];
$eventTitle = (string) ($config['event_title'] ?? 'Conference');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="<?= (int) $refresh ?>">
    <title><?= e($eventTitle) ?> — Ballroom Schedules</title>
    <link rel="stylesheet" href="assets/tv.css">
</head>
<body class="page-index" data-timezone="<?= e($timezone) ?>">
    <header class="site-header site-header--index">
        <?php require __DIR__ . '/templates/partials/event-logo.php'; ?>
        <div class="site-header__index-main">
            <h1>Ballroom Schedules</h1>
            <p class="clock" id="clock"></p>
        </div>
    </header>

    <main class="room-grid">
        <?php foreach ($config['rooms'] as $slug => $room): ?>
            <a class="room-card" href="room.php?room=<?= e($slug) ?>">
                <span class="room-card__label"><?= e($room['label']) ?></span>
                <?php if ($room['subtitle'] !== ''): ?>
                    <span class="room-card__subtitle"><?= e($room['subtitle']) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </main>

    <footer class="site-footer">
        <p>Select a room for the entrance display. Data from pretalx.</p>
    </footer>

    <script src="assets/clock.js"></script>
</body>
</html>
