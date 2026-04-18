<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'admin') {
    header('Location: ../auth/antcareers_login.php');
    exit;
}
$adminId  = (int)$_SESSION['user_id'];
$fullName = trim((string)($_SESSION['user_name'] ?? 'Admin'));
$nameParts = preg_split('/\s+/', $fullName) ?: [];
$initials  = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0],0,1).substr($nameParts[1],0,1))
    : strtoupper(substr($fullName,0,2));
$db = getDB();

try {
    $pendingCompanies = $db->query(
      "SELECT u.id, u.full_name, u.email, u.company_name, u.created_at, u.account_status,
              cp.industry, cp.company_size, cp.website, cp.about AS description
       FROM users u
       LEFT JOIN company_profiles cp ON cp.user_id = u.id
       WHERE LOWER(u.account_type) = 'employer' AND u.account_status = 'pending_approval'
       ORDER BY u.created_at DESC"
    )->fetchAll();
} catch (Throwable) { $pendingCompanies = []; }

try {
    $approvedCompanies = $db->query(
      "SELECT u.id, u.full_name, u.email, u.company_name, u.created_at, u.account_status,
              cp.industry, cp.is_verified
       FROM users u
       LEFT JOIN company_profiles cp ON cp.user_id = u.id
       WHERE LOWER(u.account_type) = 'employer' AND u.account_status = 'active'
       ORDER BY u.created_at DESC LIMIT 50"
    )->fetchAll();
} catch (Throwable) { $approvedCompanies = []; }

try {
    $rejectedCompanies = $db->query(
      "SELECT u.id, u.full_name, u.email, u.company_name, u.created_at, u.account_status,
              u.status_reason, cp.industry
       FROM users u
       LEFT JOIN company_profiles cp ON cp.user_id = u.id
       WHERE LOWER(u.account_type) = 'employer' AND u.account_status IN ('banned','suspended')
       ORDER BY u.created_at DESC LIMIT 50"
    )->fetchAll();
} catch (Throwable) { $rejectedCompanies = []; }

$pendingCount  = count($pendingCompanies);
$approvedCount = count($approvedCompanies);
$rejectedCount = count($rejectedCompanies);

$unread = 0;
try { $unread = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId} AND is_read=0")->fetchColumn(); } catch (Throwable) {}

