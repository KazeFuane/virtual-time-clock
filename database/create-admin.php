<?php
/**
 * ClockWise - create the first administrator on a fresh database.
 *
 * For a live site, use this instead of seed.sql (which adds demo
 * accounts with public passwords). Run it once from a terminal, after
 * importing database/schema.sql:
 *
 *   php database/create-admin.php
 *
 * It adds the three roles (if missing) and asks for the admin's details.
 * Command line only: it refuses to run from a browser.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

function ask(string $label, bool $hidden = false): string
{
    echo $label . ': ';
    $canHide = $hidden && DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec');
    if ($canHide) {
        shell_exec('stty -echo 2>/dev/null');
    }
    $value = trim((string) fgets(STDIN));
    if ($canHide) {
        shell_exec('stty echo 2>/dev/null');
        echo PHP_EOL;
    }
    return $value;
}

$db = Database::getInstance()->getConnection();

// Roles, in the order the app expects (1 employee, 2 manager, 3 administrator)
$db->exec("INSERT IGNORE INTO roles (id, name) VALUES (1, 'employee'), (2, 'manager'), (3, 'administrator')");

echo "Create the ClockWise administrator account" . PHP_EOL;
$username = ask('Username');
$email    = ask('Email');
$first    = ask('First name');
$last     = ask('Last name');
$password = ask('Password (at least 8 characters)', true);
$confirm  = ask('Password again', true);

if ($username === '' || $email === '' || $first === '' || $last === '') {
    fwrite(STDERR, "All fields are required." . PHP_EOL);
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters." . PHP_EOL);
    exit(1);
}
if ($password !== $confirm) {
    fwrite(STDERR, "The two passwords do not match." . PHP_EOL);
    exit(1);
}

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);
if ((int) $stmt->fetchColumn() > 0) {
    fwrite(STDERR, "A user with that username or email already exists." . PHP_EOL);
    exit(1);
}

$stmt = $db->prepare("
    INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, is_active)
    VALUES (3, ?, ?, ?, ?, ?, 1)
");
$stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $first, $last]);

echo "Done. Sign in as '{$username}', then add managers and employees from the Admin page." . PHP_EOL;
