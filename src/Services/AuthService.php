<?php
/**
 * Virtual Time Clock - Authentication Service
 *
 * Handles login, logout, and session management.
 * Uses password_hash() / password_verify() for secure authentication.
 */

class AuthService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Authenticate a user with username and password.
     * Returns user data on success, or null on failure.
     */
    public function login(string $username, string $password): ?array
    {
        // Rate limiting: check failed attempts
        if ($this->isLockedOut()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT u.id, u.role_id, u.username, u.email, u.password_hash,
                   u.first_name, u.last_name, u.is_active, r.name AS role
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ?
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->recordFailedAttempt();
            return null;
        }

        // Check if account is active
        if (!$user['is_active']) {
            $this->recordFailedAttempt();
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedAttempt();
            return null;
        }

        // Success: create session
        $this->createSession($user);
        $this->resetFailedAttempts();

        return [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'role'       => $user['role'],
            'role_id'    => $user['role_id'],
        ];
    }

    /**
     * Destroy the current session (logout).
     */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Create a session for the authenticated user.
     */
    private function createSession(array $user): void
    {
        // Regenerate session ID to prevent fixation attacks (if headers not already sent)
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['login_time'] = time();

        // Regenerate CSRF token on new session
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Record a failed login attempt in the session.
     */
    private function recordFailedAttempt(): void
    {
        if (!isset($_SESSION['failed_attempts'])) {
            $_SESSION['failed_attempts'] = 0;
        }
        $_SESSION['failed_attempts']++;
        $_SESSION['last_failed_attempt'] = time();
    }

    /**
     * Reset failed attempt counter.
     */
    private function resetFailedAttempts(): void
    {
        unset($_SESSION['failed_attempts'], $_SESSION['last_failed_attempt']);
    }

    /**
     * Check if the user is locked out due to too many failed attempts.
     * Locks out for 15 minutes after 5 failures.
     */
    private function isLockedOut(): bool
    {
        $maxAttempts  = 5;
        $lockDuration = 900; // 15 minutes

        $attempts = $_SESSION['failed_attempts'] ?? 0;
        $lastFail = $_SESSION['last_failed_attempt'] ?? 0;

        if ($attempts >= $maxAttempts) {
            if ((time() - $lastFail) < $lockDuration) {
                return true;
            }
            // Lockout period expired, reset
            $this->resetFailedAttempts();
        }

        return false;
    }

    /**
     * Get remaining lockout seconds, or 0 if not locked out.
     */
    public function getLockoutRemaining(): int
    {
        $maxAttempts  = 5;
        $lockDuration = 900;

        $attempts = $_SESSION['failed_attempts'] ?? 0;
        $lastFail = $_SESSION['last_failed_attempt'] ?? 0;

        if ($attempts >= $maxAttempts) {
            $remaining = $lockDuration - (time() - $lastFail);
            return max(0, $remaining);
        }

        return 0;
    }
}
