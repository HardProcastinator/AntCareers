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
require_once dirname(__DIR__) . '/includes/admin_notif_panel.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$search     = trim($_GET['q'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$where  = [];
$params = [];

if ($search !== '') {
    $where[]      = "(u.full_name LIKE :q OR al.description LIKE :q)";
    $params[':q'] = "%{$search}%";
}
if ($actionType !== '') {
    $where[]       = "al.action_type = :at";
    $params[':at'] = $actionType;
}
if ($dateFrom !== '') {
    $where[]       = "al.created_at >= :df";
    $params[':df'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]       = "al.created_at <= :dt";
    $params[':dt'] = $dateTo . ' 23:59:59';
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = 0;
try {
    $totalStmt = $db->prepare("SELECT COUNT(*) FROM activity_logs al LEFT JOIN users u ON u.id = al.user_id {$whereStr}");
    $totalStmt->execute($params);
    $total = (int)$totalStmt->fetchColumn();
} catch (Throwable) { $total = 0; }

$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

$logs = [];
try {
    $stmt = $db->prepare(
      "SELECT al.id, al.action_type, al.entity_type, al.entity_id, al.description,
              al.ip_address, al.created_at,
              u.full_name AS user_name, u.account_type AS user_role,
              a.full_name AS actor_name
       FROM activity_logs al
       LEFT JOIN users u ON u.id = al.user_id
       LEFT JOIN users a ON a.id = al.actor_id
       {$whereStr}
       ORDER BY al.created_at DESC
       LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Throwable) { $logs = []; }

$actionTypes = [];
try {
    $actionTypes = $db->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable) { $actionTypes = []; }


$pendingCompanies = 0;
try { $pendingCompanies = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'")->fetchColumn(); } catch (Throwable) {}
$pendingJobs = 0;
try { $pendingJobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'")->fetchColumn(); } catch (Throwable) {}
$totalRecruiters = 0;
try { $totalRecruiters = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'")->fetchColumn(); } catch (Throwable) {}

function actionBadgeClass(string $type): string {
    if (in_array($type, ['company_approved','user_unsuspended','user_unbanned'], true)) return 'green';
    if (in_array($type, ['company_rejected','user_suspended','user_banned'], true)) return 'red';
    if (in_array($type, ['job_approved','job_rejected','job_removed','job_submitted'], true)) return 'amber';
    if ($type === 'application_made') return 'blue';
    return 'muted';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Activity Logs</title>
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
    .theme-btn{ width:36px;height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; flex-shrink:0; }
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

    /* Filter bar */
    .filter-bar { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 18px; margin-bottom:20px; }
    .filter-form { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
    .filter-group { display:flex; flex-direction:column; gap:5px; }
    .filter-label { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; }
    .filter-input { padding:8px 12px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:0.2s; }
    .filter-input:focus { border-color:var(--red-vivid); }
    .filter-input::placeholder { color:var(--text-muted); }
    .search-input { padding:8px 13px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:0.2s; min-width:220px; }
    .search-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-input::placeholder { color:var(--text-muted); }
    .filter-select { padding:8px 12px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; cursor:pointer; min-width:160px; }
    .filter-submit { padding:8px 18px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.18s; align-self:flex-end; }
    .filter-submit:hover { background:var(--red-bright); }
    @media (min-width:761px) {
      .filter-form { display:grid; grid-template-columns:minmax(260px,2fr) minmax(220px,1.4fr) minmax(180px,1fr) minmax(180px,1fr) auto; gap:12px; align-items:end; }
      .filter-group { min-width:0; }
      .search-input, .filter-input, .filter-select { width:100%; min-width:0; }
      .filter-submit { height:40px; align-self:auto; }
    }

    /* Table */
    .table-wrap { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; min-width:860px; }
    .data-table th { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; padding:10px 14px; text-align:left; border-bottom:1px solid var(--soil-line); white-space:nowrap; }
    .data-table td { padding:11px 14px; font-size:12px; color:var(--text-mid); border-bottom:1px solid rgba(53,46,46,0.5); vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--soil-hover); }
    .td-ts { font-size:11px; color:var(--text-muted); white-space:nowrap; }
    .td-user { font-weight:600; color:#F5F0EE; font-size:12px; }
    .td-desc { max-width:300px; line-height:1.5; }
    .td-ip { font-family:monospace; font-size:11px; color:var(--text-muted); }
    .chip { font-size:10px; font-weight:700; padding:2px 7px; border-radius:3px; white-space:nowrap; text-transform:uppercase; letter-spacing:0.04em; }
    .chip-green { background:rgba(76,175,112,.1); color:#6ccf8a; border:1px solid rgba(76,175,112,.2); }
    .chip-red { background:rgba(209,61,44,.1); color:var(--red-pale); border:1px solid rgba(209,61,44,.2); }
    .chip-amber { background:rgba(212,148,58,.1); color:var(--amber); border:1px solid rgba(212,148,58,.2); }
    .chip-blue { background:rgba(74,144,217,.1); color:#7ab8f0; border:1px solid rgba(74,144,217,.18); }
    .chip-muted { background:var(--soil-hover); color:var(--text-muted); border:1px solid var(--soil-line); }

    /* Pagination */
    .pagination { display:flex; align-items:center; gap:6px; margin-top:20px; flex-wrap:wrap; }
    .page-link { padding:6px 13px; border-radius:6px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; text-decoration:none; }
    .page-link:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.4); }
    .page-link.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .page-link.disabled { opacity:0.4; pointer-events:none; }
    .page-info { font-size:12px; color:var(--text-muted); padding:0 6px; }

    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    body.light { --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FFF4E0; --amber:#B8620A; }
    body.light .navbar { background:rgba(249,245,244,0.97); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .theme-btn:hover { color:#1A0A09; border-color:var(--red-vivid); background:#FEF0EE; }
    body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .notif-btn-nav:hover { color:#1A0A09; border-color:var(--red-vivid); background:#FEF0EE; }
    body.light .notif-btn-nav .badge { border-color:#F9F5F4; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-btn:hover { background:#FEF0EE; border-color:var(--red-vivid); }
    body.light .profile-name { color:#1A0A09; }
    body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
    body.light .page-title { color:#1A0A09; }
    body.light .filter-bar, body.light .table-wrap { background:#FFFFFF; border-color:#E0CECA; }
    body.light .filter-input, body.light .filter-select { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .search-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .page-link { background:#F5EEEC; border-color:#E0CECA; }
    body.light .page-link:hover { color:#1A0A09; border-color:rgba(209,61,44,0.35); }
    body.light .td-user { color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; }

    .hamburger { display:none; width:36px;height:36px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover,.mobile-link.active { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }
    body.light .mobile-menu { background:rgba(249,245,244,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover,body.light .mobile-link.active { background:#FEF0EE; color:#1A0A09; }
    @media(max-width:760px) {
      .nav-links{display:none}
      .hamburger{display:flex}
      .profile-wrap{display:none}
      .nav-inner{padding:0 10px}
      .page-shell{padding:0 16px 60px}
      .theme-btn,.notif-btn-nav{width:32px;height:32px;font-size:13px}
      .nav-right{gap:6px}
      .filter-form{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end}
      .filter-group{min-width:0}
      .filter-group:nth-child(1),.filter-group:nth-child(2){grid-column:span 2}
      .search-input,.filter-input,.filter-select{width:100%;min-width:0}
      .filter-submit{width:100%;min-height:40px;grid-column:span 2;align-self:auto}
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
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="admin_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
      <a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
      <a class="nav-link" href="admin_companies.php"><i class="fas fa-building"></i> Companies<?php if($adminPendingCompanies>0): ?> <span style="background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminPendingCompanies; ?></span><?php endif; ?></a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs<?php if($adminPendingJobs>0): ?> <span style="background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminPendingJobs; ?></span><?php endif; ?></a>
      <a class="nav-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
      <a class="nav-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <button class="notif-btn-nav" id="navNotifBtn" onclick="toggleAdminNotifPanel()" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($adminUnreadCount > 0): ?><span class="badge" id="adminNotifBadge"><?php echo $adminUnreadCount; ?></span><?php endif; ?>
      </button>
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
      <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</nav>
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link" href="admin_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
  <a class="mobile-link" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
  <a class="mobile-link" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval</a>
  <a class="mobile-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link active" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
  <a class="mobile-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
  <a class="mobile-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
  <a class="mobile-link" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>
<script>(function(){var h=document.getElementById('hamburger'),m=document.getElementById('mobileMenu');function syncMobileMenuPosition(){var nav=document.getElementById('mainNavbar')||document.querySelector('.navbar');if(!m||!nav)return;var rect=nav.getBoundingClientRect();var top=Math.max(0,Math.round(rect.bottom));m.style.top=top+'px';m.style.maxHeight='calc(100dvh - '+top+'px)';}window.addEventListener('scroll',syncMobileMenuPosition,{passive:true});window.addEventListener('resize',syncMobileMenuPosition);syncMobileMenuPosition();if(h&&m){h.addEventListener('click',function(e){e.stopPropagation();syncMobileMenuPosition();var o=m.classList.toggle('open');h.querySelector('i').className=o?'fas fa-times':'fas fa-bars';});document.addEventListener('click',function(e){if(!m.contains(e.target)&&e.target!==h){m.classList.remove('open');h.querySelector('i').className='fas fa-bars';}});}})();</script>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-history" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i>Activity <span>Logs</span></div>
    <div class="page-sub"><?php echo number_format($total); ?> log entr<?php echo $total !== 1 ? 'ies' : 'y'; ?> found. Page <?php echo $page; ?> of <?php echo $totalPages; ?>.</div>
  </div>

  <div class="content-layout">

    <!-- MAIN -->
    <main class="anim">
      <!-- FILTER -->
      <form class="filter-bar" method="GET" action="">
        <div class="filter-form">
          <div class="filter-group">
            <div class="filter-label">Search</div>
            <input class="search-input" type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" placeholder="User name or description…" style="min-width:200px;">
          </div>
          <div class="filter-group">
            <div class="filter-label">Action Type</div>
            <select class="filter-select" name="action_type">
              <option value="">All types</option>
              <?php foreach ($actionTypes as $at): ?>
              <option value="<?php echo htmlspecialchars((string)$at, ENT_QUOTES); ?>" <?php echo $actionType === $at ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$at, ENT_QUOTES); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="filter-group">
            <div class="filter-label">From</div>
            <input class="filter-input" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES); ?>">
          </div>
          <div class="filter-group">
            <div class="filter-label">To</div>
            <input class="filter-input" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES); ?>">
          </div>
          <button class="filter-submit" type="submit"><i class="fas fa-filter"></i> Filter</button>
        </div>
      </form>

      <!-- TABLE -->
      <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fas fa-history"></i><div>No activity logs match your filters.</div></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Role</th>
            <th>Actor</th>
            <th>Action Type</th>
            <th>Entity</th>
            <th>Description</th>
            <th>IP</th>
          </tr></thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="td-ts"><?php echo htmlspecialchars(date('M j, Y H:i', strtotime((string)($log['created_at'] ?? 'now'))), ENT_QUOTES); ?></td>
              <td class="td-user"><?php echo htmlspecialchars((string)($log['user_name'] ?? '—'), ENT_QUOTES); ?></td>
              <td><?php if (!empty($log['user_role'])): ?><span class="chip chip-muted"><?php echo htmlspecialchars((string)$log['user_role'], ENT_QUOTES); ?></span><?php else: ?>—<?php endif; ?></td>
              <td><?php echo htmlspecialchars((string)($log['actor_name'] ?? '—'), ENT_QUOTES); ?></td>
              <td>
                <?php $cls = 'chip-' . actionBadgeClass((string)($log['action_type'] ?? '')); ?>
                <span class="chip <?php echo $cls; ?>"><?php echo htmlspecialchars((string)($log['action_type'] ?? ''), ENT_QUOTES); ?></span>
              </td>
              <td><?php if (!empty($log['entity_type'])): ?><span style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars((string)$log['entity_type'], ENT_QUOTES); ?> #<?php echo (int)($log['entity_id'] ?? 0); ?></span><?php else: ?>—<?php endif; ?></td>
              <td class="td-desc"><?php echo htmlspecialchars((string)($log['description'] ?? ''), ENT_QUOTES); ?></td>
              <td class="td-ip"><?php echo htmlspecialchars((string)($log['ip_address'] ?? '—'), ENT_QUOTES); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <?php if ($totalPages > 1): ?>
      <?php
        $baseUrl = '?' . http_build_query(array_filter(['q' => $search, 'action_type' => $actionType, 'date_from' => $dateFrom, 'date_to' => $dateTo]));
        $sep = strpos($baseUrl, '?') !== false && strlen($baseUrl) > 1 ? '&' : '?';
      ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a class="page-link" href="<?php echo $baseUrl . '&page=' . ($page - 1); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
        <?php else: ?>
          <span class="page-link disabled"><i class="fas fa-chevron-left"></i> Prev</span>
        <?php endif; ?>

        <?php
          $start = max(1, $page - 2);
          $end   = min($totalPages, $page + 2);
          if ($start > 1): ?><span class="page-info">…</span><?php endif;
          for ($p = $start; $p <= $end; $p++):
        ?>
          <a class="page-link <?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo $baseUrl . '&page=' . $p; ?>"><?php echo $p; ?></a>
        <?php endfor;
          if ($end < $totalPages): ?><span class="page-info">…</span><?php endif; ?>

        <?php if ($page < $totalPages): ?>
          <a class="page-link" href="<?php echo $baseUrl . '&page=' . ($page + 1); ?>">Next <i class="fas fa-chevron-right"></i></a>
        <?php else: ?>
          <span class="page-link disabled">Next <i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
        <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; <?php echo number_format($total); ?> entries</span>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</div>

<?php renderAdminNotifPanel(); ?>
<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
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
