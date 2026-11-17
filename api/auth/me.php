<?php
/**
 * Authentication: Current Session User Info
 * GET /api/auth/me.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$user = AuthMiddleware::requireAuth();

Response::success('Authenticated user retrieved.', [
    'user' => $user
]);
