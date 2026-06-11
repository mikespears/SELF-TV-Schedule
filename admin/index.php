<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$auth->requireLogin();

$config = $store->load();
$flash = adminConsumeFlash();
$cacheInfo = $store->getCacheInfo();
$overridesExist = $store->hasOverrides();

$pretalxRooms = [];
$pretalxRoomsError = null;
$pretalxById = [];

try {
    $client = new PretalxClient($config);
    $pretalxRooms = $client->getRooms();
    $pretalxById = pretalxRoomsById($pretalxRooms);
    if ($pretalxRooms === []) {
        $pretalxRoomsError = 'No rooms returned from pretalx (check host, slug, and network).';
    }
} catch (Throwable $exception) {
    logScheduleException($exception);
    $pretalxRoomsError = 'Could not fetch rooms from pretalx.';
}

$locale = (string) ($config['locale'] ?? 'en');
$rooms = is_array($config['rooms'] ?? null) ? $config['rooms'] : [];
if ($pretalxById !== []) {
    foreach ($rooms as $slug => &$room) {
        if (!is_array($room)) {
            continue;
        }
        $id = (int) ($room['id'] ?? 0);
        if ($id > 0 && isset($pretalxById[$id])) {
            $room['label'] = pretalxRoomDisplayName($pretalxById[$id], $locale);
        }
    }
    unset($room);
}

$testNowInput = adminTestNowForInput(
    isset($config['test_now']) && is_string($config['test_now']) ? $config['test_now'] : null
);
$testNowQuery = adminTestNowQuery(
    isset($config['test_now']) && is_string($config['test_now']) ? $config['test_now'] : null
);

$pageTitle = 'Dashboard';
$adminSidebar = true;

ob_start();
require __DIR__ . '/../templates/admin/sections.php';
$content = (string) ob_get_clean();

require __DIR__ . '/../templates/admin/layout.php';
