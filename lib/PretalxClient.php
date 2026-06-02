<?php

declare(strict_types=1);

final class PretalxClient
{
    private string $apiBase;
    private string $apiHost;
    private string $cacheDir;
    private int $cacheTtl;

    private string $dataSource = 'none';
    private ?int $lastUpdated = null;
    private bool $loadFailed = false;
    private bool $fetchIncomplete = false;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->apiBase = rtrim((string) $config['api_base'], '/');
        $this->cacheDir = (string) $config['cache_dir'];
        $this->cacheTtl = (int) $config['cache_ttl_seconds'];
        $this->apiHost = $this->resolveApiHost($this->apiBase);
    }

    /** @return list<array<string, mixed>> */
    public function getSlots(): array
    {
        $this->resetStatus();
        $cacheFile = $this->cacheDir . '/slots.json';

        if ($this->isCacheFresh($cacheFile)) {
            $cached = $this->readSlotsFile($cacheFile);
            if ($cached !== null) {
                $this->dataSource = 'cache';
                $this->lastUpdated = (int) filemtime($cacheFile);

                return $cached;
            }
        }

        $result = $this->fetchAllSlots();

        if ($result['complete']) {
            $this->writeCache($cacheFile, $result['slots']);
            $this->dataSource = 'api';
            $this->lastUpdated = time();

            return $result['slots'];
        }

        $this->fetchIncomplete = true;
        error_log('SELF Schedule Display: pretalx pagination did not complete; not updating cache');

        $cached = $this->readSlotsFile($cacheFile);
        if ($cached !== null) {
            $this->dataSource = 'stale-cache';
            $this->lastUpdated = (int) filemtime($cacheFile);

            return $cached;
        }

        if ($result['slots'] !== []) {
            error_log('SELF Schedule Display: using incomplete slot fetch (' . count($result['slots']) . ' slots)');
            return $result['slots'];
        }

        $this->loadFailed = true;
        error_log('SELF Schedule Display: no schedule data available from API or cache');

        return [];
    }

    public function hasLoadError(): bool
    {
        return $this->loadFailed;
    }

    public function usedStaleCache(): bool
    {
        return $this->dataSource === 'stale-cache';
    }

    public function hadIncompleteFetch(): bool
    {
        return $this->fetchIncomplete;
    }

    public function getDataSource(): string
    {
        return $this->dataSource;
    }

    public function getLastUpdated(): ?int
    {
        return $this->lastUpdated;
    }

    /**
     * @return array{slots: list<array<string, mixed>>, complete: bool}
     */
    private function fetchAllSlots(): array
    {
        $slots = [];
        $url = $this->apiBase . '/slots/?expand=room,submission.speakers&page_size=50';
        $complete = true;

        while ($url !== null) {
            $payload = $this->httpGet($url);
            if ($payload === null) {
                error_log('SELF Schedule Display: HTTP request failed for ' . $url);
                $complete = false;
                break;
            }

            if (!isset($payload['results']) || !is_array($payload['results'])) {
                error_log('SELF Schedule Display: unexpected API response shape for ' . $url);
                $complete = false;
                break;
            }

            foreach ($payload['results'] as $slot) {
                if (is_array($slot)) {
                    $slots[] = $slot;
                }
            }

            $next = $payload['next'] ?? null;
            if (!is_string($next) || $next === '') {
                break;
            }

            if (!$this->isAllowedApiUrl($next)) {
                error_log('SELF Schedule Display: rejected pagination URL host: ' . $next);
                $complete = false;
                break;
            }

            $url = $next;
        }

        usort($slots, static function (array $a, array $b): int {
            return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
        });

        return ['slots' => $slots, 'complete' => $complete];
    }

    private function isAllowedApiUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, $this->apiHost) === 0;
    }

    private function resolveApiHost(string $apiBase): string
    {
        $host = parse_url($apiBase, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            throw new InvalidArgumentException('config api_base must include a valid host');
        }

        return $host;
    }

    /** @return array<string, mixed>|null */
    private function httpGet(string $url): ?array
    {
        if (!$this->isAllowedApiUrl($url)) {
            error_log('SELF Schedule Display: rejected request URL host: ' . $url);

            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: SELF-Schedule-Display/1.0\r\n",
                'timeout' => 30,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log('SELF Schedule Display: invalid JSON from ' . $url . ': ' . $exception->getMessage());

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function isCacheFresh(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        return (time() - (int) filemtime($path)) < $this->cacheTtl;
    }

    /** @return list<array<string, mixed>>|null */
    private function readSlotsFile(string $path): ?array
    {
        $body = @file_get_contents($path);
        if ($body === false) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: corrupt cache file ' . $path);

            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $slot) {
            if (!is_array($slot) || !isset($slot['start'])) {
                error_log('SELF Schedule Display: invalid slot entry in cache');

                return null;
            }
        }

        return $decoded;
    }

    /** @param list<array<string, mixed>> $data */
    private function writeCache(string $path, array $data): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }

    private function resetStatus(): void
    {
        $this->dataSource = 'none';
        $this->lastUpdated = null;
        $this->loadFailed = false;
        $this->fetchIncomplete = false;
    }
}
