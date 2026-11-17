<?php
/**
 * Login page handler.
 */
require_once __DIR__ . '/../src/bootstrap.php';

if (AuthMiddleware::isLoggedIn()) {
    header('Location: /virtual-time-clock/public/dashboard.php');
    exit;
}

require_once ROOT_PATH . '/views/login.php';
