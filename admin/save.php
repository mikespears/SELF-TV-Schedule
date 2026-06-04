<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adminRedirect('index.php');
}

if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
    adminFlash('error', 'Invalid session token. Changes were not saved.');
    adminRedirect('index.php');
}

$action = (string) ($_POST['action'] ?? '');

try {
    match ($action) {
        'test_mode' => saveTestMode($store),
        'event' => saveEvent($store),
        'sponsors' => saveSponsors($store),
        'rooms' => saveRooms($store),
        'import_rooms_from_pretalx' => importRoomsFromPretalx($store),
        'pretalx' => savePretalx($store),
        'clear_cache' => clearCache($store),
        default => throw new InvalidArgumentException('Unknown action.'),
    };
} catch (InvalidArgumentException $exception) {
    adminFlash('error', $exception->getMessage());
    adminRedirect('index.php', anchorForAction($action));
} catch (Throwable $exception) {
    logScheduleException($exception);
    adminFlash('error', 'Could not save changes. Check server logs.');
    adminRedirect('index.php', anchorForAction($action));
}

adminFlash('success', flashMessageForAction($action));
adminRedirect('index.php', anchorForAction($action));

/** @param ConfigStore $store */
function saveTestMode(ConfigStore $store): void
{
    $allow = isset($_POST['allow_test_clock']);
    $testNow = trim((string) ($_POST['test_now'] ?? ''));

    $store->mergeAndSave([
        'allow_test_clock' => $allow,
        'test_now' => $testNow === '' ? null : $testNow,
    ]);
}

/** @param ConfigStore $store */
function saveEvent(ConfigStore $store): void
{
    $store->mergeAndSave([
        'event_title' => $_POST['event_title'] ?? '',
        'timezone' => $_POST['timezone'] ?? '',
        'refresh_seconds' => $_POST['refresh_seconds'] ?? '',
        'cache_ttl_seconds' => $_POST['cache_ttl_seconds'] ?? '',
        'event_logo' => [
            'src' => $_POST['event_logo_src'] ?? '',
            'alt' => $_POST['event_logo_alt'] ?? '',
            'url' => $_POST['event_logo_url'] ?? '',
        ],
    ]);
}

/** @param ConfigStore $store */
function saveSponsors(ConfigStore $store): void
{
    $names = $_POST['sponsor_name'] ?? [];
    $logos = $_POST['sponsor_logo'] ?? [];
    $urls = $_POST['sponsor_url'] ?? [];
    $scopes = $_POST['sponsor_scope'] ?? [];
    $roomPicks = $_POST['sponsor_rooms'] ?? [];

    if (!is_array($names) || !is_array($logos) || !is_array($scopes)) {
        throw new InvalidArgumentException('Invalid sponsor data.');
    }

    $sponsors = [];
    $count = max(count($names), count($logos), count($scopes));

    for ($i = 0; $i < $count; $i++) {
        $scope = (string) ($scopes[$i] ?? 'all');
        $rooms = 'all';
        if ($scope === 'rooms') {
            $picked = is_array($roomPicks[$i] ?? null) ? $roomPicks[$i] : [];
            $rooms = array_values(array_filter(array_map(
                static fn ($slug): string => trim((string) $slug),
                $picked
            )));
        }

        $sponsors[] = [
            'name' => $names[$i] ?? '',
            'logo' => $logos[$i] ?? '',
            'url' => is_array($urls) ? ($urls[$i] ?? '') : '',
            'rooms' => $rooms,
        ];
    }

    $store->mergeAndSave(['sponsors' => $sponsors]);
}

