<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/antcareers_login.php');
    exit;
}
if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'employer') {
    header('Location: ../index.php');
    exit;
}

$seekerId = (int)($_GET['id'] ?? 0);
if ($seekerId <= 0) {
    header('Location: ../employer/employer_applicants.php');
    exit;
}

$fullName    = trim((string)($_SESSION['user_name'] ?? 'Employer'));
$nameParts   = preg_split('/\s+/', $fullName) ?: [];
$firstName   = $nameParts[0] ?? 'Employer';
$initials    = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0],0,1).substr($nameParts[1],0,1))
    : strtoupper(substr($firstName,0,2));
$companyName = trim((string)($_SESSION['company_name'] ?? 'Your Company'));
$navActive   = 'applicants';
$navbarShowMessage = false;
$navbarShowNotif   = false;
$navbarShowPostJob = false;
$navbarShowHamburger = false;
$navbarShowMobileMenu = false;
$uid = (int)$_SESSION['user_id'];

$db = getDB();

// Verify this seeker has applied to one of the employer's jobs
$authCheck = $db->prepare("
    SELECT COUNT(*) FROM applications a
    JOIN jobs j ON j.id = a.job_id
    WHERE a.seeker_id = ? AND j.employer_id = ?
");
$authCheck->execute([$seekerId, $uid]);
if ((int)$authCheck->fetchColumn() === 0) {
    header('Location: ../employer/employer_applicants.php');
    exit;
}

// Fetch seeker basic info
// First try with all seeker_profiles columns; fall back to users-only if profile table missing
$seeker = null;
try {
    $stmt = $db->prepare("
        SELECT u.id, u.full_name, u.email, u.avatar_url, u.created_at,
               sp.phone, sp.headline, sp.address_line, sp.landmark,
               sp.country_name, sp.region_name, sp.province_name, sp.city_name, sp.barangay_name,
               sp.desired_position, sp.professional_summary, sp.experience_level, sp.bio
        FROM users u
        LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
        WHERE u.id = ? AND u.account_type = 'seeker'
        LIMIT 1
    ");
    $stmt->execute([$seekerId]);
    $seeker = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    // seeker_profiles may be missing some columns — fallback to users only
    try {
        $stmt = $db->prepare("SELECT id, full_name, email, avatar_url, created_at FROM users WHERE id = ? AND account_type = 'seeker' LIMIT 1");
        $stmt->execute([$seekerId]);
        $seeker = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e2) { $seeker = null; }
}

if (!$seeker) {
    header('Location: ../employer/employer_applicants.php');
    exit;
}

// Check for extended profile fields
$hasExtendedFields = false;
try {
    $db->query("SELECT linkedin_url FROM seeker_profiles LIMIT 0");
    $hasExtendedFields = true;
    $extStmt = $db->prepare("SELECT linkedin_url, github_url, portfolio_url FROM seeker_profiles WHERE user_id = ?");
    $extStmt->execute([$seekerId]);
    $extRow = $extStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $extRow = [];
}

// Fetch education
$education = [];
try {
    $eduStmt = $db->prepare("
        SELECT education_level, school_name, degree_course, start_year, end_year, graduation_date, remarks, honors, no_schooling
        FROM seeker_education WHERE user_id = ? ORDER BY FIELD(education_level, 'college','senior_high','junior_high','elementary'), id DESC
    ");
    $eduStmt->execute([$seekerId]);
    $education = $eduStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist yet */ }

// Fetch skills
$skills = [];
try {
    // Try with sort_order first; fall back if column missing
    try {
        $skillStmt = $db->prepare("SELECT skill_name, skill_level FROM seeker_skills WHERE user_id = ? ORDER BY sort_order, id");
        $skillStmt->execute([$seekerId]);
    } catch (PDOException $e) {
        $skillStmt = $db->prepare("SELECT skill_name, skill_level FROM seeker_skills WHERE user_id = ? ORDER BY id");
        $skillStmt->execute([$seekerId]);
    }
    $skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist yet */ }

// Fetch experience
$experience = [];
try {
    $expStmt = $db->prepare("
        SELECT company_name, job_title, start_date, end_date, is_current, description
        FROM seeker_experience WHERE user_id = ? ORDER BY is_current DESC, end_date DESC, start_date DESC
    ");
    $expStmt->execute([$seekerId]);
    $experience = $expStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist yet */ }

// Fetch resume
$resume = null;
try {
    // Try with is_active first; fall back if column missing
    try {
        $resStmt = $db->prepare("SELECT original_filename, file_path FROM seeker_resumes WHERE user_id = ? AND is_active = 1 ORDER BY uploaded_at DESC LIMIT 1");
        $resStmt->execute([$seekerId]);
    } catch (PDOException $e) {
        $resStmt = $db->prepare("SELECT original_filename, file_path FROM seeker_resumes WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $resStmt->execute([$seekerId]);
    }
    $resume = $resStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) { /* table may not exist yet */ }

// Fetch certifications if table exists
$certifications = [];
try {
    $certStmt = $db->prepare("SELECT cert_name, issuing_org, issue_date, expiry_date, no_expiry, description FROM seeker_certifications WHERE user_id = ? ORDER BY sort_order, id DESC");
    $certStmt->execute([$seekerId]);
    $certifications = $certStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

// Fetch applications to this employer's jobs
$applications = [];
try {
    $appStmt = $db->prepare("
        SELECT a.id, a.status, a.applied_at, a.cover_letter, j.title AS job_title
        FROM applications a JOIN jobs j ON j.id = a.job_id
        WHERE a.seeker_id = ? AND j.employer_id = ?
        ORDER BY a.applied_at DESC
    ");
    $appStmt->execute([$seekerId, $uid]);
    $applications = $appStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* silently skip */ }

// Helpers
function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$sName = trim((string)$seeker['full_name']);
$sParts = preg_split('/\s+/', $sName) ?: ['?'];
$sInitials = count($sParts) >= 2
    ? strtoupper(substr($sParts[0],0,1).substr($sParts[1],0,1))
    : strtoupper(substr($sParts[0],0,2));

$sAvatarUrl = $seeker['avatar_url'] ?? '';
$sEmail = $seeker['email'] ?? '';
$sPhone = $seeker['phone'] ?? '';
$sHeadline = $seeker['headline'] ?? ($seeker['desired_position'] ?? '');
$sSummary = $seeker['professional_summary'] ?? ($seeker['bio'] ?? '');
$sExpLevel = $seeker['experience_level'] ?? '';

// Build location string
$locParts = array_filter([
    $seeker['city_name'] ?? '',
    $seeker['province_name'] ?? '',
    $seeker['country_name'] ?? ''
]);
$sLocation = implode(', ', $locParts);
$sFullAddress = implode(', ', array_filter([
    $seeker['address_line'] ?? '',
    $seeker['barangay_name'] ?? '',
    $seeker['city_name'] ?? '',
    $seeker['province_name'] ?? '',
    $seeker['country_name'] ?? ''
]));

$linkedinUrl  = $extRow['linkedin_url'] ?? '';
$githubUrl    = $extRow['github_url'] ?? '';
$portfolioUrl = $extRow['portfolio_url'] ?? '';

$smeta = [
    'Pending'     => ['c'=>'amber','i'=>'fa-clock'],
    'Reviewed'    => ['c'=>'blue','i'=>'fa-eye'],
    'Shortlisted' => ['c'=>'green','i'=>'fa-star'],
    'Rejected'    => ['c'=>'red','i'=>'fa-times-circle'],
    'Hired'       => ['c'=>'purple','i'=>'fa-check-circle'],
];

$levelLabels = [
    'elementary'  => 'Elementary',
    'junior_high' => 'Junior High School',
    'senior_high' => 'Senior High School',
    'college'     => 'College / University',
];

$skillLevelColors = [
    'Beginner'     => 'amber',
    'Intermediate' => 'blue',
    'Advanced'     => 'green',
    'Expert'       => 'purple',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — <?php echo e($sName); ?>'s Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--green:#4CAF70;--blue:#4A90D9;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased;}
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}

    /* Navbar */
    .navbar{position:sticky;top:0;z-index:400;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid rgba(209,61,44,0.35);box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5);}
    .nav-inner{max-width:1380px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:64px;gap:0;min-width:0;}
    .logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:28px;flex-shrink:0;}
    .logo-icon{width:34px;height:34px;background:var(--red-vivid);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 0 18px rgba(209,61,44,0.35);}
    .logo-icon::before{content:'🐜';font-size:18px;filter:brightness(0) invert(1);}
    .logo-text{font-family:var(--font-display);font-weight:700;font-size:19px;color:#F5F0EE;white-space:nowrap;}
    .logo-text span{color:var(--red-bright);}
    .nav-links{display:flex;align-items:center;gap:2px;flex:1;min-width:0;}
    .nav-link{font-size:13px;font-weight:600;color:#A09090;text-decoration:none;padding:7px 11px;border-radius:6px;transition:all 0.2s;cursor:pointer;background:none;border:none;font-family:var(--font-body);display:flex;align-items:center;gap:5px;white-space:nowrap;}
    .nav-link:hover,.nav-link.active{color:#F5F0EE;background:var(--soil-hover);}
    .nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0;}
    .theme-btn{width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:13px;flex-shrink:0;}
    .theme-btn:hover{color:var(--red-bright);border-color:var(--red-vivid);}
    .profile-wrap{position:relative;}
    .profile-btn{display:flex;align-items:center;gap:9px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:6px 12px 6px 8px;cursor:pointer;transition:0.2s;flex-shrink:0;}
    .profile-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#8a5010);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;}
    .profile-name{font-size:13px;font-weight:600;color:#F5F0EE;}
    .profile-role{font-size:10px;color:var(--amber);margin-top:1px;letter-spacing:0.02em;font-weight:600;}
    .profile-chevron{font-size:9px;color:var(--text-muted);margin-left:2px;}
    .profile-dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:6px;min-width:200px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:all 0.18s ease;z-index:300;box-shadow:0 20px 40px rgba(0,0,0,0.5);}
    .profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0);}
    .profile-dropdown-head{padding:12px 14px 10px;border-bottom:1px solid var(--soil-line);margin-bottom:4px;}
    .pdh-name{font-size:14px;font-weight:700;color:#F5F0EE;}
    .pdh-sub{font-size:11px;color:var(--amber);margin-top:2px;font-weight:600;}
    .pd-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:6px;font-size:13px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:0.15s;font-family:var(--font-body);text-decoration:none;}
    .pd-item i{color:var(--text-muted);width:16px;text-align:center;font-size:12px;}
    .pd-item:hover{background:var(--soil-hover);color:#F5F0EE;}
    .pd-item:hover i{color:var(--red-bright);}
    .pd-divider{height:1px;background:var(--soil-line);margin:4px 6px;}
    .pd-item.danger{color:#E05555;}
    .pd-item.danger i{color:#E05555;}
    .pd-item.danger:hover{background:rgba(224,85,85,0.1);color:#FF7070;}

    /* Page */
    .page-shell{max-width:1100px;margin:0 auto;padding:28px 24px 60px;position:relative;z-index:1;}
    .back-btn{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;margin-bottom:20px;transition:0.15s;padding:6px 12px;border-radius:6px;border:1px solid var(--soil-line);background:transparent;}
    .back-btn:hover{color:var(--red-pale);border-color:var(--red-vivid);}

    /* Profile Header */
    .profile-header{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:28px;margin-bottom:20px;display:flex;gap:20px;align-items:flex-start;}
    .ph-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .ph-avatar img{width:100%;height:100%;object-fit:cover;}
    .ph-info{flex:1;min-width:0;}
    .ph-name{font-family:var(--font-display);font-size:24px;font-weight:700;color:#F5F0EE;margin-bottom:4px;}
    .ph-headline{font-size:14px;color:var(--red-pale);font-weight:600;margin-bottom:8px;}
    .ph-meta{display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--text-muted);}
    .ph-meta span{display:flex;align-items:center;gap:5px;}
    .ph-meta i{color:var(--red-bright);font-size:11px;}
    .ph-meta a{color:var(--text-muted);text-decoration:none;transition:0.15s;}
    .ph-meta a:hover{color:var(--red-pale);}
    .ph-actions{display:flex;gap:8px;flex-shrink:0;align-items:flex-start;flex-wrap:wrap;}
    .ph-btn{padding:8px 16px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.18s;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
    .ph-btn:hover{background:var(--soil-hover);color:#F5F0EE;}
    .ph-btn.primary{background:var(--red-vivid);border-color:var(--red-vivid);color:#fff;}
    .ph-btn.primary:hover{background:var(--red-bright);}

    /* Sections */
    .section{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:22px;margin-bottom:16px;}
    .sec-title{font-family:var(--font-display);font-size:16px;font-weight:700;color:#F5F0EE;margin-bottom:14px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--soil-line);}
    .sec-title i{color:var(--red-bright);font-size:14px;}

    /* About */
    .about-text{font-size:14px;color:var(--text-mid);line-height:1.7;white-space:pre-wrap;}
    .empty-text{font-size:13px;color:var(--text-muted);font-style:italic;}

    /* Info grid */
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .info-item{display:flex;flex-direction:column;gap:2px;}
    .info-label{font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;}
    .info-value{font-size:13px;color:var(--text-mid);font-weight:500;}
    .info-value a{color:var(--red-pale);text-decoration:none;}
    .info-value a:hover{text-decoration:underline;}

    /* Skills */
    .skills-list{display:flex;flex-wrap:wrap;gap:6px;}
    .skill-tag{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--soil-line);background:var(--soil-hover);color:var(--text-mid);}
    .skill-tag.amber{color:var(--amber);background:rgba(212,148,58,.08);border-color:rgba(212,148,58,.2);}
    .skill-tag.blue{color:#7ab8f0;background:rgba(74,144,217,.08);border-color:rgba(74,144,217,.18);}
    .skill-tag.green{color:#6ccf8a;background:rgba(76,175,112,.08);border-color:rgba(76,175,112,.2);}
    .skill-tag.purple{color:#cf8ae0;background:rgba(156,39,176,.08);border-color:rgba(156,39,176,.18);}

    /* Experience / Education items */
    .exp-item{padding:14px 0;border-bottom:1px solid var(--soil-line);}
    .exp-item:last-child{border-bottom:none;}
    .exp-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:4px;}
    .exp-title{font-size:14px;font-weight:700;color:#F5F0EE;}
    .exp-company{font-size:13px;color:var(--red-pale);font-weight:600;}
    .exp-dates{font-size:11px;color:var(--text-muted);font-weight:600;white-space:nowrap;}
    .exp-desc{font-size:13px;color:var(--text-muted);line-height:1.5;margin-top:6px;white-space:pre-wrap;}
    .exp-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;text-transform:uppercase;letter-spacing:0.06em;}
    .exp-badge.green{color:#6ccf8a;background:rgba(76,175,112,.1);border:1px solid rgba(76,175,112,.2);}

    /* Resume */
    .resume-card{display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;}
    .resume-icon{width:42px;height:42px;border-radius:8px;background:rgba(209,61,44,.1);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--red-pale);flex-shrink:0;}
    .resume-name{font-size:13px;font-weight:600;color:#F5F0EE;}
    .resume-dl{margin-left:auto;padding:7px 14px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
    .resume-dl:hover{background:var(--red-bright);}

    /* Application status badge */
    .sbadge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;letter-spacing:.04em;}
    .sbadge.amber{color:var(--amber);background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.25);}
    .sbadge.blue{color:#7ab8f0;background:rgba(74,144,217,.1);border:1px solid rgba(74,144,217,.2);}
    .sbadge.green{color:#6ccf8a;background:rgba(76,175,112,.1);border:1px solid rgba(76,175,112,.2);}
    .sbadge.red{color:#ff8080;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);}
    .sbadge.purple{color:#cf8ae0;background:rgba(156,39,176,.1);border:1px solid rgba(156,39,176,.2);}

    /* Apps list */
    .app-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--soil-line);gap:10px;}
    .app-row:last-child{border-bottom:none;}
    .app-row-title{font-size:13px;font-weight:600;color:#F5F0EE;}
    .app-row-date{font-size:11px;color:var(--text-muted);}

    /* Two-col layout */
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px;}

    /* Light theme */
    body.light{--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber:#B8620A;}
    body.light .navbar{background:rgba(255,253,252,0.98);border-bottom-color:#D4B0AB;box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08);}
    body.light .logo-text{color:#1A0A09;} body.light .logo-text span{color:var(--red-vivid);}
    body.light .nav-link{color:#5A4040;} body.light .nav-link:hover,body.light .nav-link.active{color:#1A0A09;background:#FEF0EE;}
    body.light .theme-btn,body.light .profile-btn{background:#F5EEEC;border-color:#E0CECA;}
    body.light .profile-name{color:#1A0A09;} body.light .profile-dropdown{background:#FFFFFF;border-color:#E0CECA;}
    body.light .pd-item{color:#4A2828;} body.light .pd-item:hover{background:#FEF0EE;color:#1A0A09;}
    body.light .pdh-name{color:#1A0A09;}
    body.light .section{background:#FFFFFF;border-color:#E0CECA;}
    body.light .profile-header{background:#FFFFFF;border-color:#E0CECA;}
    body.light .ph-name{color:#1A0A09;} body.light .sec-title{color:#1A0A09;}
    body.light .exp-title,.app-row-title{color:#1A0A09;}
    body.light .back-btn{border-color:#E0CECA;color:#5A4040;} body.light .back-btn:hover{border-color:var(--red-vivid);color:var(--red-mid);}
    body.light .resume-card{background:#F5EEEC;border-color:#E0CECA;} body.light .resume-name{color:#1A0A09;}

    /* Responsive */
    @media(max-width:760px){
      .profile-header{flex-direction:column;align-items:center;text-align:center;}
      .ph-meta{justify-content:center;}
      .ph-actions{justify-content:center;}
      .info-grid{grid-template-columns:1fr;}
      .two-col{grid-template-columns:1fr;}
      .nav-links{display:none;}
      .page-shell{padding:20px 16px 40px;}
    }
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}
    .anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}.anim-d3{animation-delay:0.15s;}
  </style>
</head>
<body>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<div class="page-shell">
  <a href="employer_applicants.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Applicants</a>

  <!-- Profile Header -->
  <div class="profile-header anim">
    <div class="ph-avatar">
      <?php if($sAvatarUrl): ?><img src="<?php echo e($sAvatarUrl); ?>" alt="<?php echo e($sName); ?>">
      <?php else: echo e($sInitials); endif; ?>
    </div>
    <div class="ph-info">
      <div class="ph-name"><?php echo e($sName); ?></div>
      <?php if($sHeadline): ?><div class="ph-headline"><?php echo e($sHeadline); ?></div><?php endif; ?>
      <div class="ph-meta">
        <?php if($sLocation): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo e($sLocation); ?></span><?php endif; ?>
        <?php if($sEmail): ?><span><i class="fas fa-envelope"></i> <a href="mailto:<?php echo e($sEmail); ?>"><?php echo e($sEmail); ?></a></span><?php endif; ?>
        <?php if($sPhone): ?><span><i class="fas fa-phone"></i> <?php echo e($sPhone); ?></span><?php endif; ?>
        <?php if($sExpLevel): ?><span><i class="fas fa-layer-group"></i> <?php echo e($sExpLevel); ?> Level</span><?php endif; ?>
        <span><i class="fas fa-calendar"></i> Joined <?php echo date('M Y', strtotime($seeker['created_at'])); ?></span>
      </div>
    </div>
    <div class="ph-actions">
      <?php if($resume): ?>
      <a class="ph-btn primary" href="<?php echo e($resume['file_path']); ?>" target="_blank"><i class="fas fa-download"></i> Resume</a>
      <?php endif; ?>
      <a class="ph-btn" href="javascript:void(0)" onclick="if(typeof openMsgSidebar==='function'){openMsgSidebar();setTimeout(function(){if(typeof sbOpenThread==='function')sbOpenThread(<?php echo (int)$seekerId; ?>);},400);}"><i class="fas fa-envelope"></i> Message</a>
    </div>
  </div>

  <div class="two-col">
    <div>
      <!-- About / Summary -->
      <div class="section anim anim-d1">
        <div class="sec-title"><i class="fas fa-user"></i> About</div>
        <?php if($sSummary): ?>
          <div class="about-text"><?php echo nl2br(e($sSummary)); ?></div>
        <?php else: ?>
          <div class="empty-text">No professional summary provided.</div>
        <?php endif; ?>
      </div>

      <!-- Work Experience -->
      <div class="section anim anim-d2">
        <div class="sec-title"><i class="fas fa-briefcase"></i> Work Experience</div>
        <?php if(empty($experience)): ?>
          <div class="empty-text">No work experience listed.</div>
        <?php else: foreach($experience as $exp): ?>
          <div class="exp-item">
            <div class="exp-top">
              <div>
                <div class="exp-title"><?php echo e($exp['job_title']); ?></div>
                <div class="exp-company"><?php echo e($exp['company_name']); ?></div>
              </div>
              <div style="text-align:right;">
                <div class="exp-dates">
                  <?php
                    $sd = $exp['start_date'] ? date('M Y', strtotime($exp['start_date'])) : '';
                    $ed = $exp['is_current'] ? 'Present' : ($exp['end_date'] ? date('M Y', strtotime($exp['end_date'])) : '');
                    echo e($sd . ($ed ? " — $ed" : ''));
                  ?>
                </div>
                <?php if($exp['is_current']): ?><span class="exp-badge green">Current</span><?php endif; ?>
              </div>
            </div>
            <?php if(trim((string)($exp['description'] ?? ''))): ?>
              <div class="exp-desc"><?php echo nl2br(e($exp['description'])); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Education -->
      <div class="section anim anim-d3">
        <div class="sec-title"><i class="fas fa-graduation-cap"></i> Education</div>
        <?php if(empty($education)): ?>
          <div class="empty-text">No education records listed.</div>
        <?php else: foreach($education as $edu):
          if((int)($edu['no_schooling'] ?? 0) === 1) continue;
          $lvl = $levelLabels[strtolower($edu['education_level'] ?? 'college')] ?? 'Education';
        ?>
          <div class="exp-item">
            <div class="exp-top">
              <div>
                <div class="exp-title"><?php echo e($edu['school_name'] ?: 'School not specified'); ?></div>
                <div class="exp-company"><?php echo e($edu['degree_course'] ?: $lvl); ?></div>
              </div>
              <div class="exp-dates">
                <?php
                  $sy = $edu['start_year'] ?? '';
                  $ey = $edu['end_year'] ?? '';
                  echo e($sy . ($ey ? " — $ey" : ''));
                ?>
              </div>
            </div>
            <?php if(trim((string)($edu['honors'] ?? ''))): ?>
              <div class="exp-desc"><strong>Honors:</strong> <?php echo e($edu['honors']); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Certifications -->
      <?php if(!empty($certifications)): ?>
      <div class="section anim">
        <div class="sec-title"><i class="fas fa-certificate"></i> Certifications</div>
        <?php foreach($certifications as $cert): ?>
          <div class="exp-item">
            <div class="exp-top">
              <div>
                <div class="exp-title"><?php echo e($cert['cert_name']); ?></div>
                <div class="exp-company"><?php echo e($cert['issuing_org'] ?? ''); ?></div>
              </div>
              <div class="exp-dates">
                <?php
                  echo $cert['issue_date'] ? date('M Y', strtotime($cert['issue_date'])) : '';
                  if(!empty($cert['no_expiry'])) echo ' — No Expiry';
                  elseif($cert['expiry_date']) echo ' — '.date('M Y', strtotime($cert['expiry_date']));
                ?>
              </div>
            </div>
            <?php if(!empty($cert['description'])): ?>
              <div class="exp-desc"><?php echo nl2br(e($cert['description'])); ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <!-- Contact & Details -->
      <div class="section anim anim-d1">
        <div class="sec-title"><i class="fas fa-id-card"></i> Contact & Details</div>
        <div class="info-grid">
          <div class="info-item"><span class="info-label">Email</span><span class="info-value"><a href="mailto:<?php echo e($sEmail); ?>"><?php echo e($sEmail); ?></a></span></div>
          <?php if($sPhone): ?><div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?php echo e($sPhone); ?></span></div><?php endif; ?>
          <?php if($sHeadline): ?><div class="info-item"><span class="info-label">Desired Position</span><span class="info-value"><?php echo e($sHeadline); ?></span></div><?php endif; ?>
          <?php if($sExpLevel): ?><div class="info-item"><span class="info-label">Experience Level</span><span class="info-value"><?php echo e($sExpLevel); ?></span></div><?php endif; ?>
          <?php if($sFullAddress): ?><div class="info-item" style="grid-column:1/-1;"><span class="info-label">Address</span><span class="info-value"><?php echo e($sFullAddress); ?></span></div><?php endif; ?>
          <?php if($linkedinUrl): ?><div class="info-item"><span class="info-label">LinkedIn</span><span class="info-value"><a href="<?php echo e($linkedinUrl); ?>" target="_blank"><?php echo e($linkedinUrl); ?></a></span></div><?php endif; ?>
          <?php if($githubUrl): ?><div class="info-item"><span class="info-label">GitHub</span><span class="info-value"><a href="<?php echo e($githubUrl); ?>" target="_blank"><?php echo e($githubUrl); ?></a></span></div><?php endif; ?>
          <?php if($portfolioUrl): ?><div class="info-item"><span class="info-label">Portfolio</span><span class="info-value"><a href="<?php echo e($portfolioUrl); ?>" target="_blank"><?php echo e($portfolioUrl); ?></a></span></div><?php endif; ?>
        </div>
      </div>

      <!-- Skills -->
      <div class="section anim anim-d2">
        <div class="sec-title"><i class="fas fa-tools"></i> Skills</div>
        <?php if(empty($skills)): ?>
          <div class="empty-text">No skills listed.</div>
        <?php else: ?>
          <div class="skills-list">
            <?php foreach($skills as $sk):
              $cls = $skillLevelColors[$sk['skill_level'] ?? 'Intermediate'] ?? '';
            ?>
              <span class="skill-tag <?php echo $cls; ?>"><?php echo e($sk['skill_name']); ?><span style="opacity:0.6;font-size:10px;"><?php echo e($sk['skill_level']); ?></span></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Resume -->
      <div class="section anim anim-d2">
        <div class="sec-title"><i class="fas fa-file-alt"></i> Resume</div>
        <?php if($resume): ?>
          <div class="resume-card">
            <div class="resume-icon"><i class="fas fa-file-pdf"></i></div>
            <div class="resume-name"><?php echo e($resume['original_filename']); ?></div>
            <a class="resume-dl" href="<?php echo e($resume['file_path']); ?>" target="_blank"><i class="fas fa-download"></i> Download</a>
          </div>
        <?php else: ?>
          <div class="empty-text">No resume uploaded.</div>
        <?php endif; ?>
      </div>

      <!-- Applications to your company -->
      <div class="section anim anim-d3">
        <div class="sec-title"><i class="fas fa-clipboard-list"></i> Applications to Your Jobs</div>
        <?php if(empty($applications)): ?>
          <div class="empty-text">No applications found.</div>
        <?php else: foreach($applications as $app):
          $sm = $smeta[$app['status']] ?? ['c'=>'muted','i'=>'fa-circle'];
        ?>
          <div class="app-row">
            <div>
              <div class="app-row-title"><?php echo e($app['job_title']); ?></div>
              <div class="app-row-date">Applied <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></div>
            </div>
            <span class="sbadge <?php echo $sm['c']; ?>"><i class="fas <?php echo $sm['i']; ?>"></i> <?php echo e($app['status']); ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
  function setTheme(t){document.body.classList.toggle('light',t==='light');localStorage.setItem('ac-theme',t);document.getElementById('themeToggle').querySelector('i').className=t==='light'?'fas fa-sun':'fas fa-moon';}
  document.getElementById('themeToggle').addEventListener('click',function(){setTheme(document.body.classList.contains('light')?'dark':'light');});
  document.getElementById('profileToggle').addEventListener('click',function(e){e.stopPropagation();document.getElementById('profileDropdown').classList.toggle('open');});
  document.addEventListener('click',function(e){if(!document.getElementById('profileWrap').contains(e.target))document.getElementById('profileDropdown').classList.remove('open');});
  (function(){var p=new URLSearchParams(window.location.search).get('theme'),s=localStorage.getItem('ac-theme'),t=p||s||'dark';if(p)localStorage.setItem('ac-theme',p);setTheme(t);})();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>
