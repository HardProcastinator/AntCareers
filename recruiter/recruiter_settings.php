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

/* ── AJAX: change_password ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    header('Content-Type: application/json');
    try {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($newPass !== $confirm) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match.']);
            exit;
        }
        if (strlen($newPass) < 8) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters.']);
            exit;
        }

        $s = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $s->execute([$uid]);
        $hash = $s->fetchColumn();

        if (!password_verify($current, $hash)) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
            exit;
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $s = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $s->execute([$newHash, $uid]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[AntCareers] recruiter change_password: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}

/* ── AJAX: update_notifications ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_notifications') {
    header('Content-Type: application/json');
    try {
        $emailApplicant = (int)(!empty($_POST['email_new_applicant']));
        $emailInterview = (int)(!empty($_POST['email_interview_reminder']));
        $emailApproval  = (int)(!empty($_POST['email_job_approval']));

        $s = $db->prepare("
            INSERT INTO recruiter_profiles (user_id, email_new_applicant, email_interview_reminder, email_job_approval)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                email_new_applicant    = VALUES(email_new_applicant),
                email_interview_reminder = VALUES(email_interview_reminder),
                email_job_approval     = VALUES(email_job_approval)
        ");
        $s->execute([$uid, $emailApplicant, $emailInterview, $emailApproval]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        error_log('[AntCareers] recruiter update_notifications: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}

/* ── Data fetch ── */
$s = $db->prepare("SELECT full_name, email, created_at, last_login_at FROM users WHERE id = ?");
$s->execute([$uid]);
$acct = $s->fetch(PDO::FETCH_ASSOC);

$s = $db->prepare("SELECT r.*, cp.company_name FROM recruiters r JOIN company_profiles cp ON cp.id = r.company_id WHERE r.user_id = ?");
$s->execute([$uid]);
$rec = $s->fetch(PDO::FETCH_ASSOC);

$memberSince = $acct ? date('F j, Y', strtotime($acct['created_at'])) : '—';
$lastLogin   = ($acct && $acct['last_login_at']) ? date('M j, Y g:i A', strtotime($acct['last_login_at'])) : 'Never';
$emailAddr   = $acct['email'] ?? '—';
$company     = $rec['company_name'] ?? $companyName;

$notifApplicant = (int)($rec['email_new_applicant'] ?? 1);
$notifInterview = (int)($rec['email_interview_reminder'] ?? 1);
$notifApproval  = (int)($rec['email_job_approval'] ?? 1);

$theme = in_array($_GET['theme'] ?? '', ['light','dark'], true) ? $_GET['theme'] : 'dark';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Settings — AntCareers Recruiter</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset & tokens ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;
  --soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;
  --text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;
  --amber:#D4943A;--amber-dim:#251C0E;--green:#4CAF70;--blue:#4A90D9;
  --font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;
  --nav-h:64px;
}
body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);min-height:100vh;overflow-x:hidden}
body.light{--soil-dark:#F9F5F4;--soil-med:#F1ECEB;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555}
a{color:var(--red-bright);text-decoration:none}

/* ── Glow orbs ── */
.glow-orbs{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}
.glow-orbs span{position:absolute;border-radius:50%;filter:blur(100px);opacity:.12}
.glow-orbs .orb1{width:600px;height:600px;background:var(--red-mid);top:-150px;left:-100px}
.glow-orbs .orb2{width:500px;height:500px;background:var(--red-deep);bottom:-120px;right:-80px}
body.light .glow-orbs span{opacity:.06}

/* ── Page shell ── */
.page-shell{position:relative;z-index:1;max-width:760px;margin:0 auto;padding:calc(var(--nav-h) + 32px) 20px 60px}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--text-muted);margin-bottom:24px}
.breadcrumb a{color:var(--text-muted);transition:.2s}
.breadcrumb a:hover{color:var(--text-light)}
.breadcrumb .sep{opacity:.4}
.page-title{font-family:var(--font-display);font-size:1.75rem;font-weight:700;margin-bottom:28px;color:var(--text-light)}

/* ── Cards ── */
.card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:28px;margin-bottom:20px}
.card-header{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.card-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.card-icon.blue{background:rgba(74,144,217,.12);color:var(--blue)}
.card-icon.amber{background:rgba(212,148,58,.12);color:var(--amber)}
.card-icon.green{background:rgba(76,175,112,.12);color:var(--green)}
.card-icon.red{background:rgba(209,61,44,.12);color:var(--red-vivid)}
.card-title{font-size:1.05rem;font-weight:700;color:var(--text-light)}
.card-subtitle{font-size:.78rem;color:var(--text-muted);margin-top:2px}

/* ── Info rows ── */
.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--soil-line)}
.info-row:last-child{border-bottom:none}
.info-label{font-size:.85rem;color:var(--text-muted);font-weight:500}
.info-value{font-size:.85rem;color:var(--text-light);font-weight:600;text-align:right}

