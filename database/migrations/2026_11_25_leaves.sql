-- =============================================================
-- Migration: leave / paid time off
-- Managers/admins record leave for an agent (full day, morning or
-- afternoon shift, or a date range). Days on leave are shown as
-- leave in the time errors report instead of an absence.
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_25_leaves.sql
-- (The app also creates the table on first use if this was not run.)
-- =============================================================
CREATE TABLE IF NOT EXISTS `leaves` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED NOT NULL                 COMMENT 'Agent on leave',
    `leave_type`    VARCHAR(20)  NOT NULL                 COMMENT 'LeaveService::TYPES key (PTO, SICK, SPECIAL, UNPAID)',
    `start_date`    DATE         NOT NULL,
    `end_date`      DATE         NOT NULL,
    `day_part`      ENUM('FULL','SHIFT_1','SHIFT_2') NOT NULL DEFAULT 'FULL' COMMENT 'Whole day, or one shift of the agent''s schedule',
    `note`          TEXT         NULL,
    `created_by`    INT UNSIGNED NULL                     COMMENT 'Manager/admin who recorded it',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `cancelled_by`  INT UNSIGNED NULL,
    `cancelled_at`  TIMESTAMP    NULL,
    `cancel_reason` TEXT         NULL,

    INDEX `idx_leaves_user_dates` (`user_id`, `start_date`, `end_date`),
    INDEX `idx_leaves_dates`      (`start_date`, `end_date`),

    CONSTRAINT `fk_leaves_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leaves_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leaves_canceller`
        FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
