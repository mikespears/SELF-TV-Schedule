CREATE TABLE IF NOT EXISTS app_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    settings_json JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT app_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (id, settings_json) VALUES (1, JSON_OBJECT());

CREATE TABLE IF NOT EXISTS admin_users (
    id CHAR(32) NOT NULL PRIMARY KEY,
    username VARCHAR(32) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    disabled TINYINT(1) NOT NULL DEFAULT 0,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY admin_users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
