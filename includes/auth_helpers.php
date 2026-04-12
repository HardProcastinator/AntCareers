<?php
/**
 * AntCareers — Authentication Helpers
 *
 * Shared utilities used by all auth/*.php endpoints:
 *   - CSRF token generation & verification
 *   - Login rate-limiting
 *   - "Remember me" split-token pattern (secure, timing-attack resistant)
 *   - JSON response helper
 *   - Client IP detection
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';


// =============================================================================
// CSRF
// =============================================================================

/**
 * Return the session-scoped CSRF token, creating it if it does not exist yet.
 * Used by csrf_token.php to expose the token to the static HTML front-end.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify that $submitted matches the session token (constant-time compare).
 * Exits with a 403 JSON error on mismatch — never returns false.
 */
function verifyCsrf(string $submitted): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        jsonResponse(
            ['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.'],
            403
        );
    }
}


// =============================================================================
// Rate limiting (brute-force protection)
// =============================================================================

/**
 * Returns true if the email has accumulated MAX_LOGIN_ATTEMPTS failed attempts
 * in the last LOCKOUT_MINUTES window, OR the IP has accumulated 3× that threshold.
 *
 * Email and IP are checked independently so that shared/NAT IPs (including
 * localhost 127.0.0.1 on a local XAMPP setup) don't lock out unrelated accounts.
 */
function isRateLimited(string $email, string $ip): bool
{
    $db     = getDB();
    $window = date('Y-m-d H:i:s', time() - (LOCKOUT_MINUTES * 60));

    // Per-email lockout: prevents brute-forcing a specific account.
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt
         FROM   login_attempts
         WHERE  success      = 0
           AND  attempted_at > :window
           AND  email        = :email'
    );
    $stmt->execute([':window' => $window, ':email' => $email]);
    $row = $stmt->fetch();
    if ((int)($row['cnt'] ?? 0) >= MAX_LOGIN_ATTEMPTS) {
        return true;
    }

    // Per-IP lockout: higher threshold to avoid false positives on shared/NAT IPs.
    $stmt = $db->prepare(
        'SELECT COUNT(*) AS cnt
         FROM   login_attempts
         WHERE  success      = 0
           AND  attempted_at > :window
           AND  ip_address   = :ip'
    );
    $stmt->execute([':window' => $window, ':ip' => $ip]);
    $row = $stmt->fetch();

    return (int)($row['cnt'] ?? 0) >= (MAX_LOGIN_ATTEMPTS * 3);
}

/**
 * Insert one row into login_attempts for audit / rate-limiting.
 */
function recordLoginAttempt(string $email, string $ip, bool $success): void
{
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (email, ip_address, user_agent, success)
         VALUES (:email, :ip, :ua, :success)'
    );
    $stmt->execute([
        ':email'   => $email,
        ':ip'      => $ip,
        ':ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ':success' => $success ? 1 : 0,
    ]);
}

/**
 * Fetch lightweight public-facing platform stats for guest pages.
 * Falls back to zeroes if the schema is not fully available yet.
 *
 * @return array{live_jobs:int, companies_hiring:int}
 */
function getPublicPlatformStats(): array
{
    $db = getDB();

    try {
        $liveJobsStmt = $db->query(
            "SELECT COUNT(*)
             FROM jobs
             WHERE status = 'Active'
               AND (deadline IS NULL OR deadline >= CURDATE())"
        );

        $companiesStmt = $db->query(
            "SELECT COUNT(DISTINCT employer_id)
             FROM jobs
             WHERE status = 'Active'
               AND (deadline IS NULL OR deadline >= CURDATE())"
        );

        return [
            'live_jobs' => (int)$liveJobsStmt->fetchColumn(),
            'companies_hiring' => (int)$companiesStmt->fetchColumn(),
        ];
    } catch (PDOException $e) {
        error_log('[AntCareers] public stats: ' . $e->getMessage());
        return ['live_jobs' => 0, 'companies_hiring' => 0];
    }
}


