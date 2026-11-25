<?php
/**
 * POST /api/attendance/end-workday.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();
$user = AuthMiddleware::requireAuth();

try {
    $attendanceService = new AttendanceService();
    $result = $attendanceService->endWorkday($user['id']);
    Response::success(t('Workday ended successfully at {time}. Have a great rest!', ['time' => $result['time']]), $result);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 409);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while finishing your workday.', 500);
}
