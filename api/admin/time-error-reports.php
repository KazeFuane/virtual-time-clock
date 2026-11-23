<?php
/**
 * GET  /api/admin/time-error-reports.php?status=OPEN|RESOLVED|ALL
 * POST /api/admin/time-error-reports.php  {id, note}   mark a report resolved
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/TimeErrorReportService.php';

$caller = AdminMiddleware::requireManager();
$service = new TimeErrorReportService();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        CsrfMiddleware::validate();
        $input = Validator::getJsonInput();
        $service->resolve((int) ($input['id'] ?? 0), (int) $caller['id'], (string) ($input['note'] ?? ''));
        Response::success('Report marked as resolved.');
    }

    $status = strtoupper($_GET['status'] ?? 'OPEN');
    $status = in_array($status, ['OPEN', 'RESOLVED'], true) ? $status : null;
    Response::success('Reports loaded.', ['reports' => $service->list(null, $status)]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while loading reports.', 500);
}
