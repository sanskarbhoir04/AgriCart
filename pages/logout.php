<?php
/**
 * AgriCart - Logout Handler
 * Session destroy करतो आणि login page वर redirect करतो
 */
require_once __DIR__ . '/../includes/security.php';
agri_session_start();

// Session data clear करा
$_SESSION = [];

// Session cookie delete करा
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session destroy करा
session_destroy();

// Login page वर redirect करा
header("Location: login.php?logout=1");
exit;
?>