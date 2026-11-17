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
                   u.is_active, u.created_at, r.name AS role
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
     * Get all roles.
     */
    public function getRoles(): array
    {
        return $this->db->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
    }
}
