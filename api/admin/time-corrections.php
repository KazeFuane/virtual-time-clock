<?php
/**
 * Correct an agent's clock times (managers and administrators).
 *
 * GET  /api/admin/time-corrections.php
 *      Agents the caller can correct, plus the latest corrections.
 * GET  /api/admin/time-corrections.php?user_id=N&date=YYYY-MM-DD
 *      That day's clock actions and its correction history.
 * POST /api/admin/time-corrections.php
 *      {user_id, date, actions: [{action_type, action_time}], reason, report_id?}
 *      Replace the day's clock actions. report_id also resolves that agent report.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/TimeCorrectionService.php';
require_once ROOT_PATH . '/src/Services/LeaveService.php';
require_once ROOT_PATH . '/src/Services/ScheduleService.php';

$caller = AdminMiddleware::requireManager();

$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && DateTime::createFromFormat('Y-m-d', $d) !== false;

try {
    $service = new TimeCorrectionService();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CsrfMiddleware::validate();
        $input = Validator::getJsonInput();
        $date = (string) ($input['date'] ?? '');
        if (!$isDate($date)) {
            Response::error('Invalid date.');
        }
        $actions = $input['actions'] ?? [];
        if (!is_array($actions)) {
            Response::error('Invalid actions.');
        }
        $result = $service->saveDay(
            $caller,
            (int) ($input['user_id'] ?? 0),
            $date,
            $actions,
            (string) ($input['reason'] ?? ''),
            !empty($input['report_id']) ? (int) $input['report_id'] : null
        );
        Response::success('Times corrected.', $result);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        Response::error('Method not allowed.', 405);
    }

    if (isset($_GET['user_id'], $_GET['date'])) {
        if (!$isDate($_GET['date'])) {
            Response::error('Invalid date.');
        }
        $userId = (int) $_GET['user_id'];
        $date = $_GET['date'];
        $day = $service->getDay($userId, $date);
        $day['derived'] = false;
        if ($day['day'] && !$day['actions']) {
            $day['actions'] = TimeCorrectionService::deriveActions($day['day'], $day['breaks']);
            $day['derived'] = (bool) $day['actions'];
        }
        // Planned shifts that day, and the ones expected after leave (for the
        // default times and "Fill missing in/out")
        $db = Database::getInstance()->getConnection();
        $planned = ScheduleService::shiftsForReport($db, [$userId], $date, $date)[$userId][$date] ?? TimeErrorService::defaultShifts($date);
        $leaveParts = LeaveService::expand(LeaveService::activeRows($db, $date, $date, $userId), $date, $date)[$userId][$date] ?? [];
        Response::success('Day loaded.', $day + [
            'history'         => $service->history($userId, $date),
            'planned_shifts'  => $planned,
            'expected_shifts' => ScheduleService::withoutLeave($planned, $leaveParts),
        ]);
    }

    Response::success('Loaded.', [
        'agents'  => $service->editableAgents($caller),
        'recent'  => $service->history(null, null, 30),
        'today'   => TimeHelper::currentDate(),
        'can_edit_self' => $caller['role'] === 'administrator',
    ]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while correcting times.', 500);
}
