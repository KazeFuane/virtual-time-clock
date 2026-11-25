<?php
/**
 * GET, POST, PATCH /api/admin/users.php
 * Administrative employee management endpoint (administrators only).
 *
 * GET                              -> list users + roles
 * POST  {username, email, password?, generate_password?, first_name, last_name, role_id}
 *                                  -> create account; returns the password once
 * PATCH {id, action: "reset_password", password?}  -> set/generate a new password; returns it once
 * PATCH {id, username?, first_name?, last_name?, email?, role_id?, is_active?} -> update account fields
 *
 * Passwords are only stored as bcrypt hashes. The plain-text password is
 * returned in the response of the create/reset call and never again.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$admin = AdminMiddleware::requireAdmin();
header('Cache-Control: no-store'); // responses may contain a one-time password
$userService = new UserService();
$method = $_SERVER['REQUEST_METHOD'];

$validRoleIds = array_map(fn($r) => (int) $r['id'], $userService->getRoles());

if ($method === 'GET') {
    Response::success('Users list retrieved.', [
        'users' => $userService->getAllUsers(),
        'roles' => $userService->getRoles()
    ]);
} elseif ($method === 'POST') {
    CsrfMiddleware::validate();
    $input = Validator::getJsonInput();

    $username  = trim($input['username'] ?? '');
    $email     = trim($input['email'] ?? '');
    $firstName = trim($input['first_name'] ?? '');
    $lastName  = trim($input['last_name'] ?? '');
    $roleId    = (int) ($input['role_id'] ?? 1);
    $generate  = !empty($input['generate_password']);
    $password  = $generate ? UserService::generatePassword() : (string) ($input['password'] ?? '');

    if ($firstName === '' || $lastName === '') {
        Response::error('First and last name are required.', 400);
    }
    foreach ([
        Validator::validateUsername($username),
        Validator::validateEmail($email),
        Validator::validatePassword($password),
    ] as $err) {
        if ($err !== null) {
            Response::error($err, 400);
        }
    }
    if (!in_array($roleId, $validRoleIds, true)) {
        Response::error('Invalid role.', 400);
    }

    try {
        $userId = $userService->createUser([
            'username'   => $username,
            'email'      => $email,
            'password'   => $password,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'role_id'    => $roleId,
            'is_active'  => 1
        ]);
        Response::success('Account created successfully.', [
            'user_id'  => $userId,
            'username' => $username,
            'password' => $password,
        ], 201);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            Response::error('Username or email is already registered.', 409);
        }
        Response::error('Database error creating account.', 500);
    }
} elseif ($method === 'PATCH') {
    CsrfMiddleware::validate();
    $input = Validator::getJsonInput();
    $id = (int) ($input['id'] ?? 0);

    if ($id <= 0 || $userService->getUserById($id) === null) {
        Response::error('User not found.', 404);
    }

    // --- Password reset ---
    if (($input['action'] ?? '') === 'reset_password') {
        $custom = (string) ($input['password'] ?? '');
        $newPassword = $custom !== '' ? $custom : UserService::generatePassword();

        if (($err = Validator::validatePassword($newPassword)) !== null) {
            Response::error($err, 400);
        }

        try {
            $userService->resetPassword($id, $newPassword);
        } catch (\PDOException $e) {
            Response::error('Database error resetting password.', 500);
        }
        Response::success('Password reset successfully.', ['user_id' => $id, 'password' => $newPassword]);
    }

    // --- Profile / role / status update ---
    $update = [];
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
        if (isset($input[$field])) {
            $value = trim((string) $input[$field]);
            if ($value === '') {
                Response::error(t('{field} cannot be empty.', ['field' => t($label)]), 400);
            }
            $update[$field] = $value;
        }
    }
    if (isset($input['username'])) {
        $username = trim((string) $input['username']);
        if (($err = Validator::validateUsername($username)) !== null) {
            Response::error($err, 400);
        }
        $update['username'] = $username;
    }
    if (isset($input['email'])) {
        $email = trim((string) $input['email']);
        if (($err = Validator::validateEmail($email)) !== null) {
            Response::error($err, 400);
        }
        $update['email'] = $email;
    }
    if (isset($input['role_id'])) {
        $roleId = (int) $input['role_id'];
        if (!in_array($roleId, $validRoleIds, true)) {
            Response::error('Invalid role.', 400);
        }
        $update['role_id'] = $roleId;
    }
    if (isset($input['is_active'])) {
        $update['is_active'] = $input['is_active'] ? 1 : 0;
    }

    // Admins cannot lock themselves out.
    if ($id === (int) $admin['id']) {
        if (isset($update['is_active']) && $update['is_active'] === 0) {
            Response::error('You cannot deactivate your own account.', 400);
        }
        if (isset($update['role_id']) && $update['role_id'] !== (int) $admin['role_id']) {
            Response::error('You cannot change your own role.', 400);
        }
    }

    try {
        $userService->updateUser($id, $update);

        // Keep the admin's own session (header name, etc.) in sync after self-edits.
        if ($id === (int) $admin['id']) {
            foreach (['username', 'email', 'first_name', 'last_name'] as $field) {
                if (isset($update[$field])) {
                    $_SESSION[$field] = $update[$field];
                }
            }
        }

        Response::success('Account updated successfully.', ['user' => $userService->getUserById($id)]);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            Response::error('That username or email is already registered.', 409);
        }
        Response::error('Database error updating user.', 500);
    }
} else {
    Response::error('Method not allowed.', 405);
}
