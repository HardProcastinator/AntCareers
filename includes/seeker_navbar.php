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
    'messages'     => '../messages.php',
    'settings'     => 'antcareers_seekerSettings.php',
];

// Build href with theme param forwarded
function _navHref(string $file): string {
    $theme = htmlspecialchars($_GET['theme'] ?? '', ENT_QUOTES, 'UTF-8');
    return htmlspecialchars($file . ($theme !== '' ? '?theme=' . $theme : ''), ENT_QUOTES, 'UTF-8');
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
  .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
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
  .msg-panel { position:fixed; top:64px; right:0; bottom:0; width:360px; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:150; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
  .msg-panel.open { transform:translateX(0); }
  .msg-panel-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
  .msg-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
  .msg-panel-title i { color:var(--red-bright); }
  .msg-panel-body { flex:1; overflow-y:auto; padding:12px 16px; }
  .msg-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; }
  .msg-item:last-child { border-bottom:none; }
  .msg-item:hover { opacity:0.85; }
  .msg-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; }
  .msg-name { font-size:13px; font-weight:700; color:#F5F0EE; margin-bottom:2px; }
  .msg-preview { font-size:12px; color:var(--text-muted); line-height:1.4; display:-webkit-box; line-clamp:2; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .msg-time { font-size:10px; color:var(--text-muted); font-weight:600; margin-left:auto; flex-shrink:0; white-space:nowrap; }
  body.light .msg-panel { background:#FFFFFF; border-color:#E0CECA; }
  body.light .msg-panel-title { color:#1A0A09; }
  body.light .msg-item { border-color:#E0CECA; }
  body.light .msg-name { color:#1A0A09; }

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
  .n-dot.red   { background:var(--red-vivid); }
  .n-dot.amber { background:var(--amber); }
  .n-dot.green { background:#4CAF70; }
  .n-dot.read  { background:var(--soil-line); }
  .n-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
  .n-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }

  /* Toast */
  .toast { position:fixed; bottom:24px; right:24px; z-index:999; background:var(--soil-card); border:1px solid var(--soil-line); border-left:2px solid var(--red-vivid); border-radius:8px; padding:11px 18px; font-size:13px; font-weight:500; color:#F5F0EE; box-shadow:0 10px 30px rgba(0,0,0,0.4); display:flex; align-items:center; gap:9px; animation:toastIn 0.25s ease; }
  @keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
  .toast i { color:var(--red-pale); }

  /* Light theme overrides for navbar elements */
  body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
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
  body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
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

  /* In-panel conversation bubbles */
  .sp-msg-bubble { max-width:82%; padding:8px 12px; border-radius:12px; font-size:13px; line-height:1.5; word-break:break-word; }
  .sp-msg-bubble.me { align-self:flex-end; background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
  .sp-msg-bubble.them { align-self:flex-start; background:var(--soil-hover); color:var(--text-mid); border-bottom-left-radius:4px; }
  .sp-msg-time-label { font-size:10px; color:var(--text-muted); text-align:center; padding:6px 0; }
  body.light .sp-msg-bubble.them { background:#F0E4E2; color:#3A2020; }

  /* Unread dot on thread item */
  .msg-item .msg-unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); flex-shrink:0; margin-top:4px; }

  @media(max-width:760px) {
    .nav-links { display:none; }
    .hamburger { display:flex; }
    .profile-name, .profile-role-lbl { display:none; }
    .profile-btn { padding:6px 8px; }
    .notif-panel { width:100%; max-width:100%; }
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
        <span class="badge" id="seekerMsgBadge" style="display:none">0</span>
      </button>

      <button class="notif-btn-nav" id="notifToggle" data-notif-trigger title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="badge" id="seekerNotifBadge" style="display:none">0</span>
      </button>

      <!-- Profile dropdown: account actions only -->
      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle" type="button" aria-haspopup="true" aria-expanded="false">
          <div class="profile-avatar"><?= htmlspecialchars($user['initials'], ENT_QUOTES, 'UTF-8') ?></div>
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
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<!-- Messages slide-in panel -->
<div class="msg-panel" id="msgPanel" aria-hidden="true">
  <!-- Thread list view (default) -->
  <div id="msgThreadView">
    <div class="msg-panel-head">
      <div class="msg-panel-title"><i class="fas fa-envelope"></i> Messages</div>
      <div style="display:flex;gap:6px;align-items:center;">
        <a class="notif-close" href="<?= _navHref($navRoutes['messages']) ?>" title="Open full page" style="text-decoration:none"><i class="fas fa-expand"></i></a>
        <button class="notif-close" id="msgClose" aria-label="Close messages"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="msg-panel-body" id="msgThreadList">
      <div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;" id="msgThreadLoading">
        <i class="fas fa-spinner fa-spin"></i> Loading messages…
      </div>
    </div>
  </div>
  <!-- Conversation view (shown when a thread is clicked) -->
  <div id="msgConvoView" style="display:none;flex-direction:column;height:100%;">
    <div class="msg-panel-head">
      <div style="display:flex;align-items:center;gap:8px;">
        <button class="notif-close" id="msgConvoBack" aria-label="Back to threads"><i class="fas fa-arrow-left"></i></button>
        <div class="msg-panel-title" id="msgConvoTitle">Conversation</div>
      </div>
      <button class="notif-close" onclick="closeMsgPanel()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="msg-panel-body" id="msgConvoBody" style="flex:1;overflow-y:auto;padding:12px 16px;display:flex;flex-direction:column;gap:6px;"></div>
    <div style="padding:10px 12px;border-top:1px solid var(--soil-line);display:flex;gap:8px;flex-shrink:0;">
      <input type="text" id="msgConvoInput" placeholder="Type a message…" style="flex:1;padding:8px 12px;border-radius:6px;border:1px solid var(--soil-line);background:var(--soil-hover);color:var(--text-mid);font-size:13px;outline:none;">
      <button id="msgConvoSend" style="padding:8px 14px;border-radius:6px;background:var(--red-vivid);color:#fff;border:none;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-paper-plane"></i></button>
    </div>
  </div>
</div>

<!-- Notifications slide-in panel -->
<div class="notif-panel" id="notifPanel" aria-hidden="true">
  <div class="notif-panel-head">
    <div class="notif-panel-title"><i class="fas fa-bell"></i> Notifications</div>
    <div style="display:flex;gap:6px;align-items:center;">
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

  hamburger.addEventListener('click', function (e) {
    e.stopPropagation();
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

  window.openNotif  = function () { closeMsgPanel(); notifPanel.classList.add('open'); notifPanel.setAttribute('aria-hidden', 'false'); loadNotifications(); };
  window.closeNotif = function () { notifPanel.classList.remove('open'); notifPanel.setAttribute('aria-hidden', 'true'); };

  document.getElementById('notifClose').addEventListener('click', closeNotif);
  document.getElementById('notifToggle').addEventListener('click', function (e) {
    e.stopPropagation();
    // Use real sidebar if chat system is loaded, else fallback to demo panel
    if (typeof openNotifSidebar === 'function') {
      openNotifSidebar();
    } else {
      notifPanel.classList.contains('open') ? closeNotif() : openNotif();
    }
  });

  // Mark all notifications read
  document.getElementById('notifMarkAll').addEventListener('click', function () {
    fetch('../api/messages.php?action=mark_notif_read', { method: 'POST' })
      .then(function () { loadNotifications(); updateSeekerBadges(); });
  });

  // ── MESSAGES PANEL ─────────────────────────────────────────────────────────
  const msgPanel = document.getElementById('msgPanel');
  const msgThreadView = document.getElementById('msgThreadView');
  const msgConvoView  = document.getElementById('msgConvoView');
  var _seekerCurrentPartner = null;

  window.openMsgPanel  = function () {
    closeNotif();
    msgPanel.classList.add('open');
    msgPanel.setAttribute('aria-hidden', 'false');
    showThreadView();
    loadSeekerThreads();
  };
  window.closeMsgPanel = function () {
    msgPanel.classList.remove('open');
    msgPanel.setAttribute('aria-hidden', 'true');
  };

  function showThreadView() {
    msgThreadView.style.display = '';
    msgConvoView.style.display = 'none';
    _seekerCurrentPartner = null;
  }
  function showConvoView() {
    msgThreadView.style.display = 'none';
    msgConvoView.style.display = 'flex';
  }

  function _esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

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
        var colors = ['#4A90D9','#9B59B6','#27AE60','#E74C3C','#D4943A','#3498DB','#E67E22','#1ABC9C'];
        data.threads.forEach(function (t, i) {
          var color = t.color || colors[i % colors.length];
          var unread = t.unread_count > 0 ? '<div class="msg-unread-dot"></div>' : '';
          html += '<div class="msg-item" data-partner-id="' + t.partner_id + '" data-partner-name="' + _esc(t.name) + '">'
            + '<div class="msg-avatar" style="background:linear-gradient(135deg,' + color + ',' + color + '88)">' + _esc(t.initials) + '</div>'
            + '<div style="flex:1;min-width:0;"><div class="msg-name">' + _esc(t.name) + '</div><div class="msg-preview">' + _esc(t.preview) + '</div></div>'
            + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;"><div class="msg-time">' + _esc(t.time) + '</div>' + unread + '</div>'
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

  // ── Open conversation inside panel ──
  function openSeekerConvo(partnerId, partnerName) {
    _seekerCurrentPartner = partnerId;
    document.getElementById('msgConvoTitle').textContent = partnerName || 'Conversation';
    showConvoView();
    var body = document.getElementById('msgConvoBody');
    body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch('../api/messages.php?action=messages&user_id=' + partnerId)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success || !data.messages || data.messages.length === 0) {
          body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">No messages yet. Say hello!</div>';
          return;
        }
        var html = '';
        data.messages.forEach(function (m) {
          if (m.show_date) html += '<div class="sp-msg-time-label">' + _esc(m.date) + '</div>';
          html += '<div class="sp-msg-bubble ' + (m.from === 'me' ? 'me' : 'them') + '">' + _esc(m.body) + '<div style="font-size:10px;opacity:0.7;margin-top:2px;">' + _esc(m.time) + '</div></div>';
        });
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
        updateSeekerBadges();
      })
      .catch(function () {
        body.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">Failed to load conversation</div>';
      });
  }

  // ── Send message from panel ──
  document.getElementById('msgConvoSend').addEventListener('click', seekerSendMsg);
  document.getElementById('msgConvoInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); seekerSendMsg(); }
  });
  function seekerSendMsg() {
    var input = document.getElementById('msgConvoInput');
    var text = input.value.trim();
    if (!text || !_seekerCurrentPartner) return;
    input.value = '';
    var body = document.getElementById('msgConvoBody');
    var bubble = document.createElement('div');
    bubble.className = 'sp-msg-bubble me';
    bubble.innerHTML = _esc(text) + '<div style="font-size:10px;opacity:0.7;margin-top:2px;">Sending\u2026</div>';
    body.appendChild(bubble);
    body.scrollTop = body.scrollHeight;
    fetch('../api/messages.php?action=send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receiver_id: _seekerCurrentPartner, message: text })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) { bubble.querySelector('div').textContent = data.time || 'Now'; }
      else { bubble.style.opacity = '0.5'; bubble.querySelector('div').textContent = 'Failed'; }
    })
    .catch(function () { bubble.style.opacity = '0.5'; bubble.querySelector('div').textContent = 'Failed'; });
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
          html += '<div class="notif-item" data-notif-id="' + n.id + '" style="cursor:pointer;">'
            + '<div class="n-dot ' + dotClass + '"></div>'
            + '<div><div class="n-text">' + n.content + '</div><div class="n-time">' + _esc(n.time) + '</div></div>'
            + '</div>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.notif-item').forEach(function (el) {
          el.addEventListener('click', function () {
            var nid = el.getAttribute('data-notif-id');
            fetch('../api/messages.php?action=mark_notif_read&id=' + nid, { method: 'POST' })
              .then(function () { el.querySelector('.n-dot').className = 'n-dot read'; updateSeekerBadges(); });
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
        if (mb) { mb.textContent = data.messages || 0; mb.style.display = data.messages > 0 ? '' : 'none'; }
        if (nb) { nb.textContent = data.notifications || 0; nb.style.display = data.notifications > 0 ? '' : 'none'; }
      })
      .catch(function () {});
  }
  updateSeekerBadges();
  setInterval(updateSeekerBadges, 30000);

  document.getElementById('msgClose').addEventListener('click', closeMsgPanel);
  document.getElementById('msgToggle').addEventListener('click', function (e) {
    e.stopPropagation();
    // Use real sidebar if chat system is loaded, else fallback to demo panel
    if (typeof openMsgSidebar === 'function') {
      openMsgSidebar();
    } else {
      msgPanel.classList.contains('open') ? closeMsgPanel() : openMsgPanel();
    }
  });

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

  // ── TOAST ──────────────────────────────────────────────────────────────────
  window.showToast = function (msg, icon) {
    icon = icon || 'fa-info-circle';
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = '<i class="fas ' + icon + '"></i> ' + msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 2600);
  };

})();
</script>