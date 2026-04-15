<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . url('auth/antcareers_login.php'));
    exit;
}
$accountType = strtolower((string)($_SESSION['account_type'] ?? ''));
if (!in_array($accountType, ['employer', 'seeker', 'recruiter'], true)) {
    header('Location: ' . url('index.php'));
    exit;
}
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'messages';
$pageSub     = $accountType === 'seeker'
    ? 'Message employers, follow up on applications, and keep conversations organized.'
    : 'Communicate with applicants and keep conversations organized.';

// Get unread counts
$db = getDB();
$uid = (int)$_SESSION['user_id'];
$msgCount = 0;
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
    body.light { --soil-dark:#F4F0EF; --soil-med:#EDE8E7; --soil-card:#FFFFFF; --soil-hover:#F0ECEA; --soil-line:#E0CECA; --text-light:#1A1010; --text-mid:#4A3030; --text-muted:#927C7A; }

    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* NAVBAR */
    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
    body.light .navbar { background:rgba(244,240,239,0.97); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:var(--text-light); white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:var(--text-muted); text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; }
    .nav-link:hover { color:var(--text-light); background:var(--soil-hover); }
    .nav-link.active { color:var(--text-light); background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover, .notif-btn-nav.active { color:var(--red-pale); border-color:var(--red-vivid); }
    .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.02em; box-shadow:0 2px 8px rgba(209,61,44,0.3); text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.45); }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-btn:hover { background:var(--soil-card); }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--amber),#8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .profile-avatar img { width:100%; height:100%; object-fit:cover; }
    .profile-name { font-size:13px; font-weight:600; color:var(--text-light); }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:var(--text-light); }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:var(--text-light); }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }
    .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:var(--text-light); }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* PAGE */
    .page-shell { max-width:1380px; margin:0 auto; padding:32px 24px 80px; position:relative; z-index:1; }
    .page-header { margin-bottom:24px; display:flex; align-items:center; justify-content:space-between; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text-light); }
    .page-title span { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); margin-top:4px; }

    /* MESSAGES LAYOUT */
    .msg-layout { display:grid; grid-template-columns:320px 1fr; gap:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; height:calc(100vh - 160px); min-height:680px; }

    /* THREAD LIST */
    .thread-list { border-right:1px solid var(--soil-line); display:flex; flex-direction:column; overflow:hidden; }
    .thread-search { padding:14px 16px; border-bottom:1px solid var(--soil-line); }
    .thread-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:8px 12px; }
    .thread-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
    .thread-search-bar input::placeholder { color:var(--text-muted); }
    .thread-search-bar i { color:var(--text-muted); font-size:13px; }
    .thread-filters { display:flex; gap:6px; padding:10px 16px; border-bottom:1px solid var(--soil-line); }
    .tf-pill { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .tf-pill.active, .tf-pill:hover { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    .threads-scroll { flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
    .thread-item { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
    .thread-item:hover { background:var(--soil-hover); }
    .thread-item.active { background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .thread-item.unread .t-name { color:var(--text-light); font-weight:700; }
    .thread-item.unread .t-preview { color:var(--text-mid); }
    .t-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .t-avatar img { width:100%; height:100%; object-fit:cover; }
    .t-body { flex:1; min-width:0; }
    .t-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
    .t-name { font-size:13px; font-weight:600; color:var(--text-mid); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .t-time { font-size:11px; color:var(--text-muted); flex-shrink:0; margin-left:8px; }
    .t-preview { font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .t-job { font-size:11px; color:var(--red-pale); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); position:absolute; top:16px; right:14px; }

    /* CHAT PANEL */
    .chat-panel { display:flex; flex-direction:column; overflow:hidden; }
    .chat-header { padding:14px 20px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; gap:12px; }
    .chat-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .chat-avatar img { width:100%; height:100%; object-fit:cover; }
    .chat-info { flex:1; }
    .chat-name { font-size:15px; font-weight:700; color:var(--text-light); }
    .chat-meta { font-size:12px; color:var(--text-muted); margin-top:1px; }
    .chat-actions { display:flex; gap:8px; }
    .chat-action-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; }
    .chat-action-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .status-tag { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .status-Shortlisted { background:rgba(212,148,58,0.15); color:var(--amber); }
    .status-Pending, .status-applied { background:rgba(74,144,217,0.15); color:var(--blue); }
    .status-Reviewed { background:rgba(156,39,176,0.15); color:#cf8ae0; }
    .status-Hired { background:rgba(76,175,112,0.15); color:var(--green); }
    .status-Rejected { background:rgba(224,85,85,0.15); color:#E05555; }

    /* MESSAGES */
    .chat-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:16px; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
    .msg-date-divider { text-align:center; font-size:11px; color:var(--text-muted); position:relative; }
    .msg-date-divider::before { content:''; position:absolute; left:0; right:0; top:50%; height:1px; background:var(--soil-line); }
    .msg-date-divider span { background:var(--soil-card); padding:0 12px; position:relative; }
    .msg-row { display:flex; gap:10px; align-items:flex-end; }
    .msg-row.sent { flex-direction:row-reverse; }
    .msg-row-avatar { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .msg-row-avatar img { width:100%; height:100%; object-fit:cover; }
    .bubble { max-width:70%; min-width:60px; padding:10px 14px; border-radius:12px; font-size:13px; line-height:1.5; word-break:break-word; white-space:pre-wrap; }
    .bubble-received { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
    .bubble-sent { background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
    .bubble-time { font-size:10px; margin-top:4px; opacity:0.6; }
    .bubble-sent .bubble-time { text-align:right; }

    /* CHAT INPUT */
    .chat-input-area { padding:14px 20px; border-top:1px solid var(--soil-line); }
    .chat-input-row { display:flex; align-items:flex-end; gap:10px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:10px 14px; transition:0.2s; }
    .chat-input-row:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.1); }
    .chat-input-row textarea { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); resize:none; min-height:36px; max-height:120px; line-height:1.5; }
    .chat-input-row textarea::placeholder { color:var(--text-muted); }
    .input-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }
    .input-icon-btn { width:30px; height:30px; border-radius:6px; background:transparent; border:none; color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; font-size:13px; }
    .input-icon-btn:hover { color:var(--red-bright); background:var(--soil-line); }
    .send-btn { width:34px; height:34px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .send-btn:hover { background:var(--red-bright); transform:scale(1.05); }

    /* EMPTY STATE */
    .empty-chat { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; color:var(--text-muted); }
    .empty-icon { width:60px; height:60px; border-radius:50%; background:var(--soil-hover); display:flex; align-items:center; justify-content:center; font-size:24px; }

    /* NEW CHAT BUTTON & PANEL */
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

    /* TOAST */
    .toast { position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px); background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:10px 18px; font-size:13px; font-weight:600; color:var(--text-light); display:flex; align-items:center; gap:8px; box-shadow:0 8px 32px rgba(0,0,0,0.4); opacity:0; transition:all 0.3s; z-index:999; pointer-events:none; }
    .toast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    .toast i { color:var(--red-bright); }

    /* LIGHT MODE */
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#7A5555; }
    body.light .msg-layout { background:#FFFFFF; border-color:#E0CECA; }
    body.light .thread-list { border-right-color:#E0CECA; }
    body.light .thread-search-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .thread-search-input::placeholder { color:#7A5555; }
    body.light .thread-name { color:#4A2828; }
    body.light .thread-item.unread .thread-name { color:#1A0A09; }
    body.light .thread-item { border-bottom-color:#E0CECA; }
    body.light .thread-item:hover { background:#FEF0EE; }
    body.light .thread-item.active { background:rgba(209,61,44,0.06); }
    body.light .chat-header { border-bottom-color:#E0CECA; }
    body.light .chat-header-name { color:#1A0A09; }
    body.light .chat-input-area { border-top-color:#E0CECA; }
    body.light .chat-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .chat-input::placeholder { color:#7A5555; }
    body.light .msg-group.received .msg-bubble { background:#F5EEEC; color:#1A0A09; }
    body.light .msg-time { color:#7A5555; }
    body.light .msg-date-divider { color:#7A5555; }
    body.light .filter-pill { color:#7A5555; }
    body.light .filter-pill.active { color:var(--red-bright); background:rgba(209,61,44,0.08); }
    body.light .filter-pill:hover:not(.active) { color:#1A0A09; background:#FEF0EE; }
    body.light .footer { border-top-color:#E0CECA; color:#7A5555; }
    body.light .bubble-received { background:#F5EEEC; }
    body.light .chat-input-row { background:#F5EEEC; border-color:#E0CECA; }
    body.light .thread-search-bar { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }

    @media(max-width:768px) {
      .msg-layout { grid-template-columns:1fr; height:auto; }
      .chat-panel { display:none; }
      .chat-panel.mobile-open { display:flex; height:calc(100vh - 160px); }
      .thread-list { height:calc(100vh - 160px); }
      .hamburger { display:flex; }
      .nav-links { display:none; }
      .profile-name,.profile-role { display:none; }
      .profile-btn { padding:6px 8px; }
    }
  </style>
</head>
<body>

<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="200" y="100" width="1040" height="700" rx="8" stroke="rgba(209,61,44,0.4)" stroke-width="1"/>
    <rect x="320" y="180" width="800" height="540" rx="8" stroke="rgba(209,61,44,0.3)" stroke-width="1"/>
    <rect x="420" y="240" width="600" height="420" rx="8" stroke="rgba(209,61,44,0.2)" stroke-width="1"/>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<!-- NAVBAR -->
<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<!-- PAGE -->
<div class="page-shell">
  <div class="page-header">
    <div>
      <div class="page-title">Messages</div>
            <div class="page-sub"><?php echo htmlspecialchars($pageSub, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>

  <div class="msg-layout">

    <!-- THREAD LIST -->
    <div class="thread-list" id="threadListPanel">
      <div class="thread-search">
        <div style="display:flex;align-items:center;gap:8px;">
          <div class="thread-search-bar" style="flex:1;">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search conversations..." id="threadSearch">
          </div>
          <button class="new-chat-btn" onclick="toggleNewChat()" title="New Conversation"><i class="fas fa-pen-to-square"></i></button>
        </div>
        <!-- New Chat Search Panel -->
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
            <div class="input-actions">
              <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Notification sidebar include -->
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>

<div class="toast" id="toast"><i class="fas fa-check"></i> <span id="toastMsg"></span></div>

<script>
const API = '../api/messages.php';
const MY_INI = <?= json_encode($initials) ?>;
const MY_AVATAR = <?= json_encode($avatarUrl) ?>;
let threads = [];
let activeThread = null;
let filteredThreads = [];
let pollInterval = null;
let msgPollInterval = null;

// ── LOAD THREADS ──────────────────────────────────────────────────────
function loadPageThreads() {
function loadPageThreads(cb) {
    fetch(API + '?action=threads')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('threadsList').innerHTML = '<div class="loading-spinner" style="color:var(--text-muted)"><i class="fas fa-exclamation-circle" style="font-size:20px;display:block;margin-bottom:8px;"></i>' + esc(data.message || 'Could not load conversations') + '</div>';
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
        el.innerHTML = '<div class="loading-spinner" style="color:var(--text-muted)"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:10px;"></i>No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(t => `
        <div class="thread-item${t.unread_count > 0 ? ' unread' : ''}${activeThread === t.partner_id ? ' active' : ''}" onclick="openThread(${t.partner_id})">
            <div class="t-avatar" style="background:${esc(t.color)}">${t.avatar_url ? `<img src="../${t.avatar_url}" alt="">` : esc(t.initials)}</div>
            <div class="t-body">
                <div class="t-top">
                    <div class="t-name">${esc(t.name)}</div>
                    <div class="t-time">${esc(t.time)}</div>
                </div>
                <div class="t-preview">${t.is_sent ? 'You: ' : ''}${esc(t.preview)}</div>
                ${t.job_title ? `<div class="t-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ${esc(t.job_title)}</div>` : ''}
            </div>
            ${t.unread_count > 0 ? '<div class="unread-dot"></div>' : ''}
        </div>
    `).join('');
}

// ── OPEN THREAD ───────────────────────────────────────────────────────
function openThread(partnerId) {
    activeThread = partnerId;
    renderThreads(filteredThreads);

    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('activeChatArea').style.display = 'flex';
    document.getElementById('chatMessages').innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    // On mobile show chat panel
    document.getElementById('chatPanel').classList.add('mobile-open');

    loadMessages(partnerId);
    startMsgPolling(partnerId);
}

function loadMessages(partnerId) {
    fetch(API + '?action=messages&user_id=' + partnerId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('chatMessages').innerHTML = '<div class="empty-chat" style="height:100%"><div class="empty-icon"><i class="fas fa-exclamation-circle"></i></div><div style="font-size:14px;font-weight:600;color:var(--text-mid);">' + esc(data.message || 'Could not load messages') + '</div></div>';
                return;
            }
            const t = threads.find(x => x.partner_id === partnerId);
            const color = t ? t.color : '#4A90D9';
            const partner = data.partner || {name: 'User'};
            const pParts = partner.name.split(/\s+/);
            const defaultIni = pParts.length >= 2 ? (pParts[0][0]+pParts[1][0]).toUpperCase() : partner.name.substring(0,2).toUpperCase();
            const ini = t ? t.initials : defaultIni;
            const job = data.job;

            const avatarUrl = (t && t.avatar_url) ? t.avatar_url : ((data.partner && data.partner.avatar_url) ? data.partner.avatar_url : null);
            document.getElementById('chatAvatar').style.background = color;
            if (avatarUrl) {
                document.getElementById('chatAvatar').innerHTML = `<img src="../${avatarUrl}" alt="" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                document.getElementById('chatAvatar').textContent = ini;
            }
            document.getElementById('chatName').textContent = partner.name;
            document.getElementById('chatMeta').textContent = job ? job.title : 'Applicant';

            const statusEl = document.getElementById('chatStatus');
            if (job && job.status) {
                statusEl.className = 'status-tag status-' + job.status;
                statusEl.textContent = job.status;
                statusEl.style.display = '';
            } else {
                statusEl.style.display = 'none';
            }

            renderMessages(data.messages, color, ini, avatarUrl);

            // Update unread in thread list
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
        el.innerHTML = '<div class="empty-chat" style="height:100%"><div class="empty-icon"><i class="fas fa-comment-dots"></i></div><div style="font-size:14px;font-weight:600;color:var(--text-mid);">Start the conversation</div></div>';
        return;
    }
    msgs.forEach(m => {
        if (m.show_date) {
            el.innerHTML += `<div class="msg-date-divider"><span>${esc(m.date)}</span></div>`;
        }
        if (m.from === 'me') {
            el.innerHTML += `<div class="msg-row sent">
                <div class="msg-row-avatar" style="background:linear-gradient(135deg,#D4943A,#8a5010)">${MY_AVATAR ? `<img src="${MY_AVATAR}" alt="">` : MY_INI}</div>
                <div class="bubble bubble-sent">${esc(m.body)}<div class="bubble-time">${esc(m.time)} <i class="fas fa-check-double" style="font-size:9px;"></i></div></div>
            </div>`;
        } else {
            el.innerHTML += `<div class="msg-row">
                <div class="msg-row-avatar" style="background:${color}">${partnerAvatarUrl ? `<img src="../${partnerAvatarUrl}" alt="">` : ini}</div>
                <div class="bubble bubble-received">${esc(m.body)}<div class="bubble-time">${esc(m.time)}</div></div>
            </div>`;
        }
    });
    el.scrollTop = el.scrollHeight;
}

// ── SEND MESSAGE ──────────────────────────────────────────────────────
function sendMessage() {
    const input = document.getElementById('msgInput');
    const text = input.value.trim();
    if (!text || !activeThread) return;
    input.value = '';
    input.style.height = 'auto';

    fetch(API + '?action=send', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({receiver_id: activeThread, message: text})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadMessages(activeThread);
            loadPageThreads();
            showToast('Message sent', 'fa-paper-plane');
        }
    })
    .catch(e => console.error('Send error:', e));
}

function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── FILTERS ───────────────────────────────────────────────────────────
function filterThreads(type, btn) {
    document.querySelectorAll('.tf-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    if (type === 'all') filteredThreads = [...threads];
    else if (type === 'unread') filteredThreads = threads.filter(t => t.unread_count > 0);
    renderThreads(filteredThreads);
}

document.getElementById('threadSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    filteredThreads = threads.filter(t =>
        t.name.toLowerCase().includes(q) || (t.job_title || '').toLowerCase().includes(q)
    );
    renderThreads(filteredThreads);
});

// ── POLLING ───────────────────────────────────────────────────────────
function startPolling() {
    pollInterval = setInterval(() => {
        loadPageThreads();
        updateBadges();
    }, 5000);
}

function startMsgPolling(partnerId) {
    if (msgPollInterval) clearInterval(msgPollInterval);
    msgPollInterval = setInterval(() => {
        loadMessages(partnerId);
    }, 4000);
}

// ── BADGE UPDATES ─────────────────────────────────────────────────────
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

// ── HELPERS ───────────────────────────────────────────────────────────
function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function showToast(msg, icon) {
    const t = document.getElementById('toast');
    t.querySelector('i').className = 'fas ' + (icon || 'fa-check');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

// ── NAVBAR: Sidebar triggers ──────────────────────────────────────────
var _navMsgBtn = document.getElementById('navMsgBtn');
if (_navMsgBtn) _navMsgBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    // Already on messages page — no-op
});

var _navNotifBtn = document.getElementById('navNotifBtn');
if (_navNotifBtn) _navNotifBtn.addEventListener('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    if (typeof openNotifSidebar === 'function') openNotifSidebar();
});

// Theme, hamburger, profile dropdown are now handled by navbar_employer.php shared script

// ── NEW CONVERSATION ──────────────────────────────────────────────────
let newChatTimeout = null;
function toggleNewChat() {
    const panel = document.getElementById('newChatPanel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        document.getElementById('newChatSearch').value = '';
        document.getElementById('newChatResults').innerHTML = '<div class="new-chat-empty">Type a name to search users</div>';
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
                results.innerHTML = data.users.map((u, i) => `
                    <div class="new-chat-user" onclick="startNewChat(${u.id}, '${esc(u.name).replace(/'/g, "\\'")}')">
                        <div class="new-chat-user-avatar" style="background:${colors[i % colors.length]}">${u.avatar_url ? `<img src="../${u.avatar_url}" alt="">` : esc(u.initials)}</div>
                        <div class="new-chat-user-info">
                            <div class="new-chat-user-name">${esc(u.name)}</div>
                            <div class="new-chat-user-type">${esc(u.type)}</div>
                        </div>
                    </div>
                `).join('');
            })
            .catch(() => { results.innerHTML = '<div class="new-chat-empty">Search failed</div>'; });
    }, 300);
}

function startNewChat(userId, userName) {
    document.getElementById('newChatPanel').style.display = 'none';
    openThread(userId);
}

// ── INIT ──────────────────────────────────────────────────────────────
const _params = new URLSearchParams(window.location.search);
const _targetUser = Number(_params.get('user_id') || 0);
loadPageThreads(() => { if (_targetUser > 0) openThread(_targetUser); });
startPolling();
updateBadges();
</script>
</body>
</html>
