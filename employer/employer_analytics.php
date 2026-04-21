<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'analytics';
$employerId  = $user['id'];
$db = getDB();

// ── Time-period filter ──
$validPeriods = ['7' => 7, '30' => 30, '90' => 90, '365' => 365];
$periodKey = (string)($_GET['period'] ?? '30');
if (!isset($validPeriods[$periodKey])) $periodKey = '30';
$periodDays = $validPeriods[$periodKey];
$periodInterval = "INTERVAL {$periodDays} DAY";

// ── Helper ──
$countVal = static function (string $sql, array $params = []) use ($db): int {
  try {
    $s = $db->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
  } catch (PDOException $e) {
    error_log('[AntCareers] analytics: ' . $e->getMessage());
    return 0;
  }
};

// ── Stat cards ──
$activeJobs    = $countVal("SELECT COUNT(*) FROM jobs WHERE employer_id=:eid AND status='Active' AND (deadline IS NULL OR deadline >= CURDATE())", [':eid'=>$employerId]);
$totalApps     = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$weekApps      = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.applied_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", [':eid'=>$employerId]);
$pendingApps   = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status='Pending' AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$shortlisted   = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status='Shortlisted' AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$interviewed   = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status='Interviewed' AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$hired         = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status IN ('Offered','Accepted') AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$rejected      = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status IN ('Rejected','Declined') AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$reviewed      = $countVal("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=:eid AND a.status='Reviewed' AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$monthJobs     = $countVal("SELECT COUNT(*) FROM jobs WHERE employer_id=:eid AND created_at >= DATE_SUB(CURDATE(), {$periodInterval})", [':eid'=>$employerId]);
$closingSoon   = $countVal("SELECT COUNT(*) FROM jobs WHERE employer_id=:eid AND status='Active' AND deadline IS NOT NULL AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)", [':eid'=>$employerId]);
$shortlistRate = $totalApps > 0 ? round($shortlisted / $totalApps * 100) : 0;
$hireRate      = $totalApps > 0 ? round($hired / $totalApps * 100, 1) : 0;

// ── Per-job stats (for bar chart, table, funnel) ──
$jobStats = [];
try {
  $stmt = $db->prepare(
    "SELECT j.id, j.title, j.status, j.created_at, j.deadline,
            COUNT(a.id) AS applicants,
            SUM(CASE WHEN a.status='Shortlisted' THEN 1 ELSE 0 END) AS shortlisted,
            SUM(CASE WHEN a.status='Interviewed' THEN 1 ELSE 0 END) AS interviewed,
            SUM(CASE WHEN a.status IN ('Offered','Accepted') THEN 1 ELSE 0 END) AS hired
     FROM jobs j
     LEFT JOIN applications a ON a.job_id = j.id
     WHERE j.employer_id = :eid
     GROUP BY j.id
     ORDER BY applicants DESC"
  );
  $stmt->execute([':eid' => $employerId]);
  $jobStats = $stmt->fetchAll();
} catch (Throwable) {}

// ── Applications over time ──
$appTimeline = [];
try {
  $stmt = $db->prepare(
    "SELECT DATE(a.applied_at) AS day, COUNT(*) AS cnt
     FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE j.employer_id = :eid AND a.applied_at >= DATE_SUB(CURDATE(), {$periodInterval})
     GROUP BY DATE(a.applied_at)
     ORDER BY day"
  );
  $stmt->execute([':eid' => $employerId]);
  $appTimeline = $stmt->fetchAll();
} catch (Throwable) {}

$timelineDays = []; $timelineCounts = [];
foreach ($appTimeline as $row) {
  $timelineDays[]   = date('M j', strtotime($row['day']));
  $timelineCounts[] = (int)$row['cnt'];
}

// ── Status breakdown for donut ──
$statusBreakdown = [
  'Pending'     => $pendingApps,
  'Reviewed'    => $reviewed,
  'Shortlisted' => $shortlisted,
  'Hired'       => $hired,
  'Rejected'    => $rejected,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    /* ── DESIGN TOKENS — identical to all employer pages ── */
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }

    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    /* ── TUNNEL BG — same decorative SVG as other pages ── */
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── NAVBAR ── */
    .navbar { position:sticky; top:0; z-index:400; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid rgba(209,61,44,0.35); box-shadow:0 1px 0 rgba(209,61,44,0.06), 0 4px 24px rgba(0,0,0,0.5); }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:var(--text-light); white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; letter-spacing:0.01em; }
    .nav-link:hover { color:var(--text-light); background:var(--soil-hover); }
    .nav-link.active { color:var(--text-light); background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn{ width:36px;height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }
    .btn-nav-red { padding:7px 16px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; letter-spacing:0.02em; box-shadow:0 2px 8px rgba(209,61,44,0.3); text-decoration:none; display:flex; align-items:center; gap:7px; }
    .btn-nav-red:hover { background:var(--red-bright); transform:translateY(-1px); box-shadow:0 4px 14px rgba(209,61,44,0.45); }
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; min-width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); overflow:hidden; padding:0 3px; }
    .profile-wrap { position:relative; }
    .profile-btn { display:flex; align-items:center; gap:9px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:6px 12px 6px 8px; cursor:pointer; transition:0.2s; flex-shrink:0; }
    .profile-btn:hover { background:var(--soil-card); }
    .profile-avatar { width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg, var(--amber), #8a5010); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .profile-avatar img { width:100%; height:100%; object-fit:cover; }
    .profile-name { font-size:13px; font-weight:600; color:var(--text-light); }
    .profile-role { font-size:10px; color:var(--amber); margin-top:1px; letter-spacing:0.02em; font-weight:600; }
    .profile-chevron { font-size:9px; color:var(--text-muted); margin-left:2px; }
    .profile-dropdown { position:absolute; top:calc(100% + 8px); right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:6px; min-width:200px; opacity:0; visibility:hidden; transform:translateY(-6px); transition:all 0.18s ease; z-index:300; box-shadow:0 20px 40px rgba(0,0,0,0.5); }
    .profile-dropdown.open { opacity:1; visibility:visible; transform:translateY(0); }
    .profile-dropdown-head { padding:12px 14px 10px; border-bottom:1px solid var(--soil-line); margin-bottom:4px; }
    .pdh-name { font-size:14px; font-weight:700; color:var(--text-light); }
    .pdh-sub { font-size:11px; color:var(--amber); margin-top:2px; font-weight:600; }
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:var(--text-light); }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }
    .hamburger { display:none; width:36px;height:36px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); text-decoration:none; }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:var(--text-light); }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 80px; }

    /* ── PAGE HEADER ── */
    .page-header { padding:32px 0 28px; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--text-light); margin-bottom:6px; }
    .page-title span { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); }

    /* Breadcrumb */
    .breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); margin-bottom:10px; }
    .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:0.15s; }
    .breadcrumb a:hover { color:var(--red-pale); }
    .breadcrumb i { font-size:9px; }

    /* Period selector pills */
    .period-pills { display:flex; gap:6px; flex-wrap:wrap; margin-top:16px; }
    .period-pill { padding:6px 14px; border-radius:100px; font-size:12px; font-weight:600; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--text-muted); cursor:pointer; transition:all 0.18s; }
    .period-pill:hover { border-color:rgba(209,61,44,0.4); color:var(--red-pale); }
    .period-pill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.45); color:var(--red-pale); }

    /* ── STAT CARDS ── */
    .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:32px; }
    .stat-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px 20px; transition:all 0.22s; position:relative; overflow:hidden; }
    .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:14px 14px 0 0; }
    .stat-card.red::before   { background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .stat-card.amber::before { background:linear-gradient(90deg,#b8620a,var(--amber)); }
    .stat-card.blue::before  { background:linear-gradient(90deg,#3a6ea8,#7ab8f0); }
    .stat-card.green::before { background:linear-gradient(90deg,#2d8c52,#6ccf8a); }
    .stat-card:hover { border-color:rgba(209,61,44,0.35); transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,0.3); }
    .sc-row { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; }
    .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
    .sc-icon.red   { background:rgba(209,61,44,.12);  color:var(--red-pale); }
    .sc-icon.amber { background:rgba(212,148,58,.12); color:var(--amber); }
    .sc-icon.blue  { background:rgba(74,144,217,.1);  color:#7ab8f0; }
    .sc-icon.green { background:rgba(76,175,112,.1);  color:#6ccf8a; }
    .sc-trend { display:flex; align-items:center; gap:4px; font-size:11px; font-weight:700; padding:3px 8px; border-radius:20px; }
    .sc-trend.up   { color:#6ccf8a; background:rgba(76,175,112,.1); }
    .sc-trend.down { color:#E05555; background:rgba(224,85,85,.1); }
    .sc-num { font-family:var(--font-display); font-size:34px; font-weight:700; color:var(--text-light); line-height:1; margin-bottom:6px; }
    .sc-label { font-size:11px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.06em; }
    .sc-sub { font-size:12px; color:var(--text-muted); margin-top:8px; padding-top:12px; border-top:1px solid var(--soil-line); }
    .sc-sub strong { color:var(--text-mid); }

    /* ── CHARTS GRID ── */
    .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }
    .chart-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:24px; }
    .chart-card.wide { grid-column:1 / -1; }
    .chart-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
    .chart-title { font-family:var(--font-display); font-size:16px; font-weight:700; color:var(--text-light); margin-bottom:3px; }
    .chart-sub { font-size:12px; color:var(--text-muted); }
    .chart-legend { display:flex; gap:14px; flex-wrap:wrap; }
    .legend-item { display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:var(--text-muted); }
    .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .chart-canvas-wrap { position:relative; }

    /* ── BOTTOM ROW: funnel + top jobs table ── */
    .bottom-grid { display:grid; grid-template-columns:340px 1fr; gap:20px; margin-bottom:28px; }

    /* Funnel card */
    .funnel-step { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
    .funnel-step:last-child { border-bottom:none; }
    .funnel-bar-wrap { flex:1; height:8px; background:var(--soil-hover); border-radius:4px; overflow:hidden; }
    .funnel-bar { height:100%; border-radius:4px; transition:width 1s cubic-bezier(0.4,0,0.2,1); }
    .funnel-label { font-size:12px; font-weight:600; color:var(--text-mid); width:100px; flex-shrink:0; }
    .funnel-count { font-size:13px; font-weight:700; color:var(--text-light); width:38px; text-align:right; flex-shrink:0; }
    .funnel-pct { font-size:11px; color:var(--text-muted); width:36px; text-align:right; flex-shrink:0; }

    /* Top jobs table */
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:13px; }
    thead tr { border-bottom:1px solid var(--soil-line); }
    th { text-align:left; padding:10px 14px; font-size:10px; font-weight:700; color:var(--text-muted); letter-spacing:.07em; text-transform:uppercase; white-space:nowrap; }
    td { padding:12px 14px; border-bottom:1px solid rgba(53,46,46,0.5); color:var(--text-mid); vertical-align:middle; }
    tbody tr:last-child td { border-bottom:none; }
    tbody tr:hover td { background:var(--soil-hover); }
    .td-title { font-weight:700; color:var(--text-light); font-size:13px; }
    .td-badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:20px; font-size:10px; font-weight:700; letter-spacing:0.04em; }
    .td-badge.open   { background:rgba(76,175,112,.1);  color:#6ccf8a;  border:1px solid rgba(76,175,112,.2); }
    .td-badge.closed { background:rgba(148,124,122,.1); color:var(--text-muted); border:1px solid var(--soil-line); }
    .td-badge.paused { background:rgba(212,148,58,.1);  color:var(--amber); border:1px solid rgba(212,148,58,.2); }
    .mini-bar-wrap { width:80px; height:6px; background:var(--soil-hover); border-radius:3px; overflow:hidden; display:inline-block; vertical-align:middle; margin-left:6px; }
    .mini-bar { height:100%; border-radius:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }

    /* ── FOOTER ── */
    .footer { border-top:1px solid var(--soil-line); padding:28px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:12px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:16px; }
    .footnote { font-size:11px; color:var(--text-muted); font-style:italic; margin-top:4px; }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.06s; }
    .anim-d2 { animation-delay:0.12s; }
    .anim-d3 { animation-delay:0.18s; }
    .anim-d4 { animation-delay:0.24s; }
    .anim-d5 { animation-delay:0.30s; }

    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    /* ── LIGHT THEME — mirrors all other employer pages exactly ── */
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
    body.light .theme-btn, body.light .notif-btn-nav, body.light .profile-btn, body.light .hamburger { background:#F5EDEB; border-color:#D4B0AB; }
    body.light .profile-name { color:#1A0A09; }
    body.light .stat-card, body.light .chart-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sc-num, body.light .page-title, body.light .chart-title { color:#1A0A09; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .period-pill { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .period-pill.active { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.3); color:var(--red-mid); }
    body.light td { color:#4A2828; }
    body.light .td-title { color:#1A0A09; }
    body.light tbody tr:hover td { background:#FEF0EE; }
    body.light .funnel-bar-wrap { background:#F5EEEC; }
    body.light .sc-sub { border-color:#E0CECA; }
    /* ── RESPONSIVE ── */
    @media(max-width:1060px) {
      .stat-grid { grid-template-columns:repeat(2,1fr); }
      .charts-grid { grid-template-columns:1fr; }
      .charts-grid .chart-card.wide { grid-column:1; }
      .bottom-grid { grid-template-columns:1fr; }
    }
    @media(max-width:760px) {
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.main-content{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .nav-links { display:none; }
      .hamburger { display:flex; }
      .page-shell { padding:0 16px 60px; }
      .nav-inner { padding:0 10px; }
      .profile-name,.profile-role { display:none; }
      .profile-btn { padding:6px 8px; }
      .stat-grid { grid-template-columns:1fr 1fr; gap:10px; }
      .sc-num { font-size:26px; }
      .bottom-grid { grid-template-columns:1fr; }
      .chart-card { padding:16px; overflow:hidden; }
      .funnel-label { width:75px; }
      .funnel-count { width:28px; }
      .funnel-pct { width:30px; }
      .table-wrap { overflow-x:auto; -webkit-overflow-scrolling:touch; }
      .footer { flex-direction:column; text-align:center; padding:20px 16px; }
    }
    @media(max-width:480px) {
      .stat-grid { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>
<!-- ── BACKGROUND DECORATION ── -->
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

<!-- ── NAVBAR ── -->
<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<!-- ── PAGE CONTENT ── -->
<div class="page-shell">

  <!-- PAGE HEADER -->
  <div class="page-header anim">
    <div class="page-title">Analytics <span>Dashboard</span></div>
    <div class="page-sub">Hiring performance overview for <?php echo htmlspecialchars($companyName, ENT_QUOTES); ?></div>
    <!-- Period filter pills — functional with page reload -->
    <div class="period-pills">
      <span class="period-pill<?= $periodKey==='7'?' active':'' ?>" onclick="setPeriod('7')">Last 7 days</span>
      <span class="period-pill<?= $periodKey==='30'?' active':'' ?>" onclick="setPeriod('30')">Last 30 days</span>
      <span class="period-pill<?= $periodKey==='90'?' active':'' ?>" onclick="setPeriod('90')">Last 90 days</span>
      <span class="period-pill<?= $periodKey==='365'?' active':'' ?>" onclick="setPeriod('365')">This year</span>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid">

    <div class="stat-card red anim anim-d1">
      <div class="sc-row">
        <div class="sc-icon red"><i class="fas fa-briefcase"></i></div>
      </div>
      <div class="sc-num"><?php echo $activeJobs; ?></div>
      <div class="sc-label">Active Jobs</div>
      <div class="sc-sub"><?php echo $monthJobs; ?> posted this month &nbsp;·&nbsp; <strong><?php echo $closingSoon; ?> closing soon</strong></div>
    </div>

    <div class="stat-card amber anim anim-d2">
      <div class="sc-row">
        <div class="sc-icon amber"><i class="fas fa-users"></i></div>
      </div>
      <div class="sc-num"><?php echo $totalApps; ?></div>
      <div class="sc-label">Total Applicants</div>
      <div class="sc-sub"><strong><?php echo $weekApps; ?></strong> new this week &nbsp;·&nbsp; <?php echo $pendingApps; ?> unreviewed</div>
    </div>

    <div class="stat-card blue anim anim-d3">
      <div class="sc-row">
        <div class="sc-icon blue"><i class="fas fa-user-check"></i></div>
      </div>
      <div class="sc-num"><?php echo $shortlisted; ?></div>
      <div class="sc-label">Shortlisted</div>
      <div class="sc-sub"><?php echo $shortlistRate; ?>% shortlist rate &nbsp;·&nbsp; <strong><?php echo $interviewed; ?> interviewed</strong></div>
    </div>

    <div class="stat-card green anim anim-d4">
      <div class="sc-row">
        <div class="sc-icon green"><i class="fas fa-handshake"></i></div>
      </div>
      <div class="sc-num"><?php echo $hired; ?></div>
      <div class="sc-label">Hired / Offered</div>
      <div class="sc-sub"><?php echo $hireRate; ?>% hire rate</div>
    </div>

  </div><!-- /stat-grid -->

  <!-- CHARTS ROW -->
  <div class="charts-grid anim anim-d5">

    <!-- Bar Chart: Applicants per Job -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <div class="chart-title">Applicants per Job Post</div>
          <div class="chart-sub">Number of applicants received per active vacancy</div>
        </div>
        <div class="chart-legend">
          <div class="legend-item"><div class="legend-dot" style="background:var(--red-vivid)"></div> Applicants</div>
          <div class="legend-item"><div class="legend-dot" style="background:var(--amber)"></div> Shortlisted</div>
        </div>
      </div>
      <div class="chart-canvas-wrap">
        <canvas id="barChart" height="240"></canvas>
      </div>
    </div>

    <!-- Line Chart: Applications Over Time -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <div class="chart-title">Applications Over Time</div>
          <div class="chart-sub">Daily application volume for the last 30 days</div>
        </div>
        <div class="chart-legend">
          <div class="legend-item"><div class="legend-dot" style="background:var(--red-bright)"></div> Applications</div>
          <div class="legend-item"><div class="legend-dot" style="background:#7ab8f0"></div> Views</div>
        </div>
      </div>
      <div class="chart-canvas-wrap">
        <canvas id="lineChart" height="240"></canvas>
      </div>
    </div>

    <!-- Donut Chart: Application Status Breakdown — full width -->
    <div class="chart-card wide">
      <div class="chart-header">
        <div>
          <div class="chart-title">Application Status Breakdown</div>
          <div class="chart-sub">Distribution of all <?php echo $totalApps; ?> applications across review stages</div>
        </div>
        <div class="chart-legend">
          <div class="legend-item"><div class="legend-dot" style="background:#7ab8f0"></div> New / Unreviewed</div>
          <div class="legend-item"><div class="legend-dot" style="background:var(--amber)"></div> Under Review</div>
          <div class="legend-item"><div class="legend-dot" style="background:var(--red-bright)"></div> Shortlisted</div>
          <div class="legend-item"><div class="legend-dot" style="background:#6ccf8a"></div> Hired</div>
          <div class="legend-item"><div class="legend-dot" style="background:var(--soil-line)"></div> Rejected</div>
        </div>
      </div>
      <div class="chart-canvas-wrap" style="display:flex;justify-content:center;">
        <canvas id="donutChart" style="max-width:300px;max-height:300px;"></canvas>
      </div>
    </div>

  </div><!-- /charts-grid -->

  <!-- BOTTOM ROW: Funnel + Top Jobs Table -->
  <div class="bottom-grid">

    <!-- Recruitment funnel -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <div class="chart-title">Recruitment Funnel</div>
          <div class="chart-sub">Conversion at each hiring stage</div>
        </div>
      </div>
      <div id="funnelWrap"></div>
    </div>

    <!-- Top performing jobs table -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <div class="chart-title">Top Job Posts</div>
          <div class="chart-sub">Sorted by total applicants received</div>
        </div>
      </div>
      <div class="table-wrap">
        <table id="jobTable">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Status</th>
              <th>Applicants</th>
              <th>Shortlisted</th>
              <th>Hired</th>
              <th>Posted</th>
            </tr>
          </thead>
          <tbody id="jobTableBody"></tbody>
        </table>
      </div>
    </div>

  </div><!-- /bottom-grid -->

</div><!-- /page-shell -->

<footer class="footer">
  <div>
    <div class="footer-logo">AntCareers</div>
  </div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<script>
/* ── REAL DATA (from PHP) ── */
const DATA = {
  jobs: <?php
    $jsJobs = [];
    foreach ($jobStats as $j) {
      $status = 'open';
      if (strtolower($j['status']) === 'closed') $status = 'closed';
      elseif (strtolower($j['status']) === 'draft') $status = 'paused';
      $jsJobs[] = [
        'title'       => $j['title'],
        'applicants'  => (int)$j['applicants'],
        'shortlisted' => (int)$j['shortlisted'],
        'interviewed' => (int)$j['interviewed'],
        'hired'       => (int)$j['hired'],
        'status'      => $status,
        'posted'      => date('M j', strtotime($j['created_at'])),
      ];
    }
    echo json_encode($jsJobs, JSON_HEX_TAG | JSON_HEX_APOS);
  ?>,

  days: <?php echo json_encode($timelineDays); ?>,
  applications: <?php echo json_encode($timelineCounts); ?>,

  funnel: [
    { label:'Applied',       count:<?php echo $totalApps; ?>,    color:'var(--amber)' },
    { label:'Reviewed',      count:<?php echo $reviewed; ?>,     color:'var(--red-pale)' },
    { label:'Shortlisted',   count:<?php echo $shortlisted; ?>,  color:'var(--red-vivid)' },
    { label:'Interviewed',   count:<?php echo $interviewed; ?>,  color:'#a855f7' },
    { label:'Hired',         count:<?php echo $hired; ?>,        color:'#6ccf8a' },
  ],

  statusLabels: <?php echo json_encode(array_keys($statusBreakdown)); ?>,
  statusData:   <?php echo json_encode(array_values($statusBreakdown)); ?>,
  statusColors: ['#7ab8f0','#D4943A','#E85540','#6ccf8a','#352E2E'],
  totalApps: <?php echo $totalApps; ?>,
};

/* ─────────────────────────────────────────────
   CHART.JS GLOBAL DEFAULTS
   These match the dark theme: no gridlines on
   x-axis, subtle y-axis lines, custom font.
───────────────────────────────────────────── */
Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
Chart.defaults.color = '#927C7A';

// Shared grid/tick style used across charts
const gridStyle = {
  color: 'rgba(53,46,46,0.6)',
  drawBorder: false,
};
const tickStyle = { color:'#927C7A', font:{ size:11, weight:'600' } };

/* ─────────────────────────────────────────────
   BAR CHART — Applicants per Job
   Two datasets: applicants (red) and shortlisted
   (amber). Grouped bars side by side.
───────────────────────────────────────────── */
new Chart(document.getElementById('barChart'), {
  type: 'bar',
  data: {
    labels: DATA.jobs.map(j => j.title.length > 16 ? j.title.slice(0,15)+'…' : j.title),
    datasets: [
      {
        label: 'Applicants',
        data: DATA.jobs.map(j => j.applicants),
        backgroundColor: 'rgba(209,61,44,0.75)',
        hoverBackgroundColor: '#E85540',
        borderRadius: 5,
        borderSkipped: false,
      },
      {
        label: 'Shortlisted',
        data: DATA.jobs.map(j => j.shortlisted),
        backgroundColor: 'rgba(212,148,58,0.65)',
        hoverBackgroundColor: '#D4943A',
        borderRadius: 5,
        borderSkipped: false,
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: { display: false }, // We use our own HTML legend above
      tooltip: {
        backgroundColor: '#1C1818',
        borderColor: '#352E2E',
        borderWidth: 1,
        titleColor: '#F5F0EE',
        bodyColor: '#D0BCBA',
        padding: 10,
      }
    },
    scales: {
      x: { grid: { display:false }, ticks: tickStyle },
      y: { grid: gridStyle, ticks: { ...tickStyle, stepSize:10 }, beginAtZero:true }
    }
  }
});

/* ─────────────────────────────────────────────
   LINE CHART — Applications Over Time
   Two lines: applications (red) and views (blue).
   Uses tension:0.4 for smooth curves. Area fill
   under the applications line for visual weight.
───────────────────────────────────────────── */
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels: DATA.days,
    datasets: [
      {
        label: 'Applications',
        data: DATA.applications,
        borderColor: '#E85540',
        backgroundColor: 'rgba(209,61,44,0.08)',
        pointBackgroundColor: '#E85540',
        pointRadius: 4,
        pointHoverRadius: 6,
        borderWidth: 2.5,
        tension: 0.4,
        fill: true,
      },
      {
        label: 'Views',
        data: DATA.applications.map(v => Math.round(v * (2.5 + Math.random()))),
        borderColor: '#7ab8f0',
        backgroundColor: 'rgba(122,184,240,0.04)',
        pointBackgroundColor: '#7ab8f0',
        pointRadius: 3,
        pointHoverRadius: 5,
        borderWidth: 2,
        tension: 0.4,
        fill: false,
        borderDash: [4,3], // dashed so it doesn't compete with applications
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: { display:false },
      tooltip: {
        backgroundColor: '#1C1818',
        borderColor: '#352E2E',
        borderWidth: 1,
        titleColor: '#F5F0EE',
        bodyColor: '#D0BCBA',
        padding: 10,
        mode: 'index',    // show both values in one tooltip
        intersect: false,
      }
    },
    scales: {
      x: { grid:{ display:false }, ticks: tickStyle },
      y: { grid: gridStyle, ticks: tickStyle, beginAtZero:true }
    }
  }
});

/* ─────────────────────────────────────────────
   DONUT CHART — Application Status Breakdown
   A doughnut with a custom center label showing
   the total, rendered using Chart.js plugins API.
───────────────────────────────────────────── */
// Custom plugin: draws text in the center of the donut
const centerTextPlugin = {
  id: 'centerText',
  afterDraw(chart) {
    if (chart.config.type !== 'doughnut') return;
    const { ctx, chartArea: { left, top, right, bottom } } = chart;
    const cx = (left + right) / 2;
    const cy = (top + bottom) / 2;
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const isLight = document.body.classList.contains('light');
    ctx.font = "700 28px 'Playfair Display', Georgia, serif";
    ctx.fillStyle = isLight ? '#1A0A09' : '#F5F0EE';
    ctx.fillText(DATA.totalApps.toString(), cx, cy - 10);
    ctx.font = "600 11px 'Plus Jakarta Sans', sans-serif";
    ctx.fillStyle = isLight ? '#7A5555' : '#927C7A';
    ctx.fillText('TOTAL', cx, cy + 14);
    ctx.restore();
  }
};
Chart.register(centerTextPlugin);

new Chart(document.getElementById('donutChart'), {
  type: 'doughnut',
  data: {
    labels: DATA.statusLabels,
    datasets: [{
      data: DATA.statusData,
      backgroundColor: DATA.statusColors,
      hoverBackgroundColor: DATA.statusColors.map(c => c),
      borderColor: '#0A0909',
      borderWidth: 3,
      hoverOffset: 8,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    cutout: '68%', // thickness of the ring
    plugins: {
      legend: { display:false },
      tooltip: {
        backgroundColor: '#1C1818',
        borderColor: '#352E2E',
        borderWidth: 1,
        titleColor: '#F5F0EE',
        bodyColor: '#D0BCBA',
        padding: 10,
        callbacks: {
          label: ctx => ` ${ctx.label}: ${ctx.parsed} (${DATA.totalApps > 0 ? Math.round(ctx.parsed/DATA.totalApps*100) : 0}%)`
        }
      }
    }
  }
});

/* ─────────────────────────────────────────────
   RECRUITMENT FUNNEL — rendered as HTML bars
   (not a Chart.js chart — HTML gives more control
   over the staggered animation effect)
───────────────────────────────────────────── */
const funnelWrap = document.getElementById('funnelWrap');
const maxCount = DATA.funnel.length > 0 ? DATA.funnel[0].count : 1;
funnelWrap.innerHTML = DATA.funnel.map(f => {
  const pct = Math.round(f.count / maxCount * 100);
  const convPct = f === DATA.funnel[0] ? '100%' : (DATA.funnel[0].count > 0 ? Math.round(f.count / DATA.funnel[0].count * 100) + '%' : '0%');
  return `
    <div class="funnel-step">
      <div class="funnel-label">${f.label}</div>
      <div class="funnel-bar-wrap">
        <div class="funnel-bar" data-w="${pct}" style="width:0%;background:${f.color}"></div>
      </div>
      <div class="funnel-count">${f.count.toLocaleString()}</div>
      <div class="funnel-pct">${convPct}</div>
    </div>`;
}).join('');

// Animate bars after paint
requestAnimationFrame(() => {
  setTimeout(() => {
    document.querySelectorAll('.funnel-bar').forEach(el => {
      el.style.width = el.dataset.w + '%';
    });
  }, 300);
});

/* ─────────────────────────────────────────────
   TOP JOBS TABLE — rendered from real data
───────────────────────────────────────────── */
const tbody = document.getElementById('jobTableBody');
const sorted = [...DATA.jobs].sort((a,b) => b.applicants - a.applicants);
const maxApplicants = sorted.length > 0 ? sorted[0].applicants : 1;
tbody.innerHTML = sorted.map(j => {
  const barW = Math.round(j.applicants / maxApplicants * 100);
  const statusMap = { open:'open', closed:'closed', paused:'paused' };
  return `<tr>
    <td class="td-title">${j.title}</td>
    <td><span class="td-badge ${statusMap[j.status]}">${j.status.charAt(0).toUpperCase()+j.status.slice(1)}</span></td>
    <td>
      ${j.applicants}
      <span class="mini-bar-wrap"><span class="mini-bar" style="width:${barW}%"></span></span>
    </td>
    <td>${j.shortlisted}</td>
    <td>${j.hired}</td>
    <td style="color:var(--text-muted);font-size:12px;">${j.posted}</td>
  </tr>`;
}).join('');

/* ─────────────────────────────────────────────
   PERIOD PILL SWITCHER
   Reloads the page with the selected period.
───────────────────────────────────────────── */
function setPeriod(period) {
  window.location.search = '?period=' + period;
}

// Theme, hamburger, profile dropdown are now handled by navbar_employer.php shared script

</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>