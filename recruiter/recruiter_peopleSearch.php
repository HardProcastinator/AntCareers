<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/countries.php';
require_once dirname(__DIR__) . '/includes/job_titles.php';
requireLogin('recruiter');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$userEmail   = $user['email'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'people';
$uid = (int)$_SESSION['user_id'];

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
            sp.nr_availability, sp.nr_work_types, sp.nr_right_to_work,
            sp.nr_salary, sp.nr_salary_period, sp.nr_classification,
            sp.professional_summary, sp.bio, sp.phone,
            GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.sort_order SEPARATOR ',') AS skills,
            sr.file_path AS resume_path, sr.original_filename AS resume_name
        FROM users u
        LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
        LEFT JOIN seeker_skills sk ON sk.user_id = u.id
        LEFT JOIN seeker_resumes sr ON sr.user_id = u.id AND sr.is_active = 1
        WHERE u.id != ? AND u.is_active = 1
          AND u.account_type = 'seeker'
          AND COALESCE(sp.show_in_people_search, 1) = 1
        GROUP BY u.id
        ORDER BY u.full_name ASC
        LIMIT 100
    ");
    $st->execute([$uid]);
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
          'availability' => $r['nr_availability'] ?? '',
          'workTypes'   => $r['nr_work_types'] ?? '',
          'rightToWork' => $r['nr_right_to_work'] ?? '',
          'salary'      => $r['nr_salary'] ?? '',
          'salaryPeriod'=> $r['nr_salary_period'] ?? '',
          'classification' => $r['nr_classification'] ?? '',
          'summary'     => $r['professional_summary'] ?? '',
          'bio'         => $r['bio'] ?? '',
          'phone'       => $r['phone'] ?? '',
          'country'     => $r['country_name'] ?? '',
          'resumePath'  => !empty($r['resume_path']) ? '../' . $r['resume_path'] : '',
          'resumeName'  => $r['resume_name'] ?? '',
        ];
        $ci++;
    }
} catch (PDOException $e) {
    error_log('[AntCareers] recruiter people search: ' . $e->getMessage());
}
$peopleJson = json_encode($peopleList, JSON_HEX_TAG | JSON_HEX_AMP);

$countrySidebarOptionsHtml = '<option value="">All countries</option>';
foreach (getCountries() as $country) {
  $name = (string)$country['name'];
  $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $countrySidebarOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
}
$countrySidebarOptionsHtml .= '<option value="Remote">Remote</option>';

