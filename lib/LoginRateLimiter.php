<?php

declare(strict_types=1);

final class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 900;

    private string $storePath;

    public function __construct(?string $rootDir = null)
    {
        $root = $rootDir ?? dirname(__DIR__);
        $this->storePath = $root . '/data/admin/login-rates.json';
    }

    public function isLocked(string $ip): bool
    {
        return $this->lockUntil($ip) > time();
    }

    public function lockRemainingSeconds(string $ip): int
    {
        return max(0, $this->lockUntil($ip) - time());
    }

    public function recordFailure(string $ip): void
    {
        $data = $this->read();
        $entry = $data[$ip] ?? ['attempts' => 0, 'lock_until' => 0];
        $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            $entry['lock_until'] = time() + self::LOCK_SECONDS;
            $entry['attempts'] = 0;
        }

        $data[$ip] = $entry;
        $this->write($data);
    }

    public function clear(string $ip): void
    {
        $data = $this->read();
        unset($data[$ip]);
        $this->write($data);
    }

    private function lockUntil(string $ip): int
    {
        $data = $this->read();

        return (int) ($data[$ip]['lock_until'] ?? 0);
    }

    /** @return array<string, array{attempts: int, lock_until: int}> */
    private function read(): array
    {
        if (!is_file($this->storePath)) {
            return [];
        }

        $body = @file_get_contents($this->storePath);
        if ($body === false) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, array{attempts: int, lock_until: int}> $data */
    private function write(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        try {
            Security::writePrivateFile($this->storePath, $json . "\n");
        } catch (RuntimeException) {
            if (!is_dir(dirname($this->storePath))) {
                @mkdir(dirname($this->storePath), 0755, true);
            }
            @file_put_contents($this->storePath, $json . "\n", LOCK_EX);
        }
    }
}
