<?php

declare(strict_types=1);

/** @var array<string, mixed>|null $slot */
/** @var ScheduleService $schedule */
/** @var string $label */
/** @var string $modifier */
/** @var bool $showSpeakerAvatars */

if ($slot === null) {
    return;
}

$title = $schedule->slotTitle($slot);
$speakerProfiles = $schedule->slotSpeakerProfiles($slot);
$speakers = $schedule->slotSpeakers($slot);
$description = $schedule->slotDescription($slot);
$time = $schedule->formatTimeRange($slot);
$hasAvatars = !empty($showSpeakerAvatars) && array_filter(
    $speakerProfiles,
    static fn (array $profile): bool => !empty($profile['avatar_url'])
) !== [];
?>
<section class="hero hero--<?= e($modifier) ?>">
    <p class="hero__label"><?= e($label) ?></p>
    <p class="hero__time"><?= e($time) ?></p>
    <h2 class="hero__session"><?= e($title) ?></h2>
    <?php if ($hasAvatars): ?>
        <div class="hero__speakers" aria-label="Speakers">
            <?php foreach ($speakerProfiles as $speaker): ?>
                <div class="hero__speaker-item">
                    <?php if (!empty($speaker['avatar_url'])): ?>
                        <img
                            class="hero__speaker-avatar"
                            src="<?= e((string) $speaker['avatar_url']) ?>"
                            alt=""
                            width="80"
                            height="80"
                            loading="lazy"
                            decoding="async"
                        >
                    <?php endif; ?>
                    <span class="hero__speaker-name"><?= e($speaker['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($speakers !== ''): ?>
        <p class="hero__speaker">
            <span class="hero__field-label">Speaker</span>
            <?= e($speakers) ?>
        </p>
    <?php endif; ?>
    <?php if ($description !== ''): ?>
        <p class="hero__description"><?= e($description) ?></p>
    <?php endif; ?>
</section>
