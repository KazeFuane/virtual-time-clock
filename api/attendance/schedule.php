<?php
/**
 * GET /api/attendance/schedule.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 * The signed-in agent's own planned shifts, day by day, with recorded leave.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/LeaveService.php';
require_once ROOT_PATH . '/src/Services/ScheduleService.php';

$user = AuthMiddleware::requireAuth();

$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTime::createFromFormat('Y-m-d', $d)) && $dt->format('Y-m-d') === $d;

$today = TimeHelper::currentDate();
$from = $_GET['from'] ?? (new DateTime($today))->modify('monday this week')->format('Y-m-d');
$to = $_GET['to'] ?? (new DateTime($from))->modify('+6 days')->format('Y-m-d');
if (!$isDate($from) || !$isDate($to) || $from > $to || (new DateTime($from))->diff(new DateTime($to))->days >= ScheduleService::MAX_RANGE_DAYS) {
    Response::error(t('Invalid date range (at most {n} days).', ['n' => ScheduleService::MAX_RANGE_DAYS]));
}

try {
    $db = Database::getInstance()->getConnection();
    $uid = (int) $user['id'];
    $plan = (new ScheduleService($db))->planFor([$uid], $from, $to)[$uid];
    $leave = LeaveService::expand(LeaveService::activeRows($db, $from, $to, $uid), $from, $to)[$uid] ?? [];

    $days = [];
    $total = 0;
    foreach ($plan as $date => $p) {
        $leaveList = [];
        foreach ($p['shifts'] ? ($leave[$date] ?? []) : [] as $part => $row) {
            $leaveList[] = ['type' => $row['leave_type'], 'label' => LeaveService::typeLabel($row['leave_type']), 'part' => $part, 'part_label' => LeaveService::partLabel($part)];
        }
        $secs = ScheduleService::totalSeconds($p['shifts']);
        $total += $secs;
        $days[] = $p + [
            'date'    => $date,
            'weekday' => ScheduleService::WEEKDAYS[(int) (new DateTime($date))->format('N')],
            'seconds' => $secs,
            'label'   => ScheduleService::label($p['shifts']),
            'leave'   => $leaveList,
        ];
    }

    Response::success('Schedule loaded.', [
        'from'            => $from,
        'to'              => $to,
        'today'           => $today,
        'days'            => $days,
        'planned_seconds' => $total,
    ]);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while loading the schedule.', 500);
}
