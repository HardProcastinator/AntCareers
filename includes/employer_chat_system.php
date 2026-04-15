<?php
declare(strict_types=1);
/**
 * AntCareers — Employer Chat & Notification System (Sidebar + Fullscreen)
 * includes/employer_chat_system.php
 *
 * Include this file in any employer page AFTER the navbar.
 * Requires: $_SESSION['user_id'], $_SESSION['user_name']
 *
 * Provides:
 *   - Message sidebar (slide-in from right)
 *   - Notification sidebar (slide-in from right)
 *   - Fullscreen chat overlay
 *   - AJAX polling for live updates
 *   - Badge count updates on navbar icons
 */
$_chatUserName = htmlspecialchars($_SESSION['user_name'] ?? 'Employer', ENT_QUOTES, 'UTF-8');
$_chatParts = preg_split('/\s+/', $_SESSION['user_name'] ?? 'Employer') ?: ['E'];
$_chatInitials = count($_chatParts) >= 2
    ? strtoupper(substr($_chatParts[0],0,1).substr($_chatParts[1],0,1))
    : strtoupper(substr($_chatParts[0],0,2));
$_chatAvatarUrl = $_SESSION['avatar_url'] ?? '';
if ($_chatAvatarUrl && !str_starts_with($_chatAvatarUrl, '../') && !str_starts_with($_chatAvatarUrl, 'http')) {
    $_chatAvatarUrl = '../' . $_chatAvatarUrl;
}
?>

<!-- ═══════════════════════════════════════════════════════════════════
     OVERLAY BACKDROP
     ═══════════════════════════════════════════════════════════════════ -->
<div class="chat-overlay-bg" id="chatOverlayBg" onclick="closeSidebars()"></div>

<!-- ═══════════════════════════════════════════════════════════════════
     MESSAGE SIDEBAR (slide-in from right)
     ═══════════════════════════════════════════════════════════════════ -->
<div class="msg-sidebar" id="msgSidebar">
  <div class="msg-sb-head">
    <div class="msg-sb-title"><i class="fas fa-envelope"></i> Messages</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="msg-sb-expand" onclick="toggleSbNewChat()" title="New Conversation" style="background:var(--red-vivid);color:#fff;border-color:var(--red-vivid);">
        <i class="fas fa-pen-to-square"></i>
      </button>
      <button class="msg-sb-expand" onclick="window.location.href='employer_messages.php?theme='+(document.body.classList.contains('light')?'light':'dark')" title="Open Fullscreen">
        <i class="fas fa-expand"></i>
      </button>
      <button class="msg-sb-close" onclick="closeMsgSidebar()"><i class="fas fa-times"></i></button>
    </div>
  </div>

  <!-- New Chat Search (Sidebar) -->
  <div class="sb-new-chat-panel" id="sbNewChatPanel" style="display:none;">
    <div class="sb-new-chat-bar">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search users to message..." id="sbNewChatSearch" oninput="sbSearchNewChat()">
    </div>
    <div class="sb-new-chat-results" id="sbNewChatResults"></div>
  </div>

  <!-- Search -->
  <div class="msg-sb-search">
    <div class="msg-sb-search-bar">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search conversations..." id="sbThreadSearch" oninput="filterSidebarThreads()">
    </div>
  </div>

  <!-- Thread List -->
  <div class="msg-sb-threads" id="sbThreadList">
    <div class="msg-sb-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
  </div>

  <!-- Chat View (shown when a thread is selected) -->
  <div class="msg-sb-chat" id="sbChatView" style="display:none;">
    <div class="msg-sb-chat-head">
      <button class="msg-sb-back" onclick="sbBackToThreads()"><i class="fas fa-arrow-left"></i></button>
      <div class="msg-sb-chat-avatar" id="sbChatAvatar"></div>
      <div class="msg-sb-chat-info">
        <div class="msg-sb-chat-name" id="sbChatName"></div>
        <div class="msg-sb-chat-meta" id="sbChatMeta"></div>
      </div>
    </div>
    <div class="msg-sb-messages" id="sbChatMessages"></div>
    <div class="msg-sb-input">
      <div class="msg-sb-input-row">
        <textarea id="sbMsgInput" placeholder="Write a message..." rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sbSendMessage();}"></textarea>
        <button class="msg-sb-send" onclick="sbSendMessage()"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     NOTIFICATION SIDEBAR
     ═══════════════════════════════════════════════════════════════════ -->
<div class="notif-sidebar" id="notifSidebar">
  <div class="notif-sb-head">
    <div class="notif-sb-title"><i class="fas fa-bell"></i> Notifications</div>
    <div style="display:flex;gap:6px;align-items:center;">
      <button class="notif-sb-mark-all" onclick="markAllNotifsRead()" title="Mark all as read">
        <i class="fas fa-check-double"></i>
      </button>
      <button class="notif-sb-close" onclick="closeNotifSidebar()"><i class="fas fa-times"></i></button>
    </div>
  </div>
  <div class="notif-sb-body" id="notifSbBody">
    <div class="msg-sb-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     FULLSCREEN CHAT OVERLAY
     ═══════════════════════════════════════════════════════════════════ -->
