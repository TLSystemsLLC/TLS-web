<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Auth.php';

// Initialize authentication
$auth = new Auth(
    Config::get('DB_SERVER'),
    Config::get('DB_USERNAME'),
    Config::get('DB_PASSWORD')
);

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    header('Location: /tls/dashboard.php');
    exit;
} else {
    header('Location: /tls/login.php');
    exit;
}