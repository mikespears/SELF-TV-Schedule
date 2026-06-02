<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $slot */
/** @var ScheduleService $schedule */
/** @var string $label */
/** @var string $modifier */

if ($slot === null) {
    return;
}

$title = $schedule->slotTitle($slot);
$speakers = $schedule->slotSpeakers($slot);
$time = $schedule->formatTimeRange($slot);
?>
<section class="hero hero--<?= e($modifier) ?>">
    <p class="hero__label"><?= e($label) ?></p>
    <p class="hero__time"><?= e($time) ?></p>
    <h2 class="hero__title"><?= e($title) ?></h2>
    <?php if ($speakers !== ''): ?>
        <p class="hero__speakers"><?= e($speakers) ?></p>
    <?php endif; ?>
</section>
