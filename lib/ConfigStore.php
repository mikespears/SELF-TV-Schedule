<?php

declare(strict_types=1);

final class ConfigStore
{
    private string $rootDir;
    private string $settingsPath;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__);
        $this->settingsPath = $this->rootDir . '/data/settings.json';
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $overrides = $this->readOverrides();
        $config = array_replace_recursive($this->loadDefaults(), $overrides);

        if (array_key_exists('gold_sponsors', $overrides) && !array_key_exists('sponsors', $overrides)) {
            $config['sponsors'] = $overrides['gold_sponsors'];
        }

        return $this->applyDerivedFields($config);
    }

    /** @return array<string, mixed> */
    public function loadDefaults(): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = require $this->rootDir . '/config.php';

        return $defaults;
    }

    /** @return array<string, mixed> */
    public function readOverrides(): array
    {
        if ($this->usesDatabase()) {
            return $this->readOverridesFromDatabase();
        }

        return $this->readOverridesFromFile();
    }

    /** @param array<string, mixed> $patch */
    public function mergeAndSave(array $patch): void
    {
        $current = $this->readOverrides();
        $validated = $this->validateOverrides($patch);
        $replaceKeys = ['rooms', 'sponsors', 'gold_sponsors', 'event_logo', 'messages', 'wifi'];

        foreach ($replaceKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $current[$key] = $validated[$key];
                unset($validated[$key]);
            }
        }

        $merged = array_replace_recursive($current, $validated);

        if ($this->usesDatabase()) {
            $this->writeOverridesToDatabase($merged);

            return;
        }

        $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode settings (invalid data).');
        }

        try {
            Security::writePrivateFile($this->settingsPath, $json . "\n");
        } catch (RuntimeException $exception) {
            if (!is_dir(dirname($this->settingsPath))) {
                mkdir(dirname($this->settingsPath), 0755, true);
            }
            if (file_put_contents($this->settingsPath, $json . "\n", LOCK_EX) === false) {
                throw $exception;
            }
        }
    }

    public function clearCache(): bool
    {
        $path = $this->rootDir . '/cache/slots.json';

        if (!is_file($path)) {
            return true;
        }

        return @unlink($path);
    }

    public function getSettingsPath(): string
    {
        return $this->usesDatabase() ? 'mysql://app_settings' : $this->settingsPath;
    }

    public function getStorageBackend(): string
    {
        return $this->usesDatabase() ? 'database' : 'file';
    }

    public function hasOverrides(): bool
    {
        if ($this->usesDatabase()) {
            return $this->readOverridesFromDatabase() !== [];
        }

        return is_file($this->settingsPath);
    }

    /** @return array<string, mixed> */
    public function getCacheInfo(): array
    {
        $path = $this->rootDir . '/cache/slots.json';

        if (!is_file($path)) {
            return ['exists' => false, 'mtime' => null, 'slot_count' => 0];
        }

        $body = @file_get_contents($path);
        $count = 0;

        if ($body !== false) {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $count = is_array($decoded) ? count($decoded) : 0;
            } catch (JsonException) {
                $count = 0;
            }
        }

        return [
            'exists' => true,
            'mtime' => (int) filemtime($path),
            'slot_count' => $count,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function applyDerivedFields(array $config): array
    {
        $host = rtrim((string) ($config['pretalx_host'] ?? ''), '/');
        if ($host !== '') {
            try {
                $host = rtrim(Security::validateHttpsUrl($host, 'pretalx_host', resolveDns: false), '/');
            } catch (InvalidArgumentException $exception) {
                error_log('SELF Schedule Display: invalid pretalx_host in config — ' . $exception->getMessage());
                $defaults = $this->loadDefaults();
                $host = rtrim((string) ($defaults['pretalx_host'] ?? ''), '/');
            }
        }
        $slug = trim((string) ($config['event_slug'] ?? ''));
        $config['pretalx_host'] = $host;
        $config['event_slug'] = $slug;
        $config['api_base'] = $host . '/api/events/' . $slug;
        $config['cache_dir'] = $this->rootDir . '/cache';
        $config['sponsors'] = sponsorsFromConfig($config);
        unset($config['gold_sponsors']);

        return $config;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validateOverrides(array $input): array
    {
        $out = [];

        if (array_key_exists('event_title', $input)) {
            $out['event_title'] = $this->requireNonEmptyString($input['event_title'], 'event_title');
        }

        if (array_key_exists('pretalx_host', $input)) {
            $out['pretalx_host'] = rtrim($this->requireUrl($input['pretalx_host'], 'pretalx_host'), '/');
        }

        if (array_key_exists('event_slug', $input)) {
            $slug = trim($this->requireNonEmptyString($input['event_slug'], 'event_slug'));
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                throw new InvalidArgumentException('event_slug must contain only lowercase letters, numbers, and hyphens.');
            }
            $out['event_slug'] = $slug;
        }

        if (array_key_exists('timezone', $input)) {
            $tz = (string) $input['timezone'];
            new DateTimeZone($tz);
            $out['timezone'] = $tz;
        }

        if (array_key_exists('refresh_seconds', $input)) {
            $out['refresh_seconds'] = $this->requirePositiveInt($input['refresh_seconds'], 'refresh_seconds');
        }

        if (array_key_exists('cache_ttl_seconds', $input)) {
            $out['cache_ttl_seconds'] = $this->requirePositiveInt($input['cache_ttl_seconds'], 'cache_ttl_seconds');
        }

        if (array_key_exists('allow_test_clock', $input)) {
            $out['allow_test_clock'] = filter_var($input['allow_test_clock'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('show_speaker_avatars', $input)) {
            $out['show_speaker_avatars'] = filter_var($input['show_speaker_avatars'], FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('test_now', $input)) {
            $out['test_now'] = $this->validateTestNow($input['test_now']);
        }

        if (array_key_exists('event_logo', $input) && is_array($input['event_logo'])) {
            $out['event_logo'] = $this->validateEventLogo($input['event_logo']);
        }

        if (array_key_exists('sponsors', $input) && is_array($input['sponsors'])) {
            $roomSlugs = $this->roomSlugsFromInput($input);
            $out['sponsors'] = $this->validateSponsors($input['sponsors'], $roomSlugs);
        } elseif (array_key_exists('gold_sponsors', $input) && is_array($input['gold_sponsors'])) {
            $roomSlugs = $this->roomSlugsFromInput($input);
            $out['sponsors'] = $this->validateSponsors($input['gold_sponsors'], $roomSlugs);
        }

        if (array_key_exists('rooms', $input) && is_array($input['rooms'])) {
            $out['rooms'] = $this->validateRooms($input['rooms']);
        }

        if (array_key_exists('messages', $input) && is_array($input['messages'])) {
            $roomSlugs = $this->roomSlugsFromInput($input);
            $out['messages'] = $this->validateMessages($input['messages'], $roomSlugs);
        }

        if (array_key_exists('wifi', $input) && is_array($input['wifi'])) {
            $roomSlugs = $this->roomSlugsFromInput($input);
            $out['wifi'] = $this->validateWifi($input['wifi'], $roomSlugs);
        }

        return $out;
    }

    /** @param array<string, mixed> $logo */
    private function validateEventLogo(array $logo): array
    {
        return [
            'src' => $this->requireUrl($logo['src'] ?? '', 'event_logo.src'),
            'alt' => $this->requireNonEmptyString($logo['alt'] ?? 'Event', 'event_logo.alt'),
            'url' => isset($logo['url']) && $logo['url'] !== ''
                ? $this->requireUrl($logo['url'], 'event_logo.url')
                : '',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function roomSlugsFromInput(array $input): array
    {
        $slugs = [];
        $rooms = $input['rooms'] ?? [];
        if (!is_array($rooms)) {
            return $slugs;
        }

        foreach ($rooms as $key => $room) {
            if (is_string($key) && preg_match('/^[a-z0-9-]+$/', $key) === 1) {
                $slugs[] = $key;
                continue;
            }
            if (is_array($room) && isset($room['slug'])) {
                $slug = trim((string) $room['slug']);
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @param array<int, mixed> $sponsors
     * @param list<string> $knownRoomSlugs
     * @return list<array{name: string, logo: string, url?: string, rooms: 'all'|list<string>}>
     */
    private function validateSponsors(array $sponsors, array $knownRoomSlugs = []): array
    {
        $out = [];

        foreach ($sponsors as $sponsor) {
            if (!is_array($sponsor)) {
                continue;
            }
            $name = trim((string) ($sponsor['name'] ?? ''));
            $logo = trim((string) ($sponsor['logo'] ?? ''));
            if ($name === '' && $logo === '') {
                continue;
            }
            if ($name === '' || $logo === '') {
                throw new InvalidArgumentException('Each sponsor must have a name and logo URL.');
            }
            $entry = [
                'name' => $name,
                'logo' => $this->requireUrl($logo, 'sponsor logo'),
                'rooms' => $this->validateRoomScope($sponsor['rooms'] ?? 'all', $knownRoomSlugs, 'sponsor rooms'),
            ];
            $url = trim((string) ($sponsor['url'] ?? ''));
            if ($url !== '') {
                $entry['url'] = $this->requireUrl($url, 'sponsor url');
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param list<string> $knownRoomSlugs
     * @return 'all'|list<string>
     */
    private function validateRoomScope(mixed $rooms, array $knownRoomSlugs, string $fieldLabel): string|array
    {
        if ($rooms === 'all' || $rooms === '*' || $rooms === null || $rooms === '') {
            return 'all';
        }

        if (!is_array($rooms)) {
            throw new InvalidArgumentException($fieldLabel . ' must be "all" or a list of room slugs.');
        }

        $slugs = [];
        foreach ($rooms as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') {
                continue;
            }
            if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1) {
                throw new InvalidArgumentException('Invalid room slug in ' . $fieldLabel . '.');
            }
            if ($knownRoomSlugs !== [] && !in_array($slug, $knownRoomSlugs, true)) {
                throw new InvalidArgumentException('Unknown room slug: ' . $slug);
            }
            $slugs[] = $slug;
        }

        $slugs = array_values(array_unique($slugs));
        if ($slugs === []) {
            throw new InvalidArgumentException('Select at least one room or choose All rooms.');
        }

        return $slugs;
    }

    /**
     * @param array<int, mixed> $messages
     * @param list<string> $knownRoomSlugs
     * @return list<array{title: string, body: string, placement: string, rooms: 'all'|list<string>, enabled: bool}>
     */
    private function validateMessages(array $messages, array $knownRoomSlugs = []): array
    {
        $out = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $title = trim((string) ($message['title'] ?? ''));
            $body = trim((string) ($message['body'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }

            $placement = (string) ($message['placement'] ?? 'below');
            if ($placement !== 'override' && $placement !== 'below') {
                throw new InvalidArgumentException('Message placement must be "override" or "below".');
            }

            $out[] = [
                'title' => $title,
                'body' => $body,
                'placement' => $placement,
                'rooms' => $this->validateRoomScope($message['rooms'] ?? 'all', $knownRoomSlugs, 'message rooms'),
                'enabled' => !empty($message['enabled']),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $wifi
     * @param list<string> $knownRoomSlugs
     * @return array<string, mixed>
     */
    private function validateWifi(array $wifi, array $knownRoomSlugs = []): array
    {
        $enabled = !empty($wifi['enabled']);
        $ssid = trim((string) ($wifi['ssid'] ?? ''));
        $security = strtoupper(trim((string) ($wifi['security'] ?? 'WPA')));
        if (!in_array($security, ['WPA', 'WEP', 'NOPASS'], true)) {
            throw new InvalidArgumentException('WiFi security must be WPA, WEP, or nopass.');
        }

        $placement = (string) ($wifi['placement'] ?? 'below');
        if ($placement !== 'override' && $placement !== 'below') {
            throw new InvalidArgumentException('WiFi placement must be "override" or "below".');
        }

        if ($enabled && $ssid === '') {
            throw new InvalidArgumentException('WiFi SSID is required when WiFi is enabled.');
        }

        $label = trim((string) ($wifi['label'] ?? ''));
        if ($label === '') {
            $label = 'WiFi';
        }

        return [
            'enabled' => $enabled,
            'ssid' => $ssid,
            'password' => (string) ($wifi['password'] ?? ''),
            'security' => $security,
            'hidden' => !empty($wifi['hidden']),
            'rooms' => $this->validateRoomScope($wifi['rooms'] ?? 'all', $knownRoomSlugs, 'WiFi rooms'),
            'placement' => $placement,
            'label' => $label,
            'show_password' => !empty($wifi['show_password']),
        ];
    }

    /**
     * @param array<int|string, mixed> $rooms
     * @return array<string, array{id: int, label: string, subtitle: string}>
     */
    private function validateRooms(array $rooms): array
    {
        $out = [];

        foreach ($rooms as $key => $room) {
            if (!is_array($room)) {
                continue;
            }

            $slug = trim((string) ($room['slug'] ?? ''));
            if ($slug === '' && is_string($key) && preg_match('/^[a-z0-9-]+$/', $key) === 1) {
                $slug = $key;
            }
            if ($slug === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                throw new InvalidArgumentException('Room slug must contain only lowercase letters, numbers, and hyphens.');
            }
            $out[$slug] = [
                'id' => $this->requirePositiveInt($room['id'] ?? 0, 'room id'),
                'label' => $this->requireNonEmptyString($room['label'] ?? '', 'room label'),
                'subtitle' => trim((string) ($room['subtitle'] ?? '')),
            ];
        }

        if ($out === []) {
            throw new InvalidArgumentException('At least one room is required.');
        }

        return $out;
    }

    private function validateTestNow(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= 'T12:00:00';
        }

        $raw = str_replace('T', ' ', $raw);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $raw)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);

        if ($dt === false) {
            throw new InvalidArgumentException('Invalid test date/time.');
        }

        return $dt->format('Y-m-d H:i:s');
    }

    private function requireNonEmptyString(mixed $value, string $field): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            throw new InvalidArgumentException($field . ' is required.');
        }

        return $text;
    }

    private function requirePositiveInt(mixed $value, string $field): int
    {
        if (!is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException($field . ' must be a positive integer.');
        }

        return (int) $value;
    }

    private function requireUrl(mixed $value, string $field): string
    {
        return Security::validateHttpsUrl(trim((string) $value), $field, resolveDns: true);
    }

    private function usesDatabase(): bool
    {
        return Database::isConfigured($this->rootDir);
    }

    /** @return array<string, mixed> */
    private function readOverridesFromFile(): array
    {
        if (!is_file($this->settingsPath)) {
            return [];
        }

        $body = @file_get_contents($this->settingsPath);
        if ($body === false) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: invalid settings.json');

            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $this->sanitizeOverrides($decoded);
    }

    /** @return array<string, mixed> */
    private function readOverridesFromDatabase(): array
    {
        try {
            $pdo = Database::connection($this->rootDir);
        } catch (Throwable $exception) {
            error_log('SELF Schedule Display: database settings read failed — ' . $exception->getMessage());

            return [];
        }

        $raw = $pdo->query('SELECT settings_json FROM app_settings WHERE id = 1')->fetchColumn();
        if (!is_string($raw) || $raw === '' || $raw === '{}' || $raw === '[]') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: invalid app_settings.settings_json');

            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $this->sanitizeOverrides($decoded);
    }

    /** @param array<string, mixed> $overrides */
    private function writeOverridesToDatabase(array $overrides): void
    {
        $json = json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $pdo = Database::connection($this->rootDir);
        $stmt = $pdo->prepare('UPDATE app_settings SET settings_json = :settings_json WHERE id = 1');
        $stmt->execute(['settings_json' => $json]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function sanitizeOverrides(array $overrides): array
    {
        try {
            if (isset($overrides['pretalx_host']) && is_string($overrides['pretalx_host'])) {
                $overrides['pretalx_host'] = rtrim(
                    Security::validateHttpsUrl($overrides['pretalx_host'], 'pretalx_host', resolveDns: false),
                    '/'
                );
            }
        } catch (InvalidArgumentException) {
            unset($overrides['pretalx_host']);
        }

        if (isset($overrides['event_logo']) && is_array($overrides['event_logo'])) {
            $overrides['event_logo'] = $this->sanitizeEventLogoOverrides($overrides['event_logo']);
        }

        if (isset($overrides['sponsors']) && is_array($overrides['sponsors'])) {
            $overrides['sponsors'] = $this->sanitizeSponsorOverrides($overrides['sponsors']);
        } elseif (isset($overrides['gold_sponsors']) && is_array($overrides['gold_sponsors'])) {
            $overrides['gold_sponsors'] = $this->sanitizeSponsorOverrides($overrides['gold_sponsors']);
        }

        return $overrides;
    }

    /** @param array<string, mixed> $logo */
    private function sanitizeEventLogoOverrides(array $logo): array
    {
        foreach (['src' => 'event_logo.src', 'url' => 'event_logo.url'] as $key => $label) {
            if (!isset($logo[$key]) || $logo[$key] === '') {
                continue;
            }
            try {
                $logo[$key] = Security::validateHttpsUrl((string) $logo[$key], $label, resolveDns: false);
            } catch (InvalidArgumentException) {
                unset($logo[$key]);
            }
        }

        return $logo;
    }

    /** @param array<int, mixed> $sponsors */
    private function sanitizeSponsorOverrides(array $sponsors): array
    {
        $out = [];
        foreach ($sponsors as $sponsor) {
            if (!is_array($sponsor)) {
                continue;
            }
            try {
                if (isset($sponsor['logo'])) {
                    $sponsor['logo'] = Security::validateHttpsUrl((string) $sponsor['logo'], 'sponsor logo', resolveDns: false);
                }
                if (isset($sponsor['url']) && $sponsor['url'] !== '') {
                    $sponsor['url'] = Security::validateHttpsUrl((string) $sponsor['url'], 'sponsor url', resolveDns: false);
                }
                $out[] = $sponsor;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $out;
    }
}
