<?php
/**
 * GET /api/attendance/history.php?date=YYYY-MM-DD
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$user = AuthMiddleware::requireAuth();

$date = $_GET['date'] ?? TimeHelper::currentDate();

// Basic regex check for YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    Response::error('Invalid date format. Expected YYYY-MM-DD.', 400);
}

$attendanceService = new AttendanceService();
$history = $attendanceService->getHistory($user['id'], $date);

Response::success('History retrieved.', [
    'date' => $date,
    'record' => $history
]);
