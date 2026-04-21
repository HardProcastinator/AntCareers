<?php
declare(strict_types=1);
/**
 * AntCareers — Job Notification Unsubscribe
 *
 * Validates the HMAC token from the email link and turns off
 * relevant-job notifications for the corresponding seeker.
 *
 * URL: /seeker/job_notif_unsubscribe.php?email=xxx&token=yyy
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/job_match_mailer.php';

$email  = trim((string)($_GET['email'] ?? ''));
$token  = trim((string)($_GET['token'] ?? ''));

$success = false;
$error   = '';

if ($email !== '' && $token !== '' && hash_equals(jobNotifToken($email), $token)) {
    try {
        $db = getDB();

        $userStmt = $db->prepare(
            'SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1'
        );
        $userStmt->execute([$email]);
        $userId = (int)$userStmt->fetchColumn();

        if ($userId > 0) {
            $db->prepare(
                "INSERT INTO user_preferences (user_id, notif_relevant_jobs)
                 VALUES (?, 0)
                 ON DUPLICATE KEY UPDATE notif_relevant_jobs = 0, updated_at = CURRENT_TIMESTAMP"
            )->execute([$userId]);
            $success = true;
        } else {
            $error = 'Email address not found.';
        }
    } catch (PDOException $e) {
        error_log('[AntCareers] unsubscribe: ' . $e->getMessage());
        $error = 'Something went wrong. Please try again later.';
    }
} else {
    $error = 'This unsubscribe link is invalid or has expired.';
}

$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $success ? 'Unsubscribed' : 'Unsubscribe Error' ?> — AntCareers</title>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family:'Segoe UI',Arial,sans-serif;
      background:#0A0909; color:var(--text-light);
      min-height:100vh; display:flex; align-items:center; justify-content:center;
      padding:24px;
    }
    .card {
      background:#1C1818; border:1px solid #352E2E; border-radius:14px;
      padding:40px 36px; max-width:440px; width:100%; text-align:center;
    }
    .icon {
      width:60px; height:60px; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:26px; margin:0 auto 20px;
    }
    .icon.success { background:rgba(76,175,112,0.12); color:#6CCF8A; }
    .icon.error   { background:rgba(209,61,44,0.12);  color:#E85540; }
    h1 { font-size:20px; font-weight:700; margin-bottom:10px; }
    p  { font-size:14px; color:#D0BCBA; line-height:1.65; }
    .back-link {
      display:inline-block; margin-top:24px; padding:10px 22px;
      background:#252020; border:1px solid #352E2E; border-radius:8px;
      color:#D0BCBA; text-decoration:none; font-size:13px; font-weight:600;
      transition:border-color 0.15s;
    }
    .back-link:hover { border-color:#D13D2C; color:var(--text-light); }
    .logo { font-size:18px; font-weight:800; margin-bottom:28px; color:var(--text-light); }
    .logo span { color:#D13D2C; }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">Ant<span>Careers</span></div>

    <?php if ($success): ?>
      <div class="icon success">&#10003;</div>
      <h1>Unsubscribed successfully</h1>
      <p>
        You will no longer receive job match email notifications at
        <strong><?= $safeEmail ?></strong>.<br><br>
        You can re-enable them anytime in
        <strong>Settings → Notifications → Relevant job postings</strong>.
      </p>
    <?php else: ?>
      <div class="icon error">&#33;</div>
      <h1>Unable to unsubscribe</h1>
      <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
      <p style="margin-top:12px;">
        To turn off notifications, sign in and go to
        <strong>Settings → Notifications</strong>.
      </p>
    <?php endif; ?>

    <a class="back-link" href="antcareers_seekerDashboard.php">← Back to Dashboard</a>
  </div>
</body>
</html>
