<?php
/**
 * Virtual Time Clock - Leave Service (PTO / paid time off)
 *
 * Managers and administrators record leave for an agent: a full day, the
 * morning or afternoon shift of one day, or a date range of full days.
 * Leave is never an error. The time errors report shows those days as leave,
 * with the hours counted as paid (or unpaid) leave, instead of an absence.
 *
 * Leave stores WHICH PART of the schedule is off (FULL, SHIFT_1, SHIFT_2),
 * not a number of hours. The hours are worked out from the agent's schedule
 * when the report is built (see scheduleFor()), so the same rows keep working
 * if agents later get their own schedules.
 *
 * Rules:
 *   - Managers record leave for employees only, not for themselves.
 *     Administrators can record leave for anyone.
 *   - A half day (morning or afternoon) is for a single date.
 *   - Leave cannot overlap another active leave of the same agent.
 *   - Cancelling keeps the row (who, when, why) for the audit trail.
 *   - Only days with planned shifts (see ScheduleService) count as leave
 *     days. A range must include at least one weekday (Mon-Fri).
 */

class LeaveService
{
    /** type => [label, paid] (edit here to add or rename types) */
    public const TYPES = [
        'PTO'     => ['label' => 'Paid time off',  'paid' => true],
        'SICK'    => ['label' => 'Sick leave',     'paid' => true],
        'SPECIAL' => ['label' => 'Special leave',  'paid' => true],   // family events, etc.
        'UNPAID'  => ['label' => 'Unpaid leave',   'paid' => false],
    ];

    public const PARTS = [
        'FULL'    => 'Full day',
        'SHIFT_1' => 'Morning shift',
        'SHIFT_2' => 'Afternoon shift',
    ];

