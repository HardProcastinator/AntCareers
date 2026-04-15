<?php
/**
 * AntCareers — Database Configuration & Bootstrap
 *
 * This file is the single entry-point for all backend scripts.
 * Every auth/*.php and includes/*.php file begins with:
 *   require_once dirname(__DIR__) . '/config.php';
 *
 * SETUP CHECKLIST (VistaPanel / InfinityFree):
 *   1. VistaPanel → MySQL Databases → create database & user.
 *   2. Copy the FULL prefixed DB name (e.g. epiz_12345678_antcareers_db).
 *   3. The DB hostname is shown in VistaPanel (e.g. sql200.epizy.com) — NOT localhost.
 *   4. Replace all DB_* constants below with those exact values.
 *   5. Import sql/schema.sql via VistaPanel → phpMyAdmin.
 *   6. Set APP_URL to your free subdomain or custom domain (no trailing slash).
 *   7. On localhost only, set 'secure' => false in session_set_cookie_params().
 *
 * ⚠ InfinityFree blocks PHP mail(). The forgot-password token is saved to the DB
 *   correctly, but no email is sent until you wire up PHPMailer + SMTP.
 */

declare(strict_types=1);

// ── Database credentials ──────────────────────────────────────────────────────
// XAMPP defaults — localhost with no password
// DB_HOST : localhost (XAMPP default)
// DB_NAME : antcareers (can be any name you created in phpMyAdmin)
// DB_USER : root (XAMPP default user)
// DB_PASS : '' (XAMPP default has no password)
define('DB_HOST',    '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME',    'antcareers');
define('DB_USER',    'root');
define('DB_PASS',    '');

define('DB_CHARSET', 'utf8mb4');

// ── Application settings ──────────────────────────────────────────────────────
define('APP_URL',    'http://localhost/antcareers'); // ← local XAMPP development
define('APP_NAME', 'AntCareers');

// ── Auth / security constants ─────────────────────────────────────────────────
define('REMEMBER_ME_DAYS',           30);  // "Remember me" cookie lifetime (days)
define('RESET_TOKEN_EXPIRY_MINUTES', 30);  // Forgot-password link TTL (minutes)
define('MAX_LOGIN_ATTEMPTS',          5);  // Failed attempts before lockout
define('LOCKOUT_MINUTES',            15);  // How long the lockout lasts

// ── Session bootstrap ─────────────────────────────────────────────────────────
// Must be called before any output. All auth/*.php files rely on $_SESSION.
if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS automatically so the session cookie works on both HTTP and HTTPS.
    // 'secure' => true would silently drop the cookie on plain HTTP, causing CSRF mismatches.
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,       // byethost serves over HTTP — keep false to avoid CSRF token mismatch
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── PDO singleton factory ─────────────────────────────────────────────────────
/**
 * Returns a shared PDO instance (lazy-initialized on first call).
 * Uses prepared statements and throws exceptions on error.
 * DB credentials are never exposed to the HTTP response.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn     = 'mysql:host=' . DB_HOST
                 . ';port='     . DB_PORT
                 . ';dbname='   . DB_NAME
                 . ';charset='  . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error server-side; never expose it to the client.
            error_log('[AntCareers] DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode([
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again later.',
            ]));
        }
    }

    return $pdo;
}

// ── URL helper ────────────────────────────────────────────────────────────────
/**
 * Build a root-relative URL for a given path.
 * Preserves the current theme preference as a query parameter.
 *
 * Examples:
 *   url('seeker/antcareers_seekerDashboard.php')         → /antcareers_seekerDashboard.php
 *   url('seeker/antcareers_seekerJobs.php', true)        → /antcareers_seekerJobs.php?theme=dark
 *   url('auth/logout.php')                         → /auth/logout.php
 */
function url(string $path, bool $withTheme = false): string
{
    // Strip leading slash so we never double-slash
    $path = ltrim($path, '/');

    // Build a web base path from filesystem paths in a Windows-safe way.
    $docRootFs    = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\'));
    $projectRootFs = str_replace('\\', '/', rtrim(__DIR__, '/\\'));

    if ($docRootFs !== '' && stripos($projectRootFs, $docRootFs) === 0) {
        $basePath = substr($projectRootFs, strlen($docRootFs));
    } else {
        // Fallback when DOCUMENT_ROOT is missing/mismatched
        $basePath = '/' . basename($projectRootFs);
    }

    $basePath = '/' . trim($basePath, '/') . '/';
    $full = $basePath . $path;

    if ($withTheme) {
        $theme = htmlspecialchars($_GET['theme'] ?? '', ENT_QUOTES, 'UTF-8');
        if ($theme !== '') {
            $full .= (str_contains($full, '?') ? '&' : '?') . 'theme=' . $theme;
        }
    }

    return $full;
}
//anothercomment