-- =============================================================
-- Migration: agent schedules (shift planning)
-- Managers/admins create named weekly schedules, assign one to each
-- agent from a start date, and can change single days (extra shift,
-- different hours, day off). Agents without a schedule follow the
-- default schedule (08:00-12:00 and 14:00-18:00, Monday to Friday).
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_25_schedules.sql
-- (The app also creates the tables on first use if this was not run.)
-- =============================================================

-- Named weekly schedules. week_json maps ISO weekday (1 = Monday ... 7 = Sunday)
-- to a list of shifts: {"1":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"6":[]}
CREATE TABLE IF NOT EXISTS `schedule_templates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL,
    `week_json`   TEXT         NOT NULL                 COMMENT 'weekday => [[start, end], ...]',
    `is_default`  TINYINT(1)   NOT NULL DEFAULT 0       COMMENT 'Used for agents with no schedule assigned',
    `created_by`  INT UNSIGNED NULL,
    `updated_by`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_schedule_templates_name` (`name`),

    CONSTRAINT `fk_schedule_templates_creator`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_templates_updater`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which schedule an agent follows, from a date on. The row with the latest
-- start_date on or before a day applies to that day.
CREATE TABLE IF NOT EXISTS `schedule_assignments` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `template_id` INT UNSIGNED NOT NULL,
    `start_date`  DATE         NOT NULL,
    `assigned_by` INT UNSIGNED NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_schedule_assignments_user_date` (`user_id`, `start_date`),

    CONSTRAINT `fk_schedule_assignments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_assignments_template`
        FOREIGN KEY (`template_id`) REFERENCES `schedule_templates` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_assignments_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One day planned differently for one agent. shifts_json is a list of
-- [start, end]; an empty list [] means a day off.
CREATE TABLE IF NOT EXISTS `schedule_overrides` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL,
    `work_date`   DATE         NOT NULL,
    `shifts_json` TEXT         NOT NULL                 COMMENT '[[start, end], ...], [] = day off',
    `note`        VARCHAR(255) NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_schedule_overrides_user_date` (`user_id`, `work_date`),
    INDEX      `idx_schedule_overrides_date`     (`work_date`),

    CONSTRAINT `fk_schedule_overrides_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_schedule_overrides_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The schedule everyone had before, used for agents with no schedule assigned
INSERT IGNORE INTO `schedule_templates` (`name`, `week_json`, `is_default`) VALUES
    ('Standard (08:00-12:00 / 14:00-18:00, Mon-Fri)',
     '{"1":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"2":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"3":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"4":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"5":[["08:00:00","12:00:00"],["14:00:00","18:00:00"]],"6":[],"7":[]}',
     1);
