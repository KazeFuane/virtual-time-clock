<?php
/**
 * Virtual Time Clock - Authentication Middleware
 *
 * Protects API endpoints and pages by verifying
 * that the user has a valid session.
 */

class AuthMiddleware
{
    /**
     * Require authentication. Returns user data or sends 401 and exits.
     */
    public static function requireAuth(): array
    {
        if (empty($_SESSION['user_id'])) {
            // Check if this is an API request or a page request
            if (self::isApiRequest()) {
                Response::error('Authentication required. Please log in.', 401);
            } else {
                header('Location: /virtual-time-clock/public/login.php');
                exit;
            }
        }

        return [
            'id'         => $_SESSION['user_id'],
            'username'   => $_SESSION['username'] ?? '',
            'email'      => $_SESSION['email'] ?? '',
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name'  => $_SESSION['last_name'] ?? '',
            'role'       => $_SESSION['role'] ?? 'employee',
            'role_id'    => $_SESSION['role_id'] ?? 1,
        ];
    }

    /**
     * Get the current user data from session, or null if not logged in.
     */
    public static function getCurrentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return [
            'id'         => $_SESSION['user_id'],
            'username'   => $_SESSION['username'] ?? '',
            'email'      => $_SESSION['email'] ?? '',
            'first_name' => $_SESSION['first_name'] ?? '',
            'last_name'  => $_SESSION['last_name'] ?? '',
            'role'       => $_SESSION['role'] ?? 'employee',
            'role_id'    => $_SESSION['role_id'] ?? 1,
        ];
    }

    /**
     * Check if a user is currently logged in.
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Determine if the current request is an API call (expects JSON).
     */
    private static function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        return str_contains($uri, '/api/')
            || str_contains($acceptHeader, 'application/json');
    }
}
