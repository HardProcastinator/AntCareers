<?php
/**
 * AntCareers — Force Password Change (First Login)
 * auth/force_change_password.php
 *
 * Shown when must_change_password = 1 (recruiter first-login).
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

if (empty($_SESSION['user_id'])) {
    header('Location: antcareers_login.php');
    exit;
}

if (empty($_SESSION['must_change_password'])) {
    // Not required — redirect to normal dashboard
    $role = strtolower((string)($_SESSION['account_type'] ?? ''));
    $redirect = match ($role) {
        'seeker'    => url('seeker/antcareers_seekerDashboard.php'),
        'employer'  => url('employer/employer_dashboard.php'),
        'recruiter' => url('recruiter/recruiter_dashboard.php'),
        'admin'     => url('admin/admin_dashboard.php'),
        default     => url('index.php'),
    };
    header('Location: ' . $redirect);
    exit;
}

$_csrfToken = csrfToken();
$fullName = trim((string)($_SESSION['user_name'] ?? 'User'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AntCareers — Change Your Password</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    body {
      font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light);
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      -webkit-font-smoothing:antialiased;
    }
    .card {
      background:var(--soil-card); border:1px solid var(--soil-line);
      border-radius:16px; padding:40px; max-width:420px; width:92%;
      box-shadow:0 20px 60px rgba(0,0,0,0.5);
    }
    .card-icon {
      width:56px; height:56px; border-radius:14px;
      background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));
      display:flex; align-items:center; justify-content:center;
      font-size:24px; color:#fff; margin-bottom:20px;
    }
    .card h1 {
      font-family:var(--font-display); font-size:22px; font-weight:700;
      margin-bottom:6px;
    }
    .card p { font-size:14px; color:var(--text-muted); margin-bottom:24px; line-height:1.6; }
    .card p strong { color:var(--amber); }
    .field { margin-bottom:16px; }
    .field label {
      display:block; font-size:12px; font-weight:600; color:var(--text-muted);
      margin-bottom:6px; letter-spacing:0.03em; text-transform:uppercase;
    }
    .field input {
      width:100%; padding:12px 14px; border-radius:8px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      font-family:var(--font-body); font-size:14px; color:var(--text-light);
      outline:none; transition:0.2s;
    }
    .field input:focus { border-color:var(--red-mid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .field input.err { border-color:var(--red-bright); }
    .err-msg { display:none; font-size:12px; color:var(--red-bright); margin-top:4px; }
    .err-msg.show { display:block; }
    .btn-submit {
      width:100%; padding:13px; border-radius:8px;
      background:var(--red-vivid); border:none; color:#fff;
      font-family:var(--font-body); font-size:14px; font-weight:700;
      cursor:pointer; transition:0.2s; margin-top:8px;
    }
    .btn-submit:hover { background:var(--red-bright); transform:translateY(-1px); }
    .btn-submit:disabled { opacity:0.6; cursor:not-allowed; transform:none; }
    .success-msg {
      display:none; text-align:center; padding:20px;
      color:var(--text-light); font-size:14px;
    }
    .success-msg i { font-size:36px; color:#4CAF70; display:block; margin-bottom:12px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-icon"><i class="fas fa-key"></i></div>
    <h1>Change Your Password</h1>
    <p>Welcome, <strong><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></strong>! You must set a new password before continuing.</p>

    <div id="formSection">
      <div class="field">
        <label>New Password</label>
        <input type="password" id="newPw" placeholder="At least 8 characters">
      </div>
      <div class="field">
        <label>Confirm New Password</label>
        <input type="password" id="confirmPw" placeholder="Repeat your new password">
        <div class="err-msg" id="errMsg"></div>
      </div>
      <button class="btn-submit" id="submitBtn" onclick="changePassword()">
        <i class="fas fa-lock"></i> Set New Password
      </button>
      <div style="text-align:center;margin-top:16px;">
        <a href="logout.php" style="font-size:13px;color:var(--text-muted);text-decoration:none;transition:color 0.18s;" onmouseover="this.style.color='#F5F0EE'" onmouseout="this.style.color=''">
          <i class="fas fa-arrow-left" style="font-size:11px;margin-right:4px;"></i> Back to Login
        </a>
      </div>
    </div>

    <div class="success-msg" id="successMsg">
      <i class="fas fa-check-circle"></i>
      <p>Password changed successfully! Redirecting...</p>
    </div>
  </div>

  <script>
    async function changePassword() {
      const newPw = document.getElementById('newPw').value;
      const confirmPw = document.getElementById('confirmPw').value;
      const errMsg = document.getElementById('errMsg');
      const btn = document.getElementById('submitBtn');

      errMsg.classList.remove('show');
      document.getElementById('newPw').classList.remove('err');
      document.getElementById('confirmPw').classList.remove('err');

      if (newPw.length < 8) {
        errMsg.textContent = 'Password must be at least 8 characters.';
        errMsg.classList.add('show');
        document.getElementById('newPw').classList.add('err');
        return;
      }
      if (newPw !== confirmPw) {
        errMsg.textContent = 'Passwords do not match.';
        errMsg.classList.add('show');
        document.getElementById('confirmPw').classList.add('err');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

      try {
        const fd = new FormData();
        fd.append('action', 'force_password_change');
        fd.append('new_password', newPw);
        fd.append('confirm_password', confirmPw);

        const res = await fetch('../api/recruiters.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          document.getElementById('formSection').style.display = 'none';
          document.getElementById('successMsg').style.display = 'block';
          setTimeout(() => {
            window.location.href = data.redirect || '../recruiter/recruiter_profile.php?setup=1';
          }, 1500);
        } else {
          errMsg.textContent = data.message || 'Failed to change password.';
          errMsg.classList.add('show');
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password';
        }
      } catch (e) {
        errMsg.textContent = 'Network error. Please try again.';
        errMsg.classList.add('show');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Set New Password';
      }
    }

    // Prevent navigation if must_change_password
    window.addEventListener('popstate', () => window.location.reload());
  </script>
</body>
</html>
