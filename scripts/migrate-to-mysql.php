<?php

declare(strict_types=1);

/**
 * CLI: import file-based admin users and settings into MySQL.
 *
 * Usage: php scripts/migrate-to-mysql.php [--force]
 *
 * Imports data/admin/users.json and data/settings.json when the database
 * tables are empty. Existing database rows are left unchanged unless --force
 * is passed (settings only; users are never overwritten).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/lib/Database.php';

if (!Database::isConfigured($root)) {
    fwrite(STDERR, "Copy data/database.example.php to data/database.php and set credentials first.\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);

try {
    $pdo = Database::connection($root);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database connection failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

$usersPath = $root . '/data/admin/users.json';
$settingsPath = $root . '/data/settings.json';
$imported = 0;

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($userCount === 0 && is_file($usersPath)) {
    $body = file_get_contents($usersPath);
    if ($body !== false) {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && isset($decoded['users']) && is_array($decoded['users'])) {
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
                try {
                    $createdAt = $createdAt !== ''
                        ? (new DateTimeImmutable($createdAt))->format('Y-m-d H:i:s.u')
                        : gmdate('Y-m-d H:i:s');
                } catch (Exception) {
                    $createdAt = gmdate('Y-m-d H:i:s');
                }
                $stmt->execute([
                    'id' => $id,
                    'username' => $username,
                    'password_hash' => $hash,
                    'created_at' => $createdAt,
                    'disabled' => !empty($user['disabled']) ? 1 : 0,
                    'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
                ]);
                $imported++;
            }
            fwrite(STDOUT, "Imported {$imported} admin user(s) from users.json.\n");
        }
    }
} elseif ($userCount > 0) {
    fwrite(STDOUT, "Skipped users: database already has {$userCount} user(s).\n");
}

$settingsRaw = $pdo->query('SELECT settings_json FROM app_settings WHERE id = 1')->fetchColumn();
$settingsEmpty = !is_string($settingsRaw) || $settingsRaw === '' || $settingsRaw === '{}' || $settingsRaw === '[]';
if (is_file($settingsPath) && ($settingsEmpty || $force)) {
    $body = file_get_contents($settingsPath);
    if ($body !== false) {
        $decoded = json_decode($body, true);
        if (is_array($decoded) && $decoded !== []) {
            $json = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $stmt = $pdo->prepare('UPDATE app_settings SET settings_json = :settings_json WHERE id = 1');
            $stmt->execute(['settings_json' => $json]);
            fwrite(STDOUT, "Imported settings from settings.json" . ($force ? ' (forced)' : '') . ".\n");
        }
    }
} elseif (!$settingsEmpty) {
    fwrite(STDOUT, "Skipped settings: database already has saved settings.\n");
}

fwrite(STDOUT, "Migration complete.\n");
