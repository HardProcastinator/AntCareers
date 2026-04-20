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

$respondError = static function (string $message, int $status = 200, string $errorType = '') use ($isJsonRequest): void {
    if ($isJsonRequest) {
        $resp = ['success' => false, 'message' => $message];
        if ($errorType !== '') $resp['error_type'] = $errorType;
        jsonResponse($resp, $status);
    }

    $_SESSION['login_error'] = $message;
    header('Location: ' . url('auth/antcareers_login.php'));
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
    'SELECT id, email, password_hash, full_name, account_type, is_verified, is_active,
            COALESCE(account_status, \'active\') AS account_status,
            status_reason, status_expires_at,
            company_name, avatar_url, COALESCE(must_change_password, 0) AS must_change_password
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

// pending_approval accounts have is_active=0, so $passwordOk fails before the status
// check below is ever reached. Intercept here so they see the real reason.
if (!$passwordOk && $user !== false
    && (string)($user['account_status'] ?? '') === 'pending_approval'
    && password_verify($password, $user['password_hash'])) {
    recordLoginAttempt($email, $ip, false);
    $respondError('Your account is pending admin approval. You will be notified once it is reviewed.', 200, 'pending');
}

recordLoginAttempt($email, $ip, $passwordOk);

if (!$passwordOk) {
    $respondError('Incorrect email or password. Please try again.');
}

// Block by account_status (column may not exist on older schema — default to 'active')
$accountStatus = (string)($user['account_status'] ?? 'active');

if ($accountStatus === 'pending_approval') {
    $respondError('Your account is pending admin approval. You will be notified once it is reviewed.', 200, 'pending');
}

if ($accountStatus === 'suspended') {
    $expiresAt = $user['status_expires_at'] ?? null;
    $reason    = trim((string)($user['status_reason'] ?? ''));
    $msg = 'Your account has been suspended.';
    if ($reason !== '') {
        $msg .= ' Reason: ' . $reason;
    }
    if ($expiresAt !== null) {
        $msg .= ' Suspension lifts on: ' . date('M j, Y g:i A', strtotime($expiresAt));
    }
    $respondError($msg, 200, 'suspended');
}

if ($accountStatus === 'banned') {
    $reason = trim((string)($user['status_reason'] ?? ''));
    $msg = 'Your account has been permanently banned from AntCareers.';
    if ($reason !== '') {
        $msg .= ' Reason: ' . $reason;
    }
    $respondError($msg, 200, 'banned');
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
$_SESSION['company_name'] = $user['company_name'] ?? '';
$_SESSION['avatar_url']   = $user['avatar_url'] ?? '';

// Check if must change password (recruiter first-login)
if ((int)$user['must_change_password'] === 1) {
    $_SESSION['must_change_password'] = true;
}

// Remember me
if ($remember) {
    setRememberMeCookie((int)$user['id']);
}

// Update last login timestamp
$db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
   ->execute([':id' => $user['id']]);

// Log the successful login
logActivity(
    (int)$user['id'],
    (int)$user['id'],
    'login',
    'user',
    (int)$user['id'],
    "User '{$user['email']}' logged in."
);

// Determine redirect URL based on account type
// If must_change_password is set, always redirect to force-change page
if (!empty($_SESSION['must_change_password'])) {
    $redirect = url('auth/force_change_password.php');
} else {
    $redirect = match ($accountType) {
        'seeker'    => url('seeker/antcareers_seekerDashboard.php'),
        'employer'  => url('employer/employer_dashboard.php'),
        'recruiter' => url('recruiter/recruiter_dashboard.php'),
        'admin'     => url('admin/admin_dashboard.php'),
        default     => url('index.php'),
    };
}

$respondSuccess($redirect);