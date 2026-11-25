<?php
/**
 * GET /api/admin/attendance.php
 * Administrative attendance monitoring and report endpoint.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$caller = AdminMiddleware::requireManager();
$db = Database::getInstance()->getConnection();

$date = $_GET['date'] ?? TimeHelper::currentDate();
$search = trim($_GET['search'] ?? '');
$format = $_GET['format'] ?? 'json'; // json or csv

$query = "
    SELECT 
        ad.id,
        u.id AS user_id,
        u.username,
        u.first_name,
        u.last_name,
        r.name AS role,
        ad.work_date,
        ad.start_time,
        ad.end_time,
        ad.total_work_seconds,
        ad.total_break_seconds,
        ad.status
    FROM users u
    JOIN roles r ON u.role_id = r.id
    LEFT JOIN attendance_days ad ON u.id = ad.user_id AND ad.work_date = ?
    WHERE u.is_active = 1
";

$params = [$date];

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.last_name ASC, u.first_name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Leave recorded for this date (see LeaveService)
require_once ROOT_PATH . '/src/Services/TimeErrorService.php';
require_once ROOT_PATH . '/src/Services/LeaveService.php';
require_once ROOT_PATH . '/src/Services/ScheduleService.php';
// Planned shifts of each agent that day (see ScheduleService)
$plan = ScheduleService::shiftsForReport($db, array_column($records, 'user_id'), $date, $date);
$plannedFor = fn(int $uid): array => $plan[$uid][$date] ?? TimeErrorService::defaultShifts($date);
$leaveByUser = [];
$leavePartsByUser = [];
foreach (LeaveService::expand(LeaveService::activeRows($db, $date, $date), $date, $date) as $uid => $dates) {
    if (!$plannedFor((int) $uid)) {
        continue; // no shift planned that day: the leave takes nothing off
    }
    $leavePartsByUser[$uid] = $dates[$date] ?? [];
    $parts = [];
    foreach ($dates[$date] ?? [] as $part => $row) {
        $parts[] = LeaveService::typeLabel($row['leave_type']) . ($part === 'FULL' ? '' : ' (' . mb_strtolower(LeaveService::partLabel($part)) . ')');
    }
    if ($parts) {
        $leaveByUser[$uid] = implode(', ', $parts);
    }
}

// Process records with Shift & Punctuality rules
$processedRecords = [];
foreach ($records as $r) {
    $planned = $plannedFor((int) $r['user_id']);
    $expected = ScheduleService::withoutLeave($planned, $leavePartsByUser[(int) $r['user_id']] ?? []);
    $r['planned_shifts'] = $planned;
    $r['planned_label'] = ScheduleService::label($planned);
    $punctuality = TimeHelper::evaluateShiftPunctuality($r['start_time'], $date, $r['status'], $expected);
    $r['punctuality'] = $punctuality;
    $r['leave'] = $leaveByUser[(int) $r['user_id']] ?? null;
    if ($r['leave'] !== null && ($r['status'] ?? 'NOT_STARTED') === 'NOT_STARTED') {
        // Not clocked in because of recorded leave: not an absence
        $r['punctuality'] = ['label' => t('On leave'), 'delay_text' => ''] + (array) $r['punctuality'];
    }
    $processedRecords[] = $r;
}

// CSV export handler
if ($format === 'csv') {
    require_once ROOT_PATH . '/src/Helpers/CsvExport.php';
    $csv = new CsvExport('attendance_' . $date . '.csv');
    $csv->row(array_map('t', ['Name', 'Username', 'Role', 'Date', 'Start time', 'End time', 'Work time', 'Break time', 'Planned shifts', 'Punctuality', 'Delay', 'Status', 'Leave']));

    foreach ($processedRecords as $r) {
        $csv->row([
            trim($r['first_name'] . ' ' . $r['last_name']),
            $r['username'],
            ucfirst(t((string) $r['role'])),
            $date,
            $r['start_time'] ?? '',
            $r['end_time'] ?? '',
            CsvExport::duration((int) ($r['total_work_seconds'] ?? 0)),
            CsvExport::duration((int) ($r['total_break_seconds'] ?? 0)),
            $r['planned_label'],
            t($r['punctuality']['label'] ?? ''),
            $r['punctuality']['delay_text'] ?? '',
            t(str_replace('_', ' ', $r['status'] ?? 'NOT_STARTED')),
            $r['leave'] ?? '',
        ]);
    }
    $csv->close();
    exit;
}

Response::success('Attendance report loaded.', [
    'date' => $date,
    'records' => $processedRecords
]);
