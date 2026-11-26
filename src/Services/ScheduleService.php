<?php
/**
 * Virtual Time Clock - Schedule Service (shift planning)
 *
 * Managers and administrators plan the shifts of each agent:
 *   - Schedules: named weekly patterns, e.g. "Morning team" with
 *     07:00-15:00 Monday to Friday. Each weekday can have several shifts
 *     (e.g. 08:00-12:00 and 14:00-18:00) or none (day off).
 *   - Assignments: an agent follows a schedule from a start date. The
 *     assignment with the latest start date on or before a day applies.
 *   - Day changes: one day planned differently for one agent (other hours,
 *     an extra day, or a day off). They win over the schedule.
 *   - Agents with no assignment follow the default schedule (the one marked
 *     default, or the SHIFT_* settings in config.php if none is marked).
 *
 * The time errors report, Correct Times, the attendance report and the time
 * clock read the planned shifts from here. A day with no planned shift is
 * not a working day: no absence, lateness or early departure is counted.
 *
 * Shifts start and end on the same calendar day (no overnight shifts), like
 * the rest of the app, which stores one attendance record per day.
 *
 * Rules:
 *   - Managers plan employees only, not themselves. Administrators can plan
 *     anyone.
 *   - Managers and administrators can create and edit schedules. Editing a
 *     schedule changes it for every day it is used, past days included.
 *     To change hours from a date on, create a new schedule and assign it
 *     from that date.
 */