/* ── Form inputs ── */
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:.82rem;font-weight:600;color:var(--text-mid);margin-bottom:6px}
.form-input{width:100%;padding:10px 14px;border-radius:8px;border:1px solid var(--soil-line);background:var(--soil-med);color:var(--text-light);font-family:var(--font-body);font-size:.88rem;transition:.2s;outline:none}
.form-input:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.15)}
body.light .form-input{background:#F5F0EE;border-color:#E0CECA}

/* ── Buttons ── */
.btn{padding:10px 22px;border-radius:8px;font-weight:600;font-size:.88rem;border:none;cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:8px}
.btn-primary{background:linear-gradient(135deg,var(--red-vivid),var(--red-mid));color:#fff}
.btn-primary:hover{filter:brightness(1.1)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}

/* ── Toggle switch ── */
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--soil-line)}
.toggle-row:last-child{border-bottom:none}
.toggle-info .toggle-label{font-size:.88rem;font-weight:600;color:var(--text-light)}
.toggle-info .toggle-desc{font-size:.76rem;color:var(--text-muted);margin-top:2px}
.switch{position:relative;width:45px;height:24px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;inset:0;background:var(--soil-line);border-radius:24px;cursor:pointer;transition:.3s}
.slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
input:checked+.slider{background:var(--green)}
input:checked+.slider::before{transform:translateX(21px)}

/* ── Danger zone ── */
.danger-zone{border-color:rgba(209,61,44,.25)}
.danger-text{font-size:.84rem;color:var(--text-muted);line-height:1.6}
.danger-text strong{color:var(--red-bright)}

/* ── Password strength ── */
.pw-strength{height:4px;border-radius:4px;background:var(--soil-line);margin-top:6px;overflow:hidden}
.pw-strength-bar{height:100%;width:0;border-radius:4px;transition:width .3s,background .3s}
.pw-hint{font-size:.72rem;color:var(--text-muted);margin-top:4px}

/* ── Footer ── */
.footer{text-align:center;padding:40px 20px 28px;color:var(--text-muted);font-size:.78rem;position:relative;z-index:1}
.footer-logo{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--text-mid);margin-bottom:4px}

