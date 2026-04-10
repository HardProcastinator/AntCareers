<?php
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
      <a class="nav-link <?= $navActive==='analytics'?'active':'' ?>"
         href="employer_analytics.php">
        <i class="fas fa-chart-bar"></i> Analytics
      </a>
    </div><!-- /nav-links -->

    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-sun"></i></button>

      <?php if ($navbarShowMessage): ?>
      <button class="notif-btn-nav" id="navMsgBtn"
              onclick="<?= navHref('../employer/employer_messages.php') ?>">
        <i class="fas fa-envelope"></i>
        <span class="badge msg-badge-count">0</span>
      </button>
      <?php endif; ?>

      <?php if ($navbarShowNotif): ?>
      <button class="notif-btn-nav" id="navNotifBtn"
              onclick="if(typeof openNotifSidebar==='function'){openNotifSidebar();}else{this.classList.toggle('active');}">
        <i class="fas fa-bell"></i>
        <span class="badge notif-badge-count">0</span>
      </button>
      <?php endif; ?>

      <?php if ($navbarShowPostJob): ?>
      <a class="btn-nav-red" style="cursor:pointer;" href="employer_manageJobs.php?postjob=1">
        <i class="fas fa-plus-circle"></i> Post Job
      </a>
      <?php endif; ?>

      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle">
          <div class="profile-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
          <div>
            <div class="profile-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-role">Company Admin</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pdh-sub">Company Admin · <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_companyProfile.php') ?>">
            <i class="fas fa-building"></i> Company Profile
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_manageJobs.php') ?>">
            <i class="fas fa-briefcase"></i> Manage Jobs
          </div>
          <div class="pd-item" onclick="<?= navHref('../employer/employer_manageRecruiters.php') ?>">
            <i class="fas fa-user-tie"></i> Manage Recruiters
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
  <a class="mobile-link" href="employer_analytics.php">
    <i class="fas fa-chart-bar"></i> Analytics
  </a>
  <a class="mobile-link" href="employer_messages.php">
    <i class="fas fa-envelope"></i> Messages
  </a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="employer_companyProfile.php">
    <i class="fas fa-building"></i> Company Profile
  </a>
  <a class="mobile-link" href="employer_manageRecruiters.php">
    <i class="fas fa-user-tie"></i> Manage Recruiters
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


