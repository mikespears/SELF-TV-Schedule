<?php

declare(strict_types=1);

/**
 * CLI: create database tables (idempotent).
 *
 * Usage: php scripts/setup-database.php
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

try {
    Database::ensureSchema(Database::connection($root));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database setup failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Database schema is ready.\n");