// =============================================================================
// Remember-me (split-token pattern)
//
// Cookie value : "selector:plaintext_validator"
// DB stores    : "selector:sha256(plaintext_validator)"  in token_hash
//
// Why split?  The selector is used to look up the DB row (no timing leak).
//             The validator is hashed, so a DB breach cannot forge cookies.
// =============================================================================

/**
 * Issue a remember-me cookie and persist its hashed token to the DB.
 * Any existing tokens for the user are replaced with the new one.
 */
function setRememberMeCookie(int $userId): void
{
    $db        = getDB();
    $selector  = bin2hex(random_bytes(16));   // 32-char hex
    $validator = bin2hex(random_bytes(32));   // 64-char hex
    $hash      = hash('sha256', $validator);
    $expires   = time() + (REMEMBER_ME_DAYS * 86400);

    // One active remember-me token per user
    $db->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
       ->execute([':uid' => $userId]);

    $db->prepare(
        'INSERT INTO remember_tokens
                (user_id, token_hash, expires_at, ip_address, user_agent)
         VALUES (:uid,    :hash,      :exp,       :ip,        :ua)'
    )->execute([
        ':uid'  => $userId,
        ':hash' => $selector . ':' . $hash,
        ':exp'  => date('Y-m-d H:i:s', $expires),
        ':ip'   => getClientIp(),
        ':ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
    ]);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    setcookie('antcareers_rm', $selector . ':' . $validator, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Validate the remember-me cookie and restore the user session.
 * Returns the user row array on success, null on failure/absence.
 *
 * On token mismatch ALL tokens for that user are invalidated
 * (indicator of possible cookie theft).
 */
function checkRememberMeCookie(): ?array
{
    $cookie = $_COOKIE['antcareers_rm'] ?? null;
    if (!$cookie || substr_count($cookie, ':') !== 1) {
        return null;
    }

    [$selector, $validator] = explode(':', $cookie, 2);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT rt.id        AS token_id,
                rt.token_hash,
                rt.expires_at,
                u.id         AS user_id,
                u.email,
                u.full_name,
                u.account_type,
                u.is_active
         FROM   remember_tokens rt
         JOIN   users u ON u.id = rt.user_id
         WHERE  rt.token_hash LIKE :sel_prefix
           AND  rt.expires_at > NOW()
         LIMIT  1'
    );
    $stmt->execute([':sel_prefix' => $selector . ':%']);
    $row = $stmt->fetch();

    if (!$row || !$row['is_active']) {
        clearRememberMeCookie(null);
        return null;
    }

    // Extract the stored hash from "selector:hash"
    [, $storedHash] = explode(':', $row['token_hash'], 2);

    if (!hash_equals($storedHash, hash('sha256', $validator))) {
        // Mismatch — invalidate all tokens for this user
        $db->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
           ->execute([':uid' => $row['user_id']]);
        clearRememberMeCookie(null);
        return null;
    }

    return $row;
}

/**
 * Delete the remember-me cookie from the browser and the DB.
 * Pass null for $userId if the user ID is unknown (token mismatch path).
 */
function clearRememberMeCookie(?int $userId): void
{
    if ($userId !== null) {
        getDB()->prepare('DELETE FROM remember_tokens WHERE user_id = :uid')
               ->execute([':uid' => $userId]);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    setcookie('antcareers_rm', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}


// =============================================================================
// Utilities
// =============================================================================

/**
 * Send a JSON response with the given HTTP status and exit.
 *
 * @param  array<string,mixed> $data
 */
function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode($data));
}

/**
 * Enforce POST method; responds with 405 on anything else.
 */
function requirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

/**
 * Return the real client IP.
 * Checks Cloudflare, X-Forwarded-For, X-Real-IP, then REMOTE_ADDR.
 */
function getClientIp(): string
{
    $keys = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',   // Load balancer / proxy
        'HTTP_X_REAL_IP',         // Nginx proxy
        'REMOTE_ADDR',            // Direct connection
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}
