-- =============================================================
-- Migration: time error reports
-- Lets an employee report a time error to managers/admins,
-- and lets them mark it resolved.
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_22_time_error_reports.sql
-- =============================================================
CREATE TABLE IF NOT EXISTS `time_error_reports` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL                 COMMENT 'Employee who reported',
    `work_date`       DATE         NOT NULL,
    `error_code`      VARCHAR(40)  NULL                     COMMENT 'TimeErrorService code, NULL = other problem',
    `message`         TEXT         NOT NULL                 COMMENT 'What the employee says happened',
    `status`          ENUM('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN',
    `resolved_by`     INT UNSIGNED NULL,
    `resolution_note` TEXT         NULL,
    `resolved_at`     TIMESTAMP    NULL,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_reports_user`   (`user_id`),
    INDEX `idx_reports_status` (`status`),

    CONSTRAINT `fk_reports_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reports_resolver`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