/* 31-industry checkbox values for sidebar filter */
$industryCheckboxesHtml = '';
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
foreach ($industryKeys as $ind) {
  $esc = htmlspecialchars($ind, ENT_QUOTES, 'UTF-8');
  $industryCheckboxesHtml .= '<label class="ms-item"><input type="checkbox" value="' . $esc . '"><span>' . $esc . '</span></label>';
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
      const t=localStorage.getItem('ac-theme')||'light';
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
    body.light .person-modal-btn.secondary:hover { border-color:var(--red-vivid); color:#1A0A09; }
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
    .role-section { display:block; margin-top:8px; }
    .role-section-label { font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); margin:10px 0 6px; display:block; }
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

    /* Anim */
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.05s; } .anim-d2 { animation-delay:0.1s; } .anim-d3 { animation-delay:0.15s; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }

    /* Toggle switch */
    .fs-toggle-row { display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
    .fs-toggle-row input { display:none; }
    .fs-toggle-switch { width:36px; height:20px; border-radius:10px; background:var(--soil-line); position:relative; transition:background 0.2s; flex-shrink:0; }
    .fs-toggle-switch::after { content:''; position:absolute; top:3px; left:3px; width:14px; height:14px; border-radius:50%; background:#fff; transition:transform 0.2s; }
    .fs-toggle-row input:checked ~ .fs-toggle-switch { background:var(--red-vivid); }
    .fs-toggle-row input:checked ~ .fs-toggle-switch::after { transform:translateX(16px); }
    .fs-toggle-text { font-size:12px; color:var(--text-mid); font-weight:500; }

    /* Expanded person modal */
    .person-modal-box { width:min(640px,100%); max-height:85vh; overflow-y:auto; scrollbar-width:thin; }
    .pm-detail-row { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-muted); margin-top:4px; }
    .pm-detail-row i { width:14px; text-align:center; color:var(--red-mid); font-size:11px; }
    .pm-section-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px; }
    .pm-info-card { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:8px; padding:10px 12px; }
    .pm-info-label { font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-muted); margin-bottom:4px; }
    .pm-info-value { font-size:13px; font-weight:600; color:var(--text-light); }
    .pm-about { font-size:13px; color:var(--text-mid); line-height:1.6; margin-top:6px; white-space:pre-wrap; }
    .pm-resume-btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:8px; border:1px solid rgba(76,175,112,0.3); background:rgba(76,175,112,0.08); color:#6ccf8a; font-size:12px; font-weight:700; font-family:var(--font-body); cursor:pointer; text-decoration:none; transition:0.18s; margin-top:8px; }
    .pm-resume-btn:hover { background:rgba(76,175,112,0.15); border-color:rgba(76,175,112,0.5); }
    .pm-resume-btn i { font-size:13px; }
    body.light .pm-info-card { background:#F5EEEC; border-color:#E0CECA; }
    body.light .pm-info-value { color:#1A0A09; }
    body.light .pm-about { color:#4A2828; }
    body.light .pm-resume-btn { background:rgba(76,175,112,0.06); border-color:rgba(76,175,112,0.25); color:#2E7D4C; }
    @media(max-width:500px) { .pm-section-grid { grid-template-columns:1fr; } }

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
    body.light .pc-btn:hover { color:#1A0A09; background:#F0E4E2; }
    body.light .pc-btn.primary:hover { color:#fff; }
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

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

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
    <button class="search-btn" onclick="filterPeople()"><i class="fas fa-search"></i> Search</button>
  </div>

  <div class="layout">

    <!-- FILTER SIDEBAR -->
    <aside class="filter-sidebar anim anim-d2">
      <div class="fs-title"><i class="fas fa-sliders-h"></i> Filters</div>

      <!-- Open to Work Toggle -->
      <div class="fs-section">
        <div class="fs-section-label">Open to Work</div>
        <label class="fs-toggle-row">
          <input type="checkbox" id="filterOpenToWork" onchange="filterPeople()">
          <span class="fs-toggle-switch"></span>
          <span class="fs-toggle-text">Show only open to work</span>
        </label>
      </div>

      <div class="fs-divider"></div>

      <!-- Availability -->
      <div class="fs-section">
        <div class="fs-section-label">Availability</div>
        <div class="ms-wrap" id="msAvailability" data-default="Any availability">
          <button class="ms-trigger" type="button"><span class="ms-text">Any availability</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Now"><span>Available Now</span></label>
            <label class="ms-item"><input type="checkbox" value="Open"><span>Open to Opportunities</span></label>
            <label class="ms-item"><input type="checkbox" value="Not Looking"><span>Not Looking</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <!-- Industry -->
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

      <!-- Experience Level -->
      <div class="fs-section">
        <div class="fs-section-label">Experience Level</div>
        <div class="ms-wrap" id="msExperience" data-default="Any level">
          <button class="ms-trigger" type="button"><span class="ms-text">Any level</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Entry"><span>Entry</span></label>
            <label class="ms-item"><input type="checkbox" value="Junior"><span>Junior</span></label>
            <label class="ms-item"><input type="checkbox" value="Mid"><span>Mid</span></label>
            <label class="ms-item"><input type="checkbox" value="Senior"><span>Senior</span></label>
            <label class="ms-item"><input type="checkbox" value="Lead"><span>Lead</span></label>
            <label class="ms-item"><input type="checkbox" value="Executive"><span>Executive</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <!-- Work Type -->
      <div class="fs-section">
        <div class="fs-section-label">Work Type</div>
        <div class="ms-wrap" id="msWorkType" data-default="Any work type">
          <button class="ms-trigger" type="button"><span class="ms-text">Any work type</span><i class="fas fa-chevron-down ms-arrow"></i></button>
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

      <!-- Preferred Location -->
      <div class="fs-section">
        <div class="fs-section-label">Preferred Location</div>
        <select class="fs-select" id="sidebarLocationFilter" onchange="filterPeople()">
          <?= $countrySidebarOptionsHtml ?>
        </select>
        <input type="text" id="sidebarLocationKeyword" class="fs-text-input" placeholder="Enter region, province or city" oninput="filterPeople()" style="margin-top:6px;">
      </div>

      <div class="fs-divider"></div>

      <!-- Right to Work -->
      <div class="fs-section">
        <div class="fs-section-label">Right to Work</div>
        <div class="ms-wrap" id="msRightToWork" data-default="Any">
          <button class="ms-trigger" type="button"><span class="ms-text">Any</span><i class="fas fa-chevron-down ms-arrow"></i></button>
          <div class="ms-panel">
            <label class="ms-item"><input type="checkbox" value="Citizen"><span>Citizen</span></label>
            <label class="ms-item"><input type="checkbox" value="Permanent Resident"><span>Permanent Resident</span></label>
            <label class="ms-item"><input type="checkbox" value="Work Visa"><span>Work Visa</span></label>
            <label class="ms-item"><input type="checkbox" value="Student Visa"><span>Student Visa</span></label>
            <label class="ms-item"><input type="checkbox" value="Require Sponsorship"><span>Require Sponsorship</span></label>
          </div>
        </div>
      </div>

      <div class="fs-divider"></div>

      <!-- Salary Budget -->
      <div class="fs-section">
        <div class="fs-section-label">Salary Budget</div>
        <div style="display:flex;gap:6px;align-items:center;">
          <input type="number" id="filterSalaryMin" class="fs-text-input" placeholder="Min" oninput="filterPeople()" style="flex:1;">
          <span style="color:var(--text-muted);font-size:11px;">–</span>
          <input type="number" id="filterSalaryMax" class="fs-text-input" placeholder="Max" oninput="filterPeople()" style="flex:1;">
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
          <option>Mutual Followers</option>
        </select>
      </div>
      <div class="people-grid anim anim-d3" id="peopleGrid"></div>
    </div>

  </div>
</div>

<div class="person-modal-overlay" id="personModal" style="display:none;">
  <div class="person-modal-box">
    <div id="personModalBody"></div>
  </div>
</div>

<script>
  const peopleData = <?= $peopleJson ?>;
  const peopleById = Object.fromEntries(peopleData.map(p => [String(p.id), p]));

  let connections = new Set();

  /* ── JOB ROLES DATA (31-industry system) ── */
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
      cb.addEventListener('change', () => { updateMsLabel(msWrap); filterPeople(); });
    });
  }

  function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function openPersonView(personId) {
    const person = peopleById[String(personId)];
    if (!person) return;
    if (person.accountType === 'employer' && person.profileUrl) {
      window.location.href = person.profileUrl;
      return;
    }

    const skills = (person.skills || []).slice(0, 8).map(s => `<span class="person-skill-chip">${esc(s)}</span>`).join('');
    const statusLabel = person.status === 'seeking' ? 'Open to work' : person.status === 'hiring' ? 'Actively hiring' : 'Available';
    const statusClass = person.status === 'seeking' ? 'seeking' : person.status === 'hiring' ? 'hiring' : 'neutral';

    let infoCards = '';
    if (person.exp) infoCards += `<div class="pm-info-card"><div class="pm-info-label">Experience</div><div class="pm-info-value">${esc(person.exp)}</div></div>`;
    if (person.availability) infoCards += `<div class="pm-info-card"><div class="pm-info-label">Availability</div><div class="pm-info-value">${esc(person.availability)}</div></div>`;
    if (person.workTypes) infoCards += `<div class="pm-info-card"><div class="pm-info-label">Work Type</div><div class="pm-info-value">${esc(person.workTypes)}</div></div>`;
    if (person.rightToWork) infoCards += `<div class="pm-info-card"><div class="pm-info-label">Right to Work</div><div class="pm-info-value">${esc(person.rightToWork)}</div></div>`;
    if (person.classification) infoCards += `<div class="pm-info-card"><div class="pm-info-label">Classification</div><div class="pm-info-value">${esc(person.classification)}</div></div>`;
    if (person.salary) {
      const salaryDisplay = person.salary + (person.salaryPeriod ? ' ' + person.salaryPeriod : '');
      infoCards += `<div class="pm-info-card"><div class="pm-info-label">Salary Expectation</div><div class="pm-info-value">${esc(salaryDisplay)}</div></div>`;
    }

    const aboutText = person.summary || person.bio || '';
    const aboutSection = aboutText
      ? `<div class="person-modal-section"><div class="person-modal-section-label">About</div><div class="pm-about">${esc(aboutText)}</div></div>`
      : '';

    const resumeSection = person.resumePath
      ? `<div class="person-modal-section"><div class="person-modal-section-label">Resume</div><a class="pm-resume-btn" href="${esc(person.resumePath)}" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> ${esc(person.resumeName || 'View Resume')}</a></div>`
      : '';

    document.getElementById('personModalBody').innerHTML = `
      <div class="person-modal-head">
        <div class="person-modal-avatar" style="background:${person.color};">${person.avatarUrl ? `<img src="${esc(person.avatarUrl)}" alt="">` : esc(person.avatar)}</div>
        <div class="person-modal-meta">
          <div class="person-modal-name">${esc(person.name)}</div>
          <div class="person-modal-title">${esc(person.title)}</div>
          <div class="person-modal-location"><i class="fas fa-map-marker-alt"></i> ${esc(person.location)}</div>
          ${person.phone ? `<div class="pm-detail-row"><i class="fas fa-phone"></i> ${esc(person.phone)}</div>` : ''}
        </div>
      </div>
      <div class="person-modal-status ${statusClass}">${statusLabel}</div>
      ${aboutSection}
      ${infoCards ? `<div class="person-modal-section"><div class="person-modal-section-label">Details</div><div class="pm-section-grid">${infoCards}</div></div>` : ''}
      <div class="person-modal-section">
        <div class="person-modal-section-label">Skills</div>
        <div class="person-skill-row">${skills || '<span class="person-skill-empty">No skills listed</span>'}</div>
      </div>
      ${resumeSection}
      <div class="person-modal-actions">
        <button class="person-modal-btn secondary" type="button" onclick="openPersonMessageFullPage(${person.id})"><i class="fas fa-comment-dots"></i> Message</button>
        <button class="person-modal-btn primary" type="button" onclick="closePersonView()">Close</button>
      </div>`;

    document.getElementById('personModal').style.display = 'flex';
  }

  function openPersonMessage(personId) {
    const person = peopleById[String(personId)];
    if (!person) return;
    window.dispatchEvent(new CustomEvent('recruiter:openMessageSidebar', {
      detail: { userId: person.id, userName: person.name }
    }));
  }

  function openPersonMessageFullPage(personId) {
    const person = peopleById[String(personId)];
    if (!person) return;
    window.location.href = 'recruiter_messages.php?user=' + person.id;
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
            <button class="pc-connect ${isConnected?'connected':''}" title="${isConnected?'Following':'Follow'}" onclick="event.stopPropagation();toggleConnect(${p.id},this)">
              <i class="fas fa-${isConnected?'check':'user-plus'}"></i>
            </button>
          </div>
          <div class="pc-skills">${skills}</div>
          <div class="pc-footer">
            <div class="pc-mutual">${p.mutual>0?`<i class="fas fa-users"></i> ${p.mutual} mutual follower${p.mutual!==1?'s':''}`:``}</div>
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

  function filterPeople() {
    const q = document.getElementById('peopleSearch').value.toLowerCase();
    const openToWork = document.getElementById('filterOpenToWork').checked;
    const availabilities = getMsValues('msAvailability');
    const industries = getMsValues('msIndustry');
    const jobRoles = getMsValues('msJobRole');
    const expLevels = getMsValues('msExperience');
    const workTypes = getMsValues('msWorkType');
    const locCountry = document.getElementById('sidebarLocationFilter')?.value || '';
    const locKeyword = (document.getElementById('sidebarLocationKeyword')?.value || '').toLowerCase();
    const rightToWorkVals = getMsValues('msRightToWork');
    const salaryMin = parseFloat(document.getElementById('filterSalaryMin').value) || 0;
    const salaryMax = parseFloat(document.getElementById('filterSalaryMax').value) || 0;

    let filtered = peopleData.filter(p => {
      const matchQ = !q || p.name.toLowerCase().includes(q) || p.title.toLowerCase().includes(q) || p.skills.some(s=>s.toLowerCase().includes(q));
      const matchOtw = !openToWork || p.status === 'seeking';
      const matchAvail = !availabilities.length || availabilities.includes(p.availability);
      const matchInd = !industries.length || industries.some(ind => (p.classification || '').toLowerCase().includes(ind.toLowerCase()));
      const matchRole = !jobRoles.length || jobRoles.some(role => (p.classification || '').toLowerCase().includes(role.toLowerCase()) || (p.title || '').toLowerCase().includes(role.toLowerCase()));
      const matchExp = !expLevels.length || expLevels.includes(p.exp);
      const matchWork = !workTypes.length || workTypes.some(wt => (p.workTypes || '').includes(wt));
      const matchCountry = !locCountry || p.location.includes(locCountry) || (p.country || '').includes(locCountry);
      const matchLocKw = !locKeyword || p.location.toLowerCase().includes(locKeyword);
      const matchRtw = !rightToWorkVals.length || rightToWorkVals.some(rtw => p.rightToWork && p.rightToWork.includes(rtw));
      const pSalary = parseFloat(p.salary) || 0;
      const matchSalMin = !salaryMin || pSalary >= salaryMin;
      const matchSalMax = !salaryMax || pSalary <= salaryMax;
      return matchQ && matchOtw && matchAvail && matchInd && matchRole && matchExp && matchWork && matchCountry && matchLocKw && matchRtw && matchSalMin && matchSalMax;
    });
    renderPeople(filtered);
  }

  function resetFilters() {
    document.getElementById('peopleSearch').value = '';
    document.getElementById('filterOpenToWork').checked = false;
    document.getElementById('sidebarLocationFilter').value = '';
    document.getElementById('sidebarLocationKeyword').value = '';
    document.getElementById('filterSalaryMin').value = '';
    document.getElementById('filterSalaryMax').value = '';
    document.querySelectorAll('.ms-wrap').forEach(wrap => {
      wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
      updateMsLabel(wrap);
    });
    updateRolePicker([]);
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
      const wrap = cb.closest('.ms-wrap');
      updateMsLabel(wrap);
      if (wrap.id === 'msIndustry') { updateRolePicker(getMsValues('msIndustry')); }
      filterPeople();
    });
  });

  function toggleConnect(personId, btn) {
    const key = String(personId);
    if (connections.has(key)) {
      connections.delete(key);
      btn.classList.remove('connected');
      btn.innerHTML = '<i class="fas fa-user-plus"></i>';
      btn.title = 'Follow';
      showToast('Unfollowed','fa-user-minus');
    } else {
      connections.add(key);
      btn.classList.add('connected');
      btn.innerHTML = '<i class="fas fa-check"></i>';
      btn.title = 'Following';
      showToast('Followed!','fa-heart');
    }
  }

  // SEARCH KEYUP
  document.getElementById('peopleSearch').addEventListener('keyup', filterPeople);

  // INIT
  renderPeople(peopleData);
</script>
</body>
</html>
