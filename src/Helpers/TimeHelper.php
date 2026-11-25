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
     * Evaluate attendance punctuality for a day from the agent's planned
     * shifts ([['start' => 'HH:MM:SS', 'end' => 'HH:MM:SS'], ...], see
     * ScheduleService). Without shifts given, the SHIFT_* settings apply
     * Monday to Friday. A day with no planned shift is a day off.
     */
    public static function evaluateShiftPunctuality(?string $startTime, string $workDate, ?string $status, ?array $shifts = null): array
    {
        $today = self::currentDate();
        $now = self::currentTime();
        $isToday = ($workDate === $today);
        $isPastDate = ($workDate < $today);

        if ($shifts === null) {
            $day = (int) (new DateTime($workDate))->format('N');
            $shifts = $day <= 5
                ? [['start' => SHIFT_1_START, 'end' => SHIFT_1_END], ['start' => SHIFT_2_START, 'end' => SHIFT_2_END]]
                : [];
        }
        $hm = fn($t) => substr((string) $t, 0, 5);
        $name = fn(array $sh) => $hm($sh['start']) . ' - ' . $hm($sh['end']);
        $all = implode(', ', array_map(fn($sh) => $hm($sh['start']) . '-' . $hm($sh['end']), $shifts));
        $result = fn(string $code, string $label, int $delay, string $delayText, string $shiftName, ?string $expected, bool $late, bool $absent) => [
            'punctuality'   => $code,
            'label'         => $label,
            'delay_seconds' => $delay,
            'delay_text'    => $delayText,
            'shift_name'    => $shiftName,
            'expected_time' => $expected,
            'is_late'       => $late,
            'is_absent'     => $absent,
            'shifts'        => $shifts,
        ];
        // The shift a time belongs to: the first shift not over yet at that time
        $shiftAt = function (string $t) use ($shifts) {
            foreach ($shifts as $sh) {
                if ($t < $sh['end']) {
                    return $sh;
                }
            }
            return null;
        };

        // No shift planned
        if (!$shifts) {
            return $result('DAY_OFF', empty($startTime) ? 'Day off' : 'Worked on a day off', 0, '--', 'No shift planned', null, false, false);
        }

        // Case 1: clocked in
        if (!empty($startTime)) {
            $sh = $shiftAt($startTime) ?? end($shifts);
            if ($startTime > $sh['start']) {
                $delay = self::secondsBetweenTimes($sh['start'], $startTime);
                if ($delay > LATE_TOLERANCE_SECONDS) {
                    return $result('LATE', 'Late Arrival', $delay, self::formatDuration($delay), $name($sh), $sh['start'], true, false);
                }
            }
            return $result('ON_TIME', 'On Time', 0, '00:00:00', $name($sh), $sh['start'], false, false);
        }

        // Case 2: did not clock in
        $last = end($shifts);
        if ($isPastDate || ($isToday && $now >= $last['end'])) {
            return $result('ABSENT', 'Absent', 0, 'Full Day', $all, $shifts[0]['start'], false, true);
        }
        if ($isToday && $now > $shifts[0]['start']) {
            $sh = $shiftAt($now) ?? $last;
            if ($now > $sh['start']) {
                $delay = self::secondsBetweenTimes($sh['start'], $now);
                return $result('DELAYED_NOT_IN', 'Delayed (Not In Yet)', $delay, self::formatDuration($delay), $name($sh), $sh['start'], true, false);
            }
            // Between two shifts, the next one has not started yet
            return $result('UPCOMING', 'Shift Upcoming', 0, '--', $name($sh), $sh['start'], false, false);
        }
        return $result('UPCOMING', 'Shift Upcoming', 0, '--', $name($shifts[0]), $shifts[0]['start'], false, false);
    }
}
