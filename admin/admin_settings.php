<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/antcareers_login.php');
    exit;
}
if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$fullName  = trim((string)($_SESSION['user_name'] ?? 'Admin'));
$nameParts = preg_split('/\s+/', $fullName) ?: [];
$firstName = $nameParts[0] ?? 'Admin';
$initials  = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0],0,1).substr($nameParts[1],0,1))
    : strtoupper(substr($firstName,0,2));
$userEmail = (string)($_SESSION['email'] ?? '');
$adminId   = (int)$_SESSION['user_id'];

$db = getDB();
require_once dirname(__DIR__) . '/includes/admin_notif_panel.php';

// ── Helper: count query ──
$countValue = static function (string $sql) use ($db): int {
  try { return (int)$db->query($sql)->fetchColumn(); }
  catch (PDOException $e) { return 0; }
};

// ── Ensure tables & columns exist ──
function settings_table_has_column(PDO $db, string $table, string $column): bool
{
  $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
  $stmt->execute([$table, $column]);
  return (int)$stmt->fetchColumn() > 0;
}

function settings_ensure_column(PDO $db, string $table, string $column, string $definition): void
{
  if (!settings_table_has_column($db, $table, $column)) {
    $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
  }
}

$db->exec("CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT UNSIGNED NOT NULL,
  email_new_message TINYINT(1) NOT NULL DEFAULT 1,
  email_application_status TINYINT(1) NOT NULL DEFAULT 1,
  email_interview_invite TINYINT(1) NOT NULL DEFAULT 1,
  notif_new_applicant TINYINT(1) NOT NULL DEFAULT 1,
  notif_invite_response TINYINT(1) NOT NULL DEFAULT 1,
  notif_flagged_content TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
settings_ensure_column($db, 'user_preferences', 'notif_flagged_content', 'TINYINT(1) NOT NULL DEFAULT 1');
settings_ensure_column($db, 'user_preferences', 'notif_company_pending', 'TINYINT(1) NOT NULL DEFAULT 1');
settings_ensure_column($db, 'user_preferences', 'notif_job_pending', 'TINYINT(1) NOT NULL DEFAULT 1');
settings_ensure_column($db, 'user_preferences', 'notif_new_registration', 'TINYINT(1) NOT NULL DEFAULT 1');

// ── Load preferences ──
$notificationPrefs = [
  'email_new_message'      => 1,
  'notif_flagged_content'  => 1,
  'notif_company_pending'  => 1,
  'notif_job_pending'      => 1,
  'notif_new_registration' => 1,
];

$prefStmt = $db->prepare('SELECT email_new_message, notif_flagged_content, notif_company_pending, notif_job_pending, notif_new_registration FROM user_preferences WHERE user_id = ? LIMIT 1');
$prefStmt->execute([$adminId]);
if ($prefRow = $prefStmt->fetch(PDO::FETCH_ASSOC)) {
  foreach ($notificationPrefs as $key => $default) {
    $notificationPrefs[$key] = (int)($prefRow[$key] ?? $default);
  }
}

$settingsNotice = '';
$settingsError  = '';

// ── Handle POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'change_password') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword     = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$adminId]);
    $passwordHash = (string)($stmt->fetchColumn() ?: '');

    if ($passwordHash === '' || !password_verify($currentPassword, $passwordHash)) {
      header('Location: admin_settings.php?error=bad_password');
      exit;
    }
    if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
      header('Location: admin_settings.php?error=password_mismatch');
      exit;
    }

    $upd = $db->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id');
    $upd->execute([
      ':hash' => password_hash($newPassword, PASSWORD_BCRYPT),
      ':id'   => $adminId,
    ]);
    header('Location: admin_settings.php?pw=1');
    exit;
  }

  if ($action === 'save_preferences') {
    $emailNewMessage      = isset($_POST['email_new_message']) ? 1 : 0;
    $notifFlaggedContent  = isset($_POST['notif_flagged_content']) ? 1 : 0;
    $notifCompanyPending  = isset($_POST['notif_company_pending']) ? 1 : 0;
    $notifJobPending      = isset($_POST['notif_job_pending']) ? 1 : 0;
    $notifNewRegistration = isset($_POST['notif_new_registration']) ? 1 : 0;

    $prefUpsert = $db->prepare("INSERT INTO user_preferences (user_id, email_new_message, notif_flagged_content, notif_company_pending, notif_job_pending, notif_new_registration)
      VALUES (:uid, :msg, :flg, :cmp, :job, :reg)
      ON DUPLICATE KEY UPDATE email_new_message = VALUES(email_new_message), notif_flagged_content = VALUES(notif_flagged_content), notif_company_pending = VALUES(notif_company_pending), notif_job_pending = VALUES(notif_job_pending), notif_new_registration = VALUES(notif_new_registration), updated_at = CURRENT_TIMESTAMP");
    $prefUpsert->execute([
      ':uid' => $adminId,
      ':msg' => $emailNewMessage,
      ':flg' => $notifFlaggedContent,
      ':cmp' => $notifCompanyPending,
      ':job' => $notifJobPending,
      ':reg' => $notifNewRegistration,
    ]);
    header('Location: admin_settings.php?prefs=1');
    exit;
  }

  if ($action === 'deactivate_account') {
    $statusColumnExists = settings_table_has_column($db, 'users', 'status');
    if ($statusColumnExists) {
      $db->prepare('UPDATE users SET is_active = 0, status = "inactive" WHERE id = :id')->execute([':id' => $adminId]);
    } else {
      $db->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute([':id' => $adminId]);
    }
    session_unset();
    session_destroy();
    header('Location: ../auth/antcareers_login.php');
    exit;
  }
}

