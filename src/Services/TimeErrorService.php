<?php
/**
 * Virtual Time Clock - Time Error Service
 *
 * Builds a per-agent summary of time errors over a date range.
 * It only reads data. It never changes attendance records.
 *
 * Error codes (thresholds marked GUESS can be tuned below):
 *   MISSING_LOGOUT        Past day still WORKING or ON_BREAK, no END_WORKDAY.
 *   OPEN_BREAK            Past day with a break that was never ended.
 *   MISSING_LOGIN         Actions or a status exist, but no START_WORKDAY.
 *   RELOGIN_AFTER_LOGOUT  Clocked in again after END_WORKDAY the same day.
 *                         (Older app versions counted the logged-out gap as
 *                         worked; "Time difference" shows if that happened.)
 *   BREAK_OVER_LIMIT      Real break time is over the daily allowance.
 *                         (Older app versions capped the stored break, so the
 *                         extra minutes were counted as worked.)
 *   EXTRA_BREAKS          More breaks than MAX_BREAKS_PER_DAY.
 *   OUT_OF_ORDER          Actions in an impossible order, or an end time
 *                         earlier than its start time.
 *   TOTAL_MISMATCH        Stored work total does not match its own
 *                         start/end/break values (manual edit or bug).
 *   LONG_SHIFT            Real work time over LONG_SHIFT_SECONDS (GUESS: 12h).
 *   SHORT_SHIFT           Finished day with real work under
 *                         SHORT_SHIFT_SECONDS (GUESS: 15 min).
 *   LATE_ARRIVAL          Clock-in after the shift start (SHIFT_1_START, or
 *                         SHIFT_2_START when clocking in after 13:00).
 *   EARLY_DEPARTURE       Last clock-out before SHIFT_2_END.
 *   ABSENCE               Past weekday (Mon-Fri, GUESS) with no clock-in,
 *                         counted only from the day after the account was created.
 *
 * Only active accounts with the employee role are checked. Managers and
 * administrators are not required to clock in, so they are left out.
 *
 * Every incident carries impact_seconds when a duration makes sense; the
 * per-agent 'durations' array sums them by error code.
 */

class TimeErrorService
{
    public const LONG_SHIFT_SECONDS  = 12 * 3600;  // GUESS
    public const SHORT_SHIFT_SECONDS = 15 * 60;    // GUESS
    public const MISMATCH_TOLERANCE  = 60;         // seconds
    public const BREAK_GRACE_SECONDS = 60;         // GUESS: ignore breaks less than 1 min over

    public const LABELS = [
        'LATE_ARRIVAL'         => 'Late arrival',
        'EARLY_DEPARTURE'      => 'Early departure',
        'ABSENCE'              => 'Absence',
        'MISSING_LOGOUT'       => 'Missing logout',
        'OPEN_BREAK'           => 'Break never ended',
        'MISSING_LOGIN'        => 'Missing login',
        'RELOGIN_AFTER_LOGOUT' => 'Logged in again after logout',
        'BREAK_OVER_LIMIT'     => 'Break over limit',
        'EXTRA_BREAKS'         => 'More breaks than allowed',
        'OUT_OF_ORDER'         => 'Actions out of order',
        'TOTAL_MISMATCH'       => 'Stored total does not match times',
        'LONG_SHIFT'           => 'Unusually long shift',
        'SHORT_SHIFT'          => 'Unusually short shift',
    ];

    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    /**
     * Load data from the database and summarize it.
     */
    public function summarize(string $from, string $to, string $search = '', ?int $userId = null): array
    {
        $db = $this->db ?? Database::getInstance()->getConnection();

        $sql = "SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role, u.created_at
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1 AND r.name = 'employee'";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        if ($userId !== null) {
            $sql .= " AND u.id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY u.last_name, u.first_name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $daySql = "SELECT * FROM attendance_days WHERE work_date BETWEEN ? AND ?";
        $dayParams = [$from, $to];
        if ($userId !== null) {
            $daySql .= " AND user_id = ?";
            $dayParams[] = $userId;
        }
        $stmt = $db->prepare($daySql . " ORDER BY work_date");
        $stmt->execute($dayParams);
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dayIds = array_column($days, 'id');
        $actions = [];
        $breaks  = [];
        if ($dayIds) {
            $in = implode(',', array_fill(0, count($dayIds), '?'));
            $stmt = $db->prepare("SELECT * FROM time_actions WHERE attendance_day_id IN ($in) ORDER BY id");
            $stmt->execute($dayIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $actions[$a['attendance_day_id']][] = $a;
            }
            $stmt = $db->prepare("SELECT * FROM breaks WHERE attendance_day_id IN ($in) ORDER BY id");
            $stmt->execute($dayIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $breaks[$b['attendance_day_id']][] = $b;
            }
        }

        return $this->buildSummary($users, $days, $actions, $breaks, TimeHelper::currentDate(), $from, $to);
    }

