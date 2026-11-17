<?php
/**
 * Virtual Time Clock - Database Connection
 *
 * PDO singleton with MariaDB timezone synchronization.
 * Uses prepared statements by default (emulated prepares disabled).
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $conn;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASSWORD, $options);

            // Synchronize MariaDB session timezone with PHP timezone
            $now  = new DateTime();
            $mins = $now->getOffset() / 60;
            $sign = ($mins < 0) ? -1 : 1;
            $mins = abs($mins);
            $hrs  = floor($mins / 60);
            $mins -= $hrs * 60;
            $offset = sprintf('%+03d:%02d', $hrs * $sign, $mins);

            $this->conn->exec("SET time_zone = '{$offset}'");
        } catch (PDOException $e) {
            // Never expose connection details to the client
            if (APP_ENV === 'development') {
                $errorMsg = 'Database connection failed: ' . $e->getMessage();
            } else {
                $errorMsg = 'Database connection failed. Please contact the administrator.';
            }

            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $errorMsg,
            ]);
            exit;
        }
    }

    /**
     * Get the singleton Database instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection.
     */
    public function getConnection(): PDO
    {
        return $this->conn;
    }
}
