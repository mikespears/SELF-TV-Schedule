<?php

declare(strict_types=1);

/** @var array<string, mixed> $config */
/** @var array{exists: bool, mtime: int|null, slot_count: int} $cacheInfo */
/** @var list<array<string, mixed>> $pretalxRooms */
/** @var string|null $pretalxRoomsError */
/** @var bool $overridesExist */
/** @var string $testNowInput */
/** @var string $testNowQuery */
/** @var AdminAuth $auth */

$timezone = (string) ($config['timezone'] ?? 'America/New_York');
$eventLogo = is_array($config['event_logo'] ?? null) ? $config['event_logo'] : [];
$sponsors = sponsorsFromConfig($config);
// $rooms may already include pretalx labels (set in admin/index.php)
if (!isset($rooms) || !is_array($rooms)) {
    $rooms = is_array($config['rooms'] ?? null) ? $config['rooms'] : [];
}
$locale = (string) ($config['locale'] ?? 'en');
$allowTest = !empty($config['allow_test_clock']);
$showSpeakerAvatars = !array_key_exists('show_speaker_avatars', $config)
    || !empty($config['show_speaker_avatars']);
$messages = messagesFromConfig($config);
$wifiConfig = is_array($config['wifi'] ?? null) ? $config['wifi'] : [];
$wifiEnabled = !empty($wifiConfig['enabled']);
?>

<section class="admin-section" id="overview">
    <h2>Overview</h2>

    <?php if ($allowTest): ?>
        <div class="admin-warning">
            <strong>Test clock is enabled.</strong> TVs and previews may show a fake time. Turn it off before the event.
        </div>
    <?php endif; ?>

    <div class="admin-stat-grid">
        <div class="admin-stat">
            <p class="admin-stat__label">Schedule cache</p>
            <p class="admin-stat__value">
                <?php if ($cacheInfo['exists']): ?>
                    <?= (int) $cacheInfo['slot_count'] ?> slots
                <?php else: ?>
                    Not cached
                <?php endif; ?>
            </p>
            <p class="admin-stat__meta">
                <?php if ($cacheInfo['exists']): ?>
                    Updated <?= e(formatLastUpdated($cacheInfo['mtime'], $timezone)) ?> (<?= e($timezone) ?>)
                <?php else: ?>
                    Fetches on next room load
                <?php endif; ?>
            </p>
        </div>
        <div class="admin-stat">
            <p class="admin-stat__label">Schedule source</p>
            <p class="admin-stat__value"><?= e((string) ($config['event_slug'] ?? '')) ?></p>
            <p class="admin-stat__meta"><?= e((string) ($config['pretalx_host'] ?? '')) ?></p>
        </div>
        <div class="admin-stat">
            <p class="admin-stat__label">Dashboard settings</p>
            <p class="admin-stat__value"><?= $overridesExist ? 'Custom' : 'Defaults' ?></p>
            <p class="admin-stat__meta"><?= $overridesExist ? 'Saved changes in effect' : 'Using installation defaults' ?></p>
        </div>
    </div>

    <h3 class="admin-subheading">TV displays</h3>
    <div class="display-link-grid">
        <a class="display-link display-link--all" href="../index.php" target="_blank" rel="noopener">
            <span class="display-link__title">Room picker</span>
            <span class="display-link__meta">All ballrooms</span>
        </a>
        <?php foreach ($rooms as $slug => $room): ?>
            <?php
            $roomUrl = '../room.php?room=' . rawurlencode((string) $slug);
            if ($allowTest && $testNowQuery !== '') {
                $roomUrl .= '&now=' . rawurlencode($testNowQuery);
            }
            $subtitle = trim((string) ($room['subtitle'] ?? ''));
            ?>
            <a class="display-link" href="<?= e($roomUrl) ?>" target="_blank" rel="noopener">
                <span class="display-link__title"><?= e((string) ($room['label'] ?? $slug)) ?></span>
                <?php if ($subtitle !== ''): ?>
                    <span class="display-link__meta"><?= e($subtitle) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section" id="test-mode">
    <h2>Test mode</h2>
    <p class="hint">Preview schedules at a specific time. Leave the default test time empty to rely on the time parameter in preview URLs only.</p>

    <form method="post" action="save.php" class="admin-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="test_mode">

        <div class="checkbox-row">
            <label>
                <input type="checkbox" name="allow_test_clock" value="1"<?= $allowTest ? ' checked' : '' ?>>
                Allow test clock
            </label>
        </div>

        <label for="test_now">Default test time</label>
        <input type="datetime-local" id="test_now" name="test_now" value="<?= e($testNowInput) ?>">

        <?php if ($testNowQuery !== ''): ?>
            <p class="hint">TV preview links include your saved test time while test clock is enabled.</p>
        <?php endif; ?>

        <div class="admin-actions">
            <button type="submit" class="primary">Save test mode</button>
        </div>
    </form>
