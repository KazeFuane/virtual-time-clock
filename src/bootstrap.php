<?php
/**
 * Virtual Time Clock - Application Bootstrap
 *
 * This file is included by every entry point. It:
 * 1. Defines the ROOT_PATH constant
 * 2. Loads configuration
 * 3. Configures and starts the PHP session
 * 4. Generates a CSRF token
 * 5. Registers a simple class autoloader
 */

// Project root directory (one level up from src/)
define('ROOT_PATH', dirname(__DIR__));

// Load configuration (sets timezone, error reporting, etc.)
require_once ROOT_PATH . '/config/config.php';

// Load database class
require_once ROOT_PATH . '/config/database.php';

// =====================================================
// Session Configuration
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,  // Set to true in production with HTTPS
        'httponly'  => true,
        'samesite'  => 'Strict',
    ]);
    session_name('vtc_session');
    session_start();
}

// Generate CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// Simple Class Autoloader
// =====================================================
spl_autoload_register(function (string $className): void {
    // Map class names to file paths
    $classMap = [
        // Services
        'AuthService'       => ROOT_PATH . '/src/Services/AuthService.php',
        'AttendanceService' => ROOT_PATH . '/src/Services/AttendanceService.php',
        'UserService'       => ROOT_PATH . '/src/Services/UserService.php',

        // Middleware
        'AuthMiddleware'    => ROOT_PATH . '/src/Middleware/AuthMiddleware.php',
        'AdminMiddleware'   => ROOT_PATH . '/src/Middleware/AdminMiddleware.php',
        'CsrfMiddleware'    => ROOT_PATH . '/src/Middleware/CsrfMiddleware.php',

        // Helpers
        'Response'          => ROOT_PATH . '/src/Helpers/Response.php',
        'Validator'         => ROOT_PATH . '/src/Helpers/Validator.php',
        'TimeHelper'        => ROOT_PATH . '/src/Helpers/TimeHelper.php',
    ];

    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
