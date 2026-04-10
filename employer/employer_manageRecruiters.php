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
$navActive   = 'manage-jobs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Manage Recruiters</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --green:#4CAF70; --blue:#4A90D9;
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

    /* NAVBAR (same as other pages) */
    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; }
    .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .notif-btn-nav { position:relative; width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .badge { position:absolute; top:-4px; right:-4px; background:var(--red-vivid); color:#fff; font-size:9px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--amber), #8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-item.active-page { color:#F5F0EE; background:var(--soil-hover); }
    .pd-item.active-page i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }
    .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* PAGE */
    .page-shell { max-width:960px; margin:0 auto; padding:36px 24px 80px; }
    .breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); margin-bottom:24px; }
    .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:0.15s; }
    .breadcrumb a:hover { color:var(--red-pale); }
    .breadcrumb i { font-size:9px; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:28px; flex-wrap:wrap; gap:12px; }
    .page-title { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; }
    .page-sub { font-size:13px; color:var(--text-muted); margin-top:2px; }

    /* Stats row */
    .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
    .stat-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 20px; display:flex; flex-direction:column; gap:4px; }
    .stat-num { font-size:24px; font-weight:800; color:#F5F0EE; font-family:var(--font-display); }
    .stat-label { font-size:12px; color:var(--text-muted); font-weight:500; }
    .stat-num.red { color:var(--red-bright); }
    .stat-num.green { color:var(--green); }
    .stat-num.amber { color:var(--amber); }

    /* Toolbar */
    .toolbar { display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap; }
    .search-box { flex:1; min-width:180px; display:flex; align-items:center; gap:8px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:8px; padding:9px 14px; }
    .search-box input { background:none; border:none; outline:none; color:#F5F0EE; font-family:var(--font-body); font-size:13px; width:100%; }
    .search-box input::placeholder { color:var(--text-muted); }
    .search-box i { color:var(--text-muted); font-size:13px; }
    .filter-select { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:8px; padding:9px 14px; font-size:13px; color:var(--text-mid); font-family:var(--font-body); cursor:pointer; outline:none; }
    .btn-invite { padding:9px 20px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; display:flex; align-items:center; gap:7px; white-space:nowrap; }
    .btn-invite:hover { background:var(--red-bright); transform:translateY(-1px); }

    /* Recruiter rows */
    .recruiter-list { display:flex; flex-direction:column; gap:10px; }
    .recruiter-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:16px 20px; display:grid; grid-template-columns:auto 1fr auto; gap:14px; align-items:center; transition:0.2s; }
    .recruiter-row:hover { border-color:rgba(209,61,44,0.25); background:var(--soil-hover); }
    .rec-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; color:#fff; flex-shrink:0; }
    .rec-info { min-width:0; }
    .rec-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .rec-email { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .rec-meta { display:flex; align-items:center; gap:10px; margin-top:6px; flex-wrap:wrap; }
    .rec-badge { font-size:11px; font-weight:600; padding:3px 9px; border-radius:10px; }
    .rb-admin { background:rgba(209,61,44,0.15); color:var(--red-pale); border:1px solid rgba(209,61,44,0.2); }
    .rb-recruiter { background:rgba(74,144,217,0.15); color:#7AB8F5; border:1px solid rgba(74,144,217,0.2); }
    .rb-viewer { background:rgba(212,148,58,0.15); color:var(--amber); border:1px solid rgba(212,148,58,0.2); }
    .rec-stat { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:4px; }
    .rec-stat i { font-size:10px; }
    .rec-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }
    .rec-status { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .rs-online { background:var(--green); box-shadow:0 0 6px rgba(76,175,112,0.5); }
    .rs-offline { background:#555; }
    .rec-action-btn { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:6px; padding:6px 12px; font-size:12px; font-weight:600; color:var(--text-mid); cursor:pointer; font-family:var(--font-body); transition:0.15s; display:flex; align-items:center; gap:5px; }
    .rec-action-btn:hover { color:#F5F0EE; border-color:var(--soil-hover); }
    .rec-action-btn.danger { color:#E05555; }
    .rec-action-btn.danger:hover { background:rgba(224,85,85,0.1); border-color:rgba(224,85,85,0.2); }

    /* Pending invites */
    .section-title { font-size:14px; font-weight:700; color:#F5F0EE; margin:28px 0 12px; display:flex; align-items:center; gap:8px; }
    .section-title i { color:var(--red-bright); font-size:13px; }
    .invite-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .invite-email { font-size:13px; color:var(--text-mid); display:flex; align-items:center; gap:8px; }
    .invite-email i { color:var(--amber); }
    .invite-meta { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .invite-actions { display:flex; gap:8px; }
    .btn-resend { background:rgba(212,148,58,0.1); border:1px solid rgba(212,148,58,0.2); border-radius:6px; padding:5px 12px; font-size:12px; font-weight:600; color:var(--amber); cursor:pointer; font-family:var(--font-body); transition:0.15s; }
    .btn-resend:hover { background:rgba(212,148,58,0.2); }
    .btn-revoke { background:transparent; border:1px solid var(--soil-line); border-radius:6px; padding:5px 10px; font-size:12px; color:var(--text-muted); cursor:pointer; font-family:var(--font-body); transition:0.15s; }
    .btn-revoke:hover { color:#E05555; border-color:rgba(224,85,85,0.3); }

    /* Invite modal */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:500; display:none; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; width:100%; max-width:440px; position:relative; }
    .modal-title { font-size:18px; font-weight:700; color:#F5F0EE; font-family:var(--font-display); margin-bottom:4px; }
    .modal-sub { font-size:13px; color:var(--text-muted); margin-bottom:20px; }
    .modal-close { position:absolute; top:16px; right:16px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:6px; width:28px; height:28px; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-muted); font-size:12px; transition:0.15s; }
    .modal-close:hover { color:#F5F0EE; }
    .modal-label { font-size:12px; font-weight:600; color:var(--text-muted); letter-spacing:0.03em; text-transform:uppercase; margin-bottom:6px; }
    .modal-input { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 14px; font-size:14px; color:#F5F0EE; font-family:var(--font-body); width:100%; outline:none; transition:0.2s; margin-bottom:14px; }
    .modal-input:focus { border-color:var(--red-mid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .modal-input::placeholder { color:var(--text-muted); }
    .role-options { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:20px; }
    .role-opt { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px; text-align:center; cursor:pointer; transition:0.2s; }
    .role-opt.selected { border-color:var(--red-mid); background:rgba(209,61,44,0.1); }
    .role-opt-icon { font-size:18px; margin-bottom:4px; }
    .role-opt-name { font-size:12px; font-weight:700; color:#F5F0EE; }
    .role-opt-desc { font-size:10px; color:var(--text-muted); margin-top:2px; }
    .modal-footer { display:flex; gap:10px; justify-content:flex-end; }
    .btn-send { padding:10px 24px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; cursor:pointer; transition:0.2s; }
    .btn-send:hover { background:var(--red-bright); }
    .btn-modal-cancel { padding:10px 16px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:14px; cursor:pointer; transition:0.2s; }
    .btn-modal-cancel:hover { color:#F5F0EE; }

    /* Toast */
    .toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:var(--soil-card); border:1px solid var(--soil-line); color:#F5F0EE; padding:11px 22px; border-radius:10px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:9px; z-index:9999; animation:toastIn 0.25s ease; box-shadow:0 8px 32px rgba(0,0,0,0.5); }
    .toast i { color:var(--green); }
    @keyframes toastIn { from { opacity:0; transform:translate(-50%,12px); } to { opacity:1; transform:translate(-50%,0); } }

    /* Footer */
    .footer { position:relative; z-index:2; border-top:1px solid var(--soil-line); padding:20px 24px; display:flex; align-items:center; justify-content:space-between; font-size:12px; color:var(--text-muted); flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--red-bright); }

    /* Light mode */
    body.light { --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FFF4E0; --amber:#B8620A; }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; } body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn, body.light .notif-btn-nav, body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .page-title { color:#1A0A09; }
    body.light .stat-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .stat-num { color:#1A0A09; }
    body.light .section-title { color:#1A0A09; }
    body.light .recruiter-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .recruiter-row:hover { background:#FEF0EE; }
    body.light .rec-name { color:#1A0A09; }
    body.light .search-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-box input { color:#1A0A09; }
    body.light .filter-select { background:#FFFFFF; border-color:#E0CECA; color:#4A2828; }
    body.light .rec-action-btn { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .invite-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-title { color:#1A0A09; }
    body.light .modal-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .role-opt { background:#F5EEEC; border-color:#E0CECA; }
    body.light .role-opt-name { color:#1A0A09; }
    body.light .toast { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }
    body.light .footer { border-color:#E0CECA; }

    @media(max-width:760px) {
      .nav-links{display:none} .hamburger{display:flex}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .stats-row { grid-template-columns:repeat(2,1fr); }
      .recruiter-row { grid-template-columns:auto 1fr; }
      .rec-actions { grid-column:1/-1; justify-content:flex-end; }
      .role-options { grid-template-columns:1fr; }
      .page-shell{padding:24px 16px 60px}
    }
      .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); background:var(--red-vivid); }
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
    .n-dot.red { background:var(--red-vivid); } .n-dot.amber { background:var(--amber); } .n-dot.green { background:#4CAF70; } .n-dot.read { background:var(--soil-line); }
    .n-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
    .n-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }
  </style>
</head>
<body>
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-head">
    <div class="notif-panel-title"><i class="fas fa-bell"></i> Notifications</div>
    <button class="notif-close" onclick="closeNotif()"><i class="fas fa-times"></i></button>
  </div>
  <div class="notif-panel-body">
    <div class="notif-item"><div class="n-dot green"></div><div><div class="n-text">Your application for <strong>Senior Frontend Engineer</strong> at Vercel was submitted.</div><div class="n-time">1 hour ago</div></div></div>
    <div class="notif-item"><div class="n-dot amber"></div><div><div class="n-text">Your status for <strong>Product Designer</strong> at Linear was updated to <em>Shortlisted</em>.</div><div class="n-time">3 hours ago</div></div></div>
    <div class="notif-item"><div class="n-dot red"></div><div><div class="n-text">You received a new message from <strong>TechPH Inc.</strong></div><div class="n-time">Yesterday</div></div></div>
    <div class="notif-item"><div class="n-dot read"></div><div><div class="n-text">3 new jobs matching your profile in Manila.</div><div class="n-time">Mar 27</div></div></div>
  </div>
</div>


<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g stroke="#C0392B" stroke-width="1.5" fill="none" opacity="0.6">
      <path d="M0 200 Q200 180 350 240 Q500 300 600 260 Q750 210 900 280 Q1050 350 1200 300 Q1320 260 1440 280"/>
      <path d="M0 450 Q150 430 300 490 Q500 560 650 510 Q800 460 950 530 Q1100 600 1300 550 Q1380 530 1440 540"/>
    </g>
    <g fill="#E54C3A" opacity="0.4">
      <circle cx="350" cy="240" r="3.5"/><circle cx="900" cy="280" r="3.5"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->
<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<!-- PAGE -->
<div class="page-shell">
  <div class="breadcrumb">
    <a href="employer_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span>Manage Recruiters</span>
  </div>

  <div class="page-header">
    <div>
      <div class="page-title">Manage Recruiters</div>
      <div class="page-sub">Control who can post jobs and review applicants on behalf of your company.</div>
    </div>
    <button class="btn-invite" onclick="openInviteModal()"><i class="fas fa-user-plus"></i> Invite Recruiter</button>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-num">1</div><div class="stat-label">Company Admin</div></div>
    <div class="stat-card"><div class="stat-num amber">3</div><div class="stat-label">Recruiters</div></div>
    <div class="stat-card"><div class="stat-num green">3</div><div class="stat-label">Currently Active</div></div>
    <div class="stat-card"><div class="stat-num red">2</div><div class="stat-label">Pending Invites</div></div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search recruiters..." oninput="filterRecruiters(this.value)" id="searchInput">
    </div>
    <select class="filter-select" onchange="filterByRole(this.value)">
      <option value="">All Roles</option>
      <option value="Admin">Company Admin</option>
      <option value="Recruiter">Recruiter</option>
      <option value="Viewer">Viewer</option>
    </select>
  </div>

  <!-- Recruiter list -->
  <div class="recruiter-list" id="recruiterList"></div>

  <!-- Pending Invites -->
  <div class="section-title"><i class="fas fa-envelope-open-text"></i> Pending Invites <span style="background:rgba(212,148,58,0.15);color:var(--amber);border:1px solid rgba(212,148,58,0.2);font-size:11px;padding:2px 8px;border-radius:8px;font-weight:600;">2</span></div>
  <div style="display:flex;flex-direction:column;gap:8px;" id="inviteList"></div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Manage Recruiters — <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
  <div style="display:flex;gap:14px;">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<!-- Invite Modal -->
<div class="modal-overlay" id="inviteModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeInviteModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title">Invite a Recruiter</div>
    <div class="modal-sub">They'll receive an email to join <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?> on AntCareers.</div>
    <div class="modal-label">Email Address</div>
    <input class="modal-input" type="email" placeholder="recruiter@example.com" id="inviteEmail">
    <div class="modal-label">Assign Role</div>
    <div class="role-options">
      <div class="role-opt" onclick="selectRole(this,'Recruiter')">
        <div class="role-opt-icon">👔</div>
        <div class="role-opt-name">Recruiter</div>
        <div class="role-opt-desc">Post jobs &amp; review applicants</div>
      </div>
      <div class="role-opt selected" onclick="selectRole(this,'Admin')">
        <div class="role-opt-icon">🛡️</div>
        <div class="role-opt-name">Co-Admin</div>
        <div class="role-opt-desc">Full company access</div>
      </div>
      <div class="role-opt" onclick="selectRole(this,'Viewer')">
        <div class="role-opt-icon">👁️</div>
        <div class="role-opt-name">Viewer</div>
        <div class="role-opt-desc">View-only access</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeInviteModal()">Cancel</button>
      <button class="btn-send" onclick="sendInvite()"><i class="fas fa-paper-plane"></i> Send Invite</button>
    </div>
  </div>
</div>

<script>
  const recruitersData = [
    { id:1, name:'Maria Admin', initials:'MA', color:'linear-gradient(135deg,#D13D2C,#7A1515)', role:'Admin', email:'maria@techph.com', status:'online', jobs:3, reviews:27, joined:'Feb 10, 2026' },
    { id:2, name:'Jose Maniego', initials:'JM', color:'linear-gradient(135deg,#4A90D9,#2A6090)', role:'Recruiter', email:'jose@techph.com', status:'online', jobs:2, reviews:14, joined:'Mar 1, 2026' },
    { id:3, name:'Ana Lim', initials:'AL', color:'linear-gradient(135deg,#9C27B0,#5A1070)', role:'Recruiter', email:'ana@techph.com', status:'offline', jobs:1, reviews:8, joined:'Mar 5, 2026' },
    { id:4, name:'Rico Santos', initials:'RS', color:'linear-gradient(135deg,#4CAF70,#2A7040)', role:'Recruiter', email:'rico@techph.com', status:'online', jobs:0, reviews:0, joined:'Mar 20, 2026' },
  ];

  const pendingInvites = [
    { email:'ben.cruz@gmail.com', role:'Recruiter', sent:'Mar 25, 2026' },
    { email:'grace.tan@outlook.com', role:'Viewer', sent:'Mar 27, 2026' },
  ];

  let selectedRoleInvite = 'Admin';

  function renderRecruiters(data) {
    const c = document.getElementById('recruiterList');
    if (!data.length) { c.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);">No recruiters found.</div>'; return; }
    c.innerHTML = data.map(r => `
      <div class="recruiter-row">
        <div class="rec-avatar" style="background:${r.color}">${r.initials}</div>
        <div class="rec-info">
          <div class="rec-name">${r.name} ${r.id===1?'<span style="font-size:10px;background:rgba(209,61,44,0.1);color:var(--red-pale);border:1px solid rgba(209,61,44,0.2);padding:2px 7px;border-radius:8px;margin-left:4px;">You</span>':''}</div>
          <div class="rec-email">${r.email}</div>
          <div class="rec-meta">
            <span class="rec-badge ${r.role==='Admin'?'rb-admin':r.role==='Recruiter'?'rb-recruiter':'rb-viewer'}">${r.role==='Admin'?'Company Admin':r.role}</span>
            <span class="rec-stat"><i class="fas fa-briefcase"></i> ${r.jobs} jobs posted</span>
            <span class="rec-stat"><i class="fas fa-users"></i> ${r.reviews} applicants reviewed</span>
            <span class="rec-stat"><i class="fas fa-calendar"></i> Joined ${r.joined}</span>
          </div>
        </div>
        <div class="rec-actions">
          <div class="rec-status ${r.status==='online'?'rs-online':'rs-offline'}" title="${r.status==='online'?'Active now':'Offline'}"></div>
          ${r.id!==1?`
            <button class="rec-action-btn" onclick="showToast('Edit role for ${r.name}','fa-pen')"><i class="fas fa-pen"></i> Edit</button>
            <button class="rec-action-btn danger" onclick="removeRecruiter(${r.id},'${r.name}')"><i class="fas fa-user-minus"></i> Remove</button>
          `:''} 
        </div>
      </div>
    `).join('');
  }

  function renderInvites() {
    const c = document.getElementById('inviteList');
    c.innerHTML = pendingInvites.map((inv,i) => `
      <div class="invite-row">
        <div>
          <div class="invite-email"><i class="fas fa-envelope"></i> ${inv.email} <span class="rec-badge rb-${inv.role==='Recruiter'?'recruiter':'viewer'}">${inv.role}</span></div>
          <div class="invite-meta">Sent ${inv.sent} · Awaiting acceptance</div>
        </div>
        <div class="invite-actions">
          <button class="btn-resend" onclick="showToast('Invite resent to ${inv.email}','fa-paper-plane')">Resend</button>
          <button class="btn-revoke" onclick="this.closest('.invite-row').style.opacity='0.3';showToast('Invite revoked','fa-times')">Revoke</button>
        </div>
      </div>
    `).join('');
  }

  function filterRecruiters(q) {
    const f = recruitersData.filter(r => r.name.toLowerCase().includes(q.toLowerCase()) || r.email.toLowerCase().includes(q.toLowerCase()));
    renderRecruiters(f);
  }

  function filterByRole(role) {
    const f = role ? recruitersData.filter(r => r.role===role) : recruitersData;
    renderRecruiters(f);
  }

  function removeRecruiter(id, name) {
    showToast(`${name} has been removed from <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>`, 'fa-user-minus');
    const updated = recruitersData.filter(r => r.id !== id);
    renderRecruiters(updated);
  }

  function openInviteModal() { document.getElementById('inviteModal').classList.add('open'); }
  function closeInviteModal() { document.getElementById('inviteModal').classList.remove('open'); }

  function selectRole(el, role) {
    document.querySelectorAll('.role-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    selectedRoleInvite = role;
  }

  function sendInvite() {
    const email = document.getElementById('inviteEmail').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      document.getElementById('inviteEmail').style.borderColor = 'var(--red-bright)';
      setTimeout(() => document.getElementById('inviteEmail').style.borderColor = '', 1000);
      return;
    }
    closeInviteModal();
    showToast(`Invite sent to ${email}`, 'fa-paper-plane');
    document.getElementById('inviteEmail').value = '';
  }

  function showToast(msg, icon) {
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2400);
  }

  // Theme
  function setTheme(t) {
    document.body.classList.toggle('light', t==='light'); document.body.classList.toggle('dark', t!=='light');
    localStorage.setItem('ac-theme', t);
    document.getElementById('themeToggle').querySelector('i').className = t==='light' ? 'fas fa-sun' : 'fas fa-moon';
  }
  const _guard_themeToggle = document.getElementById('themeToggle'); if (_guard_themeToggle) _guard_themeToggle.addEventListener('click', () =>
    setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));

  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    const open = mobileMenu.classList.toggle('open');
    hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
  });

  const _guard_profileToggle = document.getElementById('profileToggle'); if (_guard_profileToggle) _guard_profileToggle.addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('profileDropdown').classList.toggle('open');
  });
  document.addEventListener('click', e => {
    if (!document.getElementById('profileWrap').contains(e.target))
      document.getElementById('profileDropdown').classList.remove('open');
    if (!mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
    if (e.target === document.getElementById('inviteModal')) closeInviteModal();
  });

  (function() {
    const p = new URLSearchParams(window.location.search).get('theme');
    const s = localStorage.getItem('ac-theme');
    const t = p || s || 'light';
    if (p) localStorage.setItem('ac-theme', p);
    setTheme(t);
  })();

  renderRecruiters(recruitersData);
  renderInvites();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>