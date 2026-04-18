<?php
/**
 * AntCareers — Reset Password Endpoint
 *
 * GET  /auth/reset_password.php?token=<plaintext_token>
 *   Validates the token and returns its status as JSON.
 *   The front-end can use this to show/hide the new-password form.
 *
 *   Success: { "success": true,  "message": "Token valid.", "csrf": "<token>" }
 *   Failure: { "success": false, "message": "Link expired or already used." }
 *
 * POST /auth/reset_password.php
 *   Applies the new password.
 *   Request body: { "token": "...", "password": "...", "csrf_token": "..." }
 *
 *   Success: { "success": true,  "message": "Password updated successfully." }
 *   Failure: { "success": false, "message": "..." }
 *
 * Security measures:
 *   - Token stored as SHA-256 hash; plain token travels only in the email link.
 *   - Transaction wraps password update + token invalidation.
 *   - All remember-me tokens are invalidated after a password reset.
 *   - CSRF verified on POST.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];

// =============================================================================
// GET — validate token, return status + fresh CSRF token for the reset form
// =============================================================================
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $plainToken = trim($_GET['token'] ?? '');

    if ($plainToken === '') {
        jsonResponse(['success' => false, 'message' => 'Missing reset token.'], 400);
    }

    $tokenHash = hash('sha256', $plainToken);
    $db        = getDB();

    // Clean up expired tokens on each validation
    $db->exec("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");

    $stmt = $db->prepare(
        'SELECT id
         FROM   password_reset_tokens
         WHERE  token_hash = :hash
           AND  expires_at > NOW()
           AND  used_at    IS NULL
         LIMIT  1'
    );
    $stmt->execute([':hash' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse([
            'success' => false,
            'message' => 'This reset link has expired or has already been used. Please request a new one.',
        ], 410);
    }

    // Return the CSRF token so the reset form can POST it back
    jsonResponse([
        'success' => true,
        'message' => 'Token valid.',
        'csrf'    => csrfToken(),
    ]);
}

// =============================================================================
// POST — apply the new password
// =============================================================================
requirePost();
header('Content-Type: application/json; charset=utf-8');

// ── Parse input ───────────────────────────────────────────────────────────────
$raw  = json_decode(file_get_contents('php://input'), true);
$body = is_array($raw) ? $raw : [];

$plainToken = trim((string)($body['token']      ?? ''));
$password   = (string)($body['password']        ?? '');
$csrf       = (string)($body['csrf_token']      ?? '');

// ── CSRF ──────────────────────────────────────────────────────────────────────
verifyCsrf($csrf);

// ── Input validation ──────────────────────────────────────────────────────────
if ($plainToken === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Missing required fields.']);
}

if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
}

// ── Fetch token row ───────────────────────────────────────────────────────────
$tokenHash = hash('sha256', $plainToken);
$db        = getDB();

$stmt = $db->prepare(
    'SELECT id, user_id
     FROM   password_reset_tokens
     WHERE  token_hash = :hash
       AND  expires_at > NOW()
       AND  used_at    IS NULL
     LIMIT  1'
);
$stmt->execute([':hash' => $tokenHash]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    jsonResponse([
        'success' => false,
        'message' => 'Reset link is invalid or has expired. Please request a new one.',
    ], 410);
}

// ── Apply changes inside a transaction ───────────────────────────────────────
$db->beginTransaction();

try {
    // Update the user's password
    $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
       ->execute([
           ':hash' => password_hash($password, PASSWORD_BCRYPT),
           ':id'   => $resetRow['user_id'],
       ]);

    // Mark token as consumed so it cannot be reused
    $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')
       ->execute([':id' => $resetRow['id']]);

    // Invalidate all remember-me tokens — user must log in again on all devices
    $db->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
       ->execute([':uid' => $resetRow['user_id']]);

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    error_log('[AntCareers] reset_password failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again.'], 500);
}

jsonResponse([
    'success'  => true,
    'message'  => 'Password updated successfully. You can now sign in with your new password.',
    'redirect' => '../antcareers_login.php',
]);
