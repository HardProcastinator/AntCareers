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
    // Employers start as pending_approval (login blocked until admin approves)
    $isEmployer    = ($accountType === 'employer');
    $accountStatus = $isEmployer ? 'pending_approval' : 'active';
    $isActive      = $isEmployer ? 0 : 1;

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
            account_status,
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
            :account_status,
            NOW()
        )'
    );

    $stmt->execute([
        ':full_name'      => $fullName,
        ':email'          => $email,
        ':password_hash'  => $passwordHash,
        ':account_type'   => $accountType,
        ':contact'        => $contact !== '' ? $contact : null,
        ':company_name'   => $isEmployer ? $companyName : null,
        ':is_verified'    => 1,
        ':is_active'      => $isActive,
        ':account_status' => $accountStatus,
    ]);

    $userId = (int)$db->lastInsertId();

    // Notify all admin accounts about the new company awaiting approval
    if ($isEmployer) {
        try {
            $adminStmt = $db->query("SELECT id FROM users WHERE account_type = 'admin' AND is_active = 1");
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                $db->prepare(
                    'INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type, is_read, created_at)
                     VALUES (:uid, :actor, :type, :content, :ref_id, :ref_type, 0, NOW())'
                )->execute([
                    ':uid'      => $admin['id'],
                    ':actor'    => $userId,
                    ':type'     => 'company_approval_request',
                    ':content'  => htmlspecialchars($companyName, ENT_QUOTES) . ' (' . htmlspecialchars($email, ENT_QUOTES) . ') has registered and is awaiting company approval.',
                    ':ref_id'   => $userId,
                    ':ref_type' => 'user',
                ]);
            }
        } catch (Throwable) {
            // Non-fatal — notifications table may not be updated yet
        }

        // Log to activity_logs
        try {
            $db->prepare(
                'INSERT INTO activity_logs (user_id, actor_id, action_type, entity_type, entity_id, description, ip_address, user_agent, created_at)
                 VALUES (:uid, :actor, :action, :etype, :eid, :desc, :ip, :ua, NOW())'
            )->execute([
                ':uid'    => $userId,
                ':actor'  => $userId,
                ':action' => 'employer_registered',
                ':etype'  => 'user',
                ':eid'    => $userId,
                ':desc'   => "Employer '{$companyName}' ({$email}) registered and is pending admin approval.",
                ':ip'     => getClientIp(),
                ':ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (Throwable) {
            // Non-fatal
        }

        // Employer cannot log in yet — return success with pending message (no auto-login)
        jsonResponse([
            'success'  => true,
            'pending'  => true,
            'message'  => 'Registration successful! Your company account is pending admin approval. You will be notified once it has been reviewed.',
            'redirect' => url('auth/antcareers_login.php'),
        ]);
    }

    // Seeker: log in immediately after registration
    session_regenerate_id(true);

    $_SESSION['user_id']      = $userId;
    $_SESSION['user_email']   = $email;
    $_SESSION['user_name']    = $fullName;
    $_SESSION['account_type'] = $accountType;

    // Log seeker registration
    try {
        $db->prepare(
            'INSERT INTO activity_logs (user_id, actor_id, action_type, entity_type, entity_id, description, ip_address, user_agent, created_at)
             VALUES (:uid, :actor, :action, :etype, :eid, :desc, :ip, :ua, NOW())'
        )->execute([
            ':uid'    => $userId,
            ':actor'  => $userId,
            ':action' => 'user_registered',
            ':etype'  => 'user',
            ':eid'    => $userId,
            ':desc'   => "New seeker registered: {$email}",
            ':ip'     => getClientIp(),
            ':ua'     => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Throwable) {
        // Non-fatal
    }

    $redirect = url('seeker/antcareers_seekerDashboard.php');

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