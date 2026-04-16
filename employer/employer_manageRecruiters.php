<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
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
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; display:flex; flex-direction:column; }
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
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; min-width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); overflow:hidden; padding:0 3px; }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.02em; box-shadow:0 2px 8px rgba(209,61,44,0.3); text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.45); }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--amber), #8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .profile-avatar img { width:100%; height:100%; object-fit:cover; }
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
    .page-shell { width:100%; max-width:1380px; margin:0 auto; padding:28px 24px 60px; position:relative; z-index:1; flex:1; }
    .breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); margin-bottom:24px; }
    .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:0.15s; }
    .breadcrumb a:hover { color:var(--red-pale); }
    .breadcrumb i { font-size:9px; }
    .page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:22px; flex-wrap:wrap; gap:12px; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .page-title span { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); }

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
    .rec-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .rec-avatar img { width:100%; height:100%; object-fit:cover; }
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
    body.light .theme-btn, body.light .notif-btn-nav, body.light .profile-btn, body.light .hamburger { background:#F5EDEB; border-color:#D4B0AB; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#7A5555; }
    body.light .stat-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .stat-num { color:#1A0A09; }
    body.light .stat-label { color:#7A5555; }
    body.light .section-title { color:#1A0A09; }
    body.light .recruiter-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .recruiter-row:hover { background:#FEF0EE; }
    body.light .rec-name { color:#1A0A09; }
    body.light .search-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-box input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .filter-select { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
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
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
  </style>
</head>
<body>
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
<div class="page-shell anim">
  <div class="page-header">
    <div>
      <h1 class="page-title">Manage <span>Recruiters</span></h1>
      <p class="page-sub">Control who can post jobs and review applicants on behalf of your company.</p>
    </div>
    <button class="btn-invite" onclick="openInviteModal()"><i class="fas fa-user-plus"></i> Invite Recruiter</button>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card"><div class="stat-num" id="statTotal">—</div><div class="stat-label">Total Recruiters</div></div>
    <div class="stat-card"><div class="stat-num amber" id="statActive">—</div><div class="stat-label">Currently Active</div></div>
    <div class="stat-card"><div class="stat-num green" id="statHired">—</div><div class="stat-label">Total Hired</div></div>
    <div class="stat-card"><div class="stat-num red" id="statJobs">—</div><div class="stat-label">Jobs Posted</div></div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search recruiters..." oninput="filterRecruiters(this.value)" id="searchInput">
    </div>
    <select class="filter-select" onchange="filterByStatus(this.value)">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
  </div>

  <!-- Recruiter list -->
  <div class="recruiter-list" id="recruiterList"></div>

  <!-- Inactive Recruiters -->
  <div class="section-title" id="inactiveSection" style="display:none;"><i class="fas fa-user-slash"></i> Inactive Recruiters</div>
  <div style="display:flex;flex-direction:column;gap:8px;" id="inactiveList"></div>
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

<!-- Add Recruiter Modal -->
<div class="modal-overlay" id="inviteModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeInviteModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title">Add a Recruiter</div>
    <div class="modal-sub">Create a recruiter account for <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>. Credentials will be sent via in-platform message.</div>
    <div class="modal-label">First Name</div>
    <input class="modal-input" type="text" placeholder="First name" id="inviteFirstName" oninput="previewCredentials()">
    <div class="modal-label">Last Name</div>
    <input class="modal-input" type="text" placeholder="Last name" id="inviteLastName" oninput="previewCredentials()">
    <div class="modal-label">Position / Job Title</div>
    <input class="modal-input" type="text" placeholder="e.g. Senior Recruiter" id="invitePosition">
    <div class="modal-label">Personal Email</div>
    <input class="modal-input" type="email" placeholder="personal@example.com" id="inviteEmail">
    <div style="font-size:11px;color:var(--text-muted);margin:-8px 0 14px;">This is where credentials will be sent via the in-platform message system.</div>

    <div style="background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:10px;padding:14px;margin-bottom:14px;">
      <div style="font-size:11px;font-weight:700;color:var(--amber);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;"><i class="fas fa-key"></i> Auto-Generated Credentials (Preview)</div>
      <div class="modal-label" style="margin-bottom:4px;">Platform Email</div>
      <div class="modal-input" id="previewPlatformEmail" style="background:var(--soil-dark);color:var(--amber);user-select:all;cursor:default;font-family:monospace;font-size:13px;">—</div>
      <div class="modal-label" style="margin-bottom:4px;">Temporary Password</div>
      <div class="modal-input" id="previewTempPassword" style="background:var(--soil-dark);color:var(--amber);user-select:all;cursor:default;font-family:monospace;font-size:13px;margin-bottom:0;">—</div>
    </div>

    <div id="modalError" style="display:none;font-size:12px;color:var(--red-bright);margin-bottom:12px;"></div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeInviteModal()">Cancel</button>
      <button class="btn-send" id="addRecruiterBtn" onclick="addRecruiter()"><i class="fas fa-paper-plane"></i> Send Invite</button>
    </div>
  </div>
</div>

<!-- Credentials Modal -->
<div class="modal-overlay" id="credentialsModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeCredentialsModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title"><i class="fas fa-check-circle" style="color:var(--green);"></i> Recruiter Account Created</div>
    <div class="modal-sub">Credentials have been sent to the recruiter via in-platform message. They must change their password on first login.</div>
    <div class="modal-label">Platform Email (Login)</div>
    <div class="modal-input" id="credEmail" style="background:var(--soil-hover);user-select:all;cursor:text;font-family:monospace;"></div>
    <div class="modal-label">Temporary Password</div>
    <div class="modal-input" id="credPassword" style="background:var(--soil-hover);user-select:all;cursor:text;font-family:monospace;"></div>
    <div class="modal-label">Credentials Sent To</div>
    <div class="modal-input" id="credSentTo" style="background:var(--soil-hover);user-select:all;cursor:text;"></div>
    <div class="modal-footer">
      <button class="btn-send" onclick="closeCredentialsModal()"><i class="fas fa-check"></i> Done</button>
    </div>
  </div>
</div>

<!-- View Stats Modal -->
<div class="modal-overlay" id="statsModal">
  <div class="modal-box" style="max-width:500px;">
    <button class="modal-close" onclick="closeStatsModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title"><i class="fas fa-chart-bar" style="color:var(--red-bright);"></i> Recruiter Stats</div>
    <div class="modal-sub" id="statsRecName">Loading...</div>
    <div id="statsContent" style="display:flex;flex-direction:column;gap:12px;">
      <div style="text-align:center;padding:20px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading stats...</div>
    </div>
    <div class="modal-footer" style="margin-top:16px;">
      <button class="btn-modal-cancel" onclick="closeStatsModal()">Close</button>
    </div>
  </div>
</div>

<!-- Reassign Jobs Modal -->
<div class="modal-overlay" id="reassignModal">
  <div class="modal-box" style="max-width:460px;">
    <button class="modal-close" onclick="closeReassignModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title"><i class="fas fa-exchange-alt" style="color:var(--amber);"></i> Reassign Jobs</div>
    <div class="modal-sub" id="reassignDesc">Move all active job posts from this recruiter to another.</div>
    <input type="hidden" id="reassignFromId" value="">
    <div class="modal-label">Reassign To</div>
    <select class="modal-input" id="reassignToSelect" style="cursor:pointer;">
      <option value="">Select an active recruiter...</option>
    </select>
    <div id="reassignError" style="display:none;font-size:12px;color:var(--red-bright);margin-top:-8px;margin-bottom:12px;"></div>
    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeReassignModal()">Cancel</button>
      <button class="btn-send" id="reassignBtn" onclick="doReassign()"><i class="fas fa-exchange-alt"></i> Reassign</button>
    </div>
  </div>
</div>

<script>
  const API = '../api/recruiters.php';
  let allRecruiters = [];
  let currentFilter = '';
  let currentSearch = '';

  // -------- Load data --------
  async function loadRecruiters() {
    try {
      const res = await fetch(API + '?action=list_recruiters');
      const data = await res.json();
      if (!data.success) { showToast(data.message || 'Failed to load', 'fa-exclamation-triangle'); return; }
      allRecruiters = data.recruiters || [];
      applyFilters();
      updateStats();
    } catch(e) {
      showToast('Network error loading recruiters', 'fa-exclamation-triangle');
    }
  }

  function updateStats() {
    const active = allRecruiters.filter(r => r.status === 'active');
    const totalJobs = allRecruiters.reduce((s,r) => s + (parseInt(r.jobs_posted)||0), 0);
    const totalHired = allRecruiters.reduce((s,r) => s + (parseInt(r.hired_count)||0), 0);
    document.getElementById('statTotal').textContent = allRecruiters.length;
    document.getElementById('statActive').textContent = active.length;
    document.getElementById('statHired').textContent = totalHired;
    document.getElementById('statJobs').textContent = totalJobs;
  }

  function applyFilters() {
    let list = allRecruiters;
    if (currentFilter) list = list.filter(r => r.status === currentFilter);
    if (currentSearch) {
      const q = currentSearch.toLowerCase();
      list = list.filter(r => r.name.toLowerCase().includes(q) || r.email.toLowerCase().includes(q));
    }

    const activeList = list.filter(r => r.status === 'active');
    const inactiveList = list.filter(r => r.status !== 'active');

    renderRecruiters(activeList);
    renderInactive(inactiveList);
  }

  function filterRecruiters(q) { currentSearch = q; applyFilters(); }
  function filterByStatus(s) { currentFilter = s; applyFilters(); }

  const COMPANY_SLUG = <?php echo json_encode(strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $companyName))); ?>;

  // -------- Preview Credentials --------
  function previewCredentials() {
    const first = document.getElementById('inviteFirstName').value.trim();
    const last = document.getElementById('inviteLastName').value.trim();
    const emailEl = document.getElementById('previewPlatformEmail');
    const pwEl = document.getElementById('previewTempPassword');
    if (first && last) {
      const slug = COMPANY_SLUG || 'company';
      emailEl.textContent = first.charAt(0).toLowerCase() + '.' + last.toLowerCase().replace(/[^a-z]/g, '') + '@' + slug + '.work';
      pwEl.textContent = '(auto-generated on submit)';
    } else {
      emailEl.textContent = '—';
      pwEl.textContent = '—';
    }
  }

  // -------- Render --------
  const avatarColors = [
    'linear-gradient(135deg,#D13D2C,#7A1515)',
    'linear-gradient(135deg,#4A90D9,#2A6090)',
    'linear-gradient(135deg,#9C27B0,#5A1070)',
    'linear-gradient(135deg,#4CAF70,#2A7040)',
    'linear-gradient(135deg,#D4943A,#8A5010)',
    'linear-gradient(135deg,#E91E63,#880E4F)',
  ];

  function getInitials(name) {
    const parts = name.trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[1][0]).toUpperCase()
      : name.substring(0,2).toUpperCase();
  }

  function renderRecruiters(data) {
    const c = document.getElementById('recruiterList');
    if (!data.length) { c.innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);">No active recruiters found.</div>'; return; }
    c.innerHTML = data.map((r, i) => `
      <div class="recruiter-row" data-id="${r.id}">
        <div class="rec-avatar" style="background:${avatarColors[i % avatarColors.length]}">${r.avatar_url ? `<img src="../${r.avatar_url}" alt="">` : getInitials(r.name)}</div>
        <div class="rec-info">
          <div class="rec-name">${esc(r.name)} <span class="rec-badge rb-recruiter">${esc(r.position || r.role_label || 'Recruiter')}</span></div>
          <div class="rec-email">${esc(r.email)}${r.personal_email ? ' <span style="color:var(--text-muted);font-size:11px;">(' + esc(r.personal_email) + ')</span>' : ''}</div>
          <div class="rec-meta">
            <span class="rec-stat"><i class="fas fa-briefcase"></i> ${r.jobs_posted || 0} jobs posted</span>
            <span class="rec-stat"><i class="fas fa-users"></i> ${r.applicants_reviewed || 0} reviewed</span>
            <span class="rec-stat"><i class="fas fa-user-check"></i> ${r.hired_count || 0} hired</span>
            <span class="rec-stat"><i class="fas fa-calendar"></i> Joined ${formatDate(r.joined_at)}</span>
          </div>
        </div>
        <div class="rec-actions">
          <div class="rec-status rs-online" title="Active"></div>
          <button class="rec-action-btn" onclick="viewStats(${r.id},'${esc(r.name)}')"><i class="fas fa-chart-bar"></i> View Stats</button>
          <button class="rec-action-btn" onclick="resetPassword(${r.id},'${esc(r.name)}')"><i class="fas fa-key"></i> Reset PW</button>
          <button class="rec-action-btn danger" onclick="deactivateRecruiter(${r.id},'${esc(r.name)}')"><i class="fas fa-user-minus"></i> Deactivate</button>
        </div>
      </div>
    `).join('');
  }

  function renderInactive(data) {
    const section = document.getElementById('inactiveSection');
    const c = document.getElementById('inactiveList');
    if (!data.length) { section.style.display = 'none'; c.innerHTML = ''; return; }
    section.style.display = '';
    c.innerHTML = data.map((r, i) => `
      <div class="recruiter-row" style="opacity:0.65;" data-id="${r.id}">
        <div class="rec-avatar" style="background:${avatarColors[(i+3) % avatarColors.length]}">${r.avatar_url ? `<img src="../${r.avatar_url}" alt="">` : getInitials(r.name)}</div>
        <div class="rec-info">
          <div class="rec-name">${esc(r.name)} <span class="rec-badge" style="background:rgba(85,85,85,0.2);color:#888;border:1px solid rgba(85,85,85,0.3);">Inactive</span></div>
          <div class="rec-email">${esc(r.email)}</div>
        </div>
        <div class="rec-actions">
          <div class="rec-status rs-offline" title="Inactive"></div>
          <button class="rec-action-btn" onclick="openReassignModal(${r.id},'${esc(r.name)}')"><i class="fas fa-exchange-alt"></i> Reassign Jobs</button>
          <button class="rec-action-btn" style="color:var(--green);" onclick="reactivateRecruiter(${r.id},'${esc(r.name)}')"><i class="fas fa-user-check"></i> Reactivate</button>
        </div>
      </div>
    `).join('');
  }

  // -------- Actions --------
  async function addRecruiter() {
    const first = document.getElementById('inviteFirstName').value.trim();
    const last  = document.getElementById('inviteLastName').value.trim();
    const position = document.getElementById('invitePosition').value.trim();
    const email = document.getElementById('inviteEmail').value.trim();
    const errEl = document.getElementById('modalError');
    errEl.style.display = 'none';

    if (!first || !last || !email) {
      errEl.textContent = 'First name, last name, and personal email are required.'; errEl.style.display = 'block'; return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errEl.textContent = 'Please enter a valid email.'; errEl.style.display = 'block'; return;
    }

    const btn = document.getElementById('addRecruiterBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
      const fd = new FormData();
      fd.append('action', 'add_recruiter');
      fd.append('first_name', first);
      fd.append('last_name', last);
      fd.append('position', position);
      fd.append('personal_email', email);

      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        closeInviteModal();
        // Show credentials modal
        document.getElementById('credEmail').textContent = data.recruiter.platform_email;
        document.getElementById('credPassword').textContent = data.recruiter.temp_password;
        document.getElementById('credSentTo').textContent = data.recruiter.personal_email;
        document.getElementById('credentialsModal').classList.add('open');
        showToast('Recruiter added — credentials sent via message', 'fa-paper-plane');
        loadRecruiters();
      } else {
        errEl.textContent = data.message || 'Failed to add recruiter.'; errEl.style.display = 'block';
      }
    } catch(e) {
      errEl.textContent = 'Network error.'; errEl.style.display = 'block';
    }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invite';
  }

  async function deactivateRecruiter(id, name) {
    if (!confirm(`Deactivate ${name}? They will lose access to company pages.`)) return;
    const fd = new FormData();
    fd.append('action', 'deactivate_recruiter');
    fd.append('recruiter_id', id);
    try {
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) { showToast(`${name} deactivated`, 'fa-user-minus'); loadRecruiters(); }
      else showToast(data.message || 'Failed', 'fa-exclamation-triangle');
    } catch(e) { showToast('Network error', 'fa-exclamation-triangle'); }
  }

  async function reactivateRecruiter(id, name) {
    const fd = new FormData();
    fd.append('action', 'reactivate_recruiter');
    fd.append('recruiter_id', id);
    try {
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) { showToast(`${name} reactivated`, 'fa-user-check'); loadRecruiters(); }
      else showToast(data.message || 'Failed', 'fa-exclamation-triangle');
    } catch(e) { showToast('Network error', 'fa-exclamation-triangle'); }
  }

  async function resetPassword(id, name) {
    if (!confirm(`Reset password for ${name}? A new temporary password will be generated.`)) return;
    const fd = new FormData();
    fd.append('action', 'reset_password');
    fd.append('recruiter_id', id);
    try {
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        document.getElementById('credEmail').textContent = name;
        document.getElementById('credPassword').textContent = data.temp_password;
        document.getElementById('credentialsModal').classList.add('open');
        showToast('Password reset', 'fa-key');
      } else showToast(data.message || 'Failed', 'fa-exclamation-triangle');
    } catch(e) { showToast('Network error', 'fa-exclamation-triangle'); }
  }

  // -------- View Stats --------
  async function viewStats(id, name) {
    document.getElementById('statsRecName').textContent = name;
    document.getElementById('statsContent').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading stats...</div>';
    document.getElementById('statsModal').classList.add('open');
    try {
      const res = await fetch(API + '?action=recruiter_stats&recruiter_id=' + id);
      const data = await res.json();
      if (data.success && data.stats) {
        const s = data.stats;
        document.getElementById('statsContent').innerHTML = `
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="stat-card"><div class="stat-num">${s.jobs_posted || 0}</div><div class="stat-label">Jobs Posted</div></div>
            <div class="stat-card"><div class="stat-num amber">${s.applicants_reviewed || 0}</div><div class="stat-label">Applicants Reviewed</div></div>
            <div class="stat-card"><div class="stat-num green">${s.interviews_scheduled || 0}</div><div class="stat-label">Interviews Scheduled</div></div>
            <div class="stat-card"><div class="stat-num red">${s.hires_made || 0}</div><div class="stat-label">Hires Made</div></div>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
            <i class="fas fa-clock"></i> Last active: ${s.last_login_at ? formatDate(s.last_login_at) : 'Never'}
          </div>`;
      } else {
        document.getElementById('statsContent').innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">No stats available for this recruiter.</div>';
      }
    } catch(e) {
      document.getElementById('statsContent').innerHTML = '<div style="text-align:center;padding:20px;color:var(--red-bright);">Failed to load stats.</div>';
    }
  }
  function closeStatsModal() { document.getElementById('statsModal').classList.remove('open'); }

  // -------- Reassign Jobs --------
  function openReassignModal(fromId, fromName) {
    document.getElementById('reassignFromId').value = fromId;
    document.getElementById('reassignDesc').textContent = `Move all active job posts from ${fromName} to another recruiter.`;
    document.getElementById('reassignError').style.display = 'none';
    const sel = document.getElementById('reassignToSelect');
    sel.innerHTML = '<option value="">Select an active recruiter...</option>';
    allRecruiters.filter(r => r.status === 'active' && r.id !== fromId).forEach(r => {
      sel.innerHTML += `<option value="${r.id}">${esc(r.name)}</option>`;
    });
    document.getElementById('reassignModal').classList.add('open');
  }
  function closeReassignModal() { document.getElementById('reassignModal').classList.remove('open'); }

  async function doReassign() {
    const fromId = document.getElementById('reassignFromId').value;
    const toId = document.getElementById('reassignToSelect').value;
    const errEl = document.getElementById('reassignError');
    errEl.style.display = 'none';
    if (!toId) { errEl.textContent = 'Please select a recruiter.'; errEl.style.display = 'block'; return; }
    const btn = document.getElementById('reassignBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reassigning...';
    try {
      const fd = new FormData();
      fd.append('action', 'reassign_jobs');
      fd.append('from_recruiter_id', fromId);
      fd.append('to_recruiter_id', toId);
      const res = await fetch(API, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        closeReassignModal();
        showToast(data.message || 'Jobs reassigned', 'fa-exchange-alt');
        loadRecruiters();
      } else {
        errEl.textContent = data.message || 'Failed.'; errEl.style.display = 'block';
      }
    } catch(e) {
      errEl.textContent = 'Network error.'; errEl.style.display = 'block';
    }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-exchange-alt"></i> Reassign';
  }

  // -------- Modals --------
  function openInviteModal() {
    document.getElementById('inviteFirstName').value = '';
    document.getElementById('inviteLastName').value = '';
    document.getElementById('invitePosition').value = '';
    document.getElementById('inviteEmail').value = '';
    document.getElementById('previewPlatformEmail').textContent = '—';
    document.getElementById('previewTempPassword').textContent = '—';
    document.getElementById('modalError').style.display = 'none';
    document.getElementById('inviteModal').classList.add('open');
  }
  function closeInviteModal() { document.getElementById('inviteModal').classList.remove('open'); }
  function closeCredentialsModal() { document.getElementById('credentialsModal').classList.remove('open'); }

  // -------- Helpers --------
  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function formatDate(d) {
    if (!d) return '—';
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
  }

  function showToast(msg, icon) {
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2400);
  }

  // Theme, hamburger, profile dropdown are now handled by navbar_employer.php shared script

  document.addEventListener('click', e => {
    if (e.target === document.getElementById('inviteModal')) closeInviteModal();
    if (e.target === document.getElementById('credentialsModal')) closeCredentialsModal();
    if (e.target === document.getElementById('statsModal')) closeStatsModal();
    if (e.target === document.getElementById('reassignModal')) closeReassignModal();
  });

  // Load on page ready
  loadRecruiters();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>