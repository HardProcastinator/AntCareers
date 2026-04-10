<?php
/**
 * AntCareers — Central Authentication Helper
 * includes/auth.php
 *
 * Usage:
 *   require_once __DIR__ . '/../config.php';   // starts session
 *   require_once __DIR__ . '/../includes/auth.php';
 *   requireLogin('seeker');                    // redirects if not authed
 *   $user = getUser();                         // returns user array
 */

declare(strict_types=1);

/**
 * Require the user to be logged in with a specific role.
 * Redirects to login if not authenticated, or to index if wrong role.
 *
 * @param string $role  'seeker' | 'employer' | 'admin'
 */
function requireLogin(string $role = 'seeker'): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . url('auth/antcareers_login.php'));
        exit;
    }
    $sessionRole = strtolower((string)($_SESSION['account_type'] ?? ''));
    if ($sessionRole !== strtolower($role)) {
        header('Location: ' . url('index.php'));
        exit;
    }
}

/**
 * Returns a normalised array of the current user's session data.
 * Safe to call after requireLogin().
 *
 * @return array{
 *   id: int,
 *   fullName: string,
 *   firstName: string,
 *   initials: string,
 *   email: string,
 *   role: string
 * }
 */
function getUser(): array
{
    $fullName  = trim((string)($_SESSION['user_name'] ?? 'User'));
    $nameParts = preg_split('/\s+/', $fullName) ?: [];
    $firstName = $nameParts[0] ?? 'User';

    $initials = count($nameParts) >= 2
        ? strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1))
        : strtoupper(substr($firstName, 0, 2));

    return [
        'id'        => (int)($_SESSION['user_id']    ?? 0),
        'fullName'  => $fullName,
        'firstName' => $firstName,
        'initials'  => $initials,
        'email'     => (string)($_SESSION['user_email']   ?? ''),
        'role'      => (string)($_SESSION['account_type'] ?? ''),
    ];
}
