<?php
/**
 * Undo a change a manager or administrator just made (the "Undo" pop-up).
 *
 * POST /api/admin/undo.php  {token}
 *      token comes from data.undo of the API call that made the change:
 *      time corrections, leave recorded/cancelled, schedule changes,
 *      account deactivated. Only the person who made the change can undo
 *      it, once, within a few seconds. See src/Services/UndoService.php.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Services/UndoService.php';

$caller = AdminMiddleware::requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}
CsrfMiddleware::validate();

try {
    $input = Validator::getJsonInput();
    $result = (new UndoService())->undo($caller, (string) ($input['token'] ?? ''));
    Response::success($result['message'], ['kind' => $result['kind']]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while undoing the change.', 500);
}
