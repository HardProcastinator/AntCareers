<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'profile';

// Load existing company profile from DB
$profileData = null;
try {
    $db = getDB();
    $pStmt = $db->prepare("SELECT * FROM company_profiles WHERE user_id = :uid LIMIT 1");
    $pStmt->execute([':uid' => (int)$_SESSION['user_id']]);
    $profileData = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('[AntCareers] Load company profile: ' . $e->getMessage());
}

// Helper to safely get profile field
function pf(?array $p, string $key, string $default = ''): string {
    return htmlspecialchars((string)($p[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Company Profile</title>
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
    .notif-btn-nav { position:relative; width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .badge { position:absolute; top:-4px; right:-4px; background:var(--red-vivid); color:#fff; font-size:9px; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.02em; box-shadow:0 2px 8px rgba(209,61,44,0.3); text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); transform:translateY(-1px); }
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

    /* Breadcrumb */
    .breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); margin-bottom:24px; }
    .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:0.15s; }
    .breadcrumb a:hover { color:var(--red-pale); }
    .breadcrumb i { font-size:9px; }

    /* Page header */
    .page-title { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .page-sub { font-size:13px; color:var(--text-muted); margin-bottom:32px; }

    /* Cover + logo hero */
    .cover-section { position:relative; border-radius:14px; overflow:hidden; margin-bottom:28px; background:var(--soil-card); border:1px solid var(--soil-line); }
    .cover-img { height:160px; background:linear-gradient(135deg, #3A0F0F 0%, #1C0808 40%, #2A0A1A 100%); position:relative; overflow:hidden; }
    .cover-img::after { content:''; position:absolute; inset:0; background:repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(209,61,44,0.04) 20px, rgba(209,61,44,0.04) 21px); }
    .cover-change-btn { position:absolute; bottom:12px; right:12px; background:rgba(0,0,0,0.6); border:1px solid rgba(255,255,255,0.15); color:#F5F0EE; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; font-family:var(--font-body); transition:0.2s; z-index:2; display:flex; align-items:center; gap:6px; }
    .cover-change-btn:hover { background:rgba(209,61,44,0.4); }
    .cover-bottom { display:flex; align-items:flex-end; justify-content:space-between; padding:0 24px 20px; gap:16px; flex-wrap:wrap; }
    .company-logo-wrap { margin-top:-40px; position:relative; }
    .company-logo { width:80px; height:80px; border-radius:14px; background:var(--soil-hover); border:3px solid var(--soil-dark); display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; color:var(--red-bright); font-family:var(--font-display); position:relative; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.4); }
    .logo-upload-btn { position:absolute; inset:0; background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center; font-size:20px; cursor:pointer; color:#fff; border-radius:11px; }
    .company-logo-wrap:hover .logo-upload-btn { display:flex; }
    .cover-company-name { font-size:20px; font-weight:700; color:#F5F0EE; font-family:var(--font-display); }
    .cover-company-sub { font-size:12px; color:var(--amber); font-weight:600; margin-top:2px; }
    .cover-actions { display:flex; gap:8px; align-items:center; padding-bottom:4px; }

    /* Form cards */
    .form-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:24px; margin-bottom:20px; }
    .fc-title { font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    .fc-title i { color:var(--red-bright); font-size:14px; }
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    .form-group { display:flex; flex-direction:column; gap:6px; }
    .form-group.full { grid-column:1/-1; }
    .form-label { font-size:12px; font-weight:600; color:var(--text-muted); letter-spacing:0.03em; text-transform:uppercase; }
    .form-input { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 14px; font-size:14px; color:#F5F0EE; font-family:var(--font-body); transition:0.2s; outline:none; }
    .form-input:focus { border-color:var(--red-mid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .form-input::placeholder { color:var(--text-muted); }
    textarea.form-input { resize:vertical; min-height:100px; line-height:1.6; }
    select.form-input { cursor:pointer; }
    .char-count { font-size:11px; color:var(--text-muted); text-align:right; margin-top:2px; }

    /* Social row */
    .social-row { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
    .social-item { display:flex; align-items:center; gap:10px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 12px; }
    .social-icon { width:30px; height:30px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
    .si-linkedin { background:rgba(10,102,194,0.15); color:#0A66C2; }
    .si-fb { background:rgba(24,119,242,0.15); color:#1877F2; }
    .si-twitter { background:rgba(29,161,242,0.15); color:#1DA1F2; }
    .si-web { background:rgba(209,61,44,0.15); color:var(--red-bright); }
    .si-ig { background:rgba(193,53,132,0.15); color:#C13584; }
    .si-yt { background:rgba(255,0,0,0.15); color:#FF0000; }
    .social-input { background:none; border:none; outline:none; font-size:13px; color:var(--text-mid); font-family:var(--font-body); width:100%; }
    .social-input::placeholder { color:var(--text-muted); }

    /* Perks */
    .perks-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .perk-chip { display:flex; align-items:center; gap:6px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:20px; padding:6px 12px; font-size:12px; color:var(--text-mid); font-weight:500; cursor:pointer; transition:0.2s; }
    .perk-chip.selected { background:rgba(209,61,44,0.12); border-color:var(--red-mid); color:var(--red-pale); }
    .perk-chip i { font-size:10px; }

    /* Save bar */
    .save-bar { display:flex; align-items:center; justify-content:space-between; padding:16px 0 0; border-top:1px solid var(--soil-line); margin-top:4px; flex-wrap:wrap; gap:12px; }
    .save-info { font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
    .save-info i { color:var(--green); }
    .btn-save { padding:10px 28px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; cursor:pointer; transition:0.2s; display:flex; align-items:center; gap:8px; }
    .btn-save:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.4); }
    .btn-cancel { padding:10px 20px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:14px; font-weight:600; cursor:pointer; transition:0.2s; }
    .btn-cancel:hover { border-color:var(--text-muted); color:#F5F0EE; }

    /* Toast */
    .toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%); background:var(--soil-card); border:1px solid var(--soil-line); color:#F5F0EE; padding:11px 22px; border-radius:10px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:9px; z-index:9999; animation:toastIn 0.25s ease; box-shadow:0 8px 32px rgba(0,0,0,0.5); }
    .toast i { color:var(--green); }
    @keyframes toastIn { from { opacity:0; transform:translate(-50%,12px); } to { opacity:1; transform:translate(-50%,0); } }

    /* Footer */
    .footer { position:relative; z-index:2; border-top:1px solid var(--soil-line); padding:20px 24px; display:flex; align-items:center; justify-content:space-between; font-size:12px; color:var(--text-muted); flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; font-size:15px; color:var(--red-bright); }

    /* Light mode */
    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
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
    body.light .form-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fc-title { color:#1A0A09; }
    body.light .form-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .social-item { background:#F5EEEC; border-color:#E0CECA; }
    body.light .social-input { color:#4A2828; }
    body.light .perk-chip { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; cursor:pointer; }
    body.light .perk-chip.selected { background:rgba(209,61,44,0.15); border-color:var(--red-mid); color:var(--red-vivid); }
    body.light .company-logo { background:#F5EEEC; border-color:#E0CECA; }
    body.light .cover-section { background:#FFFFFF; border-color:#E0CECA; }
    body.light .cover-company-name { color:#1A0A09; }
    body.light .save-info { color:#7A5555; }
    body.light .toast { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }
    body.light .footer { border-color:#E0CECA; }

    @media(max-width:760px) {
      .nav-links{display:none} .hamburger{display:flex}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .form-grid { grid-template-columns:1fr; }
      .form-group.full { grid-column:1; }
      .social-row { grid-template-columns:1fr; }
      .cover-bottom { flex-direction:column; align-items:flex-start; }
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

<!-- PAGE CONTENT -->
<div class="page-shell">
  <div class="breadcrumb">
    <a href="employer_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
    <i class="fas fa-chevron-right"></i>
    <span>Company Profile</span>
  </div>

  <div class="page-title">Company Profile</div>
  <div class="page-sub">Manage your company's public-facing information seen by job seekers.</div>

  <!-- Cover + Logo -->
  <div class="cover-section">
    <div class="cover-img" id="coverImg"<?php if ($profileData && !empty($profileData['cover_path'])): ?> style="background-image:url('<?php echo pf($profileData,'cover_path'); ?>');background-size:cover;background-position:center;background-repeat:no-repeat;"<?php endif; ?>>
      <button class="cover-change-btn" onclick="document.getElementById('coverFileInput').click()"><i class="fas fa-camera"></i> Change Cover</button>
      <input type="file" id="coverFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadCover(this)">
    </div>
    <div class="cover-bottom">
      <div style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <div class="company-logo-wrap">
          <div class="company-logo" id="companyLogoEl"><?php
            if ($profileData && !empty($profileData['logo_path'])) {
                echo '<img src="' . pf($profileData,'logo_path') . '" style="width:100%;height:100%;object-fit:cover;border-radius:11px;" alt="Logo">';
            } else {
                echo htmlspecialchars(strtoupper(substr($companyName,0,1)), ENT_QUOTES, 'UTF-8');
            }
          ?></div>
          <div class="logo-upload-btn" onclick="document.getElementById('logoFileInput').click()"><i class="fas fa-camera"></i></div>
          <input type="file" id="logoFileInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="uploadLogo(this)">
        </div>
        <div>
          <div class="cover-company-name" id="coverCompanyName"><?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="cover-company-sub" id="coverCompanySub"><i class="fas fa-map-marker-alt"></i> <?php echo pf($profileData,'city','Quezon City'); ?>, <?php echo pf($profileData,'province','Metro Manila'); ?> &nbsp;·&nbsp; <i class="fas fa-briefcase"></i> <?php echo pf($profileData,'industry','Information Technology'); ?></div>
        </div>
      </div>
      <div class="cover-actions">
        <button class="btn-cancel" onclick="openPreview()"><i class="fas fa-eye"></i> Preview</button>
      </div>
    </div>
  </div>

  <!-- Basic Info -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-info-circle"></i> Basic Information</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Company Name</label>
        <input class="form-input" type="text" value="<?php echo pf($profileData,'company_name',$companyName); ?>" id="companyName">
      </div>
      <div class="form-group">
        <label class="form-label">Industry</label>
        <select class="form-input" id="industry">
          <?php
          $industries = ['Information Technology','Finance & Banking','Healthcare','Education','E-Commerce / Retail','BPO / Outsourcing','Engineering','Manufacturing','Media & Creative','Government','Other'];
          $savedIndustry = $profileData['industry'] ?? 'Information Technology';
          foreach ($industries as $ind) {
              $sel = ($ind === $savedIndustry) ? ' selected' : '';
              echo "<option{$sel}>" . htmlspecialchars($ind, ENT_QUOTES, 'UTF-8') . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Company Size</label>
        <select class="form-input" id="companySize">
          <?php
          $sizes = ['1–10 employees','11–50 employees','51–200 employees','201–500 employees','501–1,000 employees','1,001–5,000 employees','5,000+ employees'];
          $savedSize = $profileData['company_size'] ?? '11–50 employees';
          foreach ($sizes as $sz) {
              $sel = ($sz === $savedSize) ? ' selected' : '';
              echo "<option{$sel}>" . htmlspecialchars($sz, ENT_QUOTES, 'UTF-8') . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Founded Year</label>
        <input class="form-input" type="number" value="<?php echo pf($profileData,'founded_year','2018'); ?>" min="1900" max="2026" id="foundedYear">
      </div>
      <div class="form-group">
        <label class="form-label">Company Type</label>
        <select class="form-input" id="companyType">
          <?php
          $types = ['Private','Public','Government / NGO','Startup','Multinational'];
          $savedType = $profileData['company_type'] ?? 'Private';
          foreach ($types as $tp) {
              $sel = ($tp === $savedType) ? ' selected' : '';
              echo "<option{$sel}>" . htmlspecialchars($tp, ENT_QUOTES, 'UTF-8') . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Website</label>
        <input class="form-input" type="url" placeholder="https://yourcompany.com" value="<?php echo pf($profileData,'website'); ?>" id="websiteInput">
      </div>
      <div class="form-group full">
        <label class="form-label">Company Tagline</label>
        <input class="form-input" type="text" placeholder="A short tagline visible on your profile" maxlength="120" id="taglineInput" value="<?php echo pf($profileData,'tagline'); ?>" oninput="updateCount('taglineInput','taglineCount',120)">
        <div class="char-count"><span id="taglineCount"><?php echo mb_strlen((string)($profileData['tagline'] ?? '')); ?></span>/120</div>
      </div>
      <div class="form-group full">
        <label class="form-label">Company Description</label>
        <textarea class="form-input" placeholder="Describe your company, culture, mission, and what makes you a great place to work..." maxlength="1000" id="descInput" oninput="updateCount('descInput','descCount',1000)"><?php echo pf($profileData,'about'); ?></textarea>
        <div class="char-count"><span id="descCount"><?php echo mb_strlen((string)($profileData['about'] ?? '')); ?></span>/1000</div>
      </div>
    </div>
  </div>

  <!-- Location -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-map-marker-alt"></i> Location</div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Country</label>
        <select class="form-input" id="countrySelect">
          <?php
          $countries = ['Philippines','Singapore','United States','Other'];
          $savedCountry = $profileData['country'] ?? 'Philippines';
          foreach ($countries as $c) {
              $sel = ($c === $savedCountry) ? ' selected' : '';
              echo "<option{$sel}>" . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . "</option>";
          }
          ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">City / Municipality</label>
        <input class="form-input" type="text" value="<?php echo pf($profileData,'city'); ?>" id="cityInput">
      </div>
      <div class="form-group">
        <label class="form-label">Province / State</label>
        <input class="form-input" type="text" value="<?php echo pf($profileData,'province'); ?>" id="provinceInput">
      </div>
      <div class="form-group">
        <label class="form-label">ZIP Code</label>
        <input class="form-input" type="text" value="<?php echo pf($profileData,'zip_code'); ?>" id="zipCodeInput">
      </div>
      <div class="form-group full">
        <label class="form-label">Full Address</label>
        <input class="form-input" type="text" placeholder="Building, Street, Barangay..." value="<?php echo pf($profileData,'address_line'); ?>" id="addressInput">
      </div>
    </div>
  </div>

  <!-- Social Links -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-share-alt"></i> Social &amp; Online Presence</div>
    <div class="social-row">
      <div class="social-item"><div class="social-icon si-web"><i class="fas fa-globe"></i></div><input class="social-input" placeholder="Website URL" type="url" id="socialWebsite" value="<?php echo pf($profileData,'social_website'); ?>"></div>
      <div class="social-item"><div class="social-icon si-linkedin"><i class="fab fa-linkedin-in"></i></div><input class="social-input" placeholder="LinkedIn company page" id="socialLinkedin" value="<?php echo pf($profileData,'social_linkedin'); ?>"></div>
      <div class="social-item"><div class="social-icon si-fb"><i class="fab fa-facebook-f"></i></div><input class="social-input" placeholder="Facebook page URL" id="socialFacebook" value="<?php echo pf($profileData,'social_facebook'); ?>"></div>
      <div class="social-item"><div class="social-icon si-twitter"><i class="fab fa-twitter"></i></div><input class="social-input" placeholder="Twitter / X handle" id="socialTwitter" value="<?php echo pf($profileData,'social_twitter'); ?>"></div>
      <div class="social-item"><div class="social-icon si-ig"><i class="fab fa-instagram"></i></div><input class="social-input" placeholder="Instagram handle" id="socialInstagram" value="<?php echo pf($profileData,'social_instagram'); ?>"></div>
      <div class="social-item"><div class="social-icon si-yt"><i class="fab fa-youtube"></i></div><input class="social-input" placeholder="YouTube channel URL" id="socialYoutube" value="<?php echo pf($profileData,'social_youtube'); ?>"></div>
    </div>
  </div>

  <!-- Perks & Culture -->
  <div class="form-card">
    <div class="fc-title"><i class="fas fa-heart"></i> Perks &amp; Benefits</div>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">Select what your company offers. These appear as badges on your public profile.</div>
    <?php
    $savedPerks = [];
    if ($profileData && !empty($profileData['perks'])) {
        $decoded = json_decode($profileData['perks'], true);
        if (is_array($decoded)) $savedPerks = $decoded;
    }
    $allPerks = [
        ['icon'=>'fa-laptop-house','label'=>'Remote Work'],
        ['icon'=>'fa-heartbeat','label'=>'HMO / Health Insurance'],
        ['icon'=>'fa-graduation-cap','label'=>'Learning & Development'],
        ['icon'=>'fa-umbrella-beach','label'=>'Paid Time Off'],
        ['icon'=>'fa-dumbbell','label'=>'Gym / Wellness'],
        ['icon'=>'fa-baby','label'=>'Parental Leave'],
        ['icon'=>'fa-coffee','label'=>'Free Snacks / Meals'],
        ['icon'=>'fa-car','label'=>'Transportation Allowance'],
        ['icon'=>'fa-chart-line','label'=>'Stock Options / Equity'],
        ['icon'=>'fa-handshake','label'=>'Competitive Salary'],
        ['icon'=>'fa-gamepad','label'=>'Game Room / Recreation'],
        ['icon'=>'fa-globe-asia','label'=>'International Exposure'],
    ];
    ?>
    <div class="perks-grid" id="perksGrid">
      <?php foreach ($allPerks as $perk): ?>
        <div class="perk-chip<?php echo in_array($perk['label'], $savedPerks) ? ' selected' : ''; ?>" onclick="togglePerk(this)" data-perk="<?php echo htmlspecialchars($perk['label'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas <?php echo $perk['icon']; ?>"></i> <?php echo htmlspecialchars($perk['label'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Save bar -->
  <div class="save-bar">
    <div class="save-info"><i class="fas fa-check-circle"></i> <?php
      if ($profileData && !empty($profileData['updated_at'])) {
          echo 'Last saved: ' . date('M j, Y \a\t g:i A', strtotime($profileData['updated_at']));
      } else {
          echo 'Not saved yet';
      }
    ?></div>
    <div style="display:flex;gap:10px;">
      <button class="btn-cancel" onclick="window.location.href='employer_dashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">Cancel</button>
      <button class="btn-save" onclick="saveProfile()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Company Profile — <?php echo htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'); ?></div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<script>
  function getTP() { return '?theme=' + (document.body.classList.contains('light')?'light':'dark'); }

  function updateCount(inputId, countId, max) {
    document.getElementById(countId).textContent = document.getElementById(inputId).value.length;
  }

  function togglePerk(el) { el.classList.toggle('selected'); }

  /* ---- Collect selected perks as JSON array ---- */
  function getSelectedPerks() {
    const chips = document.querySelectorAll('#perksGrid .perk-chip.selected');
    return JSON.stringify(Array.from(chips).map(c => c.dataset.perk || c.textContent.trim()));
  }

  /* ---- SAVE PROFILE (AJAX) ---- */
  function saveProfile() {
    const btn = document.querySelector('.btn-save');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const fd = new FormData();
    fd.append('action', 'save_profile');
    fd.append('company_name', document.getElementById('companyName').value.trim());
    fd.append('industry', document.getElementById('industry').value);
    fd.append('company_size', document.getElementById('companySize').value);
    fd.append('founded_year', document.getElementById('foundedYear').value);
    fd.append('company_type', document.getElementById('companyType').value);
    fd.append('website', document.getElementById('websiteInput').value.trim());
    fd.append('tagline', document.getElementById('taglineInput').value.trim());
    fd.append('about', document.getElementById('descInput').value.trim());
    fd.append('country', document.getElementById('countrySelect').value);
    fd.append('city', document.getElementById('cityInput').value.trim());
    fd.append('province', document.getElementById('provinceInput').value.trim());
    fd.append('zip_code', document.getElementById('zipCodeInput').value.trim());
    fd.append('address_line', document.getElementById('addressInput').value.trim());
    fd.append('social_website', document.getElementById('socialWebsite').value.trim());
    fd.append('social_linkedin', document.getElementById('socialLinkedin').value.trim());
    fd.append('social_facebook', document.getElementById('socialFacebook').value.trim());
    fd.append('social_twitter', document.getElementById('socialTwitter').value.trim());
    fd.append('social_instagram', document.getElementById('socialInstagram').value.trim());
    fd.append('social_youtube', document.getElementById('socialYoutube').value.trim());
    fd.append('perks', getSelectedPerks());

    fetch('update_company_profile.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        if (data.ok) {
          showToast('Company profile saved successfully!', 'fa-check-circle');
          document.querySelector('.save-info').innerHTML = '<i class="fas fa-check-circle" style="color:var(--green)"></i> Last saved: Just now';
          // Update header displays
          const name = document.getElementById('companyName').value.trim();
          if (name) {
            document.getElementById('coverCompanyName').textContent = name;
          }
          const city = document.getElementById('cityInput').value.trim();
          const prov = document.getElementById('provinceInput').value.trim();
          const ind  = document.getElementById('industry').value;
          document.getElementById('coverCompanySub').innerHTML =
            '<i class="fas fa-map-marker-alt"></i> ' + (city||'—') + ', ' + (prov||'—') +
            ' &nbsp;·&nbsp; <i class="fas fa-briefcase"></i> ' + ind;
        } else {
          showToast(data.error || 'Save failed', 'fa-exclamation-circle');
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        showToast('Network error, please try again', 'fa-exclamation-circle');
      });
  }

  /* ---- UPLOAD LOGO ---- */
  function uploadLogo(input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload_logo');
    fd.append('logo', input.files[0]);

    showToast('Uploading logo...', 'fa-spinner fa-spin');

    fetch('update_company_profile.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          document.getElementById('companyLogoEl').innerHTML =
            '<img src="' + data.path + '?' + Date.now() + '" style="width:100%;height:100%;object-fit:cover;border-radius:11px;" alt="Logo">';
          showToast('Logo updated!', 'fa-check-circle');
        } else {
          showToast(data.error || 'Upload failed', 'fa-exclamation-circle');
        }
      })
      .catch(() => showToast('Network error', 'fa-exclamation-circle'));
    input.value = '';
  }

  /* ---- UPLOAD COVER ---- */
  function uploadCover(input) {
    if (!input.files || !input.files[0]) return;
    const fd = new FormData();
    fd.append('action', 'upload_cover');
    fd.append('cover', input.files[0]);

    showToast('Uploading cover photo...', 'fa-spinner fa-spin');

    fetch('update_company_profile.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          const el = document.getElementById('coverImg');
          el.style.backgroundImage = "url('" + data.path + '?' + Date.now() + "')";
          el.style.backgroundSize = 'cover';
          el.style.backgroundPosition = 'center';
          el.style.backgroundRepeat = 'no-repeat';
          showToast('Cover photo updated!', 'fa-check-circle');
        } else {
          showToast(data.error || 'Upload failed', 'fa-exclamation-circle');
        }
      })
      .catch(() => showToast('Network error', 'fa-exclamation-circle'));
    input.value = '';
  }

  /* ---- PREVIEW MODAL ---- */
  function openPreview() {
    const name = document.getElementById('companyName').value.trim() || 'Your Company';
    const ind  = document.getElementById('industry').value;
    const size = document.getElementById('companySize').value;
    const type = document.getElementById('companyType').value;
    const year = document.getElementById('foundedYear').value;
    const web  = document.getElementById('websiteInput').value.trim();
    const tag  = document.getElementById('taglineInput').value.trim();
    const desc = document.getElementById('descInput').value.trim();
    const city = document.getElementById('cityInput').value.trim();
    const prov = document.getElementById('provinceInput').value.trim();
    const country = document.getElementById('countrySelect').value;
    const perks = Array.from(document.querySelectorAll('#perksGrid .perk-chip.selected'))
      .map(c => c.innerHTML).join('');

    const logoEl = document.getElementById('companyLogoEl');
    const logoHTML = logoEl.querySelector('img')
      ? '<img src="' + logoEl.querySelector('img').src + '" style="width:80px;height:80px;border-radius:14px;object-fit:cover;">'
      : '<div style="width:80px;height:80px;border-radius:14px;background:var(--soil-hover);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:var(--red-bright);font-family:var(--font-display);">' + name[0].toUpperCase() + '</div>';

    const coverEl = document.getElementById('coverImg');
    // Check inline style first (set by upload), then computed style
    let coverBgImg = coverEl.style.backgroundImage;
    if (!coverBgImg || coverBgImg === 'none' || coverBgImg === '') {
      coverBgImg = getComputedStyle(coverEl).backgroundImage;
    }
    // Also check if there's an inline background shorthand
    if ((!coverBgImg || coverBgImg === 'none') && coverEl.style.background) {
      const m = coverEl.style.background.match(/url\([^)]+\)/);
      if (m) coverBgImg = m[0];
    }
    const hasCoverImg = coverBgImg && coverBgImg !== 'none' && coverBgImg.includes('url(');
    // Replace double quotes with single quotes so it doesn't break the style="" attribute
    const safeCoverBg = coverBgImg ? coverBgImg.replace(/"/g, "'") : '';
    const coverCss = hasCoverImg
      ? 'background-image:' + safeCoverBg + ';background-size:cover;background-position:center;background-repeat:no-repeat;'
      : 'background:linear-gradient(135deg, #3A0F0F 0%, #1C0808 40%, #2A0A1A 100%);';

    // Remove any existing preview
    const existing = document.getElementById('previewModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'previewModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
      <div style="background:var(--soil-card);border:1px solid var(--soil-line);border-radius:16px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto;position:relative;">
        <button onclick="document.getElementById('previewModal').remove()" style="position:absolute;top:12px;right:12px;z-index:10;width:32px;height:32px;border-radius:8px;background:rgba(0,0,0,0.5);border:none;color:#fff;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
        <div style="height:140px;border-radius:16px 16px 0 0;${coverCss}"></div>
        <div style="padding:0 24px 24px;">
          <div style="margin-top:-36px;display:flex;align-items:flex-end;gap:16px;margin-bottom:16px;">
            ${logoHTML}
            <div>
              <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--text-light);">${escHTML(name)}</div>
              ${tag ? '<div style="font-size:13px;color:var(--text-muted);margin-top:2px;">' + escHTML(tag) + '</div>' : ''}
            </div>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:12px;color:var(--text-muted);margin-bottom:16px;">
            ${ind ? '<span><i class="fas fa-briefcase" style="color:var(--red-bright);margin-right:4px;"></i>' + escHTML(ind) + '</span>' : ''}
            ${city || prov ? '<span><i class="fas fa-map-marker-alt" style="color:var(--red-bright);margin-right:4px;"></i>' + escHTML(city + (city && prov ? ', ' : '') + prov) + (country && country !== 'Philippines' ? ', ' + escHTML(country) : '') + '</span>' : ''}
            ${size ? '<span><i class="fas fa-users" style="color:var(--red-bright);margin-right:4px;"></i>' + escHTML(size) + '</span>' : ''}
            ${type ? '<span><i class="fas fa-building" style="color:var(--red-bright);margin-right:4px;"></i>' + escHTML(type) + '</span>' : ''}
            ${year ? '<span><i class="fas fa-calendar" style="color:var(--red-bright);margin-right:4px;"></i>Founded ' + escHTML(year) + '</span>' : ''}
            ${web ? '<span><i class="fas fa-globe" style="color:var(--red-bright);margin-right:4px;"></i><a href="' + escHTML(web) + '" target="_blank" style="color:var(--blue);text-decoration:none;">' + escHTML(web) + '</a></span>' : ''}
          </div>
          ${desc ? '<div style="font-size:14px;color:var(--text-mid);line-height:1.7;margin-bottom:16px;">' + escHTML(desc) + '</div>' : ''}
          ${perks ? '<div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">Perks & Benefits</div><div style="display:flex;flex-wrap:wrap;gap:6px;">' + Array.from(document.querySelectorAll('#perksGrid .perk-chip.selected')).map(c => '<span style="display:inline-flex;align-items:center;gap:4px;background:rgba(209,61,44,0.12);border:1px solid var(--red-mid);border-radius:20px;padding:4px 10px;font-size:11px;color:var(--red-pale);font-weight:500;">' + c.innerHTML + '</span>').join('') + '</div>' : ''}
        </div>
      </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
  }

  function escHTML(str) {
    const d = document.createElement('div'); d.textContent = str; return d.innerHTML;
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

  // Hamburger
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    const open = mobileMenu.classList.toggle('open');
    hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
  });

  // Profile dropdown
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
  });

  // Init theme
  (function() {
    const p = new URLSearchParams(window.location.search).get('theme');
    const s = localStorage.getItem('ac-theme');
    const t = p || s || 'light';
    if (p) localStorage.setItem('ac-theme', p);
    setTheme(t);
  })();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>