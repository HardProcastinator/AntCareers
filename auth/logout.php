<?php
/**
 * AntCareers — Logout Endpoint
 * POST /auth/logout.php  (GET also accepted for simplicity)
 *
 * Actions performed:
 *   1. Delete the remember-me token from the database.
 *   2. Expire the remember-me cookie in the browser.
 *   3. Clear $_SESSION data.
 *   4. Expire the session cookie in the browser.
 *   5. Destroy the server-side session.
 *   6. Redirect to the login page.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// 1 & 2 — Remove remember-me token from DB and expire browser cookie
clearRememberMeCookie($userId);

// 3 — Wipe all session variables
$_SESSION = [];

// 4 — Expire the PHP session cookie in the browser
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

// 5 — Destroy server-side session data
session_destroy();

// 6 — Redirect to login
header('Location: antcareers_login.php');
exit;
