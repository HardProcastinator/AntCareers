<?php
/**
 * AntCareers — Admin Notification Side Panel
 * includes/admin_notif_panel.php
 *
 * Usage (in any admin page, after $adminId and $db are defined):
 *   require_once dirname(__DIR__) . '/includes/admin_notif_panel.php';
 *
 * Then in the navbar, replace the bell anchor with:
 *   <button class="notif-btn-nav" id="navNotifBtn" onclick="toggleAdminNotifPanel()">
 *     <i class="fas fa-bell"></i>
 *     <?php if ($adminUnreadCount > 0): ?><span class="badge" id="adminNotifBadge"><?php echo $adminUnreadCount; ?></span><?php endif; ?>
 *   </button>
 *
 * Then before </body>:
 *   <?php renderAdminNotifPanel(); ?>
 */
declare(strict_types=1);

// Query unread count (requires $adminId and $db to be set by caller)
$adminUnreadCount = 0;
$adminPendingCompanies = 0;
$adminPendingJobs = 0;
if (isset($db, $adminId)) {
    try {
        $adminUnreadCount = (int)$db->query(
            "SELECT COUNT(*) FROM notifications WHERE user_id = {$adminId} AND is_read = 0"
        )->fetchColumn();
    } catch (Throwable) {}
    try {
        $adminPendingCompanies = (int)$db->query(
            "SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'"
        )->fetchColumn();
    } catch (Throwable) {}
    try {
        $adminPendingJobs = (int)$db->query(
            "SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'"
        )->fetchColumn();
    } catch (Throwable) {}
}

/**
 * Render the notification side panel HTML, CSS, and JS.
 * Call this just before </body> in each admin page.
 */
