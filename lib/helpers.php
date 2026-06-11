<?php

declare(strict_types=1);

/**
 * @param array<string, string>|string|null $value
 */
function localize(mixed $value, string $locale = 'en'): string
{
    if ($value === null) {
        return '';
    }

    if (is_string($value)) {
        return $value;
    }

    if (!is_array($value)) {
        return (string) $value;
    }

    if (isset($value[$locale]) && $value[$locale] !== '') {
        return (string) $value[$locale];
    }

    foreach ($value as $text) {
        if ($text !== '') {
            return (string) $text;
        }
    }

    return '';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, mixed> $config
 */
function resolveReferenceNow(array $config): DateTimeImmutable
{
    $tz = new DateTimeZone((string) $config['timezone']);
    $realNow = new DateTimeImmutable('now', $tz);

    if (empty($config['allow_test_clock'])) {
        return $realNow;
    }

    $raw = null;
    if (isset($_GET['now']) && is_string($_GET['now']) && $_GET['now'] !== '') {
        $raw = $_GET['now'];
    } elseif (isset($config['test_now']) && is_string($config['test_now']) && $config['test_now'] !== '') {
        $raw = $config['test_now'];
    }

    if ($raw === null) {
        return $realNow;
    }

    // Date only (YYYY-MM-DD) → noon on that day for predictable "during the conference" views.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        $raw .= 'T12:00:00';
    }

    try {
        return new DateTimeImmutable($raw, $tz);
    } catch (Exception) {
        return $realNow;
    }
}

/**
 * @param array<string, mixed> $config
 */
function isTestClockActive(array $config, DateTimeImmutable $referenceNow): bool
{
    if (empty($config['allow_test_clock'])) {
        return false;
    }

    $realNow = new DateTimeImmutable('now', new DateTimeZone((string) $config['timezone']));

    return $referenceNow->format('Y-m-d H:i:s') !== $realNow->format('Y-m-d H:i:s');
}

function formatLastUpdated(?int $timestamp, string $timezone): string
{
    if ($timestamp === null) {
        return '';
    }

    $updated = (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone($timezone));

    return $updated->format('g:i A');
}

/**
 * @param array{now: ?array<string, mixed>, up_next: ?array<string, mixed>, today: list<array<string, mixed>>} $view
 */
function scheduleIdleHeroMessage(array $view): string
{
    if ($view['today'] === []) {
        return 'No sessions in this room today.';
    }

    return 'No sessions in progress right now.';
}

function logScheduleException(Throwable $exception): void
{
    error_log('SELF Schedule Display: ' . $exception->getMessage());
}

/**
 * @param array<string, mixed> $config
 * @return list<array{name: string, logo: string, url?: string, rooms: 'all'|list<string>}>
 */
function sponsorsFromConfig(array $config): array
{
    $raw = $config['sponsors'] ?? $config['gold_sponsors'] ?? [];

    if (!is_array($raw)) {
        return [];
    }

    $out = [];

    foreach ($raw as $sponsor) {
        if (!is_array($sponsor)) {
            continue;
        }

        $name = trim((string) ($sponsor['name'] ?? ''));
        $logo = trim((string) ($sponsor['logo'] ?? ''));
        if ($name === '' || $logo === '') {
            continue;
        }

        $rooms = $sponsor['rooms'] ?? 'all';
        if ($rooms === 'all' || $rooms === '*' || $rooms === [] || $rooms === null) {
            $rooms = 'all';
        } elseif (is_array($rooms)) {
            $rooms = array_values(array_unique(array_filter(array_map(
                static fn ($slug): string => trim((string) $slug),
                $rooms
            ))));
            if ($rooms === []) {
                $rooms = 'all';
            }
        } else {
            $rooms = 'all';
        }

        $entry = [
            'name' => $name,
            'logo' => $logo,
            'rooms' => $rooms,
        ];

        $url = trim((string) ($sponsor['url'] ?? ''));
        if ($url !== '') {
            $entry['url'] = $url;
        }

        $out[] = $entry;
    }

    return $out;
}

