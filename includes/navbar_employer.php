<?php
declare(strict_types=1);
/**
 * AntCareers — Reusable Employer Navbar
 * includes/navbar_employer.php
 *
 * Usage: require_once dirname(__DIR__) . '/includes/navbar_employer.php';
 *
 * Required variables (set before including):
 *   $fullName    — string
 *   $initials    — string
 *   $companyName — string
 *   $navActive   — string: 'dashboard'|'browse'|'applicants'|'analytics'|'messages'|'profile'|'manage-jobs'
 */

// Validate required vars; provide safe fallbacks
$fullName    = $fullName    ?? 'Employer';
$initials    = $initials    ?? 'EM';
$companyName = $companyName ?? 'Your Company';
$navActive   = $navActive   ?? '';
$avatarUrl   = $avatarUrl   ?? '';
$navbarShowMessage    = $navbarShowMessage    ?? true;
$navbarShowNotif      = $navbarShowNotif      ?? true;
$navbarShowPostJob    = $navbarShowPostJob    ?? true;
$navbarShowHamburger  = $navbarShowHamburger  ?? true;
$navbarShowMobileMenu = $navbarShowMobileMenu ?? true;

// Helper: theme-aware onclick redirect
function navHref(string $page): string {
    return "window.location.href='" . $page . "?theme='+(document.body.classList.contains('light')?'light':'dark')";
}
?>
<!-- ══════════════════════════════════════════════════════════════════
     EMPLOYER NAVBAR  ·  includes/navbar_employer.php
     ══════════════════════════════════════════════════════════════════ -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="employer_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>

    <div class="nav-links">
      <a class="nav-link <?= $navActive==='dashboard'?'active':'' ?>"
         href="employer_dashboard.php">
        <i class="fas fa-th-large"></i> Dashboard
      </a>
      <a class="nav-link <?= $navActive==='browse'?'active':'' ?>"
         onclick="<?= navHref('../employer/employer_browseJobs.php') ?>">
        <i class="fas fa-search"></i> Browse Jobs
      </a>
      <a class="nav-link <?= $navActive==='manage-jobs'?'active':'' ?>"
         onclick="<?= navHref('../employer/employer_manageJobs.php') ?>">
        <i class="fas fa-briefcase"></i> Manage Jobs
      </a>
      <a class="nav-link <?= $navActive==='applicants'?'active':'' ?>"
         onclick="<?= navHref('../employer/employer_applicants.php') ?>">
        <i class="fas fa-users"></i> Applicants
      </a>
      <a class="nav-link <?= $navActive==='recruiters'?'active':'' ?>"
         onclick="<?= navHref('../employer/employer_manageRecruiters.php') ?>">
        <i class="fas fa-user-tie"></i> Recruiters
      </a>
      <a class="nav-link <?= $navActive==='analytics'?'active':'' ?>"
         href="employer_analytics.php">
        <i class="fas fa-chart-bar"></i> Analytics
      </a>
    </div><!-- /nav-links -->

    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-sun"></i></button>

      <?php if ($navbarShowMessage): ?>
      <button class="notif-btn-nav" id="navMsgBtn"
              onclick="if(typeof openMsgSidebar==='function'){openMsgSidebar();}else{this.classList.toggle('active');}">
        <i class="fas fa-envelope"></i>
        <span class="badge msg-badge-count" style="display:none">0</span>
      </button>
      <?php endif; ?>

      <?php if ($navbarShowNotif): ?>
      <button class="notif-btn-nav" id="navNotifBtn"
              onclick="if(typeof openNotifSidebar==='function'){openNotifSidebar();}else{this.classList.toggle('active');}">
        <i class="fas fa-bell"></i>
        <span class="badge notif-badge-count" style="display:none">0</span>
      </button>
      <?php endif; ?>



      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle">
          <div class="profile-avatar"><?php if (!empty($avatarUrl)): ?><img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: ?><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div>
          <div>
            <div class="profile-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
