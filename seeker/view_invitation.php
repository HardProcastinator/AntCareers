<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('seeker');

$user      = getUser();
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$navActive = 'jobs';
$seekerId  = (int)$_SESSION['user_id'];
$db        = getDB();

/* ── Ensure job_invitations table exists ── */
try { $db->query('SELECT 1 FROM job_invitations LIMIT 0'); }
catch (PDOException $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS job_invitations (
            id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id INT(10) UNSIGNED NOT NULL,
            recruiter_id INT(10) UNSIGNED NOT NULL,
            jobseeker_id INT(10) UNSIGNED NOT NULL,
            status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
            custom_note TEXT DEFAULT NULL,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_invite (job_id, jobseeker_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $ce) { error_log('[AntCareers] inv table create: ' . $ce->getMessage()); }
}

$invId = (int)($_GET['id'] ?? 0);
$inv   = null;
$error = '';

if (!$invId) {
    $error = 'Invalid invitation link.';
} else {
    try {
        $stmt = $db->prepare("
            SELECT
                ji.id, ji.job_id, ji.recruiter_id, ji.jobseeker_id,
                ji.status, ji.custom_note, ji.sent_at, ji.responded_at,
                j.title AS job_title, j.location AS job_location,
                j.job_type, j.setup, j.experience_level, j.industry,
                j.salary_min, j.salary_max, j.salary_currency,
                j.description, j.requirements, j.skills_required,
                j.status AS job_status, j.approval_status, j.deadline,
                j.employer_id,
                COALESCE(cp.company_name, eu.company_name, eu.full_name, 'Unknown Company') AS company_name,
                cp.logo_path  AS company_logo,
                cp.about      AS company_about,
                ru.full_name  AS recruiter_name,
                ru.avatar_url AS recruiter_avatar,
                (SELECT 1 FROM applications a
                    WHERE a.job_id = ji.job_id AND a.seeker_id = ji.jobseeker_id LIMIT 1) AS already_applied
            FROM job_invitations ji
            JOIN jobs j         ON j.id   = ji.job_id
            JOIN users eu       ON eu.id  = j.employer_id
            LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
            JOIN users ru       ON ru.id  = ji.recruiter_id
            WHERE ji.id = ? AND ji.jobseeker_id = ?
            LIMIT 1
        ");
        $stmt->execute([$invId, $seekerId]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) $error = 'Invitation not found or you are not authorised to view it.';
    } catch (PDOException $e) {
        error_log('[AntCareers] view_invitation load: ' . $e->getMessage());
        $error = 'Could not load invitation. Please try again later.';
    }
}

/* ── Helpers ── */
$jobIsActive = $inv && $inv['job_status'] === 'Active';
$jobDeadline = $inv['deadline'] ?? null;
$deadlineOk  = !$jobDeadline || strtotime($jobDeadline) >= strtotime('today');
$canRespond  = $inv && $inv['status'] === 'pending' && $jobIsActive && $deadlineOk && !$inv['already_applied'];
$alreadyApplied = $inv && (bool)$inv['already_applied'];

/* ── Build salary string ── */
$salaryStr = '';
if ($inv) {
    $sMin = (float)($inv['salary_min'] ?? 0);
    $sMax = (float)($inv['salary_max'] ?? 0);
    $cur  = $inv['salary_currency'] ?? 'PHP';
    if ($sMin && $sMax)  $salaryStr = $cur . ' ' . number_format($sMin) . ' – ' . number_format($sMax);
    elseif ($sMin)       $salaryStr = $cur . ' ' . number_format($sMin) . '+';
    elseif ($sMax)       $salaryStr = $cur . ' up to ' . number_format($sMax);
}

/* ── Skills array ── */
$skills = [];
if ($inv && $inv['skills_required']) {
    $skills = array_values(array_filter(array_map('trim', explode(',', $inv['skills_required']))));
}

/* ── Recruiter initials / avatar ── */
$recName   = $inv['recruiter_name'] ?? '';
$recParts  = preg_split('/\s+/', trim($recName)) ?: ['R'];
$recInit   = strtoupper(substr($recParts[0], 0, 1) . (isset($recParts[1]) ? substr($recParts[1], 0, 1) : ''));
$recAvatar = !empty($inv['recruiter_avatar']) ? '../' . ltrim($inv['recruiter_avatar'], '/') : '';

/* ── Sent date ── */
$sentDate = $inv ? date('F j, Y', strtotime($inv['sent_at'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Job Invitation — AntCareers</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;
      --soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;
      --text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;
      --amber:#D4943A;--green:#4CAF70;--blue:#2563EB;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);min-height:100vh;-webkit-font-smoothing:antialiased;}

    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.12) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}

    /* Light theme support */
    body.light{background:#FAF7F5;color:#1A0A09;--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;}
    body.light .glow-orb{opacity:0.04;}

    .page-shell{max-width:900px;margin:0 auto;padding:28px 18px 80px;position:relative;z-index:1;}

    /* ── Back link ── */
    .back-link{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;transition:.18s;}
    .back-link:hover{color:var(--red-pale);}

    /* ── Error state ── */
    .error-card{background:var(--soil-card);border:1px solid rgba(220,53,69,.3);border-radius:14px;padding:48px 24px;text-align:center;color:var(--text-muted);}
    .error-card i{font-size:40px;color:#ff8080;margin-bottom:14px;display:block;}
    .error-card h2{font-family:var(--font-display);font-size:20px;color:var(--text-light);margin-bottom:8px;}
    body.light .error-card h2{color:#1A0A09;}

    /* ── Invitation Card (formal letter) ── */
    .inv-card{background:var(--soil-card);border:1px solid var(--soil-line);border-top:3px solid #3B82F6;border-radius:16px;overflow:hidden;margin-bottom:24px;position:relative;box-shadow:0 8px 40px rgba(0,0,0,.3);}
    body.light .inv-card{background:#fff;border-color:#E0CECA;border-top-color:#2563EB;box-shadow:0 8px 40px rgba(0,0,0,.07);}

    .inv-card-header{background:linear-gradient(135deg,rgba(37,99,235,.22) 0%,rgba(209,61,44,.1) 100%);border-bottom:1px solid var(--soil-line);padding:30px 34px;display:flex;align-items:flex-start;gap:20px;}
    body.light .inv-card-header{border-bottom-color:#E0CECA;background:linear-gradient(135deg,rgba(37,99,235,.07) 0%,rgba(209,61,44,.04) 100%);}
    .inv-icon-wrap{width:64px;height:64px;border-radius:14px;background:linear-gradient(135deg,rgba(37,99,235,.28),rgba(37,99,235,.1));border:1px solid rgba(37,99,235,.35);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 18px rgba(37,99,235,.22);}
    .inv-icon-wrap i{font-size:28px;color:#60A5FA;}
    .inv-card-header-info{flex:1;}
    .inv-card-badge{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;padding:4px 13px;border-radius:20px;background:linear-gradient(135deg,rgba(37,99,235,.22),rgba(37,99,235,.1));color:#93C5FD;border:1px solid rgba(37,99,235,.3);letter-spacing:.05em;margin-bottom:10px;text-transform:uppercase;}
    .inv-card-title{font-family:var(--font-display);font-size:26px;font-weight:700;color:var(--text-light);margin-bottom:6px;line-height:1.2;}
    body.light .inv-card-title{color:#1A0A09;}
    .inv-card-meta{font-size:13px;color:var(--text-muted);line-height:1.6;}
    .inv-card-meta strong{color:var(--text-mid);}

    /* Status indicators */
    .inv-status-banner{padding:14px 34px;display:flex;align-items:center;gap:12px;font-size:13.5px;font-weight:600;}
    .inv-status-banner i{font-size:15px;}
    .inv-status-banner.accepted{background:rgba(76,175,112,.12);color:#5ec87a;border-bottom:1px solid rgba(76,175,112,.2);}
    .inv-status-banner.declined{background:rgba(220,53,69,.12);color:#ff8080;border-bottom:1px solid rgba(220,53,69,.2);}
    .inv-status-banner.applied{background:rgba(76,175,112,.12);color:#5ec87a;border-bottom:1px solid rgba(76,175,112,.2);}
    .inv-status-banner.closed{background:rgba(212,148,58,.12);color:var(--amber);border-bottom:1px solid rgba(212,148,58,.2);}

    /* Letter body */
    .inv-letter{padding:32px 34px;}
    .inv-letter-greeting{font-size:17px;font-weight:600;color:var(--text-light);margin-bottom:16px;}
    body.light .inv-letter-greeting{color:#1A0A09;}
    .inv-letter-body{font-size:14.5px;color:var(--text-mid);line-height:1.78;margin-bottom:20px;}
    body.light .inv-letter-body{color:#3A2020;}
    .inv-letter-body p{margin-bottom:14px;}
    .inv-personal-note{background:linear-gradient(135deg,rgba(37,99,235,.09),rgba(37,99,235,.04));border:1px solid rgba(37,99,235,.25);border-left:4px solid #3B82F6;border-radius:10px;padding:16px 20px;font-size:13.5px;color:var(--text-mid);font-style:italic;margin-bottom:20px;line-height:1.72;position:relative;}
    body.light .inv-personal-note{background:rgba(37,99,235,.05);border-left-color:#2563EB;border-color:rgba(37,99,235,.2);color:#3A2040;}
    .inv-personal-note-lbl{font-style:normal;font-weight:700;font-size:11px;color:#60A5FA;text-transform:uppercase;letter-spacing:.06em;margin-bottom:7px;display:flex;align-items:center;gap:5px;}
    body.light .inv-personal-note-lbl{color:#2563EB;}
    .inv-letter-sig{font-size:13px;color:var(--text-muted);line-height:1.85;margin-top:20px;padding-top:18px;border-top:1px solid var(--soil-line);}
    body.light .inv-letter-sig{border-top-color:#E0CECA;}
    .inv-letter-sig strong{color:var(--text-mid);}

    /* Recruiter info */
    .inv-rec-wrap{display:flex;align-items:center;gap:13px;margin-top:13px;}
    .inv-rec-av{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#D13D2C,#7A1515);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;border:2px solid rgba(209,61,44,.35);box-shadow:0 3px 10px rgba(209,61,44,.25);}
    .inv-rec-av img{width:100%;height:100%;object-fit:cover;}
    .inv-rec-name{font-size:14px;font-weight:700;color:var(--text-light);}
    body.light .inv-rec-name{color:#1A0A09;}
    .inv-rec-role{font-size:11.5px;color:var(--text-muted);margin-top:1px;}

    /* CTA buttons */
    .inv-cta{padding:24px 34px;background:rgba(0,0,0,.12);border-top:1px solid var(--soil-line);display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    body.light .inv-cta{border-top-color:#E0CECA;background:rgba(0,0,0,.02);}
    .btn-invite-accept{display:inline-flex;align-items:center;gap:9px;padding:13px 30px;background:linear-gradient(135deg,#22C55E,#16A34A);border:none;border-radius:10px;color:#fff;font-family:var(--font-body);font-size:14px;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 3px 14px rgba(34,197,94,.38);letter-spacing:.02em;}
    .btn-invite-accept:hover{background:linear-gradient(135deg,#4ADE80,#22C55E);transform:translateY(-2px);box-shadow:0 7px 22px rgba(34,197,94,.48);}
    .btn-invite-accept:active{transform:translateY(0);}
    .btn-invite-accept:disabled{opacity:.6;cursor:not-allowed;transform:none;box-shadow:none;}
    .btn-invite-decline{display:inline-flex;align-items:center;gap:9px;padding:13px 24px;background:transparent;border:1.5px solid rgba(220,53,69,.45);border-radius:10px;color:#ff8080;font-family:var(--font-body);font-size:14px;font-weight:600;cursor:pointer;transition:all .22s;}
    .btn-invite-decline:hover{background:rgba(220,53,69,.1);border-color:rgba(220,53,69,.7);transform:translateY(-1px);}
    .btn-invite-decline:disabled{opacity:.6;cursor:not-allowed;transform:none;}
    .inv-cta-note{font-size:12px;color:var(--text-muted);margin-left:4px;}

    /* ── Job Details Card ── */
    .job-detail-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:16px;overflow:hidden;margin-bottom:24px;box-shadow:0 4px 24px rgba(0,0,0,.2);}
    body.light .job-detail-card{background:#fff;border-color:#E0CECA;box-shadow:0 4px 24px rgba(0,0,0,.05);}
    .jd-header{padding:26px 30px;border-bottom:1px solid var(--soil-line);display:flex;align-items:flex-start;gap:18px;}
    body.light .jd-header{border-bottom-color:#E0CECA;}
    .company-logo{width:58px;height:58px;border-radius:12px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--red-bright);flex-shrink:0;overflow:hidden;box-shadow:0 3px 12px rgba(0,0,0,.25);}
    .company-logo img{width:100%;height:100%;object-fit:cover;}
    .jd-title-info{flex:1;}
    .jd-job-title{font-family:var(--font-display);font-size:23px;font-weight:700;color:var(--text-light);margin-bottom:5px;}
    body.light .jd-job-title{color:#1A0A09;}
    .jd-company{font-size:14px;font-weight:600;color:var(--text-mid);margin-bottom:9px;}
    .jd-meta-row{display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:var(--text-muted);}
    .jd-meta-row i{font-size:11px;color:var(--red-bright);margin-right:4px;}
    .jd-body{padding:26px 30px;}
    .jd-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px;}
    .jd-chip{font-size:11.5px;font-weight:600;padding:5px 13px;border-radius:7px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);}
    body.light .jd-chip{background:#F5EDEB;border-color:#D4B0AB;color:#6A4A4A;}
    .jd-chip.type{background:rgba(59,130,246,.12);color:#60A5FA;border-color:rgba(59,130,246,.25);}
    body.light .jd-chip.type{color:#2563EB;background:rgba(59,130,246,.06);}
    .jd-chip.setup{background:rgba(168,85,247,.12);color:#C084FC;border-color:rgba(168,85,247,.25);}
    .jd-chip.level{background:rgba(212,148,58,.12);color:var(--amber);border-color:rgba(212,148,58,.25);}
    .jd-chip.salary{background:linear-gradient(135deg,rgba(76,175,112,.18),rgba(76,175,112,.08));color:#5ec87a;border-color:rgba(76,175,112,.3);font-weight:700;}
    .jd-section-title{font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;margin-top:20px;}
    .jd-text{font-size:14px;color:var(--text-mid);line-height:1.76;white-space:pre-wrap;word-break:break-word;}
    body.light .jd-text{color:#3A2020;}
    .jd-skills{display:flex;gap:7px;flex-wrap:wrap;}
    .jd-skill{font-size:12px;font-weight:600;padding:5px 12px;border-radius:7px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);}
    body.light .jd-skill{background:#F5EDEB;border-color:#D4B0AB;color:#6A4A4A;}

    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .fade-in{animation:fadeUp .45s ease both;}
  </style>
</head>
<body>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<div class="page-shell fade-in">
  <a href="antcareers_seekerJobs.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Jobs</a>

<?php if ($error): ?>
  <div class="error-card">
    <i class="fas fa-envelope-open"></i>
    <h2>Invitation Not Found</h2>
    <p><?= htmlspecialchars($error) ?></p>
    <a href="antcareers_seekerJobs.php" style="display:inline-block;margin-top:16px;padding:10px 22px;background:var(--red-vivid);border-radius:8px;color:#fff;font-weight:700;font-size:13px;text-decoration:none;">Browse Jobs</a>
  </div>

<?php else:
    /* ─── Build status banner ─── */
    $statusBanner = '';
    if ($inv['status'] === 'accepted' || $alreadyApplied) {
        $statusBanner = '<div class="inv-status-banner accepted"><i class="fas fa-check-circle"></i> You have accepted this invitation and applied for the position.</div>';
    } elseif ($inv['status'] === 'declined') {
        $statusBanner = '<div class="inv-status-banner declined"><i class="fas fa-times-circle"></i> You declined this invitation.</div>';
    } elseif (!$jobIsActive) {
        $statusBanner = '<div class="inv-status-banner closed"><i class="fas fa-lock"></i> This job is no longer active.</div>';
    } elseif ($jobDeadline && !$deadlineOk) {
        $statusBanner = '<div class="inv-status-banner closed"><i class="fas fa-calendar-times"></i> The application deadline for this job has passed.</div>';
    }
?>
  <!-- ═══ INVITATION CARD ═══ -->
  <div class="inv-card">
    <div class="inv-card-header">
      <div class="inv-icon-wrap"><i class="fas fa-envelope-open-text"></i></div>
      <div class="inv-card-header-info">
        <div class="inv-card-badge"><i class="fas fa-star"></i> Exclusive Invitation</div>
        <div class="inv-card-title">You've been invited to apply!</div>
        <div class="inv-card-meta">
          For <strong><?= htmlspecialchars($inv['job_title']) ?></strong>
          at <strong><?= htmlspecialchars($inv['company_name']) ?></strong>
          &nbsp;·&nbsp; Sent <?= htmlspecialchars($sentDate) ?>
        </div>
      </div>
    </div>

    <?= $statusBanner ?>

    <!-- Letter body -->
    <div class="inv-letter">
      <div class="inv-letter-greeting">Dear <strong><?= htmlspecialchars($firstName ?? $fullName) ?>,</strong></div>
      <div class="inv-letter-body">
        <p>We came across your profile on AntCareers and believe you would be an excellent fit for the
          <strong><?= htmlspecialchars($inv['job_title']) ?></strong> position at
          <strong><?= htmlspecialchars($inv['company_name']) ?></strong>.</p>
        <p>We would like to formally invite you to review this opportunity and consider submitting your application.
           Your skills and background align with what we're looking for in this role.</p>
        <p>We look forward to hearing from you.</p>
      </div>

      <?php if (!empty($inv['custom_note'])): ?>
      <div class="inv-personal-note">
        <div class="inv-personal-note-lbl"><i class="fas fa-quote-left" style="margin-right:5px;"></i>Personal Message</div>
        <?= nl2br(htmlspecialchars($inv['custom_note'])) ?>
      </div>
      <?php endif; ?>

      <div class="inv-letter-sig">
        Best regards,
        <div class="inv-rec-wrap">
          <div class="inv-rec-av">
            <?php if ($recAvatar): ?>
              <img src="<?= htmlspecialchars($recAvatar) ?>" alt="">
            <?php else: ?>
              <?= htmlspecialchars($recInit) ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="inv-rec-name"><?= htmlspecialchars($inv['recruiter_name']) ?></div>
            <div class="inv-rec-role">Recruiter &nbsp;·&nbsp; <?= htmlspecialchars($inv['company_name']) ?></div>
          </div>
        </div>
      </div>
    </div><!-- /.inv-letter -->

    <!-- ═══ JOB DETAILS (inside same card) ═══ -->
    <div style="border-top:1px solid var(--soil-line);"></div>
    <div class="jd-header">
      <div class="company-logo">
        <?php if (!empty($inv['company_logo'])): ?>
          <img src="../<?= htmlspecialchars(ltrim($inv['company_logo'], '/')) ?>" alt="">
        <?php else: ?>
          <i class="fas fa-building"></i>
        <?php endif; ?>
      </div>
      <div class="jd-title-info">
        <div class="jd-job-title"><?= htmlspecialchars($inv['job_title']) ?></div>
        <div class="jd-company"><?= htmlspecialchars($inv['company_name']) ?></div>
        <div class="jd-meta-row">
          <?php if ($inv['job_location']): ?>
            <span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($inv['job_location']) ?></span>
          <?php endif; ?>
          <?php if ($inv['industry']): ?>
            <span><i class="fas fa-industry"></i><?= htmlspecialchars($inv['industry']) ?></span>
          <?php endif; ?>
          <?php if ($inv['deadline']): ?>
            <span><i class="fas fa-calendar-alt"></i>Deadline: <?= date('M j, Y', strtotime($inv['deadline'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="jd-body">
      <!-- Chips -->
      <div class="jd-chips">
        <span class="jd-chip type"><i class="fas fa-tag" style="font-size:9px;margin-right:3px;"></i><?= htmlspecialchars($inv['job_type'] ?? 'Full-time') ?></span>
        <?php if ($inv['setup']): ?><span class="jd-chip setup"><i class="fas fa-laptop-house" style="font-size:9px;margin-right:3px;"></i><?= htmlspecialchars($inv['setup']) ?></span><?php endif; ?>
        <?php if ($inv['experience_level']): ?><span class="jd-chip level"><?= htmlspecialchars($inv['experience_level']) ?></span><?php endif; ?>
        <?php if ($salaryStr): ?><span class="jd-chip salary"><i class="fas fa-money-bill-wave" style="font-size:9px;margin-right:3px;"></i><?= htmlspecialchars($salaryStr) ?></span><?php endif; ?>
      </div>

      <?php if ($skills): ?>
      <div class="jd-section-title">Required Skills</div>
      <div class="jd-skills">
        <?php foreach ($skills as $sk): ?>
          <span class="jd-skill"><?= htmlspecialchars($sk) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($inv['description']): ?>
      <div class="jd-section-title" style="margin-top:20px;">Job Description</div>
      <div class="jd-text"><?= htmlspecialchars($inv['description']) ?></div>
      <?php endif; ?>

      <?php if ($inv['requirements']): ?>
      <div class="jd-section-title">Requirements</div>
      <div class="jd-text"><?= htmlspecialchars($inv['requirements']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($canRespond): ?>
    <div class="inv-cta">
      <button class="btn-invite-accept" onclick="respondToInvite('accepted')">
        <i class="fas fa-check-circle"></i> Accept &amp; Apply Now
      </button>
      <button class="btn-invite-decline" onclick="respondToInvite('declined')">
        <i class="fas fa-times-circle"></i> Decline Invitation
      </button>
      <span class="inv-cta-note">Accepting will submit your application automatically.</span>
    </div>
    <?php elseif ($inv['status'] === 'pending' && !$canRespond && !$alreadyApplied): ?>
    <div class="inv-cta">
      <span style="font-size:13px;color:var(--text-muted);"><i class="fas fa-info-circle" style="margin-right:6px;"></i>This invitation can no longer be accepted.</span>
    </div>
    <?php endif; ?>
  </div><!-- /.inv-card -->

<?php endif; ?>
</div><!-- /.page-shell -->

<div class="toast" id="viToast"></div>

<script>
var _invId = <?= (int)($inv['id'] ?? 0) ?>;
var _invStatus = <?= json_encode($inv['status'] ?? 'pending') ?>;

function respondToInvite(response) {
  if (_invStatus !== 'pending') return;

  var acceptBtns  = document.querySelectorAll('.btn-invite-accept');
  var declineBtns = document.querySelectorAll('.btn-invite-decline');

  var label = response === 'accepted'
    ? '<i class="fas fa-circle-notch fa-spin"></i> Applying…'
    : '<i class="fas fa-circle-notch fa-spin"></i> Declining…';

  acceptBtns.forEach(function(b){ b.disabled = true; if (response === 'accepted') b.innerHTML = label; });
  declineBtns.forEach(function(b){ b.disabled = true; if (response === 'declined') b.innerHTML = label; });

  var fd = new FormData();
  fd.append('action',   'respond');
  fd.append('id',       _invId);
  fd.append('response', response);

  fetch('../api/job_invitations.php', { method: 'POST', body: fd })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.ok) {
        _invStatus = response;
        viToast(
          response === 'accepted'
            ? (d.app_created ? 'Application submitted successfully!' : 'Invitation accepted!')
            : 'Invitation declined.',
          response === 'accepted' ? 'ok' : ''
        );
        /* Reload after short delay so status banner updates */
        setTimeout(function(){ location.reload(); }, 1600);
      } else {
        acceptBtns.forEach(function(b){ b.disabled = false; b.innerHTML = '<i class="fas fa-check-circle"></i> Accept &amp; Apply Now'; });
        declineBtns.forEach(function(b){ b.disabled = false; b.innerHTML = '<i class="fas fa-times-circle"></i> Decline Invitation'; });
        viToast(d.msg || 'Something went wrong. Please try again.', 'err');
      }
    })
    .catch(function() {
      acceptBtns.forEach(function(b){ b.disabled = false; b.innerHTML = '<i class="fas fa-check-circle"></i> Accept &amp; Apply Now'; });
      declineBtns.forEach(function(b){ b.disabled = false; b.innerHTML = '<i class="fas fa-times-circle"></i> Decline Invitation'; });
      viToast('Network error. Please try again.', 'err');
    });
}

function viToast(msg, type) {
  var t = document.getElementById('viToast');
  t.textContent = msg;
  t.className   = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._t);
  t._t = setTimeout(function(){ t.className = 'toast'; }, 3500);
}

/* Apply theme from localStorage */
(function() {
  var theme = localStorage.getItem('ac-theme') || 'dark';
  if (theme === 'light') document.body.classList.add('light');
})();
</script>
</body>
</html>
