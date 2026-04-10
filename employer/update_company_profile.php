<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}
if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'employer') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not employer']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$action = trim((string)($_POST['action'] ?? ''));

/* ================================================================
   ACTION: get_profile  — return current company profile data
   ================================================================ */
if ($action === 'get_profile') {
    $stmt = $db->prepare("SELECT * FROM company_profiles WHERE user_id = :uid LIMIT 1");
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'profile' => $row ?: null]);
    exit;
}

/* ================================================================
   ACTION: upload_logo  — save company logo image
   ================================================================ */
if ($action === 'upload_logo') {
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        exit;
    }
    $file = $_FILES['logo'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid image type']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'File too large (max 5 MB)']);
        exit;
    }
    $ext  = match ($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/gif'  => 'gif', 'image/webp' => 'webp', default => 'jpg'
    };
    $dir  = dirname(__DIR__) . '/uploads/logos';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'logo_' . $userId . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Upload failed']);
        exit;
    }
    $relPath = 'uploads/logos/' . $name;

    // Delete old logo file if exists
    $old = $db->prepare("SELECT logo_path FROM company_profiles WHERE user_id = :uid LIMIT 1");
    $old->execute([':uid' => $userId]);
    $oldRow = $old->fetch(PDO::FETCH_ASSOC);
    if ($oldRow && !empty($oldRow['logo_path'])) {
        $oldFile = dirname(__DIR__) . '/' . $oldRow['logo_path'];
        if (is_file($oldFile)) @unlink($oldFile);
    }

    // Upsert
    ensureProfileRow($db, $userId);
    $upd = $db->prepare("UPDATE company_profiles SET logo_path = :p, updated_at = NOW() WHERE user_id = :uid");
    $upd->execute([':p' => $relPath, ':uid' => $userId]);

    echo json_encode(['ok' => true, 'path' => $relPath]);
    exit;
}

/* ================================================================
   ACTION: upload_cover  — save company cover photo
   ================================================================ */
if ($action === 'upload_cover') {
    if (empty($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
        exit;
    }
    $file = $_FILES['cover'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid image type']);
        exit;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'File too large (max 5 MB)']);
        exit;
    }
    $ext  = match ($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/gif'  => 'gif', 'image/webp' => 'webp', default => 'jpg'
    };
    $dir  = dirname(__DIR__) . '/uploads/covers';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'cover_' . $userId . '_' . time() . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Upload failed']);
        exit;
    }
    $relPath = 'uploads/covers/' . $name;

    // Delete old cover file if exists
    $old = $db->prepare("SELECT cover_path FROM company_profiles WHERE user_id = :uid LIMIT 1");
    $old->execute([':uid' => $userId]);
    $oldRow = $old->fetch(PDO::FETCH_ASSOC);
    if ($oldRow && !empty($oldRow['cover_path'])) {
        $oldFile = dirname(__DIR__) . '/' . $oldRow['cover_path'];
        if (is_file($oldFile)) @unlink($oldFile);
    }

    ensureProfileRow($db, $userId);
    $upd = $db->prepare("UPDATE company_profiles SET cover_path = :p, updated_at = NOW() WHERE user_id = :uid");
    $upd->execute([':p' => $relPath, ':uid' => $userId]);

    echo json_encode(['ok' => true, 'path' => $relPath]);
    exit;
}

/* ================================================================
   ACTION: save_profile  — save all company profile form fields
   ================================================================ */
