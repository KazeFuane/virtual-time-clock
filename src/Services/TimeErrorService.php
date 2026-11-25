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
 *   LATE_ARRIVAL          First clock-in after the start of the planned shift
 *                         it falls in (the first planned shift that has not
 *                         ended yet at the clock-in time).
 *   EARLY_DEPARTURE       Last clock-out before the end of the last planned
 *                         shift of the day.
 *   ABSENCE               Planned shift with no work, counted per shift once
 *                         the shift has ended (today included): no clock-in =
 *                         every planned shift, first clock-in at/after a
 *                         shift's end = that shift. Only from the day after
 *                         the account was created. Shifts covered by a
 *                         recorded leave are not absences.
 *   WORKED_ON_LEAVE       Clocked in on a day recorded as full-day leave.
 *
 * Leave / PTO (see LeaveService) is not an error. Each working day of leave
 * is listed per agent under 'leave_days', with hours from the agent's
 * schedule, and summed by type in 'leave_counts' / 'leave_seconds'.
 * On a half-day leave the late/early checks use the shift that is worked.
 *
 * Planned shifts come from ScheduleService (each agent's schedule, day
 * changes, or the default schedule). A day with no planned shift is not a
 * working day: no absence, lateness or early departure is counted on it.
 * Without a plan (tests), the SHIFT_* settings apply Monday to Friday.
 *
 * Only active accounts with the employee role are checked. Managers and
 * administrators are not required to clock in, so they are left out.
 *
 * Every incident carries impact_seconds when a duration makes sense; the
 * per-agent 'durations' array sums them by error code.
 */

