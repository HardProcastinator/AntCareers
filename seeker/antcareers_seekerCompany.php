<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/job_titles.php';
requireLogin('seeker');
$user = getUser();
// Convenience aliases for page templates that use the old variable names
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'company';

/* ── Fetch companies from DB ── */
$companiesList = [];
try {
    $db = getDB();
    $st = $db->prepare("
        SELECT
            cp.user_id,
            cp.company_name,
            cp.industry,
            cp.company_size,
            cp.about,
            cp.logo_path,
            cp.is_verified,
            CONCAT_WS(', ', NULLIF(cp.city,''), NULLIF(cp.province,''), NULLIF(cp.country,'')) AS location,
            cp.perks,
            COUNT(j.id) AS open_roles
        FROM company_profiles cp
        JOIN users u ON u.id = cp.user_id AND u.account_type = 'employer'
        LEFT JOIN jobs j ON j.employer_id = cp.user_id AND j.status = 'Active'
            AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        GROUP BY cp.user_id
        ORDER BY open_roles DESC, cp.company_name ASC
    ");
    $st->execute();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tags = [];
        if ($r['industry']) $tags[] = $r['industry'];
        $perks = json_decode((string)($r['perks'] ?? '[]'), true) ?: [];
        foreach (array_slice($perks, 0, 2) as $p) { $tags[] = $p; }
        $companiesList[] = [
            'id'        => (int)$r['user_id'],
            'name'      => $r['company_name'],
            'industry'  => $r['industry'] ?? 'Other',
            'size'      => $r['company_size'] ?? 'Unknown',
            'location'  => $r['location'] ?: 'Not specified',
            'desc'      => $r['about'] ?? '',
            'emoji'     => '🏢',
            'color'     => 'linear-gradient(135deg,var(--red-vivid),var(--red-deep))',
            'logo'      => ($r['logo_path'] ?? '') ? '../' . $r['logo_path'] : '',
            'jobs'      => (int)$r['open_roles'],
            'verified'  => (bool)$r['is_verified'],
            'following' => false,
            'tags'      => array_values(array_slice($tags, 0, 3)),
        ];
    }
} catch (PDOException $e) {
    error_log('[AntCareers] companies fetch: ' . $e->getMessage());
}

