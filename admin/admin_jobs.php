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
    $pendingJobs = $db->query(
      "SELECT j.id, j.title, j.description, j.location, j.job_type AS type,
             CONCAT(j.salary_currency,' ',FORMAT(j.salary_min,0),'–',FORMAT(j.salary_max,0)) AS salary_range, j.deadline, j.created_at,
              u.full_name, u.company_name, u.email
       FROM jobs j
       JOIN users u ON u.id = j.employer_id
       WHERE j.approval_status = 'pending' AND j.status = 'Active'
       ORDER BY j.created_at DESC"
    )->fetchAll();
} catch (Throwable) { $pendingJobs = []; }

try {
    $approvedJobs = $db->query(
      "SELECT j.id, j.title, j.location, j.job_type AS type, j.created_at, j.deadline,
              u.company_name, u.email, j.approval_status, j.status
       FROM jobs j
       JOIN users u ON u.id = j.employer_id
       WHERE j.approval_status = 'approved'
       ORDER BY j.created_at DESC LIMIT 50"
    )->fetchAll();
} catch (Throwable) { $approvedJobs = []; }

try {
    $rejectedJobs = $db->query(
      "SELECT j.id, j.title, j.location, j.created_at, j.approval_reason,
              u.company_name, u.email
       FROM jobs j
       JOIN users u ON u.id = j.employer_id
       WHERE j.approval_status = 'rejected'
       ORDER BY j.created_at DESC LIMIT 50"
    )->fetchAll();
} catch (Throwable) { $rejectedJobs = []; }

$pendingCount  = count($pendingJobs);
$approvedCount = count($approvedJobs);
$rejectedCount = count($rejectedJobs);