<?php
$_roleLabel = 'Company Admin';
if (isset($_SESSION['account_type']) && strtolower($_SESSION['account_type']) === 'recruiter') {
    $_roleLabel = 'Recruiter';
}
?>
            <div class="profile-role"><?= htmlspecialchars($_roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pdh-sub"><?= htmlspecialchars($_roleLabel, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_companyProfile.php') ?>">
            <i class="fas fa-building"></i> Company Profile
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_manageJobs.php') ?>">
            <i class="fas fa-briefcase"></i> Manage Jobs
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_settings.php') ?>">
            <i class="fas fa-cog"></i> Settings
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_messages.php') ?>">
            <i class="fas fa-comments"></i> Messages
          </div>
          <div class="pd-divider"></div>
          <div class="pd-item danger" onclick="window.location.href='../auth/logout.php'">
            <i class="fas fa-sign-out-alt"></i> Sign out
          </div>
        </div><!-- /profile-dropdown -->
      </div><!-- /profile-wrap -->

      <?php if ($navbarShowHamburger): ?>
      <button class="theme-btn hamburger" id="hamburger">
        <i class="fas fa-bars"></i>
      </button>
      <?php endif; ?>
    </div><!-- /nav-right -->
  </div><!-- /nav-inner -->
</nav>

<!-- Light mode overrides for employer navbar -->
<style>
  body.light .navbar { background:rgba(249,245,244,0.97); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
  body.light .logo-text { color:#1A0A09; }
  body.light .logo-text span { color:var(--red-vivid); }
  body.light .nav-link { color:#5A4040; }
  body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
  body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
  body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
  body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
  body.light .profile-name { color:#1A0A09; }
  body.light .profile-role { color:var(--amber); }
  body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; color:#5A4040; }
  body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 20px 40px rgba(0,0,0,0.12); }
  body.light .profile-dropdown-head { border-color:#E0CECA; }
  body.light .pdh-name { color:#1A0A09; }
  body.light .pd-item { color:#4A2828; }
  body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
  body.light .pd-item i { color:#7A5555; }
  body.light .pd-item:hover i { color:var(--red-bright); }
  body.light .pd-divider { background:#E0CECA; }
  body.light .mobile-menu { background:rgba(249,245,244,0.97); border-color:#E0CECA; }
  body.light .mobile-link { color:#4A2828; }
  body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }
  body.light .mobile-divider { background:#E0CECA; }
  body.light .btn-nav-red { box-shadow:0 2px 8px rgba(209,61,44,0.2); }
  body.light .glow-orb { display:none; }
</style>

<!-- Employer shared theme + interactions script -->
<script>
(function(){
  'use strict';

  // ── THEME ──
  function applyTheme(t) {
    var isLight = t === 'light';
    document.body.classList.toggle('light', isLight);
    document.body.classList.toggle('dark', !isLight);
    document.documentElement.classList.toggle('theme-light', isLight);
    var icon = document.querySelector('#themeToggle i');
    if (icon) icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('ac-theme', t);
  }
  var paramTheme = new URLSearchParams(window.location.search).get('theme');
  var storedTheme = localStorage.getItem('ac-theme') || 'light';
  var initTheme = paramTheme || storedTheme;
  if (paramTheme) localStorage.setItem('ac-theme', paramTheme);
  applyTheme(initTheme);

  // Expose globally so page scripts (e.g. settings selectAppearance) can call it
  window.applyTheme = applyTheme;
  window.setTheme = applyTheme;

  var themeBtn = document.getElementById('themeToggle');
  if (themeBtn) themeBtn.addEventListener('click', function() {
    applyTheme(document.body.classList.contains('light') ? 'dark' : 'light');
  });

  // ── HAMBURGER ──
  var hamburger = document.getElementById('hamburger');
  var mobileMenu = document.getElementById('mobileMenu');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', function(e) {
      e.stopPropagation();
      var open = mobileMenu.classList.toggle('open');
      hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
    });
  }

  // ── PROFILE DROPDOWN ──
  var profileToggle = document.getElementById('profileToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  var profileWrap = document.getElementById('profileWrap');
  if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      profileDropdown.classList.toggle('open');
    });
  }
  document.addEventListener('click', function(e) {
    if (profileWrap && !profileWrap.contains(e.target) && profileDropdown)
      profileDropdown.classList.remove('open');
    if (mobileMenu && hamburger && !mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
  });
})();
</script>

<!-- Mobile Menu -->
<?php if ($navbarShowMobileMenu): ?>
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link" href="employer_dashboard.php">
    <i class="fas fa-th-large"></i> Dashboard
  </a>
  <a class="mobile-link" onclick="<?= navHref('../employer/employer_browseJobs.php') ?>">
    <i class="fas fa-search"></i> Browse Jobs
  </a>
  <a class="mobile-link" onclick="<?= navHref('../employer/employer_manageJobs.php') ?>">
    <i class="fas fa-briefcase"></i> Manage Jobs
  </a>
  <a class="mobile-link" onclick="<?= navHref('../employer/employer_applicants.php') ?>">
    <i class="fas fa-users"></i> Applicants
  </a>
  <a class="mobile-link" href="employer_manageRecruiters.php">
    <i class="fas fa-user-tie"></i> Manage Recruiters
  </a>
  <a class="mobile-link" href="employer_analytics.php">
    <i class="fas fa-chart-bar"></i> Analytics
  </a>
  <a class="mobile-link" onclick="<?= navHref('../employer/employer_messages.php') ?>">
    <i class="fas fa-envelope"></i> Messages
  </a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="employer_companyProfile.php">
    <i class="fas fa-building"></i> Company Profile
  </a>
  <a class="mobile-link" href="employer_settings.php">
    <i class="fas fa-cog"></i> Settings
  </a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" onclick="window.location.href='../auth/logout.php'">
    <i class="fas fa-sign-out-alt"></i> Sign out
  </a>
</div><!-- /mobile-menu -->
<?php endif; ?>



