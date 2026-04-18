<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'admin') {
    header('Location: ../auth/antcareers_login.php');
    exit;
}

$adminId  = (int)$_SESSION['user_id'];
$fullName = trim((string)($_SESSION['user_name'] ?? 'Admin'));
$nameParts = preg_split('/\s+/', $fullName) ?: [];
$initials  = count($nameParts) >= 2
    ? strtoupper(substr($nameParts[0],0,1).substr($nameParts[1],0,1))
    : strtoupper(substr($fullName,0,2));
$db = getDB();

$safe = static function (string $sql, PDO $db): int|float|string {
    try { return $db->query($sql)->fetchColumn(); } catch (Throwable) { return 0; }
};

// ── CSV export ────────────────────────────────────────────────
$exportType = $_GET['export'] ?? '';
if ($exportType === 'users') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="antcareers_users_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Full Name','Email','Account Type','Status','Joined']);
    try {
        $rows = $db->query("SELECT id,full_name,email,account_type,COALESCE(account_status,'active'),created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $r) fputcsv($out, $r);
    } catch (Throwable) {}
    fclose($out); exit;
}
if ($exportType === 'jobs') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="antcareers_jobs_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Title','Company','Location','Type','Status','Approval','Posted']);
    try {
        $rows = $db->query("SELECT j.id,j.title,u.company_name,j.location,j.type,j.status,j.approval_status,j.created_at FROM jobs j JOIN users u ON u.id=j.employer_id ORDER BY j.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $r) fputcsv($out, $r);
    } catch (Throwable) {}
    fclose($out); exit;
}
if ($exportType === 'applications') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="antcareers_applications_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Job Title','Company','Applicant','Status','Applied']);
    try {
        $rows = $db->query("SELECT a.id,j.title,u2.company_name,u.full_name,a.status,a.created_at FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.user_id JOIN users u2 ON u2.id=j.employer_id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $r) fputcsv($out, $r);
    } catch (Throwable) {}
    fclose($out); exit;
}
if ($exportType === 'hirings') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="antcareers_hirings_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Application ID','Job Title','Company','Applicant','Email','Hired Date']);
    try {
        $rows = $db->query(
            "SELECT a.id, j.title, u2.company_name, u.full_name, u.email, a.updated_at
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN users u ON u.id = a.user_id
             JOIN users u2 ON u2.id = j.employer_id
             WHERE a.status = 'Accepted'
             ORDER BY a.updated_at DESC"
        )->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $r) fputcsv($out, $r);
    } catch (Throwable) {}
    fclose($out); exit;
}

// ── Stats ─────────────────────────────────────────────────────
// Time-period filter for charts
$validPeriods = ['1' => 1, '3' => 3, '6' => 6, '12' => 12];
$periodKey = (string)($_GET['period'] ?? '6');
if (!isset($validPeriods[$periodKey])) $periodKey = '6';
$periodMonths = $validPeriods[$periodKey];
$periodInterval = "INTERVAL {$periodMonths} MONTH";

$totalUsers    = (int)$safe("SELECT COUNT(*) FROM users", $db);
$totalSeekers  = (int)$safe("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='seeker'", $db);
$totalEmployers= (int)$safe("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer'", $db);
$totalRecruiters=(int)$safe("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'", $db);
$totalJobs     = (int)$safe("SELECT COUNT(*) FROM jobs", $db);
$activeJobs    = (int)$safe("SELECT COUNT(*) FROM jobs WHERE status='Active' AND (deadline IS NULL OR deadline>=CURDATE())", $db);
$pendingJobs   = (int)$safe("SELECT COUNT(*) FROM jobs WHERE approval_status='pending'", $db);
$totalApps     = (int)$safe("SELECT COUNT(*) FROM applications", $db);
$unreadNotifs  = (int)$safe("SELECT COUNT(*) FROM notifications WHERE user_id={$adminId} AND is_read=0", $db);

