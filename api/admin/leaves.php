<?php
/**
 * Leave / PTO for agents (managers and administrators).
 *
 * GET  /api/admin/leaves.php[?user_id=N&from=YYYY-MM-DD&to=YYYY-MM-DD&cancelled=1]
 *      Agents the caller can record leave for, leave types and the leave list.
 * POST /api/admin/leaves.php
 *      {user_id, leave_type, start_date, end_date?, day_part?, note?}   record leave
 *      {action: "cancel", id, reason?}                                  cancel a leave
 *      Both answers have data.undo {token, seconds} for the "Undo" pop-up (api/admin/undo.php).
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/LeaveService.php';
require_once ROOT_PATH . '/src/Services/UndoService.php';

$caller = AdminMiddleware::requireManager();

$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;

try {
    $service = new LeaveService();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CsrfMiddleware::validate();
        $input = Validator::getJsonInput();
        if (($input['action'] ?? '') === 'cancel') {
            $leave = $service->cancel($caller, (int) ($input['id'] ?? 0), (string) ($input['reason'] ?? ''));
            $undo = (new UndoService())->record($caller, 'leave_cancel', ['leave_id' => (int) $leave['id']]);
            Response::success('Leave cancelled.', ['leave' => $leave, 'undo' => $undo]);
        }
        $leave = $service->create(
            $caller,
            (int) ($input['user_id'] ?? 0),
            (string) ($input['leave_type'] ?? ''),
            (string) ($input['start_date'] ?? ''),
            (string) ($input['end_date'] ?? ''),
            (string) ($input['day_part'] ?? 'FULL'),
            (string) ($input['note'] ?? '')
        );
        $undo = (new UndoService())->record($caller, 'leave_create', ['leave_id' => (int) $leave['id']]);
        Response::success('Leave recorded.', ['leave' => $leave, 'undo' => $undo], 201);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed.', 405);
    }

    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    if (($from !== null && !$isDate($from)) || ($to !== null && !$isDate($to))) {
        Response::error('Invalid date.');
    }
    $userId = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null;

    Response::success('Leave loaded.', [
        'agents' => $service->agents($caller),
        'types'  => LeaveService::types(),
        'parts'  => LeaveService::parts(),
        'today'  => TimeHelper::currentDate(),
        'leaves' => $service->list($userId, $from, $to, !empty($_GET['cancelled'])),
    ]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while saving the leave.', 500);
}
