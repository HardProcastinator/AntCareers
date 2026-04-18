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

$search = trim($_GET['q'] ?? '');
$params = [];
$where  = [];

if ($search !== '') {
    $where[]      = "(u.full_name LIKE :q OR u.email LIKE :q OR cp.company_name LIKE :q)";
    $params[':q'] = "%{$search}%";
}
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$recruiters = [];
try {
    $stmt = $db->prepare(
      "SELECT u.id, u.full_name, u.email, COALESCE(u.account_status,'active') AS account_status,
              r.role AS recruiter_role, r.is_active AS recruiter_is_active, r.created_at AS added_at,
              cp.company_name, eu.full_name AS employer_name
       FROM users u
       JOIN recruiters r ON r.user_id = u.id
       LEFT JOIN company_profiles cp ON cp.id = r.company_id
       LEFT JOIN users eu ON eu.id = r.employer_id
       {$whereStr}
       ORDER BY r.created_at DESC"
    );
    $stmt->execute($params);
    $recruiters = $stmt->fetchAll();
} catch (Throwable) { $recruiters = []; }
$total = count($recruiters);

$unread = 0;
try { $unread = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId} AND is_read=0")->fetchColumn(); } catch (Throwable) {}
$pendingCompanies = 0;
try { $pendingCompanies = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'")->fetchColumn(); } catch (Throwable) {}
$pendingJobs = 0;
try { $pendingJobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'")->fetchColumn(); } catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Recruiters</title>
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

    /* Search bar */
    .search-bar-wrap { margin-bottom:20px; }
    .search-form { display:flex; gap:8px; }
    .search-input { flex:1; padding:10px 14px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:8px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:0.2s; }
    .search-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-input::placeholder { color:var(--text-muted); }
    .search-submit { padding:10px 18px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.18s; }
    .search-submit:hover { background:var(--red-bright); }

    /* Recruiter rows */
    .user-list { display:flex; flex-direction:column; gap:8px; }
    .user-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 20px; transition:all 0.18s; display:grid; grid-template-columns:1fr auto; gap:14px; align-items:center; }
    .user-row:hover { border-color:rgba(209,61,44,0.4); background:var(--soil-hover); }
    .ur-name { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:2px; }
    .ur-email { font-size:12px; color:var(--text-muted); margin-bottom:6px; }
    .ur-meta { display:flex; flex-wrap:wrap; gap:7px; align-items:center; }
    .chip { font-size:11px; font-weight:600; padding:3px 9px; border-radius:4px; white-space:nowrap; }
    .chip-active { background:rgba(76,175,112,.1); color:#6ccf8a; border:1px solid rgba(76,175,112,.2); }
    .chip-inactive { background:rgba(212,148,58,.1); color:var(--amber); border:1px solid rgba(212,148,58,.2); }
    .chip-suspended { background:rgba(212,148,58,.12); color:var(--amber); border:1px solid rgba(212,148,58,.25); }
    .chip-banned { background:rgba(209,61,44,.1); color:var(--red-pale); border:1px solid rgba(209,61,44,.2); }
    .chip-company { background:rgba(74,144,217,.08); color:#7ab8f0; border:1px solid rgba(74,144,217,.18); }
    .chip-role { background:rgba(156,39,176,.08); color:#cf8ae0; border:1px solid rgba(156,39,176,.15); }
    .chip-date { background:var(--soil-hover); color:var(--text-muted); border:1px solid var(--soil-line); }
    .ur-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
    .btn { padding:6px 13px; border-radius:6px; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; border:1px solid transparent; transition:0.18s; white-space:nowrap; }
    .btn-suspend { background:rgba(212,148,58,0.1); border-color:rgba(212,148,58,0.25); color:var(--amber); }
    .btn-suspend:hover { background:rgba(212,148,58,0.2); }
    .btn-ban { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .btn-ban:hover { background:rgba(209,61,44,0.2); }
    .btn-reinstate { background:rgba(76,175,112,0.1); border-color:rgba(76,175,112,0.25); color:#6ccf8a; }
    .btn-reinstate:hover { background:rgba(76,175,112,0.2); }

    .results-count { font-size:12px; color:var(--text-muted); margin-bottom:14px; font-weight:600; }
    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    .modal-overlay { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:32px; max-width:480px; width:92%; position:relative; animation:modalIn 0.2s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6); }
    @keyframes modalIn { from{opacity:0;transform:scale(0.97) translateY(8px)} to{opacity:1;transform:scale(1)} }
    .modal-close { position:absolute; top:18px; right:18px; width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-size:13px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; }
    .modal-close:hover { color:#F5F0EE; }
    .modal-title { font-family:var(--font-display); font-size:18px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .modal-sub { font-size:13px; color:var(--text-muted); margin-bottom:16px; }
    .modal-label { font-size:12px; font-weight:700; color:var(--text-mid); margin-bottom:6px; margin-top:12px; }
    .modal-textarea { width:100%; padding:12px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; resize:vertical; min-height:80px; outline:none; transition:0.2s; }
    .modal-textarea:focus { border-color:var(--red-vivid); }
    .modal-input { width:100%; padding:10px 13px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:0.2s; }
    .modal-input:focus { border-color:var(--red-vivid); }
    .modal-actions { display:flex; gap:10px; margin-top:18px; justify-content:flex-end; }
    .btn-cancel { padding:9px 18px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; }
    .btn-confirm-modal { padding:9px 18px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; }
    .btn-confirm-modal:hover { background:var(--red-bright); }
    .btn-confirm-modal.amber { background:var(--amber); color:#1A0A09; }

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
    body.light .sidebar-card, body.light .user-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sb-stat { background:#F5EEEC; border-color:#E0CECA; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .search-input { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }
    body.light .ur-name { color:#1A0A09; }
    body.light .user-row:hover { background:#FEF0EE; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-textarea, body.light .modal-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .chip-date { background:#F5EEEC; border-color:#E0CECA; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; }

    @media(max-width:1060px) { .content-layout{grid-template-columns:1fr} .sidebar{position:static} }
    @media(max-width:760px) { .nav-links{display:none} .page-shell{padding:0 16px 60px} .user-row{grid-template-columns:1fr;gap:10px} }
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
      <a class="nav-link active" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
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
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-user-tie" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i><span>Recruiters</span></div>
    <div class="page-sub"><?php echo $total; ?> recruiter<?php echo $total !== 1 ? 's' : ''; ?> found across all companies.</div>
  </div>

  <div class="content-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar anim">
      <div class="sidebar-card">
        <div class="sidebar-head">
          <div class="sidebar-title"><i class="fas fa-shield-alt"></i> Admin Panel</div>
        </div>
        <div class="sidebar-stats">
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $total; ?></div><div class="sb-stat-lbl">Recruiters</div></div>
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $pendingCompanies; ?></div><div class="sb-stat-lbl">Pending Co.</div></div>
        </div>
        <a class="sb-nav-item" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="sb-nav-item" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
        <a class="sb-nav-item" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval <?php if($pendingCompanies > 0): ?><span class="sb-badge"><?php echo $pendingCompanies; ?></span><?php endif; ?></a>
        <a class="sb-nav-item" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation <?php if($pendingJobs > 0): ?><span class="sb-badge amber"><?php echo $pendingJobs; ?></span><?php endif; ?></a>
        <a class="sb-nav-item" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
        <a class="sb-nav-item active" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters <span class="sb-badge blue"><?php echo $total; ?></span></a>
        <a class="sb-nav-item" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports &amp; Analytics</a>
        <a class="sb-nav-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread > 0): ?><span class="sb-badge"><?php echo $unread; ?></span><?php endif; ?></a>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="anim">
      <!-- SEARCH -->
      <form class="search-bar-wrap" method="GET" action="">
        <div class="search-form">
          <input class="search-input" type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" placeholder="Search by name, email, or company…">
          <button class="search-submit" type="submit"><i class="fas fa-search"></i> Search</button>
        </div>
      </form>

      <!-- RESULTS -->
      <?php if (empty($recruiters)): ?>
        <div class="empty-state"><i class="fas fa-user-tie"></i><div>No recruiters found<?php echo $search !== '' ? ' matching your search.' : '.'; ?></div></div>
      <?php else: ?>
        <div class="results-count"><?php echo $total; ?> recruiter<?php echo $total !== 1 ? 's' : ''; ?> found</div>
        <div class="user-list">
          <?php foreach ($recruiters as $r): ?>
          <?php
            $st     = (string)($r['account_status'] ?? 'active');
            $rId    = (int)$r['id'];
            $stChip = match($st) {
              'active'    => '<span class="chip chip-active">Active</span>',
              'suspended' => '<span class="chip chip-suspended">Suspended</span>',
              'banned'    => '<span class="chip chip-banned">Banned</span>',
              default     => '<span class="chip">' . htmlspecialchars($st, ENT_QUOTES) . '</span>',
            };
            $activeChip = !empty($r['recruiter_is_active'])
              ? '<span class="chip chip-active">Recruiter Active</span>'
              : '<span class="chip chip-inactive">Recruiter Inactive</span>';
          ?>
          <div class="user-row" id="user-row-<?php echo $rId; ?>">
            <div>
              <div class="ur-name"><?php echo htmlspecialchars((string)($r['full_name'] ?? ''), ENT_QUOTES); ?></div>
              <div class="ur-email"><?php echo htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES); ?></div>
              <div class="ur-meta">
                <?php echo $stChip; ?>
                <?php echo $activeChip; ?>
                <?php if (!empty($r['recruiter_role'])): ?><span class="chip chip-role"><?php echo htmlspecialchars((string)$r['recruiter_role'], ENT_QUOTES); ?></span><?php endif; ?>
                <?php if (!empty($r['company_name']) || !empty($r['employer_name'])): ?>
                  <span class="chip chip-company"><i class="fas fa-building" style="margin-right:3px;font-size:10px;"></i><?php echo htmlspecialchars((string)($r['company_name'] ?? $r['employer_name'] ?? ''), ENT_QUOTES); ?></span>
                <?php endif; ?>
                <span class="chip chip-date"><i class="fas fa-calendar" style="color:var(--red-bright);margin-right:3px;font-size:10px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($r['added_at'] ?? 'now'))), ENT_QUOTES); ?></span>
              </div>
            </div>
            <div class="ur-actions">
              <?php if ($st === 'active'): ?>
                <button class="btn btn-suspend" onclick="openModal('suspend', <?php echo $rId; ?>, '<?php echo htmlspecialchars((string)($r['full_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-pause-circle"></i> Suspend</button>
                <button class="btn btn-ban" onclick="openModal('ban', <?php echo $rId; ?>, '<?php echo htmlspecialchars((string)($r['full_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-ban"></i> Ban</button>
              <?php elseif ($st === 'suspended'): ?>
                <button class="btn btn-reinstate" onclick="doAction('unsuspend_user', <?php echo $rId; ?>)"><i class="fas fa-play-circle"></i> Unsuspend</button>
              <?php elseif ($st === 'banned'): ?>
                <button class="btn btn-reinstate" onclick="doAction('unban_user', <?php echo $rId; ?>)"><i class="fas fa-unlock"></i> Unban</button>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- SUSPEND MODAL -->
<div class="modal-overlay" id="suspendModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('suspendModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title">Suspend Recruiter</div>
    <div class="modal-sub" id="suspendModalSub"></div>
    <div class="modal-label">Reason</div>
    <textarea class="modal-textarea" id="suspendReason" placeholder="Reason…"></textarea>
    <div class="modal-label">Expires At <span style="color:var(--text-muted);font-weight:400;">(optional)</span></div>
    <input class="modal-input" type="datetime-local" id="suspendExpires">
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('suspendModal')">Cancel</button>
      <button class="btn-confirm-modal amber" id="suspendSubmit" onclick="submitSuspend()"><i class="fas fa-pause-circle"></i> Confirm Suspend</button>
    </div>
  </div>
</div>

<!-- BAN MODAL -->
<div class="modal-overlay" id="banModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('banModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title">Ban Recruiter</div>
    <div class="modal-sub" id="banModalSub"></div>
    <div class="modal-label">Reason</div>
    <textarea class="modal-textarea" id="banReason" placeholder="Reason…"></textarea>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('banModal')">Cancel</button>
      <button class="btn-confirm-modal" id="banSubmit" onclick="submitBan()"><i class="fas fa-ban"></i> Confirm Ban</button>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';
let suspendTargetId = null, banTargetId = null;

async function adminAction(action, data) {
  const res = await fetch('api_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, csrf_token: CSRF, ...data })
  });
  return res.json();
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openModal(mode, userId, name) {
  if (mode === 'suspend') {
    suspendTargetId = userId;
    document.getElementById('suspendModalSub').textContent = 'Suspend "' + name + '"?';
    document.getElementById('suspendReason').value = '';
    document.getElementById('suspendExpires').value = '';
    document.getElementById('suspendModal').classList.add('open');
  } else {
    banTargetId = userId;
    document.getElementById('banModalSub').textContent = 'Permanently ban "' + name + '"?';
    document.getElementById('banReason').value = '';
    document.getElementById('banModal').classList.add('open');
  }
}

async function submitSuspend() {
  if (!suspendTargetId) return;
  const btn = document.getElementById('suspendSubmit');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const r = await adminAction('suspend_user', {
    user_id: suspendTargetId,
    reason: document.getElementById('suspendReason').value.trim(),
    expires_at: document.getElementById('suspendExpires').value
  });
  if (r.success) {
    closeModal('suspendModal');
    showToast(r.message || 'Suspended.', 'fa-pause-circle');
    setTimeout(() => location.reload(), 1000);
  } else {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-pause-circle"></i> Confirm Suspend';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

async function submitBan() {
  if (!banTargetId) return;
  const btn = document.getElementById('banSubmit');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const r = await adminAction('ban_user', {
    user_id: banTargetId,
    reason: document.getElementById('banReason').value.trim()
  });
  if (r.success) {
    closeModal('banModal');
    showToast(r.message || 'Banned.', 'fa-ban');
    setTimeout(() => location.reload(), 1000);
  } else {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-ban"></i> Confirm Ban';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

async function doAction(action, userId) {
  const r = await adminAction(action, { user_id: userId });
  if (r.success) {
    showToast(r.message || 'Done.', 'fa-check-circle');
    setTimeout(() => location.reload(), 1000);
  } else {
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

['suspendModal','banModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) closeModal(id);
  });
});

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