if ($action === 'save_profile') {
    $companyName    = trim((string)($_POST['company_name'] ?? ''));
    $industry       = trim((string)($_POST['industry'] ?? ''));
    $companySize    = trim((string)($_POST['company_size'] ?? ''));
    $foundedYear    = trim((string)($_POST['founded_year'] ?? ''));
    $companyType    = trim((string)($_POST['company_type'] ?? ''));
    $website        = trim((string)($_POST['website'] ?? ''));
    $tagline        = trim((string)($_POST['tagline'] ?? ''));
    $about          = trim((string)($_POST['about'] ?? ''));
    $country        = trim((string)($_POST['country'] ?? ''));
    $city           = trim((string)($_POST['city'] ?? ''));
    $province       = trim((string)($_POST['province'] ?? ''));
    $zipCode        = trim((string)($_POST['zip_code'] ?? ''));
    $addressLine    = trim((string)($_POST['address_line'] ?? ''));
    $contactEmail   = trim((string)($_POST['contact_email'] ?? ''));
    $contactPhone   = trim((string)($_POST['contact_phone'] ?? ''));
    $socialWebsite  = trim((string)($_POST['social_website'] ?? ''));
    $socialLinkedin = trim((string)($_POST['social_linkedin'] ?? ''));
    $socialFacebook = trim((string)($_POST['social_facebook'] ?? ''));
    $socialTwitter  = trim((string)($_POST['social_twitter'] ?? ''));
    $socialInstagram= trim((string)($_POST['social_instagram'] ?? ''));
    $socialYoutube  = trim((string)($_POST['social_youtube'] ?? ''));
    $perks          = trim((string)($_POST['perks'] ?? '[]'));

    if ($companyName === '') {
        echo json_encode(['ok' => false, 'error' => 'Company name is required']);
        exit;
    }
    if ($tagline !== '' && mb_strlen($tagline) > 120) {
        $tagline = mb_substr($tagline, 0, 120);
    }
    if ($about !== '' && mb_strlen($about) > 1000) {
        $about = mb_substr($about, 0, 1000);
    }

    $fy = $foundedYear !== '' ? (int)$foundedYear : null;
    if ($fy !== null && ($fy < 1800 || $fy > (int)date('Y'))) $fy = null;

    try {
        $db->beginTransaction();

        // Ensure a row exists
        ensureProfileRow($db, $userId);

        // Update company_profiles
        $sql = "UPDATE company_profiles SET
            company_name     = :company_name,
            industry         = :industry,
            company_size     = :company_size,
            founded_year     = :founded_year,
            company_type     = :company_type,
            website          = :website,
            tagline          = :tagline,
            about            = :about,
            country          = :country,
            city             = :city,
            province         = :province,
            zip_code         = :zip_code,
            address_line     = :address_line,
            contact_email    = :contact_email,
            contact_phone    = :contact_phone,
            social_website   = :social_website,
            social_linkedin  = :social_linkedin,
            social_facebook  = :social_facebook,
            social_twitter   = :social_twitter,
            social_instagram = :social_instagram,
            social_youtube   = :social_youtube,
            perks            = :perks,
            updated_at       = NOW()
            WHERE user_id = :uid";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':company_name'     => $companyName,
            ':industry'         => $industry !== '' ? $industry : null,
            ':company_size'     => $companySize !== '' ? $companySize : null,
            ':founded_year'     => $fy,
            ':company_type'     => $companyType !== '' ? $companyType : null,
            ':website'          => $website !== '' ? $website : null,
            ':tagline'          => $tagline !== '' ? $tagline : null,
            ':about'            => $about !== '' ? $about : null,
            ':country'          => $country !== '' ? $country : null,
            ':city'             => $city !== '' ? $city : null,
            ':province'         => $province !== '' ? $province : null,
            ':zip_code'         => $zipCode !== '' ? $zipCode : null,
            ':address_line'     => $addressLine !== '' ? $addressLine : null,
            ':contact_email'    => $contactEmail !== '' ? $contactEmail : null,
            ':contact_phone'    => $contactPhone !== '' ? $contactPhone : null,
            ':social_website'   => $socialWebsite !== '' ? $socialWebsite : null,
            ':social_linkedin'  => $socialLinkedin !== '' ? $socialLinkedin : null,
            ':social_facebook'  => $socialFacebook !== '' ? $socialFacebook : null,
            ':social_twitter'   => $socialTwitter !== '' ? $socialTwitter : null,
            ':social_instagram' => $socialInstagram !== '' ? $socialInstagram : null,
            ':social_youtube'   => $socialYoutube !== '' ? $socialYoutube : null,
            ':perks'            => $perks,
            ':uid'              => $userId,
        ]);

        // Also update users.company_name so session stays in sync
        $uStmt = $db->prepare("UPDATE users SET company_name = :cn, updated_at = NOW() WHERE id = :uid");
        $uStmt->execute([':cn' => $companyName, ':uid' => $userId]);

        $_SESSION['company_name'] = $companyName;
        $db->commit();

        echo json_encode(['ok' => true]);
        exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[AntCareers] Save company profile error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Database error, please try again']);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
exit;

/* ================================================================
   Helper: ensure a company_profiles row exists for this user
   ================================================================ */
function ensureProfileRow(PDO $db, int $userId): void
{
    $check = $db->prepare("SELECT id FROM company_profiles WHERE user_id = :uid LIMIT 1");
    $check->execute([':uid' => $userId]);
    if (!$check->fetch()) {
        $ins = $db->prepare("INSERT INTO company_profiles (user_id, company_name, created_at, updated_at)
            VALUES (:uid, :cn, NOW(), NOW())");
        $cn = $_SESSION['company_name'] ?? '';
        $ins->execute([':uid' => $userId, ':cn' => $cn]);
    }
}
