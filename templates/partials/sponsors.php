<?php

declare(strict_types=1);

/** @var list<array{name: string, logo: string, url?: string}> $sponsors */

if ($sponsors === []) {
    return;
}

$logoCount = count($sponsors);
?>
<style>
    .sponsor-bar {
        display: block;
        width: 100%;
    }

    .sponsor-bar__logos {
        display: grid !important;
        grid-template-columns: repeat(<?= (int) $logoCount ?>, minmax(0, 1fr)) !important;
        align-items: center;
        justify-items: center;
        column-gap: 2.5rem;
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .sponsor-bar__item {
        display: block;
        width: 100%;
        text-align: center;
    }

    .sponsor-bar__link {
        display: inline-block;
        line-height: 0;
    }

    .sponsor-bar__logos img {
        display: inline-block !important;
        max-height: 52px !important;
        max-width: 300px !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain;
        vertical-align: middle;
    }
</style>
<aside class="sponsor-bar" aria-label="Sponsors">
    <div class="sponsor-bar__logos">
        <?php foreach ($sponsors as $sponsor): ?>
            <div class="sponsor-bar__item">
                <?php if (!empty($sponsor['url'])): ?>
                    <a class="sponsor-bar__link" href="<?= e((string) $sponsor['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= e((string) $sponsor['logo']) ?>" alt="<?= e((string) $sponsor['name']) ?>" loading="lazy">
                    </a>
                <?php else: ?>
                    <img src="<?= e((string) $sponsor['logo']) ?>" alt="<?= e((string) $sponsor['name']) ?>" loading="lazy">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</aside>
