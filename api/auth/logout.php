<?php
/**
 * Authentication: Logout Endpoint
 * POST /api/auth/logout.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

// CSRF check
CsrfMiddleware::validate();

$authService = new AuthService();
$authService->logout();

Response::success('Signed out successfully.');
