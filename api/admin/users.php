<?php
/**
 * GET, POST, PATCH /api/admin/users.php
 * Administrative employee management endpoint.
 */
require_once __DIR__ . '/../../src/bootstrap.php';

$admin = AdminMiddleware::requireAdmin();
$userService = new UserService();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $users = $userService->getAllUsers();
    Response::success('Users list retrieved.', [
        'users' => $users,
        'roles' => $userService->getRoles()
    ]);
} elseif ($method === 'POST') {
    CsrfMiddleware::validate();
    $input = Validator::getJsonInput();

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $roleId = (int) ($input['role_id'] ?? 1);

    if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        Response::error('All employee fields are required.', 400);
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
        Response::success('Employee registered successfully.', ['user_id' => $userId], 201);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            Response::error('Username or email is already registered.', 409);
        }
        Response::error('Database error creating employee.', 500);
    }
} elseif ($method === 'PATCH') {
    CsrfMiddleware::validate();
    $input = Validator::getJsonInput();
    $id = (int) ($input['id'] ?? 0);

    if ($id <= 0) {
        Response::error('Valid user ID is required.', 400);
    }

    try {
        $userService->updateUser($id, $input);
        Response::success('Employee updated successfully.');
    } catch (\PDOException $e) {
        Response::error('Database error updating user.', 500);
    }
} else {
    Response::error('Method not allowed.', 405);
}
