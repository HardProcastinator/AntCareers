<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/countries.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'browse';

$db = getDB();

/* ── Fetch all active jobs from DB ──────────────────────────────────────── */
$jobs      = [];
$companies = [];
$dbJobs    = [];

try {
    // Full query — works after migration (new columns exist)
    $jStmt = $db->prepare("
        SELECT
            j.id, j.title, j.location, j.job_type, j.setup AS work_setup,
            j.experience_level, j.industry, j.salary_min, j.salary_max,
            j.salary_currency, j.description, j.skills_required, j.requirements, j.created_at, j.deadline,
            COALESCE(cp.company_name, u.company_name, u.full_name, 'Unknown Company') AS company,
            cp.logo_path AS logo_url
        FROM jobs j
        JOIN users u ON u.id = j.employer_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'Active'
          AND j.approval_status = 'approved'
          AND (j.deadline IS NULL OR j.deadline >= CURDATE())
        ORDER BY j.created_at DESC
        LIMIT 100
    ");
    $jStmt->execute();
    $dbJobs = $jStmt->fetchAll();
} catch (PDOException $e) {
    // Fallback — works with original 11-column jobs table (pre-migration)
    error_log('[AntCareers] employer browse jobs error (trying fallback): ' . $e->getMessage());
    try {
        $jStmt = $db->prepare("
            SELECT
                j.id, j.title, j.location, j.job_type, 'On-site' AS work_setup,
                NULL AS experience_level, NULL AS industry, j.salary_min, j.salary_max,
                'PHP' AS salary_currency, j.description, NULL AS skills_required,
                NULL AS requirements, j.created_at,
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
        error_log('[AntCareers] employer browse jobs fallback error: ' . $e2->getMessage());
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
        'requirements'=> $r['requirements'] ?? '',
        'featured'    => false,
        'tags'        => array_values(array_slice($tags, 0, 5)),
        'postedDate'  => date('M j, Y', strtotime($r['created_at'])),
        'createdRaw'  => $r['created_at'],
        'deadlineRaw' => $r['deadline'] ?? null,
    ];
}

// Companies data removed from authenticated browse view (kept only on public index)

$countrySidebarOptionsHtml = '<option value="">All countries</option>';
foreach (getCountries() as $country) {
  $name = (string)$country['name'];
  $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
  $countrySidebarOptionsHtml .= '<option value="' . $escName . '">' . $escName . '</option>';
}
$countrySidebarOptionsHtml .= '<option value="Remote">Remote</option>';

$jobsJson      = json_encode($jobs, JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Browse Jobs</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--amber-dim:#251C0E;--green:#4CAF70;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased;}

    .tunnel-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
    .tunnel-bg svg{width:100%;height:100%;opacity:0.05;}
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}

    /* ── NAVBAR ── */
    .navbar{position:sticky;top:0;z-index:400;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid rgba(209,61,44,0.35);box-shadow:0 1px 0 rgba(209,61,44,0.06),0 4px 24px rgba(0,0,0,0.5);}
    .nav-inner{max-width:1380px;margin:0 auto;padding:0 24px;display:flex;align-items:center;height:64px;gap:0;min-width:0;}
    .logo{display:flex;align-items:center;gap:8px;text-decoration:none;margin-right:28px;flex-shrink:0;}
    .logo-icon{width:34px;height:34px;background:var(--red-vivid);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;box-shadow:0 0 18px rgba(209,61,44,0.35);}
    .logo-icon::before{content:'🐜';font-size:18px;filter:brightness(0) invert(1);}
    .logo-text{font-family:var(--font-display);font-weight:700;font-size:19px;color:#F5F0EE;white-space:nowrap;}
    .logo-text span{color:var(--red-bright);}
    .nav-links{display:flex;align-items:center;gap:2px;flex:1;min-width:0;}
    .nav-link{font-size:13px;font-weight:600;color:#A09090;text-decoration:none;padding:7px 11px;border-radius:6px;transition:all 0.2s;cursor:pointer;background:none;border:none;font-family:var(--font-body);display:flex;align-items:center;gap:5px;white-space:nowrap;letter-spacing:0.01em;}
    .nav-link:hover,.nav-link.active{color:#F5F0EE;background:var(--soil-hover);}
    .nav-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0;}
    .theme-btn{width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:13px;flex-shrink:0;}
    .theme-btn:hover{color:var(--red-bright);border-color:var(--red-vivid);}
    .notif-btn-nav{position:relative;width:36px;height:36px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:14px;color:var(--text-muted);flex-shrink:0;}
    .notif-btn-nav:hover{color:var(--red-pale);border-color:var(--red-vivid);}
    .notif-btn-nav .badge{position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;border-radius:50%;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--soil-dark);overflow:hidden;padding:0 3px;}
    .btn-nav-red{padding:7px 16px;border-radius:7px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:0.2s;white-space:nowrap;letter-spacing:0.02em;box-shadow:0 2px 8px rgba(209,61,44,0.3);text-decoration:none;display:flex;align-items:center;gap:7px;}
    .btn-nav-red:hover{background:var(--red-bright);transform:translateY(-1px);box-shadow:0 4px 14px rgba(209,61,44,0.45);}
    .profile-wrap{position:relative;}
    .profile-btn{display:flex;align-items:center;gap:9px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:6px 12px 6px 8px;cursor:pointer;transition:0.2s;flex-shrink:0;}
    .profile-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#8a5010);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .profile-avatar img{width:100%;height:100%;object-fit:cover;}
    .profile-name{font-size:13px;font-weight:600;color:#F5F0EE;}
    .profile-role{font-size:10px;color:var(--amber);margin-top:1px;letter-spacing:0.02em;font-weight:600;}
    .profile-chevron{font-size:9px;color:var(--text-muted);margin-left:2px;}
    .profile-dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:6px;min-width:200px;opacity:0;visibility:hidden;transform:translateY(-6px);transition:all 0.18s ease;z-index:300;box-shadow:0 20px 40px rgba(0,0,0,0.5);}
    .profile-dropdown.open{opacity:1;visibility:visible;transform:translateY(0);}
    .profile-dropdown-head{padding:12px 14px 10px;border-bottom:1px solid var(--soil-line);margin-bottom:4px;}
    .pdh-name{font-size:14px;font-weight:700;color:#F5F0EE;}
    .pdh-sub{font-size:11px;color:var(--amber);margin-top:2px;font-weight:600;}
    .pd-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:6px;font-size:13px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:0.15s;font-family:var(--font-body);text-decoration:none;}
    .pd-item i{color:var(--text-muted);width:16px;text-align:center;font-size:12px;}
    .pd-item:hover{background:var(--soil-hover);color:#F5F0EE;}
    .pd-item:hover i{color:var(--red-bright);}
    .pd-divider{height:1px;background:var(--soil-line);margin:4px 6px;}
    .pd-item.danger{color:#E05555;}
    .pd-item.danger i{color:#E05555;}
    .pd-item.danger:hover{background:rgba(224,85,85,0.1);color:#FF7070;}
    .hamburger{display:none;width:34px;height:34px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);align-items:center;justify-content:center;cursor:pointer;font-size:14px;flex-shrink:0;margin-left:8px;}
    .mobile-menu{display:none;position:fixed;top:64px;left:0;right:0;background:rgba(10,9,9,0.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--soil-line);padding:12px 20px 16px;z-index:190;flex-direction:column;gap:2px;}
    .mobile-menu.open{display:flex;}
    .mobile-link{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:7px;font-size:14px;font-weight:500;color:var(--text-mid);cursor:pointer;transition:0.15s;font-family:var(--font-body);text-decoration:none;}
    .mobile-link i{color:var(--red-mid);width:16px;text-align:center;}
    .mobile-link:hover{background:var(--soil-hover);color:#F5F0EE;}
    .mobile-divider{height:1px;background:var(--soil-line);margin:6px 0;}
    @media(max-width:880px){.nav-links{display:none;}.hamburger{display:flex;}}

    /* ── PAGE SHELL ── */
    .page-shell{max-width:1380px;margin:0 auto;padding:0 24px 80px;position:relative;z-index:1;}

    /* ── SEARCH HEADER ── */
    .search-header{padding:32px 0 24px;}
    .search-greeting{font-family:var(--font-display);font-size:28px;font-weight:700;color:#F5F0EE;margin-bottom:6px;}
    .search-greeting span{color:var(--red-bright);font-style:italic;}
    .search-sub{font-size:14px;color:var(--text-muted);margin-bottom:20px;}
    .search-row{display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;}
    .search-box{flex:1;min-width:240px;display:flex;align-items:center;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;overflow:hidden;transition:0.25s;}
    .search-box:focus-within{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,0.12);}
    .search-box .si{padding:0 14px;color:var(--text-muted);font-size:14px;flex-shrink:0;}
    .search-box input{flex:1;padding:13px 0;background:none;border:none;outline:none;font-family:var(--font-body);font-size:14px;color:#F5F0EE;}
    .search-box input::placeholder{color:var(--text-muted);}
    .search-row .filter-select{width:auto;flex-shrink:0;padding:13px 16px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;font-family:var(--font-body);font-size:13px;color:var(--text-mid);cursor:pointer;outline:none;transition:0.2s;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:10px 6px;padding-right:32px;}
    .search-row .filter-select:focus{border-color:var(--red-vivid);}
    .search-btn{padding:13px 24px;border-radius:10px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:0.2s;white-space:nowrap;display:flex;align-items:center;gap:7px;}
    .search-btn:hover{background:var(--red-bright);transform:translateY(-1px);}

    /* Multi-select */
    .ms-wrap{position:relative;}
    .ms-trigger{width:100%;display:flex;align-items:center;justify-content:space-between;background-color:var(--soil-hover);border:1px solid var(--soil-line);border-radius:7px;padding:9px 12px;font-family:var(--font-body);font-size:13px;color:var(--text-mid);cursor:pointer;transition:border-color 0.2s,box-shadow 0.2s,background-color 0.2s;}
    .ms-trigger:hover{border-color:var(--red-mid);}
    .ms-wrap.open .ms-trigger{border-color:var(--red-vivid);box-shadow:0 0 0 2px rgba(209,61,44,0.14);}
    .ms-trigger .ms-arrow{font-size:8px;color:var(--text-muted);transition:transform 0.2s;flex-shrink:0;}
    .ms-wrap.open .ms-trigger .ms-arrow{transform:rotate(180deg);}
    .ms-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .ms-panel{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:7px;max-height:200px;overflow-y:auto;z-index:1050;box-shadow:0 8px 24px rgba(0,0,0,0.4);}
    .ms-wrap.open .ms-panel{display:block;}
    .role-section{display:block;margin-top:8px;}
    .role-section-label{font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin:10px 0 6px;display:block;}
    .ms-item{display:flex;align-items:center;gap:8px;padding:7px 12px;font-size:13px;color:var(--text-mid);cursor:pointer;transition:background-color 0.12s;user-select:none;}
    .ms-item:hover{background:var(--soil-hover);}
    .ms-item input[type="checkbox"]{width:14px;height:14px;accent-color:var(--red-vivid);cursor:pointer;flex-shrink:0;}
    .search-row .ms-wrap{flex-shrink:0;min-width:170px;}
    .search-row .ms-trigger{padding:13px 16px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;font-size:13px;color:var(--text-mid);}
    .search-row .ms-trigger:hover{border-color:var(--red-mid);background-color:var(--soil-hover);}
    .search-row .ms-wrap.open .ms-trigger{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,0.12);}
    .search-row .ms-panel{min-width:260px;}

    /* ── CONTENT LAYOUT ── */
    .content-layout{display:grid;grid-template-columns:240px 1fr;gap:24px;}

    /* ── SIDEBAR ── */
    .filter-sidebar{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:18px;position:sticky;top:80px;}
    .fs-title{font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:7px;}
    .fs-title i{color:var(--red-bright);}
    .fs-section-label{font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;}
    .fs-divider{height:1px;background:var(--soil-line);margin:16px 0;}
    .fs-reset{width:100%;padding:9px;border-radius:7px;background:transparent;border:1px solid var(--soil-line);color:var(--text-muted);font-family:var(--font-body);font-size:12px;font-weight:600;cursor:pointer;transition:0.18s;}
    .fs-reset:hover{border-color:var(--red-vivid);color:var(--red-pale);}
    .fs-text-input{width:100%;padding:9px 12px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:7px;font-family:var(--font-body);font-size:12px;color:var(--text-mid);outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
    .fs-text-input::placeholder{color:var(--text-muted);}
    .fs-text-input:focus{border-color:var(--red-vivid);box-shadow:0 0 0 2px rgba(209,61,44,0.14);}
    .fs-select{width:100%;padding:9px 12px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:7px;font-family:var(--font-body);font-size:12px;color:var(--text-mid);outline:none;transition:border-color 0.2s,box-shadow 0.2s;-webkit-appearance:none;appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23927C7A' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;background-size:10px 6px;padding-right:28px;}
    .fs-select:hover{border-color:var(--red-mid);}
    .fs-select:focus{border-color:var(--red-vivid);box-shadow:0 0 0 2px rgba(209,61,44,0.14);}
    .fs-select option{background:var(--soil-card);color:var(--text-mid);}

    /* ── SECTIONS ── */
    .sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
    .sec-title{font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:10px;letter-spacing:0.01em;}
    .sec-title i{color:var(--red-bright);font-size:16px;}
    .sec-count{font-size:11px;font-weight:600;color:var(--text-muted);background:var(--soil-hover);padding:2px 9px;border-radius:4px;letter-spacing:0.04em;}

    /* Featured horizontal scroll */
    .featured-scroll{display:flex;gap:14px;overflow-x:auto;padding:8px 6px 24px 6px;margin:-8px -6px 32px -6px;scrollbar-width:none;}
    .featured-scroll::-webkit-scrollbar{display:none;}
    .featured-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:22px;min-width:258px;max-width:258px;cursor:pointer;transition:all 0.25s;position:relative;overflow:hidden;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
    .featured-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));}
    .featured-card:hover{border-color:rgba(209,61,44,0.55);transform:translateY(-4px);box-shadow:0 20px 48px rgba(0,0,0,0.45);}
    .fc-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--amber);background:var(--amber-dim);border:1px solid rgba(212,148,58,0.22);padding:2px 7px;border-radius:3px;margin-bottom:14px;}
    .fc-icon{width:40px;height:40px;border-radius:10px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:14px;color:var(--red-bright);}
    .fc-title{font-family:var(--font-display);font-size:15px;font-weight:700;color:#F5F0EE;margin-bottom:4px;line-height:1.3;}
    .fc-company{font-size:12px;color:var(--red-pale);font-weight:600;margin-bottom:14px;}
    .fc-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;}
    .chip{font-size:11px;font-weight:500;padding:3px 8px;border-radius:4px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);letter-spacing:0.02em;}
    .fc-footer{display:flex;align-items:center;justify-content:space-between;padding-top:14px;border-top:1px solid var(--soil-line);}
    .fc-salary{font-family:var(--font-body);font-size:14px;font-weight:700;color:#F5F0EE;letter-spacing:-0.01em;}
    .fc-action{padding:5px 12px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.2s;}
    .fc-action:hover{background:var(--red-bright);}

    /* Companies */
    .companies-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:40px;}
    .company-pill{display:flex;align-items:center;gap:10px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:8px;padding:10px 14px;transition:0.2s;flex:1;min-width:120px;}
    .cp-icon{width:32px;height:32px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;color:var(--red-vivid);font-size:13px;flex-shrink:0;}
    .cp-name{font-size:13px;font-weight:600;color:#F5F0EE;}
    .cp-roles{font-size:11px;color:var(--text-muted);margin-top:1px;}

    /* Job list */
    .job-list{display:flex;flex-direction:column;gap:10px;}
    .job-row{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:22px 24px;cursor:pointer;transition:border-color 0.18s,transform 0.18s,box-shadow 0.18s;display:flex;gap:16px;align-items:flex-start;position:relative;}
    .job-row:hover{border-color:rgba(209,61,44,0.45);transform:translateX(2px);box-shadow:0 4px 16px rgba(0,0,0,0.12);}
    .jr-icon{width:40px;height:40px;border-radius:10px;flex-shrink:0;margin-top:1px;background:rgba(209,61,44,0.1);border:1px solid rgba(209,61,44,0.15);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--red-pale);}
    .jr-left{flex:1;min-width:0;}
    .jr-top{display:flex;align-items:center;gap:8px;margin-bottom:5px;}
    .jr-title{font-family:var(--font-display);font-size:15px;font-weight:700;color:#F5F0EE;}
    .jr-badge-new{font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6ccf8a;background:rgba(76,175,112,0.1);border:1px solid rgba(76,175,112,0.25);padding:2px 7px;border-radius:4px;}
    .jr-badge-expiring{font-size:10px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#D4943A;background:rgba(212,148,58,0.1);border:1px solid rgba(212,148,58,0.25);padding:2px 7px;border-radius:4px;}
    .jr-badge-days{font-size:10px;font-weight:700;letter-spacing:0.06em;color:#927C7A;background:rgba(146,124,122,0.1);border:1px solid rgba(146,124,122,0.2);padding:2px 7px;border-radius:4px;}
    .jr-meta{display:flex;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:#927C7A;margin-bottom:8px;}
    .jr-meta span{display:flex;align-items:center;gap:4px;}
    .jr-meta i{font-size:10px;color:var(--red-bright);}
    .jr-company{color:var(--red-pale);font-weight:600;}
    .jr-chips{display:flex;gap:5px;flex-wrap:wrap;}
    .job-row-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0;min-width:120px;}
    .jr-salary{font-family:var(--font-body);font-size:14px;font-weight:700;color:#F5F0EE;white-space:nowrap;letter-spacing:-0.01em;}
    .jr-actions{display:flex;gap:8px;align-items:center;}
    .jr-view{padding:7px 16px;border-radius:8px;background:var(--red-vivid);border:none;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:background 0.18s, transform 0.14s;letter-spacing:0.02em;white-space:nowrap;}
    .jr-view:hover{background:var(--red-bright);transform:translateY(-1px);}

    /* Modal */
    .modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.82);backdrop-filter:blur(8px);align-items:center;justify-content:center;}
    .modal-overlay.open{display:flex;}
    .modal-box{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:32px;max-width:560px;width:92%;position:relative;animation:modalIn 0.2s ease;box-shadow:0 40px 80px rgba(0,0,0,0.6);max-height:88vh;overflow-y:auto;}
    @keyframes modalIn{from{opacity:0;transform:scale(0.97) translateY(8px)}to{opacity:1;transform:scale(1)}}
    .modal-close{position:absolute;top:18px;right:18px;width:30px;height:30px;border-radius:6px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);font-size:13px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.15s;}
    .modal-close:hover{color:#F5F0EE;border-color:var(--red-mid);}

    /* Footer */
    .footer{border-top:1px solid var(--soil-line);padding:28px 24px;max-width:1380px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;color:var(--text-muted);font-size:12px;position:relative;z-index:2;flex-wrap:wrap;gap:12px;}
    .footer-logo{font-family:var(--font-display);font-weight:700;color:var(--red-pale);font-size:16px;}

    /* Empty state */
    .empty-state{text-align:center;padding:56px 20px;color:var(--text-muted);}
    .empty-state i{font-size:32px;margin-bottom:14px;display:block;color:var(--soil-line);}

    /* Animations */
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}
    .anim-d1{animation-delay:0.05s;}
    .anim-d2{animation-delay:0.1s;}

    ::-webkit-scrollbar{width:5px;}
    ::-webkit-scrollbar-track{background:var(--soil-dark);}
    ::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:3px;}

    /* ── LIGHT THEME ── */
    body.light{--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A;}
    body.light .navbar{background:rgba(255,253,252,0.98);border-bottom-color:#D4B0AB;box-shadow:0 1px 0 rgba(0,0,0,0.06),0 4px 16px rgba(0,0,0,0.08);}
    body.light .logo-text{color:#1A0A09;}
    body.light .logo-text span{color:var(--red-vivid);}
    body.light .nav-link{color:#5A4040;}
    body.light .nav-link:hover,body.light .nav-link.active{color:#1A0A09;background:#FEF0EE;}
    body.light .theme-btn,body.light .notif-btn-nav,body.light .profile-btn{background:#F5EDEB;border-color:#D4B0AB;}
    body.light .profile-name{color:#1A0A09;}
    body.light .pdh-name{color:#1A0A09;}
    body.light .profile-dropdown,body.light .mobile-menu{background:#FFF8F7;border-color:#D4B0AB;}
    body.light .pd-item{color:#4A3030;}
    body.light .pd-item:hover{background:#FEF0EE;}
    body.light .search-greeting{color:#1A0A09;}
    body.light .search-sub{color:#7A5555;}
    body.light .search-box{background:#FFFFFF;border-color:#E0CECA;}
    body.light .search-box input{color:#1A0A09;}
    body.light .search-row .filter-select{background-color:#FFFFFF;border-color:#E0CECA;color:#1A0A09;}
    body.light .search-row .ms-trigger{background:#FFFFFF;border-color:#D4B0AB;color:#1A0A09;}
    body.light .search-row .ms-trigger:hover{background-color:#FEF0EE;}
    body.light .search-row .ms-wrap.open .ms-trigger{border-color:var(--red-vivid);}
    body.light .filter-sidebar{background:#FFFFFF;border-color:#E0CECA;}
    body.light .fs-text-input{background:#F5EEEC;border-color:#E0CECA;color:#1A0A09;}
    body.light .fs-text-input::placeholder{color:#B09090;}
    body.light .fs-select{background:#F5EEEC;border-color:#E0CECA;color:#4A2828;}
    body.light .fs-select:hover{background-color:#FEF0EE;}
    body.light .fs-select option{background:#FFFFFF;color:#4A2828;}
    body.light .ms-trigger{background-color:#F5EEEC;border-color:#E0CECA;color:#4A2828;}
    body.light .ms-trigger:hover{background-color:#FEF0EE;}
    body.light .ms-wrap.open .ms-trigger{border-color:var(--red-vivid);}
    body.light .ms-panel{background:#FFFFFF;border-color:#E0CECA;box-shadow:0 8px 24px rgba(0,0,0,0.1);}
    body.light .ms-item{color:#4A2828;}
    body.light .ms-item:hover{background:#FEF0EE;}
    body.light .sec-title{color:#1A0A09;}
    body.light .featured-card{background:#FFFFFF;border-color:#E0CECA;}
    body.light .featured-card:hover{box-shadow:0 16px 40px rgba(0,0,0,0.1);}
    body.light .fc-title{color:#1A0A09;}
    body.light .fc-salary{color:#1A0A09;}
    body.light .chip{background:#F5EEEC;border-color:#E0CECA;color:#5A3838;}
    body.light .fc-icon{background:#F5EEEC;border-color:#E0CECA;}
    body.light .job-row{background:#FFFFFF;border-color:#E0CECA;}
    body.light .job-row:hover{background:#FEF0EE;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
    body.light .jr-title{color:#1A0A09;}
    body.light .jr-salary{color:#1A0A09;}
    body.light .jr-meta{color:#7A5555;}
    body.light .company-pill{background:#FFFFFF;border-color:#E0CECA;}
    body.light .cp-name{color:#1A0A09;}
    body.light .modal-box{background:#FFFFFF;border-color:#E0CECA;}
    body.light .hamburger{background:#F5EEEC;border-color:#E0CECA;}
    body.light .mobile-link{color:#4A2828;}
    body.light .mobile-link:hover{background:#FEF0EE;color:#1A0A09;}
    body.light .glow-orb{display:none;}

    @media(max-width:1060px){.content-layout{grid-template-columns:1fr} .filter-sidebar{position:static}}
    @media(max-width:760px){
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.content-layout,.main-content{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .nav-links{display:none}
      .hamburger{display:flex}
      .page-shell{padding:0 16px 60px}
      .mobile-filter-toggle{display:flex;align-items:center;gap:8px;background:var(--soil-hover);border:1px solid var(--red-vivid);color:var(--text-light);font-family:var(--font-body);font-size:13px;font-weight:600;padding:9px 16px;border-radius:8px;cursor:pointer;margin-bottom:14px;width:100%;justify-content:center}
      body.light .mobile-filter-toggle{background:#F5EEEC;border-color:var(--red-vivid);color:#1A0A09}
      .filter-sidebar{display:none;margin-bottom:16px}
      .filter-sidebar.mobile-open{display:block}
      .jr-chips{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:6px;scrollbar-width:none;padding-bottom:4px}
      .jr-chips::-webkit-scrollbar{display:none}
      .jr-chips .chip{flex-shrink:0}
      .job-row{flex-direction:column;padding:16px;gap:0}
      .jr-icon{display:none}
      .jr-left{flex:none;width:100%}
      .jr-meta{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding-bottom:2px}
      .jr-meta::-webkit-scrollbar{display:none}
      .job-row-right{flex-direction:row;align-items:center;width:100%;min-width:0;margin-top:10px}
      .jr-salary{flex:1;font-size:13px;white-space:normal}
      .jr-actions{margin-left:auto;flex-shrink:0}
      .job-description-preview,.card-description{display:none}
      .featured-scroll{-webkit-overflow-scrolling:touch}
      .featured-card{min-width:230px;max-width:230px}
      .footer{flex-direction:column;text-align:center;padding:20px 16px}
      .search-box{min-width:100%}
      .search-btn{flex:1;justify-content:center}
    }
    @media(min-width:761px){.mobile-filter-toggle{display:none!important}.filter-sidebar{display:block!important}}
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
<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<!-- PAGE -->
<div class="page-shell">

  <div class="search-header anim">
    <div class="search-greeting">Browse <span>Jobs</span></div>
    <div class="search-sub">Explore all active job postings across the platform — filter, search, and discover opportunities.</div>
  </div>

  <!-- SEARCH ROW -->
  <div class="search-row anim anim-d1">
    <div class="search-box" style="flex:1;">
      <span class="si"><i class="fas fa-search"></i></span>
      <input type="text" id="keywordInput" placeholder="Search by title, company, or skill…">
    </div>
    <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i> Search</button>
  </div>

  <div class="content-layout">

    <!-- SIDEBAR FILTERS -->
    <button class="mobile-filter-toggle anim anim-d1" id="mobileFilterToggle" onclick="document.getElementById('filterSidebar').classList.toggle('mobile-open')">
      <i class="fas fa-sliders-h"></i> Filters
    </button>
    <aside class="filter-sidebar anim anim-d1" id="filterSidebar">
      <div class="fs-title"><i class="fas fa-sliders-h"></i> Filters</div>

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
            <i class="fas fa-list-ul"></i>
            <span>All Jobs</span>
            <span class="sec-count" id="jobCount">0 jobs</span>
          </div>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Employer Dashboard — <?php echo htmlspecialchars($companyName,ENT_QUOTES,'UTF-8');?></div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <span style="cursor:pointer;" onclick="window.location.href='employer_dashboard.php'">← Dashboard</span>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<!-- Job Detail Modal -->
<div class="modal-overlay" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
    <div id="modalBody"></div>
  </div>
</div>

<script>
  /* ── REAL DATA FROM PHP ── */
  const jobsData   = <?= $jobsJson ?>;

  /* ── HELPERS ── */
  function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function jobIcon(industry) {
    const m = { Technology:'fa-laptop-code', Finance:'fa-chart-line', Healthcare:'fa-heartbeat',
                Marketing:'fa-bullhorn', Design:'fa-palette', Education:'fa-graduation-cap',
                Engineering:'fa-cogs', Sales:'fa-handshake','Information & Communication Technology':'fa-laptop-code',
                'Science & Technology':'fa-flask','Banking & Financial Services':'fa-university' };
    return m[industry] || 'fa-briefcase';
  }

  /* ── MULTI-SELECT HELPERS ── */
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

  /* ── FILTERS ── */
  function getFilters() {
    return {
      keyword:        (document.getElementById('keywordInput')?.value || '').toLowerCase().trim(),
      locationKeyword:(document.getElementById('locationKeyword')?.value || '').toLowerCase().trim(),
      salaryMin:      parseFloat(document.getElementById('salaryMinFilter')?.value) || 0,
      salaryMax:      parseFloat(document.getElementById('salaryMaxFilter')?.value) || 0,
      salaryPeriod:   document.getElementById('salaryPeriodFilter')?.value || '',
      industries:     getMsValues('msIndustry'),
      jobRoles:       getMsValues('msJobRole'),
      sidebarLocation: document.getElementById('sidebarLocationFilter')?.value || '',
      jobTypes:       getMsValues('msWorkType'),
      setups:         getMsValues('msRemote'),
      experiences:    getMsValues('msExperience'),
      dateDays:       getMsValues('msListed'),
    };
  }

  function matchesFilters(j, f) {
    if (f.keyword && !`${j.title} ${j.company} ${j.description} ${(j.tags||[]).join(' ')}`.toLowerCase().includes(f.keyword)) return false;
    if (f.jobTypes.length && !f.jobTypes.includes(j.jobType)) return false;
    if (f.sidebarLocation && !j.location.toLowerCase().includes(f.sidebarLocation.toLowerCase())) return false;
    if (f.locationKeyword && !j.location.toLowerCase().includes(f.locationKeyword)) return false;
    if (f.experiences.length && !f.experiences.includes(j.experience)) return false;
    if (f.industries.length && !f.industries.includes(j.industry)) return false;
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
    if (f.salaryPeriod && j.salaryPeriod && j.salaryPeriod !== f.salaryPeriod) return false;
    if (f.salaryMin) {
      if (j.salaryMax && j.salaryMax < f.salaryMin / 1000) return false;
      if (!j.salaryMax && j.salaryMin && j.salaryMin < f.salaryMin / 1000) return false;
    }
    if (f.salaryMax) {
      if (j.salaryMin && j.salaryMin > f.salaryMax / 1000) return false;
    }
    if (f.dateDays.length) {
      const maxDays = Math.max(...f.dateDays.map(d => parseInt(d)));
      const posted = new Date(j.postedDate);
      const cutoff = new Date(); cutoff.setDate(cutoff.getDate() - maxDays);
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

  /* ── RENDER ALL JOBS ── */
  function renderAllJobs() {
    const el = document.getElementById('jobsContainer');
    const countEl = document.getElementById('jobCount');
    if (!el) return;
    const f = getFilters();
    const filtered = jobsData.filter(j => matchesFilters(j, f));
    if (countEl) countEl.textContent = filtered.length + ' job' + (filtered.length !== 1 ? 's' : '');
    if (filtered.length === 0) {
      el.innerHTML = `
        <div class="empty-state">
          <i class="fas fa-search"></i>
          <div style="font-size:15px;font-weight:700;color:var(--text-mid);margin-bottom:6px;">No jobs match your filters</div>
          <div style="font-size:13px;">Try adjusting your search or <button onclick="resetFilters()" style="background:none;border:none;color:var(--red-pale);cursor:pointer;font-weight:700;font-size:13px;">reset filters</button></div>
        </div>`;
      return;
    }
    el.innerHTML = filtered.map((j, i) => {
      const tags = (j.tags||[]).slice(0,4).map(t => `<span class="chip">${esc(t)}</span>`).join('');
      return `
        <div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;" onclick="openJobModal(${j.id})">
          <div class="jr-icon"><i class="fas ${jobIcon(j.industry)}"></i></div>
          <div class="jr-left">
            <div class="jr-top">
              <div class="jr-title">${esc(j.title)}</div>
              ${jobBadge(j)}
            </div>
            <div class="jr-meta">
              <span class="jr-company"><i class="fas fa-building"></i> ${esc(j.company)}</span>
              <span><i class="fas fa-map-marker-alt"></i> ${esc(j.location)}</span>
              <span><i class="fas fa-laptop-house"></i> ${esc(j.workSetup)}</span>
              <span><i class="fas fa-briefcase"></i> ${esc(j.jobType)}</span>
              ${j.experience ? `<span><i class="fas fa-layer-group"></i> ${esc(j.experience)}</span>` : ''}
            </div>
            ${tags ? `<div class="jr-chips">${tags}</div>` : ''}
          </div>
          <div class="job-row-right">
            <div class="jr-salary">${esc(j.salary)}</div>
            <div class="jr-actions">
              <button class="jr-view" onclick="event.stopPropagation();openJobModal(${j.id})">
                <i class="fas fa-eye"></i> View Details
              </button>
            </div>
          </div>
        </div>`;
    }).join('');
  }

  /* ── JOB DETAIL MODAL ── */
  function openJobModal(id) {
    const j = jobsData.find(x => x.id === id);
    if (!j) return;
    const tags = (j.tags||[]).map(t => `<span class="chip">${esc(t)}</span>`).join('');
    document.getElementById('modalBody').innerHTML = `
      <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px;">
        <div style="width:52px;height:52px;border-radius:10px;background:rgba(209,61,44,0.12);display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--red-pale);flex-shrink:0;">
          <i class="fas ${jobIcon(j.industry)}"></i>
        </div>
        <div>
          <div style="font-size:18px;font-weight:700;color:var(--text-light);">${esc(j.title)}</div>
          <div style="font-size:13px;color:var(--red-pale);font-weight:600;margin-top:2px;">${esc(j.company)}</div>
        </div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <span class="chip"><i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>${esc(j.location)}</span>
        <span class="chip"><i class="fas fa-briefcase" style="margin-right:4px;"></i>${esc(j.jobType)}</span>
        <span class="chip"><i class="fas fa-laptop-house" style="margin-right:4px;"></i>${esc(j.workSetup)}</span>
        ${j.experience ? `<span class="chip"><i class="fas fa-layer-group" style="margin-right:4px;"></i>${esc(j.experience)}</span>` : ''}
        <span class="chip" style="color:var(--green);border-color:var(--green);"><i class="fas fa-money-bill-wave" style="margin-right:4px;"></i>${esc(j.salary)}</span>
      </div>
      ${j.description ? `<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Description</div><div style="font-size:13px;color:var(--text-mid);line-height:1.7;">${esc(j.description)}</div></div>` : ''}
      ${j.requirements ? `<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">Requirements</div><div style="font-size:13px;color:var(--text-mid);line-height:1.7;">${esc(j.requirements)}</div></div>` : ''}
      ${tags ? `<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:20px;">${tags}</div>` : ''}
      <div style="font-size:12px;color:var(--text-muted);padding-top:16px;border-top:1px solid var(--soil-line);">
        <i class="fas fa-calendar-alt" style="margin-right:4px;"></i> Posted ${esc(j.postedDate)}
      </div>`;
    document.getElementById('jobModal').classList.add('open');
  }

  document.getElementById('closeModal')?.addEventListener('click', () =>
    document.getElementById('jobModal').classList.remove('open'));
  document.getElementById('jobModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('jobModal'))
      document.getElementById('jobModal').classList.remove('open');
  });

  /* ── RESET FILTERS ── */
  function resetFilters() {
    document.getElementById('keywordInput').value = '';
    updateRolePicker([]);
    const locEl = document.getElementById('locationKeyword'); if (locEl) locEl.value = '';
    const salMinEl = document.getElementById('salaryMinFilter'); if (salMinEl) salMinEl.value = '';
    const salMaxEl = document.getElementById('salaryMaxFilter'); if (salMaxEl) salMaxEl.value = '';
    ['searchCountryFilter','sidebarLocationFilter','salaryPeriodFilter'].forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    ['searchCountryFilter','sidebarLocationFilter'].forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    document.querySelectorAll('.ms-wrap').forEach(wrap => {
      wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
      updateMsLabel(wrap);
    });
    renderAllJobs();
  }

  // Theme, hamburger, profile dropdown are now handled by navbar_employer.php shared script

  /* ── MULTI-SELECT WIRING ── */
  document.querySelectorAll('.ms-wrap').forEach(wrap => {
    const trigger = wrap.querySelector('.ms-trigger');
    trigger.addEventListener('click', e => {
      e.stopPropagation();
      document.querySelectorAll('.ms-wrap.open').forEach(w => { if (w !== wrap) w.classList.remove('open'); });
      wrap.classList.toggle('open');
    });
    wrap.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        updateMsLabel(wrap);
        if (wrap.id === 'msIndustry' || wrap.id === 'msSearchIndustry') updateRolePicker(getMsValues('msIndustry'));
        renderAllJobs();
      });
    });
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.ms-wrap')) document.querySelectorAll('.ms-wrap.open').forEach(w => w.classList.remove('open'));
  });

  /* ── FILTER EVENT LISTENERS ── */
  document.getElementById('searchBtn').addEventListener('click', renderAllJobs);
  document.getElementById('keywordInput').addEventListener('keyup', e => { if (e.key === 'Enter') renderAllJobs(); });
  ['locationKeyword','salaryMinFilter','salaryMaxFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', renderAllJobs);
  });
  ['searchCountryFilter','sidebarLocationFilter','salaryPeriodFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', renderAllJobs);
  });
  document.getElementById('resetFiltersBtn')?.addEventListener('click', resetFilters);

  /* ── INIT ── */
  renderAllJobs();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>
