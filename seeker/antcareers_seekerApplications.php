<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('seeker');
$user = getUser();
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'applications';

$db       = getDB();
$seekerId = (int)$_SESSION['user_id'];

// ── Fetch all applications for this seeker ────────────────────────────────────
// Uses try/catch so the page never 500s if a table doesn't exist yet.
$rows       = [];
$interviews = [];
$savedCount = 0;
$unreadMsg  = 0;

try {
    // Check if company_profiles table exists; fall back gracefully if not
    $hasCompanyProfiles = false;
    try {
        $db->query("SELECT 1 FROM company_profiles LIMIT 1");
        $hasCompanyProfiles = true;
    } catch (PDOException $e) { /* table not created yet */ }

    $companyCol = $hasCompanyProfiles
        ? "COALESCE(cp.company_name, u.full_name, u.company_name, 'Unknown Company')"
        : "COALESCE(u.company_name, u.full_name, 'Unknown Company')";
    $companyJoin = $hasCompanyProfiles
        ? "LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id"
        : "";

    $stmt = $db->prepare("
        SELECT
            a.id,
            a.status,
            a.applied_at,
            j.title          AS job_title,
            j.location       AS job_location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.salary_currency,
            j.id             AS job_id,
            {$companyCol}    AS company
        FROM applications a
        JOIN jobs  j ON j.id = a.job_id
        JOIN users u ON u.id = j.employer_id
        {$companyJoin}
        WHERE a.seeker_id = :sid
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([':sid' => $seekerId]);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[AntCareers] applications fetch error: ' . $e->getMessage());
    // $rows stays [] — page will show empty state
}

try {
    // Auto-migrate new interview columns if missing
    try { $db->query("SELECT venue_name FROM interview_schedules LIMIT 0"); }
    catch (PDOException $__) {
        $db->exec("ALTER TABLE interview_schedules ADD COLUMN venue_name VARCHAR(300) DEFAULT NULL, ADD COLUMN full_address VARCHAR(500) DEFAULT NULL, ADD COLUMN map_link VARCHAR(500) DEFAULT NULL, ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL, ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL");
    }
    $iStmt = $db->prepare("
        SELECT application_id, scheduled_at, interview_type, meeting_link, location, notes,
               venue_name, full_address, map_link, phone_number, contact_person
        FROM interview_schedules
        WHERE seeker_id = :sid AND status = 'Scheduled'
        ORDER BY scheduled_at ASC
    ");
    $iStmt->execute([':sid' => $seekerId]);
    foreach ($iStmt->fetchAll() as $iv) {
        $interviews[(int)$iv['application_id']] = $iv;
    }
} catch (PDOException $e) {
    // interview_schedules may not exist yet — silently skip
}

try {
    $svStmt = $db->prepare("SELECT COUNT(*) FROM saved_jobs WHERE user_id = :uid");
    $svStmt->execute([':uid' => $seekerId]);
    $savedCount = (int)$svStmt->fetchColumn();
} catch (PDOException $e) { /* table may not exist */ }

try {
    $msgStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = :uid AND is_read = 0");
    $msgStmt->execute([':uid' => $seekerId]);
    $unreadMsg = (int)$msgStmt->fetchColumn();
} catch (PDOException $e) { /* table may not exist */ }

// ── Build the JS-ready array ──────────────────────────────────────────────────
$applications = [];
foreach ($rows as $r) {
    $statusMap = [
        'pending'    => 'pending',
        'reviewed'   => 'reviewed',
        'shortlisted'=> 'shortlist',
        'rejected'   => 'rejected',
        'hired'      => 'hired',
    ];
    $status   = strtolower((string)$r['status']);
    $jsStatus = $statusMap[$status] ?? 'pending';
    $appId    = (int)$r['id'];

    // If scheduled interview exists, show interview status
    if (isset($interviews[$appId])) {
        $jsStatus = 'interview';
    }

    // Salary string
    if ($r['salary_min'] && $r['salary_max']) {
        $cur    = $r['salary_currency'] === 'PHP' ? '₱' : $r['salary_currency'];
        $salary = $cur . number_format((float)$r['salary_min'] / 1000, 0) . 'k – '
                . $cur . number_format((float)$r['salary_max'] / 1000, 0) . 'k';
    } elseif ($r['salary_min']) {
        $cur    = $r['salary_currency'] === 'PHP' ? '₱' : $r['salary_currency'];
        $salary = $cur . number_format((float)$r['salary_min'] / 1000, 0) . 'k+';
    } else {
        $salary = 'Salary not disclosed';
    }

    $ivDetails = null;
    if (isset($interviews[$appId])) {
        $iv = $interviews[$appId];
        $ivDetails = [
            'type'          => $iv['interview_type'],
            'date'          => date('M j, Y g:i A', strtotime($iv['scheduled_at'])),
            'link'          => $iv['meeting_link'] ?? '',
            'location'      => $iv['location'] ?? '',
            'notes'         => $iv['notes'] ?? '',
            'venueName'     => $iv['venue_name'] ?? '',
            'fullAddress'   => $iv['full_address'] ?? '',
            'mapLink'       => $iv['map_link'] ?? '',
            'phoneNumber'   => $iv['phone_number'] ?? '',
            'contactPerson' => $iv['contact_person'] ?? '',
        ];
    }

    $applications[] = [
        'id'         => $appId,
        'title'      => $r['job_title'],
        'company'    => $r['company'],
        'location'   => $r['job_location'] ?? 'Not specified',
        'type'       => $r['job_type'],
        'salary'     => $salary,
        'appliedDate'=> date('M j, Y', strtotime($r['applied_at'])),
        'status'     => $jsStatus,
        'jobId'      => (int)$r['job_id'],
        'interview'  => $ivDetails,
    ];
}

// ── Counts ────────────────────────────────────────────────────────────────────
$totalApps      = count($applications);
$activeApps     = count(array_filter($applications, fn($a) => $a['status'] !== 'rejected'));
$interviewCount = count(array_filter($applications, fn($a) => $a['status'] === 'interview'));
$rejectedCount  = count(array_filter($applications, fn($a) => $a['status'] === 'rejected'));

$appsJson = json_encode($applications, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — My Applications</title>
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
      --amber:#D4943A; --green:#4CAF70;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); min-height:100vh; -webkit-font-smoothing:antialiased; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }

    /* LAYOUT */
    .main-wrap { max-width:1380px; margin:0 auto; padding:28px 24px 60px; position:relative; z-index:1; }
    .content-layout { display:grid; grid-template-columns:1fr; gap:20px; align-items:start; }

    /* SIDEBAR */
    .sidebar { position:sticky; top:72px; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .sidebar-head { padding:16px 18px 12px; border-bottom:1px solid var(--soil-line); }
    .sidebar-title { font-size:12px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:7px; letter-spacing:0.07em; text-transform:uppercase; }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }
    .sidebar-profile { padding:16px 16px 14px; border-bottom:1px solid var(--soil-line); }
    .sp-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; margin-bottom:10px; }
    .sp-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .sp-role { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; letter-spacing:0.05em; }
    .sidebar-stats { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sb-stat { background:var(--soil-hover); border-radius:7px; padding:10px 12px; }
    .sb-stat-num { font-size:18px; font-weight:800; color:#F5F0EE; }
    .sb-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; letter-spacing:0.05em; text-transform:uppercase; margin-top:2px; }
    .sb-nav-item { display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; border:none; background:none; font-family:var(--font-body); width:100%; text-align:left; border-bottom:1px solid var(--soil-line); text-decoration:none; }
    .sb-nav-item:last-child { border-bottom:none; }
    .sb-nav-item:hover { color:#F5F0EE; background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .sb-nav-item i { width:16px; text-align:center; font-size:12px; color:var(--red-bright); }
    .sb-badge { margin-left:auto; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; }
    .sb-badge.green { background:var(--green); }
    .sb-badge.amber { background:var(--amber); color:#1A0A09; }
    .sb-browse-wrap { padding:12px 14px; border-top:1px solid var(--soil-line); }
    .sb-browse { display:flex; align-items:center; justify-content:center; gap:8px; padding:11px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; width:100%; }
    .sb-browse:hover { background:var(--red-bright); transform:translateY(-1px); }

    /* PAGE HEADER */
    .page-header { margin-bottom:20px; }
    .page-title { font-family:var(--font-display); font-size:24px; font-weight:700; color:#F5F0EE; }
    .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }

    /* TABS */
    .tabs { display:flex; gap:4px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; margin-bottom:18px; }
    .tab { flex:1; padding:9px 12px; border-radius:7px; border:none; background:transparent; font-family:var(--font-body); font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:0.18s; display:flex; align-items:center; justify-content:center; gap:7px; }
    .tab:hover { color:#F5F0EE; background:var(--soil-hover); }
    .tab.active { background:var(--red-vivid); color:#fff; box-shadow:0 2px 10px rgba(209,61,44,0.35); }
    .tab-count { background:rgba(255,255,255,0.2); border-radius:10px; padding:1px 7px; font-size:10px; font-weight:700; }
    .tab.active .tab-count { background:rgba(255,255,255,0.25); }
    .tab:not(.active) .tab-count { background:var(--soil-hover); color:var(--text-muted); }

    /* APPLICATION CARD */
    .app-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px 20px; margin-bottom:12px; display:flex; gap:16px; align-items:flex-start; transition:0.2s; position:relative; }
    .app-card:hover { border-color:rgba(209,61,44,0.35); background:var(--soil-hover); }
    .app-icon { width:46px; height:46px; border-radius:9px; background:rgba(209,61,44,0.12); display:flex; align-items:center; justify-content:center; font-size:18px; color:var(--red-pale); flex-shrink:0; }
    .app-info { flex:1; min-width:0; }
    .app-title { font-size:15px; font-weight:700; color:#F5F0EE; }
    .app-company { font-size:13px; color:var(--red-pale); font-weight:600; margin-top:2px; }
    .app-meta { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
    .app-meta-item { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-muted); }
    .app-meta-item i { font-size:11px; color:var(--red-pale); }
    .app-right { display:flex; flex-direction:column; align-items:flex-end; gap:10px; flex-shrink:0; }

    /* Skeleton */
    @keyframes shimmer { 0%{background-position:-600px 0} 100%{background-position:600px 0} }
    .skel { border-radius:6px; background:linear-gradient(90deg,var(--soil-hover) 25%,var(--soil-line) 50%,var(--soil-hover) 75%); background-size:600px 100%; animation:shimmer 1.4s infinite linear; }
    .skel-icon { width:46px; height:46px; border-radius:9px; flex-shrink:0; }
    .skel-line { height:13px; margin-bottom:8px; }
    .skel-short { width:40%; }
    .skel-med { width:65%; }
    .skel-long { width:85%; }
    .skel-badge { width:90px; height:24px; border-radius:20px; }
    .skel-date { width:70px; height:11px; border-radius:4px; margin-top:4px; }
    .skeleton-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px 20px; margin-bottom:12px; display:flex; gap:16px; align-items:flex-start; opacity:0.7; }

    /* STATUS BADGES */
    .status-badge { padding:4px 12px; border-radius:99px; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; white-space:nowrap; }
    .status-pending   { background:rgba(212,148,58,0.12);  color:var(--amber); border:1px solid rgba(212,148,58,0.25); }
    .status-reviewed  { background:rgba(100,140,255,0.12); color:#7AA4FF; border:1px solid rgba(100,140,255,0.25); }
    .status-shortlist { background:rgba(76,175,112,0.12);  color:var(--green); border:1px solid rgba(76,175,112,0.25); }
    .status-rejected  { background:rgba(224,80,80,0.12);   color:#E05050; border:1px solid rgba(224,80,80,0.25); }
    .status-interview { background:rgba(150,80,224,0.12);  color:#B07AFF; border:1px solid rgba(150,80,224,0.25); }
    .status-hired     { background:rgba(76,175,112,0.18);  color:#4CAF70; border:1px solid rgba(76,175,112,0.4); }
    .app-date { font-size:11px; color:var(--text-muted); }

    /* ACTION BUTTONS */
    .app-actions { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
    .btn-app { padding:6px 14px; border-radius:6px; font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); }
    .btn-app:hover { border-color:var(--red-vivid); color:var(--red-pale); background:var(--soil-hover); }
    .btn-app.danger:hover { border-color:#E05050; color:#E05050; }
    .btn-app.interview-btn { border-color:rgba(150,80,224,0.4); color:#B07AFF; }
    .btn-app.interview-btn:hover { background:rgba(150,80,224,0.08); }

    /* TIMELINE */
    .timeline-strip { display:flex; gap:0; margin-top:12px; position:relative; }
    .timeline-strip::before { content:''; position:absolute; top:11px; left:11px; right:11px; height:2px; background:var(--soil-line); z-index:0; }
    .ts-step { display:flex; flex-direction:column; align-items:center; gap:5px; flex:1; position:relative; z-index:1; }
    .ts-dot { width:22px; height:22px; border-radius:50%; border:2px solid var(--soil-line); background:var(--soil-dark); display:flex; align-items:center; justify-content:center; font-size:9px; transition:0.2s; }
    .ts-dot.done     { background:var(--green);  border-color:var(--green);  color:#fff; }
    .ts-dot.current  { background:var(--amber);  border-color:var(--amber);  color:#1A0A09; }
    .ts-dot.rejected { background:#E05050;        border-color:#E05050;        color:#fff; }
    .ts-label { font-size:10px; color:var(--text-muted); font-weight:600; text-align:center; }
    .ts-label.done     { color:var(--green); }
    .ts-label.current  { color:var(--amber); }
    .ts-label.rejected { color:#E05050; }

    /* INTERVIEW BANNER */
    .interview-banner { margin-top:12px; background:rgba(150,80,224,0.08); border:1px solid rgba(150,80,224,0.2); border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:10px; }
    .interview-banner i { color:#B07AFF; font-size:14px; flex-shrink:0; }
    .interview-banner-text { font-size:12px; color:#B07AFF; font-weight:600; }
    .interview-banner-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }

    /* EMPTY STATE */
    .empty-state { text-align:center; padding:60px 20px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; }
    .empty-icon { font-size:40px; color:var(--soil-line); margin-bottom:14px; }
    .empty-title { font-size:16px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .empty-sub { font-size:13px; color:var(--text-muted); margin-bottom:20px; }
    .btn-primary { padding:10px 22px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; }
    .btn-primary:hover { background:var(--red-bright); }

    /* WITHDRAW MODAL */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(6px); z-index:500; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; visibility:hidden; transition:all 0.2s; }
    .modal-overlay.open { opacity:1; visibility:visible; }
    .modal { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; width:100%; max-width:400px; transform:translateY(10px); transition:all 0.22s; }
    .modal-overlay.open .modal { transform:translateY(0); }
    .modal-title { font-size:17px; font-weight:700; color:#F5F0EE; margin-bottom:10px; }
    .modal-sub { font-size:13px; color:var(--text-muted); margin-bottom:22px; line-height:1.6; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; }
    .btn-cancel { padding:9px 18px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; }
    .btn-danger { padding:9px 18px; border-radius:7px; background:#C0392B; border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; }
    .btn-danger:hover { background:#E05050; }

    /* TOAST */
    @keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
    .toast { position:fixed; bottom:24px; right:24px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:13px 18px; font-size:13px; font-weight:600; color:#F5F0EE; display:flex; align-items:center; gap:10px; z-index:900; animation:toastIn 0.25s ease; box-shadow:0 8px 32px rgba(0,0,0,0.4); }
    .toast i { color:var(--red-bright); }

    /* LIGHT THEME */
    body.light { background:#F5EDEC; color:#1A0A09; }
    body.light .glow-orb { display:none; }
    body.light .sidebar-card, body.light .tabs, body.light .app-card, body.light .empty-state, body.light .modal { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sidebar-title, body.light .sp-name, body.light .page-title, body.light .app-title, body.light .modal-title, body.light .empty-title { color:#1A0A09; }
    body.light .sb-stat { background:#F0E4E2; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .sb-nav-item:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .sb-nav-item.active { color:var(--red-mid); }
    body.light .tab { color:#6A4040; }
    body.light .tab:hover { color:#1A0A09; background:#F0E4E2; }
    body.light .app-card:hover { background:#FFF5F4; }
    body.light .app-icon { background:rgba(184,53,37,0.08); }
    body.light .ts-dot { background:#F5EDEC; }
    body.light .toast { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }

    .anim { opacity:0; transform:translateY(14px); animation:fadeUp 0.42s cubic-bezier(0.4,0,0.2,1) forwards; }
    .anim-d1 { animation-delay:0.05s; } .anim-d2 { animation-delay:0.12s; }
    @keyframes fadeUp { to { opacity:1; transform:none; } }
    @media(max-width:700px) { .app-card { flex-wrap:wrap; } .app-right { flex-direction:row; align-items:center; width:100%; justify-content:space-between; } }
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

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<div class="main-wrap">
  <div class="content-layout">

    <!-- MAIN CONTENT -->
    <div class="anim anim-d2">
      <div class="page-header">
        <div class="page-title">My Applications</div>
        <div class="page-sub">Track the status of all your job applications in one place.</div>
      </div>

      <!-- TABS -->
      <div class="tabs">
        <button class="tab active" id="tabAll"       onclick="filterApps('all')"><i class="fas fa-list"></i> All <span class="tab-count" id="countAll"><?= $totalApps ?></span></button>
        <button class="tab"        id="tabActive"    onclick="filterApps('active')"><i class="fas fa-clock"></i> Active <span class="tab-count" id="countActive"><?= $activeApps ?></span></button>
        <button class="tab"        id="tabInterview" onclick="filterApps('interview')"><i class="fas fa-calendar-check"></i> Interview <span class="tab-count" id="countInterview"><?= $interviewCount ?></span></button>
        <button class="tab"        id="tabRejected"  onclick="filterApps('rejected')"><i class="fas fa-times-circle"></i> Rejected <span class="tab-count" id="countRejected"><?= $rejectedCount ?></span></button>
      </div>

      <!-- APPLICATION LIST -->
      <div id="appList">
        <?php if (empty($applications)): ?>
          <!-- Show skeleton briefly, then empty state via JS -->
        <?php endif; ?>
        <div class="skeleton-card"><div class="skel skel-icon"></div><div style="flex:1;min-width:0;"><div class="skel skel-line skel-med"></div><div class="skel skel-line skel-short"></div><div class="skel skel-line skel-long" style="margin-top:10px;"></div><div class="skel skel-line skel-med"></div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;"><div class="skel skel-badge"></div><div class="skel skel-date"></div></div></div>
        <div class="skeleton-card"><div class="skel skel-icon"></div><div style="flex:1;min-width:0;"><div class="skel skel-line skel-long"></div><div class="skel skel-line skel-short"></div><div class="skel skel-line skel-med" style="margin-top:10px;"></div><div class="skel skel-line skel-long"></div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;"><div class="skel skel-badge"></div><div class="skel skel-date"></div></div></div>
        <div class="skeleton-card"><div class="skel skel-icon"></div><div style="flex:1;min-width:0;"><div class="skel skel-line skel-med"></div><div class="skel skel-line skel-short"></div><div class="skel skel-line skel-long" style="margin-top:10px;"></div><div class="skel skel-line skel-short"></div></div><div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;"><div class="skel skel-badge"></div><div class="skel skel-date"></div></div></div>
      </div>
    </div>

  </div>
</div>

<!-- WITHDRAW MODAL -->
<div class="modal-overlay" id="withdrawModal">
  <div class="modal">
    <div class="modal-title">Withdraw Application?</div>
    <div class="modal-sub">Are you sure you want to withdraw your application for <strong id="withdrawJobTitle"></strong>? This action cannot be undone.</div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeWithdraw()">Cancel</button>
      <button class="btn-danger" id="withdrawBtn" onclick="confirmWithdraw()"><i class="fas fa-times"></i> Withdraw</button>
    </div>
  </div>
</div>

<!-- INTERVIEW DETAILS MODAL -->
<div class="modal-overlay" id="interviewModal">
  <div class="modal">
    <div class="modal-title"><i class="fas fa-calendar-check" style="color:#B07AFF;margin-right:8px;"></i>Interview Details</div>
    <div id="interviewModalBody" style="font-size:13px;color:var(--text-mid);line-height:1.8;margin-bottom:20px;"></div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="document.getElementById('interviewModal').classList.remove('open')">Close</button>
    </div>
  </div>
</div>

<script>
  // ── REAL DATA FROM PHP ────────────────────────────────────────────────────
  const applications = <?= $appsJson ?>;

  let pendingWithdrawId = null;
  let currentFilter = 'all';

  const statusMeta = {
    pending:   { label:'Application Sent',     cls:'status-pending',   step:0, steps:['Applied','Reviewed','Shortlisted','Interview','Offer'] },
    reviewed:  { label:'Under Review',         cls:'status-reviewed',  step:1, steps:['Applied','Reviewed','Shortlisted','Interview','Offer'] },
    shortlist: { label:'Shortlisted',          cls:'status-shortlist', step:2, steps:['Applied','Reviewed','Shortlisted','Interview','Offer'] },
    interview: { label:'Interview Scheduled',  cls:'status-interview', step:3, steps:['Applied','Reviewed','Shortlisted','Interview','Offer'] },
    rejected:  { label:'Not Selected',         cls:'status-rejected',  step:null, steps:['Applied','Reviewed','Rejected'] },
    hired:     { label:'Hired 🎉',             cls:'status-hired',     step:4, steps:['Applied','Reviewed','Shortlisted','Interview','Offer'] },
  };

  function getStepClass(appStatus, stepIndex, steps) {
    const sm = statusMeta[appStatus];
    if (appStatus === 'rejected') {
      const lastIdx = steps.indexOf('Rejected');
      if (stepIndex < lastIdx)    return 'done';
      if (stepIndex === lastIdx)  return 'rejected';
      return '';
    }
    if (sm && sm.step !== null) {
      if (stepIndex < sm.step)    return 'done';
      if (stepIndex === sm.step)  return 'current';
    }
    return '';
  }

  function getStepIcon(cls) {
    if (cls === 'done')     return '<i class="fas fa-check"></i>';
    if (cls === 'current')  return '<i class="fas fa-dot-circle"></i>';
    if (cls === 'rejected') return '<i class="fas fa-times"></i>';
    return '';
  }

  function renderApps() {
    const list = document.getElementById('appList');

    const filtered = currentFilter === 'all'       ? applications
                   : currentFilter === 'active'    ? applications.filter(a => a.status !== 'rejected')
                   : currentFilter === 'interview' ? applications.filter(a => a.status === 'interview')
                   : currentFilter === 'rejected'  ? applications.filter(a => a.status === 'rejected')
                   : applications;

    if (filtered.length === 0) {
      const msgs = {
        all:       { icon:'fa-paper-plane', title:'No applications yet',          sub:'Start applying to jobs and track your progress here.' },
        active:    { icon:'fa-clock',       title:'No active applications',        sub:'All your current applications will appear here.' },
        interview: { icon:'fa-calendar',    title:'No interviews scheduled',       sub:'When an employer schedules an interview, it will appear here.' },
        rejected:  { icon:'fa-times-circle',title:'No rejected applications',      sub:'Good news — nothing has been rejected.' },
      };
      const m = msgs[currentFilter] || msgs.all;
      list.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon"><i class="fas ${m.icon}"></i></div>
          <div class="empty-title">${m.title}</div>
          <div class="empty-sub">${m.sub}</div>
          <button class="btn-primary" onclick="window.location.href='antcareers_seekerJobs.php'"><i class="fas fa-search"></i> Browse Jobs</button>
        </div>`;
      return;
    }

    list.innerHTML = filtered.map(app => {
      const sm    = statusMeta[app.status] || statusMeta.pending;
      const steps = sm.steps;

      const stepHtml = steps.map((s, i) => {
        const cls = getStepClass(app.status, i, steps);
        return `<div class="ts-step">
          <div class="ts-dot ${cls}">${getStepIcon(cls)}</div>
          <div class="ts-label ${cls}">${s}</div>
        </div>`;
      }).join('');

      // Interview banner
      let ivBanner = '';
      if (app.interview) {
        const iv = app.interview;
        let loc = '';
        if (iv.type === 'Online' || iv.type === 'Online (Video)') {
          loc = iv.link ? `<a href="${escHtml(iv.link)}" target="_blank" style="color:#B07AFF;">${escHtml(iv.link)}</a>` : 'Link TBD';
        } else if (iv.type === 'On-site') {
          loc = iv.venueName ? escHtml(iv.venueName) : (iv.location ? escHtml(iv.location) : 'Location TBD');
          if (iv.fullAddress) loc += `<br><span style="font-size:11px;opacity:0.8;">${escHtml(iv.fullAddress)}</span>`;
          if (iv.mapLink) loc += ` <a href="${escHtml(iv.mapLink)}" target="_blank" style="color:#7ab8f0;font-size:11px;"><i class="fas fa-external-link-alt"></i> Map</a>`;
        } else if (iv.type === 'Phone') {
          loc = iv.phoneNumber ? `<i class="fas fa-phone" style="margin-right:4px;"></i>${escHtml(iv.phoneNumber)}` : 'Phone call';
          if (iv.contactPerson) loc += `<br><span style="font-size:11px;opacity:0.8;">Contact: ${escHtml(iv.contactPerson)}</span>`;
        } else {
          loc = iv.link ? `<a href="${escHtml(iv.link)}" target="_blank" style="color:#B07AFF;">${escHtml(iv.link)}</a>` : (iv.location || 'TBD');
        }
        const typeLabel = iv.type === 'Phone' ? 'Phone Call' : iv.type;
        ivBanner = `
          <div class="interview-banner">
            <i class="fas ${iv.type === 'On-site' ? 'fa-map-marker-alt' : iv.type === 'Phone' ? 'fa-phone' : 'fa-video'}"></i>
            <div>
              <div class="interview-banner-text">${escHtml(typeLabel)} Interview — ${escHtml(iv.date)}</div>
              <div class="interview-banner-sub">${loc}</div>
            </div>
          </div>`;
      }

      const withdrawBtn = app.status !== 'rejected' && app.status !== 'hired'
        ? `<button class="btn-app danger" onclick="openWithdraw(${app.id}, '${app.title.replace(/'/g,"\\'")}')"><i class="fas fa-times"></i> Withdraw</button>`
        : '';

      const ivBtn = app.interview
        ? `<button class="btn-app interview-btn" onclick="showInterviewDetails(${app.id})"><i class="fas fa-calendar-check"></i> Interview Details</button>`
        : '';

      return `
        <div class="app-card" id="app-${app.id}">
          <div class="app-icon"><i class="fas fa-briefcase"></i></div>
          <div class="app-info">
            <div class="app-title">${escHtml(app.title)}</div>
            <div class="app-company">${escHtml(app.company)}</div>
            <div class="app-meta">
              <div class="app-meta-item"><i class="fas fa-map-marker-alt"></i>${escHtml(app.location)}</div>
              <div class="app-meta-item"><i class="fas fa-briefcase"></i>${escHtml(app.type)}</div>
              <div class="app-meta-item"><i class="fas fa-money-bill-wave"></i>${escHtml(app.salary)}</div>
            </div>
            <div class="timeline-strip">${stepHtml}</div>
            ${ivBanner}
            <div class="app-actions">
              ${ivBtn}
              ${withdrawBtn}
            </div>
          </div>
          <div class="app-right">
            <span class="status-badge ${sm.cls}">${sm.label}</span>
            <div class="app-date"><i class="fas fa-calendar" style="margin-right:4px;"></i>Applied ${escHtml(app.appliedDate)}</div>
          </div>
        </div>`;
    }).join('');
  }

  function filterApps(f) {
    currentFilter = f;
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab' + f.charAt(0).toUpperCase() + f.slice(1)).classList.add('active');
    renderApps();
  }

  // ── WITHDRAW ──────────────────────────────────────────────────────────────
  function openWithdraw(id, title) {
    pendingWithdrawId = id;
    document.getElementById('withdrawJobTitle').textContent = title;
    document.getElementById('withdrawModal').classList.add('open');
  }
  function closeWithdraw() {
    pendingWithdrawId = null;
    document.getElementById('withdrawModal').classList.remove('open');
  }
  async function confirmWithdraw() {
    if (!pendingWithdrawId) return;
    const btn = document.getElementById('withdrawBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Withdrawing…';

    try {
      const fd = new FormData();
      fd.append('application_id', pendingWithdrawId);
      const res  = await fetch('withdraw_application.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.success) {
        // Remove from local array and re-render
        const idx = applications.findIndex(a => a.id === pendingWithdrawId);
        if (idx !== -1) applications.splice(idx, 1);
        closeWithdraw();
        updateCounts();
        renderApps();
        showToast('Application withdrawn', 'fa-check');
      } else {
        showToast(data.message || 'Could not withdraw. Please try again.', 'fa-exclamation');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-times"></i> Withdraw';
      }
    } catch {
      showToast('Network error. Please try again.', 'fa-exclamation');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-times"></i> Withdraw';
    }
  }

  document.getElementById('withdrawModal').addEventListener('click', e => {
    if (e.target === document.getElementById('withdrawModal')) closeWithdraw();
  });

  // ── INTERVIEW MODAL ───────────────────────────────────────────────────────
  function showInterviewDetails(appId) {
    const app = applications.find(a => a.id === appId);
    if (!app || !app.interview) return;
    const iv = app.interview;
    const typeLabel = iv.type === 'Phone' ? 'Phone Call' : iv.type;
    const typeIcon = iv.type === 'On-site' ? 'fa-map-marker-alt' : iv.type === 'Phone' ? 'fa-phone' : 'fa-video';
    const typeColor = iv.type === 'On-site' ? '#6ccf8a' : iv.type === 'Phone' ? 'var(--amber)' : '#B07AFF';

    let html = `
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 14px;background:rgba(209,61,44,0.06);border:1px solid rgba(209,61,44,0.15);border-radius:8px;">
        <i class="fas ${typeIcon}" style="font-size:16px;color:${typeColor};"></i>
        <span style="font-size:14px;font-weight:700;">${escHtml(typeLabel)} Interview</span>
      </div>
      <div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Date &amp; Time</span><div style="margin-top:3px;">${escHtml(iv.date)}</div></div>`;

    if (iv.type === 'Online' || iv.type === 'Online (Video)') {
      if (iv.link) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Meeting Link</span><div style="margin-top:3px;"><a href="${escHtml(iv.link)}" target="_blank" style="color:#B07AFF;word-break:break-all;">${escHtml(iv.link)}</a></div></div>`;
    } else if (iv.type === 'On-site') {
      if (iv.venueName) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Venue / Location</span><div style="margin-top:3px;">${escHtml(iv.venueName)}</div></div>`;
      if (iv.fullAddress) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Full Address</span><div style="margin-top:3px;">${escHtml(iv.fullAddress)}</div></div>`;
      if (iv.mapLink) {
        // Google Maps embed preview — extract query for embed, fallback to static card
        let embedUrl = '';
        try {
          const u = new URL(iv.mapLink);
          if (u.hostname.includes('google') && iv.mapLink.includes('/maps/')) {
            // Try to build a simple embed from the link
            const q = iv.fullAddress || iv.venueName || iv.mapLink;
            embedUrl = 'https://maps.google.com/maps?q=' + encodeURIComponent(q) + '&output=embed&z=15';
          }
        } catch(_){}
        if (!embedUrl && (iv.fullAddress || iv.venueName)) {
          embedUrl = 'https://maps.google.com/maps?q=' + encodeURIComponent(iv.fullAddress || iv.venueName) + '&output=embed&z=15';
        }
        if (embedUrl) {
          html += `<div style="margin-bottom:10px;">
            <span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Map Preview</span>
            <div style="margin-top:6px;border-radius:10px;overflow:hidden;border:1px solid var(--soil-line);position:relative;">
              <iframe src="${escHtml(embedUrl)}" width="100%" height="200" style="border:0;display:block;filter:saturate(0.8) contrast(1.1);" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
              <a href="${escHtml(iv.mapLink)}" target="_blank" style="position:absolute;bottom:8px;right:8px;background:var(--red-vivid);color:#fff;padding:5px 12px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(0,0,0,0.4);"><i class="fas fa-external-link-alt"></i> Open in Google Maps</a>
            </div>
          </div>`;
        } else {
          html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Google Maps</span><div style="margin-top:3px;"><a href="${escHtml(iv.mapLink)}" target="_blank" style="color:#7ab8f0;word-break:break-all;"><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>${escHtml(iv.mapLink)}</a></div></div>`;
        }
      }
      else if (iv.location) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Location</span><div style="margin-top:3px;">${escHtml(iv.location)}</div></div>`;
    } else if (iv.type === 'Phone') {
      if (iv.phoneNumber) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Contact Number</span><div style="margin-top:3px;"><i class="fas fa-phone" style="margin-right:4px;color:var(--amber);"></i>${escHtml(iv.phoneNumber)}</div></div>`;
      if (iv.contactPerson) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Contact Person</span><div style="margin-top:3px;">${escHtml(iv.contactPerson)}</div></div>`;
    } else {
      if (iv.link) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Meeting Link</span><div style="margin-top:3px;"><a href="${escHtml(iv.link)}" target="_blank" style="color:#B07AFF;">${escHtml(iv.link)}</a></div></div>`;
      if (iv.location) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Location</span><div style="margin-top:3px;">${escHtml(iv.location)}</div></div>`;
    }

    if (iv.notes) html += `<div style="margin-bottom:10px;"><span style="color:var(--text-muted);font-size:11px;font-weight:700;text-transform:uppercase;">Notes / Instructions</span><div style="margin-top:3px;">${escHtml(iv.notes)}</div></div>`;
    document.getElementById('interviewModalBody').innerHTML = html;
    document.getElementById('interviewModal').classList.add('open');
  }
  document.getElementById('interviewModal').addEventListener('click', e => {
    if (e.target === document.getElementById('interviewModal')) document.getElementById('interviewModal').classList.remove('open');
  });

  // ── COUNTS ────────────────────────────────────────────────────────────────
  function updateCounts() {
    const all   = applications.length;
    const active    = applications.filter(a => a.status !== 'rejected').length;
    const interview = applications.filter(a => a.status === 'interview').length;
    const rejected  = applications.filter(a => a.status === 'rejected').length;
    document.getElementById('countAll').textContent       = all;
    document.getElementById('countActive').textContent    = active;
    document.getElementById('countInterview').textContent = interview;
    document.getElementById('countRejected').textContent  = rejected;
  }

  // ── HELPERS ───────────────────────────────────────────────────────────────
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function showToast(msg, icon = 'fa-check') {
    const d = document.createElement('div');
    d.className = 'toast';
    d.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 3200);
  }



  // INIT — skeletons visible for 600ms then render real data
  setTimeout(() => {
    updateCounts();
    const tabParam = new URLSearchParams(window.location.search).get('tab');
    if (tabParam && ['all','active','interview','rejected'].includes(tabParam)) {
      filterApps(tabParam);
    } else {
      renderApps();
    }
  }, 600);
</script>
</body>
</html>