$unread = 0;
try { $unread = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId} AND is_read=0")->fetchColumn(); } catch (Throwable) {}
$pendingCompanies = 0;
try { $pendingCompanies = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'")->fetchColumn(); } catch (Throwable) {}
$totalRecruiters = 0;
try { $totalRecruiters = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'")->fetchColumn(); } catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Job Moderation</title>
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

    .content-layout { display:grid; grid-template-columns:244px 1fr; gap:28px; align-items:start; }
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

    /* Tabs */
    .tab-bar { display:flex; gap:6px; margin-bottom:24px; flex-wrap:wrap; }
    .tab-btn { padding:8px 18px; border-radius:7px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; display:flex; align-items:center; gap:7px; }
    .tab-btn:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.4); }
    .tab-btn.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .tab-count { font-size:10px; font-weight:700; padding:1px 6px; border-radius:8px; background:rgba(209,61,44,0.18); color:var(--red-pale); }
    .tab-count.amber { background:rgba(212,148,58,0.18); color:var(--amber); }
    .tab-count.green { background:rgba(76,175,112,0.12); color:#6ccf8a; }
    .tab-section { display:none; }
    .tab-section.active { display:block; }

    /* Job rows */
    .job-list { display:flex; flex-direction:column; gap:8px; }
    .job-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px 20px; transition:all 0.18s; }
    .job-row:hover { border-color:rgba(209,61,44,0.5); background:var(--soil-hover); }
    .jr-layout { display:grid; grid-template-columns:1fr auto; gap:16px; align-items:start; }
    .jr-top { display:flex; align-items:center; gap:8px; margin-bottom:5px; flex-wrap:wrap; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; padding:2px 7px; border-radius:3px; white-space:nowrap; }
    .jr-new.amber { color:var(--amber); background:rgba(212,148,58,.1); border:1px solid rgba(212,148,58,.2); }
    .jr-new.green { color:#6ccf8a; background:rgba(76,175,112,.1); border:1px solid rgba(76,175,112,.2); }
    .jr-new.red { color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); }
    .jr-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:var(--text-muted); margin-bottom:6px; }
    .jr-meta span { display:flex; align-items:center; gap:4px; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-company { color:var(--red-pale); font-weight:600; }
    .jr-desc { font-size:12px; color:var(--text-muted); line-height:1.6; margin-top:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .jr-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
    .btn { padding:7px 14px; border-radius:6px; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; border:1px solid transparent; transition:0.18s; white-space:nowrap; }
    .btn-approve { background:rgba(76,175,112,0.12); border-color:rgba(76,175,112,0.3); color:#6ccf8a; }
    .btn-approve:hover { background:rgba(76,175,112,0.22); border-color:rgba(76,175,112,0.5); }
    .btn-reject { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .btn-reject:hover { background:rgba(209,61,44,0.18); border-color:rgba(209,61,44,0.4); }
    .btn-remove { background:rgba(212,148,58,0.1); border-color:rgba(212,148,58,0.25); color:var(--amber); }
    .btn-remove:hover { background:rgba(212,148,58,0.18); border-color:rgba(212,148,58,0.4); }

    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; padding:10px 14px; text-align:left; border-bottom:1px solid var(--soil-line); }
    .data-table td { padding:12px 14px; font-size:13px; color:var(--text-mid); border-bottom:1px solid rgba(53,46,46,0.5); vertical-align:middle; }
    .data-table tr:last-child td { border-bottom:none; }
    .data-table tr:hover td { background:var(--soil-hover); }
    .table-wrap { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .td-name { font-weight:700; color:#F5F0EE; font-size:13px; }
    .td-reason { font-size:11px; color:var(--text-muted); font-style:italic; max-width:260px; }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.amber { background:rgba(212,148,58,.08); color:var(--amber); border-color:rgba(212,148,58,.2); }
    .chip.red { background:rgba(209,61,44,.08); color:var(--red-pale); border-color:rgba(209,61,44,.15); }

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
    .modal-label { font-size:12px; font-weight:700; color:var(--text-mid); margin-bottom:6px; }
    .modal-textarea { width:100%; padding:12px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; resize:vertical; min-height:96px; outline:none; transition:0.2s; }
    .modal-textarea:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .modal-actions { display:flex; gap:10px; margin-top:18px; justify-content:flex-end; }
    .btn-cancel { padding:9px 18px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; }
    .btn-cancel:hover { color:#F5F0EE; }
    .btn-confirm { padding:9px 18px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.18s; }
    .btn-confirm:hover { background:var(--red-bright); }
    .btn-confirm.amber { background:var(--amber); color:#1A0A09; }
    .btn-confirm.amber:hover { background:#e0a040; }

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
    body.light .sidebar-card, body.light .table-wrap { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sb-stat { background:#F5EEEC; border-color:#E0CECA; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .job-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .job-row:hover { background:#FEF0EE; }
    body.light .jr-title { color:#1A0A09; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-textarea { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; }

    @media(max-width:1060px) { .content-layout{grid-template-columns:1fr} .sidebar{position:static} }
    @media(max-width:760px) { .nav-links{display:none} .page-shell{padding:0 16px 60px} .jr-layout{grid-template-columns:1fr;gap:10px} }
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
      <a class="nav-link active" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
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
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-briefcase" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i>Job <span>Moderation</span></div>
    <div class="page-sub"><?php echo $pendingCount; ?> job post<?php echo $pendingCount !== 1 ? 's' : ''; ?> pending approval.</div>
  </div>

  <div class="content-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar anim">
      <div class="sidebar-card">
        <div class="sidebar-head">
          <div class="sidebar-title"><i class="fas fa-shield-alt"></i> Admin Panel</div>
        </div>
        <div class="sidebar-stats">
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $pendingCount; ?></div><div class="sb-stat-lbl">Pending</div></div>
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $approvedCount; ?></div><div class="sb-stat-lbl">Approved</div></div>
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $rejectedCount; ?></div><div class="sb-stat-lbl">Rejected</div></div>
          <div class="sb-stat"><div class="sb-stat-num"><?php echo $pendingCount + $approvedCount + $rejectedCount; ?></div><div class="sb-stat-lbl">Total</div></div>
        </div>
        <a class="sb-nav-item" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a class="sb-nav-item" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
        <a class="sb-nav-item" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval <?php if($pendingCompanies > 0): ?><span class="sb-badge"><?php echo $pendingCompanies; ?></span><?php endif; ?></a>
        <a class="sb-nav-item active" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation <?php if($pendingCount > 0): ?><span class="sb-badge amber"><?php echo $pendingCount; ?></span><?php endif; ?></a>
        <a class="sb-nav-item" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
        <a class="sb-nav-item" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters <span class="sb-badge blue"><?php echo $totalRecruiters; ?></span></a>
        <a class="sb-nav-item" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports &amp; Analytics</a>
        <a class="sb-nav-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications <?php if($unread > 0): ?><span class="sb-badge"><?php echo $unread; ?></span><?php endif; ?></a>
      </div>
    </aside>

    <!-- MAIN -->
    <main class="anim">
      <div class="tab-bar">
        <button class="tab-btn active" data-tab="pending" onclick="switchTab('pending')">
          <i class="fas fa-hourglass-half"></i> Pending
          <span class="tab-count" id="pending-count-badge"><?php echo $pendingCount; ?></span>
        </button>
        <button class="tab-btn" data-tab="approved" onclick="switchTab('approved')">
          <i class="fas fa-check-circle"></i> Approved / Active
          <span class="tab-count green"><?php echo $approvedCount; ?></span>
        </button>
        <button class="tab-btn" data-tab="rejected" onclick="switchTab('rejected')">
          <i class="fas fa-times-circle"></i> Rejected / Removed
          <span class="tab-count amber"><?php echo $rejectedCount; ?></span>
        </button>
      </div>

      <!-- PENDING -->
      <div class="tab-section active" id="tab-pending">
        <div class="job-list" id="pending-list">
          <?php if (empty($pendingJobs)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><div>No pending jobs to review.</div></div>
          <?php else: ?>
            <?php foreach ($pendingJobs as $j): ?>
            <div class="job-row" id="job-row-<?php echo (int)$j['id']; ?>">
              <div class="jr-layout">
                <div>
                  <div class="jr-top">
                    <span class="jr-title"><?php echo htmlspecialchars((string)($j['title'] ?? ''), ENT_QUOTES); ?></span>
                    <span class="jr-new amber">Pending</span>
                  </div>
                  <div class="jr-meta">
                    <span class="jr-company"><i class="fas fa-building"></i> <?php echo htmlspecialchars((string)($j['company_name'] ?? ''), ENT_QUOTES); ?></span>
                    <?php if (!empty($j['location'])): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars((string)$j['location'], ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if (!empty($j['type'])): ?><span><i class="fas fa-clock"></i> <?php echo htmlspecialchars((string)$j['type'], ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if (!empty($j['salary_range'])): ?><span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars((string)$j['salary_range'], ENT_QUOTES); ?></span><?php endif; ?>
                    <?php if (!empty($j['deadline'])): ?><span><i class="fas fa-calendar-times"></i> Deadline: <?php echo htmlspecialchars(date('M j, Y', strtotime((string)$j['deadline'])), ENT_QUOTES); ?></span><?php endif; ?>
                    <span><i class="fas fa-calendar-plus"></i> Posted: <?php echo htmlspecialchars(date('M j, Y', strtotime((string)($j['created_at'] ?? 'now'))), ENT_QUOTES); ?></span>
                  </div>
                  <?php if (!empty($j['description'])): ?>
                    <div class="jr-desc"><?php echo htmlspecialchars((string)$j['description'], ENT_QUOTES); ?></div>
                  <?php endif; ?>
                </div>
                <div class="jr-actions">
                  <button class="btn btn-approve" onclick="approveJob(<?php echo (int)$j['id']; ?>, this)"><i class="fas fa-check"></i> Approve</button>
                  <button class="btn btn-reject" onclick="openJobModal('reject', <?php echo (int)$j['id']; ?>, '<?php echo htmlspecialchars((string)($j['title'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-times"></i> Reject</button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- APPROVED -->
      <div class="tab-section" id="tab-approved">
        <?php if (empty($approvedJobs)): ?>
          <div class="empty-state"><i class="fas fa-briefcase"></i><div>No approved jobs yet.</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Job Title</th><th>Company</th><th>Location</th><th>Type</th><th>Approved</th><th>Deadline</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($approvedJobs as $j): ?>
              <tr id="job-row-<?php echo (int)$j['id']; ?>">
                <td><div class="td-name"><?php echo htmlspecialchars((string)($j['title'] ?? ''), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars((string)($j['company_name'] ?? ''), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)($j['location'] ?? '—'), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)($j['type'] ?? '—'), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($j['created_at'] ?? 'now'))), ENT_QUOTES); ?></td>
                <td><?php echo !empty($j['deadline']) ? htmlspecialchars(date('M j, Y', strtotime((string)$j['deadline'])), ENT_QUOTES) : '—'; ?></td>
                <td><button class="btn btn-remove" onclick="openJobModal('remove', <?php echo (int)$j['id']; ?>, '<?php echo htmlspecialchars((string)($j['title'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-trash-alt"></i> Remove</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- REJECTED -->
      <div class="tab-section" id="tab-rejected">
        <?php if (empty($rejectedJobs)): ?>
          <div class="empty-state"><i class="fas fa-times-circle"></i><div>No rejected jobs.</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Job Title</th><th>Company</th><th>Location</th><th>Reason</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($rejectedJobs as $j): ?>
              <tr>
                <td><div class="td-name"><?php echo htmlspecialchars((string)($j['title'] ?? ''), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars((string)($j['company_name'] ?? ''), ENT_QUOTES); ?></td>
                <td><?php echo htmlspecialchars((string)($j['location'] ?? '—'), ENT_QUOTES); ?></td>
                <td><div class="td-reason"><?php echo htmlspecialchars((string)($j['approval_reason'] ?? '—'), ENT_QUOTES); ?></div></td>
                <td><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($j['created_at'] ?? 'now'))), ENT_QUOTES); ?></td>
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

<!-- JOB ACTION MODAL -->
<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeJobModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="jobModalTitle">Reject Job</div>
    <div class="modal-sub" id="jobModalSub">Provide a reason.</div>
    <div class="modal-label">Reason (optional)</div>
    <textarea class="modal-textarea" id="jobModalReason" placeholder="Enter reason…"></textarea>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeJobModal()">Cancel</button>
      <button class="btn-confirm" id="jobModalSubmit" onclick="submitJobAction()"><i class="fas fa-check"></i> Confirm</button>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';
let jobModalMode = null, jobModalId = null;

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
}

async function approveJob(jobId, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const r = await adminAction('approve_job', { job_id: jobId });
  if (r.success) {
    const row = document.getElementById('job-row-' + jobId);
    if (row) { row.style.opacity = '0'; row.style.transition = '0.3s'; setTimeout(() => row.remove(), 320); }
    let cnt = parseInt(document.getElementById('pending-count-badge').textContent, 10) - 1;
    document.getElementById('pending-count-badge').textContent = Math.max(0, cnt);
    showToast(r.message || 'Job approved.', 'fa-check-circle');
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Approve';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

function openJobModal(mode, id, title) {
  jobModalMode = mode; jobModalId = id;
  document.getElementById('jobModalTitle').textContent = mode === 'reject' ? 'Reject Job Post' : 'Remove Job Post';
  document.getElementById('jobModalSub').textContent = (mode === 'reject' ? 'Reject "' : 'Remove "') + title + '"?';
  document.getElementById('jobModalReason').value = '';
  const submitBtn = document.getElementById('jobModalSubmit');
  submitBtn.className = mode === 'remove' ? 'btn-confirm amber' : 'btn-confirm';
  submitBtn.innerHTML = mode === 'remove' ? '<i class="fas fa-trash-alt"></i> Remove' : '<i class="fas fa-times"></i> Reject';
  document.getElementById('jobModal').classList.add('open');
}
function closeJobModal() {
  document.getElementById('jobModal').classList.remove('open');
  jobModalMode = null; jobModalId = null;
}

async function submitJobAction() {
  if (!jobModalMode || !jobModalId) return;
  const reason = document.getElementById('jobModalReason').value.trim();
  const btn = document.getElementById('jobModalSubmit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const actionMap = { reject: 'reject_job', remove: 'remove_job' };
  const r = await adminAction(actionMap[jobModalMode], { job_id: jobModalId, reason });
  if (r.success) {
    const row = document.getElementById('job-row-' + jobModalId);
    if (row) { row.style.opacity = '0'; row.style.transition = '0.3s'; setTimeout(() => row.remove(), 320); }
    if (jobModalMode === 'reject') {
      let cnt = parseInt(document.getElementById('pending-count-badge').textContent, 10) - 1;
      document.getElementById('pending-count-badge').textContent = Math.max(0, cnt);
    }
    closeJobModal();
    showToast(r.message || 'Done.', 'fa-check-circle');
  } else {
    btn.disabled = false;
    btn.innerHTML = jobModalMode === 'remove' ? '<i class="fas fa-trash-alt"></i> Remove' : '<i class="fas fa-times"></i> Reject';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

document.getElementById('jobModal').addEventListener('click', function(e) {
  if (e.target === this) closeJobModal();
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
