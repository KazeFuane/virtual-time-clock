<?php
/**
 * Save the light / dark mode choice.
 * POST /api/auth/theme.php   { "theme": "light" | "dark" }
 * Signed in: saved for the user (all their browsers). Signed out (sign-in
 * page): only remembered in this browser's cookie.
 */
require_once __DIR__ . '/../../src/bootstrap.php';
require_once ROOT_PATH . '/src/Helpers/ThemeHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();

$input = Validator::getJsonInput();
$theme = $input['theme'] ?? ($_POST['theme'] ?? '');

if (!ThemeHelper::valid($theme)) {
    Response::error('Theme must be "light" or "dark".', 422);
}

if (AuthMiddleware::isLoggedIn()) {
    try {
        ThemeHelper::save((int) $_SESSION['user_id'], $theme);
    } catch (Throwable $e) {
        Response::error('Could not save your theme.', 500);
    }
} else {
    ThemeHelper::setCookie($theme);
}

Response::success('Theme saved.', ['theme' => $theme]);
