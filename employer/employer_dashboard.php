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
$fullName    = trim((string)($_SESSION['user_name'] ?? 'Employer'));
$nameParts   = preg_split('/\s+/', $fullName) ?: [];
$firstName   = $nameParts[0] ?? 'Employer';
$initials    = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0],0,1).substr($nameParts[1],0,1))
    : strtoupper(substr($firstName,0,2));
$companyName = trim((string)($_SESSION['company_name'] ?? 'Your Company'));
$navActive   = 'dashboard';

$db = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── Summary counts ── */
$activeJobCount = 0;
$totalApplicants = 0;
$shortlistedCount = 0;
$interviewCount = 0;
$messageCount = 0;
$recentApplicantCount = 0;

try {
    $s = $db->prepare("SELECT COUNT(*) c FROM jobs WHERE employer_id=? AND status='Active'"); $s->execute([$uid]); $activeJobCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=?"); $s->execute([$uid]); $totalApplicants = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? AND a.status='Shortlisted'"); $s->execute([$uid]); $shortlistedCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM interview_schedules WHERE employer_id=? AND status='Scheduled' AND scheduled_at>=NOW()"); $s->execute([$uid]); $interviewCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0"); $s->execute([$uid]); $messageCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? AND DATE(a.applied_at)=CURDATE()"); $s->execute([$uid]); $recentApplicantCount = (int)$s->fetchColumn();
} catch (PDOException $e) { error_log('[AntCareers] dashboard counts: '.$e->getMessage()); }

