<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/job_titles.php';

$db = getDB();
$indexJobs = [];
$indexCompanies = [];

try {
    $s = $db->prepare("
        SELECT j.id, j.title, j.location, j.job_type, j.setup AS work_setup,
               j.experience_level, j.industry, j.salary_min, j.salary_max,
               j.salary_currency, j.description, j.skills_required, j.created_at,
               COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company
        FROM jobs j
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'Active'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        ORDER BY j.created_at DESC LIMIT 50
    ");
    $s->execute();
    $rows = $s->fetchAll();
    $featCount = 0;
    foreach ($rows as $r) {
        $salMin = (float)($r['salary_min'] ?? 0);
        $salMax = (float)($r['salary_max'] ?? 0);
        $cur = ($r['salary_currency'] ?? 'PHP') === 'PHP' ? '₱' : ($r['salary_currency'] ?? '');
        if ($salMin && $salMax)      $salary = $cur . number_format($salMin/1000,0) . 'k – ' . $cur . number_format($salMax/1000,0) . 'k';
        elseif ($salMin)             $salary = $cur . number_format($salMin/1000,0) . 'k+';
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
            'salaryMin'   => (int)($salMin / 1000),
            'salaryMax'   => (int)($salMax / 1000),
            'description' => $r['description'] ?? '',
            'featured'    => $isFeatured,
            'tags'        => array_values(array_slice($tags, 0, 5)),
            'icon'        => 'fa-briefcase',
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] index jobs: '.$e->getMessage()); }

try {
    $s = $db->prepare("
        SELECT COALESCE(cp.company_name, u.company_name, u.full_name) AS name,
               COUNT(j.id) AS open_roles
        FROM jobs j
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'Active' AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        GROUP BY j.employer_id
        ORDER BY open_roles DESC LIMIT 5
    ");
    $s->execute();
    foreach ($s->fetchAll() as $c) {
        $indexCompanies[] = ['name' => $c['name'], 'openRoles' => (int)$c['open_roles'], 'icon' => 'fa-building'];
    }
} catch (PDOException $e) { /* ignore */ }

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
      color: #F5F0EE;
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
    .nav-link:hover { color: #F5F0EE; background: var(--soil-hover); }

    /* ==============================
       CATEGORIES DROPDOWN — restyled
       ============================== */
    .dropdown { position: relative; }

    .cat-toggle {
      display: flex; align-items: center; gap: 6px;
      padding: 7px 11px; border-radius: 6px;
      background: none; border: 1px solid transparent;
      font-family: var(--font-body); font-size: 13px; font-weight: 600;
      color: var(--text-muted); cursor: pointer; transition: all 0.2s;
      white-space: nowrap; letter-spacing: 0.01em;
    }
    .cat-toggle:hover {
      color: var(--text-light); background: var(--soil-hover);
    }
    .cat-toggle.active {
      color: var(--red-bright);
      background: rgba(200,57,42,0.08);
      border-color: rgba(200,57,42,0.25);
    }
    .cat-toggle .chevron {
      font-size: 9px; transition: transform 0.22s ease;
    }
    .cat-toggle.active .chevron { transform: rotate(180deg); }

    .dropdown-menu {
      position: absolute; top: calc(100% + 10px); left: 0;
      background: var(--soil-card);
      border: 1px solid var(--soil-line);
      border-top: 2px solid var(--red-vivid);
      border-radius: 10px;
      padding: 6px;
      min-width: 220px;
      opacity: 0; visibility: hidden;
      transform: translateY(-6px);
      transition: all 0.18s ease;
      z-index: 300;
      box-shadow: 0 24px 48px rgba(0,0,0,0.55), 0 0 0 1px rgba(200,57,42,0.05);
    }
    .dropdown-menu.open {
      opacity: 1; visibility: visible; transform: translateY(0);
    }

    /* Section divider inside dropdown */
    .dropdown-label {
      font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
      text-transform: uppercase; color: var(--text-muted);
      padding: 8px 12px 4px;
    }
    .dropdown-divider {
      height: 1px; background: var(--soil-line); margin: 4px 6px;
    }

    .dropdown-item {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 7px;
      font-size: 13px; font-weight: 500;
      color: var(--text-mid); cursor: pointer; transition: all 0.15s;
      font-family: var(--font-body);
    }
    .dropdown-item .di-icon {
      width: 30px; height: 30px; border-radius: 7px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; color: var(--red-vivid);
      flex-shrink: 0; transition: all 0.15s;
    }
    .dropdown-item:hover { background: var(--soil-hover); color: var(--text-light); }
    .dropdown-item:hover .di-icon {
      background: rgba(200,57,42,0.15);
      border-color: rgba(200,57,42,0.3);
      color: var(--red-bright);
    }
    .di-name { font-size: 13px; font-weight: 600; color: inherit; }
    .di-sub { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

    /* Nav right */
    .nav-right {
      display: flex; align-items: center; gap: 8px;
      margin-left: auto; flex-shrink: 0;
    }
    .theme-btn {
      width: 34px; height: 34px; border-radius: 7px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      color: var(--text-muted); display: flex; align-items: center; justify-content: center;
      cursor: pointer; transition: 0.2s; font-size: 13px; flex-shrink: 0;
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
      width: 34px; height: 34px; border-radius: 8px;
      background: var(--soil-hover); border: 1px solid var(--soil-line);
      color: var(--text-mid); align-items: center; justify-content: center;
      cursor: pointer; font-size: 14px; flex-shrink: 0;
      margin-left: 8px;
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
    .mobile-cats-label {
      font-size: 10px; font-weight: 700; letter-spacing: 0.1em;
      text-transform: uppercase; color: var(--text-muted); padding: 6px 14px 2px;
      font-family: var(--font-body);
    }
    .mobile-auth {
      display: flex; gap: 8px; padding: 8px 0 2px;
    }
    .mobile-auth .btn-ghost, .mobile-auth .btn-red { flex: 1; text-align: center; justify-content: center; }

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
    .search-bar i { padding: 0 14px; color: var(--text-muted); font-size: 14px; flex-shrink: 0; }
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
      color: #F5F0EE; line-height: 1;
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
      border: 1px solid rgba(200,57,42,0.18); white-space: nowrap; margin-left: auto;
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
    .sidebar { position: sticky; top: 72px; height: calc(100vh - 88px); overflow-y: auto; scrollbar-width: none; display:flex; flex-direction:column; }
    .sidebar::-webkit-scrollbar { display: none; }
    .sidebar-card {
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 10px; overflow: hidden; flex:1;
    }
    .sidebar-head {
      padding: 16px 18px 12px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid var(--soil-line);
    }
    .sidebar-title {
      font-family: var(--font-body); font-size: 12px; font-weight: 700;
      color: #F5F0EE; display: flex; align-items: center; gap: 7px;
      letter-spacing: 0.07em; text-transform: uppercase;
    }
    .sidebar-title i { color: var(--red-bright); font-size: 11px; }
    .reset-link {
      font-size: 12px; font-weight: 600; color: #927C7A;
      cursor: pointer; background: none; border: none; font-family: var(--font-body); transition: 0.15s;
    }
    .reset-link:hover { color: var(--red-pale); }
    .filter-section { padding: 13px 16px; border-bottom: 1px solid var(--soil-line); }
    .filter-section:last-child { border-bottom: none; }
    .filter-section-label {
      font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
      color: #927C7A; margin-bottom: 7px;
    }
    .filter-select, .filter-input {
      width: 100%;
      background: #0A0909;
      border: 1px solid var(--soil-line);
      border-radius: 6px; padding: 8px 30px 8px 11px;
      font-family: var(--font-body); font-size: 13px; color: #D0BCBA;
      outline: none; transition: border-color 0.2s, box-shadow 0.2s;
      -webkit-appearance: none; appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
      cursor: pointer;
    }
    .filter-input {
      background-image: none;
      padding-right: 11px;
      cursor: text;
    }
    .filter-select:hover, .filter-input:hover {
      border-color: var(--red-mid);
      background-color: var(--soil-hover);
    }
    .filter-select:focus, .filter-input:focus {
      border-color: var(--red-vivid);
      box-shadow: 0 0 0 2px rgba(209,61,44,0.14);
    }
    .filter-select option { background: #131010; color: #D0BCBA; }

    /* Light-mode overrides for filter inputs placed further down */
    body.light .filter-select,
    body.light .filter-input {
      background-color: #FFFFFF;
      border-color: #D4B0AB;
      color: #1A0A09;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237A5555' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    }
    body.light .filter-input { background-image: none; }
    body.light .filter-select:hover,
    body.light .filter-input:hover {
      border-color: var(--red-mid);
      background-color: #FEF0EE;
    }
    body.light .filter-select:focus,
    body.light .filter-input:focus {
      border-color: var(--red-vivid);
      box-shadow: 0 0 0 2px rgba(200,57,42,0.12);
    }
    body.light .filter-select option { background: #FFFFFF; color: #1A0A09; }
    body.light .filter-input::placeholder { color: #9A7070; }

    /* === MAIN === */
    .sec-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .sec-title {
      font-family: var(--font-display); font-size: 20px; font-weight: 700;
      color: #F5F0EE; display: flex; align-items: center; gap: 10px;
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
    .fc-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: #F5F0EE; margin-bottom: 4px; line-height: 1.3; }
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
    .save-btn {
      width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--soil-line);
      background: var(--soil-hover); color: var(--text-muted); font-size: 12px;
      display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;
    }
    .save-btn:hover, .save-btn.saved {
      border-color: var(--red-vivid); color: var(--red-pale); background: rgba(200,57,42,0.1);
    }

    /* Companies */
    .companies-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 40px; }
    .company-pill {
      display: flex; align-items: center; gap: 10px;
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-radius: 8px; padding: 10px 14px;
      transition: 0.2s; flex: 1; min-width: 120px;
    }
    .cp-icon {
      width: 32px; height: 32px; border-radius: 7px; background: var(--soil-hover);
      border: 1px solid var(--soil-line); display: flex; align-items: center; justify-content: center;
      color: var(--red-vivid); font-size: 13px; flex-shrink: 0;
    }
    .cp-name { font-size: 13px; font-weight: 600; color: var(--text-light); }
    .cp-roles { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

    /* Job list */
    .job-list { display: flex; flex-direction: column; gap: 8px; }
    .job-row {
      background: var(--soil-card);
      border: 1px solid var(--soil-line);
      border-radius: 10px; padding: 18px 20px;
      cursor: pointer; transition: all 0.18s;
      display: grid; grid-template-columns: 1fr auto;
      gap: 16px; align-items: center; position: relative;
    }
    .job-row:hover {
      border-color: rgba(209,61,44,0.5);
      background: var(--soil-hover);
      transform: translateX(2px);
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .jr-top { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
    .jr-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: #F5F0EE; }
    .jr-new {
      font-size: 10px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase;
      color: var(--red-pale); background: rgba(200,57,42,0.1); border: 1px solid rgba(200,57,42,0.2);
      padding: 2px 7px; border-radius: 3px;
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
    .jr-salary { font-family: var(--font-body); font-size: 14px; font-weight: 700; color: #F5F0EE; white-space: nowrap; letter-spacing: -0.01em; }
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

    /* === TOAST === */
    .toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 999;
      background: var(--soil-card); border: 1px solid var(--soil-line);
      border-left: 2px solid var(--red-vivid);
      border-radius: 8px; padding: 11px 18px;
      font-size: 13px; font-weight: 500; color: var(--text-light);
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      display: flex; align-items: center; gap: 9px;
      animation: toastIn 0.25s ease;
    }
    @keyframes toastIn { from { opacity:0; transform: translateY(10px); } to { opacity:1; transform: translateY(0); } }
    .toast i { color: var(--red-pale); }

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
    body.light .dropdown-menu {
      background: #FFFFFF;
      border-color: #E8D0CC;
      box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }
    body.light .dropdown-item { color: #4A2828; }
    body.light .dropdown-item .di-icon {
      background: #F5E8E6;
      border-color: #DFC0BB;
    }
    body.light .dropdown-item:hover { background: #FEF0EE; color: #1A0A09; }
    body.light .dropdown-label { color: #9A7070; }
    body.light .dropdown-divider { background: #E8D0CC; }

    /* Cat toggle active in light */
    body.light .cat-toggle { color: #5A4040; }
    body.light .cat-toggle:hover { background: #FEF0EE; color: #1A0A09; }
    body.light .cat-toggle.active {
      background: rgba(200,57,42,0.08);
      border-color: rgba(200,57,42,0.3);
    }

    /* Colony visual (hero right card) */
    body.light .colony-visual { background: #FFFFFF; border-color: #E8D0CC; }
    body.light .mini-job-card { background: #FEF0EE; border-color: #E8D0CC; }
    body.light .mini-job-title { color: #1A0A09; }

    /* Section count badge */
    body.light .sec-count { background: #F5E8E6; color: #7A5555; }

    /* Save btn */
    body.light .save-btn { background: #F5E8E6; border-color: #DFC0BB; color: #9A7070; }

    /* Featured card icon */
    body.light .fc-icon { background: #F5E8E6; border-color: #DFC0BB; }

    /* Company pill */
    body.light .cp-icon { background: #F5E8E6; border-color: #DFC0BB; }

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
    body.light .mobile-cats-label { color: #9A7070; }

    /* === LIGHT MODE — hardcoded color overrides === */
    body.light .logo-text { color: #1A0A09; }
    body.light .logo-text span { color: var(--red-vivid); }
    body.light .nav-link { color: #5A4040; }
    body.light .nav-link:hover { color: #1A0A09; background: #FEF0EE; }
    body.light .sidebar-title { color: #1A0A09; }
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
      .sidebar-card { border-radius: 14px; }
      .filter-section { display: inline-flex; flex-direction: column; gap: 4px; min-width: 140px; }
      .sidebar-card { display: flex; flex-wrap: wrap; padding: 0; }
      .sidebar-head { width: 100%; flex: 0 0 100%; }
    }

    /* Small tablet / large phone */
    @media (max-width: 760px) {
      /* Nav: hide desktop links + right buttons (keep only logo + hamburger) */
      .nav-links { display: none; }
      .btn-ghost { display: none; }
      .hamburger { display: flex; }

      .page-shell { padding: 0 16px 60px; }
      .nav-inner { padding: 0 16px; }

      .hero { padding: 40px 0 28px; }
      .hero-h1 { letter-spacing: -1px; }
      .hero-stats { gap: 16px; }
      .stat-sep { display: none; }

      .companies-row { gap: 8px; }
      .company-pill { min-width: 100px; }

      .job-row {
        grid-template-columns: 1fr;
        gap: 10px;
      }
      .job-row-right { flex-direction: row; align-items: center; justify-content: space-between; }

      .footer { flex-direction: column; text-align: center; padding: 20px 16px; }
    }

    /* Phone */
    @media (max-width: 480px) {
      .btn-red { font-size: 12px; padding: 7px 12px; }
      .hero-stats { flex-direction: column; gap: 10px; }
      .search-bar { border-radius: 12px; }
      .featured-card { min-width: 230px; max-width: 230px; }
    }
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
      <!-- Categories dropdown -->
      <div class="dropdown" id="catDropdown">
        <button class="cat-toggle" id="catToggle">
          <i class="fas fa-th-large"></i> Categories
          <i class="fas fa-chevron-down chevron"></i>
        </button>
        <div class="dropdown-menu" id="catMenu">
          <div class="dropdown-label">Browse by industry</div>
          <!-- items injected by JS -->
          <div class="dropdown-divider"></div>
          <div class="dropdown-item" data-filter-type="workSetup" data-filter-value="Remote">
            <div class="di-icon"><i class="fas fa-globe"></i></div>
            <div class="di-text">
              <div class="di-name">Remote Work</div>
              <div class="di-sub">Work from anywhere</div>
            </div>
          </div>
        </div>
      </div>

      <a class="nav-link" data-scroll="jobs"><i class="fas fa-list"></i> All Jobs</a>
    </div>

    <!-- Desktop right buttons -->
    <div class="nav-right">
      <button class="theme-btn" id="themeToggle"><i class="fas fa-moon"></i></button>
      <button class="btn-ghost" id="loginBtn" type="button" onclick="window.location.href='auth/antcareers_login.php'"><i class="fas fa-key"></i> Log in</button>
      <button class="btn-red" id="signupBtn" type="button" onclick="window.location.href='auth/antcareers_signup.php'">Get started</button>
    </div>

    <!-- Hamburger (mobile only) -->
    <button class="theme-btn" id="themeToggleMobile" style="display:none;"><i class="fas fa-moon"></i></button>
    <div class="hamburger" id="hamburger"><i class="fas fa-bars"></i></div>
  </div>
</nav>

<!-- Mobile slide-down menu -->
<div class="mobile-menu" id="mobileMenu">
  <a class="mobile-link" data-scroll="featured" data-close-mobile><i class="fas fa-star"></i> Featured jobs</a>
  <a class="mobile-link" data-scroll="companies" data-close-mobile><i class="fas fa-building"></i> Companies</a>
  <a class="mobile-link" data-scroll="jobs" data-close-mobile><i class="fas fa-list"></i> All Jobs</a>
  <div class="mobile-divider"></div>
  <div class="mobile-cats-label">Browse by industry</div>
  <?php foreach (getIndustryFilterOptions() as $_idx => $_ind): ?>
  <a class="mobile-link mobile-cat-link" data-industry="<?= htmlspecialchars($_ind['value'], ENT_QUOTES, 'UTF-8') ?>" data-close-mobile><i class="fas <?= htmlspecialchars($_ind['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars($_ind['label'], ENT_QUOTES, 'UTF-8') ?></a>
  <?php endforeach; ?>
  <div class="mobile-divider"></div>
  <div class="mobile-auth">
    <button class="btn-ghost" id="loginBtnMob" type="button" onclick="window.location.href='auth/antcareers_login.php'"><i class="fas fa-key"></i> Log in</button>
    <button class="btn-red" id="signupBtnMob" type="button" onclick="window.location.href='auth/antcareers_signup.php'">Get started</button>
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
      <div class="sidebar-card">
        <div class="sidebar-head">
          <div class="sidebar-title"><i class="fas fa-sliders-h"></i> Refine search</div>
          <button class="reset-link" id="resetFiltersBtn">Reset all</button>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Job type</div>
          <select id="jobTypeFilter" class="filter-select">
            <option value="">All types</option>
            <option value="Full-time">Full-time</option>
            <option value="Part-time">Part-time</option>
            <option value="Contract">Contract</option>
            <option value="Internship">Internship</option>
          </select>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Position</div>
          <input type="text" id="positionFilter" list="positionOptions" class="filter-input" placeholder="e.g. Product Designer">
          <datalist id="positionOptions">
            <?php foreach (getJobTitlesList() as $_jt): ?><option value="<?= htmlspecialchars($_jt, ENT_QUOTES, 'UTF-8') ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Location</div>
          <input type="text" id="locationFilter" list="locationOptions" class="filter-input" placeholder="e.g. Remote, New York">
          <datalist id="locationOptions">
            <option value="New York"><option value="San Francisco">
            <option value="Remote"><option value="Austin"><option value="Chicago">
          </datalist>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Experience level</div>
          <select id="expFilter" class="filter-select">
            <option value="">Any level</option>
            <option value="Entry">Entry level</option>
            <option value="Mid">Mid level</option>
            <option value="Senior">Senior level</option>
          </select>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Industry</div>
          <select id="industryFilter" class="filter-select">
            <option value="">All industries</option>
            <?php foreach (getIndustryFilterOptions() as $_ind): ?>
            <option value="<?= htmlspecialchars($_ind['value'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($_ind['label'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Salary range (₱)</div>
          <select id="salaryRangeFilter" class="filter-select">
            <option value="">Any salary</option>
            <option value="0-80">Under ₱80k</option>
            <option value="80-100">₱80k – ₱100k</option>
            <option value="100-120">₱100k – ₱120k</option>
            <option value="120-140">₱120k – ₱140k</option>
            <option value="140-160">₱140k – ₱160k</option>
            <option value="160-200">₱160k – ₱200k</option>
            <option value="200+">₱200k+</option>
          </select>
        </div>
        <div class="filter-section">
          <div class="filter-section-label">Work setup</div>
          <select id="workSetupFilter" class="filter-select">
            <option value="">All setups</option>
            <option value="On-site">On-site</option>
            <option value="Hybrid">Hybrid</option>
            <option value="Remote">Remote</option>
          </select>
        </div>
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

<script>
  const jobsData = <?= $indexJobsJson ?>;

  const companies = <?= $indexCompaniesJson ?>;

  const categoryList = <?= json_encode(array_map(function($i) {
      return ['name' => $i['label'], 'sub' => '', 'icon' => $i['icon'], 'filterType' => 'industry', 'value' => $i['value']];
  }, getIndustryFilterOptions()), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  let savedJobs = new Set();

  const featuredContainer = document.getElementById('featuredJobsContainer');
  const jobsContainer = document.getElementById('jobsContainer');
  const jobCount = document.getElementById('jobCount');
  const keywordInput = document.getElementById('keywordInput');
  const jobTypeFilter = document.getElementById('jobTypeFilter');
  const positionFilter = document.getElementById('positionFilter');
  const locationFilter = document.getElementById('locationFilter');
  const expFilter = document.getElementById('expFilter');
  const industryFilter = document.getElementById('industryFilter');
  const salaryRangeFilter = document.getElementById('salaryRangeFilter');
  const workSetupFilter = document.getElementById('workSetupFilter');
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

  // mobile theme toggle visibility
  function updateMobileThemeBtn() {
    const mob = document.getElementById('themeToggleMobile');
    mob.style.display = window.innerWidth <= 760 ? 'flex' : 'none';
  }
  window.addEventListener('resize', updateMobileThemeBtn);
  updateMobileThemeBtn();

  // mobile category links (dynamic)
  document.querySelectorAll('.mobile-cat-link').forEach(el => {
    el.addEventListener('click', () => {
      const val = el.dataset.industry;
      if (val) industryFilter.value = val;
      renderAllJobs();
      closeMobileMenu();
      document.getElementById('jobs').scrollIntoView({ behavior:'smooth' });
    });
  });

  // mobile scroll links
  document.querySelectorAll('[data-close-mobile][data-scroll]').forEach(el => {
    el.addEventListener('click', () => { closeMobileMenu(); });
  });
  function closeMobileMenu() {
    mobileMenu.classList.remove('open');
    hamburger.querySelector('i').className = 'fas fa-bars';
  }

  // ── CATEGORIES DROPDOWN ──
  const catMenu = document.getElementById('catMenu');
  const catToggle = document.getElementById('catToggle');

  // build industry items (remote item already in HTML as last)
  const industryItems = categoryList.map(c => `
    <div class="dropdown-item" data-filter-type="${c.filterType}" data-filter-value="${c.value}">
      <div class="di-icon"><i class="fas ${c.icon}"></i></div>
      <div class="di-text">
        <div class="di-name">${c.name}</div>
        <div class="di-sub">${c.sub}</div>
      </div>
    </div>`).join('');
  // insert before divider
  const divider = catMenu.querySelector('.dropdown-divider');
  divider.insertAdjacentHTML('beforebegin', industryItems);

  catToggle.addEventListener('click', e => {
    e.stopPropagation();
    const open = catMenu.classList.toggle('open');
    catToggle.classList.toggle('active', open);
  });
  document.addEventListener('click', () => {
    catMenu.classList.remove('open');
    catToggle.classList.remove('active');
  });

  catMenu.querySelectorAll('.dropdown-item').forEach(item => {
    item.addEventListener('click', e => {
      e.stopPropagation();
      const ft = item.dataset.filterType, fv = item.dataset.filterValue;
      if (ft === 'industry') industryFilter.value = fv;
      else if (ft === 'workSetup') workSetupFilter.value = fv;
      renderAllJobs();
      document.getElementById('jobs').scrollIntoView({ behavior:'smooth' });
      catMenu.classList.remove('open');
      catToggle.classList.remove('active');
    });
  });

  // ── FILTERING ──
  function isFiltering() {
    return !!(keywordInput.value.trim() || jobTypeFilter.value || positionFilter.value.trim() ||
              locationFilter.value.trim() || expFilter.value || industryFilter.value ||
              salaryRangeFilter.value || workSetupFilter.value);
  }

  function setSearchMode(active) {
    document.getElementById('featured').style.display = active ? 'none' : '';
    document.getElementById('companies').style.display = active ? 'none' : '';
    document.getElementById('jobsSectionIcon').className = active ? 'fas fa-search' : 'fas fa-list-ul';
    document.getElementById('jobsSectionText').textContent = active ? 'Search results ' : 'Live opportunities ';
  }
  function getFiltered() {
    const kw = keywordInput.value.trim().toLowerCase();
    const jt = jobTypeFilter.value, pos = positionFilter.value.trim();
    const loc = locationFilter.value.trim(), exp = expFilter.value;
    const ind = industryFilter.value, sal = salaryRangeFilter.value, ws = workSetupFilter.value;
    return jobsData.filter(j => {
      const mk = !kw || j.title.toLowerCase().includes(kw) || j.company.toLowerCase().includes(kw) || j.description.toLowerCase().includes(kw);
      const mjt = !jt || j.jobType === jt;
      const mpos = !pos || j.title.toLowerCase().includes(pos.toLowerCase());
      const ml = !loc || j.location.toLowerCase().includes(loc.toLowerCase());
      const me = !exp || j.experience === exp;
      const mi = !ind || j.industry === ind;
      const mws = !ws || j.workSetup === ws;
      let ms = true;
      if (sal) {
        if (sal.endsWith('+')) ms = j.salaryMin >= 200;
        else { const [minS,maxS] = sal.split('-').map(Number); ms = j.salaryMin >= minS && j.salaryMax <= maxS; }
      }
      return mk && mjt && mpos && ml && me && mi && mws && ms;
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
          <button class="save-btn ${savedJobs.has(j.id)?'saved':''}" onclick="event.stopPropagation();toggleSave(${j.id},this)">
            <i class="fa${savedJobs.has(j.id)?'s':'r'} fa-heart"></i>
          </button>
        </div>
      </div>`).join('');
  }

  function renderCompanies() {
    companiesGrid.innerHTML = companies.map(c => `
      <div class="company-pill">
        <div class="cp-icon"><i class="fas ${c.icon}"></i></div>
        <div>
          <div class="cp-name">${c.name}</div>
          <div class="cp-roles">${c.openRoles} open roles</div>
        </div>
      </div>`).join('');
  }

  function renderAllJobs() {
    const filtering = isFiltering();
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
            <button class="save-btn ${savedJobs.has(j.id)?'saved':''}" onclick="event.stopPropagation();toggleSave(${j.id},this)">
              <i class="fa${savedJobs.has(j.id)?'s':'r'} fa-heart"></i>
            </button>
            <button class="jr-apply" onclick="event.stopPropagation();startApplication(${j.id})">Apply</button>
          </div>
        </div>
      </div>`).join('');
    if (filtering) document.getElementById('jobs').scrollIntoView({ behavior:'smooth', block:'start' });
  }

  function toggleSave(id, btn) {
    if (savedJobs.has(id)) {
      savedJobs.delete(id); btn.classList.remove('saved');
      btn.innerHTML = '<i class="far fa-heart"></i>'; showToast('Removed from saved','fa-heart');
    } else {
      savedJobs.add(id); btn.classList.add('saved');
      btn.innerHTML = '<i class="fas fa-heart"></i>'; showToast('Job saved!','fa-heart');
    }
  }

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

  function showToast(msg, icon) {
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2400);
  }

  // Scroll
  document.querySelectorAll('[data-scroll]').forEach(el => {
    el.addEventListener('click', () => {
      const target = document.getElementById(el.dataset.scroll);
      if (target) target.scrollIntoView({ behavior:'smooth' });
    });
  });

  // Auth / misc
  document.getElementById('seeMoreFeatured').addEventListener('click', () => {
    const strip = document.getElementById('featuredJobsContainer');
    strip?.scrollBy({ left: Math.max(strip.clientWidth * 0.8, 320), behavior: 'smooth' });
  });
  document.getElementById('seeMoreCompanies').addEventListener('click', () => {
    document.getElementById('companies')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  document.getElementById('seeMoreJobs').addEventListener('click', () => {
    document.getElementById('jobs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  function getThemeParam() {
    return '?theme=' + (document.body.classList.contains('light') ? 'light' : 'dark');
  }
  function startApplication(jobId) {
    window.location.href = 'auth/antcareers_signup.php' + getThemeParam() + '&job=' + encodeURIComponent(jobId);
  }
  document.getElementById('loginBtn').addEventListener('click', () => window.location.href = 'auth/antcareers_login.php' + getThemeParam());
  document.getElementById('signupBtn').addEventListener('click', () => window.location.href = 'auth/antcareers_signup.php' + getThemeParam());
  document.getElementById('loginBtnMob').addEventListener('click', () => window.location.href = 'auth/antcareers_login.php' + getThemeParam());
  document.getElementById('signupBtnMob').addEventListener('click', () => window.location.href = 'auth/antcareers_signup.php' + getThemeParam());

  // Filter events
  document.getElementById('searchBtn').addEventListener('click', renderAllJobs);
  keywordInput.addEventListener('keyup', e => { if (e.key==='Enter') renderAllJobs(); });
  [jobTypeFilter,positionFilter,locationFilter,expFilter,industryFilter,salaryRangeFilter,workSetupFilter].forEach(el => {
    el.addEventListener('change', renderAllJobs);
    el.addEventListener('input', renderAllJobs);
  });
  document.getElementById('resetFiltersBtn').addEventListener('click', () => {
    keywordInput.value = '';
    [jobTypeFilter,positionFilter,locationFilter,expFilter,industryFilter,salaryRangeFilter,workSetupFilter].forEach(el => el.value='');
    renderAllJobs();
    document.querySelector('.hero').scrollIntoView({ behavior:'smooth', block:'start' });
  });

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
</body>
</html>