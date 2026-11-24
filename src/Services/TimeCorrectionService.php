<?php
/**
 * Virtual Time Clock - Time Correction Service
 *
 * Lets managers and administrators correct an agent's clock actions for one
 * day, for example when the agent was on time but forgot to clock in.
 *
 * The editor sends the full, corrected list of actions for the day. The
 * service checks it, replaces the day's time_actions and breaks, rebuilds the
 * attendance_days row (start, end, totals, status) and writes an audit row in
 * time_corrections with who, when, why, and the day before and after.
 * The time errors report reads the corrected data, so fixed errors disappear.
 *
 * Rules:
 *   - A reason is required.
 *   - Managers can correct employees only. Administrators can correct anyone,
 *     including themselves. Managers cannot correct their own times.
 *   - No future dates. On today, no action later than now.
 *   - A past day must end clocked out (END_WORKDAY).
 *   - An empty list removes the day (the agent did not work that day).
 */

require_once __DIR__ . '/TimeErrorReportService.php';

class TimeCorrectionService
{
    public const TYPES = ['START_WORKDAY', 'START_BREAK', 'END_BREAK', 'END_WORKDAY'];

    private const NEXT = [
        'NOT_STARTED' => ['START_WORKDAY'],
        'WORKING'     => ['START_BREAK', 'END_WORKDAY'],
        'ON_BREAK'    => ['END_BREAK'],
        'FINISHED'    => ['START_WORKDAY'],
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    /**
     * Create the time_corrections table on first use if the migration
     * was never run (database/migrations/2026_11_24_time_corrections.sql).
     */
    private function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $exists = $this->db->query("SHOW TABLES LIKE 'time_corrections'")->fetchColumn();
        if (!$exists) {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_24_time_corrections.sql');
            $sql = substr($sql, strpos($sql, 'CREATE TABLE'));
            $this->db->exec(rtrim(trim($sql), ';'));
        }
        $checked = true;
    }