class ScheduleService
{
    public const WEEKDAYS = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
    ];
    public const MAX_SHIFTS_PER_DAY = 4;
    public const MAX_RANGE_DAYS = 62;

    private PDO $db;
    private static ?array $defaultWeekCache = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        self::ensureTables($this->db);
    }

    /**
     * Create the schedule tables on first use if the migration was never run
     * (database/migrations/2026_11_25_schedules.sql).
     */
    public static function ensureTables(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $exists = $db->query("SHOW TABLES LIKE 'schedule_overrides'")->fetchColumn();
        if (!$exists) {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_25_schedules.sql');
            // Strip comment lines, then run each statement
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $db->exec($stmt);
            }
        }
        $checked = true;
    }

    // =================================================================
    // Pure helpers (no database), so they can be tested
    // =================================================================

    /** The schedule from config.php: SHIFT_1 and SHIFT_2, Monday to Friday. */
    public static function configWeek(): array
    {
        $day = [['start' => SHIFT_1_START, 'end' => SHIFT_1_END], ['start' => SHIFT_2_START, 'end' => SHIFT_2_END]];
        return [1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day, 6 => [], 7 => []];
    }

    /** Shifts of a week pattern for a date. */
    public static function shiftsOfWeek(array $week, string $date): array
    {
        return $week[(int) (new DateTime($date))->format('N')] ?? [];
    }

    /**
     * Check and normalize a list of shifts. Accepts [["08:00","12:00"], ...]
     * or [["start" => "08:00", "end" => "12:00"], ...]. Returns
     * [["start" => "08:00:00", "end" => "12:00:00"], ...] sorted by start.
     */
    public static function normalizeShifts($shifts): array
    {
        if (!is_array($shifts)) {
            throw new RuntimeException('Invalid shifts.');
        }
        $out = [];
        foreach ($shifts as $s) {
            if (!is_array($s)) {
                throw new RuntimeException('Invalid shift.');
            }
            $start = self::normalizeTime($s['start'] ?? $s[0] ?? '');
            $end   = self::normalizeTime($s['end'] ?? $s[1] ?? '');
            if ($start === null || $end === null) {
                throw new RuntimeException('Enter shift times as HH:MM.');
            }
            if ($end <= $start) {
                throw new RuntimeException(t('A shift must end after it starts ({hours}). Overnight shifts are not supported.', ['hours' => "$start-$end"]));
            }
            $out[] = ['start' => $start, 'end' => $end];
        }
        if (count($out) > self::MAX_SHIFTS_PER_DAY) {
            throw new RuntimeException(t('At most {n} shifts per day.', ['n' => self::MAX_SHIFTS_PER_DAY]));
        }
        usort($out, fn($a, $b) => strcmp($a['start'], $b['start']));
        for ($i = 1; $i < count($out); $i++) {
            if ($out[$i]['start'] < $out[$i - 1]['end']) {
                throw new RuntimeException(t('Shifts overlap ({a} and {b}).', [
                    'a' => substr($out[$i - 1]['start'], 0, 5) . '-' . substr($out[$i - 1]['end'], 0, 5),
                    'b' => substr($out[$i]['start'], 0, 5) . '-' . substr($out[$i]['end'], 0, 5),
                ]));
            }
        }
        return $out;
    }

    /** Normalize a week: weekday (1-7) => shifts. Missing days are days off. */
    public static function normalizeWeek($week): array
    {
        if (!is_array($week)) {
            throw new RuntimeException('Invalid schedule.');
        }
        $out = [];
        foreach (self::WEEKDAYS as $n => $name) {
            try {
                $out[$n] = self::normalizeShifts($week[$n] ?? $week[(string) $n] ?? []);
            } catch (RuntimeException $e) {
                throw new RuntimeException($name . ': ' . $e->getMessage());
            }
        }
        return $out;
    }

    private static function normalizeTime($t): ?string
    {
        $t = trim((string) $t);
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];
        $s = (int) ($m[3] ?? 0);
        if ($h > 23 || $i > 59 || $s > 59) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', $h, $i, $s);
    }

    /**
     * Planned day for an agent. Pure.
     *
     * @param array  $assignments rows with start_date, template_id (any order)
     * @param array  $templates   template_id => ['name' => ..., 'week' => normalized week]
     * @param array  $defaultWeek normalized week for agents with no assignment
     * @param ?array $override    ['shifts' => [...], 'note' => ...] or null
     */
    public static function resolve(string $date, array $assignments, array $templates, array $defaultWeek, ?string $defaultName = null, ?array $override = null): array
    {
        if ($override !== null) {
            return [
                'shifts'        => $override['shifts'],
                'source'        => 'day',
                'template_id'   => null,
                'template_name' => null,
                'note'          => $override['note'] ?? null,
            ];
        }
        $best = null;
        foreach ($assignments as $a) {
            if ($a['start_date'] <= $date && ($best === null || $a['start_date'] > $best['start_date'])) {
                $best = $a;
            }
        }
        if ($best !== null && isset($templates[$best['template_id']])) {
            $t = $templates[$best['template_id']];
            return [
                'shifts'        => self::shiftsOfWeek($t['week'], $date),
                'source'        => 'schedule',
                'template_id'   => (int) $best['template_id'],
                'template_name' => $t['name'],
                'note'          => null,
            ];
        }
        return [
            'shifts'        => self::shiftsOfWeek($defaultWeek, $date),
            'source'        => 'default',
            'template_id'   => null,
            'template_name' => $defaultName,
            'note'          => null,
        ];
    }

    /**
     * Remove the shifts covered by leave. Leave parts (see LeaveService):
     * FULL = every shift, SHIFT_1 = the first shift of the day,
     * SHIFT_2 = the shifts after the first one.
     */
    public static function withoutLeave(array $shifts, array $leaveParts): array
    {
        if (isset($leaveParts['FULL'])) {
            return [];
        }
        $out = [];
        foreach (array_values($shifts) as $i => $s) {
            if (($i === 0 && isset($leaveParts['SHIFT_1'])) || ($i > 0 && isset($leaveParts['SHIFT_2']))) {
                continue;
            }
            $out[] = $s;
        }
        return $out;
    }

    /** Seconds of planned work covered by one leave part. */
    public static function leaveSeconds(array $shifts, string $part): int
    {
        $all = self::totalSeconds($shifts);
        return $all - self::totalSeconds(self::withoutLeave($shifts, [$part => true]));
    }

    public static function totalSeconds(array $shifts): int
    {
        $t = 0;
        foreach ($shifts as $s) {
            $t += self::sec($s['end']) - self::sec($s['start']);
        }
        return $t;
    }

    /** "08:00-12:00, 14:00-18:00" or "Day off". */
    public static function label(array $shifts): string
    {
        if (!$shifts) {
            return t('Day off');
        }
        return implode(', ', array_map(fn($s) => substr($s['start'], 0, 5) . '-' . substr($s['end'], 0, 5), $shifts));
    }

    private static function sec(string $t): int
    {
        $p = array_map('intval', explode(':', $t));
        return ($p[0] ?? 0) * 3600 + ($p[1] ?? 0) * 60 + ($p[2] ?? 0);
    }

    private static function decodeWeek(string $json): array
    {
        try {
            return self::normalizeWeek(json_decode($json, true) ?: []);
        } catch (RuntimeException $e) {
            return array_fill_keys(array_keys(self::WEEKDAYS), []);
        }
    }

    private static function encodeWeek(array $week): string
    {
        $out = [];
        foreach ($week as $n => $shifts) {
            $out[(string) $n] = array_map(fn($s) => [$s['start'], $s['end']], $shifts);
        }
        return json_encode($out);
    }

    private static function encodeShifts(array $shifts): string
    {
        return json_encode(array_map(fn($s) => [$s['start'], $s['end']], $shifts));
    }

    // =================================================================
    // Reading the plan
    // =================================================================

    /** All schedules: id => ['id','name','week','is_default'] */
    public function templateMap(): array
    {
        $map = [];
        foreach ($this->db->query("SELECT id, name, week_json, is_default FROM schedule_templates ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(int) $r['id']] = [
                'id'         => (int) $r['id'],
                'name'       => $r['name'],
                'week'       => self::decodeWeek($r['week_json']),
                'is_default' => (bool) $r['is_default'],
            ];
        }
        return $map;
    }

    /** [week, name] used for agents with no schedule assigned. */
    public function defaultWeek(?array $templates = null): array
    {
        foreach ($templates ?? $this->templateMap() as $t) {
            if ($t['is_default']) {
                return [$t['week'], $t['name']];
            }
        }
        return [self::configWeek(), 'Default (config)'];
    }

    /**
     * Planned days for agents over [from, to]: user_id => date => resolve() result.
     */
    public function planFor(array $userIds, string $from, string $to): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }
        $templates = $this->templateMap();
        [$defaultWeek, $defaultName] = $this->defaultWeek($templates);

        $in = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("SELECT user_id, template_id, start_date FROM schedule_assignments WHERE user_id IN ($in) AND start_date <= ?");
        $stmt->execute(array_merge($userIds, [$to]));
        $assign = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $assign[(int) $a['user_id']][] = $a;
        }

        $stmt = $this->db->prepare("SELECT user_id, work_date, shifts_json, note FROM schedule_overrides WHERE user_id IN ($in) AND work_date BETWEEN ? AND ?");
        $stmt->execute(array_merge($userIds, [$from, $to]));
        $over = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            try {
                $shifts = self::normalizeShifts(json_decode($o['shifts_json'], true) ?: []);
            } catch (RuntimeException $e) {
                $shifts = [];
            }
            $over[(int) $o['user_id']][$o['work_date']] = ['shifts' => $shifts, 'note' => $o['note']];
        }

        $plan = [];
        foreach ($userIds as $uid) {
            for ($d = new DateTime($from); $d->format('Y-m-d') <= $to; $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                $plan[$uid][$date] = self::resolve($date, $assign[$uid] ?? [], $templates, $defaultWeek, $defaultName, $over[$uid][$date] ?? null);
            }
        }
        return $plan;
    }

    /** Planned shifts of one agent on one day. */
    public function shiftsFor(int $userId, string $date): array
    {
        return $this->planFor([$userId], $date, $date)[$userId][$date]['shifts'];
    }

    /**
     * Shifts only (user_id => date => shifts), for reports. Falls back to the
     * config schedule if anything goes wrong, so a report never breaks.
     */
    public static function shiftsForReport(PDO $db, array $userIds, string $from, string $to): array
    {
        try {
            $plan = (new self($db))->planFor($userIds, $from, $to);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($plan as $uid => $days) {
            foreach ($days as $date => $p) {
                $out[$uid][$date] = $p['shifts'];
            }
        }
        return $out;
    }

    /** Shifts of one agent for one day, never throws (config schedule on error). */
    public static function shiftsOrDefault(int $userId, string $date): array
    {
        try {
            return (new self())->shiftsFor($userId, $date);
        } catch (\Throwable $e) {
            return self::shiftsOfWeek(self::configWeek(), $date);
        }
    }

    /** Schedules with how many agents use each one now. */
    public function templates(): array
    {
        $today = TimeHelper::currentDate();
        $rows = $this->db->query("
            SELECT a.user_id, a.template_id, a.start_date FROM schedule_assignments a
            JOIN users u ON u.id = a.user_id WHERE u.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $byUser = [];
        foreach ($rows as $r) {
            $byUser[$r['user_id']][] = $r;
        }
        $current = [];
        $used = [];
        foreach ($byUser as $uid => $list) {
            $best = null;
            foreach ($list as $a) {
                $used[(int) $a['template_id']] = true;
                if ($a['start_date'] <= $today && ($best === null || $a['start_date'] > $best['start_date'])) {
                    $best = $a;
                }
            }
            if ($best) {
                $current[(int) $best['template_id']] = ($current[(int) $best['template_id']] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($this->templateMap() as $id => $t) {
            $week = [];
            foreach ($t['week'] as $n => $shifts) {
                $week[(string) $n] = $shifts;
            }
            $out[] = [
                'id'            => $id,
                'name'          => $t['name'],
                'is_default'    => $t['is_default'],
                'week'          => $week,
                'agents_now'    => $current[$id] ?? 0,
                'in_use'        => !empty($used[$id]),
                'weekly_seconds' => array_sum(array_map([self::class, 'totalSeconds'], $t['week'])),
            ];
        }
        return $out;
    }

    /** Agents the caller can plan. */
    public function agents(array $editor): array
    {
        $sql = "SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1";
        $params = [];
        if ($editor['role'] !== 'administrator') {
            $sql .= " AND r.name = 'employee' AND u.id <> ?";
            $params[] = (int) $editor['id'];
        }
        $sql .= " ORDER BY u.last_name, u.first_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Assignment history (newest first) for some agents. */
    public function assignments(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("
            SELECT a.id, a.user_id, a.template_id, a.start_date, a.created_at, t.name AS template_name,
                   b.first_name AS by_first_name, b.last_name AS by_last_name
            FROM schedule_assignments a
            JOIN schedule_templates t ON t.id = a.template_id
            LEFT JOIN users b ON b.id = a.assigned_by
            WHERE a.user_id IN ($in)
            ORDER BY a.user_id, a.start_date DESC");
        $stmt->execute($userIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =================================================================
    // Changing the plan
    // =================================================================

    public function saveTemplate(array $editor, ?int $id, string $name, $week): array
    {
        self::requirePlanner($editor);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new RuntimeException('Give the schedule a name (100 characters at most).');
        }
        $week = self::normalizeWeek($week);
        $stmt = $this->db->prepare("SELECT id FROM schedule_templates WHERE name = ? AND id <> ?");
        $stmt->execute([$name, (int) $id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException('Another schedule already has this name.');
        }
        if ($id) {
            $stmt = $this->db->prepare("UPDATE schedule_templates SET name = ?, week_json = ?, updated_by = ? WHERE id = ?");
            $stmt->execute([$name, self::encodeWeek($week), (int) $editor['id'], $id]);
            if (!$this->templateExists($id)) {
                throw new RuntimeException('Schedule not found.');
            }
        } else {
            $this->db->prepare("INSERT INTO schedule_templates (name, week_json, created_by, updated_by) VALUES (?, ?, ?, ?)")
                ->execute([$name, self::encodeWeek($week), (int) $editor['id'], (int) $editor['id']]);
            $id = (int) $this->db->lastInsertId();
        }
        return ['id' => $id];
    }

    public function deleteTemplate(array $editor, int $id): void
    {
        self::requirePlanner($editor);
        $t = $this->templateMap()[$id] ?? null;
        if (!$t) {
            throw new RuntimeException('Schedule not found.');
        }
        if ($t['is_default']) {
            throw new RuntimeException('This is the default schedule. Make another schedule the default first.');
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM schedule_assignments WHERE template_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Agents are or were assigned to this schedule, so it is kept for their past days. Assign them another schedule instead.');
        }
        $this->db->prepare("DELETE FROM schedule_templates WHERE id = ?")->execute([$id]);
    }

    /** Make a schedule the one used by agents with no schedule assigned. */
    public function setDefault(array $editor, int $id): void
    {
        self::requirePlanner($editor);
        if (!$this->templateExists($id)) {
            throw new RuntimeException('Schedule not found.');
        }
        $this->db->beginTransaction();
        $this->db->exec("UPDATE schedule_templates SET is_default = 0");
        $this->db->prepare("UPDATE schedule_templates SET is_default = 1, updated_by = ? WHERE id = ?")->execute([(int) $editor['id'], $id]);
        $this->db->commit();
    }

    /** The agent follows this schedule from $startDate on. */
    public function assign(array $editor, int $userId, int $templateId, string $startDate): void
    {
        $this->checkPermission($editor, $userId);
        self::requireDate($startDate);
        if (!$this->templateExists($templateId)) {
            throw new RuntimeException('Schedule not found.');
        }
        $this->db->prepare("
            INSERT INTO schedule_assignments (user_id, template_id, start_date, assigned_by) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE template_id = VALUES(template_id), assigned_by = VALUES(assigned_by), created_at = CURRENT_TIMESTAMP
        ")->execute([$userId, $templateId, $startDate, (int) $editor['id']]);
    }

    public function removeAssignment(array $editor, int $assignmentId): void
    {
        $stmt = $this->db->prepare("SELECT user_id FROM schedule_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $uid = $stmt->fetchColumn();
        if ($uid === false) {
            throw new RuntimeException('Assignment not found.');
        }
        $this->checkPermission($editor, (int) $uid);
        $this->db->prepare("DELETE FROM schedule_assignments WHERE id = ?")->execute([$assignmentId]);
    }

    /** Plan one day differently. An empty list of shifts = day off. */
    public function setDay(array $editor, int $userId, string $date, $shifts, string $note = ''): void
    {
        $this->checkPermission($editor, $userId);
        self::requireDate($date);
        $shifts = self::normalizeShifts($shifts);
        $note = trim($note);
        if (mb_strlen($note) > 255) {
            throw new RuntimeException('The note is too long (255 characters at most).');
        }
        $this->db->prepare("
            INSERT INTO schedule_overrides (user_id, work_date, shifts_json, note, created_by) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE shifts_json = VALUES(shifts_json), note = VALUES(note), created_by = VALUES(created_by)
        ")->execute([$userId, $date, self::encodeShifts($shifts), $note ?: null, (int) $editor['id']]);
    }

    /** Back to the agent's schedule for that day. */
    public function clearDay(array $editor, int $userId, string $date): void
    {
        $this->checkPermission($editor, $userId);
        self::requireDate($date);
        $this->db->prepare("DELETE FROM schedule_overrides WHERE user_id = ? AND work_date = ?")->execute([$userId, $date]);
    }

    // =================================================================
    // Undo (the "Undo" pop-up after a schedule change, see UndoService)
    // =================================================================

    /** [user_id, start_date] of an assignment, or null if it does not exist. */
    public function assignmentKey(int $assignmentId): ?array
    {
        $stmt = $this->db->prepare("SELECT user_id, start_date FROM schedule_assignments WHERE id = ?");
        $stmt->execute([$assignmentId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? [(int) $r['user_id'], $r['start_date']] : null;
    }

    /**
     * The assignment rows and day-change rows at these keys, as they are now
     * (null where there is none). Keys are [user_id, date] pairs.
     */
    public function snapshot(array $assignmentKeys, array $overrideKeys): array
    {
        $out = ['assignments' => [], 'overrides' => []];
        $stmt = $this->db->prepare("SELECT id, user_id, template_id, start_date, assigned_by, created_at FROM schedule_assignments WHERE user_id = ? AND start_date = ?");
        foreach ($assignmentKeys as [$uid, $date]) {
            $stmt->execute([(int) $uid, $date]);
            $out['assignments'][] = ['user_id' => (int) $uid, 'date' => $date, 'row' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null];
        }
        $stmt = $this->db->prepare("SELECT id, user_id, work_date, shifts_json, note, created_by, created_at, updated_at FROM schedule_overrides WHERE user_id = ? AND work_date = ?");
        foreach ($overrideKeys as [$uid, $date]) {
            $stmt->execute([(int) $uid, $date]);
            $out['overrides'][] = ['user_id' => (int) $uid, 'date' => $date, 'row' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null];
        }
        return $out;
    }

    /** Same rows, ignoring the time stamps. */
    private static function sameSnapshot(array $a, array $b): bool
    {
        $norm = function (array $snap): array {
            $keep = ['id', 'template_id', 'shifts_json', 'note'];
            $out = [];
            foreach (['assignments', 'overrides'] as $k) {
                foreach ($snap[$k] ?? [] as $e) {
                    $row = $e['row'] ? array_map('strval', array_intersect_key($e['row'], array_flip($keep))) : null;
                    if ($row) {
                        ksort($row);
                    }
                    $out[] = [$k, (int) $e['user_id'], $e['date'], $row];
                }
            }
            return $out;
        };
        return $norm($a) === $norm($b);
    }

    /**
     * Put the rows back as they were before a change: $p = ['before' => snapshot,
     * 'after' => snapshot]. Refused if someone changed them again since.
     * Runs inside UndoService's transaction.
     */
    public function restoreSnapshot(array $editor, array $p): void
    {
        $before = $p['before'] ?? [];
        $after = $p['after'] ?? [];
        $users = [];
        foreach (['assignments', 'overrides'] as $k) {
            foreach ($before[$k] ?? [] as $e) {
                $users[(int) $e['user_id']] = true;
            }
        }
        foreach (array_keys($users) as $uid) {
            $this->checkPermission($editor, $uid);
        }

        $keys = fn(array $snap, string $k) => array_map(fn($e) => [(int) $e['user_id'], $e['date']], $snap[$k] ?? []);
        $now = $this->snapshot($keys($after, 'assignments'), $keys($after, 'overrides'));
        if (!self::sameSnapshot($now, $after)) {
            throw new RuntimeException('This schedule changed again since. Undo is not possible; change it by hand.');
        }

        $delA = $this->db->prepare("DELETE FROM schedule_assignments WHERE user_id = ? AND start_date = ?");
        $insA = $this->db->prepare("INSERT INTO schedule_assignments (id, user_id, template_id, start_date, assigned_by, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($before['assignments'] ?? [] as $e) {
            $delA->execute([(int) $e['user_id'], $e['date']]);
            if ($r = $e['row']) {
                if (!$this->templateExists((int) $r['template_id'])) {
                    throw new RuntimeException('The schedule that was assigned no longer exists. Undo is not possible.');
                }
                $insA->execute([(int) $r['id'], (int) $r['user_id'], (int) $r['template_id'], $r['start_date'], $r['assigned_by'], $r['created_at']]);
            }
        }
        $delO = $this->db->prepare("DELETE FROM schedule_overrides WHERE user_id = ? AND work_date = ?");
        $insO = $this->db->prepare("INSERT INTO schedule_overrides (id, user_id, work_date, shifts_json, note, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($before['overrides'] ?? [] as $e) {
            $delO->execute([(int) $e['user_id'], $e['date']]);
            if ($r = $e['row']) {
                $insO->execute([(int) $r['id'], (int) $r['user_id'], $r['work_date'], $r['shifts_json'], $r['note'], $r['created_by'], $r['created_at'], $r['updated_at']]);
            }
        }
    }

    private function templateExists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM schedule_templates WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private static function requirePlanner(array $editor): void
    {
        if (!in_array($editor['role'], ['administrator', 'manager'], true)) {
            throw new RuntimeException('Only managers and administrators can plan schedules.');
        }
    }

    private static function requireDate(string $d): void
    {
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) {
            throw new RuntimeException('Enter a valid date.');
        }
    }

    public function checkPermission(array $editor, int $userId): void
    {
        self::requirePlanner($editor);
        $stmt = $this->db->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        if ($role === false) {
            throw new RuntimeException('Agent not found.');
        }
        if ($editor['role'] === 'manager') {
            if ((int) $editor['id'] === $userId) {
                throw new RuntimeException('You cannot plan your own schedule. Ask an administrator.');
            }
            if ($role !== 'employee') {
                throw new RuntimeException('Managers can only plan schedules for employees.');
            }
        }
    }
}