    /**
     * Pure summary builder (no database), so it can be tested.
     *
     * @param array $users   rows: id, username, first_name, last_name, role
     * @param array $days    attendance_days rows
     * @param array $actions attendance_day_id => time_actions rows (in insert order)
     * @param array $breaks  attendance_day_id => breaks rows
     */
    public function buildSummary(array $users, array $days, array $actions, array $breaks, string $today, string $from, string $to): array
    {
        $byUser = [];
        foreach ($users as $u) {
            $byUser[$u['id']] = [
                'user_id'               => (int) $u['id'],
                'username'              => $u['username'],
                'name'                  => trim($u['first_name'] . ' ' . $u['last_name']),
                'role'                  => $u['role'] ?? null,
                'created_on'            => !empty($u['created_at']) ? substr((string) $u['created_at'], 0, 10) : null,
                'days_checked'          => 0,
                'days_with_errors'      => 0,
                'error_count'           => 0,
                'counts'                => array_fill_keys(array_keys(self::LABELS), 0),
                'durations'             => array_fill_keys(array_keys(self::LABELS), 0),
                'recorded_work_seconds' => 0,
                'actual_work_seconds'   => 0,
                'difference_seconds'    => 0,   // recorded - actual; > 0 means the app counted extra time
                'unverifiable_days'     => 0,   // days whose real hours cannot be known (missing logout/login)
                'incidents'             => [],
            ];
        }

        foreach ($days as $day) {
            $uid = $day['user_id'];
            if (!isset($byUser[$uid])) {
                continue; // inactive or filtered out
            }
            $result = $this->checkDay($day, $actions[$day['id']] ?? [], $breaks[$day['id']] ?? [], $today);
            if ($result === null) {
                continue; // nothing to check (today, still in progress)
            }

            $s = &$byUser[$uid];
            $s['days_checked']++;
            if ($result['errors']) {
                $s['days_with_errors']++;
            }
            foreach ($result['errors'] as $err) {
                $s['error_count']++;
                $s['counts'][$err['code']]++;
                $s['durations'][$err['code']] += abs((int) ($err['impact_seconds'] ?? 0));
                $s['incidents'][] = ['work_date' => $day['work_date']] + $err;
            }
            if ($result['verifiable']) {
                $s['recorded_work_seconds'] += $result['recorded'];
                $s['actual_work_seconds']   += $result['actual'];
                $s['difference_seconds']    += $result['recorded'] - $result['actual'];
            } else {
                $s['unverifiable_days']++;
            }
            unset($s);
        }

        // Absences: past weekdays in the range with no clock-in (employees only)
        $worked = [];
        foreach ($days as $day) {
            if (!empty($day['start_time'])) {
                $worked[$day['user_id']][$day['work_date']] = true;
            }
        }
        $shiftSeconds = (self::toSeconds(SHIFT_1_END) - self::toSeconds(SHIFT_1_START))
                      + (self::toSeconds(SHIFT_2_END) - self::toSeconds(SHIFT_2_START));
        foreach ($byUser as $uid => &$s) {
            if (($s['role'] ?? 'employee') !== 'employee') {
                continue;
            }
            for ($d = new DateTime($from); $d->format('Y-m-d') <= $to && $d->format('Y-m-d') < $today; $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                // Not absent before the account existed, nor on the day it was
                // created (the account may have been made after the shift started)
                if ($s['created_on'] !== null && $date <= $s['created_on']) {
                    continue;
                }
                if ((int) $d->format('N') >= 6 || isset($worked[$uid][$date])) {
                    continue;
                }
                $s['error_count']++;
                $s['days_with_errors']++;
                $s['counts']['ABSENCE']++;
                $s['durations']['ABSENCE'] += $shiftSeconds;
                $s['incidents'][] = [
                    'work_date'      => $date,
                    'code'           => 'ABSENCE',
                    'label'          => self::LABELS['ABSENCE'],
                    'detail'         => 'No clock-in on this working day.',
                    'impact_seconds' => $shiftSeconds,
                ];
            }
            usort($s['incidents'], fn($a, $b) => strcmp($a['work_date'], $b['work_date']));
        }
        unset($s);

        $agents = array_values($byUser);
        usort($agents, fn($a, $b) => [$b['error_count'], $a['name']] <=> [$a['error_count'], $b['name']]);

        $totals = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($agents as $a) {
            foreach ($a['counts'] as $code => $n) {
                $totals[$code] += $n;
            }
        }

        return [
            'from'       => $from,
            'to'         => $to,
            'labels'     => self::LABELS,
            'totals'     => $totals,
            'agents'     => $agents,
            'thresholds' => [
                'break_allowance_seconds' => BREAK_DURATION_MINUTES * 60,
                'max_breaks_per_day'      => MAX_BREAKS_PER_DAY,
                'long_shift_seconds'      => self::LONG_SHIFT_SECONDS,
                'short_shift_seconds'     => self::SHORT_SHIFT_SECONDS,
            ],
        ];
    }

