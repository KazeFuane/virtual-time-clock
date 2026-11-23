<?php
/**
 * GET  /api/attendance/time-errors.php?from=YYYY-MM-DD&to=YYYY-MM-DD[&user_id=N]
 *      Time error summary and reports for the logged-in employee.
 *      Managers/admins may pass user_id to view any active agent, and also
 *      receive the agent list for the selector.
 * POST /api/attendance/time-errors.php  {work_date, error_code, message}
 *      Report a time error to managers/admins.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/TimeErrorReportService.php';

$user = AuthMiddleware::requireAuth();
$userId = (int) $user['id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CsrfMiddleware::validate();
        $input = Validator::getJsonInput();
        $result = (new TimeErrorReportService())->create(
            $userId,
            (string) ($input['work_date'] ?? ''),
            isset($input['error_code']) ? (string) $input['error_code'] : null,
            (string) ($input['message'] ?? '')
        );
        Response::success('Your report was sent to your manager.', $result, 201);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed.', 405);
    }

    $today = TimeHelper::currentDate();
    $to    = $_GET['to']   ?? $today;
    $from  = $_GET['from'] ?? (new DateTime($to))->modify('-29 days')->format('Y-m-d');
    $isDate = fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;
    if (!$isDate($from) || !$isDate($to) || $from > $to) {
        Response::error('Invalid date range.');
    }

    $isManager = in_array($user['role'], ['administrator', 'manager'], true);
    $targetId = $userId;
    $agents = null;
    if ($isManager) {
        $db = Database::getInstance()->getConnection();
        $agents = $db->query("
            SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE u.is_active = 1
            ORDER BY r.name = 'employee' DESC, u.last_name, u.first_name
        ")->fetchAll();
        if (!empty($_GET['user_id'])) {
            $targetId = (int) $_GET['user_id'];
        }
    }

    $summary = (new TimeErrorService())->summarize($from, $to, '', $targetId);
    $me = $summary['agents'][0] ?? null;
    if ($me === null) {
        Response::error('Agent not found.', 404);
    }

    Response::success('Your time errors loaded.', [
        'from'      => $from,
        'to'        => $to,
        'labels'    => $summary['labels'],
        'summary'   => $me,
        'is_self'   => $targetId === $userId,
        'agents'    => $agents,
        'reports'   => (new TimeErrorReportService())->list($targetId),
    ]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while loading your time errors.', 500);
}
