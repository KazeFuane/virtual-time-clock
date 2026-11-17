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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        echo json_encode([
            'success'     => false,
            'message'     => $message,
            'server_time' => (new DateTime())->format('c'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