    public const MAX_DAYS = 366;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    /**
     * Create the leaves table on first use if the migration was never run
     * (database/migrations/2026_11_25_leaves.sql).
     */
    private function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $exists = $this->db->query("SHOW TABLES LIKE 'leaves'")->fetchColumn();
        if (!$exists) {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_25_leaves.sql');
            $sql = substr($sql, strpos($sql, 'CREATE TABLE'));
            $this->db->exec(rtrim(trim($sql), ';'));
        }
        $checked = true;
    }

    /** Leave type name in the user's language. */
    public static function typeLabel(string $type): string
    {
        return isset(self::TYPES[$type]) ? t(self::TYPES[$type]['label']) : $type;
    }

    /** Part of the day (Full day / Morning shift / Afternoon shift) in the user's language. */
    public static function partLabel(string $part): string
    {
        return isset(self::PARTS[$part]) ? t(self::PARTS[$part]) : $part;
    }

    /** TYPES with labels in the user's language. */
    public static function types(): array
    {
        $out = [];
        foreach (self::TYPES as $k => $v) {
            $out[$k] = ['label' => t($v['label'])] + $v;
        }
        return $out;
    }

    /** PARTS with labels in the user's language. */
    public static function parts(): array
    {
        return array_map(fn($l) => t($l), self::PARTS);
    }

    public static function isPaid(string $type): bool
    {
        return self::TYPES[$type]['paid'] ?? true;
    }

    /**
     * Seconds of each leave part for an agent on a date, from the agent's
     * planned shifts (ScheduleService): SHIFT_1 = first shift of the day,
     * SHIFT_2 = the shifts after it, FULL = all of them.
     */
    public static function scheduleFor(int $userId, string $date): array
    {
        require_once __DIR__ . '/ScheduleService.php';
        $shifts = ScheduleService::shiftsOrDefault($userId, $date);
        return [
            'SHIFT_1' => ScheduleService::leaveSeconds($shifts, 'SHIFT_1'),
            'SHIFT_2' => ScheduleService::leaveSeconds($shifts, 'SHIFT_2'),
            'FULL'    => ScheduleService::totalSeconds($shifts),
        ];
    }

    public static function isWorkingDay(string $date): bool
    {
        return (int) (new DateTime($date))->format('N') <= 5;
    }

    /**
     * Agents the caller may record leave for.
     */
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

    /**
     * Leave rows overlapping [from, to], newest first.
     */
    public function list(?int $userId = null, ?string $from = null, ?string $to = null, bool $withCancelled = false, int $limit = 200): array
    {
        $sql = "SELECT l.*, u.username, u.first_name, u.last_name,
                       c.first_name AS creator_first_name, c.last_name AS creator_last_name,
                       x.first_name AS canceller_first_name, x.last_name AS canceller_last_name
                FROM leaves l
                JOIN users u ON u.id = l.user_id
                LEFT JOIN users c ON c.id = l.created_by
                LEFT JOIN users x ON x.id = l.cancelled_by
                WHERE 1 = 1";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND l.user_id = ?";
            $params[] = $userId;
        }
        if ($from !== null) {
            $sql .= " AND l.end_date >= ?";
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= " AND l.start_date <= ?";
            $params[] = $to;
        }
        if (!$withCancelled) {
            $sql .= " AND l.cancelled_at IS NULL";
        }
        $sql .= " ORDER BY l.start_date DESC, l.id DESC LIMIT " . max(1, min(1000, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([self::class, 'decorate'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Active leave rows overlapping [from, to] (for the report). Returns []
     * when the table does not exist yet, so the report never breaks.
     */
    public static function activeRows(PDO $db, string $from, string $to, ?int $userId = null): array
    {
        try {
            if (!$db->query("SHOW TABLES LIKE 'leaves'")->fetchColumn()) {
                return [];
            }
            $sql = "SELECT id, user_id, leave_type, start_date, end_date, day_part, note
                    FROM leaves WHERE cancelled_at IS NULL AND end_date >= ? AND start_date <= ?";
            $params = [$from, $to];
            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            $stmt = $db->prepare($sql . " ORDER BY start_date, id");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Spread leave rows over the days of the range. Pure, so it can be tested.
     * Returns [user_id][date] => [part => row], part being FULL, SHIFT_1 or SHIFT_2.
     * Every calendar day is listed: whether it is a working day depends on
     * the agent's planned shifts (ScheduleService), and days with no shift
     * planned count no leave hours.
     */
    public static function expand(array $rows, string $from, string $to): array
    {
        $out = [];
        foreach ($rows as $r) {
            $start = max($r['start_date'], $from);
            $end = min($r['end_date'], $to);
            for ($d = new DateTime($start); $d->format('Y-m-d') <= $end; $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                $out[(int) $r['user_id']][$date][$r['day_part']] = $r;
            }
        }
        return $out;
    }

    /**
     * Record leave. Throws RuntimeException on bad input.
     */
    public function create(array $editor, int $userId, string $type, string $start, string $end, string $part, string $note = ''): array
    {
        $this->checkPermission($editor, $userId);
        $clean = self::validate($type, $start, $end, $part, $note);

        $stmt = $this->db->prepare("
            SELECT id, leave_type, start_date, end_date, day_part FROM leaves
            WHERE user_id = ? AND cancelled_at IS NULL AND end_date >= ? AND start_date <= ?");
        $stmt->execute([$userId, $clean['start_date'], $clean['end_date']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
            if ($o['day_part'] === 'FULL' || $clean['day_part'] === 'FULL' || $o['day_part'] === $clean['day_part']) {
                $when = $o['start_date'] === $o['end_date'] ? $o['start_date'] : t('{from} to {to}', ['from' => $o['start_date'], 'to' => $o['end_date']]);
                throw new RuntimeException(t('This agent already has {leave} on {when}', ['leave' => mb_strtolower(self::typeLabel($o['leave_type'])), 'when' => $when])
                    . ($o['day_part'] !== 'FULL' ? ' (' . mb_strtolower(self::partLabel($o['day_part'])) . ')' : '') . '. ' . t('Cancel it first or pick other dates.'));
            }
        }

        $this->db->prepare("
            INSERT INTO leaves (user_id, leave_type, start_date, end_date, day_part, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $clean['leave_type'], $clean['start_date'], $clean['end_date'], $clean['day_part'], $clean['note'] ?: null, (int) $editor['id']]);

        return $this->get((int) $this->db->lastInsertId());
    }

    /**
     * Cancel a leave (kept for the audit trail).
     */
    public function cancel(array $editor, int $id, string $reason = ''): array
    {
        $leave = $this->get($id);
        if (!$leave) {
            throw new RuntimeException('Leave not found.');
        }
        $this->checkPermission($editor, (int) $leave['user_id']);
        if ($leave['cancelled_at']) {
            throw new RuntimeException('This leave is already cancelled.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) > 1000) {
            throw new RuntimeException('The reason is too long (1000 characters at most).');
        }
        $this->db->prepare("UPDATE leaves SET cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ?")
            ->execute([(int) $editor['id'], $reason ?: null, $id]);
        return $this->get($id);
    }

    /**
     * Undo "Record leave" (the "Undo" pop-up, see UndoService): the leave
     * was recorded by mistake a few seconds ago, so it is removed outright.
     */
    public function undoCreate(array $editor, int $id): void
    {
        $leave = $this->get($id);
        if (!$leave) {
            throw new RuntimeException('Leave not found.');
        }
        $this->checkPermission($editor, (int) $leave['user_id']);
        if ($leave['cancelled_at']) {
            throw new RuntimeException('This leave was cancelled since. Undo is not possible.');
        }
        $this->db->prepare("DELETE FROM leaves WHERE id = ?")->execute([$id]);
    }

    /**
     * Undo "Cancel leave": the leave is active again, unless other leave was
     * recorded on the same days since.
     */
    public function undoCancel(array $editor, int $id): void
    {
        $leave = $this->get($id);
        if (!$leave) {
            throw new RuntimeException('Leave not found.');
        }
        $this->checkPermission($editor, (int) $leave['user_id']);
        if (!$leave['cancelled_at']) {
            throw new RuntimeException('This leave is already active.');
        }
        $stmt = $this->db->prepare("
            SELECT day_part FROM leaves
            WHERE user_id = ? AND id <> ? AND cancelled_at IS NULL AND end_date >= ? AND start_date <= ?");
        $stmt->execute([(int) $leave['user_id'], $id, $leave['start_date'], $leave['end_date']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $part) {
            if ($part === 'FULL' || $leave['day_part'] === 'FULL' || $part === $leave['day_part']) {
                throw new RuntimeException('Other leave was recorded on these days since. Undo is not possible.');
            }
        }
        $this->db->prepare("UPDATE leaves SET cancelled_at = NULL, cancelled_by = NULL, cancel_reason = NULL WHERE id = ?")
            ->execute([$id]);
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.username, u.first_name, u.last_name,
                   c.first_name AS creator_first_name, c.last_name AS creator_last_name,
                   x.first_name AS canceller_first_name, x.last_name AS canceller_last_name
            FROM leaves l
            JOIN users u ON u.id = l.user_id
            LEFT JOIN users c ON c.id = l.created_by
            LEFT JOIN users x ON x.id = l.cancelled_by
            WHERE l.id = ?");
        $stmt->execute([$id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? self::decorate($r) : null;
    }

    /**
     * Check and normalize the input. Pure, so it can be tested.
     */
    public static function validate(string $type, string $start, string $end, string $part, string $note = ''): array
    {
        $type = strtoupper(trim($type));
        $part = strtoupper(trim($part)) ?: 'FULL';
        $end = trim($end) !== '' ? trim($end) : trim($start);
        $start = trim($start);
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Choose a leave type.');
        }
        if (!isset(self::PARTS[$part])) {
            throw new RuntimeException('Choose full day, morning or afternoon.');
        }
        foreach ([$start, $end] as $d) {
            $dt = DateTime::createFromFormat('Y-m-d', $d);
            if (!$dt || $dt->format('Y-m-d') !== $d) {
                throw new RuntimeException('Enter valid dates.');
            }
        }
        if ($end < $start) {
            throw new RuntimeException('The end date is before the start date.');
        }
        $days = (int) (new DateTime($start))->diff(new DateTime($end))->days + 1;
        if ($days > self::MAX_DAYS) {
            throw new RuntimeException(t('A leave can be at most {n} days long.', ['n' => self::MAX_DAYS]));
        }
        if ($part !== 'FULL' && $start !== $end) {
            throw new RuntimeException('A half day (morning or afternoon) is for one date only.');
        }
        $working = 0;
        for ($d = new DateTime($start); $d->format('Y-m-d') <= $end; $d->modify('+1 day')) {
            $working += self::isWorkingDay($d->format('Y-m-d')) ? 1 : 0;
        }
        if ($working === 0) {
            throw new RuntimeException('These dates are only weekend days. Leave is counted on working days (Monday to Friday).');
        }
        $note = trim($note);
        if (mb_strlen($note) > 1000) {
            throw new RuntimeException('The note is too long (1000 characters at most).');
        }
        return ['leave_type' => $type, 'start_date' => $start, 'end_date' => $end, 'day_part' => $part, 'note' => $note, 'working_days' => $working];
    }

    private function checkPermission(array $editor, int $userId): void
    {
        if (!in_array($editor['role'], ['administrator', 'manager'], true)) {
            throw new RuntimeException('Only managers and administrators can record leave.');
        }
        $stmt = $this->db->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        if ($role === false) {
            throw new RuntimeException('Agent not found.');
        }
        if ($editor['role'] === 'manager') {
            if ((int) $editor['id'] === $userId) {
                throw new RuntimeException('You cannot record your own leave. Ask an administrator.');
            }
            if ($role !== 'employee') {
                throw new RuntimeException('Managers can only record leave for employees.');
            }
        }
    }

    private static function decorate(array $r): array
    {
        $r['type_label'] = self::typeLabel($r['leave_type']);
        $r['paid'] = self::isPaid($r['leave_type']);
        $r['part_label'] = self::partLabel($r['day_part']);
        $working = 0;
        for ($d = new DateTime($r['start_date']); $d->format('Y-m-d') <= $r['end_date']; $d->modify('+1 day')) {
            $working += self::isWorkingDay($d->format('Y-m-d')) ? 1 : 0;
        }
        $r['working_days'] = $r['day_part'] === 'FULL' ? $working : 0.5;
        return $r;
    }
}
