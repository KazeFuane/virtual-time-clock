<?php
/**
 * GET /api/search.php?q=text
 *
 * People for the header search bar. Managers get matching employees,
 * administrators get any matching active account. Employees always get
 * an empty list: they can't look up other users (the header only searches
 * app pages for them).
 */
require_once __DIR__ . '/../src/bootstrap.php';

$caller = AuthMiddleware::requireAuth();

try {
    $query = mb_substr((string) ($_GET['q'] ?? ''), 0, 100);
    $people = array_map(fn($u) => [
        'id'       => (int) $u['id'],
        'name'     => trim($u['first_name'] . ' ' . $u['last_name']) ?: $u['username'],
        'username' => $u['username'],
        'role'     => $u['role'],
    ], (new UserService())->searchPeople($caller, $query));

    Response::success('Search results.', ['people' => $people]);
} catch (\Throwable $e) {
    Response::error('An unexpected error occurred while searching.', 500);
}
