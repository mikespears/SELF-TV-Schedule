<?php

declare(strict_types=1);

/** @var array{
 *     label: string,
 *     ssid: string,
 *     password: string,
 *     security: string,
 *     show_password: bool,
 *     qr_payload: string
 * } $wifi
 * @var string $modifier
 */
?>
<aside class="wifi-card wifi-card--<?= e($modifier) ?>" aria-label="<?= e($wifi['label']) ?>">
    <div class="wifi-card__qr" data-wifi-qr="<?= e($wifi['qr_payload']) ?>"></div>
    <div class="wifi-card__details">
        <p class="wifi-card__label"><?= e($wifi['label']) ?></p>
        <p class="wifi-card__ssid"><?= e($wifi['ssid']) ?></p>
        <?php if ($wifi['show_password'] && $wifi['security'] !== 'NOPASS' && $wifi['password'] !== ''): ?>
            <p class="wifi-card__password"><?= e($wifi['password']) ?></p>
        <?php endif; ?>
    </div>
</aside>