// User growth by month
$userGrowth = [];
try {
    $rows = $db->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt
         FROM users
         WHERE created_at >= DATE_SUB(NOW(), {$periodInterval})
         GROUP BY month ORDER BY month"
    )->fetchAll();
    foreach ($rows as $r) $userGrowth[$r['month']] = (int)$r['cnt'];
} catch (Throwable) {}

// Job post trends by month
$jobTrends = [];
try {
    $rows = $db->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt
         FROM jobs
         WHERE created_at >= DATE_SUB(NOW(), {$periodInterval})
         GROUP BY month ORDER BY month"
    )->fetchAll();
    foreach ($rows as $r) $jobTrends[$r['month']] = (int)$r['cnt'];
} catch (Throwable) {}

// Application status breakdown (filtered by period)
$appStatuses = [];
try {
    $rows = $db->query(
        "SELECT status, COUNT(*) AS cnt FROM applications
         WHERE created_at >= DATE_SUB(NOW(), {$periodInterval})
         GROUP BY status"
    )->fetchAll();
    foreach ($rows as $r) $appStatuses[$r['status']] = (int)$r['cnt'];
} catch (Throwable) {}

// Hiring rates by month (status = 'Accepted')
$hiringTrends = [];
try {
    $rows = $db->query(
        "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month, COUNT(*) AS cnt
         FROM applications
         WHERE status = 'Accepted' AND created_at >= DATE_SUB(NOW(), {$periodInterval})
         GROUP BY month ORDER BY month"
    )->fetchAll();
    foreach ($rows as $r) $hiringTrends[$r['month']] = (int)$r['cnt'];
} catch (Throwable) {}

// System performance
$memUsage  = round(memory_get_usage(true) / 1048576, 1);
$memPeak   = round(memory_get_peak_usage(true) / 1048576, 1);
$phpVer    = PHP_VERSION;
$mysqlVer  = '';
try { $mysqlVer = (string)$db->query("SELECT VERSION()")->fetchColumn(); } catch (Throwable) {}
$diskFree  = '';
try {
    $bytes = disk_free_space('/');
    if ($bytes !== false) $diskFree = round($bytes / 1073741824, 1) . ' GB free';
} catch (Throwable) {}

// Helper: months array
function lastNMonths(int $n): array {
    $months = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-{$i} months"));
    }
    return $months;
}
$months6 = lastNMonths($periodMonths);

