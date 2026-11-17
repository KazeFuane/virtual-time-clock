<?php
/**
 * Virtual Time Clock - CSRF Protection Middleware
 *
 * Validates CSRF tokens on state-changing requests (POST, PATCH, DELETE).
 * Token is sent via X-CSRF-TOKEN header or csrf_token in the request body.
 */

class CsrfMiddleware
{
    /**
     * Validate the CSRF token. Sends 403 and exits if invalid.
     */
    public static function validate(): void
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($sessionToken)) {
            Response::error('Session expired. Please refresh the page.', 403);
        }

        // Check header first, then POST body
        $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? '';

        // Also check JSON body
        if (empty($submittedToken)) {
            $input = Validator::getJsonInput();
            $submittedToken = $input['csrf_token'] ?? '';
        }

        if (empty($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
            Response::error('Invalid CSRF token. Please refresh the page.', 403);
        }
    }

    /**
     * Get the current CSRF token.
     */
    public static function getToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }
}
