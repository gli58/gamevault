<?php
require_once '../config.php';

// Make sure the session is started before manipulating it
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear all session data
$_SESSION = [];

// Destroy the session cookie
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

// Destroy the session on the server
session_destroy();

// Optional: also clear known auth flags to be extra safe
// (harmless even after destroy; helps in some proxies/opcache scenarios)
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['is_admin']);

// Redirect to homepage and stop execution
redirect('../index.php');
exit;
