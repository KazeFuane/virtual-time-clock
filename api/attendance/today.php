<?php
/**
 * GET /api/attendance/today.php
 * Fetches today's attendance day record, actions, breaks, and live calculations.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$user = AuthMiddleware::requireAuth();

$attendanceService = new AttendanceService();
$todayData = $attendanceService->getToday($user['id']);

Response::success('Attendance data loaded.', $todayData);
