<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

requirePost();
header('Content-Type: application/json; charset=utf-8');

// Parse JSON body
$raw = json_decode(file_get_contents('php://input'), true);
$body = is_array($raw) ? $raw : [];

$firstName   = trim((string)($body['first_name'] ?? ''));
$lastName    = trim((string)($body['last_name'] ?? ''));
$email       = trim((string)($body['email'] ?? ''));
$password    = (string)($body['password'] ?? '');
$accountType = strtolower(trim((string)($body['account_type'] ?? 'seeker')));
$contact     = trim((string)($body['contact'] ?? ''));
$companyName = trim((string)($body['company_name'] ?? ''));
$csrf        = (string)($body['csrf_token'] ?? '');

// CSRF
verifyCsrf($csrf);

// Validation
if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
    jsonResponse(['success' => false, 'message' => 'Please fill in all required fields.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => 'Please enter a valid email address.']);
}

if (strlen($password) < 8) {
    jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
}

if (!in_array($accountType, ['seeker', 'employer'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid account type selected.']);
}

if ($accountType === 'employer' && $companyName === '') {
    jsonResponse(['success' => false, 'message' => 'Company name is required for employer accounts.']);
}

// DB
$db = getDB();

// Check existing email
$check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$check->execute([':email' => $email]);

if ($check->fetch()) {
    jsonResponse(['success' => false, 'message' => 'Email is already registered.']);
}

// Prepare values
$fullName = trim($firstName . ' ' . $lastName);
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $db->prepare(
        'INSERT INTO users (
            full_name,
            email,
            password_hash,
            account_type,
            contact,
            company_name,
            is_verified,
            is_active,
            created_at
        ) VALUES (
            :full_name,
            :email,
            :password_hash,
            :account_type,
            :contact,
            :company_name,
            :is_verified,
            :is_active,
            NOW()
        )'
    );

    $stmt->execute([
        ':full_name'     => $fullName,
        ':email'         => $email,
        ':password_hash' => $passwordHash,
        ':account_type'  => $accountType,
        ':contact'       => $contact !== '' ? $contact : null,
        ':company_name'  => $accountType === 'employer' ? $companyName : null,
        ':is_verified'   => 1,
        ':is_active'     => 1,
    ]);

    $userId = (int)$db->lastInsertId();

    // Log the user in immediately after registration
    session_regenerate_id(true);

    $_SESSION['user_id']      = $userId;
    $_SESSION['user_email']   = $email;
    $_SESSION['user_name']    = $fullName;
    $_SESSION['account_type'] = $accountType;

    // Absolute redirect paths
    $redirect = match ($accountType) {
        'seeker'   => url('seeker/antcareers_seekerDashboard.php'),
        'employer' => url('employer/employer_dashboard.php'),
        default    => url('index.php'),
    };

    jsonResponse([
        'success'  => true,
        'message'  => 'Registration successful.',
        'redirect' => $redirect,
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Registration failed. Please try again.',
    ]);
}