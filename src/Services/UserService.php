<?php
/**
 * Virtual Time Clock - User Service
 *
 * Handles user CRUD operations for administration.
 */

class UserService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all users with their roles.
     */
    public function getAllUsers(): array
    {
        $stmt = $this->db->query("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                   u.is_active, u.created_at, u.role_id, r.name AS role
            FROM users u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.last_name ASC, u.first_name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get a user by ID.
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                   u.is_active, u.role_id, r.name AS role
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Find people for the header search bar, by first name, last name,
     * "first last", "last first", username, or user ID ("12" or "#12").
     *
     * Managers only see active employees (the agents they manage);
     * administrators see every active account except their own.
     * Employees never see anyone (returns an empty list).
     */
    public function searchPeople(array $caller, string $query, int $limit = 8): array
    {
        $role = $caller['role'] ?? 'employee';
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        // A number (optionally written "#12") also matches that user ID
        $id = preg_match('/^#?(\d{1,10})$/', $query, $m) ? (int) $m[1] : null;
        if ($id !== null) {
            $query = $m[1];
        }
        if (!in_array($role, ['administrator', 'manager'], true) || ($id === null && mb_strlen($query) < 2)) {
            return [];
        }

        // Typed % and _ are matched literally, not as LIKE wildcards
        $text = strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
        $like = '%' . $text . '%';
        $starts = $text . '%';

        $sql = "SELECT u.id, u.username, u.first_name, u.last_name, r.name AS role
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1
                  AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
                       OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
                       OR CONCAT(u.last_name, ' ', u.first_name) LIKE ?
                       OR u.id = ?)";
        $params = [$like, $like, $like, $like, $like, $id ?? 0];

        if ($role === 'manager') {
            $sql .= " AND r.name = 'employee'";
        } else {
            $sql .= " AND u.id <> ?";
            $params[] = (int) $caller['id'];
        }

        // The exact ID first, then names that start with the text, then alphabetical
        $sql .= " ORDER BY (u.id = ?) DESC,
                           (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?) DESC,
                           u.last_name ASC, u.first_name ASC
                  LIMIT " . max(1, min(20, $limit));
        array_push($params, $id ?? 0, $starts, $starts, $starts);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['role_id'],
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['first_name'],
            $data['last_name'],
            $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a user.
     */
    public function updateUser(int $id, array $data): void
    {
        $fields = [];
        $values = [];

        if (isset($data['username'])) {
            $fields[] = 'username = ?';
            $values[] = $data['username'];
        }
        if (isset($data['first_name'])) {
            $fields[] = 'first_name = ?';
            $values[] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $fields[] = 'last_name = ?';
            $values[] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }
        if (isset($data['role_id'])) {
            $fields[] = 'role_id = ?';
            $values[] = $data['role_id'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $values[] = $data['is_active'];
        }
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return;
        }

        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    /**
     * Undo "Deactivate" (the "Undo" pop-up, see UndoService): the account
     * can sign in again. Runs inside UndoService's transaction.
     */
    public function undoDeactivate(int $id): void
    {
        $user = $this->getUserById($id);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        if ((int) $user['is_active'] === 1) {
            throw new RuntimeException('This account is already active.');
        }
        $this->db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$id]);
    }

    /**
     * Set a new password for a user (stored as a bcrypt hash only).
     */
    public function resetPassword(int $id, string $newPassword): void
    {
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $id]);
    }

    /**
     * Generate a random, readable temporary password (no ambiguous characters).
     */
    public static function generatePassword(int $length = 12): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%&*?';
        $all = $upper . $lower . $digits . $symbols;

        // Guarantee one of each class, fill the rest randomly, then shuffle.
        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    /**
     * Get all roles.
     */
    public function getRoles(): array
    {
        return $this->db->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
    }
}
