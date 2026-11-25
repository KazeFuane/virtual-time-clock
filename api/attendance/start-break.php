<?php
/**
 * POST /api/attendance/start-break.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();
$user = AuthMiddleware::requireAuth();

try {
    $attendanceService = new AttendanceService();
    $result = $attendanceService->startBreak($user['id']);
    Response::success(t('Break started successfully at {time}. Max cumulative break allowed is 45 minutes.', ['time' => $result['time']]), $result);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while starting your break.', 500);
}
