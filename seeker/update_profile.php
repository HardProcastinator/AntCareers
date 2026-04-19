<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$db     = getDB();
$userId = (int) $_SESSION['user_id'];

// ── One-time schema compatibility fixes ──────────────────────────────────────
// These run silently on every request until the DB is on the current schema.
// Safe to remove once the fresh migration_seeker.sql has been run.

// 1. Fix experience_level ENUM → VARCHAR (form values don't match old ENUM)
try {
    $col = $db->query("SHOW COLUMNS FROM seeker_profiles LIKE 'experience_level'")->fetch(PDO::FETCH_ASSOC);
    if ($col && stripos($col['Type'], 'enum') === 0) {
        $db->exec("ALTER TABLE seeker_profiles MODIFY COLUMN experience_level VARCHAR(100) DEFAULT NULL");
    }
} catch (\Throwable $_) {}

// 2. Add link columns if missing (old schema didn't have them)
try {
    $existing = array_column(
        $db->query("SHOW COLUMNS FROM seeker_profiles")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    $toAdd = [];
    if (!in_array('linkedin_url',  $existing, true)) $toAdd[] = "ADD COLUMN linkedin_url  VARCHAR(500) DEFAULT NULL";
    if (!in_array('github_url',    $existing, true)) $toAdd[] = "ADD COLUMN github_url    VARCHAR(500) DEFAULT NULL";
    if (!in_array('portfolio_url', $existing, true)) $toAdd[] = "ADD COLUMN portfolio_url VARCHAR(500) DEFAULT NULL";
    if (!in_array('other_url',     $existing, true)) $toAdd[] = "ADD COLUMN other_url     VARCHAR(500) DEFAULT NULL";
    if (!in_array('banner_url',    $existing, true)) $toAdd[] = "ADD COLUMN banner_url    VARCHAR(500) DEFAULT NULL";
    if (!in_array('nr_availability',   $existing, true)) $toAdd[] = "ADD COLUMN nr_availability    VARCHAR(100) DEFAULT NULL";
    if (!in_array('nr_work_types',     $existing, true)) $toAdd[] = "ADD COLUMN nr_work_types      VARCHAR(255) DEFAULT NULL";
    if (!in_array('nr_locations',      $existing, true)) $toAdd[] = "ADD COLUMN nr_locations       TEXT DEFAULT NULL";
    if (!in_array('nr_right_to_work',  $existing, true)) $toAdd[] = "ADD COLUMN nr_right_to_work   VARCHAR(255) DEFAULT NULL";
    if (!in_array('nr_salary',         $existing, true)) $toAdd[] = "ADD COLUMN nr_salary          VARCHAR(100) DEFAULT NULL";
    if (!in_array('nr_salary_period',  $existing, true)) $toAdd[] = "ADD COLUMN nr_salary_period   VARCHAR(50)  DEFAULT NULL";
    if (!in_array('nr_salary_currency',$existing, true)) $toAdd[] = "ADD COLUMN nr_salary_currency VARCHAR(10)  DEFAULT NULL";
    if (!in_array('nr_classification', $existing, true)) $toAdd[] = "ADD COLUMN nr_classification  VARCHAR(255) DEFAULT NULL";
    if (!in_array('nr_approachability',$existing, true)) $toAdd[] = "ADD COLUMN nr_approachability VARCHAR(50)  DEFAULT NULL";
    if ($toAdd) {
        $db->exec("ALTER TABLE seeker_profiles " . implode(', ', $toAdd));
    }
} catch (\Throwable $_) {}

// 3. Ensure certifications + languages tables exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS seeker_certifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        cert_name VARCHAR(255) NOT NULL,
        issuing_org VARCHAR(255) DEFAULT NULL,
        issue_date DATE DEFAULT NULL,
        expiry_date DATE DEFAULT NULL,
        no_expiry TINYINT(1) NOT NULL DEFAULT 0,
        description TEXT DEFAULT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_cert_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $_) {}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS seeker_languages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        language_name VARCHAR(100) NOT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_lang_user (user_id, language_name),
        INDEX idx_lang_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $_) {}

