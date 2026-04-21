<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/antcareers_login.php');
    exit;
}

$db            = getDB();
$currentUserId = (int)$_SESSION['user_id'];
$accountType   = strtolower((string)($_SESSION['account_type'] ?? ''));
$employerId    = isset($_GET['employer_id']) ? (int)$_GET['employer_id'] : 0;

/* ── REWROTE: fetch ALL company_profile fields ── */

if (!$employerId) {
    header('Location: ../seeker/antcareers_seekerCompany.php');
    exit;
}

// Auto-create company_follows table if it doesn't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS company_follows (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_user_id INT UNSIGNED NOT NULL,
        employer_user_id INT UNSIGNED NOT NULL,
        followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_follow (follower_user_id, employer_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $_) {}

// Fetch company profile — ALL fields from company_profiles
$company = null;
try {
    $stmt = $db->prepare("
        SELECT
            u.id AS employer_id,
            COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company_name,
            cp.logo_path,
            cp.cover_path,
            cp.industry,
            cp.company_size,
            cp.company_type,
            cp.founded_year,
            cp.tagline,
            cp.about AS description,
            cp.website,
            cp.contact_email,
            cp.contact_phone,
            cp.address_line,
            cp.city,
            cp.province,
            cp.country,
            cp.zip_code,
            CONCAT_WS(', ', NULLIF(cp.city,''), NULLIF(cp.province,''), NULLIF(cp.country,'')) AS location,
            cp.perks,
            cp.social_website,
            cp.social_linkedin,
            cp.social_facebook,
            cp.social_twitter,
            cp.social_instagram,
            cp.social_youtube,
            cp.is_verified,
            (SELECT COUNT(*) FROM company_follows WHERE employer_user_id = u.id) AS follower_count
        FROM users u
        LEFT JOIN company_profiles cp ON cp.user_id = u.id
        WHERE u.id = :eid AND u.account_type = 'employer'
        LIMIT 1
    ");
    $stmt->execute([':eid' => $employerId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log('[AntCareers] public_company_profile fetch: ' . $e->getMessage());
}

if (!$company) {
    header('Location: ../seeker/antcareers_seekerCompany.php');
    exit;
}

// Parse perks JSON
$perksArray = [];
if (!empty($company['perks'])) {
    $decoded = json_decode((string)$company['perks'], true);
    if (is_array($decoded)) $perksArray = $decoded;
}

// Resolve cover image: try cover_path, fall back to banner_url column if exists
$coverImage = $company['cover_path'] ?? '';
if ($coverImage && !str_starts_with($coverImage, '../') && !str_starts_with($coverImage, 'http')) $coverImage = '../' . $coverImage;

// Is the current seeker following this company?
$isFollowing = false;
if ($accountType === 'seeker') {
    try {
        $fStmt = $db->prepare("SELECT id FROM company_follows WHERE follower_user_id = :uid AND employer_user_id = :eid LIMIT 1");
        $fStmt->execute([':uid' => $currentUserId, ':eid' => $employerId]);
        $isFollowing = (bool)$fStmt->fetchColumn();
    } catch (\Throwable $_) {}
}

// Fetch company's active jobs
$companyJobs = [];
try {
    $jStmt = $db->prepare("
        SELECT j.id, j.title, j.location, j.job_type, j.setup AS work_setup,
               j.experience_level, j.salary_min, j.salary_max, j.salary_currency,
               j.description, j.skills_required, j.created_at
        FROM jobs j
        WHERE j.employer_id = :eid AND j.status = 'Active'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        ORDER BY j.created_at DESC
        LIMIT 20
    ");
    $jStmt->execute([':eid' => $employerId]);
    foreach ($jStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $salMin = (float)($r['salary_min'] ?? 0);
        $salMax = (float)($r['salary_max'] ?? 0);
        $cur    = currencySymbol($r['salary_currency'] ?? 'PHP');
        if ($salMin && $salMax)  $salary = $cur . number_format($salMin) . ' – ' . $cur . number_format($salMax);
        elseif ($salMin)         $salary = $cur . number_format($salMin) . '+';
        else                     $salary = 'Not disclosed';
        $tags = array_values(array_slice(array_filter(array_map('trim', explode(',', (string)($r['skills_required'] ?? '')))), 0, 5));
        $companyJobs[] = [
            'id'          => (int)$r['id'],
            'title'       => $r['title'],
            'location'    => $r['location'] ?? '',
            'jobType'     => $r['job_type'] ?? '',
            'workSetup'   => $r['work_setup'] ?? 'On-site',
            'experience'  => $r['experience_level'] ?? '',
            'salary'      => $salary,
            'tags'        => $tags,
            'postedDate'  => date('M j, Y', strtotime($r['created_at'])),
            'description' => $r['description'] ?? '',
        ];
    }
} catch (\Throwable $e) {
    // Fallback without post-migration columns
    try {
        $jStmt = $db->prepare("
            SELECT j.id, j.title, j.location, j.job_type, 'On-site' AS work_setup,
                   NULL AS experience_level, j.salary_min, j.salary_max, j.salary_currency,
                   j.description, NULL AS skills_required, j.created_at
            FROM jobs j
            WHERE j.employer_id = :eid AND j.status = 'Active'
            ORDER BY j.created_at DESC LIMIT 20
        ");
        $jStmt->execute([':eid' => $employerId]);
        foreach ($jStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $salMin = (float)($r['salary_min'] ?? 0);
            $salMax = (float)($r['salary_max'] ?? 0);
            $cur    = ($r['salary_currency'] ?? 'PHP') === 'PHP' ? '₱' : ($r['salary_currency'] ?? '');
            if ($salMin && $salMax)  $salary = $cur . number_format($salMin/1000,0) . 'k – ' . $cur . number_format($salMax/1000,0) . 'k';
            elseif ($salMin)         $salary = $cur . number_format($salMin/1000,0) . 'k+';
            else                     $salary = 'Not disclosed';
            $companyJobs[] = [
                'id'          => (int)$r['id'],
                'title'       => $r['title'],
                'location'    => $r['location'] ?? '',
                'jobType'     => $r['job_type'] ?? '',
                'workSetup'   => 'On-site',
                'experience'  => '',
                'salary'      => $salary,
                'tags'        => [],
                'postedDate'  => date('M j, Y', strtotime($r['created_at'])),
                'description' => $r['description'] ?? '',
            ];
        }
    } catch (\Throwable $_) {}
}

// Navbar variables for seeker navbar
if ($accountType === 'seeker') {
    $user      = getUser();
    $navActive = 'company';
    $fullName  = $user['fullName'];
    $firstName = $user['firstName'];
    $initials  = $user['initials'];
    $userEmail = $user['email'];
}

$jobsJson        = json_encode($companyJobs, JSON_HEX_TAG | JSON_HEX_AMP);
// Pre-load applied job IDs for this seeker
$appliedJobIds = [];
if ($accountType === 'seeker') {
    try {
        $aStmt = $db->prepare("
            SELECT a.job_id FROM applications a
            INNER JOIN jobs j ON j.id = a.job_id
            WHERE a.seeker_id = :uid AND j.employer_id = :eid
        ");
        $aStmt->execute([':uid' => $currentUserId, ':eid' => $employerId]);
        $appliedJobIds = array_map('intval', $aStmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (\Throwable $_) {}
}

$jobsJson          = json_encode($companyJobs, JSON_HEX_TAG | JSON_HEX_AMP);
$appliedJobIdsJson = json_encode($appliedJobIds);
$isFollowingJson   = json_encode($isFollowing);
$followerCount     = (int)($company['follower_count'] ?? 0);
$openJobCount      = count($companyJobs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= htmlspecialchars((string)$company['company_name']) ?> — AntCareers</title>
  <script>
    (function(){
      const p = new URLSearchParams(window.location.search).get('theme');
      const t = p || localStorage.getItem('ac-theme') || 'light';
      if (p) localStorage.setItem('ac-theme', p);
      if (t === 'light') document.documentElement.classList.add('theme-light');
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
      --amber:#D4943A; --green:#4CAF70;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); min-height:100vh; -webkit-font-smoothing:antialiased; overflow-x:hidden; }
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* PAGE */
    .page-shell { max-width:1100px; margin:0 auto; padding:28px 24px 80px; position:relative; z-index:1; }
    .back-link { display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:600; color:var(--text-muted); text-decoration:none; margin-bottom:20px; transition:color 0.18s; }
    .back-link:hover { color:var(--red-pale); }

    /* HERO */
    .cp-hero { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; overflow:hidden; margin-bottom:24px; box-shadow:0 4px 24px rgba(0,0,0,0.25); }
    .cp-banner { height:200px; background:linear-gradient(135deg,#3A0F0F 0%,#1C0808 50%,#2A0A1A 100%); position:relative; overflow:hidden; }
    .cp-banner::after { content:''; position:absolute; inset:0; background:repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(209,61,44,0.03) 20px,rgba(209,61,44,0.03) 21px); pointer-events:none; }
    .cp-banner img { width:100%; height:100%; object-fit:cover; display:block; position:relative; z-index:1; }
    .cp-hero-body { padding:0 32px 28px; }
    .cp-logo-row { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-top:-44px; margin-bottom:18px; flex-wrap:wrap; position:relative; z-index:2; }
    .cp-logo { width:88px; height:88px; border-radius:14px; border:4px solid var(--soil-card); background:var(--soil-hover); display:flex; align-items:center; justify-content:center; font-size:28px; font-weight:800; color:var(--red-pale); overflow:hidden; flex-shrink:0; box-shadow:0 4px 20px rgba(0,0,0,0.4); }
    .cp-logo img { width:100%; height:100%; object-fit:cover; border-radius:10px; }
    .cp-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding-bottom:4px; }
    .btn-follow { display:flex; align-items:center; gap:7px; padding:10px 22px; border-radius:8px; background:var(--red-vivid); border:1px solid transparent; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(209,61,44,0.3); }
    .btn-follow:hover:not(:disabled) { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 16px rgba(209,61,44,0.4); }
    .btn-follow.following { background:transparent; border-color:var(--soil-line); color:var(--text-mid); box-shadow:none; }
    .btn-follow.following:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .btn-follow:disabled { opacity:0.6; cursor:not-allowed; }
    .btn-website { display:flex; align-items:center; gap:6px; padding:10px 16px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:12px; font-weight:700; text-decoration:none; transition:0.18s; }
    .btn-website:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .cp-name { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text-light); margin-bottom:5px; line-height:1.2; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .cp-tagline { font-size:14px; color:var(--text-muted); margin-bottom:12px; font-weight:500; }
    .cp-verified { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#6ccf8a; background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.25); padding:3px 9px; border-radius:4px; }
    .cp-meta { display:flex; flex-wrap:wrap; gap:16px; margin-bottom:0; }
    .cp-meta-item { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--text-muted); }
    .cp-meta-item i { color:var(--red-pale); font-size:11px; }
    .cp-stats-row { display:flex; border-top:1px solid var(--soil-line); margin-top:18px; }
    .cp-stat { flex:1; text-align:center; padding:16px 8px; border-right:1px solid var(--soil-line); }
    .cp-stat:last-child { border-right:none; }
    .cp-stat-num { font-family:var(--font-display); font-size:22px; font-weight:700; color:var(--red-pale); line-height:1; }
    .cp-stat-num.small { font-size:14px; font-family:var(--font-body); font-weight:700; margin-top:2px; }
    .cp-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.07em; margin-top:4px; }

    /* TWO-COLUMN LAYOUT */
    .cp-layout { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
    .cp-main { display:flex; flex-direction:column; gap:16px; min-width:0; }
    .cp-sidebar { display:flex; flex-direction:column; gap:16px; position:sticky; top:82px; }

    /* CARDS */
    .cp-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; overflow:hidden; }
    .cp-card-head { padding:14px 20px 12px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; }
    .cp-card-title { font-size:11px; font-weight:700; color:var(--text-muted); letter-spacing:0.08em; text-transform:uppercase; display:flex; align-items:center; gap:7px; }
    .cp-card-title i { color:var(--red-bright); }
    .cp-card-badge { font-size:11px; font-weight:700; color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); padding:2px 9px; border-radius:10px; }
    .cp-card-body { padding:18px 20px; }
    .cp-about { font-size:14px; line-height:1.85; color:var(--text-mid); white-space:pre-wrap; }

    /* SIDEBAR INFO LIST */
    .cp-info-list { display:flex; flex-direction:column; gap:12px; }
    .cp-info-row { display:flex; align-items:flex-start; gap:10px; }
    .cp-info-icon { width:28px; height:28px; border-radius:6px; background:rgba(209,61,44,0.08); border:1px solid rgba(209,61,44,0.15); display:flex; align-items:center; justify-content:center; color:var(--red-pale); font-size:11px; flex-shrink:0; margin-top:1px; }
    .cp-info-lbl { font-size:10px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:2px; }
    .cp-info-val { font-size:13px; color:var(--text-mid); word-break:break-word; line-height:1.4; }
    .cp-info-val a { color:var(--red-pale); text-decoration:none; }
    .cp-info-val a:hover { text-decoration:underline; }

    /* SOCIAL */
    .cp-social-list { display:flex; flex-direction:column; gap:7px; }
    .cp-social-link { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; transition:0.18s; border:1px solid transparent; }
    .cp-social-link:hover { transform:translateX(3px); }
    .cp-social-link .si-icon { width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
    .cp-social-link.si-linkedin { background:rgba(10,102,194,0.08); color:#4A9EE5; border-color:rgba(10,102,194,0.15); }
    .cp-social-link.si-fb { background:rgba(24,119,242,0.08); color:#5BA0F5; border-color:rgba(24,119,242,0.15); }
    .cp-social-link.si-twitter { background:rgba(29,161,242,0.08); color:#5BBCF8; border-color:rgba(29,161,242,0.15); }
    .cp-social-link.si-ig { background:rgba(193,53,132,0.08); color:#D467A8; border-color:rgba(193,53,132,0.15); }
    .cp-social-link.si-yt { background:rgba(255,0,0,0.08); color:#FF6060; border-color:rgba(255,0,0,0.15); }
    .cp-social-link.si-web { background:rgba(209,61,44,0.08); color:var(--red-pale); border-color:rgba(209,61,44,0.15); }

    /* PERKS */
    .cp-perks-grid { display:flex; flex-wrap:wrap; gap:7px; }
    .cp-perk-chip { display:inline-flex; align-items:center; gap:6px; background:rgba(209,61,44,0.06); border:1px solid rgba(209,61,44,0.15); color:var(--text-mid); padding:6px 11px; border-radius:20px; font-size:12px; font-weight:600; }
    .cp-perk-chip i { font-size:10px; color:var(--red-pale); }

    /* JOB ROWS */
    .job-row { display:flex; align-items:center; gap:14px; padding:13px 20px; border-bottom:1px solid var(--soil-line); flex-wrap:wrap; cursor:pointer; transition:background 0.15s; margin:0 -20px; }
    .job-row:hover { background:var(--soil-hover); }
    .job-row:last-child { border-bottom:none; }
    .jr-icon { width:40px; height:40px; border-radius:10px; background:rgba(209,61,44,0.08); border:1px solid rgba(209,61,44,0.15); display:flex; align-items:center; justify-content:center; font-size:16px; color:var(--red-pale); flex-shrink:0; }
    .jr-main { flex:1; min-width:0; }
    .jr-title { font-size:14px; font-weight:700; color:var(--text-light); margin-bottom:3px; }
    .jr-meta-row { display:flex; flex-wrap:wrap; gap:10px; }
    .jr-meta-row span { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:4px; }
    .jr-meta-row i { font-size:10px; color:var(--red-pale); }
    .jr-tags { display:flex; flex-wrap:wrap; gap:5px; margin-top:7px; }
    .chip { font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); }
    .jr-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
    .jr-salary { font-size:13px; font-weight:700; color:var(--amber); white-space:nowrap; }
    .btn-view { padding:7px 14px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; transition:background 0.15s; white-space:nowrap; }
    .btn-view:hover { background:var(--red-bright); }
    .btn-view.applied { background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.3); color:#6ccf8a; cursor:default; font-size:11px; }

    /* EMPTY STATE */
    .no-jobs { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13px; }
    .no-jobs i { font-size:32px; margin-bottom:12px; display:block; color:var(--soil-line); }

    /* JOB DETAIL MODAL */
    .jdm-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(8px); z-index:500; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; visibility:hidden; transition:all 0.2s; }
    .jdm-overlay.open { opacity:1; visibility:visible; }
    .jdm-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; width:100%; max-width:600px; max-height:88vh; display:flex; flex-direction:column; transform:translateY(16px); transition:transform 0.25s; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,0.6); }
    .jdm-overlay.open .jdm-box { transform:translateY(0); }
    .jdm-scroll { padding:24px; overflow-y:auto; flex:1; }
    .jdm-scroll::-webkit-scrollbar { width:4px; }
    .jdm-scroll::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:2px; }
    .jdm-footer { padding:14px 22px; border-top:1px solid var(--soil-line); display:flex; gap:10px; flex-shrink:0; background:var(--soil-card); }
    .jdm-close-row { display:flex; justify-content:flex-end; margin-bottom:16px; }
    .jdm-close { width:32px; height:32px; border-radius:50%; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; transition:0.15s; }
    .jdm-close:hover { color:var(--text-light); }
    .jdm-header { display:flex; align-items:flex-start; gap:14px; margin-bottom:16px; }
    .jdm-icon { width:46px; height:46px; border-radius:10px; background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); display:flex; align-items:center; justify-content:center; font-size:19px; color:var(--red-pale); flex-shrink:0; }
    .jdm-title { font-size:18px; font-weight:700; color:var(--text-light); line-height:1.25; margin-bottom:3px; }
    .jdm-company { font-size:13px; color:var(--text-muted); font-weight:500; }
    .jdm-badges { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
    .jdm-badge { font-size:11px; font-weight:600; padding:5px 10px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); display:flex; align-items:center; gap:5px; }
    .jdm-badge.salary { background:rgba(212,148,58,0.1); border-color:rgba(212,148,58,0.25); color:var(--amber); }
    .jdm-badge i { font-size:10px; color:var(--red-pale); }
    .jdm-badge.salary i { color:var(--amber); }
    .jdm-desc { font-size:13px; line-height:1.8; color:var(--text-mid); margin-bottom:16px; white-space:pre-wrap; }
    .jdm-skills { display:flex; flex-wrap:wrap; gap:6px; }
    .jdm-apply { flex:1; padding:12px; border-radius:9px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:background 0.15s; }
    .jdm-apply:hover:not(:disabled) { background:var(--red-bright); }
    .jdm-apply:disabled { opacity:0.85; cursor:default; }
    .jdm-cover-wrap { display:none; padding:0 22px 14px; flex-shrink:0; }
    .jdm-cover-wrap.open { display:block; }
    .jdm-cover-label { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:7px; }
    .jdm-cover-ta { width:100%; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:9px; padding:11px 13px; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:vertical; min-height:90px; outline:none; line-height:1.6; }
    .jdm-cover-ta:focus { border-color:rgba(209,61,44,0.5); box-shadow:0 0 0 3px rgba(209,61,44,0.08); }

    /* TOAST — handled by includes/toast.php */

    /* LIGHT THEME */
    html.theme-light body, body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber:#B8620A;
    }
    body.light .cp-name { color:#1A0A09; }
    body.light .cp-tagline { color:#7A5555; }
    body.light .jr-title { color:#1A0A09; }
    body.light .jdm-title { color:#1A0A09; }
    body.light .back-link { color:#7A5555; }
    body.light .cp-info-val { color:#4A2828; }
    body.light .cp-perk-chip { background:rgba(209,61,44,0.06); color:#4A2828; }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; }
    body.light .jdm-cover-ta { background:#FEF0EE; border-color:#E0CECA; color:#1A0A09; }
    body.light .glow-orb { display:none; }
    body.light .cp-hero { border-color:#E0CECA; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
    body.light .cp-card { border-color:#E0CECA; }
    body.light .cp-card-head { border-bottom-color:#E0CECA; }
    body.light .cp-stats-row { border-top-color:#E0CECA; }
    body.light .cp-stat { border-right-color:#E0CECA; }
    body.light .btn-follow.following { border-color:#E0CECA; color:#4A2828; }
    body.light .btn-website { border-color:#E0CECA; color:#4A2828; }
    body.light .jdm-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .jdm-badge { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .jdm-desc { color:#4A2828; }
    body.light .jdm-footer { background:#FFFFFF; border-top-color:#E0CECA; }
    body.light .job-row:hover { background:#FEF0EE; }
    body.light .job-row { border-bottom-color:#E0CECA; }
    body.light .jr-icon { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.15); }

    @media(max-width:860px) {
      .cp-layout { grid-template-columns:1fr; }
      .cp-sidebar { position:static; }
    }
    @media(max-width:640px) {
      .cp-hero-body { padding:0 16px 20px; }
      .cp-logo-row { flex-direction:column; align-items:flex-start; }
      .cp-actions { width:100%; }
      .btn-follow { flex:1; justify-content:center; }
      .page-shell { padding:16px 14px 60px; }
      .cp-banner { height:140px; }
      .job-row { padding:12px 16px; margin:0 -16px; }
    }
  </style>
</head>
<body>

<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g stroke="#C0392B" stroke-width="1.5" fill="none" opacity="0.6">
      <path d="M0 200 Q200 180 350 240 Q500 300 600 260 Q750 210 900 280 Q1050 350 1200 300 Q1320 260 1440 280"/>
      <path d="M0 450 Q150 430 300 490 Q500 560 650 510 Q800 460 950 530 Q1100 600 1300 550 Q1380 530 1440 540"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->
<?php if ($accountType === 'seeker'): ?>
<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>
<?php else: ?>
<nav style="position:sticky;top:0;z-index:400;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid rgba(209,61,44,0.35);padding:0 24px;height:64px;display:flex;align-items:center;gap:14px;">
  <a href="../index.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
    <div style="width:34px;height:34px;background:var(--red-vivid);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;">🐜</div>
    <span style="font-family:var(--font-display);font-weight:700;font-size:19px;color:var(--text-light);">Ant<span style="color:var(--red-bright);">Careers</span></span>
  </a>
  <a href="javascript:history.back()" style="font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;margin-left:10px;padding:6px 10px;border-radius:6px;transition:0.18s;" onmouseover="this.style.color='var(--text-light)'" onmouseout="this.style.color='var(--text-muted)'"><i class="fas fa-arrow-left"></i> Go Back</a>
  <div style="margin-left:auto;">
    <button id="themeToggle" style="width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:0.2s;">
      <i class="fas fa-sun" id="themeIcon"></i>
    </button>
  </div>
</nav>
<script>
(function(){
  function applyTheme(t){
    var isLight = t==='light';
    document.body.classList.toggle('light',isLight);
    document.documentElement.classList.toggle('theme-light',isLight);
    var icon=document.querySelector('#themeToggle i');
    if(icon) icon.className=isLight?'fas fa-sun':'fas fa-moon';
    localStorage.setItem('ac-theme',t);
  }
  var p=new URLSearchParams(window.location.search).get('theme');
  var s=localStorage.getItem('ac-theme')||'light';
  var t=p||s;
  if(p) localStorage.setItem('ac-theme',p);
  applyTheme(t);
  var btn=document.getElementById('themeToggle');
  if(btn) btn.addEventListener('click',function(){applyTheme(document.body.classList.contains('light')?'dark':'light');});
})();
</script>
<?php endif; ?>

<div class="page-shell">

  <!-- Back link -->
  <a class="back-link" href="antcareers_seekerCompany.php">
    <i class="fas fa-arrow-left"></i> All Companies
  </a>

  <!-- COMPANY HERO CARD -->
  <div class="cp-hero">
    <div class="cp-banner">
      <?php if (!empty($coverImage)): ?>
        <img src="<?= htmlspecialchars((string)$coverImage, ENT_QUOTES) ?>" alt="Banner">
      <?php else: ?>
        <svg width="100%" height="100%" viewBox="0 0 1100 200" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#3A0A0A"/><stop offset="100%" stop-color="#1A0505"/>
            </linearGradient>
          </defs>
          <rect width="1100" height="200" fill="url(#bg)"/>
          <circle cx="200" cy="100" r="220" fill="rgba(209,61,44,0.06)"/>
          <circle cx="900" cy="100" r="180" fill="rgba(209,61,44,0.04)"/>
          <circle cx="550" cy="200" r="120" fill="rgba(209,61,44,0.03)"/>
        </svg>
      <?php endif; ?>
    </div>

    <div class="cp-hero-body">
      <div class="cp-logo-row">
        <div class="cp-logo">
          <?php if (!empty($company['logo_path'])): ?>
          <?php
            $logoSrc = (string)$company['logo_path'];
            if ($logoSrc && !str_starts_with($logoSrc, '../') && !str_starts_with($logoSrc, 'http')) $logoSrc = '../' . $logoSrc;
          ?>
            <img src="<?= htmlspecialchars($logoSrc, ENT_QUOTES) ?>"
                 alt="<?= htmlspecialchars((string)$company['company_name'], ENT_QUOTES) ?>"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
              <?= htmlspecialchars(mb_strtoupper(mb_substr((string)$company['company_name'], 0, 2)), ENT_QUOTES) ?>
            </span>
          <?php else: ?>
            <?= htmlspecialchars(mb_strtoupper(mb_substr((string)$company['company_name'], 0, 2)), ENT_QUOTES) ?>
          <?php endif; ?>
        </div>

        <div class="cp-actions">
          <?php if ($accountType === 'seeker'): ?>
          <button class="btn-follow <?= $isFollowing ? 'following' : '' ?>" id="followBtn" onclick="toggleFollow()">
            <i class="fas fa-<?= $isFollowing ? 'check' : 'plus' ?>"></i>
            <span id="followBtnText"><?= $isFollowing ? 'Following' : 'Follow' ?></span>
          </button>
          <?php endif; ?>
          <?php if (!empty($company['website'])): ?>
          <a class="btn-website"
             href="<?= htmlspecialchars((string)$company['website'], ENT_QUOTES) ?>"
             target="_blank" rel="noopener">
            <i class="fas fa-globe"></i> Website
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="cp-name">
        <?= htmlspecialchars((string)$company['company_name']) ?>
        <?php if (!empty($company['is_verified'])): ?>
          <span class="cp-verified"><i class="fas fa-check-circle"></i> Verified</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($company['tagline'])): ?>
      <div class="cp-tagline"><?= htmlspecialchars((string)$company['tagline']) ?></div>
      <?php endif; ?>

      <div class="cp-meta">
        <?php if (!empty($company['industry'])): ?>
        <div class="cp-meta-item"><i class="fas fa-tag"></i> <?= htmlspecialchars((string)$company['industry']) ?></div>
        <?php endif; ?>
        <?php if (!empty($company['location'])): ?>
        <div class="cp-meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars((string)$company['location']) ?></div>
        <?php endif; ?>
        <?php if (!empty($company['company_size'])): ?>
        <div class="cp-meta-item"><i class="fas fa-users"></i> <?= htmlspecialchars((string)$company['company_size']) ?></div>
        <?php endif; ?>
      </div>

      <div class="cp-stats-row">
        <div class="cp-stat">
          <div class="cp-stat-num" id="followerCountEl"><?= $followerCount ?></div>
          <div class="cp-stat-lbl">Followers</div>
        </div>
        <div class="cp-stat">
          <div class="cp-stat-num"><?= $openJobCount ?></div>
          <div class="cp-stat-lbl">Open Roles</div>
        </div>
        <?php if (!empty($company['company_size'])): ?>
        <div class="cp-stat">
          <div class="cp-stat-num small"><?= htmlspecialchars((string)$company['company_size']) ?></div>
          <div class="cp-stat-lbl">Team Size</div>
        </div>
        <?php endif; ?>
        <?php if (!empty($company['company_type'])): ?>
        <div class="cp-stat">
          <div class="cp-stat-num small"><?= htmlspecialchars((string)$company['company_type']) ?></div>
          <div class="cp-stat-lbl">Type</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- TWO-COLUMN LAYOUT -->
  <?php
    $hasContact = !empty($company['contact_email']) || !empty($company['contact_phone']) || !empty($company['website']);
    $hasAddress = !empty($company['address_line']) || !empty($company['city']) || !empty($company['country']);
    $hasSocial  = !empty($company['social_linkedin']) || !empty($company['social_facebook']) || !empty($company['social_twitter']) || !empty($company['social_instagram']) || !empty($company['social_youtube']) || !empty($company['social_website']);
    $hasSidebarInfo = !empty($company['company_type']) || !empty($company['founded_year']) || $hasContact || $hasAddress;
  ?>
  <div class="cp-layout">
    <div class="cp-main">

      <?php if (!empty($company['description'])): ?>
      <div class="cp-card">
        <div class="cp-card-head">
          <div class="cp-card-title"><i class="fas fa-align-left"></i> About</div>
        </div>
        <div class="cp-card-body">
          <div class="cp-about"><?= htmlspecialchars((string)$company['description']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="cp-card">
        <div class="cp-card-head">
          <div class="cp-card-title"><i class="fas fa-briefcase"></i> Open Positions</div>
          <span class="cp-card-badge"><?= $openJobCount ?> role<?= $openJobCount !== 1 ? 's' : '' ?></span>
        </div>
        <div class="cp-card-body" id="jobsContainer">
          <?php if ($openJobCount === 0): ?>
          <div class="no-jobs">
            <i class="fas fa-briefcase"></i>
            No open positions right now.<br>Check back later!
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /cp-main -->

    <div class="cp-sidebar">

      <?php if ($hasSidebarInfo): ?>
      <div class="cp-card">
        <div class="cp-card-head">
          <div class="cp-card-title"><i class="fas fa-info-circle"></i> Company Info</div>
        </div>
        <div class="cp-card-body">
          <div class="cp-info-list">
            <?php if (!empty($company['company_type'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-building"></i></div>
              <div><div class="cp-info-lbl">Type</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['company_type']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['founded_year'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-calendar-alt"></i></div>
              <div><div class="cp-info-lbl">Founded</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['founded_year']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['contact_email'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-envelope"></i></div>
              <div><div class="cp-info-lbl">Email</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['contact_email']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['contact_phone'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-phone"></i></div>
              <div><div class="cp-info-lbl">Phone</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['contact_phone']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['website'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-globe"></i></div>
              <div><div class="cp-info-lbl">Website</div><div class="cp-info-val"><a href="<?= htmlspecialchars((string)$company['website'], ENT_QUOTES) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string)$company['website']) ?></a></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['address_line'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div><div class="cp-info-lbl">Address</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['address_line']) ?><?php if (!empty($company['location'])): ?><br><span style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars((string)$company['location']) ?></span><?php endif; ?></div></div>
            </div>
            <?php elseif (!empty($company['location'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-map-marker-alt"></i></div>
              <div><div class="cp-info-lbl">Location</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['location']) ?></div></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($company['zip_code'])): ?>
            <div class="cp-info-row">
              <div class="cp-info-icon"><i class="fas fa-mail-bulk"></i></div>
              <div><div class="cp-info-lbl">ZIP / Postal</div><div class="cp-info-val"><?= htmlspecialchars((string)$company['zip_code']) ?></div></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($hasSocial): ?>
      <div class="cp-card">
        <div class="cp-card-head">
          <div class="cp-card-title"><i class="fas fa-share-alt"></i> Online Presence</div>
        </div>
        <div class="cp-card-body">
          <div class="cp-social-list">
            <?php if (!empty($company['social_linkedin'])): ?>
            <a class="cp-social-link si-linkedin" href="<?= htmlspecialchars((string)$company['social_linkedin'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fab fa-linkedin-in"></i></div> LinkedIn
            </a>
            <?php endif; ?>
            <?php if (!empty($company['social_facebook'])): ?>
            <a class="cp-social-link si-fb" href="<?= htmlspecialchars((string)$company['social_facebook'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fab fa-facebook-f"></i></div> Facebook
            </a>
            <?php endif; ?>
            <?php if (!empty($company['social_twitter'])): ?>
            <a class="cp-social-link si-twitter" href="<?= htmlspecialchars((string)$company['social_twitter'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fab fa-twitter"></i></div> Twitter / X
            </a>
            <?php endif; ?>
            <?php if (!empty($company['social_instagram'])): ?>
            <a class="cp-social-link si-ig" href="<?= htmlspecialchars((string)$company['social_instagram'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fab fa-instagram"></i></div> Instagram
            </a>
            <?php endif; ?>
            <?php if (!empty($company['social_youtube'])): ?>
            <a class="cp-social-link si-yt" href="<?= htmlspecialchars((string)$company['social_youtube'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fab fa-youtube"></i></div> YouTube
            </a>
            <?php endif; ?>
            <?php if (!empty($company['social_website'])): ?>
            <a class="cp-social-link si-web" href="<?= htmlspecialchars((string)$company['social_website'], ENT_QUOTES) ?>" target="_blank" rel="noopener">
              <div class="si-icon"><i class="fas fa-globe"></i></div> Website
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($perksArray)): ?>
      <div class="cp-card">
        <div class="cp-card-head">
          <div class="cp-card-title"><i class="fas fa-heart"></i> Perks &amp; Benefits</div>
        </div>
        <div class="cp-card-body">
          <div class="cp-perks-grid">
            <?php
            $perkIcons = ['Remote Work'=>'fa-laptop-house','HMO / Health Insurance'=>'fa-heartbeat','Learning & Development'=>'fa-graduation-cap','Paid Time Off'=>'fa-umbrella-beach','Gym / Wellness'=>'fa-dumbbell','Parental Leave'=>'fa-baby','Free Snacks / Meals'=>'fa-coffee','Transportation Allowance'=>'fa-car','Stock Options / Equity'=>'fa-chart-line','Competitive Salary'=>'fa-handshake','Game Room / Recreation'=>'fa-gamepad','International Exposure'=>'fa-globe-asia'];
            foreach ($perksArray as $perk):
                $icon = $perkIcons[$perk] ?? 'fa-check-circle';
            ?>
            <div class="cp-perk-chip"><i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($perk) ?></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /cp-sidebar -->
  </div><!-- /cp-layout -->
</div><!-- /page-shell -->

<!-- JOB DETAIL MODAL -->
<div class="jdm-overlay" id="jdmOverlay">
  <div class="jdm-box">
    <div class="jdm-scroll" id="jdmBody"></div>
    <div class="jdm-cover-wrap" id="jdmCoverWrap">
      <div class="jdm-cover-label">Cover Letter <span style="color:var(--soil-line);font-weight:400;">(optional)</span></div>
      <textarea class="jdm-cover-ta" id="jdmCoverLetter" placeholder="Briefly introduce yourself..."></textarea>
    </div>
    <div class="jdm-footer" id="jdmFooter"></div>
  </div>
</div>

<script>
  // ── DATA ──
  const cpJobs        = <?= $jobsJson ?>;
  let   isFollowing   = <?= $isFollowingJson ?>;
  const employerId    = <?= (int)$employerId ?>;
  const isSeeker      = <?= $accountType === 'seeker' ? 'true' : 'false' ?>;
  const _applyDone    = Object.fromEntries((<?= $appliedJobIdsJson ?>).map(id => [id, true]));

  // ── HELPERS ──
  function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function jobIcon(t) {
    const m = {'dev':'fa-code','software':'fa-code','engineer':'fa-cogs','it':'fa-server','design':'fa-palette','market':'fa-chart-line','sales':'fa-handshake','finance':'fa-coins','account':'fa-coins','hr':'fa-users','support':'fa-headset','nurse':'fa-heartbeat','health':'fa-heartbeat','teach':'fa-chalkboard-teacher','driver':'fa-truck'};
    const k = String(t||'').toLowerCase();
    for (const [k2,v] of Object.entries(m)) if (k.includes(k2)) return v;
    return 'fa-briefcase';
  }
  // ── RENDER JOBS ──
  function renderJobs() {
    const c = document.getElementById('jobsContainer');
    if (!cpJobs.length) return;
    c.innerHTML = cpJobs.map((j,i) => {
      const tags = (j.tags||[]).slice(0,4).map(t => '<span class="chip">' + esc(t) + '</span>').join('');
      return '<div class="job-row" style="animation-delay:' + (i*0.03) + 's;">' +
        '<div class="jr-icon"><i class="fas ' + jobIcon(j.jobType || j.title) + '"></i></div>' +
        '<div class="jr-main">' +
          '<div class="jr-title">' + esc(j.title) + '</div>' +
          '<div class="jr-meta-row">' +
            (j.location ? '<span><i class="fas fa-map-marker-alt"></i>' + esc(j.location) + '</span>' : '') +
            (j.jobType  ? '<span><i class="fas fa-briefcase"></i>' + esc(j.jobType) + '</span>' : '') +
            (j.workSetup? '<span><i class="fas fa-laptop-house"></i>' + esc(j.workSetup) + '</span>' : '') +
            (j.experience? '<span><i class="fas fa-layer-group"></i>' + esc(j.experience) + '</span>' : '') +
          '</div>' +
          (tags ? '<div class="jr-tags">' + tags + '</div>' : '') +
        '</div>' +
        '<div class="jr-right">' +
          '<div class="jr-salary">' + esc(j.salary) + '</div>' +
          (isSeeker ? '<button class="btn-view' + (_applyDone[j.id] ? ' applied' : '') + '"' + (_applyDone[j.id] ? ' disabled' : ' onclick="openJobDetail(' + j.id + ')"') + '>' + (_applyDone[j.id] ? '<i class="fas fa-check"></i> Applied' : '<i class="fas fa-eye"></i> View') + '</button>' : '') +
        '</div>' +
      '</div>';
    }).join('');
  }

  // ── JOB DETAIL MODAL ──
  let _currentJobId = 0;

  function openJobDetail(id) {
    const j = cpJobs.find(x => x.id === id);
    if (!j) return;
    _currentJobId = id;
    const tags = (j.tags||[]).map(t => '<span class="chip">' + esc(t) + '</span>').join('');
    document.getElementById('jdmBody').innerHTML =
      '<div class="jdm-close-row"><button class="jdm-close" onclick="closeJobDetail()"><i class="fas fa-times"></i></button></div>' +
      '<div class="jdm-header">' +
        '<div class="jdm-icon"><i class="fas ' + jobIcon(j.jobType || j.title) + '"></i></div>' +
        '<div style="min-width:0;"><div class="jdm-title">' + esc(j.title) + '</div>' +
          '<div style="font-size:12px;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars((string)$company['company_name']) ?></div></div>' +
      '</div>' +
      '<div class="jdm-badges">' +
        (j.location  ? '<span class="jdm-badge"><i class="fas fa-map-marker-alt"></i>' + esc(j.location) + '</span>' : '') +
        (j.jobType   ? '<span class="jdm-badge"><i class="fas fa-briefcase"></i>' + esc(j.jobType) + '</span>' : '') +
        (j.workSetup ? '<span class="jdm-badge"><i class="fas fa-laptop-house"></i>' + esc(j.workSetup) + '</span>' : '') +
        (j.experience? '<span class="jdm-badge"><i class="fas fa-layer-group"></i>' + esc(j.experience) + '</span>' : '') +
        '<span class="jdm-badge salary"><i class="fas fa-money-bill-wave"></i>' + esc(j.salary) + '</span>' +
        '<span class="jdm-badge" style="color:var(--text-muted);"><i class="fas fa-clock"></i>' + esc(j.postedDate) + '</span>' +
      '</div>' +
      (j.description ? '<div class="jdm-desc">' + esc(j.description) + '</div>' : '') +
      (tags ? '<div class="jdm-skills">' + tags + '</div>' : '');
    const coverWrap = document.getElementById('jdmCoverWrap');
    const coverTa   = document.getElementById('jdmCoverLetter');
    const footer    = document.getElementById('jdmFooter');
    if (isSeeker) {
      if (_applyDone[id]) {
        coverWrap.classList.remove('open');
        footer.innerHTML = '<button class="jdm-apply" style="background:var(--green,#4CAF70);cursor:default;" disabled><i class="fas fa-check"></i> Already Applied</button>';
      } else {
        coverTa.value = '';
        coverWrap.classList.add('open');
        footer.innerHTML =
          '<button class="jdm-apply" id="jdmApplyBtn" onclick="submitApply(' + id + ')"><i class="fas fa-paper-plane"></i> Apply Now</button>';
      }
    } else {
      coverWrap.classList.remove('open');
      footer.innerHTML = '';
    }
    document.getElementById('jdmOverlay').classList.add('open');
  }

  // ── DIRECT APPLY ──
  async function submitApply(jobId) {
    const btn = document.getElementById('jdmApplyBtn');
    const cl  = (document.getElementById('jdmCoverLetter').value || '').trim();
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    try {
      const fd = new FormData();
      fd.append('job_id', jobId);
      if (cl) fd.append('cover_letter', cl);
      fd.append('csrf_token', '<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>');
      const res  = await fetch('apply_job.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.success) {
        _applyDone[jobId] = true;
        document.getElementById('jdmCoverWrap').classList.remove('open');
        btn.innerHTML = '<i class="fas fa-check"></i> Application Submitted!';
        btn.style.background = 'var(--green,#4CAF70)';
        showToast('Application submitted!', 'fa-check-circle');
        renderJobs(); // refresh list to show Applied badge on the job row
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Apply Now';
        showToast(data.message || 'Could not submit application.', 'fa-exclamation');
      }
    } catch {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Apply Now';
      showToast('Network error — try again.', 'fa-exclamation');
    }
  }

  function closeJobDetail() { document.getElementById('jdmOverlay').classList.remove('open'); }
  document.getElementById('jdmOverlay').addEventListener('click', e => {
    if (e.target === document.getElementById('jdmOverlay')) closeJobDetail();
  });

  // ── FOLLOW / UNFOLLOW ──
  async function toggleFollow() {
    if (!isSeeker) return;
    const btn  = document.getElementById('followBtn');
    const txt  = document.getElementById('followBtnText');
    const icon = btn.querySelector('i');
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('employer_id', employerId);
      const res  = await fetch('follow_company.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.ok) {
        isFollowing = data.following;
        btn.classList.toggle('following', isFollowing);
        icon.className = 'fas fa-' + (isFollowing ? 'check' : 'plus');
        txt.textContent  = isFollowing ? 'Following' : 'Follow';
        const countEl = document.getElementById('followerCountEl');
        if (countEl && typeof data.count === 'number') countEl.textContent = data.count;
        showToast(isFollowing ? 'Now following this company!' : 'Unfollowed.', isFollowing ? 'fa-heart' : 'fa-heart-broken');
      } else {
        showToast(data.error || 'Could not update follow status.', 'fa-exclamation');
      }
    } catch {
      showToast('Network error — try again.', 'fa-exclamation');
    }
    btn.disabled = false;
  }

  // Theme is handled by seeker_navbar.php shared script (no duplicate handler needed)

  // ── INIT ──
  renderJobs();
</script>
</body>
</html>