/* ── Mark which companies the current seeker already follows ── */
if (!empty($companiesList)) {
    try {
        $db = $db ?? getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS company_follows (
            id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            follower_user_id BIGINT UNSIGNED NOT NULL,
            employer_user_id BIGINT UNSIGNED NOT NULL,
            followed_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_follow (follower_user_id, employer_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $fStmt = $db->prepare(
            "SELECT employer_user_id FROM company_follows WHERE follower_user_id = :uid"
        );
        $fStmt->execute([':uid' => $user['id']]);
        $followingIds = array_flip($fStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($companiesList as &$co) {
            $co['following'] = isset($followingIds[$co['id']]);
        }
        unset($co);
    } catch (\Throwable $e) {
        error_log('[AntCareers] follow state fetch: ' . $e->getMessage());
    }
}

$companyIndustryValues = array_values(array_filter(array_unique(array_map(
  static fn(array $company): string => trim((string)($company['industry'] ?? '')),
  $companiesList
))));
$sharedIndustryValues = array_column(getIndustryFilterOptions(), 'value');
$industryFilterValues = array_values(array_unique(array_merge($sharedIndustryValues, $companyIndustryValues)));
$industryCheckboxesHtml = '';
foreach ($industryFilterValues as $industryValue) {
  $escIndustry = htmlspecialchars((string)$industryValue, ENT_QUOTES, 'UTF-8');
  $industryCheckboxesHtml .= '<label class="ms-item"><input type="checkbox" value="' . $escIndustry . '"><span>' . $escIndustry . '</span></label>';
}

$companiesJson = json_encode($companiesList, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Companies — AntCareers</title>
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

    /* ── HEADER ── */
    .page-header { margin-bottom:28px; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .page-title em { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); }

    /* ── FEATURED BANNER ── */
    .featured-banner { background:linear-gradient(135deg,rgba(209,61,44,0.15),rgba(122,21,21,0.08)); border:1px solid rgba(209,61,44,0.25); border-radius:14px; padding:28px 32px; margin-bottom:32px; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; overflow:hidden; position:relative; }
    .featured-banner::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .fb-label { font-size:10px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:var(--amber); margin-bottom:8px; display:flex; align-items:center; gap:6px; }
    .fb-title { font-family:var(--font-display); font-size:22px; font-weight:700; color:#F5F0EE; margin-bottom:6px; }
    .fb-sub { font-size:13px; color:var(--text-muted); }
    .fb-stats { display:flex; gap:24px; flex-wrap:wrap; }
    .fb-stat { text-align:center; }
    .fb-stat-num { font-family:var(--font-display); font-size:28px; font-weight:700; color:var(--red-bright); line-height:1; }
    .fb-stat-lbl { font-size:11px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.05em; margin-top:3px; }

    /* ── Multi-select dropdown ── */
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

    /* ── SEARCH ROW ── */
    .search-row { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; position:relative; z-index:10; }
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

    /* ── COMPANY GRID ── */
    .company-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:16px; }

    /* ── COMPANY CARD ── */
    .company-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; overflow:hidden; transition:all 0.22s; cursor:pointer; position:relative; }
    .company-card:hover { border-color:rgba(209,61,44,0.45); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.35); }
    .cc-banner { height:72px; display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
    .cc-body { padding:20px; }
    .cc-logo { width:52px; height:52px; border-radius:12px; background:var(--soil-dark); border:2px solid var(--soil-line); display:flex; align-items:center; justify-content:center; font-size:22px; margin-top:-38px; margin-bottom:12px; position:relative; z-index:1; }
    .cc-name { font-family:var(--font-display); font-size:16px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .cc-industry { font-size:12px; color:var(--red-pale); font-weight:600; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
    .cc-industry i { font-size:10px; }
    .cc-desc { font-size:12px; color:var(--text-muted); line-height:1.6; margin-bottom:14px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .cc-meta { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:16px; }
    .cc-meta-item { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--text-muted); font-weight:600; }
    .cc-meta-item i { color:var(--red-mid); font-size:10px; }
    .cc-footer { display:flex; align-items:center; justify-content:space-between; padding-top:14px; border-top:1px solid var(--soil-line); }
    .cc-open-jobs { font-size:12px; font-weight:700; color:var(--red-pale); display:flex; align-items:center; gap:5px; }
    .cc-open-jobs i { font-size:10px; }
    .cc-actions { display:flex; gap:6px; }
    .cc-btn { padding:6px 13px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.18s; white-space:nowrap; }
    .cc-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .cc-btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .cc-btn.primary:hover { background:var(--red-bright); }
    .verified-badge { position:absolute; top:12px; right:12px; width:22px; height:22px; border-radius:50%; background:rgba(76,175,112,0.2); border:1px solid rgba(76,175,112,0.4); display:flex; align-items:center; justify-content:center; font-size:10px; color:#6ccf8a; }
    .follow-btn { position:absolute; top:10px; right:10px; width:28px; height:28px; border-radius:6px; background:rgba(10,9,9,0.6); border:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.18s; font-size:12px; color:rgba(255,255,255,0.6); z-index:2; }
    .follow-btn:hover { background:rgba(209,61,44,0.5); border-color:rgba(209,61,44,0.5); color:#fff; }
    .follow-btn.following { background:rgba(209,61,44,0.6); border-color:rgba(209,61,44,0.6); color:#fff; }

    /* ── TOAST ── */
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
    body.light .featured-banner { background:linear-gradient(135deg,rgba(209,61,44,0.07),rgba(122,21,21,0.03)); border-color:rgba(209,61,44,0.18); }
    body.light .fb-title { color:#1A0A09; }
    body.light .search-box { background:#FFFFFF; border-color:#E0CECA; }
    body.light .search-box input { color:#1A0A09; }
    body.light .filter-select, body.light .sort-select { background:#FFFFFF; border-color:#E0CECA; color:#4A2828; }
    body.light .ms-trigger { background-color:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .ms-panel { background:#FFFFFF; border-color:#E0CECA; box-shadow:0 8px 24px rgba(0,0,0,0.1); }
    body.light .ms-item { color:#4A2828; }
    body.light .ms-item:hover { background:#FEF0EE; }
    body.light .search-row .ms-trigger { background:#FFFFFF; border-color:#E0CECA; color:#4A2828; }
    body.light .search-row .ms-trigger:hover { background-color:#FEF0EE; }
    body.light .search-row .ms-wrap.open .ms-trigger { border-color:var(--red-vivid); }
    body.light .company-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .cc-logo { background:#F5EEEC; border-color:#E0CECA; }
    body.light .cc-name { color:#1A0A09; }
    body.light .cc-footer { border-color:#E0CECA; }
    @media(max-width:760px) { .company-grid{grid-template-columns:1fr} .search-row{flex-direction:column} .nav-links{display:none} .hamburger{display:flex} .featured-banner{flex-direction:column;} }
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
    <div class="page-title">Browse <em>Companies</em></div>
    <div class="page-sub">Explore top employers, follow companies you love, and find open roles.</div>
  </div>

  <!-- FEATURED BANNER -->
  <div class="featured-banner anim anim-d1">
    <div>
      <div class="fb-label"><i class="fas fa-star"></i> Platform Highlights</div>
      <div class="fb-title">500+ Companies Hiring Now</div>
      <div class="fb-sub">From startups to Fortune 500 — find your next employer on AntCareers.</div>
    </div>
    <div class="fb-stats">
      <div class="fb-stat"><div class="fb-stat-num">500+</div><div class="fb-stat-lbl">Companies</div></div>
      <div class="fb-stat"><div class="fb-stat-num">2.4k</div><div class="fb-stat-lbl">Open Roles</div></div>
      <div class="fb-stat"><div class="fb-stat-num">120+</div><div class="fb-stat-lbl">Industries</div></div>
    </div>
  </div>

  <!-- SEARCH ROW -->
  <div class="search-row anim anim-d2">
    <div class="search-box">
      <span class="si"><i class="fas fa-search"></i></span>
      <input type="text" id="companySearch" placeholder="Search companies by name or keyword…">
    </div>
    <select class="filter-select" id="industryFilter" style="display:none;">
      <option value="">All Industries</option>
    </select>
    <div class="ms-wrap" id="msIndustryFilter" data-default="All Industries">
      <button class="ms-trigger" type="button"><span class="ms-text">All Industries</span><i class="fas fa-chevron-down ms-arrow"></i></button>
      <div class="ms-panel">
        <?= $industryCheckboxesHtml ?>
      </div>
    </div>
    <select class="filter-select" id="sizeFilter">
      <option value="">All Sizes</option>
      <option>Startup (1–50)</option>
      <option>Small (51–200)</option>
      <option>Mid-size (201–1000)</option>
      <option>Large (1000+)</option>
    </select>
    <button class="search-btn" onclick="window.filterCompanies()"><i class="fas fa-search"></i> Search</button>
  </div>

  <!-- RESULTS META -->
  <div class="results-meta anim anim-d2">
    <div class="results-count"><strong id="resultCount">12</strong> companies found</div>
    <select class="sort-select" onchange="window.filterCompanies()">
      <option>Most Relevant</option>
      <option>Most Open Jobs</option>
      <option>Recently Added</option>
    </select>
  </div>

  <!-- COMPANY GRID -->
  <div class="company-grid anim anim-d3" id="companyGrid"></div>

</div>

<script>
  // Expose interactive functions globally so inline onclick handlers work
  // regardless of what the navbar include does to scope

  function getMsValues(id) {
    var w = document.getElementById(id);
    return w ? [].slice.call(w.querySelectorAll('input:checked')).map(function(i){ return i.value; }) : [];
  }
  function updateMsLabel(wrap) {
    var checked = [].slice.call(wrap.querySelectorAll('input:checked'));
    var label = wrap.querySelector('.ms-text');
    var def = wrap.dataset.default || 'Select';
    if (!checked.length) label.textContent = def;
    else if (checked.length === 1) label.textContent = checked[0].nextElementSibling ? checked[0].nextElementSibling.textContent : checked[0].value;
    else label.textContent = checked.length + ' selected';
  }

  window.filterCompanies = function() {
    var q = document.getElementById('companySearch').value.toLowerCase();
    var industries = getMsValues('msIndustryFilter');
    var size = document.getElementById('sizeFilter').value;
    var filtered = window._acCompanies.filter(function(c) {
      var matchQ = !q || c.name.toLowerCase().indexOf(q) !== -1 || c.desc.toLowerCase().indexOf(q) !== -1 || c.tags.some(function(t){ return t.toLowerCase().indexOf(q) !== -1; });
      var matchI = !industries.length || industries.indexOf(c.industry) !== -1;
      var matchS = !size || c.size === size;
      return matchQ && matchI && matchS;
    });
    window.renderCompanies(filtered);
  };

  window.toggleFollow = function(id, name) {
    fetch('follow_company.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'employer_id=' + encodeURIComponent(id)
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (!data.ok) { window.showToast('Please log in to follow companies.', 'fa-exclamation-circle'); return; }
      if (data.following) {
        window._acFollowing.add(id);
        window.showToast('Now following ' + name + '!', 'fa-heart');
      } else {
        window._acFollowing.delete(id);
        window.showToast('Unfollowed ' + name, 'fa-heart-broken');
      }
      window.filterCompanies();
    })
    .catch(function() { window.showToast('Connection error. Try again.', 'fa-exclamation-circle'); });
  };

  window.showToast = function(msg, icon) {
    let toast = document.getElementById('acToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'acToast';
      toast.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(12px);background:#1C1818;border:1px solid rgba(209,61,44,0.35);color:#F5F0EE;padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;z-index:9999;opacity:0;transition:opacity 0.22s,transform 0.22s;pointer-events:none;white-space:nowrap;box-shadow:0 8px 32px rgba(0,0,0,0.5);';
      document.body.appendChild(toast);
    }
    toast.innerHTML = '<i class="fas ' + icon + '" style="color:var(--red-pale)"></i> ' + msg;
    toast.style.opacity = '1';
    toast.style.transform = 'translateX(-50%) translateY(0)';
    clearTimeout(toast._t);
    toast._t = setTimeout(function() {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(-50%) translateY(12px)';
    }, 2400);
  };

  window.renderCompanies = function(data) {
    const grid = document.getElementById('companyGrid');
    document.getElementById('resultCount').textContent = data.length;
    if (!data.length) {
      grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--text-muted);"><i class="fas fa-building" style="font-size:36px;margin-bottom:14px;display:block;color:var(--soil-line);"></i><h3 style="font-family:var(--font-display);font-size:20px;color:var(--text-mid);margin-bottom:8px;">No companies found</h3><p>Try adjusting your filters.</p></div>';
      return;
    }
    grid.innerHTML = data.map(function(c, i) {
      const isFollowing = window._acFollowing.has(c.id);
      const tags = c.tags.map(function(t){ return '<span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:3px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);">' + t + '</span>'; }).join('');
      const safeName = c.name.replace(/'/g, "\\'");
      return '<div class="company-card anim" style="animation-delay:' + (i*0.04) + 's;cursor:pointer;" onclick="window.location.href=\'public_company_profile.php?employer_id=' + c.id + '\'">' +
        '<div class="cc-banner" style="background:' + c.color + ';">' +
          '<button class="follow-btn ' + (isFollowing?'following':'') + '" onclick="event.stopPropagation();window.toggleFollow(' + c.id + ',\'' + safeName + '\')" title="' + (isFollowing?'Unfollow':'Follow') + '">'+
            '<i class="fas fa-heart" style="color:' + (isFollowing?'#ff6b6b':'rgba(255,255,255,0.6)') + '"></i>' +
          '</button>' +
        '</div>' +
        '<div class="cc-body">' +
          '<div class="cc-logo">' + (c.logo ? '<img src="' + c.logo + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.outerHTML=\'' + c.emoji + '\'">' : c.emoji) + '</div>' +
          '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:6px;">' +
            '<div class="cc-name">' + c.name + '</div>' +
            (c.verified ? '<span style="font-size:10px;color:#6ccf8a;background:rgba(76,175,112,0.12);border:1px solid rgba(76,175,112,0.25);padding:2px 7px;border-radius:3px;font-weight:700;white-space:nowrap;flex-shrink:0;">✓ Verified</span>' : '') +
          '</div>' +
          '<div class="cc-industry"><i class="fas fa-tag"></i> ' + c.industry + '</div>' +
          '<div class="cc-desc">' + c.desc + '</div>' +
          '<div class="cc-meta">' +
            '<span class="cc-meta-item"><i class="fas fa-users"></i> ' + c.size + '</span>' +
            '<span class="cc-meta-item"><i class="fas fa-map-marker-alt"></i> ' + c.location + '</span>' +
          '</div>' +
          '<div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px;">' + tags + '</div>' +
          '<div class="cc-footer">' +
            '<div class="cc-open-jobs"><i class="fas fa-briefcase"></i> ' + c.jobs + ' open role' + (c.jobs!==1?'s':'') + '</div>' +
            '<div class="cc-actions">' +
              '<button class="cc-btn" onclick="event.stopPropagation();window.toggleFollow(' + c.id + ',\'' + safeName + '\')">' + (isFollowing?'Following ♥':'Follow') + '</button>' +
              '<button class="cc-btn primary" onclick="event.stopPropagation();window.location.href=\'public_company_profile.php?employer_id=' + c.id + '\'" >View Jobs</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    }).join('');
  };

  const companiesData = <?= $companiesJson ?>;

  // Initialize global state
  window._acCompanies = companiesData;
  window._acFilter = 'all';
  window._acFollowing = new Set(companiesData.filter(function(c){ return c.following; }).map(function(c){ return c.id; }));

  // SEARCH KEYUP
  document.getElementById('companySearch').addEventListener('keyup', window.filterCompanies);

  // Multi-select dropdown behavior
  document.querySelectorAll('.ms-trigger').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      var wrap = btn.closest('.ms-wrap');
      var wasOpen = wrap.classList.contains('open');
      document.querySelectorAll('.ms-wrap.open').forEach(function(w){ w.classList.remove('open'); });
      if (!wasOpen) wrap.classList.add('open');
    });
  });
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.ms-wrap')) document.querySelectorAll('.ms-wrap.open').forEach(function(w){ w.classList.remove('open'); });
  });
  document.querySelectorAll('.ms-wrap input[type="checkbox"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
      updateMsLabel(cb.closest('.ms-wrap'));
      window.filterCompanies();
    });
  });

  // INIT
  window.renderCompanies(companiesData);
</script>
</body>
</html>