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
$navActive   = '';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── AJAX: update_profile ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    header('Content-Type: application/json');
    try {
        $fname      = trim($_POST['full_name'] ?? '');
        $position   = trim($_POST['position'] ?? '');
        $department  = trim($_POST['department'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $bio        = trim($_POST['bio'] ?? '');

        if ($fname === '') {
            echo json_encode(['success' => false, 'error' => 'Full name is required.']);
            exit;
        }

        $db->beginTransaction();
        $s = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $s->execute([$fname, $uid]);

        $s = $db->prepare("
            INSERT INTO recruiter_profiles (user_id, position, department, phone, bio)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE position = VALUES(position), department = VALUES(department),
                                    phone = VALUES(phone), bio = VALUES(bio)
        ");
        $s->execute([$uid, $position, $department, $phone, $bio]);
        $db->commit();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[AntCareers] recruiter update_profile: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}

/* ── AJAX: upload_avatar ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_avatar') {
    header('Content-Type: application/json');
    try {
        if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
            exit;
        }
        $file = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type. Use JPG, PNG, GIF, or WEBP.']);
            exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'File too large. Max 2MB.']);
            exit;
        }
        $ext = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif'  => 'gif', 'image/webp' => 'webp', default => 'jpg',
        };
        $newName = 'avatar_' . $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = dirname(__DIR__) . '/uploads/avatars/' . $newName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }
        $relPath = 'uploads/avatars/' . $newName;
        $s = $db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $s->execute([$relPath, $uid]);
        echo json_encode(['success' => true, 'avatar_url' => '../' . $relPath]);
    } catch (\Throwable $e) {
        error_log('[AntCareers] recruiter upload_avatar: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Upload failed.']);
    }
    exit;
}

/* ── Fetch user data ── */
$userRow = [];
try {
    $s = $db->prepare("SELECT full_name, email, avatar_url FROM users WHERE id = ?");
    $s->execute([$uid]);
    $userRow = $s->fetch() ?: [];
} catch (PDOException $e) { error_log('[AntCareers] recruiter profile user: ' . $e->getMessage()); }

$profile = [];
try {
    $s = $db->prepare("SELECT * FROM recruiter_profiles WHERE user_id = ?");
    $s->execute([$uid]);
    $profile = $s->fetch() ?: [];
} catch (PDOException $e) { error_log('[AntCareers] recruiter profile data: ' . $e->getMessage()); }

$recruiterRec = [];
try {
    $s = $db->prepare("SELECT r.*, cp.company_name FROM recruiters r JOIN company_profiles cp ON cp.id = r.company_id WHERE r.user_id = ?");
    $s->execute([$uid]);
    $recruiterRec = $s->fetch() ?: [];
} catch (PDOException $e) { error_log('[AntCareers] recruiter record: ' . $e->getMessage()); }

/* ── Stats ── */
$statsJobs = $statsApplicants = $statsHires = 0;
try {
    $s = $db->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id = ?");
    $s->execute([$uid]);
    $statsJobs = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.recruiter_id = ?");
    $s->execute([$uid]);
    $statsApplicants = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.recruiter_id = ? AND a.status = 'Hired'");
    $s->execute([$uid]);
    $statsHires = (int)$s->fetchColumn();
} catch (PDOException $e) { error_log('[AntCareers] recruiter profile stats: ' . $e->getMessage()); }

$email       = htmlspecialchars($userRow['full_name'] ?? $fullName, ENT_QUOTES, 'UTF-8');
$emailAddr   = htmlspecialchars($userRow['email'] ?? '', ENT_QUOTES, 'UTF-8');
$pPosition   = htmlspecialchars($profile['position'] ?? '', ENT_QUOTES, 'UTF-8');
$pDepartment = htmlspecialchars($profile['department'] ?? '', ENT_QUOTES, 'UTF-8');
$pPhone      = htmlspecialchars($profile['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$pBio        = htmlspecialchars($profile['bio'] ?? '', ENT_QUOTES, 'UTF-8');
$dispCompany = htmlspecialchars($recruiterRec['company_name'] ?? $companyName, ENT_QUOTES, 'UTF-8');
$dispAvatar  = !empty($userRow['avatar_url']) ? '../' . htmlspecialchars($userRow['avatar_url'], ENT_QUOTES, 'UTF-8') : '';
$isSetup     = isset($_GET['setup']) && $_GET['setup'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>AntCareers — My Profile</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --green:#4CAF70; --blue:#4A90D9;
      --font-display:'Playfair Display',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{overflow-x:hidden}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased}

    /* ── LIGHT THEME ── */
    body.light{--soil-dark:#F9F5F4;--soil-med:#F5F0EE;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF5E6}

    /* ── GLOW ORBS ── */
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1)}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1)}}
    body.light .glow-orb{display:none}

    /* ── PAGE SHELL ── */
    .page-shell{max-width:1380px;margin:0 auto;padding:0 24px 40px;position:relative;z-index:2}

    /* ── BREADCRUMB ── */
    .breadcrumb{padding:18px 0 14px;font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px}
    .breadcrumb a{color:var(--text-muted);text-decoration:none;transition:0.15s}
    .breadcrumb a:hover{color:var(--red-bright)}
    .breadcrumb .sep{color:var(--soil-line)}
    .breadcrumb .current{color:var(--text-mid);font-weight:600}

    /* ── TWO-COLUMN LAYOUT ── */
    .profile-layout{display:grid;grid-template-columns:300px 1fr;gap:24px;max-width:1000px;margin:0 auto}

    /* ── SIDEBAR CARD ── */
    .sidebar-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:28px 24px;text-align:center;position:sticky;top:84px;align-self:start}
    .avatar-wrap{position:relative;width:100px;height:100px;margin:0 auto 16px}
    .avatar-circle{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;overflow:hidden;border:3px solid var(--soil-line);transition:0.2s}
    .avatar-circle img{width:100%;height:100%;object-fit:cover}
    .avatar-upload-btn{position:absolute;bottom:0;right:0;width:30px;height:30px;border-radius:50%;background:var(--red-vivid);border:2px solid var(--soil-card);color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;z-index:2}
    .avatar-upload-btn:hover{background:var(--red-bright);transform:scale(1.1)}
    .sidebar-name{font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text-light);margin-bottom:3px}
    .sidebar-role{font-size:12px;font-weight:600;color:var(--red-pale);margin-bottom:4px}
    .sidebar-company{font-size:12px;color:var(--amber);font-weight:600;margin-bottom:10px}
    .sidebar-email{font-size:12px;color:var(--text-muted);word-break:break-all}

    /* ── FORM CARD ── */
    .form-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:28px}
    .form-title{font-family:var(--font-display);font-size:20px;font-weight:700;color:var(--text-light);margin-bottom:4px}
    .form-sub{font-size:12px;color:var(--text-muted);margin-bottom:24px}
    .form-group{margin-bottom:18px}
    .form-label{display:block;font-size:12px;font-weight:600;color:var(--text-mid);margin-bottom:6px;letter-spacing:0.02em}
    .form-input{width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--soil-line);background:var(--soil-hover);color:var(--text-light);font-family:var(--font-body);font-size:13px;transition:0.2s;outline:none}
    .form-input:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,0.15)}
    .form-input::placeholder{color:var(--text-muted)}
    textarea.form-input{resize:vertical;min-height:90px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .save-btn{padding:10px 28px;border-radius:8px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:14px;font-weight:700;cursor:pointer;transition:0.2s;display:inline-flex;align-items:center;gap:8px;margin-top:6px}
    .save-btn:hover{background:var(--red-bright);transform:translateY(-1px);box-shadow:0 4px 14px rgba(209,61,44,0.4)}
    .save-btn:disabled{opacity:0.6;cursor:not-allowed;transform:none;box-shadow:none}

    /* ── STATS SECTION ── */
    .stats-section{max-width:1000px;margin:28px auto 0}
    .stats-title{font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text-light);margin-bottom:14px;display:flex;align-items:center;gap:8px}
    .stats-title i{color:var(--red-bright);font-size:14px}
    .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .stat-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:20px;text-align:center;transition:0.2s}
    .stat-card:hover{border-color:rgba(209,61,44,0.4);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25)}
    .stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;margin:0 auto 10px}
    .stat-icon.r{background:rgba(209,61,44,.12);color:var(--red-pale)}
    .stat-icon.a{background:rgba(212,148,58,.12);color:var(--amber)}
    .stat-icon.g{background:rgba(76,175,112,.1);color:#6ccf8a}
    .stat-num{font-family:var(--font-display);font-size:28px;font-weight:700;color:var(--text-light);margin-bottom:2px}
    .stat-label{font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em}

    /* ── TOAST ── */
    .toast{position:fixed;bottom:30px;right:30px;padding:14px 22px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;z-index:9999;opacity:0;transform:translateY(20px);transition:all 0.3s ease;pointer-events:none;display:flex;align-items:center;gap:8px;font-family:var(--font-body)}
    .toast.show{opacity:1;transform:translateY(0);pointer-events:auto}
    .toast.success{background:#2d7a46;box-shadow:0 8px 24px rgba(45,122,70,0.35)}
    .toast.error{background:#a33;box-shadow:0 8px 24px rgba(170,51,51,0.35)}

    /* ── FOOTER ── */
    .footer{text-align:center;padding:28px 20px 22px;color:var(--text-muted);font-size:12px;border-top:1px solid var(--soil-line);margin-top:40px}
    .footer-logo{font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--text-mid);margin-bottom:4px}

    /* ── LIGHT THEME PAGE OVERRIDES ── */
    body.light .sidebar-card{background:#FFFFFF;border-color:#E0CECA}
    body.light .avatar-circle{border-color:#E0CECA}
    body.light .avatar-upload-btn{border-color:#FFFFFF}
    body.light .sidebar-name{color:#1A0A09}
    body.light .form-card{background:#FFFFFF;border-color:#E0CECA}
    body.light .form-title{color:#1A0A09}
    body.light .form-label{color:#4A2828}
    body.light .form-input{background:#F9F5F4;border-color:#E0CECA;color:#1A0A09}
    body.light .form-input:focus{border-color:var(--red-vivid)}
    body.light .stat-card{background:#FFFFFF;border-color:#E0CECA}
    body.light .stat-card:hover{box-shadow:0 8px 24px rgba(0,0,0,0.08)}
    body.light .stat-num{color:#1A0A09}
    body.light .stats-title{color:#1A0A09}
    body.light .breadcrumb .current{color:#1A0A09}
    body.light .footer{border-color:#E0CECA}
    body.light .footer-logo{color:#1A0A09}

    /* ── RESPONSIVE ── */
    @media(max-width:900px){
      .profile-layout{grid-template-columns:1fr}
      .sidebar-card{position:static;max-width:340px;margin:0 auto}
      .stats-grid{grid-template-columns:repeat(3,1fr)}
    }
    @media(max-width:768px){
      .nav-links,.notif-btn-nav,.btn-nav-red,.profile-wrap{display:none}
      .hamburger{display:flex}
      .form-row{grid-template-columns:1fr}
    }
    @media(max-width:480px){
      .stats-grid{grid-template-columns:1fr}
      .page-shell{padding:0 14px 30px}
    }
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
  </style>
</head>
<body>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<?php if ($isSetup): ?>
<div style="background:linear-gradient(135deg,rgba(76,175,112,0.15),rgba(76,175,112,0.05));border-bottom:1px solid rgba(76,175,112,0.3);padding:14px 24px;text-align:center;position:relative;z-index:10;">
  <span style="font-size:14px;font-weight:700;color:#6ccf8a;"><i class="fas fa-check-circle"></i> Password set! Please complete your profile below to get started.</span>
</div>
<?php endif; ?>

<div class="page-shell anim">
  <div class="breadcrumb">
    <a href="recruiter_dashboard.php">Dashboard</a>
    <span class="sep"><i class="fas fa-chevron-right" style="font-size:9px"></i></span>
    <span class="current">My Profile</span>
  </div>

  <div class="profile-layout">
    <!-- SIDEBAR -->
    <div class="sidebar-card">
      <div class="avatar-wrap">
        <div class="avatar-circle" id="avatarCircle">
          <?php if ($dispAvatar): ?>
            <img src="<?= $dispAvatar ?>" alt="Avatar" id="avatarImg">
          <?php else: ?>
            <span id="avatarInitials"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
        <label class="avatar-upload-btn" for="avatarInput" title="Change photo">
          <i class="fas fa-camera"></i>
        </label>
        <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
      </div>
      <div class="sidebar-name"><?= $email ?></div>
      <div class="sidebar-role">Recruiter</div>
      <div class="sidebar-company"><?= $dispCompany ?></div>
      <div class="sidebar-email"><?= $emailAddr ?></div>
    </div>

    <!-- FORM -->
    <div class="form-card">
      <div class="form-title">Edit Profile</div>
      <div class="form-sub">Update your personal information. Fields marked with * are required.</div>
      <form id="profileForm" autocomplete="off">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input class="form-input" type="text" name="full_name" id="inputName" value="<?= $email ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Position / Title</label>
            <input class="form-input" type="text" name="position" value="<?= $pPosition ?>" placeholder="e.g. Senior Recruiter">
          </div>
          <div class="form-group">
            <label class="form-label">Department</label>
            <input class="form-input" type="text" name="department" value="<?= $pDepartment ?>" placeholder="e.g. Human Resources">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input class="form-input" type="tel" name="phone" value="<?= $pPhone ?>" placeholder="+1 (555) 123-4567">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Bio</label>
          <textarea class="form-input" name="bio" placeholder="A short professional bio..."><?= $pBio ?></textarea>
        </div>
        <button type="submit" class="save-btn" id="saveBtn">
          <i class="fas fa-check"></i> Save Changes
        </button>
      </form>
    </div>
  </div>

  <!-- STATS -->
  <div class="stats-section">
    <div class="stats-title"><i class="fas fa-chart-bar"></i> Recruitment Stats</div>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon r"><i class="fas fa-briefcase"></i></div>
        <div class="stat-num"><?= $statsJobs ?></div>
        <div class="stat-label">Jobs Posted</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon a"><i class="fas fa-users"></i></div>
        <div class="stat-num"><?= $statsApplicants ?></div>
        <div class="stat-label">Applicants Reviewed</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon g"><i class="fas fa-user-check"></i></div>
        <div class="stat-num"><?= $statsHires ?></div>
        <div class="stat-label">Hires Made</div>
      </div>
    </div>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Profile — Recruiter Portal</div>
</footer>

<div class="toast" id="toast"></div>

<script>
(function(){
  'use strict';

  /* ── Toast ── */
  function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.className = 'toast ' + type + ' show';
    t.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + msg;
    clearTimeout(t._tid);
    t._tid = setTimeout(function(){ t.classList.remove('show'); }, 3500);
  }

  /* ── Profile form submit ── */
  document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var nameField = document.getElementById('inputName');
    if (!nameField.value.trim()) {
      showToast('Full name is required.', 'error');
      nameField.focus();
      return;
    }
    var btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    var fd = new FormData(this);
    fd.append('action', 'update_profile');

    fetch('recruiter_profile.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.success) {
          showToast('Profile updated successfully!', 'success');
          document.querySelector('.sidebar-name').textContent = nameField.value.trim();
        } else {
          showToast(d.error || 'Update failed.', 'error');
        }
      })
      .catch(function(){ showToast('Network error. Please try again.', 'error'); })
      .finally(function(){
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Save Changes';
      });
  });

  /* ── Avatar upload ── */
  document.getElementById('avatarInput').addEventListener('change', function() {
    var file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      showToast('File too large. Max 2MB.', 'error');
      this.value = '';
      return;
    }
    var fd = new FormData();
    fd.append('action', 'upload_avatar');
    fd.append('avatar', file);

    showToast('Uploading photo...', 'success');

    fetch('recruiter_profile.php', { method:'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (d.success) {
          var circle = document.getElementById('avatarCircle');
          circle.innerHTML = '<img src="' + d.avatar_url + '" alt="Avatar" id="avatarImg">';
          showToast('Photo updated!', 'success');
        } else {
          showToast(d.error || 'Upload failed.', 'error');
        }
      })
      .catch(function(){ showToast('Network error.', 'error'); });
    this.value = '';
  });

})();
</script>
</body>
</html>
