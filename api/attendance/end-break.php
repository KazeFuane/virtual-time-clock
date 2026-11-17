<?php
/**
 * POST /api/attendance/end-break.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();
$user = AuthMiddleware::requireAuth();

try {
    $attendanceService = new AttendanceService();
    $result = $attendanceService->endBreak($user['id']);
    Response::success('Break ended at ' . $result['time'] . '. Welcome back to work.', $result);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while ending your break.', 500);
}
