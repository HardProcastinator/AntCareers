<?php
declare(strict_types=1);
/**
 * AntCareers — Reusable Recruiter Navbar
 * includes/navbar_recruiter.php
 *
 * Nav links: Dashboard, People Search, My Jobs, Applicants
 * Icons: Messages (side panel), Notifications (side panel)
 * Profile dropdown: My Profile, Company Profile, Messages, Settings, Sign Out
 *
 * Required variables (set before including):
 *   $fullName, $initials, $companyName, $navActive, $avatarUrl (optional)
 */

$fullName    = $fullName    ?? 'Recruiter';
$initials    = $initials    ?? 'RC';
$companyName = $companyName ?? 'Your Company';
$navActive   = $navActive   ?? '';
$avatarUrl   = $avatarUrl   ?? '';

$recNavRoutes = [
    'dashboard'    => 'recruiter_dashboard.php',
    'people'       => 'recruiter_peopleSearch.php',
    'my-jobs'      => 'recruiter_jobs.php',
    'applicants'   => 'recruiter_applicants.php',
    'messages'     => 'recruiter_messages.php',
    'profile'      => 'recruiter_profile.php',
    'company'      => 'recruiter_company.php',
    'settings'     => 'recruiter_settings.php',
];

function _recNavHref(string $file): string {
    return htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
}
function _recActive(string $key, string $active): string {
    return $key === $active ? ' active' : '';
}
?>

<!-- ═══════════════════════════════════════════════════════
     RECRUITER NAVBAR — Shared across all recruiter pages
     ═══════════════════════════════════════════════════════ -->
