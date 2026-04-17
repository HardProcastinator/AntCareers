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

<!-- Notification side panel (employer) -->
<div class="notif-panel" id="notifPanel" aria-hidden="true">
  <div class="notif-panel-head">
    <div class="notif-panel-title"><i class="fas fa-bell"></i> Notifications</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="notif-close" id="notifClearAll" title="Clear all"><i class="fas fa-trash-alt"></i></button>
      <button class="notif-close" id="notifMarkAll" title="Mark all read"><i class="fas fa-check-double"></i></button>
      <button class="notif-close" id="notifClose"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div class="notif-panel-body" id="notifList">
    <!-- notifications loaded via JS -->
  </div>
</div>

<!-- Light mode overrides for employer navbar -->
<style>
  /* Notifications panel */
  .notif-panel{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:100vw;background:var(--soil-card,#1A1110);border-left:1px solid var(--soil-line,#3A2A28);z-index:500;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,0.4)}
  .notif-panel.open{transform:translateX(0)}
  .notif-panel-head{padding:20px 20px 16px;border-bottom:1px solid var(--soil-line,#3A2A28);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .notif-panel-title{font-family:var(--font-display,sans-serif);font-size:17px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:8px}
  .notif-panel-title i{color:var(--red-bright,#E04A3A)}
  .notif-close{width:28px;height:28px;border-radius:6px;background:var(--soil-hover,#2A1E1C);border:1px solid var(--soil-line,#3A2A28);color:var(--text-muted,#8A7572);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:.15s}
  .notif-close:hover{color:#F5F0EE}
  .notif-panel-body{flex:1;overflow-y:auto;padding:12px 16px}
  .notif-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--soil-line,#3A2A28);cursor:pointer}
  .notif-item:last-child{border-bottom:none}
  .n-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px}
  .n-dot.red{background:var(--red-vivid,#D13D2C)}.n-dot.amber{background:var(--amber,#D4943A)}.n-dot.green{background:#4CAF70}.n-dot.read{background:var(--soil-line,#3A2A28)}
  .n-text{font-size:13px;color:var(--text-mid,#B5A09C);line-height:1.55}
  .n-time{font-size:11px;color:var(--text-muted,#8A7572);margin-top:3px;font-weight:600}
  @media(max-width:760px){.notif-panel{width:100%;max-width:100%}}

  body.light .notif-panel{background:#FFFFFF;border-color:#E0CECA;box-shadow:-8px 0 32px rgba(0,0,0,0.1)}
  body.light .notif-panel-title{color:#1A0A09}
  body.light .notif-item{border-color:#E0CECA}
  body.light .n-text{color:#3A2020}
  body.light .n-time{color:#7A5555}
  body.light .notif-close{background:#F0E4E2;border-color:#E0CECA;color:#7A5555}
  body.light .n-dot.read{background:#E0CECA}

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
  /* Mobile menu */
  .mobile-menu{display:none;position:fixed;top:64px;left:0;right:0;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--soil-line);padding:12px 20px 16px;z-index:190;flex-direction:column;gap:2px;}
  .mobile-menu.open{display:flex;}
  .mobile-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:7px;font-size:14px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:0.15s;font-family:var(--font-body);text-decoration:none;}
  .mobile-link i{color:var(--red-mid);width:16px;text-align:center;}
  .mobile-link:hover{background:var(--soil-hover);color:#F5F0EE;}
  .mobile-divider{height:1px;background:var(--soil-line);margin:6px 0;}
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
    // Close notification panel on outside click
    var notifPanel = document.getElementById('notifPanel');
    if (notifPanel && notifPanel.classList.contains('open') && !notifPanel.contains(e.target)) {
      var notifBtn = document.getElementById('navNotifBtn');
      if (!notifBtn || !notifBtn.contains(e.target)) closeNotif();
    }
  });

  // ── NOTIFICATIONS ──
  var notifPanel = document.getElementById('notifPanel');

  function _esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function getEmpNotifUrl(type, refId) {
    switch (type) {
      case 'message': return 'employer_messages.php' + (refId ? '?user_id=' + refId : '');
      case 'new_application': case 'application': case 'offer_response': return 'employer_applicants.php';
      case 'offer': return 'employer_applicants.php';
      case 'interview_invite': return 'employer_applicants.php';
      case 'follow': case 'unfollow': return 'employer_dashboard.php';
      case 'recruiter_added': return 'employer_manageRecruiters.php';
      default: return 'employer_dashboard.php';
    }
  }

  window.openNotifSidebar = window.openNotif = function() {
    if (typeof closeMsgSidebar === 'function') closeMsgSidebar();
    notifPanel.classList.add('open');
    notifPanel.setAttribute('aria-hidden', 'false');
    loadEmpNotifications();
  };
  window.closeNotif = function() {
    notifPanel.classList.remove('open');
    notifPanel.setAttribute('aria-hidden', 'true');
  };

  document.getElementById('notifClose').addEventListener('click', closeNotif);
  document.getElementById('navNotifBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    notifPanel.classList.contains('open') ? closeNotif() : openNotif();
  });

  // Mark all read
  document.getElementById('notifMarkAll').addEventListener('click', function() {
    fetch('../api/messages.php?action=mark_notif_read', { method: 'POST' })
      .then(function() { loadEmpNotifications(); updateEmpBadges(); });
  });

  // Clear all
  document.getElementById('notifClearAll').addEventListener('click', function() {
    fetch('../api/messages.php?action=clear_notifications', { method: 'POST' })
      .then(function() { loadEmpNotifications(); updateEmpBadges(); });
  });

  function loadEmpNotifications() {
    var container = document.getElementById('notifList');
    fetch('../api/messages.php?action=notifications')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success || !data.notifications || !data.notifications.length) {
          container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;"><i class="fas fa-bell-slash"></i><div style="margin-top:8px;">No notifications</div></div>';
          return;
        }
        var html = '';
        data.notifications.forEach(function(n) {
          var dotClass = n.is_read ? 'read' : (n.type === 'message' ? 'red' : (n.type === 'new_application' ? 'green' : 'amber'));
          var href = getEmpNotifUrl(n.type, n.reference_id);
          html += '<div class="notif-item" data-notif-id="' + n.id + '" data-href="' + _esc(href) + '">'
            + '<div class="n-dot ' + dotClass + '"></div>'
            + '<div><div class="n-text">' + _esc(n.content) + '</div><div class="n-time">' + _esc(n.time) + '</div></div></div>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.notif-item').forEach(function(el) {
          el.addEventListener('click', function() {
            var nid = el.getAttribute('data-notif-id');
            var href = el.getAttribute('data-href');
            fetch('../api/messages.php?action=mark_notif_read&id=' + nid, { method: 'POST' })
              .then(function() {
                el.querySelector('.n-dot').className = 'n-dot read';
                updateEmpBadges();
                if (href) {
                  if (href.indexOf('employer_messages.php?user_id=') !== -1 && window.location.pathname.indexOf('employer_messages.php') !== -1) {
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
      .catch(function() {
        container.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;">Failed to load notifications</div>';
      });
  }

  function updateEmpBadges() {
    fetch('../api/messages.php?action=unread_count')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.success) return;
        var mb = document.querySelector('.msg-badge-count');
        var nb = document.querySelector('.notif-badge-count');
        if (mb) { mb.textContent = data.messages || 0; mb.style.display = data.messages > 0 ? '' : 'none'; }
        if (nb) { nb.textContent = data.notifications || 0; nb.style.display = data.notifications > 0 ? '' : 'none'; }
      }).catch(function() {});
  }
  window.updateEmpBadges = updateEmpBadges;
  updateEmpBadges();
  setInterval(updateEmpBadges, 30000);
})();
</script>
<?php require_once __DIR__ . '/toast.php'; ?>

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