if (isset($_GET['pw'])) {
  $settingsNotice = 'Password updated successfully.';
} elseif (isset($_GET['prefs'])) {
  $settingsNotice = 'Preferences saved successfully.';
} elseif (isset($_GET['error'])) {
  if ($_GET['error'] === 'bad_password') {
    $settingsError = 'Current password is incorrect.';
  } elseif ($_GET['error'] === 'password_mismatch') {
    $settingsError = 'New password must be at least 8 characters and match the confirmation.';
  } else {
    $settingsError = 'Could not save your changes. Please try again.';
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Settings - AntCareers Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --green:#4CAF70;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── NAVBAR ── */
    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'\1F41C'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; }
    .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
    .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted); flex-shrink:0; text-decoration:none; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); }
    .admin-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:rgba(209,61,44,0.12); border:1px solid rgba(209,61,44,0.25); border-radius:100px; font-size:11px; font-weight:700; color:var(--red-pale); letter-spacing:0.04em; white-space:nowrap; }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-btn:hover { background:var(--soil-card); }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--red-deep),var(--red-vivid)); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
    .profile-name { font-size:13px; font-weight:600; color:#F5F0EE; }
    .profile-role { font-size:10px; color:var(--red-pale); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:#F5F0EE; }
    .pdh-sub { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; text-decoration:none; }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }
    .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; text-decoration:none; }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* ── LAYOUT ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:28px 24px 60px; }
    .content-layout { display:block; }

    /* ── SETTINGS CONTENT ── */
    .settings-content { display:flex; flex-direction:column; gap:16px; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
    .page-title { font-family:var(--font-display); font-size:24px; font-weight:700; color:var(--text-light); }
    .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }

    .section-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; }
    .section-head { padding:16px 20px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; gap:10px; }
    .section-head-icon { width:32px; height:32px; border-radius:8px; background:rgba(209,61,44,0.12); display:flex; align-items:center; justify-content:center; font-size:13px; color:var(--red-pale); flex-shrink:0; }
    .section-title { font-size:14px; font-weight:700; color:#F5F0EE; }
    .section-sub { font-size:11px; color:var(--text-muted); margin-top:2px; }
    .section-body { padding:20px; }

    .info-row { display:flex; align-items:center; gap:16px; padding:14px 0; border-bottom:1px solid var(--soil-line); }
    .info-row:last-child { border-bottom:none; padding-bottom:0; }
    .info-row:first-child { padding-top:0; }
    .info-icon-wrap { width:38px; height:38px; border-radius:9px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:13px; color:var(--red-pale); flex-shrink:0; }
    .info-label { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:3px; }
    .info-value { font-size:14px; font-weight:600; color:#F5F0EE; }

    .account-avatar { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; color:#fff; box-shadow:0 0 0 2px var(--red-vivid),0 4px 16px rgba(209,61,44,0.35); flex-shrink:0; }
    .account-info-details { flex:1; min-width:0; }
    .account-name { font-size:16px; font-weight:700; color:#F5F0EE; }
    .account-email { font-size:13px; color:var(--text-muted); margin-top:3px; }
    .account-badge { display:inline-flex; align-items:center; gap:5px; margin-top:6px; padding:3px 9px; border-radius:99px; background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.25); font-size:10px; font-weight:700; color:var(--green); letter-spacing:0.05em; text-transform:uppercase; }

    .form-group { margin-bottom:14px; }
    .form-group:last-child { margin-bottom:0; }
    .form-label { display:block; font-size:11px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:6px; }
    .form-input-wrap { position:relative; }
    .form-input { width:100%; padding:10px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; font-family:var(--font-body); font-size:13px; color:#F5F0EE; transition:0.18s; outline:none; }
    .form-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .form-input::placeholder { color:var(--text-muted); }
    .form-input-wrap .toggle-pw { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:13px; padding:2px 4px; transition:0.15s; }
    .form-input-wrap .toggle-pw:hover { color:var(--red-pale); }
    .form-hint { font-size:11px; color:var(--text-muted); margin-top:5px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    .btn-primary { padding:10px 22px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; box-shadow:0 2px 10px rgba(209,61,44,0.35); }
    .btn-primary:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 6px 18px rgba(209,61,44,0.45); }
    .btn-primary:active { transform:translateY(0); }
    .btn-secondary { padding:10px 18px; border-radius:8px; background:transparent; border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.2s; }
    .btn-secondary:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:#F5F0EE; }
    .btn-danger { padding:10px 22px; border-radius:8px; background:transparent; border:1px solid rgba(224,85,85,0.3); color:#E05555; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
    .btn-danger:hover { background:rgba(224,85,85,0.1); border-color:#E05555; color:#FF7070; }
    .section-footer { padding:14px 20px; border-top:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .section-footer-right { display:flex; gap:8px; align-items:center; }

    .flash-banner { display:flex; align-items:center; gap:10px; margin:0 0 16px; padding:14px 16px; border-radius:12px; border:1px solid transparent; font-size:13px; font-weight:600; }
    .flash-banner.success { background:rgba(76,175,112,0.1); border-color:rgba(76,175,112,0.22); color:#6ccf8a; }
    .flash-banner.error { background:rgba(224,85,85,0.08); border-color:rgba(224,85,85,0.2); color:#ff8a8a; }

    .success-notice { display:none; align-items:center; gap:7px; font-size:12px; font-weight:600; color:var(--green); }
    .success-notice.show { display:flex; }
    .success-notice i { font-size:13px; }

    .pref-group-label { font-size:11px; font-weight:700; color:var(--red-bright); text-transform:uppercase; letter-spacing:0.08em; padding:18px 0 6px; display:flex; align-items:center; gap:7px; }
    .pref-group-label i { font-size:10px; }
    .pref-group-label:first-child { padding-top:0; }
    .pref-row { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid var(--soil-line); }
    .pref-row:last-child { border-bottom:none; padding-bottom:0; }
    .pref-row:first-child { padding-top:0; }
    .pref-info { flex:1; min-width:0; }
    .pref-label { font-size:13px; font-weight:600; color:#F5F0EE; }
    .pref-desc { font-size:11px; color:var(--text-muted); margin-top:3px; }
    .toggle-switch { position:relative; width:44px; height:24px; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; position:absolute; }
    .toggle-track { position:absolute; inset:0; border-radius:99px; background:var(--soil-hover); border:1px solid var(--soil-line); cursor:pointer; transition:0.25s; }
    .toggle-track::after { content:''; position:absolute; width:18px; height:18px; border-radius:50%; background:var(--text-muted); top:2px; left:2px; transition:0.25s; }
    .toggle-switch input:checked + .toggle-track { background:var(--red-vivid); border-color:var(--red-vivid); }
    .toggle-switch input:checked + .toggle-track::after { transform:translateX(20px); background:#fff; }

    .security-row { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid var(--soil-line); gap:16px; }
    .security-row:last-child { border-bottom:none; padding-bottom:0; }
    .security-row:first-child { padding-top:0; }
    .security-info { flex:1; min-width:0; }
    .security-label { font-size:13px; font-weight:600; color:#F5F0EE; display:flex; align-items:center; gap:7px; }
    .security-label i { color:var(--red-pale); font-size:12px; }
    .security-desc { font-size:11px; color:var(--text-muted); margin-top:3px; }
    .security-status { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:99px; font-size:10px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase; }
    .security-status.active { background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.25); color:var(--green); }

    .pw-strength-bar { height:3px; border-radius:99px; background:var(--soil-hover); margin-top:6px; overflow:hidden; }
    .pw-strength-fill { height:100%; border-radius:99px; width:0%; transition:width 0.3s, background 0.3s; }

    .anim { opacity:0; transform:translateY(12px); animation:fadeUp 0.4s ease forwards; }
    .anim-d1 { animation-delay:0.05s; }
    .anim-d2 { animation-delay:0.10s; }
    .anim-d3 { animation-delay:0.15s; }
    .anim-d4 { animation-delay:0.20s; }
    .anim-d5 { animation-delay:0.25s; }
    @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }

    /* ── LIGHT THEME ── */
    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .navbar { background:rgba(255,253,252,0.98); border-bottom-color:#D4B0AB; box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08); }
    body.light .logo-text { color:#1A0A09; }
    body.light .logo-text span { color:var(--red-vivid); }
    body.light .nav-link { color:#5A4040; }
    body.light .nav-link:hover, body.light .nav-link.active { color:#1A0A09; background:#FEF0EE; }
    body.light .theme-btn { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .profile-btn { background:#F5EEEC; border-color:#E0CECA; }
    body.light .profile-name { color:#1A0A09; }
    body.light .hamburger { background:#F5EEEC; border-color:#E0CECA; }
    body.light .glow-orb { display:none; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#8A6060; }
    body.light .section-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .section-head { border-color:#E0CECA; }
    body.light .section-title { color:#1A0A09; }
    body.light .section-head-icon { background:#FEF0EE; }
    body.light .section-footer { border-color:#E0CECA; }
    body.light .info-row { border-color:#E0CECA; }
    body.light .info-icon-wrap { background:#F5EEEC; border-color:#E0CECA; }
    body.light .info-value { color:#1A0A09; }
    body.light .account-name { color:#1A0A09; }
    body.light .account-email { color:#8A6060; }
    body.light .pref-row { border-color:#E0CECA; }
    body.light .pref-label { color:#1A0A09; }
    body.light .pref-desc { color:#8A6060; }
    body.light .toggle-track { background:#F0E4E2; border-color:#D0BCBA; }
    body.light .security-row { border-color:#E0CECA; }
    body.light .security-label { color:#1A0A09; }
    body.light .security-desc { color:#8A6060; }
    body.light .form-input { background:#F5EEEC; border-color:#D0BCBA; color:#1A0A09; }
    body.light .form-input:focus { border-color:var(--red-vivid); }
    body.light .form-input::placeholder { color:#A08080; }
    body.light .form-label { color:#8A6060; }
    body.light .form-hint { color:#A08080; }
    body.light .btn-secondary { border-color:#D0BCBA; color:#4A2828; }
    body.light .btn-secondary:hover { background:#FEF0EE; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }

    @media(max-width:760px) {
      .nav-links{display:none} .hamburger{display:flex}
      .page-shell{padding:16px 16px 60px} .nav-inner{padding:0 16px}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .form-row { grid-template-columns:1fr; }
    }
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

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="admin_dashboard.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
      <a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
      <a class="nav-link" href="admin_companies.php"><i class="fas fa-building"></i> Companies<?php if($adminPendingCompanies>0): ?> <span style="background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminPendingCompanies; ?></span><?php endif; ?></a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs<?php if($adminPendingJobs>0): ?> <span style="background:var(--amber);color:#1A0A09;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminPendingJobs; ?></span><?php endif; ?></a>
      <a class="nav-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
      <a class="nav-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-sun"></i></button>
      <button class="notif-btn-nav" id="navNotifBtn" onclick="toggleAdminNotifPanel()" title="Admin Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($adminUnreadCount > 0): ?><span class="badge" id="adminNotifBadge"><?php echo $adminUnreadCount; ?></span><?php endif; ?>
      </button>
      <div class="profile-wrap" id="profileWrap">
        <button class="profile-btn" id="profileToggle">
          <div class="profile-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES); ?></div>
          <div>
            <div class="profile-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></div>
            <div class="profile-role">Administrator</div>
          </div>
          <i class="fas fa-chevron-down profile-chevron"></i>
        </button>
        <div class="profile-dropdown" id="profileDropdown">
          <div class="profile-dropdown-head">
            <div class="pdh-name"><?php echo htmlspecialchars($fullName, ENT_QUOTES); ?></div>
            <div class="pdh-sub"><i class="fas fa-shield-alt" style="margin-right:4px;"></i>Administrator</div>
          </div>
          <a class="pd-item" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
          <a class="pd-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
      <button class="theme-btn hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
    </div>
  </div>
</nav>

<!-- Mobile menu -->
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
  <a class="mobile-link" href="admin_users.php"><i class="fas fa-users"></i> User Accounts</a>
  <a class="mobile-link" href="admin_companies.php"><i class="fas fa-building"></i> Company Approval</a>
  <a class="mobile-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Job Moderation</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="admin_activity.php"><i class="fas fa-history"></i> Activity Logs</a>
  <a class="mobile-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
  <a class="mobile-link" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
  <a class="mobile-link" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
  <div class="mobile-divider"></div>
  <a class="mobile-link" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<div class="page-shell">
  <div class="content-layout">

    <!-- MAIN SETTINGS CONTENT -->
    <main>
      <div class="settings-content">

        <div class="page-header anim anim-d1">
          <div>
            <div class="page-title">Settings</div>
            <div class="page-sub">Manage your account, preferences, and security.</div>
          </div>
        </div>

        <?php if ($settingsNotice): ?>
          <div class="flash-banner success anim anim-d1"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($settingsNotice, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($settingsError): ?>
          <div class="flash-banner error anim anim-d1"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($settingsError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- A. ACCOUNT INFORMATION -->
        <div class="section-card anim anim-d2">
          <div class="section-head">
            <div class="section-head-icon"><i class="fas fa-user"></i></div>
            <div>
              <div class="section-title">Account Information</div>
              <div class="section-sub">Your name and email address on file</div>
            </div>
          </div>
          <div class="section-body">
            <div class="info-row" style="align-items:center; gap:18px;">
              <div class="account-avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
              <div class="account-info-details">
                <div class="account-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="account-email"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></div>
                <span class="account-badge"><i class="fas fa-check-circle"></i> Active Account</span>
              </div>
            </div>
            <div class="info-row">
              <div class="info-icon-wrap"><i class="fas fa-id-card"></i></div>
              <div style="flex:1; min-width:0;">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-icon-wrap"><i class="fas fa-envelope"></i></div>
              <div style="flex:1; min-width:0;">
                <div class="info-label">Email Address</div>
                <div class="info-value"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-icon-wrap"><i class="fas fa-shield-alt"></i></div>
              <div style="flex:1; min-width:0;">
                <div class="info-label">Account Type</div>
                <div class="info-value">System Administrator</div>
              </div>
            </div>
          </div>
        </div>

        <!-- B. CHANGE PASSWORD -->
        <div class="section-card anim anim-d3">
          <div class="section-head">
            <div class="section-head-icon"><i class="fas fa-lock"></i></div>
            <div>
              <div class="section-title">Change Password</div>
              <div class="section-sub">Choose a strong, unique password</div>
            </div>
          </div>
          <form class="section-body" id="passwordForm" method="post" action="admin_settings.php">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label class="form-label" for="currentPassword">Current Password</label>
              <div class="form-input-wrap">
                <input class="form-input" type="password" id="currentPassword" name="current_password" placeholder="Enter your current password" autocomplete="current-password">
                <button type="button" class="toggle-pw" tabindex="-1" onclick="togglePw('currentPassword', this)" aria-label="Show/hide"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="newPassword">New Password</label>
                <div class="form-input-wrap">
                  <input class="form-input" type="password" id="newPassword" name="new_password" placeholder="Minimum 8 characters" autocomplete="new-password" oninput="updatePwStrength(this.value)">
                  <button type="button" class="toggle-pw" tabindex="-1" onclick="togglePw('newPassword', this)" aria-label="Show/hide"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwStrengthFill"></div></div>
                <div class="form-hint" id="pwStrengthLabel">Enter a new password</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="confirmPassword">Confirm New Password</label>
                <div class="form-input-wrap">
                  <input class="form-input" type="password" id="confirmPassword" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                  <button type="button" class="toggle-pw" tabindex="-1" onclick="togglePw('confirmPassword', this)" aria-label="Show/hide"><i class="fas fa-eye"></i></button>
                </div>
              </div>
            </div>
          </form>
          <div class="section-footer">
            <span class="success-notice" id="pwSuccess"><i class="fas fa-check-circle"></i> Password saved successfully</span>
            <div class="section-footer-right">
              <button type="button" class="btn-secondary" onclick="clearPasswordFields()">Cancel</button>
              <button type="button" class="btn-primary" onclick="handleSavePassword()"><i class="fas fa-save" style="margin-right:6px;"></i>Save Password</button>
            </div>
          </div>
        </div>

        <!-- C. PREFERENCES -->
        <div class="section-card anim anim-d4">
          <div class="section-head">
            <div class="section-head-icon"><i class="fas fa-sliders-h"></i></div>
            <div>
              <div class="section-title">Preferences</div>
              <div class="section-sub">Personalise your AntCareers experience</div>
            </div>
          </div>
          <form class="section-body" method="post" action="admin_settings.php">
            <input type="hidden" name="action" value="save_preferences">

            <!-- Appearance -->
            <div class="pref-group-label"><i class="fas fa-palette"></i> Appearance</div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">Dark Mode</div>
                <div class="pref-desc">Use the dark colour scheme across all pages</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle dark mode">
                <input type="checkbox" id="darkModeToggle" onchange="handleThemeToggle(this.checked)">
                <span class="toggle-track"></span>
              </label>
            </div>

            <!-- Notification Preferences -->
            <div class="pref-group-label"><i class="fas fa-bell"></i> Notification Preferences</div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">New messages</div>
                <div class="pref-desc">Notify me when users or employers message me</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle message notifications">
                <input type="checkbox" name="email_new_message" <?= !empty($notificationPrefs['email_new_message']) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">Flagged content &amp; reports</div>
                <div class="pref-desc">Notify me when content is flagged or a user is reported</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle flagged content notifications">
                <input type="checkbox" name="notif_flagged_content" <?= !empty($notificationPrefs['notif_flagged_content']) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">Company approval requests</div>
                <div class="pref-desc">Notify me when a new company registers and needs approval</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle company pending notifications">
                <input type="checkbox" name="notif_company_pending" <?= !empty($notificationPrefs['notif_company_pending']) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">Job moderation requests</div>
                <div class="pref-desc">Notify me when a new job posting is submitted for review</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle job pending notifications">
                <input type="checkbox" name="notif_job_pending" <?= !empty($notificationPrefs['notif_job_pending']) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <div class="pref-row">
              <div class="pref-info">
                <div class="pref-label">New user registrations</div>
                <div class="pref-desc">Notify me when a new user registers on the platform</div>
              </div>
              <label class="toggle-switch" aria-label="Toggle new registration notifications">
                <input type="checkbox" name="notif_new_registration" <?= !empty($notificationPrefs['notif_new_registration']) ? 'checked' : '' ?>>
                <span class="toggle-track"></span>
              </label>
            </div>
            <div class="section-footer" style="padding:14px 0 0;border-top:none;">
              <span class="success-notice" id="prefSuccess"><i class="fas fa-check-circle"></i> Preferences saved</span>
              <div class="section-footer-right" style="margin-left:auto;">
                <button type="submit" class="btn-primary"><i class="fas fa-save" style="margin-right:6px;"></i>Save Preferences</button>
              </div>
            </div>
          </form>
        </div>

        <!-- D. SECURITY -->
        <div class="section-card anim anim-d5">
          <div class="section-head">
            <div class="section-head-icon"><i class="fas fa-shield-alt"></i></div>
            <div>
              <div class="section-title">Security</div>
              <div class="section-sub">Manage your session and account access</div>
            </div>
          </div>
          <div class="section-body">
            <div class="security-row">
              <div class="security-info">
                <div class="security-label"><i class="fas fa-circle"></i> Session Status</div>
                <div class="security-desc">You are currently signed in as <?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <span class="security-status active"><i class="fas fa-check"></i> Active</span>
            </div>
            <div class="security-row">
              <div class="security-info">
                <div class="security-label"><i class="fas fa-key"></i> Password</div>
                <div class="security-desc">Last updated - use the Change Password section above</div>
              </div>
            </div>
            <div class="security-row">
              <div class="security-info">
                <div class="security-label"><i class="fas fa-user-slash"></i> Deactivate Account</div>
                <div class="security-desc">Soft-delete your account and log out immediately</div>
              </div>
              <form method="post" action="admin_settings.php" onsubmit="return confirm('Deactivate your account? This will disable admin access.');">
                <input type="hidden" name="action" value="deactivate_account">
                <button type="submit" class="btn-danger"><i class="fas fa-user-slash"></i> Deactivate</button>
              </form>
            </div>
            <div class="security-row">
              <div class="security-info">
                <div class="security-label"><i class="fas fa-sign-out-alt"></i> Sign Out</div>
                <div class="security-desc">End your current session and return to the login page</div>
              </div>
              <a href="../auth/logout.php" class="btn-danger"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
            </div>
          </div>
        </div>

      </div>
    </main>

  </div>
</div>

<script>
(function () {
  'use strict';

  // ── Theme ──
  function setTheme(t) {
    document.body.classList.toggle('light', t==='light');
    document.querySelectorAll('#themeToggle i').forEach(function(i) { i.className = t==='light' ? 'fas fa-sun' : 'fas fa-moon'; });
    localStorage.setItem('ac-theme', t);
    syncDarkToggle();
  }
  document.getElementById('themeToggle').addEventListener('click', function() {
    setTheme(document.body.classList.contains('light') ? 'dark' : 'light');
  });
  setTheme(localStorage.getItem('ac-theme') || 'dark');

  function syncDarkToggle() {
    var isDark = !document.body.classList.contains('light');
    var toggle = document.getElementById('darkModeToggle');
    if (toggle) toggle.checked = isDark;
  }

  window.handleThemeToggle = function (isDark) {
    setTheme(isDark ? 'dark' : 'light');
  };

  // ── Profile dropdown ──
  var profileToggle = document.getElementById('profileToggle');
  var profileDropdown = document.getElementById('profileDropdown');
  if (profileToggle && profileDropdown) {
    profileToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      profileDropdown.classList.toggle('open');
    });
    document.addEventListener('click', function() { profileDropdown.classList.remove('open'); });
  }

  // ── Hamburger ──
  var hamburger = document.getElementById('hamburger');
  var mobileMenu = document.getElementById('mobileMenu');
  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', function() { mobileMenu.classList.toggle('open'); });
  }

  // ── Password visibility ──
  window.togglePw = function (inputId, btn) {
    var input = document.getElementById(inputId);
    var isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
  };

  // ── Password strength ──
  window.updatePwStrength = function (val) {
    var fill  = document.getElementById('pwStrengthFill');
    var label = document.getElementById('pwStrengthLabel');
    if (!fill || !label) return;
    var score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    var levels = [
      { pct:'0%',   color:'transparent',  text:'Enter a new password' },
      { pct:'25%',  color:'#E05555',      text:'Weak' },
      { pct:'50%',  color:'var(--amber)', text:'Fair' },
      { pct:'75%',  color:'#6BB8F5',      text:'Good' },
      { pct:'100%', color:'var(--green)',  text:'Strong' },
    ];
    var lvl = levels[val.length === 0 ? 0 : score] || levels[4];
    fill.style.width      = lvl.pct;
    fill.style.background = lvl.color;
    label.textContent     = lvl.text;
    label.style.color     = val.length === 0 ? '' : lvl.color;
  };

  // ── Save password ──
  window.handleSavePassword = function () {
    var cur  = document.getElementById('currentPassword').value.trim();
    var nw   = document.getElementById('newPassword').value;
    var conf = document.getElementById('confirmPassword').value;
    if (!cur) return;
    if (nw.length < 8) return;
    if (nw !== conf) return;
    var form = document.getElementById('passwordForm');
    if (form) form.submit();
  };

  window.clearPasswordFields = function () {
    ['currentPassword','newPassword','confirmPassword'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) { el.value = ''; el.type = 'password'; }
      var wrap = el && el.closest('.form-input-wrap');
      if (wrap) { var btn = wrap.querySelector('.toggle-pw i'); if (btn) btn.className = 'fas fa-eye'; }
    });
    updatePwStrength('');
  };
})();
</script>
<?php renderAdminNotifPanel(); ?>
<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>
</body>
</html>
