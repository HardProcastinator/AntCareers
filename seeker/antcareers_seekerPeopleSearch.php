<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/countries.php';
require_once dirname(__DIR__) . '/includes/job_titles.php';
requireLogin('seeker');
$user = getUser();
// Convenience aliases for page templates that use the old variable names
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'people';
$seekerId = (int)$_SESSION['user_id'];

/* ── Fetch people from DB (seekers + employers with profiles) ── */
$peopleList = [];
try {
    $db = getDB();
  try { $db->query('SELECT industry FROM seeker_profiles LIMIT 0'); }
  catch (PDOException $e) { $db->exec("ALTER TABLE seeker_profiles ADD COLUMN industry VARCHAR(255) DEFAULT NULL AFTER headline"); }
  try { $db->query('SELECT show_in_people_search FROM seeker_profiles LIMIT 0'); }
  catch (PDOException $e) { $db->exec("ALTER TABLE seeker_profiles ADD COLUMN show_in_people_search TINYINT(1) NOT NULL DEFAULT 1 AFTER professional_summary"); }
    $st = $db->prepare("
        SELECT
            u.id, u.full_name, u.avatar_url, u.account_type,
            sp.headline, sp.industry, sp.experience_level,
            sp.city_name, sp.province_name, sp.country_name,
            sp.nr_availability,
            GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.sort_order SEPARATOR ',') AS skills
        FROM users u
        LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
        LEFT JOIN seeker_skills sk ON sk.user_id = u.id
        WHERE u.id != ? AND u.is_active = 1
          AND u.account_type IN ('seeker','employer')
          AND (u.account_type <> 'seeker' OR COALESCE(sp.show_in_people_search, 1) = 1)
        GROUP BY u.id
        ORDER BY u.full_name ASC
        LIMIT 100
    ");
    $st->execute([$seekerId]);
    $colors = [
        'linear-gradient(135deg,#D13D2C,#7A1515)',
        'linear-gradient(135deg,#4A90D9,#2A6090)',
        'linear-gradient(135deg,#4CAF70,#2A7040)',
        'linear-gradient(135deg,#D4943A,#8A5A10)',
        'linear-gradient(135deg,#9C27B0,#5A0080)',
    ];
    $ci = 0;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = $r['full_name'] ?? 'Unknown';
        $parts = preg_split('/\s+/', trim($name)) ?: ['?'];
        $initA = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
        $loc = $r['city_name'] ?? $r['province_name'] ?? $r['country_name'] ?? 'Not specified';
        $skills = array_filter(array_map('trim', explode(',', (string)($r['skills'] ?? ''))));
        $status = '';
        if ($r['nr_availability'] === 'Now' || $r['nr_availability'] === 'Open') $status = 'seeking';

        $peopleList[] = [
          'id'        => (int)$r['id'],
            'name'      => $name,
            'title'     => $r['headline'] ?? ($r['account_type'] === 'employer' ? 'Employer' : 'Job Seeker'),
            'location'  => $loc,
          'industry'  => $r['industry'] ?? $r['headline'] ?? 'Professional',
            'skills'    => array_values($skills),
            'exp'       => $r['experience_level'] ?? '',
            'status'    => $status,
            'avatar'    => $initA,
            'avatarUrl' => !empty($r['avatar_url']) ? '../' . $r['avatar_url'] : '',
            'color'     => $colors[$ci % count($colors)],
            'mutual'    => 0,
            'connected' => false,
          'accountType' => $r['account_type'] ?? 'seeker',
          'profileUrl'  => ($r['account_type'] === 'employer') ? ('public_company_profile.php?employer_id=' . (int)$r['id']) : null,
        ];
        $ci++;
    }
} catch (PDOException $e) {
    error_log('[AntCareers] people search: ' . $e->getMessage());
}
$peopleJson = json_encode($peopleList, JSON_HEX_TAG | JSON_HEX_AMP);

