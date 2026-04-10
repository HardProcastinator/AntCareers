<?php
/**
 * AntCareers — Login Endpoint
 * POST /auth/login.php
 *
 * Accepts a JSON body from the front-end:
 *   {
 *     "email":       "user@example.com",
 *     "password":    "secret",
 *     "remember":    true,
 *     "csrf_token":  "<token from csrf_token.php>"
 *   }
 *
 * Success response (200):
 *   { "success": true, "redirect": "../antcareers_seekerDashboard.php" }
 *
 * Failure response (200 / 429):
 *   { "success": false, "message": "Human-readable error" }
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

requirePost();
$contentType   = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$isJsonRequest = stripos($contentType, 'application/json') !== false;

if ($isJsonRequest) {
    header('Content-Type: application/json; charset=utf-8');
}

// Parse request body (JSON for fetch, form-data for no-JS fallback)
if ($isJsonRequest) {
    $raw  = json_decode(file_get_contents('php://input'), true);
    $body = is_array($raw) ? $raw : [];
} else {
    $body = $_POST;
}

$email    = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');
$remember = (bool)($body['remember'] ?? false);
$csrf     = (string)($body['csrf_token'] ?? '');

$respondError = static function (string $message, int $status = 200) use ($isJsonRequest): void {
    if ($isJsonRequest) {
        jsonResponse(['success' => false, 'message' => $message], $status);
    }

    header('Location: antcareers_login.php?error=' . rawurlencode($message));
    exit;
};

$respondSuccess = static function (string $redirect) use ($isJsonRequest): void {
    if ($isJsonRequest) {
        jsonResponse(['success' => true, 'redirect' => $redirect]);
    }

    header('Location: ' . $redirect);
    exit;
};

// CSRF
if ($isJsonRequest) {
    verifyCsrf($csrf);
} elseif (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    $respondError('Invalid request. Please refresh the page and try again.', 403);
}

// Input validation
if ($email === '' || $password === '') {
    $respondError('Please fill in all fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respondError('Please enter a valid email address.');
}

$ip = getClientIp();

// Rate limiting
if (isRateLimited($email, $ip)) {
    $respondError('Too many failed attempts. Please wait ' . LOCKOUT_MINUTES . ' minutes and try again.', 429);
}

// Fetch user
$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, email, password_hash, full_name, account_type, is_verified, is_active
     FROM users
     WHERE email = :email
     LIMIT 1'
);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Verify credentials
$passwordOk = $user !== false
           && $user['is_active']
           && password_verify($password, $user['password_hash']);

recordLoginAttempt($email, $ip, $passwordOk);

if (!$passwordOk) {
    $respondError('Incorrect email or password. Please try again.');
}

// Rehash if needed
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
    $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
       ->execute([
           ':hash' => password_hash($password, PASSWORD_BCRYPT),
           ':id'   => $user['id'],
       ]);
}

// Regenerate session ID
session_regenerate_id(true);

// Normalize account type
$accountType = strtolower(trim((string)$user['account_type']));

// Store user data in session
$_SESSION['user_id']      = (int)$user['id'];
$_SESSION['user_email']   = $user['email'];
$_SESSION['user_name']    = $user['full_name'];
$_SESSION['account_type'] = $accountType;

// Remember me
if ($remember) {
    setRememberMeCookie((int)$user['id']);
}

// Update last login timestamp
$db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
   ->execute([':id' => $user['id']]);

// Determine redirect URL based on account type
$redirect = match ($accountType) {
    'seeker'   => url('seeker/antcareers_seekerDashboard.php'),
    'employer' => url('employer/employer_dashboard.php'),
    'admin'    => url('admin/admin_dashboard.php'),
    default    => url('index.php'),
};

$respondSuccess($redirect);