<div class="fs-chat-overlay" id="fsChatOverlay">
  <div class="fs-chat-container">
    <!-- Header -->
    <div class="fs-chat-header">
      <div class="fs-chat-title"><i class="fas fa-comments"></i> Messages</div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="msg-sb-expand" onclick="toggleFsNewChat()" title="New Conversation" style="background:var(--red-vivid);color:#fff;border-color:var(--red-vivid);">
          <i class="fas fa-pen-to-square"></i>
        </button>
        <button class="fs-chat-close" onclick="closeFullscreenChat()"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <div class="fs-chat-body">
      <!-- Thread list -->
      <div class="fs-thread-panel">
        <div class="fs-thread-search">
          <div class="fs-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search..." id="fsThreadSearch" oninput="filterFsThreads()">
          </div>
        </div>
        <!-- New Chat Search (Fullscreen) -->
        <div class="fs-new-chat-panel" id="fsNewChatPanel" style="display:none;">
          <div class="sb-new-chat-bar">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search users..." id="fsNewChatSearch" oninput="fsSearchNewChat()">
          </div>
          <div class="sb-new-chat-results" id="fsNewChatResults"></div>
        </div>
        <div class="fs-thread-filters">
          <button class="fs-tf active" data-filter="all" onclick="fsTfFilter('all',this)">All</button>
          <button class="fs-tf" data-filter="unread" onclick="fsTfFilter('unread',this)">Unread</button>
        </div>
        <div class="fs-thread-list" id="fsThreadList"></div>
      </div>
      <!-- Chat panel -->
      <div class="fs-chat-panel">
        <div class="fs-empty" id="fsEmpty">
          <div class="fs-empty-icon"><i class="fas fa-comments"></i></div>
          <div style="font-size:14px;font-weight:600;color:var(--text-mid);">Select a conversation</div>
          <div style="font-size:13px;color:var(--text-muted);">Choose a thread to start messaging</div>
        </div>
        <div class="fs-active-chat" id="fsActiveChat" style="display:none;">
          <div class="fs-chat-head2">
            <div class="fs-ch-avatar" id="fsChatAvatar"></div>
            <div class="fs-ch-info">
              <div class="fs-ch-name" id="fsChatName"></div>
              <div class="fs-ch-meta" id="fsChatMeta"></div>
            </div>
            <span class="fs-ch-status" id="fsChatStatus"></span>
          </div>
          <div class="fs-messages" id="fsChatMessages"></div>
          <div class="fs-input-area">
            <div class="fs-input-row">
              <textarea id="fsMsgInput" placeholder="Write a message..." rows="1"
                        onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();fsSendMessage();}"
                        oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px';"></textarea>
              <button class="fs-send-btn" onclick="fsSendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     TOAST
     ═══════════════════════════════════════════════════════════════════ -->
<div class="chat-toast" id="chatToast"><i class="fas fa-check"></i> <span id="chatToastMsg"></span></div>

<style>
/* ── OVERLAY BG ─────────────────────────────────────────────────────── */
.chat-overlay-bg { display:none; }


/* ── MESSAGE SIDEBAR ────────────────────────────────────────────────── */
.msg-sidebar { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:100vw; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:500; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
.msg-sidebar.open { transform:translateX(0); }
.msg-sb-head { padding:18px 18px 14px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.msg-sb-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:8px; }
.msg-sb-title i { color:var(--red-bright); }
.msg-sb-close, .msg-sb-expand, .msg-sb-back { width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
.msg-sb-close:hover, .msg-sb-expand:hover, .msg-sb-back:hover { color:var(--text-light); border-color:var(--red-vivid); }
.msg-sb-search { padding:10px 14px; border-bottom:1px solid var(--soil-line); }
.msg-sb-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 12px; }
.msg-sb-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
.msg-sb-search-bar input::placeholder { color:var(--text-muted); }
.msg-sb-search-bar i { color:var(--text-muted); font-size:13px; }
.msg-sb-threads { flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
.msg-sb-loading { padding:40px 20px; text-align:center; color:var(--text-muted); font-size:13px; }

/* Thread items in sidebar */
.sb-thread-item { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
.sb-thread-item:hover { background:var(--soil-hover); }
.sb-thread-item.unread .sb-t-name { font-weight:700; color:var(--text-light); }
.sb-t-avatar { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
.sb-t-avatar img { width:100%; height:100%; object-fit:cover; }
.sb-t-body { flex:1; min-width:0; }
.sb-t-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:2px; }
.sb-t-name { font-size:13px; font-weight:600; color:var(--text-mid); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sb-t-time { font-size:10px; color:var(--text-muted); flex-shrink:0; margin-left:6px; }
.sb-t-preview { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sb-t-job { font-size:11px; color:var(--red-pale); margin-top:2px; }
.sb-unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); position:absolute; top:14px; right:12px; }

/* Chat view in sidebar */
.msg-sb-chat { display:flex; flex-direction:column; flex:1; overflow:hidden; }
.msg-sb-chat-head { display:flex; align-items:center; gap:10px; padding:12px 14px; border-bottom:1px solid var(--soil-line); }
.msg-sb-chat-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
.msg-sb-chat-avatar img { width:100%; height:100%; object-fit:cover; }
.msg-sb-chat-name { font-size:14px; font-weight:700; color:var(--text-light); }
.msg-sb-chat-meta { font-size:11px; color:var(--text-muted); }
.msg-sb-messages { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px; scrollbar-width:thin; }
.msg-sb-input { padding:10px 14px; border-top:1px solid var(--soil-line); }
.msg-sb-input-row { display:flex; align-items:flex-end; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 10px; transition:0.2s; }
.msg-sb-input-row:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.1); }
.msg-sb-input-row textarea { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:none; min-height:32px; max-height:80px; line-height:1.4; }
.msg-sb-input-row textarea::placeholder { color:var(--text-muted); }
.msg-sb-send { width:32px; height:32px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:12px; transition:0.2s; flex-shrink:0; }
.msg-sb-send:hover { background:var(--red-bright); transform:scale(1.05); }

/* New Chat Search Panels */
.sb-new-chat-panel { padding:10px 14px; border-bottom:1px solid var(--soil-line); }
.sb-new-chat-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--red-vivid); border-radius:8px; padding:8px 12px; }
.sb-new-chat-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
.sb-new-chat-bar input::placeholder { color:var(--text-muted); }
.sb-new-chat-bar i { color:var(--red-bright); font-size:13px; }
.sb-new-chat-results { max-height:180px; overflow-y:auto; margin-top:6px; scrollbar-width:thin; }
.sb-new-chat-user { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:0.15s; }
.sb-new-chat-user:hover { background:var(--soil-hover); }
.sb-new-chat-user-av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
.sb-new-chat-user-name { font-size:13px; font-weight:600; color:var(--text-light); }
.sb-new-chat-user-type { font-size:11px; color:var(--text-muted); text-transform:capitalize; }
.sb-new-chat-empty { text-align:center; padding:12px; font-size:12px; color:var(--text-muted); }
.fs-new-chat-panel { padding:8px 12px; border-bottom:1px solid var(--soil-line); }