<style>
  .navbar{position:sticky;top:0;z-index:200;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid rgba(209,61,44,0.35);box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5)}
  .nav-inner{max-width:1380px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:64px;gap:0;min-width:0}
  .logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:28px;flex-shrink:0}
  .logo-icon{width:34px;height:34px;background:var(--red-vivid);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 0 18px rgba(209,61,44,0.35)}
  .logo-icon::before{content:'🐜';font-size:18px;filter:brightness(0) invert(1)}
  .logo-text{font-family:var(--font-display);font-weight:700;font-size:19px;color:#F5F0EE;white-space:nowrap}
  .logo-text span{color:var(--red-bright)}
  .nav-links{display:flex;align-items:center;gap:2px;flex:1;min-width:0}
  .nav-link{font-size:13px;font-weight:600;color:#A09090;text-decoration:none;padding:7px 11px;border-radius:6px;transition:color .2s,background .2s;display:flex;align-items:center;gap:5px;white-space:nowrap;letter-spacing:.01em;cursor:pointer}
  .nav-link:hover{color:#F5F0EE;background:var(--soil-hover)}
  .nav-link.active{color:#F5F0EE;background:var(--soil-hover)}
  .nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0}
  .theme-btn{width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;font-size:13px;flex-shrink:0}
  .theme-btn:hover{color:var(--red-bright);border-color:var(--red-vivid)}
  .profile-wrap{position:relative}
  .profile-btn{display:flex;align-items:center;gap:9px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:6px 12px 6px 8px;cursor:pointer;transition:.2s;flex-shrink:0}
  .profile-btn:hover{background:var(--soil-card)}
  .profile-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden}
  .profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
  .profile-name{font-size:13px;font-weight:600;color:#F5F0EE}
  .profile-role-lbl{font-size:10px;color:var(--red-pale);margin-top:1px;letter-spacing:.02em;font-weight:600}
  .profile-chevron{font-size:9px;color:var(--text-muted);margin-left:2px}
  .profile-dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:6px;min-width:190px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:all .18s ease;z-index:300;box-shadow:0 20px 40px rgba(0,0,0,0.5)}
  .profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0)}
  .profile-dropdown-head{padding:12px 14px 10px;border-bottom:1px solid var(--soil-line);margin-bottom:4px}
  .pdh-name{font-size:14px;font-weight:700;color:#F5F0EE}
  .pdh-sub{font-size:11px;color:var(--red-pale);margin-top:2px;font-weight:600}
  .pd-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:6px;font-size:13px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:.15s;font-family:var(--font-body);text-decoration:none}
  .pd-item i{color:var(--text-muted);width:16px;text-align:center;font-size:12px}
  .pd-item:hover{background:var(--soil-hover);color:#F5F0EE}
  .pd-item:hover i{color:var(--red-bright)}
  .pd-divider{height:1px;background:var(--soil-line);margin:4px 6px}
  .pd-item.danger{color:#E05555}
  .pd-item.danger i{color:#E05555}
  .pd-item.danger:hover{background:rgba(224,85,85,0.1);color:#FF7070}

  /* Nav icon buttons (Messages, Notifications) */
  .msg-btn-nav,.notif-btn-nav{position:relative;width:36px;height:36px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.2s;font-size:15px;color:var(--text-muted);flex-shrink:0}
  .msg-btn-nav:hover,.notif-btn-nav:hover{color:var(--red-pale);border-color:var(--red-vivid)}
  .msg-btn-nav .badge,.notif-btn-nav .badge{position:absolute;top:-5px;right:-5px;width:17px;height:17px;border-radius:50%;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--soil-dark)}
  body.light .msg-btn-nav,body.light .notif-btn-nav{background:#F5EEEC;border-color:#E0CECA;color:#7A5555}
  body.light .msg-btn-nav .badge,body.light .notif-btn-nav .badge{border-color:#F9F5F4}

  /* Messages panel */
  .msg-panel{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:100vw;background:var(--soil-card);border-left:1px solid var(--soil-line);z-index:500;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,0.4)}
  .msg-panel.open{transform:translateX(0)}
  .msg-panel-head{padding:18px 18px 14px;border-bottom:1px solid var(--soil-line);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .msg-panel-title{font-family:var(--font-display);font-size:17px;font-weight:700;color:var(--text-light);display:flex;align-items:center;gap:8px}
  .msg-panel-title i{color:var(--red-bright)}
  #msgThreadView{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
  .msg-panel-body{flex:1;overflow-y:auto;min-height:0;padding:0;scrollbar-width:thin;scrollbar-color:var(--soil-line) transparent}
  .msg-panel-search{padding:10px 14px;border-bottom:1px solid var(--soil-line);flex-shrink:0}
  .msg-panel-search-bar{display:flex;align-items:center;gap:8px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:8px 12px}
  .msg-panel-search-bar input{flex:1;background:none;border:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-light)}
  .msg-panel-search-bar input::placeholder{color:var(--text-muted)}
  .msg-panel-search-bar i{color:var(--text-muted);font-size:12px}
  .msg-panel-new-chat{padding:10px 14px;border-bottom:1px solid var(--soil-line);flex-shrink:0}
  .msg-panel-new-chat-bar{display:flex;align-items:center;gap:8px;background:var(--soil-hover);border:1px solid var(--red-vivid);border-radius:8px;padding:8px 12px}
  .msg-panel-new-chat-bar input{flex:1;background:none;border:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-light)}
  .msg-panel-new-chat-bar input::placeholder{color:var(--text-muted)}
  .msg-panel-new-chat-bar i{color:var(--red-bright);font-size:13px}
  .msg-panel-new-chat-results{max-height:180px;overflow-y:auto;margin-top:6px;scrollbar-width:thin}
  .msg-panel-new-chat-user{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:6px;cursor:pointer;transition:.15s}
  .msg-panel-new-chat-user:hover{background:var(--soil-hover)}
  .msg-panel-new-chat-user-av{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden}
  .msg-panel-new-chat-user-av img{width:100%;height:100%;object-fit:cover}
  body.light .msg-panel-new-chat-bar{border-color:var(--red-vivid);background:#F5EEEC}
  body.light .msg-panel-new-chat-bar input{color:#1A0A09}
  .msg-item{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid var(--soil-line);cursor:pointer;transition:.15s;position:relative}
  .msg-item:last-child{border-bottom:none}
  .msg-item:hover{background:var(--soil-hover)}
  .msg-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden}
  .msg-avatar img{width:100%;height:100%;object-fit:cover}
  .msg-name{font-size:13px;font-weight:600;color:var(--text-mid);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .msg-item.unread .msg-name{font-weight:700;color:var(--text-light)}
  .msg-preview{font-size:12px;color:var(--text-muted);line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .msg-time{font-size:10px;color:var(--text-muted);font-weight:600;flex-shrink:0;white-space:nowrap}
  .msg-job-tag{font-size:11px;color:var(--red-pale);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  body.light .msg-panel{background:#FFFFFF;border-color:#E0CECA;box-shadow:-8px 0 32px rgba(0,0,0,0.1)}
  body.light .msg-panel-title{color:#1A0A09}
  body.light .msg-item{border-color:#E0CECA}
  body.light .msg-item:hover{background:#FEF0EE}
  body.light .msg-panel-search-bar{background:#F5EEEC;border-color:#E0CECA}
  body.light .msg-panel-search-bar input{color:#1A0A09}
  body.light .msg-name{color:#4A2828}
  body.light .msg-item.unread .msg-name{color:#1A0A09}
  .msg-item .msg-unread-dot{width:8px;height:8px;border-radius:50%;background:var(--red-vivid);flex-shrink:0;margin-top:4px}

  /* Hamburger + Mobile */
  .hamburger{display:none;width:34px;height:34px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);align-items:center;justify-content:center;cursor:pointer;font-size:14px;flex-shrink:0;margin-left:8px}
  .mobile-menu{display:none;position:fixed;top:64px;left:0;right:0;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--soil-line);padding:12px 20px 16px;z-index:190;flex-direction:column;gap:2px}
  .mobile-menu.open{display:flex}
  .mobile-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:7px;font-size:14px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:.15s;font-family:var(--font-body);text-decoration:none}
  .mobile-link i{color:var(--red-mid);width:16px;text-align:center}
  .mobile-link:hover{background:var(--soil-hover);color:#F5F0EE}
  .mobile-link.active{color:#F5F0EE;background:var(--soil-hover)}
  .mobile-divider{height:1px;background:var(--soil-line);margin:6px 0}

  /* Notifications panel */
  .notif-panel-side{position:fixed;top:64px;right:0;bottom:0;width:360px;background:var(--soil-card);border-left:1px solid var(--soil-line);z-index:150;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,0.4)}
  .notif-panel-side.open{transform:translateX(0)}
  .notif-panel-head{padding:20px 20px 16px;border-bottom:1px solid var(--soil-line);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .notif-panel-title{font-family:var(--font-display);font-size:17px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:8px}
  .notif-panel-title i{color:var(--red-bright)}
  .notif-close{width:28px;height:28px;border-radius:6px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:.15s}
  .notif-close:hover{color:#F5F0EE}
  .notif-panel-body{flex:1;overflow-y:auto;padding:12px 16px}
  .notif-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--soil-line);cursor:pointer}
  .notif-item:last-child{border-bottom:none}
  .n-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px}
  .n-dot.red{background:var(--red-vivid)}.n-dot.amber{background:var(--amber)}.n-dot.green{background:#4CAF70}.n-dot.read{background:var(--soil-line)}
  .n-text{font-size:13px;color:var(--text-mid);line-height:1.55}
  .n-time{font-size:11px;color:var(--text-muted);margin-top:3px;font-weight:600}

  body.light .notif-panel-side{background:#FFFFFF;border-color:#E0CECA;box-shadow:-8px 0 32px rgba(0,0,0,0.1)}
  body.light .notif-panel-title{color:#1A0A09}
  body.light .notif-item{border-color:#E0CECA}
  body.light .n-text{color:#3A2020}
  body.light .n-time{color:#7A5555}
  body.light .notif-close{background:#F0E4E2;border-color:#E0CECA;color:#7A5555}
  body.light .n-dot.read{background:#E0CECA}

  /* Conversation slide-over */
  #msgConvoView{position:absolute;inset:0;background:var(--soil-card);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);z-index:1}
  #msgConvoView.open{transform:translateX(0)}
  body.light #msgConvoView{background:#FFFFFF}
  .sp-chat-head{display:flex;align-items:center;gap:10px;padding:12px 14px;border-bottom:1px solid var(--soil-line);flex-shrink:0}
  .sp-chat-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden}
  .sp-chat-avatar img{width:100%;height:100%;object-fit:cover}
  .sp-chat-name{font-size:14px;font-weight:700;color:var(--text-light)}
  .sp-chat-meta{font-size:11px;color:var(--text-muted);margin-top:1px}
  .sp-chat-messages{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;scrollbar-width:thin;min-height:0}
  .sp-chat-input{padding:10px 14px;border-top:1px solid var(--soil-line);flex-shrink:0}
  .sp-chat-input-row{display:flex;align-items:flex-end;gap:8px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:8px 10px;transition:.2s}
  .sp-chat-input-row:focus-within{border-color:var(--red-vivid);box-shadow:0 0 0 2px rgba(209,61,44,0.1)}
  .sp-chat-input-row textarea{flex:1;background:none;border:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-light);resize:none;min-height:32px;max-height:80px;line-height:1.4}
  .sp-chat-input-row textarea::placeholder{color:var(--text-muted)}
  .sp-chat-send{width:32px;height:32px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;transition:.2s;flex-shrink:0}
  .sp-chat-send:hover{background:var(--red-bright);transform:scale(1.05)}
  .sp-msg-row{display:flex;gap:6px;align-items:flex-end}
  .sp-msg-row.sent{flex-direction:row-reverse}
  .sp-msg-date{text-align:center;font-size:10px;color:var(--text-muted);padding:4px 0;position:relative}
  .sp-msg-date::before{content:'';position:absolute;left:0;right:0;top:50%;height:1px;background:var(--soil-line)}
  .sp-msg-date span{background:var(--soil-card);padding:0 8px;position:relative}
  body.light .sp-msg-date span{background:#FFFFFF}
  body.light .sp-chat-name{color:#1A0A09}
  body.light .sp-chat-input-row{background:#F5EEEC;border-color:#E0CECA}
  body.light .sp-chat-input-row textarea{color:#1A0A09}
  body.light .sp-chat-head{border-color:#E0CECA}
  body.light .sp-chat-input{border-color:#E0CECA}
  .sb-bubble{max-width:80%;min-width:48px;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.45;word-break:break-word;white-space:pre-wrap}
  .sb-bubble-recv{background:var(--soil-hover);color:var(--text-light);border-bottom-left-radius:4px}
  .sb-bubble-sent{background:var(--red-vivid);color:#fff;border-bottom-right-radius:4px}
  .sb-bubble-time{font-size:9px;margin-top:3px;opacity:.6}
  .sb-bubble-sent .sb-bubble-time{text-align:right}
  body.light .sb-bubble-recv{background:#F5EEEC;color:#1A0A09}

  /* Toast */
  .toast{position:fixed;bottom:24px;right:24px;z-index:999;background:var(--soil-card);border:1px solid var(--soil-line);border-left:2px solid var(--red-vivid);border-radius:8px;padding:11px 18px;font-size:13px;font-weight:500;color:#F5F0EE;box-shadow:0 10px 30px rgba(0,0,0,0.4);display:flex;align-items:center;gap:9px;animation:toastIn .25s ease;pointer-events:none}
  @keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
  .toast i{color:var(--red-pale)}
  body.light .toast{background:#FFFFFF;border-color:#E0CECA;color:#1A0A09;box-shadow:0 10px 30px rgba(0,0,0,0.1)}
  body.light .toast i{color:var(--red-vivid)}

  /* Light overrides */
  body.light .navbar{background:rgba(249,245,244,0.97);border-bottom-color:#D4B0AB;box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08)}
  body.light .logo-text{color:#1A0A09}
  body.light .logo-text span{color:var(--red-vivid)}
  body.light .nav-link{color:#5A4040}
  body.light .nav-link:hover,body.light .nav-link.active{color:#1A0A09;background:#FEF0EE}
  body.light .theme-btn{background:#F5EEEC;border-color:#E0CECA;color:#7A5555}
  body.light .profile-btn{background:#F5EEEC;border-color:#E0CECA}
  body.light .profile-name{color:#1A0A09}
  body.light .profile-role-lbl{color:var(--red-bright)}
  body.light .hamburger{background:#F5EEEC;border-color:#E0CECA;color:#5A4040}
  body.light .profile-dropdown{background:#FFFFFF;border-color:#E0CECA;box-shadow:0 20px 40px rgba(0,0,0,0.12)}
  body.light .profile-dropdown-head{border-color:#E0CECA}
  body.light .pdh-name{color:#1A0A09}
  body.light .pd-item{color:#4A2828}
  body.light .pd-item:hover{background:#FEF0EE;color:#1A0A09}
  body.light .pd-item i{color:#7A5555}
  body.light .pd-item:hover i{color:var(--red-bright)}
  body.light .pd-divider{background:#E0CECA}
  body.light .mobile-menu{background:rgba(249,245,244,0.97);border-color:#E0CECA}
  body.light .mobile-link{color:#4A2828}
  body.light .mobile-link:hover{background:#FEF0EE;color:#1A0A09}
  body.light .mobile-divider{background:#E0CECA}
  body.light .glow-orb{display:none}

  @media(max-width:760px){
    .nav-links{display:none}.hamburger{display:flex}
    .profile-name,.profile-role-lbl{display:none}.profile-btn{padding:6px 8px}
    .notif-panel-side{width:100%;max-width:100%}
  }
</style>

<!-- ═══════════ NAVBAR ═══════════ -->
<nav class="navbar" id="mainNavbar">
  <div class="nav-inner">
    <a class="logo" href="<?= _recNavHref($recNavRoutes['dashboard']) ?>">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>

    <div class="nav-links" id="navLinks">
      <a class="nav-link<?= _recActive('dashboard', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['dashboard']) ?>">
        <i class="fas fa-th-large"></i> Dashboard
      </a>
      <a class="nav-link<?= _recActive('people', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['people']) ?>">
        <i class="fas fa-users"></i> People Search
      </a>
      <a class="nav-link<?= _recActive('my-jobs', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['my-jobs']) ?>">
        <i class="fas fa-briefcase"></i> My Jobs
      </a>
      <a class="nav-link<?= _recActive('applicants', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['applicants']) ?>">
        <i class="fas fa-user-check"></i> Applicants
      </a>
    </div>

    <div class="nav-right">
      <button class="theme-btn" id="themeToggle" title="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>

      <button class="msg-btn-nav" id="msgToggle" data-msg-trigger title="Messages">
        <i class="fas fa-envelope"></i>
        <span class="badge" id="recMsgBadge" style="display:none">0</span>
      </button>

      <button class="notif-btn-nav" id="notifToggle" data-notif-trigger title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="badge" id="recNotifBadge" style="display:none">0</span>
      </button>

      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle" type="button" aria-haspopup="true" aria-expanded="false">
          <div class="profile-avatar"><?php if (!empty($avatarUrl)): ?><img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></div>
          <div>
            <div class="profile-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="profile-role-lbl">Recruiter</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown" role="menu">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="pdh-sub">Recruiter · <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <a class="pd-item" href="<?= _recNavHref($recNavRoutes['profile']) ?>" role="menuitem">
            <i class="fas fa-user"></i> My Profile
          </a>
          <a class="pd-item" href="<?= _recNavHref($recNavRoutes['company']) ?>" role="menuitem">
            <i class="fas fa-building"></i> Company Profile
          </a>
          <a class="pd-item" href="<?= _recNavHref($recNavRoutes['messages']) ?>" role="menuitem">
            <i class="fas fa-envelope"></i> Messages
          </a>
          <a class="pd-item" href="<?= _recNavHref($recNavRoutes['settings']) ?>" role="menuitem">
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

<!-- Mobile menu -->
<div class="mobile-menu" id="mobileMenu" aria-hidden="true">
  <a class="mobile-link<?= _recActive('dashboard', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['dashboard']) ?>"><i class="fas fa-th-large"></i> Dashboard</a>
  <a class="mobile-link<?= _recActive('people', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['people']) ?>"><i class="fas fa-users"></i> People Search</a>
  <a class="mobile-link<?= _recActive('my-jobs', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['my-jobs']) ?>"><i class="fas fa-briefcase"></i> My Jobs</a>
  <a class="mobile-link<?= _recActive('applicants', $navActive) ?>" href="<?= _recNavHref($recNavRoutes['applicants']) ?>"><i class="fas fa-user-check"></i> Applicants</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="<?= _recNavHref($recNavRoutes['profile']) ?>"><i class="fas fa-user"></i> My Profile</a>
  <a class="mobile-link" href="<?= _recNavHref($recNavRoutes['company']) ?>"><i class="fas fa-building"></i> Company Profile</a>
  <a class="mobile-link" href="<?= _recNavHref($recNavRoutes['settings']) ?>"><i class="fas fa-cog"></i> Settings</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<!-- Messages slide-in panel -->
<div class="msg-panel" id="msgPanel" aria-hidden="true">
  <div class="msg-panel-head">
    <div class="msg-panel-title"><i class="fas fa-envelope"></i> Messages</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="notif-close" id="msgNewChat" title="New Conversation" style="background:var(--red-vivid);color:#fff;border-color:var(--red-vivid);"><i class="fas fa-pen-to-square"></i></button>
      <a class="notif-close" id="msgExpandFull" href="<?= _recNavHref($recNavRoutes['messages']) ?>" title="Open full page" style="text-decoration:none"><i class="fas fa-expand"></i></a>
      <button class="notif-close" id="msgClose" aria-label="Close messages"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div id="msgContentArea" style="flex:1;position:relative;min-height:0;overflow:hidden;display:flex;flex-direction:column;">
    <div id="msgThreadView">
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
            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();recSendMsg();}"
            oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,80)+'px';"></textarea>
          <button class="sp-chat-send" id="msgConvoSend"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Notifications slide-in panel -->
<div class="notif-panel-side" id="notifPanel" aria-hidden="true">
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

<!-- ═══════════ SHARED NAVBAR SCRIPTS ═══════════ -->
<script>
(function(){
  'use strict';

  /* ── THEME ── */
  function applyTheme(t){
    var isLight = t==='light';
    document.body.classList.toggle('light',isLight);
    document.body.classList.toggle('dark',!isLight);
    document.documentElement.classList.toggle('theme-light',isLight);
    var icon=document.getElementById('themeIcon');
    if(icon) icon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
    localStorage.setItem('ac-theme',t);
  }
  var storedTheme = localStorage.getItem('ac-theme') || 'light';
  applyTheme(storedTheme);
  window.applyTheme = applyTheme;
  window.setTheme = applyTheme;

  document.getElementById('themeToggle').addEventListener('click',function(){
    applyTheme(document.body.classList.contains('light')?'dark':'light');
  });

  /* ── HAMBURGER ── */
  var hamburger = document.getElementById('hamburger');
  var mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click',function(e){
    e.stopPropagation();
    var isOpen = mobileMenu.classList.toggle('open');
    hamburger.setAttribute('aria-expanded',String(isOpen));
    mobileMenu.setAttribute('aria-hidden',String(!isOpen));
    hamburger.querySelector('i').className = isOpen ? 'fas fa-times' : 'fas fa-bars';
  });

  /* ── PROFILE DROPDOWN ── */
  var profileToggle = document.getElementById('profileToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  profileToggle.addEventListener('click',function(e){
    e.stopPropagation();
    var isOpen = profileDropdown.classList.toggle('open');
    profileToggle.setAttribute('aria-expanded',String(isOpen));
  });

  /* ── NOTIFICATIONS PANEL ── */
  var notifPanel = document.getElementById('notifPanel');
  window.openNotif = function(){ closeMsgPanel(); notifPanel.classList.add('open'); notifPanel.setAttribute('aria-hidden','false'); loadRecNotifications(); };
  window.closeNotif = function(){ notifPanel.classList.remove('open'); notifPanel.setAttribute('aria-hidden','true'); };
  window.openNotifSidebar = window.openNotif;
  document.getElementById('notifClose').addEventListener('click',closeNotif);
  document.getElementById('notifToggle').addEventListener('click',function(e){
    e.stopPropagation();
    notifPanel.classList.contains('open') ? closeNotif() : openNotif();
  });
  document.getElementById('notifMarkAll').addEventListener('click',function(){
    fetch('../api/messages.php?action=mark_notif_read',{method:'POST'})
      .then(function(){ loadRecNotifications(); updateRecBadges(); });
  });

  /* ── MESSAGES PANEL ── */
  var msgPanel = document.getElementById('msgPanel');
  var msgThreadView = document.getElementById('msgThreadView');
  var msgConvoView = document.getElementById('msgConvoView');
  var _recCurrentPartner = null;
  var _recThreadsCache = [];

  function getRecMessagesUrl(partnerId){
    var base = '<?= htmlspecialchars($recNavRoutes['messages'], ENT_QUOTES, 'UTF-8') ?>';
    var q = new URLSearchParams();
    q.set('theme', document.body.classList.contains('light')?'light':'dark');
    if(partnerId && Number.isFinite(partnerId) && partnerId > 0) q.set('user_id',String(partnerId));
    return base + '?' + q.toString();
  }
  function refreshRecFullMessageLinks(){
    var href = getRecMessagesUrl(_recCurrentPartner);
    var a = document.getElementById('msgExpandFull');
    if(a) a.href = href;
  }

  window.openMsgPanel = function(){
    closeNotif();
    msgPanel.classList.add('open'); msgPanel.setAttribute('aria-hidden','false');
    showRecThreadView(); loadRecThreads(); refreshRecFullMessageLinks();
  };
  window.closeMsgPanel = function(){
    msgPanel.classList.remove('open'); msgPanel.setAttribute('aria-hidden','true');
  };
  window.openMsgSidebar = window.openMsgPanel;

  function showRecThreadView(){ msgConvoView.classList.remove('open'); _recCurrentPartner = null; refreshRecFullMessageLinks(); }
  function showRecConvoView(){ msgConvoView.classList.add('open'); }
  function _esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

  /* Load threads */
  function loadRecThreads(){
    var container = document.getElementById('msgThreadList');
    fetch('../api/messages.php?action=threads')
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(!data.success||!data.threads||!data.threads.length){
          container.innerHTML='<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;"><i class="fas fa-inbox"></i><div style="margin-top:8px;">No messages yet</div></div>';
          return;
        }
        var colors=['#4A90D9','#9B59B6','#27AE60','#E74C3C','#D4943A','#3498DB','#E67E22','#1ABC9C'];
        _recThreadsCache = data.threads;
        var html = '';
        data.threads.forEach(function(t,i){
          var color = t.color||colors[i%colors.length];
          var unread = t.unread_count > 0 ? ' unread' : '';
          var unreadDot = t.unread_count > 0 ? '<div class="msg-unread-dot"></div>' : '';
          html += '<div class="msg-item'+unread+'" data-partner-id="'+t.partner_id+'" data-partner-name="'+_esc(t.name)+'">'
            +'<div class="msg-avatar" style="background:'+color+'">'+(t.avatar_url?'<img src="../'+t.avatar_url+'" alt="">':_esc(t.initials))+'</div>'
            +'<div style="flex:1;min-width:0;">'
            +'<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">'
            +'<div class="msg-name">'+_esc(t.name)+'</div>'
            +'<div class="msg-time">'+_esc(t.time)+'</div></div>'
            +'<div class="msg-preview">'+(t.is_sent?'You: ':'')+_esc(t.preview)+'</div>'
            +(t.job_title?'<div class="msg-job-tag"><i class="fas fa-briefcase" style="font-size:9px;"></i> '+_esc(t.job_title)+'</div>':'')
            +'</div>'+unreadDot+'</div>';
        });
        container.innerHTML = html;
        container.querySelectorAll('.msg-item').forEach(function(el){
          el.addEventListener('click',function(){
            openRecConvo(parseInt(el.getAttribute('data-partner-id')),el.getAttribute('data-partner-name'));
          });
        });
      })
      .catch(function(){ container.innerHTML='<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;">Failed to load messages</div>'; });
  }

  function filterMsgPanelList(q){
    var items=document.getElementById('msgThreadList').querySelectorAll('.msg-item');
    q=(q||'').toLowerCase();
    items.forEach(function(el){ el.style.display=(!q||el.getAttribute('data-partner-name').toLowerCase().includes(q))?'':'none'; });
  }

  /* Open conversation */
  function openRecConvo(partnerId,partnerName){
    _recCurrentPartner=partnerId;
    var t=(_recThreadsCache||[]).find(function(x){return x.partner_id==partnerId;});
    var color=(t&&t.color)?t.color:'#4A90D9';
    var ini=(t&&t.initials)?t.initials:(partnerName?partnerName.substring(0,2).toUpperCase():'?');
    var avatarUrl=t&&t.avatar_url?t.avatar_url:null;
    var avatarEl=document.getElementById('msgConvoAvatar');
    avatarEl.style.background=color;
    avatarEl.innerHTML=avatarUrl?'<img src="../'+avatarUrl+'" alt="">':_esc(ini);
    document.getElementById('msgConvoName').textContent=partnerName||'Conversation';
    document.getElementById('msgConvoMeta').textContent=(t&&t.job_title)?t.job_title:'';
    showRecConvoView(); refreshRecFullMessageLinks();
    var body=document.getElementById('msgConvoBody');
    body.innerHTML='<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch('../api/messages.php?action=messages&user_id='+partnerId)
      .then(function(r){return r.json();})
      .then(function(data){
        renderRecConvoMsgs(body,data,color,ini,avatarUrl);
        if(data.success){markRecConversationRead(partnerId);updateRecBadges();}
      })
      .catch(function(){body.innerHTML='<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:12px;">Failed to load conversation</div>';});
  }

  function renderRecConvoMsgs(body,data,color,ini,avatarUrl){
    if(!data.success||!data.messages||!data.messages.length){
      body.innerHTML='<div style="text-align:center;padding:30px 20px;color:var(--text-muted);font-size:12px;"><i class="fas fa-comment-dots" style="font-size:24px;display:block;margin-bottom:8px;"></i>No messages yet. Say hello!</div>';
      return;
    }
    var html='';
    data.messages.forEach(function(m){
      if(m.show_date) html+='<div class="sp-msg-date"><span>'+_esc(m.date)+'</span></div>';
      if(m.from==='me'){
        html+='<div class="sp-msg-row sent"><div class="sb-bubble sb-bubble-sent">'+_esc(m.body)+'<div class="sb-bubble-time">'+_esc(m.time)+' <i class="fas fa-check-double" style="font-size:8px;"></i></div></div></div>';
      } else {
        html+='<div class="sp-msg-row"><div class="sp-chat-avatar" style="background:'+color+';width:26px;height:26px;font-size:10px;">'+(avatarUrl?'<img src="../'+avatarUrl+'" alt="">':ini)+'</div><div class="sb-bubble sb-bubble-recv">'+_esc(m.body)+'<div class="sb-bubble-time">'+_esc(m.time)+'</div></div></div>';
      }
    });
    body.innerHTML=html;
    body.scrollTop=body.scrollHeight;
  }

  function markRecConversationRead(partnerId){
    return fetch('../api/messages.php?action=mark_read',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({partner_id:partnerId})}).then(function(r){return r.json();}).catch(function(){return null;});
  }

  /* Send message */
  function recSendMsg(){
    var input=document.getElementById('msgConvoInput');
    var text=input.value.trim();
    if(!text||!_recCurrentPartner) return;
    input.value=''; input.style.height='auto';
    var body=document.getElementById('msgConvoBody');
    var row=document.createElement('div'); row.className='sp-msg-row sent';
    row.innerHTML='<div class="sb-bubble sb-bubble-sent">'+_esc(text)+'<div class="sb-bubble-time" id="_sp_sending">Sending\u2026</div></div>';
    body.appendChild(row); body.scrollTop=body.scrollHeight;
    fetch('../api/messages.php?action=send',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({receiver_id:_recCurrentPartner,message:text})})
      .then(function(r){return r.json();})
      .then(function(data){
        var timeEl=row.querySelector('#_sp_sending');
        if(data.success){
          if(timeEl) timeEl.textContent=data.time||new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
          var t=(_recThreadsCache||[]).find(function(x){return x.partner_id==_recCurrentPartner;});
          var clr=(t&&t.color)?t.color:'#4A90D9'; var ini=(t&&t.initials)?t.initials:'?'; var avu=t&&t.avatar_url?t.avatar_url:null;
          fetch('../api/messages.php?action=messages&user_id='+_recCurrentPartner).then(function(r2){return r2.json();}).then(function(d2){renderRecConvoMsgs(body,d2,clr,ini,avu);});
        } else { row.style.opacity='0.5'; if(timeEl) timeEl.textContent='Failed'; }
      })
      .catch(function(){row.style.opacity='0.5';});
  }
  document.getElementById('msgConvoSend').addEventListener('click',recSendMsg);
  window.recSendMsg = recSendMsg;

  /* Back button */
  document.getElementById('msgConvoBack').addEventListener('click',function(){ showRecThreadView(); loadRecThreads(); });

  /* Load notifications */
  function loadRecNotifications(){
    var container=document.getElementById('notifList');
    fetch('../api/messages.php?action=notifications')
      .then(function(r){return r.json();})
      .then(function(data){
        if(!data.success||!data.notifications||!data.notifications.length){
          container.innerHTML='<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;"><i class="fas fa-bell-slash"></i><div style="margin-top:8px;">No notifications</div></div>';
          return;
        }
        var html='';
        data.notifications.forEach(function(n){
          var dotClass=n.is_read?'read':(n.type==='message'?'red':(n.type==='application'?'green':'amber'));
          html+='<div class="notif-item" data-notif-id="'+n.id+'">'
            +'<div class="n-dot '+dotClass+'"></div>'
            +'<div><div class="n-text">'+n.content+'</div><div class="n-time">'+_esc(n.time)+'</div></div></div>';
        });
        container.innerHTML=html;
        container.querySelectorAll('.notif-item').forEach(function(el){
          el.addEventListener('click',function(){
            var nid=el.getAttribute('data-notif-id');
            fetch('../api/messages.php?action=mark_notif_read&id='+nid,{method:'POST'})
              .then(function(){el.querySelector('.n-dot').className='n-dot read'; updateRecBadges();});
          });
        });
      })
      .catch(function(){ container.innerHTML='<div style="text-align:center;padding:40px 0;color:var(--text-muted);font-size:13px;">Failed to load notifications</div>'; });
  }

  /* Badge updates */
  function updateRecBadges(){
    fetch('../api/messages.php?action=unread_count')
      .then(function(r){return r.json();})
      .then(function(data){
        if(!data.success) return;
        var mb=document.getElementById('recMsgBadge');
        var nb=document.getElementById('recNotifBadge');
        if(mb){mb.textContent=data.messages||0;mb.style.display=data.messages>0?'':'none';}
        if(nb){nb.textContent=data.notifications||0;nb.style.display=data.notifications>0?'':'none';}
      }).catch(function(){});
  }
  window.updateRecBadges = updateRecBadges;
  updateRecBadges();
  setInterval(updateRecBadges,30000);

  document.getElementById('msgClose').addEventListener('click',closeMsgPanel);
  document.getElementById('msgNewChat').addEventListener('click',function(e){
    e.stopPropagation();
    var panel=document.getElementById('msgNewChatPanel');
    var searchInput=document.getElementById('msgNewChatSearch');
    var results=document.getElementById('msgNewChatResults');
    if(panel.style.display==='none'){
      panel.style.display=''; results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Type a name to search</div>';
      setTimeout(function(){searchInput.value='';searchInput.focus();},50);
    } else { panel.style.display='none'; }
  });

  var _newChatTimer=null;
  window.searchMsgPanelNewChat=function(){
    var q=document.getElementById('msgNewChatSearch').value.trim();
    var results=document.getElementById('msgNewChatResults');
    if(q.length<2){results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Type at least 2 characters</div>';return;}
    if(_newChatTimer) clearTimeout(_newChatTimer);
    _newChatTimer=setTimeout(function(){
      results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
      fetch('../api/messages.php?action=search_users&q='+encodeURIComponent(q))
        .then(function(r){return r.json();})
        .then(function(data){
          if(!data.success||!data.users||!data.users.length){results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">No users found</div>';return;}
          var clrs=['#4A90D9','#D4943A','#4CAF70','#9C27B0','#E05555','#00897B'];
          results.innerHTML=data.users.map(function(u,i){
            return '<div class="msg-panel-new-chat-user" onclick="msgPanelStartChat('+u.id+',\''+_esc(u.name).replace(/'/g,"\\'")+'\')">'
              +'<div class="msg-panel-new-chat-user-av" style="background:'+clrs[i%clrs.length]+'">'+(u.avatar_url?'<img src="../'+u.avatar_url+'" alt="">':_esc(u.initials))+'</div>'
              +'<div><div style="font-size:13px;font-weight:600;color:var(--text-light);">'+_esc(u.name)+'</div><div style="font-size:11px;color:var(--text-muted);text-transform:capitalize;">'+_esc(u.type)+'</div></div></div>';
          }).join('');
        })
        .catch(function(){results.innerHTML='<div style="padding:10px;text-align:center;color:var(--text-muted);font-size:12px;">Search failed</div>';});
    },300);
  };

  window.msgPanelStartChat=function(userId,userName){
    document.getElementById('msgNewChatPanel').style.display='none';
    openRecConvo(userId,userName);
  };

  document.getElementById('msgToggle').addEventListener('click',function(e){
    e.stopPropagation();
    msgPanel.classList.contains('open') ? closeMsgPanel() : openMsgPanel();
  });

  /* Click-outside */
  document.addEventListener('click',function(e){
    if(!mobileMenu.contains(e.target)&&!hamburger.contains(e.target)){
      mobileMenu.classList.remove('open'); hamburger.setAttribute('aria-expanded','false'); mobileMenu.setAttribute('aria-hidden','true');
      hamburger.querySelector('i').className='fas fa-bars';
    }
    if(!profileToggle.contains(e.target)&&!profileDropdown.contains(e.target)){
      profileDropdown.classList.remove('open'); profileToggle.setAttribute('aria-expanded','false');
    }
    var triggers=document.querySelectorAll('[data-notif-trigger]');
    var clickedTrigger=Array.from(triggers).some(function(t){return t.contains(e.target);});
    if(!notifPanel.contains(e.target)&&!clickedTrigger) closeNotif();
    var msgTriggers=document.querySelectorAll('[data-msg-trigger]');
    var clickedMsgTrigger=Array.from(msgTriggers).some(function(t){return t.contains(e.target);});
    if(!msgPanel.contains(e.target)&&!clickedMsgTrigger) closeMsgPanel();
  });

  /* Toast */
  window.showToast=function(msg,icon){
    icon=icon||'fa-info-circle';
    var t=document.createElement('div'); t.className='toast';
    t.innerHTML='<i class="fas '+icon+'"></i> '+msg;
    document.body.appendChild(t);
    setTimeout(function(){t.remove();},2600);
  };

  /* Allow external event dispatch */
  window.addEventListener('recruiter:openMessageSidebar',function(evt){
    var partnerId=Number(evt&&evt.detail&&evt.detail.userId?evt.detail.userId:0);
    var partnerName=evt&&evt.detail&&evt.detail.userName?String(evt.detail.userName):'Conversation';
    openMsgPanel();
    if(partnerId>0) openRecConvo(partnerId,partnerName);
  });

  refreshRecFullMessageLinks();
})();
</script>
