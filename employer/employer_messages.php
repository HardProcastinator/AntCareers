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
if ($avatarUrl !== '' && !str_starts_with($avatarUrl, '../') && !str_starts_with($avatarUrl, 'http')) {
  $avatarUrl = '../' . $avatarUrl;
}
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
  <title>AntCareers - Messages</title>
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
    .theme-btn{ width:36px;height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; flex-shrink:0; }
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
    .hamburger { display:none; width:36px;height:36px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
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
    .thread-search { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; gap:10px; }
    .thread-search-bar { display:flex; align-items:center; gap:8px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:0 12px; min-height:36px; flex:1; }
    .thread-search-bar input { flex:1; background:none; border:none; outline:none; font-family:var(--font-body); font-size:13px; color:var(--text-light); }
    .thread-search-bar input::placeholder { color:var(--text-muted); }
    .thread-search-bar i { color:var(--text-muted); font-size:13px; }
    .threads-scroll { flex:1; overflow-y:auto; scrollbar-width:thin; scrollbar-color:var(--soil-line) transparent; }
    .thread-item { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--soil-line); cursor:pointer; transition:0.15s; position:relative; }
    .thread-item:hover { background:var(--soil-hover); }
    .thread-item.active { background:rgba(209,61,44,0.08); border-left:2px solid var(--red-vivid); }
    .thread-item.unread .thread-name { color:var(--text-light); font-weight:700; }
    .thread-item.unread .thread-preview { color:var(--text-mid); }
    .thread-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .thread-avatar img { width:100%; height:100%; object-fit:cover; }
    .thread-body { flex:1; min-width:0; }
    .thread-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
    .thread-name { font-size:13px; font-weight:600; color:var(--text-mid); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .thread-preview { font-size:11px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.4; }
    .thread-job { font-size:11px; color:var(--red-pale); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .thread-time { font-size:10px; color:var(--text-muted); flex-shrink:0; margin-left:8px; }
    .unread-dot { width:8px; height:8px; border-radius:50%; background:var(--red-vivid); position:absolute; top:16px; right:14px; }

    /* THREAD TABS â€” seeker style */
    .thread-tabs { display:flex; padding:8px 12px; gap:4px; border-bottom:1px solid var(--soil-line); }
    .ttab { flex:1; padding:6px; border-radius:6px; background:none; border:none; font-family:var(--font-body); font-size:12px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:0.15s; text-align:center; }
    .ttab.active { background:rgba(209,61,44,0.1); color:var(--red-pale); }
    .ttab:hover:not(.active) { background:var(--soil-hover); color:var(--text-light); }

    /* NEW MSG BTN (in page header) */
    .new-msg-btn { display:flex; align-items:center; justify-content:center; padding:0; background:var(--red-vivid); border:none; border-radius:8px; color:var(--text-light); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:all 0.22s; width:36px; min-width:36px; height:36px; min-height:36px; flex-shrink:0; }
    .new-msg-btn:hover { background:var(--red-bright); transform:translateY(-1px); }
    body.light .new-msg-btn { color:#fff; background:var(--red-vivid); border:1px solid var(--red-vivid); }
    body.light .new-msg-btn:hover { background:var(--red-bright); border-color:var(--red-bright); }

    /* NEW MSG PANEL */
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
    .new-msg-user-av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }

    /* CHAT AREA â€” seeker style */
    .chat-area { display:flex; flex-direction:column; overflow:hidden; }
    .chat-header { padding:16px 20px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; gap:14px; }
    .chat-header-avatar { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .chat-header-avatar img { width:100%; height:100%; object-fit:cover; }
    .chat-header-info { flex:1; min-width:0; }
    .chat-header-name { font-size:15px; font-weight:700; color:var(--text-light); }
    .chat-header-role { font-size:11px; color:var(--red-pale); font-weight:600; margin-top:1px; }
    .chat-header-actions { display:flex; gap:8px; }
    .chat-action-btn { width:32px; height:32px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
    .chat-action-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .status-tag { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
    .status-Shortlisted { background:rgba(212,148,58,0.15); color:var(--amber); }
    .status-Pending,.status-applied { background:rgba(74,144,217,0.15); color:var(--blue); }
    .status-Reviewed { background:rgba(156,39,176,0.15); color:#cf8ae0; }
    .status-Hired { background:rgba(76,175,112,0.15); color:var(--green); }
    .status-Rejected { background:rgba(224,85,85,0.15); color:#E05555; }

    /* MESSAGES */
    .chat-messages { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:16px; scrollbar-width:thin; min-height:0; }
    .msg-date-divider { text-align:center; font-size:11px; color:var(--text-muted); font-weight:600; margin:8px 0; display:flex; align-items:center; gap:10px; }
    .msg-date-divider::before,.msg-date-divider::after { content:''; flex:1; height:1px; background:var(--soil-line); }
    .msg-row { display:flex; gap:10px; align-items:flex-end; }
    .msg-row.sent { flex-direction:row-reverse; }
    .msg-row-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .msg-row-avatar img { width:100%; height:100%; object-fit:cover; }
    .bubble { max-width:70%; min-width:60px; padding:10px 14px; border-radius:12px; font-size:13px; line-height:1.55; word-break:break-word; white-space:pre-wrap; }
    .bubble-received { background:var(--soil-hover); color:var(--text-light); border-bottom-left-radius:4px; }
    .bubble-sent { background:var(--red-vivid); color:#fff; border-bottom-right-radius:4px; }
    .bubble-time { font-size:10px; margin-top:4px; opacity:0.6; }
    .bubble-sent .bubble-time { text-align:right; }

    /* CHAT INPUT */
    .chat-input-area { padding:16px 20px; border-top:1px solid var(--soil-line); }
    .chat-input-row { display:flex; gap:10px; align-items:flex-end; }
    .chat-input { flex:1; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:12px 16px; color:var(--text-light); font-family:var(--font-body); font-size:13px; resize:none; outline:none; transition:0.2s; min-height:44px; max-height:120px; }
    .chat-input:focus { border-color:var(--red-vivid); }
    .chat-input::placeholder { color:var(--text-muted); }
    .send-btn { width:42px; height:42px; border-radius:10px; background:var(--red-vivid); border:none; color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; transition:0.2s; flex-shrink:0; }
    .send-btn:hover { background:var(--red-bright); }

    /* EMPTY STATE */
    .chat-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:14px; color:var(--text-muted); padding:40px; }
    .chat-empty i { font-size:40px; color:var(--soil-line); }
    .chat-empty-title { font-family:var(--font-display); font-size:18px; color:var(--text-mid); }
    .chat-empty-sub { font-size:13px; text-align:center; max-width:260px; line-height:1.6; }

    /* MOBILE BACK BTN */
    .mobile-back-btn { display:none; align-items:center; gap:8px; padding:10px 16px; background:none; border:none; border-bottom:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; width:100%; text-align:left; transition:0.15s; flex-shrink:0; }
    .mobile-back-btn:hover { color:var(--text-light); background:var(--soil-hover); }

    /* LOADING */
    .loading-spinner { padding:40px; text-align:center; color:var(--text-muted); font-size:13px; }

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
    body.light .thread-search-bar { background:#F5EEEC; border-color:#E0CECA; }
    body.light .thread-search-bar input { color:#1A0A09; }
    body.light .thread-item { border-bottom-color:#E0CECA; }
    body.light .thread-item:hover { background:#FEF0EE; }
    body.light .thread-item.active { background:rgba(209,61,44,0.06); }
    body.light .thread-name { color:#4A2828; }
    body.light .thread-item.unread .thread-name { color:#1A0A09; }
    body.light .thread-tabs { border-bottom-color:#E0CECA; }
    body.light .ttab { color:#7A5555; }
    body.light .ttab.active { color:var(--red-bright); background:rgba(209,61,44,0.08); }
    body.light .ttab:hover:not(.active) { color:#1A0A09; background:#FEF0EE; }
    body.light .chat-header { border-bottom-color:#E0CECA; }
    body.light .chat-header-name { color:#1A0A09; }
    body.light .chat-input-area { border-top-color:#E0CECA; }
    body.light .chat-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .chat-input::placeholder { color:#7A5555; }
    body.light .bubble-received { background:#F5EEEC; color:#1A0A09; }
    body.light .msg-date-divider { color:#7A5555; }
    body.light .msg-date-divider::before,.body.light .msg-date-divider::after { background:#E0CECA; }
    body.light .new-msg-search-bar { background:#F5EEEC; border-color:var(--red-vivid); }
    body.light .new-msg-search-bar input { color:#1A0A09; }
    body.light .mobile-back-btn { color:#7A5555; border-bottom-color:#E0CECA; }
    body.light .mobile-back-btn:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }

    /* RESPONSIVE */
    @media(max-width:760px){
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell{overflow-x:hidden;max-width:100%;padding-left:14px;padding-right:14px;box-sizing:border-box;}
      .main-content{max-width:100%;overflow-x:hidden}
    }
    @media(max-width:800px){
      .msg-layout{grid-template-columns:1fr;height:calc(100vh - 180px);min-height:500px;border-radius:10px;width:100%;box-sizing:border-box;overflow-x:hidden;}
      .thread-list{display:flex;flex-direction:column;border-right:none;height:100%;min-height:0;overflow-x:hidden;}
      .thread-search{padding:12px;}
      .new-msg-btn{width:34px;height:34px;}
      .thread-item{max-width:100%;box-sizing:border-box;}
      .threads-scroll{flex:1;overflow-y:auto;overflow-x:hidden;min-height:0;}
      .chat-area{display:none;flex-direction:column;height:100%;overflow-x:hidden;}
      .mobile-back-btn{display:flex;flex-shrink:0;}
      .msg-layout.chat-open .thread-list{display:none;}
      .msg-layout.chat-open .chat-area{display:flex;}
      .hamburger{display:flex;}
      .nav-links{display:none;}
      .profile-name,.profile-role{display:none;}
      .profile-btn{padding:6px 8px;}
    }
    @media(max-width:600px){.page-shell{padding:16px 14px 40px}}
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
    <div class="thread-list">
      <!-- New message search panel -->
      <div class="new-msg-panel" id="newMsgPanel" style="display:none;">
        <div class="new-msg-search-bar">
          <i class="fas fa-search"></i>
          <input type="text" id="newMsgSearchInput" placeholder="Search users to message…" oninput="searchNewMsgUsers()">
          <button class="new-msg-close-btn" onclick="toggleNewMsgSearch()"><i class="fas fa-times"></i></button>
        </div>
        <div class="new-msg-results" id="newMsgResults"></div>
      </div>
      <div class="thread-search">
        <div class="thread-search-bar">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Search conversations…" id="threadSearch" oninput="filterThreadsByQuery(this.value)">
        </div>
        <button class="new-msg-btn" onclick="toggleNewMsgSearch()" title="New Message">
          <i class="fas fa-pen-to-square"></i>
        </button>
      </div>
      <div class="thread-tabs">
        <button class="ttab active" onclick="setTab(this,'all')">All</button>
        <button class="ttab" onclick="setTab(this,'unread')">Unread</button>
        <button class="ttab" onclick="setTab(this,'applicants')">Applicants</button>
      </div>
      <div class="threads-scroll" id="threadsList">
        <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loadingâ€¦</div>
      </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-area" id="chatArea">
      <button class="mobile-back-btn" onclick="mobileBackToThreads()"><i class="fas fa-arrow-left"></i> All Conversations</button>
      <div class="chat-empty" id="chatEmpty">
        <i class="fas fa-comments"></i>
        <div class="chat-empty-title">Select a conversation</div>
        <div class="chat-empty-sub">Choose a thread on the left to read and reply to messages.</div>
      </div>
    </div>

  </div>
</div>

<!-- Notification sidebar include -->
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>


<script>
const API = '../api/messages.php';
const MY_INI = <?= json_encode($initials) ?>;
const MY_AVATAR = <?= json_encode($avatarUrl) ?>;
let threads = [];
let activeThread = null;
let currentTab = 'all';
let msgPollTimer = null;

function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function fmtMsgTime(ts) {
  if (!ts) return '';
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(ts)) {
    var d = new Date(ts.replace(' ', 'T') + 'Z');
    if (!isNaN(d.getTime())) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
  }
  return ts;
}

// â”€â”€ LOAD THREADS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadThreads(cb) {
  fetch(API + '?action=threads')
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('threadsList').innerHTML = '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;">' + esc(data.message || 'Could not load conversations') + '</div>';
        if (cb) cb(); return;
      }
      threads = data.threads;
      renderThreads();
      if (cb) cb();
    })
    .catch(() => {
      document.getElementById('threadsList').innerHTML = '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;">Failed to load conversations</div>';
      if (cb) cb();
    });
}

// â”€â”€ TABS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function setTab(el, tab) {
  document.querySelectorAll('.ttab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  currentTab = tab;
  renderThreads();
}

function filterThreadsByQuery(q) { renderThreads(q.toLowerCase()); }

// â”€â”€ RENDER THREADS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function renderThreads(query = '') {
  const list = document.getElementById('threadsList');
  let filtered = threads;
  if (currentTab === 'unread') filtered = threads.filter(t => t.unread_count > 0);
  if (currentTab === 'applicants') filtered = threads.filter(t => t.job_title || (t.account_type || '').toLowerCase() === 'seeker');
  if (query) filtered = filtered.filter(t => t.name.toLowerCase().includes(query) || (t.preview || '').toLowerCase().includes(query) || (t.job_title || '').toLowerCase().includes(query));

  if (!filtered.length) {
    list.innerHTML = query
      ? '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-search" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i>No matching conversations</div>'
      : '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);font-size:13px;"><i class="fas fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i><div style="font-weight:700;color:var(--text-mid);margin-bottom:4px;">No conversations yet</div></div>';
    return;
  }

  list.innerHTML = filtered.map(t => `
    <div class="thread-item${t.unread_count > 0 ? ' unread' : ''}${activeThread === t.partner_id ? ' active' : ''}" onclick="openThread(${t.partner_id})">
      <div class="thread-avatar" style="background:${esc(t.color)}">${t.avatar_url ? `<img src="../${t.avatar_url}" alt="">` : esc(t.initials)}</div>
      <div class="thread-body">
        <div class="thread-top">
          <div class="thread-name">${esc(t.name)}</div>
          <div class="thread-time">${esc(t.time)}</div>
        </div>
        <div class="thread-preview">${t.is_sent ? 'You: ' : ''}${esc(t.preview)}</div>
        ${t.job_title ? `<div class="thread-job"><i class="fas fa-briefcase" style="font-size:9px;"></i> ${esc(t.job_title)}</div>` : ''}
      </div>
      ${t.unread_count > 0 ? '<div class="unread-dot"></div>' : ''}
    </div>`).join('');
}

// â”€â”€ OPEN THREAD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openThread(partnerId) {
  activeThread = partnerId;
  renderThreads(document.getElementById('threadSearch').value.toLowerCase());
  loadConversation(partnerId);
  const layout = document.querySelector('.msg-layout');
  if (layout) layout.classList.add('chat-open');
  if (msgPollTimer) clearInterval(msgPollTimer);
  msgPollTimer = setInterval(() => loadConversation(partnerId), 3000);
}

function mobileBackToThreads() {
  const layout = document.querySelector('.msg-layout');
  if (layout) layout.classList.remove('chat-open');
  activeThread = null;
  if (msgPollTimer) { clearInterval(msgPollTimer); msgPollTimer = null; }
}

// â”€â”€ LOAD CONVERSATION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function loadConversation(partnerId) {
  const prevInput = document.getElementById('msgInput');
  const draftText = prevInput ? prevInput.value : '';

  fetch(API + '?action=messages&user_id=' + partnerId)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        document.getElementById('chatArea').innerHTML = `
          <button class="mobile-back-btn" onclick="mobileBackToThreads()"><i class="fas fa-arrow-left"></i> All Conversations</button>
          <div class="chat-empty"><i class="fas fa-triangle-exclamation"></i><div class="chat-empty-title">Conversation unavailable</div><div class="chat-empty-sub">${esc(data.message || 'Could not load this conversation.')}</div></div>`;
        return;
      }
      const t = threads.find(x => x.partner_id === partnerId);
      const color = t ? t.color : 'linear-gradient(135deg,#D13D2C,#7A1515)';
      const partner = data.partner || {name: 'User'};
      const pParts = partner.name.split(/\s+/);
      const ini = t
        ? t.initials
        : (pParts.length >= 2
          ? (pParts[0][0] + pParts[1][0]).toUpperCase()
          : ((pParts[0] && pParts[0][0]) ? pParts[0][0].toUpperCase() : '?'));
      const avatarUrl = (t && t.avatar_url) ? t.avatar_url : ((data.partner && data.partner.avatar_url) ? data.partner.avatar_url : null);
      const job = data.job;

      let msgsHtml = '';
      if (data.messages.length) {
        msgsHtml = data.messages.map(m => {
          let html = '';
          if (m.show_date) html += `<div class="msg-date-divider">${esc(m.date)}</div>`;
          if (m.from === 'me') {
            html += `<div class="msg-row sent">
              <div class="msg-row-avatar" style="background:linear-gradient(135deg,#D4943A,#8a5010)">${MY_AVATAR ? `<img src="${MY_AVATAR}" alt="">` : MY_INI}</div>
              <div class="bubble bubble-sent">${esc(m.body)}<div class="bubble-time">${esc(fmtMsgTime(m.utc_ts || m.time))} <i class="fas fa-check-double" style="font-size:9px;"></i></div></div>
            </div>`;
          } else {
            html += `<div class="msg-row">
              <div class="msg-row-avatar" style="background:${color}">${avatarUrl ? `<img src="../${avatarUrl}" alt="">` : esc(ini)}</div>
              <div class="bubble bubble-received">${esc(m.body)}<div class="bubble-time">${esc(fmtMsgTime(m.utc_ts || m.time))}</div></div>
            </div>`;
          }
          return html;
        }).join('');
      } else {
        msgsHtml = '<div style="padding:40px 20px;text-align:center;color:var(--text-muted);"><i class="fas fa-comment-dots" style="font-size:28px;display:block;margin-bottom:10px;color:var(--soil-line);"></i>Start the conversation</div>';
      }

      const jobRole = job ? esc(job.title) : 'Applicant';
      const statusHtml = (job && job.status) ? `<span class="status-tag status-${esc(job.status)}">${esc(job.status)}</span>` : '';

      document.getElementById('chatArea').innerHTML = `
        <button class="mobile-back-btn" onclick="mobileBackToThreads()"><i class="fas fa-arrow-left"></i> All Conversations</button>
        <div class="chat-header">
          <div class="chat-header-avatar" style="background:${color}">${avatarUrl ? `<img src="../${avatarUrl}" alt="">` : esc(ini)}</div>
          <div class="chat-header-info">
            <div class="chat-header-name">${esc(partner.name)}</div>
            <div class="chat-header-role">${jobRole} ${statusHtml}</div>
          </div>
        </div>
        <div class="chat-messages" id="chatMessages">${msgsHtml}</div>
        <div class="chat-input-area">
          <div class="chat-input-row">
            <textarea class="chat-input" id="msgInput" placeholder="Type a messageâ€¦" rows="1"
              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage();}"
              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>
            <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
          </div>
        </div>`;

      const msgs = document.getElementById('chatMessages');
      if (msgs) msgs.scrollTop = msgs.scrollHeight;

      if (draftText && document.getElementById('msgInput')) document.getElementById('msgInput').value = draftText;

      if (t) t.unread_count = 0;
      renderThreads(document.getElementById('threadSearch').value.toLowerCase());
      fetch(API + '?action=mark_read', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({partner_id:partnerId})}).catch(()=>{});
    })
    .catch(e => console.error('Load conversation error:', e));
}

// â”€â”€ SEND MESSAGE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function sendMessage() {
  const input = document.getElementById('msgInput');
  if (!input) return;
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
    if (data.success) { loadConversation(activeThread); loadThreads(); }
    else if (typeof showToast === 'function') showToast(data.message || 'Send failed', 'fa-exclamation-circle');
  })
  .catch(e => console.error('Send error:', e));
}

// â”€â”€ BADGE UPDATES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function updateBadges() {
  fetch(API + '?action=unread_count')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      document.querySelectorAll('.msg-badge-count').forEach(el => { el.textContent = data.messages; el.style.display = data.messages > 0 ? 'flex' : 'none'; });
      document.querySelectorAll('.notif-badge-count').forEach(el => { el.textContent = data.notifications; el.style.display = data.notifications > 0 ? 'flex' : 'none'; });
    }).catch(() => {});
}

// â”€â”€ NEW MESSAGE SEARCH â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function toggleNewMsgSearch() {
  const panel = document.getElementById('newMsgPanel');
  if (panel.style.display === 'none') {
    panel.style.display = 'block';
    document.getElementById('newMsgSearchInput').value = '';
    document.getElementById('newMsgResults').innerHTML = '';
    setTimeout(() => document.getElementById('newMsgSearchInput').focus(), 100);
  } else {
    panel.style.display = 'none';
  }
}

let newMsgTimeout = null;
function searchNewMsgUsers() {
  const q = document.getElementById('newMsgSearchInput').value.trim();
  const results = document.getElementById('newMsgResults');
  if (q.length < 2) {
    results.innerHTML = '<div style="padding:8px 10px;font-size:12px;color:var(--text-muted);">Type at least 2 characters</div>';
    return;
  }
  if (newMsgTimeout) clearTimeout(newMsgTimeout);
  newMsgTimeout = setTimeout(() => {
    results.innerHTML = '<div style="padding:8px 10px;font-size:12px;color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
    fetch(API + '?action=search_users&q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.users.length) { results.innerHTML = '<div style="padding:8px 10px;font-size:12px;color:var(--text-muted);">No users found</div>'; return; }
        const colors = [
          'linear-gradient(135deg,#D13D2C,#7A1515)',
          'linear-gradient(135deg,#4A90D9,#2A6090)',
          'linear-gradient(135deg,#4CAF70,#2A7040)',
          'linear-gradient(135deg,#D4943A,#8A5A10)',
          'linear-gradient(135deg,#9C27B0,#5A0080)'
        ];
        results.innerHTML = data.users.map((u, i) => `
          <div class="new-msg-user" onclick="startNewMsg(${u.id})">
            <div class="new-msg-user-av" style="background:${colors[i % colors.length]}">${u.avatar_url ? `<img src="../${u.avatar_url}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">` : esc(u.initials)}</div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:600;color:var(--text-light);">${esc(u.name)}</div>
              <div style="font-size:11px;color:var(--text-muted);">${esc(u.type)}</div>
            </div>
          </div>`).join('');
      })
      .catch(() => { results.innerHTML = '<div style="padding:8px 10px;font-size:12px;color:var(--text-muted);">Search failed</div>'; });
  }, 300);
}

function startNewMsg(userId) {
  document.getElementById('newMsgPanel').style.display = 'none';
  openThread(userId);
}

// â”€â”€ NAVBAR OVERRIDES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// On the full messages page, prevent navbar sidebar from opening
window.openMsgSidebar = function() {
  window.scrollTo({top: 0, behavior: 'smooth'});
};
const _navMsgBtn = document.getElementById('navMsgBtn');
if (_navMsgBtn) _navMsgBtn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); });

// â”€â”€ INIT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const _params = new URLSearchParams(window.location.search);
const _targetUser = Number(_params.get('user_id') || 0);
loadThreads(() => { if (_targetUser > 0) openThread(_targetUser); });
setInterval(() => { loadThreads(); updateBadges(); }, 3000);
updateBadges();
</script>
</body>
</html>
