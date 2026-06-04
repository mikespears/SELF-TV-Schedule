<?php

declare(strict_types=1);

/**
 * Legacy single-password admin auth (deprecated).
 *
 * On first login, this file is migrated automatically to data/admin/users.json
 * with username "admin" and your existing password hash.
 *
 * Prefer the setup screen at admin/login.php or data/admin/users.example.json.
 */
return [
    'password_hash' => '',
];