// Max values for bar scaling
$maxUserGrowth = max(array_values($userGrowth) ?: [1]);
$maxJobTrend   = max(array_values($jobTrends)   ?: [1]);
$maxHiring     = max(array_values($hiringTrends) ?: [1]);
$maxApps       = max(array_values($appStatuses)  ?: [1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Reports &amp; Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    :root{
      --red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;
      --soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;
      --text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;
      --amber:#D4943A;--amber-dim:#251C0E;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html{overflow-x:hidden}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);min-height:100vh;-webkit-font-smoothing:antialiased}
    a{text-decoration:none;color:inherit}

    /* NAVBAR */
    .navbar{position:sticky;top:0;z-index:400;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid rgba(209,61,44,0.35);box-shadow:0 4px 24px rgba(0,0,0,0.5)}
    .nav-inner{max-width:1380px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:64px;gap:0}
    .logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:28px;flex-shrink:0}
    .logo-icon{width:34px;height:34px;background:var(--red-vivid);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px}
    .logo-text{font-family:var(--font-display);font-weight:700;font-size:19px;color:#F5F0EE}
    .logo-text span{color:var(--red-bright)}
    .nav-links{display:flex;align-items:center;gap:2px;flex:1}
    .nav-link{font-size:13px;font-weight:600;color:#A09090;text-decoration:none;padding:7px 11px;border-radius:6px;transition:0.2s;display:flex;align-items:center;gap:5px;white-space:nowrap}
    .nav-link:hover,.nav-link.active{color:#F5F0EE;background:var(--soil-hover)}
    .nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0}
    .theme-btn{width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:13px}
    .notif-btn-nav{position:relative;width:36px;height:36px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:15px;color:var(--text-muted)}
    .notif-btn-nav:hover{color:var(--red-pale);border-color:var(--red-vivid)}
    .notif-btn-nav .badge{position:absolute;top:-5px;right:-5px;width:17px;height:17px;border-radius:50%;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--soil-dark)}
    .admin-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:rgba(209,61,44,0.12);border:1px solid rgba(209,61,44,0.25);border-radius:100px;font-size:11px;font-weight:700;color:var(--red-pale);letter-spacing:0.04em}
    .profile-wrap{position:relative}
    .profile-btn{display:flex;align-items:center;gap:9px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:6px 12px 6px 8px;cursor:pointer;transition:0.2s}
    .profile-btn:hover{background:var(--soil-card)}
    .profile-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--red-deep),var(--red-vivid));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff}
    .profile-name{font-size:13px;font-weight:600;color:#F5F0EE}
    .profile-role{font-size:10px;color:var(--red-pale);font-weight:600}
    .profile-chevron{font-size:9px;color:var(--text-muted)}
    .profile-dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:6px;min-width:200px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:0.18s;z-index:300;box-shadow:0 20px 40px rgba(0,0,0,0.5)}
    .profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0)}
    .profile-dropdown-head{padding:12px 14px 10px;border-bottom:1px solid var(--soil-line);margin-bottom:4px}
    .pdh-name{font-size:14px;font-weight:700;color:#F5F0EE}
    .pdh-sub{font-size:11px;color:var(--red-pale);font-weight:600}
    .pd-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:6px;font-size:13px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:0.15s}
    .pd-item i{color:var(--text-muted);width:16px;text-align:center;font-size:12px}
    .pd-item:hover{background:var(--soil-hover);color:#F5F0EE}
    .pd-item:hover i{color:var(--red-bright)}
    .pd-divider{height:1px;background:var(--soil-line);margin:4px 6px}
    .pd-item.danger{color:#E05555}
    .pd-item.danger i{color:#E05555}
    .pd-item.danger:hover{background:rgba(224,85,85,0.1);color:#FF7070}

    /* LAYOUT */
    .page-shell{max-width:1380px;margin:0 auto;padding:0 24px 80px}
    .content-layout{display:block}

    /* PAGE HEADER */
    .page-header{padding:32px 0 24px}
    .page-title{font-family:var(--font-display);font-size:28px;font-weight:700;color:#F5F0EE;margin-bottom:6px}
    .page-title span{color:var(--red-bright);font-style:italic}
    .page-sub{font-size:14px;color:var(--text-muted)}

    /* SECTION */
    .sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
    .sec-title{font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:10px}
    .sec-title i{color:var(--red-bright);font-size:16px}
    .sec-section{margin-bottom:40px}

    /* STATS GRID */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:40px}
    .stat-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:20px;transition:0.2s}
    .stat-card:hover{border-color:rgba(209,61,44,0.4);transform:translateY(-2px)}
    .stat-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
    .stat-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px}
    .stat-icon.r{background:rgba(209,61,44,.12);color:var(--red-pale)}
    .stat-icon.a{background:rgba(212,148,58,.12);color:var(--amber)}
    .stat-icon.b{background:rgba(74,144,217,.1);color:#7ab8f0}
    .stat-icon.g{background:rgba(76,175,112,.1);color:#6ccf8a}
    .stat-num{font-family:var(--font-display);font-size:26px;font-weight:700;color:#F5F0EE;line-height:1}
    .stat-lbl{font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-top:4px}

    /* REPORT CARD */
    .report-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:24px;margin-bottom:28px}

    /* BAR CHART */
    .bar-chart{display:flex;gap:10px;align-items:flex-end;height:160px;margin:16px 0 8px}
    .bar-col{display:flex;flex-direction:column;align-items:center;flex:1;gap:4px;height:100%;justify-content:flex-end}
    .bar-fill-v{width:100%;border-radius:4px 4px 0 0;background:linear-gradient(180deg,var(--red-bright),var(--red-vivid));min-height:4px;transition:height 0.4s ease}
    .bar-fill-v.amber{background:linear-gradient(180deg,#f0b050,var(--amber))}
    .bar-fill-v.blue{background:linear-gradient(180deg,#7ab8f0,#4A90D9)}
    .bar-fill-v.green{background:linear-gradient(180deg,#6ccf8a,#4CAF70)}
    .bar-lbl{font-size:10px;color:var(--text-muted);font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
    .bar-val{font-size:10px;color:#F5F0EE;font-weight:700;text-align:center}

    /* HORIZONTAL BAR (for app status) */
    .hbar-list{display:flex;flex-direction:column;gap:10px}
    .hbar-row{display:flex;align-items:center;gap:10px}
    .hbar-key{font-size:12px;color:var(--text-muted);font-weight:600;width:100px;flex-shrink:0}
    .hbar-track{flex:1;height:10px;background:var(--soil-line);border-radius:5px;overflow:hidden}
    .hbar-fill{height:100%;border-radius:5px}
    .hbar-fill.green{background:linear-gradient(90deg,#4CAF70,#6ccf8a)}
    .hbar-fill.amber{background:linear-gradient(90deg,var(--amber),#f0b050)}
    .hbar-fill.red{background:linear-gradient(90deg,var(--red-vivid),var(--red-bright))}
    .hbar-fill.blue{background:linear-gradient(90deg,#4A90D9,#7ab8f0)}
    .hbar-fill.muted{background:var(--soil-hover);border:1px solid var(--soil-line)}
    .hbar-val{font-size:12px;color:#F5F0EE;font-weight:700;width:40px;text-align:right;flex-shrink:0}

    /* SYSTEM PERFORMANCE */
    .perf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
    .perf-item{background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:16px}
    .perf-label{font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px}
    .perf-value{font-size:18px;font-weight:700;color:#F5F0EE}
    .perf-sub{font-size:11px;color:var(--text-muted);margin-top:2px}

    /* EXPORT BUTTONS */
    .export-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px}
    .export-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);font-size:13px;font-weight:700;font-family:var(--font-body);cursor:pointer;transition:0.18s;text-decoration:none}
    .export-btn:hover{background:rgba(209,61,44,0.1);border-color:var(--red-vivid);color:var(--red-pale)}
    .export-btn.print-btn{background:var(--red-vivid);border-color:var(--red-vivid);color:#fff}
    .export-btn.print-btn:hover{background:var(--red-bright)}

    .empty-note{font-size:13px;color:var(--text-muted);text-align:center;padding:32px}

    /* Print styles */
    @media print{
      .navbar,.sidebar,.export-row,.theme-btn,.notif-btn-nav,.admin-pill,.profile-wrap{display:none!important}
      .content-layout{grid-template-columns:1fr!important}
      body{background:#fff!important;color:#000!important}
      .report-card,.stat-card,.perf-item{border:1px solid #ccc!important;background:#fff!important;break-inside:avoid}
      .stat-num,.perf-value{color:#000!important}
      .stat-lbl,.perf-label,.bar-lbl{color:#555!important}
      .bar-fill-v{background:#333!important}
    }

    /* Light theme */
    body.light{--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A}
    body.light .navbar{background:rgba(255,253,252,0.98);border-bottom-color:#D4B0AB}
    body.light .report-card,.body.light .stat-card{background:#fff;border-color:#E0CECA}

    /* Period pills */
    .period-pills { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
    .period-pill { padding:6px 14px; border-radius:100px; font-size:12px; font-weight:600; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); cursor:pointer; transition:all 0.18s; }
    .period-pill:hover { border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .period-pill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.45); color:var(--red-pale); }
    body.light .period-pill { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .period-pill.active { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.3); color:var(--red-mid,#B83525); }

    ::-webkit-scrollbar{width:5px}
    ::-webkit-scrollbar-track{background:var(--soil-dark)}
    ::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:3px}
    @media(max-width:760px){.stats-grid{grid-template-columns:1fr 1fr}.perf-grid{grid-template-columns:1fr 1fr}.nav-links{display:none}.page-shell{padding:0 16px 60px}.bar-chart{height:100px}}
  </style>
</head>
<body>

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
      <a class="nav-link" href="admin_companies.php"><i class="fas fa-building"></i> Companies</a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs</a>
      <a class="nav-link" href="admin_recruiters.php"><i class="fas fa-user-tie"></i> Recruiters</a>
      <a class="nav-link active" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    </div>
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <a class="notif-btn-nav" href="admin_notifications.php" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if($unreadNotifs > 0): ?><span class="badge"><?php echo $unreadNotifs; ?></span><?php endif; ?>
      </a>
      <span class="admin-pill"><i class="fas fa-shield-alt"></i> Admin</span>
      <div class="profile-wrap">
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
          <a class="pd-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
          <a class="pd-item" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
          <div class="pd-divider"></div>
          <a class="pd-item danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="page-shell">
  <div class="page-header">
    <div class="page-title">Reports &amp; <span>Analytics</span></div>
    <div class="page-sub">Platform statistics, trends, and exportable data.</div>
  </div>

  <div class="content-layout">

    <!-- MAIN -->
    <main>

      <!-- EXPORT BUTTONS -->
      <div class="export-row" id="exportRow">
        <button class="export-btn print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
        <a class="export-btn" href="admin_reports.php?export=users"><i class="fas fa-download"></i> Export Users CSV</a>
        <a class="export-btn" href="admin_reports.php?export=jobs"><i class="fas fa-download"></i> Export Jobs CSV</a>
        <a class="export-btn" href="admin_reports.php?export=applications"><i class="fas fa-download"></i> Export Applications CSV</a>
        <a class="export-btn" href="admin_reports.php?export=hirings"><i class="fas fa-handshake"></i> Export Hirings CSV</a>
      </div>

      <!-- SUMMARY STATS -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon b"><i class="fas fa-users"></i></div></div>
          <div class="stat-num"><?php echo number_format($totalUsers); ?></div>
          <div class="stat-lbl">Total Users</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon a"><i class="fas fa-building"></i></div></div>
          <div class="stat-num"><?php echo number_format($totalEmployers); ?></div>
          <div class="stat-lbl">Employers</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon r"><i class="fas fa-briefcase"></i></div></div>
          <div class="stat-num"><?php echo number_format($activeJobs); ?></div>
          <div class="stat-lbl">Active Jobs</div>
        </div>
        <div class="stat-card">
          <div class="stat-top"><div class="stat-icon g"><i class="fas fa-paper-plane"></i></div></div>
          <div class="stat-num"><?php echo number_format($totalApps); ?></div>
          <div class="stat-lbl">Applications</div>
        </div>
      </div>

      <!-- Period Filter -->
      <div class="period-pills">
        <span class="period-pill<?= $periodKey==='1'?' active':'' ?>" onclick="window.location.search='?period=1'">Last 1 month</span>
        <span class="period-pill<?= $periodKey==='3'?' active':'' ?>" onclick="window.location.search='?period=3'">Last 3 months</span>
        <span class="period-pill<?= $periodKey==='6'?' active':'' ?>" onclick="window.location.search='?period=6'">Last 6 months</span>
        <span class="period-pill<?= $periodKey==='12'?' active':'' ?>" onclick="window.location.search='?period=12'">Last 12 months</span>
      </div>

      <!-- 1. USER GROWTH -->
      <div class="sec-section">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-user-plus"></i> User Growth</div>
          <span style="font-size:12px;color:var(--text-muted);">Last <?= $periodMonths ?> month<?= $periodMonths>1?'s':'' ?></span>
        </div>
        <div class="report-card">
          <?php if(empty($userGrowth)): ?>
            <div class="empty-note">No user growth data available yet.</div>
          <?php else: ?>
          <div class="bar-chart">
            <?php foreach($months6 as $m):
              $cnt = $userGrowth[$m] ?? 0;
              $pct = $maxUserGrowth > 0 ? max(4, round($cnt / $maxUserGrowth * 150)) : 4;
              $label = date('M', strtotime($m . '-01'));
            ?>
            <div class="bar-col">
              <div class="bar-val"><?php echo $cnt; ?></div>
              <div class="bar-fill-v blue" style="height:<?php echo $pct; ?>px;width:100%"></div>
              <div class="bar-lbl"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">
            Total new users in period: <strong style="color:var(--text-light);"><?php echo array_sum($userGrowth); ?></strong>
            &nbsp;·&nbsp; Seekers: <strong style="color:var(--text-light);"><?php echo number_format($totalSeekers); ?></strong>
            &nbsp;·&nbsp; Employers: <strong style="color:var(--text-light);"><?php echo number_format($totalEmployers); ?></strong>
            &nbsp;·&nbsp; Recruiters: <strong style="color:var(--text-light);"><?php echo number_format($totalRecruiters); ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 2. JOB POST TRENDS -->
      <div class="sec-section">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-briefcase"></i> Job Post Trends</div>
          <span style="font-size:12px;color:var(--text-muted);">Last <?= $periodMonths ?> month<?= $periodMonths>1?'s':'' ?></span>
        </div>
        <div class="report-card">
          <?php if(empty($jobTrends)): ?>
            <div class="empty-note">No job post data available yet.</div>
          <?php else: ?>
          <div class="bar-chart">
            <?php foreach($months6 as $m):
              $cnt = $jobTrends[$m] ?? 0;
              $pct = $maxJobTrend > 0 ? max(4, round($cnt / $maxJobTrend * 150)) : 4;
              $label = date('M', strtotime($m . '-01'));
            ?>
            <div class="bar-col">
              <div class="bar-val"><?php echo $cnt; ?></div>
              <div class="bar-fill-v amber" style="height:<?php echo $pct; ?>px;width:100%"></div>
              <div class="bar-lbl"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">
            Total posts in period: <strong style="color:var(--text-light);"><?php echo array_sum($jobTrends); ?></strong>
            &nbsp;·&nbsp; Active now: <strong style="color:var(--text-light);"><?php echo number_format($activeJobs); ?></strong>
            &nbsp;·&nbsp; Pending approval: <strong style="color:var(--text-light);"><?php echo number_format($pendingJobs); ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 3. APPLICATION STATUS BREAKDOWN -->
      <div class="sec-section">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-paper-plane"></i> Application Rates</div>
          <span style="font-size:12px;color:var(--text-muted);">By status</span>
        </div>
        <div class="report-card">
          <?php if(empty($appStatuses)): ?>
            <div class="empty-note">No application data available yet.</div>
          <?php else:
            $statusColors = ['Pending'=>'amber','Reviewed'=>'blue','Shortlisted'=>'blue','Interview'=>'blue','Accepted'=>'green','Rejected'=>'red','Withdrawn'=>'muted'];
          ?>
          <div class="hbar-list">
            <?php foreach($appStatuses as $st => $cnt):
              $pct = $maxApps > 0 ? max(2, round($cnt / $maxApps * 100)) : 2;
              $color = $statusColors[$st] ?? 'muted';
            ?>
            <div class="hbar-row">
              <div class="hbar-key"><?php echo htmlspecialchars($st, ENT_QUOTES); ?></div>
              <div class="hbar-track"><div class="hbar-fill <?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div></div>
              <div class="hbar-val"><?php echo number_format($cnt); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:16px;">
            Total applications: <strong style="color:var(--text-light);"><?php echo number_format($totalApps); ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. HIRING RATES -->
      <div class="sec-section">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-handshake"></i> Hiring Rates</div>
          <span style="font-size:12px;color:var(--text-muted);">Accepted applications per month</span>
        </div>
        <div class="report-card">
          <?php if(empty($hiringTrends)): ?>
            <div class="empty-note">No hiring data available yet.</div>
          <?php else: ?>
          <div class="bar-chart">
            <?php foreach($months6 as $m):
              $cnt = $hiringTrends[$m] ?? 0;
              $pct = $maxHiring > 0 ? max(4, round($cnt / $maxHiring * 150)) : 4;
              $label = date('M', strtotime($m . '-01'));
            ?>
            <div class="bar-col">
              <div class="bar-val"><?php echo $cnt; ?></div>
              <div class="bar-fill-v green" style="height:<?php echo $pct; ?>px;width:100%"></div>
              <div class="bar-lbl"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">
            Total hires in period: <strong style="color:var(--text-light);"><?php echo array_sum($hiringTrends); ?></strong>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 5. SYSTEM PERFORMANCE -->
      <div class="sec-section">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-server"></i> System Performance</div>
        </div>
        <div class="report-card">
          <div class="perf-grid">
            <div class="perf-item">
              <div class="perf-label">PHP Version</div>
              <div class="perf-value"><?php echo htmlspecialchars($phpVer, ENT_QUOTES); ?></div>
              <div class="perf-sub">Runtime</div>
            </div>
            <div class="perf-item">
              <div class="perf-label">MySQL Version</div>
              <div class="perf-value"><?php echo htmlspecialchars($mysqlVer ?: 'N/A', ENT_QUOTES); ?></div>
              <div class="perf-sub">Database engine</div>
            </div>
            <div class="perf-item">
              <div class="perf-label">Memory Usage</div>
              <div class="perf-value"><?php echo $memUsage; ?> MB</div>
              <div class="perf-sub">Peak: <?php echo $memPeak; ?> MB</div>
            </div>
            <div class="perf-item">
              <div class="perf-label">Disk Free</div>
              <div class="perf-value"><?php echo $diskFree ?: 'N/A'; ?></div>
              <div class="perf-sub">Server disk space</div>
            </div>
            <div class="perf-item">
              <div class="perf-label">Total Rows</div>
              <div class="perf-value"><?php echo number_format($totalUsers + $totalJobs + $totalApps); ?></div>
              <div class="perf-sub">Users + Jobs + Applications</div>
            </div>
            <div class="perf-item">
              <div class="perf-label">Server Time</div>
              <div class="perf-value" style="font-size:14px;"><?php echo date('M d, Y H:i'); ?></div>
              <div class="perf-sub">PHP server timezone</div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<footer style="border-top:1px solid var(--soil-line);padding:24px;max-width:1380px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;color:var(--text-muted);font-size:12px;flex-wrap:wrap;gap:12px">
  <div style="font-family:var(--font-display);font-weight:700;color:var(--red-pale);font-size:16px">AntCareers</div>
  <div>© 2025 AntCareers — Admin Panel</div>
  <a href="../index.php" style="color:inherit">← Public Site</a>
</footer>

<script>
  // Theme
  function setTheme(t){
    document.body.classList.toggle('light',t==='light');
    document.getElementById('themeToggle').querySelector('i').className=t==='light'?'fas fa-sun':'fas fa-moon';
    localStorage.setItem('ac-theme',t);
  }
  document.getElementById('themeToggle').addEventListener('click',()=>setTheme(document.body.classList.contains('light')?'dark':'light'));
  setTheme(localStorage.getItem('ac-theme')||'dark');

  // Profile dropdown
  document.getElementById('profileToggle').addEventListener('click',e=>{
    e.stopPropagation();
    document.getElementById('profileDropdown').classList.toggle('open');
  });
  document.addEventListener('click',e=>{
    if(!document.getElementById('profileToggle').contains(e.target))
      document.getElementById('profileDropdown').classList.remove('open');
  });
</script>
<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>
</body>
</html>
