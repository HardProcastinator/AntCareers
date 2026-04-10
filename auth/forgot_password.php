<?php
/**
 * AntCareers — Forgot Password Endpoint
 * POST /auth/forgot_password.php
 *
 * Accepts a JSON body:
 *   { "email": "user@example.com", "csrf_token": "<token>" }
 *
 * Response is ALWAYS the same success message regardless of whether the
 * email exists in the database. This prevents email enumeration attacks.
 *
 * On a real address:
 *   1. Any previous unused reset tokens for that user are invalidated.
 *   2. A new SHA-256-hashed token is stored in password_reset_tokens.
 *   3. An email with a signed reset link is sent.
 *
 * NOTE: The built-in mail() function is used as a placeholder. For production
 * replace it with PHPMailer + SMTP or a transactional API (SendGrid, Mailgun).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

requirePost();
header('Content-Type: application/json; charset=utf-8');

// ── Parse input ───────────────────────────────────────────────────────────────
$raw  = json_decode(file_get_contents('php://input'), true);
$body = is_array($raw) ? $raw : [];

$email = trim((string)($body['email']      ?? ''));
$csrf  = (string)($body['csrf_token']      ?? '');

// ── CSRF ──────────────────────────────────────────────────────────────────────
verifyCsrf($csrf);

// Always return this message — never tell the caller if the email exists.
$genericOk = 'If that email is registered, a reset link has been sent. Check your inbox.';

// ── Validate email format — still return success to prevent enumeration ───────
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => true, 'message' => $genericOk]);
}

// ── Look up user ──────────────────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare(
    'SELECT id FROM users WHERE email = :email AND is_active = 1 LIMIT 1'
);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if ($user) {
    $userId    = (int)$user['id'];
    $plainToken = bin2hex(random_bytes(32));          // 64-char hex plain token
    $tokenHash  = hash('sha256', $plainToken);
    $expiresAt  = date('Y-m-d H:i:s', time() + (RESET_TOKEN_EXPIRY_MINUTES * 60));

    // Invalidate any existing unused tokens for this user
    $db->prepare(
        'UPDATE password_reset_tokens
         SET    used_at = NOW()
         WHERE  user_id = :uid AND used_at IS NULL'
    )->execute([':uid' => $userId]);

    // Persist the new token
    $db->prepare(
        'INSERT INTO password_reset_tokens
                (user_id, token_hash, expires_at, ip_address)
         VALUES (:uid,    :hash,      :exp,       :ip)'
    )->execute([
        ':uid'  => $userId,
        ':hash' => $tokenHash,
        ':exp'  => $expiresAt,
        ':ip'   => getClientIp(),
    ]);

    // ── Send reset email ──────────────────────────────────────────────────────
    // ⚠ VistaPanel / InfinityFree BLOCKS PHP mail().
    //   The token is safely stored in the DB above. To actually deliver the
    //   email, replace the mail() call below with PHPMailer + Gmail SMTP
    //   (or any transactional API such as SendGrid / Mailgun).
    //   Until then, you can retrieve the plain token from the DB for testing.
    $resetUrl = APP_URL . '/auth/reset_password.php?token=' . urlencode($plainToken);
    $host     = parse_url(APP_URL, PHP_URL_HOST) ?: 'example.com';

    $subject = APP_NAME . ' — Password Reset Request';
    $body    = "Hi,\n\n"
             . "We received a request to reset the password for your " . APP_NAME . " account.\n\n"
             . "Click the link below to set a new password.\n"
             . "This link expires in " . RESET_TOKEN_EXPIRY_MINUTES . " minutes:\n\n"
             . $resetUrl . "\n\n"
             . "If you did not request a password reset, you can safely ignore this email.\n"
             . "Your password will not be changed.\n\n"
             . "— The " . APP_NAME . " Team";

    $headers = implode("\r\n", [
        'From: noreply@' . $host,
        'Reply-To: noreply@' . $host,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    @mail($email, $subject, $body, $headers);

    error_log('[AntCareers] Password reset token issued for: ' . $email);
}

jsonResponse(['success' => true, 'message' => $genericOk]);