/** @param ConfigStore $store */
function saveRooms(ConfigStore $store): void
{
    $slugs = $_POST['room_slug'] ?? [];
    $ids = $_POST['room_id'] ?? [];
    $labels = $_POST['room_label'] ?? [];
    $subtitles = $_POST['room_subtitle'] ?? [];

    if (!is_array($slugs) || !is_array($ids) || !is_array($labels)) {
        throw new InvalidArgumentException('Invalid room data.');
    }

    $rooms = [];
    $count = count($slugs);

    for ($i = 0; $i < $count; $i++) {
        $rooms[] = [
            'slug' => $slugs[$i] ?? '',
            'id' => $ids[$i] ?? '',
            'label' => $labels[$i] ?? '',
            'subtitle' => is_array($subtitles) ? ($subtitles[$i] ?? '') : '',
        ];
    }

    $config = $store->load();
    $locale = (string) ($config['locale'] ?? 'en');

    try {
        $pretalxById = pretalxRoomsById((new PretalxClient($config))->getRooms());
        $rooms = applyPretalxRoomLabels($rooms, $pretalxById, $locale);
    } catch (Throwable $exception) {
        logScheduleException($exception);
    }

    $store->mergeAndSave(['rooms' => $rooms]);
}

/** @param ConfigStore $store */
function importRoomsFromPretalx(ConfigStore $store): void
{
    $config = $store->load();
    $locale = (string) ($config['locale'] ?? 'en');
    $existing = is_array($config['rooms'] ?? null) ? $config['rooms'] : [];

    try {
        $pretalxRooms = (new PretalxClient($config))->getRooms();
    } catch (Throwable $exception) {
        logScheduleException($exception);
        throw new InvalidArgumentException('Could not fetch rooms from pretalx.');
    }

    if ($pretalxRooms === []) {
        throw new InvalidArgumentException('No rooms returned from pretalx.');
    }

    $usedSlugs = array_keys($existing);
    $usedIds = [];
    foreach ($existing as $room) {
        if (is_array($room)) {
            $usedIds[] = (int) ($room['id'] ?? 0);
        }
    }

    foreach ($pretalxRooms as $pretalxRoom) {
        if (!is_array($pretalxRoom)) {
            continue;
        }
        $id = (int) ($pretalxRoom['id'] ?? 0);
        if ($id < 1 || in_array($id, $usedIds, true)) {
            continue;
        }

        $name = pretalxRoomDisplayName($pretalxRoom, $locale);
        $slug = uniqueRoomSlug(suggestRoomSlug($name), $usedSlugs);
        $existing[$slug] = [
            'id' => $id,
            'label' => $name,
            'subtitle' => '',
        ];
        $usedSlugs[] = $slug;
        $usedIds[] = $id;
    }

    $rooms = [];
    foreach ($existing as $slug => $room) {
        if (!is_array($room)) {
            continue;
        }
        $rooms[] = [
            'slug' => (string) $slug,
            'id' => $room['id'] ?? 0,
            'label' => $room['label'] ?? '',
            'subtitle' => $room['subtitle'] ?? '',
        ];
    }

    $pretalxById = pretalxRoomsById($pretalxRooms);
    $rooms = applyPretalxRoomLabels($rooms, $pretalxById, $locale);

    $store->mergeAndSave(['rooms' => $rooms]);
}

/** @param ConfigStore $store */
function savePretalx(ConfigStore $store): void
{
    $store->mergeAndSave([
        'pretalx_host' => $_POST['pretalx_host'] ?? '',
        'event_slug' => $_POST['event_slug'] ?? '',
    ]);
}

/** @param ConfigStore $store */
function clearCache(ConfigStore $store): void
{
    if (!$store->clearCache()) {
        throw new RuntimeException('Could not clear schedule cache.');
    }
}

function anchorForAction(string $action): ?string
{
    return match ($action) {
        'test_mode' => 'test-mode',
        'event' => 'event',
        'sponsors' => 'sponsors',
        'rooms' => 'rooms',
        'import_rooms_from_pretalx' => 'rooms',
        'pretalx' => 'pretalx',
        'clear_cache' => 'actions',
        default => null,
    };
}

function flashMessageForAction(string $action): string
{
    return match ($action) {
        'clear_cache' => 'Schedule cache cleared.',
        'import_rooms_from_pretalx' => 'Rooms imported from pretalx.',
        default => 'Settings saved.',
    };
}
