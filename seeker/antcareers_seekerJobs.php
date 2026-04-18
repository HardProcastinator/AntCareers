<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
require_once dirname(__DIR__) . '/includes/countries.php';
requireLogin('seeker');
$user = getUser();
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'jobs';
$seekerId  = (int)$_SESSION['user_id'];

$db = getDB();

// ── Fetch live jobs from DB ───────────────────────────────────────────────────
$jobs      = [];
$companies = [];
$dbJobs    = [];
$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;
$totalJobs  = 0;
$totalPages = 1;

try {
    // Count for pagination
    $cStmt = $db->prepare("
        SELECT COUNT(*)
        FROM jobs j
        WHERE j.status = 'Active'
          AND j.approval_status = 'approved'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
          AND j.deleted_at IS NULL
    ");
    $cStmt->execute();
    $totalJobs  = (int)$cStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalJobs / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    // Full query — works after migration (new columns exist)
    $jStmt = $db->prepare("
        SELECT
            j.id, j.employer_id, j.title, j.location, j.job_type, j.setup AS work_setup,
            j.experience_level, j.industry, j.salary_min, j.salary_max,
            j.salary_currency, j.description, j.skills_required, j.created_at, j.deadline,
            COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company,
            cp.logo_path AS logo_url
        FROM jobs j
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'Active'
          AND j.approval_status = 'approved'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
          AND j.deleted_at IS NULL
        ORDER BY j.created_at DESC
        LIMIT :perPage OFFSET :offset
    ");
    $jStmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $jStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $jStmt->execute();
    $dbJobs = $jStmt->fetchAll();
} catch (PDOException $e) {
    // Fallback — works with original 11-column jobs table (pre-migration)
    error_log('[AntCareers] jobs fetch error (trying fallback): ' . $e->getMessage());
    try {
        $jStmt = $db->prepare("
            SELECT
                j.id, j.employer_id, j.title, j.location, j.job_type, 'On-site' AS work_setup,
                NULL AS experience_level, NULL AS industry, j.salary_min, j.salary_max,
                'PHP' AS salary_currency, j.description, NULL AS skills_required, j.created_at,
                COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company,
                cp.logo_path AS logo_url
            FROM jobs j
            JOIN users u ON u.id = j.employer_id
            LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
            WHERE j.status = 'Active'
            ORDER BY j.created_at DESC
            LIMIT 100
        ");
        $jStmt->execute();
        $dbJobs = $jStmt->fetchAll();
    } catch (PDOException $e2) {
        error_log('[AntCareers] jobs fallback fetch error: ' . $e2->getMessage());
    }
}

foreach ($dbJobs as $r) {
    $salMin = (float)($r['salary_min'] ?? 0);
    $salMax = (float)($r['salary_max'] ?? 0);
    $cur    = currencySymbol($r['salary_currency'] ?? 'PHP');
    if ($salMin && $salMax)      $salary = $cur . number_format($salMin) . ' – ' . $cur . number_format($salMax);
    elseif ($salMin)             $salary = $cur . number_format($salMin) . '+';
    else                         $salary = 'Not disclosed';

    $tags = array_filter(array_map('trim', explode(',', (string)($r['skills_required'] ?? ''))));
    $jobs[] = [
        'id'          => (int)$r['id'],
        'employerId'  => (int)$r['employer_id'],
        'title'       => $r['title'],
        'company'     => $r['company'],
        'location'    => $r['location'] ?? 'Not specified',
        'workSetup'   => $r['work_setup'] ?? 'On-site',
        'jobType'     => $r['job_type'],
        'experience'  => $r['experience_level'] ?? '',
        'industry'    => $r['industry'] ?? '',
        'salary'      => $salary,
        'salaryMin'   => (int)($salMin / 1000),
        'salaryMax'   => (int)($salMax / 1000),
        'description' => $r['description'] ?? '',
        'featured'    => false,
        'tags'        => array_values(array_slice($tags, 0, 5)),
        'postedDate'  => date('M j, Y', strtotime($r['created_at'])),
        'createdRaw'  => $r['created_at'],
        'deadlineRaw' => $r['deadline'] ?? null,
    ];
}

// Companies data removed from authenticated browse view (kept only on public index)

// ── Already-applied job IDs (to disable apply button) ────────────────────────
$appliedJobIds = [];
try {
    $aStmt = $db->prepare("SELECT job_id FROM applications WHERE seeker_id = :sid");
    $aStmt->execute([':sid' => $seekerId]);
    $appliedJobIds = array_column($aStmt->fetchAll(), 'job_id');
} catch (PDOException $e) { /* ignore */ }

// ── Saved job IDs ─────────────────────────────────────────────────────────────
$savedJobIds = [];
try {
    $sStmt = $db->prepare("SELECT job_id FROM saved_jobs WHERE user_id = :uid");
    $sStmt->execute([':uid' => $seekerId]);
    $savedJobIds = array_column($sStmt->fetchAll(), 'job_id');
} catch (PDOException $e) { /* ignore */ }

$jobsJson     = json_encode($jobs,        JSON_HEX_TAG | JSON_HEX_AMP);
$appliedJson  = json_encode($appliedJobIds);
$savedJson    = json_encode($savedJobIds);

$countrySidebarOptionsHtml = '<option value="">All countries</option>';
foreach (getCountries() as $country) {
  $name = (string)$country['name'];
  $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $countrySidebarOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
}
$countrySidebarOptionsHtml .= '<option value="Remote">Remote</option>';

$industryKeys = [
  'Accounting','Administration & Office Support','Advertising, Arts & Media',
  'Banking & Financial Services','Call Centre & Customer Service','CEO & General Management',
  'Community Services & Development','Construction','Consulting & Strategy',
  'Design & Architecture','Education & Training','Engineering',
  'Farming, Animals & Conservation','Government & Defence','Healthcare & Medical',
  'Hospitality & Tourism','Human Resources & Recruitment',
  'Information & Communication Technology','Insurance & Superannuation','Legal',
  'Manufacturing, Transport & Logistics','Marketing & Communications',
  'Mining, Resources & Energy','Real Estate & Property','Retail & Consumer Products',
  'Sales','Science & Technology','Self Employment','Sports & Recreation','Trades & Services',
];
$industryCheckboxesHtml = '';
foreach ($industryKeys as $industryValue) {
  $escIndustry = htmlspecialchars((string)$industryValue, ENT_QUOTES, 'UTF-8');
  $industryCheckboxesHtml .= '<label class="ms-item"><input type="checkbox" value="' . $escIndustry . '"><span>' . $escIndustry . '</span></label>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Browse Jobs — AntCareers</title>
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
    /* Logo */

    /* Nav links */

    /* Dropdown */
    .dropdown { position:relative; }
    .cat-toggle {
      display:flex; align-items:center; gap:6px; padding:7px 11px; border-radius:6px;
      background:none; border:1px solid transparent; font-family:var(--font-body);
      font-size:13px; font-weight:600; color:#A09090; cursor:pointer; transition:all 0.2s;
      white-space:nowrap; letter-spacing:0.01em;
    }
    .cat-toggle:hover { color:#F5F0EE; background:var(--soil-hover); }
    .cat-toggle.active { color:var(--red-bright); background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.25); }
    .cat-toggle .chevron { font-size:9px; transition:transform 0.22s ease; }
    .cat-toggle.active .chevron { transform:rotate(180deg); }
    .dropdown-menu {
      position:absolute; top:calc(100% + 10px); left:0;
      background:var(--soil-card); border:1px solid var(--soil-line); border-top:2px solid var(--red-vivid);
      border-radius:10px; padding:6px; min-width:220px;
      opacity:0; visibility:hidden; transform:translateY(-6px);
      transition:all 0.18s ease; z-index:300;
      box-shadow:0 24px 48px rgba(0,0,0,0.55);
    }
    .dropdown-menu.open { opacity:1; visibility:visible; transform:translateY(0); }
    .dropdown-label { font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--text-muted); padding:8px 12px 4px; }
    .dropdown-divider { height:1px; background:var(--soil-line); margin:4px 6px; }
    .dropdown-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:7px; font-size:13px; font-weight:500; color:var(--text-mid); cursor:pointer; transition:all 0.15s; font-family:var(--font-body); }
    .dropdown-item .di-icon { width:30px; height:30px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:12px; color:var(--red-vivid); flex-shrink:0; transition:all 0.15s; }
    .dropdown-item:hover { background:var(--soil-hover); color:#F5F0EE; }
    .dropdown-item:hover .di-icon { background:rgba(209,61,44,0.15); border-color:rgba(209,61,44,0.3); color:var(--red-bright); }
    .di-name { font-size:13px; font-weight:600; color:inherit; }
    .di-sub { font-size:11px; color:var(--text-muted); margin-top:1px; }

    /* Nav right — profile + saved */

    /* Saved jobs button */
    .saved-btn {
      position:relative; width:36px; height:36px; border-radius:7px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:0.2s; font-size:15px; color:var(--text-muted);
      flex-shrink:0;
    }
    .saved-btn:hover { color:var(--red-pale); border-color:var(--red-vivid); background:rgba(209,61,44,0.1); }
    .saved-btn .badge {
      position:absolute; top:-5px; right:-5px;
      width:17px; height:17px; border-radius:50%;
      background:var(--red-vivid); color:#fff;
      font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center;
      border:2px solid var(--soil-dark);
    }
    .saved-btn .badge.hidden { display:none; }

    /* Profile button */
    /* Profile dropdown */
    .pdh-email { font-size:11px; color:var(--text-muted); margin-top:2px; }
    /* Nav right extras */
    .msg-btn-nav, .notif-btn-nav { position:relative; width:36px; height:36px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.2s; font-size:14px; color:var(--text-muted); flex-shrink:0; }
    .msg-btn-nav:hover, .notif-btn-nav:hover { color:var(--red-pale); border-color:var(--red-vivid); }
    .msg-btn-nav .badge, .notif-btn-nav .badge { position:absolute; top:-5px; right:-5px; width:17px; height:17px; border-radius:50%; color:#fff; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--soil-dark); background:var(--red-vivid); }
    body.light .msg-btn-nav, body.light .notif-btn-nav { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }

    /* Profile wrapper (relative for dropdown) */
    /* ── HAMBURGER ── */
    .mobile-cats-label { font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--text-muted); padding:6px 14px 2px; }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 80px; }

    /* ── SEARCH HEADER ── */
    .search-header { padding:32px 0 24px; }
    .search-greeting { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .search-greeting span { color:var(--red-bright); font-style:italic; }
    .search-sub { font-size:14px; color:var(--text-muted); margin-bottom:20px; }

    /* Search row (like People Search) */
    .search-row { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
    .search-box { flex:1; min-width:240px; display:flex; align-items:center; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; transition:0.25s; }
    .search-box:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-box .si { padding:0 14px; color:var(--text-muted); font-size:14px; flex-shrink:0; }
    .search-box input { flex:1; padding:13px 0; background:none; border:none; outline:none; font-family:var(--font-body); font-size:14px; color:#F5F0EE; }
    .search-box input::placeholder { color:var(--text-muted); }
    .search-row .filter-select { width:auto; flex-shrink:0; padding:13px 16px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; font-family:var(--font-body); font-size:13px; color:var(--text-mid); cursor:pointer; outline:none; transition:0.2s; -webkit-appearance:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; background-size:10px 6px; padding-right:32px; }
    .search-row .filter-select:focus { border-color:var(--red-vivid); }
    .search-btn { padding:13px 24px; border-radius:10px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; display:flex; align-items:center; gap:7px; }
    .search-btn:hover { background:var(--red-bright); transform:translateY(-1px); }
    /* Search row multi-select */
    .search-row .ms-wrap { flex-shrink:0; min-width:170px; }
    .search-row .ms-trigger { padding:13px 16px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; font-size:13px; color:var(--text-mid); }
    .search-row .ms-trigger:hover { border-color:var(--red-mid); background-color:var(--soil-hover); }
    .search-row .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-row .ms-panel { min-width:260px; }

    /* Quick filter pills */
    .quick-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
    .qf-pill {
      display:flex; align-items:center; gap:5px; padding:6px 13px;
      background:var(--soil-hover); border:1px solid var(--soil-line);
      border-radius:100px; font-size:12px; font-weight:600; color:var(--text-muted);
      cursor:pointer; transition:all 0.18s; white-space:nowrap;
    }
    .qf-pill:hover, .qf-pill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    .qf-pill i { font-size:11px; }

    /* ── CONTENT LAYOUT ── */
    .content-layout { display:grid; grid-template-columns:240px 1fr; gap:24px; }

    /* ── SIDEBAR ── */
    .filter-sidebar { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:18px; position:sticky; top:80px; }
    .fs-title { font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-muted); margin-bottom:14px; display:flex; align-items:center; gap:7px; }
    .fs-title i { color:var(--red-bright); }
    .fs-section-label { font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); margin-bottom:10px; }
    .fs-divider { height:1px; background:var(--soil-line); margin:16px 0; }
    .fs-reset { width:100%; padding:9px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; }
    .fs-reset:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .fs-text-input { width:100%; padding:9px 12px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; font-family:var(--font-body); font-size:12px; color:var(--text-mid); outline:none; transition:border-color 0.2s, box-shadow 0.2s; }
    .fs-text-input::placeholder { color:var(--text-muted); }
    .fs-text-input:focus { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.14); }
    .fs-select { width:100%; padding:9px 12px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; font-family:var(--font-body); font-size:12px; color:var(--text-mid); outline:none; transition:border-color 0.2s, box-shadow 0.2s; -webkit-appearance:none; appearance:none; cursor:pointer; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 11px center; background-size:10px 6px; padding-right:28px; }
    .fs-select:hover { border-color:var(--red-mid); }
    .fs-select:focus { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.14); }
    .fs-select option { background:var(--soil-card); color:var(--text-mid); }

    /* Multi-select dropdown */
    .ms-wrap { position:relative; }
    .ms-trigger { width:100%; display:flex; align-items:center; justify-content:space-between; background-color:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; padding:9px 12px; font-family:var(--font-body); font-size:13px; color:var(--text-mid); cursor:pointer; transition:border-color 0.2s, box-shadow 0.2s, background-color 0.2s; }
    .ms-trigger:hover { border-color:var(--red-mid); }
    .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.14); }
    .ms-trigger .ms-arrow { font-size:8px; color:var(--text-muted); transition:transform 0.2s; flex-shrink:0; }
    .ms-wrap.open .ms-trigger .ms-arrow { transform:rotate(180deg); }
    .ms-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ms-panel { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:7px; max-height:200px; overflow-y:auto; z-index:1050; box-shadow:0 8px 24px rgba(0,0,0,0.4); }
    .ms-wrap.open .ms-panel { display:block; }
    .role-section{display:block;margin-top:8px;}
    .role-section-label{font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin:10px 0 6px;display:block;}
    .ms-item { display:flex; align-items:center; gap:8px; padding:7px 12px; font-size:13px; color:var(--text-mid); cursor:pointer; transition:background-color 0.12s; user-select:none; }
    .ms-item:hover { background:var(--soil-hover); }
    .ms-item input[type="checkbox"] { width:14px; height:14px; accent-color:var(--red-vivid); cursor:pointer; flex-shrink:0; }

    /* ── MAIN CONTENT ── */
    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:10px; letter-spacing:0.01em; }
    .sec-title i { color:var(--red-bright); font-size:16px; }
    .sec-count { font-size:11px; font-weight:600; color:var(--text-muted); background:var(--soil-hover); padding:2px 9px; border-radius:4px; letter-spacing:0.04em; }
    .see-more { font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:4px; transition:0.15s; letter-spacing:0.02em; }
    .see-more:hover { color:var(--red-bright); }
    .sort-select { appearance:none; -webkit-appearance:none; background:var(--soil-card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23A08080'/%3E%3C/svg%3E") no-repeat right 10px center; border:1px solid var(--soil-line); border-radius:8px; color:var(--text-mid); cursor:pointer; font-family:var(--font-body); font-size:12px; font-weight:600; outline:none; padding:6px 28px 6px 10px; transition:border-color 0.15s,color 0.15s; }
    .sort-select:hover,.sort-select:focus { border-color:var(--red-vivid); color:var(--text-bright); }

    /* ── FEATURED CARDS ── */
    .featured-scroll { display:flex; gap:16px; overflow-x:auto; padding:4px 2px 28px 2px; margin:0 0 36px 0; scrollbar-width:none; }
    .featured-scroll::-webkit-scrollbar { display:none; }
    .featured-card {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px;
      padding:22px; min-width:258px; max-width:258px;
      cursor:pointer; transition:border-color 0.22s, transform 0.22s, box-shadow 0.22s;
      position:relative; overflow:hidden; flex-shrink:0;
      display:flex; flex-direction:column;
    }
    .featured-card::before {
      content:''; position:absolute; top:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg, var(--red-vivid), var(--red-bright));
    }
    .featured-card:hover { border-color:rgba(209,61,44,0.5); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.35); }
    .fc-badge {
      display:inline-flex; align-items:center; gap:4px; font-size:10px;
      font-weight:700; letter-spacing:0.07em; text-transform:uppercase;
      color:var(--amber); background:var(--amber-dim); border:1px solid rgba(212,148,58,0.2);
      padding:3px 8px; border-radius:4px; margin-bottom:14px; align-self:flex-start;
    }
    .fc-header { display:flex; align-items:center; gap:11px; margin-bottom:12px; }
    .fc-icon {
      width:40px; height:40px; border-radius:10px;
      background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.18);
      display:flex; align-items:center; justify-content:center;
      font-size:17px; color:var(--red-pale); flex-shrink:0;
    }
    .fc-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; line-height:1.35; }
    .fc-company { font-size:12px; color:var(--red-pale); font-weight:600; margin-top:2px; }
    .fc-chips { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:14px; align-content:flex-start; min-height:0; }
    .chip {
      font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px;
      background:var(--soil-hover); color:var(--text-muted); border:1px solid var(--soil-line);
      letter-spacing:0.01em; white-space:nowrap;
    }
    .fc-footer {
      display:flex; align-items:center; justify-content:space-between;
      padding-top:14px; border-top:1px solid var(--soil-line); gap:10px;
    }
    .fc-salary { font-size:14px; font-weight:700; color:#F5F0EE; letter-spacing:-0.01em; white-space:nowrap; }
    .fc-action {
      padding:7px 16px; border-radius:7px; background:var(--red-vivid);
      border:none; color:#fff; font-size:12px; font-weight:700;
      cursor:pointer; font-family:var(--font-body); transition:background 0.18s, transform 0.14s;
      display:flex; align-items:center; gap:5px; white-space:nowrap; flex-shrink:0;
    }
    .fc-action:hover:not(:disabled) { background:var(--red-bright); transform:translateY(-1px); }
    .fc-action.applied { background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:default; }

    /* ── COMPANIES HIRING ── */
    .companies-section { margin-bottom:40px; }
    .companies-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px; }
    .company-card {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px;
      padding:16px; display:flex; flex-direction:column; align-items:flex-start; gap:10px;
      cursor:pointer; transition:border-color 0.2s, transform 0.18s, box-shadow 0.18s;
    }
    .company-card:hover { border-color:rgba(209,61,44,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.2); }
    .cc-logo {
      width:40px; height:40px; border-radius:9px;
      background:rgba(209,61,44,0.08); border:1px solid rgba(209,61,44,0.15);
      display:flex; align-items:center; justify-content:center;
      font-size:14px; font-weight:800; color:var(--red-pale); flex-shrink:0;
      overflow:hidden;
    }
    .cc-logo img { width:100%; height:100%; object-fit:cover; }
    .cc-name { font-size:13px; font-weight:700; color:#F5F0EE; line-height:1.3; }
    .cc-roles { font-size:11px; color:var(--text-muted); margin-top:1px; display:flex; align-items:center; gap:4px; }
    .cc-roles i { font-size:9px; color:var(--red-pale); }

    /* ── JOB LIST (LIVE OPPORTUNITIES) ── */
    .job-list { display:flex; flex-direction:column; gap:10px; }
    .job-row {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px;
      padding:22px 24px; cursor:pointer; transition:border-color 0.18s, transform 0.18s, box-shadow 0.18s;
      display:flex; gap:16px; align-items:flex-start; position:relative;
    }
    .job-row:hover { border-color:rgba(209,61,44,0.45); transform:translateX(2px); box-shadow:0 4px 16px rgba(0,0,0,0.12); }
    .jr-icon {
      width:40px; height:40px; border-radius:10px; flex-shrink:0; margin-top:1px;
      background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.15);
      display:flex; align-items:center; justify-content:center;
      font-size:16px; color:var(--red-pale);
    }
    .jr-left { flex:1; min-width:0; }
    .jr-top { display:flex; align-items:center; gap:7px; margin-bottom:4px; flex-wrap:wrap; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new {
      font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase;
      color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2);
      padding:2px 7px; border-radius:4px;
    }
    .jr-badge-new {
      font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;
      color:#6ccf8a; background:rgba(76,175,112,0.1); border:1px solid rgba(76,175,112,0.25);
      padding:2px 7px; border-radius:4px;
    }
    .jr-badge-expiring {
      font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase;
      color:#D4943A; background:rgba(212,148,58,0.1); border:1px solid rgba(212,148,58,0.25);
      padding:2px 7px; border-radius:4px;
    }
    .jr-badge-days {
      font-size:10px; font-weight:700; letter-spacing:0.06em;
      color:var(--text-muted); background:rgba(146,124,122,0.1); border:1px solid rgba(146,124,122,0.2);
      padding:2px 7px; border-radius:4px;
    }
    .jr-meta {
      display:flex; align-items:center; flex-wrap:wrap;
      gap:10px; font-size:12px; color:var(--text-muted); margin-bottom:8px;
    }
    .jr-meta span { display:flex; align-items:center; gap:4px; white-space:nowrap; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-company { color:var(--red-pale); font-weight:600; }
    .jr-chips { display:flex; gap:5px; flex-wrap:wrap; }
    .job-row-right {
      display:flex; flex-direction:column; align-items:flex-end;
      gap:10px; flex-shrink:0; min-width:120px;
    }
    .jr-salary { font-size:14px; font-weight:700; color:#F5F0EE; white-space:nowrap; letter-spacing:-0.01em; }
    .jr-actions { display:flex; gap:8px; align-items:center; }
    /* Save heart button */
    .jr-btn {
      width:34px; height:34px; border-radius:8px;
      border:1px solid var(--soil-line); background:var(--soil-hover);
      color:var(--text-muted); font-size:13px;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:all 0.18s; flex-shrink:0;
    }
    .jr-btn:hover { border-color:rgba(209,61,44,0.5); color:var(--red-pale); background:rgba(209,61,44,0.08); }
    .jr-btn.saved { border-color:var(--red-vivid); color:var(--red-pale); background:rgba(209,61,44,0.12); }
    /* Apply button */
    .jr-apply {
      padding:7px 16px; border-radius:8px; background:var(--red-vivid);
      border:none; color:#fff; font-size:12px; font-weight:700;
      cursor:pointer; font-family:var(--font-body); transition:background 0.18s, transform 0.14s;
      white-space:nowrap; letter-spacing:0.02em;
    }
    .jr-apply:hover:not(:disabled) { background:var(--red-bright); transform:translateY(-1px); }
    .jr-apply.applied { background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:default; }
    .jr-apply:disabled { opacity:0.7; cursor:default; }

    /* Saved jobs panel */
    .saved-panel {
      position:fixed; top:64px; right:0; bottom:0; width:360px;
      background:var(--soil-card); border-left:1px solid var(--soil-line);
      z-index:150; transform:translateX(100%);
      transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);
      display:flex; flex-direction:column;
      box-shadow:-8px 0 32px rgba(0,0,0,0.4);
    }
    .saved-panel.open { transform:translateX(0); }
    .saved-panel-head { padding:20px 20px 16px; border-bottom:1px solid var(--soil-line); display:flex; align-items:center; justify-content:space-between; flex-shrink:0; }
    .saved-panel-title { font-family:var(--font-display); font-size:17px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
    .saved-panel-title i { color:var(--red-bright); font-size:15px; }
    .saved-close { width:28px; height:28px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:0.15s; }
    .saved-close:hover { color:#F5F0EE; }
    .saved-panel-body { flex:1; overflow-y:auto; padding:12px; }
    .saved-empty { text-align:center; padding:48px 20px; color:var(--text-muted); }
    .saved-empty i { font-size:32px; margin-bottom:12px; display:block; color:var(--soil-line); }
    .saved-empty p { font-size:13px; }
    .saved-job-item { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:14px; margin-bottom:8px; display:flex; gap:12px; align-items:flex-start; cursor:pointer; transition:0.18s; }
    .saved-job-item:hover { border-color:rgba(209,61,44,0.4); }
    .sji-icon { width:36px; height:36px; border-radius:8px; background:var(--soil-card); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:15px; color:var(--red-vivid); flex-shrink:0; }
    .sji-info { flex:1; min-width:0; }
    .sji-title { font-size:13px; font-weight:700; color:#F5F0EE; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sji-company { font-size:11px; color:var(--red-pale); font-weight:500; margin-bottom:4px; }
    .sji-salary { font-size:12px; font-weight:600; color:var(--text-muted); }
    .sji-remove { width:24px; height:24px; border-radius:5px; background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.15s; flex-shrink:0; margin-top:2px; }
    .sji-remove:hover { color:var(--red-pale); background:rgba(209,61,44,0.1); }

    /* ── MODAL ── */
    .modal-overlay { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.78); backdrop-filter:blur(10px); align-items:center; justify-content:center; padding:20px; }
    .modal-overlay.open { display:flex; }
    .modal-box {
      background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px;
      padding:0; max-width:580px; width:100%; position:relative;
      animation:modalIn 0.22s ease; box-shadow:0 40px 80px rgba(0,0,0,0.6);
      max-height:90vh; overflow:hidden; display:flex; flex-direction:column;
    }
    .modal-scroll { overflow-y:auto; flex:1; padding:28px 28px 24px; }
    @keyframes modalIn { from{opacity:0;transform:scale(0.96) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .modal-close {
      position:absolute; top:16px; right:16px; width:32px; height:32px; border-radius:8px;
      background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted);
      font-size:13px; display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:0.15s; z-index:2;
    }
    .modal-close:hover { color:#F5F0EE; border-color:var(--red-mid); background:rgba(209,61,44,0.08); }
    /* Modal inner pieces */
    .modal-header { display:flex; align-items:center; gap:14px; margin-bottom:18px; padding-right:28px; }
    .modal-job-icon {
      width:52px; height:52px; border-radius:12px; flex-shrink:0;
      background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.18);
      display:flex; align-items:center; justify-content:center;
      font-size:22px; color:var(--red-pale);
    }
    .modal-job-title { font-family:var(--font-display); font-size:19px; font-weight:700; color:var(--text-light); line-height:1.3; }
    .modal-job-company { font-size:13px; color:var(--red-pale); font-weight:600; margin-top:3px; }
    .modal-badges { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:18px; }
    .modal-badge {
      display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500;
      padding:5px 11px; border-radius:6px; background:var(--soil-hover);
      border:1px solid var(--soil-line); color:var(--text-mid);
    }
    .modal-badge i { font-size:10px; color:var(--red-pale); }
    .modal-badge.salary { background:rgba(76,175,80,0.08); border-color:rgba(76,175,80,0.2); color:#6FCF77; }
    .modal-badge.salary i { color:#6FCF77; }
    .modal-desc { font-size:13px; color:var(--text-mid); line-height:1.75; margin-bottom:18px; white-space:pre-line; }
    .modal-skills { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:22px; }
    .modal-footer {
      display:flex; gap:10px; padding:16px 28px;
      border-top:1px solid var(--soil-line); background:var(--soil-card);
      flex-shrink:0;
    }
    .modal-save-btn {
      width:44px; height:44px; border-radius:9px;
      border:1px solid var(--soil-line); background:var(--soil-hover);
      color:var(--text-muted); font-size:16px;
      display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:all 0.18s; flex-shrink:0;
    }
    .modal-save-btn:hover { border-color:rgba(209,61,44,0.5); color:var(--red-pale); background:rgba(209,61,44,0.08); }
    .modal-save-btn.saved { border-color:var(--red-vivid); color:var(--red-pale); background:rgba(209,61,44,0.12); }
    .modal-apply-btn {
      flex:1; padding:12px 20px; border-radius:9px; background:var(--red-vivid);
      border:none; color:#fff; font-size:14px; font-weight:700;
      cursor:pointer; font-family:var(--font-body); transition:background 0.18s, transform 0.14s;
      display:flex; align-items:center; justify-content:center; gap:7px;
    }
    .modal-apply-btn:hover:not(:disabled) { background:var(--red-bright); transform:translateY(-1px); }
    .modal-apply-btn.applied { background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:default; }

    /* Footer */
    .footer { border-top:1px solid var(--soil-line); padding:28px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:12px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:16px; }

    /* Empty state */
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
    body.light .cat-toggle { color:#5A4040; }
    body.light .cat-toggle:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .saved-btn { background:#F5EEEC; border-color:#E0CECA; color:#9A7070; }
    body.light .search-bar { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-bar input { color:#1A0A09; }
    body.light .search-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-box input { color:#1A0A09; }
    body.light .search-row .filter-select { background-color:#FFFFFF; border-color:#E0CECA; color:#1A0A09; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%237A5555' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); }
    body.light .search-row .filter-select:focus { border-color:var(--red-vivid); }
    body.light .search-greeting { color:#1A0A09; }
    body.light .search-greeting span { color:var(--red-bright); }
    body.light .search-sub { color:#7A5555; }
    body.light .qf-pill { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .qf-pill.active, body.light .qf-pill:hover { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.3); color:var(--red-mid); }
    body.light .filter-sidebar { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fs-text-input { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .fs-text-input::placeholder { color:#B09090; }
    body.light .fs-select { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .fs-select:hover { background-color:#FEF0EE; }
    body.light .fs-select option { background:#FFFFFF; color:#4A2828; }
    body.light .ms-trigger { background-color:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .ms-panel { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 8px 24px rgba(0,0,0,0.1); }
    body.light .ms-item { color:#4A2828; }
    body.light .ms-item:hover { background:#FEF0EE; }
    body.light .search-row .ms-trigger { background:#FFFFFF; border-color:#D4B0AB; color:#1A0A09; }
    body.light .search-row .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .search-row .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .sec-title { color:#1A0A09; }
    body.light .featured-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .featured-card:hover { box-shadow:0 12px 32px rgba(0,0,0,0.1); }
    body.light .fc-title { color:#1A0A09; }
    body.light .fc-company { color:var(--red-mid); }
    body.light .fc-salary { color:#1A0A09; }
    body.light .fc-icon { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.15); }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .fc-action { background:var(--red-vivid); }
    body.light .fc-action.applied { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .job-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .job-row:hover { background:#FFF8F7; box-shadow:0 4px 16px rgba(0,0,0,0.08); }
    body.light .jr-icon { background:rgba(209,61,44,0.07); border-color:rgba(209,61,44,0.12); }
    body.light .jr-title { color:#1A0A09; }
    body.light .jr-salary { color:#1A0A09; }
    body.light .jr-meta { color:#7A5555; }
    body.light .jr-btn { background:#F5EEEC; border-color:#E0CECA; color:#9A7070; }
    body.light .jr-btn:hover { border-color:rgba(209,61,44,0.4); color:var(--red-vivid); background:rgba(209,61,44,0.06); }
    body.light .jr-btn.saved { border-color:var(--red-vivid); color:var(--red-vivid); background:rgba(209,61,44,0.08); }
    body.light .jr-apply.applied { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .company-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .cc-name { color:#1A0A09; }
    body.light .cc-logo { background:rgba(209,61,44,0.07); border-color:rgba(209,61,44,0.12); }
    body.light .modal-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-footer { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-job-title { color:#1A0A09; }
    body.light .modal-badge { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .modal-desc { color:#4A2828; }
    body.light .modal-save-btn { background:#F5EEEC; border-color:#E0CECA; color:#9A7070; }
    body.light .modal-save-btn:hover { border-color:rgba(209,61,44,0.4); color:var(--red-vivid); }
    body.light .modal-save-btn.saved { border-color:var(--red-vivid); color:var(--red-vivid); background:rgba(209,61,44,0.08); }
    body.light .modal-apply-btn.applied { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .saved-panel { background:#FFFFFF; border-color:#E0CECA; }
    body.light .saved-panel-title { color:#1A0A09; }
    body.light .saved-job-item { background:#FEF0EE; border-color:#E0CECA; }
    body.light .sji-title { color:#1A0A09; }
    body.light .dropdown-menu { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 20px 40px rgba(0,0,0,0.12); }
    body.light .dropdown-item { color:#4A2828; }
    body.light .dropdown-item:hover { background:#FEF0EE; color:#1A0A09; }
    body.light .glow-orb { display:none; }
    body.light .fs-option { color:#4A2828; }
    body.light .fs-option:hover { background:#FEF0EE; color:#1A0A09; }
    /* Responsive */
    @media(max-width:1060px) { .content-layout{grid-template-columns:1fr} .filter-sidebar{position:static} }
    @media(max-width:760px) {
      .nav-links{display:none}
      .page-shell{padding:0 16px 60px}
      .profile-name { display:none; }
      .job-row{ flex-wrap:wrap; }
      .jr-icon { display:none; }
      .job-row-right{flex-direction:row;align-items:center;justify-content:space-between;width:100%;min-width:unset;}
      .companies-grid { grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); }
      .saved-panel{width:100%;max-width:100%}
      .footer{flex-direction:column;text-align:center;padding:20px 16px}
      .search-box{min-width:100%}
      .search-btn{flex:1;justify-content:center}
    }
    @media(max-width:480px) { .featured-card{min-width:230px;max-width:230px} }
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

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<!-- PAGE -->
<div class="page-shell">

  <div class="search-header anim">
    <div class="search-greeting">Browse <span>Jobs</span></div>
    <div class="search-sub">Find the right role — filter, explore, and apply with confidence.</div>
  </div>

  <!-- SEARCH ROW -->
  <div class="search-row anim anim-d1">
    <div class="search-box" style="flex:1;">
      <span class="si"><i class="fas fa-search"></i></span>
      <input type="text" id="keywordInput" placeholder="Search by name, skill, or job title…">
    </div>
    <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i> Search</button>
  </div>

  <div class="content-layout">

    <!-- SIDEBAR FILTERS -->
    <aside class="filter-sidebar anim anim-d1">
      <div class="fs-title"><i class="fas fa-sliders-h"></i> Filters</div>

      <div class="fs-section">
        <div class="fs-section-label">Industry</div>
        <div class="ms-wrap" id="msIndustry" data-default="All Industries">
          <button class="ms-trigger" type="button"><span class="ms-text">All Industries</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <?= $industryCheckboxesHtml ?>
          </div>
        </div>
        <div class="role-section" id="rolePickerWrap">
          <span class="role-section-label">Job Role</span>
          <div class="ms-wrap" id="msJobRole" data-default="All roles">
            <button class="ms-trigger" type="button"><span class="ms-text">All roles</span><i class="fas fa-chevron-down ms-arrow"></i></button>
            <div class="ms-panel" id="msJobRolePanel"></div>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Location</div>
        <select class="fs-select" id="sidebarLocationFilter">
          <?= $countrySidebarOptionsHtml ?>
        </select>
        <input type="text" id="locationKeyword" class="fs-text-input" placeholder="Enter region, province or city" style="margin-top:6px;">
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Work Type</div>
        <div class="ms-wrap" id="msWorkType" data-default="All types">
          <button class="ms-trigger" type="button"><span class="ms-text">All types</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Full-time"><span>Full-time</span></label>
            <label class="ms-item"><input type="checkbox" value="Part-time"><span>Part-time</span></label>
            <label class="ms-item"><input type="checkbox" value="Contract"><span>Contract</span></label>
            <label class="ms-item"><input type="checkbox" value="Freelance"><span>Freelance</span></label>
            <label class="ms-item"><input type="checkbox" value="Internship"><span>Internship</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Remote Options</div>
        <div class="ms-wrap" id="msRemote" data-default="All setups">
          <button class="ms-trigger" type="button"><span class="ms-text">All setups</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="On-site"><span>On-site</span></label>
            <label class="ms-item"><input type="checkbox" value="Hybrid"><span>Hybrid</span></label>
            <label class="ms-item"><input type="checkbox" value="Remote"><span>Remote</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Experience</div>
        <div class="ms-wrap" id="msExperience" data-default="Any level">
          <button class="ms-trigger" type="button"><span class="ms-text">Any level</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Entry"><span>Entry level</span></label>
            <label class="ms-item"><input type="checkbox" value="Junior"><span>Junior</span></label>
            <label class="ms-item"><input type="checkbox" value="Mid"><span>Mid level</span></label>
            <label class="ms-item"><input type="checkbox" value="Senior"><span>Senior level</span></label>
            <label class="ms-item"><input type="checkbox" value="Lead"><span>Lead</span></label>
            <label class="ms-item"><input type="checkbox" value="Executive"><span>Executive</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Salary</div>
        <select class="fs-select" id="salaryPeriodFilter">
          <option value="">Any period</option>
          <option value="Annually">Annually</option>
          <option value="Monthly">Monthly</option>
          <option value="Hourly">Hourly</option>
        </select>
        <div style="display:flex;gap:6px;align-items:center;margin-top:6px;">
          <input type="number" id="salaryMinFilter" class="fs-text-input" placeholder="Min" style="flex:1;">
          <span style="color:var(--text-muted);font-size:11px;">–</span>
          <input type="number" id="salaryMaxFilter" class="fs-text-input" placeholder="Max" style="flex:1;">
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Listed</div>
        <div class="ms-wrap" id="msListed" data-default="Any time">
          <button class="ms-trigger" type="button"><span class="ms-text">Any time</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="1"><span>Today</span></label>
            <label class="ms-item"><input type="checkbox" value="3"><span>Last 3 days</span></label>
            <label class="ms-item"><input type="checkbox" value="7"><span>Last 7 days</span></label>
            <label class="ms-item"><input type="checkbox" value="14"><span>Last 14 days</span></label>
            <label class="ms-item"><input type="checkbox" value="30"><span>Last 30 days</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>
      <button class="fs-reset" id="resetFiltersBtn"><i class="fas fa-undo" style="margin-right:5px;"></i> Reset Filters</button>
    </aside>

    <!-- MAIN -->
    <main class="main-content">

      <div id="jobs" class="anim anim-d2">
        <div class="sec-header">
          <div class="sec-title">
            <i class="fas fa-list-ul" id="jobsSectionIcon"></i>
            <span id="jobsSectionText">Live opportunities</span>
            <span class="sec-count" id="jobCount">0 jobs</span>
          </div>
          <select class="sort-select" id="sortSelect" onchange="renderAllJobs()">
            <option value="relevant">Most Relevant</option>
            <option value="recent">Most Recent</option>
            <option value="salary_high">Salary: High to Low</option>
            <option value="salary_low">Salary: Low to High</option>
          </select>
        </div>
        <div class="job-list" id="jobsContainer"></div>
        <?php if ($totalPages > 1): ?>
        <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap;padding:0 4px;">
          <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" style="padding:7px 14px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:8px;color:var(--text-mid);text-decoration:none;font-size:13px;">← Prev</a>
          <?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="?page=<?= $p ?>" style="padding:7px 14px;background:<?= $p === $page ? 'var(--red-vivid)' : 'var(--soil-card)' ?>;border:1px solid <?= $p === $page ? 'var(--red-vivid)' : 'var(--soil-line)' ?>;border-radius:8px;color:<?= $p === $page ? '#fff' : 'var(--text-mid)' ?>;text-decoration:none;font-size:13px;"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>" style="padding:7px 14px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:8px;color:var(--text-mid);text-decoration:none;font-size:13px;">Next →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>© 2025 AntCareers — Where careers take flight.</div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='antcareers_seekerDashboard.php?theme='+(document.body.classList.contains('light')?'light':'dark')">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    <div class="modal-scroll" id="modalBody"></div>
    <div class="modal-footer" id="modalFooter"></div>
  </div>
</div>

<script>
  // ── REAL DATA FROM PHP ───────────────────────────────────────────────────
  const jobsData    = <?= $jobsJson ?>;
  const appliedIds  = new Set(<?= $appliedJson ?>);
  let   savedJobs   = new Set(<?= $savedJson ?>);

  // ── HELPERS ──────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function salaryLabel(j) { return j.salary || 'Not disclosed'; }
  function jobIcon(industry) {
    const m = { Tech:'fa-laptop-code', Finance:'fa-chart-line', Healthcare:'fa-heartbeat',
                Marketing:'fa-bullhorn', Design:'fa-palette', Education:'fa-graduation-cap',
                Engineering:'fa-cogs', Sales:'fa-handshake' };
    return m[industry] || 'fa-briefcase';
  }

  // ── MULTI-SELECT HELPERS ─────────────────────────────────────────────────
  /* ── JOB ROLES DATA ── */
  const JOB_ROLES = {
    'Accounting':['Accounts Officers / Clerks','Accounts Payable','Accounts Receivable / Credit Control','Analysis & Reporting','Assistant Accountants','Audit - External','Audit - Internal','Bookkeeping & Small Practice Accounting','Business Services & Corporate Advisory','Company Secretaries','Compliance & Risk','Cost Accounting','Financial Accounting & Reporting','Financial Managers & Controllers','Forensic Accounting & Investigation','Insolvency & Corporate Recovery','Inventory & Fixed Assets','Management','Management Accounting & Budgeting','Payroll','Strategy & Planning','Systems Accounting & IT Audit','Taxation','Treasury','Other'],
    'Administration & Office Support':['Administrative Assistants','Client & Sales Administration','Contracts Administration','Data Entry & Word Processing','Office Management','PA, EA & Secretarial','Receptionists','Records Management & Document Control','Other'],
    'Advertising, Arts & Media':['Agency Account Management','Art Direction','Editing & Publishing','Event Management','Journalism & Writing','Management','Media Strategy, Planning & Buying','Other'],
    'Banking & Financial Services':['Account & Relationship Management','Analysis & Reporting','Banking - Business','Banking - Corporate & Institutional','Banking - Retail / Branch','Client Services','Compliance & Risk','Corporate Finance & Investment Banking','Credit','Financial Planning','Funds Management','Management','Mortgages','Settlements','Other'],
    'Call Centre & Customer Service':['Collections','Customer Service - Call Centre','Customer Service - Customer Facing','Management & Support','Sales - Inbound','Sales - Outbound','Supervisors / Team Leaders','Other'],
    'CEO & General Management':['Board Appointments','CEO','COO & MD','General / Business Unit Manager','Other'],
    'Community Services & Development':['Aged & Disability Support','Child Welfare, Youth & Family Services','Community Development','Employment Services','Fundraising','Housing & Homelessness Services','Indigenous & Multicultural Services','Management','Volunteer Coordination & Support','Other'],
    'Construction':['Contracts Management','Estimating','Foreperson / Supervisors','Health, Safety & Environment','Management','Planning & Scheduling','Plant & Machinery Operators','Project Management','Quality Assurance & Control','Surveying','Other'],
    'Consulting & Strategy':['Analysts','Corporate Development','Environment & Sustainability Consulting','Management & Change Consulting','Policy','Strategy & Planning','Other'],
    'Design & Architecture':['Architectural Drafting','Architecture','Fashion Design','Graphic Design','Interior Design','Landscape Architecture','Management','Product Design','Urban Design & Planning','Other'],
    'Education & Training':['Childcare & Outside School Hours Care','Library Services & Information Management','Management - Schools','Management - Universities','Management - Vocational','Research & Fellowships','Student Services','Teaching - Early Childhood','Teaching - Primary','Teaching - Secondary','Teaching - Tertiary','Teaching - Vocational','Teaching Aides & Special Needs','Tutoring','Workplace Training & Assessment','Other'],
    'Engineering':['Aerospace Engineering','Automotive Engineering','Building Services Engineering','Chemical Engineering','Civil/Structural Engineering','Electrical/Electronic Engineering','Engineering Drafting','Environmental Engineering','Field Engineering','Industrial Engineering','Maintenance','Management','Materials Handling Engineering','Mechanical Engineering','Process Engineering','Project Engineering','Project Management','Supervisors','Systems Engineering','Water & Waste Engineering','Other'],
    'Farming, Animals & Conservation':['Agronomy & Farm Services','Conservation, Parks & Wildlife','Farm Labour','Farm Management','Fishing & Aquaculture','Horticulture','Veterinary Services & Animal Welfare','Winery & Viticulture','Other'],
    'Government & Defence':['Air Force','Army','Emergency Services','Government - Federal','Government - Local','Government - State','Navy','Police & Corrections','Other'],
    'Healthcare & Medical':['Ambulance/Paramedics','Chiropractic & Osteopathic','Clinical/Medical Research','Dental','Dieticians','Environmental Services','General Practitioners','Management','Medical Administration','Medical Imaging','Medical Specialists','Natural Therapies & Alternative Medicine','Nursing - A&E, Critical Care & ICU','Nursing - Aged Care','Nursing - Community, Maternal & Child Health','Nursing - Educators & Facilitators','Nursing - General Medical & Surgical','Nursing - High Acuity','Nursing - Management','Nursing - Midwifery, Neo-Natal, SCN & NICU','Nursing - Paediatric & PICU','Nursing - Psych, Forensic & Correctional Health','Nursing - Theatre & Recovery','Optical','Pathology','Pharmaceuticals & Medical Devices','Pharmacy','Physiotherapy, OT & Rehabilitation','Psychology, Counselling & Social Work','Residents & Registrars','Sales','Speech Therapy','Other'],
    'Hospitality & Tourism':['Airlines','Bar & Beverage Staff','Chefs/Cooks','Front Office & Guest Services','Gaming','Housekeeping','Kitchen & Sandwich Hands','Management','Reservations','Tour Guides','Travel Agents/Consultants','Waiting Staff','Other'],
    'Human Resources & Recruitment':['Consulting & Generalist HR','Industrial & Employee Relations','Management - Agency','Management - Internal','Occupational Health & Safety','Organisational Development','Recruitment - Agency','Recruitment - Internal','Remuneration & Benefits','Training & Development','Other'],
    'Information & Communication Technology':['Architects','Computer Operators','Consultants','Database Development & Administration','Developers/Programmers','Engineering - Hardware','Engineering - Network','Engineering - Software','Help Desk & IT Support','Management','Networks & Systems Administration','Product Management & Development','Program & Project Management','Sales - Pre & Post','Security','Software Quality Assurance','System Services & Support','Systems Analysis & Modelling','Team Leaders','Technical Writing','Telecommunications','Testing & Quality Assurance','Other'],
    'Insurance & Superannuation':['Actuarial','Assessment','Brokerage','Claims','Management','Risk Management','Superannuation','Underwriting','Workers\' Compensation','Other'],
    'Legal':['Banking & Financial Services Law','Construction Law','Corporate & Commercial Law','Criminal Law','Family Law','Generalists - In-house','Generalists - Law Firm','Industrial Relations & Employment Law','Insurance & Superannuation Law','Intellectual Property Law','Legal Secretaries','Litigation & Dispute Resolution','Management','Personal Injury Law','Property Law','Tax Law','Other'],
    'Manufacturing, Transport & Logistics':['Assembly & Process Work','Aviation Services','Couriers, Drivers & Postal Services','Fleet Management','Freight/Cargo Forwarding','Import/Export & Customs','Inventory & Stock Control','Machine Operators','Management','Methods & Quality Control','Operations','Production, Planning & Scheduling','Public Transport & Taxi Services','Purchasing, Procurement & Inventory','Rail Operations','Road Transport','Shipping','Warehouse, Storage & Distribution','Other'],
    'Marketing & Communications':['Brand Management','Digital & Search Marketing','Direct Marketing & CRM','Event Management','Internal Communications','Management','Market Research & Analysis','Marketing Assistants/Coordinators','Marketing Communications','Media Strategy, Planning & Buying','Product Management & Development','Public Relations & Corporate Affairs','Trade Marketing','Other'],
    'Mining, Resources & Energy':['Analysis & Reporting','Corporate Services','Engineering','Health, Safety & Environment','Management','Natural Resources & Water','Oil & Gas - Drilling','Oil & Gas - Exploration & Geoscience','Oil & Gas - Operations','Oil & Gas - Production & Refinement','Operations','Power Generation & Distribution','Project Management','Renewable Energy','Surveying','Other'],
    'Real Estate & Property':['Administration','Body Corporate & Facilities Management','Commercial Sales, Leasing & Property Mgmt','Management','Residential Leasing & Property Management','Residential Sales','Retail & Shopping Centre Management','Valuation','Other'],
    'Retail & Consumer Products':['Merchandisers','Management - Area/Multi-site','Management - Department/Assistant','Management - Store','Planning','Purchasing, Procurement & Inventory','Retail Assistants','Sales Representatives/Consultants','Visual Merchandising','Other'],
    'Sales':['Account & Relationship Management','Analysis & Reporting','Management','New Business Development','Sales Representatives/Consultants','Other'],
    'Science & Technology':['Biological & Biomedical Sciences','Biotechnology','Chemistry','Environmental, Earth & Geosciences','Food Technology & Safety','Laboratory & Technical Services','Materials Sciences','Mathematics, Statistics & Information Sciences','Modelling & Simulation','Physics','Other'],
    'Self Employment':['Self Employment'],
    'Sports & Recreation':['Coaching & Instruction','Fitness & Personal Training','Management','Other'],
    'Trades & Services':['Automotive Trades','Bakers & Pastry Cooks','Building Trades','Butchers','Caretakers & Handypersons','Cleaning Services','Electricians','Floristry','Gardening & Landscaping','Hair & Beauty Services','Labourers','Locksmiths','Maintenance & Handypersons','Management','Nannies & Babysitters','Painters & Sign Writers','Plumbers','Printing & Publishing Services','Security Services','Tailors & Dressmakers','Technicians','Upholstery & Textile Trades','Other']
  };

  function updateRolePicker(selectedIndustries) {
    const wrap = document.getElementById('rolePickerWrap');
    const panel = document.getElementById('msJobRolePanel');
    const msWrap = document.getElementById('msJobRole');
    if (!wrap || !panel) return;
    const roles = [];
    selectedIndustries.forEach(ind => {
      (JOB_ROLES[ind] || []).forEach(r => { if (!roles.includes(r)) roles.push(r); });
    });
    if (!roles.length) {
      panel.innerHTML = '';
      if (msWrap) { msWrap.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false); updateMsLabel(msWrap); }
      return;
    }
    panel.innerHTML = roles.map(r => `<label class="ms-item"><input type="checkbox" value="${r.replace(/"/g,'&quot;')}"><span>${r.replace(/&/g,'&amp;')}</span></label>`).join('');
    panel.querySelectorAll('input[type=checkbox]').forEach(cb => {
      cb.addEventListener('change', () => { updateMsLabel(msWrap); renderAllJobs(); });
    });
  }

  function getMsValues(id) {
    const w = document.getElementById(id);
    return w ? [...w.querySelectorAll('input:checked')].map(i => i.value) : [];
  }
  function updateMsLabel(wrap) {
    const checked = [...wrap.querySelectorAll('input:checked')];
    const label = wrap.querySelector('.ms-text');
    const def = wrap.dataset.default || 'Select';
    if (!checked.length) label.textContent = def;
    else if (checked.length === 1) label.textContent = checked[0].nextElementSibling?.textContent || checked[0].value;
    else label.textContent = checked.length + ' selected';
  }

  // ── SYNC: sidebar ↔ search bar ───────────────────────────────────────────
  function syncMsCheckboxes(sourceId, targetId) {
    const src = document.getElementById(sourceId);
    const tgt = document.getElementById(targetId);
    if (!src || !tgt) return;
    const vals = new Set([...src.querySelectorAll('input:checked')].map(i => i.value));
    tgt.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = vals.has(cb.value); });
    updateMsLabel(tgt);
  }
  function syncLocationFromSidebar() {
    const v = document.getElementById('sidebarLocationFilter')?.value || '';
    const el = document.getElementById('searchCountryFilter');
    if (el) el.value = v;
  }
  function syncLocationFromSearch() {
    const v = document.getElementById('searchCountryFilter')?.value || '';
    const el = document.getElementById('sidebarLocationFilter');
    if (el) el.value = v;
  }

  // ── RENDER ALL JOBS (with filters) ───────────────────────────────────────
  function getFilters() {
    return {
      keyword:        (document.getElementById('keywordInput')?.value || '').toLowerCase().trim(),
      locationKeyword:(document.getElementById('locationKeyword')?.value || '').toLowerCase().trim(),
      salaryMin:      parseFloat(document.getElementById('salaryMinFilter')?.value) || 0,
      salaryMax:      parseFloat(document.getElementById('salaryMaxFilter')?.value) || 0,
      searchIndustries: getMsValues('msSearchIndustry'),
      searchCountry:  document.getElementById('searchCountryFilter')?.value || '',
      industries:     getMsValues('msIndustry'),
      jobRoles:       getMsValues('msJobRole'),
      sidebarLocation: document.getElementById('sidebarLocationFilter')?.value || '',
      jobTypes:       getMsValues('msWorkType'),
      setups:         getMsValues('msRemote'),
      experiences:    getMsValues('msExperience'),
      salaryPeriod:   document.getElementById('salaryPeriodFilter')?.value || '',
      dateDays:       getMsValues('msListed'),
    };
  }

  function matchesFilters(j, f) {
    if (f.keyword && !`${j.title} ${j.company} ${j.description} ${(j.tags||[]).join(' ')}`.toLowerCase().includes(f.keyword)) return false;
    if (f.jobTypes.length && !f.jobTypes.includes(j.jobType)) return false;
    // Location: sidebar single-select takes priority, then search bar country
    if (f.sidebarLocation) { if (!j.location.toLowerCase().includes(f.sidebarLocation.toLowerCase())) return false; }
    else if (f.searchCountry && !j.location.toLowerCase().includes(f.searchCountry.toLowerCase())) return false;
    if (f.locationKeyword && !j.location.toLowerCase().includes(f.locationKeyword)) return false;
    if (f.experiences.length && !f.experiences.includes(j.experience)) return false;
    // Industry: sidebar multi-select takes priority, then search bar multi-select
    if (f.industries.length) { if (!f.industries.includes(j.industry)) return false; }
    else if (f.searchIndustries.length && !f.searchIndustries.includes(j.industry)) return false;
    if (f.jobRoles && f.jobRoles.length) {
      const tl = j.title.toLowerCase();
      const matched = f.jobRoles.some(role => {
        const rl = role.toLowerCase();
        if (tl.includes(rl)) return true;
        return rl.split(/[\s\-\/]+/).filter(p => p.length > 3).some(p => tl.includes(p));
      });
      if (!matched) return false;
    }
    if (f.setups.length && !f.setups.includes(j.workSetup)) return false;
    // Salary period (single-select)
    if (f.salaryPeriod && j.salaryPeriod && j.salaryPeriod !== f.salaryPeriod) return false;
    // Salary min/max filter
    if (f.salaryMin) {
      if (j.salaryMax && j.salaryMax < f.salaryMin / 1000) return false;
      if (!j.salaryMax && j.salaryMin && j.salaryMin < f.salaryMin / 1000) return false;
    }
    if (f.salaryMax) {
      if (j.salaryMin && j.salaryMin > f.salaryMax / 1000) return false;
    }
    // Date listed (use the maximum days span from selected options)
    if (f.dateDays.length) {
      const maxDays = Math.max(...f.dateDays.map(d => parseInt(d)));
      const posted = new Date(j.postedDate);
      const cutoff = new Date();
      cutoff.setDate(cutoff.getDate() - maxDays);
      if (posted < cutoff) return false;
    }
    return true;
  }

  /* New / Expiring badge helpers */
  function jobBadge(j) {
    const now = Date.now();
    const sevenDays = 7 * 24 * 60 * 60 * 1000;
    const threeDays = 3 * 24 * 60 * 60 * 1000;
    let badges = '';
    if (j.createdRaw) {
      const created = new Date(j.createdRaw).getTime();
      if (now - created <= sevenDays) badges += '<span class="jr-badge-new">New</span>';
    }
    if (j.deadlineRaw) {
      const dl = new Date(j.deadlineRaw).getTime();
      if (dl > now && dl - now <= threeDays) badges += '<span class="jr-badge-expiring">Expiring</span>';
      if (dl > now) {
        const daysLeft = Math.ceil((dl - now) / (24 * 60 * 60 * 1000));
        badges += '<span class="jr-badge-days">' + daysLeft + 'd left</span>';
      }
    }
    return badges;
  }

  function renderAllJobs() {
    const el = document.getElementById('jobsContainer');
    const countEl = document.getElementById('jobCount');
    if (!el) return;

    const f = getFilters();
    const filtered = jobsData.filter(j => matchesFilters(j, f));

    const sortVal = document.getElementById('sortSelect')?.value || 'relevant';
    const sorted = [...filtered];
    if (sortVal === 'recent') {
      sorted.sort((a, b) => new Date(b.createdRaw || 0) - new Date(a.createdRaw || 0));
    } else if (sortVal === 'salary_high') {
      sorted.sort((a, b) => (b.salaryMax || b.salaryMin || 0) - (a.salaryMax || a.salaryMin || 0));
    } else if (sortVal === 'salary_low') {
      sorted.sort((a, b) => (a.salaryMin || a.salaryMax || 0) - (b.salaryMin || b.salaryMax || 0));
    } else if (f.keyword) {
      sorted.sort((a, b) => {
        const score = j => (j.title.toLowerCase().includes(f.keyword) ? 2 : 0) + (j.company.toLowerCase().includes(f.keyword) ? 1 : 0);
        return score(b) - score(a);
      });
    }

    if (countEl) countEl.textContent = sorted.length + ' job' + (sorted.length !== 1 ? 's' : '');

    if (sorted.length === 0) {
      el.innerHTML = `
        <div style="text-align:center;padding:50px 20px;color:var(--text-muted);">
          <i class="fas fa-search" style="font-size:32px;margin-bottom:14px;display:block;opacity:0.4;"></i>
          <div style="font-size:15px;font-weight:700;color:var(--text-mid);margin-bottom:6px;">No jobs match your filters</div>
          <div style="font-size:13px;">Try adjusting your search or <button onclick="resetFilters()" style="background:none;border:none;color:var(--red-pale);cursor:pointer;font-weight:700;font-size:13px;">reset filters</button></div>
        </div>`;
      return;
    }

    el.innerHTML = sorted.map((j, i) => {
      const saved   = savedJobs.has(j.id);
      const applied = appliedIds.has(j.id);
      const tags    = (j.tags || []).slice(0, 4).map(t => `<span class="chip">${esc(t)}</span>`).join('');
      return `
        <div class="job-row" style="animation:fadeUp 0.28s ${i * 0.04}s both ease;" onclick="openJobModal(${j.id})">
          <div class="jr-icon"><i class="fas ${jobIcon(j.industry)}"></i></div>
          <div class="jr-left">
            <div class="jr-top">
              <div class="jr-title">${esc(j.title)}</div>
              ${j.featured ? '<span class="jr-new">Featured</span>' : ''}
              ${jobBadge(j)}
            </div>
            <div class="jr-meta">
              <span class="jr-company"><i class="fas fa-building"></i>${esc(j.company)}</span>
              <span><i class="fas fa-map-marker-alt"></i>${esc(j.location)}</span>
              <span><i class="fas fa-laptop-house"></i>${esc(j.workSetup)}</span>
              <span><i class="fas fa-briefcase"></i>${esc(j.jobType)}</span>
              ${j.experience ? `<span><i class="fas fa-layer-group"></i>${esc(j.experience)}</span>` : ''}
            </div>
            ${tags ? `<div class="jr-chips">${tags}</div>` : ''}
          </div>
          <div class="job-row-right">
            <div class="jr-salary">${esc(j.salary)}</div>
            <div class="jr-actions">
              <button class="jr-btn ${saved ? 'saved' : ''}" title="${saved ? 'Unsave job' : 'Save job'}"
                onclick="event.stopPropagation(); toggleSave(${j.id}, this)" aria-label="${saved ? 'Unsave' : 'Save'}">
                <i class="fas fa-heart"></i>
              </button>
              <button class="jr-apply ${applied ? 'applied' : ''}"
                onclick="event.stopPropagation(); ${applied ? '' : 'openApplyModal(' + j.id + ')'}"
                ${applied ? 'disabled' : ''}>
                ${applied ? '<i class="fas fa-check"></i> Applied' : 'Apply'}
              </button>
            </div>
          </div>
        </div>`;
    }).join('');
  }

  // ── JOB DETAIL MODAL ─────────────────────────────────────────────────────
  function openJobModal(id) {
    const j = jobsData.find(x => x.id === id);
    if (!j) return;
    const applied = appliedIds.has(j.id);
    const saved   = savedJobs.has(j.id);
    const tags    = (j.tags || []).map(t => `<span class="chip">${esc(t)}</span>`).join('');
    const box     = document.getElementById('modalBody');
    box.innerHTML = `
      <div class="modal-header">
        <div class="modal-job-icon"><i class="fas ${jobIcon(j.industry)}"></i></div>
        <div style="min-width:0;">
          <div class="modal-job-title">${esc(j.title)}</div>
          <a class="modal-job-company" href="public_company_profile.php?employer_id=${j.employerId}" onclick="event.stopPropagation()" style="color:var(--red-pale);text-decoration:none;">${esc(j.company)}</a>
        </div>
      </div>
      <div class="modal-badges">
        <span class="modal-badge"><i class="fas fa-map-marker-alt"></i>${esc(j.location)}</span>
        <span class="modal-badge"><i class="fas fa-briefcase"></i>${esc(j.jobType)}</span>
        <span class="modal-badge"><i class="fas fa-laptop-house"></i>${esc(j.workSetup)}</span>
        ${j.experience ? `<span class="modal-badge"><i class="fas fa-layer-group"></i>${esc(j.experience)}</span>` : ''}
        <span class="modal-badge salary"><i class="fas fa-money-bill-wave"></i>${esc(j.salary)}</span>
      </div>
      ${j.description ? `<div class="modal-desc">${esc(j.description)}</div>` : ''}
      ${tags ? `<div class="modal-skills">${tags}</div>` : ''}`;
    // Footer with save + apply
    document.getElementById('modalFooter').innerHTML = `
      <button id="modalSaveBtn" class="modal-save-btn ${saved ? 'saved' : ''}" title="${saved ? 'Unsave' : 'Save job'}" onclick="toggleSave(${j.id}, this)">
        <i class="fas fa-heart"></i>
      </button>
      <button id="modalApplyBtn" class="modal-apply-btn ${applied ? 'applied' : ''}" ${applied ? 'disabled' : ''}
        onclick="${applied ? '' : 'openApplyModal(' + j.id + ')'} ">
        ${applied ? '<i class="fas fa-check"></i> Already Applied' : '<i class="fas fa-paper-plane"></i> Apply Now'}
      </button>`;
    document.getElementById('jobModal').classList.add('open');
  }

  document.getElementById('closeModal')?.addEventListener('click', () =>
    document.getElementById('jobModal').classList.remove('open'));
  document.getElementById('jobModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('jobModal'))
      document.getElementById('jobModal').classList.remove('open');
  });

  // ── SAVE / UNSAVE ─────────────────────────────────────────────────────────
  async function toggleSave(jobId, btn) {
    const isSaved = savedJobs.has(jobId);
    try {
      const fd = new FormData();
      fd.append('job_id', jobId);
      fd.append('action', isSaved ? 'unsave' : 'save');
      const res  = await fetch('save_job.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.success) {
        if (isSaved) { savedJobs.delete(jobId); showToast('Removed from saved jobs', 'fa-heart-broken'); }
        else         { savedJobs.add(jobId);    showToast('Job saved!', 'fa-heart'); }
        renderAllJobs();
      }
    } catch { showToast('Could not save. Try again.', 'fa-exclamation'); }
  }

  // ── QUICK APPLY MODAL ─────────────────────────────────────────────────────
  let currentApplyJobId = null;

  function openApplyModal(jobId) {
    if (appliedIds.has(jobId)) return;
    currentApplyJobId = jobId;
    const j = jobsData.find(x => x.id === jobId);
    if (!j) return;
    document.getElementById('quickApplyTitle').textContent = j.title;
    document.getElementById('quickApplyInfo').innerHTML =
      `<strong>${esc(j.company)}</strong> &mdash; ${esc(j.location)} &middot; ${esc(j.jobType)} &middot; ${esc(j.salary)}`;
    document.getElementById('quickCoverLetter').value = '';
    document.getElementById('quickApplyModal').classList.add('open');
    document.getElementById('jobModal').classList.remove('open');
  }

  function closeApplyModal() {
    document.getElementById('quickApplyModal').classList.remove('open');
    currentApplyJobId = null;
  }

  async function submitQuickApply() {
    if (!currentApplyJobId) return;
    const btn = document.querySelector('#quickApplyModal button[onclick="submitQuickApply()"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…'; }

    try {
      const fd = new FormData();
      fd.append('job_id',      currentApplyJobId);
      fd.append('cover_letter', document.getElementById('quickCoverLetter').value);
      fd.append('csrf_token', document.getElementById('quickCsrfToken').value);
      const res  = await fetch('apply_job.php', { method:'POST', body:fd });
      const data = await res.json();

      if (data.success) {
        appliedIds.add(currentApplyJobId);
        closeApplyModal();
        renderAllJobs();
        renderFeatured();
        showToast('Application submitted! 🎉', 'fa-check');
      } else {
        showToast(data.message || 'Could not apply. Try again.', 'fa-exclamation');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
      }
    } catch {
      showToast('Network error. Try again.', 'fa-exclamation');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
    }
  }

  // ── FILTERS ───────────────────────────────────────────────────────────────
  function resetFilters() {
    document.getElementById('keywordInput').value = '';
    document.getElementById('locationKeyword') && (document.getElementById('locationKeyword').value = '');
    document.getElementById('salaryMinFilter') && (document.getElementById('salaryMinFilter').value = '');
    document.getElementById('salaryMaxFilter') && (document.getElementById('salaryMaxFilter').value = '');
    updateRolePicker([]);
    ['searchCountryFilter','sidebarLocationFilter','salaryPeriodFilter'].forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    document.querySelectorAll('.ms-wrap').forEach(wrap => {
      wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
      updateMsLabel(wrap);
    });
    document.querySelectorAll('.qf-pill').forEach(p => p.classList.remove('active'));
    renderAllJobs();
    document.querySelector('.search-header')?.scrollIntoView({ behavior:'smooth', block:'start' });
  }

  // Multi-select dropdown behavior
  document.querySelectorAll('.ms-trigger').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const wrap = btn.closest('.ms-wrap');
      const wasOpen = wrap.classList.contains('open');
      document.querySelectorAll('.ms-wrap.open').forEach(w => w.classList.remove('open'));
      if (!wasOpen) wrap.classList.add('open');
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.ms-wrap')) document.querySelectorAll('.ms-wrap.open').forEach(w => w.classList.remove('open'));
  });
  document.querySelectorAll('.ms-wrap input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => {
      const wrap = cb.closest('.ms-wrap');
      updateMsLabel(wrap);
      // Sync industry checkboxes between sidebar and search bar
      if (wrap.id === 'msIndustry') { syncMsCheckboxes('msIndustry', 'msSearchIndustry'); updateRolePicker(getMsValues('msIndustry')); }
      else if (wrap.id === 'msSearchIndustry') { syncMsCheckboxes('msSearchIndustry', 'msIndustry'); updateRolePicker(getMsValues('msIndustry')); }
      renderAllJobs();
    });
  });

  // Location sync: sidebar → search bar
  document.getElementById('sidebarLocationFilter')?.addEventListener('change', () => { syncLocationFromSidebar(); renderAllJobs(); });
  // Location sync: search bar → sidebar
  document.getElementById('searchCountryFilter')?.addEventListener('change', () => { syncLocationFromSearch(); renderAllJobs(); });

  ['salaryPeriodFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) { el.addEventListener('change', renderAllJobs); }
  });
  document.getElementById('salaryMinFilter')?.addEventListener('input', renderAllJobs);
  document.getElementById('salaryMaxFilter')?.addEventListener('input', renderAllJobs);
  document.getElementById('searchBtn')?.addEventListener('click', renderAllJobs);
  document.getElementById('keywordInput')?.addEventListener('keyup', e => { if (e.key === 'Enter') renderAllJobs(); });
  document.getElementById('resetFiltersBtn')?.addEventListener('click', resetFilters);

  // ── SCROLL NAV ────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-scroll]').forEach(el =>
    el.addEventListener('click', () =>
      document.getElementById(el.dataset.scroll)?.scrollIntoView({ behavior:'smooth' })));

  // ── URL PARAMS (from dashboard search) ───────────────────────────────────
  const _p = new URLSearchParams(window.location.search);
  if (_p.get('q'))   { const ki = document.getElementById('keywordInput');  if(ki) ki.value = _p.get('q'); }
  if (_p.get('loc')) { const lf = document.getElementById('searchCountryFilter'); if(lf) lf.value = _p.get('loc'); }

  // ── TIME-BASED GREETING ────────────────────────────────────────────────────
  // (static title — no dynamic greeting needed)

  // ── INIT ──────────────────────────────────────────────────────────────────
  document.getElementById('locationKeyword')?.addEventListener('input', renderAllJobs);
  renderAllJobs();
</script>

<!-- QUICK APPLY MODAL -->
<div id="quickApplyModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:600;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;transition:all 0.2s;">
  <div style="background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:28px;width:100%;max-width:480px;transform:translateY(10px);transition:all 0.22s;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div style="font-size:17px;font-weight:700;color:var(--text-light);">Apply for <span id="quickApplyTitle" style="color:var(--red-pale);"></span></div>
      <button onclick="closeApplyModal()" style="width:30px;height:30px;border-radius:6px;background:var(--soil-hover);border:none;color:var(--text-muted);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
    </div>
    <div id="quickApplyInfo" style="background:var(--soil-hover);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--text-mid);"></div>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:5px;">Cover Letter (optional)</label>
      <textarea id="quickCoverLetter" rows="4" placeholder="Tell this employer why you're a great fit..." style="width:100%;padding:10px 14px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:7px;font-family:var(--font-body);font-size:13px;color:var(--text-light);outline:none;resize:vertical;"></textarea>
    </div>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:5px;">Resume / CV</label>
      <select id="quickResumeSelect" style="width:100%;padding:10px 14px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:7px;font-family:var(--font-body);font-size:13px;color:var(--text-mid);outline:none;cursor:pointer;">
        <option value="profile">Use resume from my profile</option>
      </select>
    </div>
    <input type="hidden" id="quickCsrfToken" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
    <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--soil-line);">
      <button onclick="closeApplyModal()" style="padding:9px 18px;border-radius:7px;background:transparent;border:1px solid var(--soil-line);color:var(--text-mid);font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="submitQuickApply()" style="padding:9px 22px;border-radius:7px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;"><i class="fas fa-paper-plane"></i> Submit Application</button>
    </div>
  </div>
</div>
<script>
  const _qam = document.getElementById('quickApplyModal');
  _qam?.addEventListener('click', e => { if(e.target===_qam) closeApplyModal(); });
  // Show/hide modal via class
  new MutationObserver(() => {
    const open = _qam.classList.contains('open');
    _qam.style.opacity    = open ? '1' : '0';
    _qam.style.visibility = open ? 'visible' : 'hidden';
    _qam.querySelector('div').style.transform = open ? 'translateY(0)' : 'translateY(10px)';
  }).observe(_qam, { attributes:true, attributeFilter:['class'] });
</script>
</body>
</html>