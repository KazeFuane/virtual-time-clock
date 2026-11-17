<?php
/**
 * Virtual Time Clock - Attendance Service
 *
 * Core business logic for the attendance state machine.
 * Handles all four attendance actions with database transactions.
 *
 * State Machine:
 *   NOT_STARTED → WORKING → ON_BREAK → WORKING → FINISHED
 *
 * Break Policy: One single 45-minute break per day.
 */

class AttendanceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get today's attendance record for a user.
     * Returns attendance data with actions and break info, or null if no record.
     */
    public function getToday(int $userId): ?array
    {
        $today = TimeHelper::currentDate();
        $now   = TimeHelper::currentTime();

        $stmt = $this->db->prepare("
            SELECT * FROM attendance_days
            WHERE user_id = ? AND work_date = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $today]);
        $attendance = $stmt->fetch();

        if (!$attendance) {
            $punctuality = TimeHelper::evaluateShiftPunctuality(null, $today, 'NOT_STARTED');
            return [
                'status'              => 'NOT_STARTED',
                'work_date'           => $today,
                'start_time'          => null,
                'end_time'            => null,
                'total_work_seconds'  => 0,
                'total_break_seconds' => 0,
                'actions'             => [],
                'current_break'       => null,
                'server_time'         => TimeHelper::getServerTimeISO(),
                'punctuality'         => $punctuality,
            ];
        }

        // Fetch today's actions
        $actionsStmt = $this->db->prepare("
            SELECT action_type, action_time
            FROM time_actions
            WHERE attendance_day_id = ?
            ORDER BY action_time ASC
        ");
        $actionsStmt->execute([$attendance['id']]);
        $actions = $actionsStmt->fetchAll();

        // Fetch current open break (if any)
        $breakStmt = $this->db->prepare("
            SELECT * FROM breaks
            WHERE attendance_day_id = ? AND end_time IS NULL
            LIMIT 1
        ");
        $breakStmt->execute([$attendance['id']]);
        $currentBreak = $breakStmt->fetch();

        // Calculate live durations
        $totalWorkSeconds  = (int) $attendance['total_work_seconds'];
        $totalBreakSeconds = (int) $attendance['total_break_seconds'];

        if ($attendance['status'] === 'WORKING') {
            // Add elapsed time since last work-resume point
            $totalWorkSeconds = $this->calculateLiveWorkSeconds($attendance, $now);
        } elseif ($attendance['status'] === 'ON_BREAK') {
            // Add elapsed break time
            if ($currentBreak) {
                $elapsedBreak = TimeHelper::secondsBetweenTimes($currentBreak['start_time'], $now);
                $totalBreakSeconds = (int) $attendance['total_break_seconds'] + $elapsedBreak;
            }
            $totalWorkSeconds = (int) $attendance['total_work_seconds'];
        }

        $punctuality = TimeHelper::evaluateShiftPunctuality($attendance['start_time'], $attendance['work_date'], $attendance['status']);

        return [
            'id'                  => $attendance['id'],
            'status'              => $attendance['status'],
            'work_date'           => $attendance['work_date'],
            'start_time'          => $attendance['start_time'],
            'end_time'            => $attendance['end_time'],
            'total_work_seconds'  => $totalWorkSeconds,
            'total_break_seconds' => $totalBreakSeconds,
            'actions'             => $actions,
            'current_break'       => $currentBreak ?: null,
            'server_time'         => TimeHelper::getServerTimeISO(),
            'punctuality'         => $punctuality,
        ];
    }

    /**
     * Calculate live work seconds (accounts for break time already taken).
     */
    private function calculateLiveWorkSeconds(array $attendance, string $currentTime): int
    {
        $totalElapsed = TimeHelper::secondsBetweenTimes($attendance['start_time'], $currentTime);
        $breakSeconds = (int) $attendance['total_break_seconds'];
        return max(0, $totalElapsed - $breakSeconds);
    }

    /**
     * START WORKDAY action.
     */
    public function startWorkday(int $userId): array
    {
        $today = TimeHelper::currentDate();
        $now   = TimeHelper::currentTime();

        // Check current state: if user accidentally ended workday, allow them to resume!
        $current = $this->getTodayRecord($userId, $today);
        $isResuming = false;
        if ($current && $current['status'] !== 'NOT_STARTED') {
            if ($current['status'] === 'WORKING') {
                throw new RuntimeException('You are already actively working.');
            }
            if ($current['status'] === 'ON_BREAK') {
                throw new RuntimeException('You are currently on break. Please click End Break first.');
            }
            if ($current['status'] === 'FINISHED') {
                $isResuming = true;
            }
        }

        $this->db->beginTransaction();
        try {
            // Create or update attendance_days record
            if ($current) {
                // If resuming, clear end_time and return to WORKING
                $stmt = $this->db->prepare("
                    UPDATE attendance_days
                    SET end_time = NULL, status = 'WORKING', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$current['id']]);
                $attendanceId = $current['id'];
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO attendance_days (user_id, work_date, start_time, status)
                    VALUES (?, ?, ?, 'WORKING')
                ");
                $stmt->execute([$userId, $today, $now]);
                $attendanceId = $this->db->lastInsertId();
            }

            // Log the action
            $actionLabel = $isResuming ? 'START_WORKDAY' : 'START_WORKDAY';
            $stmt = $this->db->prepare("
                INSERT INTO time_actions (attendance_day_id, user_id, action_type, action_time)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$attendanceId, $userId, $actionLabel, $now]);

            $this->db->commit();

            return [
                'action'   => 'START_WORKDAY',
                'time'     => $now,
                'resumed'  => $isResuming,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * START BREAK action.
     */
    public function startBreak(int $userId): array
    {
        $today = TimeHelper::currentDate();
        $now   = TimeHelper::currentTime();

        $current = $this->getTodayRecord($userId, $today);

        if (!$current || $current['status'] === 'NOT_STARTED') {
            throw new RuntimeException('You must start your workday first.');
        }
        if ($current['status'] === 'ON_BREAK') {
            throw new RuntimeException('You are already on break.');
        }
        if ($current['status'] === 'FINISHED') {
            throw new RuntimeException('Your workday is already complete.');
        }

        // Check cumulative break limit (maximum 45 minutes total per day)
        $maxBreakSeconds = BREAK_DURATION_MINUTES * 60; // 45 * 60 = 2700 seconds
        $usedBreakSeconds = (int) $current['total_break_seconds'];
        if ($usedBreakSeconds >= $maxBreakSeconds) {
            throw new RuntimeException('You have reached the maximum daily break allowance of ' . BREAK_DURATION_MINUTES . ' minutes.');
        }

        // Calculate and store work seconds up to now
        $workSecondsUntilNow = $this->calculateLiveWorkSeconds($current, $now);

        $this->db->beginTransaction();
        try {
            // Update attendance status and store accumulated work seconds
            $stmt = $this->db->prepare("
                UPDATE attendance_days
                SET status = 'ON_BREAK',
                    total_work_seconds = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$workSecondsUntilNow, $current['id']]);

            // Create break record
            $stmt = $this->db->prepare("
                INSERT INTO breaks (attendance_day_id, user_id, start_time)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$current['id'], $userId, $now]);

            // Log the action
            $stmt = $this->db->prepare("
                INSERT INTO time_actions (attendance_day_id, user_id, action_type, action_time)
                VALUES (?, ?, 'START_BREAK', ?)
            ");
            $stmt->execute([$current['id'], $userId, $now]);

            $this->db->commit();

            return [
                'action' => 'START_BREAK',
                'time'   => $now,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * END BREAK action.
     */
    public function endBreak(int $userId): array
    {
        $today = TimeHelper::currentDate();
        $now   = TimeHelper::currentTime();

        $current = $this->getTodayRecord($userId, $today);

        if (!$current || $current['status'] !== 'ON_BREAK') {
            throw new RuntimeException('You are not currently on break.');
        }

        // Find the open break
        $breakStmt = $this->db->prepare("
            SELECT * FROM breaks
            WHERE attendance_day_id = ? AND user_id = ? AND end_time IS NULL
            LIMIT 1
        ");
        $breakStmt->execute([$current['id'], $userId]);
        $openBreak = $breakStmt->fetch();

        if (!$openBreak) {
            throw new RuntimeException('No open break found.');
        }

        $breakDuration = TimeHelper::secondsBetweenTimes($openBreak['start_time'], $now);
        $maxBreakSeconds = BREAK_DURATION_MINUTES * 60; // 2700s
        $prevBreakSeconds = (int) $current['total_break_seconds'];
        $totalBreakSeconds = $prevBreakSeconds + $breakDuration;

        // If break exceeds max allowance, cap duration cleanly
        if ($totalBreakSeconds > $maxBreakSeconds) {
            $breakDuration = max(0, $maxBreakSeconds - $prevBreakSeconds);
            $totalBreakSeconds = $maxBreakSeconds;
        }

        $this->db->beginTransaction();
        try {
            // Close the break record
            $stmt = $this->db->prepare("
                UPDATE breaks
                SET end_time = ?, duration_seconds = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$now, $breakDuration, $openBreak['id']]);

            // Update attendance: back to WORKING, update break totals
            $stmt = $this->db->prepare("
                UPDATE attendance_days
                SET status = 'WORKING',
                    total_break_seconds = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$totalBreakSeconds, $current['id']]);

            // Log the action
            $stmt = $this->db->prepare("
                INSERT INTO time_actions (attendance_day_id, user_id, action_type, action_time)
                VALUES (?, ?, 'END_BREAK', ?)
            ");
            $stmt->execute([$current['id'], $userId, $now]);

            $this->db->commit();

            return [
                'action'           => 'END_BREAK',
                'time'             => $now,
                'break_duration'   => $breakDuration,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * END WORKDAY action.
     */
    public function endWorkday(int $userId): array
    {
        $today = TimeHelper::currentDate();
        $now   = TimeHelper::currentTime();

        $current = $this->getTodayRecord($userId, $today);

        if (!$current || $current['status'] === 'NOT_STARTED') {
            throw new RuntimeException('You have not started your workday.');
        }
        if ($current['status'] === 'ON_BREAK') {
            throw new RuntimeException('You must end your break before ending your workday.');
        }
        if ($current['status'] === 'FINISHED') {
            throw new RuntimeException('Your workday is already complete.');
        }

        // Calculate final work seconds
        $totalElapsed      = TimeHelper::secondsBetweenTimes($current['start_time'], $now);
        $totalBreakSeconds = (int) $current['total_break_seconds'];
        $totalWorkSeconds  = max(0, $totalElapsed - $totalBreakSeconds);

        $this->db->beginTransaction();
        try {
            // Finalize the attendance record
            $stmt = $this->db->prepare("
                UPDATE attendance_days
                SET end_time = ?,
                    total_work_seconds = ?,
                    status = 'FINISHED',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$now, $totalWorkSeconds, $current['id']]);

            // Log the action
            $stmt = $this->db->prepare("
                INSERT INTO time_actions (attendance_day_id, user_id, action_type, action_time)
                VALUES (?, ?, 'END_WORKDAY', ?)
            ");
            $stmt->execute([$current['id'], $userId, $now]);

            $this->db->commit();

            return [
                'action'             => 'END_WORKDAY',
                'time'               => $now,
                'total_work_seconds' => $totalWorkSeconds,
                'total_break_seconds'=> $totalBreakSeconds,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get attendance history for a specific date.
     */
    public function getHistory(int $userId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM attendance_days
            WHERE user_id = ? AND work_date = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $date]);
        $attendance = $stmt->fetch();

        if (!$attendance) {
            return null;
        }

        // Get actions
        $actionsStmt = $this->db->prepare("
            SELECT action_type, action_time
            FROM time_actions
            WHERE attendance_day_id = ?
            ORDER BY action_time ASC
        ");
        $actionsStmt->execute([$attendance['id']]);
        $actions = $actionsStmt->fetchAll();

        // Get breaks
        $breakStmt = $this->db->prepare("
            SELECT start_time, end_time, duration_seconds
            FROM breaks
            WHERE attendance_day_id = ?
            ORDER BY start_time ASC
        ");
        $breakStmt->execute([$attendance['id']]);
        $breaks = $breakStmt->fetchAll();

        $punctuality = TimeHelper::evaluateShiftPunctuality($attendance['start_time'], $attendance['work_date'], $attendance['status']);

        return [
            'id'                  => $attendance['id'],
            'work_date'           => $attendance['work_date'],
            'start_time'          => $attendance['start_time'],
            'end_time'            => $attendance['end_time'],
            'total_work_seconds'  => (int) $attendance['total_work_seconds'],
            'total_break_seconds' => (int) $attendance['total_break_seconds'],
            'status'              => $attendance['status'],
            'actions'             => $actions,
            'breaks'              => $breaks,
            'punctuality'         => $punctuality,
        ];
    }

    /**
     * Get the raw attendance record for today (no live calculations).
     */
    private function getTodayRecord(int $userId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM attendance_days
            WHERE user_id = ? AND work_date = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Count the number of breaks for an attendance day.
     */
    private function getBreakCount(int $attendanceDayId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM breaks
            WHERE attendance_day_id = ?
        ");
        $stmt->execute([$attendanceDayId]);
        return (int) $stmt->fetchColumn();
    }
}
