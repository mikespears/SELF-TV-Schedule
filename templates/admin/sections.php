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
?>

<section class="admin-section" id="overview">
    <h2>Overview</h2>
    <p class="hint">Runtime settings<?= $overridesExist ? ' include saved overrides from data/settings.json' : ' use defaults from config.php' ?>.</p>

    <?php if ($allowTest): ?>
        <div class="admin-warning">
            <strong>Test clock is enabled.</strong> TVs and previews may show a fake time. Turn it off before the event.
        </div>
    <?php endif; ?>

    <table class="admin-table">
        <tbody>
            <tr>
                <th>Schedule cache</th>
                <td>
                    <?php if ($cacheInfo['exists']): ?>
                        Present — <?= (int) $cacheInfo['slot_count'] ?> slots,
                        updated <?= e(formatLastUpdated($cacheInfo['mtime'], $timezone)) ?>
                        (<?= e($timezone) ?>)
                    <?php else: ?>
                        Not cached yet (will fetch on next room load)
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>API base</th>
                <td><code><?= e((string) ($config['api_base'] ?? '')) ?></code></td>
            </tr>
        </tbody>
    </table>

    <h3>Display links</h3>
    <ul class="link-list">
        <li><a href="../index.php" target="_blank" rel="noopener">index.php</a> — room picker</li>
        <?php foreach ($rooms as $slug => $room): ?>
            <?php
            $roomUrl = '../room.php?room=' . rawurlencode((string) $slug);
            if ($allowTest && $testNowQuery !== '') {
                $roomUrl .= '&now=' . rawurlencode($testNowQuery);
            }
            ?>
            <li>
                <a href="<?= e($roomUrl) ?>" target="_blank" rel="noopener">
                    <?= e((string) ($room['label'] ?? $slug)) ?>
                </a>
                <span class="hint">(<code><?= e((string) $slug) ?></code>)</span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="admin-section" id="test-mode">
    <h2>Test mode</h2>
    <p class="hint">Preview schedules at a specific time. Leave test time empty to use only <code>?now=</code> on URLs.</p>

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
            <p class="hint">Preview links append <code>&amp;now=<?= e($testNowQuery) ?></code> when test clock is enabled.</p>
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

        <label for="event_title">Event title</label>
        <input type="text" id="event_title" name="event_title" value="<?= e((string) ($config['event_title'] ?? '')) ?>" required>

        <label for="timezone">Timezone</label>
        <input type="text" id="timezone" name="timezone" value="<?= e($timezone) ?>" required>

        <label for="refresh_seconds">Page refresh (seconds)</label>
        <input type="number" id="refresh_seconds" name="refresh_seconds" min="1" value="<?= e((string) ($config['refresh_seconds'] ?? '60')) ?>" required>

        <label for="cache_ttl_seconds">Schedule cache TTL (seconds)</label>
        <input type="number" id="cache_ttl_seconds" name="cache_ttl_seconds" min="1" value="<?= e((string) ($config['cache_ttl_seconds'] ?? '300')) ?>" required>

        <h3>Event logo</h3>
        <label for="event_logo_src">Image URL</label>
        <input type="url" id="event_logo_src" name="event_logo_src" value="<?= e((string) ($eventLogo['src'] ?? '')) ?>" required>

        <label for="event_logo_alt">Alt text</label>
        <input type="text" id="event_logo_alt" name="event_logo_alt" value="<?= e((string) ($eventLogo['alt'] ?? '')) ?>" required>

        <label for="event_logo_url">Link URL (optional)</label>
        <input type="url" id="event_logo_url" name="event_logo_url" value="<?= e((string) ($eventLogo['url'] ?? '')) ?>">

        <div class="admin-actions">
            <button type="submit" class="primary">Save event settings</button>
        </div>
    </form>
</section>

<section class="admin-section" id="sponsors">
    <h2>Sponsors</h2>
    <p class="hint">Logo and website fields must be full <code>https://</code> URLs. Assign each sponsor to all rooms or selected rooms only.</p>

    <form method="post" action="save.php" class="admin-form" id="sponsors-form">
        <?= $auth->csrfField() ?>
        <input type="hidden" name="action" value="sponsors">

        <div id="sponsor-rows">
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

