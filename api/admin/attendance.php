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
// Process records with Shift & Punctuality rules
$processedRecords = [];
foreach ($records as $r) {
    $punctuality = TimeHelper::evaluateShiftPunctuality($r['start_time'], $date, $r['status']);
    $r['punctuality'] = $punctuality;
    $processedRecords[] = $r;
}

// CSV export handler
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . $date . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'Name', 'Username', 'Role', 'Date', 'Start Time', 'End Time', 'Work Hours', 'Break Hours', 'Punctuality', 'Delay', 'Status']);

    foreach ($processedRecords as $r) {
        $workedHours = round(($r['total_work_seconds'] ?? 0) / 3600, 2);
        $breakHours  = round(($r['total_break_seconds'] ?? 0) / 3600, 2);
        fputcsv($output, [
            $r['user_id'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['username'],
            $r['role'],
            $date,
            $r['start_time'] ?? 'N/A',
            $r['end_time'] ?? 'N/A',
            $workedHours,
            $breakHours,
            $r['punctuality']['label'] ?? 'N/A',
            $r['punctuality']['delay_text'] ?? '00:00:00',
            $r['status'] ?? 'NOT_STARTED'
        ]);
    }
    fclose($output);
    exit;
}

Response::success('Attendance report loaded.', [
    'date' => $date,
    'records' => $processedRecords
]);
