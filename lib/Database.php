<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;
    private static ?bool $configured = null;

    public static function isConfigured(?string $rootDir = null): bool
    {
        if (self::$configured !== null) {
            return self::$configured;
        }

        self::$configured = self::loadConfig($rootDir) !== null;

        return self::$configured;
    }

    public static function connection(?string $rootDir = null): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::loadConfig($rootDir);
        if ($config === null) {
            throw new RuntimeException('Database is not configured.');
        }

        $host = (string) $config['host'];
        $port = (int) ($config['port'] ?? 3306);
        $database = (string) $config['database'];
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        self::$connection = new PDO(
            $dsn,
            (string) $config['username'],
            (string) $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        self::ensureSchema(self::$connection);
        self::migrateFromFilesIfNeeded(self::$connection, $rootDir ?? dirname(__DIR__));

        return self::$connection;
    }

    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_settings (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                settings_json JSON NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT app_settings_singleton CHECK (id = 1)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            "INSERT IGNORE INTO app_settings (id, settings_json) VALUES (1, JSON_OBJECT())"
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_users (
                id CHAR(32) NOT NULL PRIMARY KEY,
                username VARCHAR(32) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME(6) NOT NULL,
                disabled TINYINT(1) NOT NULL DEFAULT 0,
                auth_version INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY admin_users_username_unique (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, mixed>|null */
    private static function loadConfig(?string $rootDir): ?array
    {
        $root = $rootDir ?? dirname(__DIR__);
        $path = $root . '/data/database.php';

        if (!is_file($path)) {
            return null;
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('data/database.php must return an array.');
        }

        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (!isset($config[$key]) || trim((string) $config[$key]) === '') {
                throw new RuntimeException('Database config is missing "' . $key . '".');
            }
        }

        return $config;
    }

    private static function migrateFromFilesIfNeeded(PDO $pdo, string $rootDir): void
    {
        $usersPath = $rootDir . '/data/admin/users.json';
        $settingsPath = $rootDir . '/data/settings.json';

        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($userCount === 0 && is_file($usersPath)) {
            self::importUsersFromFile($pdo, $usersPath);
        }

        $settings = $pdo->query('SELECT settings_json FROM app_settings WHERE id = 1')->fetchColumn();
        $settingsEmpty = !is_string($settings) || $settings === '' || $settings === '{}' || $settings === '[]';
        if ($settingsEmpty && is_file($settingsPath)) {
            self::importSettingsFromFile($pdo, $settingsPath);
        }
    }

    private static function importUsersFromFile(PDO $pdo, string $path): void
    {
        $body = @file_get_contents($path);
        if ($body === false) {
            return;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: could not import users.json into database');

            return;
        }

        if (!is_array($decoded) || !isset($decoded['users']) || !is_array($decoded['users'])) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (id, username, password_hash, created_at, disabled, auth_version)
             VALUES (:id, :username, :password_hash, :created_at, :disabled, :auth_version)'
        );

        foreach ($decoded['users'] as $user) {
            if (!is_array($user)) {
                continue;
            }

            $id = trim((string) ($user['id'] ?? ''));
            $username = strtolower(trim((string) ($user['username'] ?? '')));
            $hash = trim((string) ($user['password_hash'] ?? ''));
            if ($id === '' || $username === '' || $hash === '') {
                continue;
            }

            $createdAt = trim((string) ($user['created_at'] ?? ''));
            if ($createdAt === '') {
                $createdAt = gmdate('Y-m-d H:i:s');
            } else {
                try {
                    $createdAt = (new DateTimeImmutable($createdAt))->format('Y-m-d H:i:s.u');
                } catch (Exception) {
                    $createdAt = gmdate('Y-m-d H:i:s');
                }
            }

            $stmt->execute([
                'id' => $id,
                'username' => $username,
                'password_hash' => $hash,
                'created_at' => $createdAt,
                'disabled' => !empty($user['disabled']) ? 1 : 0,
                'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
            ]);
        }

        error_log('SELF Schedule Display: imported admin users from users.json into database');
    }

    private static function importSettingsFromFile(PDO $pdo, string $path): void
    {
        $body = @file_get_contents($path);
        if ($body === false) {
            return;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            error_log('SELF Schedule Display: could not import settings.json into database');

            return;
        }

        if (!is_array($decoded) || $decoded === []) {
            return;
        }

        $json = json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare('UPDATE app_settings SET settings_json = :settings_json WHERE id = 1');
        $stmt->execute(['settings_json' => $json]);

        error_log('SELF Schedule Display: imported settings from settings.json into database');
    }
}