/* Message bubbles (shared) */
.sb-msg-date { text-align:center; font-size:10px; color:var(--text-muted); padding:6px 0; }
.sb-msg-row { display:flex; gap:6px; align-items:flex-end; }
.sb-msg-row.sent { flex-direction:row-reverse; }
.sb-bubble { max-width:80%; min-width:48px; padding:8px 12px; border-radius:12px; font-size:13px; line-height:1.45; word-break:break-word; white-space:pre-wrap; }
.sb-bubble-recv { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
.sb-bubble-sent { background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
.sb-bubble-time { font-size:9px; margin-top:3px; opacity:0.6; }
.sb-bubble-sent .sb-bubble-time { text-align:right; }

/* Empty state */
.sb-empty { padding:40px 20px; text-align:center; color:var(--text-muted); }
.sb-empty i { font-size:28px; display:block; margin-bottom:10px; color:var(--soil-line); }

/* ── NOTIFICATION SIDEBAR ───────────────────────────────────────────── */
.notif-sidebar { position:fixed; top:0; right:0; bottom:0; width:380px; max-width:100vw; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:500; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
.notif-sidebar.open { transform:translateX(0); }
.notif-sb-head { padding:18px 18px 14px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
.notif-sb-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:8px; }
.notif-sb-title i { color:var(--red-bright); }
.notif-sb-close, .notif-sb-mark-all { width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
.notif-sb-close:hover, .notif-sb-mark-all:hover { color:var(--text-light); border-color:var(--red-vivid); }
.notif-sb-body { flex:1; overflow-y:auto; padding:12px 16px; scrollbar-width:thin; }
.notif-sb-item { display:flex; gap:10px; padding:12px 0; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; }
.notif-sb-item:last-child { border-bottom:none; }
.notif-sb-item:hover { opacity:0.85; }
.notif-sb-item.unread { padding-left:4px; border-left:2px solid var(--red-vivid); }
.nsb-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:5px; }
.nsb-dot.message { background:var(--red-vivid); }
.nsb-dot.application { background:var(--amber); }
.nsb-dot.interview { background:#4CAF70; }
.nsb-dot.general { background:var(--blue, #4A90D9); }
.nsb-dot.read { background:var(--soil-line); }
.nsb-text { font-size:13px; color:var(--text-mid); line-height:1.5; }
.nsb-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }

/* ── FULLSCREEN CHAT ────────────────────────────────────────────────── */
.fs-chat-overlay { position:fixed; inset:0; z-index:600; background:rgba(0,0,0,0.85); backdrop-filter:blur(8px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.3s; }
.fs-chat-overlay.open { opacity:1; pointer-events:auto; }
.fs-chat-container { width:95vw; max-width:1200px; height:90vh; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; display:flex; flex-direction:column; overflow:hidden; animation:fsIn 0.35s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6); }
@keyframes fsIn { from{opacity:0;transform:scale(0.95) translateY(12px)} to{opacity:1;transform:scale(1)} }
.fs-chat-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--soil-line); flex-shrink:0; }
.fs-chat-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:10px; }
.fs-chat-title i { color:var(--red-bright); }
.fs-chat-close { width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; transition:0.15s; }
.fs-chat-close:hover { color:var(--text-light); border-color:var(--red-vivid); }
.fs-chat-body { display:grid; grid-template-columns:320px 1fr; flex:1; overflow:hidden; }

/* Fullscreen thread panel */
.fs-thread-panel { border-right:1px solid var(--soil-line); display:flex; flex-direction:column; overflow:hidden; }
.fs-thread-search { padding:12px 14px; border-bottom:1px solid var(--soil-line); }
.fs-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 10px; }
.fs-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
.fs-search-bar input::placeholder { color:var(--text-muted); }
.fs-search-bar i { color:var(--text-muted); font-size:12px; }
.fs-thread-filters { display:flex; gap:6px; padding:8px 14px; border-bottom:1px solid var(--soil-line); }
.fs-tf { padding:3px 10px; border-radius:16px; font-size:11px; font-weight:600; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
.fs-tf.active, .fs-tf:hover { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
.fs-thread-list { flex:1; overflow-y:auto; scrollbar-width:thin; }
.fs-t-item { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
.fs-t-item:hover { background:var(--soil-hover); }
.fs-t-item.active { background:rgba(209,61,44,0.08); border-left:2px solid var(--red-vivid); }
.fs-t-item.unread .fs-t-name { font-weight:700; color:var(--text-light); }
.fs-t-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
.fs-t-avatar img { width:100%; height:100%; object-fit:cover; }
.fs-t-body { flex:1; min-width:0; }
.fs-t-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:2px; }
.fs-t-name { font-size:13px; font-weight:600; color:var(--text-mid); }
.fs-t-time { font-size:10px; color:var(--text-muted); }
.fs-t-preview { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fs-t-job { font-size:11px; color:var(--red-pale); margin-top:1px; }
.fs-t-unread { position:absolute; top:14px; right:12px; width:8px; height:8px; border-radius:50%; background:var(--red-vivid); }

/* Fullscreen chat panel */
.fs-chat-panel { display:flex; flex-direction:column; overflow:hidden; }
.fs-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; }
.fs-empty-icon { width:56px; height:56px; border-radius:50%; background:var(--soil-hover); display:flex; align-items:center; justify-content:center; font-size:22px; color:var(--text-muted); }
.fs-active-chat { display:flex; flex-direction:column; height:100%; }
.fs-chat-head2 { display:flex; align-items:center; gap:12px; padding:14px 20px; border-bottom:1px solid var(--soil-line); flex-shrink:0; }
.fs-ch-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
.fs-ch-avatar img { width:100%; height:100%; object-fit:cover; }
.fs-ch-name { font-size:15px; font-weight:700; color:var(--text-light); }
.fs-ch-meta { font-size:12px; color:var(--text-muted); }
.fs-ch-info { flex:1; }
.fs-ch-status { display:inline-flex; align-items:center; padding:3px 10px; border-radius:16px; font-size:11px; font-weight:600; }
.fs-ch-status.Pending { background:rgba(74,144,217,0.15); color:#4A90D9; }
.fs-ch-status.Shortlisted { background:rgba(212,148,58,0.15); color:var(--amber); }
.fs-ch-status.Hired { background:rgba(76,175,112,0.15); color:#4CAF70; }
.fs-ch-status.Reviewed { background:rgba(156,39,176,0.15); color:#cf8ae0; }
.fs-ch-status.Rejected { background:rgba(224,85,85,0.15); color:#E05555; }

.fs-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:12px; scrollbar-width:thin; }
.fs-msg-date { text-align:center; font-size:11px; color:var(--text-muted); position:relative; }
.fs-msg-date::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background:var(--soil-line); }
.fs-msg-date span { background:var(--soil-card); padding:0 10px; position:relative; }
.fs-msg-row { display:flex; gap:8px; align-items:flex-end; max-width:100%; }
.fs-msg-row.sent { flex-direction:row-reverse; }
.fs-msg-row > div:not(.fs-msg-avatar) { min-width:0; max-width:calc(100% - 40px); }
.fs-msg-avatar { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
.fs-msg-avatar img { width:100%; height:100%; object-fit:cover; }
.fs-bubble { max-width:70%; min-width:60px; padding:10px 16px; border-radius:12px; font-size:13px; line-height:1.5; word-break:break-word; white-space:pre-wrap; }
.fs-bubble-recv { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
.fs-bubble-sent { background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
.fs-bubble-time { font-size:10px; margin-top:3px; opacity:0.6; }
.fs-bubble-sent .fs-bubble-time { text-align:right; }

.fs-input-area { padding:14px 20px; border-top:1px solid var(--soil-line); flex-shrink:0; }
.fs-input-row { display:flex; align-items:flex-end; gap:10px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:10px 14px; transition:0.2s; }
.fs-input-row:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.1); }
.fs-input-row textarea { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:none; min-height:36px; max-height:120px; line-height:1.5; }
.fs-input-row textarea::placeholder { color:var(--text-muted); }
.fs-send-btn { width:36px; height:36px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.2s; flex-shrink:0; }
.fs-send-btn:hover { background:var(--red-bright); transform:scale(1.05); }

/* Toast */
.chat-toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px); background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:10px 18px; font-size:13px; font-weight:600; color:var(--text-light); display:flex; align-items:center; gap:8px; box-shadow:0 8px 32px rgba(0,0,0,0.4); opacity:0; transition:all 0.3s; z-index:999; pointer-events:none; }
.chat-toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
.chat-toast i { color:var(--red-bright); }

/* Light theme overrides */
body.light .msg-sidebar, body.light .notif-sidebar { background:#FFFFFF; border-color:#E0CECA; box-shadow:-8px 0 32px rgba(0,0,0,0.1); }
body.light .fs-chat-container { background:#FFFFFF; border-color:#E0CECA; }
body.light .sb-bubble-recv, body.light .fs-bubble-recv { background:#F5EEEC; color:#1A0A09; }
body.light .msg-sb-search-bar, body.light .fs-search-bar, body.light .msg-sb-input-row, body.light .fs-input-row { background:#F5EEEC; border-color:#E0CECA; }
body.light .msg-sb-search-bar input, body.light .fs-search-bar input, body.light .msg-sb-input-row textarea, body.light .fs-input-row textarea { color:#1A0A09; }
body.light .sb-thread-item:hover, body.light .fs-t-item:hover { background:#FEF0EE; }
body.light .sb-thread-item { border-bottom-color:#E0CECA; }
body.light .fs-t-item { border-bottom-color:#E0CECA; }
body.light .sb-t-name, body.light .fs-t-name { color:#4A2828; }
body.light .sb-thread-item.unread .sb-t-name, body.light .fs-t-item.unread .fs-t-name { color:#1A0A09; }
body.light .msg-sb-head, body.light .notif-sb-head, body.light .fs-chat-header { border-bottom-color:#E0CECA; }
body.light .msg-sb-title, body.light .notif-sb-title, body.light .fs-chat-title { color:#1A0A09; }
body.light .msg-sb-chat-name, body.light .fs-ch-name { color:#1A0A09; }
body.light .msg-sb-chat-head, body.light .fs-chat-head2 { border-bottom-color:#E0CECA; }
body.light .msg-sb-input, body.light .fs-input-area { border-top-color:#E0CECA; }
body.light .fs-thread-panel { border-right-color:#E0CECA; }
body.light .fs-thread-search, body.light .fs-thread-filters { border-bottom-color:#E0CECA; }
body.light .sb-new-chat-bar { border-color:var(--red-vivid); }
body.light .sb-new-chat-user-name { color:#1A0A09; }
body.light .chat-toast { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }
body.light .notif-sb-body { color:#4A2828; }
body.light .notif-sb-item { border-bottom-color:#E0CECA; }
body.light .nsb-text { color:#3A2020; }
body.light .msg-sb-close, body.light .msg-sb-expand, body.light .msg-sb-back,
body.light .notif-sb-close, body.light .notif-sb-mark-all, body.light .fs-chat-close { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
body.light .fs-tf { border-color:#E0CECA; color:#7A5555; }
body.light .fs-tf.active, body.light .fs-tf:hover { color:var(--red-bright); background:rgba(209,61,44,0.08); }

@media(max-width:768px) {
  .msg-sidebar, .notif-sidebar { width:100%; max-width:100%; }
  .fs-chat-container { width:100vw; height:100vh; border-radius:0; }
  .fs-chat-body { grid-template-columns:1fr; }
  .fs-thread-panel { display:none; }
  .fs-thread-panel.mobile-show { display:flex; position:absolute; inset:0; z-index:2; background:var(--soil-card); }
}
</style>

<script>
/* ═══════════════════════════════════════════════════════════════════════
   CHAT SYSTEM — JavaScript
   ═══════════════════════════════════════════════════════════════════════ */
const API_URL = '../api/messages.php';
const MY_INITIALS = <?= json_encode($_chatInitials) ?>;
const MY_AVATAR_URL = <?= json_encode($_chatAvatarUrl) ?>;
let _threads = [];
let _sbActivePartner = null;
let _fsActivePartner = null;
let _pollTimer = null;
let _msgPollTimer = null;

// ── SIDEBAR CONTROLS ──────────────────────────────────────────────────
function openMsgSidebar() {
    closeSidebars();
    document.getElementById('msgSidebar').classList.add('open');
    sbBackToThreads();
    loadThreads();
}
function closeMsgSidebar() {
    document.getElementById('msgSidebar').classList.remove('open');
    _sbActivePartner = null;
    stopMsgPoll();
}
function openNotifSidebar() {
    closeSidebars();
    document.getElementById('notifSidebar').classList.add('open');
    loadNotifications();
}
function closeNotifSidebar() {
    document.getElementById('notifSidebar').classList.remove('open');
}
function closeSidebars() {
    closeMsgSidebar();
    closeNotifSidebar();
}

// ── FULLSCREEN CONTROLS ───────────────────────────────────────────────
function openFullscreenChat(partnerId) {
    closeSidebars();
    document.getElementById('fsChatOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    loadThreads(function() {
        if (partnerId) fsOpenThread(partnerId);
    });
}
function closeFullscreenChat() {
    document.getElementById('fsChatOverlay').classList.remove('open');
    document.body.style.overflow = '';
    _fsActivePartner = null;
    stopMsgPoll();
    document.getElementById('fsEmpty').style.display = 'flex';
    document.getElementById('fsActiveChat').style.display = 'none';
}

// ── LOAD THREADS ──────────────────────────────────────────────────────
function loadThreads(callback) {
    fetch(API_URL + '?action=threads')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                _threads = data.threads;
                renderSbThreads(_threads);
                renderFsThreads(_threads);
            }
            if (callback) callback();
        })
        .catch(e => console.error('Load threads error:', e));
}

function renderSbThreads(list) {
    const el = document.getElementById('sbThreadList');
    if (!list.length) {
        el.innerHTML = '<div class="sb-empty"><i class="fas fa-inbox"></i>No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(t => `
        <div class="sb-thread-item${t.unread_count > 0 ? ' unread' : ''}" onclick="sbOpenThread(${t.partner_id})">
            <div class="sb-t-avatar" style="background:${t.color}">${t.avatar_url ? `<img src="../${t.avatar_url}" alt="">` : t.initials}</div>
            <div class="sb-t-body">
                <div class="sb-t-top">
                    <div class="sb-t-name">${esc(t.name)}</div>
                    <div class="sb-t-time">${t.time}</div>
                </div>
                <div class="sb-t-preview">${t.is_sent ? 'You: ' : ''}${esc(t.preview)}</div>
                ${t.job_title ? `<div class="sb-t-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ${esc(t.job_title)}</div>` : ''}
            </div>
            ${t.unread_count > 0 ? '<div class="sb-unread-dot"></div>' : ''}
        </div>
    `).join('');
}

function renderFsThreads(list) {
    const el = document.getElementById('fsThreadList');
    if (!list.length) {
        el.innerHTML = '<div class="sb-empty"><i class="fas fa-inbox"></i>No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(t => `
        <div class="fs-t-item${t.unread_count > 0 ? ' unread' : ''}${_fsActivePartner === t.partner_id ? ' active' : ''}" onclick="fsOpenThread(${t.partner_id})">
            <div class="fs-t-avatar" style="background:${t.color}">${t.avatar_url ? `<img src="../${t.avatar_url}" alt="">` : t.initials}</div>
            <div class="fs-t-body">
                <div class="fs-t-top">
                    <div class="fs-t-name">${esc(t.name)}</div>
                    <div class="fs-t-time">${t.time}</div>
                </div>
                <div class="fs-t-preview">${t.is_sent ? 'You: ' : ''}${esc(t.preview)}</div>
                ${t.job_title ? `<div class="fs-t-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ${esc(t.job_title)}</div>` : ''}
            </div>
            ${t.unread_count > 0 ? '<div class="fs-t-unread"></div>' : ''}
        </div>
    `).join('');
}

// ── SIDEBAR: Open Thread ──────────────────────────────────────────────
// v2-debug-20250704
function sbOpenThread(partnerId) {
    console.log('[CHAT DEBUG] sbOpenThread called, partnerId=' + partnerId);
    try {
        _sbActivePartner = partnerId;
        var threadList = document.getElementById('sbThreadList');
        console.log('[CHAT DEBUG] sbThreadList:', threadList);
        if (threadList) threadList.style.display = 'none';
        var searchEl = document.querySelector('.msg-sb-search');
        console.log('[CHAT DEBUG] searchEl:', searchEl);
        if (searchEl) searchEl.style.display = 'none';
        var sbNewPanel = document.getElementById('sbNewChatPanel');
        if (sbNewPanel) sbNewPanel.style.display = 'none';
        var chatView = document.getElementById('sbChatView');
        console.log('[CHAT DEBUG] sbChatView:', chatView);
        if (chatView) chatView.style.display = 'flex';
        console.log('[CHAT DEBUG] sbChatView.display is now:', chatView ? chatView.style.display : 'NOT FOUND');
        loadConversation(partnerId, 'sidebar');
        startMsgPoll(partnerId, 'sidebar');
    } catch(err) {
        console.error('[CHAT DEBUG] ERROR in sbOpenThread:', err);
        alert('sbOpenThread error: ' + err.message);
    }
}

function sbBackToThreads() {
    _sbActivePartner = null;
    stopMsgPoll();
    document.getElementById('sbChatView').style.display = 'none';
    document.getElementById('sbThreadList').style.display = '';
    var searchEl = document.querySelector('.msg-sb-search');
    if (searchEl) searchEl.style.display = '';
    loadThreads();
}

// ── FULLSCREEN: Open Thread ───────────────────────────────────────────
function fsOpenThread(partnerId) {
    _fsActivePartner = partnerId;
    var fsNewPanel = document.getElementById('fsNewChatPanel');
    if (fsNewPanel) fsNewPanel.style.display = 'none';
    document.getElementById('fsEmpty').style.display = 'none';
    document.getElementById('fsActiveChat').style.display = 'flex';
    renderFsThreads(_threads); // re-render to show active
    loadConversation(partnerId, 'fullscreen');
    startMsgPoll(partnerId, 'fullscreen');
}

// ── LOAD CONVERSATION ─────────────────────────────────────────────────
function loadConversation(partnerId, mode) {
    fetch(API_URL + '?action=messages&user_id=' + partnerId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                if (mode === 'sidebar') {
                    document.getElementById('sbChatMessages').innerHTML = '<div class="sb-empty"><i class="fas fa-exclamation-circle"></i>' + (data.message || 'Could not load conversation') + '</div>';
                    document.getElementById('sbChatName').textContent = 'Error';
                    document.getElementById('sbChatMeta').textContent = '';
                } else {
                    document.getElementById('fsChatMessages').innerHTML = '<div class="sb-empty" style="height:100%;display:flex;flex-direction:column;justify-content:center;"><i class="fas fa-exclamation-circle"></i>' + (data.message || 'Could not load conversation') + '</div>';
                    document.getElementById('fsChatName').textContent = 'Error';
                    document.getElementById('fsChatMeta').textContent = '';
                }
                return;
            }
            const t = _threads.find(x => x.partner_id === partnerId);
            const color = t ? t.color : '#4A90D9';
            const pName = (data.partner && data.partner.name) ? data.partner.name : 'User';
            const pParts = pName.split(/\s+/);
            const defaultIni = pParts.length >= 2 ? (pParts[0][0]+pParts[1][0]).toUpperCase() : pName.substring(0,2).toUpperCase();
            const ini = t ? t.initials : defaultIni;
            const partnerAvUrl = (t && t.avatar_url) ? t.avatar_url : ((data.partner && data.partner.avatar_url) ? data.partner.avatar_url : null);
            const partner = data.partner || {name: 'User'};
            const job = data.job;

            if (mode === 'sidebar') {
                document.getElementById('sbChatAvatar').style.background = color;
                if (partnerAvUrl) {
                    document.getElementById('sbChatAvatar').innerHTML = `<img src="../${partnerAvUrl}" alt="">`;
                } else {
                    document.getElementById('sbChatAvatar').textContent = ini;
                }
                document.getElementById('sbChatName').textContent = partner.name;
                document.getElementById('sbChatMeta').textContent = job ? job.title : '';
                renderSbMessages(data.messages, color, ini);
            } else {
                document.getElementById('fsChatAvatar').style.background = color;
                if (partnerAvUrl) {
                    document.getElementById('fsChatAvatar').innerHTML = `<img src="../${partnerAvUrl}" alt="">`;
                } else {
                    document.getElementById('fsChatAvatar').textContent = ini;
                }
                document.getElementById('fsChatName').textContent = partner.name;
                document.getElementById('fsChatMeta').textContent = job ? job.title : '';
                const statusEl = document.getElementById('fsChatStatus');
                if (job && job.status) {
                    statusEl.textContent = job.status;
                    statusEl.className = 'fs-ch-status ' + job.status;
                    statusEl.style.display = '';
                } else {
                    statusEl.style.display = 'none';
                }
                renderFsMessages(data.messages, color, ini, partnerAvUrl);
            }
            // Update thread unread count
            if (t) t.unread_count = 0;
            updateBadges();
        })
        .catch(e => console.error('Load conversation error:', e));
}

// ── RENDER MESSAGES ───────────────────────────────────────────────────
function renderSbMessages(msgs, color, ini) {
    const el = document.getElementById('sbChatMessages');
    el.innerHTML = '';
    msgs.forEach(m => {
        if (m.show_date) el.innerHTML += `<div class="sb-msg-date">${m.date}</div>`;
        if (m.from === 'me') {
            el.innerHTML += `<div class="sb-msg-row sent"><div class="sb-bubble sb-bubble-sent">${esc(m.body)}<div class="sb-bubble-time">${m.time} <i class="fas fa-check-double" style="font-size:9px;"></i></div></div></div>`;
        } else {
            el.innerHTML += `<div class="sb-msg-row"><div class="sb-bubble sb-bubble-recv">${esc(m.body)}<div class="sb-bubble-time">${m.time}</div></div></div>`;
        }
    });
    if (!msgs.length) el.innerHTML = '<div class="sb-empty"><i class="fas fa-comment-dots"></i>Start the conversation</div>';
    el.scrollTop = el.scrollHeight;
}

function renderFsMessages(msgs, color, ini, partnerAvUrl) {
    const el = document.getElementById('fsChatMessages');
    el.innerHTML = '';
    msgs.forEach(m => {
        if (m.show_date) el.innerHTML += `<div class="fs-msg-date"><span>${m.date}</span></div>`;
        if (m.from === 'me') {
            el.innerHTML += `<div class="fs-msg-row sent">
                <div class="fs-msg-avatar" style="background:linear-gradient(135deg,#D4943A,#8a5010)">${MY_AVATAR_URL ? `<img src="${MY_AVATAR_URL}" alt="">` : MY_INITIALS}</div>
                <div class="fs-bubble fs-bubble-sent">${esc(m.body)}<div class="fs-bubble-time">${m.time} <i class="fas fa-check-double" style="font-size:9px;"></i></div></div>
            </div>`;
        } else {
            el.innerHTML += `<div class="fs-msg-row">
                <div class="fs-msg-avatar" style="background:${color}">${partnerAvUrl ? `<img src="../${partnerAvUrl}" alt="">` : ini}</div>
                <div class="fs-bubble fs-bubble-recv">${esc(m.body)}<div class="fs-bubble-time">${m.time}</div></div>
            </div>`;
        }
    });
    if (!msgs.length) el.innerHTML = '<div class="sb-empty" style="height:100%;display:flex;flex-direction:column;justify-content:center;"><i class="fas fa-comment-dots"></i>Start the conversation</div>';
    el.scrollTop = el.scrollHeight;
}

// ── SEND MESSAGE ──────────────────────────────────────────────────────
function sbSendMessage() {
    const input = document.getElementById('sbMsgInput');
    const text = input.value.trim();
    if (!text || !_sbActivePartner) return;
    input.value = '';
    sendMsg(_sbActivePartner, text, 'sidebar');
}

function fsSendMessage() {
    const input = document.getElementById('fsMsgInput');
    const text = input.value.trim();
    if (!text || !_fsActivePartner) return;
    input.value = '';
    input.style.height = 'auto';
    sendMsg(_fsActivePartner, text, 'fullscreen');
}

function sendMsg(receiverId, text, mode) {
    fetch(API_URL + '?action=send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({receiver_id: receiverId, message: text})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadConversation(receiverId, mode);
            loadThreads(); // refresh thread list
            showChatToast('Message sent');
        }
    })
    .catch(e => console.error('Send error:', e));
}

// ── NOTIFICATIONS ─────────────────────────────────────────────────────
function loadNotifications() {
    fetch(API_URL + '?action=notifications')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('notifSbBody');
            if (!data.success || !data.notifications.length) {
                el.innerHTML = '<div class="sb-empty"><i class="fas fa-bell-slash"></i>No notifications yet</div>';
                return;
            }
            el.innerHTML = data.notifications.map(n => `
                <div class="notif-sb-item${n.is_read ? '' : ' unread'}" onclick="markNotifRead(${n.id}, this)">
                    <div class="nsb-dot ${n.is_read ? 'read' : n.type}"></div>
                    <div>
                        <div class="nsb-text">${n.content}</div>
                        <div class="nsb-time">${n.time}</div>
                    </div>
                </div>
            `).join('');
        })
        .catch(e => console.error('Notifications error:', e));
}

function markNotifRead(id, el) {
    fetch(API_URL + '?action=mark_notif_read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    }).then(() => {
        if (el) {
            el.classList.remove('unread');
            const dot = el.querySelector('.nsb-dot');
            if (dot) dot.className = 'nsb-dot read';
        }
        updateBadges();
    });
}

function markAllNotifsRead() {
    fetch(API_URL + '?action=mark_notif_read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: 0})
    }).then(() => {
        loadNotifications();
        updateBadges();
        showChatToast('All notifications marked as read');
    });
}

// ── BADGE UPDATES ─────────────────────────────────────────────────────
function updateBadges() {
    fetch(API_URL + '?action=unread_count')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            // Update all message badges
            document.querySelectorAll('.msg-badge-count').forEach(el => {
                el.textContent = data.messages;
                el.style.display = data.messages > 0 ? 'flex' : 'none';
            });
            // Update all notification badges
            document.querySelectorAll('.notif-badge-count').forEach(el => {
                el.textContent = data.notifications;
                el.style.display = data.notifications > 0 ? 'flex' : 'none';
            });
        })
        .catch(() => {});
}

// ── POLLING ───────────────────────────────────────────────────────────
function startPolling() {
    if (_pollTimer) return;
    _pollTimer = setInterval(() => {
        updateBadges();
        // If sidebar is open, refresh threads
        if (document.getElementById('msgSidebar').classList.contains('open') && !_sbActivePartner) {
            loadThreads();
        }
    }, 5000);
}

function startMsgPoll(partnerId, mode) {
    stopMsgPoll();
    _msgPollTimer = setInterval(() => {
        loadConversation(partnerId, mode);
    }, 4000);
}

function stopMsgPoll() {
    if (_msgPollTimer) { clearInterval(_msgPollTimer); _msgPollTimer = null; }
}

// ── SEARCH FILTERS ────────────────────────────────────────────────────
function filterSidebarThreads() {
    const q = document.getElementById('sbThreadSearch').value.toLowerCase();
    const filtered = _threads.filter(t => t.name.toLowerCase().includes(q) || (t.job_title || '').toLowerCase().includes(q));
    renderSbThreads(filtered);
}

function filterFsThreads() {
    const q = document.getElementById('fsThreadSearch').value.toLowerCase();
    const filtered = _threads.filter(t => t.name.toLowerCase().includes(q) || (t.job_title || '').toLowerCase().includes(q));
    renderFsThreads(filtered);
}

function fsTfFilter(type, btn) {
    document.querySelectorAll('.fs-tf').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    let list = _threads;
    if (type === 'unread') list = _threads.filter(t => t.unread_count > 0);
    renderFsThreads(list);
}

// ── HELPERS ───────────────────────────────────────────────────────────
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function showChatToast(msg) {
    const t = document.getElementById('chatToast');
    document.getElementById('chatToastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

// ── NEW CONVERSATION (Sidebar) ────────────────────────────────────────
let _sbNewChatTimeout = null;
function toggleSbNewChat() {
    const p = document.getElementById('sbNewChatPanel');
    if (p.style.display === 'none') {
        p.style.display = 'block';
        document.getElementById('sbNewChatSearch').value = '';
        document.getElementById('sbNewChatResults').innerHTML = '<div class="sb-new-chat-empty">Type a name to search</div>';
        setTimeout(() => document.getElementById('sbNewChatSearch').focus(), 100);
    } else {
        p.style.display = 'none';
    }
}
function sbSearchNewChat() {
    const q = document.getElementById('sbNewChatSearch').value.trim();
    const res = document.getElementById('sbNewChatResults');
    if (q.length < 2) { res.innerHTML = '<div class="sb-new-chat-empty">Type at least 2 characters</div>'; return; }
    if (_sbNewChatTimeout) clearTimeout(_sbNewChatTimeout);
    _sbNewChatTimeout = setTimeout(() => {
        res.innerHTML = '<div class="sb-new-chat-empty"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        fetch(API_URL + '?action=search_users&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.users.length) { res.innerHTML = '<div class="sb-new-chat-empty">No users found</div>'; return; }
                const colors = ['#4A90D9','#D4943A','#4CAF70','#9C27B0','#E05555','#00897B','#5C6BC0','#F4511E'];
                res.innerHTML = data.users.map((u, i) => `
                    <div class="sb-new-chat-user" onclick="sbStartNewChat(${u.id})">
                        <div class="sb-new-chat-user-av" style="background:${colors[i % colors.length]}">${u.avatar_url ? `<img src="../${u.avatar_url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">` : esc(u.initials)}</div>
                        <div><div class="sb-new-chat-user-name">${esc(u.name)}</div><div class="sb-new-chat-user-type">${esc(u.type)}</div></div>
                    </div>
                `).join('');
            })
            .catch(() => { res.innerHTML = '<div class="sb-new-chat-empty">Search failed</div>'; });
    }, 300);
}
function sbStartNewChat(userId) {
    document.getElementById('sbNewChatPanel').style.display = 'none';
    sbOpenThread(userId);
}

// ── NEW CONVERSATION (Fullscreen) ─────────────────────────────────────
let _fsNewChatTimeout = null;
function toggleFsNewChat() {
    const p = document.getElementById('fsNewChatPanel');
    if (p.style.display === 'none') {
        p.style.display = 'block';
        document.getElementById('fsNewChatSearch').value = '';
        document.getElementById('fsNewChatResults').innerHTML = '<div class="sb-new-chat-empty">Type a name to search</div>';
        setTimeout(() => document.getElementById('fsNewChatSearch').focus(), 100);
    } else {
        p.style.display = 'none';
    }
}
function fsSearchNewChat() {
    const q = document.getElementById('fsNewChatSearch').value.trim();
    const res = document.getElementById('fsNewChatResults');
    if (q.length < 2) { res.innerHTML = '<div class="sb-new-chat-empty">Type at least 2 characters</div>'; return; }
    if (_fsNewChatTimeout) clearTimeout(_fsNewChatTimeout);
    _fsNewChatTimeout = setTimeout(() => {
        res.innerHTML = '<div class="sb-new-chat-empty"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        fetch(API_URL + '?action=search_users&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.users.length) { res.innerHTML = '<div class="sb-new-chat-empty">No users found</div>'; return; }
                const colors = ['#4A90D9','#D4943A','#4CAF70','#9C27B0','#E05555','#00897B','#5C6BC0','#F4511E'];
                res.innerHTML = data.users.map((u, i) => `
                    <div class="sb-new-chat-user" onclick="fsStartNewChat(${u.id})">
                        <div class="sb-new-chat-user-av" style="background:${colors[i % colors.length]}">${u.avatar_url ? `<img src="../${u.avatar_url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">` : esc(u.initials)}</div>
                        <div><div class="sb-new-chat-user-name">${esc(u.name)}</div><div class="sb-new-chat-user-type">${esc(u.type)}</div></div>
                    </div>
                `).join('');
            })
            .catch(() => { res.innerHTML = '<div class="sb-new-chat-empty">Search failed</div>'; });
    }, 300);
}
function fsStartNewChat(userId) {
    document.getElementById('fsNewChatPanel').style.display = 'none';
    fsOpenThread(userId);
}

// ── INIT ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    updateBadges();
    startPolling();

    // Click-outside-to-close for sidebars (matches seeker/recruiter behavior)
    document.addEventListener('click', function(e) {
        var msgSb = document.getElementById('msgSidebar');
        var notifSb = document.getElementById('notifSidebar');
        var navMsgBtn = document.getElementById('navMsgBtn');
        var navNotifBtn = document.getElementById('navNotifBtn');
        if (msgSb && msgSb.classList.contains('open') && !msgSb.contains(e.target) && (!navMsgBtn || !navMsgBtn.contains(e.target))) {
            closeMsgSidebar();
        }
        if (notifSb && notifSb.classList.contains('open') && !notifSb.contains(e.target) && (!navNotifBtn || !navNotifBtn.contains(e.target))) {
            closeNotifSidebar();
        }
    });

    // DEBUG: Listen for clicks on thread list
    var tl = document.getElementById('sbThreadList');
    if (tl) {
        tl.addEventListener('click', function(e) {
            console.log('[CHAT DEBUG] Click inside sbThreadList, target:', e.target, 'tagName:', e.target.tagName, 'className:', e.target.className);
            var threadItem = e.target.closest('.sb-thread-item');
            if (threadItem) {
                console.log('[CHAT DEBUG] Found thread item, onclick attr:', threadItem.getAttribute('onclick'));
            } else {
                console.log('[CHAT DEBUG] Click NOT on a .sb-thread-item');
            }
        });
    } else {
        console.error('[CHAT DEBUG] sbThreadList NOT FOUND in DOM!');
    }
    console.log('[CHAT DEBUG] Chat system initialized. sbOpenThread type:', typeof sbOpenThread);
});
</script>
