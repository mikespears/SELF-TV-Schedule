<?php

declare(strict_types=1);

$config = require __DIR__ . '/bootstrap.php';

$slug = isset($_GET['room']) ? (string) $_GET['room'] : '';
if ($slug === '' || !isset($config['rooms'][$slug])) {
    header('Location: index.php', true, 302);
    exit;
}

$room = $config['rooms'][$slug];
$refresh = (int) $config['refresh_seconds'];
$locale = (string) $config['locale'];
$timezone = (string) $config['timezone'];
$eventTitle = (string) ($config['event_title'] ?? 'Conference');

$client = new PretalxClient($config);
$schedule = new ScheduleService($timezone, $locale);
$now = resolveReferenceNow($config);
$testClock = isTestClockActive($config, $now);

$error = null;
$view = ['now' => null, 'up_next' => null, 'today' => []];
$staleNotice = false;

try {
    $allSlots = $client->getSlots();

    if ($client->hasLoadError()) {
        $error = 'Unable to load schedule. Retrying shortly.';
    } else {
        if ($client->usedStaleCache() || $client->hadIncompleteFetch()) {
            $staleNotice = true;
        }

        $roomSlots = $schedule->slotsForRoom($allSlots, (int) $room['id']);
        $view = $schedule->buildRoomView($roomSlots, $now);
    }
} catch (Throwable $exception) {
    logScheduleException($exception);
    $error = 'Unable to load schedule. Retrying shortly.';
}

$dateLabel = $now->format('l, F j, Y');
$lastUpdatedLabel = formatLastUpdated($client->getLastUpdated(), $timezone);
$idleHeroMessage = scheduleIdleHeroMessage($view);
/** @var list<array{name: string, logo: string, url?: string}> $goldSponsors */
$goldSponsors = is_array($config['gold_sponsors'] ?? null) ? $config['gold_sponsors'] : [];

$listSlots = [];
if ($error === null) {
    $listSlots = array_values(array_filter(
        $view['today'],
        static fn (array $slot): bool => $schedule->slotStatus($slot, $now) !== 'past'
    ));
    $listSlots = array_slice($listSlots, 0, 8);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1920, height=1080">
    <meta http-equiv="refresh" content="<?= (int) $refresh ?>">
    <title><?= e($room['label']) ?> — <?= e($eventTitle) ?></title>
    <?php $cssVersion = is_file(__DIR__ . '/assets/tv.css') ? (int) filemtime(__DIR__ . '/assets/tv.css') : 1; ?>
    <link rel="stylesheet" href="assets/tv.css?v=<?= $cssVersion ?>">
</head>
<body
    class="page-room<?= $testClock ? ' page-room--test-clock' : '' ?>"
    data-room="<?= e($slug) ?>"
    data-timezone="<?= e($timezone) ?>"
>
    <header class="site-header site-header--room">
        <?php require __DIR__ . '/templates/partials/event-logo.php'; ?>
        <div class="site-header__titles">
            <h1><?= e($room['label']) ?></h1>
            <?php if ($room['subtitle'] !== ''): ?>
                <p class="room-subtitle"><?= e($room['subtitle']) ?></p>
            <?php endif; ?>
        </div>
        <div class="site-header__meta">
            <p class="date-label"><?= e($dateLabel) ?></p>
            <p class="clock" id="clock"<?= $testClock ? ' data-frozen="' . e($now->format('c')) . '"' : '' ?>><?= $testClock ? e($now->format('g:i:s A')) : '' ?></p>
        </div>
    </header>

    <?php if ($testClock): ?>
        <div class="test-clock-banner" role="status">Test clock: <?= e($now->format('Y-m-d g:i A T')) ?></div>
    <?php endif; ?>

    <?php if ($staleNotice && $error === null): ?>
        <div class="warn-banner" role="status">Showing last known schedule; refresh may be delayed.</div>
    <?php endif; ?>

    <div class="page-room__body">
        <?php if ($error !== null): ?>
            <div class="error-banner" role="alert"><?= e($error) ?></div>
        <?php else: ?>
            <div class="hero-row">
                <?php
                $heroPartial = __DIR__ . '/templates/partials/hero.php';
                $label = 'Now';
                $modifier = 'now';
                $slot = $view['now'];
                require $heroPartial;
                $label = 'Up Next';
                $modifier = 'next';
                $slot = $view['up_next'];
                require $heroPartial;
                ?>
                <?php if ($view['now'] === null && $view['up_next'] === null): ?>
                    <section class="hero hero--empty">
                        <p class="hero__label">Now</p>
                        <h2 class="hero__session"><?= e($idleHeroMessage) ?></h2>
                    </section>
                <?php endif; ?>
            </div>

            <main class="schedule-list">
                <h2 class="schedule-list__heading">Coming up today</h2>
                <?php if ($listSlots === []): ?>
                    <p class="schedule-list__empty">No more sessions scheduled in this room today.</p>
                <?php else: ?>
                    <ul class="schedule-list__items">
                        <?php foreach ($listSlots as $slot): ?>
                            <?php
                            $status = $schedule->slotStatus($slot, $now);
                            $title = $schedule->slotTitle($slot);
                            $speakers = $schedule->slotSpeakers($slot);
                            $time = $schedule->formatTimeRange($slot);
                            ?>
                            <li class="session session--<?= e($status) ?>">
                                <span class="session__time"><?= e($time) ?></span>
                                <div class="session__body">
                                    <span class="session__title"><?= e($title) ?></span>
                                    <?php if ($speakers !== ''): ?>
                                        <span class="session__speakers"><?= e($speakers) ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </main>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/templates/partials/sponsors.php'; ?>

    <footer class="site-footer site-footer--room">
        <a href="index.php">All rooms</a>
        <span>
            Updates every <?= (int) $refresh ?>s
            <?php if ($lastUpdatedLabel !== ''): ?>
                &middot; Schedule data <?= e($lastUpdatedLabel) ?>
            <?php endif; ?>
        </span>
    </footer>

    <?php if (!$testClock): ?>
        <script src="assets/clock.js"></script>
    <?php endif; ?>
</body>
</html>
