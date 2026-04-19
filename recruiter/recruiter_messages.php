<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('recruiter');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'messages';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

$msgCount   = 0;
$notifCount = 0;
try {
    $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
    $s->execute([$uid]);
    $msgCount = (int)$s->fetchColumn();
} catch (PDOException $e) {}
try {
    $db->query("SELECT 1 FROM notifications LIMIT 0");
    $s = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$uid]);
    $notifCount = (int)$s->fetchColumn();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Messages</title>
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
    body.light { --soil-dark:#F9F5F4; --soil-med:#F1ECEB; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA; --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; }
    body.light .glow-orb { opacity:0.04; }

    /* GLOW ORBS */
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* PAGE */
    .page-shell { max-width:1380px; margin:0 auto; padding:32px 24px 80px; position:relative; z-index:1; }
    .page-header { margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text-light); }
    .page-title span { color:var(--red-bright); font-style:italic; }
    body.light .page-title { color:#1A0A09; }
    .page-sub { font-size:14px; color:var(--text-muted); margin-top:4px; }
    body.light .page-sub { color:#7A5555; }

    /* MESSAGES LAYOUT */
    .msg-layout { display:grid; grid-template-columns:320px 1fr; gap:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; height:calc(100vh - 160px); min-height:680px; }
    body.light .msg-layout { background:#FFFFFF; border-color:#E0CECA; }

    /* CONVERSATION LIST */
    .thread-list { border-right:1px solid var(--soil-line); display:flex; flex-direction:column; overflow:hidden; }
    body.light .thread-list { border-right-color:#E0CECA; }
    .thread-search { padding:14px 16px; border-bottom:1px solid var(--soil-line); }
    .thread-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 12px; }
    body.light .thread-search-bar { background:#F5EEEC; border-color:#E0CECA; }
    .thread-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
    .thread-search-bar input::placeholder { color:var(--text-muted); }
    .thread-filters { display:flex; gap:6px; padding:10px 16px; border-bottom:1px solid var(--soil-line); }
    .tf-pill { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .tf-pill.active, .tf-pill:hover { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    body.light .tf-pill { color:#7A5555; }
    body.light .tf-pill.active { color:var(--red-bright); background:rgba(209,61,44,0.08); }
    body.light .tf-pill:hover:not(.active) { color:#1A0A09; background:#FEF0EE; }
    .threads-scroll { flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
    .thread-item { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
    .thread-item:hover { background:var(--soil-hover); }
    .thread-item.active { background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .thread-item.unread .t-name { color:var(--text-light); font-weight:700; }
    .thread-item.unread .t-preview { color:var(--text-mid); }
    body.light .thread-item { border-bottom-color:#E0CECA; }
    body.light .thread-item:hover { background:#FEF0EE; }
    body.light .thread-item.active { background:rgba(209,61,44,0.06); }
    .t-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .t-avatar img { width:100%; height:100%; object-fit:cover; }
    .t-body { flex:1; min-width:0; }
    .t-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
    .t-name { font-size:13px; font-weight:600; color:var(--text-mid); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    body.light .t-name { color:#4A2828; }
    body.light .thread-item.unread .t-name { color:#1A0A09; }
    .t-time { font-size:11px; color:var(--text-muted); flex-shrink:0; margin-left:8px; }
    .t-preview { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .t-job { font-size:11px; color:var(--red-pale); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .t-role { font-size:10px; font-weight:600; padding:1px 7px; border-radius:10px; margin-left:6px; flex-shrink:0; white-space:nowrap; }
    .t-role-seeker { background:rgba(74,144,217,0.12); color:var(--blue); }
    .t-role-employer { background:rgba(212,148,58,0.12); color:var(--amber); }
    .t-role-applicant { background:rgba(76,175,112,0.12); color:var(--green); }
    .t-role-recruiter { background:rgba(156,39,176,0.12); color:#cf8ae0; }
    .unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); position:absolute; top:16px; right:14px; }

    /* CHAT PANEL */
    .chat-panel { display:flex; flex-direction:column; overflow:hidden; }
    .chat-header { padding:14px 20px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; gap:12px; }
    body.light .chat-header { border-bottom-color:#E0CECA; }
    .chat-back-btn { display:none; width:32px; height:32px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .chat-back-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .chat-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .chat-avatar img { width:100%; height:100%; object-fit:cover; }
    .chat-info { flex:1; }
    .chat-name { font-size:15px; font-weight:700; color:var(--text-light); }
    body.light .chat-name { color:#1A0A09; }
    .chat-meta { font-size:12px; color:var(--text-muted); margin-top:1px; }
    .status-tag { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .status-Shortlisted { background:rgba(212,148,58,0.15); color:var(--amber); }
    .status-Pending, .status-applied { background:rgba(74,144,217,0.15); color:var(--blue); }
    .status-Reviewed { background:rgba(156,39,176,0.15); color:#cf8ae0; }
    .status-Hired { background:rgba(76,175,112,0.15); color:var(--green); }
    .status-Rejected { background:rgba(224,85,85,0.15); color:#E05555; }

    /* MESSAGES */
    .chat-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:16px; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
    .msg-date-divider { text-align:center; font-size:11px; color:var(--text-muted); position:relative; }
    body.light .msg-date-divider { color:#7A5555; }
    .msg-date-divider::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background:var(--soil-line); }
    .msg-date-divider span { background:var(--soil-card); padding:0 12px; position:relative; }
    .msg-row { display:flex; gap:10px; align-items:flex-end; }
    .msg-row.sent { flex-direction:row-reverse; }
    .msg-row-avatar { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .msg-row-avatar img { width:100%; height:100%; object-fit:cover; }
    .bubble { max-width:70%; min-width:60px; padding:10px 14px; border-radius:12px; font-size:13px; line-height:1.5; word-break:break-word; white-space:pre-wrap; }
    .bubble-received { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
    body.light .bubble-received { background:#F5EEEC; color:#1A0A09; }
    .bubble-sent { background:rgba(209,61,44,0.15); color:var(--text-light); border-bottom-right-radius:4px; }
    .bubble-time { font-size:10px; margin-top:4px; opacity:0.6; }
    body.light .bubble-time { color:#7A5555; }
    .bubble-sent .bubble-time { text-align:right; }

    /* CHAT INPUT */
    .chat-input-area { padding:14px 20px; border-top:1px solid var(--soil-line); }
    body.light .chat-input-area { border-top-color:#E0CECA; }
    .chat-input-row { display:flex; align-items:flex-end; gap:10px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:10px 14px; transition:0.2s; }
    .chat-input-row:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.1); }
    body.light .chat-input-row { background:#F5EEEC; border-color:#E0CECA; }
    .chat-input-row textarea { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:none; min-height:36px; max-height:120px; line-height:1.5; }
    .chat-input-row textarea::placeholder { color:var(--text-muted); }
    .send-btn { width:34px; height:34px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .send-btn:hover { background:var(--red-bright); transform:scale(1.05); }

    /* EMPTY STATE */
    .empty-chat { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:var(--text-muted); }
    .empty-icon { width:60px; height:60px; border-radius:50%; background:var(--soil-hover); display:flex; align-items:center; justify-content:center; font-size:24px; }

    /* NEW CHAT */
    .new-chat-btn { width:36px; height:36px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; flex-shrink:0; }
    .new-chat-btn:hover { background:var(--red-bright); transform:scale(1.05); }
    .new-chat-panel { margin-top:10px; }
    .new-chat-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--red-vivid); border-radius:8px; padding:8px 12px; }
    .new-chat-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
    .new-chat-search-bar input::placeholder { color:var(--text-muted); }
    .new-chat-search-bar i { color:var(--red-bright); font-size:13px; }
    .new-chat-results { max-height:200px; overflow-y:auto; margin-top:6px; scrollbar-width:thin; }
    .new-chat-user { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:0.15s; }
    .new-chat-user:hover { background:var(--soil-hover); }
    .new-chat-user-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .new-chat-user-avatar img { width:100%; height:100%; object-fit:cover; }
    .new-chat-user-info { flex:1; min-width:0; }
    .new-chat-user-name { font-size:13px; font-weight:600; color:var(--text-light); }
    .new-chat-user-type { font-size:11px; color:var(--text-muted); text-transform:capitalize; }
    .new-chat-empty { text-align:center; padding:12px; font-size:12px; color:var(--text-muted); }

    /* LOADING */
    .loading-spinner { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

    /* FOOTER */
    .footer { text-align:center; padding:24px 20px; font-size:12px; color:var(--text-muted); border-top:1px solid var(--soil-line); margin-top:40px; position:relative; z-index:1; }
    body.light .footer { border-top-color:#E0CECA; color:#7A5555; }
    .footer-logo { font-family:var(--font-display); font-weight:700; font-size:16px; color:var(--text-mid); margin-bottom:4px; }

    /* RESPONSIVE */
    @media(max-width:760px){html,body{overflow-x:hidden;max-width:100vw}.page-shell,.main-content{max-width:100%;overflow-x:hidden}}
    @media(max-width:768px) {
      .msg-layout { grid-template-columns:1fr; height:auto; }
      .chat-panel { display:none; }
      .chat-panel.mobile-open { display:flex; height:calc(100vh - 160px); }
      .thread-list { height:calc(100vh - 160px); }
      .thread-list.mobile-hidden { display:none; }
      .chat-back-btn { display:flex; }
      .hamburger { display:flex; }
      .nav-links { display:none; }
      .profile-name,.profile-role { display:none; }
      .profile-btn { padding:6px 8px; }
    }
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
  </style>

<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->
<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<!-- PAGE -->
<div class="page-shell">
  <div class="page-header">
    <div>
      <div class="page-title">Messages</div>
      <div class="page-sub">Communicate with applicants and keep conversations organized.</div>
    </div>
  </div>

  <div class="msg-layout">

    <!-- CONVERSATION LIST -->
    <div class="thread-list" id="threadListPanel">
      <div class="thread-search">
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="thread-search-bar" style="flex:1;">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search conversations..." id="threadSearch">
          </div>
          <button class="new-chat-btn" onclick="toggleNewChat()" title="New Conversation"><i class="fas fa-pen-to-square"></i></button>
        </div>
        <div class="new-chat-panel" id="newChatPanel" style="display:none;">
          <div class="new-chat-search-bar">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search users to message..." id="newChatSearch" oninput="searchNewChatUsers()">
          </div>
          <div class="new-chat-results" id="newChatResults"></div>
        </div>
      </div>
      <div class="thread-filters">
        <button class="tf-pill active" data-filter="all" onclick="filterThreads('all',this)">All</button>
        <button class="tf-pill" data-filter="unread" onclick="filterThreads('unread',this)">Unread</button>
      </div>
      <div class="threads-scroll" id="threadsList">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading conversations...</div>
      </div>
    </div>

    <!-- CHAT PANEL -->
    <div class="chat-panel" id="chatPanel">
      <div class="empty-chat" id="emptyState">
        <div class="empty-icon"><i class="fas fa-comments"></i></div>
        <div style="font-size:14px; font-weight:600; color:var(--text-mid);">Select a conversation</div>
        <div style="font-size:13px;">Choose a thread on the left to start messaging</div>
      </div>
      <div id="activeChatArea" style="display:none; flex-direction:column; height:100%;">
        <div class="chat-header">
          <button class="chat-back-btn" id="chatBackBtn" onclick="closeMobileChat()"><i class="fas fa-arrow-left"></i></button>
          <div class="chat-avatar" id="chatAvatar"></div>
          <div class="chat-info">
            <div class="chat-name" id="chatName"></div>
            <div class="chat-meta" id="chatMeta"></div>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <span class="status-tag" id="chatStatus"></span>
          </div>
        </div>
        <div class="chat-messages" id="chatMessages">
          <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="chat-input-area">
          <div class="chat-input-row">
            <textarea id="msgInput" placeholder="Write a message..." rows="1" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
              <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Messages &mdash; Recruiter Portal</div>
</footer>

<script>
const API = '../api/messages.php';
const MY_INI = <?= json_encode($initials, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const MY_AVATAR = <?= json_encode($avatarUrl, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let threads = [];
let activeThread = null;
let filteredThreads = [];
let pollInterval = null;
let msgPollInterval = null;

/* ── Helpers ──────────────────────────────────────────────────────── */
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

function userLabel(t) {
    if (t.job_title || t.app_status) return { text:'Applicant', cls:'applicant' };
    const type = (t.account_type || '').toLowerCase();
    if (type === 'seeker')    return { text:'Seeker', cls:'seeker' };
    if (type === 'employer')  return { text:'Employer', cls:'employer' };
    if (type === 'recruiter') return { text:'Recruiter', cls:'recruiter' };
    return null;
}

/* ── Load conversations ───────────────────────────────────────────── */
function loadPageThreads(cb) {
    fetch(API + '?action=threads')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('threadsList').innerHTML =
                    '<div class="loading-spinner" style="color:var(--text-muted)"><i class="fas fa-exclamation-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>' +
                    esc(data.message || 'Could not load conversations') + '</div>';
                if (cb) cb();
                return;
            }
            threads = data.threads;
            filteredThreads = [...threads];
            renderThreads(filteredThreads);
            if (cb) cb();
        })
        .catch(e => { console.error('Thread load error:', e); if (cb) cb(); });
}

function renderThreads(list) {
    const el = document.getElementById('threadsList');
    if (!list.length) {
        el.innerHTML = '<div class="loading-spinner" style="color:var(--text-muted)">' +
            '<i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:10px;"></i>No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(t => {
        const avatarHtml = t.avatar_url
            ? '<img src="../' + esc(t.avatar_url) + '" alt="">'
            : esc(t.initials);
        const lbl = userLabel(t);
        const lblHtml = lbl ? ' <span class="t-role t-role-' + lbl.cls + '">' + lbl.text + '</span>' : '';
        return '<div class="thread-item' + (t.unread_count > 0 ? ' unread' : '') + (activeThread === t.partner_id ? ' active' : '') + '" onclick="openThread(' + t.partner_id + ')">' +
            '<div class="t-avatar" style="background:' + esc(t.color) + '">' + avatarHtml + '</div>' +
            '<div class="t-body">' +
                '<div class="t-top">' +
                    '<div class="t-name">' + esc(t.name) + lblHtml + '</div>' +
                    '<div class="t-time">' + esc(t.time) + '</div>' +
                '</div>' +
                '<div class="t-preview">' + (t.is_sent ? 'You: ' : '') + esc(t.preview) + '</div>' +
                (t.job_title ? '<div class="t-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ' + esc(t.job_title) + '</div>' : '') +
            '</div>' +
            (t.unread_count > 0 ? '<div class="unread-dot"></div>' : '') +
        '</div>';
    }).join('');
}

/* ── Open conversation ────────────────────────────────────────────── */
function openThread(partnerId) {
    activeThread = partnerId;
    renderThreads(filteredThreads);

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('activeChatArea').style.display = 'flex';
    document.getElementById('chatMessages').innerHTML =
        '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    // Mobile: show chat, hide list
    document.getElementById('chatPanel').classList.add('mobile-open');
    document.getElementById('threadListPanel').classList.add('mobile-hidden');

    loadMessages(partnerId);
    startMsgPolling(partnerId);
}

function closeMobileChat() {
    document.getElementById('chatPanel').classList.remove('mobile-open');
    document.getElementById('threadListPanel').classList.remove('mobile-hidden');
    activeThread = null;
    renderThreads(filteredThreads);
    if (msgPollInterval) { clearInterval(msgPollInterval); msgPollInterval = null; }
}

function loadMessages(partnerId) {
    fetch(API + '?action=messages&user_id=' + partnerId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('chatMessages').innerHTML =
                    '<div class="empty-chat" style="height:100%"><div class="empty-icon"><i class="fas fa-exclamation-circle"></i></div>' +
                    '<div style="font-size:14px;font-weight:600;color:var(--text-mid);">' + esc(data.message || 'Could not load messages') + '</div></div>';
                return;
            }

            const t = threads.find(x => x.partner_id === partnerId);
            const color = t ? t.color : '#4A90D9';
            const partner = data.partner || { name: 'User' };
            const pParts = partner.name.split(/\s+/);
            const defaultIni = pParts.length >= 2
                ? (pParts[0][0] + pParts[1][0]).toUpperCase()
                : partner.name.substring(0, 2).toUpperCase();
            const ini = t ? t.initials : defaultIni;
            const job = data.job;

            const partnerAvatar = (t && t.avatar_url) ? t.avatar_url
                : ((data.partner && data.partner.avatar_url) ? data.partner.avatar_url : null);

            // Header
            const avatarEl = document.getElementById('chatAvatar');
            avatarEl.style.background = color;
            avatarEl.innerHTML = partnerAvatar
                ? '<img src="../' + esc(partnerAvatar) + '" alt="">'
                : esc(ini);
            document.getElementById('chatName').textContent = partner.name;
            // Build label: Applicant if applied, else Seeker/Employer/Recruiter
            const pType = (partner.account_type || '').toLowerCase();
            let chatLabel = job ? 'Applicant' : (pType === 'seeker' ? 'Seeker' : pType === 'employer' ? 'Employer' : pType === 'recruiter' ? 'Recruiter' : '');
            document.getElementById('chatMeta').textContent = job ? chatLabel + ' · ' + job.title : chatLabel;

            const statusEl = document.getElementById('chatStatus');
            if (job && job.status) {
                statusEl.className = 'status-tag status-' + job.status;
                statusEl.textContent = job.status;
                statusEl.style.display = '';
            } else {
                statusEl.style.display = 'none';
            }

            renderMessages(data.messages, color, ini, partnerAvatar);

            // Clear unread in local list
            if (t) t.unread_count = 0;
            renderThreads(filteredThreads);
            updateBadges();
        })
        .catch(e => console.error('Messages load error:', e));
}

function renderMessages(msgs, color, ini, partnerAvatarUrl) {
    const el = document.getElementById('chatMessages');
    el.innerHTML = '';
    if (!msgs.length) {
        el.innerHTML = '<div class="empty-chat" style="height:100%">' +
            '<div class="empty-icon"><i class="fas fa-comment-dots"></i></div>' +
            '<div style="font-size:14px;font-weight:600;color:var(--text-mid);">Start the conversation</div></div>';
        return;
    }

    let html = '';
    msgs.forEach(m => {
        if (m.show_date) {
            html += '<div class="msg-date-divider"><span>' + esc(m.date) + '</span></div>';
        }
        if (m.from === 'me') {
            html += '<div class="msg-row sent">' +
                '<div class="msg-row-avatar" style="background:linear-gradient(135deg,#D4943A,#8a5010)">' +
                    (MY_AVATAR ? '<img src="' + esc(MY_AVATAR) + '" alt="">' : esc(MY_INI)) +
                '</div>' +
                '<div class="bubble bubble-sent">' + esc(m.body) +
                    '<div class="bubble-time">' + esc(m.time) + ' <i class="fas fa-check-double" style="font-size:9px;"></i></div>' +
                '</div></div>';
        } else {
            html += '<div class="msg-row">' +
                '<div class="msg-row-avatar" style="background:' + color + '">' +
                    (partnerAvatarUrl ? '<img src="../' + esc(partnerAvatarUrl) + '" alt="">' : esc(ini)) +
                '</div>' +
                '<div class="bubble bubble-received">' + esc(m.body) +
                    '<div class="bubble-time">' + esc(m.time) + '</div>' +
                '</div></div>';
        }
    });
    el.innerHTML = html;
    el.scrollTop = el.scrollHeight;
}

/* ── Send message ─────────────────────────────────────────────────── */
function sendMessage() {
    const input = document.getElementById('msgInput');
    const text = input.value.trim();
    if (!text || !activeThread) return;
    input.value = '';
    input.style.height = 'auto';

    fetch(API + '?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receiver_id: activeThread, message: text })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages(activeThread);
            loadPageThreads();
            showToast('Message sent', 'fa-paper-plane');
        } else {
            showToast(data.message || 'Send failed', 'fa-exclamation-circle');
        }
    })
    .catch(e => { console.error('Send error:', e); showToast('Send failed', 'fa-exclamation-circle'); });
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

/* ── Filters ──────────────────────────────────────────────────────── */
function filterThreads(type, btn) {
    document.querySelectorAll('.tf-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    if (type === 'all') filteredThreads = [...threads];
    else if (type === 'unread') filteredThreads = threads.filter(t => t.unread_count > 0);
    renderThreads(filteredThreads);
}

document.getElementById('threadSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    filteredThreads = threads.filter(t =>
        t.name.toLowerCase().includes(q) || (t.job_title || '').toLowerCase().includes(q)
    );
    renderThreads(filteredThreads);
});

/* ── Polling ──────────────────────────────────────────────────────── */
function startPolling() {
    pollInterval = setInterval(() => {
        loadPageThreads();
        updateBadges();
    }, 10000);
}

function startMsgPolling(partnerId) {
    if (msgPollInterval) clearInterval(msgPollInterval);
    msgPollInterval = setInterval(() => {
        loadMessages(partnerId);
    }, 10000);
}

/* ── Badge updates ────────────────────────────────────────────────── */
function updateBadges() {
    fetch(API + '?action=unread_count')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            document.querySelectorAll('.msg-badge-count').forEach(el => {
                el.textContent = data.messages;
                el.style.display = data.messages > 0 ? 'flex' : 'none';
            });
            document.querySelectorAll('.notif-badge-count').forEach(el => {
                el.textContent = data.notifications;
                el.style.display = data.notifications > 0 ? 'flex' : 'none';
            });
        })
        .catch(() => {});
}

/* ── New conversation ─────────────────────────────────────────────── */
let newChatTimeout = null;

function toggleNewChat() {
    const panel = document.getElementById('newChatPanel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        document.getElementById('newChatSearch').value = '';
        document.getElementById('newChatResults').innerHTML =
            '<div class="new-chat-empty">Type a name to search users</div>';
        setTimeout(() => document.getElementById('newChatSearch').focus(), 100);
    } else {
        panel.style.display = 'none';
    }
}

function searchNewChatUsers() {
    const q = document.getElementById('newChatSearch').value.trim();
    const results = document.getElementById('newChatResults');
    if (q.length < 2) {
        results.innerHTML = '<div class="new-chat-empty">Type at least 2 characters</div>';
        return;
    }
    if (newChatTimeout) clearTimeout(newChatTimeout);
    newChatTimeout = setTimeout(() => {
        results.innerHTML = '<div class="new-chat-empty"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        fetch(API + '?action=search_users&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.users.length) {
                    results.innerHTML = '<div class="new-chat-empty">No users found</div>';
                    return;
                }
                const colors = ['#4A90D9','#D4943A','#4CAF70','#9C27B0','#E05555','#00897B','#5C6BC0','#F4511E'];
                results.innerHTML = data.users.map((u, i) => {
                    const avHtml = u.avatar_url
                        ? '<img src="../' + esc(u.avatar_url) + '" alt="">'
                        : esc(u.initials);
                    return '<div class="new-chat-user" onclick="startNewChat(' + u.id + ')">' +
                        '<div class="new-chat-user-avatar" style="background:' + colors[i % colors.length] + '">' + avHtml + '</div>' +
                        '<div class="new-chat-user-info">' +
                            '<div class="new-chat-user-name">' + esc(u.name) + '</div>' +
                            '<div class="new-chat-user-type">' + esc(u.type) + '</div>' +
                        '</div></div>';
                }).join('');
            })
            .catch(() => { results.innerHTML = '<div class="new-chat-empty">Search failed</div>'; });
    }, 300);
}

function startNewChat(userId) {
    document.getElementById('newChatPanel').style.display = 'none';
    openThread(userId);
}

/* ── Navbar message btn (already on page, no-op) ──────────────────── */
const _navMsgBtn = document.getElementById('navMsgBtn');
if (_navMsgBtn) _navMsgBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); });

/* ── Init ─────────────────────────────────────────────────────────── */
const _params = new URLSearchParams(window.location.search);
const _targetUser = Number(_params.get('user_id') || 0);
loadPageThreads(() => { if (_targetUser > 0) openThread(_targetUser); });
startPolling();
updateBadges();
</script>
</body>
</html>