require_once __DIR__ . '/LeaveService.php';
require_once __DIR__ . '/ScheduleService.php';

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
        'WORKED_ON_LEAVE'      => 'Clocked in on a leave day',
    ];

    private ?PDO $db;

    /** All error labels in the user's language (code => label). */
    public static function labels(): array
    {
        return array_map(fn($l) => t($l), self::LABELS);
    }

    /** One error label in the user's language. */
    public static function label(string $code): string
    {
        return isset(self::LABELS[$code]) ? t(self::LABELS[$code]) : $code;
    }

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

        $leave = LeaveService::expand(LeaveService::activeRows($db, $from, $to, $userId), $from, $to);
        $plan = ScheduleService::shiftsForReport($db, array_column($users, 'id'), $from, $to);

        return $this->buildSummary($users, $days, $actions, $breaks, TimeHelper::currentDate(), $from, $to, null, $leave, $plan);
    }

    /**
     * Pure summary builder (no database), so it can be tested.
     *
     * @param array $users   rows: id, username, first_name, last_name, role
     * @param array $days    attendance_days rows
     * @param array $actions attendance_day_id => time_actions rows (in insert order)
     * @param array $breaks  attendance_day_id => breaks rows
     * @param array $leave   LeaveService::expand() result: user_id => date => part => leave row
     * @param ?array $plan   user_id => date => planned shifts [['start','end'], ...]
     *                       (ScheduleService::shiftsForReport). Missing = SHIFT_* Mon-Fri.
     */
    public function buildSummary(array $users, array $days, array $actions, array $breaks, string $today, string $from, string $to, ?string $now = null, array $leave = [], ?array $plan = null): array
    {
        $planned = fn($uid, string $date): array => $plan[$uid][$date] ?? self::defaultShifts($date);
        $leaveTypes = array_keys(LeaveService::TYPES);
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
                // Leave is not an error: kept apart from counts/error_count
                'leave_counts'          => array_fill_keys($leaveTypes, 0),   // days (a half day = 0.5)
                'leave_seconds'         => array_fill_keys($leaveTypes, 0),
                'paid_leave_seconds'    => 0,
                'unpaid_leave_seconds'  => 0,
                'leave_days'            => [],
            ];
        }

        foreach ($days as $day) {
            $uid = $day['user_id'];
            if (!isset($byUser[$uid])) {
                continue; // inactive or filtered out
            }
            $result = $this->checkDay($day, $actions[$day['id']] ?? [], $breaks[$day['id']] ?? [], $today, $leave[$uid][$day['work_date']] ?? [], $planned($uid, $day['work_date']));
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

        // Absences, per planned shift (employees only). A shift counts once it
        // has ended: every shift of a past day, and today's shifts after their
        // end time. No clock-in at all = every planned shift; a first clock-in
        // at or after the end of a shift = that shift missed. Shifts on leave
        // are not expected.
        $now = $now ?? TimeHelper::currentTime();
        $firstIn = [];
        foreach ($days as $day) {
            if (!empty($day['start_time'])) {
                $firstIn[$day['user_id']][$day['work_date']] = $day['start_time'];
            }
        }
        $hm = fn($t) => substr($t, 0, 5);
        $span = fn(array $sh) => $hm($sh['start']) . '-' . $hm($sh['end']);
        foreach ($byUser as $uid => &$s) {
            if (($s['role'] ?? 'employee') !== 'employee') {
                continue;
            }
            for ($d = new DateTime($from); $d->format('Y-m-d') <= $to && $d->format('Y-m-d') <= $today; $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                // Not absent before the account existed, nor on the day it was
                // created (the account may have been made after the shift started)
                if ($s['created_on'] !== null && $date <= $s['created_on']) {
                    continue;
                }
                $shifts = $planned($uid, $date);
                if (!$shifts) {
                    continue; // nothing planned: not a working day
                }
                $off = $leave[$uid][$date] ?? [];
                $expected = ScheduleService::withoutLeave($shifts, $off);
                $isPast = $date < $today;
                $in = $firstIn[$uid][$date] ?? null;

                // Name a shift: with two shifts a day, "morning"/"afternoon"
                // (like leave parts); otherwise by its hours.
                $name = fn(array $sh) => count($shifts) === 2
                    ? ($sh === $shifts[0]
                        ? t('morning shift ({hours})', ['hours' => $span($sh)])
                        : t('afternoon shift ({hours})', ['hours' => $span($sh)]))
                    : t('shift {hours}', ['hours' => $span($sh)]);
                $missed = [];
                foreach ($expected as $sh) {
                    $ended = $isPast || self::toSeconds($now) >= self::toSeconds($sh['end']);
                    if ($ended && ($in === null || self::toSeconds($in) >= self::toSeconds($sh['end']))) {
                        $missed[] = $sh;
                    }
                }
                if (!$missed) {
                    continue;
                }
                $secs = ScheduleService::totalSeconds($missed);
                $names = implode(' ' . t('and') . ' ', array_map($name, $missed));
                $leaveShifts = array_values(array_filter($shifts, fn($sh) => !in_array($sh, $expected, true)));
                $leaveNames = implode(' ' . t('and') . ' ', array_map($name, $leaveShifts));
                $onLeave = $leaveShifts ? ' ' . (count($leaveShifts) > 1
                    ? t('The {shifts} are on leave.', ['shifts' => $leaveNames])
                    : t('The {shifts} is on leave.', ['shifts' => $leaveNames])) : '';
                if ($in === null && count($missed) === count($shifts)) {
                    $detail = t('No clock-in on this working day (planned {shifts}).', ['shifts' => ScheduleService::label($shifts)]);
                } elseif ($in === null) {
                    $detail = t('No clock-in for the {shifts}.', ['shifts' => $names]) . $onLeave;
                } else {
                    $detail = t('Missed the {shifts}; first clock-in at {time}.', ['shifts' => $names, 'time' => $hm($in)]) . $onLeave;
                }

                $hadErrors = false;
                foreach ($s['incidents'] as $i) {
                    if ($i['work_date'] === $date) {
                        $hadErrors = true;
                        break;
                    }
                }
                $s['error_count']++;
                if (!$hadErrors) {
                    $s['days_with_errors']++;
                }
                $s['counts']['ABSENCE']++;
                $s['durations']['ABSENCE'] += $secs;
                $s['incidents'][] = [
                    'work_date'      => $date,
                    'code'           => 'ABSENCE',
                    'label'          => self::label('ABSENCE'),
                    'detail'         => $detail,
                    'impact_seconds' => $secs,
                ];
            }
            usort($s['incidents'], fn($a, $b) => strcmp($a['work_date'], $b['work_date']));
        }
        unset($s);

        // Leave days (every planned working day in the period, future days included)
        foreach ($byUser as $uid => &$s) {
            foreach ($leave[$uid] ?? [] as $date => $parts) {
                if ($date < $from || $date > $to) {
                    continue;
                }
                $shifts = $planned($uid, $date);
                if (!$shifts) {
                    continue; // no shift planned that day: nothing taken off
                }
                foreach ($parts as $part => $row) {
                    $type = $row['leave_type'];
                    $secs = ScheduleService::leaveSeconds($shifts, $part);
                    if ($secs === 0) {
                        continue; // e.g. afternoon leave on a day with one shift
                    }
                    $paid = LeaveService::isPaid($type);
                    $s['leave_counts'][$type] = ($s['leave_counts'][$type] ?? 0) + ($part === 'FULL' ? 1 : 0.5);
                    $s['leave_seconds'][$type] = ($s['leave_seconds'][$type] ?? 0) + $secs;
                    $s[$paid ? 'paid_leave_seconds' : 'unpaid_leave_seconds'] += $secs;
                    $s['leave_days'][] = [
                        'work_date'  => $date,
                        'leave_id'   => (int) $row['id'],
                        'type'       => $type,
                        'label'      => LeaveService::typeLabel($type),
                        'part'       => $part,
                        'part_label' => LeaveService::partLabel($part),
                        'paid'       => $paid,
                        'seconds'    => $secs,
                        'planned'    => $date > $today,
                        'note'       => (string) ($row['note'] ?? ''),
                    ];
                }
            }
            usort($s['leave_days'], fn($a, $b) => [$a['work_date'], $a['part']] <=> [$b['work_date'], $b['part']]);
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

        $leaveTotals = array_fill_keys($leaveTypes, 0);
        foreach ($agents as $a) {
            foreach ($a['leave_seconds'] as $type => $secs) {
                $leaveTotals[$type] = ($leaveTotals[$type] ?? 0) + $secs;
            }
        }

        return [
            'from'       => $from,
            'to'         => $to,
            'labels'     => self::labels(),
            'leave_labels' => array_map(fn($t) => $t['label'], LeaveService::types()),
            'leave_totals' => $leaveTotals,
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
    public function checkDay(array $day, array $actions, array $breaks, string $today, array $leaveParts = [], ?array $planned = null): ?array
    {
        $planned  = $planned ?? self::defaultShifts($day['work_date']);
        // Shifts the agent was expected to work (planned, minus leave)
        $expectedShifts = ScheduleService::withoutLeave($planned, $leaveParts);
        $lastPlannedEnd = $expectedShifts ? end($expectedShifts)['end'] : ($planned ? end($planned)['end'] : SHIFT_2_END);
        $isPast = $day['work_date'] < $today;
        $status = $day['status'];
        if (!$isPast && $status !== 'FINISHED') {
            return null;
        }

        $errors = [];
        $add = function (string $code, string $detail, ?int $impact = null) use (&$errors) {
            $errors[] = [
                'code'           => $code,
                'label'          => self::label($code),
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
            $add('MISSING_LOGIN', t('Day has status {status} and {n} action(s) but no clock-in.', ['status' => $status, 'n' => count($actions)]));
            $verifiable = false;
        } elseif (empty($day['start_time']) && $status !== 'NOT_STARTED') {
            $add('MISSING_LOGIN', t('Clock-in action exists but the day has no start time.'));
            $verifiable = false;
        }

        // --- Missing logout
        if ($isPast && in_array($status, ['WORKING', 'ON_BREAK'], true)) {
            $last = end($actions);
            $dayEnd = $lastPlannedEnd;
            $open = $last ? max(0, self::toSeconds($dayEnd) - self::toSeconds($last['action_time'])) : 0;
            $add('MISSING_LOGOUT', t('Day was left {status}', ['status' => $status])
                . ($last ? ' (' . t('last action {action} at {time}', ['action' => $last['action_type'], 'time' => $last['action_time']]) . ')' : '')
                . '. ' . t('Duration shown is from the last action to {time}.', ['time' => substr($dayEnd, 0, 5)]), $open ?: null);
            $verifiable = false;
        } elseif ($status === 'FINISHED' && !$hasEnd) {
            $add('MISSING_LOGOUT', t('Day is marked FINISHED but has no clock-out action.'));
            $verifiable = false;
        }

        // --- Open breaks
        foreach ($breaks as $b) {
            if (empty($b['end_time']) && ($isPast || $status === 'FINISHED')) {
                $add('OPEN_BREAK', t('Break started at {time} was never ended.', ['time' => $b['start_time']]));
            }
        }

        // --- Walk the actions in the order they happened (insert order)
        [$actual, $orderProblems, $reloginGaps] = $this->replay($actions);
        foreach ($orderProblems as $p) {
            $add('OUT_OF_ORDER', $p);
        }
        foreach ($reloginGaps as [$out, $in, $gap]) {
            // Clocking out at the end of one planned shift and back in for the
            // next one is the normal schedule, not an error.
            $between = false;
            for ($i = 1; $i < count($planned); $i++) {
                if (self::toSeconds($out) >= self::toSeconds($planned[$i - 1]['end']) && self::toSeconds($in) <= self::toSeconds($planned[$i]['start'])) {
                    $between = true;
                    break;
                }
            }
            if ($between) {
                continue;
            }
            $add('RELOGIN_AFTER_LOGOUT', t('Logged out at {out} and back in at {in} ({duration} logged out).', ['out' => $out, 'in' => $in, 'duration' => TimeHelper::formatDuration($gap)]), $gap);
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
                    $add('OUT_OF_ORDER', t('Break ends ({end}) before it starts ({start}).', ['end' => $b['end_time'], 'start' => $b['start_time']]));
                    $verifiable = false;
                } else {
                    $realBreak += $len;
                }
            }
        }
        if (count($breaks) > MAX_BREAKS_PER_DAY) {
            $add('EXTRA_BREAKS', t('{n} breaks taken, policy allows {max}.', ['n' => count($breaks), 'max' => MAX_BREAKS_PER_DAY]));
        }
        if ($realBreak > $allowance + self::BREAK_GRACE_SECONDS) {
            $excess = $realBreak - $allowance;
            $add('BREAK_OVER_LIMIT', t('Real break time {real}, allowance {allowed}. Over by {over}.', ['real' => TimeHelper::formatDuration($realBreak), 'allowed' => TimeHelper::formatDuration($allowance), 'over' => TimeHelper::formatDuration($excess)]), $excess);
        }

        // --- End before start on the day itself
        if (!empty($day['start_time']) && !empty($day['end_time']) && self::toSeconds($day['end_time']) < self::toSeconds($day['start_time'])) {
            $add('OUT_OF_ORDER', t('Clock-out ({out}) is earlier than clock-in ({in}).', ['out' => $day['end_time'], 'in' => $day['start_time']]));
            $verifiable = false;
        }

        // --- Stored total vs its own start/end/break values
        $recorded = (int) $day['total_work_seconds'];
        // Only for single-session days: with a re-login, end - start includes the logged-out gap.
        if ($status === 'FINISHED' && $starts === 1 && !empty($day['start_time']) && !empty($day['end_time'])) {
            $expected = max(0, self::toSeconds($day['end_time']) - self::toSeconds($day['start_time']) - (int) $day['total_break_seconds']);
            if (abs($expected - $recorded) > self::MISMATCH_TOLERANCE) {
                $add('TOTAL_MISMATCH', t('Stored work total {stored} but start/end/break give {expected}.', ['stored' => TimeHelper::formatDuration($recorded), 'expected' => TimeHelper::formatDuration($expected)]), $recorded - $expected);
            }
        }

        // --- Leave: full day off but clocked in; half day changes the expected times
        $fullLeave = isset($leaveParts['FULL']);
        if ($fullLeave && $starts > 0) {
            $row = $leaveParts['FULL'];
            $add('WORKED_ON_LEAVE', t('Day is recorded as {leave} (full day) but the agent clocked in at {time}. Cancel the leave or correct the times.', ['leave' => mb_strtolower(LeaveService::typeLabel($row['leave_type'])), 'time' => $day['start_time'] ?? '?']), $actual ?: null);
        }

        // --- Late arrival (first clock-in)
        // The shift the clock-in belongs to is the first expected shift that
        // has not ended yet at that time.
        if (!$fullLeave && $expectedShifts && !empty($day['start_time'])) {
            $in = self::toSeconds($day['start_time']);
            $shiftStart = null;
            foreach ($expectedShifts as $sh) {
                if ($in < self::toSeconds($sh['end'])) {
                    $shiftStart = $sh['start'];
                    break;
                }
            }
            $delay = $shiftStart === null ? 0 : $in - self::toSeconds($shiftStart);
            if ($delay > LATE_TOLERANCE_SECONDS) {
                $add('LATE_ARRIVAL', t('Clocked in at {in}, shift starts at {start}.', ['in' => $day['start_time'], 'start' => $shiftStart]), $delay);
            }
        }

        // --- Early departure (last clock-out)
        if (!$fullLeave && $expectedShifts && $status === 'FINISHED' && $hasEnd) {
            $lastOut = null;
            foreach ($actions as $a) {
                if ($a['action_type'] === 'END_WORKDAY') {
                    $lastOut = $a['action_time'];
                }
            }
            $shiftEnd = $lastPlannedEnd;
            $early = self::toSeconds($shiftEnd) - self::toSeconds($lastOut);
            if ($early > 0) {
                $add('EARLY_DEPARTURE', t('Clocked out at {out}, shift ends at {end}.', ['out' => $lastOut, 'end' => $shiftEnd]), $early);
            }
        }

        // --- Plausibility of real work time
        if ($verifiable && $status === 'FINISHED') {
            if ($actual > self::LONG_SHIFT_SECONDS) {
                $add('LONG_SHIFT', t('Real work time {time}.', ['time' => TimeHelper::formatDuration($actual)]));
            } elseif ($actual < self::SHORT_SHIFT_SECONDS) {
                $add('SHORT_SHIFT', t('Real work time {time}.', ['time' => TimeHelper::formatDuration($actual)]));
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
                $problems[] = t('{action} at {time} is earlier than the action before it.', ['action' => $type, 'time' => $a['action_time']]);
            }
            $prevTime = $t;

            if (!in_array($type, $allowed[$state], true)) {
                $problems[] = t('{action} at {time} while the agent was {state}.', ['action' => $type, 'time' => $a['action_time'], 'state' => $state]);
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

    /** Shifts from the SHIFT_* settings, Monday to Friday (used when no plan is given). */
    public static function defaultShifts(string $date): array
    {
        return ScheduleService::shiftsOfWeek(ScheduleService::configWeek(), $date);
    }

    public static function toSeconds(string $time): int
    {
        $p = array_map('intval', explode(':', $time));
        return ($p[0] ?? 0) * 3600 + ($p[1] ?? 0) * 60 + ($p[2] ?? 0);
    }
}