function renderAdminNotifPanel(): void {
    $csrfToken = htmlspecialchars((string)($_SESSION['csrf_token'] ?? ''), ENT_QUOTES);
    echo <<<HTML
<!-- Admin Notification Side Panel -->
<div class="admin-notif-panel" id="adminNotifPanel" aria-hidden="true">
  <div class="anp-head">
    <div class="anp-title"><i class="fas fa-bell"></i> Notifications</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="anp-close-btn" id="anpClearAll" title="Clear all"><i class="fas fa-trash-alt"></i></button>
      <button class="anp-close-btn" id="anpMarkAll" title="Mark all as read"><i class="fas fa-check-double"></i></button>
      <button class="anp-close-btn" id="anpClose"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div class="anp-body" id="anpBody">
    <div class="anp-loading"><i class="fas fa-spinner fa-spin"></i></div>
  </div>
  <div class="anp-footer">
    <a href="admin_notifications.php" class="anp-view-all">View all notifications <i class="fas fa-arrow-right"></i></a>
  </div>
</div>
<div class="admin-notif-overlay" id="adminNotifOverlay" onclick="closeAdminNotifPanel()"></div>

<style>
  .admin-notif-panel{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:100vw;background:var(--soil-card,#1C1818);border-left:1px solid var(--soil-line,#352E2E);z-index:600;transform:translateX(100%);transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;box-shadow:-8px 0 32px rgba(0,0,0,0.5)}
  .admin-notif-panel.open{transform:translateX(0)}
  .admin-notif-overlay{display:none;position:fixed;inset:0;z-index:599;background:rgba(0,0,0,0.35);backdrop-filter:blur(2px)}
  .admin-notif-overlay.visible{display:block}
  .anp-head{padding:20px 20px 16px;border-bottom:1px solid var(--soil-line,#352E2E);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
  .anp-title{font-family:var(--font-display,'Playfair Display',serif);font-size:17px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:8px}
  .anp-title i{color:var(--red-bright,#E85540)}
  .anp-close-btn{width:28px;height:28px;border-radius:6px;background:var(--soil-hover,#252020);border:1px solid var(--soil-line,#352E2E);color:var(--text-muted,#927C7A);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;transition:.15s}
  .anp-close-btn:hover{color:#F5F0EE;border-color:var(--red-vivid,#D13D2C)}
  .anp-body{flex:1;overflow-y:auto;padding:8px 0}
  .anp-footer{border-top:1px solid var(--soil-line,#352E2E);padding:12px 20px;flex-shrink:0}
  .anp-view-all{font-size:12px;font-weight:700;color:var(--red-pale,#F07060);text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px;transition:.15s}
  .anp-view-all:hover{color:var(--red-bright,#E85540)}
  .anp-loading{text-align:center;padding:40px;color:var(--text-muted,#927C7A);font-size:18px}
  .anp-empty{text-align:center;padding:40px 20px;color:var(--text-muted,#927C7A);font-size:13px}
  .anp-empty i{font-size:28px;margin-bottom:10px;display:block;color:var(--soil-line,#352E2E)}
  .anp-item{display:flex;gap:12px;padding:12px 20px;border-bottom:1px solid rgba(53,46,46,0.6);cursor:pointer;transition:.15s;text-decoration:none;color:inherit}
  .anp-item:last-child{border-bottom:none}
  .anp-item:hover{background:var(--soil-hover,#252020)}
  .anp-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:4px}
  .anp-dot.unread{background:var(--red-vivid,#D13D2C)}
  .anp-dot.read{background:var(--soil-line,#352E2E)}
  .anp-content{flex:1;min-width:0}
  .anp-text{font-size:13px;color:var(--text-mid,#D0BCBA);line-height:1.55;margin-bottom:3px}
  .anp-time{font-size:11px;color:var(--text-muted,#927C7A);font-weight:600}
  @media(max-width:760px){.admin-notif-panel{width:92vw;max-width:380px;}}
  body.light .admin-notif-panel{background:#FFFFFF;border-color:#E0CECA;box-shadow:-8px 0 32px rgba(0,0,0,0.1)}
  body.light .anp-title{color:#1A0A09}
  body.light .anp-item{border-color:#F0E0DC}
  body.light .anp-item:hover{background:#FEF0EE}
  body.light .anp-text{color:#3A2020}
  body.light .anp-time{color:#7A5555}
  body.light .anp-close-btn{background:#F0E4E2;border-color:#E0CECA;color:#7A5555}
  body.light .anp-dot.read{background:#E0CECA}
  body.light .anp-footer{border-color:#E0CECA}
  body.light .admin-notif-overlay{background:rgba(0,0,0,0.15)}
</style>

<script>
(function(){
  'use strict';
  var CSRF = '{$csrfToken}';
  var panel = document.getElementById('adminNotifPanel');
  var overlay = document.getElementById('adminNotifOverlay');
  var loaded = false;

  window.toggleAdminNotifPanel = function() {
    if (panel.classList.contains('open')) {
      closeAdminNotifPanel();
    } else {
      openAdminNotifPanel();
    }
  };

  window.openAdminNotifPanel = function() {
    panel.classList.add('open');
    overlay.classList.add('visible');
    panel.setAttribute('aria-hidden','false');
    if (!loaded) { loaded = true; loadAdminNotifs(); }
  };

  window.closeAdminNotifPanel = function() {
    panel.classList.remove('open');
    overlay.classList.remove('visible');
    panel.setAttribute('aria-hidden','true');
  };

  document.getElementById('anpClose').addEventListener('click', closeAdminNotifPanel);

  document.getElementById('anpClearAll').addEventListener('click', function() {
    fetch('api_admin.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'delete_all_notifications', csrf_token:CSRF})
    }).then(function(r){return r.json();}).then(function(d){
      if (d.success) {
        document.getElementById('anpBody').innerHTML = '<div class="anp-empty"><i class="fas fa-bell-slash"></i><div>No unread notifications.</div></div>';
        var badge = document.getElementById('adminNotifBadge');
        if (badge) badge.remove();
        if (typeof showToast === 'function') showToast('All notifications cleared.','fa-trash-alt');
      }
    }).catch(function(){});
  });

  document.getElementById('anpMarkAll').addEventListener('click', function() {
    fetch('api_admin.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({action:'mark_all_notifications_read', csrf_token:CSRF})
    }).then(function(r){return r.json();}).then(function(d){
      if (d.success) {
        document.querySelectorAll('.anp-dot.unread').forEach(function(el){el.className='anp-dot read';});
        var badge = document.getElementById('adminNotifBadge');
        if (badge) badge.remove();
        if (typeof showToast === 'function') showToast('All marked as read.','fa-check-double');
      }
    }).catch(function(){});
  });

  function timeAgo(ts) {
    var diff = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff/86400) + 'd ago';
    return new Date(ts).toLocaleDateString('en-US',{month:'short',day:'numeric'});
  }

  function getAdminNotifLink(type, refType) {
    if (refType === 'user') return 'admin_companies.php';
    if (refType === 'job')  return 'admin_jobs.php';
    return 'admin_notifications.php';
  }

  function loadAdminNotifs() {
    var body = document.getElementById('anpBody');
    body.innerHTML = '<div class="anp-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    fetch('api_admin.php?action=get_notifications')
      .then(function(r){return r.json();})
      .then(function(data){
        if (!data.success || !data.notifications || !data.notifications.length) {
          body.innerHTML = '<div class="anp-empty"><i class="fas fa-bell-slash"></i><div>No unread notifications.</div></div>';
          return;
        }
        var html = '';
        data.notifications.forEach(function(n){
          var dotCls = n.is_read ? 'read' : 'unread';
          var href   = getAdminNotifLink(n.type, n.reference_type || '');
          html += '<a class="anp-item" href="' + _esc(href) + '" data-id="' + n.id + '">'
            + '<div class="anp-dot ' + dotCls + '"></div>'
            + '<div class="anp-content"><div class="anp-text">' + _esc(n.content) + '</div>'
            + '<div class="anp-time">' + timeAgo(n.created_at) + '</div></div></a>';
        });
        body.innerHTML = html;
        body.querySelectorAll('.anp-item').forEach(function(el){
          el.addEventListener('click', function(e){
            var nid = el.getAttribute('data-id');
            if (nid) {
              fetch('api_admin.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({action:'mark_notification_read',csrf_token:CSRF,notification_id:parseInt(nid)})
              }).catch(function(){});
              var dot = el.querySelector('.anp-dot');
              if (dot) dot.className = 'anp-dot read';
            }
          });
        });
      })
      .catch(function(){
        body.innerHTML = '<div class="anp-empty"><div>Failed to load notifications.</div></div>';
      });
  }

  function _esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
})();
</script>
HTML;
}
