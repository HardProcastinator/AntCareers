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

$showAll = isset($_GET['all']);

$notifWhere  = "WHERE n.user_id = :uid";
$notifParams = [':uid' => $adminId];
if (!$showAll) {
    $notifWhere .= " AND n.is_read = 0";
}

$notifs = [];
try {
    $stmt = $db->prepare(
      "SELECT n.id, n.type, n.content, n.reference_id, n.reference_type, n.is_read, n.created_at,
              u.full_name AS actor_name
       FROM notifications n
       LEFT JOIN users u ON u.id = n.actor_id
       {$notifWhere}
       ORDER BY n.created_at DESC LIMIT 100"
    );
    $stmt->execute($notifParams);
    $notifs = $stmt->fetchAll();
} catch (Throwable) { $notifs = []; }

$unreadCount = 0;
try {
    $unreadCount = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$adminId} AND is_read = 0")->fetchColumn();
} catch (Throwable) { $unreadCount = 0; }

$pendingCompanies = 0;
try { $pendingCompanies = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'")->fetchColumn(); } catch (Throwable) {}
$pendingJobs = 0;
try { $pendingJobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'")->fetchColumn(); } catch (Throwable) {}
$totalRecruiters = 0;
try { $totalRecruiters = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'")->fetchColumn(); } catch (Throwable) {}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function notifIconClass(string $type): string {
    return match($type) {
        'company_approval_request' => 'fa-building',
        'job_approval_request'     => 'fa-briefcase',
        default                    => 'fa-bell',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Admin Notifications</title>
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
    .sidebar { display:none; }
    .sidebar { position:sticky; top:72px; max-height:calc(100vh - 88px); overflow-y:auto; scrollbar-width:none; }
    .sidebar::-webkit-scrollbar { display:none; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .sidebar-head { padding:16px 18px 12px; border-bottom:1px solid var(--soil-line); }
    .sidebar-title { font-family:var(--font-body); font-size:12px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:7px; letter-spacing:0.07em; text-transform:uppercase; }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }
    .sidebar-stats { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sb-stat { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; padding:10px 12px; }
    .sb-stat-num { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; line-height:1; }
    .sb-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-top:3px; }
    .sb-nav-item { display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; border:none; background:none; font-family:var(--font-body); width:100%; text-align:left; border-bottom:1px solid var(--soil-line); text-decoration:none; }
    .sb-nav-item:last-child { border-bottom:none; }
    .sb-nav-item:hover { color:#F5F0EE; background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .sb-nav-item i { width:16px; text-align:center; font-size:12px; color:var(--red-bright); }
    .sb-badge { margin-left:auto; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; }
    .sb-badge.amber { background:var(--amber); color:#1A0A09; }
    .sb-badge.blue { background:#4A90D9; color:#fff; }

    /* Notif header */
    .notif-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
    .notif-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:10px; }
    .notif-title i { color:var(--red-bright); font-size:16px; }
    .notif-actions { display:flex; gap:8px; align-items:center; }
    .btn-mark-all { padding:7px 14px; border-radius:7px; background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.25); color:var(--red-pale); font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; transition:0.18s; }
    .btn-mark-all:hover { background:rgba(209,61,44,0.2); }
    .toggle-link { font-size:12px; font-weight:600; color:var(--red-pale); text-decoration:none; padding:7px 12px; border-radius:6px; border:1px solid var(--soil-line); background:var(--soil-hover); transition:0.18s; }
    .toggle-link:hover { border-color:rgba(209,61,44,0.35); color:var(--red-bright); }

    /* Notification cards */
    .notif-list { display:flex; flex-direction:column; gap:8px; }
    .notif-item { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 20px; display:flex; gap:14px; align-items:flex-start; transition:all 0.18s; }
    .notif-item:hover { border-color:rgba(209,61,44,0.35); background:var(--soil-hover); }
    .notif-item.unread { border-left:2px solid var(--red-vivid); }
    .notif-dot-wrap { flex-shrink:0; padding-top:2px; }
    .notif-dot { width:9px; height:9px; border-radius:50%; background:var(--red-vivid); display:block; }
    .notif-dot.read { background:var(--soil-line); }
    .notif-icon-wrap { width:38px; height:38px; border-radius:9px; background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); display:flex; align-items:center; justify-content:center; font-size:14px; color:var(--red-pale); flex-shrink:0; }
    .notif-body { flex:1; min-width:0; }
    .notif-content { font-size:13px; color:var(--text-mid); line-height:1.6; margin-bottom:5px; }
    .notif-actor { font-size:11px; color:var(--text-muted); margin-bottom:4px; }
    .notif-actor strong { color:var(--text-mid); }
    .notif-time { font-size:11px; color:var(--text-muted); font-weight:600; }
    .notif-footer { display:flex; align-items:center; gap:8px; margin-top:8px; flex-wrap:wrap; }
    .notif-link { font-size:11px; font-weight:700; color:var(--red-pale); text-decoration:none; padding:3px 9px; border-radius:4px; background:rgba(209,61,44,0.08); border:1px solid rgba(209,61,44,0.15); transition:0.15s; }
    .notif-link:hover { background:rgba(209,61,44,0.16); }
    .btn-read { font-size:11px; font-weight:700; color:var(--text-muted); background:var(--soil-hover); border:1px solid var(--soil-line); padding:3px 9px; border-radius:4px; cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .btn-read:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.3); }

    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

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
    body.light .sidebar-card, body.light .notif-item { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sb-stat { background:#F5EEEC; border-color:#E0CECA; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .notif-title { color:#1A0A09; }
    body.light .notif-item:hover { background:#FEF0EE; }
    body.light .notif-content { color:#4A2828; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; }

    @media(max-width:1060px) { .content-layout{grid-template-columns:1fr} .sidebar{position:static} }
    @media(max-width:760px) { .nav-links{display:none} .page-shell{padding:0 16px 60px} }
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
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="admin_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
      <a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
      <a class="nav-link" href="admin_companies.php"><i class="fas fa-building"></i> Companies</a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
      <a class="nav-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
      <a class="nav-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <a class="notif-btn-nav" href="admin_notifications.php" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if($unreadCount > 0): ?><span class="badge"><?php echo $unreadCount; ?></span><?php endif; ?>
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
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-bell" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i>Admin <span>Notifications</span></div>
    <div class="page-sub"><?php echo $unreadCount; ?> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>.</div>
  </div>

  <div class="content-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar anim">
      <div class="sidebar-card">
        <div class="sidebar-head">
          <div class="sidebar-title"><i class="fas fa-shield-alt"></i> Admin Panel</div>
        </div>
        <div class="sidebar-stats">
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $unreadCount; ?></div><div class="sb-stat-lbl">Unread</div></div>
          <div class="sb-stat"><div class="sb-stat-num"><?php echo count($notifs); ?></div><div class="sb-stat-lbl">Showing</div></div>
        </div>
        <a class="sb-nav-item" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="sb-nav-item" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
        <a class="sb-nav-item" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval <?php if($pendingCompanies > 0): ?><span class="sb-badge"><?php echo $pendingCompanies; ?></span><?php endif; ?></a>
        <a class="sb-nav-item" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation <?php if($pendingJobs > 0): ?><span class="sb-badge amber"><?php echo $pendingJobs; ?></span><?php endif; ?></a>
        <a class="sb-nav-item" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
        <a class="sb-nav-item" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters <span class="sb-badge blue"><?php echo $totalRecruiters; ?></span></a>
        <a class="sb-nav-item" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports &amp; Analytics</a>
        <a class="sb-nav-item active" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unreadCount > 0): ?><span class="sb-badge"><?php echo $unreadCount; ?></span><?php endif; ?></a>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="anim">
      <div class="notif-header">
        <div class="notif-title"><i class="fas fa-bell"></i> <?php echo $showAll ? 'All Notifications' : 'Unread Notifications'; ?></div>
        <div class="notif-actions">
          <?php if ($unreadCount > 0): ?>
            <button class="btn-mark-all" id="markAllBtn" onclick="markAllRead()"><i class="fas fa-check-double"></i> Mark all as read</button>
          <?php endif; ?>
          <?php if ($showAll): ?>
            <a class="toggle-link" href="admin_notifications.php"><i class="fas fa-filter"></i> Unread only</a>
          <?php else: ?>
            <a class="toggle-link" href="admin_notifications.php?all=1"><i class="fas fa-list"></i> Show all</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($notifs)): ?>
        <div class="empty-state"><i class="fas fa-bell-slash"></i><div><?php echo $showAll ? 'No notifications yet.' : 'No unread notifications. <a href="admin_notifications.php?all=1" style="color:var(--red-pale);">View all</a>'; ?></div></div>
      <?php else: ?>
        <div class="notif-list" id="notifList">
          <?php foreach ($notifs as $n): ?>
          <?php
            $nId    = (int)$n['id'];
            $isRead = !empty($n['is_read']);
            $type   = (string)($n['type'] ?? '');
            $refType = (string)($n['reference_type'] ?? '');
            $actionLink = match($refType) {
              'user' => 'admin_companies.php',
              'job'  => 'admin_jobs.php',
              default => '',
            };
          ?>
          <div class="notif-item <?php echo $isRead ? '' : 'unread'; ?>" id="notif-<?php echo $nId; ?>">
            <div class="notif-dot-wrap">
              <span class="notif-dot <?php echo $isRead ? 'read' : ''; ?>" id="dot-<?php echo $nId; ?>"></span>
            </div>
            <div class="notif-icon-wrap">
              <i class="fas <?php echo htmlspecialchars(notifIconClass($type), ENT_QUOTES); ?>"></i>
            </div>
            <div class="notif-body">
              <div class="notif-content"><?php echo htmlspecialchars((string)($n['content'] ?? ''), ENT_QUOTES); ?></div>
              <?php if (!empty($n['actor_name'])): ?>
                <div class="notif-actor">By <strong><?php echo htmlspecialchars((string)$n['actor_name'], ENT_QUOTES); ?></strong></div>
              <?php endif; ?>
              <div class="notif-time"><?php echo htmlspecialchars(timeAgo((string)($n['created_at'] ?? 'now')), ENT_QUOTES); ?></div>
              <div class="notif-footer">
                <?php if ($actionLink !== ''): ?>
                  <a class="notif-link" href="<?php echo htmlspecialchars($actionLink, ENT_QUOTES); ?>"><i class="fas fa-arrow-right"></i> View</a>
                <?php endif; ?>
                <?php if (!$isRead): ?>
                  <button class="btn-read" id="read-btn-<?php echo $nId; ?>" onclick="markRead(<?php echo $nId; ?>)"><i class="fas fa-check"></i> Mark as read</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';

async function adminAction(action, data) {
  const res = await fetch('api_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, csrf_token: CSRF, ...data })
  });
  return res.json();
}

async function markRead(notifId) {
  const r = await adminAction('mark_notification_read', { notification_id: notifId });
  if (r.success) {
    const dot = document.getElementById('dot-' + notifId);
    if (dot) { dot.classList.add('read'); }
    const btn = document.getElementById('read-btn-' + notifId);
    if (btn) btn.remove();
    const item = document.getElementById('notif-' + notifId);
    if (item) item.classList.remove('unread');
    showToast('Marked as read.', 'fa-check');
  } else {
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

async function markAllRead() {
  const btn = document.getElementById('markAllBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
  const r = await adminAction('mark_all_notifications_read', {});
  if (r.success) {
    document.querySelectorAll('.notif-dot').forEach(d => d.classList.add('read'));
    document.querySelectorAll('.btn-read').forEach(b => b.remove());
    document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('unread'));
    if (btn) btn.remove();
    showToast('All notifications marked as read.', 'fa-check-double');
    // Update badge in navbar
    const navBadge = document.querySelector('.notif-btn-nav .badge');
    if (navBadge) navBadge.remove();
  } else {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check-double"></i> Mark all as read'; }
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

function setTheme(t) {
  document.body.classList.toggle('light', t === 'light');
  document.getElementById('themeToggle').querySelector('i').className = t === 'light' ? 'fas fa-sun' : 'fas fa-moon';
  localStorage.setItem('ac-theme', t);
}
document.getElementById('themeToggle').addEventListener('click', () =>
  setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));
setTheme(localStorage.getItem('ac-theme') || 'dark');

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
