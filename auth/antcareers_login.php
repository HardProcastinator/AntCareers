<?php
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
$_csrfToken = csrfToken(); // generate + store in session once, server-side
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — AntCareers</title>
  <script>
    // Run before any CSS renders — no flash
    (function() {
      const p = new URLSearchParams(window.location.search).get('theme');
      const s = localStorage.getItem('ac-theme');
      const t = p || s || 'light';
      if (p) localStorage.setItem('ac-theme', p);
      if (t === 'dark') document.documentElement.classList.add('dark-init');
    })();
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --bg:#F9F5F4; --card:#FFFFFF; --line:#E4D0CC; --line-soft:#F0E6E4;
      --text:#1A0A09; --text-mid:#4A2828; --text-muted:#8A6060;
      --font-display:'Playfair Display',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }

    /* ── DARK THEME ── */
    html.dark-init body,
    body.dark {
      --bg:#0A0909; --card:#1C1818; --line:#352E2E; --line-soft:#252020;
      --text:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
    }
    html.dark-init .left-panel, body.dark .left-panel { background:#1C1818; }
    html.dark-init .left-panel::before, body.dark .left-panel::before { background:radial-gradient(circle,rgba(209,61,44,0.2) 0%,transparent 65%); }
    html.dark-init .left-panel::after, body.dark .left-panel::after { background:radial-gradient(circle,rgba(209,61,44,0.08) 0%,transparent 65%); }
    html.dark-init .left-logo-text, body.dark .left-logo-text { color:#F5F0EE; }
    html.dark-init .left-eyebrow, body.dark .left-eyebrow { color:var(--red-pale); }
    html.dark-init .left-heading, body.dark .left-heading { color:#F5F0EE; }
    html.dark-init .left-sub, body.dark .left-sub { color:#7A6868; }
    html.dark-init .testimonial-text, body.dark .testimonial-text { color:#C0ACAA; }
    html.dark-init .testimonial-name, body.dark .testimonial-name { color:#F5F0EE; }
    html.dark-init .testimonial-role, body.dark .testimonial-role { color:#7A6868; }
    html.dark-init .left-stat-num, body.dark .left-stat-num { color:#F5F0EE; }
    html.dark-init .left-stat-label, body.dark .left-stat-label { color:#7A6868; }
    html.dark-init .left-footer, body.dark .left-footer { color:#4A3838; }
    html.dark-init .right-panel, body.dark .right-panel { background:var(--card); }
    html.dark-init .btn-social, body.dark .btn-social { background:var(--card); border-color:var(--line); color:var(--text); }
    html.dark-init .btn-social:hover, body.dark .btn-social:hover { background:var(--line-soft); border-color:#5A4848; }
    html.dark-init .field input, body.dark .field input { background:#131010; border-color:var(--line); color:var(--text); }
    html.dark-init .field input::placeholder, body.dark .field input::placeholder { color:#5A4848; }
    html.dark-init .field input:focus, body.dark .field input:focus { border-color:var(--red-vivid); }
    html.dark-init .field-header label, body.dark .field-header label { color:#A09090; }
    html.dark-init .field label, body.dark .field label { color:#A09090; }
    html.dark-init .remember-row label, body.dark .remember-row label { color:var(--text-muted); }
    html.dark-init .err-banner, body.dark .err-banner { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.3); color:var(--red-pale); }
    html.dark-init .testimonial, body.dark .testimonial { background:rgba(255,255,255,0.03); border-color:rgba(255,255,255,0.07); }
    html.dark-init .btn-text, body.dark .btn-text { color:var(--text-muted); }
    html.dark-init .btn-text:hover, body.dark .btn-text:hover { color:var(--text); }
    html.dark-init .info-box, body.dark .info-box { background:rgba(74,144,217,0.1); border-color:rgba(74,144,217,0.25); color:#8ABCE8; }
    html.dark-init .terms-notice a, body.dark .terms-notice a { color:var(--red-pale); }
    html.dark-init .signup-link a, body.dark .signup-link a { color:var(--red-pale); }
    html.dark-init .forgot-link, body.dark .forgot-link { color:var(--red-pale); }
    html.dark-init .right-title, body.dark .right-title { color:var(--text); }
    html.dark-init .right-sub, body.dark .right-sub { color:var(--text-muted); }
    html.dark-init .divider, body.dark .divider { color:var(--text-muted); }
    html.dark-init .signin-link, body.dark .signin-link { color:var(--text-muted); }
    html.dark-init .signin-link a, body.dark .signin-link a { color:var(--red-pale); }
    html,body { height:100%; font-family:var(--font-body); background:var(--bg); color:var(--text); -webkit-font-smoothing:antialiased; }
    .page { min-height:100vh; display:grid; grid-template-columns:1fr 1fr; }

    /* LEFT */
    .left-panel { background:#F3E4E0; display:flex; flex-direction:column; justify-content:space-between; padding:40px 48px; position:relative; overflow:hidden; }
    .left-panel::before { content:''; position:absolute; top:-100px; left:-100px; width:500px; height:500px; background:radial-gradient(circle,rgba(209,61,44,0.1) 0%,transparent 65%); pointer-events:none; }
    .left-panel::after { content:''; position:absolute; bottom:-80px; right:-80px; width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 65%); pointer-events:none; }
    .left-art { position:absolute; inset:0; opacity:0.06; pointer-events:none; }
    .left-logo { display:flex; align-items:center; gap:10px; text-decoration:none; position:relative; z-index:2; }
    .left-logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .left-logo-icon::before { content:'🐜'; filter:brightness(0) invert(1); font-size:17px; }
    .left-logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#1A0A09; }
    .left-logo-text span { color:var(--red-bright); }
    .left-content { position:relative; z-index:2; flex:1; display:flex; flex-direction:column; justify-content:center; }
    .left-eyebrow { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--red-mid); margin-bottom:16px; display:flex; align-items:center; gap:8px; }
    .left-eyebrow::before { content:''; width:18px; height:2px; background:var(--red-vivid); }
    .left-heading { font-family:var(--font-display); font-size:clamp(30px,3.2vw,46px); font-weight:700; line-height:1.12; color:#1A0A09; margin-bottom:18px; }
    .left-heading em { color:var(--red-bright); font-style:italic; }
    .left-sub { font-size:15px; color:#6A4040; line-height:1.7; max-width:340px; margin-bottom:40px; }

    /* Testimonial card on left */
    .testimonial {
      background:rgba(255,255,255,0.6); border:1px solid rgba(209,61,44,0.12);
      border-radius:12px; padding:20px 22px; margin-bottom:32px; max-width:360px;
    }
    .testimonial-text { font-size:14px; color:#4A2828; line-height:1.65; margin-bottom:14px; font-style:italic; }
    .testimonial-author { display:flex; align-items:center; gap:10px; }
    .testimonial-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; }
    .testimonial-name { font-size:13px; font-weight:700; color:#1A0A09; }
    .testimonial-role { font-size:11px; color:#8A6060; margin-top:1px; }

    .left-stats { display:flex; gap:32px; }
    .left-stat-num { font-family:var(--font-body); font-size:22px; font-weight:800; color:#1A0A09; }
    .left-stat-num span { color:var(--red-bright); }
    .left-stat-label { font-size:11px; color:#6A4040; margin-top:3px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; }
    .left-footer { position:relative; z-index:2; font-size:12px; color:#8A6060; }

    /* RIGHT */
    .right-panel { background:var(--card); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px; position:relative; overflow-y:auto; }
    .right-inner { width:100%; max-width:400px; }
    .right-back-home { position:absolute; top:28px; left:28px; }
    .btn-home { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--text-muted); text-decoration:none; transition:color .15s; }
    .btn-home:hover { color:var(--text); }

    .right-header { margin-bottom:28px; }
    .right-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text); margin-bottom:5px; line-height:1.2; }
    .right-sub { font-size:14px; color:var(--text-muted); line-height:1.6; }

    /* SOCIAL */
    .social-btns { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:4px; }
    .btn-social { display:flex; align-items:center; justify-content:center; gap:8px; padding:12px 14px; border:1.5px solid var(--line); border-radius:8px; background:#fff; font-family:var(--font-body); font-size:13px; font-weight:600; color:var(--text); cursor:pointer; transition:all .2s; }
    .btn-social:hover { border-color:#C0A0A0; background:var(--line-soft); }
    .divider { display:flex; align-items:center; gap:12px; margin:18px 0; color:var(--text-muted); font-size:12px; font-weight:500; }
    .divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--line); }

    /* FIELDS */
    .field { margin-bottom:14px; }
    .field label { display:block; font-size:11px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--text-mid); margin-bottom:5px; }
    .field input { width:100%; padding:12px 14px; background:#FDFAFA; border:1.5px solid var(--line); border-radius:8px; font-family:var(--font-body); font-size:14px; color:var(--text); outline:none; transition:border-color .2s,box-shadow .2s; }
    .field input::placeholder { color:#C0A8A4; }
    .field input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,.1); }
    .field input.err { border-color:var(--red-vivid); background:#FFF5F4; }
    .field-pw { position:relative; }
    .field-pw input { padding-right:44px; }
    .pw-eye { position:absolute; right:14px; top:50%; transform:translateY(-50%); color:var(--text-muted); cursor:pointer; font-size:13px; transition:color .15s; }
    .pw-eye:hover { color:var(--text); }

    /* Forgot password row */
    .field-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:5px; }
    .field-header label { font-size:11px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--text-mid); }
    .forgot-link { font-size:12px; font-weight:600; color:var(--red-vivid); text-decoration:none; transition:color .15s; }
    .forgot-link:hover { color:var(--red-bright); }

    /* Error banner */
    .err-banner { background:#FFF5F4; border:1px solid #F0C8C4; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:13px; color:var(--red-vivid); font-weight:500; display:none; align-items:center; gap:9px; }
    .err-banner.show { display:flex; }
    .err-banner i { flex-shrink:0; }

    /* Remember me */
    .remember-row { display:flex; align-items:center; gap:9px; margin-bottom:4px; }
    .remember-row input[type="checkbox"] { width:15px; height:15px; accent-color:var(--red-vivid); }
    .remember-row label { font-size:13px; color:var(--text-muted); cursor:pointer; }

    /* Submit */
    .btn-submit { width:100%; padding:13px; background:var(--red-vivid); border:none; border-radius:8px; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; letter-spacing:.02em; cursor:pointer; transition:all .2s; margin-top:14px; display:flex; align-items:center; justify-content:center; gap:8px; }
    .btn-submit:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 6px 20px rgba(209,61,44,.28); }
    .btn-submit:active { transform:translateY(0); }

    /* Spinner inside button */
    .spinner { width:16px; height:16px; border:2px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; display:none; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* Sign up link */
    .signup-link { text-align:center; margin-top:22px; font-size:13px; color:var(--text-muted); }
    .signup-link a { color:var(--red-vivid); font-weight:600; text-decoration:none; }
    .signup-link a:hover { color:var(--red-bright); }

    /* Terms */
    .terms-notice { text-align:center; font-size:11px; color:var(--text-muted); margin-top:14px; line-height:1.5; }
    .terms-notice a { color:var(--red-mid); text-decoration:none; }

    /* Forgot password screen */
    .forgot-screen { display:none; }
    .forgot-screen.show { display:block; }
    .main-form.hide { display:none; }
    .btn-text { background:none; border:none; font-family:var(--font-body); font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; padding:0 0 18px 0; display:flex; align-items:center; gap:5px; transition:color .15s; }
    .btn-text:hover { color:var(--text); }
    .info-box { background:#F5F9FF; border:1px solid #C8DCF0; border-radius:8px; padding:14px 16px; margin-bottom:20px; font-size:13px; color:#2A4A6A; line-height:1.6; display:flex; gap:10px; }
    .info-box i { color:#4A90D9; margin-top:1px; flex-shrink:0; }

    @media(max-width:860px) { .page{grid-template-columns:1fr} .left-panel{display:none} .right-panel{padding:70px 24px 40px;min-height:100vh} .right-back-home{left:20px;top:20px} }
    @media(max-width:480px) { .social-btns{grid-template-columns:1fr} }
      .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); background:var(--red-vivid); }
    .notif-panel { position:fixed; top:64px; right:0; bottom:0; width:360px; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:150; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
    .notif-panel.open { transform:translateX(0); }
    .notif-panel-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
    .notif-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
    .notif-panel-title i { color:var(--red-bright); }
    .notif-close { width:28px; height:28px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
    .notif-close:hover { color:#F5F0EE; }
    .notif-panel-body { flex:1; overflow-y:auto; padding:12px 16px; }
    .notif-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
    .notif-item:last-child { border-bottom:none; }
    .n-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .n-dot.red { background:var(--red-vivid); } .n-dot.amber { background:var(--amber); } .n-dot.green { background:#4CAF70; } .n-dot.read { background:var(--soil-line); }
    .n-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
    .n-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }
  </style>
</head>
<body>
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-head">
    <div class="notif-panel-title"><i class="fas fa-bell"></i> Notifications</div>
    <button class="notif-close" onclick="closeNotif()"><i class="fas fa-times"></i></button>
  </div>
  <div class="notif-panel-body">
    <div class="notif-item"><div class="n-dot green"></div><div><div class="n-text">Your application for <strong>Senior Frontend Engineer</strong> at Vercel was submitted.</div><div class="n-time">1 hour ago</div></div></div>
    <div class="notif-item"><div class="n-dot amber"></div><div><div class="n-text">Your status for <strong>Product Designer</strong> at Linear was updated to <em>Shortlisted</em>.</div><div class="n-time">3 hours ago</div></div></div>
    <div class="notif-item"><div class="n-dot red"></div><div><div class="n-text">You received a new message from <strong>TechPH Inc.</strong></div><div class="n-time">Yesterday</div></div></div>
    <div class="notif-item"><div class="n-dot read"></div><div><div class="n-text">3 new jobs matching your profile in Manila.</div><div class="n-time">Mar 27</div></div></div>
  </div>
</div>

<div class="page">

  <!-- LEFT -->
  <div class="left-panel">
    <svg class="left-art" viewBox="0 0 600 900" xmlns="http://www.w3.org/2000/svg">
      <g stroke="#D13D2C" stroke-width="1.2" fill="none">
        <path d="M0 150 Q120 130 200 180 Q300 240 400 200 Q500 160 600 190"/>
        <path d="M0 350 Q100 330 220 380 Q350 440 480 400 Q560 375 600 390"/>
        <path d="M0 580 Q150 560 280 610 Q400 660 520 620 Q570 602 600 615"/>
        <path d="M0 750 Q100 730 200 780 Q320 840 440 800 Q530 770 600 785"/>
        <path d="M200 0 Q190 100 210 220 Q230 340 200 460 Q170 580 190 700 Q210 820 200 900"/>
        <path d="M420 0 Q410 120 430 260 Q450 400 420 540 Q390 680 410 900"/>
      </g>
      <g fill="#D13D2C">
        <circle cx="200" cy="180" r="3.5"/><circle cx="400" cy="200" r="3"/>
        <circle cx="220" cy="380" r="3.5"/><circle cx="480" cy="400" r="3"/>
        <circle cx="280" cy="610" r="3.5"/><circle cx="430" cy="260" r="3"/>
      </g>
    </svg>

    <a class="left-logo" href="javascript:void(0)" onclick="window.location.href='../index.php?theme='+(document.body.classList.contains('dark')?'dark':'light')">
      <div class="left-logo-icon"></div>
      <span class="left-logo-text">Ant<span>Careers</span></span>
    </a>

    <div class="left-content">
      <div class="left-eyebrow">Welcome back</div>
      <h2 class="left-heading">Good to see<br>you <em>again.</em></h2>
      <p class="left-sub">Your next opportunity is one login away. Pick up right where you left off.</p>

      <div class="testimonial">
        <div class="testimonial-text">"Found my current role through AntCareers in under two weeks. The whole process felt genuinely different."</div>
        <div class="testimonial-author">
          <div class="testimonial-avatar">MS</div>
          <div>
            <div class="testimonial-name">Maria Santos</div>
            <div class="testimonial-role">Senior Designer · Hired via AntCareers</div>
          </div>
        </div>
      </div>

      <div class="left-stats">
        <div><div class="left-stat-num">2<span>,4K+</span></div><div class="left-stat-label">Live roles</div></div>
        <div><div class="left-stat-num">840<span>+</span></div><div class="left-stat-label">Companies</div></div>
      </div>
    </div>

    <div class="left-footer">© 2025 AntCareers</div>
  </div>

  <!-- RIGHT -->
  <div class="right-panel">
    <div class="right-back-home">
      <a class="btn-home" href="javascript:void(0)" onclick="window.location.href='../index.php?theme='+(document.body.classList.contains('dark')?'dark':'light')"><i class="fas fa-arrow-left"></i> Back to jobs</a>
    </div>

    <div class="right-inner">

      <!-- MAIN LOGIN FORM -->
      <div class="main-form" id="mainForm">
        <div class="right-header">
          <div class="right-title">Sign in</div>
          <div class="right-sub">Welcome back — let's find your next role.</div>
        </div>

        <div class="social-btns">
          <button class="btn-social" onclick="socialLogin('Google')">
            <svg width="15" height="15" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Google
          </button>
          <button class="btn-social" onclick="socialLogin('LinkedIn')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="#0A66C2"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            LinkedIn
          </button>
        </div>

        <div class="divider">or sign in with email</div>

        <!-- Error banner -->
        <div class="err-banner" id="errBanner">
          <i class="fas fa-exclamation-circle"></i>
          <span id="errMsg">Incorrect email or password. Please try again.</span>
        </div>

        <div class="field">
          <label>Email address</label>
          <input type="email" id="loginEmail" placeholder="you@example.com" autocomplete="email">
        </div>

        <div class="field">
          <div class="field-header">
            <label>Password</label>
            <a href="#" class="forgot-link" onclick="showForgot(event)">Forgot password?</a>
          </div>
          <div class="field-pw">
            <input type="password" id="loginPw" placeholder="Your password" autocomplete="current-password" onkeydown="if(event.key==='Enter')doLogin()">
            <span class="pw-eye" onclick="togglePw('loginPw',this)"><i class="fas fa-eye-slash"></i></span>
          </div>
        </div>

        <div class="remember-row">
          <input type="checkbox" id="remember" checked>
          <label for="remember">Remember me for 30 days</label>
        </div>

        <button class="btn-submit" id="loginBtn" onclick="doLogin()">
          <div class="spinner" id="spinner"></div>
          <span id="loginBtnText">Sign in <i class="fas fa-arrow-right"></i></span>
        </button>

        <div class="signup-link">Don't have an account? <a href="javascript:void(0)" onclick="window.location.href='antcareers_signup.php?theme='+(document.body.classList.contains('light')?'light':'dark')">Create one free</a></div>
        <div class="terms-notice">Protected by AntCareers. <a href="#">Privacy Policy</a> · <a href="#">Terms</a></div>
      </div>

      <!-- FORGOT PASSWORD SCREEN -->
      <div class="forgot-screen" id="forgotScreen">
        <button class="btn-text" onclick="hideForgot()"><i class="fas fa-arrow-left"></i> Back to sign in</button>
        <div class="right-header">
          <div class="right-title">Reset password</div>
          <div class="right-sub">Enter your email and we'll send you a reset link.</div>
        </div>

        <div class="info-box" id="forgotSuccess" style="display:none;">
          <i class="fas fa-check-circle"></i>
          <span>Reset link sent! Check your inbox — it may take a minute or two to arrive.</span>
        </div>

        <div id="forgotForm">
          <div class="field">
            <label>Email address</label>
            <input type="email" id="forgotEmail" placeholder="you@example.com">
          </div>
          <button class="btn-submit" onclick="sendReset()">Send reset link <i class="fas fa-paper-plane"></i></button>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  function doLogin() {
    const email  = document.getElementById('loginEmail').value.trim();
    const pw     = document.getElementById('loginPw').value;
    const banner = document.getElementById('errBanner');
    banner.classList.remove('show');

    if (!email || !pw) {
      document.getElementById('errMsg').textContent = 'Please fill in all fields.';
      banner.classList.add('show'); return;
    }

    const btn  = document.getElementById('loginBtn');
    const txt  = document.getElementById('loginBtnText');
    const spin = document.getElementById('spinner');
    btn.disabled = true; txt.style.display = 'none'; spin.style.display = 'block';

    // Use server-rendered CSRF token — no extra round-trip needed
    fetch('login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        email,
        password:    pw,
        remember:    document.getElementById('remember').checked,
        csrf_token:  '<?php echo htmlspecialchars($_csrfToken, ENT_QUOTES, "UTF-8"); ?>',
      }),
    })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false; txt.style.display = 'flex'; spin.style.display = 'none';
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          document.getElementById('errMsg').textContent = data.message || 'Login failed. Please try again.';
          banner.classList.add('show');
          document.getElementById('loginEmail').classList.add('err');
          document.getElementById('loginPw').classList.add('err');
        }
      })
      .catch(() => {
        btn.disabled = false; txt.style.display = 'flex'; spin.style.display = 'none';
        document.getElementById('errMsg').textContent = 'Network error. Please try again.';
        banner.classList.add('show');
      });
  }

  // ── SYNC body.dark from html.dark-init set in <head> ──
  (function() {
    const p = new URLSearchParams(window.location.search).get('theme');
    const s = localStorage.getItem('ac-theme');
    const t = p || s || 'light';
    if (p) localStorage.setItem('ac-theme', p);
    if (t === 'dark') {
      document.body.classList.add('dark');
      document.body.classList.remove('light');
    } else {
      document.body.classList.add('light');
      document.body.classList.remove('dark');
      document.documentElement.classList.remove('dark-init');
    }
  })();

  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    btn.querySelector('i').className = showing ? 'fas fa-eye-slash' : 'fas fa-eye';
  }

  function showForgot(e) {
    e.preventDefault();
    document.getElementById('mainForm').classList.add('hide');
    document.getElementById('forgotScreen').classList.add('show');
  }
  function hideForgot() {
    document.getElementById('mainForm').classList.remove('hide');
    document.getElementById('forgotScreen').classList.remove('show');
  }

  function sendReset() {
    const email = document.getElementById('forgotEmail').value.trim();
    const re    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!re.test(email)) {
      document.getElementById('forgotEmail').classList.add('err'); return;
    }

    const btn = document.querySelector('#forgotForm .btn-submit');
    if (btn) { btn.disabled = true; btn.textContent = 'Sending…'; }

    fetch('forgot_password.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, csrf_token: '<?php echo htmlspecialchars($_csrfToken, ENT_QUOTES, "UTF-8"); ?>' }),
    })
      .then(r => r.json())
      .then(() => {
        // Always show success (endpoint never reveals if email exists)
        document.getElementById('forgotForm').style.display = 'none';
        document.getElementById('forgotSuccess').style.display = 'flex';
      })
      .catch(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = 'Send reset link <i class="fas fa-paper-plane"></i>'; }
        document.getElementById('forgotEmail').classList.add('err');
      });
  }

  function socialLogin(p) { alert(p + ' login — OAuth integration coming soon!'); }

  // Clear error state on input
  ['loginEmail','loginPw'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', () => {
      document.getElementById(id).classList.remove('err');
      document.getElementById('errBanner').classList.remove('show');
    });
  });
</script>
</body>
</html>