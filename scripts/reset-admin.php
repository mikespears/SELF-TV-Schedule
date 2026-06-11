<?php

declare(strict_types=1);

/**
 * CLI: create or reset an admin user (local / recovery use).
 *
 * Usage:
 *   php scripts/reset-admin.php <password> [username]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/lib/helpers.php';
require $root . '/lib/Security.php';
require $root . '/lib/Database.php';
require $root . '/lib/AdminUserStore.php';

$password = (string) ($argv[1] ?? '');
$username = (string) ($argv[2] ?? 'admin');

if (strlen($password) < 10) {
    fwrite(STDERR, "Usage: php scripts/reset-admin.php <password> [username]\n");
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$store = new AdminUserStore($root);
$user = $store->findByUsername($username);

if ($user === null) {
    $store->createUser($username, $password);
    fwrite(STDOUT, "Created admin user \"{$username}\".\n");
} else {
    $store->updatePassword($user['id'], $password);
    fwrite(STDOUT, "Password updated for \"{$username}\".\n");
}

fwrite(STDOUT, "Sign in at /admin/login.php\n");
