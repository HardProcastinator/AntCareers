<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');

$db = getDB();
$userId = (int)$_SESSION['user_id'];
$company = false;

try {
    $stmt = $db->prepare("
        SELECT
            u.id AS user_id,
            u.full_name,
            u.email,
            'company_admin' AS employer_role,
            cp.id AS company_id,
            NULL AS job_title,
            COALESCE(cp.contact_phone, u.contact) AS phone,
            COALESCE(cp.company_name, u.company_name) AS company_name,
            cp.logo_path,
            cp.industry,
            cp.address_line,
            cp.city,
            cp.province,
            COALESCE(cp.contact_email, u.email) AS contact_email,
            COALESCE(cp.contact_phone, u.contact) AS contact_phone,
            cp.website,
            cp.about AS description,
            cp.updated_at
        FROM users u
        LEFT JOIN company_profiles cp ON cp.user_id = u.id
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $userId]);
    $company = $stmt->fetch();
} catch (Throwable $e) {
    error_log('[AntCareers] Company profile query failed: ' . $e->getMessage());
}

if (!$company) {
    try {
        $fallbackStmt = $db->prepare("
            SELECT
                id AS user_id,
                full_name,
                email,
                'company_admin' AS employer_role,
                NULL AS company_id,
                NULL AS job_title,
                contact AS phone,
                company_name,
                NULL AS logo_path,
                NULL AS industry,
                NULL AS address_line,
                NULL AS city,
                NULL AS province,
                email AS contact_email,
                contact AS contact_phone,
                NULL AS website,
                NULL AS description,
                updated_at
            FROM users
            WHERE id = :user_id
              AND account_type = 'employer'
            LIMIT 1
        ");
        $fallbackStmt->execute([':user_id' => $userId]);
        $company = $fallbackStmt->fetch();
    } catch (Throwable $e2) {
        error_log('[AntCareers] Company profile fallback failed: ' . $e2->getMessage());
    }
}

if (!$company) {
    header('Location: employer_dashboard.php?profile_error=1');
    exit;
}

$fullName = trim((string)($company['full_name'] ?? 'Employer'));
$nameParts = preg_split('/\s+/', $fullName) ?: [];
$firstName = $nameParts[0] ?? 'Employer';

if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $initials = strtoupper(substr($firstName, 0, 2));
}

