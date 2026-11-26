<?php
/**
 * Virtual Time Clock - Undo Service
 *
 * After a manager or administrator changes something (corrects clock times,
 * records or cancels leave, changes a schedule, deactivates an account), the
 * API saves what is needed to put it back and returns an "undo" token. The
 * page shows an "Undo" pop-up for UNDO_SECONDS; clicking it calls
 * api/admin/undo.php with the token.
 *
 * Rules:
 *   - Only the person who made the change can undo it, once.
 *   - The pop-up shows for UNDO_SECONDS; the server accepts the click a few
 *     seconds longer (GRACE_SECONDS) so a slow connection does not lose it.
 *   - If someone changed the same thing again in the meantime, the undo is
 *     refused instead of overwriting their change.
 *   - Agents' own clock actions have no undo (managers fix those on
 *     Correct Times). Theme, language, search and downloads have none either.
 *
 * Each kind of change is put back by its own service:
 *   time_correction  TimeCorrectionService::undoCorrection()  (also written to the audit trail)
 *   leave_create     LeaveService::undoCreate()
 *   leave_cancel     LeaveService::undoCancel()
 *   schedule         ScheduleService::restoreSnapshot()
 *   user_deactivate  UserService::undoDeactivate()
 */

class UndoService
{
    public const UNDO_SECONDS = 10;
    private const GRACE_SECONDS = 20;
    private const KINDS = ['time_correction', 'leave_create', 'leave_cancel', 'schedule', 'user_deactivate'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        self::ensureTable($this->db);
    }

    /**
     * Create the undo_actions table on first use if the migration was never
     * run (database/migrations/2026_11_26_undo_actions.sql).
     */
    public static function ensureTable(PDO $db): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        if (!$db->query("SHOW TABLES LIKE 'undo_actions'")->fetchColumn()) {
            $sql = file_get_contents(ROOT_PATH . '/database/migrations/2026_11_26_undo_actions.sql');
            $sql = substr($sql, strpos($sql, 'CREATE TABLE'));
            $db->exec(rtrim(trim($sql), ';'));
        }
        $checked = true;
    }

    /**
     * Remember how to put a change back. Returns what the page needs for
     * the pop-up: ['token' => ..., 'seconds' => UNDO_SECONDS].
     */
    public function record(array $actor, string $kind, array $payload): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('Unknown undo kind: ' . $kind);
        }
        // Old rows are of no use once expired
        $this->db->exec("DELETE FROM undo_actions WHERE expires_at < NOW() - INTERVAL 1 DAY");

        $token = bin2hex(random_bytes(16));
        $this->db->prepare("
            INSERT INTO undo_actions (token, actor_id, kind, payload_json, expires_at)
            VALUES (?, ?, ?, ?, NOW() + INTERVAL " . (self::UNDO_SECONDS + self::GRACE_SECONDS) . " SECOND)
        ")->execute([$token, (int) $actor['id'], $kind, json_encode($payload)]);

        return ['token' => $token, 'seconds' => self::UNDO_SECONDS];
    }

    /**
     * Put a change back. Throws RuntimeException when it can no longer be undone.
     * Returns ['kind' => ..., 'message' => English text for Response::success].
     */
    public function undo(array $actor, string $token): array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            throw new RuntimeException('This change can no longer be undone.');
        }

        // Load the user's language now: the first t() may create the
        // user_preferences table, which would end the transaction below.
        Lang::current();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT *, (expires_at >= NOW()) AS still_valid
                FROM undo_actions WHERE token = ? FOR UPDATE");
            $stmt->execute([$token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || (int) $row['actor_id'] !== (int) $actor['id']) {
                throw new RuntimeException('This change can no longer be undone.');
            }
            if ($row['undone_at']) {
                throw new RuntimeException('This change was already undone.');
            }
            if (!(int) $row['still_valid']) {
                throw new RuntimeException('Too late to undo. Make the change again by hand.');
            }

            $payload = json_decode($row['payload_json'], true) ?: [];
            $message = $this->apply($actor, $row['kind'], $payload);

            $this->db->prepare("UPDATE undo_actions SET undone_at = NOW() WHERE id = ?")->execute([(int) $row['id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['kind' => $row['kind'], 'message' => $message];
    }

    private function apply(array $actor, string $kind, array $p): string
    {
        switch ($kind) {
            case 'time_correction':
                require_once __DIR__ . '/TimeErrorService.php';
                require_once __DIR__ . '/TimeCorrectionService.php';
                (new TimeCorrectionService())->undoCorrection($actor, (int) ($p['correction_id'] ?? 0), $p);
                return 'Correction undone. The times are back as they were.';
            case 'leave_create':
                require_once __DIR__ . '/LeaveService.php';
                (new LeaveService($this->db))->undoCreate($actor, (int) ($p['leave_id'] ?? 0));
                return 'Leave removed.';
            case 'leave_cancel':
                require_once __DIR__ . '/LeaveService.php';
                (new LeaveService($this->db))->undoCancel($actor, (int) ($p['leave_id'] ?? 0));
                return 'Leave restored.';
            case 'schedule':
                (new ScheduleService($this->db))->restoreSnapshot($actor, $p);
                return 'Schedule change undone.';
            case 'user_deactivate':
                if (($actor['role'] ?? '') !== 'administrator') {
                    throw new RuntimeException('Access denied. Administrator privileges required.');
                }
                (new UserService())->undoDeactivate((int) ($p['user_id'] ?? 0));
                return 'Account active again.';
        }
        throw new RuntimeException('This change can no longer be undone.');
    }
}