    /**
     * Agents the caller may correct.
     */
    public function editableAgents(array $editor): array
    {
        $sql = "SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1";
        if ($editor['role'] !== 'administrator') {
            $sql .= " AND r.name = 'employee'";
        }
        $sql .= " ORDER BY u.last_name, u.first_name";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The day as it is now: attendance row (or null), actions in order, breaks.
     */
    public function getDay(int $userId, string $date): array
    {
        $stmt = $this->db->prepare("SELECT * FROM attendance_days WHERE user_id = ? AND work_date = ? LIMIT 1");
        $stmt->execute([$userId, $date]);
        $day = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $actions = [];
        $breaks = [];
        if ($day) {
            $stmt = $this->db->prepare("SELECT action_type, action_time FROM time_actions WHERE attendance_day_id = ? ORDER BY id");
            $stmt->execute([$day['id']]);
            $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $this->db->prepare("SELECT start_time, end_time, duration_seconds FROM breaks WHERE attendance_day_id = ? ORDER BY id");
            $stmt->execute([$day['id']]);
            $breaks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['day' => $day, 'actions' => $actions, 'breaks' => $breaks];
    }

    /**
     * When a day has saved times but no clock actions (for example rows added
     * straight in the database, or by an older version), build the lines from
     * the day's start/end and its breaks so the editor can show and fix them.
     */
    public static function deriveActions(?array $day, array $breaks): array
    {
        if (!$day || empty($day['start_time'])) {
            return [];
        }
        $list = [['action_type' => 'START_WORKDAY', 'action_time' => $day['start_time']]];
        foreach ($breaks as $b) {
            if (!empty($b['start_time'])) {
                $list[] = ['action_type' => 'START_BREAK', 'action_time' => $b['start_time']];
            }
            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                $list[] = ['action_type' => 'END_BREAK', 'action_time' => $b['end_time']];
            }
        }
        if (!empty($day['end_time'])) {
            $list[] = ['action_type' => 'END_WORKDAY', 'action_time' => $day['end_time']];
        }
        return $list;
    }

    /**
     * Audit trail. With $userId and $date: that day. Otherwise the latest corrections.
     */
    public function history(?int $userId = null, ?string $date = null, int $limit = 50): array
    {
        $sql = "SELECT c.*, u.username, u.first_name, u.last_name,
                       e.first_name AS editor_first_name, e.last_name AS editor_last_name, e.username AS editor_username
                FROM time_corrections c
                JOIN users u ON u.id = c.user_id
                LEFT JOIN users e ON e.id = c.edited_by
                WHERE 1 = 1";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND c.user_id = ?";
            $params[] = $userId;
        }
        if ($date !== null) {
            $sql .= " AND c.work_date = ?";
            $params[] = $date;
        }
        $sql .= " ORDER BY c.id DESC LIMIT " . max(1, min(500, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(function ($r) {
            $r['before'] = json_decode($r['before_json'], true);
            $r['after']  = json_decode($r['after_json'], true);
            unset($r['before_json'], $r['after_json']);
            return $r;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Replace the day's clock actions with $actions and log the change.
     *
     * @param array $editor   the logged-in manager/admin (id, role)
     * @param array $actions  list of ['action_type' => ..., 'action_time' => 'HH:MM[:SS]']
     */
    public function saveDay(array $editor, int $userId, string $date, array $actions, string $reason, ?int $reportId = null): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('Please give a reason for the correction (1 to 1000 characters).');
        }
        $this->checkPermission($editor, $userId);

        $today = TimeHelper::currentDate();
        $built = self::buildDay($actions, $date, $today, TimeHelper::currentTime());

        $before = $this->getDay($userId, $date);
        if (self::sameActions($before['actions'], $built['actions'])) {
            throw new RuntimeException('Nothing changed.');
        }

        $this->db->beginTransaction();
        try {
            $dayId = $before['day']['id'] ?? null;

            if (!$built['actions']) {
                if ($dayId) {
                    // Cascades to time_actions and breaks
                    $this->db->prepare("DELETE FROM attendance_days WHERE id = ?")->execute([$dayId]);
                }
            } else {
                if ($dayId) {
                    $this->db->prepare("DELETE FROM time_actions WHERE attendance_day_id = ?")->execute([$dayId]);
                    $this->db->prepare("DELETE FROM breaks WHERE attendance_day_id = ?")->execute([$dayId]);
                    $this->db->prepare("
                        UPDATE attendance_days
                        SET start_time = ?, end_time = ?, total_work_seconds = ?, total_break_seconds = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$built['start_time'], $built['end_time'], $built['total_work_seconds'], $built['total_break_seconds'], $built['status'], $dayId]);
                } else {
                    $this->db->prepare("
                        INSERT INTO attendance_days (user_id, work_date, start_time, end_time, total_work_seconds, total_break_seconds, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$userId, $date, $built['start_time'], $built['end_time'], $built['total_work_seconds'], $built['total_break_seconds'], $built['status']]);
                    $dayId = (int) $this->db->lastInsertId();
                }

                $ins = $this->db->prepare("INSERT INTO time_actions (attendance_day_id, user_id, action_type, action_time) VALUES (?, ?, ?, ?)");
                foreach ($built['actions'] as $a) {
                    $ins->execute([$dayId, $userId, $a['action_type'], $a['action_time']]);
                }
                $ins = $this->db->prepare("INSERT INTO breaks (attendance_day_id, user_id, start_time, end_time, duration_seconds) VALUES (?, ?, ?, ?, ?)");
                foreach ($built['breaks'] as $b) {
                    $ins->execute([$dayId, $userId, $b['start_time'], $b['end_time'], $b['duration_seconds']]);
                }
            }

            $after = $this->getDay($userId, $date);
            $this->db->prepare("
                INSERT INTO time_corrections (user_id, work_date, edited_by, reason, before_json, after_json, report_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $userId, $date, (int) $editor['id'], $reason,
                json_encode(self::snapshot($before)), json_encode(self::snapshot($after)),
                $reportId ?: null,
            ]);
            $correctionId = (int) $this->db->lastInsertId();

            if ($reportId) {
                $this->resolveReport($reportId, $userId, $date, (int) $editor['id'], $reason);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['correction_id' => $correctionId] + $this->getDay($userId, $date);
    }

    private function checkPermission(array $editor, int $userId): void
    {
        if (!in_array($editor['role'], ['administrator', 'manager'], true)) {
            throw new RuntimeException('Only managers and administrators can correct times.');
        }
        $stmt = $this->db->prepare("SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        $role = $stmt->fetchColumn();
        if ($role === false) {
            throw new RuntimeException('Agent not found.');
        }
        if ($editor['role'] === 'manager') {
            if ((int) $editor['id'] === $userId) {
                throw new RuntimeException('You cannot correct your own times. Ask an administrator.');
            }
            if ($role !== 'employee') {
                throw new RuntimeException('Managers can only correct employee times.');
            }
        }
    }

    private function resolveReport(int $reportId, int $userId, string $date, int $editorId, string $reason): void
    {
        $stmt = $this->db->prepare("SELECT user_id, work_date, status FROM time_error_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r || (int) $r['user_id'] !== $userId || $r['work_date'] !== $date) {
            throw new RuntimeException('The report does not match this agent and day.');
        }
        if ($r['status'] === 'OPEN') {
            (new TimeErrorReportService())->resolve($reportId, $editorId, 'Times corrected: ' . $reason);
        }
    }

    /**
     * Check a corrected action list and compute the day from it. Pure, so it
     * can be tested without a database. Throws RuntimeException on bad input.
     */
    public static function buildDay(array $actions, string $date, string $today, string $now): array
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new RuntimeException('Invalid date.');
        }
        if ($date > $today) {
            throw new RuntimeException('You cannot enter times for a future date.');
        }
        if (count($actions) > 40) {
            throw new RuntimeException('Too many actions for one day.');
        }

        $clean = [];
        $state = 'NOT_STARTED';
        $prev = null;
        $segStart = null;
        $breakStart = null;
        $work = 0;
        $breakTotal = 0;
        $breaks = [];
        $start = null;
        $end = null;

        foreach (array_values($actions) as $i => $a) {
            $n = $i + 1;
            $type = strtoupper(trim((string) ($a['action_type'] ?? '')));
            $time = self::normalizeTime((string) ($a['action_time'] ?? ''));
            if (!in_array($type, self::TYPES, true)) {
                throw new RuntimeException("Line $n: unknown action.");
            }
            if ($time === null) {
                throw new RuntimeException("Line $n: enter a time as HH:MM.");
            }
            $t = TimeErrorService::toSeconds($time);
            if ($prev !== null && $t < $prev) {
                throw new RuntimeException("Line $n: $time is earlier than the line before it.");
            }
            if ($date === $today && $t > TimeErrorService::toSeconds($now)) {
                throw new RuntimeException("Line $n: $time is later than the current time.");
            }
            if (!in_array($type, self::NEXT[$state], true)) {
                throw new RuntimeException("Line $n: " . self::label($type) . ' is not possible here (' . self::stateLabel($state) . ').');
            }
            $prev = $t;

            switch ($type) {
                case 'START_WORKDAY':
                    $start ??= $time;
                    $end = null;
                    $segStart = $t;
                    $state = 'WORKING';
                    break;
                case 'START_BREAK':
                    $work += $t - $segStart;
                    $breakStart = $time;
                    $state = 'ON_BREAK';
                    break;
                case 'END_BREAK':
                    $len = $t - TimeErrorService::toSeconds($breakStart);
                    $breaks[] = ['start_time' => $breakStart, 'end_time' => $time, 'duration_seconds' => $len];
                    $breakTotal += $len;
                    $breakStart = null;
                    $segStart = $t;
                    $state = 'WORKING';
                    break;
                case 'END_WORKDAY':
                    $work += $t - $segStart;
                    $end = $time;
                    $state = 'FINISHED';
                    break;
            }
            $clean[] = ['action_type' => $type, 'action_time' => $time];
        }

        if ($breakStart !== null) {
            $breaks[] = ['start_time' => $breakStart, 'end_time' => null, 'duration_seconds' => null];
        }
        if ($clean && $date < $today && $state !== 'FINISHED') {
            throw new RuntimeException('A past day must end with a clock-out.');
        }

        return [
            'actions'             => $clean,
            'breaks'              => $breaks,
            'start_time'          => $start,
            'end_time'            => $end,
            'total_work_seconds'  => $work,   // while still WORKING today, the live clock adds the rest
            'total_break_seconds' => $breakTotal,
            'status'              => $clean ? $state : 'NOT_STARTED',
        ];
    }

    private static function normalizeTime(string $t): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($t), $m)) {
            return null;
        }
        [$h, $i, $s] = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
        if ($h > 23 || $i > 59 || $s > 59) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', $h, $i, $s);
    }

