<?php
/**
 * Entry point redirector.
 */
require_once __DIR__ . '/../src/bootstrap.php';

if (AuthMiddleware::isLoggedIn()) {
    header('Location: /virtual-time-clock/public/dashboard.php');
} else {
    header('Location: /virtual-time-clock/public/login.php');
}
exit;
