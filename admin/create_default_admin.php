<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth_helpers.php';

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

try {
    $db = getDB();

    $adminEmail = trim((string)(getenv('ANTCAREERS_ADMIN_EMAIL') ?: ''));
    $adminPassword = (string)(getenv('ANTCAREERS_ADMIN_PASSWORD') ?: '');
    $adminFullName = trim((string)(getenv('ANTCAREERS_ADMIN_NAME') ?: 'AntCareers Admin'));
    $accountType = 'admin';

    if ($adminEmail === '') {
        $adminEmail = 'admin@antcareers.local';
    }
    if ($adminPassword === '') {
        $adminPassword = bin2hex(random_bytes(8)) . '!';
        echo "Generated admin password (save this now): " . $adminPassword . "\n";
    }

    // Check if admin already exists
    $check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute([':email' => $adminEmail]);
    $existing = $check->fetch();

    if ($existing) {
        echo "Default admin already exists.\n";
        exit;
    }

    $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);

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
        ':full_name'     => $adminFullName,
        ':email'         => $adminEmail,
        ':password_hash' => $passwordHash,
        ':account_type'  => $accountType,
        ':contact'       => null,
        ':company_name'  => null,
        ':is_verified'   => 1,
        ':is_active'     => 1,
    ]);

    echo "Default admin account created successfully.\n";
    echo "Email: " . $adminEmail . "\n";
    echo "Password: " . $adminPassword . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}