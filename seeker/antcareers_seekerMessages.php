<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('seeker');
$user = getUser();
$initials = (string)($user['initials'] ?? 'ME');
$avatarUrl = (string)($user['avatarUrl'] ?? '');
if ($avatarUrl !== '' && !str_starts_with($avatarUrl, '../') && !str_starts_with($avatarUrl, 'http')) {
  $avatarUrl = '../' . $avatarUrl;
}
$navActive = 'messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Messages</title>
  <script>(function(){const p=new URLSearchParams(window.location.search).get('theme');const t=p||localStorage.getItem('ac-theme')||'light';if(p)localStorage.setItem('ac-theme',p);if(t==='light')document.documentElement.classList.add('theme-light');})();</script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--amber-dim:#251C0E;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);min-height:100vh;-webkit-font-smoothing:antialiased;}
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}@keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }

    /* Page shell */
    .page-shell{max-width:1380px;margin:0 auto;padding:24px 24px 48px;position:relative;z-index:2;}
    .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;}
    .page-title{font-family:var(--font-display);font-size:24px;font-weight:700;color:var(--text-light);}
    .page-sub{font-size:13px;color:var(--text-muted);margin-top:3px;}
    .new-msg-btn{display:flex;align-items:center;justify-content:center;padding:0;background:var(--red-vivid);border:none;border-radius:8px;color:#F5F0EE;font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;transition:all 0.22s;width:36px;height:36px;min-width:36px;min-height:36px;flex-shrink:0;}
    .new-msg-btn:hover{background:var(--red-bright);transform:translateY(-1px);}
    body.light .new-msg-btn{color:#fff;background:var(--red-vivid);border:1px solid var(--red-vivid);}
    body.light .new-msg-btn:hover{background:var(--red-bright);border-color:var(--red-bright);}

    /* Messages layout */
    .msg-layout{display:grid;grid-template-columns:320px 1fr;gap:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;overflow:hidden;height:calc(100vh - 160px);min-height:680px;}

    /* Thread list */
    .thread-list{border-right:1px solid var(--soil-line);display:flex;flex-direction:column;min-height:0;}
    .thread-search{padding:14px 16px;border-bottom:1px solid var(--soil-line);display:flex;align-items:center;gap:8px;}
    .thread-search-bar{display:flex;align-items:center;gap:8px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:8px 12px;transition:0.2s;flex:1;}
    .thread-search-bar:focus-within{border-color:var(--red-vivid);}
    .thread-search-bar input{flex:1;background:none;border:none;outline:none;font-family:var(--font-body);font-size:13px;color:var(--text-light);}
    .thread-search-bar input::placeholder{color:var(--text-muted);}
    .thread-search-bar i{color:var(--text-muted);font-size:13px;}
    .thread-tabs{display:flex;padding:8px 12px;gap:4px;border-bottom:1px solid var(--soil-line);}
    .ttab{flex:1;padding:6px;border-radius:6px;background:none;border:none;font-family:var(--font-body);font-size:12px;font-weight:600;color:var(--text-muted);cursor:pointer;transition:0.15s;text-align:center;}
    .ttab.active{background:rgba(209,61,44,0.1);color:var(--red-pale);}
    .ttab:hover:not(.active){background:var(--soil-hover);color:var(--text-light);}
    .threads-scroll{flex:1;overflow-y:auto;scrollbar-width:thin;}
    .thread-item{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;cursor:pointer;transition:0.15s;border-bottom:1px solid var(--soil-line);position:relative;}
    .thread-item:last-child{border-bottom:none;}
    .thread-item:hover{background:var(--soil-hover);}
    .thread-item.active{background:rgba(209,61,44,0.07);border-left:2px solid var(--red-vivid);}
    .thread-item.unread .thread-name{color:#F5F0EE;font-weight:700;}
    .thread-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .thread-avatar img{width:100%;height:100%;object-fit:cover;}
    .thread-body{flex:1;min-width:0;}
    .thread-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:3px;}
    .thread-name{font-size:13px;font-weight:600;color:var(--text-mid);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .thread-preview{font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.4;}
    .thread-job{font-size:11px;color:var(--red-pale);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .thread-time{font-size:10px;color:var(--text-muted);flex-shrink:0;margin-left:8px;}
    .unread-dot{width:8px;height:8px;border-radius:50%;background:var(--red-vivid);position:absolute;top:16px;right:14px;}

    /* Chat area */
    .chat-area{display:flex;flex-direction:column;overflow:hidden;}
    .chat-header{padding:16px 20px;border-bottom:1px solid var(--soil-line);display:flex;align-items:center;gap:14px;}
    .chat-header-avatar{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .chat-header-avatar img{width:100%;height:100%;object-fit:cover;}
    .chat-header-info{flex:1;min-width:0;}
    .chat-header-name{font-size:15px;font-weight:700;color:#F5F0EE;}
    .chat-header-role{font-size:11px;color:var(--red-pale);font-weight:600;margin-top:1px;}
    .chat-header-actions{display:flex;gap:8px;}
    .chat-action-btn{width:32px;height:32px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;transition:0.15s;}
    .chat-action-btn:hover{color:var(--red-bright);border-color:var(--red-vivid);}

    .chat-messages{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:16px;scrollbar-width:thin;min-height:0;}
    .msg-group{display:flex;flex-direction:column;gap:4px;}
    .msg-group.sent{align-items:flex-end;}
    .msg-group.received{align-items:flex-start;}
    .msg-bubble{max-width:70%;min-width:60px;padding:11px 16px;border-radius:12px;font-size:13px;line-height:1.55;position:relative;white-space:pre-wrap;}
    .msg-group.sent .msg-bubble{background:var(--red-vivid);color:#fff;border-bottom-right-radius:4px;}
    .msg-group.received .msg-bubble{background:var(--soil-hover);color:var(--text-light);border-bottom-left-radius:4px;}
    .msg-time{font-size:10px;color:var(--text-muted);font-weight:600;padding:0 4px;}
    .msg-date-divider{text-align:center;font-size:11px;color:var(--text-muted);font-weight:600;margin:8px 0;display:flex;align-items:center;gap:10px;}
    .msg-date-divider::before,.msg-date-divider::after{content:'';flex:1;height:1px;background:var(--soil-line);}
    /* Row-based message layout with avatars */
    .msg-row{display:flex;gap:10px;align-items:flex-end;}
    .msg-row.sent{flex-direction:row-reverse;}
    .msg-row-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .msg-row-avatar img{width:100%;height:100%;object-fit:cover;}
    .bubble{max-width:70%;min-width:60px;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.55;word-break:break-word;white-space:pre-wrap;}
    .bubble-received{background:var(--soil-hover);color:var(--text-light);border-bottom-left-radius:4px;}
    .bubble-sent{background:var(--red-vivid);color:#fff;border-bottom-right-radius:4px;}
    .bubble-time{font-size:10px;margin-top:4px;opacity:0.6;}
    .bubble-sent .bubble-time{text-align:right;}

    .chat-input-area{padding:16px 20px;border-top:1px solid var(--soil-line);}
    .chat-input-row{display:flex;gap:10px;align-items:flex-end;}
    .chat-input{flex:1;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:10px;padding:12px 16px;color:var(--text-light);font-family:var(--font-body);font-size:13px;resize:none;outline:none;transition:0.2s;min-height:44px;max-height:120px;}
    .chat-input:focus{border-color:var(--red-vivid);}
    .chat-input::placeholder{color:var(--text-muted);}
    .send-btn{width:42px;height:42px;border-radius:10px;background:var(--red-vivid);border:none;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:0.2s;flex-shrink:0;}
    .send-btn:hover{background:var(--red-bright);}

    /* Empty state */
    .chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;color:var(--text-muted);padding:40px;}
    .chat-empty i{font-size:40px;color:var(--soil-line);}
    .chat-empty-title{font-family:var(--font-display);font-size:18px;color:var(--text-mid);}
    .chat-empty-sub{font-size:13px;text-align:center;max-width:260px;line-height:1.6;}

    /* Footer */
    .footer{border-top:1px solid var(--soil-line);padding:20px 24px;max-width:1380px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;color:var(--text-muted);font-size:12px;position:relative;z-index:2;flex-wrap:wrap;gap:10px;}
    .footer-logo{font-family:var(--font-display);font-weight:700;color:var(--red-pale);font-size:15px;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}
    ::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:var(--soil-dark);}::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:3px;}

    /* Light theme */
    html.theme-light body,body.light{--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A;}
    body.light .glow-orb{display:none;}
    body.light .chat-input{background:#F5EEEC;border-color:#E0CECA;color:#1A0A09;}
    body.light .chat-input::placeholder{color:#7A5555;}
    body.light .thread-search-bar{background:#F5EEEC;border-color:#E0CECA;}
    body.light .thread-search-bar input{color:#1A0A09;}
    body.light .msg-group.received .msg-bubble{background:#F5EEEC;color:#1A0A09;}
    body.light .bubble-received{background:#F5EEEC;color:#1A0A09;}
    body.light .chat-header-name{color:#1A0A09;}
    body.light .chat-header-role{color:var(--red-bright);}
    body.light .thread-name{color:#4A2828;}
    body.light .thread-item.unread .thread-name{color:#1A0A09;}
    body.light .thread-item:hover{background:#FEF0EE;}
    body.light .thread-item.active{background:rgba(209,61,44,0.06);}
    body.light .page-title{color:#1A0A09;}
    body.light .page-sub{color:#7A5555;}
    body.light .new-msg-btn{box-shadow:0 2px 8px rgba(209,61,44,0.2);}
    body.light .thread-list{border-right-color:#E0CECA;}
    body.light .thread-tabs{border-bottom-color:#E0CECA;}
    body.light .ttab{color:#7A5555;}
    body.light .ttab.active{color:var(--red-bright);background:rgba(209,61,44,0.08);}
    body.light .ttab:hover:not(.active){color:#1A0A09;background:#FEF0EE;}
    body.light .thread-search{border-bottom-color:#E0CECA;}
    body.light .thread-item{border-bottom-color:#E0CECA;}
    body.light .chat-header{border-bottom-color:#E0CECA;}
    body.light .chat-input-area{border-top-color:#E0CECA;}
    body.light .msg-time{color:#7A5555;}
    body.light .msg-date-divider{color:#7A5555;}
    body.light .msg-date-divider::before,.body.light .msg-date-divider::after{background:#E0CECA;}
    body.light .new-msg-search-bar{background:#F5EEEC;border-color:var(--red-vivid);}
    body.light .new-msg-search-bar input{color:#1A0A09;}
    body.light .footer{border-top-color:#E0CECA;color:#7A5555;}

    /* Mobile back button */
    .mobile-back-btn { display:none; align-items:center; gap:8px; padding:10px 16px; background:none; border:none; border-bottom:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; width:100%; text-align:left; transition:0.15s; }
    .mobile-back-btn:hover { color:var(--text-light); background:var(--soil-hover); }
    body.light .mobile-back-btn { color:#7A5555; border-bottom-color:#E0CECA; }
    body.light .mobile-back-btn:hover { color:#1A0A09; background:#FEF0EE; }

    /* Responsive */
    @media(max-width:760px){
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell{overflow-x:hidden;max-width:100%;padding-left:14px;padding-right:14px;box-sizing:border-box;}
      .main-content{max-width:100%;overflow-x:hidden}
    }
    @media(max-width:800px){
      .msg-layout{grid-template-columns:1fr;height:calc(100vh - 180px);min-height:500px;border-radius:10px;width:100%;box-sizing:border-box;overflow-x:hidden;}
      .thread-list{display:flex;flex-direction:column;border-right:none;height:100%;min-height:0;overflow-x:hidden;}
      .thread-item{max-width:100%;box-sizing:border-box;}
      .threads-scroll{flex:1;overflow-y:auto;overflow-x:hidden;min-height:0;}
      .chat-area{display:none;flex-direction:column;height:100%;overflow-x:hidden;}
      .mobile-back-btn{display:flex;flex-shrink:0;}
      .msg-layout.chat-open .thread-list{display:none;}
      .msg-layout.chat-open .chat-area{display:flex;}
    }
    @media(max-width:600px){.page-shell{padding:16px 14px 40px}}

    /* New message search panel */
    .new-msg-panel { padding:12px 16px; border-bottom:1px solid var(--soil-line); }
    .new-msg-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--red-vivid); border-radius:8px; padding:8px 12px; }
    .new-msg-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
    .new-msg-search-bar input::placeholder { color:var(--text-muted); }
    .new-msg-search-bar i { color:var(--red-bright); font-size:13px; }
    .new-msg-close-btn { background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:2px; }
    .new-msg-close-btn:hover { color:var(--text-light); }
    .new-msg-results { max-height:200px; overflow-y:auto; margin-top:6px; scrollbar-width:thin; }
    .new-msg-user { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:6px; cursor:pointer; transition:0.15s; }
    .new-msg-user:hover { background:var(--soil-hover); }
    .new-msg-user-av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
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

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<div class="page-shell anim">
  <div class="page-header">
    <div>
      <div class="page-title">Messages</div>
      <div class="page-sub">Your conversations with employers and recruiters</div>
    </div>
  </div>

  <div class="msg-layout">

    <!-- THREAD LIST -->
    <div class="thread-list">
      <!-- New message search panel -->
      <div class="new-msg-panel" id="newMsgPanel" style="display:none;">
        <div class="new-msg-search-bar">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Search users to message..." id="newMsgSearchInput" oninput="searchNewMsgUsers()">
          <button class="new-msg-close-btn" onclick="toggleNewMsgSearch()"><i class="fas fa-times"></i></button>
        </div>
        <div class="new-msg-results" id="newMsgResults"></div>
      </div>
      <div class="thread-search">
        <div class="thread-search-bar">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Search conversations…" id="threadSearch" oninput="filterThreads(this.value)">
        </div>
        <button class="new-msg-btn" onclick="toggleNewMsgSearch()" title="New Message">
          <i class="fas fa-pen-to-square"></i>
        </button>
      </div>
      <div class="thread-tabs">
        <button class="ttab active" onclick="setTab(this,'all')">All</button>
        <button class="ttab" onclick="setTab(this,'unread')">Unread</button>
        <button class="ttab" onclick="setTab(this,'employers')">Employers</button>
      </div>
      <div class="threads-scroll" id="threadsList"></div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-area" id="chatArea">
      <button class="mobile-back-btn" onclick="mobileBackToThreads()"><i class="fas fa-arrow-left"></i> All Conversations</button>
      <div class="chat-empty" id="chatEmpty">
        <i class="fas fa-comments"></i>
        <div class="chat-empty-title">Select a conversation</div>
        <div class="chat-empty-sub">Choose a thread on the left to read and reply to messages from employers.</div>
      </div>
      <!-- Chat content injected by JS -->
    </div>

  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Messages &mdash; <?= htmlspecialchars($user['fullName'], ENT_QUOTES, 'UTF-8') ?></div>
  <div style="display:flex;gap:14px;">
    <a href="antcareers_seekerDashboard.php" style="color:inherit;">&#8592; Dashboard</a>
    <span>Privacy</span><span>Terms</span>
  </div>
</footer>

<script>
const API = '../api/messages.php';
const MY_INI = <?= json_encode($initials) ?>;
const MY_AVATAR = <?= json_encode($avatarUrl) ?>;
let threads = [];
let activeThread = null;
let currentTab = 'all';
let msgPollTimer = null;

function apiGet(url) {
  return fetch(url, { credentials: 'same-origin' })
    .then(async r => {
      const text = await r.text();
      let data = null;
      try { data = JSON.parse(text); } catch (_) {}
      if (!r.ok) {
        const msg = (data && data.message) ? data.message : 'Request failed';
        throw new Error(msg);
      }
      if (!data || typeof data !== 'object') {
        throw new Error('Invalid API response');
      }
      return data;
    });
}

function apiPost(url, body) {
  return fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body || {}),
    credentials: 'same-origin'
  }).then(async r => {
    const text = await r.text();
    let data = null;
    try { data = JSON.parse(text); } catch (_) {}
    if (!r.ok) {
      const msg = (data && data.message) ? data.message : 'Request failed';
      throw new Error(msg);
    }
    if (!data || typeof data !== 'object') {
      throw new Error('Invalid API response');
    }
    return data;
  });
}

// ── Load real threads from API ──
function loadThreads(callback) {
  apiGet(API + '?action=threads')
        .then(data => {
            if (data.success) {
                threads = data.threads;
                renderThreads();
      } else {
        const list = document.getElementById('threadsList');
        if (list) list.innerHTML = '<div style="padding:30px 20px;text-align:center;color:var(--text-muted);font-size:13px;">' + esc(data.message || 'Could not load conversations') + '</div>';
            }
            if (callback) callback();
        })
    .catch(e => {
      const list = document.getElementById('threadsList');
      if (list) list.innerHTML = '<div style="padding:30px 20px;text-align:center;color:var(--text-muted);font-size:13px;">' + esc(e.message || 'Failed to load conversations') + '</div>';
      console.error('Load threads error:', e);
    });
}

function setTab(el, tab) {
  document.querySelectorAll('.ttab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  currentTab = tab;
  renderThreads();
}

function filterThreads(q) {
  renderThreads(q.toLowerCase());
}

function renderThreads(query = '') {
  const list = document.getElementById('threadsList');
  let filtered = threads;
  if (currentTab === 'unread') filtered = threads.filter(t => t.unread_count > 0);
  if (query) filtered = filtered.filter(t => t.name.toLowerCase().includes(query) || (t.preview || '').toLowerCase().includes(query));

  if (!filtered.length) {
    list.innerHTML = query
      ? '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-search" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i>No matching conversations<div style="margin-top:6px;font-size:12px;">Try a different name or keyword.</div></div>'
      : '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i><div style="font-weight:700;color:var(--text-mid);margin-bottom:4px;">No conversations yet</div><div style="font-size:12px;line-height:1.5;">Start messaging recruiters and employers.</div></div>';
    return;
  }

  list.innerHTML = filtered.map(t => `
    <div class="thread-item ${t.unread_count > 0 ? 'unread' : ''} ${activeThread === t.partner_id ? 'active' : ''}"
         onclick="openThread(${t.partner_id})">
      <div class="thread-avatar" style="background:${t.color}">${t.avatar_url ? `<img src="../${t.avatar_url}" alt="">` : esc(t.initials)}</div>
      <div class="thread-body">
        <div class="thread-top">
          <div class="thread-name">${esc(t.name)}</div>
          <div class="thread-time">${t.time}</div>
        </div>
        <div class="thread-preview">${t.is_sent ? 'You: ' : ''}${esc(t.preview)}</div>
        ${t.job_title ? `<div class="thread-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ${esc(t.job_title)}</div>` : ''}
      </div>
      ${t.unread_count > 0 ? '<div class="unread-dot"></div>' : ''}
    </div>`).join('');
}

function openThread(partnerId) {
  activeThread = partnerId;
  renderThreads();
  loadConversation(partnerId);
  // On mobile: switch to chat view
  document.getElementById('msgLayout') && document.getElementById('msgLayout').classList.add('chat-open');
  const layout = document.querySelector('.msg-layout');
  if (layout) layout.classList.add('chat-open');
  if (typeof window.updateSeekerBadges === 'function') {
    window.updateSeekerBadges();
  }
  startMsgPoll(partnerId);
}

function mobileBackToThreads() {
  const layout = document.querySelector('.msg-layout');
  if (layout) layout.classList.remove('chat-open');
  activeThread = null;
  if (msgPollTimer) { clearInterval(msgPollTimer); msgPollTimer = null; }
}

function selectThread(partnerId) {
  openThread(partnerId);
}

function markConversationRead(partnerId) {
  return apiPost(API + '?action=mark_read', { partner_id: partnerId });
}

function loadConversation(partnerId) {
  const prevInput = document.getElementById('msgInput');
  const draftText = prevInput ? prevInput.value : '';
  const wasTyping = !!(prevInput && document.activeElement === prevInput);
  const draftSelStart = prevInput && typeof prevInput.selectionStart === 'number'
    ? prevInput.selectionStart
    : draftText.length;
  const draftSelEnd = prevInput && typeof prevInput.selectionEnd === 'number'
    ? prevInput.selectionEnd
    : draftText.length;

  apiGet(API + '?action=messages&user_id=' + partnerId)
        .then(data => {
      if (!data.success) {
        const chatArea = document.getElementById('chatArea');
        chatArea.innerHTML = '<div class="chat-empty"><i class="fas fa-triangle-exclamation"></i><div class="chat-empty-title">Conversation unavailable</div><div class="chat-empty-sub">' + esc(data.message || 'Could not load this conversation.') + '</div></div>';
        return;
      }
            const t = threads.find(x => x.partner_id === partnerId);
            const color = t ? t.color : 'linear-gradient(135deg,#D13D2C,#7A1515)';
            const pName = (data.partner && data.partner.name) ? data.partner.name : 'User';
            const pParts = pName.split(/\s+/);
            const ini = t
              ? t.initials
              : (pParts.length >= 2
                ? (pParts[0][0] + pParts[1][0]).toUpperCase()
                : ((pParts[0] && pParts[0][0]) ? pParts[0][0].toUpperCase() : '?'));
            const avatarUrl = (t && t.avatar_url) ? t.avatar_url : ((data.partner && data.partner.avatar_url) ? data.partner.avatar_url : null);
            const partner = data.partner || {name:'User'};
            const job = data.job;

            const chatArea = document.getElementById('chatArea');
            let msgsHtml = '';
            if (data.messages.length) {
                msgsHtml = data.messages.map(m => {
                    let html = '';
                    if (m.show_date) html += `<div class="msg-date-divider">${m.date}</div>`;
                    if (m.from === 'me') {
                        html += `<div class="msg-row sent">
                            <div class="msg-row-avatar" style="background:linear-gradient(135deg,#D4943A,#8a5010)">${MY_AVATAR ? `<img src="${MY_AVATAR}" alt="">` : MY_INI}</div>
                            <div class="bubble bubble-sent">${esc(m.body)}<div class="bubble-time">${m.time} <i class="fas fa-check-double" style="font-size:9px;"></i></div></div>
                        </div>`;
                    } else {
                        html += `<div class="msg-row">
                            <div class="msg-row-avatar" style="background:${color}">${avatarUrl ? `<img src="../${avatarUrl}" alt="">` : esc(ini)}</div>
                            <div class="bubble bubble-received">${esc(m.body)}<div class="bubble-time">${m.time}</div></div>
                        </div>`;
                    }
                    return html;
                }).join('');
            } else {
                msgsHtml = '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);"><i class="fas fa-comment-dots" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i>Start the conversation</div>';
            }

            chatArea.innerHTML = `
                <button class="mobile-back-btn" onclick="mobileBackToThreads()"><i class="fas fa-arrow-left"></i> All Conversations</button>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background:${color}">${avatarUrl ? `<img src="../${avatarUrl}" alt="">` : esc(ini)}</div>
                    <div class="chat-header-info">
                        <div class="chat-header-name">${esc(partner.name)}</div>
                        <div class="chat-header-role">${job ? esc(job.title) : ''}</div>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">${msgsHtml}</div>
                <div class="chat-input-area">
                    <div class="chat-input-row">
                        <textarea class="chat-input" id="msgInput" placeholder="Type a message…" rows="1"
                            onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"></textarea>
                        <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>`;

            const msgs = document.getElementById('chatMessages');
            if (msgs) msgs.scrollTop = msgs.scrollHeight;

            // Preserve the in-progress draft while polling refreshes the conversation.
            if (activeThread === partnerId) {
              const nextInput = document.getElementById('msgInput');
              if (nextInput) {
                nextInput.value = draftText;
                if (wasTyping) {
                  nextInput.focus();
                  const safeStart = Math.min(draftSelStart, draftText.length);
                  const safeEnd = Math.min(draftSelEnd, draftText.length);
                  nextInput.setSelectionRange(safeStart, safeEnd);
                }
              }
            }

            // Mark as read
            if (t) t.unread_count = 0;
            renderThreads();
            markConversationRead(partnerId).catch(() => {}).finally(() => {
              if (typeof window.updateSeekerBadges === 'function') {
                window.updateSeekerBadges();
              }
            });
        })
        .catch(e => {
          const chatArea = document.getElementById('chatArea');
          chatArea.innerHTML = '<div class="chat-empty"><i class="fas fa-wifi"></i><div class="chat-empty-title">Could not load messages</div><div class="chat-empty-sub">' + esc(e.message || 'Please refresh and try again.') + '</div></div>';
          console.error('Load conversation error:', e);
        });
}

function sendMessage() {
  const input = document.getElementById('msgInput');
  if (!input) return;
  const text = input.value.trim();
  if (!text || !activeThread) return;
  input.value = '';

    apiPost(API + '?action=send', {receiver_id: activeThread, message: text})
  .then(data => {
      if (data.success) {
          loadConversation(activeThread);
          loadThreads();
          if (typeof window.updateSeekerBadges === 'function') {
            window.updateSeekerBadges();
          }
      }
  })
  .catch(e => console.error('Send error:', e));
}

// ── Polling ──
function startMsgPoll(partnerId) {
    stopMsgPoll();
    msgPollTimer = setInterval(() => {
        loadConversation(partnerId);
    }, 3000);
}

function stopMsgPoll() {
    if (msgPollTimer) { clearInterval(msgPollTimer); msgPollTimer = null; }
}

// ── New Message Search ──
let newMsgTimeout = null;
function toggleNewMsgSearch() {
    const p = document.getElementById('newMsgPanel');
    if (p.style.display === 'none') {
        p.style.display = 'block';
        document.getElementById('newMsgSearchInput').value = '';
        document.getElementById('newMsgResults').innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);font-size:12px;">Type a name to search</div>';
        setTimeout(() => document.getElementById('newMsgSearchInput').focus(), 100);
    } else {
        p.style.display = 'none';
    }
}

function searchNewMsgUsers() {
    const q = document.getElementById('newMsgSearchInput').value.trim();
    const res = document.getElementById('newMsgResults');
    if (q.length < 2) { res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);font-size:12px;">Type at least 2 characters</div>'; return; }
    if (newMsgTimeout) clearTimeout(newMsgTimeout);
    newMsgTimeout = setTimeout(() => {
        res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        apiGet(API + '?action=search_users&q=' + encodeURIComponent(q))
            .then(data => {
                if (!data.success || !data.users.length) { res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);font-size:12px;">No users found</div>'; return; }
                const colors = [
                  'linear-gradient(135deg,#D13D2C,#7A1515)',
                  'linear-gradient(135deg,#4A90D9,#2A6090)',
                  'linear-gradient(135deg,#4CAF70,#2A7040)',
                  'linear-gradient(135deg,#D4943A,#8A5A10)',
                  'linear-gradient(135deg,#9C27B0,#5A0080)'
                ];
                res.innerHTML = data.users.map((u, i) => `
                    <div class="new-msg-user" onclick="startNewChat(${u.id})">
                        <div class="new-msg-user-av" style="background:${colors[i % colors.length]}">${u.avatar_url ? `<img src="../${u.avatar_url}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">` : esc(u.initials)}</div>
                        <div><div style="font-size:13px;font-weight:600;color:var(--text-light);">${esc(u.name)}</div><div style="font-size:11px;color:var(--text-muted);text-transform:capitalize;">${esc(u.type)}</div></div>
                    </div>
                `).join('');
            })
            .catch(() => { res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--text-muted);font-size:12px;">Search failed</div>'; });
    }, 300);
}

function startNewChat(userId) {
    document.getElementById('newMsgPanel').style.display = 'none';
    openThread(userId);
}

// ── Helpers ──
function esc(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

// ── Init ──
const _params = new URLSearchParams(window.location.search);
const _targetUser = Number(_params.get('user_id') || 0);
loadThreads(() => {
  if (_targetUser > 0) {
    selectThread(_targetUser);
  }
});
// Poll for new threads every 3 seconds
setInterval(() => {
    loadThreads();
}, 3000);
</script>
</body>
</html>