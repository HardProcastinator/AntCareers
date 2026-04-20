<?php
/**
 * AntCareers — Seeker Navbar Component
 * includes/seeker_navbar.php
 *
 * USAGE (in every seeker page, after requireLogin + getUser):
 *
 *   $navActive = 'dashboard'; // or 'jobs', 'people', 'company', 'profile',
 *                             //    'applications', 'saved', 'messages'
 *   include dirname(__DIR__) . '/includes/seeker_navbar.php';
 *
 * The $user array MUST be set by getUser() before including this file.
 * The $navActive string controls which link gets the .active class.
 */

if (!isset($user) || !is_array($user)) {
    die('[seeker_navbar.php] $user is not set. Call getUser() before including this file.');
}
$navActive = $navActive ?? 'dashboard';

// Helper: emit "active" class if the key matches
function _navActiveClass(string $key, string $active): string {
    return $key === $active ? ' active' : '';
}

// All seeker page routes in one place — single source of truth
$navRoutes = [
    'dashboard'    => 'antcareers_seekerDashboard.php',
    'jobs'         => 'antcareers_seekerJobs.php',
    'people'       => 'antcareers_seekerPeopleSearch.php',
    'company'      => 'antcareers_seekerCompany.php',
    'profile'      => 'antcareers_seekerProfile.php',
    'saved'        => 'antcareers_seekerSaved.php',
    'applications' => 'antcareers_seekerApplications.php',
    'messages'     => 'antcareers_seekerMessages.php',
    'settings'     => 'antcareers_seekerSettings.php',
];

