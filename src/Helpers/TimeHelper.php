<?php
/**
 * Virtual Time Clock - Time Helper
 *
 * Utility functions for timezone-aware time operations,
 * duration formatting, and time calculations.
 */

class TimeHelper
{
    /**
     * Get the current DateTime in the application timezone.
     */
    public static function now(): DateTime
    {
        return new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    }

    /**
     * Get the current date as Y-m-d string.
     */
    public static function currentDate(): string
    {
        return self::now()->format('Y-m-d');
    }

    /**
     * Get the current time as H:i:s string.
     */
    public static function currentTime(): string
    {
        return self::now()->format('H:i:s');
    }

    /**
     * Format seconds into HH:MM:SS string.
     */
    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs    = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Calculate seconds between two TIME strings (H:i:s) on the same day.
     */
    public static function secondsBetweenTimes(string $startTime, string $endTime): int
    {
        $start = DateTime::createFromFormat('H:i:s', $startTime);
        $end   = DateTime::createFromFormat('H:i:s', $endTime);

        if ($start === false || $end === false) {
            return 0;
        }

        return max(0, $end->getTimestamp() - $start->getTimestamp());
    }

    /**
     * Get the current server time in ISO 8601 format.
     */
    public static function getServerTimeISO(): string
    {
        return self::now()->format('c');
    }

    /**
     * Evaluate attendance punctuality for a given date and start time.
     * Shifts:
     * Shift 1: 08:00 - 12:00
     * Shift 2: 14:00 - 18:00
     */
    public static function evaluateShiftPunctuality(?string $startTime, string $workDate, ?string $status): array
    {
        $today = self::currentDate();
        $now = self::currentTime();
        $isToday = ($workDate === $today);
        $isPastDate = ($workDate < $today);

        // Case 1: Employee clocked in
        if (!empty($startTime)) {
            $clockTime = $startTime;
            $shift1Start = SHIFT_1_START; // '08:00:00'
            $shift2Start = SHIFT_2_START; // '14:00:00'

            // Check if clocked in late for morning shift
            // If they clocked in after 08:00:00 and before/around shift 2
            if ($clockTime <= '13:00:00') {
                if ($clockTime > $shift1Start) {
                    $delaySeconds = self::secondsBetweenTimes($shift1Start, $clockTime);
                    return [
                        'punctuality'   => 'LATE',
                        'label'         => 'Late Arrival',
                        'delay_seconds' => $delaySeconds,
                        'delay_text'    => self::formatDuration($delaySeconds),
                        'shift_name'    => 'Morning (08:00 - 12:00)',
                        'expected_time' => $shift1Start,
                        'is_late'       => true,
                        'is_absent'     => false
                    ];
                } else {
                    return [
                        'punctuality'   => 'ON_TIME',
                        'label'         => 'On Time',
                        'delay_seconds' => 0,
                        'delay_text'    => '00:00:00',
                        'shift_name'    => 'Morning (08:00 - 12:00)',
                        'expected_time' => $shift1Start,
                        'is_late'       => false,
                        'is_absent'     => false
                    ];
                }
            } else {
                // Afternoon shift
                if ($clockTime > $shift2Start) {
                    $delaySeconds = self::secondsBetweenTimes($shift2Start, $clockTime);
                    return [
                        'punctuality'   => 'LATE',
                        'label'         => 'Late Arrival',
                        'delay_seconds' => $delaySeconds,
                        'delay_text'    => self::formatDuration($delaySeconds),
                        'shift_name'    => 'Afternoon (14:00 - 18:00)',
                        'expected_time' => $shift2Start,
                        'is_late'       => true,
                        'is_absent'     => false
                    ];
                } else {
                    return [
                        'punctuality'   => 'ON_TIME',
                        'label'         => 'On Time',
                        'delay_seconds' => 0,
                        'delay_text'    => '00:00:00',
                        'shift_name'    => 'Afternoon (14:00 - 18:00)',
                        'expected_time' => $shift2Start,
                        'is_late'       => false,
                        'is_absent'     => false
                    ];
                }
            }
        }

        // Case 2: Employee did NOT clock in
        // If it's a past day, it is definitely an Absence.
        if ($isPastDate) {
            return [
                'punctuality'   => 'ABSENT',
                'label'         => 'Absent',
                'delay_seconds' => 0,
                'delay_text'    => 'Full Day',
                'shift_name'    => 'Both Shifts (08:00-12:00, 14:00-18:00)',
                'expected_time' => SHIFT_1_START,
                'is_late'       => false,
                'is_absent'     => true
            ];
        }

        // If it is today and current time has passed the end of the workday (18:00:00), they are absent
        if ($isToday && $now >= SHIFT_2_END) {
            return [
                'punctuality'   => 'ABSENT',
                'label'         => 'Absent',
                'delay_seconds' => 0,
                'delay_text'    => 'Full Day',
                'shift_name'    => 'Both Shifts',
                'expected_time' => SHIFT_1_START,
                'is_late'       => false,
                'is_absent'     => true
            ];
        }

        // If it is today and current time is past morning start (08:00:00) but hasn't logged in yet
        if ($isToday && $now > SHIFT_1_START) {
            $delaySeconds = self::secondsBetweenTimes(SHIFT_1_START, $now);
            return [
                'punctuality'   => 'DELAYED_NOT_IN',
                'label'         => 'Delayed (Not In Yet)',
                'delay_seconds' => $delaySeconds,
                'delay_text'    => self::formatDuration($delaySeconds),
                'shift_name'    => ($now < SHIFT_2_START) ? 'Morning (08:00 - 12:00)' : 'Afternoon (14:00 - 18:00)',
                'expected_time' => ($now < SHIFT_2_START) ? SHIFT_1_START : SHIFT_2_START,
                'is_late'       => true,
                'is_absent'     => false
            ];
        }

        // Before 08:00:00 today
        return [
            'punctuality'   => 'UPCOMING',
            'label'         => 'Shift Upcoming',
            'delay_seconds' => 0,
            'delay_text'    => '--',
            'shift_name'    => 'Morning (08:00 - 12:00)',
            'expected_time' => SHIFT_1_START,
            'is_late'       => false,
            'is_absent'     => false
        ];
    }
}