$pendingJobsCount = 0;
try { $pendingJobsCount = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'")->fetchColumn(); } catch (Throwable) {}
$totalRecruiters = 0;
try { $totalRecruiters = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'")->fetchColumn(); } catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Company Approval</title>
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
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; letter-spacing:0.01em; }
    .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
    .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted); flex-shrink:0; text-decoration:none; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); }
    .admin-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:rgba(209,61,44,0.12); border:1px solid rgba(209,61,44,0.25); border-radius:100px; font-size:11px; font-weight:700; color:var(--red-pale); letter-spacing:0.04em; white-space:nowrap; }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-btn:hover { background:var(--soil-card); }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--red-deep),var(--red-vivid)); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--red-pale); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; text-decoration:none; }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }

    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 80px; position:relative; z-index:1; }
    .page-header { padding:32px 0 24px; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .page-title span { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); }

    .content-layout { display:block; }

    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:10px; }
    .sec-title i { color:var(--red-bright); font-size:16px; }

    /* Tabs */
    .tab-bar { display:flex; gap:6px; margin-bottom:24px; }
    .tab-btn { padding:8px 18px; border-radius:7px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; display:flex; align-items:center; gap:7px; }
    .tab-btn:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.4); }
    .tab-btn.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .tab-count { font-size:10px; font-weight:700; padding:1px 6px; border-radius:8px; background:rgba(209,61,44,0.18); color:var(--red-pale); }
    .tab-count.amber { background:rgba(212,148,58,0.18); color:var(--amber); }
    .tab-count.green { background:rgba(76,175,112,0.12); color:#6ccf8a; }
    .tab-section { display:none; }
    .tab-section.active { display:block; }

    /* Company cards */
    .company-list { display:flex; flex-direction:column; gap:10px; }
    .company-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:20px 22px; transition:all 0.18s; }
    .company-card:hover { border-color:rgba(209,61,44,0.4); background:var(--soil-hover); }
    .cc-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:10px; }
    .cc-info { flex:1; min-width:0; }
    .cc-name { font-family:var(--font-display); font-size:16px; font-weight:700; color:#F5F0EE; margin-bottom:3px; }
    .cc-reg { font-size:12px; color:var(--text-muted); margin-bottom:6px; }
    .cc-meta { display:flex; flex-wrap:wrap; gap:10px; font-size:12px; color:var(--text-muted); margin-bottom:8px; }
    .cc-meta span { display:flex; align-items:center; gap:4px; }
    .cc-meta i { color:var(--red-bright); font-size:10px; }
    .cc-desc { font-size:12px; color:var(--text-muted); line-height:1.6; margin-top:6px; border-top:1px solid var(--soil-line); padding-top:8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .cc-actions { display:flex; gap:8px; align-items:flex-start; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }
    .btn { padding:7px 15px; border-radius:6px; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; border:1px solid transparent; transition:0.18s; white-space:nowrap; }
    .btn-approve { background:rgba(76,175,112,0.12); border-color:rgba(76,175,112,0.3); color:#6ccf8a; }
    .btn-approve:hover { background:rgba(76,175,112,0.22); border-color:rgba(76,175,112,0.5); }
    .btn-reject { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .btn-reject:hover { background:rgba(209,61,44,0.18); border-color:rgba(209,61,44,0.4); }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.amber { background:rgba(212,148,58,.08); color:var(--amber); border-color:rgba(212,148,58,.2); }
    .chip.verified { background:rgba(74,144,217,.08); color:#7ab8f0; border-color:rgba(74,144,217,.18); }

    /* Simple table for approved/rejected */
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; padding:10px 14px; text-align:left; border-bottom:1px solid var(--soil-line); }
    .data-table td { padding:12px 14px; font-size:13px; color:var(--text-mid); border-bottom:1px solid rgba(53,46,46,0.5); vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--soil-hover); }
    .table-wrap { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .td-name { font-weight:700; color:#F5F0EE; font-size:13px; }
    .td-reason { font-size:11px; color:var(--text-muted); font-style:italic; max-width:260px; }

    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    .modal-overlay { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:32px; max-width:480px; width:92%; position:relative; animation:modalIn 0.2s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6); }
    @keyframes modalIn { from{opacity:0;transform:scale(0.97) translateY(8px)} to{opacity:1;transform:scale(1)} }
    .modal-close { position:absolute; top:18px; right:18px; width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-size:13px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; }
    .modal-close:hover { color:#F5F0EE; border-color:var(--red-mid); }
    .modal-title { font-family:var(--font-display); font-size:18px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .modal-sub { font-size:13px; color:var(--text-muted); margin-bottom:18px; }
    .modal-label { font-size:12px; font-weight:700; color:var(--text-mid); margin-bottom:6px; letter-spacing:0.03em; }
    .modal-textarea { width:100%; padding:12px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; resize:vertical; min-height:96px; outline:none; transition:0.2s; }
    .modal-textarea:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .modal-actions { display:flex; gap:10px; margin-top:18px; justify-content:flex-end; }
    .btn-cancel { padding:9px 18px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; }
    .btn-cancel:hover { color:#F5F0EE; }
    .btn-confirm-reject { padding:9px 18px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.18s; }
    .btn-confirm-reject:hover { background:var(--red-bright); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    body.light { --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FFF4E0; --amber:#B8620A; }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .profile-name { color:#1A0A09; }
    body.light .company-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .company-card:hover { background:#FEF0EE; }
    body.light .cc-name { color:#1A0A09; }
    body.light .table-wrap { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-textarea { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .sec-title { color:#1A0A09; }

    @media(max-width:760px) { .nav-links{display:none} .page-shell{padding:0 16px 60px} .cc-top{flex-direction:column} .cc-actions{flex-direction:row} }
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
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="admin_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
      <a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
      <a class="nav-link active" href="admin_companies.php"><i class="fas fa-building"></i> Companies</a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
      <a class="nav-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
      <a class="nav-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <a class="notif-btn-nav" href="admin_notifications.php" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if($unread > 0): ?><span class="badge"><?php echo $unread; ?></span><?php endif; ?>
      </a>
      <span class="admin-pill"><i class="fas fa-shield-alt"></i> Admin</span>
      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle">
          <div class="profile-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES); ?></div>
          <div>
            <div class="profile-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></div>
            <div class="profile-role">Administrator</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></div>
            <div class="pdh-sub"><i class="fas fa-shield-alt" style="margin-right:4px;"></i>Administrator</div>
          </div>
          <a class="pd-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
          <a class="pd-item" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
          <a class="pd-item" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-building" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i>Company <span>Approval</span></div>
    <div class="page-sub"><?php echo $pendingCount; ?> company registration<?php echo $pendingCount !== 1 ? 's' : ''; ?> pending review.</div>
  </div>

  <div class="content-layout">

    <!-- MAIN -->
    <main class="anim">
      <!-- SEARCH -->
      <div style="margin-bottom:16px;position:relative;max-width:420px;">
        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none;"></i>
        <input id="companySearch" type="text" placeholder="Search companies, email, industry…"
          style="width:100%;padding:9px 12px 9px 36px;border-radius:8px;border:1px solid var(--soil-line);background:var(--soil-hover);color:#F5F0EE;font-family:var(--font-body);font-size:14px;outline:none;box-sizing:border-box;"
          oninput="filterCompanies(this.value)">
      </div>
      <!-- TABS -->
      <div class="tab-bar">
        <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending')">
          <i class="fas fa-hourglass-half"></i> Pending
          <span class="tab-count" id="pending-count-badge"><?php echo $pendingCount; ?></span>
        </button>
        <button class="tab-btn" data-tab="approved" onclick="switchTab('approved')">
          <i class="fas fa-check-circle"></i> Approved
          <span class="tab-count green"><?php echo $approvedCount; ?></span>
        </button>
        <button class="tab-btn" data-tab="rejected" onclick="switchTab('rejected')">
          <i class="fas fa-times-circle"></i> Rejected
          <span class="tab-count amber"><?php echo $rejectedCount; ?></span>
        </button>
      </div>

      <!-- PENDING TAB -->
      <div class="tab-section active" id="tab-pending">
        <div class="company-list" id="pending-list">
          <?php if (empty($pendingCompanies)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><div>No pending company registrations.</div></div>
          <?php else: ?>
            <?php foreach ($pendingCompanies as $c): ?>
            <div class="company-card" id="company-row-<?php echo (int)$c['id']; ?>" data-search="<?php echo strtolower(htmlspecialchars((string)($c['company_name'] ?? '').' '.(string)($c['full_name'] ?? '').' '.(string)($c['email'] ?? '').' '.(string)($c['industry'] ?? ''), ENT_QUOTES)); ?>">
              <div class="cc-top">
                <div class="cc-info">
                  <div class="cc-name"><?php echo htmlspecialchars((string)($c['company_name'] ?? ''), ENT_QUOTES); ?></div>
                  <div class="cc-reg">Registered as: <?php echo htmlspecialchars((string)($c['full_name'] ?? ''), ENT_QUOTES); ?> &middot; <?php echo htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES); ?></div>
                  <div class="cc-meta">
                    <?php if (!empty($c['industry'])): ?><span><i class="fas fa-industry"></i> <?php echo htmlspecialchars((string)$c['industry'], ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if (!empty($c['company_size'])): ?><span><i class="fas fa-users"></i> <?php echo htmlspecialchars((string)$c['company_size'], ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if (!empty($c['website'])): ?><span><i class="fas fa-globe"></i> <?php echo htmlspecialchars((string)$c['website'], ENT_QUOTES); ?></span><?php endif; ?>
                    <span><i class="fas fa-calendar"></i> <?php echo htmlspecialchars(date('M j, Y', strtotime((string)($c['created_at'] ?? 'now'))), ENT_QUOTES); ?></span>
                  </div>
                  <?php if (!empty($c['description'])): ?>
                    <div class="cc-desc"><?php echo htmlspecialchars((string)$c['description'], ENT_QUOTES); ?></div>
                  <?php endif; ?>
                </div>
                <div class="cc-actions">
                  <button class="btn btn-approve" onclick="approveCompany(<?php echo (int)$c['id']; ?>, this)"><i class="fas fa-check"></i> Approve</button>
                  <button class="btn btn-reject" onclick="openRejectModal(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars((string)($c['company_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-times"></i> Reject</button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- APPROVED TAB -->
      <div class="tab-section" id="tab-approved">
        <?php if (empty($approvedCompanies)): ?>
          <div class="empty-state"><i class="fas fa-building"></i><div>No approved companies yet.</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr>
              <th>Company</th><th>Contact</th><th>Industry</th><th>Registered</th><th>Status</th>
            </tr></thead>
            <tbody>
              <?php foreach ($approvedCompanies as $c): ?>
              <tr data-search="<?php echo strtolower(htmlspecialchars((string)($c['company_name'] ?? '').' '.(string)($c['full_name'] ?? '').' '.(string)($c['email'] ?? '').' '.(string)($c['industry'] ?? ''), ENT_QUOTES)); ?>">
                <td><div class="td-name"><?php echo htmlspecialchars((string)($c['company_name'] ?? ''), ENT_QUOTES); ?></div><div style="font-size:11px;color:var(--text-muted)"><?php echo htmlspecialchars((string)($c['full_name'] ?? ''), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)($c['industry'] ?? '—'), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($c['created_at'] ?? 'now'))), ENT_QUOTES); ?></td>
                <td>
                  <?php if (!empty($c['is_verified'])): ?>
                    <span class="chip verified"><i class="fas fa-check-circle"></i> Verified</span>
                  <?php else: ?>
                    <span class="chip green">Active</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- REJECTED TAB -->
      <div class="tab-section" id="tab-rejected">
        <?php if (empty($rejectedCompanies)): ?>
          <div class="empty-state"><i class="fas fa-times-circle"></i><div>No rejected companies.</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr>
              <th>Company</th><th>Email</th><th>Industry</th><th>Reason</th><th>Date</th>
            </tr></thead>
            <tbody>
              <?php foreach ($rejectedCompanies as $c): ?>
              <tr data-search="<?php echo strtolower(htmlspecialchars((string)($c['company_name'] ?? '').' '.(string)($c['full_name'] ?? '').' '.(string)($c['email'] ?? '').' '.(string)($c['industry'] ?? ''), ENT_QUOTES)); ?>">
                <td><div class="td-name"><?php echo htmlspecialchars((string)($c['company_name'] ?? ''), ENT_QUOTES); ?></div><div style="font-size:11px;color:var(--text-muted)"><?php echo htmlspecialchars((string)($c['full_name'] ?? ''), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)($c['industry'] ?? '—'), ENT_QUOTES); ?></td>
                <td><div class="td-reason"><?php echo htmlspecialchars((string)($c['status_reason'] ?? '—'), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($c['created_at'] ?? 'now'))), ENT_QUOTES); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeRejectModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title">Reject Company</div>
    <div class="modal-sub" id="rejectModalSub">Provide a reason for rejecting this registration.</div>
    <div class="modal-label">Reason (optional)</div>
    <textarea class="modal-textarea" id="rejectReason" placeholder="e.g. Incomplete information, suspected fraud…"></textarea>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
      <button class="btn-confirm-reject" id="rejectSubmitBtn" onclick="submitReject()"><i class="fas fa-times"></i> Confirm Reject</button>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';
let rejectTargetId = null;

async function adminAction(action, data) {
  const res = await fetch('api_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, csrf_token: CSRF, ...data })
  });
  return res.json();
}

function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
  document.querySelectorAll('.tab-section').forEach(s => s.classList.toggle('active', s.id === 'tab-' + name));
  filterCompanies(document.getElementById('companySearch').value);
}

function filterCompanies(q) {
  q = q.toLowerCase().trim();
  const activeSection = document.querySelector('.tab-section.active');
  if (!activeSection) return;
  const items = activeSection.querySelectorAll('[data-search]');
  let visible = 0;
  items.forEach(el => {
    const match = !q || el.dataset.search.includes(q);
    el.style.display = match ? '' : 'none';
    if (match) visible++;
  });
  let noRes = activeSection.querySelector('.no-search-result');
  if (!q || visible > 0) { if (noRes) noRes.remove(); }
  else {
    if (!noRes) {
      noRes = document.createElement('div');
      noRes.className = 'no-search-result';
      noRes.style.cssText = 'text-align:center;padding:32px;color:var(--text-muted);font-size:14px;';
      noRes.innerHTML = '<i class="fas fa-search" style="font-size:28px;margin-bottom:10px;display:block;opacity:0.4;"></i>No companies match your search.';
      activeSection.appendChild(noRes);
    }
  }
}

// Switch to tab from URL param
(function() {
  const tab = new URLSearchParams(location.search).get('tab');
  if (tab && ['pending','approved','rejected'].includes(tab)) switchTab(tab);
})();

async function approveCompany(userId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving…';
  const r = await adminAction('approve_company', { user_id: userId });
  if (r.success) {
    const row = document.getElementById('company-row-' + userId);
    if (row) { row.style.opacity = '0'; row.style.transform = 'translateX(20px)'; row.style.transition = '0.3s'; setTimeout(() => row.remove(), 320); }
    let cnt = parseInt(document.getElementById('pending-count-badge').textContent, 10) - 1;
    document.getElementById('pending-count-badge').textContent = Math.max(0, cnt);
    showToast(r.message || 'Company approved.', 'fa-check-circle');
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Approve';
    showToast(r.message || 'Error approving.', 'fa-exclamation-circle');
  }
}

function openRejectModal(userId, name) {
  rejectTargetId = userId;
  document.getElementById('rejectModalSub').textContent = 'Provide a reason for rejecting "' + name + '".';
  document.getElementById('rejectReason').value = '';
  document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() {
  document.getElementById('rejectModal').classList.remove('open');
  rejectTargetId = null;
}

async function submitReject() {
  if (!rejectTargetId) return;
  const reason = document.getElementById('rejectReason').value.trim();
  const btn = document.getElementById('rejectSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting…';
  const r = await adminAction('reject_company', { user_id: rejectTargetId, reason });
  if (r.success) {
    const row = document.getElementById('company-row-' + rejectTargetId);
    if (row) { row.style.opacity = '0'; row.style.transition = '0.3s'; setTimeout(() => row.remove(), 320); }
    let cnt = parseInt(document.getElementById('pending-count-badge').textContent, 10) - 1;
    document.getElementById('pending-count-badge').textContent = Math.max(0, cnt);
    closeRejectModal();
    showToast(r.message || 'Company rejected.', 'fa-times-circle');
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-times"></i> Confirm Reject';
    showToast(r.message || 'Error rejecting.', 'fa-exclamation-circle');
  }
}

// Close modal on overlay click
document.getElementById('rejectModal').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});

// Theme toggle
function setTheme(t) {
  document.body.classList.toggle('light', t === 'light');
  document.getElementById('themeToggle').querySelector('i').className = t === 'light' ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('ac-theme', t);
}
document.getElementById('themeToggle').addEventListener('click', () =>
  setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));
setTheme(localStorage.getItem('ac-theme') || 'dark');

// Profile dropdown
document.getElementById('profileToggle').addEventListener('click', e => {
  e.stopPropagation();
  document.getElementById('profileDropdown').classList.toggle('open');
});
document.addEventListener('click', e => {
  if (!document.getElementById('profileToggle').contains(e.target))
    document.getElementById('profileDropdown').classList.remove('open');
});
</script>
</body>
</html>
