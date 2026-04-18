<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
require_once dirname(__DIR__) . '/includes/job_titles.php';
requireLogin('seeker');
$user = getUser();
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'profile';
$userId    = $user['id'];

// ── LOAD EXISTING PROFILE FROM DB ──────────────────────────────────────────
$db = getDB();

// Ensure certifications + languages tables exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS seeker_certifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
        cert_name VARCHAR(255) NOT NULL, issuing_org VARCHAR(255) DEFAULT NULL,
        issue_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL,
        no_expiry TINYINT(1) NOT NULL DEFAULT 0, description TEXT DEFAULT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), INDEX idx_cert_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS seeker_languages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
        language_name VARCHAR(100) NOT NULL, sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_lang_user (user_id, language_name),
        INDEX idx_lang_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS seeker_resumes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
        original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL, file_size INT UNSIGNED NOT NULL DEFAULT 0,
        mime_type VARCHAR(100) DEFAULT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), INDEX idx_resume_user (user_id), INDEX idx_resume_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $_) {}

// seeker_profiles row
$spStmt = $db->prepare("SELECT * FROM seeker_profiles WHERE user_id = :uid LIMIT 1");
$spStmt->execute([':uid' => $userId]);
$sp = $spStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// skills
$skStmt = $db->prepare("SELECT skill_name FROM seeker_skills WHERE user_id = :uid ORDER BY sort_order");
$skStmt->execute([':uid' => $userId]);
$dbSkills = $skStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// experience
$exStmt = $db->prepare("SELECT * FROM seeker_experience WHERE user_id = :uid ORDER BY sort_order");
$exStmt->execute([':uid' => $userId]);
$dbExperience = $exStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// education
$edStmt = $db->prepare("SELECT * FROM seeker_education WHERE user_id = :uid ORDER BY sort_order");
$edStmt->execute([':uid' => $userId]);
$dbEducation = $edStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// certifications
try {
    $crStmt = $db->prepare("SELECT * FROM seeker_certifications WHERE user_id = :uid ORDER BY sort_order");
    $crStmt->execute([':uid' => $userId]);
    $dbCertifications = $crStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) { $dbCertifications = []; }

