-- =============================================================
-- Virtual Time Clock - Development Seed Data
-- =============================================================
-- WARNING: These are DEVELOPMENT accounts.
-- Change passwords or remove these accounts before production.
--
-- Test Credentials:
--   admin    / Admin2024!    (Administrator)
--   manager  / Manager2024!  (Manager)
--   employee / Employee2024! (Employee)
-- =============================================================

-- Insert roles
INSERT INTO `roles` (`name`) VALUES
    ('employee'),
    ('manager'),
    ('administrator');

-- Insert test users
-- Passwords generated with PHP password_hash() using PASSWORD_DEFAULT
INSERT INTO `users` (`role_id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `is_active`) VALUES
    (3, 'admin',    'admin@company.com',    '$2y$12$4R9qKDgzD/WsZH53HqqlTOSNIpB4Zi82eefsTZwQrtdnsQYaq5Lau', 'Soufiane', 'El Harouchy', 1),
    (2, 'manager',  'manager@company.com',  '$2y$12$c3V2aUielYJfAL2iSJfRV.a8asbEBajprgBcJJDRdNyRVKunkf0wW', 'Sarah',    'Connor',      1),
    (1, 'employee', 'employee@company.com', '$2y$12$3GODc/JzGgOe8hnRE9VFx.0iAnNTxD1H.Wz4qOJ736STGWUKuxuy.', 'John',     'Doe',         1);

-- =============================================================
-- Sample attendance data for the employee user (id=3)
-- Simulates the last 3 completed workdays
-- =============================================================

-- Day 1: 3 days ago - Full workday with break
INSERT INTO `attendance_days` (`user_id`, `work_date`, `start_time`, `end_time`, `total_work_seconds`, `total_break_seconds`, `status`)
VALUES (3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:00:00', '17:30:00', '27900', '2700', 'FINISHED');

SET @day1_id = LAST_INSERT_ID();

INSERT INTO `time_actions` (`attendance_day_id`, `user_id`, `action_type`, `action_time`) VALUES
    (@day1_id, 3, 'START_WORKDAY', '09:00:00'),
    (@day1_id, 3, 'START_BREAK',   '12:30:00'),
    (@day1_id, 3, 'END_BREAK',     '13:15:00'),
    (@day1_id, 3, 'END_WORKDAY',   '17:30:00');

INSERT INTO `breaks` (`attendance_day_id`, `user_id`, `start_time`, `end_time`, `duration_seconds`)
VALUES (@day1_id, 3, '12:30:00', '13:15:00', 2700);

-- Day 2: 2 days ago - Full workday with break
INSERT INTO `attendance_days` (`user_id`, `work_date`, `start_time`, `end_time`, `total_work_seconds`, `total_break_seconds`, `status`)
VALUES (3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:45:00', '17:15:00', '27900', '2700', 'FINISHED');

SET @day2_id = LAST_INSERT_ID();

INSERT INTO `time_actions` (`attendance_day_id`, `user_id`, `action_type`, `action_time`) VALUES
    (@day2_id, 3, 'START_WORKDAY', '08:45:00'),
    (@day2_id, 3, 'START_BREAK',   '12:00:00'),
    (@day2_id, 3, 'END_BREAK',     '12:45:00'),
    (@day2_id, 3, 'END_WORKDAY',   '17:15:00');

INSERT INTO `breaks` (`attendance_day_id`, `user_id`, `start_time`, `end_time`, `duration_seconds`)
VALUES (@day2_id, 3, '12:00:00', '12:45:00', 2700);

-- Day 3: Yesterday - Full workday with break
INSERT INTO `attendance_days` (`user_id`, `work_date`, `start_time`, `end_time`, `total_work_seconds`, `total_break_seconds`, `status`)
VALUES (3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:05:23', '17:02:11', '25896', '2712', 'FINISHED');

SET @day3_id = LAST_INSERT_ID();

INSERT INTO `time_actions` (`attendance_day_id`, `user_id`, `action_type`, `action_time`) VALUES
    (@day3_id, 3, 'START_WORKDAY', '09:05:23'),
    (@day3_id, 3, 'START_BREAK',   '12:30:10'),
    (@day3_id, 3, 'END_BREAK',     '13:15:22'),
    (@day3_id, 3, 'END_WORKDAY',   '17:02:11');

INSERT INTO `breaks` (`attendance_day_id`, `user_id`, `start_time`, `end_time`, `duration_seconds`)
VALUES (@day3_id, 3, '12:30:10', '13:15:22', 2712);