$success = isset($_GET['success']);
$error = isset($_GET['error']);
$roleLabel = ((string)$company['employer_role'] === 'company_admin') ? 'Company Admin' : 'Recruiter';
$companyName = trim((string)($company['company_name'] ?? ($_SESSION['company_name'] ?? 'Your Company')));
$navActive   = 'profile';
$navbarShowMessage = false;
$navbarShowNotif   = false;
$navbarShowPostJob = false;
$navbarShowHamburger = false;
$navbarShowMobileMenu = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Company Profile</title>

  <script>
    (function(){
      const p = new URLSearchParams(window.location.search).get('theme');
      const t = p || localStorage.getItem('ac-theme') || 'light';
      if (p) localStorage.setItem('ac-theme', p);
      if (t === 'light') document.documentElement.classList.add('theme-light');
    })();
  </script>

  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }

    html { overflow-x:hidden; }
    body {
      font-family:var(--font-body);
      background:var(--soil-dark);
      color:var(--text-light);
      overflow-x:hidden;
      min-height:100vh;
      -webkit-font-smoothing:antialiased;
    }

    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; }

    .navbar { position:sticky; top:0; z-index:200; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }

    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link {
      font-size:13px; font-weight:600; color:#A09090; text-decoration:none;
      padding:7px 11px; border-radius:6px; transition:all 0.2s; display:flex;
      align-items:center; gap:5px; white-space:nowrap;
    }
    .nav-link:hover, .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }

    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn {
      width:34px; height:34px; border-radius:7px; background:var(--soil-hover);
      border:1px solid var(--soil-line); color:var(--text-muted); display:flex;
      align-items:center; justify-content:center; cursor:pointer;
    }

    .profile-wrap { position: relative; z-index: 500; flex-shrink: 0; }
    .profile-btn {
      display:flex; align-items:center; gap:10px; min-width:210px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer;
    }
    .profile-avatar {
      width:28px; height:28px; border-radius:50%;
      background:linear-gradient(135deg, var(--red-vivid), var(--red-deep));
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:700; color:#fff;
    }
    .profile-meta { min-width: 0; flex: 1; text-align: left; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .profile-role { font-size:10px; color:var(--amber); margin-top:2px; font-weight:600; }
    .profile-chevron { font-size:10px; color:var(--text-muted); }

    .profile-dropdown {
      position:absolute; top:calc(100% + 8px); right:0; width:230px;
      background:var(--soil-card); border:1px solid var(--soil-line);
      border-radius:12px; padding:6px; opacity:0; visibility:hidden;
      transform:translateY(-6px); transition:all 0.18s ease;
      z-index:9999; box-shadow:0 20px 40px rgba(0,0,0,0.5); pointer-events:none;
    }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); pointer-events:auto; }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:3px; font-weight:600; }

    .pd-item {
      display:flex; align-items:center; gap:10px; padding:9px 12px;
      border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid);
      text-decoration:none;
    }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }

    .page-shell { max-width:1380px; margin:0 auto; padding:32px 24px 80px; position:relative; z-index:2; }
    .content-layout { display:grid; grid-template-columns:244px 1fr; gap:28px; }

    .sidebar { position:sticky; top:72px; height:fit-content; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .sidebar-profile { padding:16px 16px 14px; border-bottom:1px solid var(--soil-line); }
    .sp-inner { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .sp-avatar {
      width:42px; height:42px; border-radius:50%;
      background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));
      display:flex; align-items:center; justify-content:center;
      font-size:15px; font-weight:700; color:#fff;
    }
    .sp-name { font-size:13px; font-weight:700; color:#F5F0EE; }
    .sp-role { font-size:10px; color:var(--red-pale); font-weight:600; margin-top:2px; }
    .prog-label { display:flex; justify-content:space-between; font-size:10px; color:var(--text-muted); margin-bottom:5px; font-weight:600; text-transform:uppercase; }
    .prog-bar { height:5px; background:var(--soil-hover); border-radius:3px; overflow:hidden; }
    .prog-fill { height:100%; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); border-radius:3px; }

    .sb-nav-item {
      display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px;
      font-weight:600; color:var(--text-muted); transition:all 0.18s;
      width:100%; text-align:left; border-bottom:1px solid var(--soil-line); text-decoration:none;
    }
    .sb-nav-item:hover { color:#F5F0EE; background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }

    .main-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; overflow:hidden; }
    .card-head { padding:22px 24px 18px; border-bottom:1px solid var(--soil-line); }
    .eyebrow { color:var(--red-pale); font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; margin-bottom:8px; }
    .card-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .card-sub { color:var(--text-muted); font-size:14px; line-height:1.6; }
    .card-body { padding:24px; }

    .alert { border-radius:10px; padding:13px 15px; font-size:13px; font-weight:600; margin-bottom:18px; }
    .alert.success { background:rgba(76,175,112,.10); border:1px solid rgba(76,175,112,.25); color:#8fe0a8; }
    .alert.error { background:rgba(224,85,85,.10); border:1px solid rgba(224,85,85,.25); color:#ff9f9f; }

    .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:18px; }
    .field { display:flex; flex-direction:column; gap:8px; }
    .field.full { grid-column:1 / -1; }

    .field label {
      font-size:12px;
      font-weight:700;
      letter-spacing:.04em;
      text-transform:uppercase;
      color:var(--text-muted);
    }

    .input, .textarea {
      width:100%;
      background:var(--soil-hover);
      border:1px solid var(--soil-line);
      border-radius:10px;
      padding:14px 14px;
      color:#F5F0EE;
      font-family:var(--font-body);
      font-size:14px;
      outline:none;
    }
    .input:focus, .textarea:focus {
      border-color:rgba(209,61,44,.5);
      box-shadow:0 0 0 3px rgba(209,61,44,.10);
    }
    .textarea { min-height:140px; resize:vertical; }

    .actions {
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:22px;
    }

    .btn-red {
      padding:12px 18px; border-radius:8px; background:var(--red-vivid);
      border:none; color:#fff; font-family:var(--font-body); font-size:13px;
      font-weight:700; cursor:pointer;
    }
    .btn-ghost {
      padding:12px 18px; border-radius:8px; background:transparent;
      border:1px solid var(--soil-line); color:var(--text-mid);
      font-family:var(--font-body); font-size:13px; font-weight:700;
      text-decoration:none;
    }

    .meta-note {
      margin-top:14px;
      font-size:12px;
      color:var(--text-muted);
    }

    html.theme-light body, body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555; --amber:#B8620A;
    }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; }
    body.light .logo-text, body.light .card-title, body.light .sp-name, body.light .profile-name { color:#1A0A09; }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn, body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .profile-dropdown, body.light .sidebar-card, body.light .main-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .sb-nav-item:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .input, body.light .textarea { background:#FFFFFF; border-color:#E0CECA; color:#1A0A09; }

    @media(max-width:1060px) {
      .content-layout { grid-template-columns:1fr; }
      .sidebar { position:static; }
    }
    @media(max-width:760px) {
      .nav-links { display:none; }
      .page-shell { padding:20px 16px 60px; }
      .nav-inner { padding:0 16px; }
      .profile-name, .profile-role { display:none; }
      .profile-btn { padding:6px 8px; min-width:auto; }
      .form-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g stroke="#C0392B" stroke-width="1.5" fill="none" opacity="0.6">
      <path d="M0 200 Q200 180 350 240 Q500 300 600 260 Q750 210 900 280 Q1050 350 1200 300 Q1320 260 1440 280"/>
      <path d="M0 450 Q150 430 300 490 Q500 560 650 510 Q800 460 950 530 Q1100 600 1300 550 Q1380 530 1440 540"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<div class="page-shell">
  <div class="content-layout">

    <aside class="sidebar">
      <div class="sidebar-card">
        <div class="sidebar-profile">
          <div class="sp-inner">
            <div class="sp-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
            <div>
              <div class="sp-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="sp-role"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
          </div>
          <div class="prog-label"><span>Company Module</span><span>Week 2</span></div>
          <div class="prog-bar"><div class="prog-fill" style="width:76%"></div></div>
        </div>

        <a class="sb-nav-item" href="employer_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a class="sb-nav-item active" href="company_profile.php"><i class="fas fa-building"></i> Company Profile</a>
        <a class="sb-nav-item" href="employer_manageRecruiters.php"><i class="fas fa-users"></i> Manage Recruiters</a>
        <a class="sb-nav-item" href="employer_browseJobs.php"><i class="fas fa-search"></i> Browse Jobs</a>
      </div>
    </aside>

    <main>
      <section class="main-card">
        <div class="card-head">
          <div class="eyebrow">Employer Company Profile</div>
          <div class="card-title">Build your company profile.</div>
          <div class="card-sub">Keep your company details updated so job seekers can learn more about your organization.</div>
        </div>

        <div class="card-body">
          <?php if ($success): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> Company profile updated successfully.</div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert error"><i class="fas fa-triangle-exclamation"></i> Failed to update company profile.</div>
          <?php endif; ?>

          <form action="update_company_profile.php" method="POST">
            <div class="form-grid">
              <div class="field">
                <label for="company_name">Company Name</label>
                <input class="input" type="text" id="company_name" name="company_name" value="<?= htmlspecialchars((string)($company['company_name'] ?? '')) ?>" required>
              </div>

              <div class="field">
                <label for="industry">Industry</label>
                <input class="input" type="text" id="industry" name="industry" value="<?= htmlspecialchars((string)($company['industry'] ?? '')) ?>">
              </div>

              <div class="field full">
                <label for="address_line">Address Line</label>
                <input class="input" type="text" id="address_line" name="address_line" value="<?= htmlspecialchars((string)($company['address_line'] ?? '')) ?>">
              </div>

              <div class="field">
                <label for="city">City</label>
                <input class="input" type="text" id="city" name="city" value="<?= htmlspecialchars((string)($company['city'] ?? '')) ?>">
              </div>

              <div class="field">
                <label for="province">Province</label>
                <input class="input" type="text" id="province" name="province" value="<?= htmlspecialchars((string)($company['province'] ?? '')) ?>">
              </div>

              <div class="field">
                <label for="contact_email">Contact Email</label>
                <input class="input" type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars((string)($company['contact_email'] ?? '')) ?>">
              </div>

              <div class="field">
                <label for="contact_phone">Contact Phone</label>
                <input class="input" type="text" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars((string)($company['contact_phone'] ?? '')) ?>">
              </div>

              <div class="field full">
                <label for="website">Website</label>
                <input class="input" type="text" id="website" name="website" value="<?= htmlspecialchars((string)($company['website'] ?? '')) ?>">
              </div>

              <div class="field full">
                <label for="description">Company Description</label>
                <textarea class="textarea" id="description" name="description"><?= htmlspecialchars((string)($company['description'] ?? '')) ?></textarea>
              </div>
            </div>

            <div class="actions">
              <button type="submit" class="btn-red"><i class="fas fa-floppy-disk"></i> Save Company Profile</button>
              <a class="btn-ghost" href="employer_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <div class="meta-note">
              Last updated:
              <?= !empty($company['updated_at']) ? htmlspecialchars((string)$company['updated_at']) : 'Not yet updated' ?>
            </div>
          </form>
        </div>
      </section>
    </main>

  </div>
</div>

<script>
  const body = document.body;
  const themeToggle = document.getElementById('themeToggle');
  const rootLight = document.documentElement.classList.contains('theme-light');
  if (rootLight) body.classList.add('light');

  function setThemeIcon() {
    const icon = themeToggle ? themeToggle.querySelector('i') : null;
    if (!icon) return;
    icon.className = body.classList.contains('light') ? 'fas fa-sun' : 'fas fa-moon';
  }
  setThemeIcon();

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      body.classList.toggle('light');
      localStorage.setItem('ac-theme', body.classList.contains('light') ? 'light' : 'dark');
      setThemeIcon();
    });
  }

  const profileWrap = document.getElementById('profileWrap');
  const profileToggle = document.getElementById('profileToggle');
  const profileDropdown = document.getElementById('profileDropdown');

  if (profileToggle && profileDropdown && profileWrap) {
    profileToggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      profileDropdown.classList.toggle('open');
    });

    profileDropdown.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    document.addEventListener('click', function (e) {
      if (!profileWrap.contains(e.target)) {
        profileDropdown.classList.remove('open');
      }
    });
  }
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>