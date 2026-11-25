<?php
/**
 * Virtual Time Clock - Time Error Report Service
 *
 * Employees report a time error; managers/admins see and resolve it.
 * Table: time_error_reports (see database/migrations/2026_11_22_time_error_reports.sql)
 */

require_once __DIR__ . '/TimeErrorService.php';

class TimeErrorReportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTable();
    }

    /**
     * Create the time_error_reports table on first use if the migration
     * was never run (database/migrations/2026_11_22_time_error_reports.sql).
     */
    private function ensureTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $exists = $this->db->query("SHOW TABLES LIKE 'time_error_reports'")->fetchColumn();
        if (!$exists) {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_22_time_error_reports.sql');
            $sql = substr($sql, strpos($sql, 'CREATE TABLE'));
            $this->db->exec(rtrim(trim($sql), ';'));
        }
        $checked = true;
    }

    /**
     * Create a report. Throws RuntimeException on invalid input.
     */
    public function create(int $userId, string $workDate, ?string $errorCode, string $message): array
    {
        $message = trim($message);
        $d = DateTime::createFromFormat('Y-m-d', $workDate);
        if (!$d || $d->format('Y-m-d') !== $workDate) {
            throw new RuntimeException('Invalid date.');
        }
        if ($workDate > TimeHelper::currentDate()) {
            throw new RuntimeException('You cannot report a future date.');
        }
        if ($errorCode !== null && $errorCode !== '' && !isset(TimeErrorService::LABELS[$errorCode])) {
            throw new RuntimeException('Unknown error type.');
        }
        if ($message === '' || mb_strlen($message) > 1000) {
            throw new RuntimeException('Please describe what happened (1 to 1000 characters).');
        }

        // One open report per agent, day and error type
        $stmt = $this->db->prepare("
            SELECT id FROM time_error_reports
            WHERE user_id = ? AND work_date = ? AND status = 'OPEN' AND error_code <=> ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $workDate, $errorCode ?: null]);
        if ($stmt->fetch()) {
            throw new RuntimeException('You already reported this. Your manager has not resolved it yet.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO time_error_reports (user_id, work_date, error_code, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $workDate, $errorCode ?: null, $message]);
        return ['id' => (int) $this->db->lastInsertId()];
    }

    /**
     * List reports. $userId limits to one agent; $status = 'OPEN', 'RESOLVED' or null for all.
     */
    public function list(?int $userId = null, ?string $status = null, int $limit = 200): array
    {
        $sql = "
            SELECT r.*, u.username, u.first_name, u.last_name,
                   ru.first_name AS resolver_first_name, ru.last_name AS resolver_last_name
            FROM time_error_reports r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN users ru ON ru.id = r.resolved_by
            WHERE 1 = 1
        ";
        $params = [];
        if ($userId !== null) {
            $sql .= " AND r.user_id = ?";
            $params[] = $userId;
        }
        if ($status !== null) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.status = 'OPEN' DESC, r.created_at DESC LIMIT " . max(1, min(1000, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(function ($r) {
            $r['error_label'] = $r['error_code'] ? TimeErrorService::label($r['error_code']) : t('Other problem');
            return $r;
        }, $stmt->fetchAll());
    }

    /**
     * Mark a report resolved.
     */
    public function resolve(int $reportId, int $resolverId, string $note): void
    {
        $note = trim($note);
        if (mb_strlen($note) > 1000) {
            throw new RuntimeException('Note is too long (max 1000 characters).');
        }
        $stmt = $this->db->prepare("
            UPDATE time_error_reports
            SET status = 'RESOLVED', resolved_by = ?, resolution_note = ?, resolved_at = NOW()
            WHERE id = ? AND status = 'OPEN'
        ");
        $stmt->execute([$resolverId, $note !== '' ? $note : null, $reportId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Report not found or already resolved.');
        }
    }
}