/**
 * @param array<string, mixed> $room pretalx room API object
 */
function pretalxRoomDisplayName(array $room, string $locale = 'en'): string
{
    $name = localize($room['name'] ?? null, $locale);
    if ($name !== '') {
        return $name;
    }

    $id = (int) ($room['id'] ?? 0);

    return $id > 0 ? 'Room ' . $id : 'Room';
}

/**
 * @param list<array<string, mixed>> $pretalxRooms
 * @return array<int, array<string, mixed>>
 */
function pretalxRoomsById(array $pretalxRooms): array
{
    $byId = [];

    foreach ($pretalxRooms as $room) {
        if (!is_array($room)) {
            continue;
        }
        $id = (int) ($room['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $room;
        }
    }

    return $byId;
}

/**
 * @param list<array{slug: string, id: mixed, label: string, subtitle: string}> $rooms
 * @param array<int, array<string, mixed>> $pretalxById
 * @return list<array{slug: string, id: mixed, label: string, subtitle: string}>
 */
function applyPretalxRoomLabels(array $rooms, array $pretalxById, string $locale = 'en'): array
{
    foreach ($rooms as &$room) {
        $id = (int) ($room['id'] ?? 0);
        if ($id > 0 && isset($pretalxById[$id])) {
            $room['label'] = pretalxRoomDisplayName($pretalxById[$id], $locale);
        }
    }
    unset($room);

    return $rooms;
}

function suggestRoomSlug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($slug === '') {
        return 'room';
    }

    return substr($slug, 0, 48);
}

/**
 * @param list<string> $usedSlugs
 */
