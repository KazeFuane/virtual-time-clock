<?php
/**
 * Change the interface language
 * POST /api/auth/language.php   { "language": "fr" }
 *
 * Works signed in (saved to the user's preferences) and on the sign-in
 * page (kept in the session and a cookie).
 */
require_once __DIR__ . '/../../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed.', 405);
}

CsrfMiddleware::validate();

$input = Validator::getJsonInput();
$code  = (string) ($input['language'] ?? '');

if (!Lang::set($code)) {
    Response::error('This language is not available.', 422);
}

Response::success('Language changed.', [
    'language' => Lang::current(),
    'dir'      => Lang::dir(),
]);