    /**
     * Check one attendance day. Returns null when the day is still in progress.
     */
    public function checkDay(array $day, array $actions, array $breaks, string $today): ?array
    {
        $isPast = $day['work_date'] < $today;
        $status = $day['status'];
        if (!$isPast && $status !== 'FINISHED') {
            return null;
        }

        $errors = [];
        $add = function (string $code, string $detail, ?int $impact = null) use (&$errors) {
            $errors[] = [
                'code'           => $code,
                'label'          => self::LABELS[$code],
                'detail'         => $detail,
                'impact_seconds' => $impact,
            ];
        };

        $types   = array_column($actions, 'action_type');
        $starts  = count(array_keys($types, 'START_WORKDAY', true));
        $hasEnd  = in_array('END_WORKDAY', $types, true);
        $verifiable = true;

        // --- Missing login
        if ($starts === 0 && ($actions || $status !== 'NOT_STARTED')) {
            $add('MISSING_LOGIN', 'Day has status ' . $status . ' and ' . count($actions) . ' action(s) but no clock-in.');
            $verifiable = false;
        } elseif (empty($day['start_time']) && $status !== 'NOT_STARTED') {
            $add('MISSING_LOGIN', 'Clock-in action exists but the day has no start time.');
            $verifiable = false;
        }

        // --- Missing logout
        if ($isPast && in_array($status, ['WORKING', 'ON_BREAK'], true)) {
            $last = end($actions);
            $open = $last ? max(0, self::toSeconds(SHIFT_2_END) - self::toSeconds($last['action_time'])) : 0;
            $add('MISSING_LOGOUT', 'Day was left ' . $status . ($last ? ' (last action ' . $last['action_type'] . ' at ' . $last['action_time'] . ')' : '') . '. Duration shown is from the last action to ' . substr(SHIFT_2_END, 0, 5) . '.', $open ?: null);
            $verifiable = false;
        } elseif ($status === 'FINISHED' && !$hasEnd) {
            $add('MISSING_LOGOUT', 'Day is marked FINISHED but has no clock-out action.');
            $verifiable = false;
        }

        // --- Open breaks
        foreach ($breaks as $b) {
            if (empty($b['end_time']) && ($isPast || $status === 'FINISHED')) {
                $add('OPEN_BREAK', 'Break started at ' . $b['start_time'] . ' was never ended.');
            }
        }

        // --- Walk the actions in the order they happened (insert order)
        [$actual, $orderProblems, $reloginGaps] = $this->replay($actions);
        foreach ($orderProblems as $p) {
            $add('OUT_OF_ORDER', $p);
        }
        foreach ($reloginGaps as [$out, $in, $gap]) {
            $add('RELOGIN_AFTER_LOGOUT', "Logged out at $out and back in at $in (" . TimeHelper::formatDuration($gap) . ' logged out).', $gap);
        }
        if ($orderProblems) {
            $verifiable = false;
        }

        // --- Breaks: count and real length
        $allowance  = BREAK_DURATION_MINUTES * 60;
        $realBreak  = 0;
        foreach ($breaks as $b) {
            if (!empty($b['end_time'])) {
                $len = self::toSeconds($b['end_time']) - self::toSeconds($b['start_time']);
                if ($len < 0) {
                    $add('OUT_OF_ORDER', 'Break ends (' . $b['end_time'] . ') before it starts (' . $b['start_time'] . ').');
                    $verifiable = false;
                } else {
                    $realBreak += $len;
                }
            }
        }
        if (count($breaks) > MAX_BREAKS_PER_DAY) {
            $add('EXTRA_BREAKS', count($breaks) . ' breaks taken, policy allows ' . MAX_BREAKS_PER_DAY . '.');
        }
        if ($realBreak > $allowance + self::BREAK_GRACE_SECONDS) {
            $excess = $realBreak - $allowance;
            $add('BREAK_OVER_LIMIT', 'Real break time ' . TimeHelper::formatDuration($realBreak) . ', allowance ' . TimeHelper::formatDuration($allowance) . '. Over by ' . TimeHelper::formatDuration($excess) . '.', $excess);
        }

        // --- End before start on the day itself
        if (!empty($day['start_time']) && !empty($day['end_time']) && self::toSeconds($day['end_time']) < self::toSeconds($day['start_time'])) {
            $add('OUT_OF_ORDER', 'Clock-out (' . $day['end_time'] . ') is earlier than clock-in (' . $day['start_time'] . ').');
            $verifiable = false;
        }

        // --- Stored total vs its own start/end/break values
        $recorded = (int) $day['total_work_seconds'];
        // Only for single-session days: with a re-login, end - start includes the logged-out gap.
        if ($status === 'FINISHED' && $starts === 1 && !empty($day['start_time']) && !empty($day['end_time'])) {
            $expected = max(0, self::toSeconds($day['end_time']) - self::toSeconds($day['start_time']) - (int) $day['total_break_seconds']);
            if (abs($expected - $recorded) > self::MISMATCH_TOLERANCE) {
                $add('TOTAL_MISMATCH', 'Stored work total ' . TimeHelper::formatDuration($recorded) . ' but start/end/break give ' . TimeHelper::formatDuration($expected) . '.', $recorded - $expected);
            }
        }

        // --- Late arrival (first clock-in)
        if (!empty($day['start_time'])) {
            $in = self::toSeconds($day['start_time']);
            $expected = $in <= self::toSeconds('13:00:00') ? SHIFT_1_START : SHIFT_2_START;
            $delay = $in - self::toSeconds($expected);
            if ($delay > LATE_TOLERANCE_SECONDS) {
                $add('LATE_ARRIVAL', 'Clocked in at ' . $day['start_time'] . ', shift starts at ' . $expected . '.', $delay);
            }
        }

        // --- Early departure (last clock-out)
        if ($status === 'FINISHED' && $hasEnd) {
            $lastOut = null;
            foreach ($actions as $a) {
                if ($a['action_type'] === 'END_WORKDAY') {
                    $lastOut = $a['action_time'];
                }
            }
            $early = self::toSeconds(SHIFT_2_END) - self::toSeconds($lastOut);
            if ($early > 0) {
                $add('EARLY_DEPARTURE', 'Clocked out at ' . $lastOut . ', shift ends at ' . SHIFT_2_END . '.', $early);
            }
        }

        // --- Plausibility of real work time
        if ($verifiable && $status === 'FINISHED') {
            if ($actual > self::LONG_SHIFT_SECONDS) {
                $add('LONG_SHIFT', 'Real work time ' . TimeHelper::formatDuration($actual) . '.');
            } elseif ($actual < self::SHORT_SHIFT_SECONDS) {
                $add('SHORT_SHIFT', 'Real work time ' . TimeHelper::formatDuration($actual) . '.');
            }
        }

        return [
            'errors'     => $errors,
            'verifiable' => $verifiable,
            'recorded'   => $recorded,
            'actual'     => $actual,
        ];
    }