// ── Helper ───────────────────────────────────────────────────────────────────
function postValue(string $key): ?string
{
    $value = trim((string)($_POST[$key] ?? ''));
    return $value !== '' ? $value : null;
}

// ── Collect POST values ───────────────────────────────────────────────────────
$phone               = postValue('phone');
$addressLine         = postValue('address_line');
$landmark            = postValue('landmark');
$countryName         = postValue('country_name');
$regionCode          = postValue('region_code');
$regionName          = postValue('region_name');
$provinceCode        = postValue('province_code');
$provinceName        = postValue('province_name');
$cityCode            = postValue('city_code');
$cityName            = postValue('city_name');
$barangayCode        = postValue('barangay_code');
$barangayName        = postValue('barangay_name');
$desiredPosition     = postValue('desired_position');
$professionalSummary = postValue('professional_summary');
$headline            = postValue('headline');
$bio                 = postValue('bio');
$experienceLevel     = postValue('experience_level');
$linkedinUrl         = postValue('linkedin_url');
$githubUrl           = postValue('github_url');
$portfolioUrl        = postValue('portfolio_url');
$otherUrl            = postValue('other_url');
$nrAvailability      = postValue('nr_availability');
$nrWorkTypes         = postValue('nr_work_types');
$nrLocations         = postValue('nr_locations');
$nrRightToWork       = postValue('nr_right_to_work');
$nrSalary            = postValue('nr_salary');
$nrSalaryPeriod      = postValue('nr_salary_period');
$nrSalaryCurrency    = postValue('nr_salary_currency') ?: 'PHP';
$nrClassification    = postValue('nr_classification');
$nrApproachability   = postValue('nr_approachability');

// ── TRANSACTION 1: Save core profile fields ───────────────────────────────────
// First tries the full UPDATE/INSERT (with link + nr columns).
// If MySQL rejects it because optional columns don't exist yet on the live DB,
// falls back to a core-only UPDATE/INSERT so the profile is ALWAYS saved.
// The fallback keeps bio/headline/etc. working even before migration is run.

// Params shared by both full and core-only queries
$coreParams = [
    ':uid'                  => $userId,
    ':phone'                => $phone,
    ':address_line'         => $addressLine,
    ':landmark'             => $landmark,
    ':country_name'         => $countryName,
    ':region_code'          => $regionCode,
    ':region_name'          => $regionName,
    ':province_code'        => $provinceCode,
    ':province_name'        => $provinceName,
    ':city_code'            => $cityCode,
    ':city_name'            => $cityName,
    ':barangay_code'        => $barangayCode,
    ':barangay_name'        => $barangayName,
    ':desired_position'     => $desiredPosition,
    ':professional_summary' => $professionalSummary,
    ':headline'             => $headline,
    ':bio'                  => $bio,
    ':experience_level'     => $experienceLevel,
];

// Extra params for the full query (optional columns)
$fullParams = $coreParams + [
    ':linkedin_url'      => $linkedinUrl,
    ':github_url'        => $githubUrl,
    ':portfolio_url'     => $portfolioUrl,
    ':other_url'         => $otherUrl,
    ':nr_availability'   => $nrAvailability,
    ':nr_work_types'     => $nrWorkTypes,
    ':nr_locations'      => $nrLocations,
    ':nr_right_to_work'  => $nrRightToWork,
    ':nr_salary'          => $nrSalary,
    ':nr_salary_period'   => $nrSalaryPeriod,
    ':nr_salary_currency' => $nrSalaryCurrency,
    ':nr_classification' => $nrClassification,
    ':nr_approachability'=> $nrApproachability,
];

