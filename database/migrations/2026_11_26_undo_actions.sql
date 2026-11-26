-- =============================================================
-- Migration: undo for manager/admin actions
-- After a manager or administrator corrects clock times, records or
-- cancels leave, changes a schedule or deactivates an account, the page
-- shows an "Undo" pop-up for 10 seconds. Each row keeps what is needed
-- to put things back, for that one person, for a short time.
-- Run once on an existing database:
--   mysql -u root virtual_time_clock < database/migrations/2026_11_26_undo_actions.sql
-- (The app also creates the table on first use if this was not run.)
-- =============================================================
CREATE TABLE IF NOT EXISTS `undo_actions` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token`        CHAR(32)     NOT NULL                 COMMENT 'Random id sent to the page',
    `actor_id`     INT UNSIGNED NOT NULL                 COMMENT 'Manager/admin who made the change (only they can undo it)',
    `kind`         VARCHAR(40)  NOT NULL                 COMMENT 'time_correction, leave_create, leave_cancel, schedule, user_deactivate',
    `payload_json` MEDIUMTEXT   NOT NULL                 COMMENT 'What to put back',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`   DATETIME     NOT NULL,
    `undone_at`    DATETIME     NULL,

    UNIQUE KEY `uk_undo_token`   (`token`),
    INDEX      `idx_undo_expires` (`expires_at`),

    CONSTRAINT `fk_undo_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
