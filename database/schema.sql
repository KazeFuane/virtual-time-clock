-- =============================================================
-- Virtual Time Clock - Database Schema
-- MariaDB / MySQL
-- =============================================================
-- Timezone: Africa/Casablanca
-- Break Policy: One 45-minute break per day
-- =============================================================

-- Drop tables in reverse FK dependency order
DROP TABLE IF EXISTS `breaks`;
DROP TABLE IF EXISTS `time_actions`;
DROP TABLE IF EXISTS `attendance_days`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- =============================================================
-- ROLES
-- =============================================================
CREATE TABLE `roles` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- USERS
-- =============================================================
CREATE TABLE `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id`       INT UNSIGNED NOT NULL,
    `username`      VARCHAR(50)  NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name`    VARCHAR(100) NOT NULL,
    `last_name`     VARCHAR(100) NOT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email`    (`email`),
    INDEX      `idx_users_role`    (`role_id`),
    INDEX      `idx_users_active`  (`is_active`),

    CONSTRAINT `fk_users_role`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ATTENDANCE_DAYS
-- One record per employee per calendar day
-- =============================================================
CREATE TABLE `attendance_days` (
    `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`             INT UNSIGNED NOT NULL,
    `work_date`           DATE         NOT NULL,
    `start_time`          TIME         NULL     COMMENT 'Set on START_WORKDAY',
    `end_time`            TIME         NULL     COMMENT 'Set on END_WORKDAY',
    `total_work_seconds`  INT UNSIGNED NOT NULL DEFAULT 0,
    `total_break_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `status`              ENUM('NOT_STARTED','WORKING','ON_BREAK','FINISHED') NOT NULL DEFAULT 'NOT_STARTED',
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_attendance_user_date` (`user_id`, `work_date`),
    INDEX      `idx_attendance_date`     (`work_date`),
    INDEX      `idx_attendance_status`   (`status`),

    CONSTRAINT `fk_attendance_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- TIME_ACTIONS
-- Immutable log of every attendance action
-- =============================================================
CREATE TABLE `time_actions` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `attendance_day_id` INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `action_type`       ENUM('START_WORKDAY','END_WORKDAY','START_BREAK','END_BREAK') NOT NULL,
    `action_time`       TIME      NOT NULL,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_actions_day`    (`attendance_day_id`),
    INDEX `idx_actions_user`   (`user_id`),
    INDEX `idx_actions_type`   (`action_type`),

    CONSTRAINT `fk_actions_attendance`
        FOREIGN KEY (`attendance_day_id`) REFERENCES `attendance_days` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_actions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- BREAKS
-- Tracks individual break periods (max 1 per day per policy)
-- =============================================================
CREATE TABLE `breaks` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `attendance_day_id` INT UNSIGNED NOT NULL,
    `user_id`           INT UNSIGNED NOT NULL,
    `start_time`        TIME         NOT NULL,
    `end_time`          TIME         NULL     COMMENT 'Set on END_BREAK',
    `duration_seconds`  INT UNSIGNED NULL     COMMENT 'Calculated on END_BREAK',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_breaks_day`  (`attendance_day_id`),
    INDEX `idx_breaks_user` (`user_id`),

    CONSTRAINT `fk_breaks_attendance`
        FOREIGN KEY (`attendance_day_id`) REFERENCES `attendance_days` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_breaks_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