try {
    $db->beginTransaction();

    $checkStmt = $db->prepare("SELECT id FROM seeker_profiles WHERE user_id = :uid LIMIT 1");
    $checkStmt->execute([':uid' => $userId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // ── UPDATE ──────────────────────────────────────────────────────────
        try {
            // Full UPDATE — all columns including links and next-role prefs
            $db->prepare("
                UPDATE seeker_profiles SET
                    phone = :phone, address_line = :address_line, landmark = :landmark,
                    country_name = :country_name, region_code = :region_code,
                    region_name = :region_name, province_code = :province_code,
                    province_name = :province_name, city_code = :city_code,
                    city_name = :city_name, barangay_code = :barangay_code,
                    barangay_name = :barangay_name, desired_position = :desired_position,
                    professional_summary = :professional_summary, headline = :headline,
                    bio = :bio, experience_level = :experience_level,
                    linkedin_url = :linkedin_url, github_url = :github_url,
                    portfolio_url = :portfolio_url, other_url = :other_url,
                    nr_availability = :nr_availability, nr_work_types = :nr_work_types,
                    nr_locations = :nr_locations, nr_right_to_work = :nr_right_to_work,
                    nr_salary = :nr_salary, nr_salary_period = :nr_salary_period,
                    nr_salary_currency = :nr_salary_currency,
                    nr_classification = :nr_classification,
                    nr_approachability = :nr_approachability,
                    updated_at = NOW()
                WHERE user_id = :uid LIMIT 1
            ")->execute($fullParams);
        } catch (\PDOException $colErr) {
            // Optional columns not yet in DB — fall back to core-only UPDATE
            error_log('[AntCareers] Full UPDATE failed, using core fallback: ' . $colErr->getMessage());
            $db->prepare("
                UPDATE seeker_profiles SET
                    phone = :phone, address_line = :address_line, landmark = :landmark,
                    country_name = :country_name, region_code = :region_code,
                    region_name = :region_name, province_code = :province_code,
                    province_name = :province_name, city_code = :city_code,
                    city_name = :city_name, barangay_code = :barangay_code,
                    barangay_name = :barangay_name, desired_position = :desired_position,
                    professional_summary = :professional_summary, headline = :headline,
                    bio = :bio, experience_level = :experience_level,
                    updated_at = NOW()
                WHERE user_id = :uid LIMIT 1
            ")->execute($coreParams);
        }
    } else {
        // ── INSERT ──────────────────────────────────────────────────────────
        try {
            // Full INSERT — all columns including links and next-role prefs
            $db->prepare("
                INSERT INTO seeker_profiles (
                    user_id, phone, address_line, landmark, country_name, region_code,
                    region_name, province_code, province_name, city_code, city_name,
                    barangay_code, barangay_name, desired_position, professional_summary,
                    headline, bio, experience_level, linkedin_url, github_url,
                    portfolio_url, other_url, nr_availability, nr_work_types, nr_locations,
                    nr_right_to_work, nr_salary, nr_salary_period, nr_salary_currency,
                    nr_classification, nr_approachability, created_at, updated_at
                ) VALUES (
                    :uid, :phone, :address_line, :landmark, :country_name, :region_code,
                    :region_name, :province_code, :province_name, :city_code, :city_name,
                    :barangay_code, :barangay_name, :desired_position, :professional_summary,
                    :headline, :bio, :experience_level, :linkedin_url, :github_url,
                    :portfolio_url, :other_url, :nr_availability, :nr_work_types, :nr_locations,
                    :nr_right_to_work, :nr_salary, :nr_salary_period, :nr_salary_currency,
                    :nr_classification, :nr_approachability, NOW(), NOW()
                )
            ")->execute($fullParams);
        } catch (\PDOException $colErr) {
            // Optional columns not yet in DB — fall back to core-only INSERT
            error_log('[AntCareers] Full INSERT failed, using core fallback: ' . $colErr->getMessage());
            $db->prepare("
                INSERT INTO seeker_profiles (
                    user_id, phone, address_line, landmark, country_name, region_code,
                    region_name, province_code, province_name, city_code, city_name,
                    barangay_code, barangay_name, desired_position, professional_summary,
                    headline, bio, experience_level, created_at, updated_at
                ) VALUES (
                    :uid, :phone, :address_line, :landmark, :country_name, :region_code,
                    :region_name, :province_code, :province_name, :city_code, :city_name,
                    :barangay_code, :barangay_name, :desired_position, :professional_summary,
                    :headline, :bio, :experience_level, NOW(), NOW()
                )
            ")->execute($coreParams);
        }
    }

    $db->commit(); // ← Profile (with or without links) is now safely saved

} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Profile save error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

// ── TRANSACTION 2: Education ──────────────────────────────────────────────────
try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM seeker_education WHERE user_id = :uid')->execute([':uid' => $userId]);

    $insertEdu = $db->prepare("
        INSERT INTO seeker_education
            (user_id, education_level, school_name, degree_course, start_year,
             end_year, graduation_date, remarks, no_schooling, sort_order, created_at, updated_at)
        VALUES
            (:uid, :level, :school, :degree, :start_year,
             :end_year, :grad_date, :remarks, :no_school, :sort, NOW(), NOW())
    ");

    foreach (['elementary', 'junior_high', 'senior_high', 'college'] as $level) {
        $noSchooling = isset($_POST['education'][$level]['no_schooling']) ? 1 : 0;
        $entries     = $_POST['education'][$level]['entries'] ?? [];

        if ($noSchooling === 1) {
            $insertEdu->execute([
                ':uid' => $userId, ':level' => $level, ':school' => null, ':degree' => null,
                ':start_year' => null, ':end_year' => null, ':grad_date' => null,
                ':remarks' => null, ':no_school' => 1, ':sort' => 0,
            ]);
            continue;
        }

        if (!is_array($entries)) continue;
        $sort = 0;
        foreach ($entries as $entry) {
            $school = trim((string)($entry['school_name']  ?? ''));
            $degree = trim((string)($entry['degree_course']?? ''));
            $sy     = trim((string)($entry['start_year']   ?? ''));
            $ey     = trim((string)($entry['end_year']     ?? ''));
            $gd     = trim((string)($entry['graduation_date'] ?? ''));
            $rm     = trim((string)($entry['remarks']      ?? ''));
            if (!$school && !$degree && !$sy && !$ey && !$gd && !$rm) continue;
            $insertEdu->execute([
                ':uid' => $userId, ':level' => $level,
                ':school' => $school ?: null, ':degree' => $degree ?: null,
                ':start_year' => $sy ?: null, ':end_year' => $ey ?: null,
                ':grad_date' => $gd ?: null, ':remarks' => $rm ?: null,
                ':no_school' => 0, ':sort' => $sort++,
            ]);
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Education save error: ' . $e->getMessage());
}

// ── TRANSACTION 3: Skills ─────────────────────────────────────────────────────
try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM seeker_skills WHERE user_id = :uid')->execute([':uid' => $userId]);

    $skillNames  = $_POST['skills']['name']  ?? [];
    $skillLevels = $_POST['skills']['level'] ?? [];
    $validLevels = ['Beginner', 'Intermediate', 'Advanced', 'Expert'];
    $insSkill    = $db->prepare(
        'INSERT INTO seeker_skills (user_id, skill_name, skill_level, sort_order)
         VALUES (:uid, :name, :level, :sort)'
    );
    foreach ($skillNames as $i => $sName) {
        $sName = trim((string)$sName);
        if ($sName === '') continue;
        $sLevel = in_array($skillLevels[$i] ?? '', $validLevels, true) ? $skillLevels[$i] : 'Intermediate';
        $insSkill->execute([':uid' => $userId, ':name' => $sName, ':level' => $sLevel, ':sort' => (int)$i]);
    }
    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Skills save error: ' . $e->getMessage());
}

// ── TRANSACTION 4: Experience ─────────────────────────────────────────────────
try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM seeker_experience WHERE user_id = :uid')->execute([':uid' => $userId]);

    $expEntries = $_POST['experience'] ?? [];
    $insExp = $db->prepare(
        'INSERT INTO seeker_experience
            (user_id, company_name, job_title, start_date, end_date, is_current, description, sort_order)
         VALUES (:uid, :company, :title, :start, :end, :current, :desc, :sort)'
    );
    if (is_array($expEntries)) {
        $expSort = 0;
        foreach ($expEntries as $ex) {
            $company = trim((string)($ex['company_name'] ?? ''));
            $title   = trim((string)($ex['job_title']    ?? ''));
            if ($company === '' && $title === '') continue;
            $startDate = trim((string)($ex['start_date'] ?? ''));
            $endDate   = trim((string)($ex['end_date']   ?? ''));
            $isCurrent = isset($ex['is_current']) ? 1 : 0;
            $desc      = trim((string)($ex['description'] ?? ''));
            $insExp->execute([
                ':uid'     => $userId,
                ':company' => $company !== '' ? $company : 'Unknown',   // NOT NULL safe
                ':title'   => $title   !== '' ? $title   : 'Unknown',   // NOT NULL safe
                ':start'   => $startDate !== '' ? $startDate : null,
                ':end'     => ($endDate !== '' && !$isCurrent) ? $endDate : null,
                ':current' => $isCurrent,
                ':desc'    => $desc !== '' ? $desc : null,
                ':sort'    => $expSort++,
            ]);
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Experience save error: ' . $e->getMessage());
}

// ── TRANSACTION 5: Certifications ────────────────────────────────────────────
try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM seeker_certifications WHERE user_id = :uid')->execute([':uid' => $userId]);

    $certEntries = $_POST['certifications'] ?? [];
    if (is_array($certEntries)) {
        $insCert = $db->prepare(
            'INSERT INTO seeker_certifications
                (user_id, cert_name, issuing_org, issue_date, expiry_date, no_expiry, description, sort_order)
             VALUES (:uid, :name, :org, :issue, :expiry, :no_expiry, :desc, :sort)'
        );
        $certSort = 0;
        foreach ($certEntries as $c) {
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '') continue;
            $issueDate  = trim((string)($c['issue_date']  ?? ''));
            $expiryDate = trim((string)($c['expiry_date'] ?? ''));
            $noExpiry   = (($c['no_expiry'] ?? '0') === '1') ? 1 : 0;
            $insCert->execute([
                ':uid'      => $userId,
                ':name'     => $name,
                ':org'      => trim((string)($c['org']  ?? '')) ?: null,
                ':issue'    => $issueDate  !== '' ? $issueDate  : null,
                ':expiry'   => (!$noExpiry && $expiryDate !== '') ? $expiryDate : null,
                ':no_expiry'=> $noExpiry,
                ':desc'     => trim((string)($c['desc'] ?? '')) ?: null,
                ':sort'     => $certSort++,
            ]);
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Certifications save error: ' . $e->getMessage());
}

// ── TRANSACTION 6: Languages ──────────────────────────────────────────────────
try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM seeker_languages WHERE user_id = :uid')->execute([':uid' => $userId]);

    $langEntries = $_POST['languages'] ?? [];
    if (is_array($langEntries)) {
        $insLang = $db->prepare(
            'INSERT INTO seeker_languages (user_id, language_name, sort_order)
             VALUES (:uid, :name, :sort)'
        );
        $langSort = 0;
        foreach ($langEntries as $l) {
            $l = trim((string)$l);
            if ($l === '') continue;
            $insLang->execute([':uid' => $userId, ':name' => $l, ':sort' => $langSort++]);
        }
    }
    $db->commit();
} catch (\Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[AntCareers] Languages save error: ' . $e->getMessage());
}

// ── Done ──────────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
echo json_encode(['ok' => true]);