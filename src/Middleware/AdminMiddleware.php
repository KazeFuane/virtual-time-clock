<?php
/**
 * Virtual Time Clock - Admin Middleware
 *
 * Restricts access to administrator and manager endpoints.
 */

class AdminMiddleware
{
    /**
     * Require the administrator role. Sends 403 if not authorized.
     */
    public static function requireAdmin(): array
    {
        $user = AuthMiddleware::requireAuth();

        if ($user['role'] !== 'administrator') {
            Response::error('Access denied. Administrator privileges required.', 403);
        }

        return $user;
    }

    /**
     * Require administrator or manager role. Sends 403 if not authorized.
     */
    public static function requireManager(): array
    {
        $user = AuthMiddleware::requireAuth();

        if (!in_array($user['role'], ['administrator', 'manager'])) {
            Response::error('Access denied. Manager or administrator privileges required.', 403);
        }

        return $user;
    }
}
