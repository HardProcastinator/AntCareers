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

$db = getDB();
require_once dirname(__DIR__) . '/includes/admin_notif_panel.php';
$countValue = static function (string $sql) use ($db): int {
  try {
    return (int)$db->query($sql)->fetchColumn();
  } catch (PDOException $e) {
    error_log('[AntCareers] admin dashboard stats: ' . $e->getMessage());
    return 0;
  }
};
$adminId = (int)$_SESSION['user_id'];
$adminStats = [
  'users'             => $countValue("SELECT COUNT(*) FROM users"),
  'seekers'           => $countValue("SELECT COUNT(*) FROM users WHERE LOWER(account_type) = 'seeker'"),
  'employers'         => $countValue("SELECT COUNT(*) FROM users WHERE LOWER(account_type) = 'employer'"),
  'jobs'              => $countValue("SELECT COUNT(*) FROM jobs"),
  'active_jobs'       => $countValue("SELECT COUNT(*) FROM jobs WHERE status = 'Active' AND (deadline IS NULL OR deadline >= CURDATE())"),
  'applications'      => $countValue("SELECT COUNT(*) FROM applications"),
  'pending_companies' => $countValue("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='employer' AND account_status='pending_approval'"),
  'pending_jobs'      => $countValue("SELECT COUNT(*) FROM jobs WHERE approval_status='pending' AND status='Active'"),
  'recruiters'        => $countValue("SELECT COUNT(*) FROM users WHERE LOWER(account_type)='recruiter'"),
];

// Recent activity logs — defensive (table created by migration)
$recentActivity = [];
try {
  $stmt = $db->query(
    "SELECT al.action_type, al.description, al.created_at, u.full_name
     FROM activity_logs al
     LEFT JOIN users u ON u.id = al.user_id
     ORDER BY al.created_at DESC LIMIT 8"
  );
  $recentActivity = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable) {}

// ── Recent users (latest 5) ──
$recentUsers = [];
try {
  $stmt = $db->query(
    "SELECT id, full_name, email, account_type, account_status, avatar_url, contact, company_name, created_at
     FROM users
     WHERE LOWER(account_type) != 'admin'
     ORDER BY created_at DESC LIMIT 5"
  );
  $recentUsers = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable) {}

// ── Recent jobs (latest 5) ──
$recentJobs = [];
try {
  $stmt = $db->query(
    "SELECT j.id, j.title, j.status, j.approval_status, j.job_type, j.location, j.setup,
            j.salary_min, j.salary_max, j.salary_currency, j.experience_level, j.deadline, j.created_at,
            u.company_name, u.full_name AS employer_name, u.email AS employer_email
     FROM jobs j
     LEFT JOIN users u ON u.id = j.employer_id
     ORDER BY j.created_at DESC LIMIT 5"
  );
  $recentJobs = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable) {}

