<?php

declare(strict_types=1);

final class Security
{
    /** @var list<string> */
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
    ];

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' https: data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'"
        );
    }

    public static function clientIp(): string
    {
        $candidates = [
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        ];

        foreach ($candidates as $raw) {
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            $ip = trim(explode(',', $raw)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * HTTPS URL validation. Set $resolveDns true when saving admin settings (SSRF guard).
     */
    public static function validateHttpsUrl(string $url, string $field, bool $resolveDns = false): string
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException($field . ' must be a valid URL.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new InvalidArgumentException($field . ' must use https.');
        }

        if ($host === '' || in_array($host, self::BLOCKED_HOSTNAMES, true)) {
            throw new InvalidArgumentException($field . ' uses a blocked host.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (!self::isPublicIp($host)) {
                throw new InvalidArgumentException($field . ' must not use a private or reserved IP address.');
            }

            return $url;
        }

        if ($resolveDns && !self::hostnameResolvesToPublicIps($host)) {
            throw new InvalidArgumentException($field . ' host could not be verified as a public address.');
        }

        return $url;
    }

    public static function writePrivateFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        self::ensurePrivateDir($dir);

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write ' . basename($path));
        }

        @chmod($path, 0600);
    }

    public static function ensurePrivateDir(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0700, true)) {
                @mkdir($dir, 0755, true);
            }
        }
        @chmod($dir, 0700);
    }

    private static function hostnameResolvesToPublicIps(string $host): bool
    {
        $dnsType = defined('DNS_AAAA') ? DNS_A + DNS_AAAA : DNS_A;
        $records = @dns_get_record($host, $dnsType);
        if (!is_array($records) || $records === []) {
            $fallback = @gethostbyname($host);

            return is_string($fallback) && $fallback !== $host && self::isPublicIp($fallback);
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && !self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