// languages
try {
    $lgStmt = $db->prepare("SELECT language_name FROM seeker_languages WHERE user_id = :uid ORDER BY sort_order");
    $lgStmt->execute([':uid' => $userId]);
    $dbLanguages = $lgStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (\Throwable $e) { $dbLanguages = []; }

// avatar & banner URLs
$avatarStmt = $db->prepare("SELECT avatar_url FROM users WHERE id = :uid LIMIT 1");
$avatarStmt->execute([':uid' => $userId]);
$avatarUrl = $avatarStmt->fetchColumn() ?: '';
if ($avatarUrl && !str_starts_with($avatarUrl, '../') && !str_starts_with($avatarUrl, 'http')) $avatarUrl = '../' . $avatarUrl;
$bannerUrl = $sp['banner_url'] ?? '';
if ($bannerUrl && !str_starts_with($bannerUrl, '../') && !str_starts_with($bannerUrl, 'http')) $bannerUrl = '../' . $bannerUrl;

// resume — fetch here so PHP can render it directly in HTML (no JS dependency)
$dbResume = null;
try {
    $resStmt = $db->prepare("SELECT original_filename, file_size, uploaded_at FROM seeker_resumes WHERE user_id = :uid AND is_active = 1 ORDER BY uploaded_at DESC LIMIT 1");
    $resStmt->execute([':uid' => $userId]);
    $dbResume = $resStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (\Throwable $_) {}

// Encode for JS
$jsProfile = json_encode([
    'title'    => $sp['headline'] ?? '',
    'city'     => $sp['city_name'] ?? '',
    'country'  => $sp['country_name'] ?? '',
    'expLevel' => $sp['experience_level'] ?? '',
    'about'    => $sp['bio'] ?? '',
    'skills'   => $dbSkills,
    'experience' => array_map(fn($e) => [
        'title'   => $e['job_title'] ?? '',
        'company' => $e['company_name'] ?? '',
        'start'   => $e['start_date'] ? substr($e['start_date'],0,7) : '',
        'end'     => $e['is_current'] ? 'Present' : ($e['end_date'] ? substr($e['end_date'],0,7) : ''),
        'desc'    => $e['description'] ?? '',
    ], $dbExperience),
    'education' => array_map(fn($e) => [
        'school' => $e['school_name'] ?? '',
        'degree' => $e['degree_course'] ?? '',
        'start'  => $e['start_year'] ?? '',
        'end'    => $e['end_year'] ?? '',
    ], $dbEducation),
    'contact' => [
        'phone'        => $sp['phone'] ?? '',
        'city'         => $sp['city_name'] ?? '',
        'province'     => $sp['province_name'] ?? '',
        'availability' => $sp['desired_position'] ?? '',
    ],
    'links' => [
        'linkedin'  => $sp['linkedin_url']  ?? '',
        'github'    => $sp['github_url']    ?? '',
        'portfolio' => $sp['portfolio_url'] ?? '',
        'other'     => $sp['other_url']     ?? '',
    ],
    'certifications' => array_map(fn($c) => [
        'name'       => $c['cert_name']   ?? '',
        'org'        => $c['issuing_org'] ?? '',
        'issueDate'  => $c['issue_date']  ? substr($c['issue_date'],0,7) : '',
        'expiryDate' => $c['expiry_date'] ? substr($c['expiry_date'],0,7) : '',
        'noExpiry'   => (bool)($c['no_expiry'] ?? 0),
        'desc'       => $c['description'] ?? '',
    ], $dbCertifications),
    'languages' => $dbLanguages,
    'nextRole' => [
        'availability'   => $sp['nr_availability']   ?? '',
        'workTypes'      => ($sp['nr_work_types'] ?? '') ? explode(',', $sp['nr_work_types']) : [],
        'locations'      => $sp['nr_locations']      ?? '',
        'rightToWork'    => $sp['nr_right_to_work']  ?? '',
        'salary'         => $sp['nr_salary']         ?? '',
        'salaryPeriod'   => $sp['nr_salary_period']  ?? 'per month',
        'classification' => $sp['nr_classification'] ?? '',
        'approachability'=> $sp['nr_approachability']?? '',
    ],
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

// ── Compute initial profile completeness server-side ──
$initScore = 35; // base
if (!empty($sp['headline']))    $initScore += 10; // title
if (!empty($sp['bio']))         $initScore += 10; // about
if (count($dbSkills) > 0)       $initScore += 10; // skills
if (count($dbExperience) > 0)   $initScore += 10; // experience
if (count($dbEducation) > 0)    $initScore += 10; // education
if (!empty($sp['phone']))       $initScore +=  5; // phone
if (!empty($sp['linkedin_url']) || !empty($sp['github_url']) || !empty($sp['portfolio_url']))
                                $initScore += 10; // links
$initScore = min($initScore, 100);

// ── Following / Followers counts ──
$followingCount = 0;
try {
    $db->exec("CREATE TABLE IF NOT EXISTS company_follows (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_user_id INT UNSIGNED NOT NULL,
        employer_user_id INT UNSIGNED NOT NULL,
        followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_follow (follower_user_id, employer_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $cfStmt = $db->prepare("SELECT COUNT(*) FROM company_follows WHERE follower_user_id = :uid");
    $cfStmt->execute([':uid' => $userId]);
    $followingCount = (int)$cfStmt->fetchColumn();
} catch (\Throwable $_) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — My Profile</title>
  <script>
    (function(){
      const p=new URLSearchParams(window.location.search).get('theme');
      const t=p||localStorage.getItem('ac-theme')||'light';
      if(p) localStorage.setItem('ac-theme',p);
      if(t==='light') document.documentElement.classList.add('theme-light');
    })();
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }

    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    /* TUNNEL BG */
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── NAVBAR ── */

    .pd-sep { border:none; border-top:1px solid var(--soil-line); margin:4px 0; }
    .pd-logout { color:#E05050 !important; }
    .pd-logout i { color:#E05050 !important; }

    /* ── LAYOUT ── */
    .main-wrap { max-width:860px; margin:0 auto; padding:28px 24px 60px; position:relative; z-index:1; }
    .content-layout { display:flex; flex-direction:column; gap:16px; }

    /* ── SIDEBAR ── */
    .sidebar { position:sticky; top:72px; height:calc(100vh - 88px); overflow-y:hidden; scrollbar-width:none; display:flex; flex-direction:column; }
    .sidebar::-webkit-scrollbar { display:none; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; display:flex; flex-direction:column; flex:1; min-height:0; }
    .sidebar-head { padding:16px 18px 12px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--soil-line); }
    .sidebar-title { font-family:var(--font-body); font-size:12px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:7px; letter-spacing:0.07em; text-transform:uppercase; }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }
    .sidebar-profile { padding:16px 16px 14px; border-bottom:1px solid var(--soil-line); }
    .sp-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; margin-bottom:10px; box-shadow:0 0 20px rgba(209,61,44,0.25); }
    .sp-name { font-size:14px; font-weight:700; color:var(--text-light); }
    .sp-role { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; letter-spacing:0.05em; }
    .sidebar-stats { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sb-stat { background:var(--soil-hover); border-radius:7px; padding:10px 12px; }
    .sb-stat-num { font-size:18px; font-weight:800; color:var(--text-light); }
    .sb-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; letter-spacing:0.05em; text-transform:uppercase; margin-top:2px; }
    .sb-browse-wrap { padding:12px 14px; border-top:1px solid var(--soil-line); background:var(--soil-card); flex-shrink:0; }
    .sb-browse { display:flex; align-items:center; justify-content:center; gap:8px; padding:11px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; width:100%; box-shadow:0 2px 12px rgba(209,61,44,0.3); }
    .sb-browse:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 6px 20px rgba(209,61,44,0.4); }
    .sb-nav-scroll { flex:1; overflow-y:auto; scrollbar-width:none; }
    .sb-nav-scroll::-webkit-scrollbar { display:none; }
    .sb-nav-item { display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; border:none; background:none; font-family:var(--font-body); width:100%; text-align:left; border-bottom:1px solid var(--soil-line); }
    .sb-nav-item:last-child { border-bottom:none; }
    .sb-nav-item:hover { color:var(--text-light); background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .sb-nav-item i { width:16px; text-align:center; font-size:12px; color:var(--red-bright); }
    .sb-badge { margin-left:auto; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; }
    .sb-badge.amber { background:var(--amber); color:#1A0A09; }
    .sb-badge.green { background:#4CAF70; color:#fff; }

    /* ── PROFILE CONTENT ── */
    .profile-content { display:flex; flex-direction:column; gap:16px; }

    /* Profile Hero Card */
    .profile-hero { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; }
    .hero-banner { height:120px; background:linear-gradient(135deg,#3A0808 0%,var(--red-deep) 50%,#1A0505 100%); position:relative; overflow:hidden; }
    .hero-banner::after { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23D13D2C' fill-opacity='0.15'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
    .hero-edit-banner { position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.5); border:1px solid rgba(255,255,255,0.15); border-radius:6px; padding:5px 10px; font-size:11px; font-weight:600; color:rgba(255,255,255,0.7); cursor:pointer; display:flex; align-items:center; gap:5px; transition:0.2s; z-index:1; }
    .hero-edit-banner:hover { background:rgba(0,0,0,0.7); color:#fff; }
    .hero-body { padding:0 24px 24px; }
    .hero-avatar-wrap { display:flex; align-items:flex-end; justify-content:space-between; margin-top:-36px; margin-bottom:14px; }
    .hero-avatar { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800; color:#fff; border:4px solid var(--soil-card); box-shadow:0 0 0 2px var(--red-vivid),0 4px 20px rgba(209,61,44,0.4); flex-shrink:0; position:relative; }
    .avatar-edit-btn { position:absolute; bottom:0; right:0; width:24px; height:24px; border-radius:50%; background:var(--red-vivid); border:2px solid var(--soil-card); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:10px; color:#fff; transition:0.2s; }
    .avatar-edit-btn:hover { background:var(--red-bright); }
    .hero-actions { display:flex; gap:8px; align-items:center; }
    .btn-primary { padding:9px 20px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(209,61,44,0.35); }
    .btn-primary:hover { background:var(--red-bright); transform:translateY(-1px); }
    .btn-secondary { padding:9px 18px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; }
    .btn-secondary:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--text-light); }
    .hero-name { font-family:var(--font-display); font-size:22px; font-weight:700; color:var(--text-light); }
    .hero-title { font-size:14px; color:var(--text-mid); margin-top:4px; }
    .hero-meta { display:flex; flex-wrap:wrap; gap:12px; margin-top:12px; }
    .hero-meta-item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); font-weight:500; }
    .hero-meta-item i { color:var(--red-pale); font-size:11px; }
    .completeness-bar-wrap { margin-top:14px; padding-top:14px; border-top:1px solid var(--soil-line); }
    .completeness-label { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; }
    .completeness-label span { font-size:11px; font-weight:700; color:var(--text-muted); letter-spacing:0.05em; text-transform:uppercase; }
    .completeness-label strong { font-size:13px; color:var(--amber); font-weight:700; }
    .completeness-track { height:6px; background:var(--soil-hover); border-radius:99px; overflow:hidden; }
    .completeness-fill { height:100%; background:linear-gradient(90deg,var(--red-vivid),var(--amber)); border-radius:99px; transition:width 1s cubic-bezier(0.4,0,0.2,1); }

    /* Section Cards */
    .section-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; }
    .section-head { padding:16px 20px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; }
    .section-title { font-size:14px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:9px; }
    .section-title i { color:var(--red-bright); font-size:13px; }
    .section-body { padding:20px; }
    .btn-add { display:flex; align-items:center; gap:6px; padding:6px 12px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; }
    .btn-add:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--red-pale); }
    .btn-edit-section { display:flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:11px; font-weight:600; cursor:pointer; transition:0.18s; }
    .btn-edit-section:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--red-pale); }

    /* About section */
    .about-text { font-size:14px; color:var(--text-mid); line-height:1.7; }
    .about-placeholder { font-size:13px; color:var(--text-muted); font-style:italic; line-height:1.6; padding:14px; background:var(--soil-hover); border-radius:8px; border:1px dashed var(--soil-line); text-align:center; }
    .about-placeholder i { display:block; font-size:22px; color:var(--soil-line); margin-bottom:8px; }

    /* Skills */
    .skills-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .skill-tag { display:flex; align-items:center; gap:7px; padding:7px 13px; border-radius:99px; background:var(--soil-hover); border:1px solid var(--soil-line); font-size:12px; font-weight:600; color:var(--text-mid); transition:0.18s; }
    .skill-tag:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .skill-tag .remove { color:var(--text-muted); font-size:10px; cursor:pointer; transition:0.15s; margin-left:2px; }
    .skill-tag .remove:hover { color:#E05050; }
    .skills-placeholder { font-size:13px; color:var(--text-muted); font-style:italic; text-align:center; padding:20px; }

    /* Experience & Education */
    .timeline-item { display:flex; gap:16px; padding:16px 0; border-bottom:1px solid var(--soil-line); }
    .timeline-item:last-child { border-bottom:none; padding-bottom:0; }
    .timeline-logo { width:44px; height:44px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--text-muted); flex-shrink:0; }
    .timeline-info { flex:1; min-width:0; }
    .timeline-title { font-size:14px; font-weight:700; color:var(--text-light); }
    .timeline-sub { font-size:13px; color:var(--red-pale); font-weight:600; margin-top:2px; }
    .timeline-date { font-size:11px; color:var(--text-muted); margin-top:4px; display:flex; align-items:center; gap:5px; }
    .timeline-date i { font-size:10px; }
    .timeline-desc { font-size:13px; color:var(--text-mid); margin-top:8px; line-height:1.6; }
    .timeline-actions { display:flex; gap:6px; margin-top:8px; }
    .timeline-action { padding:4px 10px; border-radius:5px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:11px; font-weight:600; cursor:pointer; transition:0.15s; }
    .timeline-action:hover { border-color:var(--red-vivid); color:var(--red-pale); background:var(--soil-hover); }
    .empty-state { text-align:center; padding:32px 20px; }
    .empty-icon { font-size:32px; color:var(--soil-line); margin-bottom:10px; }
    .empty-text { font-size:13px; color:var(--text-muted); margin-bottom:14px; }

    /* Resume upload */
    .resume-upload-zone { border:2px dashed var(--soil-line); border-radius:10px; padding:32px 20px; text-align:center; cursor:pointer; transition:0.2s; }
    .resume-upload-zone:hover { border-color:var(--red-vivid); background:rgba(209,61,44,0.04); }
    .resume-upload-zone i { font-size:32px; color:var(--text-muted); margin-bottom:10px; display:block; }
    .resume-upload-zone p { font-size:13px; color:var(--text-muted); }
    .resume-upload-zone strong { color:var(--red-pale); cursor:pointer; }
    .resume-file-item { display:flex; align-items:center; gap:12px; padding:12px 14px; background:var(--soil-hover); border-radius:8px; border:1px solid var(--soil-line); }
    .resume-file-icon { width:38px; height:38px; border-radius:7px; background:rgba(209,61,44,0.12); display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--red-pale); flex-shrink:0; }
    .resume-file-info { flex:1; min-width:0; }
    .resume-file-name { font-size:13px; font-weight:600; color:var(--text-light); }
    .resume-file-size { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .resume-file-actions { display:flex; gap:8px; }

    /* Contact Info */
    .contact-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .contact-label { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:5px; }
    .contact-value { font-size:13px; color:var(--text-mid); font-weight:500; display:flex; align-items:center; gap:7px; }
    .contact-value i { color:var(--red-pale); font-size:12px; }
    .contact-value.placeholder { color:var(--text-muted); font-style:italic; }

    /* Links section */
    .links-grid { display:flex; flex-direction:column; gap:8px; }
    .link-item { display:flex; align-items:center; gap:10px; padding:10px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; text-decoration:none; color:var(--text-mid); transition:0.18s; font-size:13px; font-weight:500; }
    .link-item i { color:var(--red-pale); width:18px; text-align:center; }
    .link-item:hover { border-color:var(--red-vivid); color:var(--text-light); background:rgba(209,61,44,0.06); }
    .link-placeholder { font-size:12px; color:var(--text-muted); font-style:italic; }

    /* Two column layout for lower sections */
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

    /* Modal overlay */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(6px); z-index:500; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; visibility:hidden; transition:all 0.2s; }
    .modal-overlay.open { opacity:1; visibility:visible; }
    .modal { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; width:100%; max-width:520px; max-height:90vh; overflow-y:auto; transform:translateY(10px) scale(0.98); transition:all 0.22s cubic-bezier(0.4,0,0.2,1); }
    .modal-overlay.open .modal { transform:translateY(0) scale(1); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
    .modal-title { font-size:16px; font-weight:700; color:var(--text-light); }
    .modal-close { width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:none; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:0.15s; }
    .modal-close:hover { background:rgba(224,80,80,0.15); color:#E05050; }
    .form-group { margin-bottom:16px; }
    .form-label { display:block; font-size:11px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:6px; }
    .form-input { width:100%; padding:10px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; font-family:var(--font-body); font-size:13px; color:var(--text-light); transition:0.18s; outline:none; }
    .form-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .form-input::placeholder { color:var(--text-muted); }
    .form-textarea { resize:vertical; min-height:90px; line-height:1.5; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:22px; padding-top:16px; border-top:1px solid var(--soil-line); }
    .checkbox-row { display:flex; align-items:center; gap:10px; padding:4px 0; }
    .checkbox-row input[type="checkbox"] { width:16px; height:16px; accent-color:var(--red-vivid); cursor:pointer; }
    .checkbox-row label { font-size:13px; color:var(--text-mid); cursor:pointer; }

    /* LIGHT THEME */
    body.light {
      background:#F5EDEC; color:#1A0A09;
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .glow-orb { display:none; }
    body.light .modal-title { color:#1A0A09; }
    body.light .modal-head { border-color:#E0CECA; }
    body.light .form-label { color:#7A5555; }
    body.light .section-head { border-color:#E0CECA; }
    body.light .completeness-bar { background:#F0E4E2; }
    body.light .completeness-track { background:#F0E4E2; }
    body.light .empty-icon { color:#D0BCBA; }
    body.light .empty-text { color:#7A5555; }
    body.light .nr-label { color:#5A3838; }
    body.light .nr-edit-btn { color:#7A5555; }
    body.light .checkbox-row label { color:#4A2828; }
    body.light .btn-secondary { background:#F5EEEC; border-color:#D0BCBA; color:#4A2828; }
    body.light .btn-secondary:hover { background:#FEF0EE; }
    body.light .btn-add { border-color:#D0BCBA; color:#7A5555; }
    body.light .btn-add:hover { background:#FEF0EE; border-color:var(--red-mid); color:var(--red-mid); }
    body.light .btn-edit-section { border-color:#D0BCBA; color:#7A5555; }
    body.light .btn-edit-section:hover { background:#FEF0EE; border-color:var(--red-mid); color:var(--red-mid); }
    body.light .timeline-action { border-color:#D0BCBA; color:#7A5555; }
    body.light .timeline-action:hover { background:#FEF0EE; border-color:var(--red-mid); color:var(--red-mid); }
    body.light .sidebar-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sidebar-title { color:#1A0A09; }
    body.light .sp-name { color:#1A0A09; }
    body.light .sb-stat { background:#F0E4E2; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .sb-nav-item:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .sb-nav-item.active { color:var(--red-mid); }
    body.light .sb-browse-wrap { background:#FFFFFF; border-color:#E0CECA; }
    body.light .section-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .profile-hero { background:#FFFFFF; border-color:#E0CECA; }
    body.light .hero-avatar { border-color:#FFFFFF; }
    body.light .hero-name { color:#1A0A09; }
    body.light .hero-title { color:#6A4040; }
    body.light .hero-meta-item { color:#8A6060; }
    body.light .about-text { color:#3A2020; }
    body.light .about-placeholder { background:#F9F0EF; border-color:#D0BCBA; color:#8A6060; }
    body.light .skill-tag { background:#F0E4E2; border-color:#D0BCBA; color:#3A2020; }
    body.light .skill-tag:hover { border-color:var(--red-mid); color:var(--red-mid); }
    body.light .timeline-title { color:#1A0A09; }
    body.light .timeline-desc { color:#3A2020; }
    body.light .section-title { color:#1A0A09; }
    body.light .contact-value { color:#3A2020; }
    body.light .resume-upload-zone { border-color:#D0BCBA; }
    body.light .resume-upload-zone:hover { border-color:var(--red-mid); background:rgba(209,61,44,0.03); }
    body.light .resume-file-item { background:#F0E4E2; border-color:#D0BCBA; }
    body.light .resume-file-name { color:#1A0A09; }
    body.light .link-item { background:#F0E4E2; border-color:#D0BCBA; color:#3A2020; }
    body.light .link-item:hover { border-color:var(--red-mid); color:#1A0A09; background:#EDD8D6; }
    body.light .timeline-logo { background:#F0E4E2; border-color:#D0BCBA; }
    body.light .modal { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-close { background:#F0E4E2; }
    body.light .form-input { background:#F5EDEC; border-color:#D0BCBA; color:#1A0A09; }
    body.light .form-input:focus { border-color:var(--red-mid); box-shadow:0 0 0 3px rgba(184,53,37,0.1); }
    body.light .form-input::placeholder { color:#A08080; }
    /* Cert item */
    .cert-item { display:flex; gap:14px; padding:14px 0; border-bottom:1px solid var(--soil-line); }
    .cert-item:last-child { border-bottom:none; padding-bottom:0; }
    .cert-icon { width:42px; height:42px; border-radius:8px; background:rgba(209,61,44,0.1); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--red-pale); flex-shrink:0; }
    .cert-info { flex:1; min-width:0; }
    .cert-name { font-size:14px; font-weight:700; color:var(--text-light); }
    .cert-org { font-size:13px; color:var(--red-pale); font-weight:600; margin-top:2px; }
    .cert-dates { font-size:11px; color:var(--text-muted); margin-top:4px; display:flex; align-items:center; gap:5px; }
    .cert-dates i { font-size:10px; }
    .cert-desc { font-size:13px; color:var(--text-mid); margin-top:6px; line-height:1.5; }
    .cert-actions { display:flex; gap:6px; margin-top:8px; }

    /* Language chips */
    .lang-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .lang-chip { display:flex; align-items:center; gap:8px; padding:8px 14px; border-radius:99px; background:var(--soil-hover); border:1px solid var(--soil-line); font-size:13px; font-weight:600; color:var(--text-mid); transition:0.18s; }
    .lang-chip:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .lang-chip .remove { color:var(--text-muted); font-size:10px; cursor:pointer; transition:0.15s; }
    .lang-chip .remove:hover { color:#E05050; }

    /* Next Role rows */
    .next-role-grid { display:flex; flex-direction:column; gap:0; }
    .nr-row { display:flex; align-items:center; gap:14px; padding:13px 0; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:background 0.15s; }
    .nr-row:last-child { border-bottom:none; padding-bottom:0; }
    .nr-row:hover { background:rgba(209,61,44,0.03); }
    .nr-icon { width:36px; height:36px; border-radius:8px; background:rgba(209,61,44,0.08); display:flex; align-items:center; justify-content:center; font-size:14px; color:var(--red-pale); flex-shrink:0; }
    .nr-info { flex:1; min-width:0; }
    .nr-label { font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); }
    .nr-value { font-size:13px; color:var(--text-mid); font-weight:500; margin-top:2px; }
    .nr-value.placeholder { color:var(--text-muted); font-style:italic; }
    .nr-edit-btn { width:28px; height:28px; border-radius:6px; background:transparent; border:none; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:0.15s; flex-shrink:0; }
    .nr-edit-btn:hover { color:var(--red-pale); background:rgba(209,61,44,0.1); }
    /* Radio group for availability */
    .nr-radio-group { display:flex; flex-direction:column; gap:6px; }
    .nr-radio-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; cursor:pointer; transition:background 0.15s; font-size:14px; color:var(--text-mid); }
    .nr-radio-item:hover { background:var(--soil-hover); }
    .nr-radio-item input[type="radio"] { width:18px; height:18px; accent-color:var(--red-vivid); cursor:pointer; flex-shrink:0; }
    /* Location tags */
    .nr-loc-tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
    .nr-loc-tag { display:flex; align-items:center; gap:6px; padding:5px 12px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); font-size:12px; font-weight:600; color:var(--text-mid); }
    .nr-loc-tag .remove-loc { cursor:pointer; color:var(--text-muted); transition:0.15s; font-size:11px; }
    .nr-loc-tag .remove-loc:hover { color:var(--red-vivid); }
    /* Salary row */
    .nr-salary-row { display:flex; gap:8px; align-items:stretch; }
    .nr-salary-currency { padding:10px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px 0 0 8px; font-size:13px; color:var(--text-muted); font-weight:700; display:flex; align-items:center; border-right:none; }
    .nr-salary-input { flex:1; border-radius:0 8px 8px 0 !important; }
    .nr-salary-note { font-size:11px; color:var(--amber); margin-top:8px; display:flex; align-items:center; gap:6px; }
    .nr-salary-note i { font-size:12px; }
    /* Approachability toggle */
    .nr-toggle-row { display:flex; align-items:center; justify-content:space-between; gap:14px; margin-bottom:14px; }
    .nr-toggle-label { font-size:14px; font-weight:600; color:var(--text-mid); }
    .nr-toggle-track { width:44px; height:24px; border-radius:12px; background:var(--soil-line); position:relative; cursor:pointer; transition:background 0.2s; flex-shrink:0; }
    .nr-toggle-track.on { background:var(--red-vivid); }
    .nr-toggle-thumb { width:20px; height:20px; border-radius:50%; background:#fff; position:absolute; top:2px; left:2px; transition:left 0.2s; box-shadow:0 1px 3px rgba(0,0,0,0.3); }
    .nr-toggle-track.on .nr-toggle-thumb { left:22px; }
    .nr-toggle-desc { font-size:12px; color:var(--text-muted); line-height:1.6; }
    /* Right to work row */
    .nr-rtw-entry { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
    .nr-rtw-entry select { flex:1; }
    .nr-rtw-remove { width:28px; height:28px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:12px; transition:0.15s; flex-shrink:0; }
    .nr-rtw-remove:hover { color:var(--red-vivid); border-color:var(--red-vivid); }
    .nr-add-link { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer; padding:6px 0; transition:0.15s; background:none; border:none; font-family:var(--font-body); }
    .nr-add-link:hover { color:var(--red-bright); }

    body.light .cert-icon { background:rgba(209,61,44,0.08); border-color:#D0BCBA; }
    body.light .cert-name { color:#1A0A09; }
    body.light .cert-desc { color:#3A2020; }
    body.light .lang-chip { background:#F0E4E2; border-color:#D0BCBA; color:#3A2020; }
    body.light .lang-chip:hover { border-color:var(--red-mid); color:var(--red-mid); }
    body.light .nr-icon { background:rgba(209,61,44,0.06); }
    body.light .nr-value { color:#3A2020; }
    body.light .nr-radio-item { color:#3A2020; }
    body.light .nr-radio-item:hover { background:#FEF0EE; }
    body.light .nr-loc-tag { background:#F0E4E2; border-color:#D0BCBA; color:#3A2020; }
    body.light .nr-salary-currency { background:#F0E4E2; border-color:#D0BCBA; color:#5A3838; }
    body.light .nr-toggle-track { background:#D0BCBA; }
    body.light .nr-toggle-desc { color:#7A5555; }
    body.light .nr-row:hover { background:rgba(209,61,44,0.03); }

    /* ANIM */
    .anim { opacity:0; transform:translateY(14px); animation:fadeUp 0.45s cubic-bezier(0.4,0,0.2,1) forwards; }
    .anim-d1 { animation-delay:0.05s; }
    .anim-d2 { animation-delay:0.12s; }
    .anim-d3 { animation-delay:0.18s; }
    .anim-d4 { animation-delay:0.22s; }
    .anim-d5 { animation-delay:0.26s; }
    @keyframes fadeUp { to { opacity:1; transform:none; } }

    @media(max-width:1060px) { .two-col{grid-template-columns:1fr} }
    @media(max-width:640px) { .form-row{grid-template-columns:1fr} .contact-grid{grid-template-columns:1fr} .hero-actions{flex-wrap:wrap} }

    /* Additional light-mode overrides */
    body.light .timeline-sub { color:#4A2828; }
    body.light .timeline-date { color:#7A5555; }
    body.light .contact-label { color:#7A5555; }
    body.light .cert-org { color:#4A2828; }
    body.light .cert-dates { color:#7A5555; }
</style>
</head>
<body>
<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g stroke="#C0392B" stroke-width="1.5" fill="none" opacity="0.6">
      <path d="M0 200 Q200 180 350 240 Q500 300 600 260 Q750 210 900 280 Q1050 350 1200 300 Q1320 260 1440 280"/>
      <path d="M0 450 Q150 430 300 490 Q500 560 650 510 Q800 460 950 530 Q1100 600 1300 550 Q1380 530 1440 540"/>
      <path d="M350 0 Q340 100 360 200 Q380 300 350 400 Q320 500 340 600 Q360 700 350 900"/>
      <path d="M720 0 Q710 150 730 300 Q750 450 720 600 Q690 750 710 900"/>
    </g>
    <g fill="#E54C3A" opacity="0.4">
      <circle cx="350" cy="240" r="3.5"/><circle cx="600" cy="260" r="3"/>
      <circle cx="900" cy="280" r="3.5"/><circle cx="300" cy="490" r="3"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<!-- MAIN -->
<div class="main-wrap">
  <div class="content-layout">

    <!-- PROFILE CONTENT -->
    <div class="profile-content">

      <!-- Profile Hero -->
      <div class="profile-hero anim anim-d2">
        <div class="hero-banner" id="heroBanner"<?php if($bannerUrl): ?> style="background:url('<?= htmlspecialchars($bannerUrl, ENT_QUOTES) ?>') center/cover no-repeat;"<?php endif; ?>>
          <div class="hero-edit-banner" onclick="document.getElementById('bannerInput').click()"><i class="fas fa-camera"></i> Edit Banner</div>
          <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="uploadPhoto(this,'banner')">
        </div>
        <div class="hero-body">
          <div class="hero-avatar-wrap">
            <div class="hero-avatar" id="heroAvatar"<?php if($avatarUrl): ?> style="background:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>') center/cover no-repeat; font-size:0;"<?php endif; ?>>
              <?= htmlspecialchars($initials) ?>
              <div class="avatar-edit-btn" onclick="document.getElementById('avatarInput').click()"><i class="fas fa-camera"></i></div>
              <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="uploadPhoto(this,'avatar')">
            </div>
            <div class="hero-actions">
              <button class="btn-primary" onclick="openModal('editProfileModal')"><i class="fas fa-pencil-alt"></i> Edit Profile</button>
            </div>
          </div>
          <div class="hero-name"><?= htmlspecialchars($fullName) ?></div>
          <div class="hero-title" id="heroTitle">Add your job title or headline</div>
          <div class="hero-meta">
            <div class="hero-meta-item" id="heroLocation"><i class="fas fa-map-marker-alt"></i> Add location</div>
            <div class="hero-meta-item" id="heroExperience"><i class="fas fa-briefcase"></i> Experience level</div>
            <div class="hero-meta-item"><i class="fas fa-envelope"></i> <?= htmlspecialchars($userEmail) ?></div>
          </div>
          <div class="sidebar-stats" style="margin:10px 0 4px;">
            <div class="sb-stat">
              <div class="sb-stat-num" id="followingCountEl"><?= $followingCount ?></div>
              <div class="sb-stat-lbl">Following</div>
            </div>
            <div class="sb-stat">
              <div class="sb-stat-num">0</div>
              <div class="sb-stat-lbl">Followers</div>
            </div>
          </div>
          <div class="completeness-bar-wrap">
            <div class="completeness-label">
              <span>Profile Completeness</span>
              <div style="display:flex;align-items:center;gap:8px;">
                <strong id="completenessText"><?= $initScore ?>%</strong>
                <button id="completenessToggle" onclick="toggleChecklist()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:11px;font-family:var(--font-body);font-weight:600;padding:2px 6px;border-radius:4px;transition:0.15s;" title="What's missing?"><i class="fas fa-chevron-down" id="checklistChevron"></i> What's missing?</button>
              </div>
            </div>
            <div class="completeness-track">
              <div class="completeness-fill" id="completenessFill" style="width:<?= $initScore ?>%"></div>
            </div>
            <div id="completenessChecklist" style="display:none;margin-top:12px;background:var(--soil-hover);border-radius:8px;padding:12px 14px;border:1px solid var(--soil-line);">
              <div style="font-size:11px;font-weight:700;color:var(--text-muted);letter-spacing:0.06em;text-transform:uppercase;margin-bottom:8px;">Complete your profile</div>
              <div id="checklistItems" style="display:flex;flex-direction:column;gap:6px;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- About -->
      <div class="section-card anim anim-d2">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-align-left"></i> About Me</div>
          <button class="btn-edit-section" onclick="openModal('aboutModal')"><i class="fas fa-pencil-alt"></i> Edit</button>
        </div>
        <div class="section-body">
          <div id="aboutContent">
            <div class="about-placeholder">
              <i class="fas fa-pen-fancy"></i>
              Write a short bio to let employers know who you are, what you're passionate about, and what you're looking for.
            </div>
          </div>
        </div>
      </div>

      <!-- Skills -->
      <div class="section-card anim anim-d3">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-tools"></i> Skills</div>
          <button class="btn-add" onclick="openModal('skillModal')"><i class="fas fa-plus"></i> Add Skill</button>
        </div>
        <div class="section-body">
          <div class="skills-grid" id="skillsGrid">
            <div class="skills-placeholder">No skills added yet. Add skills to help employers find you.</div>
          </div>
        </div>
      </div>

      <!-- Experience & Education (two column) -->
      <div class="two-col">

        <!-- Work Experience -->
        <div class="section-card anim anim-d3">
          <div class="section-head">
            <div class="section-title"><i class="fas fa-briefcase"></i> Experience</div>
            <button class="btn-add" onclick="openModal('expModal')"><i class="fas fa-plus"></i> Add</button>
          </div>
          <div class="section-body" id="expList">
            <div class="empty-state">
              <div class="empty-icon"><i class="fas fa-briefcase"></i></div>
              <div class="empty-text">No work experience added yet.</div>
              <button class="btn-add" style="margin:0 auto;" onclick="openModal('expModal')"><i class="fas fa-plus"></i> Add Experience</button>
            </div>
          </div>
        </div>

        <!-- Education -->
        <div class="section-card anim anim-d3">
          <div class="section-head">
            <div class="section-title"><i class="fas fa-graduation-cap"></i> Education</div>
            <button class="btn-add" onclick="openModal('eduModal')"><i class="fas fa-plus"></i> Add</button>
          </div>
          <div class="section-body" id="eduList">
            <div class="empty-state">
              <div class="empty-icon"><i class="fas fa-graduation-cap"></i></div>
              <div class="empty-text">No education history added yet.</div>
              <button class="btn-add" style="margin:0 auto;" onclick="openModal('eduModal')"><i class="fas fa-plus"></i> Add Education</button>
            </div>
          </div>
        </div>

      </div>

      <!-- Resume -->
      <div id="resume-section" class="section-card anim anim-d4">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-file-alt"></i> Resume / CV</div>
        </div>
        <div class="section-body">
          <div id="resumeArea">
            <?php if ($dbResume):
              $rName = htmlspecialchars($dbResume['original_filename'], ENT_QUOTES);
              $rSize = $dbResume['file_size'] < 1048576
                  ? round($dbResume['file_size']/1024).'KB'
                  : number_format($dbResume['file_size']/1048576,1).'MB';
              $rDate = date('M j, Y', strtotime($dbResume['uploaded_at']));
            ?>
            <div class="resume-file-item">
              <div class="resume-file-icon"><i class="fas fa-file-pdf"></i></div>
              <div class="resume-file-info">
                <div class="resume-file-name"><?= $rName ?></div>
                <div class="resume-file-meta"><?= $rSize ?> &middot; Uploaded <?= $rDate ?></div>
              </div>
              <div class="resume-file-actions">
                <button class="timeline-action" onclick="document.getElementById('resumeInput').click()"><i class="fas fa-sync"></i> Replace</button>
              </div>
            </div>
            <?php else: ?>
            <div class="resume-upload-zone" onclick="document.getElementById('resumeInput').click()">
              <i class="fas fa-cloud-upload-alt"></i>
              <p>Drag & drop your resume here or <strong>browse files</strong></p>
              <p style="font-size:11px;margin-top:6px;">Accepted formats: PDF, DOC, DOCX &middot; Max 5MB</p>
            </div>
            <?php endif; ?>
            <input type="file" id="resumeInput" accept=".pdf,.doc,.docx" style="display:none" onchange="handleResumeUpload(this)">
          </div>
        </div>
      </div>

      <!-- Links -->
      <div class="section-card anim anim-d4">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-link"></i> Links & Portfolio</div>
          <button class="btn-edit-section" onclick="openModal('linksModal')"><i class="fas fa-pencil-alt"></i> Edit</button>
        </div>
        <div class="section-body">
          <div class="links-grid" id="linksGrid">
            <?php if (!empty($sp['linkedin_url']) || !empty($sp['github_url']) || !empty($sp['portfolio_url']) || !empty($sp['other_url'])): ?>
            <?php if (!empty($sp['linkedin_url'])): ?><a class="link-item" href="<?= htmlspecialchars($sp['linkedin_url'], ENT_QUOTES) ?>" target="_blank"><i class="fab fa-linkedin"></i> LinkedIn</a><?php endif; ?>
            <?php if (!empty($sp['github_url'])): ?><a class="link-item" href="<?= htmlspecialchars($sp['github_url'], ENT_QUOTES) ?>" target="_blank"><i class="fab fa-github"></i> GitHub</a><?php endif; ?>
            <?php if (!empty($sp['portfolio_url'])): ?><a class="link-item" href="<?= htmlspecialchars($sp['portfolio_url'], ENT_QUOTES) ?>" target="_blank"><i class="fas fa-globe"></i> Portfolio</a><?php endif; ?>
            <?php if (!empty($sp['other_url'])): ?><a class="link-item" href="<?= htmlspecialchars($sp['other_url'], ENT_QUOTES) ?>" target="_blank"><i class="fas fa-link"></i> Other Link</a><?php endif; ?>
            <?php else: ?>
            <div class="link-placeholder">No links added yet. Add your LinkedIn, GitHub, or portfolio.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Licences & Certifications -->
      <div class="section-card anim anim-d4">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-certificate"></i> Licences & Certifications</div>
          <button class="btn-add" onclick="openModal('certModal')"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div class="section-body" id="certList">
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-certificate"></i></div>
            <div class="empty-text">No licences or certifications added yet.</div>
            <button class="btn-add" style="margin:0 auto;" onclick="openModal('certModal')"><i class="fas fa-plus"></i> Add Licence or Certification</button>
          </div>
        </div>
      </div>

      <!-- Languages -->
      <div class="section-card anim anim-d5">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-globe-americas"></i> Languages</div>
          <button class="btn-add" onclick="openModal('langModal')"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div class="section-body" id="langList">
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-globe-americas"></i></div>
            <div class="empty-text">No languages added yet.</div>
            <button class="btn-add" style="margin:0 auto;" onclick="openModal('langModal')"><i class="fas fa-plus"></i> Add Language</button>
          </div>
        </div>
      </div>

      <!-- About Your Next Role -->
      <div class="section-card anim anim-d5">
        <div class="section-head">
          <div class="section-title"><i class="fas fa-compass"></i> About Your Next Role</div>
        </div>
        <div class="section-body">
          <div class="next-role-grid" id="nextRoleGrid">
            <div class="nr-row" onclick="openModal('nrAvailModal')">
              <div class="nr-icon"><i class="fas fa-clock"></i></div>
              <div class="nr-info">
                <div class="nr-label">Availability</div>
                <div class="nr-value placeholder" id="nrAvailability">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-plus"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrWorkTypeModal')">
              <div class="nr-icon"><i class="fas fa-briefcase"></i></div>
              <div class="nr-info">
                <div class="nr-label">Preferred work types</div>
                <div class="nr-value placeholder" id="nrWorkTypes">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-plus"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrLocModal')">
              <div class="nr-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div class="nr-info">
                <div class="nr-label">Preferred locations</div>
                <div class="nr-value placeholder" id="nrLocations">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-pencil-alt"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrRightModal')">
              <div class="nr-icon"><i class="fas fa-id-card"></i></div>
              <div class="nr-info">
                <div class="nr-label">Right to work</div>
                <div class="nr-value placeholder" id="nrRightToWork">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-plus"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrSalaryModal')">
              <div class="nr-icon"><i class="fas fa-money-bill-wave"></i></div>
              <div class="nr-info">
                <div class="nr-label">Salary expectation</div>
                <div class="nr-value placeholder" id="nrSalary">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-plus"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrClassModal')">
              <div class="nr-icon"><i class="fas fa-layer-group"></i></div>
              <div class="nr-info">
                <div class="nr-label">Classification of interest</div>
                <div class="nr-value placeholder" id="nrClassification">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-pencil-alt"></i></button>
            </div>
            <div class="nr-row" onclick="openModal('nrApproachModal')">
              <div class="nr-icon"><i class="fas fa-handshake"></i></div>
              <div class="nr-info">
                <div class="nr-label">Approachability</div>
                <div class="nr-value placeholder" id="nrApproachability">Not specified</div>
              </div>
              <button class="nr-edit-btn"><i class="fas fa-pencil-alt"></i></button>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /profile-content -->
  </div><!-- /content-layout -->
</div><!-- /main-wrap -->

<!-- ── MODALS ── -->

<!-- Edit Profile Modal -->
<div class="modal-overlay" id="editProfileModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Edit personal details</div>
      <button class="modal-close" onclick="closeModal('editProfileModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">First name</label>
        <input class="form-input" type="text" id="editFirstName" value="<?= htmlspecialchars($firstName) ?>" placeholder="First name">
      </div>
      <div class="form-group">
        <label class="form-label">Last name</label>
        <input class="form-input" type="text" id="editLastName" value="<?= htmlspecialchars(trim(str_replace($firstName, '', $fullName))) ?>" placeholder="Last name">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Job Title / Headline</label>
      <input class="form-input" type="text" id="editTitle" placeholder="e.g. Fresh Graduate · Web Developer">
    </div>
    <div class="form-group">
      <label class="form-label">Home location</label>
      <input class="form-input" type="text" id="editLocation" placeholder="e.g. San Rafael Bulacan">
    </div>
    <div class="form-group">
      <label class="form-label">Phone number <span style="font-weight:400;text-transform:none;letter-spacing:0;font-style:italic;color:var(--text-muted);">(recommended)</span></label>
      <div class="form-row">
        <div>
          <select class="form-input" id="editPhoneCountry">
            <option value="+63">Philippines (+63)</option>
            <option value="+1">United States (+1)</option>
            <option value="+65">Singapore (+65)</option>
            <option value="+61">Australia (+61)</option>
            <option value="+81">Japan (+81)</option>
            <option value="+44">United Kingdom (+44)</option>
            <option value="+91">India (+91)</option>
          </select>
        </div>
        <div>
          <div style="display:flex;align-items:center;gap:0;">
            <span style="padding:10px 8px 10px 14px;background:var(--soil-hover);border:1px solid var(--soil-line);border-right:none;border-radius:8px 0 0 8px;font-size:13px;color:var(--text-muted);white-space:nowrap;" id="phoneCodeDisplay">+63</span>
            <input class="form-input" type="tel" id="editPhone" placeholder="Enter phone number" style="border-radius:0 8px 8px 0;">
          </div>
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Experience Level</label>
      <select class="form-input" id="editExpLevel">
        <option value="">Select level...</option>
        <option>Entry</option>
        <option>Junior</option>
        <option>Mid</option>
        <option>Senior</option>
        <option>Lead</option>
        <option>Executive</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label">Email address</label>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:13px;color:var(--text-mid);"><?= htmlspecialchars($userEmail) ?></span>
        <a href="antcareers_seekerSettings.php" style="font-size:13px;font-weight:600;color:var(--red-pale);text-decoration:underline;cursor:pointer;">Edit in Settings</a>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="saveProfile()">Save</button>
      <button class="btn-secondary" onclick="closeModal('editProfileModal')">Cancel</button>
    </div>
  </div>
</div>

<!-- About Modal -->
<div class="modal-overlay" id="aboutModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">About Me</div>
      <button class="modal-close" onclick="closeModal('aboutModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Bio</label>
      <textarea class="form-input form-textarea" id="aboutText" rows="6" placeholder="Write a short summary about yourself, your career goals, and what you bring to the table..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('aboutModal')">Cancel</button>
      <button class="btn-primary" onclick="saveAbout()">Save</button>
    </div>
  </div>
</div>

<!-- Skill Modal -->
<div class="modal-overlay" id="skillModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Add Skill</div>
      <button class="modal-close" onclick="closeModal('skillModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Skill Name</label>
      <input class="form-input" type="text" id="skillInput" placeholder="e.g. HTML, CSS, JavaScript, PHP..." onkeydown="if(event.key==='Enter') saveSkill()">
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:-4px;">
      <?php
      $suggestions = ['HTML','CSS','JavaScript','PHP','MySQL','Python','React','Laravel','Bootstrap','Git','Figma','Node.js'];
      foreach($suggestions as $s) {
        echo "<span style='padding:5px 10px;border-radius:99px;background:var(--soil-hover);border:1px solid var(--soil-line);font-size:11px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:0.15s;' onclick=\"document.getElementById('skillInput').value='$s'\" onmouseover=\"this.style.borderColor='var(--red-vivid)';this.style.color='var(--red-pale)'\" onmouseout=\"this.style.borderColor='var(--soil-line)';this.style.color='var(--text-muted)'\">$s</span>";
      }
      ?>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('skillModal')">Cancel</button>
      <button class="btn-primary" onclick="saveSkill()">Add Skill</button>
    </div>
  </div>
</div>

<!-- Experience Modal -->
<div class="modal-overlay" id="expModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Add Work Experience</div>
      <button class="modal-close" onclick="closeModal('expModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Job Title</label>
      <input class="form-input" type="text" id="expTitle" placeholder="e.g. Web Developer Intern">
    </div>
    <div class="form-group">
      <label class="form-label">Company / Organization</label>
      <input class="form-input" type="text" id="expCompany" placeholder="Company name">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Start Date</label>
        <input class="form-input" type="month" id="expStart">
      </div>
      <div class="form-group">
        <label class="form-label">End Date</label>
        <input class="form-input" type="month" id="expEnd">
      </div>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="expCurrent" onchange="document.getElementById('expEnd').disabled=this.checked">
      <label for="expCurrent">I currently work here</label>
    </div>
    <div class="form-group" style="margin-top:14px;">
      <label class="form-label">Description (optional)</label>
      <textarea class="form-input form-textarea" id="expDesc" rows="4" placeholder="Briefly describe your role and key achievements..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('expModal')">Cancel</button>
      <button class="btn-primary" onclick="saveExperience()">Save Experience</button>
    </div>
  </div>
</div>

<!-- Education Modal -->
<div class="modal-overlay" id="eduModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Add Education</div>
      <button class="modal-close" onclick="closeModal('eduModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">School / University</label>
      <input class="form-input" type="text" id="eduSchool" placeholder="e.g. Bulacan State University">
    </div>
    <div class="form-group">
      <label class="form-label">Degree / Program</label>
      <input class="form-input" type="text" id="eduDegree" placeholder="e.g. Bachelor of Science in Information Technology">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Year Started</label>
        <input class="form-input" type="number" id="eduStart" min="1990" max="2030" placeholder="e.g. 2022">
      </div>
      <div class="form-group">
        <label class="form-label">Year Ended / Expected</label>
        <input class="form-input" type="number" id="eduEnd" min="1990" max="2035" placeholder="e.g. 2026">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('eduModal')">Cancel</button>
      <button class="btn-primary" onclick="saveEducation()">Save Education</button>
    </div>
  </div>
</div>

<!-- Links Modal -->
<div class="modal-overlay" id="linksModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Links & Portfolio</div>
      <button class="modal-close" onclick="closeModal('linksModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">LinkedIn</label>
      <input class="form-input" type="url" id="linkLinkedIn" placeholder="https://linkedin.com/in/your-profile">
    </div>
    <div class="form-group">
      <label class="form-label">GitHub</label>
      <input class="form-input" type="url" id="linkGitHub" placeholder="https://github.com/your-username">
    </div>
    <div class="form-group">
      <label class="form-label">Portfolio Website</label>
      <input class="form-input" type="url" id="linkPortfolio" placeholder="https://yourportfolio.com">
    </div>
    <div class="form-group">
      <label class="form-label">Other Link</label>
      <input class="form-input" type="url" id="linkOther" placeholder="Any other relevant link">
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('linksModal')">Cancel</button>
      <button class="btn-primary" onclick="saveLinks()">Save Links</button>
    </div>
  </div>
</div>

<!-- Licence / Certification Modal -->
<div class="modal-overlay" id="certModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="certModalTitle">Add Licence or Certification</div>
      <button class="modal-close" onclick="closeModal('certModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Name</label>
      <input class="form-input" type="text" id="certName" placeholder="e.g. AWS Certified Solutions Architect">
    </div>
    <div class="form-group">
      <label class="form-label">Issuing Organisation</label>
      <input class="form-input" type="text" id="certOrg" placeholder="e.g. Amazon Web Services">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Issue Date</label>
        <input class="form-input" type="month" id="certIssueDate">
      </div>
      <div class="form-group">
        <label class="form-label">Expiry Date</label>
        <input class="form-input" type="month" id="certExpiryDate">
      </div>
    </div>
    <div class="checkbox-row">
      <input type="checkbox" id="certNoExpiry" onchange="document.getElementById('certExpiryDate').disabled=this.checked">
      <label for="certNoExpiry">This credential does not expire</label>
    </div>
    <div class="form-group" style="margin-top:14px;">
      <label class="form-label">Description (optional)</label>
      <textarea class="form-input form-textarea" id="certDesc" rows="3" placeholder="Briefly describe this credential..."></textarea>
    </div>
    <input type="hidden" id="certEditIndex" value="-1">
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('certModal')">Cancel</button>
      <button class="btn-primary" onclick="saveCert()">Save</button>
    </div>
  </div>
</div>

<!-- Language Modal -->
<div class="modal-overlay" id="langModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Add Language</div>
      <button class="modal-close" onclick="closeModal('langModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Language</label>
      <input class="form-input" type="text" id="langInput" placeholder="e.g. English, Filipino, Japanese..." onkeydown="if(event.key==='Enter') saveLang()">
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:-4px;">
      <?php
      $langSuggestions = ['English','Filipino','Mandarin','Japanese','Korean','Spanish','French','Hindi','Arabic','German'];
      foreach($langSuggestions as $l) {
        echo "<span style='padding:5px 10px;border-radius:99px;background:var(--soil-hover);border:1px solid var(--soil-line);font-size:11px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:0.15s;' onclick=\"document.getElementById('langInput').value='" . htmlspecialchars($l, ENT_QUOTES) . "'\" onmouseover=\"this.style.borderColor='var(--red-vivid)';this.style.color='var(--red-pale)'\" onmouseout=\"this.style.borderColor='var(--soil-line)';this.style.color='var(--text-muted)'\">" . htmlspecialchars($l) . "</span>";
      }
      ?>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('langModal')">Cancel</button>
      <button class="btn-primary" onclick="saveLang()">Add Language</button>
    </div>
  </div>
</div>

<!-- Availability Modal -->
<div class="modal-overlay" id="nrAvailModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Availability</div>
      <button class="modal-close" onclick="closeModal('nrAvailModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">When can you start?</label>
      <select class="form-input" id="nrAvailInput">
        <option value="">Select availability...</option>
        <option value="Now">Now</option>
        <option value="2 weeks">2 weeks</option>
        <option value="4 weeks">4 weeks</option>
        <option value="8 weeks">8 weeks</option>
        <option value="12+ weeks">12+ weeks</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="saveNrAvail()">Done</button>
    </div>
  </div>
</div>

<!-- Preferred Work Types Modal -->
<div class="modal-overlay" id="nrWorkTypeModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Preferred work types</div>
      <button class="modal-close" onclick="closeModal('nrWorkTypeModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Work type</label>
      <select class="form-input" id="nrWorkTypeInput">
        <option value="">Select...</option>
        <option>Full-time</option>
        <option>Part-time</option>
        <option>Contract</option>
        <option>Freelance</option>
        <option>Internship</option>
        <option>Casual</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('nrWorkTypeModal')">Cancel</button>
      <button class="btn-primary" onclick="saveNrWorkType()">Save</button>
    </div>
  </div>
</div>

<!-- Preferred Locations Modal -->
<div class="modal-overlay" id="nrLocModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Preferred locations</div>
      <button class="modal-close" onclick="closeModal('nrLocModal')"><i class="fas fa-times"></i></button>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Let employers know about your preferred work locations.</p>
    <div class="form-group">
      <label class="form-label">Enter location</label>
      <div style="display:flex;gap:8px;">
        <input class="form-input" type="text" id="nrLocInput" placeholder="e.g. Manila, Makati, Cebu" style="flex:1;">
        <button class="btn-secondary" onclick="addNrLocation()" style="white-space:nowrap;"><i class="fas fa-plus" style="margin-right:4px;"></i> Add</button>
      </div>
    </div>
    <div class="form-group" id="nrLocListGroup" style="display:none;">
      <label class="form-label">Added locations</label>
      <div class="nr-loc-tags" id="nrLocTags"></div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('nrLocModal')">Cancel</button>
      <button class="btn-primary" onclick="saveNrLocations()">Save</button>
    </div>
  </div>
</div>

<!-- Right to Work Modal -->
<div class="modal-overlay" id="nrRightModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Right to work</div>
      <button class="modal-close" onclick="closeModal('nrRightModal')"><i class="fas fa-times"></i></button>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Your citizenship and visas</p>
    <div id="nrRtwEntries">
      <div class="form-group">
        <label class="form-label">Country</label>
        <select class="form-input" id="nrRtwCountry">
          <option value="">Select country...</option>
          <option>Philippines</option>
          <option>United States</option>
          <option>Singapore</option>
          <option>Australia</option>
          <option>Japan</option>
          <option>United Kingdom</option>
          <option>Canada</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Right to work</label>
        <select class="form-input" id="nrRtwType">
          <option value="">Select right to work...</option>
          <option>Citizen</option>
          <option>Permanent Resident</option>
          <option>Work Visa</option>
          <option>Student Visa</option>
          <option>Requires Sponsorship</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('nrRightModal')">Cancel</button>
      <button class="btn-primary" onclick="saveNrRight()">Save</button>
    </div>
  </div>
</div>

<!-- Salary Expectation Modal -->
<div class="modal-overlay" id="nrSalaryModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Salary expectation</div>
      <button class="modal-close" onclick="closeModal('nrSalaryModal')"><i class="fas fa-times"></i></button>
    </div>
    <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">Your expected base salary will help you be found by relevant employers.</p>
    <div class="form-group">
      <label class="form-label">Amount</label>
      <div class="nr-salary-row">
        <div class="nr-salary-currency">PHP</div>
        <input class="form-input nr-salary-input" type="text" id="nrSalaryInput" placeholder="Enter amount">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Pay period</label>
      <select class="form-input" id="nrSalaryPeriod">
        <option value="Monthly">Monthly</option>
        <option value="Annually">Annually</option>
        <option value="Hourly">Hourly</option>
      </select>
    </div>
    <div class="nr-salary-note"><i class="fas fa-info-circle"></i> You will not appear in salary searches by employers without a salary.</div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('nrSalaryModal')">Cancel</button>
      <button class="btn-primary" onclick="saveNrSalary()">Save</button>
    </div>
  </div>
</div>

<!-- Classification of Interest Modal -->
<div class="modal-overlay" id="nrClassModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Classification of interest</div>
      <button class="modal-close" onclick="closeModal('nrClassModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="form-group">
      <label class="form-label">Classification</label>
      <select class="form-input" id="nrClassSelect">
        <option value="">Select classification...</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Job Title</label>
      <select class="form-input" id="nrSubClassInput">
        <option value="">Select a classification first...</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn-secondary" onclick="closeModal('nrClassModal')">Cancel</button>
      <button class="btn-primary" onclick="saveNrClass()">Save</button>
    </div>
  </div>
</div>

<!-- Approachability Modal -->
<div class="modal-overlay" id="nrApproachModal">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title">Approachability</div>
      <button class="modal-close" onclick="closeModal('nrApproachModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="nr-toggle-row">
      <div class="nr-toggle-label">Show my approachability</div>
      <div class="nr-toggle-track" id="nrApproachToggle" onclick="toggleNrApproach()">
        <div class="nr-toggle-thumb"></div>
      </div>
    </div>
    <div class="nr-toggle-desc">If you choose to show your approachability and are active on AntCareers, employers will see a "May Be Approachable" label on your profile.</div>
    <div class="modal-footer">
      <button class="btn-primary" onclick="saveNrApproach()">Done</button>
    </div>
  </div>
</div>

<script>
  // ── STATE — seeded from PHP/DB ──
  const CSRF_TOKEN = '<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES); ?>';
  const profile = <?= $jsProfile ?>;
  if (!profile.certifications) profile.certifications = [];
  if (!profile.languages) profile.languages = [];
  if (!profile.nextRole) profile.nextRole = { availability:'', workTypes:[], locations:'', rightToWork:'', salary:'', salaryPeriod:'per month', classification:'', approachability:'' };

  // ── MODAL OPEN / CLOSE ──
  function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  }
  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });

  // ── PHOTO UPLOAD (avatar / banner) ──
  async function uploadPhoto(input, type) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { showToast('File too large (max 5MB)', 'fa-exclamation'); input.value=''; return; }
    const fd = new FormData();
    fd.append('photo', file);
    fd.append('type', type);
    fd.append('csrf_token', CSRF_TOKEN);
    try {
      const res = await fetch('upload_photo.php', { method:'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        if (type === 'avatar') {
          const el = document.getElementById('heroAvatar');
          el.style.background = `url('${data.url}') center/cover no-repeat`;
          el.style.fontSize = '0';
          showToast('Profile photo updated!', 'fa-check');
        } else {
          const el = document.getElementById('heroBanner');
          el.style.background = `url('${data.url}') center/cover no-repeat`;
          showToast('Banner updated!', 'fa-check');
        }
      } else {
        showToast(data.error || 'Upload failed', 'fa-exclamation');
      }
    } catch(e) {
      showToast('Upload failed', 'fa-exclamation');
    }
    input.value = '';
  }

  // ── CHECKLIST TOGGLE ──
  function toggleChecklist() {
    const box = document.getElementById('completenessChecklist');
    const chev = document.getElementById('checklistChevron');
    const open = box.style.display === 'none';
    box.style.display = open ? 'block' : 'none';
    chev.className = open ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    if (open) updateCompleteness(); // Always rebuild list when opening
  }

  // ── COMPLETENESS ──
  const CHECKS = [
    { key:'title',      label:'Job title / headline',     points:10, action:"openModal('editProfileModal')" },
    { key:'about',      label:'About Me bio',             points:10, action:"openModal('aboutModal')" },
    { key:'skills',     label:'At least one skill',       points:10, action:"openModal('skillModal')" },
    { key:'experience', label:'Work experience',          points:10, action:"openModal('expModal')" },
    { key:'education',  label:'Education history',        points:10, action:"openModal('eduModal')" },
    { key:'phone',      label:'Phone number',             points:5,  action:"openModal('editProfileModal')" },
    { key:'links',      label:'LinkedIn / GitHub / Portfolio', points:10, action:"openModal('linksModal')" },
  ];

  function checkDone(key) {
    if (key === 'title')      return !!profile.title;
    if (key === 'about')      return !!profile.about;
    if (key === 'skills')     return profile.skills.length > 0;
    if (key === 'experience') return profile.experience.length > 0;
    if (key === 'education')  return profile.education.length > 0;
    if (key === 'phone')      return !!profile.contact.phone;
    if (key === 'links')      return !!(profile.links.linkedin || profile.links.github || profile.links.portfolio);
    return false;
  }

  function updateCompleteness() {
    let score = 35;
    CHECKS.forEach(c => { if (checkDone(c.key)) score += c.points; });
    score = Math.min(score, 100);
    document.getElementById('completenessFill').style.width = score + '%';
    document.getElementById('completenessText').textContent = score + '%';

    // Rebuild checklist
    const box = document.getElementById('checklistItems');
    const missing = CHECKS.filter(c => !checkDone(c.key));
    const done    = CHECKS.filter(c =>  checkDone(c.key));

    let html = '';
    if (missing.length === 0) {
      html = `<div style="font-size:13px;color:#4CAF70;font-weight:600;"><i class="fas fa-check-circle"></i> Profile complete!</div>`;
    } else {
      missing.forEach(c => {
        html += `<div onclick="${c.action};toggleChecklist()" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 0;font-size:12px;color:var(--text-mid);font-weight:500;transition:0.15s;" onmouseover="this.style.color='var(--text-light)'" onmouseout="this.style.color='var(--text-mid)'">
          <i class="fas fa-circle-dot" style="color:var(--red-pale);font-size:10px;width:14px;"></i>
          <span>${c.label}</span>
          <span style="margin-left:auto;font-size:10px;color:var(--text-muted);font-weight:700;">+${c.points}%</span>
          <i class="fas fa-arrow-right" style="font-size:10px;color:var(--text-muted);"></i>
        </div>`;
      });
      if (done.length > 0) {
        html += `<div style="border-top:1px solid var(--soil-line);margin:6px 0;"></div>`;
        done.forEach(c => {
          html += `<div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12px;color:var(--text-muted);">
            <i class="fas fa-check-circle" style="color:#4CAF70;font-size:10px;width:14px;"></i>
            <span style="text-decoration:line-through;">${c.label}</span>
          </div>`;
        });
      }
    }
    box.innerHTML = html;
  }

  // ── SAVE TO DB (async) ──
  async function saveToDb() {
    const payload = {
      headline:             profile.title,
      city_name:            profile.city,
      country_name:         profile.country,
      experience_level:     profile.expLevel,
      bio:                  profile.about,
      phone:                profile.contact.phone,
      province_name:        profile.contact.province,
      desired_position:     profile.contact.availability,
      'skills[name][]':     profile.skills,
      experience:           profile.experience,
      education:            profile.education,
    };

    // Build FormData
    const fd = new FormData();
    fd.append('headline',         profile.title        || '');
    fd.append('city_name',        profile.city         || '');
    fd.append('country_name',     profile.country      || '');
    fd.append('experience_level', profile.expLevel     || '');
    fd.append('bio',              profile.about        || '');
    fd.append('phone',            profile.contact.phone     || '');
    fd.append('province_name',    profile.contact.province  || '');
    fd.append('desired_position', profile.contact.availability || '');
    fd.append('linkedin_url',  profile.links.linkedin  || '');
    fd.append('github_url',    profile.links.github    || '');
    fd.append('portfolio_url', profile.links.portfolio || '');
    fd.append('other_url',     profile.links.other     || '');

    profile.skills.forEach((s, i) => {
      fd.append(`skills[name][${i}]`,  s);
      fd.append(`skills[level][${i}]`, 'Intermediate');
    });

    profile.experience.forEach((e, i) => {
      fd.append(`experience[${i}][job_title]`,    e.title   || '');
      fd.append(`experience[${i}][company_name]`, e.company || '');
      fd.append(`experience[${i}][start_date]`,   e.start   || '');
      fd.append(`experience[${i}][end_date]`,     e.end !== 'Present' ? (e.end || '') : '');
      if (e.end === 'Present') fd.append(`experience[${i}][is_current]`, '1');
      fd.append(`experience[${i}][description]`,  e.desc    || '');
    });

    profile.education.forEach((e, i) => {
      fd.append(`education[college][entries][${i}][school_name]`,   e.school || '');
      fd.append(`education[college][entries][${i}][degree_course]`, e.degree || '');
      fd.append(`education[college][entries][${i}][start_year]`,    e.start  || '');
      fd.append(`education[college][entries][${i}][end_year]`,      e.end    || '');
    });

    profile.certifications.forEach((c, i) => {
      fd.append(`certifications[${i}][name]`,        c.name        || '');
      fd.append(`certifications[${i}][org]`,         c.org         || '');
      fd.append(`certifications[${i}][issue_date]`,  c.issueDate   || '');
      fd.append(`certifications[${i}][expiry_date]`, c.noExpiry ? '' : (c.expiryDate || ''));
      fd.append(`certifications[${i}][no_expiry]`,   c.noExpiry ? '1' : '0');
      fd.append(`certifications[${i}][desc]`,        c.desc        || '');
    });

    profile.languages.forEach((l, i) => {
      fd.append(`languages[${i}]`, l);
    });

    fd.append('nr_availability',    profile.nextRole.availability    || '');
    fd.append('nr_work_types',      profile.nextRole.workTypes.join(',') || '');
    fd.append('nr_locations',       profile.nextRole.locations       || '');
    fd.append('nr_right_to_work',   profile.nextRole.rightToWork     || '');
    fd.append('nr_salary',          profile.nextRole.salary          || '');
    fd.append('nr_salary_period',   profile.nextRole.salaryPeriod    || 'per month');
    fd.append('nr_classification',  profile.nextRole.classification  || '');
    fd.append('nr_approachability', profile.nextRole.approachability || '');

    try {
      const res = await fetch('update_profile.php', { method:'POST', body: fd });
      const data = await res.json().catch(() => null);
      if (data && data.ok) return true;
      console.error('DB save failed:', data?.error || res.status);
    } catch(e) {
      console.error('DB save error:', e);
    }
    return false;
  }

  // ── SAVE PROFILE ──
  async function saveProfile() {
    const firstName = document.getElementById('editFirstName').value.trim();
    const lastName  = document.getElementById('editLastName').value.trim();
    const fullName  = [firstName, lastName].filter(Boolean).join(' ');
    profile.title    = document.getElementById('editTitle').value.trim();
    profile.city     = document.getElementById('editLocation').value.trim();
    profile.expLevel = document.getElementById('editExpLevel').value;
    profile.contact.phone = (document.getElementById('editPhoneCountry').value + ' ' + document.getElementById('editPhone').value.trim()).trim();
    // Update hero
    if (fullName) document.querySelector('.hero-name').textContent = fullName;
    document.getElementById('heroTitle').textContent = profile.title || 'Add your job title or headline';
    const locDisplay = profile.city || 'Add location';
    document.getElementById('heroLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${locDisplay}`;
    if(profile.expLevel) document.getElementById('heroExperience').innerHTML = `<i class="fas fa-briefcase"></i> ${profile.expLevel}`;
    closeModal('editProfileModal');
    updateCompleteness();
    await saveToDb();
    showToast('Profile updated!', 'fa-check');
  }

  // Phone country code sync
  document.getElementById('editPhoneCountry')?.addEventListener('change', function() {
    document.getElementById('phoneCodeDisplay').textContent = this.value;
  });

  // ── SAVE ABOUT ──
  async function saveAbout() {
    profile.about = document.getElementById('aboutText').value.trim();
    const el = document.getElementById('aboutContent');
    if(profile.about) {
      el.innerHTML = `<p class="about-text">${profile.about.replace(/\n/g,'<br>')}</p>`;
    } else {
      el.innerHTML = `<div class="about-placeholder"><i class="fas fa-pen-fancy"></i>Write a short bio to let employers know who you are, what you're passionate about, and what you're looking for.</div>`;
    }
    closeModal('aboutModal');
    updateCompleteness();
    await saveToDb();
    showToast('About section saved!', 'fa-check');
  }

  // ── SKILLS ──
  async function saveSkill() {
    const val = document.getElementById('skillInput').value.trim();
    if(!val) return;
    if(profile.skills.includes(val)) { showToast('Skill already added!','fa-exclamation'); return; }
    profile.skills.push(val);
    renderSkills();
    document.getElementById('skillInput').value = '';
    closeModal('skillModal');
    updateCompleteness();
    await saveToDb();
    showToast(`"${val}" added!`, 'fa-check');
  }
  async function removeSkill(s) {
    profile.skills = profile.skills.filter(x => x !== s);
    renderSkills();
    updateCompleteness();
    await saveToDb();
    showToast(`"${s}" removed`, 'fa-times');
  }
  function renderSkills() {
    const g = document.getElementById('skillsGrid');
    if(profile.skills.length === 0) {
      g.innerHTML = '<div class="skills-placeholder">No skills added yet. Add skills to help employers find you.</div>';
    } else {
      g.innerHTML = profile.skills.map(s =>
        `<div class="skill-tag"><i class="fas fa-check" style="color:var(--red-pale);font-size:10px;"></i>${s}<span class="remove" onclick="removeSkill('${s.replace(/'/g,"\\'")}')"><i class="fas fa-times"></i></span></div>`
      ).join('');
    }
  }

  // ── EXPERIENCE ──
  async function saveExperience() {
    const title   = document.getElementById('expTitle').value.trim();
    const company = document.getElementById('expCompany').value.trim();
    if(!title || !company) { showToast('Please fill in title and company','fa-exclamation'); return; }
    const start   = document.getElementById('expStart').value;
    const current = document.getElementById('expCurrent').checked;
    const end     = current ? 'Present' : document.getElementById('expEnd').value;
    const desc    = document.getElementById('expDesc').value.trim();
    profile.experience.unshift({ title, company, start, end, desc });
    renderExperience();
    ['expTitle','expCompany','expStart','expEnd','expDesc'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('expCurrent').checked = false;
    document.getElementById('expEnd').disabled = false;
    closeModal('expModal');
    updateCompleteness();
    await saveToDb();
    showToast('Experience added!', 'fa-check');
  }
  function renderExperience() {
    const el = document.getElementById('expList');
    if(profile.experience.length === 0) {
      el.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fas fa-briefcase"></i></div><div class="empty-text">No work experience added yet.</div><button class="btn-add" style="margin:0 auto;" onclick="openModal('expModal')"><i class="fas fa-plus"></i> Add Experience</button></div>`;
    } else {
      el.innerHTML = profile.experience.map((e,i) => `
        <div class="timeline-item">
          <div class="timeline-logo"><i class="fas fa-building"></i></div>
          <div class="timeline-info">
            <div class="timeline-title">${e.title}</div>
            <div class="timeline-sub">${e.company}</div>
            <div class="timeline-date"><i class="fas fa-calendar"></i>${e.start || 'N/A'} — ${e.end || 'N/A'}</div>
            ${e.desc ? `<div class="timeline-desc">${e.desc}</div>` : ''}
            <div class="timeline-actions">
              <button class="timeline-action" onclick="deleteExp(${i})"><i class="fas fa-trash"></i> Remove</button>
            </div>
          </div>
        </div>
      `).join('');
    }
  }
  async function deleteExp(i) {
    profile.experience.splice(i,1);
    renderExperience();
    updateCompleteness();
    await saveToDb();
    showToast('Experience removed', 'fa-trash');
  }

  // ── EDUCATION ──
  async function saveEducation() {
    const school = document.getElementById('eduSchool').value.trim();
    const degree = document.getElementById('eduDegree').value.trim();
    if(!school || !degree) { showToast('Please fill in school and degree','fa-exclamation'); return; }
    const start = document.getElementById('eduStart').value;
    const end   = document.getElementById('eduEnd').value;
    profile.education.unshift({ school, degree, start, end });
    renderEducation();
    ['eduSchool','eduDegree','eduStart','eduEnd'].forEach(id => document.getElementById(id).value = '');
    closeModal('eduModal');
    updateCompleteness();
    await saveToDb();
    showToast('Education added!', 'fa-check');
  }
  function renderEducation() {
    const el = document.getElementById('eduList');
    if(profile.education.length === 0) {
      el.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fas fa-graduation-cap"></i></div><div class="empty-text">No education history added yet.</div><button class="btn-add" style="margin:0 auto;" onclick="openModal('eduModal')"><i class="fas fa-plus"></i> Add Education</button></div>`;
    } else {
      el.innerHTML = profile.education.map((e,i) => `
        <div class="timeline-item">
          <div class="timeline-logo"><i class="fas fa-university"></i></div>
          <div class="timeline-info">
            <div class="timeline-title">${e.school}</div>
            <div class="timeline-sub">${e.degree}</div>
            <div class="timeline-date"><i class="fas fa-calendar"></i>${e.start || 'N/A'} — ${e.end || 'N/A'}</div>
            <div class="timeline-actions">
              <button class="timeline-action" onclick="deleteEdu(${i})"><i class="fas fa-trash"></i> Remove</button>
            </div>
          </div>
        </div>
      `).join('');
    }
  }
  async function deleteEdu(i) {
    profile.education.splice(i,1);
    renderEducation();
    updateCompleteness();
    await saveToDb();
    showToast('Education removed', 'fa-trash');
  }

  // ── RESUME ──
  async function handleResumeUpload(input) {
    const file = input.files[0];
    if(!file) return;
    if(file.size > 5*1024*1024) { showToast('File too large. Max 5MB','fa-exclamation'); return; }
    const ext = file.name.split('.').pop().toLowerCase();
    if(!['pdf','doc','docx'].includes(ext)) { showToast('Please upload PDF or Word file','fa-exclamation'); return; }
    const size = file.size < 1024*1024 ? Math.round(file.size/1024)+'KB' : (file.size/1024/1024).toFixed(1)+'MB';

    // Show uploading state
    document.getElementById('resumeArea').innerHTML = `
      <div class="resume-file-item">
        <div class="resume-file-icon"><i class="fas fa-spinner fa-spin"></i></div>
        <div class="resume-file-info">
          <div class="resume-file-name">${file.name}</div>
          <div class="resume-file-meta">Uploading...</div>
        </div>
      </div>`;

    // Upload to server
    const fd = new FormData();
    fd.append('resume', file);
    fd.append('csrf_token', CSRF_TOKEN);
    try {
      const res = await fetch('upload_resume.php', {
        method: 'POST',
        body: fd,
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json().catch(() => ({ ok: false }));
      if (data.ok) {
        document.getElementById('resumeArea').innerHTML = `
          <div class="resume-file-item">
            <div class="resume-file-icon"><i class="fas fa-file-pdf"></i></div>
            <div class="resume-file-info">
              <div class="resume-file-name">${file.name}</div>
              <div class="resume-file-meta">${size} &middot; Saved to your profile</div>
            </div>
            <div class="resume-file-actions">
              <button class="timeline-action" onclick="document.getElementById('resumeInput').click()"><i class="fas fa-sync"></i> Replace</button>
            </div>
          </div>
          <input type="file" id="resumeInput" accept=".pdf,.doc,.docx" style="display:none" onchange="handleResumeUpload(this)">`;
        showToast('Resume saved!', 'fa-file-alt');
      } else {
        throw new Error('Upload failed');
      }
    } catch(e) {
      document.getElementById('resumeArea').innerHTML = `
        <div class="resume-upload-zone" onclick="document.getElementById('resumeInput').click()">
          <i class="fas fa-exclamation-triangle" style="color:var(--red-pale)"></i>
          <p>Upload failed. <strong>Try again</strong></p>
          <p style="font-size:11px;margin-top:6px;">Accepted formats: PDF, DOC, DOCX &middot; Max 5MB</p>
        </div>
        <input type="file" id="resumeInput" accept=".pdf,.doc,.docx" style="display:none" onchange="handleResumeUpload(this)">`;
      showToast('Upload failed. Please try again.', 'fa-exclamation');
    }
  }

  // ── CONTACT ──
  // ── LINKS ──
  async function saveLinks() {
    profile.links.linkedin  = document.getElementById('linkLinkedIn').value.trim();
    profile.links.github    = document.getElementById('linkGitHub').value.trim();
    profile.links.portfolio = document.getElementById('linkPortfolio').value.trim();
    profile.links.other     = document.getElementById('linkOther').value.trim();
    const g = document.getElementById('linksGrid');
    const items = [];
    if(profile.links.linkedin)  items.push(`<a class="link-item" href="${profile.links.linkedin}"  target="_blank"><i class="fab fa-linkedin"></i> LinkedIn</a>`);
    if(profile.links.github)    items.push(`<a class="link-item" href="${profile.links.github}"    target="_blank"><i class="fab fa-github"></i> GitHub</a>`);
    if(profile.links.portfolio) items.push(`<a class="link-item" href="${profile.links.portfolio}" target="_blank"><i class="fas fa-globe"></i> Portfolio</a>`);
    if(profile.links.other)     items.push(`<a class="link-item" href="${profile.links.other}"     target="_blank"><i class="fas fa-link"></i> Other Link</a>`);
    g.innerHTML = items.length ? items.join('') : '<div class="link-placeholder">No links added yet. Add your LinkedIn, GitHub, or portfolio.</div>';
    closeModal('linksModal');
    updateCompleteness();
    await saveToDb();
    showToast('Links saved!', 'fa-check');
  }

  // ── CERTIFICATIONS ──
  function openCertAdd() {
    document.getElementById('certEditIndex').value = '-1';
    document.getElementById('certModalTitle').textContent = 'Add Licence or Certification';
    ['certName','certOrg','certIssueDate','certExpiryDate','certDesc'].forEach(id => { const e=document.getElementById(id); if(e) e.value=''; });
    document.getElementById('certNoExpiry').checked = false;
    document.getElementById('certExpiryDate').disabled = false;
    openModal('certModal');
  }
  function openCertEdit(i) {
    const c = profile.certifications[i];
    if (!c) return;
    document.getElementById('certEditIndex').value = i;
    document.getElementById('certModalTitle').textContent = 'Edit Licence or Certification';
    document.getElementById('certName').value = c.name || '';
    document.getElementById('certOrg').value = c.org || '';
    document.getElementById('certIssueDate').value = c.issueDate || '';
    document.getElementById('certExpiryDate').value = c.expiryDate || '';
    document.getElementById('certNoExpiry').checked = c.noExpiry || false;
    document.getElementById('certExpiryDate').disabled = c.noExpiry || false;
    document.getElementById('certDesc').value = c.desc || '';
    openModal('certModal');
  }
  async function saveCert() {
    const name = document.getElementById('certName').value.trim();
    const org  = document.getElementById('certOrg').value.trim();
    if (!name) { showToast('Please enter a name','fa-exclamation'); return; }
    const cert = {
      name,
      org,
      issueDate:  document.getElementById('certIssueDate').value,
      expiryDate: document.getElementById('certExpiryDate').value,
      noExpiry:   document.getElementById('certNoExpiry').checked,
      desc:       document.getElementById('certDesc').value.trim()
    };
    const idx = parseInt(document.getElementById('certEditIndex').value);
    if (idx >= 0) {
      profile.certifications[idx] = cert;
    } else {
      profile.certifications.push(cert);
    }
    renderCerts();
    closeModal('certModal');
    updateCompleteness();
    await saveToDb();
    showToast(idx >= 0 ? 'Certification updated!' : 'Certification added!', 'fa-check');
  }
  async function deleteCert(i) {
    profile.certifications.splice(i, 1);
    renderCerts();
    updateCompleteness();
    await saveToDb();
    showToast('Certification removed', 'fa-trash');
  }
  function renderCerts() {
    const el = document.getElementById('certList');
    if (profile.certifications.length === 0) {
      el.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-certificate"></i></div><div class="empty-text">No licences or certifications added yet.</div><button class="btn-add" style="margin:0 auto;" onclick="openModal(\'certModal\')"><i class="fas fa-plus"></i> Add Licence or Certification</button></div>';
    } else {
      el.innerHTML = profile.certifications.map((c, i) => {
        const dates = [];
        if (c.issueDate) dates.push('Issued ' + c.issueDate);
        if (c.noExpiry) dates.push('No expiry');
        else if (c.expiryDate) dates.push('Expires ' + c.expiryDate);
        return `<div class="cert-item">
          <div class="cert-icon"><i class="fas fa-award"></i></div>
          <div class="cert-info">
            <div class="cert-name">${c.name}</div>
            ${c.org ? '<div class="cert-org">' + c.org + '</div>' : ''}
            ${dates.length ? '<div class="cert-dates"><i class="fas fa-calendar"></i> ' + dates.join(' · ') + '</div>' : ''}
            ${c.desc ? '<div class="cert-desc">' + c.desc + '</div>' : ''}
            <div class="cert-actions">
              <button class="timeline-action" onclick="openCertEdit(${i})"><i class="fas fa-pencil-alt"></i> Edit</button>
              <button class="timeline-action" onclick="deleteCert(${i})"><i class="fas fa-trash"></i> Remove</button>
            </div>
          </div>
        </div>`;
      }).join('');
    }
  }

  // ── LANGUAGES ──
  async function saveLang() {
    const val = document.getElementById('langInput').value.trim();
    if (!val) return;
    if (profile.languages.includes(val)) { showToast('Language already added!','fa-exclamation'); return; }
    profile.languages.push(val);
    renderLangs();
    document.getElementById('langInput').value = '';
    closeModal('langModal');
    updateCompleteness();
    await saveToDb();
    showToast(`"${val}" added!`, 'fa-check');
  }
  async function removeLang(lang) {
    profile.languages = profile.languages.filter(l => l !== lang);
    renderLangs();
    updateCompleteness();
    await saveToDb();
    showToast(`"${lang}" removed`, 'fa-times');
  }
  function renderLangs() {
    const el = document.getElementById('langList');
    if (profile.languages.length === 0) {
      el.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-globe-americas"></i></div><div class="empty-text">No languages added yet.</div><button class="btn-add" style="margin:0 auto;" onclick="openModal(\'langModal\')"><i class="fas fa-plus"></i> Add Language</button></div>';
    } else {
      el.innerHTML = '<div class="lang-chips">' + profile.languages.map(l =>
        `<div class="lang-chip"><i class="fas fa-language" style="color:var(--red-pale);font-size:12px;"></i>${l}<span class="remove" onclick="removeLang('${l.replace(/'/g,"\\'")}')" title="Remove"><i class="fas fa-times"></i></span></div>`
      ).join('') + '</div>';
    }
  }

  // ── ABOUT YOUR NEXT ROLE (individual modals) ──
  let _nrTempLocations = [];

  // Helper: update a nr-row's icon between + and pencil based on value
  function updateNrRowIcon(valueId, hasValue) {
    const el = document.getElementById(valueId);
    if (!el) return;
    const row = el.closest('.nr-row');
    if (!row) return;
    const btn = row.querySelector('.nr-edit-btn i');
    if (btn) btn.className = hasValue ? 'fas fa-pencil-alt' : 'fas fa-plus';
  }

  // ── Availability ──
  async function saveNrAvail() {
    const val = document.getElementById('nrAvailInput').value;
    profile.nextRole.availability = val || '';
    renderNextRole();
    closeModal('nrAvailModal');
    updateCompleteness();
    await saveToDb();
    showToast('Availability saved!', 'fa-check');
  }

  // ── Work Type ──
  async function saveNrWorkType() {
    const val = document.getElementById('nrWorkTypeInput').value;
    profile.nextRole.workTypes = val ? [val] : [];
    renderNextRole();
    closeModal('nrWorkTypeModal');
    updateCompleteness();
    await saveToDb();
    showToast('Work type saved!', 'fa-check');
  }

  // ── Locations ──
  function renderNrLocTags() {
    const container = document.getElementById('nrLocTags');
    const group = document.getElementById('nrLocListGroup');
    if (!_nrTempLocations.length) { group.style.display = 'none'; return; }
    group.style.display = '';
    container.innerHTML = _nrTempLocations.map((loc, i) =>
      `<div class="nr-loc-tag">${loc} <span class="remove-loc" onclick="removeNrLoc(${i})"><i class="fas fa-times"></i></span></div>`
    ).join('');
  }
  function addNrLocation() {
    const input = document.getElementById('nrLocInput');
    const val = input.value.trim();
    if (val && !_nrTempLocations.includes(val)) {
      _nrTempLocations.push(val);
      renderNrLocTags();
    }
    input.value = '';
    input.focus();
  }
  function removeNrLoc(i) {
    _nrTempLocations.splice(i, 1);
    renderNrLocTags();
  }
  async function saveNrLocations() {
    profile.nextRole.locations = _nrTempLocations.join(', ');
    renderNextRole();
    closeModal('nrLocModal');
    updateCompleteness();
    await saveToDb();
    showToast('Locations saved!', 'fa-check');
  }

  // ── Right to Work ──
  async function saveNrRight() {
    const country = document.getElementById('nrRtwCountry').value;
    const type = document.getElementById('nrRtwType').value;
    if (country && type) {
      profile.nextRole.rightToWork = country + ' — ' + type;
    } else if (country) {
      profile.nextRole.rightToWork = country;
    } else {
      profile.nextRole.rightToWork = type;
    }
    renderNextRole();
    closeModal('nrRightModal');
    updateCompleteness();
    await saveToDb();
    showToast('Right to work saved!', 'fa-check');
  }

  // ── Salary ──
  async function saveNrSalary() {
    const amount = document.getElementById('nrSalaryInput').value.trim();
    const period = document.getElementById('nrSalaryPeriod').value;
    profile.nextRole.salary = amount;
    profile.nextRole.salaryPeriod = period;
    renderNextRole();
    closeModal('nrSalaryModal');
    updateCompleteness();
    await saveToDb();
    showToast('Salary expectation saved!', 'fa-check');
  }

  // ── Classification ──
  const _jobTitlesTree = {
    'Accounting':['Accounts Officers / Clerks','Accounts Payable','Accounts Receivable / Credit Control','Analysis & Reporting','Assistant Accountants','Audit - External','Audit - Internal','Bookkeeping & Small Practice Accounting','Business Services & Corporate Advisory','Company Secretaries','Compliance & Risk','Cost Accounting','Financial Accounting & Reporting','Financial Managers & Controllers','Forensic Accounting & Investigation','Insolvency & Corporate Recovery','Inventory & Fixed Assets','Management','Management Accounting & Budgeting','Payroll','Strategy & Planning','Systems Accounting & IT Audit','Taxation','Treasury','Other'],
    'Administration & Office Support':['Administrative Assistants','Client & Sales Administration','Contracts Administration','Data Entry & Word Processing','Office Management','PA, EA & Secretarial','Receptionists','Records Management & Document Control','Other'],
    'Advertising, Arts & Media':['Agency Account Management','Art Direction','Editing & Publishing','Event Management','Journalism & Writing','Management','Media Strategy, Planning & Buying','Other'],
    'Banking & Financial Services':['Account & Relationship Management','Analysis & Reporting','Banking - Business','Banking - Corporate & Institutional','Banking - Retail / Branch','Client Services','Compliance & Risk','Corporate Finance & Investment Banking','Credit','Financial Planning','Funds Management','Management','Mortgages','Settlements','Other'],
    'Call Centre & Customer Service':['Collections','Customer Service - Call Centre','Customer Service - Customer Facing','Management & Support','Sales - Inbound','Sales - Outbound','Supervisors / Team Leaders','Other'],
    'CEO & General Management':['Board Appointments','CEO','COO & MD','General / Business Unit Manager','Other'],
    'Community Services & Development':['Aged & Disability Support','Child Welfare, Youth & Family Services','Community Development','Employment Services','Fundraising','Housing & Homelessness Services','Indigenous & Multicultural Services','Management','Volunteer Coordination & Support','Other'],
    'Construction':['Contracts Management','Estimating','Foreperson / Supervisors','Health, Safety & Environment','Management','Planning & Scheduling','Plant & Machinery Operators','Project Management','Quality Assurance & Control','Surveying','Other'],
    'Consulting & Strategy':['Analysts','Corporate Development','Environment & Sustainability Consulting','Management & Change Consulting','Policy','Strategy & Planning','Other'],
    'Design & Architecture':['Architectural Drafting','Architecture','Fashion Design','Graphic Design','Interior Design','Landscape Architecture','Management','Product Design','Urban Design & Planning','Other'],
    'Education & Training':['Childcare & Outside School Hours Care','Library Services & Information Management','Management - Schools','Management - Universities','Management - Vocational','Research & Fellowships','Student Services','Teaching - Early Childhood','Teaching - Primary','Teaching - Secondary','Teaching - Tertiary','Teaching - Vocational','Teaching Aides & Special Needs','Tutoring','Workplace Training & Assessment','Other'],
    'Engineering':['Aerospace Engineering','Automotive Engineering','Building Services Engineering','Chemical Engineering','Civil/Structural Engineering','Electrical/Electronic Engineering','Engineering Drafting','Environmental Engineering','Field Engineering','Industrial Engineering','Maintenance','Management','Materials Handling Engineering','Mechanical Engineering','Process Engineering','Project Engineering','Project Management','Supervisors','Systems Engineering','Water & Waste Engineering','Other'],
    'Farming, Animals & Conservation':['Agronomy & Farm Services','Conservation, Parks & Wildlife','Farm Labour','Farm Management','Fishing & Aquaculture','Horticulture','Veterinary Services & Animal Welfare','Winery & Viticulture','Other'],
    'Government & Defence':['Air Force','Army','Emergency Services','Government - Federal','Government - Local','Government - State','Navy','Police & Corrections','Other'],
    'Healthcare & Medical':['Ambulance/Paramedics','Chiropractic & Osteopathic','Clinical/Medical Research','Dental','Dieticians','Environmental Services','General Practitioners','Management','Medical Administration','Medical Imaging','Medical Specialists','Natural Therapies & Alternative Medicine','Nursing - A&E, Critical Care & ICU','Nursing - Aged Care','Nursing - Community, Maternal & Child Health','Nursing - Educators & Facilitators','Nursing - General Medical & Surgical','Nursing - High Acuity','Nursing - Management','Nursing - Midwifery, Neo-Natal, SCN & NICU','Nursing - Paediatric & PICU','Nursing - Psych, Forensic & Correctional Health','Nursing - Theatre & Recovery','Optical','Pathology','Pharmaceuticals & Medical Devices','Pharmacy','Physiotherapy, OT & Rehabilitation','Psychology, Counselling & Social Work','Residents & Registrars','Sales','Speech Therapy','Other'],
    'Hospitality & Tourism':['Airlines','Bar & Beverage Staff','Chefs/Cooks','Front Office & Guest Services','Gaming','Housekeeping','Kitchen & Sandwich Hands','Management','Reservations','Tour Guides','Travel Agents/Consultants','Waiting Staff','Other'],
    'Human Resources & Recruitment':['Consulting & Generalist HR','Industrial & Employee Relations','Management - Agency','Management - Internal','Occupational Health & Safety','Organisational Development','Recruitment - Agency','Recruitment - Internal','Remuneration & Benefits','Training & Development','Other'],
    'Information & Communication Technology':['Architects','Computer Operators','Consultants','Database Development & Administration','Developers/Programmers','Engineering - Hardware','Engineering - Network','Engineering - Software','Help Desk & IT Support','Management','Networks & Systems Administration','Product Management & Development','Program & Project Management','Sales - Pre & Post','Security','Software Quality Assurance','System Services & Support','Systems Analysis & Modelling','Team Leaders','Technical Writing','Telecommunications','Testing & Quality Assurance','Other'],
    'Insurance & Superannuation':['Actuarial','Assessment','Brokerage','Claims','Management','Risk Management','Superannuation','Underwriting','Workers\' Compensation','Other'],
    'Legal':['Banking & Financial Services Law','Construction Law','Corporate & Commercial Law','Criminal Law','Family Law','Generalists - In-house','Generalists - Law Firm','Industrial Relations & Employment Law','Insurance & Superannuation Law','Intellectual Property Law','Legal Secretaries','Litigation & Dispute Resolution','Management','Personal Injury Law','Property Law','Tax Law','Other'],
    'Manufacturing, Transport & Logistics':['Assembly & Process Work','Aviation Services','Couriers, Drivers & Postal Services','Fleet Management','Freight/Cargo Forwarding','Import/Export & Customs','Inventory & Stock Control','Machine Operators','Management','Methods & Quality Control','Operations','Production, Planning & Scheduling','Public Transport & Taxi Services','Purchasing, Procurement & Inventory','Rail Operations','Road Transport','Shipping','Warehouse, Storage & Distribution','Other'],
    'Marketing & Communications':['Brand Management','Digital & Search Marketing','Direct Marketing & CRM','Event Management','Internal Communications','Management','Market Research & Analysis','Marketing Assistants/Coordinators','Marketing Communications','Media Strategy, Planning & Buying','Product Management & Development','Public Relations & Corporate Affairs','Trade Marketing','Other'],
    'Mining, Resources & Energy':['Analysis & Reporting','Corporate Services','Engineering','Health, Safety & Environment','Management','Natural Resources & Water','Oil & Gas - Drilling','Oil & Gas - Exploration & Geoscience','Oil & Gas - Operations','Oil & Gas - Production & Refinement','Operations','Power Generation & Distribution','Project Management','Renewable Energy','Surveying','Other'],
    'Real Estate & Property':['Administration','Body Corporate & Facilities Management','Commercial Sales, Leasing & Property Mgmt','Management','Residential Leasing & Property Management','Residential Sales','Retail & Shopping Centre Management','Valuation','Other'],
    'Retail & Consumer Products':['Merchandisers','Management - Area/Multi-site','Management - Department/Assistant','Management - Store','Planning','Purchasing, Procurement & Inventory','Retail Assistants','Sales Representatives/Consultants','Visual Merchandising','Other'],
    'Sales':['Account & Relationship Management','Analysis & Reporting','Management','New Business Development','Sales Representatives/Consultants','Other'],
    'Science & Technology':['Biological & Biomedical Sciences','Biotechnology','Chemistry','Environmental, Earth & Geosciences','Food Technology & Safety','Laboratory & Technical Services','Materials Sciences','Mathematics, Statistics & Information Sciences','Modelling & Simulation','Physics','Other'],
    'Self Employment':['Self Employment'],
    'Sports & Recreation':['Coaching & Instruction','Fitness & Personal Training','Management','Other'],
    'Trades & Services':['Automotive Trades','Bakers & Pastry Cooks','Building Trades','Butchers','Caretakers & Handypersons','Cleaning Services','Electricians','Floristry','Gardening & Landscaping','Hair & Beauty Services','Labourers','Locksmiths','Maintenance & Handypersons','Management','Nannies & Babysitters','Painters & Sign Writers','Plumbers','Printing & Publishing Services','Security Services','Tailors & Dressmakers','Technicians','Upholstery & Textile Trades','Other']
  };

  // Populate classification dropdown from _jobTitlesTree keys
  (function() {
    const sel = document.getElementById('nrClassSelect');
    Object.keys(_jobTitlesTree).forEach(function(k) {
      const o = document.createElement('option');
      o.value = k; o.textContent = k;
      sel.appendChild(o);
    });
  })();

  document.getElementById('nrClassSelect').addEventListener('change', function() {
    const sel = document.getElementById('nrSubClassInput');
    sel.innerHTML = '<option value="">Any job title</option>';
    const titles = _jobTitlesTree[this.value] || [];
    titles.forEach(function(t) {
      const o = document.createElement('option');
      o.value = t; o.textContent = t;
      sel.appendChild(o);
    });
  });

  async function saveNrClass() {
    const cls = document.getElementById('nrClassSelect').value;
    const sub = document.getElementById('nrSubClassInput').value;
    profile.nextRole.classification = [cls, sub].filter(Boolean).join(' — ');
    renderNextRole();
    closeModal('nrClassModal');
    updateCompleteness();
    await saveToDb();
    showToast('Classification saved!', 'fa-check');
  }

  // ── Approachability ──
  let _nrApproachOn = false;
  function toggleNrApproach() {
    _nrApproachOn = !_nrApproachOn;
    const track = document.getElementById('nrApproachToggle');
    track.classList.toggle('on', _nrApproachOn);
  }
  async function saveNrApproach() {
    profile.nextRole.approachability = _nrApproachOn ? 'Shown' : 'Hidden';
    renderNextRole();
    closeModal('nrApproachModal');
    updateCompleteness();
    await saveToDb();
    showToast('Approachability saved!', 'fa-check');
  }

  function renderNextRole() {
    const nr = profile.nextRole;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (!el) return;
      if (val) { el.textContent = val; el.className = 'nr-value'; }
      else { el.textContent = 'Not specified'; el.className = 'nr-value placeholder'; }
      updateNrRowIcon(id, !!val);
    };
    set('nrAvailability', nr.availability);
    set('nrWorkTypes', nr.workTypes.length ? nr.workTypes.join(', ') : '');
    set('nrLocations', nr.locations);
    set('nrRightToWork', nr.rightToWork);
    set('nrSalary', nr.salary ? ('₱' + nr.salary + ' ' + nr.salaryPeriod) : '');
    set('nrClassification', nr.classification);
    set('nrApproachability', nr.approachability);
  }

  // ── INIT — render DB data on page load ──
  (function init() {
    try {
    // Render profile meta
    if (profile.title) {
      document.getElementById('heroTitle').textContent = profile.title;
    }
    if (profile.city || profile.country) {
      const loc = [profile.city, profile.country].filter(Boolean).join(', ');
      document.getElementById('heroLocation').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${loc}`;
    }
    if (profile.expLevel) {
      document.getElementById('heroExperience').innerHTML = `<i class="fas fa-briefcase"></i> ${profile.expLevel}`;
    }
    if (profile.about) {
      document.getElementById('aboutContent').innerHTML = `<p class="about-text">${profile.about.replace(/\n/g,'<br>')}</p>`;
    }
    if (profile.skills.length)     renderSkills();
    if (profile.experience.length) renderExperience();
    if (profile.education.length)  renderEducation();
    if (profile.certifications.length) renderCerts();
    if (profile.languages.length)  renderLangs();
    renderNextRole();

    // Contact
    if (profile.contact.phone) {
      const ph = document.getElementById('contactPhone');
      ph.className = 'contact-value';
      ph.innerHTML = `<i class="fas fa-phone"></i> ${profile.contact.phone}`;
    }
    if (profile.contact.city || profile.contact.province) {
      const loc = document.getElementById('contactLocation');
      const locStr = [profile.contact.city, profile.contact.province].filter(Boolean).join(', ');
      loc.className = 'contact-value';
      loc.innerHTML = `<i class="fas fa-map-marker-alt"></i> ${locStr}`;
    }
    if (profile.contact.availability) {
      const av = document.getElementById('contactAvailability');
      av.className = 'contact-value';
      av.innerHTML = `<i class="fas fa-calendar-check"></i> ${profile.contact.availability}`;
    }

    // Pre-fill modal inputs with existing data
    const editTitle = document.getElementById('editTitle');
    if(editTitle && profile.title) editTitle.value = profile.title;
    const editLocation = document.getElementById('editLocation');
    if(editLocation && profile.city) editLocation.value = profile.city;
    const editExpLevel = document.getElementById('editExpLevel');
    if(editExpLevel && profile.expLevel) editExpLevel.value = profile.expLevel;
    // Pre-fill phone
    if(profile.contact.phone) {
      const parts = profile.contact.phone.match(/^(\+\d+)\s+(.*)$/);
      if(parts) {
        const editPhoneCountry = document.getElementById('editPhoneCountry');
        const editPhone = document.getElementById('editPhone');
        const phoneCodeDisplay = document.getElementById('phoneCodeDisplay');
        if(editPhoneCountry) { editPhoneCountry.value = parts[1]; }
        if(editPhone)        { editPhone.value = parts[2]; }
        if(phoneCodeDisplay) { phoneCodeDisplay.textContent = parts[1]; }
      }
    }
    const aboutText = document.getElementById('aboutText');
    if(aboutText && profile.about) aboutText.value = profile.about;
    const linkLI = document.getElementById('linkLinkedIn');
    if(linkLI && profile.links.linkedin) linkLI.value = profile.links.linkedin;
    const linkGH = document.getElementById('linkGitHub');
    if(linkGH && profile.links.github) linkGH.value = profile.links.github;
    const linkPF = document.getElementById('linkPortfolio');
    if(linkPF && profile.links.portfolio) linkPF.value = profile.links.portfolio;
    const linkOT = document.getElementById('linkOther');
    if(linkOT && profile.links.other) linkOT.value = profile.links.other;

    // Pre-fill next role individual modals
    if (profile.nextRole.availability) {
      const nrAv = document.getElementById('nrAvailInput');
      if (nrAv) nrAv.value = profile.nextRole.availability;
    }
    const nrWt = document.getElementById('nrWorkTypeInput');
    if(nrWt && profile.nextRole.workTypes && profile.nextRole.workTypes.length) nrWt.value = profile.nextRole.workTypes[0];
    // Locations
    if (profile.nextRole.locations) {
      _nrTempLocations = profile.nextRole.locations.split(',').map(s => s.trim()).filter(Boolean);
      renderNrLocTags();
    }
    // Right to work
    if (profile.nextRole.rightToWork) {
      const parts = profile.nextRole.rightToWork.split(' — ');
      const nrRtwC = document.getElementById('nrRtwCountry');
      const nrRtwT = document.getElementById('nrRtwType');
      if (parts.length === 2) { if(nrRtwC) nrRtwC.value = parts[0]; if(nrRtwT) nrRtwT.value = parts[1]; }
      else { if(nrRtwT) nrRtwT.value = profile.nextRole.rightToWork; }
    }
    // Salary
    const nrSal = document.getElementById('nrSalaryInput');
    if(nrSal && profile.nextRole.salary) nrSal.value = profile.nextRole.salary;
    const nrSalP = document.getElementById('nrSalaryPeriod');
    if(nrSalP && profile.nextRole.salaryPeriod) nrSalP.value = profile.nextRole.salaryPeriod;
    // Classification
    if (profile.nextRole.classification) {
      const cparts = profile.nextRole.classification.split(' — ');
      const nrCls = document.getElementById('nrClassSelect');
      const nrSub = document.getElementById('nrSubClassInput');
      if (cparts.length >= 1 && nrCls) nrCls.value = cparts[0];
      if (cparts.length >= 2 && nrSub) nrSub.value = cparts[1];
    }
    // Approachability
    _nrApproachOn = profile.nextRole.approachability === 'Shown';
    const apTrack = document.getElementById('nrApproachToggle');
    if (apTrack) apTrack.classList.toggle('on', _nrApproachOn);

    updateCompleteness();
    } catch(e) {
      console.error('[AntCareers] init() error:', e);
      try { updateCompleteness(); } catch(_) {}
    }
  })();
</script>

</body>
</html>