body.light .card{background:#fff;border-color:#E0CECA}

/* ── Responsive ── */
@media(max-width:768px){
  .nav-links{display:none}
  .btn-nav-red{display:none}
  .hamburger{display:flex}
  .page-shell{padding-top:calc(var(--nav-h) + 20px)}
  .page-title{font-size:1.4rem}
  .card{padding:20px}
  .info-row{flex-direction:column;align-items:flex-start;gap:4px}
  .info-value{text-align:left}
}
@media(max-width:480px){
  .page-shell{padding-left:12px;padding-right:12px}
  .card{padding:16px}
  .toggle-row{flex-direction:column;align-items:flex-start;gap:10px}
}
</style>
</head>
<body class="<?= $theme === 'light' ? 'light' : '' ?>">

<div class="glow-orbs"><span class="orb1"></span><span class="orb2"></span></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<div class="page-shell">
  <div class="breadcrumb">
    <a href="recruiter_dashboard.php">Dashboard</a>
    <span class="sep">/</span>
    <span>Settings</span>
  </div>
  <h1 class="page-title">Settings</h1>

  <!-- Account Info -->
  <div class="card">
    <div class="card-header">
      <div class="card-icon blue"><i class="fas fa-user-circle"></i></div>
      <div>
        <div class="card-title">Account Information</div>
        <div class="card-subtitle">Your account details — read only</div>
      </div>
    </div>
    <div class="info-row">
      <span class="info-label">Email</span>
      <span class="info-value"><?= htmlspecialchars($emailAddr, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Account Type</span>
      <span class="info-value">Recruiter</span>
    </div>
    <div class="info-row">
      <span class="info-label">Company</span>
      <span class="info-value"><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Member Since</span>
      <span class="info-value"><?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Last Login</span>
      <span class="info-value"><?= htmlspecialchars($lastLogin, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header">
      <div class="card-icon amber"><i class="fas fa-lock"></i></div>
      <div>
        <div class="card-title">Change Password</div>
        <div class="card-subtitle">Update your account password</div>
      </div>
    </div>
    <form id="pwForm" autocomplete="off">
      <div class="form-group">
        <label for="currentPw">Current Password</label>
        <input type="password" id="currentPw" class="form-input" placeholder="Enter current password" required>
      </div>
      <div class="form-group">
        <label for="newPw">New Password</label>
        <input type="password" id="newPw" class="form-input" placeholder="Min 8 characters" required minlength="8">
        <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
        <div class="pw-hint" id="pwHint"></div>
      </div>
      <div class="form-group">
        <label for="confirmPw">Confirm New Password</label>
        <input type="password" id="confirmPw" class="form-input" placeholder="Re-enter new password" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary" id="pwBtn">
        <i class="fas fa-shield-alt"></i> Update Password
      </button>
    </form>
  </div>

  <!-- Notification Preferences -->
  <div class="card">
    <div class="card-header">
      <div class="card-icon green"><i class="fas fa-bell"></i></div>
      <div>
        <div class="card-title">Notification Preferences</div>
        <div class="card-subtitle">Choose which email notifications you receive</div>
      </div>
    </div>
    <div class="toggle-row">
      <div class="toggle-info">
        <div class="toggle-label">New Applicant Alerts</div>
        <div class="toggle-desc">Receive an email when someone applies to your jobs</div>
      </div>
      <label class="switch">
        <input type="checkbox" id="notifApplicant" <?= $notifApplicant ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>
    <div class="toggle-row">
      <div class="toggle-info">
        <div class="toggle-label">Interview Reminders</div>
        <div class="toggle-desc">Get reminders before scheduled interviews</div>
      </div>
      <label class="switch">
        <input type="checkbox" id="notifInterview" <?= $notifInterview ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>
    <div class="toggle-row">
      <div class="toggle-info">
        <div class="toggle-label">Job Approval Updates</div>
        <div class="toggle-desc">Notified when your posted jobs are approved or need changes</div>
      </div>
      <label class="switch">
        <input type="checkbox" id="notifApproval" <?= $notifApproval ? 'checked' : '' ?>>
        <span class="slider"></span>
      </label>
    </div>
  </div>

  <!-- Danger Zone -->
  <div class="card danger-zone">
    <div class="card-header">
      <div class="card-icon red"><i class="fas fa-exclamation-triangle"></i></div>
      <div>
        <div class="card-title">Danger Zone</div>
        <div class="card-subtitle">Irreversible account actions</div>
      </div>
    </div>
    <p class="danger-text">
      To <strong>delete your account</strong>, please contact your company administrator or email
      <strong>support@antcareers.com</strong>. Account deletion is permanent and cannot be undone.
      All your job postings and applicant data will be removed.
    </p>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Settings — Recruiter Portal</div>
</footer>

<script>
(function(){

  /* ── Password strength ── */
  const newPw = document.getElementById('newPw');
  const pwBar = document.getElementById('pwBar');
  const pwHint = document.getElementById('pwHint');
  newPw.addEventListener('input',()=>{
    const v = newPw.value;
    let s=0;
    if(v.length>=8) s++;
    if(v.length>=12) s++;
    if(/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if(/\d/.test(v)) s++;
    if(/[^A-Za-z0-9]/.test(v)) s++;
    const pct = Math.min(s/5*100,100);
    const clr = pct<40?'var(--red-vivid)':pct<70?'var(--amber)':'var(--green)';
    pwBar.style.width = pct+'%';
    pwBar.style.background = clr;
    const labels = ['Very weak','Weak','Fair','Strong','Very strong'];
    pwHint.textContent = v.length ? labels[Math.min(s,4)] : '';
  });

  /* ── Change password AJAX ── */
  document.getElementById('pwForm').addEventListener('submit',async e=>{
    e.preventDefault();
    const btn = document.getElementById('pwBtn');
    btn.disabled = true;
    const body = new URLSearchParams({
      action:'change_password',
      current_password: document.getElementById('currentPw').value,
      new_password: newPw.value,
      confirm_password: document.getElementById('confirmPw').value
    });
    try{
      const r = await fetch(location.pathname,{method:'POST',body,credentials:'same-origin'});
      const j = await r.json();
      if(j.success){
        toast('Password updated successfully');
        e.target.reset();
        pwBar.style.width='0';
        pwHint.textContent='';
      } else {
        toast(j.error||'Failed to update password','error');
      }
    }catch(err){
      toast('Network error. Please try again.','error');
    }
    btn.disabled = false;
  });

  /* ── Notification toggles AJAX ── */
  ['notifApplicant','notifInterview','notifApproval'].forEach(id=>{
    document.getElementById(id).addEventListener('change',async()=>{
      const body = new URLSearchParams({
        action:'update_notifications',
        email_new_applicant:      document.getElementById('notifApplicant').checked?'1':'',
        email_interview_reminder: document.getElementById('notifInterview').checked?'1':'',
        email_job_approval:       document.getElementById('notifApproval').checked?'1':''
      });
      try{
        const r = await fetch(location.pathname,{method:'POST',body,credentials:'same-origin'});
        const j = await r.json();
        if(j.success) toast('Preferences saved');
        else toast(j.error||'Failed to save preferences','error');
      }catch(err){
        toast('Network error.','error');
      }
    });
  });
})();
</script>
</body>
</html>