    private static function sameActions(array $a, array $b): bool
    {
        $norm = fn($list) => array_map(fn($x) => $x['action_type'] . '@' . $x['action_time'], $list);
        return $norm($a) === $norm($b);
    }

    private static function snapshot(array $d): array
    {
        $day = $d['day'];
        return [
            'status'              => $day['status'] ?? null,
            'start_time'          => $day['start_time'] ?? null,
            'end_time'            => $day['end_time'] ?? null,
            'total_work_seconds'  => isset($day['total_work_seconds']) ? (int) $day['total_work_seconds'] : 0,
            'total_break_seconds' => isset($day['total_break_seconds']) ? (int) $day['total_break_seconds'] : 0,
            'actions'             => $d['actions'],
        ];
    }

    public static function label(string $type): string
    {
        return [
            'START_WORKDAY' => 'Clock in',
            'START_BREAK'   => 'Start break',
            'END_BREAK'     => 'End break',
            'END_WORKDAY'   => 'Clock out',
        ][$type] ?? $type;
    }

    private static function stateLabel(string $state): string
    {
        return [
            'NOT_STARTED' => 'the agent has not clocked in yet',
            'WORKING'     => 'the agent is working',
            'ON_BREAK'    => 'the agent is on break',
            'FINISHED'    => 'the agent has clocked out',
        ][$state];
    }
}
