<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
$logo = $config['event_logo'] ?? null;
if (!is_array($logo) || empty($logo['src'])) {
    return;
}

$src = (string) $logo['src'];
$alt = (string) ($logo['alt'] ?? 'Southeast Linux Fest');
$href = isset($logo['url']) && is_string($logo['url']) && $logo['url'] !== '' ? $logo['url'] : '';
?>
<div class="site-logo">
    <?php if ($href !== ''): ?>
        <a class="site-logo__link" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer">
            <img class="site-logo__img" src="<?= e($src) ?>" alt="<?= e($alt) ?>" width="400" height="120">
        </a>
    <?php else: ?>
        <img class="site-logo__img" src="<?= e($src) ?>" alt="<?= e($alt) ?>" width="400" height="120">
    <?php endif; ?>
</div>
