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

$typeFilter   = $_GET['type']   ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;

$where  = ["LOWER(u.account_type) != 'admin'"];
$params = [];
if (in_array($typeFilter, ['seeker','employer','recruiter'], true)) {
    $where[]       = "LOWER(u.account_type) = :type";
    $params[':type'] = $typeFilter;
}
if (in_array($statusFilter, ['active','pending_approval','suspended','banned'], true)) {
    $where[]         = "u.account_status = :status";
    $params[':status'] = $statusFilter;
}
// search is handled client-side
$whereStr = 'WHERE ' . implode(' AND ', $where);

try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u {$whereStr}");
    $countStmt->execute($params);
    $totalUsers = (int)$countStmt->fetchColumn();
} catch (Throwable) { $totalUsers = 0; }
$totalPages = $totalUsers > 0 ? (int)ceil($totalUsers / $perPage) : 1;
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

try {
    $stmt = $db->prepare(
      "SELECT u.id, u.full_name, u.email, u.account_type, u.company_name,
              COALESCE(u.account_status,'active') AS account_status, u.status_reason,
              u.status_expires_at, u.created_at, u.last_login_at AS last_login
       FROM users u
       {$whereStr}
       ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (Throwable) { $users = []; }

$pendingCompanies = 0;
try { $pendingCompanies = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'")->fetchColumn(); } catch (Throwable) {}
$pendingJobs = 0;
try { $pendingJobs = (int)$db->query("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'")->fetchColumn(); } catch (Throwable) {}
$totalRecruiters = 0;
try { $totalRecruiters = (int)$db->query("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'")->fetchColumn(); } catch (Throwable) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — User Accounts</title>
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
    .pill-group { display:flex; gap:4px; flex-wrap:wrap; }
    .pill-opt { padding:5px 12px; border-radius:100px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; white-space:nowrap; text-decoration:none; }
    .pill-opt:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.4); }
    .pill-opt.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .search-input { padding:8px 13px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:0.2s; min-width:220px; }
    .search-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-input::placeholder { color:var(--text-muted); }
    .filter-submit { padding:8px 18px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.18s; white-space:nowrap; }
    .filter-submit:hover { background:var(--red-bright); }

    /* User rows */
    .user-list { display:flex; flex-direction:column; gap:8px; }
    .user-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 20px; transition:all 0.18s; display:grid; grid-template-columns:1fr auto; gap:14px; align-items:center; }
    .user-row:hover { border-color:rgba(209,61,44,0.4); background:var(--soil-hover); }
    .ur-name { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:2px; }
    .ur-email { font-size:12px; color:var(--text-muted); margin-bottom:6px; }
    .ur-meta { display:flex; flex-wrap:wrap; gap:7px; align-items:center; }
    .ur-reason { font-size:11px; color:var(--text-muted); font-style:italic; margin-top:4px; }
    .chip { font-size:11px; font-weight:600; padding:3px 9px; border-radius:4px; white-space:nowrap; }
    .chip-active { background:rgba(76,175,112,.1); color:#6ccf8a; border:1px solid rgba(76,175,112,.2); }
    .chip-pending { background:rgba(212,148,58,.1); color:var(--amber); border:1px solid rgba(212,148,58,.2); }
    .chip-suspended { background:rgba(212,148,58,.12); color:var(--amber); border:1px solid rgba(212,148,58,.25); }
    .chip-banned { background:rgba(209,61,44,.1); color:var(--red-pale); border:1px solid rgba(209,61,44,.2); }
    .chip-seeker { background:rgba(74,144,217,.08); color:#7ab8f0; border:1px solid rgba(74,144,217,.18); }
    .chip-employer { background:rgba(212,148,58,.08); color:var(--amber); border:1px solid rgba(212,148,58,.18); }
    .chip-recruiter { background:rgba(156,39,176,.08); color:#cf8ae0; border:1px solid rgba(156,39,176,.15); }
    .chip-date { background:var(--soil-hover); color:var(--text-muted); border:1px solid var(--soil-line); }
    .ur-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
    .btn { padding:6px 13px; border-radius:6px; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; border:1px solid transparent; transition:0.18s; white-space:nowrap; }
    .btn-suspend { background:rgba(212,148,58,0.1); border-color:rgba(212,148,58,0.25); color:var(--amber); }
    .btn-suspend:hover { background:rgba(212,148,58,0.2); }
    .btn-ban { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .btn-ban:hover { background:rgba(209,61,44,0.2); }
    .btn-reinstate { background:rgba(76,175,112,0.1); border-color:rgba(76,175,112,0.25); color:#6ccf8a; }
    .btn-reinstate:hover { background:rgba(76,175,112,0.2); }
    .btn-approve { background:rgba(76,175,112,0.12); border-color:rgba(76,175,112,0.3); color:#6ccf8a; }
    .btn-approve:hover { background:rgba(76,175,112,0.22); }
    .btn-reject { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .btn-reject:hover { background:rgba(209,61,44,0.18); }
    .btn-reset-pw { background:rgba(74,144,217,0.08); border-color:rgba(74,144,217,0.2); color:#7ab8f0; }
    .btn-reset-pw:hover { background:rgba(74,144,217,0.16); }

    .pagination { display:flex; align-items:center; gap:6px; justify-content:center; margin-top:24px; flex-wrap:wrap; }
    .pag-btn { padding:7px 13px; border-radius:7px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; text-decoration:none; }
    .pag-btn:hover { color:#F5F0EE; border-color:rgba(209,61,44,0.4); }
    .pag-btn.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.4); color:var(--red-pale); cursor:default; }
    .pag-btn.disabled { opacity:0.35; pointer-events:none; }

    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }
    .results-count { font-size:12px; color:var(--text-muted); margin-bottom:14px; font-weight:600; }

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
    body.light .navbar { background:rgba(249,245,244,0.97); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .notif-btn-nav .badge { border-color:#F9F5F4; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
    body.light .page-title { color:#1A0A09; }
    body.light .filter-bar, body.light .user-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pill-opt { background:#F5EEEC; border-color:#E0CECA; }
    body.light .pill-opt:hover { color:#1A0A09; border-color:rgba(209,61,44,0.35); }
    body.light .search-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .ur-name { color:#1A0A09; }
    body.light .user-row:hover { background:#FEF0EE; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-close:hover { color:#1A0A09; background:#FEF0EE; border-color:#D4B0AB; }
    body.light .modal-textarea, body.light .modal-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; }
    body.light .chip-date { background:#F5EEEC; border-color:#E0CECA; }

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
    @media(max-width:760px) { .nav-links{display:none} .hamburger{display:flex} .profile-wrap{display:none} .nav-inner{padding:0 10px} .page-shell{padding:0 16px 60px} .theme-btn,.notif-btn-nav{width:32px;height:32px;font-size:13px} .nav-right{gap:6px} .user-row{grid-template-columns:1fr;gap:10px} }
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
      <a class="nav-link active" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
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
  <a class="mobile-link active" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
  <a class="mobile-link" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval</a>
  <a class="mobile-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
  <a class="mobile-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
  <a class="mobile-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
  <a class="mobile-link" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>
<script>(function(){var h=document.getElementById('hamburger'),m=document.getElementById('mobileMenu');function syncMobileMenuPosition(){var nav=document.getElementById('mainNavbar')||document.querySelector('.navbar');if(!m||!nav)return;var rect=nav.getBoundingClientRect();var top=Math.max(0,Math.round(rect.bottom));m.style.top=top+'px';m.style.maxHeight='calc(100dvh - '+top+'px)';}window.addEventListener('scroll',syncMobileMenuPosition,{passive:true});window.addEventListener('resize',syncMobileMenuPosition);syncMobileMenuPosition();if(h&&m){h.addEventListener('click',function(e){e.stopPropagation();syncMobileMenuPosition();var o=m.classList.toggle('open');h.querySelector('i').className=o?'fas fa-times':'fas fa-bars';});document.addEventListener('click',function(e){if(!m.contains(e.target)&&e.target!==h){m.classList.remove('open');h.querySelector('i').className='fas fa-bars';}});}})();</script>

<div class="page-shell">
  <div class="page-header anim">
    <div class="page-title"><i class="fas fa-users" style="color:var(--red-bright);font-size:22px;vertical-align:middle;margin-right:8px;"></i>User <span>Accounts</span></div>
    <div class="page-sub">Manage all platform users. Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> result<?php echo $totalUsers !== 1 ? 's' : ''; ?>.</div>
  </div>

  <div class="content-layout">

    <!-- MAIN -->
    <main class="anim">
      <!-- FILTER BAR -->
      <form class="filter-bar" method="GET" action="">
        <div class="filter-form">
          <div class="filter-group">
            <div class="filter-label">Account Type</div>
            <div class="pill-group">
              <?php foreach (['all'=>'All','seeker'=>'Seeker','employer'=>'Employer','recruiter'=>'Recruiter'] as $val => $lbl): ?>
              <a class="pill-opt <?php echo $typeFilter === $val ? 'active' : ''; ?>" href="?type=<?php echo urlencode($val); ?>&status=<?php echo urlencode($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><?php echo $lbl; ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="filter-group">
            <div class="filter-label">Status</div>
            <div class="pill-group">
              <?php foreach (['all'=>'All','active'=>'Active','pending_approval'=>'Pending','suspended'=>'Suspended','banned'=>'Banned'] as $val => $lbl): ?>
              <a class="pill-opt <?php echo $statusFilter === $val ? 'active' : ''; ?>" href="?type=<?php echo urlencode($typeFilter); ?>&status=<?php echo urlencode($val); ?>&q=<?php echo urlencode($search); ?>"><?php echo $lbl; ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="filter-group" style="flex:1;min-width:220px;">
            <div class="filter-label">Search</div>
            <div style="position:relative;">
              <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;pointer-events:none;"></i>
              <input class="search-input" id="liveSearch" type="text" autocomplete="off" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" placeholder="Filter by name, email, company…" style="padding-left:32px;width:100%;">
            </div>
          </div>
          <input type="hidden" name="type" value="<?php echo htmlspecialchars($typeFilter, ENT_QUOTES); ?>">
          <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES); ?>">
        </div>
      </form>

      <!-- RESULTS -->
      <?php if (empty($users)): ?>
        <div class="empty-state"><i class="fas fa-users"></i><div>No users match your filters.</div></div>
      <?php else: ?>
        <div class="results-count"><?php echo count($users); ?> user<?php echo count($users) !== 1 ? 's' : ''; ?> found</div>
        <div class="user-list">
          <?php foreach ($users as $u): ?>
          <?php
            $st     = (string)($u['account_status'] ?? 'active');
            $role   = strtolower((string)($u['account_type'] ?? ''));
            $userId = (int)$u['id'];
            $stChip = match($st) {
              'active'           => '<span class="chip chip-active">Active</span>',
              'pending_approval' => '<span class="chip chip-pending">Pending Approval</span>',
              'suspended'        => '<span class="chip chip-suspended">Suspended</span>',
              'banned'           => '<span class="chip chip-banned">Banned</span>',
              default            => '<span class="chip">' . htmlspecialchars($st, ENT_QUOTES) . '</span>',
            };
            $roleChip = match($role) {
              'seeker'    => '<span class="chip chip-seeker">Seeker</span>',
              'employer'  => '<span class="chip chip-employer">Employer</span>',
              'recruiter' => '<span class="chip chip-recruiter">Recruiter</span>',
              default     => '<span class="chip">' . htmlspecialchars($role, ENT_QUOTES) . '</span>',
            };
          ?>
          <div class="user-row" id="user-row-<?php echo $userId; ?>" data-search="<?php echo htmlspecialchars(strtolower(($u['full_name'] ?? '').' '.($u['email'] ?? '').' '.($u['company_name'] ?? '')), ENT_QUOTES); ?>">
            <div>
              <div class="ur-name"><?php echo htmlspecialchars((string)($u['full_name'] ?? ''), ENT_QUOTES); ?><?php if (!empty($u['company_name'])): ?> <span style="font-size:12px;color:var(--text-muted);font-family:var(--font-body);font-weight:400;">— <?php echo htmlspecialchars((string)$u['company_name'], ENT_QUOTES); ?></span><?php endif; ?></div>
              <div class="ur-email"><?php echo htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES); ?></div>
              <div class="ur-meta">
                <?php echo $roleChip; ?>
                <?php echo $stChip; ?>
                <span class="chip chip-date"><i class="fas fa-calendar" style="color:var(--red-bright);margin-right:3px;font-size:10px;"></i><?php echo htmlspecialchars(date('M j, Y', strtotime((string)($u['created_at'] ?? 'now'))), ENT_QUOTES); ?></span>
                <?php if (!empty($u['status_expires_at'])): ?><span class="chip chip-suspended">Expires: <?php echo htmlspecialchars(date('M j, Y', strtotime((string)$u['status_expires_at'])), ENT_QUOTES); ?></span><?php endif; ?>
              </div>
              <?php if (!empty($u['status_reason'])): ?>
                <div class="ur-reason"><i class="fas fa-info-circle" style="margin-right:4px;"></i><?php echo htmlspecialchars((string)$u['status_reason'], ENT_QUOTES); ?></div>
              <?php endif; ?>
            </div>
            <div class="ur-actions">
              <?php if ($st === 'active'): ?>
                <button class="btn btn-suspend" onclick="openUserModal('suspend', <?php echo $userId; ?>, '<?php echo htmlspecialchars((string)($u['full_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-pause-circle"></i> Suspend</button>
                <button class="btn btn-ban" onclick="openUserModal('ban', <?php echo $userId; ?>, '<?php echo htmlspecialchars((string)($u['full_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-ban"></i> Ban</button>
              <?php elseif ($st === 'pending_approval' && $role === 'employer'): ?>
                <button class="btn btn-approve" onclick="doUserAction('approve_company', <?php echo $userId; ?>, null, null)"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-reject" onclick="openUserModal('reject_company', <?php echo $userId; ?>, '<?php echo htmlspecialchars((string)($u['full_name'] ?? ''), ENT_QUOTES); ?>')"><i class="fas fa-times"></i> Reject</button>
              <?php elseif ($st === 'suspended'): ?>
                <button class="btn btn-reinstate" onclick="doUserAction('unsuspend_user', <?php echo $userId; ?>, null, null)"><i class="fas fa-play-circle"></i> Unsuspend</button>
              <?php elseif ($st === 'banned'): ?>
                <button class="btn btn-reinstate" onclick="doUserAction('unban_user', <?php echo $userId; ?>, null, null)"><i class="fas fa-unlock"></i> Unban</button>
              <?php endif; ?>
              <button class="btn btn-reset-pw" onclick="doUserAction('force_password_reset', <?php echo $userId; ?>, null, null)" title="Force user to reset password on next login"><i class="fas fa-key"></i> Reset PW</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php
            $baseUrl = '?type='.urlencode($typeFilter).'&status='.urlencode($statusFilter).'&q='.urlencode($search);
            $prev = $page - 1; $next = $page + 1;
          ?>
          <a class="pag-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $baseUrl; ?>&page=<?php echo $prev; ?>"><i class="fas fa-chevron-left"></i></a>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a class="pag-btn <?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo $baseUrl; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
          <?php endfor; ?>
          <a class="pag-btn <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $baseUrl; ?>&page=<?php echo $next; ?>"><i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- SUSPEND MODAL -->
<div class="modal-overlay" id="suspendModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('suspendModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title">Suspend User</div>
    <div class="modal-sub" id="suspendModalSub">Enter suspension details.</div>
    <div class="modal-label">Reason</div>
    <textarea class="modal-textarea" id="suspendReason" placeholder="Reason for suspension…"></textarea>
    <div class="modal-label">Expires At <span style="color:var(--text-muted);font-weight:400;">(optional)</span></div>
    <input class="modal-input" type="datetime-local" id="suspendExpires">
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('suspendModal')">Cancel</button>
      <button class="btn-confirm-modal amber" id="suspendSubmit" onclick="submitSuspend()"><i class="fas fa-pause-circle"></i> Confirm Suspend</button>
    </div>
  </div>
</div>

<!-- BAN / REJECT MODAL -->
<div class="modal-overlay" id="banModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('banModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="banModalTitle">Ban User</div>
    <div class="modal-sub" id="banModalSub">This action is permanent.</div>
    <div class="modal-label">Reason</div>
    <textarea class="modal-textarea" id="banReason" placeholder="Reason…"></textarea>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('banModal')">Cancel</button>
      <button class="btn-confirm-modal" id="banSubmit" onclick="submitBan()"><i class="fas fa-ban"></i> Confirm</button>
    </div>
  </div>
</div>

<?php renderAdminNotifPanel(); ?>
<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>

<script>
const CSRF = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';
let suspendTargetId = null, banTargetId = null, banMode = null;

async function adminAction(action, data) {
  const res = await fetch('api_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, csrf_token: CSRF, ...data })
  });
  return res.json();
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openUserModal(mode, userId, name) {
  if (mode === 'suspend') {
    suspendTargetId = userId;
    document.getElementById('suspendModalSub').textContent = 'Suspend "' + name + '"?';
    document.getElementById('suspendReason').value = '';
    document.getElementById('suspendExpires').value = '';
    document.getElementById('suspendModal').classList.add('open');
  } else {
    banTargetId = userId;
    banMode = mode;
    document.getElementById('banModalTitle').textContent = mode === 'ban' ? 'Ban User' : 'Reject Company';
    document.getElementById('banModalSub').textContent = (mode === 'ban' ? 'Permanently ban "' : 'Reject registration for "') + name + '"?';
    document.getElementById('banReason').value = '';
    document.getElementById('banSubmit').innerHTML = mode === 'ban' ? '<i class="fas fa-ban"></i> Confirm Ban' : '<i class="fas fa-times"></i> Confirm Reject';
    document.getElementById('banModal').classList.add('open');
  }
}

async function submitSuspend() {
  if (!suspendTargetId) return;
  const reason = document.getElementById('suspendReason').value.trim();
  const expires = document.getElementById('suspendExpires').value;
  const btn = document.getElementById('suspendSubmit');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const r = await adminAction('suspend_user', { user_id: suspendTargetId, reason, expires_at: expires });
  if (r.success) {
    closeModal('suspendModal');
    showToast(r.message || 'User suspended.', 'fa-pause-circle');
    setTimeout(() => location.reload(), 1000);
  } else {
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-pause-circle"></i> Confirm Suspend';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

async function submitBan() {
  if (!banTargetId) return;
  const reason = document.getElementById('banReason').value.trim();
  const btn = document.getElementById('banSubmit');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  const actionName = banMode === 'ban' ? 'ban_user' : 'reject_company';
  const payload = banMode === 'ban' ? { user_id: banTargetId, reason } : { user_id: banTargetId, reason };
  const r = await adminAction(actionName, payload);
  if (r.success) {
    closeModal('banModal');
    showToast(r.message || 'Done.', 'fa-check-circle');
    setTimeout(() => location.reload(), 1000);
  } else {
    btn.disabled = false; btn.innerHTML = banMode === 'ban' ? '<i class="fas fa-ban"></i> Confirm Ban' : '<i class="fas fa-times"></i> Confirm Reject';
    showToast(r.message || 'Error.', 'fa-exclamation-circle');
  }
}

async function doUserAction(action, userId, _n1, _n2) {
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

// Live search filter
(function() {
  const input = document.getElementById('liveSearch');
  if (!input) return;
  const rows = document.querySelectorAll('.user-row');
  const countEl = document.querySelector('.results-count');
  const emptyEl = document.querySelector('.empty-state');
  const userList = document.querySelector('.user-list');

  function filter() {
    const q = input.value.trim().toLowerCase();
    let visible = 0;
    rows.forEach(row => {
      const match = !q || (row.dataset.search || '').includes(q);
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    if (countEl) countEl.textContent = visible + ' user' + (visible !== 1 ? 's' : '') + ' found';
    if (userList) userList.style.display = visible === 0 ? 'none' : '';
    const existingEmpty = document.getElementById('live-empty');
    if (visible === 0 && q) {
      if (!existingEmpty) {
        const d = document.createElement('div');
        d.id = 'live-empty';
        d.className = 'empty-state';
        d.innerHTML = '<i class="fas fa-search"></i><div>No users match "' + q.replace(/</g,'&lt;') + '".</div>';
        userList && userList.parentNode.insertBefore(d, userList.nextSibling);
      }
    } else {
      existingEmpty && existingEmpty.remove();
    }
  }

  input.addEventListener('input', filter);
  // Run on load if there's a pre-filled value
  if (input.value.trim()) filter();
})();

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
