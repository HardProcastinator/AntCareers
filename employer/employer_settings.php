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
$navActive   = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Settings</title>
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

    /* NAVBAR */
    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 18px rgba(209,61,44,0.35); }
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
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; flex-shrink:0; }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--amber), #8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .profile-avatar img { width:100%; height:100%; object-fit:cover; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; font-weight:600; }
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

    /* LAYOUT */
    .page-shell { max-width:960px; margin:0 auto; padding:36px 24px 80px; }
    .breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); margin-bottom:24px; }
    .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:0.15s; }
    .breadcrumb a:hover { color:var(--red-pale); }
    .breadcrumb i.sep { font-size:9px; }
    .page-title { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .page-sub { font-size:13px; color:var(--text-muted); margin-bottom:32px; }

    /* Tabs */
    .tabs { display:flex; gap:4px; border-bottom:1px solid var(--soil-line); margin-bottom:28px; overflow-x:auto; }
    .tab { padding:10px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; border:none; background:none; font-family:var(--font-body); border-bottom:2px solid transparent; margin-bottom:-1px; transition:0.18s; display:flex; align-items:center; gap:6px; white-space:nowrap; }
    .tab:hover { color:var(--text-mid); }
    .tab.active { color:var(--red-pale); border-bottom-color:var(--red-vivid); }
    .tab i { font-size:12px; }

    /* Tab panels */
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }

    /* Settings cards */
    .s-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:24px; margin-bottom:18px; }
    .s-card-title { font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
    .s-card-title i { color:var(--red-bright); font-size:13px; }
    .s-card-sub { font-size:12px; color:var(--text-muted); margin-bottom:20px; }
    .s-divider { height:1px; background:var(--soil-line); margin:18px 0; }

    /* Form elements */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    .form-group.full { grid-column:1/-1; }
    .form-label { font-size:12px; font-weight:600; color:var(--text-muted); letter-spacing:0.03em; text-transform:uppercase; }
    .form-input { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 14px; font-size:14px; color:#F5F0EE; font-family:var(--font-body); transition:0.2s; outline:none; }
    .form-input:focus { border-color:var(--red-mid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .form-input::placeholder { color:var(--text-muted); }
    .input-with-btn { display:flex; gap:8px; }
    .input-with-btn .form-input { flex:1; }
    .btn-inline { padding:10px 16px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; white-space:nowrap; }
    .btn-inline:hover { color:#F5F0EE; border-color:var(--text-muted); }
    .pw-hint { font-size:11px; color:var(--text-muted); margin-top:3px; }

    /* Toggle rows */
    .toggle-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
    .toggle-row:last-child { border-bottom:none; padding-bottom:0; }
    .toggle-row:first-child { padding-top:0; }
    .toggle-info { flex:1; }
    .toggle-label { font-size:14px; font-weight:600; color:#F5F0EE; }
    .toggle-desc { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .toggle { position:relative; width:42px; height:24px; flex-shrink:0; }
    .toggle input { opacity:0; width:0; height:0; position:absolute; }
    .toggle-slider { position:absolute; inset:0; background:var(--soil-line); border-radius:12px; cursor:pointer; transition:0.2s; }
    .toggle-slider::before { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:3px; left:3px; transition:0.2s; }
    .toggle input:checked + .toggle-slider { background:var(--red-vivid); }
    .toggle input:checked + .toggle-slider::before { transform:translateX(18px); }

    /* Appearance options */
    .appearance-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .appear-opt { background:var(--soil-hover); border:2px solid var(--soil-line); border-radius:10px; padding:14px 10px; text-align:center; cursor:pointer; transition:0.2s; }
    .appear-opt.selected { border-color:var(--red-vivid); background:rgba(209,61,44,0.08); }
    .appear-opt-preview { height:40px; border-radius:6px; margin:0 auto 8px; width:80%; }
    .pv-dark { background:linear-gradient(135deg,#0A0909,#1C1818); border:1px solid #352E2E; }
    .pv-light { background:linear-gradient(135deg,#F9F5F4,#FFFFFF); border:1px solid #E0CECA; }
    .pv-system { background:linear-gradient(135deg,#0A0909 50%,#F9F5F4 50%); border:1px solid #888; }
    .appear-opt-name { font-size:12px; font-weight:700; color:#F5F0EE; }
    .appear-opt-desc { font-size:10px; color:var(--text-muted); margin-top:2px; }

    /* Privacy options */
    .privacy-select { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 12px; font-size:13px; color:var(--text-mid); font-family:var(--font-body); cursor:pointer; outline:none; transition:0.2s; }
    .privacy-select:focus { border-color:var(--red-mid); }

    /* Danger zone */
    .danger-card { background:rgba(224,85,85,0.05); border:1px solid rgba(224,85,85,0.2); border-radius:12px; padding:24px; margin-bottom:18px; }
    .danger-title { font-size:15px; font-weight:700; color:#E05555; margin-bottom:4px; display:flex; align-items:center; gap:8px; }
    .danger-sub { font-size:12px; color:var(--text-muted); margin-bottom:20px; }
    .danger-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 0; border-bottom:1px solid rgba(224,85,85,0.1); flex-wrap:wrap; }
    .danger-row:last-child { border-bottom:none; padding-bottom:0; }
    .danger-row:first-child { padding-top:0; }
    .danger-row-label { font-size:14px; font-weight:600; color:#F5F0EE; }
    .danger-row-desc { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .btn-danger { padding:8px 18px; border-radius:7px; background:transparent; border:1px solid rgba(224,85,85,0.4); color:#E05555; font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; white-space:nowrap; }
    .btn-danger:hover { background:rgba(224,85,85,0.12); border-color:#E05555; }

    /* Save bar */
    .save-bar { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:16px 0 0; border-top:1px solid var(--soil-line); margin-top:8px; }
    .btn-save { padding:10px 28px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; cursor:pointer; transition:0.2s; display:flex; align-items:center; gap:8px; }
    .btn-save:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.4); }
    .btn-cancel-sm { padding:10px 20px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:14px; font-weight:600; cursor:pointer; transition:0.2s; }
    .btn-cancel-sm:hover { border-color:var(--text-muted); color:#F5F0EE; }

    /* Confirm modal */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:500; display:none; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; width:100%; max-width:420px; }
    .modal-icon { width:52px; height:52px; border-radius:12px; background:rgba(224,85,85,0.1); border:1px solid rgba(224,85,85,0.2); display:flex; align-items:center; justify-content:center; font-size:22px; color:#E05555; margin-bottom:16px; }
    .modal-title { font-size:18px; font-weight:700; color:#F5F0EE; font-family:var(--font-display); margin-bottom:6px; }
    .modal-body { font-size:13px; color:var(--text-muted); line-height:1.6; margin-bottom:20px; }
    .modal-confirm-input { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 14px; font-size:14px; color:#F5F0EE; font-family:var(--font-body); width:100%; outline:none; margin-bottom:16px; }
    .modal-confirm-input:focus { border-color:#E05555; }
    .modal-confirm-input::placeholder { color:var(--text-muted); }
    .modal-footer { display:flex; gap:10px; justify-content:flex-end; }
    .btn-modal-cancel { padding:10px 16px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:14px; cursor:pointer; }
    .btn-modal-danger { padding:10px 22px; border-radius:8px; background:#D13D2C; border:none; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; cursor:pointer; transition:0.2s; }
    .btn-modal-danger:hover { background:#E85540; }
    .btn-modal-danger:disabled { opacity:0.4; cursor:not-allowed; }

    /* Toast */
    .toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:var(--soil-card); border:1px solid var(--soil-line); color:#F5F0EE; padding:11px 22px; border-radius:10px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:9px; z-index:9999; animation:toastIn 0.25s ease; box-shadow:0 8px 32px rgba(0,0,0,0.5); }
    .toast i { color:var(--green); }
    .toast.warn i { color:var(--amber); }
    @keyframes toastIn { from { opacity:0; transform:translate(-50%,12px); } to { opacity:1; transform:translate(-50%,0); } }

    /* Footer */
    .footer { position:relative; z-index:2; border-top:1px solid var(--soil-line); padding:20px 24px; display:flex; align-items:center; justify-content:space-between; font-size:12px; color:var(--text-muted); flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--red-bright); }

    /* Light mode */
    body.light { --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FFF4E0; --amber:#B8620A; }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; } body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; } body.light .nav-link:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn, body.light .notif-btn-nav, body.light .profile-btn, body.light .hamburger { background:#F5EDEB; border-color:#D4B0AB; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; } body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .page-title { color:#1A0A09; }
    body.light .s-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .s-card-title { color:#1A0A09; }
    body.light .toggle-label { color:#1A0A09; }
    body.light .form-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .appear-opt { background:#F5EEEC; border-color:#E0CECA; }
    body.light .appear-opt-name { color:#1A0A09; }
    body.light .privacy-select { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .tab { color:#7A5555; } body.light .tab.active { color:var(--red-mid); }
    body.light .tabs { border-color:#E0CECA; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-title { color:#1A0A09; }
    body.light .modal-confirm-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .btn-inline { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .toast { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }
    body.light .footer { border-color:#E0CECA; }

    @media(max-width:760px) {
      .nav-links{display:none} .hamburger{display:flex}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .form-grid { grid-template-columns:1fr; }
      .form-group.full { grid-column:1; }
      .appearance-grid { grid-template-columns:1fr 1fr; }
      .danger-row { flex-direction:column; align-items:flex-start; }
      .page-shell{padding:24px 16px 60px}
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
    <g fill="#E54C3A" opacity="0.4"><circle cx="350" cy="240" r="3.5"/><circle cx="900" cy="280" r="3.5"/></g>
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
    <i class="sep fas fa-chevron-right"></i>
    <span>Settings</span>
  </div>
  <div class="page-title">Settings</div>
  <div class="page-sub">Manage your account, preferences, and security.</div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('account',this)"><i class="fas fa-user"></i> Account</button>
    <button class="tab" onclick="switchTab('security',this)"><i class="fas fa-lock"></i> Security</button>
    <button class="tab" onclick="switchTab('notifications',this)"><i class="fas fa-bell"></i> Notifications</button>
    <button class="tab" onclick="switchTab('appearance',this)"><i class="fas fa-palette"></i> Appearance</button>
    <button class="tab" onclick="switchTab('privacy',this)"><i class="fas fa-shield-alt"></i> Privacy</button>
    <button class="tab" onclick="switchTab('danger',this)"><i class="fas fa-exclamation-triangle"></i> Danger Zone</button>
  </div>

  <!-- ACCOUNT TAB -->
  <div class="tab-panel active" id="tab-account">
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-id-card"></i> Personal Information</div>
      <div class="s-card-sub">Your name and contact details associated with this account.</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">First Name</label>
          <input class="form-input" type="text" value="Maria">
        </div>
        <div class="form-group">
          <label class="form-label">Last Name</label>
          <input class="form-input" type="text" value="Admin">
        </div>
        <div class="form-group full">
          <label class="form-label">Email Address</label>
          <div class="input-with-btn">
            <input class="form-input" type="email" value="maria@techph.com" id="emailInput">
            <button class="btn-inline" onclick="showToast('Verification email sent to new address','fa-envelope')">Change</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input class="form-input" type="tel" placeholder="+63 9XX XXX XXXX">
        </div>
        <div class="form-group">
          <label class="form-label">Job Title</label>
          <input class="form-input" type="text" value="HR Manager" placeholder="Your role at the company">
        </div>
        <div class="form-group full">
          <label class="form-label">Timezone</label>
          <select class="form-input">
            <option selected>Asia/Manila (GMT+8)</option>
            <option>Asia/Singapore (GMT+8)</option>
            <option>America/New_York (GMT-5)</option>
            <option>Europe/London (GMT+0)</option>
          </select>
        </div>
      </div>
      <div class="save-bar">
        <button class="btn-cancel-sm" onclick="showToast('Changes discarded','fa-undo')">Discard</button>
        <button class="btn-save" onclick="saveSettings('Account info')"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-language"></i> Language &amp; Region</div>
      <div class="s-card-sub">Display language and regional format preferences.</div>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Language</label>
          <select class="form-input">
            <option selected>English (US)</option>
            <option>Filipino</option>
            <option>English (UK)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date Format</label>
          <select class="form-input">
            <option>MM/DD/YYYY</option>
            <option selected>DD/MM/YYYY</option>
            <option>YYYY-MM-DD</option>
          </select>
        </div>
      </div>
      <div class="save-bar">
        <button class="btn-save" onclick="saveSettings('Language &amp; region')"><i class="fas fa-save"></i> Save</button>
      </div>
    </div>
  </div>

  <!-- SECURITY TAB -->
  <div class="tab-panel" id="tab-security">
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-key"></i> Change Password</div>
      <div class="s-card-sub">Use a strong password with at least 8 characters, a number, and a symbol.</div>
      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label">Current Password</label>
          <div class="input-with-btn">
            <input class="form-input" type="password" id="currentPw" placeholder="Enter current password">
            <button class="btn-inline" onclick="togglePw('currentPw',this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-with-btn">
            <input class="form-input" type="password" id="newPw" placeholder="At least 8 characters" oninput="checkPwStrength(this.value)">
            <button class="btn-inline" onclick="togglePw('newPw',this)"><i class="fas fa-eye"></i></button>
          </div>
          <div style="display:flex;gap:4px;margin-top:6px;" id="pwBars">
            <div style="height:4px;flex:1;border-radius:2px;background:var(--soil-line);" id="pb1"></div>
            <div style="height:4px;flex:1;border-radius:2px;background:var(--soil-line);" id="pb2"></div>
            <div style="height:4px;flex:1;border-radius:2px;background:var(--soil-line);" id="pb3"></div>
            <div style="height:4px;flex:1;border-radius:2px;background:var(--soil-line);" id="pb4"></div>
          </div>
          <div class="pw-hint" id="pwLabel"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="input-with-btn">
            <input class="form-input" type="password" id="confirmPw" placeholder="Re-enter new password">
            <button class="btn-inline" onclick="togglePw('confirmPw',this)"><i class="fas fa-eye"></i></button>
          </div>
        </div>
      </div>
      <div class="save-bar">
        <button class="btn-save" onclick="changePassword()"><i class="fas fa-lock"></i> Update Password</button>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</div>
      <div class="s-card-sub">Add an extra layer of protection to your account.</div>
      <div class="toggle-row">
        <div class="toggle-info">
          <div class="toggle-label">Enable 2FA</div>
          <div class="toggle-desc">Require a verification code when signing in from a new device.</div>
        </div>
        <label class="toggle"><input type="checkbox" onchange="showToast(this.checked?'2FA enabled — authenticator app required':'2FA disabled','fa-shield-alt')"><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <div class="toggle-label">SMS Backup Code</div>
          <div class="toggle-desc">Send a fallback code via SMS if your authenticator is unavailable.</div>
        </div>
        <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-history"></i> Login Sessions</div>
      <div class="s-card-sub">Devices currently signed in to your account.</div>
      <div id="sessionList"></div>
    </div>
  </div>

  <!-- NOTIFICATIONS TAB -->
  <div class="tab-panel" id="tab-notifications">
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-envelope"></i> Email Notifications</div>
      <div class="s-card-sub">Choose which emails you receive from AntCareers.</div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">New Application Received</div><div class="toggle-desc">Get notified when a job seeker applies to one of your postings.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Applicant Status Update</div><div class="toggle-desc">When a recruiter changes an applicant's status.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Interview Scheduled</div><div class="toggle-desc">Reminders for upcoming interviews you've scheduled.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">New Message Received</div><div class="toggle-desc">When a job seeker sends you a message.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Recruiter Invitation Accepted</div><div class="toggle-desc">When someone accepts your recruiter invite.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Platform Announcements</div><div class="toggle-desc">Updates and new features from AntCareers.</div></div>
        <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Weekly Digest</div><div class="toggle-desc">A summary of your recruitment activity every Monday.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-bell"></i> In-App Notifications</div>
      <div class="s-card-sub">Control what appears in your notification bell.</div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Application Alerts</div><div class="toggle-desc">Live alerts for new applicants.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Message Alerts</div><div class="toggle-desc">Ping when you receive a new message.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Job Posting Expiry Reminder</div><div class="toggle-desc">Alert you 3 days before a job post expires.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;">
      <button class="btn-save" onclick="saveSettings('Notification preferences')"><i class="fas fa-save"></i> Save Preferences</button>
    </div>
  </div>

  <!-- APPEARANCE TAB -->
  <div class="tab-panel" id="tab-appearance">
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-palette"></i> Theme</div>
      <div class="s-card-sub">Choose how AntCareers looks for you. System theme follows your OS preference.</div>
      <div class="appearance-grid">
        <div class="appear-opt" id="opt-dark" onclick="selectAppearance('dark',this)">
          <div class="appear-opt-preview pv-dark"></div>
          <div class="appear-opt-name">Dark</div>
          <div class="appear-opt-desc">Easy on the eyes at night</div>
        </div>
        <div class="appear-opt" id="opt-light" onclick="selectAppearance('light',this)">
          <div class="appear-opt-preview pv-light"></div>
          <div class="appear-opt-name">Light</div>
          <div class="appear-opt-desc">Clean and bright</div>
        </div>
        <div class="appear-opt" id="opt-system" onclick="selectAppearance('system',this)">
          <div class="appear-opt-preview pv-system"></div>
          <div class="appear-opt-name">System</div>
          <div class="appear-opt-desc">Follows OS setting</div>
        </div>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-text-height"></i> Display</div>
      <div class="s-card-sub">Font size and density settings.</div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Compact Mode</div><div class="toggle-desc">Reduce spacing in lists and tables for more content on screen.</div></div>
        <label class="toggle"><input type="checkbox" onchange="showToast(this.checked?'Compact mode on':'Compact mode off','fa-compress')"><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Reduced Motion</div><div class="toggle-desc">Minimize animations throughout the interface.</div></div>
        <label class="toggle"><input type="checkbox" onchange="showToast('Motion preference saved','fa-ban')"><span class="toggle-slider"></span></label>
      </div>
    </div>
  </div>

  <!-- PRIVACY TAB -->
  <div class="tab-panel" id="tab-privacy">
    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-user-shield"></i> Profile Visibility</div>
      <div class="s-card-sub">Control who can see your company's information on AntCareers.</div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Show Company on Browse Page</div><div class="toggle-desc">Allow job seekers to discover your company when browsing.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Show Open Jobs Count Publicly</div><div class="toggle-desc">Display the number of active openings on your public profile.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <div class="toggle-label">Applicant Visibility</div>
          <div class="toggle-desc">Who can see applicant names in your job postings.</div>
        </div>
        <select class="privacy-select">
          <option selected>Company Admins &amp; Recruiters</option>
          <option>Company Admins only</option>
          <option>All team members</option>
        </select>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-database"></i> Data &amp; Analytics</div>
      <div class="s-card-sub">Manage how your data is used.</div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Allow Usage Analytics</div><div class="toggle-desc">Help us improve AntCareers by sharing anonymized usage data.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info"><div class="toggle-label">Personalized Recommendations</div><div class="toggle-desc">Show applicant suggestions based on your past hiring patterns.</div></div>
        <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
    </div>

    <div class="s-card">
      <div class="s-card-title"><i class="fas fa-download"></i> Your Data</div>
      <div class="s-card-sub">Download or review all data associated with your account.</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn-inline" onclick="showToast('Data export requested — email will be sent within 24 hours','fa-download')"><i class="fas fa-download"></i> Export All Data</button>
        <button class="btn-inline" onclick="showToast('Activity log downloaded','fa-file-alt')"><i class="fas fa-file-alt"></i> Download Activity Log</button>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;">
      <button class="btn-save" onclick="saveSettings('Privacy settings')"><i class="fas fa-save"></i> Save Privacy Settings</button>
    </div>
  </div>

  <!-- DANGER ZONE TAB -->
  <div class="tab-panel" id="tab-danger">
    <div class="danger-card">
      <div class="danger-title"><i class="fas fa-exclamation-triangle"></i> Danger Zone</div>
      <div class="danger-sub">These actions are irreversible. Please read carefully before proceeding.</div>

      <div class="danger-row">
        <div>
          <div class="danger-row-label">Suspend All Job Postings</div>
          <div class="danger-row-desc">Temporarily hide all active job postings from public view. You can reactivate them later.</div>
        </div>
        <button class="btn-danger" onclick="openConfirm('suspend')"><i class="fas fa-pause-circle"></i> Suspend All</button>
      </div>

      <div class="danger-row">
        <div>
          <div class="danger-row-label">Remove All Recruiters</div>
          <div class="danger-row-desc">Revoke access for all recruiter accounts. Company Admin access is unaffected.</div>
        </div>
        <button class="btn-danger" onclick="openConfirm('removeRecruiters')"><i class="fas fa-user-minus"></i> Remove All</button>
      </div>

      <div class="danger-row">
        <div>
          <div class="danger-row-label">Delete Company Account</div>
          <div class="danger-row-desc">Permanently delete <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?> from AntCareers. All jobs, applicants, and data will be erased.</div>
        </div>
        <button class="btn-danger" onclick="openConfirm('deleteCompany')"><i class="fas fa-building"></i> Delete Company</button>
      </div>

      <div class="danger-row">
        <div>
          <div class="danger-row-label">Delete My Account</div>
          <div class="danger-row-desc">Permanently delete your personal admin account. This cannot be undone.</div>
        </div>
        <button class="btn-danger" onclick="openConfirm('deleteAccount')"><i class="fas fa-user-times"></i> Delete Account</button>
      </div>
    </div>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Settings — <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
  <div style="display:flex;gap:14px;">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<!-- Confirm Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <div class="modal-icon" id="confirmIcon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="modal-title" id="confirmTitle">Are you sure?</div>
    <div class="modal-body" id="confirmBody">This action cannot be undone.</div>
    <input class="modal-confirm-input" id="confirmInput" placeholder="" oninput="checkConfirmInput(this.value)">
    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeConfirm()">Cancel</button>
      <button class="btn-modal-danger" id="confirmBtn" disabled onclick="executeConfirm()">Confirm</button>
    </div>
  </div>
</div>

<script>
  let currentConfirmAction = null;
  const confirmConfigs = {
    suspend: {
      title: 'Suspend All Job Postings?',
      body: 'All active job posts will be hidden from job seekers immediately. Type <strong>SUSPEND</strong> to confirm.',
      placeholder: 'Type SUSPEND to confirm',
      keyword: 'SUSPEND',
      successMsg: 'All job postings suspended'
    },
    removeRecruiters: {
      title: 'Remove All Recruiters?',
      body: 'All recruiter accounts will lose access to your company. Type <strong>REMOVE</strong> to confirm.',
      placeholder: 'Type REMOVE to confirm',
      keyword: 'REMOVE',
      successMsg: 'All recruiters removed'
    },
    deleteCompany: {
      title: 'Delete <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?>?',
      body: 'This will permanently delete your company, all job postings, and applicant data. This is irreversible. Type <strong>DELETE COMPANY</strong> to confirm.',
      placeholder: 'Type DELETE COMPANY to confirm',
      keyword: 'DELETE COMPANY',
      successMsg: 'Company deletion requested'
    },
    deleteAccount: {
      title: 'Delete Your Account?',
      body: 'Your personal admin account will be permanently removed. Type <strong>DELETE</strong> to confirm.',
      placeholder: 'Type DELETE to confirm',
      keyword: 'DELETE',
      successMsg: 'Account deletion requested'
    }
  };

  function openConfirm(action) {
    currentConfirmAction = action;
    const cfg = confirmConfigs[action];
    document.getElementById('confirmTitle').textContent = cfg.title;
    document.getElementById('confirmBody').innerHTML = cfg.body;
    document.getElementById('confirmInput').placeholder = cfg.placeholder;
    document.getElementById('confirmInput').value = '';
    document.getElementById('confirmBtn').disabled = true;
    document.getElementById('confirmModal').classList.add('open');
    setTimeout(() => document.getElementById('confirmInput').focus(), 100);
  }
  function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    currentConfirmAction = null;
  }
  function checkConfirmInput(val) {
    const cfg = confirmConfigs[currentConfirmAction];
    document.getElementById('confirmBtn').disabled = val.trim().toUpperCase() !== cfg.keyword;
  }
  function executeConfirm() {
    const cfg = confirmConfigs[currentConfirmAction];
    closeConfirm();
    showToast(cfg.successMsg, 'fa-check-circle', true);
  }

  function switchTab(id, el) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-'+id).classList.add('active');
  }

  function saveSettings(section) {
    showToast(section + ' saved successfully!', 'fa-check-circle');
  }

  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'text';
    inp.type = show ? 'password' : 'text';
    btn.querySelector('i').className = show ? 'fas fa-eye' : 'fas fa-eye-slash';
  }

  function checkPwStrength(val) {
    let s = 0;
    if (val.length >= 8) s++;
    if (/[A-Z]/.test(val)) s++;
    if (/[0-9]/.test(val)) s++;
    if (/[^A-Za-z0-9]/.test(val)) s++;
    const cols = ['','#D13D2C','#D4943A','#D4943A','#4CAF70'];
    const labels = ['','Weak','Fair','Good','Strong'];
    for (let i = 1; i <= 4; i++) {
      const bar = document.getElementById('pb'+i);
      bar.style.background = i <= s ? cols[s] : 'var(--soil-line)';
      bar.style.transition = '0.3s';
    }
    document.getElementById('pwLabel').textContent = val.length ? labels[s] : '';
    document.getElementById('pwLabel').style.color = cols[s] || 'var(--text-muted)';
  }

  function changePassword() {
    const cur = document.getElementById('currentPw').value;
    const nw = document.getElementById('newPw').value;
    const cf = document.getElementById('confirmPw').value;
    if (!cur || !nw || !cf) { showToast('Please fill all password fields','fa-exclamation', true); return; }
    if (nw !== cf) { showToast('New passwords do not match','fa-exclamation', true); return; }
    if (nw.length < 8) { showToast('Password must be at least 8 characters','fa-exclamation', true); return; }
    showToast('Password updated successfully!','fa-lock');
    document.getElementById('currentPw').value = '';
    document.getElementById('newPw').value = '';
    document.getElementById('confirmPw').value = '';
    checkPwStrength('');
  }

  function selectAppearance(theme, el) {
    document.querySelectorAll('.appear-opt').forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    const t = theme === 'system'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : theme;
    setTheme(t);
    showToast('Theme set to ' + theme, 'fa-palette');
  }

  function renderSessions() {
    const sessions = [
      { device:'Chrome on Windows 11', location:'Quezon City, PH', time:'Active now', current:true, icon:'fa-desktop' },
      { device:'Safari on iPhone 15', location:'Makati, PH', time:'2 hours ago', current:false, icon:'fa-mobile-alt' },
      { device:'Firefox on macOS', location:'Quezon City, PH', time:'Yesterday', current:false, icon:'fa-laptop' },
    ];
    document.getElementById('sessionList').innerHTML = sessions.map(s => `
      <div class="toggle-row" style="border-bottom:1px solid var(--soil-line);padding:12px 0;">
        <div style="display:flex;align-items:center;gap:12px;flex:1;">
          <div style="width:36px;height:36px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;color:${s.current?'var(--red-bright)':'var(--text-muted)'};font-size:14px;flex-shrink:0;"><i class="fas ${s.icon}"></i></div>
          <div>
            <div style="font-size:13px;font-weight:600;color:#F5F0EE;">${s.device} ${s.current?'<span style="font-size:10px;background:rgba(76,175,112,0.15);color:var(--green);border:1px solid rgba(76,175,112,0.2);padding:2px 7px;border-radius:8px;margin-left:4px;">Current</span>':''}</div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${s.location} · ${s.time}</div>
          </div>
        </div>
        ${!s.current?`<button class="btn-inline" style="font-size:12px;" onclick="showToast('Session revoked','fa-sign-out-alt')">Revoke</button>`:''}
      </div>
    `).join('') + `<div style="padding-top:12px;"><button class="btn-danger" onclick="showToast('All other sessions signed out','fa-sign-out-alt')">Sign out all other devices</button></div>`;
  }

  function showToast(msg, icon, warn=false) {
    const t = document.createElement('div');
    t.className = 'toast' + (warn ? ' warn' : '');
    t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
  }

  function setTheme(t) {
    document.body.classList.toggle('light', t==='light'); document.body.classList.toggle('dark', t!=='light');
    localStorage.setItem('ac-theme', t);
    document.getElementById('themeToggle').querySelector('i').className = t==='light' ? 'fas fa-sun' : 'fas fa-moon';
    // sync appearance tab selection
    document.querySelectorAll('.appear-opt').forEach(o => o.classList.remove('selected'));
    const match = t === 'light' ? 'opt-light' : 'opt-dark';
    const el = document.getElementById(match);
    if (el) el.classList.add('selected');
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
    if (e.target === document.getElementById('confirmModal')) closeConfirm();
  });

  (function() {
    const p = new URLSearchParams(window.location.search).get('theme');
    const s = localStorage.getItem('ac-theme');
    const t = p || s || 'light';
    if (p) localStorage.setItem('ac-theme', p);
    setTheme(t);
  })();

  renderSessions();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>