function uniqueRoomSlug(string $base, array $usedSlugs): string
{
    $slug = $base;
    $suffix = 2;

    while (in_array($slug, $usedSlugs, true)) {
        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

/**
 * @param array<string, mixed> $config
 * @return list<array{name: string, logo: string, url?: string}>
 */
function sponsorsForRoom(array $config, string $roomSlug): array
{
    $display = [];

    foreach (sponsorsFromConfig($config) as $sponsor) {
        $rooms = $sponsor['rooms'];
        if ($rooms !== 'all' && (!is_array($rooms) || !in_array($roomSlug, $rooms, true))) {
            continue;
        }

        $entry = [
            'name' => $sponsor['name'],
            'logo' => $sponsor['logo'],
        ];
        if (isset($sponsor['url'])) {
            $entry['url'] = $sponsor['url'];
        }
        $display[] = $entry;
    }

    return $display;
}

/**
 * @param 'all'|list<string>|mixed $rooms
 */
function itemAppliesToRoom(mixed $rooms, string $roomSlug): bool
{
    if ($rooms === 'all' || $rooms === '*' || $rooms === null || $rooms === '') {
        return true;
    }

    if (!is_array($rooms)) {
        return false;
    }

    if ($rooms === []) {
        return true;
    }

    return in_array($roomSlug, $rooms, true);
}

/**
 * @param array<string, mixed> $config
 * @return list<array{title: string, body: string, placement: string, rooms: 'all'|list<string>, enabled: bool}>
 */
function messagesFromConfig(array $config): array
{
    $raw = $config['messages'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $message) {
        if (!is_array($message)) {
            continue;
        }

        $body = trim((string) ($message['body'] ?? ''));
        $title = trim((string) ($message['title'] ?? ''));
        if ($body === '' && $title === '') {
            continue;
        }

        $placement = (string) ($message['placement'] ?? 'below');
        if ($placement !== 'override' && $placement !== 'below') {
            $placement = 'below';
        }

        $rooms = $message['rooms'] ?? 'all';
        if ($rooms === 'all' || $rooms === '*' || $rooms === [] || $rooms === null) {
            $rooms = 'all';
        } elseif (!is_array($rooms)) {
            $rooms = 'all';
        }

        $out[] = [
            'title' => $title,
            'body' => $body,
            'placement' => $placement,
            'rooms' => $rooms,
            'enabled' => !empty($message['enabled']),
        ];
    }

    return $out;
}

/**
 * @param array<string, mixed> $config
 * @return list<array{title: string, body: string, placement: string, rooms: 'all'|list<string>, enabled: bool}>
 */
function messagesForRoom(array $config, string $roomSlug, string $placement): array
{
    $messages = [];
    foreach (messagesFromConfig($config) as $message) {
        if (!$message['enabled'] || $message['placement'] !== $placement) {
            continue;
        }
        if (!itemAppliesToRoom($message['rooms'], $roomSlug)) {
            continue;
        }
        $messages[] = $message;
    }

    return $messages;
}

/**
 * @param array<string, mixed> $config
 * @return array{
 *     enabled: bool,
 *     ssid: string,
 *     password: string,
 *     security: string,
 *     hidden: bool,
 *     rooms: 'all'|list<string>,
 *     placement: string,
 *     label: string,
 *     show_password: bool
 * }|null
 */
function wifiFromConfig(array $config): ?array
{
    $wifi = $config['wifi'] ?? null;
    if (!is_array($wifi) || empty($wifi['enabled'])) {
        return null;
    }

    $ssid = trim((string) ($wifi['ssid'] ?? ''));
    if ($ssid === '') {
        return null;
    }

    $security = strtoupper(trim((string) ($wifi['security'] ?? 'WPA')));
    if (!in_array($security, ['WPA', 'WEP', 'NOPASS'], true)) {
        $security = 'WPA';
    }

    $rooms = $wifi['rooms'] ?? 'all';
    if ($rooms === 'all' || $rooms === '*' || $rooms === [] || $rooms === null) {
        $rooms = 'all';
    } elseif (!is_array($rooms)) {
        $rooms = 'all';
    }

    $placement = (string) ($wifi['placement'] ?? 'below');
    if ($placement !== 'override' && $placement !== 'below') {
        $placement = 'below';
    }

    $label = trim((string) ($wifi['label'] ?? ''));
    if ($label === '') {
        $label = 'WiFi';
    }

    return [
        'enabled' => true,
        'ssid' => $ssid,
        'password' => (string) ($wifi['password'] ?? ''),
        'security' => $security,
        'hidden' => !empty($wifi['hidden']),
        'rooms' => $rooms,
        'placement' => $placement,
        'label' => $label,
        'show_password' => !array_key_exists('show_password', $wifi) || !empty($wifi['show_password']),
    ];
}

/**
 * @param array<string, mixed> $config
 * @return array{
 *     enabled: bool,
 *     ssid: string,
 *     password: string,
 *     security: string,
 *     hidden: bool,
 *     rooms: 'all'|list<string>,
 *     placement: string,
 *     label: string,
 *     show_password: bool,
 *     qr_payload: string
 * }|null
 */
function wifiForRoom(array $config, string $roomSlug): ?array
{
    $wifi = wifiFromConfig($config);
    if ($wifi === null || !itemAppliesToRoom($wifi['rooms'], $roomSlug)) {
        return null;
    }

    $wifi['qr_payload'] = buildWifiQrPayload(
        $wifi['ssid'],
        $wifi['password'],
        $wifi['security'],
        $wifi['hidden']
    );

    return $wifi;
}

function escapeWifiField(string $value): string
{
    return str_replace(['\\', ';', ',', '"'], ['\\\\', '\;', '\,', '\"'], $value);
}

function buildWifiQrPayload(string $ssid, string $password, string $security, bool $hidden): string
{
    $security = strtoupper($security);
    if (!in_array($security, ['WPA', 'WEP', 'NOPASS'], true)) {
        $security = 'WPA';
    }

    $parts = ['WIFI:T:' . $security, 'S:' . escapeWifiField($ssid)];
    if ($security !== 'NOPASS' && $password !== '') {
        $parts[] = 'P:' . escapeWifiField($password);
    }
    if ($hidden) {
        $parts[] = 'H:true';
    }

    return implode(';', $parts) . ';;';
}
