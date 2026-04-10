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
 * Recruiters are allowed on employer pages (pass 'employer' or 'recruiter').
 *
 * @param string $role  'seeker' | 'employer' | 'recruiter' | 'admin'
 */
function requireLogin(string $role = 'seeker'): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . url('auth/antcareers_login.php'));
        exit;
    }

    // Force password change redirect (for hired recruiters on first login)
    if (!empty($_SESSION['must_change_password'])) {
        $currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($currentScript !== 'force_change_password.php') {
            header('Location: ' . url('auth/force_change_password.php'));
            exit;
        }
    }

    $sessionRole = strtolower((string)($_SESSION['account_type'] ?? ''));
    $requiredRole = strtolower($role);

    // Recruiters can access employer pages
    if ($requiredRole === 'employer' && $sessionRole === 'recruiter') {
        return;
    }

    if ($sessionRole !== $requiredRole) {
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
        'id'          => (int)($_SESSION['user_id']    ?? 0),
        'fullName'    => $fullName,
        'firstName'   => $firstName,
        'initials'    => $initials,
        'email'       => (string)($_SESSION['user_email']   ?? ''),
        'role'        => (string)($_SESSION['account_type'] ?? ''),
        'companyName' => (string)($_SESSION['company_name'] ?? ''),
    ];
}