    /**
     * Replay actions in insert order. Returns [realWorkSeconds, orderProblems, reloginGaps].
     */
    private function replay(array $actions): array
    {
        $state = 'NOT_STARTED';
        $segStart = null;      // seconds when the current work segment started
        $lastOut = null;       // time string of the last END_WORKDAY
        $prevTime = null;
        $work = 0;
        $problems = [];
        $gaps = [];

        $allowed = [
            'NOT_STARTED' => ['START_WORKDAY'],
            'WORKING'     => ['START_BREAK', 'END_WORKDAY'],
            'ON_BREAK'    => ['END_BREAK'],
            'FINISHED'    => ['START_WORKDAY'],
        ];

        foreach ($actions as $a) {
            $type = $a['action_type'];
            $t    = self::toSeconds($a['action_time']);

            if ($prevTime !== null && $t < $prevTime) {
                $problems[] = "$type at {$a['action_time']} is earlier than the action before it.";
            }
            $prevTime = $t;

            if (!in_array($type, $allowed[$state], true)) {
                $problems[] = "$type at {$a['action_time']} while the agent was " . $state . '.';
                continue;
            }

            switch ($type) {
                case 'START_WORKDAY':
                    if ($state === 'FINISHED' && $lastOut !== null) {
                        $gaps[] = [$lastOut, $a['action_time'], max(0, $t - self::toSeconds($lastOut))];
                    }
                    $segStart = $t;
                    $state = 'WORKING';
                    break;
                case 'START_BREAK':
                    $work += max(0, $t - $segStart);
                    $state = 'ON_BREAK';
                    break;
                case 'END_BREAK':
                    $segStart = $t;
                    $state = 'WORKING';
                    break;
                case 'END_WORKDAY':
                    $work += max(0, $t - $segStart);
                    $lastOut = $a['action_time'];
                    $state = 'FINISHED';
                    break;
            }
        }

        return [$work, $problems, $gaps];
    }

    public static function toSeconds(string $time): int
    {
        $p = array_map('intval', explode(':', $time));
        return ($p[0] ?? 0) * 3600 + ($p[1] ?? 0) * 60 + ($p[2] ?? 0);
    }
}