</section>

<section class="admin-section" id="event">
    <h2>Event and display</h2>
    <form method="post" action="save.php" class="admin-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="event">

        <div class="admin-form-grid">
            <div class="admin-form-field admin-form-field--wide">
                <label for="event_title">Event title</label>
                <input type="text" id="event_title" name="event_title" value="<?= e((string) ($config['event_title'] ?? '')) ?>" required>
            </div>
            <div class="admin-form-field">
                <label for="timezone">Timezone</label>
                <input type="text" id="timezone" name="timezone" value="<?= e($timezone) ?>" required>
            </div>
            <div class="admin-form-field">
                <label for="refresh_seconds">Page refresh (seconds)</label>
                <input type="number" id="refresh_seconds" name="refresh_seconds" min="1" value="<?= e((string) ($config['refresh_seconds'] ?? '60')) ?>" required>
            </div>
            <div class="admin-form-field">
                <label for="cache_ttl_seconds">Schedule cache TTL (seconds)</label>
                <input type="number" id="cache_ttl_seconds" name="cache_ttl_seconds" min="1" value="<?= e((string) ($config['cache_ttl_seconds'] ?? '300')) ?>" required>
            </div>
            <div class="admin-form-field admin-form-field--wide">
                <div class="checkbox-row">
                    <label>
                        <input type="checkbox" name="show_speaker_avatars" value="1"<?= $showSpeakerAvatars ? ' checked' : '' ?>>
                        Show speaker profile pictures in Now / Up Next
                    </label>
                </div>
            </div>
        </div>

        <h3 class="admin-subheading">Event logo</h3>
        <div class="admin-form-grid">
            <div class="admin-form-field admin-form-field--wide">
                <label for="event_logo_src">Image URL</label>
                <input type="url" id="event_logo_src" name="event_logo_src" value="<?= e((string) ($eventLogo['src'] ?? '')) ?>" required>
            </div>
            <div class="admin-form-field">
                <label for="event_logo_alt">Alt text</label>
                <input type="text" id="event_logo_alt" name="event_logo_alt" value="<?= e((string) ($eventLogo['alt'] ?? '')) ?>" required>
            </div>
            <div class="admin-form-field admin-form-field--wide">
                <label for="event_logo_url">Link URL (optional)</label>
                <input type="url" id="event_logo_url" name="event_logo_url" value="<?= e((string) ($eventLogo['url'] ?? '')) ?>">
            </div>
        </div>

        <div class="admin-actions">
            <button type="submit" class="primary">Save event settings</button>
        </div>
    </form>
</section>

