<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/job_titles.php';
require_once __DIR__ . '/includes/countries.php';

$db = getDB();
$indexJobs = [];
$indexCompanies = [];

try {
    $s = $db->prepare("
        SELECT j.id, j.title, j.location, j.job_type, j.setup AS work_setup,
               j.experience_level, j.industry, j.salary_min, j.salary_max,
               j.salary_currency, j.description, j.skills_required, j.created_at, j.deadline,
               COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company
        FROM jobs j
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'Active'
          AND j.approval_status = 'approved'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        ORDER BY j.created_at DESC LIMIT 50
    ");
    $s->execute();
    $rows = $s->fetchAll();
    $featCount = 0;
    foreach ($rows as $r) {
        $salMin = (float)($r['salary_min'] ?? 0);
        $salMax = (float)($r['salary_max'] ?? 0);
        $cur = currencySymbol($r['salary_currency'] ?? 'PHP');
        if ($salMin && $salMax)      $salary = $cur . number_format($salMin) . ' – ' . $cur . number_format($salMax);
        elseif ($salMin)             $salary = $cur . number_format($salMin) . '+';
        else                         $salary = 'Not disclosed';

        $tags = array_filter(array_map('trim', explode(',', (string)($r['skills_required'] ?? ''))));
        $isFeatured = $featCount < 4;
        if ($isFeatured) $featCount++;
        $indexJobs[] = [
            'id'          => (int)$r['id'],
            'title'       => $r['title'],
            'company'     => $r['company'],
            'location'    => $r['location'] ?? 'Not specified',
            'workSetup'   => $r['work_setup'] ?? 'On-site',
            'jobType'     => $r['job_type'],
            'experience'  => $r['experience_level'] ?? '',
            'industry'    => $r['industry'] ?? '',
            'salary'      => $salary,
            'salaryMin'   => (int)$salMin,
            'salaryMax'   => (int)$salMax,
            'description' => $r['description'] ?? '',
            'featured'    => $isFeatured,
            'tags'        => array_values(array_slice($tags, 0, 5)),
            'icon'        => 'fa-briefcase',
            'postedDate'  => date('M j, Y', strtotime($r['created_at'])),
            'createdRaw'  => $r['created_at'],
            'deadlineRaw' => $r['deadline'] ?? null,
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] index jobs: '.$e->getMessage()); }

try {
    $s = $db->prepare("
        SELECT COALESCE(cp.company_name, u.company_name, u.full_name) AS name,
               u.id AS employer_id,
               COUNT(j.id) AS open_roles,
               cp.logo_path, cp.tagline, cp.about, cp.industry,
               cp.company_size, cp.location AS company_location
        FROM users u
        JOIN jobs j ON j.employer_id = u.id
            AND j.status = 'Active'
            AND j.approval_status = 'approved'
            AND (j.deadline IS NULL OR j.deadline >= CURDATE())
            AND j.deleted_at IS NULL
        LEFT JOIN company_profiles cp ON cp.user_id = u.id
        WHERE u.account_type = 'employer'
        GROUP BY u.id
        ORDER BY open_roles DESC LIMIT 6
    ");
    $s->execute();
    foreach ($s->fetchAll() as $c) {
        $bio = $c['tagline'] ?: ($c['about'] ? mb_substr(strip_tags($c['about']), 0, 90) : '');
        $indexCompanies[] = [
            'name'       => $c['name'],
            'openRoles'  => (int)$c['open_roles'],
            'employerId' => (int)$c['employer_id'],
            'logo'       => $c['logo_path'] ? 'uploads/logos/' . basename($c['logo_path']) : '',
            'bio'        => $bio,
            'industry'   => $c['industry'] ?? '',
            'size'       => $c['company_size'] ?? '',
            'location'   => $c['company_location'] ?? '',
            'about'      => $c['about'] ?? '',
        ];
    }
} catch (PDOException $e) { /* ignore */ }

// Country options for sidebar
$countrySidebarOptionsHtml = '<option value="">All countries</option>';
foreach (getCountries() as $country) {
    $escName = htmlspecialchars((string)$country['name'], ENT_QUOTES, 'UTF-8');
    $countrySidebarOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
}
$countrySidebarOptionsHtml .= '<option value="Remote">Remote</option>';

$indexJobsJson = json_encode($indexJobs, JSON_HEX_TAG | JSON_HEX_AMP);
$indexCompaniesJson = json_encode($indexCompanies, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Ant Careers — Find Your Next Role</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after {
      margin: 0; padding: 0; box-sizing: border-box;
    }

    :root {
      --red-deep:   #7A1515;
      --red-mid:    #B83525;
      --red-vivid:  #D13D2C;
      --red-bright: #E85540;
      --red-pale:   #F07060;
      --soil-dark:  #0A0909;
      --soil-med:   #131010;
      --soil-card:  #1C1818;
      --soil-hover: #252020;
      --soil-line:  #352E2E;
      --text-light: #F5F0EE;
      --text-mid:   #D0BCBA;
      --text-muted: #927C7A;
      --amber:      #D4943A;
      --amber-dim:  #251C0E;
      --glow-r:     rgba(180, 50, 38, 0.15);
      --font-display: 'Playfair Display', Georgia, serif;
      --font-body:    'Plus Jakarta Sans', system-ui, sans-serif;
    }

    html { overflow-x: hidden; }

    body {
      font-family: var(--font-body);
      background: var(--soil-dark);
      color: var(--text-light);
      overflow-x: hidden;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* === ANT TUNNEL BACKGROUND === */
    .tunnel-bg {
      position: fixed; inset: 0;
      pointer-events: none; z-index: 0; overflow: hidden;
    }
    .tunnel-bg svg { width: 100%; height: 100%; opacity: 0.07; }
    .glow-orb {
      position: fixed; border-radius: 50%;
      filter: blur(90px); pointer-events: none; z-index: 0;
    }
    .glow-orb-1 {
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(197,57,43,0.25), transparent 70%);
      top: -100px; left: -150px;
      animation: orbFloat1 18s ease-in-out infinite alternate;
    }
    .glow-orb-2 {
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(232,160,69,0.1), transparent 70%);
      bottom: 0; right: -100px;
      animation: orbFloat2 22s ease-in-out infinite alternate;
    }
    @keyframes orbFloat1 { to { transform: translate(60px,80px) scale(1.15); } }
    @keyframes orbFloat2 { to { transform: translate(-40px,-60px) scale(1.1); } }

    /* ==============================
       NAVBAR — fixed overflow issues
       ============================== */
    .navbar {
      position: sticky; top: 0; z-index: 200;
      background: rgba(10, 9, 9, 0.97);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(200,57,42,0.4);
      box-shadow: 0 1px 0 rgba(200,57,42,0.08), 0 4px 24px rgba(0,0,0,0.5);
    }
    .nav-inner {
      max-width: 1380px; margin: 0 auto;
      padding: 0 24px;                      /* reduced from 40px */
      display: flex; align-items: center;
      height: 64px;                          /* slightly shorter */
      gap: 0;
      min-width: 0;                          /* allow flex children to shrink */
    }

    /* Logo */
    .logo {
      display: flex; align-items: center; gap: 8px;
      text-decoration: none;
      margin-right: 28px;                    /* tighter */
      flex-shrink: 0;
    }
    .logo-icon {
      width: 34px; height: 34px;
      background: linear-gradient(135deg, var(--red-vivid), var(--red-deep));
      border-radius: 9px;
      display: flex; align-items: center; justify-content: center;
      font-size: 17px;
      box-shadow: 0 0 18px rgba(200,57,42,0.35);
    }
    .logo-icon::before { content: '🐜'; font-size: 18px; filter: brightness(0) invert(1); }
    .logo-text {
      font-family: var(--font-display);
      font-weight: 700;
      font-size: 19px;
      letter-spacing: 0.01em;
      color: var(--text-light);
      white-space: nowrap;
    }
    .logo-text span {
      color: var(--red-bright);
    }

    /* Nav links (hidden on mobile, shown on desktop) */
    .nav-links {
      display: flex; align-items: center; gap: 2px;
      flex: 1; min-width: 0;
    }
    .nav-link {
      font-size: 13px; font-weight: 600;
      color: #A09090;
      text-decoration: none; padding: 7px 11px;
      border-radius: 6px; transition: all 0.2s;
      cursor: pointer; background: none; border: none;
      font-family: var(--font-body);
      display: flex; align-items: center; gap: 5px;
      white-space: nowrap; letter-spacing: 0.01em;
    }
    .nav-link:hover { color: var(--text-light); background: var(--soil-hover); }

    /* Nav right */
    .nav-right {
      display: flex; align-items: center; gap: 8px;
      margin-left: auto; flex-shrink: 0;
    }
    .theme-btn {
      width: 36px; height: 36px; border-radius: 7px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      color: var(--text-muted); display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: 0.2s; font-size: 14px; flex-shrink: 0;
    }
    .theme-btn:hover { color: var(--red-bright); border-color: var(--red-vivid); }

    .btn-ghost {
      padding: 7px 14px; border-radius: 7px;
      border: 1px solid var(--soil-line);
      background: transparent; color: var(--text-mid);
      font-size: 13px; font-weight: 600;
      cursor: pointer; transition: 0.2s; font-family: var(--font-body);
      white-space: nowrap; letter-spacing: 0.01em;
    }
    .btn-ghost:hover { border-color: var(--red-vivid); color: var(--text-light); }

    .btn-red {
      padding: 7px 18px; border-radius: 7px;
      background: var(--red-vivid);
      border: none; color: #fff;
      font-size: 13px; font-weight: 700;
      cursor: pointer; transition: 0.2s; font-family: var(--font-body);
      box-shadow: 0 2px 8px rgba(200,57,42,0.35);
      white-space: nowrap; letter-spacing: 0.02em;
    }
    .btn-red:hover { background: var(--red-bright); box-shadow: 0 4px 16px rgba(200,57,42,0.45); transform: translateY(-1px); }

    .employer-btn {
      padding: 7px 14px; border-radius: 7px;
      background: var(--amber-dim); border: 1px solid rgba(212,148,58,0.3);
      color: var(--amber); font-size: 13px; font-weight: 600;
      cursor: pointer; transition: 0.2s; font-family: var(--font-body);
      white-space: nowrap;
    }
    .employer-btn:hover { background: rgba(212,148,58,0.15); border-color: var(--amber); }

    /* === HAMBURGER (mobile only) === */
    .hamburger {
      display: none;
      width: 36px; height: 36px; border-radius: 8px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      color: var(--text-mid); align-items: center; justify-content: center;
      cursor: pointer; font-size: 14px; flex-shrink: 0;
      margin-left: 8px;
    }
    #themeToggleMobile {
      display: none;
    }

    /* Mobile drawer */
    .mobile-menu {
      display: none;
      position: fixed; top: 64px; left: 0; right: 0;
      background: rgba(12,11,11,0.97);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--soil-line);
      padding: 12px 20px 16px;
      z-index: 190;
      flex-direction: column; gap: 2px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.5);
    }
    .mobile-menu.open { display: flex; }
    .mobile-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 14px; border-radius: 7px;
      font-size: 14px; font-weight: 500; color: var(--text-mid);
      cursor: pointer; transition: 0.15s; font-family: var(--font-body);
    }
    .mobile-link i { color: var(--red-mid); width: 16px; text-align: center; }
    .mobile-link:hover { background: var(--soil-hover); color: var(--text-light); }
    .mobile-divider { height: 1px; background: var(--soil-line); margin: 6px 0; }
    .mobile-auth {
      display: flex; gap: 8px; padding: 8px 0 2px;
    }
    .mobile-auth .btn-ghost, .mobile-auth .btn-red { flex: 1; text-align: center; justify-content: center; padding: 10px 16px; font-size: 14px; border-radius: 8px; }

    /* ============================================
       FILTER COLLAPSIBLE — MOBILE ONLY
       ============================================ */
    .filter-toggle-bar {
      display: none; /* desktop: hidden, filters always visible */
    }
    .filter-body {
      /* always visible on desktop */
    }
    /* Active-filter badge on filter header */
    .filter-active-badge {
      display: none;
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--red-vivid);
      margin-left: 6px;
      flex-shrink: 0;
    }
    .filter-active-badge.visible { display: inline-block; }

    /* Salary row: side-by-side inputs */
    .salary-row {
      display: flex; gap: 6px; align-items: center; margin-top: 6px;
    }
    .salary-row .fs-text-input { flex: 1; min-width: 0; }
    .salary-row .sal-sep { color: var(--text-muted); font-size: 11px; flex-shrink: 0; }
    .salary-error {
      font-size: 11px; color: var(--red-pale); margin-top: 5px; display: none;
    }
    .salary-error.visible { display: block; }

    /* === MAIN LAYOUT === */
    .page-shell {
      max-width: 1380px; margin: 0 auto;
      padding: 0 24px 80px;                 /* match nav padding */
      position: relative; z-index: 2;
    }

    /* === HERO === */
    .hero {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 56px; align-items: center;
      padding: 72px 0 56px;
    }
    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(200,57,42,0.08); border: 1px solid rgba(200,57,42,0.22);
      border-radius: 4px; padding: 5px 12px;
      font-size: 11px; font-weight: 700; color: var(--red-pale);
      margin-bottom: 24px; letter-spacing: 0.08em; text-transform: uppercase;
    }
    .hero-eyebrow i { font-size: 6px; }
    .hero-h1 {
      font-family: var(--font-display);
      font-size: clamp(40px, 5.2vw, 70px);
      font-weight: 700; line-height: 1.08;
      letter-spacing: -0.01em; margin-bottom: 20px;
    }
    .hero-h1 .red {
      color: var(--red-vivid);
      font-style: italic;
    }
    .hero-h1 .dim { color: rgba(245,240,238,0.82); }
    body.light .hero-h1 .dim { color: rgba(26,10,9,0.68); }
    body.light .hero-h1 .red { color: var(--red-mid); }
    body.light .hero-sub { color: #4A2828; }
    body.light .hero-eyebrow { background: rgba(200,57,42,0.07); border-color: rgba(200,57,42,0.25); color: var(--red-mid); }
    .hero-sub { font-size: 16px; color: #B0A0A0; max-width: 460px; line-height: 1.7; margin-bottom: 32px; font-weight: 400; }

    /* Search */
    .search-bar {
      display: flex; align-items: center;
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 8px; overflow: hidden; transition: 0.25s;
      max-width: 520px;
    }
    .search-bar:focus-within {
      border-color: var(--red-vivid);
      box-shadow: 0 0 0 3px rgba(200,57,42,0.12), 0 4px 20px rgba(0,0,0,0.25);
    }
    .search-bar > i { padding: 0 14px; color: var(--text-muted); font-size: 14px; flex-shrink: 0; }
    .search-bar button i { color: inherit; }
    .search-bar input {
      flex: 1; padding: 14px 0; min-width: 0;
      background: none; border: none; outline: none;
      font-family: var(--font-body); font-size: 14px; color: var(--text-light);
    }
    .search-bar input::placeholder { color: var(--text-muted); }
    .search-bar button {
      margin: 5px; padding: 9px 18px; border-radius: 6px;
      background: var(--red-vivid);
      border: none; color: #fff; font-family: var(--font-body); font-size: 13px; font-weight: 700;
      cursor: pointer; transition: 0.2s; white-space: nowrap; flex-shrink: 0; letter-spacing: 0.02em;
    }
    .search-bar button:hover { background: var(--red-bright); }

    /* Stats */
    .hero-stats { display: flex; gap: 28px; margin-top: 28px; flex-wrap: wrap; }
    .hero-stat-num {
      font-family: var(--font-body); font-size: 26px; font-weight: 800;
      color: var(--text-light); line-height: 1;
    }
    .hero-stat-num span { color: var(--red-bright); }
    .hero-stat-label { font-size: 11px; color: #927C7A; margin-top: 4px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; }
    .stat-sep { width: 1px; background: var(--soil-line); align-self: stretch; }

    /* Light mode stat overrides */
    body.light .hero-stat-num { color: #1A0A09; }
    body.light .hero-stat-num span { color: var(--red-vivid); }
    body.light .hero-stat-label { color: #7A5555; }

    /* Colony visual */
    .hero-right { position: relative; }
    .colony-visual {
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 12px; padding: 24px; position: relative; overflow: hidden;
    }
    .colony-visual::before {
      content: ''; position: absolute; top: 0; right: 0; width: 160px; height: 160px;
      background: radial-gradient(circle, rgba(200,57,42,0.1), transparent 70%);
      pointer-events: none;
    }
    .cv-label { font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; }
    .mini-job-card {
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      border-radius: 8px; padding: 13px 14px;
      margin-bottom: 8px; transition: 0.2s; cursor: pointer;
      display: flex; align-items: center; gap: 12px;
    }
    .mini-job-card:last-of-type { margin-bottom: 0; }
    .mini-job-card:hover { border-color: var(--red-mid); background: rgba(200,57,42,0.05); }
    .mini-company-dot {
      width: 36px; height: 36px; border-radius: 8px;
      background: linear-gradient(135deg, var(--red-vivid), var(--red-deep));
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; flex-shrink: 0; color: #fff;
    }
    .mini-job-title { font-size: 13px; font-weight: 600; color: var(--text-light); margin-bottom: 2px; }
    .mini-job-company { font-size: 11px; color: var(--text-muted); }
    .mini-job-badge {
      font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 4px;
      background: rgba(200,57,42,0.1); color: var(--red-pale);
      border: 1px solid rgba(200,57,42,0.18); margin-left: auto;
      max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .cv-footer {
      margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--soil-line);
      display: flex; align-items: center; justify-content: space-between;
    }
    .cv-footer-text { font-size: 11px; color: var(--text-muted); }
    .cv-footer-link {
      font-size: 12px; font-weight: 600; color: var(--red-pale);
      cursor: pointer; background: none; border: none; font-family: var(--font-body);
    }

    /* === CONTENT LAYOUT === */
    .content-layout {
      display: grid; grid-template-columns: 244px 1fr;
      gap: 28px;
    }

    /* === SIDEBAR === */
    .sidebar { position: sticky; top: 72px; align-self: start; overflow: visible; z-index: 10; }
    .filter-sidebar {
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 12px; padding: 18px; overflow: visible;
    }
    .fs-title {
      font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--text-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 7px;
    }
    .fs-title i { color: var(--red-bright); }
    .fs-section-label {
      font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase;
      color: var(--text-muted); margin-bottom: 10px;
    }
    .fs-divider { height: 1px; background: var(--soil-line); margin: 16px 0; }
    .fs-reset {
      width: 100%; padding: 9px; border-radius: 7px; background: transparent;
      border: 1px solid var(--soil-line); color: var(--text-muted);
      font-family: var(--font-body); font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.18s;
    }
    .fs-reset:hover { border-color: var(--red-vivid); color: var(--red-pale); }
    .fs-text-input {
      width: 100%; padding: 9px 12px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); border-radius: 7px;
      font-family: var(--font-body); font-size: 12px; color: var(--text-mid);
      outline: none; transition: border-color 0.2s, box-shadow 0.2s;
    }
    .fs-text-input::placeholder { color: var(--text-muted); }
    .fs-text-input:focus { border-color: var(--red-vivid); box-shadow: 0 0 0 2px rgba(209,61,44,0.14); }
    .fs-select {
      width: 100%; padding: 9px 12px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); border-radius: 7px;
      font-family: var(--font-body); font-size: 12px; color: var(--text-mid);
      outline: none; transition: border-color 0.2s, box-shadow 0.2s;
      -webkit-appearance: none; appearance: none; cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 11px center; background-size: 10px 6px; padding-right: 28px;
    }
    .fs-select:hover { border-color: var(--red-mid); }
    .fs-select:focus { border-color: var(--red-vivid); box-shadow: 0 0 0 2px rgba(209,61,44,0.14); }
    .fs-select option { background: var(--soil-card); color: var(--text-mid); }

    /* Light-mode overrides for sidebar */
    body.light .filter-sidebar { background: #FFFFFF; border-color: #E0CECA; }
    body.light .fs-text-input { background: #F5EEEC; border-color: #E0CECA; color: #1A0A09; }
    body.light .fs-text-input::placeholder { color: #B09090; }
    body.light .fs-select { background: #F5EEEC; border-color: #E0CECA; color: #4A2828; }
    body.light .fs-select:hover { background-color: #FEF0EE; }
    body.light .fs-select option { background: #FFFFFF; color: #4A2828; }

    /* === MAIN === */
    .sec-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .sec-title {
      font-family: var(--font-display); font-size: 20px; font-weight: 700;
      color: var(--text-light); display: flex; align-items: center; gap: 10px;
      letter-spacing: 0.01em;
    }
    .sec-title i { color: var(--red-bright); font-size: 16px; }
    .sec-count {
      font-size: 11px; font-weight: 600; color: var(--text-muted);
      background: var(--soil-hover); padding: 2px 9px; border-radius: 4px;
      letter-spacing: 0.04em;
    }
    .see-more {
      font-size: 12px; font-weight: 600; color: var(--red-pale);
      cursor: pointer; background: none; border: none; font-family: var(--font-body);
      display: flex; align-items: center; gap: 4px; transition: 0.15s;
      letter-spacing: 0.02em;
    }
    .see-more:hover { color: var(--red-bright); }

    /* Featured */
    .featured-scroll {
      display: flex; gap: 14px; overflow-x: auto;
      padding: 8px 6px 24px 6px;
      margin: -8px -6px 32px -6px;
      scrollbar-width: none;
    }
    .featured-scroll::-webkit-scrollbar { display: none; }
    .featured-card {
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 14px; padding: 22px; min-width: 258px; max-width: 258px;
      cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden; flex-shrink: 0;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .featured-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--red-vivid), var(--red-bright));
    }
    .featured-card:hover {
      border-color: rgba(209,61,44,0.4); transform: translateY(-4px);
      box-shadow: 0 20px 48px rgba(0,0,0,0.15), 0 4px 12px rgba(209,61,44,0.1);
    }
    .fc-badge {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--amber); background: var(--amber-dim); border: 1px solid rgba(212,148,58,0.22);
      padding: 2px 7px; border-radius: 3px; margin-bottom: 14px;
    }
    .fc-icon {
      width: 40px; height: 40px; border-radius: 10px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); display: flex; align-items: center;
      justify-content: center; font-size: 18px; margin-bottom: 14px; color: var(--red-bright);
    }
    .fc-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text-light); margin-bottom: 4px; line-height: 1.3; }
    .fc-company { font-size: 12px; color: var(--red-pale); font-weight: 600; margin-bottom: 14px; }
    .fc-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 14px; }
    .chip {
      font-size: 11px; font-weight: 500; padding: 3px 8px; border-radius: 4px;
      background: var(--soil-hover); color: #A09090; border: 1px solid var(--soil-line);
      letter-spacing: 0.02em;
    }
    .fc-footer {
      display: flex; align-items: center; justify-content: space-between;
      padding-top: 14px; border-top: 1px solid var(--soil-line);
    }
    .fc-salary {
      font-family: var(--font-body); font-size: 14px; font-weight: 700;
      color: var(--text-light); letter-spacing: -0.01em;
    }

    /* Companies */
    .companies-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 40px; }
    .company-pill {
      display: flex; flex-direction: column; gap: 0;
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 14px; overflow: hidden;
      transition: all 0.22s; cursor: pointer;
    }
    .company-pill:hover {
      border-color: rgba(209,61,44,0.45); background: var(--soil-hover);
      transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.18);
    }
    .cp-top { display: flex; align-items: center; gap: 14px; padding: 18px 20px 14px; }
    .cp-logo {
      width: 48px; height: 48px; border-radius: 10px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); display: flex; align-items: center; justify-content: center;
      color: var(--red-vivid); font-size: 18px; flex-shrink: 0; overflow: hidden;
    }
    .cp-logo img { width: 100%; height: 100%; object-fit: cover; }
    .cp-name { font-size: 14px; font-weight: 700; color: var(--text-light); line-height: 1.25; }
    .cp-industry { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
    .cp-bio { font-size: 12px; color: var(--text-mid); line-height: 1.5; padding: 0 20px 14px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .cp-footer { display: flex; align-items: center; justify-content: space-between; padding: 10px 20px; border-top: 1px solid var(--soil-line); }
    .cp-roles { font-size: 12px; color: var(--amber); font-weight: 600; }
    .cp-view { font-size: 11px; color: var(--red-pale); font-weight: 600; display: flex; align-items: center; gap: 4px; }

    /* Job list */
    .job-list { display: flex; flex-direction: column; gap: 8px; }
    .job-row {
      background: var(--soil-card);
      border: 1px solid var(--soil-line);
      border-radius: 12px; padding: 22px 24px;
      cursor: pointer; transition: all 0.18s;
      display: grid; grid-template-columns: 1fr auto;
      gap: 16px; align-items: center; position: relative;
    }
    .job-row:hover {
      border-color: rgba(209,61,44,0.5);
      background: var(--soil-hover);
      transform: translateX(2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    }
    .jr-top { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
    .jr-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text-light); }
    .jr-new {
      font-size: 10px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
      color: var(--red-pale); background: rgba(200,57,42,0.1); border: 1px solid rgba(200,57,42,0.2);
      padding: 2px 7px; border-radius: 3px;
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
    .jr-meta {
      display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
      font-size: 12px; color: #927C7A; margin-bottom: 8px;
    }
    .jr-meta span { display: flex; align-items: center; gap: 4px; }
    .jr-meta i { font-size: 10px; color: var(--red-bright); }
    .jr-company { color: var(--red-pale); font-weight: 600; }
    .jr-chips { display: flex; gap: 5px; flex-wrap: wrap; }
    .job-row-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
    .jr-salary { font-family: var(--font-body); font-size: 14px; font-weight: 700; color: var(--text-light); white-space: nowrap; letter-spacing: -0.01em; }
    .jr-actions { display: flex; gap: 7px; align-items: center; }
    .jr-apply {
      padding: 7px 16px; border-radius: 6px;
      background: var(--red-vivid);
      border: none; color: #fff; font-size: 12px; font-weight: 700;
      cursor: pointer; font-family: var(--font-body); transition: 0.2s; letter-spacing: 0.02em;
    }
    .jr-apply:hover { background: var(--red-bright); }

    /* === MODAL === */
    .modal-overlay {
      display: none; position: fixed; inset: 0; z-index: 500;
      background: rgba(0,0,0,0.82); backdrop-filter: blur(8px);
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 12px; padding: 32px; max-width: 560px; width: 92%;
      position: relative; animation: modalIn 0.2s ease;
      box-shadow: 0 40px 80px rgba(0,0,0,0.6); max-height: 88vh; overflow-y: auto;
    }
    @keyframes modalIn { from { opacity:0; transform: scale(0.97) translateY(8px); } to { opacity:1; transform: scale(1); } }
    .modal-close {
      position: absolute; top: 18px; right: 18px;
      width: 30px; height: 30px; border-radius: 6px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      color: var(--text-muted); font-size: 13px;
      display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s;
    }
    .modal-close:hover { color: var(--text-light); border-color: var(--red-mid); }

    /* === COMPANY PREVIEW === */
    .cp-modal-header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
    .cp-modal-logo {
      width: 60px; height: 60px; border-radius: 12px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); display: flex; align-items: center; justify-content: center;
      color: var(--red-vivid); font-size: 22px; flex-shrink: 0; overflow: hidden;
    }
    .cp-modal-logo img { width: 100%; height: 100%; object-fit: cover; }
    .cp-modal-name { font-family: var(--font-display); font-size: 20px; font-weight: 700; color: var(--text-light); line-height: 1.2; }
    .cp-modal-industry { font-size: 13px; color: var(--text-muted); margin-top: 3px; }
    .cp-modal-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; }
    .cp-modal-meta .chip { font-size: 12px; }
    .cp-modal-about { font-size: 14px; color: var(--text-mid); line-height: 1.7; margin-bottom: 20px; }
    .cp-modal-jobs-label { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
    .cp-modal-job { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: 8px; background: var(--soil-hover); border: 1px solid var(--soil-line); margin-bottom: 6px; cursor: pointer; transition: 0.15s; }
    .cp-modal-job:hover { border-color: rgba(209,61,44,0.4); }
    .cp-modal-job-title { font-size: 13px; font-weight: 600; color: var(--text-light); }
    .cp-modal-job-info { font-size: 11px; color: var(--text-muted); }
    .cp-modal-cta { margin-top: 18px; display: flex; gap: 8px; }
    .cp-modal-cta button { flex: 1; padding: 10px; border-radius: 8px; font-family: var(--font-body); font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.15s; }
    .cp-modal-cta .btn-primary { background: var(--red-vivid); border: none; color: #fff; }
    .cp-modal-cta .btn-primary:hover { background: var(--red-bright); }
    .cp-modal-cta .btn-secondary { background: transparent; border: 1px solid var(--soil-line); color: var(--text-muted); }
    .cp-modal-cta .btn-secondary:hover { border-color: var(--red-mid); color: var(--text-light); }

    /* Light mode for company preview */
    body.light .cp-modal-logo { background: #F5E8E6; border-color: #DFC0BB; }
    body.light .cp-modal-job { background: #F9F5F4; border-color: #E0CECA; }
    body.light .cp-modal-job:hover { border-color: rgba(209,61,44,0.4); }

    /* === TOAST — handled by includes/toast.php === */

    /* === FOOTER === */
    .footer {
      border-top: 1px solid var(--soil-line); padding: 28px 24px;
      max-width: 1380px; margin: 0 auto;
      display: flex; align-items: center; justify-content: space-between;
      color: var(--text-muted); font-size: 12px;
      position: relative; z-index: 2; flex-wrap: wrap; gap: 12px;
    }
    .footer-logo { font-family: var(--font-display); font-weight: 700; color: var(--red-pale); font-size: 16px; letter-spacing: 0.02em; }

    /* === LIGHT THEME === */
    body.light {
      --soil-dark: #FAF5F4;
      --soil-card: #FFFFFF;
      --soil-hover: #FEF0EE;
      --soil-line: #E8D0CC;
      --text-light: #1A0A09;
      --text-mid: #4A2828;
      --text-muted: #7A5555;
      --amber-dim: #FFF4E0;
      --amber: #B8620A;
    }
    body.light .navbar {
      background: rgba(255, 253, 252, 0.98);
      border-bottom-color: #D4B0AB;
      box-shadow: 0 1px 0 rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.08);
    }

    /* Hero heading already handled above */
    body.light .chip {
      background: #F5E8E6;
      border-color: #DFC0BB;
      color: #5A3030;
    }

    /* Job row salary — needs strong contrast in light */

    /* Featured badge amber in light */
    body.light .fc-badge {
      background: #FFF0CC;
      border-color: rgba(184,98,10,0.3);
      color: #B8620A;
    }

    /* jr-new (Featured badge on list) */
    body.light .jr-new {
      background: rgba(200,57,42,0.1);
      border-color: rgba(200,57,42,0.3);
      color: var(--red-deep);
    }

    /* Sidebar filter inputs */
    body.light .filter-section-label { color: #7A5555; }

    /* Dropdown panel */
    /* Colony visual (hero right card) */
    body.light .colony-visual { background: #FFFFFF; border-color: #E8D0CC; }
    body.light .mini-job-card { background: #FEF0EE; border-color: #E8D0CC; }
    body.light .mini-job-title { color: #1A0A09; }

    /* Section count badge */
    body.light .sec-count { background: #F5E8E6; color: #7A5555; }

    /* Featured card icon */
    body.light .fc-icon { background: #F5E8E6; border-color: #DFC0BB; }

    /* Company pill */
    body.light .company-pill { background: #FFFFFF; border-color: #E0CECA; }
    body.light .company-pill:hover { background: #FEF0EE; border-color: rgba(209,61,44,0.4); }
    body.light .cp-logo { background: #F5E8E6; border-color: #DFC0BB; }
    body.light .cp-industry { color: #7A5555; }
    body.light .cp-bio { color: #4A2828; }
    body.light .cp-footer { border-color: #E0CECA; }
    body.light .cp-roles { color: #B06820; }
    body.light .cp-view { color: var(--red-mid); }

    /* Search bar */
    body.light .search-bar { background: #FFFFFF; border-color: #DFC0BB; }
    body.light .search-bar input { color: #1A0A09; }

    /* Mobile menu */
    body.light .mobile-menu {
      background: rgba(250,245,244,0.97);
      border-color: #E8D0CC;
    }
    body.light .mobile-link { color: #4A2828; }
    body.light .mobile-link:hover { background: #FEF0EE; color: #1A0A09; }
    body.light .mobile-divider { background: #E8D0CC; }
    /* === LIGHT MODE — hardcoded color overrides === */
    body.light .logo-text { color: #1A0A09; }
    body.light .logo-text span { color: var(--red-vivid); }
    body.light .nav-link { color: #5A4040; }
    body.light .nav-link:hover { color: #1A0A09; background: #FEF0EE; }
    body.light .sec-title { color: #1A0A09; }
    body.light .fc-title { color: #1A0A09; }
    body.light .jr-title { color: #1A0A09; }
    body.light .jr-salary { color: #1A0A09; }
    body.light .fc-salary { color: #1A0A09; }
    body.light .featured-card {
      background: #FFFFFF;
      border-color: #E0CECA;
      box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    }
    body.light .featured-card:hover {
      border-color: rgba(209,61,44,0.35);
      box-shadow: 0 16px 40px rgba(0,0,0,0.1), 0 4px 12px rgba(209,61,44,0.08);
    }
    body.light .fc-company { color: var(--red-mid); }
    body.light .fc-footer { border-top-color: #E0CECA; }
    body.light .chip {
      background: #F5EEEC;
      border-color: #E0CECA;
      color: #5A3838;
    }
    body.light .hero-stat-num { color: #1A0A09; }
    body.light .hero-stat-num span { color: var(--red-vivid); }
    body.light .hero-stat-label { color: #7A5555; }
    body.light .stat-sep { background: #E0CECA; }

    /* Job row light mode */
    body.light .job-row { background: #FFFFFF; border-color: #E0CECA; }
    body.light .job-row:hover { background: #FEF0EE; border-color: rgba(209,61,44,0.4); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

    /* === EMPTY STATE === */
    .empty-state { text-align: center; padding: 56px 20px; color: var(--text-muted); }
    .empty-state i { font-size: 32px; margin-bottom: 14px; display: block; color: var(--soil-line); }
    .empty-state p { font-size: 14px; }

    /* === ANIMATIONS === */
    @keyframes fadeUp { from { opacity:0; transform: translateY(14px); } to { opacity:1; transform: translateY(0); } }
    .anim { animation: fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay: 0.05s; }
    .anim-d2 { animation-delay: 0.1s; }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background: var(--soil-line); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--red-deep); }

    /* ===========================
       RESPONSIVE BREAKPOINTS
       =========================== */

    /* Tablet: hide hero right, switch to 1-col content */
    @media (max-width: 1060px) {
      .hero { grid-template-columns: 1fr; }
      .hero-right { display: none; }
      .content-layout { grid-template-columns: 1fr; }
      .sidebar { position: static; }
      /* show sidebar filters in a horizontal wrap */
      .filter-sidebar { border-radius: 14px; display: flex; flex-wrap: wrap; padding: 12px; gap: 8px; }
      .fs-title { width: 100%; flex: 0 0 100%; }
      .fs-section { display: inline-flex; flex-direction: column; gap: 4px; min-width: 140px; }
      .fs-divider { display: none; }
      .fs-reset { margin-top: 4px; }
    }

    /* Small tablet / large phone */
    @media (max-width: 760px) {
      html, body { overflow-x: hidden; max-width: 100vw; }
      .page-shell, .content-layout, .main-content, section, .container { max-width: 100%; overflow-x: hidden; }
      table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; white-space: nowrap; }
      .modal-box { width: 100% !important; max-width: 100vw !important; margin: 0 !important; border-radius: 12px 12px 0 0 !important; position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; top: auto !important; max-height: 90vh; overflow-y: auto; }

      .nav-links { display: none; }
      .hamburger { display: flex; }
      .nav-inner { padding: 0 10px; gap: 4px; }
      .nav-right { gap: 4px; flex-shrink: 0; overflow: hidden; }
      .nav-right #themeToggle { display: none !important; }
      /* Hide login/register from navbar on mobile — they move into burger menu */
      .nav-right .btn-ghost,
      .nav-right .btn-red { display: none !important; }
      #themeToggleMobile { display: flex !important; }
      .theme-btn { width: 32px; height: 32px; font-size: 13px; }
      .hamburger { width: 32px; height: 32px; font-size: 13px; }

      /* Collapsible filter on mobile */
      .filter-toggle-bar {
        display: flex; align-items: center; justify-content: space-between;
        cursor: pointer; padding: 2px 0 12px; user-select: none;
      }
      .filter-toggle-bar .fth-label {
        font-size: 11px; font-weight: 700; letter-spacing: 0.08em;
        text-transform: uppercase; color: var(--text-muted);
        display: flex; align-items: center; gap: 7px;
      }
      .filter-toggle-bar .fth-label i { color: var(--red-bright); }
      .filter-toggle-bar .fth-chevron {
        font-size: 10px; color: var(--text-muted);
        transition: transform 0.25s ease;
      }
      .filter-toggle-bar.expanded .fth-chevron { transform: rotate(180deg); }
      /* Filter body: collapsed by default on mobile */
      .filter-body {
        overflow: hidden;
        max-height: 0;
        transition: max-height 0.35s ease;
      }
      .filter-body.expanded { max-height: 9999px; }
      /* Hide old static fs-title on mobile (replaced by toggle bar) */
      .fs-title { display: none; }

      .page-shell { padding: 0 16px 60px; }

      .hero { padding: 40px 0 28px; }
      .hero-h1 { letter-spacing: -1px; }
      .hero-stats { gap: 0; flex-direction: row; flex-wrap: nowrap; align-items: stretch; }
      .hero-stat { flex: 1; text-align: center; }
      .stat-sep { display: block; width: 1px; background: var(--soil-line); margin: 0 16px; flex-shrink: 0; }

      .companies-row {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scroll-snap-type: x mandatory;
        gap: 10px;
        padding-bottom: 6px;
        scrollbar-width: none;
      }
      .companies-row::-webkit-scrollbar { display: none; }
      .company-pill { min-width: calc(100% - 10px); flex-shrink: 0; scroll-snap-align: start; }
      .cp-top { padding: 14px 16px 10px; }
      .cp-bio { padding: 0 16px 10px; }
      .cp-footer { padding: 8px 16px; }

      .job-row { grid-template-columns: 1fr; gap: 10px; }
      .job-row-right { flex-direction: row; align-items: center; justify-content: space-between; }
      .job-description-preview, .card-description { display: none; }
      .job-meta, .job-tags { display: flex; flex-wrap: nowrap; overflow-x: auto; gap: 6px; scrollbar-width: none; padding-bottom: 4px; }
      .job-meta::-webkit-scrollbar { display: none; }
      .job-actions, .card-actions { flex-direction: row; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
      .job-actions .btn, .job-actions button, .card-actions .btn, .card-actions button { flex: 1; min-width: 80px; font-size: 12px; padding: 7px 10px; text-align: center; justify-content: center; }

      .footer { flex-direction: column; text-align: center; padding: 20px 16px; }

      .search-bar { max-width: 100%; }
      .search-bar input { min-width: 0; }

      .jr-salary { white-space: normal; word-break: break-word; font-size: 13px; }
      .jr-top { flex-wrap: wrap; }
      .job-row-right { min-width: 0; overflow: hidden; }

      .filter-sidebar { padding: 20px 16px; }
      .filter-sidebar .fs-section { display: flex; flex-direction: column; width: 100%; min-width: 0; box-sizing: border-box; margin-bottom: 18px; }
      .filter-sidebar .fs-section-label { margin-bottom: 8px; margin-top: 4px; }
      .filter-sidebar .fs-divider { margin: 4px 0 18px; }
      .filter-sidebar .fs-select,
      .filter-sidebar .fs-text-input { width: 100%; box-sizing: border-box; padding: 11px 14px; font-size: 13px; }
      .filter-sidebar .ms-wrap,
      .filter-sidebar .ms-trigger { width: 100%; box-sizing: border-box; }
      .filter-sidebar .ms-trigger { padding: 11px 14px; font-size: 13px; }
      .filter-sidebar .role-section { width: 100%; box-sizing: border-box; margin-top: 14px; }
      .filter-sidebar .fs-reset { padding: 12px; font-size: 13px; margin-top: 6px; }

      .featured-scroll { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; padding-bottom: 6px; padding-left: 0; margin-left: 0; }
      .featured-card { scroll-snap-align: start; box-sizing: border-box; min-width: 100%; max-width: 100%; flex-shrink: 0; }
    }

    /* Phone */
    @media (max-width: 480px) {
      .btn-red { font-size: 12px; padding: 7px 12px; }
      .hero-stats { flex-direction: row; flex-wrap: wrap; gap: 12px; }
      .search-bar { border-radius: 12px; }
    }

    /* === MS-WRAP MULTI-SELECT === */
    .ms-wrap { position:relative; }
    .ms-trigger { width:100%; display:flex; align-items:center; justify-content:space-between; background-color:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; padding:9px 12px; font-family:var(--font-body); font-size:13px; color:var(--text-mid); cursor:pointer; transition:border-color 0.2s, box-shadow 0.2s, background-color 0.2s; }
    .ms-trigger:hover { border-color:var(--red-mid); }
    .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); box-shadow:0 0 0 2px rgba(209,61,44,0.14); }
    .ms-trigger .ms-arrow { font-size:8px; color:var(--text-muted); transition:transform 0.2s; flex-shrink:0; }
    .ms-wrap.open .ms-trigger .ms-arrow { transform:rotate(180deg); }
    .ms-text { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ms-panel { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:7px; max-height:200px; overflow-y:auto; z-index:1050; box-shadow:0 8px 24px rgba(0,0,0,0.4); }
    .ms-wrap.open .ms-panel { display:block; }
    .ms-item { display:flex; align-items:center; gap:8px; padding:7px 12px; font-size:13px; color:var(--text-mid); cursor:pointer; transition:background-color 0.12s; user-select:none; }
    .ms-item:hover { background:var(--soil-hover); }
    .ms-item input[type="checkbox"] { width:14px; height:14px; accent-color:var(--red-vivid); cursor:pointer; flex-shrink:0; }
    .role-section { display:block; margin-top:8px; }
    .role-section-label { font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); margin:10px 0 6px; display:block; }
    body.light .ms-trigger { background-color:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .ms-panel { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 8px 24px rgba(0,0,0,0.1); }
    body.light .ms-item { color:#4A2828; }
    body.light .ms-item:hover { background:#FEF0EE; }
  </style>
</head>
<body>
<!-- Notification panel removed: public landing page has no logged-in user -->


<!-- Background -->
<div class="tunnel-bg">
  <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
    <g stroke="#C0392B" stroke-width="1.5" fill="none" opacity="0.6">
      <path d="M0 200 Q200 180 350 240 Q500 300 600 260 Q750 210 900 280 Q1050 350 1200 300 Q1320 260 1440 280"/>
      <path d="M0 450 Q150 430 300 490 Q500 560 650 510 Q800 460 950 530 Q1100 600 1300 550 Q1380 530 1440 540"/>
      <path d="M0 700 Q200 680 400 740 Q550 780 700 730 Q850 680 1000 750 Q1150 820 1440 780"/>
      <path d="M350 0 Q340 100 360 200 Q380 300 350 400 Q320 500 340 600 Q360 700 350 900"/>
      <path d="M720 0 Q710 150 730 300 Q750 450 720 600 Q690 750 710 900"/>
      <path d="M1100 0 Q1090 120 1110 250 Q1130 380 1100 520 Q1070 650 1090 900"/>
      <path d="M0 100 Q200 200 350 240"/>
      <path d="M600 260 Q720 310 900 280"/>
      <path d="M350 240 Q430 320 400 450"/>
      <path d="M720 300 Q780 420 720 530"/>
    </g>
    <g fill="#E54C3A" opacity="0.5">
      <circle cx="350" cy="240" r="4"/><circle cx="600" cy="260" r="3"/>
      <circle cx="900" cy="280" r="4"/><circle cx="1200" cy="300" r="3"/>
      <circle cx="300" cy="490" r="3"/><circle cx="650" cy="510" r="4"/>
      <circle cx="1000" cy="530" r="3"/><circle cx="400" cy="740" r="3"/>
      <circle cx="700" cy="730" r="4"/><circle cx="1100" cy="250" r="3"/>
      <circle cx="720" cy="150" r="4"/>
    </g>
  </svg>
</div>
<div class="glow-orb glow-orb-1"></div>
<div class="glow-orb glow-orb-2"></div>

<!-- NAVBAR -->
<nav class="navbar">
  <div class="nav-inner">
    <a class="logo" href="#">
      <div class="logo-icon"></div>
      <span class="logo-text">Ant<span>Careers</span></span>
    </a>

    <!-- Desktop nav links -->
    <div class="nav-links">
      <a class="nav-link" data-scroll="featured"><i class="fas fa-star"></i> Featured</a>
      <a class="nav-link" data-scroll="companies"><i class="fas fa-building"></i> Companies</a>
      <a class="nav-link" data-scroll="jobs"><i class="fas fa-list"></i> All Jobs</a>
    </div>

    <!-- Desktop right buttons -->
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <button class="btn-ghost" id="loginBtn" type="button" onclick="window.location.href='auth/antcareers_login.php'"><i class="fas fa-key"></i> Log in</button>
      <button class="btn-red" id="signupBtn" type="button" onclick="window.location.href='auth/antcareers_signup.php'">Get started</button>
    </div>

    <!-- Hamburger (mobile only) -->
    <button class="theme-btn" id="themeToggleMobile"><i class="fas fa-moon"></i></button>
    <div class="hamburger" id="hamburger"><i class="fas fa-bars"></i></div>
  </div>
</nav>

<!-- Mobile slide-down menu -->
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link" data-scroll="featured" data-close-mobile><i class="fas fa-star"></i> Featured jobs</a>
  <a class="mobile-link" data-scroll="companies" data-close-mobile><i class="fas fa-building"></i> Companies</a>
  <a class="mobile-link" data-scroll="jobs" data-close-mobile><i class="fas fa-list"></i> All Jobs</a>
  <div class="mobile-divider"></div>
  <div class="mobile-auth">
    <button class="btn-ghost" type="button" onclick="window.location.href='auth/antcareers_login.php'">Log in</button>
    <button class="btn-red" type="button" onclick="window.location.href='auth/antcareers_signup.php'">Get started</button>
  </div>
</div>

<!-- PAGE -->
<div class="page-shell">

  <!-- HERO -->
  <section class="hero anim">
    <div class="hero-left">
      <div class="hero-eyebrow">
        <i class="fas fa-circle"></i> <?php echo count($indexJobs); ?>+ verified roles across the Philippines
      </div>
      <h1 class="hero-h1">
        <span class="dim">Your career,</span><br>
        <span class="red">elevated.</span><br>
        <span class="dim">Start here.</span>
      </h1>
      <p class="hero-sub">
        Ant Careers connects driven professionals with companies that invest in people. Find your next role — and mean it.
      </p>
      <div class="search-bar">
        <i class="fas fa-search"></i>
        <input type="text" id="keywordInput" placeholder="Job title, skill, or company…">
        <button id="searchBtn"><i class="fas fa-arrow-right"></i> Search</button>
      </div>
      <div class="hero-stats">
        <div class="hero-stat">
          <div class="hero-stat-num"><?php echo count($indexJobs);?></div>
          <div class="hero-stat-label">Live jobs</div>
        </div>
        <div class="stat-sep"></div>
        <div class="hero-stat">
          <div class="hero-stat-num"><?php echo count($indexCompanies);?></div>
          <div class="hero-stat-label">Companies hiring</div>
        </div>
      </div>
    </div>
    <div class="hero-right anim anim-d2">
      <div class="colony-visual">
        <div class="cv-label">🔥 Trending this week</div>
        <div id="heroMiniJobs"></div>
        <div class="cv-footer">
          <span class="cv-footer-text">Updated just now</span>
          <button class="cv-footer-link" data-scroll="featured">View all featured →</button>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTENT -->
  <div class="content-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar anim anim-d1">
      <div class="filter-sidebar">
        <!-- Static title (desktop only — replaced by toggle bar on mobile) -->
        <div class="fs-title"><i class="fas fa-sliders-h"></i> Filters</div>
        <!-- Toggle bar (mobile only) -->
        <div class="filter-toggle-bar" id="filterToggleBar">
          <span class="fth-label"><i class="fas fa-sliders-h"></i> FILTERS <span class="filter-active-badge" id="filterActiveBadge"></span></span>
          <i class="fas fa-chevron-down fth-chevron"></i>
        </div>

        <!-- Filter body: collapsible on mobile -->
        <div class="filter-body" id="filterBody">

        <div class="fs-section">
          <div class="fs-section-label">Industry</div>
          <div class="ms-wrap" id="msIndustry" data-default="All Industries">
            <button class="ms-trigger" type="button"><span class="ms-text">All Industries</span><i class="fas fa-chevron-down ms-arrow"></i></button>
            <div class="ms-panel">
              <label class="ms-item"><input type="checkbox" value="Accounting"><span>Accounting</span></label>
              <label class="ms-item"><input type="checkbox" value="Administration &amp; Office Support"><span>Administration &amp; Office Support</span></label>
              <label class="ms-item"><input type="checkbox" value="Advertising, Arts &amp; Media"><span>Advertising, Arts &amp; Media</span></label>
              <label class="ms-item"><input type="checkbox" value="Banking &amp; Financial Services"><span>Banking &amp; Financial Services</span></label>
              <label class="ms-item"><input type="checkbox" value="Call Centre &amp; Customer Service"><span>Call Centre &amp; Customer Service</span></label>
              <label class="ms-item"><input type="checkbox" value="CEO &amp; General Management"><span>CEO &amp; General Management</span></label>
              <label class="ms-item"><input type="checkbox" value="Community Services &amp; Development"><span>Community Services &amp; Development</span></label>
              <label class="ms-item"><input type="checkbox" value="Construction"><span>Construction</span></label>
              <label class="ms-item"><input type="checkbox" value="Consulting &amp; Strategy"><span>Consulting &amp; Strategy</span></label>
              <label class="ms-item"><input type="checkbox" value="Design &amp; Architecture"><span>Design &amp; Architecture</span></label>
              <label class="ms-item"><input type="checkbox" value="Education &amp; Training"><span>Education &amp; Training</span></label>
              <label class="ms-item"><input type="checkbox" value="Engineering"><span>Engineering</span></label>
              <label class="ms-item"><input type="checkbox" value="Farming, Animals &amp; Conservation"><span>Farming, Animals &amp; Conservation</span></label>
              <label class="ms-item"><input type="checkbox" value="Government &amp; Defence"><span>Government &amp; Defence</span></label>
              <label class="ms-item"><input type="checkbox" value="Healthcare &amp; Medical"><span>Healthcare &amp; Medical</span></label>
              <label class="ms-item"><input type="checkbox" value="Hospitality &amp; Tourism"><span>Hospitality &amp; Tourism</span></label>
              <label class="ms-item"><input type="checkbox" value="Human Resources &amp; Recruitment"><span>Human Resources &amp; Recruitment</span></label>
              <label class="ms-item"><input type="checkbox" value="Information &amp; Communication Technology"><span>Information &amp; Communication Technology</span></label>
              <label class="ms-item"><input type="checkbox" value="Insurance &amp; Superannuation"><span>Insurance &amp; Superannuation</span></label>
              <label class="ms-item"><input type="checkbox" value="Legal"><span>Legal</span></label>
              <label class="ms-item"><input type="checkbox" value="Manufacturing, Transport &amp; Logistics"><span>Manufacturing, Transport &amp; Logistics</span></label>
              <label class="ms-item"><input type="checkbox" value="Marketing &amp; Communications"><span>Marketing &amp; Communications</span></label>
              <label class="ms-item"><input type="checkbox" value="Mining, Resources &amp; Energy"><span>Mining, Resources &amp; Energy</span></label>
              <label class="ms-item"><input type="checkbox" value="Real Estate &amp; Property"><span>Real Estate &amp; Property</span></label>
              <label class="ms-item"><input type="checkbox" value="Retail &amp; Consumer Products"><span>Retail &amp; Consumer Products</span></label>
              <label class="ms-item"><input type="checkbox" value="Sales"><span>Sales</span></label>
              <label class="ms-item"><input type="checkbox" value="Science &amp; Technology"><span>Science &amp; Technology</span></label>
              <label class="ms-item"><input type="checkbox" value="Self Employment"><span>Self Employment</span></label>
              <label class="ms-item"><input type="checkbox" value="Sports &amp; Recreation"><span>Sports &amp; Recreation</span></label>
              <label class="ms-item"><input type="checkbox" value="Trades &amp; Services"><span>Trades &amp; Services</span></label>
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
          <div class="salary-row">
            <input type="number" id="salaryMinFilter" class="fs-text-input" placeholder="Min salary" min="0">
            <span class="sal-sep">–</span>
            <input type="number" id="salaryMaxFilter" class="fs-text-input" placeholder="Max salary" min="0">
          </div>
          <div class="salary-error" id="salaryError">Max salary must be greater than min salary</div>
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

        </div><!-- /filter-body -->
      </div>
    </aside>

    <!-- MAIN -->
    <main class="main-content">

      <div id="featured" class="anim">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-star"></i> Featured jobs</div>
          <button class="see-more" id="seeMoreFeatured">See all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="featured-scroll" id="featuredJobsContainer"></div>
      </div>

      <div id="companies" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-building"></i> Hiring now</div>
          <button class="see-more" id="seeMoreCompanies">All companies <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="companies-row" id="companiesGrid"></div>
      </div>

      <div id="jobs" class="anim anim-d2">
        <div class="sec-header">
          <div class="sec-title">
            <i class="fas fa-list-ul" id="jobsSectionIcon"></i>
            <span id="jobsSectionText">Live opportunities </span>
            <span class="sec-count" id="jobCount">0 jobs</span>
          </div>
          <button class="see-more" id="seeMoreJobs">Browse all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">Ant Careers</div>
  <div>© 2025 Ant Careers — Where careers take flight.</div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
    <span style="cursor:pointer;">Contact</span>
  </div>
</footer>

<!-- Modal -->
<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    <div id="modalBody"></div>
  </div>
</div>

<!-- Company Preview Modal -->
<div class="modal-overlay" id="companyPreviewModal">
  <div class="modal-box" style="max-width:520px;">
    <button class="modal-close" onclick="document.getElementById('companyPreviewModal').classList.remove('open')"><i class="fas fa-times"></i></button>
    <div id="companyPreviewBody"></div>
  </div>
</div>


<script>
  const jobsData = <?= $indexJobsJson ?>;

  const companies = <?= $indexCompaniesJson ?>;

  const featuredContainer = document.getElementById('featuredJobsContainer');
  const jobsContainer = document.getElementById('jobsContainer');
  const jobCount = document.getElementById('jobCount');
  const keywordInput = document.getElementById('keywordInput');
  const companiesGrid = document.getElementById('companiesGrid');
  const modal = document.getElementById('jobModal');
  const modalBody = document.getElementById('modalBody');

  // ── THEME ──
  function setTheme(t) {
    const icons = document.querySelectorAll('#themeToggle i, #themeToggleMobile i');
    if (t === 'light') {
      document.body.classList.add('light');
      icons.forEach(i => i.className = 'fas fa-sun');
      localStorage.setItem('ac-theme','light');
    } else {
      document.body.classList.remove('light');
      icons.forEach(i => i.className = 'fas fa-moon');
      localStorage.setItem('ac-theme','dark');
    }
  }
  ['themeToggle','themeToggleMobile'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () =>
      setTheme(document.body.classList.contains('light') ? 'dark' : 'light'));
  });
  const urlTheme = new URLSearchParams(window.location.search).get('theme');
  const savedTheme = urlTheme || localStorage.getItem('ac-theme');
  if (urlTheme) localStorage.setItem('ac-theme', urlTheme); // sync if came from login/signup
  if (savedTheme) { setTheme(savedTheme); } else { setTheme('light'); }

  // ── HELPERS ──
  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function getThemeParam() {
    return '?theme=' + (document.body.classList.contains('light') ? 'light' : 'dark');
  }

  // ── HAMBURGER ──
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobileMenu');
  hamburger.addEventListener('click', e => {
    e.stopPropagation();
    const open = mobileMenu.classList.toggle('open');
    hamburger.querySelector('i').className = open ? 'fas fa-times' : 'fas fa-bars';
  });
  document.addEventListener('click', e => {
    if (!mobileMenu.contains(e.target) && e.target !== hamburger) {
      mobileMenu.classList.remove('open');
      hamburger.querySelector('i').className = 'fas fa-bars';
    }
  });

  // mobile scroll links
  document.querySelectorAll('[data-close-mobile][data-scroll]').forEach(el => {
    el.addEventListener('click', () => { closeMobileMenu(); });
  });
  function closeMobileMenu() {
    mobileMenu.classList.remove('open');
    hamburger.querySelector('i').className = 'fas fa-bars';
  }

  // ── MULTI-SELECT HELPERS ─────────────────────────────────────────────────
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

  // ── MS-WRAP WIRING ────────────────────────────────────────────────────
  document.querySelectorAll('.ms-wrap').forEach(wrap => {
    const trigger = wrap.querySelector('.ms-trigger');
    if (!trigger) return;
    trigger.addEventListener('click', e => {
      e.stopPropagation();
      const wasOpen = wrap.classList.contains('open');
      document.querySelectorAll('.ms-wrap.open').forEach(w => w.classList.remove('open'));
      if (!wasOpen) wrap.classList.add('open');
    });
    wrap.querySelectorAll('input[type=checkbox]').forEach(cb => {
      cb.addEventListener('change', () => {
        updateMsLabel(wrap);
        if (wrap.id === 'msIndustry') updateRolePicker(getMsValues('msIndustry'));
        renderAllJobs();
      });
    });
  });
  document.addEventListener('click', e => {
    document.querySelectorAll('.ms-wrap.open').forEach(w => {
      if (!w.contains(e.target)) w.classList.remove('open');
    });
  });

  // ── FILTERING ──
  function isFiltering() {
    const kw = (keywordInput?.value || '').trim();
    const loc = (document.getElementById('locationKeyword')?.value || '').trim();
    const sLoc = document.getElementById('sidebarLocationFilter')?.value || '';
    const salMin = parseFloat(document.getElementById('salaryMinFilter')?.value) || 0;
    const salMax = parseFloat(document.getElementById('salaryMaxFilter')?.value) || 0;
    return !!(kw || loc || sLoc || salMin || salMax ||
      getMsValues('msIndustry').length || getMsValues('msJobRole').length ||
      getMsValues('msWorkType').length || getMsValues('msRemote').length ||
      getMsValues('msExperience').length || getMsValues('msListed').length);
  }

  function setSearchMode(active) {
    document.getElementById('featured').style.display = active ? 'none' : '';
    document.getElementById('companies').style.display = active ? 'none' : '';
    document.getElementById('jobsSectionIcon').className = active ? 'fas fa-search' : 'fas fa-list-ul';
    document.getElementById('jobsSectionText').textContent = active ? 'Search results ' : 'Live opportunities ';
  }
  function getFiltered() {
    const kw  = (keywordInput?.value || '').trim().toLowerCase();
    const locKw = (document.getElementById('locationKeyword')?.value || '').trim().toLowerCase();
    const sLoc = document.getElementById('sidebarLocationFilter')?.value || '';
    const salMin = parseFloat(document.getElementById('salaryMinFilter')?.value) || 0;
    const salMax = parseFloat(document.getElementById('salaryMaxFilter')?.value) || 0;
    const industries = getMsValues('msIndustry');
    const roles      = getMsValues('msJobRole');
    const jobTypes   = getMsValues('msWorkType');
    const setups     = getMsValues('msRemote');
    const exps       = getMsValues('msExperience');
    const dateDays   = getMsValues('msListed');

    // Salary validation
    const salErr = document.getElementById('salaryError');
    if (salMin > 0 && salMax > 0 && salMax <= salMin) {
      if (salErr) salErr.classList.add('visible');
      return [];
    }
    if (salErr) salErr.classList.remove('visible');

    return jobsData.filter(j => {
      if (kw && !`${j.title} ${j.company} ${j.description} ${(j.tags||[]).join(' ')}`.toLowerCase().includes(kw)) return false;
      if (sLoc && !j.location.toLowerCase().includes(sLoc.toLowerCase())) return false;
      if (locKw && !j.location.toLowerCase().includes(locKw)) return false;
      if (industries.length && !industries.includes(j.industry)) return false;
      if (roles.length) {
        const tl = j.title.toLowerCase();
        const matched = roles.some(role => {
          const rl = role.toLowerCase();
          if (tl.includes(rl)) return true;
          return rl.split(/[\s\-\/]+/).filter(p => p.length > 3).some(p => tl.includes(p));
        });
        if (!matched) return false;
      }
      if (jobTypes.length && !jobTypes.includes(j.jobType)) return false;
      if (setups.length && !setups.includes(j.workSetup)) return false;
      if (exps.length && !exps.includes(j.experience)) return false;
      // Salary range-overlap filter: show jobs where job.salaryMin <= userMax AND job.salaryMax >= userMin
      if (salMin > 0) {
        if (j.salaryMax && j.salaryMax < salMin) return false;
        if (!j.salaryMax && j.salaryMin && j.salaryMin < salMin) return false;
      }
      if (salMax > 0) {
        if (j.salaryMin && j.salaryMin > salMax) return false;
      }
      if (dateDays.length) {
        const maxDays = Math.max(...dateDays.map(d => parseInt(d)));
        const posted = new Date(j.createdRaw || j.postedDate);
        const cutoff = new Date();
        cutoff.setDate(cutoff.getDate() - maxDays);
        if (posted < cutoff) return false;
      }
      return true;
    });
  }

  // ── RENDER ──
  function renderFeatured() {
    featuredContainer.innerHTML = jobsData.filter(j => j.featured).map(j => `
      <div class="featured-card" onclick="showModal(${j.id})">
        <div class="fc-badge"><i class="fas fa-star"></i> Featured</div>
        <div class="fc-icon"><i class="fas ${j.icon||'fa-briefcase'}" style="color:var(--red-vivid);"></i></div>
        <div class="fc-title">${j.title}</div>
        <div class="fc-company">${j.company}</div>
        <div class="fc-chips">
          <span class="chip">${j.location}</span>
          <span class="chip">${j.jobType}</span>
          <span class="chip">${j.workSetup}</span>
          ${j.tags.slice(0,2).map(t=>`<span class="chip">${t}</span>`).join('')}
        </div>
        <div class="fc-footer">
          <div class="fc-salary">${j.salary}</div>
        </div>
      </div>`).join('');
  }

  function renderCompanies() {
    companiesGrid.innerHTML = companies.map(c => {
      const logoHtml = c.logo
        ? `<img src="${c.logo}" alt="${esc(c.name)}" onerror="this.parentNode.innerHTML='<i class=\\'fas fa-building\\'></i>'">`
        : `<i class="fas fa-building"></i>`;
      const bioText = c.bio ? esc(c.bio) : (c.about ? esc(c.about).substring(0, 90) : 'Company on AntCareers');
      return `
      <div class="company-pill" onclick="showCompanyPreview(${companies.indexOf(c)})">
        <div class="cp-top">
          <div class="cp-logo">${logoHtml}</div>
          <div>
            <div class="cp-name">${esc(c.name)}</div>
            ${c.industry ? `<div class="cp-industry">${esc(c.industry)}</div>` : ''}
          </div>
        </div>
        ${bioText ? `<div class="cp-bio">${bioText}</div>` : ''}
        <div class="cp-footer">
          <span class="cp-roles">${c.openRoles} open role${c.openRoles !== 1 ? 's' : ''}</span>
          <span class="cp-view">View <i class="fas fa-arrow-right"></i></span>
        </div>
      </div>`;
    }).join('');
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
    }
    return badges;
  }

  function renderAllJobs() {
    const filtering = isFiltering();
    updateActiveFilterBadge();
    setSearchMode(filtering);
    const filtered = getFiltered();
    const countEl = document.getElementById('jobCount');
    if (countEl) countEl.textContent = `${filtered.length} job${filtered.length !== 1?'s':''}`;
    if (!filtered.length) {
      jobsContainer.innerHTML = `<div class="empty-state"><i class="fas fa-search"></i><p>No jobs match your filters — try resetting.</p></div>`;
      if (filtering) document.getElementById('jobs').scrollIntoView({ behavior:'smooth', block:'start' });
      return;
    }
    jobsContainer.innerHTML = filtered.map((j,i) => `
      <div class="job-row" onclick="showModal(${j.id})" style="animation:fadeUp 0.3s ${i*0.035}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div class="jr-title">${j.title}</div>
            ${j.featured?'<span class="jr-new">Featured</span>':''}
            ${jobBadge(j)}
          </div>
          <div class="jr-meta">
            <span class="jr-company"><i class="fas fa-building"></i> ${j.company}</span>
            <span><i class="fas fa-map-marker-alt"></i> ${j.location}</span>
            <span><i class="fas fa-clock"></i> ${j.jobType}</span>
            <span><i class="fas fa-laptop-house"></i> ${j.workSetup}</span>
          </div>
          <div class="jr-chips">${j.tags.map(t=>`<span class="chip">${t}</span>`).join('')}</div>
        </div>
        <div class="job-row-right">
          <div class="jr-salary">${j.salary}</div>
          <div class="jr-actions">
            <button class="jr-apply" onclick="event.stopPropagation();startApplication(${j.id})">Apply</button>
          </div>
        </div>
      </div>`).join('');
    if (filtering) document.getElementById('jobs').scrollIntoView({ behavior:'smooth', block:'start' });
  }

  function filterByCompany(name) {
    if (keywordInput) { keywordInput.value = name; }
    renderAllJobs();
  }

  function showCompanyPreview(idx) {
    const c = companies[idx]; if (!c) return;
    const logoHtml = c.logo
      ? `<img src="${c.logo}" alt="${esc(c.name)}" onerror="this.parentNode.innerHTML='<i class=\\'fas fa-building\\'></i>'">`
      : `<i class="fas fa-building"></i>`;
    const aboutText = c.about ? esc(c.about) : (c.bio ? esc(c.bio) : '');
    const companyJobs = jobsData.filter(j => j.company === c.name).slice(0, 5);
    const jobsHtml = companyJobs.length ? companyJobs.map(j => `
      <div class="cp-modal-job" onclick="document.getElementById('companyPreviewModal').classList.remove('open'); showModal(${j.id})">
        <div>
          <div class="cp-modal-job-title">${esc(j.title)}</div>
          <div class="cp-modal-job-info">${esc(j.jobType)} · ${esc(j.location)}</div>
        </div>
        <div style="font-size:12px;font-weight:600;color:var(--amber);white-space:nowrap;">${esc(j.salary)}</div>
      </div>`).join('') : '<div style="text-align:center;padding:20px 0;color:var(--text-muted);font-size:13px;">No open positions right now</div>';

    document.getElementById('companyPreviewBody').innerHTML = `
      <div class="cp-modal-header">
        <div class="cp-modal-logo">${logoHtml}</div>
        <div>
          <div class="cp-modal-name">${esc(c.name)}</div>
          ${c.industry ? `<div class="cp-modal-industry">${esc(c.industry)}</div>` : ''}
        </div>
      </div>
      <div class="cp-modal-meta">
        ${c.size ? `<span class="chip"><i class="fas fa-users" style="color:var(--red-mid);margin-right:3px;"></i>${esc(c.size)}</span>` : ''}
        ${c.location ? `<span class="chip"><i class="fas fa-map-marker-alt" style="color:var(--red-mid);margin-right:3px;"></i>${esc(c.location)}</span>` : ''}
        <span class="chip"><i class="fas fa-briefcase" style="color:var(--red-mid);margin-right:3px;"></i>${c.openRoles} open role${c.openRoles !== 1 ? 's' : ''}</span>
      </div>
      ${aboutText ? `<div class="cp-modal-about">${aboutText}</div>` : ''}
      <div class="cp-modal-jobs-label">Open positions</div>
      ${jobsHtml}
      <div class="cp-modal-cta">
        <button class="btn-primary" onclick="window.location.href='auth/antcareers_signup.php'+getThemeParam()"><i class="fas fa-user-plus"></i> Sign up to connect</button>
      </div>`;
    document.getElementById('companyPreviewModal').classList.add('open');
  }

  // Close company preview modal
  document.getElementById('companyPreviewModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });

  function showModal(id) {
    const j = jobsData.find(x => x.id === id); if (!j) return;
    modalBody.innerHTML = `
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
        <div style="width:48px;height:48px;border-radius:8px;background:var(--red-vivid);display:flex;align-items:center;justify-content:center;font-size:19px;color:#fff;flex-shrink:0;">
          <i class="fas ${j.icon||'fa-briefcase'}"></i>
        </div>
        <div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:700;color:var(--text-light);line-height:1.15;">${j.title}</div>
          <div style="color:var(--red-pale);font-weight:600;font-size:13px;margin-top:3px;">${j.company}</div>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
        <span class="chip"><i class="fas fa-map-marker-alt" style="color:var(--red-mid);margin-right:3px;"></i>${j.location}</span>
        <span class="chip"><i class="fas fa-clock" style="color:var(--red-mid);margin-right:3px;"></i>${j.jobType}</span>
        <span class="chip"><i class="fas fa-chart-simple" style="color:var(--red-mid);margin-right:3px;"></i>${j.experience}</span>
        <span class="chip"><i class="fas fa-laptop-house" style="color:var(--red-mid);margin-right:3px;"></i>${j.workSetup}</span>
      </div>
      <div style="background:rgba(200,57,42,0.08);border:1px solid rgba(200,57,42,0.18);border-radius:6px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:baseline;gap:8px;">
        <span style="font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--text-light);">${j.salary}</span>
        <span style="font-size:11px;color:var(--text-muted);letter-spacing:0.05em;text-transform:uppercase;">per year</span>
      </div>
      <p style="color:var(--text-mid);font-size:14px;line-height:1.75;margin-bottom:18px;">${j.description}</p>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:26px;">${j.tags.map(t=>`<span class="chip">${t}</span>`).join('')}</div>
      <div style="display:flex;gap:9px;">
        <button onclick="startApplication(${j.id})"
          style="flex:1;padding:11px 16px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;letter-spacing:0.02em;">
          <i class="fas fa-paper-plane"></i> Create account to apply
        </button>
        <button onclick="document.getElementById('jobModal').classList.remove('open')"
          style="padding:11px 16px;border-radius:6px;background:transparent;border:1px solid var(--soil-line);color:var(--text-muted);font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;">
          Close
        </button>
      </div>`;
    modal.classList.add('open');
  }

  document.getElementById('closeModal').addEventListener('click', () => modal.classList.remove('open'));
  modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('open'); });

  // Scroll
  document.querySelectorAll('[data-scroll]').forEach(el => {
    el.addEventListener('click', () => {
      const target = document.getElementById(el.dataset.scroll);
      if (target) target.scrollIntoView({ behavior:'smooth' });
    });
  });

  // Auth / misc
  function goToSignup() {
    window.location.href = 'auth/antcareers_signup.php' + getThemeParam();
  }

  document.getElementById('seeMoreFeatured').addEventListener('click', goToSignup);
  document.getElementById('seeMoreCompanies').addEventListener('click', goToSignup);
  document.getElementById('seeMoreJobs').addEventListener('click', goToSignup);

  function startApplication(jobId) {
    goToSignup();
  }
  document.getElementById('loginBtn').addEventListener('click', () => window.location.href = 'auth/antcareers_login.php' + getThemeParam());
  document.getElementById('signupBtn').addEventListener('click', goToSignup);

  // Filter events
  document.getElementById('searchBtn').addEventListener('click', renderAllJobs);
  keywordInput.addEventListener('keyup', e => { if (e.key==='Enter') renderAllJobs(); });
  document.getElementById('locationKeyword')?.addEventListener('input', renderAllJobs);
  document.getElementById('sidebarLocationFilter')?.addEventListener('change', renderAllJobs);
  document.getElementById('salaryMinFilter')?.addEventListener('input', renderAllJobs);
  document.getElementById('salaryMaxFilter')?.addEventListener('input', renderAllJobs);

  // Active filter badge
  function updateActiveFilterBadge() {
    const badge = document.getElementById('filterActiveBadge');
    if (badge) badge.classList.toggle('visible', isFiltering());
  }

  document.getElementById('resetFiltersBtn').addEventListener('click', () => {
    keywordInput.value = '';
    document.querySelectorAll('.ms-wrap input[type=checkbox]').forEach(cb => cb.checked = false);
    document.querySelectorAll('.ms-wrap').forEach(updateMsLabel);
    updateRolePicker([]);
    const locKw = document.getElementById('locationKeyword');
    if (locKw) locKw.value = '';
    const salMin = document.getElementById('salaryMinFilter');
    if (salMin) salMin.value = '';
    const salMax = document.getElementById('salaryMaxFilter');
    if (salMax) salMax.value = '';
    const salErr = document.getElementById('salaryError');
    if (salErr) salErr.classList.remove('visible');
    const sLoc = document.getElementById('sidebarLocationFilter');
    if (sLoc) sLoc.value = '';
    renderAllJobs();
    document.querySelector('.hero').scrollIntoView({ behavior:'smooth', block:'start' });
  });

  // ── COLLAPSIBLE FILTER (mobile only) ──────────────────────────────────────
  const filterToggleBar = document.getElementById('filterToggleBar');
  const filterBody      = document.getElementById('filterBody');
  if (filterToggleBar && filterBody) {
    filterToggleBar.addEventListener('click', () => {
      const expanded = filterBody.classList.toggle('expanded');
      filterToggleBar.classList.toggle('expanded', expanded);
    });
  }

  // ── HERO MINI JOBS ──
  (function(){
    const el = document.getElementById('heroMiniJobs');
    if (!el) return;
    const top3 = jobsData.slice(0, 3);
    if (!top3.length) { el.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:12px;padding:12px;">No jobs posted yet.</div>'; return; }
    el.innerHTML = top3.map(j => `
      <div class="mini-job-card" onclick="showModal(${j.id})">
        <div class="mini-company-dot"><i class="fas ${j.icon||'fa-briefcase'}"></i></div>
        <div class="mini-job-info">
          <div class="mini-job-title">${j.title}</div>
          <div class="mini-job-company">${j.company} · ${j.location}</div>
        </div>
        <div class="mini-job-badge">${j.salary}</div>
      </div>`).join('');
  })();

  renderFeatured(); renderCompanies(); renderAllJobs();
</script>
<?php require_once __DIR__ . '/includes/toast.php'; ?>
</body>
</html>