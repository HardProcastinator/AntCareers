<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';
requireLogin('seeker');
$user = getUser();
// Convenience aliases for page templates that use the old variable names
$fullName  = $user['fullName'];
$firstName = $user['firstName'];
$initials  = $user['initials'];
$userEmail = $user['email'];
$navActive = 'saved';
$seekerId  = (int)$_SESSION['user_id'];

/* ── Fetch saved jobs from DB ── */
$savedJobs = [];
$rows = [];
try {
    $db = getDB();
    // Full query — works after migration (setup, skills_required, industry, experience_level exist)
    $st = $db->prepare("
        SELECT
            j.id, j.employer_id, j.title, j.description, j.location, j.job_type, j.setup AS work_setup,
            j.salary_min, j.salary_max, j.salary_currency, j.skills_required,
            j.industry, j.experience_level, j.created_at,
            COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown') AS company,
            cp.logo_path AS logo_url,
            sj.saved_at
        FROM saved_jobs sj
        JOIN jobs j ON j.id = sj.job_id
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE sj.user_id = ?
          AND j.status = 'Active'
        ORDER BY sj.saved_at DESC
    ");
    $st->execute([$seekerId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback for pre-migration server (no setup/skills_required/industry columns)
    try {
        $st = $db->prepare("
            SELECT
                j.id, j.employer_id, j.title, j.description, j.location, j.job_type, 'On-site' AS work_setup,
                j.salary_min, j.salary_max, j.salary_currency, NULL AS skills_required,
                NULL AS industry, NULL AS experience_level, j.created_at,
                COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown') AS company,
                cp.logo_path AS logo_url,
                sj.saved_at
            FROM saved_jobs sj
            JOIN jobs j ON j.id = sj.job_id
            JOIN users u ON u.id = j.employer_id
            LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
            WHERE sj.user_id = ?
            ORDER BY sj.saved_at DESC
        ");
        $st->execute([$seekerId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        error_log('[AntCareers] saved jobs fallback fetch: ' . $e2->getMessage());
    }
}
foreach ($rows as $r) {
    $salMin = (float)($r['salary_min'] ?? 0);
    $salMax = (float)($r['salary_max'] ?? 0);
    $cur    = currencySymbol($r['salary_currency'] ?? 'PHP');
    if ($salMin && $salMax)      $salary = $cur . number_format($salMin) . ' – ' . $cur . number_format($salMax);
    elseif ($salMin)             $salary = $cur . number_format($salMin) . '+';
    else                         $salary = 'Not disclosed';
    $tags = array_filter(array_map('trim', explode(',', (string)($r['skills_required'] ?? ''))));
    $savedJobs[] = [
        'id'          => (int)$r['id'],
        'employerId'  => (int)$r['employer_id'],
        'title'       => $r['title'],
        'company'     => $r['company'],
        'location'    => $r['location'] ?? 'Not specified',
        'type'        => $r['job_type'],
        'workSetup'   => $r['work_setup'] ?? 'On-site',
        'experience'  => $r['experience_level'] ?? '',
        'industry'    => $r['industry'] ?? '',
        'salary'      => $salary,
        'salaryMin'   => (int)($salMin / 1000),
        'icon'        => 'fa-briefcase',
        'savedDate'   => date('M j, Y', strtotime($r['saved_at'])),
        'featured'    => false,
        'tags'        => array_values(array_slice($tags, 0, 5)),
        'description' => $r['description'] ?? '',
    ];
}

/* ── Already-applied job IDs ── */
$appliedIds = [];
try {
    $a = $db->prepare("SELECT job_id FROM applications WHERE seeker_id = ?");
    $a->execute([$seekerId]);
    $appliedIds = array_column($a->fetchAll(), 'job_id');
} catch (PDOException $e) {}

$savedJobsJson = json_encode($savedJobs, JSON_HEX_TAG | JSON_HEX_AMP);
$appliedIdsJson = json_encode(array_map('intval', $appliedIds));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Saved Jobs</title>
  <script>
    (function(){
      const p=new URLSearchParams(window.location.search).get('theme');
      const t=p||localStorage.getItem('ac-theme')||'light';
      if(p) localStorage.setItem('ac-theme',p);
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
      --amber:#D4943A; --green:#4CAF70;
      --font-display:'Playfair Display',Georgia,serif;
      --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); min-height:100vh; -webkit-font-smoothing:antialiased; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .pd-sep { border:none; border-top:1px solid var(--soil-line); margin:4px 0; }
    .pd-logout { color:#E05050 !important; } .pd-logout i { color:#E05050 !important; }

    .main-wrap { max-width:1380px; margin:0 auto; padding:28px 24px 60px; position:relative; z-index:1; }
    .content-layout { display:grid; grid-template-columns:1fr; gap:20px; align-items:start; }

    .sidebar { position:sticky; top:72px; }
    .sidebar-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; }
    .sidebar-head { padding:16px 18px 12px; border-bottom:1px solid var(--soil-line); }
    .sidebar-title { font-size:12px; font-weight:700; color:var(--text-light); display:flex; align-items:center; gap:7px; letter-spacing:0.07em; text-transform:uppercase; }
    .sidebar-title i { color:var(--red-bright); font-size:11px; }
    .sidebar-profile { padding:16px 16px 14px; border-bottom:1px solid var(--soil-line); }
    .sp-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--red-vivid),var(--red-deep)); display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:700; color:#fff; margin-bottom:10px; }
    .sp-name { font-size:14px; font-weight:700; color:var(--text-light); }
    .sp-role { font-size:11px; color:var(--red-pale); margin-top:2px; font-weight:600; letter-spacing:0.05em; }
    .sidebar-stats { padding:14px 16px; border-bottom:1px solid var(--soil-line); display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .sb-stat { background:var(--soil-hover); border-radius:7px; padding:10px 12px; }
    .sb-stat-num { font-size:18px; font-weight:800; color:var(--text-light); }
    .sb-stat-lbl { font-size:10px; color:var(--text-muted); font-weight:600; letter-spacing:0.05em; text-transform:uppercase; margin-top:2px; }
    .sb-nav-item { display:flex; align-items:center; gap:10px; padding:11px 18px; font-size:13px; font-weight:600; color:var(--text-muted); cursor:pointer; transition:all 0.18s; border:none; background:none; font-family:var(--font-body); width:100%; text-align:left; border-bottom:1px solid var(--soil-line); }
    .sb-nav-item:last-child { border-bottom:none; }
    .sb-nav-item:hover { color:var(--text-light); background:var(--soil-hover); }
    .sb-nav-item.active { color:var(--red-pale); background:rgba(209,61,44,0.08); border-right:2px solid var(--red-vivid); }
    .sb-nav-item i { width:16px; text-align:center; font-size:12px; color:var(--red-bright); }
    .sb-badge { margin-left:auto; background:var(--red-vivid); color:#fff; font-size:10px; font-weight:700; border-radius:10px; padding:1px 7px; }
    .sb-badge.green { background:var(--green); }
    .sb-badge.amber { background:var(--amber); color:#1A0A09; }
    .sb-browse-wrap { padding:12px 14px; border-top:1px solid var(--soil-line); }
    .sb-browse { display:flex; align-items:center; justify-content:center; gap:8px; padding:11px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s; width:100%; }
    .sb-browse:hover { background:var(--red-bright); transform:translateY(-1px); }

    .page-header { margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .page-title { font-family:var(--font-display); font-size:24px; font-weight:700; color:var(--text-light); }
    .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }

    /* SORT/FILTER BAR */
    .filter-bar { display:flex; gap:10px; align-items:center; margin-bottom:18px; flex-wrap:wrap; }
    .filter-select { padding:8px 14px; padding-right:32px; border-radius:7px; background:var(--soil-card); border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; cursor:pointer; outline:none; transition:0.18s; -webkit-appearance:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpolygon points='0,0 6,8 12,0' fill='%23927C7A'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; background-size:12px 8px; }
    .filter-select:focus, .filter-select:hover { border-color:var(--red-vivid); }
    .filter-select option { background:var(--soil-card); color:var(--text-mid); }
    .filter-label { font-size:12px; font-weight:600; color:var(--text-muted); }
    .clear-btn { margin-left:auto; padding:8px 14px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:600; cursor:pointer; transition:0.18s; }
    .clear-btn:hover { border-color:#E05050; color:#E05050; }

    /* JOB CARDS GRID */
    .jobs-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:14px; }
    .saved-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:18px; display:flex; flex-direction:column; gap:12px; transition:0.2s; position:relative; }
    .saved-card:hover { border-color:rgba(209,61,44,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.3); }
    .card-head { display:flex; align-items:flex-start; gap:12px; }
    .card-icon { width:44px; height:44px; border-radius:8px; background:rgba(209,61,44,0.12); display:flex; align-items:center; justify-content:center; font-size:17px; color:var(--red-pale); flex-shrink:0; }
    .card-info { flex:1; min-width:0; }
    .card-title { font-size:14px; font-weight:700; color:var(--text-light); line-height:1.3; }
    .card-company { font-size:12px; color:var(--red-pale); font-weight:600; margin-top:3px; }
    .unsave-btn { position:absolute; top:14px; right:14px; width:30px; height:30px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--red-pale); font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.18s; }
    .unsave-btn:hover { background:rgba(224,80,80,0.1); border-color:#E05050; color:#E05050; }
    .chips { display:flex; flex-wrap:wrap; gap:5px; }
    .chip { padding:3px 10px; border-radius:99px; background:var(--soil-hover); border:1px solid var(--soil-line); font-size:11px; font-weight:600; color:var(--text-muted); display:flex; align-items:center; gap:4px; }
    .chip i { font-size:10px; color:var(--red-pale); }
    .chip.featured { background:rgba(209,61,44,0.1); border-color:rgba(209,61,44,0.25); color:var(--red-pale); }
    .salary-row { font-size:15px; font-weight:800; color:var(--text-light); display:flex; align-items:center; gap:6px; }
    .salary-row small { font-size:11px; font-weight:500; color:var(--text-muted); }
    .saved-date { font-size:11px; color:var(--text-muted); }
    .card-actions { display:flex; gap:8px; }
    .btn-apply { flex:1; padding:9px; border-radius:7px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; display:flex; align-items:center; justify-content:center; gap:6px; }
    .btn-apply:hover { background:var(--red-bright); }
    .btn-view { padding:9px 14px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; transition:0.18s; }
    .btn-view:hover { border-color:var(--red-vivid); color:var(--red-pale); }

    .empty-state { text-align:center; padding:60px 20px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; }
    .empty-icon { font-size:40px; color:var(--soil-line); margin-bottom:14px; }
    .empty-title { font-size:16px; font-weight:700; color:var(--text-light); margin-bottom:6px; }
    .empty-sub { font-size:13px; color:var(--text-muted); margin-bottom:20px; }
    .btn-primary { padding:10px 22px; border-radius:8px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:13px; font-weight:700; cursor:pointer; transition:0.2s; }

    /* APPLY MODAL */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(6px); z-index:500; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; visibility:hidden; transition:all 0.2s; }
    .modal-overlay.open { opacity:1; visibility:visible; }
    .modal { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:28px; width:100%; max-width:480px; max-height:88vh; overflow-y:auto; transform:translateY(10px); transition:all 0.22s; }
    .modal-overlay.open .modal { transform:translateY(0); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
    .modal-title { font-size:17px; font-weight:700; color:var(--text-light); }
    .modal-close-btn { width:30px; height:30px; border-radius:6px; background:var(--soil-hover); border:none; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; }
    .form-group { margin-bottom:14px; }
    .form-label { display:block; font-size:11px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:5px; }
    .form-input { width:100%; padding:10px 14px; background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:7px; font-family:var(--font-body); font-size:13px; color:var(--text-light); outline:none; transition:0.18s; }
    .form-input:focus { border-color:var(--red-vivid); }
    .form-textarea { resize:vertical; min-height:80px; line-height:1.5; }
    .modal-footer { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; padding-top:14px; border-top:1px solid var(--soil-line); }
    .btn-cancel { padding:9px 18px; border-radius:7px; background:transparent; border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; font-weight:600; cursor:pointer; }
    html.theme-light body, body.light {
      background:#F9F5F4; color:#1A0A09;
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .glow-orb { display:none; }
    body.light .sidebar-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sidebar-title { color:#1A0A09; }
    body.light .sp-name { color:#1A0A09; }
    body.light .sb-stat { background:#F0E4E2; }
    body.light .sb-stat-num { color:#1A0A09; }
    body.light .sb-nav-item:hover { color:#1A0A09; background:#FEF0EE; }
    body.light .sb-nav-item.active { color:var(--red-mid); }
    body.light .sb-browse-wrap { background:#FFFFFF; border-color:#E0CECA; }
    body.light .page-title { color:#1A0A09; }
    body.light .page-sub { color:#7A5555; }
    body.light .saved-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .saved-card:hover { border-color:#D4B0AB; }
    body.light .card-title { color:#1A0A09; }
    body.light .salary-row { color:#1A0A09; }
    body.light .chip { background:#F0E4E2; border-color:#D0BCBA; }
    body.light .filter-select { background:#FFFFFF; border-color:#D0BCBA; color:#3A2020; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpolygon points='0,0 6,8 12,0' fill='%237A5555'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; background-size:12px 8px; }
    body.light .filter-select option { background:#FFFFFF; color:#3A2020; }
    body.light .empty-state { background:#FFFFFF; border-color:#E0CECA; color:#7A5555; }
    body.light .empty-title { color:#1A0A09; }
    body.light .modal { background:#FFFFFF; border-color:#E0CECA; }
    body.light .modal-title { color:#1A0A09; }
    body.light .modal-close-btn { background:#F0E4E2; }
    body.light .form-input { background:#F5EDEC; border-color:#D0BCBA; color:#1A0A09; }
    body.light .card-company { color:var(--red-mid); }
    body.light .card-icon { background:rgba(184,53,37,0.08); }
    body.light .saved-date { color:#7A5555; }
    body.light .btn-apply:hover { background:var(--red-bright); }
    body.light .btn-view { border-color:#D0BCBA; color:#4A2828; }
    body.light .btn-view:hover { border-color:var(--red-vivid); color:var(--red-mid); }

    /* ── JOB DETAIL MODAL ── */
    .dmo { position:fixed; inset:0; background:rgba(0,0,0,0.78); backdrop-filter:blur(10px); z-index:600; display:flex; align-items:center; justify-content:center; padding:20px; opacity:0; visibility:hidden; transition:opacity 0.2s, visibility 0.2s; }
    .dmo.open { opacity:1; visibility:visible; }
    .dm { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; width:100%; max-width:580px; max-height:90vh; overflow:hidden; display:flex; flex-direction:column; transform:translateY(12px); transition:transform 0.22s; box-shadow:0 40px 80px rgba(0,0,0,0.6); }
    .dmo.open .dm { transform:translateY(0); }
    .dm-scroll { overflow-y:auto; flex:1; padding:28px 28px 20px; position:relative; }
    .dm-close { position:absolute; top:16px; right:16px; width:32px; height:32px; border-radius:8px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); font-size:13px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:0.15s; }
    .dm-close:hover { color:var(--text-light); border-color:var(--red-mid); background:rgba(209,61,44,0.08); }
    .dm-header { display:flex; align-items:center; gap:14px; margin-bottom:18px; padding-right:36px; }
    .dm-icon { width:52px; height:52px; border-radius:12px; flex-shrink:0; background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.18); display:flex; align-items:center; justify-content:center; font-size:22px; color:var(--red-pale); }
    .dm-title { font-family:var(--font-display); font-size:19px; font-weight:700; color:var(--text-light); line-height:1.3; }
    .dm-company { font-size:13px; color:var(--red-pale); font-weight:600; margin-top:3px; }
    .dm-badges { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:18px; }
    .dm-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; padding:5px 11px; border-radius:6px; background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-mid); }
    .dm-badge i { font-size:10px; color:var(--red-pale); }
    .dm-badge.salary { background:rgba(76,175,80,0.08); border-color:rgba(76,175,80,0.2); color:#6FCF77; }
    .dm-badge.salary i { color:#6FCF77; }
    .dm-desc { font-size:13px; color:var(--text-mid); line-height:1.75; margin-bottom:18px; white-space:pre-line; }
    .dm-skills { display:flex; flex-wrap:wrap; gap:6px; }
    .dm-footer { display:flex; gap:10px; padding:16px 28px; border-top:1px solid var(--soil-line); background:var(--soil-card); flex-shrink:0; }
    .dm-unsave { width:44px; height:44px; border-radius:9px; border:1px solid var(--soil-line); background:var(--soil-hover); color:var(--red-pale); font-size:16px; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.18s; flex-shrink:0; }
    .dm-unsave:hover { border-color:#E05050; color:#E05050; background:rgba(224,80,80,0.08); }
    .dm-apply { flex:1; padding:12px 20px; border-radius:9px; background:var(--red-vivid); border:none; color:#fff; font-size:14px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:background 0.18s, transform 0.14s; display:flex; align-items:center; justify-content:center; gap:7px; }
    .dm-apply:hover:not(:disabled) { background:var(--red-bright); transform:translateY(-1px); }
    .dm-apply.applied { background:var(--soil-hover); border:1px solid var(--soil-line); color:var(--text-muted); cursor:default; }
    /* detail modal light theme */
    body.light .dm { background:#FFFFFF; border-color:#E0CECA; }
    body.light .dm-footer { background:#FFFFFF; border-color:#E0CECA; }
    body.light .dm-title { color:#1A0A09; }
    body.light .dm-badge { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }
    body.light .dm-desc { color:#4A2828; }
    body.light .dm-close { background:#F0E4E2; border-color:#E0CECA; }
    body.light .dm-unsave { background:#F5EEEC; border-color:#E0CECA; }
    body.light .dm-apply.applied { background:#F5EEEC; border-color:#E0CECA; color:#7A5555; }

    .anim { opacity:0; transform:translateY(14px); animation:fadeUp 0.42s cubic-bezier(0.4,0,0.2,1) forwards; }
    .anim-d1 { animation-delay:0.05s; } .anim-d2 { animation-delay:0.12s; }
    @keyframes fadeUp { to { opacity:1; transform:none; } }
    @media(max-width:760px) {
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.main-content{max-width:100%;overflow-x:hidden}
      .main-wrap{padding:14px 14px 40px}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .job-row{grid-template-columns:1fr;gap:10px}
      .jr-icon{display:none}
      .jr-chips{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:6px;scrollbar-width:none;padding-bottom:4px}
      .jr-chips::-webkit-scrollbar{display:none}
      .jr-chips .chip{flex-shrink:0}
      .job-row-right{flex-direction:row;align-items:center;justify-content:space-between}
      .job-description-preview,.card-description{display:none}
      .jobs-grid{grid-template-columns:1fr}
      .filter-bar{flex-direction:column;align-items:stretch;gap:8px}
      .filter-select{width:100%}
      .filter-label{display:none}
      .clear-btn{margin-left:0;width:100%;text-align:center}
      .page-header{flex-direction:column;align-items:flex-start;gap:6px}
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

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<div class="main-wrap">
  <div class="content-layout">
    <div class="anim anim-d2">
      <div class="page-header">
        <div>
          <div class="page-title">Saved Jobs</div>
          <div class="page-sub" id="savedSubtitle">3 jobs saved — apply when you're ready.</div>
        </div>
      </div>

      <div class="filter-bar">
        <span class="filter-label">Sort by:</span>
        <select class="filter-select" id="sortSelect" onchange="window.renderSaved()">
          <option value="newest">Date Saved (Newest)</option>
          <option value="oldest">Date Saved (Oldest)</option>
          <option value="salary">Highest Salary</option>
        </select>
        <select class="filter-select" id="typeFilter" onchange="window.renderSaved()">
          <option value="">All Types</option>
          <option value="Full-time">Full-time</option>
          <option value="Part-time">Part-time</option>
          <option value="Internship">Internship</option>
          <option value="Contract">Contract</option>
        </select>
        <button class="clear-btn" onclick="window.clearAllSaved()"><i class="fas fa-trash"></i> Clear All</button>
      </div>

      <div class="jobs-grid" id="savedGrid"></div>
    </div>
  </div>
</div>

<!-- JOB DETAIL MODAL -->
<div class="dmo" id="detailModal">
  <div class="dm">
    <div class="dm-scroll" id="detailBody"></div>
    <div class="dm-footer" id="detailFooter"></div>
  </div>
</div>

<!-- APPLY MODAL -->
<div class="modal-overlay" id="applyModal">
  <div class="modal" id="applyModalInner"></div>
</div>

<script>
  window._savedJobs = <?= $savedJobsJson ?>;
  window._appliedIds = new Set(<?= $appliedIdsJson ?>);
  window._applyingJobId = null;

  window.showToast = function(msg, icon) {
    var toast = document.getElementById("acToastSaved");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "acToastSaved";
      toast.style.cssText = "position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(12px);background:#1C1818;border:1px solid rgba(209,61,44,0.35);color:var(--text-light);padding:11px 20px;border-radius:10px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:9px;z-index:9999;opacity:0;transition:opacity 0.22s,transform 0.22s;pointer-events:none;white-space:nowrap;box-shadow:0 8px 32px rgba(0,0,0,0.5);";
      document.body.appendChild(toast);
    }
    toast.innerHTML = "<i class=\"fas " + icon + "\" style=\"color:var(--red-pale)\"></i> " + msg;
    toast.style.opacity = "1";
    toast.style.transform = "translateX(-50%) translateY(0)";
    clearTimeout(toast._t);
    toast._t = setTimeout(function() {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(-50%) translateY(12px)";
    }, 2600);
  };

  window.renderSaved = function() {
    var grid = document.getElementById("savedGrid");
    var sort  = document.getElementById("sortSelect").value;
    var typeF = document.getElementById("typeFilter").value;
    var jobs  = window._savedJobs.slice();
    if (typeF) jobs = jobs.filter(function(j){ return j.type === typeF; });
    if (sort === "salary")  jobs.sort(function(a,b){ return b.salaryMin - a.salaryMin; });
    else if (sort === "oldest") jobs.reverse();

    var total = window._savedJobs.length;
    var sc = document.getElementById("savedCount");
    if (sc) sc.textContent = total;
    document.getElementById("savedSubtitle").textContent  = total === 0
      ? "No saved jobs yet."
      : total + " job" + (total !== 1 ? "s" : "") + " saved \u2014 apply when you\u2019re ready.";

    if (!jobs.length) {
      grid.innerHTML =
        "<div class=\"empty-state\" style=\"grid-column:1/-1;\">" +
          "<div class=\"empty-icon\"><i class=\"fas fa-heart\"></i></div>" +
          "<div class=\"empty-title\">" + (typeF ? "No " + typeF + " jobs saved" : "No saved jobs yet") + "</div>" +
          "<div class=\"empty-sub\">Browse jobs and click the heart icon to save them here.</div>" +
          "<button class=\"btn-primary\" onclick=\"window.location.href=\'antcareers_seekerJobs.php\'\"><i class=\"fas fa-search\"></i> Browse Jobs</button>" +
        "</div>";
      return;
    }

    grid.innerHTML = jobs.map(function(j) {
      var featuredChip = j.featured
        ? "<span class=\"chip featured\"><i class=\"fas fa-star\"></i>Featured</span>"
        : "";
      var tagChips = j.tags.map(function(t){ return "<span class=\"chip\">" + t + "</span>"; }).join("");
      var safeTitle = j.title.replace(/"/g, "&quot;");
      return (
        "<div class=\"saved-card\" id=\"saved-" + j.id + "\">" +
          "<button class=\"unsave-btn\" title=\"Remove from saved\" onclick=\"window.unsave(" + j.id + ")\"><i class=\"fas fa-heart\"></i></button>" +
          "<div class=\"card-head\">" +
            "<div class=\"card-icon\"><i class=\"fas " + j.icon + "\"></i></div>" +
            "<div class=\"card-info\">" +
              "<div class=\"card-title\">" + j.title + "</div>" +
              "<a class=\"card-company\" href=\"public_company_profile.php?employer_id=" + j.employerId + "\" onclick=\"event.stopPropagation()\" style=\"text-decoration:none;\">" + j.company + "</a>" +
            "</div>" +
          "</div>" +
          "<div class=\"chips\">" + featuredChip +
            "<span class=\"chip\"><i class=\"fas fa-map-marker-alt\"></i>" + j.location + "</span>" +
            "<span class=\"chip\"><i class=\"fas fa-briefcase\"></i>" + j.type + "</span>" +
          "</div>" +
          "<div class=\"salary-row\">" + j.salary + " <small>/ year</small></div>" +
          "<div class=\"chips\">" + tagChips + "</div>" +
          "<div class=\"saved-date\"><i class=\"fas fa-bookmark\" style=\"color:var(--red-pale);margin-right:4px;\"></i>Saved " + j.savedDate + "</div>" +
          "<div class=\"card-actions\">" +
            (window._appliedIds.has(j.id)
              ? "<button class=\"btn-apply\" disabled style=\"opacity:0.6;cursor:default;\"><i class=\"fas fa-check\"></i> Applied</button>"
              : "<button class=\"btn-apply\" onclick=\"window.openApply(" + j.id + ")\"><i class=\"fas fa-paper-plane\"></i> Apply Now</button>") +
            "<button class=\"btn-view\" onclick=\"window.openJobDetail(" + j.id + ")\"><i class=\"fas fa-eye\"></i> View</button>" +
          "</div>" +
        "</div>"
      );
    }).join("");
  };

  window.unsave = function(id) {
    fetch('save_job.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
      body:'job_id=' + id
    }).then(function(r){ return r.json(); }).then(function(d){
      if (d.ok || d.action === 'removed') {
        window._savedJobs = window._savedJobs.filter(function(j){ return j.id !== id; });
        window.renderSaved();
        window.showToast("Removed from saved jobs", "fa-heart-broken");
      }
    }).catch(function(){
      window._savedJobs = window._savedJobs.filter(function(j){ return j.id !== id; });
      window.renderSaved();
      window.showToast("Removed from saved jobs", "fa-heart-broken");
    });
  };

  window.clearAllSaved = function() {
    if (!window._savedJobs.length) return;
    if (!confirm("Remove all " + window._savedJobs.length + " saved jobs?")) return;
    var ids = window._savedJobs.map(function(j){ return j.id; });
    var done = 0;
    ids.forEach(function(id){
      fetch('save_job.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body:'job_id=' + id
      }).finally(function(){
        done++;
        if (done >= ids.length) {
          window._savedJobs = [];
          window.renderSaved();
          window.showToast("All saved jobs cleared", "fa-trash");
        }
      });
    });
  };

  window.openApply = function(id) {
    window._applyingJobId = id;
    var j = window._savedJobs.find(function(x){ return x.id === id; });
    if (!j) return;
    var initials = j.company.split(' ').map(function(w){ return w[0] || ''; }).join('').slice(0,2).toUpperCase();
    function chip(icon, text, accent) {
      if (!text) return '';
      return '<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;' +
        'background:' + (accent ? 'rgba(209,61,44,0.08)' : 'var(--soil-hover)') + ';' +
        'border:1px solid ' + (accent ? 'rgba(209,61,44,0.22)' : 'var(--soil-line)') + ';' +
        'color:' + (accent ? 'var(--red-pale)' : 'var(--text-muted)') + ';white-space:nowrap;">' +
        '<i class="fas ' + icon + '" style="font-size:10px;color:var(--red-bright);"></i>' + esc(text) + '</span>';
    }
    var chips = [chip('fa-map-marker-alt', j.location, false), chip('fa-laptop-house', j.setup || j.workSetup, false), chip('fa-briefcase', j.type || j.jobType, false), chip('fa-money-bill-wave', j.salary, true)].filter(Boolean).join('');
    document.getElementById('applyModalInner').innerHTML =
      '<div style="height:3px;background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));border-radius:14px 14px 0 0;margin:-28px -28px 24px;"></div>' +
      '<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">' +
        '<div style="display:flex;align-items:center;gap:12px;min-width:0;">' +
          '<div style="width:46px;height:46px;border-radius:12px;background:rgba(209,61,44,0.12);border:1px solid rgba(209,61,44,0.22);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--red-pale);flex-shrink:0;">' + initials + '</div>' +
          '<div style="min-width:0;">' +
            '<div style="font-family:var(--font-display);font-size:19px;font-weight:700;color:var(--text-light);line-height:1.25;">' + esc(j.title) + '</div>' +
            '<div style="font-size:13px;color:var(--red-pale);font-weight:600;margin-top:2px;">' + esc(j.company) + '</div>' +
          '</div>' +
        '</div>' +
        '<button onclick="window.closeModal()" style="width:30px;height:30px;border-radius:6px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-left:8px;"><i class="fas fa-times"></i></button>' +
      '</div>' +
      (chips ? '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;">' + chips + '</div>' : '') +
      '<div style="margin-bottom:16px;">' +
        '<label style="display:block;font-size:11px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">Cover Letter <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>' +
        '<textarea id="coverLetter" rows="5" placeholder="Tell the employer why you&#39;re a great fit..." style="width:100%;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:10px 12px;color:var(--text-light);font-family:var(--font-body);font-size:13px;resize:vertical;outline:none;transition:border-color 0.18s;box-sizing:border-box;" onfocus="this.style.borderColor=\'rgba(209,61,44,0.5)\'" onblur="this.style.borderColor=\'var(--soil-line)\'"></textarea>' +
      '</div>' +
      '<div style="display:flex;justify-content:flex-end;gap:10px;padding-top:14px;border-top:1px solid var(--soil-line);">' +
        '<button onclick="window.closeModal()" style="padding:9px 20px;border-radius:8px;background:transparent;border:1px solid var(--soil-line);color:var(--text-muted);font-family:var(--font-body);font-size:13px;font-weight:600;cursor:pointer;transition:border-color 0.18s,color 0.18s;" onmouseover="this.style.borderColor=\'rgba(209,61,44,0.5)\';this.style.color=\'var(--red-pale)\'" onmouseout="this.style.borderColor=\'var(--soil-line)\';this.style.color=\'var(--text-muted)\'">Cancel</button>' +
        '<button id="savedApplySubmit" onclick="window.submitApplication()" style="padding:9px 22px;border-radius:8px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:7px;transition:background 0.18s,transform 0.14s;" onmouseover="this.style.background=\'var(--red-bright)\';this.style.transform=\'translateY(-1px)\'" onmouseout="this.style.background=\'var(--red-vivid)\';this.style.transform=\'none\'"><i class="fas fa-paper-plane"></i> Submit Application</button>' +
      '</div>';
    document.getElementById('applyModal').classList.add('open');
  };

  window.closeModal = function() {
    document.getElementById("applyModal").classList.remove("open");
    window._applyingJobId = null;
  };

  window.submitApplication = async function() {
    if (!window._applyingJobId) return;
    var applyingJobId = window._applyingJobId;
    var j = window._savedJobs.find(function(x){ return x.id === applyingJobId; });
    var btn = document.getElementById('savedApplySubmit');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…'; }
    var cover = document.getElementById('coverLetter')?.value ?? '';
    var data;
    try {
      var fd = new FormData();
      fd.append('job_id', String(applyingJobId));
      fd.append('cover_letter', cover);
      fd.append('csrf_token', '<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>');
      var res = await fetch('apply_job.php', { method:'POST', body:fd });
      data = await res.json();
    } catch {
      window.showToast('Network error — try again', 'fa-exclamation-circle');
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application'; }
      return;
    }
    window.closeModal();
    if (data.success) {
      window._appliedIds.add(applyingJobId);
      window.renderSaved();
      window.showToast('Application sent to ' + (j ? j.company : 'employer') + '! 🎉', 'fa-check');
    } else {
      window.showToast(data.message || 'Could not apply', 'fa-exclamation-circle');
    }
  };

  // Close apply modal on overlay click
  document.getElementById("applyModal").addEventListener("click", function(e) {
    if (e.target === document.getElementById("applyModal")) window.closeModal();
  });

  // ── JOB DETAIL MODAL ──
  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  window.openJobDetail = function(id) {
    var j = window._savedJobs.find(function(x){ return x.id === id; });
    if (!j) return;
    var applied = window._appliedIds.has(id);
    var tags = j.tags.map(function(t){ return '<span class="chip">' + esc(t) + '</span>'; }).join('');
    document.getElementById('detailBody').innerHTML =
      '<button class="dm-close" onclick="window.closeDetailModal()"><i class="fas fa-times"></i></button>' +
      '<div class="dm-header">' +
        '<div class="dm-icon"><i class="fas ' + esc(j.icon) + '"></i></div>' +
        '<div style="min-width:0;">' +
          '<div class="dm-title">' + esc(j.title) + '</div>' +
          '<a class="dm-company" href="public_company_profile.php?employer_id=' + j.employerId + '" onclick="event.stopPropagation()" style="color:var(--red-pale);text-decoration:none;">' + esc(j.company) + '</a>' +
        '</div>' +
      '</div>' +
      '<div class="dm-badges">' +
        '<span class="dm-badge"><i class="fas fa-map-marker-alt"></i>' + esc(j.location) + '</span>' +
        '<span class="dm-badge"><i class="fas fa-briefcase"></i>' + esc(j.type) + '</span>' +
        (j.workSetup ? '<span class="dm-badge"><i class="fas fa-laptop-house"></i>' + esc(j.workSetup) + '</span>' : '') +
        (j.experience ? '<span class="dm-badge"><i class="fas fa-layer-group"></i>' + esc(j.experience) + '</span>' : '') +
        '<span class="dm-badge salary"><i class="fas fa-money-bill-wave"></i>' + esc(j.salary) + '</span>' +
      '</div>' +
      (j.description ? '<div class="dm-desc">' + esc(j.description) + '</div>' : '') +
      (tags ? '<div class="dm-skills">' + tags + '</div>' : '');
    document.getElementById('detailFooter').innerHTML =
      '<button class="dm-unsave" title="Remove from saved" onclick="window.unsave(' + id + '); window.closeDetailModal();">' +
        '<i class="fas fa-heart"></i>' +
      '</button>' +
      '<button class="dm-apply ' + (applied ? 'applied' : '') + '" ' + (applied ? 'disabled' : '') + ' ' +
        'onclick="' + (applied ? '' : 'window.closeDetailModal(); window.openApply(' + id + ');') + '">' +
        (applied ? '<i class="fas fa-check"></i> Already Applied' : '<i class="fas fa-paper-plane"></i> Apply Now') +
      '</button>';
    document.getElementById('detailModal').classList.add('open');
  };

  window.closeDetailModal = function() {
    document.getElementById('detailModal').classList.remove('open');
  };

  document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === document.getElementById('detailModal')) window.closeDetailModal();
  });

  // Init
  window.renderSaved();
</script>
</body>
</html>