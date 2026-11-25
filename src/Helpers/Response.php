<?php
/**
 * Virtual Time Clock - JSON Response Helper
 *
 * Standardized JSON response format for all API endpoints.
 * Every response includes server_time for frontend clock sync.
 */

class Response
{
    /**
     * Send a success JSON response and exit.
     */
    public static function success(string $message, array $data = [], int $statusCode = 200): void
    {
        $message = self::translate($message);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode([
            'success'     => true,
            'message'     => $message,
            'data'        => $data,
            'server_time' => (new DateTime())->format('c'), // ISO 8601
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an error JSON response and exit.
     */
    public static function error(string $message, int $statusCode = 400): void
    {
        $message = self::translate($message);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode([
            'success'     => false,
            'message'     => $message,
            'server_time' => (new DateTime())->format('c'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Show API messages in the user's language. Messages are English;
     * any that have an entry in lang/<code>.php are translated.
     */
    private static function translate(string $message): string
    {
        return function_exists('t') ? t($message) : $message;
    }
}
