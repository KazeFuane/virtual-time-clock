<?php
/**
 * POST /api/attendance/start-workday.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();
$user = AuthMiddleware::requireAuth();

try {
    $attendanceService = new AttendanceService();
    $result = $attendanceService->startWorkday($user['id']);
    $msg = !empty($result['resumed']) 
        ? 'Workday resumed successfully at ' . $result['time'] . '.' 
        : 'Workday started successfully at ' . $result['time'] . '.';
    Response::success($msg, $result);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while starting your workday.', 500);
}
