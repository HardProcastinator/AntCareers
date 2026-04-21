<?php
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
$_csrfToken = csrfToken(); // generate + store in session once, server-side
$serverError = trim((string)($_SESSION['login_error'] ?? ($_GET['error'] ?? '')));
unset($_SESSION['login_error']);
$platformStats = getPublicPlatformStats();
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
    html.dark-init .left-logo-text, body.dark .left-logo-text { color:var(--text-light); }
    html.dark-init .left-eyebrow, body.dark .left-eyebrow { color:var(--red-pale); }
    html.dark-init .left-heading, body.dark .left-heading { color:var(--text-light); }
    html.dark-init .left-sub, body.dark .left-sub { color:#7A6868; }
    html.dark-init .testimonial-text, body.dark .testimonial-text { color:#C0ACAA; }
    html.dark-init .testimonial-name, body.dark .testimonial-name { color:var(--text-light); }
    html.dark-init .testimonial-role, body.dark .testimonial-role { color:#7A6868; }
    html.dark-init .left-stat-num, body.dark .left-stat-num { color:var(--text-light); }
    html.dark-init .left-stat-label, body.dark .left-stat-label { color:#7A6868; }
    html.dark-init .left-footer, body.dark .left-footer { color:#4A3838; }
    html.dark-init .right-panel, body.dark .right-panel { background:var(--card); }
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
    html.dark-init .signup-link a, body.dark .signup-link a { color:var(--red-pale); }
    html.dark-init .err-banner.err-pending, body.dark .err-banner.err-pending { background:rgba(212,148,58,0.12); border-color:rgba(212,148,58,0.3); color:#E8B44A; }
    html.dark-init .err-banner.err-banned, body.dark .err-banner.err-banned { background:rgba(209,61,44,0.15); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    html.dark-init .err-banner.err-suspended, body.dark .err-banner.err-suspended { background:rgba(209,61,44,0.15); border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
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

    /* Error banner */
    .err-banner { background:#FFF5F4; border:1px solid #F0C8C4; border-radius:8px; padding:12px 14px; margin-bottom:16px; font-size:13px; color:var(--red-vivid); font-weight:500; display:none; align-items:center; gap:9px; }
    .err-banner.show { display:flex; }
    .err-banner i { flex-shrink:0; }
    .err-banner.err-pending { background:#FFF8EE; border-color:#F0D8A8; color:#B8860B; }
    .err-banner.err-pending i { color:#D4943A; }
    .err-banner.err-suspended { background:#FFF0EE; border-color:#F0B8B4; color:#C0392B; }
    .err-banner.err-banned { background:#FFF0EE; border-color:#F0B8B4; color:#C0392B; }

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

    @media(max-width:760px){html,body{overflow-x:hidden;max-width:100vw}.page,.right-panel{max-width:100%;overflow-x:hidden}}
    @media(max-width:860px) { .page{grid-template-columns:1fr} .left-panel{display:none} .right-panel{padding:70px 24px 40px;min-height:100vh} .right-back-home{left:20px;top:20px} }
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

      <!-- MAIN LOGIN FORM -->
      <div class="main-form" id="mainForm">
        <div class="right-header">
          <div class="right-title">Sign in</div>
          <div class="right-sub">Welcome back — let's find your next role.</div>
        </div>



        <form id="loginForm" method="post" action="login.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

          <!-- Error banner -->
          <div class="err-banner<?php echo $serverError !== '' ? ' show' : ''; ?>" id="errBanner">
            <i class="fas fa-exclamation-circle"></i>
            <span id="errMsg"><?php echo $serverError !== '' ? htmlspecialchars($serverError, ENT_QUOTES, 'UTF-8') : 'Incorrect email or password. Please try again.'; ?></span>
          </div>

          <div class="field">
            <label>Email address</label>
            <input type="email" id="loginEmail" name="email" placeholder="you@example.com" autocomplete="email">
          </div>

          <div class="field">
            <label>Password</label>
            <div class="field-pw">
              <input type="password" id="loginPw" name="password" placeholder="Your password" autocomplete="current-password">
              <span class="pw-eye" onclick="togglePw('loginPw',this)"><i class="fas fa-eye-slash"></i></span>
            </div>
          </div>

          <div class="remember-row">
            <input type="checkbox" id="remember" name="remember" checked>
            <label for="remember">Remember me for 30 days</label>
          </div>

          <button class="btn-submit" id="loginBtn" type="submit">
            <div class="spinner" id="spinner"></div>
            <span id="loginBtnText">Sign in <i class="fas fa-arrow-right"></i></span>
          </button>
        </form>

        <div class="signup-link">Don't have an account? <a href="javascript:void(0)" onclick="window.location.href='antcareers_signup.php?theme='+(document.body.classList.contains('light')?'light':'dark')">Create one free</a></div>
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
          banner.className = 'err-banner show';
          if (data.error_type === 'pending') banner.classList.add('err-pending');
          else if (data.error_type === 'suspended') banner.classList.add('err-suspended');
          else if (data.error_type === 'banned') banner.classList.add('err-banned');
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

  document.getElementById('loginForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    doLogin();
  });

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