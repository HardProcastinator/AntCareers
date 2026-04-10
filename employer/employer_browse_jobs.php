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
$navActive   = 'browse';
$companyIndustry = trim((string)($_SESSION['company_industry'] ?? 'Company Account'));
$companyLocation = trim((string)($_SESSION['company_location'] ?? 'Philippines'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Browse Jobs — AntCareers Employer</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root{
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --font-display:'Playfair Display', Georgia, serif;
      --font-body:'Plus Jakarta Sans', system-ui, sans-serif;
    }

    html { overflow-x:hidden; }
    body {
      font-family:var(--font-body);
      background:var(--soil-dark);
      color:var(--text-light);
      overflow-x:hidden;
      min-height:100vh;
      -webkit-font-smoothing:antialiased;
    }

    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    .navbar {
      position:sticky; top:0; z-index:200;
      background:rgba(10,9,9,.97);
      backdrop-filter:blur(20px);
      border-bottom:1px solid rgba(209,61,44,.35);
      box-shadow:0 1px 0 rgba(209,61,44,.06), 0 4px 24px rgba(0,0,0,.5);
    }
    .nav-inner {
      max-width:1380px; margin:0 auto; padding:0 24px;
      display:flex; align-items:center; height:64px; min-width:0;
    }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon {
      width:34px; height:34px; background:var(--red-vivid); border-radius:9px;
      display:flex; align-items:center; justify-content:center; font-size:17px;
      box-shadow:0 0 18px rgba(209,61,44,.35);
    }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }

    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link {
      font-size:13px; font-weight:600; color:#A09090;
      text-decoration:none; padding:7px 11px; border-radius:6px;
      transition:.2s; cursor:pointer; background:none; border:none;
      font-family:var(--font-body); display:flex; align-items:center; gap:5px;
      white-space:nowrap;
    }
    .nav-link:hover, .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }

    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn, .icon-btn {
      width:36px; height:36px; border-radius:7px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      color:var(--text-muted); display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:.2s; font-size:14px; flex-shrink:0;
    }
    .theme-btn:hover, .icon-btn:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .icon-btn { position:relative; }
    .icon-btn .badge {
      position:absolute; top:-5px; right:-5px;
      width:17px; height:17px; border-radius:50%;
      background:var(--red-vivid); color:#fff;
      font-size:10px; font-weight:700;
      display:flex; align-items:center; justify-content:center;
      border:2px solid var(--soil-dark);
    }
    .notif-btn-nav{position:relative;width:36px;height:36px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;font-size:14px;flex-shrink:0;}
    .notif-btn-nav:hover{color:var(--red-pale);border-color:var(--red-vivid);}
    .notif-btn-nav .badge{position:absolute;top:-5px;right:-5px;width:17px;height:17px;border-radius:50%;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--soil-dark);}
    .btn-nav-red{padding:7px 16px;border-radius:7px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap;text-decoration:none;display:flex;align-items:center;gap:7px;}
    .btn-nav-red:hover{background:var(--red-bright);}

    .browse-cta {
      padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none;
      color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700;
      cursor:pointer; transition:.2s; white-space:nowrap; letter-spacing:.02em;
      box-shadow:0 2px 8px rgba(209,61,44,.3);
      text-decoration:none; display:flex; align-items:center; gap:7px;
    }
    .browse-cta:hover { background:var(--red-bright); transform:translateY(-1px); }

    .profile-wrap { position:relative; }
    .profile-btn {
      display:flex; align-items:center; gap:9px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      border-radius:8px; padding:6px 12px 6px 8px;
      cursor:pointer; transition:.2s;
    }
    .profile-btn:hover { background:var(--soil-card); }
    .profile-avatar {
      width:28px; height:28px; border-radius:50%;
      background:linear-gradient(135deg,var(--amber),#8a5010);
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:700; color:#fff;
      flex-shrink:0;
    }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); }

    .profile-dropdown {
      position:absolute; top:calc(100% + 8px); right:0;
      background:var(--soil-card); border:1px solid var(--soil-line);
      border-radius:10px; padding:6px; min-width:220px;
      opacity:0; visibility:hidden; transform:translateY(-6px);
      transition:all .18s ease; z-index:300;
      box-shadow:0 20px 40px rgba(0,0,0,.5);
    }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:2px; font-weight:600; }
    .pd-item {
      display:flex; align-items:center; gap:10px;
      padding:9px 12px; border-radius:6px;
      font-size:13px; font-weight:500; color:var(--text-mid);
      cursor:pointer; transition:.15s; font-family:var(--font-body);
      text-decoration:none;
    }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,.1); color:#FF7070; }

    .hamburger {
      display:none; width:36px; height:36px; border-radius:8px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      color:var(--text-mid); align-items:center; justify-content:center;
      cursor:pointer; font-size:14px; margin-left:8px;
    }
    .mobile-menu {
      display:none; position:fixed; top:64px; left:0; right:0;
      background:rgba(10,9,9,.97); backdrop-filter:blur(20px);
      border-bottom:1px solid var(--soil-line);
      padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px;
    }
    .mobile-menu.open { display:flex; }
    .mobile-link {
      display:flex; align-items:center; gap:10px; padding:10px 14px;
      border-radius:7px; font-size:14px; font-weight:500;
      color:var(--text-mid); cursor:pointer; transition:.15s;
      font-family:var(--font-body); text-decoration:none;
      background:none; border:none; width:100%; text-align:left;
    }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 80px; position:relative; z-index:2; }

    .search-header { padding:32px 0 24px; }
    .search-greeting { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .search-greeting span { color:var(--red-bright); font-style:italic; }
    .search-sub { font-size:14px; color:var(--text-muted); margin-bottom:20px; }

 .search-bar {
  display:flex;
  align-items:center;
  background:var(--soil-card);
  border:1px solid var(--soil-line);
  border-radius:10px;
  overflow:hidden;
  transition:0.25s;
}

.search-bar:focus-within {
  border-color:var(--red-vivid);
  box-shadow:0 0 0 3px rgba(209,61,44,0.12), 0 4px 20px rgba(0,0,0,0.3);
}

.search-bar .si {
  padding:0 16px;
  color:var(--text-muted);
  font-size:16px;
  flex-shrink:0;
}

.search-bar input {
  flex:1;
  padding:16px 0;
  min-width:0;
  background:none;
  border:none;
  outline:none;
  font-family:var(--font-body);
  font-size:15px;
  color:#F5F0EE;
}

.search-bar input::placeholder {
  color:var(--text-muted);
}

.search-divider {
  width:1px;
  height:28px;
  background:var(--soil-line);
  flex-shrink:0;
}

.search-btn {
  margin:6px;
  padding:10px 22px;
  border-radius:7px;
  background:var(--red-vivid);
  border:none;
  color:#fff;
  font-family:var(--font-body);
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  transition:0.2s;
  white-space:nowrap;
  flex-shrink:0;
  letter-spacing:0.02em;
  display:flex;
  align-items:center;
  gap:7px;
}

.search-btn:hover {
  background:var(--red-bright);
}

    .quick-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .qf-pill {
      display:flex; align-items:center; gap:5px; padding:6px 13px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      border-radius:100px; font-size:12px; font-weight:600; color:var(--text-muted);
      cursor:pointer; transition:all .18s;
    }
    .qf-pill:hover, .qf-pill.active {
      background:rgba(209,61,44,.12); border-color:rgba(209,61,44,.35); color:var(--red-pale);
    }

    .content-layout { display:grid; grid-template-columns: 1fr; gap:28px; }

    .sidebar { position:sticky; top:72px; align-self:start; }
    .sidebar-card {
      background:var(--soil-card); border:1px solid var(--soil-line);
      border-radius:10px; overflow:hidden;
    }
    .sidebar-head {
      padding:16px 18px 12px; display:flex; align-items:center; justify-content:space-between;
      border-bottom:1px solid var(--soil-line);
    }
    .sidebar-title {
      font-size:12px; font-weight:700; color:#F5F0EE;
      display:flex; align-items:center; gap:7px; letter-spacing:.07em; text-transform:uppercase;
    }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }
    .reset-link {
      font-size:12px; font-weight:600; color:#927C7A; cursor:pointer; background:none; border:none;
      font-family:var(--font-body);
    }
    .reset-link:hover { color:var(--red-pale); }

    .filter-section { padding:13px 16px; border-bottom:1px solid var(--soil-line); }
    .filter-section:last-child { border-bottom:none; }
    .filter-section-label {
      font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
      color:#927C7A; margin-bottom:7px;
    }
    .filter-select, .filter-input {
      width:100%; background:#0A0909; border:1px solid var(--soil-line); border-radius:6px;
      padding:11px 12px; color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none;
    }
    .filter-select:focus, .filter-input:focus { border-color:var(--red-vivid); }
    .salary-range { display:flex; gap:8px; }
    .salary-range input { width:100%; }

    .sidebar-company {
      padding:16px; border-bottom:1px solid var(--soil-line);
      background:linear-gradient(180deg, rgba(209,61,44,.08), transparent);
    }
    .sc-inner { display:flex; align-items:center; gap:10px; }
    .sc-logo {
      width:42px; height:42px; border-radius:10px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      display:flex; align-items:center; justify-content:center; font-size:18px;
    }
    .sc-name { font-size:13px; font-weight:700; color:#F5F0EE; }
    .sc-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .sc-pill {
      display:inline-flex; align-items:center; gap:4px; margin-top:7px;
      font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px;
      background:rgba(76,175,112,.1); color:#6ccf8a; border:1px solid rgba(76,175,112,.2);
    }

    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title {
      font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE;
      display:flex; align-items:center; gap:10px;
    }
    .sec-title i { color:var(--red-bright); font-size:16px; }
    .sec-count {
      font-size:11px; font-weight:600; color:var(--text-muted);
      background:var(--soil-hover); padding:2px 9px; border-radius:4px;
    }
    .see-more {
      font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer;
      background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:4px;
    }
    .see-more:hover { color:var(--red-bright); }

    .featured-scroll {
      display:flex; gap:14px; overflow-x:auto;
      padding:8px 6px 24px; margin:-8px -6px 24px; scrollbar-width:none;
    }
    .featured-scroll::-webkit-scrollbar { display:none; }
    .featured-card {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px;
      padding:22px; min-width:248px; max-width:248px; cursor:pointer; transition:.25s;
      position:relative; overflow:hidden; flex-shrink:0;
    }
    .featured-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));
    }
    .featured-card:hover { border-color:rgba(209,61,44,.55); transform:translateY(-4px); box-shadow:0 20px 48px rgba(0,0,0,.45); }
    .fc-badge {
      display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700;
      letter-spacing:.08em; text-transform:uppercase; color:var(--amber);
      background:var(--amber-dim); border:1px solid rgba(212,148,58,.22);
      padding:2px 7px; border-radius:3px; margin-bottom:14px;
    }
    .fc-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .fc-company { font-size:12px; color:var(--red-pale); font-weight:600; margin-bottom:14px; }
    .fc-footer { display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid var(--soil-line); margin-top:14px; }
    .fc-salary { font-size:14px; font-weight:700; color:#F5F0EE; }
    .fc-action {
      padding:5px 12px; border-radius:6px; background:var(--red-vivid); border:none;
      color:#fff; font-size:11px; font-weight:700; cursor:pointer;
    }
    .fc-action:hover { background:var(--red-bright); }

    .job-list { display:flex; flex-direction:column; gap:8px; }
    .job-row {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px;
      padding:18px 20px; transition:.18s; display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center;
    }
    .job-row:hover { border-color:rgba(209,61,44,.5); background:var(--soil-hover); transform:translateX(2px); box-shadow:0 4px 20px rgba(0,0,0,.3); }
    .jr-top { display:flex; align-items:center; gap:8px; margin-bottom:5px; flex-wrap:wrap; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new {
      font-size:10px; font-weight:700; letter-spacing:.07em; text-transform:uppercase;
      padding:2px 7px; border-radius:3px;
    }
    .jr-new.green { color:#6ccf8a; background:rgba(76,175,112,.1); border:1px solid rgba(76,175,112,.2); }
    .jr-new.amber { color:var(--amber); background:rgba(212,148,58,.1); border:1px solid rgba(212,148,58,.2); }
    .jr-new.blue { color:#7ab8f0; background:rgba(74,144,217,.1); border:1px solid rgba(74,144,217,.2); }
    .jr-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:#927C7A; margin-bottom:8px; }
    .jr-meta span { display:flex; align-items:center; gap:4px; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-company { color:var(--red-pale); font-weight:600; }
    .jr-chips { display:flex; gap:5px; flex-wrap:wrap; }
    .chip {
      font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px;
      background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line);
    }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.blue { background:rgba(74,144,217,.08); color:#7ab8f0; border-color:rgba(74,144,217,.2); }
    .jr-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .jr-salary { font-size:14px; font-weight:700; color:#F5F0EE; white-space:nowrap; }
    .jr-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
    .jr-btn {
      padding:6px 13px; border-radius:6px; background:transparent; border:1px solid var(--soil-line);
      color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer;
      font-family:var(--font-body); transition:.18s; white-space:nowrap;
    }
    .jr-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .jr-btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .jr-btn.primary:hover { background:var(--red-bright); border-color:var(--red-bright); }

    .empty-state {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px;
      padding:36px 24px; text-align:center; color:var(--text-muted);
    }

    .footer {
      border-top:1px solid var(--soil-line); padding:28px 24px; max-width:1380px; margin:0 auto;
      display:flex; align-items:center; justify-content:space-between; color:var(--text-muted);
      font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:12px;
    }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:16px; }

    .toast {
      position:fixed; bottom:24px; right:24px; z-index:999;
      background:var(--soil-card); border:1px solid var(--soil-line); border-left:2px solid var(--red-vivid);
      border-radius:8px; padding:11px 18px; font-size:13px; font-weight:500; color:#F5F0EE;
      box-shadow:0 10px 30px rgba(0,0,0,.4); display:flex; align-items:center; gap:9px;
      animation:toastIn .25s ease;
    }
    @keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
    .toast i { color:var(--red-pale); }

    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .navbar { background:rgba(255,253,252,.98); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.08); }
    body.light .logo-text, body.light .search-greeting, body.light .sec-title, body.light .jr-title, body.light .fc-title,
    body.light .profile-name, body.light .pdh-name, body.light .sc-name, body.light .jr-salary, body.light .fc-salary { color:#1A0A09; }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn, body.light .icon-btn, body.light .notif-btn-nav, body.light .profile-btn, body.light .sidebar-card,
    body.light .featured-card, body.light .job-row, body.light .search-bar, body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .mobile-menu { background:rgba(255,253,252,.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .search-bar input, body.light .filter-select, body.light .filter-input { color:#1A0A09; background:#FFFFFF; }
    body.light .qf-pill, body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .job-row:hover { background:#FEF0EE; }
    body.light .sidebar-title, body.light .sc-name { color:#1A0A09; }
    body.light .reset-link { color:#7A5555; }
    body.light .footer { color:#7A5555; }

    @media (max-width:1060px){
      .content-layout{grid-template-columns:1fr}
      
    }
    @media (max-width:760px){
      .nav-links{display:none}
      .hamburger{display:flex}
      .page-shell{padding:0 16px 60px}
      .nav-inner{padding:0 16px}
      .profile-name,.profile-role{display:none}
      .profile-btn{padding:6px 8px}
      .job-row{grid-template-columns:1fr}
      .jr-right{align-items:flex-start}
      .jr-actions{justify-content:flex-start}
      .search-bar{flex-wrap:wrap}
      .s-divider{display:none}
      .s-loc{width:100%; padding:0 16px 12px}
      .s-loc input{width:100%}
      .footer{flex-direction:column;text-align:center;padding:20px 16px}
    }
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
    <g fill="#E54C3A" opacity="0.4">
      <circle cx="350" cy="240" r="3.5"/><circle cx="600" cy="260" r="3"/>
      <circle cx="900" cy="280" r="3.5"/><circle cx="300" cy="490" r="3"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<div class="page-shell">
  <div class="search-header">
    <div class="search-greeting">Good morning, <span><?php echo htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'); ?>.</span></div>
    <div class="search-sub">Browse active listings with the employer navbar layout.</div>

   <div class="search-bar">
  <span class="si"><i class="fas fa-search"></i></span>
  <input type="text" id="keywordInput" placeholder="Job title, skill, or company...">
  <button class="search-btn" id="searchBtn" type="button">
    <i class="fas fa-arrow-right"></i> Search
  </button>
</div>

    <div class="quick-filters">
      <span class="qf-pill" onclick="quickFilter('Remote')"><i class="fas fa-globe"></i> Remote</span>
      <span class="qf-pill" onclick="quickFilter('Full-time')"><i class="fas fa-clock"></i> Full-time</span>
      <span class="qf-pill" onclick="quickFilter('Tech')"><i class="fas fa-laptop-code"></i> Tech</span>
      <span class="qf-pill" onclick="quickFilter('Design')"><i class="fas fa-palette"></i> Design</span>
      <span class="qf-pill" onclick="quickFilter('Finance')"><i class="fas fa-chart-line"></i> Finance</span>
      <span class="qf-pill" onclick="quickFilter('Senior')"><i class="fas fa-layer-group"></i> Senior</span>
    </div>
  </div>

  <div class="content-layout">
    <main>
      <div class="sec-header">
        <div class="sec-title"><i class="fas fa-building"></i> Featured Companies</div>
        <button class="see-more" type="button" onclick="showToast('Company pages coming soon','fa-building')">View all <i class="fas fa-arrow-right"></i></button>
      </div>

      <div class="featured-scroll" id="featuredCompanies"></div>

      <section id="jobs">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-briefcase"></i> Active Listings <span class="sec-count" id="jobCount">0 jobs</span></div>
          <button class="see-more" type="button" onclick="showToast('More browse options coming soon','fa-magnifying-glass')">More filters <i class="fas fa-arrow-right"></i></button>
        </div>

        <div class="job-list" id="jobsContainer"></div>
      </section>
    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Employer Browse Jobs — <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php'">← My Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<script>
  const jobsData = [
    { id:1, title:"Senior Frontend Engineer", company:"Vercel", location:"Remote", workSetup:"Remote", jobType:"Full-time", experience:"Senior", industry:"Tech", salary:"₱160k – ₱210k", salaryMin:160, salaryMax:210, featured:true, tags:["React","TypeScript","Next.js"] },
    { id:2, title:"Product Designer", company:"Figma", location:"San Francisco", workSetup:"Hybrid", jobType:"Full-time", experience:"Mid", industry:"Design", salary:"₱120k – ₱150k", salaryMin:120, salaryMax:150, featured:true, tags:["Figma","Design Systems","UX"] },
    { id:3, title:"Financial Analyst", company:"Goldman Sachs", location:"New York", workSetup:"On-site", jobType:"Full-time", experience:"Mid", industry:"Finance", salary:"₱110k – ₱145k", salaryMin:110, salaryMax:145, featured:false, tags:["Excel","Modeling","Banking"] },
    { id:4, title:"Backend Engineer", company:"Stripe", location:"Remote", workSetup:"Remote", jobType:"Full-time", experience:"Senior", industry:"Tech", salary:"₱170k – ₱220k", salaryMin:170, salaryMax:220, featured:true, tags:["Go","APIs","Kubernetes"] },
    { id:5, title:"Marketing Lead", company:"Notion", location:"San Francisco", workSetup:"Hybrid", jobType:"Full-time", experience:"Senior", industry:"Marketing", salary:"₱130k – ₱160k", salaryMin:130, salaryMax:160, featured:false, tags:["Growth","Brand","SaaS"] },
    { id:6, title:"Healthcare Data Analyst", company:"Kaiser Permanente", location:"Remote", workSetup:"Remote", jobType:"Contract", experience:"Mid", industry:"Healthcare", salary:"₱90k – ₱110k", salaryMin:90, salaryMax:110, featured:false, tags:["SQL","Tableau","Healthcare"] },
    { id:7, title:"Junior UI Developer", company:"Adobe", location:"Austin", workSetup:"On-site", jobType:"Internship", experience:"Entry", industry:"Design", salary:"₱65k – ₱75k", salaryMin:65, salaryMax:75, featured:false, tags:["CSS","JavaScript","Figma"] },
    { id:8, title:"DevOps Engineer", company:"Netflix", location:"Remote", workSetup:"Remote", jobType:"Full-time", experience:"Senior", industry:"Tech", salary:"₱175k – ₱210k", salaryMin:175, salaryMax:210, featured:true, tags:["AWS","Terraform","CI/CD"] },
    { id:9, title:"Investment Associate", company:"BlackRock", location:"New York", workSetup:"On-site", jobType:"Full-time", experience:"Mid", industry:"Finance", salary:"₱120k – ₱145k", salaryMin:120, salaryMax:145, featured:false, tags:["Finance","Portfolio","CFA"] },
    { id:10, title:"Content Marketing Manager", company:"HubSpot", location:"Remote", workSetup:"Remote", jobType:"Part-time", experience:"Mid", industry:"Marketing", salary:"₱80k – ₱95k", salaryMin:80, salaryMax:95, featured:false, tags:["SEO","Writing","B2B"] }
  ];

  const companies = [
    { name:"Vercel", roles:8, salary:"₱160k+", tag:"Hiring Now" },
    { name:"Stripe", roles:24, salary:"₱170k+", tag:"Featured" },
    { name:"Figma", roles:12, salary:"₱120k+", tag:"Design" },
    { name:"Netflix", roles:9, salary:"₱175k+", tag:"Remote" },
    { name:"Notion", roles:15, salary:"₱130k+", tag:"Growth" }
  ];

  const themeToggle = document.getElementById('themeToggle');
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  const profileWrap = document.getElementById('profileWrap');
  const profileToggle = document.getElementById('profileToggle');
  const profileDropdown = document.getElementById('profileDropdown');

  function showToast(message, icon = 'fa-circle-info') {
    const old = document.querySelector('.toast');
    if (old) old.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 2200);
  }

  const savedTheme = localStorage.getItem('ac-employer-theme');
  if (savedTheme === 'light') {
    document.body.classList.add('light');
    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
  }

  themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('light');
    const isLight = document.body.classList.contains('light');
    localStorage.setItem('ac-employer-theme', isLight ? 'light' : 'dark');
    themeToggle.innerHTML = isLight ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
  });

  hamburger.addEventListener('click', () => {
    mobileMenu.classList.toggle('open');
    hamburger.innerHTML = mobileMenu.classList.contains('open')
      ? '<i class="fas fa-times"></i>'
      : '<i class="fas fa-bars"></i>';
  });

  profileToggle.addEventListener('click', (e) => {
    e.stopPropagation();
    profileDropdown.classList.toggle('open');
  });

  document.addEventListener('click', (e) => {
    if (!profileWrap.contains(e.target)) {
      profileDropdown.classList.remove('open');
    }
    if (!mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.innerHTML = '<i class="fas fa-bars"></i>';
    }
  });

  function renderFeaturedCompanies() {
    const container = document.getElementById('featuredCompanies');
    container.innerHTML = companies.map(c => `
      <div class="featured-card">
        <div class="fc-badge"><i class="fas fa-star"></i> ${c.tag}</div>
        <div class="fc-title">${c.name}</div>
        <div class="fc-company">${c.roles} open roles</div>
        <div class="chip blue">${c.salary}</div>
        <div class="fc-footer">
          <div class="fc-salary">${c.roles} roles</div>
          <button class="fc-action" type="button" onclick="showToast('Company profile for ${c.name} coming soon','fa-building')">View</button>
        </div>
      </div>
    `).join('');
  }

  function getFilteredJobs() {
    const keyword = document.getElementById('keywordInput').value.trim().toLowerCase();
    const jobType = document.getElementById('jobTypeFilter').value;
    const workSetup = document.getElementById('workSetupFilter').value;
    const experience = document.getElementById('expFilter').value;
    const industry = document.getElementById('industryFilter').value;
    const salaryMin = Number(document.getElementById('salaryMinFilter').value || 0);
    const salaryMax = Number(document.getElementById('salaryMaxFilter').value || 0);

    return jobsData.filter(job => {
      const matchKeyword =
        !keyword ||
        job.title.toLowerCase().includes(keyword) ||
        job.company.toLowerCase().includes(keyword) ||
        job.tags.some(tag => tag.toLowerCase().includes(keyword));

      const matchJobType = !jobType || job.jobType === jobType;
      const matchWorkSetup = !workSetup || job.workSetup === workSetup;
      const matchExperience = !experience || job.experience === experience;
      const matchIndustry = !industry || job.industry === industry;
      const matchSalaryMin = !salaryMin || job.salaryMax >= salaryMin;
      const matchSalaryMax = !salaryMax || job.salaryMin <= salaryMax;

      return matchKeyword && matchJobType && matchWorkSetup && matchExperience && matchIndustry && matchSalaryMin && matchSalaryMax;
    });
  }

  function renderJobs() {
    const filtered = getFilteredJobs();
    const container = document.getElementById('jobsContainer');
    document.getElementById('jobCount').textContent = `${filtered.length} job${filtered.length !== 1 ? 's' : ''}`;

    if (!filtered.length) {
      container.innerHTML = `
        <div class="empty-state">
          <i class="fas fa-briefcase" style="font-size:28px;color:#927C7A;margin-bottom:10px;"></i>
          <div style="font-weight:700;color:var(--text-light);margin-bottom:6px;">No jobs found</div>
          <div>Try changing your filters or search keywords.</div>
        </div>
      `;
      return;
    }

    container.innerHTML = filtered.map(job => `
      <div class="job-row">
        <div>
          <div class="jr-top">
            <div class="jr-title">${job.title}</div>
            <span class="jr-new ${job.featured ? 'green' : 'blue'}">${job.featured ? 'Featured' : 'Active'}</span>
          </div>

          <div class="jr-meta">
            <span class="jr-company"><i class="fas fa-building"></i> ${job.company}</span>
            <span><i class="fas fa-map-marker-alt"></i> ${job.location}</span>
            <span><i class="fas fa-laptop-house"></i> ${job.workSetup}</span>
            <span><i class="fas fa-clock"></i> ${job.jobType}</span>
            <span><i class="fas fa-layer-group"></i> ${job.experience}</span>
          </div>

          <div class="jr-chips">
            <span class="chip green">${job.industry}</span>
            ${job.tags.map(tag => `<span class="chip">${tag}</span>`).join('')}
          </div>
        </div>

        <div class="jr-right">
          <div class="jr-salary">${job.salary}</div>
          <div class="jr-actions">
            <button class="jr-btn" type="button" onclick="showToast('Preview for ${job.title}','fa-eye')">Preview</button>
            <button class="jr-btn" type="button" onclick="showToast('Open company profile for ${job.company}','fa-building')">Company</button>
            <button class="jr-btn primary" type="button" onclick="showToast('Job details for ${job.title}','fa-arrow-right')">View Job</button>
          </div>
        </div>
      </div>
    `).join('');
  }

  function quickFilter(value) {
    const industry = document.getElementById('industryFilter');
    const workSetup = document.getElementById('workSetupFilter');
    const jobType = document.getElementById('jobTypeFilter');
    const exp = document.getElementById('expFilter');

    if (['Tech', 'Design', 'Finance'].includes(value)) {
      industry.value = industry.value === value ? '' : value;
    } else if (value === 'Remote') {
      workSetup.value = workSetup.value === 'Remote' ? '' : 'Remote';
    } else if (value === 'Full-time') {
      jobType.value = jobType.value === 'Full-time' ? '' : 'Full-time';
    } else if (value === 'Senior') {
      exp.value = exp.value === 'Senior' ? '' : 'Senior';
    }

    document.querySelectorAll('.qf-pill').forEach(pill => {
      const text = pill.textContent.trim();
      const active =
        (text.includes('Remote') && workSetup.value === 'Remote') ||
        (text.includes('Full-time') && jobType.value === 'Full-time') ||
        (text.includes('Tech') && industry.value === 'Tech') ||
        (text.includes('Design') && industry.value === 'Design') ||
        (text.includes('Finance') && industry.value === 'Finance') ||
        (text.includes('Senior') && exp.value === 'Senior');
      pill.classList.toggle('active', active);
    });

    renderJobs();
  }

  const _guard_searchBtn = document.getElementById('searchBtn'); if (_guard_searchBtn) _guard_searchBtn.addEventListener('click', renderJobs);
  const _guard_keywordInput = document.getElementById('keywordInput'); if (_guard_keywordInput) _guard_keywordInput.addEventListener('input', renderJobs);
  const _guard_jobTypeFilter = document.getElementById('jobTypeFilter'); if (_guard_jobTypeFilter) _guard_jobTypeFilter.addEventListener('change', renderJobs);
  const _guard_workSetupFilter = document.getElementById('workSetupFilter'); if (_guard_workSetupFilter) _guard_workSetupFilter.addEventListener('change', renderJobs);
  const _guard_expFilter = document.getElementById('expFilter'); if (_guard_expFilter) _guard_expFilter.addEventListener('change', renderJobs);
  const _guard_industryFilter = document.getElementById('industryFilter'); if (_guard_industryFilter) _guard_industryFilter.addEventListener('change', renderJobs);
  const _guard_salaryMinFilter = document.getElementById('salaryMinFilter'); if (_guard_salaryMinFilter) _guard_salaryMinFilter.addEventListener('input', renderJobs);
  const _guard_salaryMaxFilter = document.getElementById('salaryMaxFilter'); if (_guard_salaryMaxFilter) _guard_salaryMaxFilter.addEventListener('input', renderJobs);

  const _guard_resetFiltersBtn = document.getElementById('resetFiltersBtn'); if (_guard_resetFiltersBtn) _guard_resetFiltersBtn.addEventListener('click', () => {
    document.getElementById('keywordInput').value = '';
    document.getElementById('jobTypeFilter').value = '';
    document.getElementById('workSetupFilter').value = '';
    document.getElementById('expFilter').value = '';
    document.getElementById('industryFilter').value = '';
    document.getElementById('salaryMinFilter').value = '';
    document.getElementById('salaryMaxFilter').value = '';
    document.querySelectorAll('.qf-pill').forEach(p => p.classList.remove('active'));
    renderJobs();
    showToast('Filters reset', 'fa-rotate-left');
  });

  renderFeaturedCompanies();
  renderJobs();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>