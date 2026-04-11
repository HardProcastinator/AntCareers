<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('seeker');
$user = getUser();
$navActive = 'dashboard';

// ── Profile completion score ──────────────────────────────────────────────
$uid = (int)$user['id'];
try {
    $db = getDB();

    $sp = $db->prepare("
        SELECT headline, bio, phone,
               linkedin_url, github_url, portfolio_url
        FROM seeker_profiles WHERE user_id = :uid
    ");
    $sp->execute([':uid' => $uid]);
    $spRow = $sp->fetch(PDO::FETCH_ASSOC) ?: [];

    $skillCount = (int)$db->query("SELECT COUNT(*) FROM seeker_skills     WHERE user_id = $uid")->fetchColumn();
    $expCount   = (int)$db->query("SELECT COUNT(*) FROM seeker_experience WHERE user_id = $uid")->fetchColumn();
    $eduCount   = (int)$db->query("SELECT COUNT(*) FROM seeker_education  WHERE user_id = $uid")->fetchColumn();

    $completionScore = 35;
    if (!empty($spRow['headline']))                                               $completionScore += 10;
    if (!empty($spRow['bio']))                                                    $completionScore += 10;
    if ($skillCount > 0)                                                          $completionScore += 10;
    if ($expCount   > 0)                                                          $completionScore += 10;
    if ($eduCount   > 0)                                                          $completionScore += 10;
    if (!empty($spRow['phone']))                                                  $completionScore += 5;
    if (!empty($spRow['linkedin_url']) || !empty($spRow['github_url']) || !empty($spRow['portfolio_url'])) $completionScore += 10;
    $completionScore = min($completionScore, 100);
} catch (\Throwable $e) {
    $completionScore = 35;
}

// ── Dashboard data: recent applications, interviews, recommended jobs ──
$applicationsData = [];
$interviewsData   = [];
$jobsData         = [];
try {
    if (!isset($db)) $db = getDB();

    // Recent applications (last 10)
    $stmtApps = $db->prepare("
        SELECT a.id, j.title, cp.company_name AS company,
               DATE_FORMAT(a.applied_at, '%b %d') AS date,
               a.status
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE a.seeker_id = :uid
        ORDER BY a.applied_at DESC
        LIMIT 10
    ");
    $stmtApps->execute([':uid' => $uid]);
    $appRows = $stmtApps->fetchAll(PDO::FETCH_ASSOC);
    $statusMap = [
        'pending'    => ['Submitted',           'blue'],
        'reviewed'   => ['Under Review',        'amber'],
        'shortlisted'=> ['Shortlisted',         'green'],
        'interview'  => ['Interview Scheduled',  'purple'],
        'hired'      => ['Hired',                'green'],
        'rejected'   => ['Rejected',             'red'],
    ];
    foreach ($appRows as $r) {
        $s = $statusMap[$r['status']] ?? ['Submitted', 'blue'];
        $applicationsData[] = [
            'id'          => (int)$r['id'],
            'title'       => $r['title'],
            'company'     => $r['company'] ?? 'Company',
            'date'        => $r['date'],
            'status'      => $s[0],
            'statusClass' => $s[1],
        ];
    }

    // Upcoming interviews — auto-migrate new columns if missing
    try { $db->query("SELECT venue_name FROM interview_schedules LIMIT 0"); }
    catch (PDOException $e) {
        $db->exec("ALTER TABLE interview_schedules ADD COLUMN venue_name VARCHAR(300) DEFAULT NULL, ADD COLUMN full_address VARCHAR(500) DEFAULT NULL, ADD COLUMN map_link VARCHAR(500) DEFAULT NULL, ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL, ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL");
    }
    $stmtInt = $db->prepare("
        SELECT isc.scheduled_at, isc.interview_type,
               isc.meeting_link, isc.location,
               isc.venue_name, isc.full_address, isc.map_link,
               isc.phone_number, isc.contact_person,
               j.title AS role, cp.company_name AS company
        FROM interview_schedules isc
        JOIN applications a ON a.id = isc.application_id
        JOIN jobs j ON j.id = a.job_id
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE a.seeker_id = :uid AND isc.scheduled_at >= NOW()
          AND isc.status = 'Scheduled'
        GROUP BY isc.id
        ORDER BY isc.scheduled_at ASC
        LIMIT 5
    ");
    $stmtInt->execute([':uid' => $uid]);
    $intRows = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
    $colors = [
        'linear-gradient(135deg,#4CAF70,#2A7040)',
        'linear-gradient(135deg,#D13D2C,#7A1515)',
        'linear-gradient(135deg,#4A90D9,#1E3A5F)',
        'linear-gradient(135deg,#D4943A,#7A5315)',
    ];
    foreach ($intRows as $i => $r) {
        $dt = new DateTime($r['scheduled_at']);
        $type = ucfirst($r['interview_type'] ?? 'Online');
        if ($type === 'On-site' || $type === 'On_site' || $type === 'Onsite') { $icon = 'fa-map-marker-alt'; $type = 'On-site'; }
        elseif ($type === 'Phone' || $type === 'Phone call') { $icon = 'fa-phone'; $type = 'Phone'; }
        else { $icon = 'fa-video'; $type = 'Online'; }
        $interviewsData[] = [
            'company' => $r['company'] ?? 'Company',
            'role'    => $r['role'],
            'day'     => $dt->format('j'),
            'mon'     => $dt->format('M'),
            'time'    => $dt->format('g:i A'),
            'type'    => $type,
            'icon'    => $icon,
            'color'   => $colors[$i % count($colors)],
            'venueName'     => $r['venue_name'] ?? '',
            'fullAddress'   => $r['full_address'] ?? '',
            'mapLink'       => $r['map_link'] ?? '',
            'phoneNumber'   => $r['phone_number'] ?? '',
            'contactPerson' => $r['contact_person'] ?? '',
            'meetingLink'   => $r['meeting_link'] ?? '',
            'location'      => $r['location'] ?? '',
        ];
    }

    // Recommended / latest jobs (exclude already-applied)
    $stmtJobs = $db->prepare("
        SELECT j.id, j.title, cp.company_name AS company,
               j.location, j.setup, j.salary_min, j.salary_max,
               j.skills_required, j.created_at
        FROM jobs j
        LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
        WHERE j.status = 'active'
           AND j.id NOT IN (SELECT job_id FROM applications WHERE seeker_id = :uid)
        ORDER BY j.created_at DESC
        LIMIT 6
    ");
    $stmtJobs->execute([':uid' => $uid]);
    $jobRows = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jobRows as $r) {
        $tags = $r['skills_required'] ? array_slice(array_map('trim', explode(',', $r['skills_required'])), 0, 3) : [];
        $salaryStr = '';
        if ($r['salary_min'] && $r['salary_max']) {
            $salaryStr = '&#8369;' . number_format($r['salary_min']/1000) . 'k – &#8369;' . number_format($r['salary_max']/1000) . 'k';
        }
        $dayOld = (time() - strtotime($r['created_at'])) / 86400;
        $jobsData[] = [
            'id'        => (int)$r['id'],
            'title'     => $r['title'],
            'company'   => $r['company'] ?? 'Company',
            'location'  => $r['location'] ?? '',
            'workSetup' => ucfirst($r['setup'] ?? 'On-site'),
            'salary'    => $salaryStr,
            'tags'      => $tags,
            'isNew'     => $dayOld <= 3,
            'saved'     => false,
        ];
    }
} catch (\Throwable $e) {
    error_log('seekerDashboard data error: ' . $e->getMessage());
}

// ── Summary card counts ──
$appCount      = count($applicationsData);
$savedCount    = 0;
$interviewCount= count($interviewsData);
$msgCount      = 0;
$savedJobIds   = [];
try {
    if (!isset($db)) $db = getDB();
    $scQ = $db->prepare("SELECT COUNT(*) FROM saved_jobs WHERE user_id = ?");
    $scQ->execute([$uid]);
    $savedCount    = (int)$scQ->fetchColumn();
    $savedIdsQ = $db->prepare("SELECT job_id FROM saved_jobs WHERE user_id = ?");
    $savedIdsQ->execute([$uid]);
    $savedJobIds   = array_map('intval', $savedIdsQ->fetchAll(PDO::FETCH_COLUMN));
    foreach ($jobsData as &$jobRow) {
      $jobRow['saved'] = in_array($jobRow['id'], $savedJobIds, true);
    }
    unset($jobRow);
      $appCount      = (int)$db->query("SELECT COUNT(*) FROM applications WHERE seeker_id = $uid")->fetchColumn();
    $msgCount      = (int)$db->query("SELECT COUNT(*) FROM messages WHERE receiver_id = $uid AND is_read = 0")->fetchColumn();
} catch (\Throwable $e) {
    // keep defaults
}

$appsJson       = json_encode($applicationsData, JSON_HEX_TAG | JSON_HEX_AMP);
$interviewsJson = json_encode($interviewsData,   JSON_HEX_TAG | JSON_HEX_AMP);
$jobsJson       = json_encode($jobsData,         JSON_HEX_TAG | JSON_HEX_AMP);
$savedJobIdsJson = json_encode($savedJobIds);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Job Seeker Dashboard</title>
  <script>(function(){const p=new URLSearchParams(window.location.search).get('theme');const t=p||localStorage.getItem('ac-theme')||'light';if(p)localStorage.setItem('ac-theme',p);if(t==='light')document.documentElement.classList.add('theme-light');})();</script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--amber-dim:#251C0E;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased;}
    .tunnel-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}.tunnel-bg svg{width:100%;height:100%;opacity:0.05;}
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}@keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}
    .page-shell{max-width:1380px;margin:0 auto;padding:0 24px 40px;position:relative;z-index:2;}
    .search-header{padding:20px 0 18px;}.search-greeting{font-family:var(--font-display);font-size:26px;font-weight:700;color:#F5F0EE;margin-bottom:4px;}.search-greeting em{color:var(--red-bright);font-style:italic;}.search-sub{font-size:13px;color:var(--text-muted);}
    .content-layout{display:block;max-width:1100px;margin:0 auto;}
    .sidebar-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;overflow:hidden;display:flex;flex-direction:column;flex:1;min-height:0;}
    .sidebar-profile{padding:14px 14px 12px;border-bottom:1px solid var(--soil-line);}
    .sp-inner{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
    .sp-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
    .sp-name{font-size:13px;font-weight:700;color:#F5F0EE;}.sp-role{font-size:10px;color:var(--red-pale);font-weight:600;margin-top:1px;}
    .prog-label{display:flex;justify-content:space-between;font-size:10px;color:var(--text-muted);margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;}
    .prog-bar{height:4px;background:var(--soil-hover);border-radius:3px;overflow:hidden;}.prog-fill{height:100%;background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));border-radius:3px;}
    .sidebar-stats{padding:10px 12px;border-bottom:1px solid var(--soil-line);display:grid;grid-template-columns:1fr 1fr;gap:6px;}
    .sb-stat{background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:6px;padding:8px 10px;}
    .sb-stat-num{font-family:var(--font-display);font-size:18px;font-weight:700;color:#F5F0EE;line-height:1;}.sb-stat-lbl{font-size:9px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px;}
    .sb-nav-scroll{flex:1;overflow-y:auto;scrollbar-width:none;}.sb-nav-scroll::-webkit-scrollbar{display:none;}
    .sb-nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all 0.18s;border:none;border-bottom:1px solid var(--soil-line);background:none;font-family:var(--font-body);cursor:pointer;width:100%;text-align:left;}
    .sb-nav-item:last-child{border-bottom:none;}.sb-nav-item:hover{color:#F5F0EE;background:var(--soil-hover);}
    .sb-nav-item.active{color:var(--red-pale);background:rgba(209,61,44,0.08);border-right:2px solid var(--red-vivid);}
    .sb-nav-item i{width:15px;text-align:center;font-size:12px;color:var(--red-bright);}
    .sb-badge{margin-left:auto;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;border-radius:10px;padding:1px 7px;}
    .sb-badge.amber{background:var(--amber);color:#1A0A09;}.sb-badge.green{background:#4CAF70;color:#fff;}
    .sec-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
    .sec-title{font-family:var(--font-display);font-size:18px;font-weight:700;color:#F5F0EE;display:flex;align-items:center;gap:8px;}
    .sec-title i{color:var(--red-bright);font-size:14px;}.sec-count{font-size:11px;font-weight:600;color:var(--text-muted);background:var(--soil-hover);padding:2px 9px;border-radius:4px;}
    .see-more{font-size:12px;font-weight:600;color:var(--red-pale);background:none;border:none;font-family:var(--font-body);display:flex;align-items:center;gap:4px;transition:0.15s;cursor:pointer;text-decoration:none;}.see-more:hover{color:var(--red-bright);}
    .cards-row{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px;}
    .sum-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:8px;transition:all 0.2s;}
    .sum-card:hover{border-color:rgba(209,61,44,0.4);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25);}
    .sc-top{display:flex;align-items:center;justify-content:space-between;}
    .sc-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;}
    .sc-icon.r{background:rgba(209,61,44,.12);color:var(--red-pale);}.sc-icon.a{background:rgba(212,148,58,.12);color:var(--amber);}.sc-icon.g{background:rgba(76,175,112,.1);color:#6ccf8a;}.sc-icon.b{background:rgba(74,144,217,.1);color:#7ab8f0;}.sc-icon.p{background:rgba(156,39,176,.1);color:#cf8ae0;}
    .sc-num{font-family:var(--font-display);font-size:24px;font-weight:700;color:#F5F0EE;line-height:1;}.sc-label{font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.05em;}
    .sc-btn{padding:6px;border-radius:6px;background:transparent;border:1px solid var(--soil-line);color:var(--text-muted);font-family:var(--font-body);font-size:11px;font-weight:700;cursor:pointer;transition:0.18s;width:100%;display:block;text-align:center;text-decoration:none;}
    .sc-btn:hover{background:var(--soil-hover);border-color:var(--red-vivid);color:var(--red-pale);}
    .prog-bar-sm{height:4px;background:var(--soil-hover);border-radius:3px;overflow:hidden;}.prog-fill-sm{height:100%;background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));border-radius:3px;}
    .main-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
    .job-list{display:flex;flex-direction:column;gap:8px;}
    .job-row{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:14px 16px;transition:all 0.18s;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:center;}
    .job-row:hover{border-color:rgba(209,61,44,0.5);background:var(--soil-hover);transform:translateX(2px);}
    .jr-top{display:flex;align-items:center;gap:8px;margin-bottom:4px;}.jr-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:#F5F0EE;}
    .jr-new{font-size:10px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;padding:2px 7px;border-radius:3px;color:var(--red-pale);background:rgba(209,61,44,.1);border:1px solid rgba(209,61,44,.2);}
    .jr-new.green{color:#6ccf8a;background:rgba(76,175,112,.1);border-color:rgba(76,175,112,.2);}.jr-new.amber{color:var(--amber);background:rgba(212,148,58,.1);border-color:rgba(212,148,58,.2);}
    .jr-new.blue{color:#7ab8f0;background:rgba(74,144,217,.1);border-color:rgba(74,144,217,.2);}.jr-new.purple{color:#cf8ae0;background:rgba(156,39,176,.1);border-color:rgba(156,39,176,.2);}
    .jr-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:11px;color:#927C7A;margin-bottom:6px;}.jr-meta span{display:flex;align-items:center;gap:3px;}.jr-meta i{font-size:10px;color:var(--red-bright);}
    .jr-company{color:var(--red-pale);font-weight:600;}.jr-chips{display:flex;gap:4px;flex-wrap:wrap;align-items:flex-start;max-height:56px;overflow:hidden;}
    .chip{font-size:10px;font-weight:500;padding:2px 7px;border-radius:4px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);}
    .job-row-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;}.jr-salary{font-size:13px;font-weight:700;color:#F5F0EE;white-space:nowrap;}
    .jr-actions{display:flex;gap:5px;flex-wrap:wrap;}.jr-btn{padding:5px 11px;border-radius:6px;background:transparent;border:1px solid var(--soil-line);color:var(--text-muted);font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.18s;white-space:nowrap;}
    .jr-btn:hover{background:var(--soil-hover);color:#F5F0EE;}.jr-apply{padding:6px 14px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.2s;}.jr-apply:hover{background:var(--red-bright);}
    .jr-btn.saved{background:rgba(209,61,44,0.12);border-color:rgba(209,61,44,0.3);color:var(--red-pale);}
    .jr-btn.saved:hover{background:rgba(209,61,44,0.18);color:#fff;}
    .jr-btn.saved{background:rgba(209,61,44,0.12);border-color:rgba(209,61,44,0.3);color:var(--red-pale);}.jr-btn.saved:hover{background:rgba(209,61,44,0.18);color:#fff;}
    .featured-scroll{display:flex;gap:12px;overflow-x:auto;padding:4px 4px 16px 4px;margin:-4px -4px 0 -4px;scrollbar-width:none;}.featured-scroll::-webkit-scrollbar{display:none;}
    .featured-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:18px;min-width:220px;max-width:220px;cursor:pointer;transition:all 0.25s;position:relative;overflow:hidden;flex-shrink:0;}
    .featured-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--red-vivid),var(--red-bright));}
    .featured-card:hover{border-color:rgba(209,61,44,0.55);transform:translateY(-3px);box-shadow:0 16px 40px rgba(0,0,0,0.4);}
    .fc-badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--amber);background:var(--amber-dim);border:1px solid rgba(212,148,58,0.22);padding:2px 7px;border-radius:3px;margin-bottom:10px;}
    .fc-badge.green{color:#6ccf8a;background:rgba(76,175,112,0.1);border-color:rgba(76,175,112,0.2);}
    .fc-title{font-family:var(--font-display);font-size:14px;font-weight:700;color:#F5F0EE;margin-bottom:3px;}.fc-company{font-size:11px;color:var(--red-pale);font-weight:600;margin-bottom:10px;}
    .fc-chips{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:10px;}.fc-footer{display:flex;align-items:center;justify-content:space-between;padding-top:10px;border-top:1px solid var(--soil-line);}
    .fc-action{padding:5px 12px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-size:11px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:0.2s;}.fc-action:hover{background:var(--red-bright);}
    .qa-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .qa-btn{display:flex;align-items:center;gap:10px;padding:13px 16px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;cursor:pointer;transition:all 0.2s;font-family:var(--font-body);color:var(--text-mid);font-size:13px;font-weight:600;text-decoration:none;}
    .qa-btn:hover{border-color:rgba(209,61,44,0.4);background:var(--soil-hover);color:#F5F0EE;transform:translateY(-1px);}
    .qa-icon{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;}
    .modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0.82);backdrop-filter:blur(8px);align-items:center;justify-content:center;}.modal-overlay.open{display:flex;}
    .modal-box{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:28px;max-width:540px;width:92%;position:relative;animation:modalIn 0.2s ease;box-shadow:0 40px 80px rgba(0,0,0,0.6);max-height:88vh;overflow-y:auto;}
    @keyframes modalIn{from{opacity:0;transform:scale(0.97)}to{opacity:1;transform:scale(1)}}
    .modal-close{position:absolute;top:16px;right:16px;width:28px;height:28px;border-radius:6px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);font-size:13px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.15s;}.modal-close:hover{color:#F5F0EE;}
    .footer{border-top:1px solid var(--soil-line);padding:20px 24px;max-width:1380px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;color:var(--text-muted);font-size:12px;position:relative;z-index:2;flex-wrap:wrap;gap:10px;}
    .footer-logo{font-family:var(--font-display);font-weight:700;color:var(--red-pale);font-size:15px;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
    ::-webkit-scrollbar{width:5px;}::-webkit-scrollbar-track{background:var(--soil-dark);}::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:3px;}
    html.theme-light body,body.light{--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A;}
    body.light .sidebar-card{background:#FFFFFF;border-color:#E0CECA;}body.light .sp-name{color:#1A0A09;}body.light .sb-stat{background:#F5EEEC;border-color:#E0CECA;}body.light .sb-stat-num{color:#1A0A09;}
    body.light .sb-nav-item:hover{color:#1A0A09;background:#FEF0EE;}body.light .sb-nav-item.active{color:var(--red-mid);}body.light .search-greeting{color:#1A0A09;}body.light .sec-title{color:#1A0A09;}
    body.light .sum-card{background:#FFFFFF;border-color:#E0CECA;}body.light .sc-num{color:#1A0A09;}body.light .job-row{background:#FFFFFF;border-color:#E0CECA;}body.light .job-row:hover{background:#FEF0EE;}
    body.light .jr-title{color:#1A0A09;}body.light .jr-salary{color:#1A0A09;}body.light .chip{background:#F5EEEC;border-color:#E0CECA;color:#5A3838;}body.light .featured-card{background:#FFFFFF;border-color:#E0CECA;}body.light .fc-title{color:#1A0A09;}
    body.light .qa-btn{background:#FFFFFF;border-color:#E0CECA;color:#4A2828;}body.light .qa-btn:hover{background:#FEF0EE;color:#1A0A09;}body.light .modal-box{background:#FFFFFF;border-color:#E0CECA;}
    @media(max-width:1060px){.cards-row{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:760px){.page-shell{padding:0 16px 40px}.cards-row{grid-template-columns:repeat(2,1fr)}.footer{flex-direction:column;text-align:center;padding:16px}}
    @media(max-width:480px){.cards-row{grid-template-columns:1fr 1fr}}
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
<div class="glow-orb glow-1"></div><div class="glow-orb glow-2"></div>

<?php include dirname(__DIR__) . '/includes/seeker_navbar.php'; ?>

<div class="page-shell">

  <!-- SEARCH HEADER -->
  <div class="search-header anim">
    <div class="search-greeting"><span id="greetingText">Good morning</span>, <em><?= htmlspecialchars($user['firstName'], ENT_QUOTES, 'UTF-8') ?>.</em></div>
    <div class="search-sub">Here's a quick look at your job search progress today.</div>
  </div>

  <div class="content-layout">
    <main>

      <!-- SUMMARY CARDS -->
      <div class="cards-row anim">
        <div class="sum-card"><div class="sc-top"><div class="sc-icon a"><i class="fas fa-user"></i></div><div class="sc-num"><?= $completionScore ?>%</div></div><div class="sc-label">Profile</div><div class="prog-bar-sm"><div class="prog-fill-sm" style="width:<?= $completionScore ?>%"></div></div><a class="sc-btn" href="antcareers_seekerProfile.php"><?= $completionScore >= 100 ? 'View Profile' : 'Complete Profile' ?></a></div>
          <div class="sum-card"><div class="sc-top"><div class="sc-icon r"><i class="fas fa-paper-plane"></i></div><div class="sc-num"><?= $appCount ?></div></div><div class="sc-label">Applications</div><a class="sc-btn" href="antcareers_seekerApplications.php">View Applications</a></div>
          <div class="sum-card"><div class="sc-top"><div class="sc-icon g"><i class="fas fa-heart"></i></div><div class="sc-num" id="savedCount"><?= $savedCount ?></div></div><div class="sc-label">Saved Jobs</div><a class="sc-btn" href="antcareers_seekerSaved.php">View Saved</a></div>
          <div class="sum-card"><div class="sc-top"><div class="sc-icon b"><i class="fas fa-calendar-check"></i></div><div class="sc-num"><?= $interviewCount ?></div></div><div class="sc-label">Interviews</div><button class="sc-btn" onclick="window.location.href='antcareers_seekerApplications.php?tab=interview'">View Details</button></div>
          <div class="sum-card"><div class="sc-top"><div class="sc-icon p"><i class="fas fa-envelope"></i></div><div class="sc-num"><?= $msgCount ?></div></div><div class="sc-label">Messages</div><a class="sc-btn" href="antcareers_seekerMessages.php">Open Messages</a></div>
      </div>

      <!-- RECENT APPLICATIONS -->
      <div id="section-apps" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-paper-plane"></i> Recent Applications <span class="sec-count" id="appCount"><?= $appCount ?> applications</span></div>
          <a class="see-more" href="antcareers_seekerApplications.php">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="appsContainer"></div>
      </div>

      <!-- RECOMMENDED JOBS -->
      <div id="section-jobs" style="margin-top:40px;" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-star"></i> Recommended For You <span class="sec-count" id="jobCount"><?= count($jobsData) ?> jobs</span></div>
          <a class="see-more" href="antcareers_seekerJobs.php">Browse all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

      <!-- UPCOMING INTERVIEWS -->
      <div id="section-interviews" style="margin-top:40px;" class="anim anim-d2">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-calendar-alt"></i> Upcoming Interviews</div>
          <button class="see-more" onclick="window.location.href='antcareers_seekerApplications.php?tab=interview'">View all <i class="fas fa-arrow-right"></i></button>
        </div>
        <div class="featured-scroll" id="interviewsContainer"></div>
      </div>

    </main>
  </div>
</div>

<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Job Seeker Dashboard &mdash; <?= htmlspecialchars($user['fullName'], ENT_QUOTES, 'UTF-8') ?></div>
  <div style="display:flex;gap:14px;"><a href="../index.php" style="color:inherit;">&#8592; Public Site</a><span>Privacy</span><span>Terms</span></div>
</footer>

<div class="modal-overlay" id="jobModal">
  <div class="modal-box"><button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button><div id="modalBody"></div></div>
</div>

<script>
const applicationsData = <?= $appsJson ?>;
const interviewsData   = <?= $interviewsJson ?>;
const jobsData         = <?= $jobsJson ?>;
const savedJobs        = new Set(<?= $savedJobIdsJson ?>);
let currentApplyJobId  = null;

function escHtmlD(s){ if(!s) return ''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

function renderApplications() {
  const c = document.getElementById('appsContainer');
  document.getElementById('appCount').textContent = applicationsData.length + ' application' + (applicationsData.length !== 1 ? 's' : '');
  c.innerHTML = applicationsData.map((a,i)=>`<div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;"><div><div class="jr-top"><div class="jr-title">${a.title}</div><span class="jr-new ${a.statusClass}">${a.status}</span></div><div class="jr-meta"><span class="jr-company"><i class="fas fa-building"></i> ${a.company}</span><span><i class="fas fa-calendar"></i> ${a.date}</span></div></div><div class="job-row-right"><div class="jr-actions"><button class="jr-btn" onclick="window.location.href='antcareers_seekerApplications.php'">View</button></div></div></div>`).join('');
}

function renderInterviews(){document.getElementById('interviewsContainer').innerHTML=interviewsData.map(iv=>{
let typeColor=iv.type==='On-site'?'#6ccf8a':iv.type==='Phone'?'var(--amber)':'#B07AFF';
let detail='';
if(iv.type==='Online'){
  if(iv.meetingLink) detail=`<div style="margin-top:6px;font-size:11px;"><a href="${escHtmlD(iv.meetingLink)}" target="_blank" style="color:#B07AFF;word-break:break-all;"><i class="fas fa-link" style="margin-right:3px;"></i>Join Meeting</a></div>`;
}else if(iv.type==='On-site'){
  let parts=[];
  if(iv.venueName) parts.push(`<span><i class="fas fa-building" style="margin-right:3px;color:#6ccf8a;"></i>${escHtmlD(iv.venueName)}</span>`);
  if(iv.fullAddress) parts.push(`<span style="color:var(--text-muted);">${escHtmlD(iv.fullAddress)}</span>`);
  let mapQ = iv.fullAddress || iv.venueName || '';
  if(mapQ){
    let embedSrc = 'https://maps.google.com/maps?q='+encodeURIComponent(mapQ)+'&output=embed&z=14';
    parts.push(`<div style="margin-top:4px;border-radius:8px;overflow:hidden;border:1px solid var(--soil-line);position:relative;"><iframe src="${embedSrc}" width="100%" height="120" style="border:0;display:block;filter:saturate(0.7) contrast(1.1);" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>`);
  }
  if(iv.mapLink) parts.push(`<a href="${escHtmlD(iv.mapLink)}" target="_blank" style="color:#7ab8f0;font-size:10px;display:inline-flex;align-items:center;gap:3px;margin-top:2px;"><i class="fas fa-external-link-alt"></i>Open in Google Maps</a>`);
  else if(iv.location) parts.push(`<span style="color:var(--text-muted);">${escHtmlD(iv.location)}</span>`);
  if(parts.length) detail=`<div style="margin-top:6px;font-size:11px;display:flex;flex-direction:column;gap:2px;">${parts.join('')}</div>`;
}else if(iv.type==='Phone'){
  let parts=[];
  if(iv.phoneNumber) parts.push(`<span><i class="fas fa-phone" style="margin-right:3px;color:var(--amber);"></i>${escHtmlD(iv.phoneNumber)}</span>`);
  if(iv.contactPerson) parts.push(`<span style="color:var(--text-muted);">Contact: ${escHtmlD(iv.contactPerson)}</span>`);
  if(parts.length) detail=`<div style="margin-top:6px;font-size:11px;display:flex;flex-direction:column;gap:2px;">${parts.join('')}</div>`;
}
return `<div class="featured-card"><div class="fc-badge green"><i class="fas fa-calendar-check"></i> Scheduled</div><div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;"><div style="width:36px;height:36px;border-radius:50%;background:${iv.color};display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;">${iv.company.split(' ').map(w=>w[0]).join('').slice(0,2)}</div><div><div class="fc-title" style="font-size:13px;">${escHtmlD(iv.company)}</div><div class="fc-company">${escHtmlD(iv.role)}</div></div></div><div style="background:rgba(209,61,44,0.08);border:1px solid rgba(209,61,44,0.18);border-radius:6px;padding:8px 12px;margin-bottom:8px;"><div style="font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--text-light);line-height:1;">${iv.mon} ${iv.day}</div><div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><i class="fas fa-clock" style="color:var(--red-bright);margin-right:3px;"></i>${iv.time}</div></div><div class="fc-chips"><span class="chip" style="border-color:${typeColor};"><i class="fas ${iv.icon}" style="margin-right:3px;color:${typeColor};"></i>${iv.type}</span></div>${detail}<div class="fc-footer"><button class="jr-btn" style="font-size:10px;" onclick="window.location.href='antcareers_seekerApplications.php?tab=interview'">View Details</button></div></div>`;}).join('');}

function renderJobs() {
  document.getElementById('jobsContainer').innerHTML = jobsData.map((j,i)=>{
    const isSaved = savedJobs.has(j.id) || !!j.saved;
    const iconClass = isSaved ? 'fas fa-heart' : 'far fa-heart';
    return `<div class="job-row" style="animation:fadeUp 0.3s ${i*0.04}s both ease;"><div><div class="jr-top"><div class="jr-title">${j.title}</div>${j.isNew?'<span class="jr-new">New</span>':''}</div><div class="jr-meta"><span class="jr-company"><i class="fas fa-building"></i> ${j.company}</span><span><i class="fas fa-map-marker-alt"></i> ${j.location}</span><span><i class="fas fa-laptop-house"></i> ${j.workSetup}</span></div><div class="jr-chips">${j.tags.map(t=>`<span class="chip">${t}</span>`).join('')}</div></div><div class="job-row-right"><div class="jr-salary">${j.salary}</div><div class="jr-actions"><button class="jr-btn ${isSaved ? 'saved' : ''}" data-saved="${isSaved ? 'true' : 'false'}" title="${isSaved ? 'Unsave job' : 'Save job'}" aria-label="${isSaved ? 'Unsave' : 'Save'}" onclick="event.stopPropagation(); toggleSave(${j.id}, this)"><i class="${iconClass}"></i></button><button class="jr-apply" onclick="event.stopPropagation();openApplyModal(${j.id})">Apply</button></div></div></div>`;
  }).join('');
}

async function toggleSave(jobId, btn) {
  const isSaved = savedJobs.has(jobId);
  try {
    const fd = new FormData();
    fd.append('job_id', String(jobId));
    fd.append('action', isSaved ? 'unsave' : 'save');
    fd.append('json', '1');
    const res = await fetch('save_job.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Could not update saved jobs.');
      return;
    }

    if (isSaved) {
      savedJobs.delete(jobId);
    } else {
      savedJobs.add(jobId);
    }

    const savedCountEl = document.getElementById('savedCount');
    if (savedCountEl) {
      savedCountEl.textContent = String(savedJobs.size);
    }
    renderJobs();

    if (typeof window.showToast === 'function') {
      window.showToast(isSaved ? 'Removed from saved jobs' : 'Job saved!', isSaved ? 'fa-heart-broken' : 'fa-heart');
    }
  } catch (error) {
    alert('Network error while updating saved jobs.');
  }
}

function openApplyModal(jobId) {
  currentApplyJobId = jobId;
  const j = jobsData.find(x => x.id === jobId);
  if (!j) return;

  document.getElementById('modalBody').innerHTML = `
    <div class="sec-title" style="font-size:22px;margin-bottom:10px;"><i class="fas fa-paper-plane"></i> Quick Apply</div>
    <div style="font-size:16px;font-weight:700;color:var(--text-light);margin-bottom:6px;">${escHtmlD(j.title)}</div>
    <div style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">${escHtmlD(j.company)} · ${escHtmlD(j.location)} · ${escHtmlD(j.workSetup)}</div>
    <label style="display:block;font-size:11px;color:var(--text-muted);font-weight:700;letter-spacing:0.06em;text-transform:uppercase;margin-bottom:6px;">Cover Letter (Optional)</label>
    <textarea id="dashApplyCover" rows="5" style="width:100%;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:10px 12px;color:var(--text-light);font-family:var(--font-body);font-size:13px;resize:vertical;outline:none;" placeholder="Tell the employer why you're a great fit..."></textarea>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;">
      <button class="jr-btn" onclick="closeApplyModal()">Cancel</button>
      <button class="jr-apply" id="dashApplySubmit" onclick="submitDashboardApply()"><i class="fas fa-paper-plane"></i> Submit Application</button>
    </div>`;

  document.getElementById('jobModal').classList.add('open');
}

function closeApplyModal() {
  document.getElementById('jobModal').classList.remove('open');
  currentApplyJobId = null;
}

async function submitDashboardApply() {
  if (!currentApplyJobId) return;
  const btn = document.getElementById('dashApplySubmit');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
  }

  const fd = new FormData();
  fd.append('job_id', String(currentApplyJobId));
  fd.append('cover_letter', document.getElementById('dashApplyCover')?.value || '');

  try {
    const res = await fetch('apply_job.php', { method:'POST', body:fd });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'antcareers_seekerApplications.php';
      return;
    }
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
    }
    alert(data.message || 'Could not submit application.');
  } catch (_) {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
    }
    alert('Network error while submitting application.');
  }
}

document.getElementById('closeModal').addEventListener('click', closeApplyModal);
document.getElementById('jobModal').addEventListener('click', e => { if (e.target === document.getElementById('jobModal')) closeApplyModal(); });

renderApplications();
renderInterviews();
renderJobs();

(function(){var h=new Date().getHours();var el=document.getElementById('greetingText');if(el)el.textContent=h<12?'Good morning':h<18?'Good afternoon':'Good evening';})();
</script>
</body>
</html>