<section class="admin-section" id="sponsors">
    <h2>Sponsors</h2>
    <p class="hint">Logo and website URLs must use HTTPS. Assign each sponsor to all rooms or selected rooms only.</p>

    <form method="post" action="save.php" class="admin-form" id="sponsors-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="sponsors">

        <div id="sponsor-rows" class="repeatable-rows">
            <?php foreach ($sponsors as $index => $sponsor): ?>
                <?php
                $sponsorRooms = $sponsor['rooms'] ?? 'all';
                $scopeAll = $sponsorRooms === 'all';
                $selectedSlugs = is_array($sponsorRooms) ? $sponsorRooms : [];
                ?>
                <div class="sponsor-row" data-sponsor-row>
                    <label>Name</label>
                    <input type="text" name="sponsor_name[]" value="<?= e((string) ($sponsor['name'] ?? '')) ?>">

                    <label>Logo URL</label>
                    <input type="url" name="sponsor_logo[]" value="<?= e((string) ($sponsor['logo'] ?? '')) ?>">

                    <label>Website URL</label>
                    <input type="url" name="sponsor_url[]" value="<?= e((string) ($sponsor['url'] ?? '')) ?>">

                    <fieldset class="sponsor-scope">
                        <legend>Show on</legend>
                        <label class="sponsor-scope__option">
                            <input type="radio" name="sponsor_scope[<?= (int) $index ?>]" value="all" data-sponsor-scope<?= $scopeAll ? ' checked' : '' ?>>
                            All rooms
                        </label>
                        <label class="sponsor-scope__option">
                            <input type="radio" name="sponsor_scope[<?= (int) $index ?>]" value="rooms" data-sponsor-scope<?= !$scopeAll ? ' checked' : '' ?>>
                            Selected rooms
                        </label>
                    </fieldset>

                    <div class="sponsor-room-picks" data-sponsor-room-picks<?= $scopeAll ? ' hidden' : '' ?>>
                        <?php foreach ($rooms as $slug => $room): ?>
                            <label class="sponsor-room-picks__option">
                                <input
                                    type="checkbox"
                                    name="sponsor_rooms[<?= (int) $index ?>][]"
                                    value="<?= e((string) $slug) ?>"
                                    <?= in_array((string) $slug, $selectedSlugs, true) ? 'checked' : '' ?>
                                >
                                <?= e((string) ($room['label'] ?? $slug)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-actions">
                        <button type="button" class="danger" data-remove-sponsor>Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-actions">
            <button type="button" id="add-sponsor">Add sponsor</button>
            <button type="submit" class="primary">Save sponsors</button>
        </div>
    </form>

    <template id="sponsor-template">
        <div class="sponsor-row" data-sponsor-row>
            <label>Name</label>
            <input type="text" name="sponsor_name[]" value="">

            <label>Logo URL</label>
            <input type="url" name="sponsor_logo[]" value="">

            <label>Website URL</label>
            <input type="url" name="sponsor_url[]" value="">

            <fieldset class="sponsor-scope">
                <legend>Show on</legend>
                <label class="sponsor-scope__option">
                    <input type="radio" name="sponsor_scope[__IDX__]" value="all" data-sponsor-scope checked>
                    All rooms
                </label>
                <label class="sponsor-scope__option">
                    <input type="radio" name="sponsor_scope[__IDX__]" value="rooms" data-sponsor-scope>
                    Selected rooms
                </label>
            </fieldset>

            <div class="sponsor-room-picks" data-sponsor-room-picks hidden>
                <?php foreach ($rooms as $slug => $room): ?>
                    <label class="sponsor-room-picks__option">
                        <input type="checkbox" name="sponsor_rooms[__IDX__][]" value="<?= e((string) $slug) ?>">
                        <?= e((string) ($room['label'] ?? $slug)) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="admin-actions">
                <button type="button" class="danger" data-remove-sponsor>Remove</button>
            </div>
        </div>
    </template>
</section>

<section class="admin-section" id="messages">
    <h2>Messages</h2>
    <p class="hint">Post announcements on room TVs. <strong>Replace schedule</strong> hides the session list for matching rooms. <strong>Below schedule</strong> adds content under the list. Replace-schedule content takes priority over below-schedule items on the same display.</p>

    <form method="post" action="save.php" class="admin-form" id="messages-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="messages">
        <div id="message-rows" class="repeatable-rows">
            <?php foreach ($messages as $index => $message): ?>
                <?php
                $messageRooms = $message['rooms'] ?? 'all';
                $scopeAll = $messageRooms === 'all';
                $selectedSlugs = is_array($messageRooms) ? $messageRooms : [];
                ?>
                <div class="message-row" data-message-row>
                    <div class="checkbox-row">
                        <label>
                            <input type="checkbox" name="message_enabled[<?= (int) $index ?>]" value="1"<?= !empty($message['enabled']) ? ' checked' : '' ?>>
                            Enabled
                        </label>
                    </div>

                    <label>Title (optional)</label>
                    <input type="text" name="message_title[]" value="<?= e((string) ($message['title'] ?? '')) ?>">

                    <label>Message</label>
                    <textarea name="message_body[]" rows="3"><?= e((string) ($message['body'] ?? '')) ?></textarea>

                    <label>Placement</label>
                    <select name="message_placement[]">
                        <option value="below"<?= ($message['placement'] ?? 'below') === 'below' ? ' selected' : '' ?>>Below schedule</option>
                        <option value="override"<?= ($message['placement'] ?? '') === 'override' ? ' selected' : '' ?>>Replace schedule</option>
                    </select>

                    <fieldset class="room-scope">
                        <legend>Show on</legend>
                        <label class="room-scope__option">
                            <input type="radio" name="message_scope[<?= (int) $index ?>]" value="all" data-room-scope<?= $scopeAll ? ' checked' : '' ?>>
                            All rooms
                        </label>
                        <label class="room-scope__option">
                            <input type="radio" name="message_scope[<?= (int) $index ?>]" value="rooms" data-room-scope<?= !$scopeAll ? ' checked' : '' ?>>
                            Selected rooms
                        </label>
                    </fieldset>

                    <div class="room-scope-picks" data-room-scope-picks<?= $scopeAll ? ' hidden' : '' ?>>
                        <?php foreach ($rooms as $slug => $room): ?>
                            <label class="room-scope-picks__option">
                                <input
                                    type="checkbox"
                                    name="message_rooms[<?= (int) $index ?>][]"
                                    value="<?= e((string) $slug) ?>"
                                    <?= in_array((string) $slug, $selectedSlugs, true) ? 'checked' : '' ?>
                                >
                                <?= e((string) ($room['label'] ?? $slug)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="admin-actions">
                        <button type="button" class="danger" data-remove-message>Remove</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-actions">
            <button type="button" id="add-message">Add message</button>
            <button type="submit" class="primary">Save messages</button>
        </div>
    </form>

    <template id="message-template">
        <div class="message-row" data-message-row>
            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="message_enabled[__IDX__]" value="1" checked>
                    Enabled
                </label>
            </div>

            <label>Title (optional)</label>
            <input type="text" name="message_title[]" value="">

            <label>Message</label>
            <textarea name="message_body[]" rows="3"></textarea>

            <label>Placement</label>
            <select name="message_placement[]">
                <option value="below" selected>Below schedule</option>
                <option value="override">Replace schedule</option>
            </select>

            <fieldset class="room-scope">
                <legend>Show on</legend>
                <label class="room-scope__option">
                    <input type="radio" name="message_scope[__IDX__]" value="all" data-room-scope checked>
                    All rooms
                </label>
                <label class="room-scope__option">
                    <input type="radio" name="message_scope[__IDX__]" value="rooms" data-room-scope>
                    Selected rooms
                </label>
            </fieldset>

            <div class="room-scope-picks" data-room-scope-picks hidden>
                <?php foreach ($rooms as $slug => $room): ?>
                    <label class="room-scope-picks__option">
                        <input type="checkbox" name="message_rooms[__IDX__][]" value="<?= e((string) $slug) ?>">
                        <?= e((string) ($room['label'] ?? $slug)) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="admin-actions">
                <button type="button" class="danger" data-remove-message>Remove</button>
            </div>
        </div>
    </template>
</section>

<section class="admin-section" id="wifi">
    <h2>WiFi QR code</h2>
    <p class="hint">Show a scannable WiFi QR code on room displays. Guests can scan to join without typing the network name or password.</p>

    <form method="post" action="save.php" class="admin-form" id="wifi-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="wifi">

        <div class="wifi-settings">
            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="wifi_enabled" value="1"<?= $wifiEnabled ? ' checked' : '' ?>>
                    Show WiFi QR code on displays
                </label>
            </div>

            <label for="wifi_label">Label</label>
            <input type="text" id="wifi_label" name="wifi_label" value="<?= e((string) ($wifiConfig['label'] ?? 'WiFi')) ?>">

            <label for="wifi_ssid">Network name (SSID)</label>
            <input type="text" id="wifi_ssid" name="wifi_ssid" value="<?= e((string) ($wifiConfig['ssid'] ?? '')) ?>" autocomplete="off">

            <label for="wifi_password">Password</label>
            <input type="text" id="wifi_password" name="wifi_password" value="<?= e((string) ($wifiConfig['password'] ?? '')) ?>" autocomplete="off">

            <label for="wifi_security">Security</label>
            <select id="wifi_security" name="wifi_security">
                <?php $wifiSecurity = strtoupper((string) ($wifiConfig['security'] ?? 'WPA')); ?>
                <option value="WPA"<?= $wifiSecurity === 'WPA' ? ' selected' : '' ?>>WPA / WPA2 / WPA3</option>
                <option value="WEP"<?= $wifiSecurity === 'WEP' ? ' selected' : '' ?>>WEP</option>
                <option value="nopass"<?= $wifiSecurity === 'NOPASS' ? ' selected' : '' ?>>Open network</option>
            </select>

            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="wifi_hidden" value="1"<?= !empty($wifiConfig['hidden']) ? ' checked' : '' ?>>
                    Hidden network
                </label>
            </div>

            <div class="checkbox-row">
                <label>
                    <input type="checkbox" name="wifi_show_password" value="1"<?= !array_key_exists('show_password', $wifiConfig) || !empty($wifiConfig['show_password']) ? ' checked' : '' ?>>
                    Show password on screen
                </label>
            </div>
        </div>

        <div class="admin-actions">
            <button type="submit" class="primary">Save WiFi</button>
        </div>
    </form>
</section>

<section class="admin-section" id="rooms">
    <h2>Rooms</h2>
    <p class="hint">
        Each room maps to a pretalx space. Display names sync from pretalx when you save;
        use <strong>Subtitle</strong> for custom ballroom text (e.g. sponsor ballroom names).
    </p>

    <?php if ($pretalxRoomsError !== null): ?>
        <p class="login-error"><?= e($pretalxRoomsError) ?></p>
    <?php else: ?>
        <p class="hint"><?= count($pretalxRooms) ?> room(s) loaded from pretalx. Names update when you save.</p>
    <?php endif; ?>

    <form method="post" action="save.php" class="admin-form" id="rooms-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="rooms">

        <div id="room-rows">
            <?php foreach ($rooms as $slug => $room): ?>
                <?php $roomId = (int) ($room['id'] ?? 0); ?>
                <div class="room-row" data-room-row>
                    <label>pretalx room</label>
                    <?php if ($pretalxRooms !== []): ?>
                        <select class="room-pretalx-select" data-room-pretalx-select required>
                            <option value="">Select pretalx room…</option>
                            <?php foreach ($pretalxRooms as $pretalxRoom): ?>
                                <?php
                                $pretalxId = (int) ($pretalxRoom['id'] ?? 0);
                                $pretalxName = pretalxRoomDisplayName($pretalxRoom, $locale);
                                ?>
                                <option
                                    value="<?= $pretalxId ?>"
                                    data-label="<?= e($pretalxName) ?>"
                                    data-slug="<?= e(suggestRoomSlug($pretalxName)) ?>"
                                    <?= $roomId === $pretalxId ? 'selected' : '' ?>
                                ><?= e($pretalxName) ?> (ID <?= $pretalxId ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="number" name="room_id[]" min="1" value="<?= e((string) $roomId) ?>" required>
                    <?php endif; ?>

                    <label>URL slug</label>
                    <input type="text" name="room_slug[]" value="<?= e((string) $slug) ?>" pattern="[a-z0-9-]+" required data-room-slug>

                    <input type="hidden" name="room_id[]" value="<?= e((string) $roomId) ?>" data-room-id>

                    <label>Display name<?= $pretalxRooms !== [] ? ' <span class="hint">(from pretalx)</span>' : '' ?></label>
                    <input
                        type="text"
                        name="room_label[]"
                        value="<?= e((string) ($room['label'] ?? '')) ?>"
                        required
                        data-room-label
                        <?= $pretalxRooms !== [] ? 'readonly class="input-readonly"' : '' ?>
                    >

                    <label>Subtitle</label>
                    <input type="text" name="room_subtitle[]" value="<?= e((string) ($room['subtitle'] ?? '')) ?>" placeholder="Optional ballroom label">

                    <div class="admin-actions">
                        <button type="button" class="danger" data-remove-room>Remove room</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="admin-actions">
            <button type="button" id="add-room"<?= $pretalxRooms === [] ? ' disabled' : '' ?>>Add room</button>
            <button type="submit" class="primary">Save rooms</button>
        </div>
    </form>

    <?php if ($pretalxRooms !== []): ?>
        <form method="post" action="save.php" class="admin-form admin-form--inline">
            <?= $auth->csrfField() ?>
            <input type="hidden" name="action" value="import_rooms_from_pretalx">
            <div class="admin-actions">
                <button type="submit" class="btn">Import all pretalx rooms</button>
            </div>
            <p class="hint">Adds any pretalx rooms not already mapped (existing slugs and IDs are kept).</p>
        </form>
    <?php endif; ?>

    <template id="room-template">
        <div class="room-row" data-room-row>
            <label>pretalx room</label>
            <?php if ($pretalxRooms !== []): ?>
                <select class="room-pretalx-select" data-room-pretalx-select required>
                    <option value="">Select pretalx room…</option>
                    <?php foreach ($pretalxRooms as $pretalxRoom): ?>
                        <?php
                        $pretalxId = (int) ($pretalxRoom['id'] ?? 0);
                        $pretalxName = pretalxRoomDisplayName($pretalxRoom, $locale);
                        ?>
                        <option
                            value="<?= $pretalxId ?>"
                            data-label="<?= e($pretalxName) ?>"
                            data-slug="<?= e(suggestRoomSlug($pretalxName)) ?>"
                        ><?= e($pretalxName) ?> (ID <?= $pretalxId ?>)</option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label>URL slug</label>
            <input type="text" name="room_slug[]" value="" pattern="[a-z0-9-]+" required data-room-slug>

            <input type="hidden" name="room_id[]" value="" data-room-id>

            <label>Display name<?= $pretalxRooms !== [] ? ' <span class="hint">(from pretalx)</span>' : '' ?></label>
            <input type="text" name="room_label[]" value="" required data-room-label<?= $pretalxRooms !== [] ? ' readonly class="input-readonly"' : '' ?>>

            <label>Subtitle</label>
            <input type="text" name="room_subtitle[]" value="" placeholder="Optional ballroom label">

            <div class="admin-actions">
                <button type="button" class="danger" data-remove-room>Remove room</button>
            </div>
        </div>
    </template>
</section>

<section class="admin-section" id="pretalx">
    <h2>Schedule connection</h2>
    <p class="hint">Connects to your pretalx event for room names and session data.</p>

    <form method="post" action="save.php" class="admin-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="pretalx">

        <label for="pretalx_host">pretalx host</label>
        <input type="url" id="pretalx_host" name="pretalx_host" value="<?= e((string) ($config['pretalx_host'] ?? '')) ?>" required>

        <label for="event_slug">Event slug</label>
        <input type="text" id="event_slug" name="event_slug" value="<?= e((string) ($config['event_slug'] ?? '')) ?>" pattern="[a-z0-9-]+" required>

        <div class="admin-actions">
            <button type="submit" class="primary">Save pretalx settings</button>
        </div>
    </form>
</section>

<section class="admin-section" id="actions">
    <h2>Actions</h2>
    <form method="post" action="save.php" class="admin-form" onsubmit="return confirm('Clear cached schedule data? The next page load will refetch from pretalx.');">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="clear_cache">
        <div class="admin-actions">
            <button type="submit" class="danger">Clear schedule cache</button>
        </div>
    </form>
</section>
