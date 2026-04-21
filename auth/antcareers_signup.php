<?php
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
$_csrfToken = csrfToken();
$platformStats = getPublicPlatformStats();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join AntCareers</title>
  <script>
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
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
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
    html.dark-init .left-logo-text, body.dark .left-logo-text { color:var(--text-light); }
    html.dark-init .left-eyebrow, body.dark .left-eyebrow { color:var(--red-pale); }
    html.dark-init .left-heading, body.dark .left-heading { color:var(--text-light); }
    html.dark-init .left-sub, body.dark .left-sub { color:#7A6868; }
    html.dark-init .left-stat-num, body.dark .left-stat-num { color:var(--text-light); }
    html.dark-init .left-stat-label, body.dark .left-stat-label { color:#7A6868; }
    html.dark-init .left-footer, body.dark .left-footer { color:#4A3838; }
    html.dark-init .right-panel, body.dark .right-panel { background:var(--card); }
    html.dark-init .type-card, body.dark .type-card { border-color:var(--line); }
    html.dark-init .type-card:hover, body.dark .type-card:hover { border-color:var(--red-pale); }
    html.dark-init .type-card.selected, body.dark .type-card.selected { background:rgba(209,61,44,0.1); border-color:var(--red-vivid); }
    html.dark-init .type-card-label, body.dark .type-card-label { color:var(--text); }
    html.dark-init .type-card-check, body.dark .type-card-check { background:var(--card); border-color:var(--line); }
    html.dark-init .field input, body.dark .field input { background:#131010; border-color:var(--line); color:var(--text); }
    html.dark-init .field input::placeholder, body.dark .field input::placeholder { color:#5A4848; }
    html.dark-init .field input:focus, body.dark .field input:focus { border-color:var(--red-vivid); }
    html.dark-init .field label, body.dark .field label { color:#A09090; }
    html.dark-init .summary-pill, body.dark .summary-pill { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); }
    html.dark-init .summary-pill-name, body.dark .summary-pill-name { color:var(--text); }
    html.dark-init .checkbox-field label, body.dark .checkbox-field label { color:var(--text-muted); }
    html.dark-init .terms-notice a, body.dark .terms-notice a { color:var(--red-pale); }
    html.dark-init .signin-link a, body.dark .signin-link a { color:var(--red-pale); }
    html.dark-init .btn-back, body.dark .btn-back { color:var(--text-muted); }
    html.dark-init .btn-back:hover, body.dark .btn-back:hover { color:var(--text); }
    html.dark-init .divider, body.dark .divider { color:var(--text-muted); }
    html.dark-init .right-title, body.dark .right-title { color:var(--text); }
    html.dark-init .right-sub, body.dark .right-sub { color:var(--text-muted); }
    html.dark-init .pw-label, body.dark .pw-label { color:var(--text-muted); }
    html.dark-init .step-dot, body.dark .step-dot { border-color:var(--line); color:var(--text-muted); background:var(--card); }
    html.dark-init .step-line, body.dark .step-line { background:var(--line); }
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
    .left-stats { display:flex; gap:32px; }
    .left-stat-num { font-family:var(--font-body); font-size:22px; font-weight:800; color:#1A0A09; }
    .left-stat-num span { color:var(--red-bright); }
    .left-stat-label { font-size:11px; color:#6A4040; margin-top:3px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; }
    .left-footer { position:relative; z-index:2; font-size:12px; color:#8A6060; }

    /* RIGHT */
    .right-panel { background:var(--card); display:flex; flex-direction:column; align-items:center; padding:80px 48px 48px; position:relative; overflow-y:auto; }
    @media(min-height:900px) { .right-panel { justify-content:center; padding-top:40px; } }
    .right-inner { width:100%; max-width:420px; }
    .right-back-home { position:fixed; top:28px; right:calc(50% - 420px/2 - 48px + 6px); }
    .btn-home { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--text-muted); text-decoration:none; transition:color .15s; }
    .btn-home:hover { color:var(--text); }
    .right-header { margin-bottom:24px; }
    .right-title { font-family:var(--font-display); font-size:26px; font-weight:700; color:var(--text); margin-bottom:5px; line-height:1.2; }
    .right-sub { font-size:14px; color:var(--text-muted); line-height:1.6; }

    /* STEPS */
    .steps { display:flex; align-items:center; margin-bottom:24px; }
    .step-dot { width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; border:2px solid var(--line); color:var(--text-muted); background:var(--card); transition:all .22s; flex-shrink:0; }
    .step-dot.active { border-color:var(--red-vivid); background:var(--red-vivid); color:#fff; }
    .step-dot.done { border-color:var(--red-vivid); background:var(--red-vivid); color:#fff; }
    .step-dot.done::after { content:'✓'; font-size:11px; }
    .step-dot.done span { display:none; }
    .step-line { flex:1; height:2px; background:var(--line); transition:background .22s; }
    .step-line.done { background:var(--red-vivid); }

    /* FORM STEPS */
    .form-step { display:none; }
    .form-step.active { display:block; }

    /* TYPE CARDS */
    .type-cards { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:4px; }
    .type-card { border:2px solid var(--line); border-radius:12px; padding:20px 16px; cursor:pointer; transition:all .2s; position:relative; display:block; }
    .type-card:hover { border-color:var(--red-pale); transform:translateY(-1px); }
    .type-card.selected { border-color:var(--red-vivid); background:#FFF5F4; }
    .type-card.flash { border-color:var(--red-vivid) !important; animation:flashBorder .5s ease; }
    @keyframes flashBorder { 0%,100%{border-color:var(--line)} 50%{border-color:var(--red-vivid)} }
    .type-card input[type="radio"] { position:absolute; opacity:0; width:0; height:0; }
    .type-card-check { position:absolute; top:12px; right:12px; width:18px; height:18px; border-radius:50%; border:2px solid var(--line); background:#fff; display:flex; align-items:center; justify-content:center; font-size:9px; color:transparent; transition:all .2s; }
    .type-card.selected .type-card-check { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .type-card-icon { font-size:26px; margin-bottom:10px; display:block; }
    .type-card-label { font-size:14px; font-weight:700; color:var(--text); margin-bottom:4px; }
    .type-card-sub { font-size:12px; color:var(--text-muted); line-height:1.4; }

    /* FIELDS */
    .field { margin-bottom:13px; }
    .field label { display:block; font-size:11px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--text-mid); margin-bottom:5px; }
    .field input { width:100%; padding:11px 14px; background:#FDFAFA; border:1.5px solid var(--line); border-radius:8px; font-family:var(--font-body); font-size:14px; color:var(--text); outline:none; transition:border-color .2s,box-shadow .2s; }
    .field input::placeholder { color:#C0A8A4; }
    .field input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,.1); }
    .field input.err { border-color:var(--red-vivid); background:#FFF5F4; }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .field-pw { position:relative; }
    .field-pw input { padding-right:42px; }
    .pw-eye { position:absolute; right:13px; top:50%; transform:translateY(-50%); color:var(--text-muted); cursor:pointer; font-size:13px; transition:color .15s; }
    .pw-eye:hover { color:var(--text); }
    .pw-strength { display:flex; gap:4px; margin-top:6px; }
    .pw-bar { flex:1; height:3px; border-radius:2px; background:var(--line); transition:background .3s; }
    .pw-bar.weak { background:#D13D2C; } .pw-bar.fair { background:#D4943A; } .pw-bar.strong { background:#4CAF70; }
    .pw-label { font-size:11px; color:var(--text-muted); margin-top:4px; font-weight:600; }
    .err-msg { font-size:11px; color:var(--red-vivid); margin-top:4px; font-weight:500; display:none; }
    .show-err { display:block; }

    /* BUTTONS */
    .btn-submit { width:100%; padding:13px; background:var(--red-vivid); border:none; border-radius:8px; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; letter-spacing:.02em; cursor:pointer; transition:all .2s; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; }
    .btn-submit:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 6px 20px rgba(209,61,44,.28); }
    .btn-back { background:none; border:none; font-family:var(--font-body); font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; padding:0 0 18px 0; display:flex; align-items:center; gap:5px; transition:color .15s; }
    .btn-back:hover { color:var(--text); }

    .divider { display:flex; align-items:center; gap:12px; margin:14px 0; color:var(--text-muted); font-size:12px; font-weight:500; }
    .divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--line); }

    /* TERMS */
    .checkbox-field { display:flex; align-items:flex-start; gap:10px; margin-bottom:11px; }
    .checkbox-field input[type="checkbox"] { width:15px; height:15px; margin-top:2px; accent-color:var(--red-vivid); flex-shrink:0; }
    .checkbox-field label { font-size:12px; color:var(--text-muted); line-height:1.5; cursor:pointer; }
    .checkbox-field a { color:var(--red-mid); text-decoration:none; }

    .signin-link { text-align:center; margin-top:18px; font-size:13px; color:var(--text-muted); }
    .signin-link a { color:var(--red-vivid); font-weight:600; text-decoration:none; }
    .terms-notice { text-align:center; font-size:11px; color:var(--text-muted); margin-top:12px; line-height:1.5; }
    .terms-notice a { color:var(--red-mid); text-decoration:none; }

    /* SUMMARY PILL */
    .summary-pill { display:flex; align-items:center; gap:12px; background:#FFF5F4; border:1px solid #F0D0CC; border-radius:10px; padding:14px 16px; margin-bottom:20px; }
    .summary-pill-name { font-size:14px; font-weight:700; color:#1A0A09; }
    .summary-pill-sub { font-size:12px; color:#8A6060; margin-top:2px; }
    .summary-badge { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#D13D2C; background:rgba(209,61,44,.1); padding:3px 9px; border-radius:4px; margin-left:auto; white-space:nowrap; }

    /* SUCCESS */
    .success-screen { text-align:center; padding:10px 0; display:none; }
    .success-icon { width:64px; height:64px; background:var(--red-vivid); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-size:26px; color:#fff; animation:popIn .4s cubic-bezier(.175,.885,.32,1.275); }
    @keyframes popIn { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
    .success-title { font-family:var(--font-display); font-size:24px; font-weight:700; color:var(--text); margin-bottom:8px; }
    .success-sub { font-size:14px; color:var(--text-muted); line-height:1.6; margin-bottom:28px; }
    .btn-success { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:13px 32px; background:var(--red-vivid); border:none; border-radius:8px; color:#fff; font-family:var(--font-body); font-size:14px; font-weight:700; cursor:pointer; transition:all .2s; }
    .btn-success:hover { background:var(--red-bright); }

    @media(max-width:760px){html,body{overflow-x:hidden;max-width:100vw}.page,.right-panel{max-width:100%;overflow-x:hidden}}
    @media(max-width:860px) { .page{grid-template-columns:1fr} .left-panel{display:none} .right-panel{padding:70px 24px 40px;min-height:100vh} .right-back-home{right:auto;left:20px;top:20px} }
    @media(max-width:480px) { .field-row{grid-template-columns:1fr} .type-cards{grid-template-columns:1fr} }
  </style>
</head>
<body>

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
      <div class="left-eyebrow">Join the network</div>
      <h2 class="left-heading">Your next role<br>is <em>already</em><br>waiting.</h2>
      <p class="left-sub">Create your free account and get matched with opportunities that fit your skills, your pace, your goals.</p>
      <div class="left-stats">
        <div><div class="left-stat-num"><?php echo number_format((int)$platformStats['live_jobs']); ?><span>+</span></div><div class="left-stat-label">Live roles</div></div>
        <div><div class="left-stat-num"><?php echo number_format((int)$platformStats['companies_hiring']); ?><span>+</span></div><div class="left-stat-label">Companies hiring</div></div>
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

      <div class="steps" id="stepsBar">
        <div class="step-dot active" id="dot1"><span>1</span></div>
        <div class="step-line" id="line1"></div>
        <div class="step-dot" id="dot2"><span>2</span></div>
        <div class="step-line" id="line2"></div>
        <div class="step-dot" id="dot3"><span>3</span></div>
      </div>

      <!-- STEP 1: Choose type -->
      <div class="form-step active" id="step1">
        <div class="right-header">
          <div class="right-title">Welcome aboard.</div>
          <div class="right-sub">How will you be using AntCareers?</div>
        </div>
        <div class="type-cards">
          <label class="type-card" id="card-seeker" onclick="selectType('seeker')">
            <input type="radio" name="accountType" value="seeker">
            <div class="type-card-check"><i class="fas fa-check"></i></div>
            <span class="type-card-icon">🔍</span>
            <div class="type-card-label">Job Seeker</div>
            <div class="type-card-sub">Find roles and apply to companies</div>
          </label>
          <label class="type-card" id="card-employer" onclick="selectType('employer')">
            <input type="radio" name="accountType" value="employer">
            <div class="type-card-check"><i class="fas fa-check"></i></div>
            <span class="type-card-icon">🏢</span>
            <div class="type-card-label">Employer</div>
            <div class="type-card-sub">Post jobs and find talent</div>
          </label>
        </div>
        <button class="btn-submit" style="margin-top:20px;" onclick="goStep2()">
          Continue <i class="fas fa-arrow-right"></i>
        </button>
        <div class="signin-link">Already have an account? <a href="javascript:void(0)" onclick="window.location.href='antcareers_login.php?theme='+(document.body.classList.contains('light')?'light':'dark')">Sign in</a></div>
      </div>

      <!-- STEP 2a: Job Seeker -->
      <div class="form-step" id="step2-seeker">
        <button class="btn-back" onclick="goStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
        <div class="right-header">
          <div class="right-title">Your details</div>
          <div class="right-sub">Tell us a bit about yourself.</div>
        </div>
        <div class="field-row">
          <div class="field"><label>First name</label><input type="text" id="s-first" placeholder="Maria"></div>
          <div class="field"><label>Last name</label><input type="text" id="s-last" placeholder="Santos"></div>
        </div>
        <div class="field"><label>Email address</label><input type="email" id="s-email" placeholder="maria@email.com"><div class="err-msg" id="s-email-err">Please enter a valid email address.</div></div>
        <div class="field"><label>Contact number</label><input type="tel" id="s-contact" placeholder="+63 917 000 0000"><div class="err-msg" id="s-contact-err">Enter a valid phone number (7–15 digits).</div></div>
        <div class="field">
          <label>Password</label>
          <div class="field-pw">
            <input type="password" id="s-pw" placeholder="At least 8 characters" oninput="checkPw('s-pw','s-bars','s-plabel')">
            <span class="pw-eye" onclick="togglePw('s-pw',this)"><i class="fas fa-eye-slash"></i></span>
          </div>
          <div class="pw-strength" id="s-bars"><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div></div>
          <div class="pw-label" id="s-plabel"></div>
        </div>
        <div class="field">
          <label>Confirm password</label>
          <div class="field-pw">
            <input type="password" id="s-pw2" placeholder="Repeat password">
            <span class="pw-eye" onclick="togglePw('s-pw2',this)"><i class="fas fa-eye-slash"></i></span>
          </div>
          <div class="err-msg" id="s-pw-err">Passwords do not match.</div>
        </div>
        <button class="btn-submit" onclick="validateAndNext('seeker')">Continue <i class="fas fa-arrow-right"></i></button>
      </div>

      <!-- STEP 2b: Employer -->
      <div class="form-step" id="step2-employer">
        <button class="btn-back" onclick="goStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
        <div class="right-header">
          <div class="right-title">Company details</div>
          <div class="right-sub">Set up your employer account.</div>
        </div>
        <div class="field-row">
          <div class="field"><label>First name</label><input type="text" id="e-first" placeholder="Juan"></div>
          <div class="field"><label>Last name</label><input type="text" id="e-last" placeholder="dela Cruz"></div>
        </div>
        <div class="field"><label>Company name</label><input type="text" id="e-company" placeholder="Acme Corp"></div>
        <div class="field"><label>Company email</label><input type="email" id="e-email" placeholder="hr@acmecorp.com"><div class="err-msg" id="e-email-err">Please enter a valid email address.</div></div>
        <div class="field"><label>Contact number</label><input type="tel" id="e-contact" placeholder="+63 917 000 0000"><div class="err-msg" id="e-contact-err">Enter a valid phone number (7–15 digits).</div></div>
        <div class="field">
          <label>Password</label>
          <div class="field-pw">
            <input type="password" id="e-pw" placeholder="At least 8 characters" oninput="checkPw('e-pw','e-bars','e-plabel')">
            <span class="pw-eye" onclick="togglePw('e-pw',this)"><i class="fas fa-eye-slash"></i></span>
          </div>
          <div class="pw-strength" id="e-bars"><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div><div class="pw-bar"></div></div>
          <div class="pw-label" id="e-plabel"></div>
        </div>
        <div class="field">
          <label>Confirm password</label>
          <div class="field-pw">
            <input type="password" id="e-pw2" placeholder="Repeat password">
            <span class="pw-eye" onclick="togglePw('e-pw2',this)"><i class="fas fa-eye-slash"></i></span>
          </div>
          <div class="err-msg" id="e-pw-err">Passwords do not match.</div>
        </div>
        <button class="btn-submit" onclick="validateAndNext('employer')">Continue <i class="fas fa-arrow-right"></i></button>
      </div>

      <!-- STEP 3: Terms -->
      <div class="form-step" id="step3">
        <button class="btn-back" onclick="backToStep2()"><i class="fas fa-arrow-left"></i> Back</button>
        <div class="right-header">
          <div class="right-title">Almost there.</div>
          <div class="right-sub">Review and create your account.</div>
        </div>
        <div class="summary-pill" id="summaryPill">
          <span id="summaryIcon" style="font-size:22px;"></span>
          <div>
            <div class="summary-pill-name" id="summaryName"></div>
            <div class="summary-pill-sub" id="summaryTypeTxt"></div>
          </div>
          <span class="summary-badge" id="summaryBadge"></span>
        </div>
        <div id="signupErrBanner" style="display:none;background:rgba(209,61,44,0.1);border:1px solid rgba(209,61,44,0.3);color:#F07060;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:12px;"></div>
        <button class="btn-submit" id="createBtn" onclick="submitForm()">Create account <i class="fas fa-check"></i></button>

      </div>

      <!-- SUCCESS -->
      <div class="success-screen" id="successScreen">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <div class="success-title" id="successTitle">Welcome to AntCareers!</div>
        <div class="success-sub" id="successSub">Your account has been created. You're ready to explore opportunities.</div>
        <button class="btn-success" id="successBtn" onclick="redirectAfterSignup()">Go to Dashboard <i class="fas fa-arrow-right"></i></button>
      </div>

    </div>
  </div>
</div>

<script>
  let selectedType = null, activeStep2 = null;

  function selectType(t) {
    selectedType = t;
    ['seeker','employer'].forEach(id => document.getElementById('card-'+id).classList.toggle('selected', id===t));
  }

  function goStep2() {
    if (!selectedType) {
      ['card-seeker','card-employer'].forEach(id => {
        const el = document.getElementById(id);
        el.style.borderColor = '#D13D2C';
        setTimeout(() => el.style.borderColor = '', 700);
      });
      return;
    }
    activeStep2 = selectedType;
    hide('step1'); show('step2-'+selectedType); setDots(2);
  }

  function goStep(n) {
    ['step1','step2-seeker','step2-employer','step3'].forEach(hide);
    if (n===1) show('step1');
    setDots(n);
  }

  function backToStep2() {
    document.getElementById('signupErrBanner').style.display = 'none';
    hide('step3'); show('step2-'+activeStep2); setDots(2);
  }

  function validatePhone(val) {
    const digits = val.replace(/\D/g, '');
    return digits.length >= 7 && digits.length <= 15;
  }

  function validateAndNext(type) {
    const p = type==='seeker' ? 's' : 'e';
    const first = v(p+'-first'), last = v(p+'-last');
    const email = v(p==='s'?'s-email':'e-email');
    const contact = v(p+'-contact');
    const pw = v(p+'-pw'), pw2 = v(p+'-pw2');

    // Validate email format
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!first||!last||!email||pw.length<8) return;
    if (!emailRe.test(email)) {
      const emailInp = document.getElementById(p+'-email');
      const emailErr = document.getElementById(p+'-email-err');
      emailInp.classList.add('err'); emailErr.classList.add('show-err');
      return;
    }

    // Validate phone
    const contactErr = document.getElementById(p+'-contact-err');
    if (contact && !validatePhone(contact)) {
      document.getElementById(p+'-contact').classList.add('err');
      contactErr.classList.add('show-err');
      return;
    }
    contactErr.classList.remove('show-err');
    document.getElementById(p+'-contact').classList.remove('err');

    const errEl = document.getElementById(p+'-pw-err');
    if (pw !== pw2) { errEl.classList.add('show-err'); return; }
    errEl.classList.remove('show-err');

    // Fill summary
    document.getElementById('summaryIcon').textContent = type==='seeker'?'🔍':'🏢';
    document.getElementById('summaryName').textContent = first+' '+last;
    document.getElementById('summaryTypeTxt').textContent = type==='seeker'?'Job Seeker account':'Employer account';
    document.getElementById('summaryBadge').textContent = type==='seeker'?'Seeker':'Employer';

    hide('step2-'+type); show('step3'); setDots(3);
  }

  function setDots(active) {
    for(let i=1;i<=3;i++){
      const d=document.getElementById('dot'+i);
      d.classList.remove('active','done');
      if(i<active) d.classList.add('done');
      else if(i===active) d.classList.add('active');
      if(i<3) document.getElementById('line'+i).classList.toggle('done',i<active);
    }
  }

  function checkPw(inputId, barsId, labelId) {
    const val = document.getElementById(inputId).value;
    const bars = document.getElementById(barsId).querySelectorAll('.pw-bar');
    const lbl = document.getElementById(labelId);
    bars.forEach(b => b.className='pw-bar');
    let s=0;
    if(val.length>=8) s++;
    if(/[A-Z]/.test(val)) s++;
    if(/[0-9]/.test(val)) s++;
    if(/[^A-Za-z0-9]/.test(val)) s++;
    const cls=['','weak','fair','fair','strong'];
    const labels=['','Weak','Fair','Good','Strong'];
    const cols={weak:'#D13D2C',fair:'#D4943A',strong:'#4CAF70'};
    for(let i=0;i<s;i++) bars[i].classList.add(cls[s]);
    lbl.textContent = val.length ? labels[s] : '';
    lbl.style.color = cols[cls[s]]||'#8A6060';
  }

  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type==='text';
    inp.type = show?'password':'text';
    btn.querySelector('i').className = show?'fas fa-eye-slash':'fas fa-eye';
  }

  let _signupRedirect = null;

  function submitForm() {
    const isSeeker = selectedType === 'seeker';
    const p        = isSeeker ? 's' : 'e';

    const payload = {
      first_name:   document.getElementById(p + '-first').value.trim(),
      last_name:    document.getElementById(p + '-last').value.trim(),
      email:        document.getElementById(p + '-email').value.trim(),
      password:     document.getElementById(p + '-pw').value,
      account_type: selectedType,
      contact:      document.getElementById(p + '-contact')?.value.trim() || '',
      company_name: isSeeker ? '' : (document.getElementById('e-company')?.value.trim() || ''),
    };

    const btn = document.getElementById('createBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account…';

    const errBanner = document.getElementById('signupErrBanner');
    errBanner.style.display = 'none';

    payload.csrf_token = '<?php echo htmlspecialchars($_csrfToken, ENT_QUOTES, "UTF-8"); ?>';
    fetch('register.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = 'Create account <i class="fas fa-check"></i>';
        if (data.success) {
          _signupRedirect = data.redirect;
          hide('step3');
          document.getElementById('stepsBar').style.display = 'none';
          if (data.pending) {
            document.getElementById('successTitle').textContent = 'Registration Submitted!';
            document.getElementById('successSub').textContent = 'Your company account is pending admin approval. You\'ll be notified once it has been reviewed.';
            document.getElementById('successBtn').innerHTML = 'Go to Login <i class="fas fa-arrow-right"></i>';
          }
          document.getElementById('successScreen').style.display = 'block';
        } else {
          errBanner.textContent = data.message || 'Registration failed. Please try again.';
          errBanner.style.display = 'block';
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = 'Create account <i class="fas fa-check"></i>';
        errBanner.textContent = 'Network error. Please check your connection and try again.';
        errBanner.style.display = 'block';
      });
  }

  function redirectAfterSignup() {
    if (_signupRedirect) {
      window.location.href = _signupRedirect;
    } else {
      // Fallback if redirect URL wasn't captured
      window.location.href = selectedType === 'employer'
        ? '../employer/employer_dashboard.php'
        : '../seeker/antcareers_seekerDashboard.php';
    }
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


  function show(id) { document.getElementById(id).classList.add('active'); }
  function hide(id) { document.getElementById(id).classList.remove('active'); }
  function v(id) { return document.getElementById(id)?.value.trim()||''; }

  // ── Email validation on blur ──
  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  ['s-email','e-email'].forEach(id => {
    const inp = document.getElementById(id);
    const err = document.getElementById(id + '-err');
    if (!inp || !err) return;
    inp.addEventListener('blur', () => {
      if (inp.value.trim() && !emailRe.test(inp.value.trim())) {
        inp.classList.add('err');
        err.classList.add('show-err');
      }
    });
    inp.addEventListener('input', () => {
      if (!inp.value.trim() || emailRe.test(inp.value.trim())) {
        inp.classList.remove('err');
        err.classList.remove('show-err');
      }
    });
  });

  // ── Phone number validation: strip invalid chars + validate on blur ──
  ['s-contact','e-contact'].forEach(id => {
    const inp = document.getElementById(id);
    const err = document.getElementById(id + '-err');
    if (!inp) return;
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/[^0-9+\s]/g, '');
      // Clear error once valid
      if (!inp.value.trim() || validatePhone(inp.value)) {
        inp.classList.remove('err');
        if (err) err.classList.remove('show-err');
      }
    });
    inp.addEventListener('blur', () => {
      if (inp.value.trim() && !validatePhone(inp.value)) {
        inp.classList.add('err');
        if (err) err.classList.add('show-err');
      }
    });
  });
</script>
</body>
</html>