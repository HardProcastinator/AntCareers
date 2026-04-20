<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$viewerId = (int)$_SESSION['user_id'];
$personId = (int)($_GET['id'] ?? 0);

if ($personId <= 0 || $personId === $viewerId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

$db = getDB();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS user_follows (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_user_id INT UNSIGNED NOT NULL,
        followed_user_id INT UNSIGNED NOT NULL,
        followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_follow (follower_user_id, followed_user_id),
        KEY idx_followed_user (followed_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $_) {
}

try {
    $stmt = $db->prepare("SELECT
            u.id,
            u.full_name,
            u.avatar_url,
            u.account_type,
            sp.headline,
            sp.industry,
            sp.experience_level,
            sp.city_name,
            sp.province_name,
            sp.country_name,
            sp.nr_availability,
            sp.nr_work_types,
            sp.nr_right_to_work,
            sp.nr_salary,
            sp.nr_salary_period,
            sp.nr_classification,
            sp.professional_summary,
            sp.bio,
            sp.phone,
            rp.position,
            cp.company_name,
            cp.industry AS company_industry,
            GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.sort_order SEPARATOR ',') AS skills,
            sr.file_path AS resume_path,
            sr.original_filename AS resume_name
        FROM users u
        LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
        LEFT JOIN recruiter_profiles rp ON rp.user_id = u.id
        LEFT JOIN company_profiles cp ON cp.user_id = u.id
        LEFT JOIN seeker_skills sk ON sk.user_id = u.id
        LEFT JOIN seeker_resumes sr ON sr.user_id = u.id AND sr.is_active = 1
        WHERE u.id = :id AND u.is_active = 1
        GROUP BY u.id, u.full_name, u.avatar_url, u.account_type, sp.headline, sp.industry, sp.experience_level, sp.city_name, sp.province_name, sp.country_name, sp.nr_availability, sp.nr_work_types, sp.nr_right_to_work, sp.nr_salary, sp.nr_salary_period, sp.nr_classification, sp.professional_summary, sp.bio, sp.phone, rp.position, cp.company_name, cp.industry, sr.file_path, sr.original_filename
        LIMIT 1");
    $stmt->execute([':id' => $personId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $parts = preg_split('/\s+/', trim((string)($row['full_name'] ?? 'User'))) ?: ['U'];
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

    $city = trim((string)($row['city_name'] ?? ''));
    $province = trim((string)($row['province_name'] ?? ''));
    $country = trim((string)($row['country_name'] ?? ''));
    $location = $city !== '' ? $city : ($province !== '' ? $province : ($country !== '' ? $country : 'Not specified'));

    $title = '';
    if (($row['account_type'] ?? '') === 'employer') {
        $title = trim((string)($row['company_name'] ?? 'Employer'));
        if (!empty($row['company_industry'])) {
            $title .= ' • ' . $row['company_industry'];
        }
    } elseif (($row['account_type'] ?? '') === 'recruiter') {
        $title = trim((string)($row['position'] ?? 'Recruiter'));
    } else {
        $title = trim((string)($row['headline'] ?? 'Job Seeker'));
        if (!empty($row['industry'])) {
            $title .= ' • ' . $row['industry'];
        }
    }

    $skills = array_values(array_filter(array_map('trim', explode(',', (string)($row['skills'] ?? '')))));
    $status = '';
    if (in_array((string)($row['nr_availability'] ?? ''), ['Now', 'Open'], true)) {
        $status = 'seeking';
    } elseif (($row['account_type'] ?? '') === 'employer') {
        $status = 'hiring';
    }

    $colors = [
        'linear-gradient(135deg,#D13D2C,#7A1515)',
        'linear-gradient(135deg,#4A90D9,#2A6090)',
        'linear-gradient(135deg,#4CAF70,#2A7040)',
        'linear-gradient(135deg,#D4943A,#8A5A10)',
        'linear-gradient(135deg,#9C27B0,#5A0080)',
    ];

    echo json_encode([
        'success' => true,
        'person' => [
            'id' => (int)$row['id'],
            'name' => (string)($row['full_name'] ?? 'User'),
            'title' => $title,
            'location' => $location,
            'skills' => $skills,
            'exp' => (string)($row['experience_level'] ?? ''),
            'status' => $status,
            'avatar' => $initials,
            'avatarUrl' => !empty($row['avatar_url']) ? '../' . $row['avatar_url'] : '',
            'color' => $colors[$personId % count($colors)],
            'accountType' => (string)($row['account_type'] ?? 'seeker'),
            'availability' => (string)($row['nr_availability'] ?? ''),
            'workTypes' => (string)($row['nr_work_types'] ?? ''),
            'rightToWork' => (string)($row['nr_right_to_work'] ?? ''),
            'salary' => (string)($row['nr_salary'] ?? ''),
            'salaryPeriod' => (string)($row['nr_salary_period'] ?? ''),
            'classification' => (string)($row['nr_classification'] ?? ''),
            'summary' => (string)($row['professional_summary'] ?? ''),
            'bio' => (string)($row['bio'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'country' => (string)($row['country_name'] ?? ''),
            'resumePath' => !empty($row['resume_path']) ? '../' . $row['resume_path'] : '',
            'resumeName' => (string)($row['resume_name'] ?? ''),
        ],
    ]);
} catch (Throwable $e) {
    error_log('[AntCareers] person_preview: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
