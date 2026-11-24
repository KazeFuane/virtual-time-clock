-- =============================================================
-- Migration: time corrections
-- Lets managers/admins correct an agent's clock times for a day
-- (e.g. the agent forgot to clock in), with an audit trail.
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_24_time_corrections.sql
-- (The app also creates the table on first use if this was not run.)
-- =============================================================
CREATE TABLE IF NOT EXISTS `time_corrections` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL                 COMMENT 'Agent whose times were corrected',
    `work_date`   DATE         NOT NULL,
    `edited_by`   INT UNSIGNED NULL                     COMMENT 'Manager/admin who made the correction',
    `reason`      TEXT         NOT NULL,
    `before_json` TEXT         NOT NULL                 COMMENT 'Day and clock actions before the change',
    `after_json`  TEXT         NOT NULL                 COMMENT 'Day and clock actions after the change',
    `report_id`   INT UNSIGNED NULL                     COMMENT 'time_error_reports row resolved by this correction',
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_corrections_user_date` (`user_id`, `work_date`),
    INDEX `idx_corrections_created`   (`created_at`),

    CONSTRAINT `fk_corrections_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_corrections_editor`
        FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