// Build href with theme param forwarded
function _navHref(string $file): string {
    $theme = htmlspecialchars($_GET['theme'] ?? '', ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($file . ($theme !== '' ? '?theme=' . $theme : ''), ENT_QUOTES, 'UTF-8');
}

$seekerAvatarUrl = (string)($user['avatarUrl'] ?? '');
if ($seekerAvatarUrl !== '' && !str_starts_with($seekerAvatarUrl, '../') && !str_starts_with($seekerAvatarUrl, 'http')) {
  $seekerAvatarUrl = '../' . $seekerAvatarUrl;
}
?>

<!-- ═══════════════════════════════════════════════════════
     GLOBAL STYLES — Navbar + Mobile Menu + Notifications
     (shared across ALL seeker pages)
     ═══════════════════════════════════════════════════════ -->
<style>
  /* ── NAVBAR ── */
  .navbar { position:sticky; top:0; z-index:200; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
  .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
  .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
  .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
  .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
  .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
  .logo-text span { color:var(--red-bright); }

  .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
  /* nav-link uses <a> with href — never plain text, never onclick-only */
  .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:color 0.2s, background 0.2s; display:flex; align-items:center; gap:5px; white-space:nowrap; letter-spacing:0.01em; cursor:pointer; }
  .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
  .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }
  /* Active links stay clickable — pointer-events always on */
  .nav-link, .nav-link.active { pointer-events:auto; }

  .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
  .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
  .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }

  /* Profile dropdown */
  .profile-wrap { position:relative; }
  .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
  .profile-btn:hover { background:var(--soil-card); }
  .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
  .profile-avatar img { width:100%; height:100%; object-fit:cover; }
  .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
  .profile-role-lbl { font-size:10px; color:var(--red-pale); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
  .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }

  .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:190px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
  .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
  .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
  .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
  .pdh-sub  { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; }

  .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
  .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
  .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
  .pd-item:hover i { color:var(--red-bright); }
  .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
  .pd-item.danger { color:#E05555; }
  .pd-item.danger i { color:#E05555; }
  .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }

  /* Notification & Message nav buttons */
  .msg-btn-nav, .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted); flex-shrink:0; }
  .msg-btn-nav:hover, .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
  .msg-btn-nav .badge, .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); }
  body.light .msg-btn-nav, body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
  body.light .msg-btn-nav .badge, body.light .notif-btn-nav .badge { border-color:#F9F5F4; }

  /* Messages panel */
  .msg-panel { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:100vw; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:500; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
  .msg-panel.open { transform:translateX(0); }
  .msg-panel-head { padding:18px 18px 14px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
  .msg-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:8px; }
  .msg-panel-title i { color:var(--red-bright); }
  #msgThreadView { display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden; }
  .msg-panel-body { flex:1; overflow-y:auto; min-height:0; padding:0; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
  .msg-panel-search { padding:10px 14px; border-bottom:1px solid var(--soil-line); flex-shrink:0; }
  .msg-panel-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 12px; }
  .msg-panel-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
  .msg-panel-search-bar input::placeholder { color:var(--text-muted); }
  .msg-panel-search-bar i { color:var(--text-muted); font-size:12px; }
  /* New compose area inside panel */
  .msg-panel-new-chat { padding:10px 14px; border-bottom:1px solid var(--soil-line); flex-shrink:0; }
  .msg-panel-new-chat-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--red-vivid); border-radius:8px; padding:8px 12px; }
  .msg-panel-new-chat-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
  .msg-panel-new-chat-bar input::placeholder { color:var(--text-muted); }
  .msg-panel-new-chat-bar i { color:var(--red-bright); font-size:13px; }
  .msg-panel-new-chat-results { max-height:180px; overflow-y:auto; margin-top:6px; scrollbar-width:thin; }
  .msg-panel-new-chat-user { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:0.15s; }
  .msg-panel-new-chat-user:hover { background:var(--soil-hover); }
  .msg-panel-new-chat-user-av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
  .msg-panel-new-chat-user-av img { width:100%; height:100%; object-fit:cover; }
  body.light .msg-panel-new-chat-bar { border-color:var(--red-vivid); background:#F5EEEC; }
  body.light .msg-panel-new-chat-bar input { color:#1A0A09; }
  .msg-item { display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
  .msg-item:last-child { border-bottom:none; }
  .msg-item:hover { background:var(--soil-hover); }
  .msg-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
  .msg-avatar img { width:100%; height:100%; object-fit:cover; }
  .msg-name { font-size:13px; font-weight:600; color:var(--text-mid); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .msg-item.unread .msg-name { font-weight:700; color:var(--text-light); }
  .msg-preview { font-size:12px; color:var(--text-muted); line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .msg-time { font-size:10px; color:var(--text-muted); font-weight:600; flex-shrink:0; white-space:nowrap; }
  .msg-job-tag { font-size:11px; color:var(--red-pale); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  body.light .msg-panel { background:#FFFFFF; border-color:#E0CECA; box-shadow:-8px 0 32px rgba(0,0,0,0.1); }
  body.light .msg-panel-title { color:#1A0A09; }
  body.light .msg-item { border-color:#E0CECA; }
  body.light .msg-item:hover { background:#FEF0EE; }
  body.light .msg-panel-search-bar { background:#F5EEEC; border-color:#E0CECA; }
  body.light .msg-panel-search-bar input { color:#1A0A09; }
  body.light .msg-name { color:#4A2828; }
  body.light .msg-item.unread .msg-name { color:#1A0A09; }

  /* Hamburger */
  .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }

  /* Mobile menu */
  .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
  .mobile-menu.open { display:flex; }
  .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
  .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
  .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
  .mobile-link.active { color:#F5F0EE; background:var(--soil-hover); }
  .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

  /* Notifications panel */
  .notif-panel { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:100vw; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:500; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
  .notif-panel.open { transform:translateX(0); }
  .sidepanel-overlay { display:none; position:fixed; inset:0; z-index:499; background:rgba(0,0,0,0.35); backdrop-filter:blur(2px); }
  .sidepanel-overlay.visible { display:block; }
  .notif-panel-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
  .notif-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
  .notif-panel-title i { color:var(--red-bright); }
  .notif-close { width:28px; height:28px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
  #msgNewChat i { font-size:11px; }
  .notif-close:hover { color:#F5F0EE; }
  .notif-panel-body { flex:1; overflow-y:auto; padding:12px 16px; }
  .person-modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:20px; }
  .person-modal-box { width:min(640px,100%); max-height:85vh; overflow-y:auto; scrollbar-width:thin; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; padding:24px; box-shadow:0 32px 80px rgba(0,0,0,0.55); position:relative; animation:fadeUp 0.22s ease both; }
  .person-modal-close { position:absolute; top:14px; right:14px; width:30px; height:30px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:pointer; }
  .person-modal-close:hover { color:var(--text-light); border-color:var(--red-vivid); }
  .person-modal-head { display:flex; gap:16px; align-items:flex-start; margin-bottom:16px; }
  .person-modal-avatar { width:72px; height:72px; border-radius:18px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:22px; font-weight:800; overflow:hidden; flex-shrink:0; }
  .person-modal-avatar img { width:100%; height:100%; object-fit:cover; }
  .person-modal-meta { flex:1; min-width:0; }
  .person-modal-name { font-family:var(--font-display); font-size:22px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
  .person-modal-title { font-size:14px; color:var(--red-pale); font-weight:600; margin-bottom:4px; }
  .person-modal-location, .pm-detail-row { font-size:12px; color:var(--text-muted); display:flex; align-items:center; gap:5px; margin-top:3px; }
  .person-modal-location i, .pm-detail-row i { font-size:11px; color:var(--red-mid); }
  .person-modal-status { display:inline-flex; align-items:center; padding:5px 10px; border-radius:999px; font-size:11px; font-weight:700; margin-bottom:14px; }
  .person-modal-status.seeking { background:rgba(76,175,112,0.12); color:#6CCF8A; }
  .person-modal-status.hiring { background:rgba(209,61,44,0.12); color:var(--red-pale); }
  .person-modal-status.neutral { background:rgba(212,148,58,0.12); color:#E0B06F; }
  .person-modal-section { margin-top:16px; }
  .person-modal-section-label { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); margin-bottom:8px; }
  .pm-about { font-size:13px; line-height:1.6; color:var(--text-mid); }
  .pm-section-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
  .pm-info-card { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:12px; }
  .pm-info-label { font-size:10px; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); margin-bottom:5px; font-weight:800; }
  .pm-info-value { font-size:13px; color:var(--text-light); font-weight:600; line-height:1.45; }
  .person-skill-row { display:flex; flex-wrap:wrap; gap:6px; }
  .person-skill-chip { font-size:11px; padding:5px 9px; border-radius:999px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); }
  .person-skill-empty { font-size:12px; color:var(--text-muted); }
  .person-modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; flex-wrap:wrap; }
  .person-modal-btn { padding:8px 14px; border-radius:8px; border:1px solid var(--soil-line); font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; }
  .person-modal-btn.secondary { background:var(--soil-hover); color:var(--text-light); }
  .person-modal-btn.secondary:hover { border-color:var(--red-vivid); }
  .person-modal-btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
  .person-modal-btn.primary:hover { background:var(--red-bright); }
  .pm-resume-btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:8px; border:1px solid rgba(76,175,112,0.3); background:rgba(76,175,112,0.08); color:#6ccf8a; font-size:12px; font-weight:700; font-family:var(--font-body); cursor:pointer; text-decoration:none; transition:0.18s; margin-top:8px; }
  .pm-resume-btn:hover { background:rgba(76,175,112,0.15); border-color:rgba(76,175,112,0.5); }
  .pm-resume-btn i { font-size:13px; }
  .notif-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
  .notif-item:last-child { border-bottom:none; }
  .n-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:5px; }
  .n-dot.red   { background:var(--red-vivid); }
  .n-dot.amber { background:var(--amber); }
  .n-dot.green { background:#4CAF70; }
  .n-dot.read  { background:var(--soil-line); }
  .n-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
  .n-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }

  /* Toast — handled by includes/toast.php */

  /* Light theme overrides for navbar elements */
  body.light .navbar { background:rgba(249,245,244,0.97); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
  body.light .logo-text { color:#1A0A09; }
  body.light .logo-text span { color:var(--red-vivid); }
  body.light .nav-link { color:#5A4040; }
  body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
  body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
  body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
  body.light .profile-name { color:#1A0A09; }
  body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
  body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
  body.light .pd-item { color:#4A2828; }
  body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
  body.light .pdh-name { color:#1A0A09; }
  body.light .mobile-menu { background:rgba(249,245,244,0.97); border-color:#E0CECA; }
  body.light .mobile-link { color:#4A2828; }
  body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }
  body.light .notif-panel { background:#FFFFFF; border-color:#E0CECA; box-shadow:-8px 0 32px rgba(0,0,0,0.1); }
  body.light .notif-panel-title { color:#1A0A09; }
  body.light .notif-item { border-color:#E0CECA; }
  body.light .n-text { color:#3A2020; }
  body.light .n-text strong { color:#1A0A09; }
  body.light .n-time { color:#7A5555; }
  body.light .notif-close { background:#F0E4E2; border-color:#E0CECA; color:#7A5555; }
  body.light .n-dot.read { background:#E0CECA; }
  body.light .sidepanel-overlay { background:rgba(0,0,0,0.15); }
  body.light .person-modal-overlay { background:rgba(0,0,0,0.5); }
  body.light .person-modal-box { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 32px 80px rgba(0,0,0,0.18); }
  body.light .person-modal-close { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
  body.light .person-modal-name { color:#1A0A09; }
  body.light .person-modal-title { color:var(--red-vivid); }
  body.light .person-modal-section-label { color:#7A5555; }
  body.light .pm-about { color:#4A2828; }
  body.light .pm-info-card { background:#F5EEEC; border-color:#E0CECA; }
  body.light .pm-info-value { color:#1A0A09; }
  body.light .person-skill-chip { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
  body.light .person-modal-btn.secondary { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
  body.light .person-modal-btn.secondary:hover { border-color:var(--red-vivid); }
  body.light .pm-resume-btn { background:rgba(76,175,112,0.06); border-color:rgba(76,175,112,0.25); color:#2E7D4C; }
  body.light .pm-resume-btn:hover { background:rgba(76,175,112,0.12); }

  /* Conversation slide-over — mirrors employer .msg-sb-chat */
  #msgConvoView {
    position:absolute; inset:0;
    background:var(--soil-card);
    display:flex; flex-direction:column;
    transform:translateX(100%);
    transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);
    z-index:1;
  }
  #msgConvoView.open { transform:translateX(0); }
  body.light #msgConvoView { background:#FFFFFF; }
  .sp-chat-head { display:flex; align-items:center; gap:10px; padding:12px 14px; border-bottom:1px solid var(--soil-line); flex-shrink:0; }
  .sp-chat-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
  .sp-chat-avatar img { width:100%; height:100%; object-fit:cover; }
  .sp-chat-name { font-size:14px; font-weight:700; color:var(--text-light); }
  .sp-chat-meta { font-size:11px; color:var(--text-muted); margin-top:1px; }
  .sp-chat-messages { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px; scrollbar-width:thin; min-height:0; }
  .sp-chat-input { padding:10px 14px; border-top:1px solid var(--soil-line); flex-shrink:0; }
  .sp-chat-input-row { display:flex; align-items:flex-end; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 10px; transition:0.2s; }
  .sp-chat-input-row:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.1); }
  .sp-chat-input-row textarea { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:none; min-height:32px; max-height:80px; line-height:1.4; }
  .sp-chat-input-row textarea::placeholder { color:var(--text-muted); }
  .sp-chat-send { width:32px; height:32px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px; transition:0.2s; flex-shrink:0; }
  .sp-chat-send:hover { background:var(--red-bright); transform:scale(1.05); }
  .sp-msg-row { display:flex; gap:6px; align-items:flex-end; }
  .sp-msg-row.sent { flex-direction:row-reverse; }
  .sp-msg-date { text-align:center; font-size:10px; color:var(--text-muted); padding:4px 0; position:relative; }
  .sp-msg-date::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background:var(--soil-line); }
  .sp-msg-date span { background:var(--soil-card); padding:0 8px; position:relative; }
  body.light .sp-msg-date span { background:#FFFFFF; }
  body.light .sp-chat-name { color:#1A0A09; }
  body.light .sp-chat-input-row { background:#F5EEEC; border-color:#E0CECA; }
  body.light .sp-chat-input-row textarea { color:#1A0A09; }
  body.light .sp-chat-head { border-color:#E0CECA; }
  body.light .sp-chat-input { border-color:#E0CECA; }

  /* Bubble styles shared with conversation view */
  .sb-bubble { max-width:80%; min-width:48px; padding:8px 12px; border-radius:12px; font-size:13px; line-height:1.45; word-break:break-word; white-space:pre-wrap; }
  .sb-bubble-recv { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
  .sb-bubble-sent { background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
  .sb-bubble-time { font-size:9px; margin-top:3px; opacity:0.6; }
  .sb-bubble-sent .sb-bubble-time { text-align:right; }
  body.light .sb-bubble-recv { background:#F5EEEC; color:#1A0A09; }

  /* Unread dot on thread item */
  .msg-item .msg-unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); flex-shrink:0; margin-top:4px; }

  @media(max-width:760px) {
    .nav-links { display:none; }
    .hamburger { display:flex; }
    .profile-name, .profile-role-lbl { display:none; }
    .profile-chevron { display:none; }
    .profile-btn { padding:4px; gap:0; }
    .nav-inner { padding:0 10px; gap:4px; }
    .nav-right { gap:6px; flex-shrink:0; }
    .theme-btn, .msg-btn-nav, .notif-btn-nav { width:30px; height:30px; font-size:12px; }
    .profile-wrap { display:none; }
  }
</style>

<!-- ═══════════ NAVBAR ═══════════ -->
<nav class="navbar" id="mainNavbar">
  <div class="nav-inner">

    <a class="logo" href="<?= _navHref($navRoutes['dashboard']) ?>">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>

    <!-- Top nav: global discovery links only -->
    <div class="nav-links" id="navLinks">
      <a class="nav-link<?= _navActiveClass('dashboard', $navActive) ?>"
         href="<?= _navHref($navRoutes['dashboard']) ?>">
        <i class="fas fa-th-large"></i> Dashboard
      </a>
      <a class="nav-link<?= _navActiveClass('jobs', $navActive) ?>"
         href="<?= _navHref($navRoutes['jobs']) ?>">
        <i class="fas fa-search"></i> Browse Jobs
      </a>
      <a class="nav-link<?= _navActiveClass('people', $navActive) ?>"
         href="<?= _navHref($navRoutes['people']) ?>">
        <i class="fas fa-users"></i> People Search
      </a>
      <a class="nav-link<?= _navActiveClass('company', $navActive) ?>"
         href="<?= _navHref($navRoutes['company']) ?>">
        <i class="fas fa-building"></i> Companies
      </a>
    </div>

    <div class="nav-right">
      <button class="theme-btn" id="themeToggle" title="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>

      <button class="msg-btn-nav" id="msgToggle" data-msg-trigger title="Messages">
        <i class="fas fa-envelope"></i>
        <span class="badge" id="seekerMsgBadge" style="visibility:hidden;opacity:0">0</span>
      </button>

      <button class="notif-btn-nav" id="notifToggle" data-notif-trigger title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="badge" id="seekerNotifBadge" style="visibility:hidden;opacity:0">0</span>
      </button>

      <!-- Profile dropdown: account actions only -->
      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle" type="button" aria-haspopup="true" aria-expanded="false">
          <div class="profile-avatar"><?php if (!empty($seekerAvatarUrl)): ?><img src="<?= htmlspecialchars($seekerAvatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($user['initials'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div>
          <div>
            <div class="profile-name"><?= htmlspecialchars($user['fullName'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-role-lbl">Job Seeker</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown" role="menu">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?= htmlspecialchars($user['fullName'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pdh-sub">Job Seeker · Active</div>
          </div>
          <a class="pd-item" href="<?= _navHref($navRoutes['profile']) ?>" role="menuitem">
            <i class="fas fa-user"></i> My Profile
          </a>
          <a class="pd-item" href="<?= _navHref($navRoutes['applications']) ?>" role="menuitem">
            <i class="fas fa-paper-plane"></i> My Applications
          </a>
          <a class="pd-item" href="<?= _navHref($navRoutes['saved']) ?>" role="menuitem">
            <i class="fas fa-heart"></i> Saved Jobs
          </a>
          <a class="pd-item" href="<?= _navHref($navRoutes['messages']) ?>" role="menuitem">
            <i class="fas fa-envelope"></i> Messages
          </a>
          <a class="pd-item" href="<?= _navHref($navRoutes['settings']) ?>" role="menuitem">
            <i class="fas fa-cog"></i> Settings
          </a>
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php" role="menuitem">
            <i class="fas fa-sign-out-alt"></i> Sign out
          </a>
        </div>
      </div>

      <button class="theme-btn hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
        <i class="fas fa-bars"></i>
      </button>
    </div>

  </div>
</nav>

<!-- Mobile menu — mirrors all nav + sidebar links -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <a class="mobile-link<?= _navActiveClass('dashboard',    $navActive) ?>" href="<?= _navHref($navRoutes['dashboard'])    ?>"><i class="fas fa-th-large"></i> Dashboard</a>
  <a class="mobile-link<?= _navActiveClass('jobs',         $navActive) ?>" href="<?= _navHref($navRoutes['jobs'])         ?>"><i class="fas fa-search"></i> Browse Jobs</a>
  <a class="mobile-link<?= _navActiveClass('people',       $navActive) ?>" href="<?= _navHref($navRoutes['people'])       ?>"><i class="fas fa-users"></i> People Search</a>
  <a class="mobile-link<?= _navActiveClass('company',      $navActive) ?>" href="<?= _navHref($navRoutes['company'])      ?>"><i class="fas fa-building"></i> Companies</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link<?= _navActiveClass('profile',      $navActive) ?>" href="<?= _navHref($navRoutes['profile'])      ?>"><i class="fas fa-user"></i> My Profile</a>
  <a class="mobile-link<?= _navActiveClass('applications', $navActive) ?>" href="<?= _navHref($navRoutes['applications']) ?>"><i class="fas fa-paper-plane"></i> My Applications</a>
  <a class="mobile-link<?= _navActiveClass('saved',        $navActive) ?>" href="<?= _navHref($navRoutes['saved'])        ?>"><i class="fas fa-heart"></i> Saved Jobs</a>
  <a class="mobile-link<?= _navActiveClass('messages',     $navActive) ?>" href="<?= _navHref($navRoutes['messages'])     ?>"><i class="fas fa-envelope"></i> Messages</a>
  <a class="mobile-link<?= _navActiveClass('settings',     $navActive) ?>" href="<?= _navHref($navRoutes['settings'])     ?>"><i class="fas fa-cog"></i> Settings</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<!-- Messages slide-in panel -->
<div class="msg-panel" id="msgPanel" aria-hidden="true">
  <!-- Persistent header — always visible -->
  <div class="msg-panel-head">
    <div class="msg-panel-title"><i class="fas fa-envelope"></i> Messages</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="notif-close" id="msgNewChat" title="New Conversation" style="background:var(--red-vivid);color:#fff;border-color:var(--red-vivid);"><i class="fas fa-pen-to-square"></i></button>
      <a class="notif-close" id="msgExpandFull" href="<?= _navHref($navRoutes['messages']) ?>" title="Open full page" style="text-decoration:none"><i class="fas fa-expand"></i></a>
      <button class="notif-close" id="msgClose" aria-label="Close messages"><i class="fas fa-times"></i></button>
    </div>
  </div>

  <!-- Content area: threads + conversation slide-over -->
  <div id="msgContentArea" style="flex:1;position:relative;min-height:0;overflow:hidden;display:flex;flex-direction:column;">
    <!-- Thread list view (default) -->
    <div id="msgThreadView">
      <!-- New chat search -->
      <div class="msg-panel-new-chat" id="msgNewChatPanel" style="display:none;">
        <div class="msg-panel-new-chat-bar">
          <i class="fas fa-search"></i>
          <input type="text" id="msgNewChatSearch" placeholder="Search users to message..." oninput="searchMsgPanelNewChat()">
        </div>
        <div class="msg-panel-new-chat-results" id="msgNewChatResults"></div>
      </div>
      <div class="msg-panel-search">
        <div class="msg-panel-search-bar">
          <i class="fas fa-search"></i>
          <input type="text" id="msgPanelSearch" placeholder="Search conversations..." oninput="filterMsgPanelList(this.value)">
        </div>
      </div>
      <div class="msg-panel-body" id="msgThreadList">
        <div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;" id="msgThreadLoading">
          <i class="fas fa-spinner fa-spin"></i> Loading messages…
        </div>
      </div>
    </div>

    <!-- Conversation view (slides over thread list) -->
    <div id="msgConvoView">
      <div class="sp-chat-head">
        <button class="notif-close" id="msgConvoBack" aria-label="Back"><i class="fas fa-arrow-left"></i></button>
        <div class="sp-chat-avatar" id="msgConvoAvatar"></div>
        <div style="flex:1;min-width:0;">
          <div class="sp-chat-name" id="msgConvoName"></div>
          <div class="sp-chat-meta" id="msgConvoMeta"></div>
        </div>
      </div>
      <div class="sp-chat-messages" id="msgConvoBody"></div>
      <div class="sp-chat-input">
        <div class="sp-chat-input-row">
          <textarea id="msgConvoInput" placeholder="Write a message..." rows="1"
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();seekerSendMsg();}"
            oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,80)+'px';"></textarea>
          <button class="sp-chat-send" id="msgConvoSend"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Notifications slide-in panel -->
<div class="notif-panel" id="notifPanel" aria-hidden="true">
  <div class="notif-panel-head">
    <div class="notif-panel-title"><i class="fas fa-bell"></i> Notifications</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="notif-close" id="notifClearAll" title="Clear all"><i class="fas fa-trash-alt"></i></button>
      <button class="notif-close" id="notifMarkAll" title="Mark all read"><i class="fas fa-check-double"></i></button>
      <button class="notif-close" id="notifClose" aria-label="Close notifications"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div class="notif-panel-body" id="notifList">
    <div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;" id="notifLoading">
      <i class="fas fa-spinner fa-spin"></i> Loading notifications…
    </div>
  </div>
</div>
<div class="sidepanel-overlay" id="sidePanelOverlay" aria-hidden="true"></div>

<div class="person-modal-overlay" id="globalPersonModal" aria-hidden="true" style="display:none;">
  <div class="person-modal-box">
    <button class="person-modal-close" type="button" id="globalPersonModalClose" aria-label="Close profile preview"><i class="fas fa-times"></i></button>
    <div id="globalPersonModalBody"></div>
  </div>
</div>

<!-- ═══════════ SHARED NAVBAR SCRIPTS ═══════════
     Placed right after the markup so IDs are already in the DOM.
     Each page may add its own <script> blocks after including this file.
     ════════════════════════════════════════════ -->
<script>
(function () {
  'use strict';

  // ── THEME ──────────────────────────────────────────────────────────────────
  function applyTheme(t) {
    const isLight = t === 'light';
    document.body.classList.toggle('light', isLight);
    document.documentElement.classList.toggle('theme-light', isLight);
    const icon = document.getElementById('themeIcon');
    if (icon) icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('ac-theme', t);
  }

  // On load: honour ?theme= param, then localStorage, then default 'light'
  const paramTheme = new URLSearchParams(window.location.search).get('theme');
  const storedTheme = localStorage.getItem('ac-theme') || 'light';
  const initialTheme = paramTheme || storedTheme;
  if (paramTheme) localStorage.setItem('ac-theme', paramTheme);
  applyTheme(initialTheme);

  document.getElementById('themeToggle').addEventListener('click', function () {
    applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
  });

  // ── HAMBURGER ──────────────────────────────────────────────────────────────
  const hamburger  = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');

  function syncMobileMenuPosition() {
    const nav = document.getElementById('mainNavbar') || document.querySelector('.navbar');
    if (!mobileMenu || !nav) return;
    const rect = nav.getBoundingClientRect();
    const top = Math.max(0, Math.round(rect.bottom));
    mobileMenu.style.top = top + 'px';
    mobileMenu.style.maxHeight = `calc(100dvh - ${top}px)`;
  }

  window.addEventListener('scroll', syncMobileMenuPosition, { passive: true });
  window.addEventListener('resize', syncMobileMenuPosition);
  syncMobileMenuPosition();

  hamburger.addEventListener('click', function (e) {
    e.stopPropagation();
    syncMobileMenuPosition();
    const isOpen = mobileMenu.classList.toggle('open');
    hamburger.setAttribute('aria-expanded', String(isOpen));
    mobileMenu.setAttribute('aria-hidden', String(!isOpen));
    hamburger.querySelector('i').className = isOpen ? 'fas fa-times' : 'fas fa-bars';
  });

  // ── PROFILE DROPDOWN ───────────────────────────────────────────────────────
  const profileToggle   = document.getElementById('profileToggle');
  const profileDropdown = document.getElementById('profileDropdown');

  profileToggle.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = profileDropdown.classList.toggle('open');
    profileToggle.setAttribute('aria-expanded', String(isOpen));
  });

  // ── NOTIFICATIONS PANEL ────────────────────────────────────────────────────
  const notifPanel = document.getElementById('notifPanel');
  const sidePanelOverlay = document.getElementById('sidePanelOverlay');
  const globalPersonModal = document.getElementById('globalPersonModal');

  function escHtml(value) {
    var div = document.createElement('div');
    div.textContent = value || '';
    return div.innerHTML;
  }

  function closeGlobalPersonModal() {
    if (!globalPersonModal) return;
    globalPersonModal.style.display = 'none';
    globalPersonModal.setAttribute('aria-hidden', 'true');
  }

  function openGlobalPersonModal(personId) {
    var id = parseInt(personId, 10);
    if (!id) return;

    fetch('../api/person_preview.php?id=' + id)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success || !data.person) {
          throw new Error(data.message || 'Unable to load profile');
        }

        var person = data.person;
        var skills = (person.skills || []).slice(0, 8).map(function (s) {
          return '<span class="person-skill-chip">' + escHtml(s) + '</span>';
        }).join('');

        var infoCards = '';
        if (person.exp) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Experience</div><div class="pm-info-value">' + escHtml(person.exp) + '</div></div>';
        if (person.availability) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Availability</div><div class="pm-info-value">' + escHtml(person.availability) + '</div></div>';
        if (person.workTypes) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Work Type</div><div class="pm-info-value">' + escHtml(person.workTypes) + '</div></div>';
        if (person.rightToWork) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Right to Work</div><div class="pm-info-value">' + escHtml(person.rightToWork) + '</div></div>';
        if (person.classification) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Classification</div><div class="pm-info-value">' + escHtml(person.classification) + '</div></div>';
        if (person.salary) {
          var salaryDisplay = person.salary + (person.salaryPeriod ? ' ' + person.salaryPeriod : '');
          infoCards += '<div class="pm-info-card"><div class="pm-info-label">Salary Expectation</div><div class="pm-info-value">' + escHtml(salaryDisplay) + '</div></div>';
        }

        var aboutText = person.summary || person.bio || '';
        var aboutSection = aboutText
          ? '<div class="person-modal-section"><div class="person-modal-section-label">About</div><div class="pm-about">' + escHtml(aboutText) + '</div></div>'
          : '';

        var resumeSection = person.resumePath
          ? '<div class="person-modal-section"><div class="person-modal-section-label">Resume</div><a class="pm-resume-btn" href="' + escHtml(person.resumePath) + '" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> ' + escHtml(person.resumeName || 'View Resume') + '</a></div>'
          : '';

        var statusLabel = person.status === 'seeking' ? 'Open to work' : person.status === 'hiring' ? 'Actively hiring' : 'Available';
        var statusClass = person.status === 'seeking' ? 'seeking' : person.status === 'hiring' ? 'hiring' : 'neutral';

        document.getElementById('globalPersonModalBody').innerHTML =
          '<div class="person-modal-head">' +
            '<div class="person-modal-avatar" style="background:' + escHtml(person.color || 'linear-gradient(135deg,var(--red-vivid),var(--red-deep))') + ';">' +
              (person.avatarUrl ? '<img src="' + escHtml(person.avatarUrl) + '" alt="">' : escHtml(person.avatar)) +
            '</div>' +
            '<div class="person-modal-meta">' +
              '<div class="person-modal-name">' + escHtml(person.name) + '</div>' +
              '<div class="person-modal-title">' + escHtml(person.title) + '</div>' +
              '<div class="person-modal-location"><i class="fas fa-map-marker-alt"></i> ' + escHtml(person.location) + '</div>' +
              (person.phone ? '<div class="pm-detail-row"><i class="fas fa-phone"></i> ' + escHtml(person.phone) + '</div>' : '') +
            '</div>' +
          '</div>' +
          '<div class="person-modal-status ' + statusClass + '">' + escHtml(statusLabel) + '</div>' +
          aboutSection +
          (infoCards ? '<div class="person-modal-section"><div class="person-modal-section-label">Details</div><div class="pm-section-grid">' + infoCards + '</div></div>' : '') +
          '<div class="person-modal-section"><div class="person-modal-section-label">Skills</div><div class="person-skill-row">' + (skills || '<span class="person-skill-empty">No skills listed</span>') + '</div></div>' +
          resumeSection +
          '<div class="person-modal-actions">' +
            '<button class="person-modal-btn secondary" type="button" onclick="window.location.href=\'antcareers_seekerMessages.php?user_id=' + id + '\'"><i class="fas fa-comment-dots"></i> Message</button>' +
            '<button class="person-modal-btn primary" type="button" id="globalPersonModalCloseBtn">Close</button>' +
          '</div>';

        globalPersonModal.style.display = 'flex';
        globalPersonModal.setAttribute('aria-hidden', 'false');
        var closeBtn = document.getElementById('globalPersonModalCloseBtn');
        if (closeBtn) closeBtn.addEventListener('click', closeGlobalPersonModal, { once: true });
      })
      .catch(function () {
        if (typeof showToast === 'function') showToast('Unable to load profile', 'fa-exclamation');
      });
  }

  window.openGlobalPersonModal = openGlobalPersonModal;
  window.closeGlobalPersonModal = closeGlobalPersonModal;

  if (globalPersonModal) {
    globalPersonModal.addEventListener('click', function (e) {
      if (e.target === globalPersonModal) closeGlobalPersonModal();
    });
    var closeIcon = document.getElementById('globalPersonModalClose');
    if (closeIcon) closeIcon.addEventListener('click', closeGlobalPersonModal);
  }

  function syncSidePanelOverlay() {
    if (!sidePanelOverlay) return;
    if (notifPanel.classList.contains('open') || msgPanel.classList.contains('open')) {
      sidePanelOverlay.classList.add('visible');
      sidePanelOverlay.setAttribute('aria-hidden', 'false');
    } else {
      sidePanelOverlay.classList.remove('visible');
      sidePanelOverlay.setAttribute('aria-hidden', 'true');
    }
  }

  window.openNotif  = function () { closeMsgPanel(); notifPanel.classList.add('open'); notifPanel.setAttribute('aria-hidden', 'false'); syncSidePanelOverlay(); loadNotifications(); };
  window.closeNotif = function () { notifPanel.classList.remove('open'); notifPanel.setAttribute('aria-hidden', 'true'); syncSidePanelOverlay(); };

  document.getElementById('notifClose').addEventListener('click', closeNotif);
  document.getElementById('notifToggle').addEventListener('click', function (e) {
    e.stopPropagation();
    notifPanel.classList.contains('open') ? closeNotif() : openNotif();
  });

  // Mark all notifications read
  document.getElementById('notifMarkAll').addEventListener('click', function () {
    fetch('../api/messages.php?action=mark_notif_read', { method: 'POST' })
      .then(function () { loadNotifications(); updateSeekerBadges(); });
  });

  // Clear all notifications
  document.getElementById('notifClearAll').addEventListener('click', function () {
    fetch('../api/messages.php?action=clear_notifications', { method: 'POST' })
      .then(function () { loadNotifications(); updateSeekerBadges(); });
  });

  // ── MESSAGES PANEL ─────────────────────────────────────────────────────────
  const msgPanel = document.getElementById('msgPanel');
  const msgThreadView = document.getElementById('msgThreadView');
  const msgConvoView  = document.getElementById('msgConvoView');
  var _seekerCurrentPartner = null;
  var _seekerThreadsCache   = [];

  function getCurrentTheme() {
    return document.body.classList.contains('light') ? 'light' : 'dark';
  }

  function getFullMessagesUrl(partnerId) {
    var base = '<?= htmlspecialchars($navRoutes['messages'], ENT_QUOTES, 'UTF-8') ?>';
    var q = new URLSearchParams();
    q.set('theme', getCurrentTheme());
    if (partnerId && Number.isFinite(partnerId) && partnerId > 0) {
      q.set('user_id', String(partnerId));
    }
    return base + '?' + q.toString();
  }

  function refreshFullMessageLinks() {
    var href = getFullMessagesUrl(_seekerCurrentPartner);
    var a = document.getElementById('msgExpandFull');
    var b = document.getElementById('msgConvoExpandFull');
    if (a) a.href = href;
    if (b) b.href = href;
  }

  window.openMsgPanel  = function () {
    closeNotif();
    msgPanel.classList.add('open');
    msgPanel.setAttribute('aria-hidden', 'false');
    syncSidePanelOverlay();
    showThreadView();
    loadSeekerThreads();
    refreshFullMessageLinks();
  };
  window.closeMsgPanel = function () {
    msgPanel.classList.remove('open');
    msgPanel.setAttribute('aria-hidden', 'true');
    syncSidePanelOverlay();
  };

  function showThreadView() {
    msgConvoView.classList.remove('open');
    _seekerCurrentPartner = null;
    refreshFullMessageLinks();
  }
  function showConvoView() {
    msgConvoView.classList.add('open');
  }

  function _esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function getNotifUrl(type, refId) {
    switch (type) {
      case 'message': return 'antcareers_seekerMessages.php' + (refId ? '?user_id=' + refId : '');
      case 'application': case 'offer': case 'offer_response': return 'antcareers_seekerApplications.php';
      case 'offer_credential': case 'hired_credential': return 'antcareers_seekerApplications.php';
      case 'interview': case 'interview_invite': case 'interview_accepted': return 'antcareers_seekerApplications.php';
      case 'new_application': return 'antcareers_seekerApplications.php';
      case 'job_invite': return 'view_invitation.php' + (refId ? '?id=' + refId : '');
      case 'follow': case 'unfollow': return '';
      default: return 'antcareers_seekerDashboard.php';
    }
  }

  // ── Load threads from API ──
  function loadSeekerThreads() {
    var container = document.getElementById('msgThreadList');
    fetch('../api/messages.php?action=threads')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success || !data.threads || data.threads.length === 0) {
          container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;"><i class="fas fa-inbox"></i><div style="margin-top:8px;">No messages yet</div></div>';
          return;
        }
        var html = '';
        var colors = [
          'linear-gradient(135deg,#D13D2C,#7A1515)',
          'linear-gradient(135deg,#4A90D9,#2A6090)',
          'linear-gradient(135deg,#4CAF70,#2A7040)',
          'linear-gradient(135deg,#D4943A,#8A5A10)',
          'linear-gradient(135deg,#9C27B0,#5A0080)'
        ];
        _seekerThreadsCache = data.threads;
        data.threads.forEach(function (t, i) {
          var color = t.color || colors[i % colors.length];
          var unread = t.unread_count > 0 ? ' unread' : '';
          var unreadDot = t.unread_count > 0 ? '<div class="msg-unread-dot"></div>' : '';
          html += '<div class="msg-item' + unread + '" data-partner-id="' + t.partner_id + '" data-partner-name="' + _esc(t.name) + '">'
            + '<div class="msg-avatar" style="background:' + color + '">' + (t.avatar_url ? '<img src="../' + t.avatar_url + '" alt="">' : _esc(t.initials)) + '</div>'
            + '<div style="flex:1;min-width:0;">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">'
            + '<div class="msg-name">' + _esc(t.name) + '</div>'
            + '<div class="msg-time">' + _esc(t.time) + '</div>'
            + '</div>'
            + '<div class="msg-preview">' + (t.is_sent ? 'You: ' : '') + _esc(t.preview) + '</div>'
            + (t.job_title ? '<div class="msg-job-tag"><i class="fas fa-briefcase" style="font-size:9px;"></i> ' + _esc(t.job_title) + '</div>' : '')
            + '</div>'
            + unreadDot
            + '</div>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.msg-item').forEach(function (el) {
          el.addEventListener('click', function () {
            openSeekerConvo(parseInt(el.getAttribute('data-partner-id')), el.getAttribute('data-partner-name'));
          });
        });
      })
      .catch(function () {
        container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;">Failed to load messages</div>';
      });
  }

  function filterMsgPanelList(q) {
    var items = document.getElementById('msgThreadList').querySelectorAll('.msg-item');
    q = (q || '').toLowerCase();
    items.forEach(function(el) {
      var name = (el.getAttribute('data-partner-name') || '').toLowerCase();
      el.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  }

  // ── Open conversation inside panel ──
  function openSeekerConvo(partnerId, partnerName) {
    _seekerCurrentPartner = partnerId;
    // Find thread data for avatar/color/meta
    var t = (_seekerThreadsCache || []).find(function(x){ return x.partner_id == partnerId; });
    var color = (t && t.color) ? t.color : 'linear-gradient(135deg,#D13D2C,#7A1515)';
    var pParts = (partnerName || '?').trim().split(/\s+/);
    var ini = (t && t.initials)
      ? t.initials
      : (pParts.length >= 2
        ? (pParts[0][0] + pParts[1][0]).toUpperCase()
        : ((pParts[0] && pParts[0][0]) ? pParts[0][0].toUpperCase() : '?'));
    var avatarUrl = t && t.avatar_url ? t.avatar_url : null;
    // Set header
    var avatarEl = document.getElementById('msgConvoAvatar');
    avatarEl.style.background = color;
    if (avatarUrl) { avatarEl.innerHTML = '<img src="../' + avatarUrl + '" alt="">'; }
    else { avatarEl.textContent = ini; }
    document.getElementById('msgConvoName').textContent = partnerName || 'Conversation';
    document.getElementById('msgConvoMeta').textContent = (t && t.job_title) ? t.job_title : '';
    showConvoView();
    refreshFullMessageLinks();
    var body = document.getElementById('msgConvoBody');
    body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch('../api/messages.php?action=messages&user_id=' + partnerId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success) {
          var loadedName = (data.partner && data.partner.name) ? data.partner.name : (partnerName || 'Conversation');
          var loadedParts = loadedName.trim().split(/\s+/);
          var loadedIni = (t && t.initials)
            ? t.initials
            : (loadedParts.length >= 2
              ? (loadedParts[0][0] + loadedParts[1][0]).toUpperCase()
              : ((loadedParts[0] && loadedParts[0][0]) ? loadedParts[0][0].toUpperCase() : '?'));
          var loadedAvatar = (data.partner && data.partner.avatar_url) ? data.partner.avatar_url : avatarUrl;
          var loadedAvatarSrc = loadedAvatar
            ? ((loadedAvatar.indexOf('http') === 0 || loadedAvatar.indexOf('../') === 0) ? loadedAvatar : ('../' + loadedAvatar))
            : null;

          avatarEl.style.background = color;
          avatarEl.innerHTML = loadedAvatarSrc ? '<img src="' + loadedAvatarSrc + '" alt="">' : _esc(loadedIni);
          document.getElementById('msgConvoName').textContent = loadedName;
          document.getElementById('msgConvoMeta').textContent = (data.job && data.job.title) ? data.job.title : ((t && t.job_title) ? t.job_title : '');

          renderSeekerConvoMsgs(body, data, color, loadedIni, loadedAvatar);
        } else {
          renderSeekerConvoMsgs(body, data, color, ini, avatarUrl);
        }
        if (data.success) { markSeekerConversationRead(partnerId); updateSeekerBadges(); }
      })
      .catch(function () {
        body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">Failed to load conversation</div>';
      });
  }

  function renderSeekerConvoMsgs(body, data, color, ini, avatarUrl) {
    if (!data.success || !data.messages || !data.messages.length) {
      body.innerHTML = '<div style="text-align:center;padding:30px 20px;color:var(--text-muted);font-size:12px;"><i class="fas fa-comment-dots" style="font-size:24px;display:block;margin-bottom:8px;"></i>No messages yet. Say hello!</div>';
      return;
    }
    var html = '';
    data.messages.forEach(function (m) {
      if (m.show_date) html += '<div class="sp-msg-date"><span>' + _esc(m.date) + '</span></div>';
      if (m.from === 'me') {
        html += '<div class="sp-msg-row sent">'
          + '<div class="sb-bubble sb-bubble-sent">' + _esc(m.body) + '<div class="sb-bubble-time">' + _esc(m.time) + ' <i class="fas fa-check-double" style="font-size:8px;"></i></div></div>'
          + '</div>';
      } else {
        html += '<div class="sp-msg-row">'
            + '<div class="sp-chat-avatar" style="background:' + color + ';width:26px;height:26px;font-size:10px;">' + (avatarUrl ? '<img src="' + ((avatarUrl.indexOf('http') === 0 || avatarUrl.indexOf('../') === 0) ? avatarUrl : ('../' + avatarUrl)) + '" alt="">' : ini) + '</div>'
          + '<div class="sb-bubble sb-bubble-recv">' + _esc(m.body) + '<div class="sb-bubble-time">' + _esc(m.time) + '</div></div>'
          + '</div>';
      }
    });
    body.innerHTML = html;
    body.scrollTop = body.scrollHeight;
  }

  function markSeekerConversationRead(partnerId) {
    return fetch('../api/messages.php?action=mark_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ partner_id: partnerId })
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }

  // ── Send message from panel ──
  document.getElementById('msgConvoSend').addEventListener('click', seekerSendMsg);
  function seekerSendMsg() {
    var input = document.getElementById('msgConvoInput');
    var text = input.value.trim();
    if (!text || !_seekerCurrentPartner) return;
    input.value = '';
    input.style.height = 'auto';
    var body = document.getElementById('msgConvoBody');
    var row = document.createElement('div');
    row.className = 'sp-msg-row sent';
    row.innerHTML = '<div class="sb-bubble sb-bubble-sent">' + _esc(text) + '<div class="sb-bubble-time" id="_sp_sending">Sending\u2026</div></div>';
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
    fetch('../api/messages.php?action=send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receiver_id: _seekerCurrentPartner, message: text })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var timeEl = row.querySelector('#_sp_sending');
      if (data.success) {
        if (timeEl) timeEl.textContent = data.time || new Date().toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
        var t = (_seekerThreadsCache || []).find(function(x){ return x.partner_id == _seekerCurrentPartner; });
        var clr = (t && t.color) ? t.color : 'linear-gradient(135deg,#D13D2C,#7A1515)';
        var ini = (t && t.initials) ? t.initials : '?';
        var avu = t && t.avatar_url ? t.avatar_url : null;
        fetch('../api/messages.php?action=messages&user_id=' + _seekerCurrentPartner)
          .then(function(r2){ return r2.json(); })
          .then(function(d2){ renderSeekerConvoMsgs(body, d2, clr, ini, avu); });
      } else {
        row.style.opacity = '0.5';
        if (timeEl) timeEl.textContent = 'Failed';
      }
    })
    .catch(function () { row.style.opacity = '0.5'; });
  }

  // ── Back button ──
  document.getElementById('msgConvoBack').addEventListener('click', function () {
    showThreadView();
    loadSeekerThreads();
  });

  // ── Load notifications from API ──
  function loadNotifications() {
    var container = document.getElementById('notifList');
    fetch('../api/messages.php?action=notifications')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success || !data.notifications || data.notifications.length === 0) {
          container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;"><i class="fas fa-bell-slash"></i><div style="margin-top:8px;">No notifications</div></div>';
          return;
        }
        var html = '';
        data.notifications.forEach(function (n) {
          var dotClass = n.is_read ? 'read' : (n.type === 'message' ? 'red' : (n.type === 'application' ? 'green' : 'amber'));
          var href = getNotifUrl(n.type, n.reference_id);
          var personId = (n.type === 'follow' || n.type === 'unfollow') ? (n.actor_id || n.reference_id || '') : '';
          html += '<div class="notif-item" data-notif-id="' + n.id + '" data-href="' + _esc(href) + '" data-person-id="' + _esc(String(personId)) + '" style="cursor:pointer;">'
            + '<div class="n-dot ' + dotClass + '"></div>'
            + '<div><div class="n-text">' + _esc(n.content) + '</div><div class="n-time">' + _esc(n.time) + '</div></div>'
            + '</div>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.notif-item').forEach(function (el) {
          el.addEventListener('click', function () {
            var nid = el.getAttribute('data-notif-id');
            var href = el.getAttribute('data-href');
            var personId = el.getAttribute('data-person-id');
            fetch('../api/messages.php?action=mark_notif_read&id=' + nid, { method: 'POST' })
              .then(function () {
                el.querySelector('.n-dot').className = 'n-dot read';
                updateSeekerBadges();
                if (personId) {
                  closeNotif();
                  openGlobalPersonModal(personId);
                  return;
                }
                if (href) {
                  if (href.indexOf('seekerMessages.php?user_id=') !== -1 && window.location.pathname.indexOf('seekerMessages.php') !== -1) {
                    var uid = parseInt(href.split('user_id=')[1]);
                    if (uid > 0 && typeof openThread === 'function') {
                      closeNotif();
                      openThread(uid);
                      return;
                    }
                  }
                  window.location.href = href;
                }
              });
          });
        });
      })
      .catch(function () {
        container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;">Failed to load notifications</div>';
      });
  }

  // ── Badge updates ──
  function updateSeekerBadges() {
    fetch('../api/messages.php?action=unread_count')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        var mb = document.getElementById('seekerMsgBadge');
        var nb = document.getElementById('seekerNotifBadge');
        if (mb) { mb.textContent = data.messages || 0; mb.style.visibility = data.messages > 0 ? 'visible' : 'hidden'; mb.style.opacity = data.messages > 0 ? '1' : '0'; }
        if (nb) { nb.textContent = data.notifications || 0; nb.style.visibility = data.notifications > 0 ? 'visible' : 'hidden'; nb.style.opacity = data.notifications > 0 ? '1' : '0'; }
      })
      .catch(function () {});
  }
  window.updateSeekerBadges = updateSeekerBadges;
  updateSeekerBadges();
  setInterval(updateSeekerBadges, 30000);

  document.getElementById('msgClose').addEventListener('click', closeMsgPanel);
  document.getElementById('msgNewChat').addEventListener('click', function(e) {
    e.stopPropagation();
    var panel = document.getElementById('msgNewChatPanel');
    var searchInput = document.getElementById('msgNewChatSearch');
    var results = document.getElementById('msgNewChatResults');
    if (panel.style.display === 'none') {
      panel.style.display = '';
      results.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Type a name to search</div>';
      setTimeout(function() { searchInput.value = ''; searchInput.focus(); }, 50);
    } else {
      panel.style.display = 'none';
    }
  });

  var _newChatTimer = null;
  window.searchMsgPanelNewChat = function() {
    var q = document.getElementById('msgNewChatSearch').value.trim();
    var results = document.getElementById('msgNewChatResults');
    if (q.length < 2) { results.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Type at least 2 characters</div>'; return; }
    if (_newChatTimer) clearTimeout(_newChatTimer);
    _newChatTimer = setTimeout(function() {
      results.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
      fetch('../api/messages.php?action=search_users&q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (!data.success || !data.users || !data.users.length) { results.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">No users found</div>'; return; }
          var clrs = [
            'linear-gradient(135deg,#D13D2C,#7A1515)',
            'linear-gradient(135deg,#4A90D9,#2A6090)',
            'linear-gradient(135deg,#4CAF70,#2A7040)',
            'linear-gradient(135deg,#D4943A,#8A5A10)',
            'linear-gradient(135deg,#9C27B0,#5A0080)'
          ];
          results.innerHTML = data.users.map(function(u, i) {
            return '<div class="msg-panel-new-chat-user" onclick="msgPanelStartChat(' + u.id + ',_esc(' + JSON.stringify(u.name) + '))">' +
              '<div class="msg-panel-new-chat-user-av" style="background:' + clrs[i % clrs.length] + '">' + (u.avatar_url ? '<img src="../' + u.avatar_url + '" alt="">' : _esc(u.initials)) + '</div>' +
              '<div><div style="font-size:13px;font-weight:600;color:var(--text-light);">' + _esc(u.name) + '</div><div style="font-size:11px;color:var(--text-muted);text-transform:capitalize;">' + _esc(u.type) + '</div></div>' +
              '</div>';
          }).join('');
        })
        .catch(function() { results.innerHTML = '<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Search failed</div>'; });
    }, 300);
  };

  window.msgPanelStartChat = function(userId, userName) {
    document.getElementById('msgNewChatPanel').style.display = 'none';
    openSeekerConvo(userId, userName);
  };
  document.getElementById('msgToggle').addEventListener('click', function (e) {
    e.stopPropagation();
    msgPanel.classList.contains('open') ? closeMsgPanel() : openMsgPanel();
  });

  // Backward-compatible shared entrypoints for contextual actions on seeker pages.
  window.openMsgSidebar = function () { openMsgPanel(); };
  window.openNotifSidebar = function () { openNotif(); };

  if (sidePanelOverlay) {
    sidePanelOverlay.addEventListener('click', function () {
      closeNotif();
      closeMsgPanel();
    });
  }

  // Allow any seeker page to open sidebar conversation without redirect.
  window.addEventListener('seeker:openMessageSidebar', function (evt) {
    var partnerId = Number(evt && evt.detail && evt.detail.userId ? evt.detail.userId : 0);
    var partnerName = evt && evt.detail && evt.detail.userName ? String(evt.detail.userName) : 'Conversation';
    openMsgPanel();
    if (partnerId > 0) {
      openSeekerConvo(partnerId, partnerName);
    }
  });

  refreshFullMessageLinks();

  // ── CLICK-OUTSIDE: close dropdowns / menus / panels ───────────────────────
  document.addEventListener('click', function (e) {
    // Mobile menu
    if (!mobileMenu.contains(e.target) && !hamburger.contains(e.target)) {
      mobileMenu.classList.remove('open');
      hamburger.setAttribute('aria-expanded', 'false');
      mobileMenu.setAttribute('aria-hidden', 'true');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
    // Profile dropdown
    if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
      profileDropdown.classList.remove('open');
      profileToggle.setAttribute('aria-expanded', 'false');
    }
    // Notif panel
    const triggers = document.querySelectorAll('[data-notif-trigger]');
    const clickedTrigger = Array.from(triggers).some(t => t.contains(e.target));
    if (!notifPanel.contains(e.target) && !clickedTrigger) {
      closeNotif();
    }
    // Msg panel
    const msgTriggers = document.querySelectorAll('[data-msg-trigger]');
    const clickedMsgTrigger = Array.from(msgTriggers).some(t => t.contains(e.target));
    if (!msgPanel.contains(e.target) && !clickedMsgTrigger) {
      closeMsgPanel();
    }
  });

  // ── TOAST — handled by includes/toast.php ──

})();
</script>
<?php require_once __DIR__ . '/toast.php'; ?>

