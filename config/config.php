<?php
/**
 * Virtual Time Clock - Application Configuration
 *
 * Loads environment variables from .env file and defines
 * application constants. Never hardcode credentials here.
 */

// =====================================================
// Load .env file (simple key=value parser)
// =====================================================
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        // Parse KEY=VALUE
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);
            // Don't overwrite existing environment variables
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// =====================================================
// Helper to read env with fallback
// =====================================================
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// =====================================================
// Database Configuration
// =====================================================
define('DB_HOST',     env('DB_HOST', 'localhost'));
define('DB_PORT',     env('DB_PORT', '3306'));
define('DB_NAME',     env('DB_NAME', 'virtual_time_clock'));
define('DB_USER',     env('DB_USER', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));

// =====================================================
// Application Configuration
// =====================================================
define('APP_ENV',      env('APP_ENV', 'development'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Casablanca'));
define('APP_NAME',     env('APP_NAME', 'Virtual Time Clock'));
define('APP_VERSION',  env('APP_VERSION', '1.0.0'));

// =====================================================
// Break Policy
// =====================================================
define('BREAK_DURATION_MINUTES', (int) env('BREAK_DURATION_MINUTES', '45'));
define('MAX_BREAKS_PER_DAY',     (int) env('MAX_BREAKS_PER_DAY', '1'));

// =====================================================
// Working Shifts Schedule & Lateness Rules
// Morning Shift: 08:00:00 -> 12:00:00
// Afternoon Shift: 14:00:00 -> 18:00:00
// =====================================================
define('SHIFT_1_START', env('SHIFT_1_START', '08:00:00'));
define('SHIFT_1_END',   env('SHIFT_1_END',   '12:00:00'));
define('SHIFT_2_START', env('SHIFT_2_START', '14:00:00'));
define('SHIFT_2_END',   env('SHIFT_2_END',   '18:00:00'));
define('LATE_TOLERANCE_SECONDS', (int) env('LATE_TOLERANCE_SECONDS', '0')); // Can be 0 or small grace window in seconds

// =====================================================
// Session Configuration
// =====================================================
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', '28800'));

// =====================================================
// Set timezone
// =====================================================
date_default_timezone_set(APP_TIMEZONE);

// =====================================================
// Error reporting based on environment
// =====================================================
if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}