<section class="admin-section" id="rooms">
    <h2>Rooms</h2>
    <p class="hint">
        URL slugs appear in TV bookmarks (<code>room.php?room=…</code>). Display names are pulled from pretalx automatically;
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
    <h2>Pretalx connection</h2>
    <p class="hint">API base is derived from host and event slug on save.</p>

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

<script>
(function () {
    function bindRemove(container, selector, attr) {
        container.addEventListener('click', function (event) {
            var btn = event.target.closest(selector);
            if (!btn) return;
            var row = btn.closest('[' + attr + ']');
            if (row) row.remove();
        });
    }

    function bindSponsorScope(row) {
        var picks = row.querySelector('[data-sponsor-room-picks]');
        var radios = row.querySelectorAll('[data-sponsor-scope]');
        function syncScope() {
            var checked = row.querySelector('[data-sponsor-scope]:checked');
            if (picks) {
                picks.hidden = !checked || checked.value !== 'rooms';
            }
        }
        radios.forEach(function (radio) {
            radio.addEventListener('change', syncScope);
        });
        syncScope();
    }

    function renumberSponsorRows() {
        var sponsorRows = document.getElementById('sponsor-rows');
        if (!sponsorRows) {
            return;
        }
        sponsorRows.querySelectorAll('[data-sponsor-row]').forEach(function (row, idx) {
            row.querySelectorAll('[data-sponsor-scope]').forEach(function (radio) {
                radio.name = 'sponsor_scope[' + idx + ']';
            });
            row.querySelectorAll('.sponsor-room-picks__option input[type="checkbox"]').forEach(function (box) {
                box.name = 'sponsor_rooms[' + idx + '][]';
            });
        });
    }

    var sponsorRows = document.getElementById('sponsor-rows');
    var sponsorTemplate = document.getElementById('sponsor-template');
    if (sponsorRows && sponsorTemplate) {
        bindRemove(sponsorRows, '[data-remove-sponsor]', 'data-sponsor-row');
        sponsorRows.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove-sponsor]')) {
                setTimeout(renumberSponsorRows, 0);
            }
        });
        sponsorRows.querySelectorAll('[data-sponsor-row]').forEach(bindSponsorScope);
        renumberSponsorRows();
        document.getElementById('add-sponsor')?.addEventListener('click', function () {
            var idx = sponsorRows.querySelectorAll('[data-sponsor-row]').length;
            var html = sponsorTemplate.innerHTML.replace(/__IDX__/g, String(idx));
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var row = wrap.firstElementChild;
            if (row) {
                sponsorRows.appendChild(row);
                bindSponsorScope(row);
                renumberSponsorRows();
            }
        });
    }

    function bindPretalxRoomSelect(row) {
        var select = row.querySelector('[data-room-pretalx-select]');
        if (!select) {
            return;
        }
        function syncFromPretalx() {
            var opt = select.options[select.selectedIndex];
            var idInput = row.querySelector('[data-room-id]');
            var labelInput = row.querySelector('[data-room-label]');
            var slugInput = row.querySelector('[data-room-slug]');
            if (!opt || opt.value === '') {
                if (idInput) idInput.value = '';
                if (labelInput) labelInput.value = '';
                return;
            }
            if (idInput) idInput.value = opt.value;
            if (labelInput) labelInput.value = opt.dataset.label || '';
            if (slugInput && !slugInput.value && opt.dataset.slug) {
                slugInput.value = opt.dataset.slug;
            }
        }
        select.addEventListener('change', syncFromPretalx);
        syncFromPretalx();
    }

    var roomRows = document.getElementById('room-rows');
    var roomTemplate = document.getElementById('room-template');
    if (roomRows && roomTemplate) {
        bindRemove(roomRows, '[data-remove-room]', 'data-room-row');
        roomRows.querySelectorAll('[data-room-row]').forEach(bindPretalxRoomSelect);
        document.getElementById('add-room')?.addEventListener('click', function () {
            var node = roomTemplate.content.cloneNode(true);
            roomRows.appendChild(node);
            var row = roomRows.lastElementChild;
            if (row) {
                bindPretalxRoomSelect(row);
            }
        });
    }
})();
</script>