// ── Analytics: weekly stats ──
$weeklyUsers = $countValue("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weeklyJobs  = $countValue("SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weeklyApps  = $countValue("SELECT COUNT(*) FROM applications WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$activeEmployers = $countValue("SELECT COUNT(DISTINCT employer_id) FROM jobs WHERE status='Active' AND (deadline IS NULL OR deadline >= CURDATE())");

// ── Master data counts ──
$mdCategories = $countValue("SELECT COUNT(DISTINCT industry) FROM jobs WHERE industry IS NOT NULL AND industry != ''");
$mdLocations  = $countValue("SELECT COUNT(DISTINCT location) FROM jobs WHERE location IS NOT NULL AND location != ''");
$mdExpLevels  = $countValue("SELECT COUNT(DISTINCT experience_level) FROM jobs WHERE experience_level IS NOT NULL");
$mdJobTypes   = $countValue("SELECT COUNT(DISTINCT job_type) FROM jobs");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

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

    /* ── TUNNEL BG ── */
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── NAVBAR ── */
    .navbar {
      position:sticky; top:0; z-index:400;
      background:rgba(10,9,9,0.97); backdrop-filter:blur(20px);
      border-bottom:1px solid rgba(209,61,44,0.35);
      box-shadow:0 1px 0 rgba(209,61,44,0.06), 0 4px 24px rgba(0,0,0,0.5);
    }
    .nav-inner { max-width:1380px; margin:0 auto; padding:0 24px; display:flex; align-items:center; height:64px; gap:0; min-width:0; }
    .logo { display:flex; align-items:center; gap:8px; text-decoration:none; margin-right:28px; flex-shrink:0; }
    .logo-icon { width:34px; height:34px; background:var(--red-vivid); border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; box-shadow:0 0 18px rgba(209,61,44,0.35); }
    .logo-icon::before { content:'🐜'; font-size:18px; filter:brightness(0) invert(1); }
    .logo-text { font-family:var(--font-display); font-weight:700; font-size:19px; color:#F5F0EE; white-space:nowrap; }
    .logo-text span { color:var(--red-bright); }
    .nav-links { display:flex; align-items:center; gap:2px; flex:1; min-width:0; }
    .nav-link { font-size:13px; font-weight:600; color:#A09090; text-decoration:none; padding:7px 11px; border-radius:6px; transition:all 0.2s; cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:5px; white-space:nowrap; letter-spacing:0.01em; }
    .nav-link:hover { color:#F5F0EE; background:var(--soil-hover); }
    .nav-link.active { color:#F5F0EE; background:var(--soil-hover); }
    .nav-right { display:flex; align-items:center; gap:10px; margin-left:auto; flex-shrink:0; }
    .theme-btn { width:34px; height:34px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:13px; flex-shrink:0; }
    .theme-btn:hover { color:var(--red-bright); border-color:var(--red-vivid); }

    /* Notification button */
    .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted); flex-shrink:0; }
    .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); }

    /* Admin tag pill */
    .admin-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; background:rgba(209,61,44,0.12); border:1px solid rgba(209,61,44,0.25); border-radius:100px; font-size:11px; font-weight:700; color:var(--red-pale); letter-spacing:0.04em; white-space:nowrap; }

    /* Profile button */
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
    .pd-item { display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:6px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .pd-item i { color:var(--text-muted); width:16px; text-align:center; font-size:12px; }
    .pd-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pd-item:hover i { color:var(--red-bright); }
    .pd-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .pd-item.danger { color:#E05555; }
    .pd-item.danger i { color:#E05555; }
    .pd-item.danger:hover { background:rgba(224,85,85,0.1); color:#FF7070; }

    /* Hamburger */
    .hamburger { display:none; width:34px; height:34px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); align-items:center; justify-content:center; cursor:pointer; font-size:14px; flex-shrink:0; margin-left:8px; }
    .mobile-menu { display:none; position:fixed; top:64px; left:0; right:0; background:rgba(10,9,9,0.97); backdrop-filter:blur(20px); border-bottom:1px solid var(--soil-line); padding:12px 20px 16px; z-index:190; flex-direction:column; gap:2px; }
    .mobile-menu.open { display:flex; }
    .mobile-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:7px; font-size:14px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:0.15s; font-family:var(--font-body); }
    .mobile-link i { color:var(--red-mid); width:16px; text-align:center; }
    .mobile-link:hover { background:var(--soil-hover); color:#F5F0EE; }
    .mobile-divider { height:1px; background:var(--soil-line); margin:6px 0; }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 80px; }

    /* ── SEARCH HEADER ── */
    .search-header { padding:32px 0 24px; }
    .search-greeting { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .search-greeting span { color:var(--red-bright); font-style:italic; }
    .search-sub { font-size:14px; color:var(--text-muted); margin-bottom:20px; }
    .search-bar { display:flex; align-items:center; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; transition:0.25s; }
    .search-bar:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12), 0 4px 20px rgba(0,0,0,0.3); }
    .search-bar .si { padding:0 16px; color:var(--text-muted); font-size:16px; flex-shrink:0; }
    .search-bar input { flex:1; padding:16px 0; min-width:0; background:none; border:none; outline:none; font-family:var(--font-body); font-size:15px; color:#F5F0EE; }
    .search-bar input::placeholder { color:var(--text-muted); }
    .search-divider { width:1px; height:28px; background:var(--soil-line); flex-shrink:0; }
    .search-btn { margin:6px; padding:10px 22px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; flex-shrink:0; letter-spacing:0.02em; display:flex; align-items:center; gap:7px; }
    .search-btn:hover { background:var(--red-bright); }

    /* Quick filter pills */
    .quick-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .qf-pill { display:flex; align-items:center; gap:5px; padding:6px 13px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:100px; font-size:12px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; white-space:nowrap; }
    .qf-pill:hover, .qf-pill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    .qf-pill i { font-size:11px; }

    /* ── CONTENT LAYOUT ── */
    .content-layout { display:block; }

    /* ── MAIN SECTIONS ── */
    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:10px; letter-spacing:0.01em; }
    .sec-title i { color:var(--red-bright); font-size:16px; }
    .sec-count { font-size:11px; font-weight:600; color:var(--text-muted); background:var(--soil-hover); padding:2px 9px; border-radius:4px; letter-spacing:0.04em; }
    .see-more { font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:4px; transition:0.15s; letter-spacing:0.02em; }
    .see-more:hover { color:var(--red-bright); }

    /* ── SUMMARY CARDS ── */
    .cards-row { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:32px; }
    .sum-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:18px; display:flex; flex-direction:column; gap:10px; transition:all 0.2s; }
    .sum-card:hover { border-color:rgba(209,61,44,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.25); }
    .sc-top { display:flex; align-items:center; justify-content:space-between; }
    .sc-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .sc-icon.r { background:rgba(209,61,44,.12); color:var(--red-pale); }
    .sc-icon.a { background:rgba(212,148,58,.12); color:var(--amber); }
    .sc-icon.b { background:rgba(74,144,217,.1); color:#7ab8f0; }
    .sc-icon.g { background:rgba(76,175,112,.1); color:#6ccf8a; }
    .sc-icon.p { background:rgba(156,39,176,.1); color:#cf8ae0; }
    .sc-num { font-family:var(--font-display); font-size:24px; font-weight:700; color:#F5F0EE; line-height:1; }
    .sc-label { font-size:10px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .sc-btn { padding:6px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:10px; font-weight:700; cursor:pointer; transition:0.18s; width:100%; }
    .sc-btn:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--red-pale); }

    /* ── FEATURED CARDS (for master data + analytics) ── */
    .featured-scroll { display:flex; gap:14px; overflow-x:auto; padding:8px 6px 24px 6px; margin:-8px -6px 32px -6px; scrollbar-width:none; }
    .featured-scroll::-webkit-scrollbar { display:none; }
    .featured-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px; min-width:220px; max-width:220px; cursor:pointer; transition:all 0.25s; position:relative; overflow:hidden; flex-shrink:0; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .featured-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .featured-card:hover { border-color:rgba(209,61,44,0.55); transform:translateY(-4px); box-shadow:0 20px 48px rgba(0,0,0,0.45); }
    .fc-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--amber); background:var(--amber-dim); border:1px solid rgba(212,148,58,0.22); padding:2px 7px; border-radius:3px; margin-bottom:14px; }
    .fc-icon { width:40px; height:40px; border-radius:10px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; color:var(--red-bright); }
    .fc-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:4px; line-height:1.3; }
    .fc-sub { font-size:12px; color:var(--text-muted); margin-bottom:14px; }
    .fc-num { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; line-height:1; margin-bottom:4px; }
    .fc-footer { display:flex; align-items:center; justify-content:space-between; padding-top:14px; border-top:1px solid var(--soil-line); }
    .fc-action { padding:6px 14px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.2s; }
    .fc-action:hover { background:var(--red-bright); }
    .fc-action.outline { background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); }
    .fc-action.outline:hover { border-color:var(--red-vivid); color:var(--red-pale); background:transparent; }

    /* ── JOB ROW (used for users, job posts, activity) ── */
    .job-list { display:flex; flex-direction:column; gap:8px; }
    .job-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px 20px; transition:all 0.18s; display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; position:relative; }
    .job-row:hover { border-color:rgba(209,61,44,0.5); background:var(--soil-hover); transform:translateX(2px); box-shadow:0 4px 20px rgba(0,0,0,0.3); }
    .jr-top { display:flex; align-items:center; gap:8px; margin-bottom:5px; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; padding:2px 7px; border-radius:3px; white-space:nowrap; }
    .jr-new.red { color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); }
    .jr-new.green { color:#6ccf8a; background:rgba(76,175,112,.1); border:1px solid rgba(76,175,112,.2); }
    .jr-new.amber { color:var(--amber); background:rgba(212,148,58,.1); border:1px solid rgba(212,148,58,.2); }
    .jr-new.blue { color:#7ab8f0; background:rgba(74,144,217,.1); border:1px solid rgba(74,144,217,.2); }
    .jr-new.muted { color:var(--text-muted); background:var(--soil-hover); border:1px solid var(--soil-line); }
    .jr-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:#927C7A; margin-bottom:8px; }
    .jr-meta span { display:flex; align-items:center; gap:4px; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-company { color:var(--red-pale); font-weight:600; }
    .jr-chips { display:flex; gap:5px; flex-wrap:wrap; }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); letter-spacing:0.02em; }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.amber { background:rgba(212,148,58,.08); color:var(--amber); border-color:rgba(212,148,58,.2); }
    .chip.red { background:rgba(209,61,44,.08); color:var(--red-pale); border-color:rgba(209,61,44,.15); }
    .chip.blue { background:rgba(74,144,217,.08); color:#7ab8f0; border-color:rgba(74,144,217,.18); }
    .job-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .jr-actions { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
    .jr-btn { padding:6px 13px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-size:12px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.18s; white-space:nowrap; }
    .jr-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .jr-btn.r:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .jr-btn.g:hover { border-color:rgba(76,175,112,.5); color:#6ccf8a; }
    .jr-btn.a:hover { border-color:rgba(212,148,58,.5); color:var(--amber); }
    .jr-btn.b:hover { border-color:rgba(74,144,217,.5); color:#7ab8f0; }
    .jr-apply { padding:7px 16px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-size:12px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.2s; letter-spacing:0.02em; }
    .jr-apply:hover { background:var(--red-bright); }

    /* ── ACTIVITY PANEL (slide-in like saved/notif panel) ── */
    .activity-panel { position:fixed; top:64px; right:0; bottom:0; width:380px; background:var(--soil-card); border-left:1px solid var(--soil-line); z-index:150; transform:translateX(100%); transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 32px rgba(0,0,0,0.4); }
    .activity-panel.open { transform:translateX(0); }
    .ap-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
    .ap-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
    .ap-title i { color:var(--red-bright); }
    .ap-close { width:28px; height:28px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
    .ap-close:hover { color:#F5F0EE; }
    .ap-body { flex:1; overflow-y:auto; padding:12px 16px; }
    .ap-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--soil-line); }
    .ap-item:last-child { border-bottom:none; }
    .a-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; margin-top:5px; }
    .a-dot.red { background:var(--red-vivid); }
    .a-dot.amber { background:var(--amber); }
    .a-dot.blue { background:#4A90D9; }
    .a-dot.green { background:#4CAF70; }
    .a-dot.read { background:var(--soil-line); }
    .a-text { font-size:13px; color:var(--text-mid); line-height:1.55; }
    .a-time { font-size:11px; color:var(--text-muted); margin-top:3px; font-weight:600; }

    /* Analytics bars */
    .analytics-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:4px; }
    .aitem { margin-bottom:14px; }
    .aitem:last-child { margin-bottom:0; }
    .a-row { display:flex; justify-content:space-between; margin-bottom:5px; }
    .a-key { font-size:12px; color:var(--text-muted); font-weight:600; }
    .a-val { font-size:12px; color:#F5F0EE; font-weight:700; }
    .bar-track { height:6px; background:var(--soil-line); border-radius:3px; overflow:hidden; }
    .bar-fill { height:100%; border-radius:3px; }
    .bar-fill.red { background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .bar-fill.amber { background:linear-gradient(90deg,var(--amber),#f0b050); }
    .bar-fill.blue { background:linear-gradient(90deg,#4A90D9,#7ab8f0); }
    .bar-fill.green { background:linear-gradient(90deg,#4CAF70,#6ccf8a); }

    /* Analytics card wrapper */
    .analytics-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px; margin-bottom:32px; }
    .analytics-note { margin-top:16px; padding:12px 16px; background:rgba(209,61,44,.05); border:1px solid rgba(209,61,44,.12); border-left:2px solid var(--red-vivid); border-radius:8px; font-size:12px; color:var(--text-muted); line-height:1.65; }

    /* Modal */
    .modal-overlay { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); align-items:center; justify-content:center; }
    .modal-overlay.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:32px; max-width:560px; width:92%; position:relative; animation:modalIn 0.2s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6); max-height:88vh; overflow-y:auto; }
    @keyframes modalIn { from{opacity:0;transform:scale(0.97) translateY(8px)} to{opacity:1;transform:scale(1)} }
    .modal-close { position:absolute; top:18px; right:18px; width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-size:13px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; }
    .modal-close:hover { color:#F5F0EE; border-color:var(--red-mid); }

    /* Toast — handled by includes/toast.php */

    /* Footer */
    .footer { border-top:1px solid var(--soil-line); padding:28px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:12px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:16px; }

    /* Empty */
    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    /* Animations */
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.05s; }
    .anim-d2 { animation-delay:0.1s; }

    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

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
    body.light .search-bar { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-bar input { color:#1A0A09; }
    body.light .search-greeting { color:#1A0A09; }
    body.light .search-sub { color:#7A5555; }
    body.light .qf-pill { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .qf-pill.active, body.light .qf-pill:hover { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.3); color:var(--red-mid); }
    body.light .sec-title { color:#1A0A09; }
    body.light .sum-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sc-num { color:#1A0A09; }
    body.light .featured-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fc-title { color:#1A0A09; }
    body.light .fc-num { color:#1A0A09; }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .chip.green,.chip.amber,.chip.red,.chip.blue { opacity:1; }
    body.light .job-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .job-row:hover { background:#FEF0EE; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    body.light .jr-title { color:#1A0A09; }
    body.light .jr-meta { color:#7A5555; }
    body.light .activity-panel { background:#FFFFFF; border-color:#E0CECA; }
    body.light .ap-title { color:#1A0A09; }
    body.light .ap-item { border-color:#E0CECA; }
    body.light .a-dot.read { background:#E0CECA; }
    body.light .analytics-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .bar-track { background:#E0CECA; }
    body.light .analytics-note { background:#FEF8F7; border-color:#E0CECA; }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .profile-dropdown { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pd-item { color:#4A2828; }
    body.light .pd-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .pdh-name { color:#1A0A09; }
    body.light .mobile-menu { background:rgba(255,253,252,0.97); border-color:#E0CECA; }
    body.light .mobile-link { color:#4A2828; }
    body.light .mobile-link:hover { background:#FEF0EE; color:#1A0A09; }

    @media(max-width:1200px) { .cards-row{grid-template-columns:repeat(3,1fr);} }
    @media(max-width:760px) {
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.main-content{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .nav-links{display:none} .hamburger{display:flex}
      .page-shell{padding:0 16px 60px} .nav-inner{padding:0 10px}
      .profile-name,.profile-role{display:none} .profile-btn{padding:6px 8px}
      .job-row{grid-template-columns:1fr;gap:10px}
      .job-row-right{flex-direction:row;align-items:center;justify-content:space-between}
      .activity-panel{width:100%;max-width:100%}
      .footer{flex-direction:column;text-align:center;padding:20px 16px}
      .cards-row{grid-template-columns:repeat(2,1fr);}
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
    <a class="logo" href="index.php">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>
    <div class="nav-links">
      <a class="nav-link active" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
      <a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a>
      <a class="nav-link" href="admin_companies.php"><i class="fas fa-building"></i> Companies<?php if($adminStats['pending_companies']>0): ?> <span style="background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminStats['pending_companies']; ?></span><?php endif; ?></a>
      <a class="nav-link" href="admin_jobs.php"><i class="fas fa-briefcase"></i> Jobs<?php if($adminStats['pending_jobs']>0): ?> <span style="background:var(--amber);color:#1A0A09;font-size:10px;font-weight:700;border-radius:8px;padding:1px 6px;"><?php echo $adminStats['pending_jobs']; ?></span><?php endif; ?></a>
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
          <a class="pd-item" href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
          <a class="pd-item" href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
          <a class="pd-item" href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a>
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
  <a class="mobile-link" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign out</a>
</div>

<!-- Activity slide-in panel (removed; replaced by inline notification bell link) -->

<!-- PAGE -->
<div class="page-shell">

  <!-- SEARCH HEADER -->
  <div class="search-header anim">
    <div class="search-greeting">Welcome back, <span><?php echo htmlspecialchars($firstName, ENT_QUOTES); ?>.</span></div>
    <div class="search-sub"><?php echo $adminStats['pending_jobs']; ?> job post<?php echo $adminStats['pending_jobs']!==1?'s':''; ?> pending approval&nbsp;·&nbsp;<?php echo $adminStats['pending_companies']; ?> company account<?php echo $adminStats['pending_companies']!==1?'s':''; ?> awaiting review.</div>

  </div>

  <div class="content-layout">

    <!-- MAIN -->
    <main>

      <!-- 6 SUMMARY CARDS -->
      <div class="cards-row anim">
        <div class="sum-card"><div class="sc-top"><div class="sc-icon b"><i class="fas fa-users"></i></div><div class="sc-num"><?php echo number_format($adminStats['users']); ?></div></div><div class="sc-label">Total Users</div><a class="sc-btn" href="admin_users.php">Manage Users</a></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon a"><i class="fas fa-building"></i></div><div class="sc-num"><?php echo number_format($adminStats['employers']); ?></div></div><div class="sc-label">Employers<?php if($adminStats['pending_companies']>0): ?> <span style="font-size:10px;font-weight:700;background:var(--red-vivid);color:#fff;border-radius:8px;padding:1px 6px;"><?php echo $adminStats['pending_companies']; ?> pending</span><?php endif; ?></div><a class="sc-btn" href="admin_companies.php<?php echo $adminStats['pending_companies']>0?'?tab=pending':''; ?>">Review Companies</a></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon r"><i class="fas fa-briefcase"></i></div><div class="sc-num"><?php echo number_format($adminStats['jobs']); ?></div></div><div class="sc-label">Job Posts<?php if($adminStats['pending_jobs']>0): ?> <span style="font-size:10px;font-weight:700;background:var(--amber);color:#1A0A09;border-radius:8px;padding:1px 6px;"><?php echo $adminStats['pending_jobs']; ?> pending</span><?php endif; ?></div><a class="sc-btn" href="admin_jobs.php<?php echo $adminStats['pending_jobs']>0?'?tab=pending':''; ?>">Moderate Jobs</a></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon p"><i class="fas fa-paper-plane"></i></div><div class="sc-num"><?php echo number_format($adminStats['applications']); ?></div></div><div class="sc-label">Applications</div><a class="sc-btn" href="admin_reports.php">View Reports</a></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon g"><i class="fas fa-check-circle"></i></div><div class="sc-num"><?php echo number_format($adminStats['active_jobs']); ?></div></div><div class="sc-label">Active Jobs</div><a class="sc-btn" href="admin_jobs.php">View Active</a></div>
        <div class="sum-card"><div class="sc-top"><div class="sc-icon b"><i class="fas fa-user-tie"></i></div><div class="sc-num"><?php echo number_format($adminStats['recruiters']); ?></div></div><div class="sc-label">Recruiters</div><a class="sc-btn" href="admin_recruiters.php">View Recruiters</a></div>
      </div>

      <!-- MASTER DATA (featured-card scroll) -->
      <div id="section-masterdata" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-database"></i> Master Data</div>
          <button class="see-more" onclick="window.location.href='admin_settings.php'">Manage all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="featured-scroll">
          <div class="featured-card" onclick="window.location.href='admin_settings.php'" style="cursor:pointer">
            <div class="fc-badge"><i class="fas fa-tags"></i> Master Data</div>
            <div class="fc-icon"><i class="fas fa-tags"></i></div>
            <div class="fc-title">Industries</div>
            <div class="fc-sub">Job classification groups</div>
            <div class="fc-num"><?php echo $mdCategories; ?></div>
            <div class="fc-footer">
              <span style="font-size:11px;color:var(--text-muted);">From job posts</span>
              <button class="fc-action">View</button>
            </div>
          </div>
          <div class="featured-card" onclick="window.location.href='admin_settings.php'" style="cursor:pointer">
            <div class="fc-badge"><i class="fas fa-briefcase"></i> Master Data</div>
            <div class="fc-icon"><i class="fas fa-briefcase"></i></div>
            <div class="fc-title">Job Types</div>
            <div class="fc-sub">Employment types</div>
            <div class="fc-num"><?php echo $mdJobTypes; ?></div>
            <div class="fc-footer">
              <span style="font-size:11px;color:var(--text-muted);">System-defined</span>
              <button class="fc-action">View</button>
            </div>
          </div>
          <div class="featured-card" onclick="window.location.href='admin_settings.php'" style="cursor:pointer">
            <div class="fc-badge"><i class="fas fa-map-marker-alt"></i> Master Data</div>
            <div class="fc-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="fc-title">Locations</div>
            <div class="fc-sub">Cities and regions</div>
            <div class="fc-num"><?php echo $mdLocations; ?></div>
            <div class="fc-footer">
              <span style="font-size:11px;color:var(--text-muted);">From job posts</span>
              <button class="fc-action">View</button>
            </div>
          </div>
          <div class="featured-card" onclick="window.location.href='admin_settings.php'" style="cursor:pointer">
            <div class="fc-badge"><i class="fas fa-layer-group"></i> Master Data</div>
            <div class="fc-icon"><i class="fas fa-layer-group"></i></div>
            <div class="fc-title">Experience Levels</div>
            <div class="fc-sub">Seniority tiers</div>
            <div class="fc-num"><?php echo $mdExpLevels; ?></div>
            <div class="fc-footer">
              <span style="font-size:11px;color:var(--text-muted);">System-defined</span>
              <button class="fc-action">View</button>
            </div>
          </div>
        </div>
      </div>

      <!-- RECENT REGISTRATIONS -->
      <div id="section-users" class="anim anim-d1" style="margin-bottom:40px;">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-user-plus"></i> Recent Registrations <span class="sec-count" id="userCount">5 users</span></div>
          <a class="see-more" href="admin_users.php">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="usersContainer"></div>
      </div>

      <!-- RECENT JOB POSTS -->
      <div id="section-jobs" class="anim anim-d1" style="margin-bottom:40px;">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-briefcase"></i> Recent Job Posts <span class="sec-count" id="jobCount">5 posts</span></div>
          <a class="see-more" href="admin_jobs.php">Manage all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

      <!-- ANALYTICS SUMMARY -->
      <div id="section-reports" class="anim anim-d2" style="margin-bottom:40px;">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-chart-line"></i> Reports / Analytics Summary</div>
          <div style="display:flex;gap:14px;">
            <a class="see-more" href="admin_reports.php">Open Reports <i class="fas fa-arrow-right"></i></a>
            <a class="see-more" href="admin_reports.php">Export <i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="analytics-card">
          <div class="analytics-col">
            <div>
              <?php $userBar = $adminStats['users'] > 0 ? min(100, round($weeklyUsers / max($adminStats['users'], 1) * 100)) : 0; ?>
              <div class="aitem"><div class="a-row"><span class="a-key">New Users (this week)</span><span class="a-val">+<?php echo $weeklyUsers; ?></span></div><div class="bar-track"><div class="bar-fill blue" style="width:<?php echo max($userBar, 5); ?>%"></div></div></div>
              <?php $jobBar = $adminStats['jobs'] > 0 ? min(100, round($weeklyJobs / max($adminStats['jobs'], 1) * 100)) : 0; ?>
              <div class="aitem"><div class="a-row"><span class="a-key">Jobs Posted (this week)</span><span class="a-val">+<?php echo $weeklyJobs; ?></span></div><div class="bar-track"><div class="bar-fill amber" style="width:<?php echo max($jobBar, 5); ?>%"></div></div></div>
            </div>
            <div>
              <?php $appBar = $adminStats['applications'] > 0 ? min(100, round($weeklyApps / max($adminStats['applications'], 1) * 100)) : 0; ?>
              <div class="aitem"><div class="a-row"><span class="a-key">Applications (this week)</span><span class="a-val">+<?php echo $weeklyApps; ?></span></div><div class="bar-track"><div class="bar-fill red" style="width:<?php echo max($appBar, 5); ?>%"></div></div></div>
              <?php $empBar = $adminStats['employers'] > 0 ? min(100, round($activeEmployers / max($adminStats['employers'], 1) * 100)) : 0; ?>
              <div class="aitem"><div class="a-row"><span class="a-key">Active Employers</span><span class="a-val"><?php echo $activeEmployers; ?> / <?php echo $adminStats['employers']; ?></span></div><div class="bar-track"><div class="bar-fill green" style="width:<?php echo max($empBar, 5); ?>%"></div></div></div>
            </div>
          </div>
          <div class="analytics-note">
            <strong style="color:var(--red-pale);">Platform Summary:</strong>
            <?php echo $adminStats['users']; ?> total users · <?php echo $adminStats['active_jobs']; ?> active jobs · <?php echo $adminStats['applications']; ?> total applications · <?php echo $adminStats['pending_jobs']; ?> jobs pending approval.
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>© 2025 AntCareers — Admin Panel</div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <a href="../index.php" style="color:inherit;text-decoration:none;cursor:pointer;">← Public Site</a>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    <div id="modalBody"></div>
  </div>
</div>

<script>
  // ── DATA (from DB) ──
  const usersData = <?php
    $jsUsers = [];
    $colors = [
      'seeker'    => 'linear-gradient(135deg,#4A90D9,#2A6090)',
      'employer'  => 'linear-gradient(135deg,#D4943A,#a06020)',
      'recruiter' => 'linear-gradient(135deg,#9C27B0,#5A1070)',
    ];
    foreach ($recentUsers as $u) {
      $name = $u['full_name'] ?: 'Unknown';
      $parts = preg_split('/\s+/', $name);
      $initials = count($parts) >= 2
        ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1))
        : strtoupper(substr($name,0,2));
      $role = strtolower($u['account_type']);
      $status = strtolower($u['account_status'] ?? 'active');
      $jsUsers[] = [
        'id'       => (int)$u['id'],
        'name'     => $name,
        'initials' => $initials,
        'color'    => $colors[$role] ?? 'linear-gradient(135deg,#D13D2C,#7A1515)',
        'role'     => $role,
        'email'    => $u['email'],
        'contact'  => $u['contact'] ?? '',
        'company'  => $u['company_name'] ?? '',
        'date'     => date('M d, Y', strtotime($u['created_at'])),
        'status'   => $status === 'active' ? 'active' : ($status === 'suspended' ? 'flagged' : ($status === 'banned' ? 'inactive' : $status)),
        'rawStatus'=> $status,
      ];
    }
    echo json_encode($jsUsers, JSON_HEX_TAG | JSON_HEX_APOS);
  ?>;

  const jobPostsData = <?php
    $jsJobs = [];
    foreach ($recentJobs as $j) {
      $tags = [];
      if (!empty($j['job_type'])) $tags[] = $j['job_type'];
      if (!empty($j['approval_status'])) $tags[] = ucfirst($j['approval_status']);
      $status = strtolower($j['approval_status'] ?? 'approved');
      if ($status === 'approved' && strtolower($j['status']) === 'active') $status = 'active';
      $salaryMin = $j['salary_min'] ? number_format((float)$j['salary_min'], 0) : null;
      $salaryMax = $j['salary_max'] ? number_format((float)$j['salary_max'], 0) : null;
      $cur = currencySymbol($j['salary_currency'] ?? 'PHP');
      $salary = $salaryMin && $salaryMax
        ? $cur . $salaryMin . ' – ' . $salaryMax
        : ($salaryMin ? $cur . $salaryMin . '+' : '');
      $jsJobs[] = [
        'id'       => (int)$j['id'],
        'title'    => $j['title'],
        'company'  => $j['company_name'] ?: ($j['employer_name'] ?: 'Unknown'),
        'employer' => $j['employer_name'] ?: '',
        'empEmail' => $j['employer_email'] ?? '',
        'date'     => date('M d, Y', strtotime($j['created_at'])),
        'deadline' => $j['deadline'] ? date('M d, Y', strtotime($j['deadline'])) : '',
        'status'   => $status,
        'location' => $j['location'] ?? '',
        'setup'    => $j['setup'] ?? '',
        'jobType'  => $j['job_type'] ?? '',
        'expLevel' => $j['experience_level'] ?? '',
        'salary'   => $salary,
        'tags'     => $tags,
      ];
    }
    echo json_encode($jsJobs, JSON_HEX_TAG | JSON_HEX_APOS);
  ?>;

  // ── RENDER USERS ──
  function renderUsers(data) {
    const c = document.getElementById('usersContainer');
    document.getElementById('userCount').textContent = `${data.length} user${data.length!==1?'s':''}`;
    if (!data.length) { c.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><p>No users match.</p></div>`; return; }
    const roleLabel = { seeker:'Job Seeker', employer:'Employer', admin:'Admin' };
    const roleClass = { seeker:'blue', employer:'amber', admin:'red' };
    const statusClass = { active:'green', inactive:'muted', flagged:'red' };
    c.innerHTML = data.map((u,i) => `
      <div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div style="width:34px;height:34px;border-radius:50%;background:${u.color};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">${u.initials}</div>
            <div class="jr-title" style="font-size:14px;">${u.name}</div>
            <span class="jr-new ${roleClass[u.role]||''}">${roleLabel[u.role]||u.role}</span>
            <span class="jr-new ${statusClass[u.status]||'muted'}">${u.status.charAt(0).toUpperCase()+u.status.slice(1)}</span>
          </div>
          <div class="jr-meta">
            <span><i class="fas fa-envelope"></i> ${u.email}</span>
            <span><i class="fas fa-calendar"></i> Joined ${u.date}</span>
          </div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions">
            <button class="jr-btn" onclick="viewUser(${u.id})">View</button>
            <button class="jr-btn a" onclick="suspendUser(${u.id}, this)">Suspend</button>
            <button class="jr-btn r" onclick="deleteUser(${u.id}, this)">Delete</button>
          </div>
        </div>
      </div>`).join('');
  }

  // ── RENDER JOB POSTS ──
  function renderJobs(data) {
    const c = document.getElementById('jobsContainer');
    document.getElementById('jobCount').textContent = `${data.length} post${data.length!==1?'s':''}`;
    if (!data.length) { c.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><p>No job posts match.</p></div>`; return; }
    c.innerHTML = data.map((j,i) => `
      <div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div class="jr-title">${j.title}</div>
            <span class="jr-new ${j.status==='pending'?'amber':j.status==='active'?'green':'muted'}">${j.status.charAt(0).toUpperCase()+j.status.slice(1)}</span>
          </div>
          <div class="jr-meta">
            <span class="jr-company"><i class="fas fa-building"></i> ${j.company}</span>
            <span><i class="fas fa-calendar"></i> Posted ${j.date}</span>
          </div>
          <div class="jr-chips">${j.tags.map(t=>`<span class="chip">${t}</span>`).join('')}</div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions">
            <button class="jr-btn" onclick="viewJob(${j.id})">View</button>
            ${j.status==='pending'?`<button class="jr-apply" onclick="approveJob(${j.id}, this)">Approve</button>`:''}
            <button class="jr-btn r" onclick="removeJob(${j.id}, this)">Remove</button>
          </div>
        </div>
      </div>`).join('');
  }

  // ── SEARCH ──
  const CSRF = '<?php echo htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES); ?>';
  async function adminAction(action, data) {
    const res = await fetch('api_admin.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ action, csrf_token: CSRF, ...data })
    });
    return res.json();
  }
  function viewUser(id) {
    const u = usersData.find(x => x.id === id);
    if (!u) return;
    const roleLabel = { seeker:'Job Seeker', employer:'Employer', recruiter:'Recruiter', admin:'Admin' };
    const roleColor = { seeker:'#4A90D9', employer:'#D4943A', recruiter:'#9C27B0', admin:'#D13D2C' };
    const statusColor = { active:'#4CAF70', flagged:'#D13D2C', inactive:'#927C7A', suspended:'#D13D2C', banned:'#927C7A' };
    const statusLabel = { active:'Active', flagged:'Suspended', inactive:'Banned', suspended:'Suspended', banned:'Banned' };
    document.getElementById('modalBody').innerHTML = `
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-right:24px;">
        <div style="width:56px;height:56px;border-radius:50%;background:${u.color};display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:#fff;flex-shrink:0;">${u.initials}</div>
        <div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;">${u.name}</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
            <span style="background:${roleColor[u.role]||'#D13D2C'}22;color:${roleColor[u.role]||'#D13D2C'};border:1px solid ${roleColor[u.role]||'#D13D2C'}44;border-radius:20px;padding:2px 10px;font-weight:700;">${roleLabel[u.role]||u.role}</span>
            &nbsp;
            <span style="background:${statusColor[u.rawStatus]||'#927C7A'}22;color:${statusColor[u.rawStatus]||'#927C7A'};border:1px solid ${statusColor[u.rawStatus]||'#927C7A'}44;border-radius:20px;padding:2px 10px;font-weight:700;">${statusLabel[u.rawStatus]||u.rawStatus}</span>
          </div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        ${infoRow('fa-envelope','Email',u.email)}
        ${infoRow('fa-phone','Contact',u.contact||'—')}
        ${u.company ? infoRow('fa-building','Company',u.company) : ''}
        ${infoRow('fa-calendar-plus','Joined',u.date)}
        ${infoRow('fa-id-badge','User ID','#'+u.id)}
        ${infoRow('fa-user-tag','Account Type',roleLabel[u.role]||u.role)}
      </div>
      <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
        <a href="admin_users.php" style="padding:8px 16px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);font-size:13px;font-weight:600;text-decoration:none;">All Users</a>
      </div>`;
    document.getElementById('jobModal').classList.add('open');
  }
  function viewJob(id) {
    const j = jobPostsData.find(x => x.id === id);
    if (!j) return;
    const statusColor = { active:'#4CAF70', pending:'#D4943A', approved:'#4CAF70', rejected:'#D13D2C' };
    const statusLabel = { active:'Active', pending:'Pending', approved:'Approved', rejected:'Rejected' };
    const sc = statusColor[j.status]||'#927C7A';
    document.getElementById('modalBody').innerHTML = `
      <div style="padding-right:24px;margin-bottom:20px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">Job Post</div>
        <div style="font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;margin-bottom:8px;">${j.title}</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <span style="background:${sc}22;color:${sc};border:1px solid ${sc}44;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">${statusLabel[j.status]||j.status}</span>
          ${j.jobType?`<span style="background:rgba(74,144,217,.15);color:#7ab8f0;border:1px solid rgba(74,144,217,.3);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">${j.jobType}</span>`:''}
          ${j.setup?`<span style="background:rgba(76,175,112,.12);color:#6ccf8a;border:1px solid rgba(76,175,112,.25);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">${j.setup}</span>`:''}
          ${j.expLevel?`<span style="background:rgba(156,39,176,.12);color:#ce93d8;border:1px solid rgba(156,39,176,.25);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">${j.expLevel}</span>`:''}
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        ${infoRow('fa-building','Company',j.company)}
        ${j.empEmail?infoRow('fa-envelope','Employer Email',j.empEmail):''}
        ${j.location?infoRow('fa-map-marker-alt','Location',j.location):''}
        ${j.salary?infoRow('fa-money-bill-wave','Salary',j.salary):''}
        ${infoRow('fa-calendar-plus','Posted',j.date)}
        ${j.deadline?infoRow('fa-calendar-times','Deadline',j.deadline):''}
        ${infoRow('fa-id-badge','Job ID','#'+j.id)}
      </div>
      <div style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
        <a href="admin_jobs.php" style="padding:8px 16px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);font-size:13px;font-weight:600;text-decoration:none;">All Jobs</a>
      </div>`;
    document.getElementById('jobModal').classList.add('open');
  }
  function infoRow(icon, label, val) {
    return `<div style="background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:10px 14px;">
      <div style="font-size:10px;font-weight:700;letter-spacing:.06em;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;"><i class="fas ${icon}" style="margin-right:4px;"></i>${label}</div>
      <div style="font-size:13px;color:#F5F0EE;word-break:break-all;">${val||'—'}</div>
    </div>`;
  }
  async function suspendUser(id, btn) {
    if (!confirm('Suspend this user?')) return;
    const r = await adminAction('suspend_user', { user_id: id });
    if (r.success) { showToast('User suspended', 'fa-ban'); btn.closest('.job-row').remove(); }
    else showToast(r.message || 'Action failed', 'fa-exclamation-triangle');
  }
  async function deleteUser(id, btn) {
    if (!confirm('Ban this user?')) return;
    const r = await adminAction('ban_user', { user_id: id });
    if (r.success) { showToast('User banned', 'fa-trash'); btn.closest('.job-row').remove(); }
    else showToast(r.message || 'Action failed', 'fa-exclamation-triangle');
  }
  async function approveJob(id, btn) {
    const r = await adminAction('approve_job', { job_id: id });
    if (r.success) {
      showToast('Job approved', 'fa-check');
      const row = btn.closest('.job-row');
      row.querySelector('.jr-new.amber')?.classList.replace('amber','green');
      row.querySelector('.jr-new.amber, .jr-new.green') && (row.querySelector('.jr-new.green').textContent = 'Active');
      btn.remove();
    } else showToast(r.message || 'Action failed', 'fa-exclamation-triangle');
  }
  async function removeJob(id, btn) {
    if (!confirm('Remove this job post?')) return;
    const r = await adminAction('remove_job', { job_id: id });
    if (r.success) { showToast('Job removed', 'fa-trash'); btn.closest('.job-row').remove(); }
    else showToast(r.message || 'Action failed', 'fa-exclamation-triangle');
  }
  let activeFilter = null;
  function pillClick(id) {
    const pill = document.getElementById('pill-'+id);
    if (activeFilter === id) {
      activeFilter = null;
      document.querySelectorAll('.qf-pill').forEach(p=>p.classList.remove('active'));
      renderUsers(usersData); renderJobs(jobPostsData); return;
    }
    document.querySelectorAll('.qf-pill').forEach(p=>p.classList.remove('active'));
    activeFilter = id; pill.classList.add('active');
    if (id==='pending') { renderJobs(jobPostsData.filter(j=>j.status==='pending')); renderUsers(usersData); }
    else if (id==='flagged') { renderUsers(usersData.filter(u=>u.status==='flagged')); renderJobs(jobPostsData); }
    else if (id==='seekers') { renderUsers(usersData.filter(u=>u.role==='seeker')); renderJobs(jobPostsData); }
    else if (id==='employers') { renderUsers(usersData.filter(u=>u.role==='employer')); renderJobs(jobPostsData); }
    else if (id==='reports') { document.getElementById('section-reports').scrollIntoView({behavior:'smooth'}); }
    else if (id==='masterdata') { document.getElementById('section-masterdata').scrollIntoView({behavior:'smooth'}); }
  }

  // ── THEME ──
  function setTheme(t) {
    document.body.classList.toggle('light', t==='light'); document.body.classList.toggle('dark', t!=='light');
    document.querySelectorAll('#themeToggle i').forEach(i => i.className = t==='light' ? 'fas fa-sun' : 'fas fa-moon');
    localStorage.setItem('ac-theme', t);
  }
  document.getElementById('themeToggle').addEventListener('click', () =>
    setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));
  setTheme(localStorage.getItem('ac-theme') || 'dark');

  // ── HAMBURGER ──
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    const open = mobileMenu.classList.toggle('open');
    hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
  });

  // ── PROFILE DROPDOWN ──
  document.getElementById('profileToggle').addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('profileDropdown').classList.toggle('open');
  });

  // ── ACTIVITY PANEL ──
  // Activity panel removed (replaced by notifications page link)

  // ── CLICK OUTSIDE ──
  document.addEventListener('click', e => {
    if (!mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
    if (!document.getElementById('profileToggle').contains(e.target) && !document.getElementById('profileDropdown').contains(e.target))
      document.getElementById('profileDropdown').classList.remove('open');
    // activity panel removed
  });

  // ── MODAL ──
  document.getElementById('closeModal').addEventListener('click', () => document.getElementById('jobModal').classList.remove('open'));
  document.getElementById('jobModal').addEventListener('click', e => { if(e.target===document.getElementById('jobModal')) document.getElementById('jobModal').classList.remove('open'); });

  // ── INIT ──
  renderUsers(usersData);
  renderJobs(jobPostsData);
</script>
<?php renderAdminNotifPanel(); ?>
<?php require_once dirname(__DIR__) . '/includes/toast.php'; ?>
</body>
</html>