/* ── Recent jobs (this employer) ── */
$dashJobs = [];
try {
    $s = $db->prepare("
        SELECT j.id, j.title, j.status, j.job_type, j.setup, j.skills_required, j.created_at,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id=j.id) AS applicants
        FROM jobs j WHERE j.employer_id=? ORDER BY j.created_at DESC LIMIT 6
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $tags = array_filter(array_map('trim', explode(',', (string)($r['skills_required'] ?? ''))));
        $dashJobs[] = [
            'id'         => (int)$r['id'],
            'title'      => $r['title'],
            'status'     => $r['status'],
            'type'       => $r['job_type'],
            'setup'      => $r['setup'],
            'posted'     => date('M j', strtotime($r['created_at'])),
            'applicants' => (int)$r['applicants'],
            'tags'       => array_values(array_slice($tags, 0, 4)),
            'icon'       => 'fa-briefcase',
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] dashboard jobs: '.$e->getMessage()); }

/* ── Recent applicants ── */
$dashApplicants = [];
try {
    $s = $db->prepare("
        SELECT a.id, u.full_name, u.id AS seeker_id, a.status, j.title AS job, a.applied_at
        FROM applications a
        JOIN jobs j ON j.id=a.job_id
        JOIN users u ON u.id=a.seeker_id
        WHERE j.employer_id=?
        ORDER BY a.applied_at DESC LIMIT 6
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $parts = preg_split('/\s+/', $r['full_name']) ?: ['?'];
        $ini = count($parts) >= 2
            ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1))
            : strtoupper(substr($parts[0],0,2));
        $colors = [
            'linear-gradient(135deg,#D13D2C,#7A1515)',
            'linear-gradient(135deg,#4A90D9,#2A6090)',
            'linear-gradient(135deg,#4CAF70,#2A7040)',
            'linear-gradient(135deg,#9C27B0,#5A1070)',
            'linear-gradient(135deg,#D4943A,#8a5010)',
        ];
        $dashApplicants[] = [
            'id'       => (int)$r['id'],
            'seekerId' => (int)$r['seeker_id'],
            'name'     => $r['full_name'],
            'initials' => $ini,
            'color'    => $colors[$r['id'] % count($colors)],
            'job'      => $r['job'],
            'date'     => date('M j', strtotime($r['applied_at'])),
            'status'   => $r['status'],
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] dashboard applicants: '.$e->getMessage()); }

/* ── Upcoming interviews ── */
$dashInterviews = [];
try {
    // Auto-migrate new interview columns if missing
    try { $db->query("SELECT venue_name FROM interview_schedules LIMIT 0"); }
    catch (PDOException $__) {
        $db->exec("ALTER TABLE interview_schedules ADD COLUMN venue_name VARCHAR(300) DEFAULT NULL, ADD COLUMN full_address VARCHAR(500) DEFAULT NULL, ADD COLUMN map_link VARCHAR(500) DEFAULT NULL, ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL, ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL");
    }
    $s = $db->prepare("
        SELECT iv.id, u.full_name, j.title AS job, iv.scheduled_at, iv.interview_type,
               iv.meeting_link, iv.location, iv.notes,
               COALESCE(iv.venue_name, iv.location) AS venue_name,
               iv.full_address, iv.map_link, iv.phone_number, iv.contact_person
        FROM interview_schedules iv
        JOIN applications a ON a.id=iv.application_id
        JOIN jobs j ON j.id=a.job_id
        JOIN users u ON u.id=iv.seeker_id
        WHERE iv.employer_id=? AND iv.status='Scheduled' AND iv.scheduled_at>=NOW()
        GROUP BY iv.id
        ORDER BY iv.scheduled_at ASC LIMIT 5
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $parts = preg_split('/\s+/', $r['full_name']) ?: ['?'];
        $ini = count($parts) >= 2
            ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1))
            : strtoupper(substr($parts[0],0,2));
        $colors = [
            'linear-gradient(135deg,#4A90D9,#2A6090)',
            'linear-gradient(135deg,#4CAF70,#2A7040)',
            'linear-gradient(135deg,#9C27B0,#5A1070)',
        ];
        $dt = strtotime($r['scheduled_at']);
        $type = $r['interview_type'];
        // Build display info per type
        if ($type === 'On-site') {
            $linkText = $r['venue_name'] ?: ($r['location'] ?? 'On-site');
            $icon = 'fa-map-marker-alt';
            <?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>
    .nav-inner {
      max-width:1380px; margin:0 auto; padding:0 24px;
      display:flex; align-items:center; height:64px; gap:0; min-width:0;
    }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link {
      font-size:13px; font-weight:600; color:#A09090;
      text-decoration:none; padding:7px 11px; border-radius:6px;
      transition:all 0.2s; cursor:pointer; background:none; border:none;
      font-family:var(--font-body); display:flex; align-items:center; gap:5px;
      white-space:nowrap; letter-spacing:0.01em;
    }
    .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
    .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.02em; box-shadow:0 2px 8px rgba(209,61,44,0.3); text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.45); }

    /* Profile button */
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-btn:hover { border-color:var(--soil-line); background:var(--soil-card); }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--amber), #8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }

    /* Hamburger */
    .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 24px; }

    /* ── SEARCH HEADER ── */
    .search-header { padding:16px 0 12px; }
    .search-greeting { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .search-greeting span { color:var(--red-bright); font-style:italic; }
    .search-sub { font-size:14px; color:var(--text-muted); margin-bottom:20px; }
    .search-bar { display:flex; align-items:center; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; transition:0.25s; }
    .search-bar:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12), 0 4px 20px rgba(0,0,0,0.3); }
    .search-bar .si { padding:0 16px; color:var(--text-muted); font-size:16px; flex-shrink:0; }
    .search-bar input { flex:1; padding:16px 0; min-width:0; background:none; border:none; outline:none; font-family:var(--font-body); font-size:15px; color:#F5F0EE; }
    .search-bar input::placeholder { color:var(--text-muted); }
    .search-divider { width:1px; height:28px; background:var(--soil-line); flex-shrink:0; }
    .search-btn { margin:6px; padding:10px 22px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; flex-shrink:0; letter-spacing:0.02em; display:flex; align-items:center; gap:7px; }
    .search-btn:hover { background:var(--red-bright); }

    /* Quick action pills (replaces filter pills) */
    .quick-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .qf-pill { display:flex; align-items:center; gap:5px; padding:6px 13px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:100px; font-size:12px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; white-space:nowrap; }
    .qf-pill:hover, .qf-pill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    .qf-pill i { font-size:11px; }

    /* Ghost nav button */
    .btn-nav-ghost { padding:7px 14px; border-radius:7px; border:1px solid var(--soil-line); background:transparent; color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.01em; text-decoration:none; display:flex; align-items:center; gap:6px; }
    .btn-nav-ghost:hover { border-color:var(--red-vivid); color:var(--text-light); }

    /* Browse Jobs Banner */
    .browse-banner { display:flex; align-items:center; justify-content:space-between; gap:16px; background:var(--soil-card); border:1px solid var(--soil-line); border-left:3px solid var(--red-vivid); border-radius:10px; padding:14px 20px; margin-bottom:18px; flex-wrap:wrap; }
    .browse-banner-left { display:flex; align-items:center; gap:12px; }
    .browse-banner-btn { display:flex; align-items:center; gap:7px; padding:8px 18px; border-radius:7px; background:var(--red-vivid); color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; text-decoration:none; transition:0.2s; white-space:nowrap; flex-shrink:0; letter-spacing:0.02em; }
    .browse-banner-btn:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.35); }
    body.light .browse-banner { background:#FFFFFF; border-color:#E0CECA; }

    /* ── CONTENT LAYOUT ── */
    .content-layout { display:grid; grid-template-columns: 1fr; gap:28px; align-items:start; }

    /* ── SIDEBAR ── */
    .sidebar { position:sticky; top:72px; max-height:calc(100vh - 88px); overflow-y:auto; scrollbar-width:none; }
    .sidebar::-webkit-scrollbar { display:none; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .sidebar-head { padding:16px 18px 12px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--soil-line); }
    .sidebar-title { font-family:var(--font-body); font-size:12px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:7px; letter-spacing:0.07em; text-transform:uppercase; }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }

    /* Sidebar nav items */
    .sb-nav-item { display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; border:none; background:none; font-family:var(--font-body); width:100%; text-align:left; border-bottom:1px solid var(--soil-line); }
    .sb-nav-item:last-child { border-bottom:none; }
    .sb-nav-item:hover { color:#F5F0EE; background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .sb-nav-item i { width:16px; text-align:center; font-size:12px; color:var(--red-bright); }
    .sb-badge { margin-left:auto; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; }
    .sb-badge.amber { background:var(--amber); color:#1A0A09; }

    /* Sidebar stat mini-cards */
    .sidebar-stats { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sb-stat { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; padding:10px 12px; }
    .sb-stat-num { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; line-height:1; }
    .sb-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-top:3px; }

    /* Company block in sidebar */
    .sidebar-co { padding:14px 16px; border-bottom:1px solid var(--soil-line); }
    .sidebar-co-inner { display:flex; align-items:center; gap:10px; }
    .co-logo { width:36px; height:36px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .co-name { font-size:13px; font-weight:700; color:#F5F0EE; }
    .co-industry { font-size:11px; color:var(--text-muted); margin-top:1px; }
    .co-complete { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px; margin-top:5px; background:rgba(76,175,112,.1); color:#6ccf8a; border:1px solid rgba(76,175,112,.2); }

    /* ── MAIN CONTENT ── */
    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:10px; letter-spacing:0.01em; }
    .sec-title i { color:var(--red-bright); font-size:16px; }
    .sec-count { font-size:11px; font-weight:600; color:var(--text-muted); background:var(--soil-hover); padding:2px 9px; border-radius:4px; letter-spacing:0.04em; }
    .see-more { font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:4px; transition:0.15s; letter-spacing:0.02em; }
    .see-more:hover { color:var(--red-bright); }

    /* ── SUMMARY CARDS ROW ── */
    .cards-row { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-bottom:16px; }
    .sum-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:10px; transition:all 0.2s; cursor:default; }
    .sum-card:hover { border-color:rgba(209,61,44,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.25); }
    .sc-top { display:flex; align-items:center; justify-content:space-between; }
    .sc-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .sc-icon.r { background:rgba(209,61,44,.12); color:var(--red-pale); }
    .sc-icon.a { background:rgba(212,148,58,.12); color:var(--amber); }
    .sc-icon.b { background:rgba(74,144,217,.1); color:#7ab8f0; }
    .sc-icon.g { background:rgba(76,175,112,.1); color:#6ccf8a; }
    .sc-icon.p { background:rgba(156,39,176,.1); color:#cf8ae0; }
    .sc-num { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; line-height:1; }
    .sc-label { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .sc-btn { padding:7px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:11px; font-weight:700; cursor:pointer; transition:0.18s; width:100%; }
    .sc-btn:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--red-pale); }

    /* ── FEATURED CARDS (same as seeker) ── */
    .featured-scroll { display:flex; gap:14px; overflow-x:auto; padding:8px 6px 24px 6px; margin:-8px -6px 32px -6px; scrollbar-width:none; }
    .featured-scroll::-webkit-scrollbar { display:none; }
    .featured-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px; min-width:258px; max-width:258px; cursor:pointer; transition:all 0.25s; position:relative; overflow:hidden; flex-shrink:0; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .featured-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .featured-card:hover { border-color:rgba(209,61,44,0.55); transform:translateY(-4px); box-shadow:0 20px 48px rgba(0,0,0,0.45); }
    .fc-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--amber); background:var(--amber-dim); border:1px solid rgba(212,148,58,0.22); padding:2px 7px; border-radius:3px; margin-bottom:14px; }
    .fc-icon { width:40px; height:40px; border-radius:10px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; color:var(--red-bright); }
    .fc-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:4px; line-height:1.3; }
    .fc-company { font-size:12px; color:var(--red-pale); font-weight:600; margin-bottom:14px; }
    .fc-chips { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:14px; }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); letter-spacing:0.02em; }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.amber { background:rgba(212,148,58,.08); color:var(--amber); border-color:rgba(212,148,58,.2); }
    .chip.red { background:rgba(209,61,44,.08); color:var(--red-pale); border-color:rgba(209,61,44,.15); }
    .chip.blue { background:rgba(74,144,217,.08); color:#7ab8f0; border-color:rgba(74,144,217,.18); }
    .fc-footer { display:flex; align-items:center; justify-content:space-between; padding-top:14px; border-top:1px solid var(--soil-line); }
    .fc-count { font-size:13px; font-weight:700; color:#F5F0EE; }
    .fc-action { padding:5px 12px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.2s; }
    .fc-action:hover { background:var(--red-bright); }

    /* ── JOB ROW (applicant rows) — same structure as seeker ── */
    .job-list { display:flex; flex-direction:column; gap:8px; }
    .job-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px 20px; transition:all 0.18s; display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; position:relative; }
    .job-row:hover { border-color:rgba(209,61,44,0.5); background:var(--soil-hover); transform:translateX(2px); box-shadow:0 4px 20px rgba(0,0,0,0.3); }
    .jr-top { display:flex; align-items:center; gap:8px; margin-bottom:5px; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); padding:2px 7px; border-radius:3px; }
    .jr-new.green { color:#6ccf8a; background:rgba(76,175,112,.1); border-color:rgba(76,175,112,.2); }
    .jr-new.amber { color:var(--amber); background:rgba(212,148,58,.1); border-color:rgba(212,148,58,.2); }
    .jr-new.blue { color:#7ab8f0; background:rgba(74,144,217,.1); border-color:rgba(74,144,217,.2); }
    .jr-new.muted { color:var(--text-muted); background:var(--soil-hover); border-color:var(--soil-line); }
    .jr-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:#927C7A; margin-bottom:8px; }
    .jr-meta span { display:flex; align-items:center; gap:4px; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-company { color:var(--red-pale); font-weight:600; }
    .jr-chips { display:flex; gap:5px; flex-wrap:wrap; }
    .job-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .jr-salary { font-family:var(--font-body); font-size:14px; font-weight:700; color:#F5F0EE; white-space:nowrap; letter-spacing:-0.01em; }
    .jr-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .jr-btn { padding:6px 13px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.18s; white-space:nowrap; }
    .jr-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .jr-btn.r:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .jr-btn.g:hover { border-color:rgba(76,175,112,.5); color:#6ccf8a; }
    .jr-btn.b:hover { border-color:rgba(74,144,217,.5); color:#7ab8f0; }
    .jr-btn.a:hover { border-color:rgba(212,148,58,.5); color:var(--amber); }
    .jr-apply { padding:7px 16px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.2s; letter-spacing:0.02em; }
    .jr-apply:hover { background:var(--red-bright); }

    /* ── NOTIFICATIONS PANEL (slide-in like saved panel) ── */
    .notif-panel { position:fixed; top:64px; right:0; bottom:0; width:360px; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:150; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
    .notif-panel.open { transform:translateX(0); }
    .notif-panel-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
    .notif-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
    .notif-panel-title i { color:var(--red-bright); }
    .notif-close { width:28px; height:28px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
    .notif-close:hover { color:#F5F0EE; }
    .notif-panel-body { flex:1; overflow-y:auto; padding:12px 16px; }
    .notif-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
    .notif-item:last-child { border-bottom:none; }
    .n-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .n-dot.red { background:var(--red-vivid); }
    .n-dot.amber { background:var(--amber); }
    .n-dot.read { background:var(--soil-line); }
    .n-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
    .n-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; min-width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); overflow:hidden; padding:0 3px; }

    /* Modal */
    .modal-overlay { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:32px; max-width:560px; width:92%; position:relative; animation:modalIn 0.2s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6); max-height:88vh; overflow-y:auto; }
    @keyframes modalIn { from{opacity:0;transform:scale(0.97) translateY(8px)} to{opacity:1;transform:scale(1)} }
    .modal-close { position:absolute; top:18px; right:18px; width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-size:13px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; }
    .modal-close:hover { color:#F5F0EE; border-color:var(--red-mid); }

    /* Toast */
    .toast { position:fixed; bottom:24px; right:24px; z-index:999; background:var(--soil-card); border:1px solid var(--soil-line); border-left:2px solid var(--red-vivid); border-radius:8px; padding:11px 18px; font-size:13px; font-weight:500; color:#F5F0EE; box-shadow:0 10px 30px rgba(0,0,0,0.4); display:flex; align-items:center; gap:9px; animation:toastIn 0.25s ease; }
    @keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
    .toast i { color:var(--red-pale); }

    /* Footer */
    .footer { border-top:1px solid var(--soil-line); padding:28px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:12px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:16px; }

    /* Empty state */
    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.05s; }
    .anim-d2 { animation-delay:0.1s; }

    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    /* ── LIGHT THEME ── */
    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
    body.light .search-bar { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-bar input { color:#1A0A09; }
    body.light .search-greeting { color:#1A0A09; }
    body.light .search-sub { color:#7A5555; }
    body.light .qf-pill { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .qf-pill.active, body.light .qf-pill:hover { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.3); color:var(--red-mid); }
    body.light .sidebar-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sidebar-title { color:#1A0A09; }
    body.light .sb-nav-item { color:#5A4040; border-color:#E0CECA; }
    body.light .sb-nav-item:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .sb-nav-item.active { color:var(--red-mid); }
    body.light .sb-stat { background:#F5EEEC; border-color:#E0CECA; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .sidebar-co { border-color:#E0CECA; }
    body.light .co-logo { background:#F5EEEC; border-color:#E0CECA; }
    body.light .co-name { color:#1A0A09; }
    body.light .sec-title { color:#1A0A09; }
    body.light .sum-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sc-num { color:#1A0A09; }
    body.light .featured-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fc-title { color:#1A0A09; }
    body.light .fc-count { color:#1A0A09; }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .job-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .job-row:hover { background:#FEF0EE; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    body.light .jr-title { color:#1A0A09; }
    body.light .jr-salary { color:#1A0A09; }
    body.light .jr-meta { color:#7A5555; }
    body.light .notif-panel { background:#FFFFFF; border-color:#E0CECA; }
    body.light .notif-panel-title { color:#1A0A09; }
    body.light .notif-item { border-color:#E0CECA; }
    body.light .n-dot.read { background:#E0CECA; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }

    @media(max-width:1060px) { .content-layout{grid-template-columns:1fr}  .cards-row{grid-template-columns:repeat(3,1fr);} }
    @media(max-width:760px) {
      .nav-links{display:none} .hamburger{display:flex}
      .page-shell{padding:0 16px 60px} .nav-inner{padding:0 16px}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .job-row{grid-template-columns:1fr;gap:10px}
      .job-row-right{flex-direction:row;align-items:center;justify-content:space-between}
      .notif-panel{width:100%;max-width:100%}
      .footer{flex-direction:column;text-align:center;padding:20px 16px}
      .cards-row{grid-template-columns:repeat(2,1fr);}
    }
  </style>
  <script>
    (function(){
      var p = new URLSearchParams(window.location.search).get('theme');
      var s = localStorage.getItem('ac-theme');
      var t = p || s || 'dark';
      if (p) localStorage.setItem('ac-theme', t);
      if (t === 'light') document.documentElement.classList.add('light-init');
    })();
  </script>
</head>
<body id="pageBody">

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
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="employer_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>

    <div class="nav-links">
      <a class="nav-link active"><i class="fas fa-th-large"></i> Dashboard</a>
      <a class="nav-link" onclick="window.location.href='employer_browseJobs.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-search"></i> Browse Jobs</a>
      <a class="nav-link" onclick="window.location.href='employer_manageJobs.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-briefcase"></i> Manage Jobs</a>
      <a class="nav-link" onclick="window.location.href='employer_applicants.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-users"></i> Applicants</a>
      <a class="nav-link" href="employer_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
    </div>

    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-sun"></i></button>

      <button class="notif-btn-nav" id="dashMsgBtn" onclick="window.location.href='employer_messages.php?theme='+(document.body.classList.contains('light')?'light':'dark')">
        <i class="fas fa-envelope"></i>
        <span class="badge msg-badge-count">0</span>
      </button>

      <button class="notif-btn-nav" id="notifToggle" onclick="if(typeof openNotifSidebar==='function'){openNotifSidebar();}">
        <i class="fas fa-bell"></i>
        <span class="badge notif-badge-count">0</span>
      </button>

      <a class="btn-nav-red" style="cursor:pointer;" href="employer_manageJobs.php?postjob=1"><i class="fas fa-plus-circle"></i> Post Job</a>

      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle">
          <div class="profile-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
          <div>
            <div class="profile-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="profile-role">Company Admin</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="pdh-sub">Company Admin · <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="pd-item" onclick="window.location.href='employer_companyProfile.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-building"></i> Company Profile</div>
          <div class="pd-item" onclick="window.location.href='employer_manageRecruiters.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-user-tie"></i> Manage Recruiters</div>
          <div class="pd-item" onclick="window.location.href='employer_settings.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-cog"></i> Settings</div>
          <div class="pd-item" onclick="if(typeof openFullscreenChat==='function'){openFullscreenChat();}else{window.location.href='employer_messages.php';}"><i class="fas fa-comments"></i> Messages</div>
          <div class="pd-divider"></div>
          <div class="pd-item danger" onclick="window.location.href='../auth/logout.php'"><i class="fas fa-sign-out-alt"></i> Sign out</div>
        </div>
      </div>
      <button class="theme-btn hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
    </div><!-- /nav-right -->
  </div><!-- /nav-inner -->
</nav>

<!-- Mobile menu -->
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link"><i class="fas fa-th-large"></i> Dashboard</a>
  <a class="mobile-link" onclick="window.location.href='employer_browseJobs.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-search"></i> Browse Jobs</a>
  <a class="mobile-link" onclick="window.location.href='employer_manageJobs.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-briefcase"></i> Manage Jobs</a>
  <a class="mobile-link" onclick="window.location.href='employer_applicants.php?theme='+(document.body.classList.contains('light')?'light':'dark')"><i class="fas fa-users"></i> Applicants</a>
  <a class="mobile-link" href="employer_analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
  <a class="mobile-link" href="employer_messages.php"><i class="fas fa-envelope"></i> Messages</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="employer_companyProfile.php"><i class="fas fa-building"></i> Company Profile</a>
  <a class="mobile-link" href="employer_manageRecruiters.php"><i class="fas fa-user-tie"></i> Manage Recruiters</a>
  <a class="mobile-link" href="employer_settings.php"><i class="fas fa-cog"></i> Settings</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" onclick="window.location.href='../auth/logout.php'"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<!-- PAGE -->
<div class="page-shell">

  <!-- SEARCH HEADER -->
  <div class="search-header anim">
    <div class="search-greeting"><span id="greetingText">Good morning</span>, <span><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>.</span></div>
    <div class="search-sub">You have <?php echo $recentApplicantCount; ?> new applicant<?php echo $recentApplicantCount!==1?'s':''; ?> today — let's keep the momentum going.</div>

    <div style="margin-top:20px;">
      <a class="btn-nav-red" style="display:inline-flex; padding:11px 24px; font-size:14px; border-radius:9px; cursor:pointer;" href="employer_manageJobs.php?postjob=1">
        <i class="fas fa-plus-circle"></i>&nbsp; Post a New Job
      </a>
    </div>
  </div>

  <div class="content-layout">

    <!-- SIDEBAR -->
    <!-- MAIN -->
    <main>

      <!-- SUMMARY CARDS -->
      <div class="cards-row anim">
        <div class="sum-card"><div class="sc-top"><div class="sc-icon r"><i class="fas fa-list-alt"></i></div><div class="sc-num"><?php echo $activeJobCount;?></div></div><div class="sc-label">Active Jobs</div><button class="sc-btn" onclick="window.location.href='employer_manageJobs.php'">Manage Jobs</button></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon a"><i class="fas fa-users"></i></div><div class="sc-num"><?php echo $totalApplicants;?></div></div><div class="sc-label">Total Applicants</div><button class="sc-btn" onclick="window.location.href='employer_applicants.php'">View Applicants</button></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon g"><i class="fas fa-user-check"></i></div><div class="sc-num"><?php echo $shortlistedCount;?></div></div><div class="sc-label">Shortlisted</div><button class="sc-btn" onclick="window.location.href='employer_applicants.php?status=Shortlisted'">View Shortlisted</button></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon b"><i class="fas fa-calendar-check"></i></div><div class="sc-num"><?php echo $interviewCount;?></div></div><div class="sc-label">Interviews</div><button class="sc-btn" onclick="window.location.href='employer_applicants.php'">View Interviews</button></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon p"><i class="fas fa-envelope"></i></div><div class="sc-num"><?php echo $messageCount;?></div></div><div class="sc-label">Messages</div><button class="sc-btn" onclick="window.location.href='employer_messages.php'">Open Messages</button></div>
      </div>

      <!-- RECENT JOB POSTS -->
      <div id="section-jobs" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-list-alt"></i> Recent Job Posts <span class="sec-count" id="jobCount">4 jobs</span></div>
          <button class="see-more" onclick="window.location.href='employer_manageJobs.php'">Manage all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

      <!-- RECENT APPLICANTS -->
      <div id="section-applicants" style="margin-top:40px;" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-user-clock"></i> Recent Applicants <span class="sec-count" id="appCount">4 applicants</span></div>
          <button class="see-more" onclick="window.location.href='employer_applicants.php'">View all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="job-list" id="applicantsContainer"></div>
      </div>

      <!-- UPCOMING INTERVIEWS -->
      <div id="section-interviews" style="margin-top:40px;" class="anim anim-d2">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-calendar-alt"></i> Upcoming Interviews</div>
          <button class="see-more" onclick="showToast('View all interviews','fa-calendar-alt')">View all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="featured-scroll" id="interviewsContainer"></div>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Employer Dashboard — <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='../index.php'">← Public Site</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>



<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    <div id="modalBody"></div>
  </div>
</div>

<script>
  // ── DATA (from DB) ──
  const jobsData = <?= $dashJobsJson ?>;
  const applicantsData = <?= $dashApplicantsJson ?>;
  const interviewsData = <?= $dashInterviewsJson ?>;

  // ── RENDER JOBS ──
  function renderJobs(data) {
    const container = document.getElementById('jobsContainer');
    document.getElementById('jobCount').textContent = `${data.length} job${data.length!==1?'s':''}`;
    if (!data.length) { container.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><p>No jobs match your search.</p></div>`; return; }
    container.innerHTML = data.map((j,i) => `
      <div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div class="jr-title">${j.title}</div>
            <span class="jr-new ${j.status==='Active'?'green':j.status==='Draft'?'muted':''}">${j.status}</span>
          </div>
          <div class="jr-meta">
            <span class="jr-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></span>
            <span><i class="fas fa-clock"></i> ${j.type}</span>
            <span><i class="fas fa-laptop-house"></i> ${j.setup}</span>
            <span><i class="fas fa-calendar"></i> Posted ${j.posted}</span>
          </div>
          <div class="jr-chips">
            ${j.tags.map(t=>`<span class="chip">${t}</span>`).join('')}
            ${j.applicants>0?`<span class="chip green">${j.applicants} applicants</span>`:'<span class="chip">No applicants yet</span>'}
          </div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions">
            <button class="jr-btn" onclick="event.stopPropagation();showToast('View ${j.title}','fa-eye')">View</button>
            <button class="jr-btn" onclick="event.stopPropagation();showToast('Edit ${j.title}','fa-pen')">Edit</button>
            ${j.status==='Active'?`<button class="jr-btn r" onclick="event.stopPropagation();showToast('Closed','fa-times-circle')">Close</button>`:''}
            <button class="jr-btn r" onclick="event.stopPropagation();showToast('Deleted','fa-trash')">Delete</button>
          </div>
        </div>
      </div>`).join('');
  }

  // ── RENDER APPLICANTS ──
  function renderApplicants(data) {
    const container = document.getElementById('applicantsContainer');
    document.getElementById('appCount').textContent = `${data.length} applicant${data.length!==1?'s':''}`;
    if (!data.length) { container.innerHTML = `<div class="empty-state"><i class="fas fa-users"></i><p>No applicants match your search.</p></div>`; return; }

    const statusClass = { Reviewed:'blue', Shortlisted:'green', Pending:'amber', Rejected:'red' };
    container.innerHTML = data.map((a,i) => `
      <div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <a href="employer_view_applicant.php?id=${a.seekerId}" style="text-decoration:none;display:flex;align-items:center;gap:8px;cursor:pointer;" title="View ${escapeHtml(a.name)}'s profile">
              <div style="width:34px;height:34px;border-radius:50%;background:${a.color};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">${a.initials}</div>
              <div class="jr-title" style="font-size:14px;">${escapeHtml(a.name)}</div>
            </a>
            <span class="jr-new ${statusClass[a.status]||''}">${a.status}</span>
          </div>
          <div class="jr-meta">
            <span><i class="fas fa-briefcase"></i> ${escapeHtml(a.job)}</span>
            <span><i class="fas fa-calendar"></i> Applied ${a.date}</span>
          </div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions">
            <a class="jr-btn" href="employer_view_applicant.php?id=${a.seekerId}" style="text-decoration:none;">View Profile</a>
            <button class="jr-btn g" onclick="showToast('Shortlisted ${escapeHtml(a.name)}','fa-user-check')">Shortlist</button>
            <button class="jr-btn r" onclick="showToast('Rejected ${escapeHtml(a.name)}','fa-times')">Reject</button>
            <button class="jr-btn b" onclick="showToast('Message ${escapeHtml(a.name)}','fa-envelope')">Message</button>
          </div>
        </div>
      </div>`).join('');
  }

  // ── RENDER INTERVIEWS ──
  function renderInterviews() {
    const el = document.getElementById('interviewsContainer');
    if (!interviewsData.length) { el.innerHTML = `<div class="empty-state" style="padding:30px 20px;"><i class="fas fa-calendar-alt"></i><p>No upcoming interviews.</p></div>`; return; }
    el.innerHTML = interviewsData.map(iv => {
      // Build type-specific detail chip
      let detailChip = '';
      if (iv.type === 'On-site') {
        const mapUrl = iv.mapLink || '#';
        detailChip = `<span class="chip"><i class="fas fa-map-marker-alt" style="margin-right:3px;color:#6ccf8a;"></i>${escapeHtml(iv.venueName || iv.link)}</span>`;
        if (iv.fullAddress) detailChip += `<span class="chip" style="font-size:10px;"><i class="fas fa-road" style="margin-right:3px;"></i>${escapeHtml(iv.fullAddress)}</span>`;
        if (iv.mapLink) detailChip += `<a href="${escapeHtml(iv.mapLink)}" target="_blank" class="chip" style="text-decoration:none;color:#7ab8f0;cursor:pointer;"><i class="fas fa-external-link-alt" style="margin-right:3px;"></i>Google Maps</a>`;
      } else if (iv.type === 'Phone') {
        detailChip = `<span class="chip"><i class="fas fa-phone" style="margin-right:3px;color:var(--amber);"></i>${escapeHtml(iv.phoneNumber || iv.link)}</span>`;
        if (iv.contactPerson) detailChip += `<span class="chip"><i class="fas fa-user" style="margin-right:3px;"></i>${escapeHtml(iv.contactPerson)}</span>`;
      } else {
        const meetLink = iv.meetingLink || iv.link;
        detailChip = meetLink && meetLink.startsWith('http')
          ? `<a href="${escapeHtml(meetLink)}" target="_blank" class="chip" style="text-decoration:none;color:#7ab8f0;cursor:pointer;"><i class="fas fa-video" style="margin-right:3px;"></i>Join Meeting</a>`
          : `<span class="chip"><i class="fas fa-video" style="margin-right:3px;"></i>${escapeHtml(meetLink)}</span>`;
      }
      // Type badge
      const typeBadge = iv.type === 'On-site' ? '<span class="chip green">On-site</span>'
        : iv.type === 'Phone' ? '<span class="chip amber">Phone Call</span>'
        : '<span class="chip blue">Online</span>';

      return `
      <div class="featured-card" style="min-width:260px;max-width:260px;">
        <div class="fc-badge"><i class="fas fa-calendar-check"></i> Scheduled</div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
          <div style="width:42px;height:42px;border-radius:50%;background:${iv.color};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0;">${iv.initials}</div>
          <div>
            <div class="fc-title" style="font-size:14px;">${escapeHtml(iv.name)}</div>
            <div class="fc-company">${escapeHtml(iv.job)}</div>
          </div>
        </div>
        <div style="background:rgba(209,61,44,0.08);border:1px solid rgba(209,61,44,0.18);border-radius:6px;padding:10px 14px;margin-bottom:12px;">
          <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:#F5F0EE;line-height:1;">${iv.mon} ${iv.day}</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><i class="fas fa-clock" style="color:var(--red-bright);margin-right:3px;"></i>${iv.time}</div>
        </div>
        <div class="fc-chips" style="flex-direction:column;gap:4px;">${typeBadge}${detailChip}</div>
        <div class="fc-footer" style="margin-top:12px;">
          <button class="jr-btn" style="font-size:11px;" onclick="showToast('Reschedule','fa-calendar')">Reschedule</button>
          <button class="jr-apply" onclick="showToast('Message ${escapeHtml(iv.name)}','fa-envelope')">Message</button>
        </div>
      </div>`;
    }).join('');
  }
  function escapeHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

  // ── SEARCH ──
  let activeFilter = null;
  function pillClick(id) {
    const pill = document.getElementById('pill-'+id);
    if (activeFilter === id) {
      activeFilter = null;
      pill.classList.remove('active');
      if (id==='post') { window.location.href='employer_manageJobs.php?postjob=1'; return; }
      renderJobs(jobsData);
      renderApplicants(applicantsData);
      return;
    }
    document.querySelectorAll('.qf-pill').forEach(p=>p.classList.remove('active'));
    activeFilter = id;
    pill.classList.add('active');
    if (id==='post') { window.location.href='employer_manageJobs.php?postjob=1'; return; }
    if (id==='pending') { renderApplicants(applicantsData.filter(a=>a.status==='Pending')); renderJobs(jobsData); }
    else if (id==='shortlisted') { renderApplicants(applicantsData.filter(a=>a.status==='Shortlisted')); renderJobs(jobsData); }
    else if (id==='interviews') { document.getElementById('section-interviews').scrollIntoView({behavior:'smooth'}); return; }
    else if (id==='fulltime') { renderJobs(jobsData.filter(j=>j.type==='Full-time')); renderApplicants(applicantsData); }
    else if (id==='remote') { renderJobs(jobsData.filter(j=>j.setup==='Remote')); renderApplicants(applicantsData); }
  }

  const _guard_searchBtn = document.getElementById('searchBtn'); if (_guard_searchBtn) _guard_searchBtn.addEventListener('click', () => {
    const kw = document.getElementById('searchInput').value.trim().toLowerCase();
    if (!kw) { renderJobs(jobsData); renderApplicants(applicantsData); return; }
    renderJobs(jobsData.filter(j => j.title.toLowerCase().includes(kw) || j.tags.some(t=>t.toLowerCase().includes(kw))));
    renderApplicants(applicantsData.filter(a => a.name.toLowerCase().includes(kw) || a.job.toLowerCase().includes(kw)));
  });
  const _guard_searchInput = document.getElementById('searchInput'); if (_guard_searchInput) _guard_searchInput.addEventListener('keyup', e => {
    if (e.key==='Enter') document.getElementById('searchBtn').click();
  });

  // ── THEME ──
  function setTheme(t) {
    document.body.classList.toggle('light', t==='light');
    document.body.classList.toggle('dark', t!=='light');
    document.querySelectorAll('#themeToggle i').forEach(i => i.className = t==='light' ? 'fas fa-sun' : 'fas fa-moon');
    localStorage.setItem('ac-theme', t);
  }
  const _guard_themeToggle = document.getElementById('themeToggle'); if (_guard_themeToggle) _guard_themeToggle.addEventListener('click', () =>
    setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));
  const _urlTheme = new URLSearchParams(window.location.search).get('theme');
  const _initTheme = _urlTheme || localStorage.getItem('ac-theme') || 'dark';
  if (_urlTheme) localStorage.setItem('ac-theme', _urlTheme);
  setTheme(_initTheme);

  // ── HAMBURGER ──
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    const open = mobileMenu.classList.toggle('open');
    hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
  });

  // ── PROFILE DROPDOWN ──
  const _guard_profileToggle = document.getElementById('profileToggle'); if (_guard_profileToggle) _guard_profileToggle.addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('profileDropdown').classList.toggle('open');
  });

  // ── NOTIF PANEL (handled by chat system sidebar) ──
  function closeNotif() {}

  // ── CLICK OUTSIDE ──
  document.addEventListener('click', e => {
    if (!mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
    if (!document.getElementById('profileToggle').contains(e.target) && !document.getElementById('profileDropdown').contains(e.target))
      document.getElementById('profileDropdown').classList.remove('open');
  });

  // ── MODAL ──

  // ── POST JOB → redirect to Manage Jobs ──
  function openPostJob() { window.location.href='employer_manageJobs.php?postjob=1'; }

  const _guard_closeModal = document.getElementById('closeModal'); if (_guard_closeModal) _guard_closeModal.addEventListener('click', () => document.getElementById('jobModal').classList.remove('open'));
  const _guard_jobModal = document.getElementById('jobModal'); if (_guard_jobModal) _guard_jobModal.addEventListener('click', e => { if(e.target===document.getElementById('jobModal')) document.getElementById('jobModal').classList.remove('open'); });

  // ── TOAST ──
  function showToast(msg, icon) {
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2400);
  }

  // ── INIT ──
  renderJobs(jobsData);
  renderApplicants(applicantsData);
  renderInterviews();
  (function(){var h=new Date().getHours();var el=document.getElementById('greetingText');if(el)el.textContent=h<12?'Good morning':h<18?'Good afternoon':'Good evening';})();

</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>