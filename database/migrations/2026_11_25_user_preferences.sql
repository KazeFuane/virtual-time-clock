-- =============================================================
-- Migration: per-user preferences (key/value)
-- Used for the light / dark mode choice (pref_key = 'theme') and the
-- display language (pref_key = 'language').
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_25_user_preferences.sql
-- (The app also creates the table on first use if this was not run.)
-- =============================================================
CREATE TABLE IF NOT EXISTS `user_preferences` (
    `user_id`    INT UNSIGNED NOT NULL,
    `pref_key`   VARCHAR(50)  NOT NULL                 COMMENT 'e.g. theme, language',
    `pref_value` VARCHAR(255) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `pref_key`),
    CONSTRAINT `fk_user_prefs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