$countrySearchOptionsHtml = '<option value="">All Countries</option>';
$countrySidebarOptionsHtml = '<option value="">All countries</option>';
foreach (getCountries() as $country) {
  $name = (string)$country['name'];
  $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $countrySearchOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
  $countrySidebarOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
}
$countrySearchOptionsHtml .= '<option value="Remote">Remote</option>';
$countrySidebarOptionsHtml .= '<option value="Remote">Remote</option>';

$industryCheckboxesHtml = '';
foreach (getIndustryFilterOptions() as $industryOption) {
  $value = htmlspecialchars((string)$industryOption['value'], ENT_QUOTES, 'UTF-8');
  $label = htmlspecialchars((string)$industryOption['label'], ENT_QUOTES, 'UTF-8');
  $industryCheckboxesHtml .= '<label class="ms-item"><input type="checkbox" value="' . $value . '"><span>' . $label . '</span></label>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>People Search — AntCareers</title>
  <script>
    (function(){
      const p=new URLSearchParams(window.location.search).get('theme');
      const t=p||localStorage.getItem('ac-theme')||'light';
      if(p) localStorage.setItem('ac-theme',t);
      if(t==='light') document.documentElement.classList.add('theme-light');
    })();
  </script>
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

    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }

    /* ── NAVBAR ── */
    /* ── PAGE ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:32px 24px 80px; position:relative; z-index:1; }

    /* ── PAGE HEADER ── */
    .page-header { margin-bottom:28px; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .page-title em { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); }

    /* ── SEARCH BAR ── */
    .search-row { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; position:relative; z-index:10; }
    .search-box { flex:1; min-width:240px; display:flex; align-items:center; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; transition:0.25s; }
    .search-box:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-box .si { padding:0 14px; color:var(--text-muted); font-size:14px; flex-shrink:0; }
    .search-box input { flex:1; padding:13px 0; background:none; border:none; outline:none; font-family:var(--font-body); font-size:14px; color:#F5F0EE; }
    .search-box input::placeholder { color:var(--text-muted); }
    .filter-select { padding:13px 16px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; font-family:var(--font-body); font-size:13px; color:var(--text-mid); cursor:pointer; outline:none; transition:0.2s; }
    .filter-select:focus { border-color:var(--red-vivid); }
    .search-btn { padding:13px 24px; border-radius:10px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; white-space:nowrap; display:flex; align-items:center; gap:7px; }
    .search-btn:hover { background:var(--red-bright); transform:translateY(-1px); }
    /* Search row multi-select */
    .search-row .ms-wrap { flex-shrink:0; min-width:170px; }
    .search-row .ms-trigger { padding:13px 16px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; font-size:13px; color:var(--text-mid); }
    .search-row .ms-trigger:hover { border-color:var(--red-mid); background-color:var(--soil-hover); }
    .search-row .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.12); }
    .search-row .ms-panel { min-width:260px; z-index:50; }

    /* ── FILTER PILLS ── */
    .filter-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:28px; }
    .fpill { display:flex; align-items:center; gap:5px; padding:6px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:100px; font-size:12px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; white-space:nowrap; }
    .fpill:hover, .fpill.active { background:rgba(209,61,44,0.12); border-color:rgba(209,61,44,0.35); color:var(--red-pale); }
    .fpill i { font-size:11px; }

    /* ── RESULTS META ── */
    .results-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
    .results-count { font-size:13px; color:var(--text-muted); font-weight:600; }
    .results-count strong { color:var(--text-mid); }
    .sort-select { padding:7px 12px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:7px; font-family:var(--font-body); font-size:12px; color:var(--text-mid); cursor:pointer; outline:none; }

    /* ── PEOPLE GRID ── */
    .people-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }

    /* ── PERSON CARD ── */
    .person-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px; transition:all 0.22s; cursor:pointer; position:relative; overflow:hidden; }
    .person-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); opacity:0; transition:0.22s; }
    .person-card:hover { border-color:rgba(209,61,44,0.45); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.35); }
    .person-card:hover::before { opacity:1; }
    .pc-top { display:flex; align-items:flex-start; gap:14px; margin-bottom:14px; }
    .pc-avatar { width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .pc-avatar img { width:100%; height:100%; object-fit:cover; }
    .pc-info { flex:1; min-width:0; }
    .pc-name { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pc-title { font-size:12px; color:var(--red-pale); font-weight:600; margin-bottom:4px; }
    .pc-location { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:4px; }
    .pc-location i { font-size:10px; color:var(--red-mid); }
    .pc-connect { width:32px; height:32px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:13px; color:var(--text-muted); cursor:pointer; transition:0.18s; flex-shrink:0; }
    .pc-connect:hover { background:rgba(209,61,44,0.15); border-color:var(--red-vivid); color:var(--red-pale); }
    .pc-connect.connected { background:rgba(76,175,112,0.15); border-color:rgba(76,175,112,0.3); color:#6ccf8a; }
    .pc-skills { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:14px; }
    .skill-chip { font-size:11px; padding:3px 9px; border-radius:4px; background:var(--soil-hover); border:1px solid var(--soil-line); color:#A09090; font-weight:500; }
    .skill-chip.highlight { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.2); color:var(--red-pale); }
    .pc-footer { display:flex; align-items:center; justify-content:space-between; padding-top:12px; border-top:1px solid var(--soil-line); }
    .pc-mutual { font-size:11px; color:var(--text-muted); display:flex; align-items:center; gap:5px; }
    .pc-mutual i { color:var(--amber); }
    .pc-actions { display:flex; gap:6px; }
    .pc-btn { padding:5px 12px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.18s; white-space:nowrap; }
    .pc-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .pc-btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .pc-btn.primary:hover { background:var(--red-bright); }
    .person-modal-overlay { position:fixed; inset:0; z-index:500; background:rgba(0,0,0,0.82); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:20px; }
    .person-modal-box { width:min(560px,100%); background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; padding:24px; box-shadow:0 32px 80px rgba(0,0,0,0.55); position:relative; animation:fadeUp 0.22s ease both; }
    .person-modal-close { position:absolute; top:14px; right:14px; width:30px; height:30px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:pointer; }
    .person-modal-close:hover { color:#F5F0EE; border-color:var(--red-vivid); }
    .person-modal-head { display:flex; align-items:center; gap:14px; margin-bottom:14px; padding-right:40px; }
    .person-modal-avatar { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:20px; font-weight:700; flex-shrink:0; overflow:hidden; }
    .person-modal-avatar img { width:100%; height:100%; object-fit:cover; }
    .person-modal-meta { min-width:0; }
    .person-modal-name { font-family:var(--font-display); font-size:22px; font-weight:700; color:var(--text-light); margin-bottom:4px; }
    .person-modal-title { color:var(--red-pale); font-size:13px; font-weight:600; margin-bottom:4px; }
    .person-modal-location { color:var(--text-muted); font-size:12px; display:flex; align-items:center; gap:5px; }
    .person-modal-section { margin-top:14px; }
    .person-modal-section-label { font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-muted); margin-bottom:8px; }
    .person-skill-row { display:flex; flex-wrap:wrap; gap:6px; }
    .person-skill-chip { font-size:11px; padding:4px 9px; border-radius:999px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); }
    .person-skill-empty { color:var(--text-muted); font-size:12px; }
    .person-modal-status { display:inline-flex; align-items:center; gap:6px; margin-top:4px; padding:4px 10px; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; }
    .person-modal-status.seeking { background:rgba(209,61,44,0.12); border:1px solid rgba(209,61,44,0.22); color:var(--red-pale); }
    .person-modal-status.hiring { background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.22); color:#6ccf8a; }
    .person-modal-status.neutral { background:rgba(212,148,58,0.12); border:1px solid rgba(212,148,58,0.22); color:var(--amber); }
    .person-modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:18px; }
    .person-modal-btn { padding:8px 14px; border-radius:8px; border:1px solid var(--soil-line); font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; }
    .person-modal-btn.secondary { background:var(--soil-hover); color:var(--text-light); }
    .person-modal-btn.secondary:hover { border-color:var(--red-vivid); }
    .person-modal-btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .person-modal-btn.primary:hover { background:var(--red-bright); }
    body.light .person-modal-overlay { background:rgba(0,0,0,0.5); }
    body.light .person-modal-box { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 32px 80px rgba(0,0,0,0.18); }
    body.light .person-modal-close { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }
    body.light .person-modal-close:hover { color:#1A0A09; border-color:var(--red-vivid); }
    body.light .person-modal-name { color:#1A0A09; }
    body.light .person-modal-title { color:var(--red-bright); }
    body.light .person-modal-location { color:#7A5555; }
    body.light .person-modal-section-label { color:#7A5555; }
    body.light .person-skill-chip { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .person-skill-empty { color:#7A5555; }
    body.light .person-modal-btn.secondary { background:#F5EEEC; border-color:#E0CECA; color:#1A0A09; }
    body.light .person-modal-btn.secondary:hover { border-color:var(--red-vivid); }
    body.light .person-modal-status.seeking { background:rgba(209,61,44,0.08); border-color:rgba(209,61,44,0.2); color:var(--red-bright); }
    body.light .person-modal-status.hiring { background:rgba(76,175,112,0.08); border-color:rgba(76,175,112,0.2); color:#2E7D4C; }
    body.light .person-modal-status.neutral { background:rgba(212,148,58,0.08); border-color:rgba(212,148,58,0.2); color:#8B5E1E; }
    .open-badge { font-size:10px; font-weight:700; letter-spacing:0.07em; padding:2px 8px; border-radius:3px; text-transform:uppercase; flex-shrink:0; margin-left:auto; }
    .open-badge.hiring { background:rgba(76,175,112,0.12); border:1px solid rgba(76,175,112,0.25); color:#6ccf8a; }
    .open-badge.seeking { background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); color:var(--red-pale); }

    /* ── SIDEBAR FILTERS ── */
    .layout { display:grid; grid-template-columns:240px 1fr; gap:24px; align-items:start; }
    .filter-sidebar { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:18px; position:sticky; top:80px; }
    .fs-title { font-size:11px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--text-muted); margin-bottom:14px; display:flex; align-items:center; gap:7px; }
    .fs-title i { color:var(--red-bright); }
    .fs-section { margin-bottom:20px; }
    .fs-section-label { font-size:11px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); margin-bottom:10px; }
    .fs-option { display:flex; align-items:center; gap:9px; padding:7px 10px; border-radius:7px; font-size:13px; color:var(--text-mid); cursor:pointer; transition:0.15s; font-weight:500; }
    .fs-option:hover { background:var(--soil-hover); color:#F5F0EE; }
    .fs-option input[type="checkbox"] { width:14px; height:14px; accent-color:var(--red-vivid); cursor:pointer; flex-shrink:0; }
    .fs-count { margin-left:auto; font-size:10px; color:var(--text-muted); background:var(--soil-hover); padding:1px 6px; border-radius:3px; font-weight:600; }
    .fs-divider { height:1px; background:var(--soil-line); margin:16px 0; }
    .fs-reset { width:100%; padding:9px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; }
    .fs-reset:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .fs-filter-select { width:100%; padding:10px 12px; font-size:12px; border-radius:7px; }
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
    .ms-panel { display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:7px; max-height:200px; overflow-y:auto; z-index:20; box-shadow:0 8px 24px rgba(0,0,0,0.4); }
    .ms-wrap.open .ms-panel { display:block; }
    .ms-item { display:flex; align-items:center; gap:8px; padding:7px 12px; font-size:13px; color:var(--text-mid); cursor:pointer; transition:background-color 0.12s; user-select:none; }
    .ms-item:hover { background:var(--soil-hover); }
    .ms-item input[type="checkbox"] { width:14px; height:14px; accent-color:var(--red-vivid); cursor:pointer; flex-shrink:0; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align:center; padding:60px 20px; color:var(--text-muted); }
    .empty-state i { font-size:40px; margin-bottom:14px; display:block; color:var(--soil-line); }
    .empty-state h3 { font-family:var(--font-display); font-size:20px; color:var(--text-mid); margin-bottom:8px; }
    .empty-state p { font-size:14px; }

    /* Toast */
    @keyframes toastIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
    /* Anim */
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.05s; } .anim-d2 { animation-delay:0.1s; } .anim-d3 { animation-delay:0.15s; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* ── LIGHT THEME ── */
    html.theme-light body, body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
    }
    body.light .glow-orb { display:none; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#7A5555; }
    body.light .search-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-box input { color:#1A0A09; }
    body.light .filter-select, body.light .sort-select { background:#FFFFFF; border-color:#E0CECA; color:#4A2828; }
    body.light .filter-sidebar { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fs-option { color:#4A2828; }
    body.light .fs-option:hover { background:#FEF0EE; color:#1A0A09; }
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
    body.light .search-row .ms-trigger { background:#FFFFFF; border-color:#E0CECA; color:#4A2828; }
    body.light .search-row .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .search-row .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .person-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .pc-name { color:#1A0A09; }
    body.light .skill-chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .pc-connect { background:#F5EEEC; border-color:#E0CECA; }
    body.light .pc-footer { border-color:#E0CECA; }
    @media(max-width:1060px) { .layout{grid-template-columns:1fr} .filter-sidebar{position:static} }
    @media(max-width:640px) { .people-grid{grid-template-columns:1fr} .search-row{flex-direction:column} .nav-links{display:none} .hamburger{display:flex} }
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

  <!-- HEADER -->
  <div class="page-header anim">
    <div class="page-title">People <em>Search</em></div>
    <div class="page-sub">Discover professionals, connect with peers, and grow your network.</div>
  </div>

  <!-- SEARCH ROW -->
  <div class="search-row anim anim-d1">
    <div class="search-box">
      <span class="si"><i class="fas fa-search"></i></span>
      <input type="text" id="peopleSearch" placeholder="Search by name, skill, or job title…">
    </div>
    <select class="filter-select" id="locationFilter" onchange="syncLocationFromSearch(); filterPeople()">
      <?= $countrySearchOptionsHtml ?>
    </select>
    <div class="ms-wrap" id="msSearchIndustry" data-default="All Industries">
      <button class="ms-trigger" type="button"><span class="ms-text">All Industries</span><i class="fas fa-chevron-down ms-arrow"></i></button>
      <div class="ms-panel">
        <?= $industryCheckboxesHtml ?>
      </div>
    </div>
    <button class="search-btn" onclick="filterPeople()"><i class="fas fa-search"></i> Search</button>
  </div>

  <div class="layout">

    <!-- FILTER SIDEBAR -->
    <aside class="filter-sidebar anim anim-d2">
      <div class="fs-title"><i class="fas fa-sliders-h"></i> Filters</div>

      <div class="fs-section">
        <div class="fs-section-label">Industry</div>
        <div class="ms-wrap" id="msSidebarIndustry" data-default="All Industries">
          <button class="ms-trigger" type="button"><span class="ms-text">All Industries</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <?= $industryCheckboxesHtml ?>
          </div>
        </div>
        <input type="text" id="sidebarPositionKeyword" class="fs-text-input" placeholder="Enter job title or position" oninput="filterPeople()" style="margin-top:6px;">
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Location</div>
        <select class="fs-select" id="sidebarLocationFilter" onchange="syncLocationFromSidebar(); filterPeople()">
          <?= $countrySidebarOptionsHtml ?>
        </select>
        <input type="text" id="sidebarLocationKeyword" class="fs-text-input" placeholder="Enter region, province or city" oninput="filterPeople()" style="margin-top:6px;">
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Company</div>
        <input type="text" id="sidebarCompanyFilter" class="fs-text-input" placeholder="Search company name" oninput="filterPeople()">
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Experience Level</div>
        <div class="ms-wrap" id="msExperience" data-default="Any level">
          <button class="ms-trigger" type="button"><span class="ms-text">Any level</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Entry Level"><span>Entry Level</span></label>
            <label class="ms-item"><input type="checkbox" value="Mid Level"><span>Mid Level</span></label>
            <label class="ms-item"><input type="checkbox" value="Senior"><span>Senior</span></label>
            <label class="ms-item"><input type="checkbox" value="Lead / Manager"><span>Lead / Manager</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <div class="fs-section">
        <div class="fs-section-label">Status</div>
        <div class="ms-wrap" id="msStatus" data-default="Any status">
          <button class="ms-trigger" type="button"><span class="ms-text">Any status</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="seeking"><span>Open to Work</span></label>
            <label class="ms-item"><input type="checkbox" value="hiring"><span>Actively Hiring</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>
      <button class="fs-reset" onclick="resetFilters()"><i class="fas fa-undo" style="margin-right:5px;"></i> Reset Filters</button>
    </aside>

    <!-- RESULTS -->
    <div>
      <div class="results-meta anim anim-d2">
        <div class="results-count"><strong id="resultCount">120</strong> professionals found</div>
        <select class="sort-select" onchange="filterPeople()">
          <option>Most Relevant</option>
          <option>Recently Active</option>
          <option>Mutual Connections</option>
        </select>
      </div>
      <div class="people-grid anim anim-d3" id="peopleGrid"></div>
    </div>

  </div>
</div>

<div class="person-modal-overlay" id="personModal" style="display:none;">
  <div class="person-modal-box">
    <button class="person-modal-close" type="button" onclick="closePersonView()"><i class="fas fa-times"></i></button>
    <div id="personModalBody"></div>
  </div>
</div>

<script>
  const peopleData = <?= $peopleJson ?>;
  const peopleById = Object.fromEntries(peopleData.map(p => [String(p.id), p]));

  let connections = new Set();

  function openPersonView(personId) {
    const person = peopleById[String(personId)];
    if (!person) return;
    if (person.accountType === 'employer' && person.profileUrl) {
      window.location.href = person.profileUrl;
      return;
    }

    const skills = (person.skills || []).slice(0, 6).map(s => `<span class="person-skill-chip">${s}</span>`).join('');
    const statusLabel = person.status === 'seeking' ? 'Open to work' : person.status === 'hiring' ? 'Actively hiring' : 'Available';
    const statusClass = person.status === 'seeking' ? 'seeking' : person.status === 'hiring' ? 'hiring' : 'neutral';

    document.getElementById('personModalBody').innerHTML = `
      <div class="person-modal-head">
        <div class="person-modal-avatar" style="background:${person.color};">${person.avatarUrl ? `<img src="${person.avatarUrl}" alt="">` : person.avatar}</div>
        <div class="person-modal-meta">
          <div class="person-modal-name">${person.name}</div>
          <div class="person-modal-title">${person.title}</div>
          <div class="person-modal-location"><i class="fas fa-map-marker-alt"></i> ${person.location}</div>
        </div>
      </div>
      <div class="person-modal-status ${statusClass}">${statusLabel}</div>
      <div class="person-modal-section">
        <div class="person-modal-section-label">Skills</div>
        <div class="person-skill-row">${skills || '<span class="person-skill-empty">No skills listed</span>'}</div>
      </div>
      <div class="person-modal-actions">
        <button class="person-modal-btn secondary" type="button" onclick="openPersonMessage(${person.id})"><i class="fas fa-comment-dots"></i> Message</button>
        <button class="person-modal-btn primary" type="button" onclick="closePersonView()">Close</button>
      </div>`;

    document.getElementById('personModal').style.display = 'flex';
  }

  function openPersonMessage(personId) {
    const person = peopleById[String(personId)];
    if (!person) return;
    window.dispatchEvent(new CustomEvent('seeker:openMessageSidebar', {
      detail: { userId: person.id, userName: person.name }
    }));
  }

  function closePersonView() {
    document.getElementById('personModal').style.display = 'none';
  }

  function renderPeople(data) {
    const grid = document.getElementById('peopleGrid');
    document.getElementById('resultCount').textContent = data.length;
    if (!data.length) {
      grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><i class="fas fa-users-slash"></i><h3>No professionals found</h3><p>Try adjusting your search or filters.</p></div>`;
      return;
    }
    grid.innerHTML = data.map((p,i) => {
      const isConnected = connections.has(String(p.id));
      const badge = p.status === 'seeking'
        ? `<span class="open-badge seeking">Open to Work</span>`
        : p.status === 'hiring'
        ? `<span class="open-badge hiring">Hiring</span>`
        : '';
      const skills = p.skills.slice(0,3).map((s,si) =>
        `<span class="skill-chip${si===0?' highlight':''}">${s}</span>`).join('') +
        (p.skills.length > 3 ? `<span class="skill-chip">+${p.skills.length-3}</span>` : '');
      return `
        <div class="person-card anim" style="animation-delay:${i*0.04}s;cursor:pointer;" onclick="openPersonView(${p.id})">
          <div class="pc-top">
            <div class="pc-avatar" style="background:${p.color};">${p.avatarUrl ? `<img src="${p.avatarUrl}" alt="">` : p.avatar}</div>
            <div class="pc-info">
              <div class="pc-name">${p.name}</div>
              <div class="pc-title">${p.title}</div>
              <div class="pc-location"><i class="fas fa-map-marker-alt"></i> ${p.location}</div>
            </div>
            ${badge}
            <button class="pc-connect ${isConnected?'connected':''}" title="${isConnected?'Connected':'Connect'}" onclick="event.stopPropagation();toggleConnect(${p.id},this)">
              <i class="fas fa-${isConnected?'check':'user-plus'}"></i>
            </button>
          </div>
          <div class="pc-skills">${skills}</div>
          <div class="pc-footer">
            <div class="pc-mutual">${p.mutual>0?`<i class="fas fa-users"></i> ${p.mutual} mutual connection${p.mutual!==1?'s':''}`:``}</div>
            <div class="pc-actions">
              <button class="pc-btn" onclick="event.stopPropagation();openPersonMessage(${p.id});">Message</button>
              <button class="pc-btn primary" onclick="event.stopPropagation();openPersonView(${p.id})">View</button>
            </div>
          </div>
        </div>`;
    }).join('');
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
  function syncLocationFromSidebar() {
    const v = document.getElementById('sidebarLocationFilter').value;
    const el = document.getElementById('locationFilter');
    if (el) el.value = v;
  }
  function syncLocationFromSearch() {
    const v = document.getElementById('locationFilter').value;
    const el = document.getElementById('sidebarLocationFilter');
    if (el) el.value = v;
  }
  function syncIndustryFromSidebar() {
    const vals = getMsValues('msSidebarIndustry');
    const wrap = document.getElementById('msSearchIndustry');
    if (!wrap) return;
    wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = vals.includes(cb.value); });
    updateMsLabel(wrap);
  }
  function syncIndustryFromSearch() {
    const vals = getMsValues('msSearchIndustry');
    const wrap = document.getElementById('msSidebarIndustry');
    if (!wrap) return;
    wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = vals.includes(cb.value); });
    updateMsLabel(wrap);
  }

  function filterPeople() {
    const q = document.getElementById('peopleSearch').value.toLowerCase();
    const loc = document.getElementById('locationFilter').value;
    const searchIndustries = getMsValues('msSearchIndustry');
    const sidebarIndustries = getMsValues('msSidebarIndustry');
    const expLevels = getMsValues('msExperience');
    const statuses = getMsValues('msStatus');
    const sidebarLoc = document.getElementById('sidebarLocationFilter')?.value || '';
    const locKeyword = (document.getElementById('sidebarLocationKeyword')?.value || '').toLowerCase();
    const posKeyword = (document.getElementById('sidebarPositionKeyword')?.value || '').toLowerCase();
    const companyQ = (document.getElementById('sidebarCompanyFilter')?.value || '').toLowerCase();
    let filtered = peopleData.filter(p => {
      const matchQ = !q || p.name.toLowerCase().includes(q) || p.title.toLowerCase().includes(q) || p.skills.some(s=>s.toLowerCase().includes(q));
      const matchL = !loc || p.location === loc;
      const matchI = sidebarIndustries.length ? sidebarIndustries.includes(p.industry) : (!searchIndustries.length || searchIndustries.includes(p.industry));
      const matchExp = !expLevels.length || expLevels.includes(p.exp);
      const matchStatus = !statuses.length || statuses.includes(p.status);
      const matchSidebarLoc = !sidebarLoc || p.location.includes(sidebarLoc);
      const matchLocKw = !locKeyword || p.location.toLowerCase().includes(locKeyword);
      const matchPosKw = !posKeyword || p.title.toLowerCase().includes(posKeyword);
      const matchCompany = !companyQ || (p.company && p.company.toLowerCase().includes(companyQ)) || p.title.toLowerCase().includes(companyQ);
      return matchQ && matchL && matchI && matchExp && matchStatus && matchSidebarLoc && matchLocKw && matchPosKw && matchCompany;
    });
    renderPeople(filtered);
  }

  function resetFilters() {
    document.getElementById('locationFilter').value='';

    document.getElementById('sidebarLocationFilter') && (document.getElementById('sidebarLocationFilter').value='');
    document.querySelectorAll('.ms-wrap').forEach(wrap => {
      wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
      updateMsLabel(wrap);
    });
    document.getElementById('sidebarLocationKeyword') && (document.getElementById('sidebarLocationKeyword').value='');
    document.getElementById('sidebarPositionKeyword') && (document.getElementById('sidebarPositionKeyword').value='');
    document.getElementById('sidebarCompanyFilter') && (document.getElementById('sidebarCompanyFilter').value='');
    document.getElementById('peopleSearch').value='';
    filterPeople();
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
      updateMsLabel(cb.closest('.ms-wrap'));
      if (cb.closest('#msSearchIndustry')) syncIndustryFromSearch();
      if (cb.closest('#msSidebarIndustry')) syncIndustryFromSidebar();
      filterPeople();
    });
  });

  function toggleConnect(personId, btn) {
    const key = String(personId);
    if (connections.has(key)) {
      connections.delete(key);
      btn.classList.remove('connected');
      btn.innerHTML = '<i class="fas fa-user-plus"></i>';
      btn.title = 'Connect';
      showToast('Connection removed','fa-user-minus');
    } else {
      connections.add(key);
      btn.classList.add('connected');
      btn.innerHTML = '<i class="fas fa-check"></i>';
      btn.title = 'Connected';
      showToast('Connection request sent!','fa-handshake');
    }
  }
  // theme handled by seeker_navbar.php
  // PROFILE DROPDOWN
  // SEARCH KEYUP
  document.getElementById('peopleSearch').addEventListener('keyup', filterPeople);

  // INIT
  renderPeople(peopleData);
</script>
</body>
</html>