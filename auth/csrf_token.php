<?php
/**
 * AntCareers — CSRF Token Endpoint
 * GET /auth/csrf_token.php
 *
 * Returns a fresh CSRF token stored in the PHP session.
 * Called once on page-load by the static HTML front-end (../auth/antcareers_login.php)
 * so that subsequent AJAX POSTs can include the token for verification.
 *
 * Response JSON: { "csrf_token": "<hex string>" }
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

// Prevent the browser / CDN from caching the CSRF token response.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['csrf_token' => csrfToken()]);
