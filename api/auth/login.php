<?php
/**
 * Authentication: Login Endpoint
 * POST /api/auth/login.php
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

// Validate CSRF
CsrfMiddleware::validate();

$input = Validator::getJsonInput();
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    Response::error('Username and password are required.', 400);
}

$authService = new AuthService();
$user = $authService->login($username, $password);

if (!$user) {
    $remaining = $authService->getLockoutRemaining();
    if ($remaining > 0) {
        $mins = ceil($remaining / 60);
        Response::error("Too many failed attempts. Account locked for {$mins} more minutes.", 429);
    }
    Response::error('Invalid username or password.', 401);
}

Response::success('Signed in successfully.', [
    'user' => $user
]);
