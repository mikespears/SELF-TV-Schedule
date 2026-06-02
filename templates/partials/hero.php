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
$description = $schedule->slotDescription($slot);
$time = $schedule->formatTimeRange($slot);
?>
<section class="hero hero--<?= e($modifier) ?>">
    <p class="hero__label"><?= e($label) ?></p>
    <p class="hero__time"><?= e($time) ?></p>
    <h2 class="hero__session"><?= e($title) ?></h2>
    <?php if ($speakers !== ''): ?>
        <p class="hero__speaker">
            <span class="hero__field-label">Speaker</span>
            <?= e($speakers) ?>
        </p>
    <?php endif; ?>
    <?php if ($description !== ''): ?>
        <p class="hero__description"><?= e($description) ?></p>
    <?php endif; ?>
</section>
