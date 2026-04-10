<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'applicants';

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $allowed = ['Pending','Reviewed','Shortlisted','Rejected','Hired'];
    $action  = (string)($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $newS  = trim((string)($_POST['status'] ?? ''));
        if (!$appId || !in_array($newS, $allowed, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
        }
        try {
            $db  = getDB();
            $chk = $db->prepare("SELECT a.id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.employer_id=?");
            $chk->execute([$appId,(int)$_SESSION['user_id']]);
            if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }
            $db->prepare("UPDATE applications SET status=?,reviewed_at=NOW() WHERE id=?")->execute([$newS,$appId]);
            echo json_encode(['ok'=>true,'status'=>$newS]);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>'DB error']); }
        exit;
    }

    if ($action === 'schedule_interview') {
        $appId         = (int)($_POST['application_id'] ?? 0);
        $dt            = trim((string)($_POST['scheduled_at'] ?? ''));
        $type          = trim((string)($_POST['interview_type'] ?? 'Online'));
        $link          = trim((string)($_POST['meeting_link'] ?? ''));
        $notes         = trim((string)($_POST['notes'] ?? ''));
        $venueName     = trim((string)($_POST['venue_name'] ?? ''));
        $fullAddress   = trim((string)($_POST['full_address'] ?? ''));
        $mapLink       = trim((string)($_POST['map_link'] ?? ''));
        $phoneNumber   = trim((string)($_POST['phone_number'] ?? ''));
        $contactPerson = trim((string)($_POST['contact_person'] ?? ''));
        $location      = trim((string)($_POST['location'] ?? ''));

        if (!$appId || !$dt) { echo json_encode(['ok'=>false,'msg'=>'Missing fields']); exit; }
        if (!in_array($type, ['Online','Phone','On-site'], true)) { echo json_encode(['ok'=>false,'msg'=>'Invalid interview type']); exit; }

        // Type-specific validation
        if ($type === 'Online' && $link === '') {
            echo json_encode(['ok'=>false,'msg'=>'Meeting link is required for online interviews']); exit;
        }
        if ($type === 'On-site') {
            if ($venueName === '') { echo json_encode(['ok'=>false,'msg'=>'Venue name is required for on-site interviews']); exit; }
            if ($fullAddress === '') { echo json_encode(['ok'=>false,'msg'=>'Full address is required for on-site interviews']); exit; }
            if ($mapLink === '') { echo json_encode(['ok'=>false,'msg'=>'Google Maps link is required for on-site interviews']); exit; }
            $location = $venueName;
        }
        if ($type === 'Phone' && $phoneNumber === '') {
            echo json_encode(['ok'=>false,'msg'=>'Phone number is required for phone call interviews']); exit;
        }

        try {
            $db  = getDB();

            // Auto-add new columns if they don't exist yet
            try {
                $db->query("SELECT venue_name FROM interview_schedules LIMIT 0");
            } catch (PDOException $e) {
                $db->exec("ALTER TABLE interview_schedules ADD COLUMN venue_name VARCHAR(300) DEFAULT NULL AFTER location, ADD COLUMN full_address VARCHAR(500) DEFAULT NULL AFTER venue_name, ADD COLUMN map_link VARCHAR(500) DEFAULT NULL AFTER full_address, ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL AFTER map_link, ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL AFTER phone_number");
            }

            $chk = $db->prepare("SELECT a.seeker_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.employer_id=?");
            $chk->execute([$appId,(int)$_SESSION['user_id']]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

            // Check if an active interview already exists for this application → UPDATE; otherwise INSERT
            $existing = $db->prepare("SELECT id FROM interview_schedules WHERE application_id=? AND status='Scheduled' ORDER BY id DESC LIMIT 1");
            $existing->execute([$appId]);
            $existingId = $existing->fetchColumn();

            $ivData = [
                $dt,
                $type,
                $type === 'Online' ? $link : null,
                $type === 'On-site' ? $venueName : null,
                $type === 'On-site' ? $venueName : null,
                $type === 'On-site' ? $fullAddress : null,
                $type === 'On-site' ? $mapLink : null,
                $type === 'Phone' ? $phoneNumber : null,
                $type === 'Phone' ? ($contactPerson ?: null) : null,
                $notes ?: null,
            ];

            if ($existingId) {
                // UPDATE the existing record
                $db->prepare("UPDATE interview_schedules SET scheduled_at=?,interview_type=?,meeting_link=?,location=?,venue_name=?,full_address=?,map_link=?,phone_number=?,contact_person=?,notes=?,updated_at=NOW() WHERE id=?")
                   ->execute(array_merge($ivData, [$existingId]));
            } else {
                // First time scheduling — INSERT
                $db->prepare("INSERT INTO interview_schedules (application_id,employer_id,seeker_id,scheduled_at,interview_type,meeting_link,location,venue_name,full_address,map_link,phone_number,contact_person,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute(array_merge([$appId, (int)$_SESSION['user_id'], $row['seeker_id']], $ivData));
            }

            $db->prepare("UPDATE applications SET status='Shortlisted',reviewed_at=NOW() WHERE id=? AND status IN ('Pending','Reviewed')")->execute([$appId]);
            echo json_encode(['ok'=>true,'updated'=>(bool)$existingId]);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]); }
        exit;
    }
    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

/* ── FETCH DATA ── */
$filterStatus = trim((string)($_GET['status'] ?? ''));
$filterJob    = (int)($_GET['job_id'] ?? 0);
$search       = trim((string)($_GET['q'] ?? ''));
$uid          = (int)$_SESSION['user_id'];
$applicants   = [];
$sCounts      = ['Pending'=>0,'Reviewed'=>0,'Shortlisted'=>0,'Rejected'=>0,'Hired'=>0];
$total        = 0;
$jobsList     = [];
$dbErr        = false;

try {
    $db = getDB();

    // One-time cleanup: cancel stale duplicate Scheduled interviews (keep only newest per application)
    try {
        $db->prepare("
            UPDATE interview_schedules
            SET status='Cancelled', updated_at=NOW()
            WHERE employer_id=? AND status='Scheduled'
              AND id NOT IN (
                SELECT keep_id FROM (
                    SELECT MAX(id) AS keep_id FROM interview_schedules
                    WHERE employer_id=? AND status='Scheduled'
                    GROUP BY application_id
                ) AS keep_tbl
              )
        ")->execute([$uid, $uid]);
    } catch (PDOException $ignore) {}

    $sc = $db->prepare("SELECT a.status,COUNT(*) AS c FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.employer_id=? GROUP BY a.status");
    $sc->execute([$uid]);
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($sCounts[$r['status']])) $sCounts[$r['status']] = (int)$r['c'];
    }
    $total = array_sum($sCounts);

    $w = ['j.employer_id=?']; $p = [$uid];
    if ($filterStatus && in_array($filterStatus,array_keys($sCounts),true)){ $w[]='a.status=?'; $p[]=$filterStatus; }
    if ($filterJob>0){ $w[]='j.id=?'; $p[]=$filterJob; }
    if ($search!==''){
        $w[]='(u.full_name LIKE ? OR j.title LIKE ? OR u.email LIKE ?)';
        $lk="%{$search}%"; $p[]=$lk; $p[]=$lk; $p[]=$lk;
    }
    $wc='WHERE '.implode(' AND ',$w);
    $st=$db->prepare("SELECT a.id AS app_id,a.status,a.cover_letter,a.resume_url,a.applied_at,a.reviewed_at,a.employer_notes,u.id AS seeker_id,u.full_name AS seeker_name,u.email AS seeker_email,j.id AS job_id,j.title AS job_title,j.job_type,j.setup,j.location AS job_location,(SELECT COUNT(*) FROM interview_schedules i WHERE i.application_id=a.id AND i.status='Scheduled') AS has_interview,iv_cur.interview_type AS iv_type,iv_cur.scheduled_at AS iv_date,iv_cur.meeting_link AS iv_link,iv_cur.location AS iv_location,iv_cur.venue_name AS iv_venue,iv_cur.full_address AS iv_address,iv_cur.map_link AS iv_map,iv_cur.phone_number AS iv_phone,iv_cur.contact_person AS iv_contact,iv_cur.notes AS iv_notes FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.seeker_id LEFT JOIN interview_schedules iv_cur ON iv_cur.application_id=a.id AND iv_cur.status='Scheduled' {$wc} GROUP BY a.id ORDER BY FIELD(a.status,'Pending','Reviewed','Shortlisted','Hired','Rejected'),a.applied_at DESC");
    $st->execute($p);
    $applicants=$st->fetchAll(PDO::FETCH_ASSOC);
    $js=$db->prepare("SELECT id,title FROM jobs WHERE employer_id=? AND status='Active' ORDER BY created_at DESC");
    $js->execute([$uid]);
    $jobsList=$js->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dbErr=true;
    error_log('[AntCareers] applicants fetch: '.$e->getMessage());
}

$smeta=['Pending'=>['c'=>'amber','i'=>'fa-clock'],'Reviewed'=>['c'=>'blue','i'=>'fa-eye'],'Shortlisted'=>['c'=>'green','i'=>'fa-star'],'Rejected'=>['c'=>'red','i'=>'fa-times-circle'],'Hired'=>['c'=>'purple','i'=>'fa-check-circle']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — Applicants</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--green:#4CAF70;--blue:#4A90D9;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased;}
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}
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
    .notif-btn-nav{position:relative;width:34px;height:34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:13px;flex-shrink:0;}
    .notif-btn-nav:hover{color:var(--red-bright);}
    .badge{position:absolute;top:-4px;right:-4px;background:var(--red-vivid);color:#fff;font-size:9px;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;}
    .btn-nav-red{padding:7px 16px;border-radius:7px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:13px;font-weight:700;cursor:pointer;transition:0.2s;white-space:nowrap;text-decoration:none;display:flex;align-items:center;gap:7px;}
    .btn-nav-red:hover{background:var(--red-bright);}
    .profile-wrap{position:relative;}
    .profile-btn{display:flex;align-items:center;gap:9px;background:var(--soil-hover);border:1px solid var(--soil-line);border-radius:8px;padding:6px 12px 6px 8px;cursor:pointer;transition:0.2s;flex-shrink:0;}
    .profile-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--amber),#8a5010);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;}
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
    body.light{background:#FAF7F5;color:#1A0A09;}
    body.light .navbar{background:rgba(255,253,252,0.98);border-bottom-color:#D4B0AB;}
    body.light .nav-link{color:#5A4040;}
    body.light .nav-link:hover,body.light .nav-link.active{color:#1A0A09;background:#FEF0EE;}
    body.light .theme-btn,body.light .notif-btn-nav,body.light .profile-btn{background:#F5EDEB;border-color:#D4B0AB;}
    body.light .profile-name{color:#1A0A09;}
    body.light .profile-dropdown,body.light .mobile-menu{background:#FFF8F7;border-color:#D4B0AB;}
    body.light .pd-item{color:#4A3030;}
    body.light .pd-item:hover{background:#FEF0EE;}
    .page-shell{max-width:1380px;margin:0 auto;padding:28px 24px 60px;position:relative;z-index:1;}
    .page-title{font-family:var(--font-display);font-size:28px;font-weight:700;color:#F5F0EE;margin-bottom:5px;}
    .page-title span{color:var(--red-bright);font-style:italic;}
    .page-sub{font-size:14px;color:var(--text-muted);margin-bottom:20px;}
    body.light .page-title{color:#1A0A09;}
    .db-warn{background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.3);border-radius:8px;padding:10px 16px;font-size:13px;color:var(--amber);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    .stats-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
    .stat-pill{display:flex;align-items:center;gap:8px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:10px 15px;cursor:pointer;transition:all 0.18s;text-decoration:none;}
    .stat-pill:hover,.stat-pill.active{border-color:rgba(209,61,44,.45);background:rgba(209,61,44,.07);}
    .sp-icon{font-size:13px;width:17px;text-align:center;}
    .sp-label{font-size:12px;font-weight:600;color:var(--text-muted);}
    .sp-count{font-size:17px;font-weight:800;color:#F5F0EE;font-family:var(--font-display);}
    body.light .stat-pill{background:#fff;border-color:#E0CECA;}
    body.light .sp-count{color:#1A0A09;}
    .toolbar{display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
    .search-bar{display:flex;align-items:center;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;overflow:hidden;flex:1;min-width:200px;transition:0.25s;}
    .search-bar:focus-within{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,0.1);}
    .search-bar .si{padding:0 13px;color:var(--text-muted);font-size:14px;}
    .search-bar input{flex:1;padding:11px 0;background:none;border:none;outline:none;font-family:var(--font-body);font-size:14px;color:#F5F0EE;}
    .search-bar input::placeholder{color:var(--text-muted);}
    body.light .search-bar{background:#fff;border-color:#E0CECA;}
    body.light .search-bar input{color:#1A0A09;}
    select.fsel{padding:10px 13px;border-radius:8px;background:var(--soil-card);border:1px solid var(--soil-line);color:var(--text-mid);font-family:var(--font-body);font-size:13px;cursor:pointer;outline:none;}
    select.fsel:focus{border-color:var(--red-vivid);}
    body.light select.fsel{background:#fff;border-color:#E0CECA;color:#3A2020;}
    .app-list{display:flex;flex-direction:column;gap:10px;}
    .app-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;overflow:hidden;transition:border-color 0.2s,box-shadow 0.2s;}
    .app-card:hover{border-color:rgba(209,61,44,.4);box-shadow:0 6px 24px rgba(0,0,0,.3);}
    body.light .app-card{background:#fff;border-color:#E0CECA;}
    .app-main{display:grid;grid-template-columns:44px 1fr auto;gap:14px;padding:16px 18px;align-items:start;}
    .app-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;flex-shrink:0;}
    .app-info{min-width:0;}
    .app-name{font-family:var(--font-display);font-size:15px;font-weight:700;color:#F5F0EE;margin-bottom:2px;transition:color 0.2s;}
    a.app-name:hover{color:var(--red-bright);}
    a.app-avatar:hover{opacity:0.85;}
    body.light .app-name{color:#1A0A09;}
    .app-email{font-size:12px;color:var(--text-muted);margin-bottom:7px;}
    .app-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;color:var(--text-muted);}
    .app-meta i{color:var(--red-bright);font-size:10px;}
    .app-job{font-weight:700;color:var(--red-pale);}
    .app-right{display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex-shrink:0;}
    .app-date{font-size:11px;color:var(--text-muted);}
    .app-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;}
    .sbadge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;letter-spacing:.04em;}
    .sbadge.amber{color:var(--amber);background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.25);}
    .sbadge.blue{color:#7ab8f0;background:rgba(74,144,217,.1);border:1px solid rgba(74,144,217,.2);}
    .sbadge.green{color:#6ccf8a;background:rgba(76,175,112,.1);border:1px solid rgba(76,175,112,.2);}
    .sbadge.red{color:#ff8080;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);}
    .sbadge.purple{color:#cf8ae0;background:rgba(156,39,176,.1);border:1px solid rgba(156,39,176,.2);}
    .sbadge.muted{color:var(--text-muted);background:var(--soil-hover);border:1px solid var(--soil-line);}
    .chip{font-size:11px;font-weight:500;padding:3px 8px;border-radius:4px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);}
    body.light .chip{background:#F5EDEB;border-color:#D4B0AB;color:#6A4A4A;}
    .btn{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:.18s;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);white-space:nowrap;}
    .btn:hover{background:var(--soil-hover);color:#F5F0EE;}
    .btn.primary{background:var(--red-vivid);border-color:var(--red-vivid);color:#fff;}
    .btn.primary:hover{background:var(--red-bright);}
    .btn.amb{border-color:rgba(212,148,58,.4);color:var(--amber);}
    .btn.amb:hover{background:rgba(212,148,58,.1);}
    body.light .btn{border-color:#D4B0AB;color:#5A4040;}
    .app-expand{border-top:1px solid var(--soil-line);padding:16px 18px;display:none;background:var(--soil-hover);}
    body.light .app-expand{background:#FAF7F5;border-color:#E0CECA;}
    .app-expand.open{display:block;}
    .exp-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
    @media(max-width:640px){.exp-grid{grid-template-columns:1fr;}.app-right{display:none;}}
    .etitle{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:7px;}
    .etitle i{color:var(--red-bright);margin-right:4px;}
    .cover-text{font-size:13px;color:var(--text-mid);line-height:1.6;white-space:pre-wrap;}
    .status-row{display:flex;align-items:center;gap:8px;margin-top:4px;}
    .status-sel{padding:7px 11px;border-radius:7px;background:var(--soil-card);border:1px solid var(--soil-line);color:#F5F0EE;font-family:var(--font-body);font-size:13px;cursor:pointer;outline:none;}
    .status-sel:focus{border-color:var(--red-vivid);}
    body.light .status-sel{background:#fff;border-color:#D4B0AB;color:#1A0A09;}
    .save-btn{padding:7px 15px;border-radius:6px;background:var(--red-vivid);border:none;color:#fff;font-family:var(--font-body);font-size:12px;font-weight:700;cursor:pointer;transition:.2s;}
    .save-btn:hover{background:var(--red-bright);}
    .modal-bd{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;display:none;align-items:flex-start;justify-content:center;padding:40px 20px;overflow-y:auto;}
    .modal-bd.open{display:flex;}
    .modal-box{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:16px;padding:26px;width:100%;max-width:480px;position:relative;margin:auto;}
    body.light .modal-box{background:#fff;border-color:#E0CECA;}
    .modal-title{font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;margin-bottom:18px;display:flex;align-items:center;gap:9px;}
    body.light .modal-title{color:#1A0A09;}
    .modal-close{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:6px;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;}
    .modal-close:hover{background:var(--soil-hover);color:#F5F0EE;}
    .fg{margin-bottom:13px;}
    .fl{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;display:block;}
    .fi{width:100%;padding:9px 13px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:#F5F0EE;font-family:var(--font-body);font-size:13px;outline:none;transition:.2s;}
    .fi:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .fi{background:#F5EDEB;border-color:#D4B0AB;color:#1A0A09;}
    textarea.fi{resize:vertical;min-height:72px;}
    .frow{display:grid;grid-template-columns:1fr 1fr;gap:11px;}
    .mfoot{display:flex;justify-content:flex-end;gap:9px;margin-top:18px;}
    .empty{text-align:center;padding:55px 20px;color:var(--text-muted);}
    .empty i{font-size:42px;margin-bottom:12px;color:var(--soil-line);display:block;}
    .empty p{font-size:14px;}
    .toast{position:fixed;bottom:22px;right:22px;background:var(--soil-card);border:1px solid var(--soil-line);border-left:4px solid var(--red-vivid);border-radius:8px;padding:11px 16px;font-size:13px;font-weight:600;color:#F5F0EE;z-index:900;transform:translateY(80px);opacity:0;transition:all .35s ease;max-width:300px;box-shadow:0 8px 32px rgba(0,0,0,.4);pointer-events:none;}
    .toast.show{transform:translateY(0);opacity:1;}
    .toast.ok{border-left-color:#4CAF70;}
    .toast.err{border-left-color:#E05555;}
  </style>
</head>
<body>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_employer.php'; ?>

<div class="page-shell">
  <h1 class="page-title">Applicant <span>Pipeline</span></h1>
  <p class="page-sub">Review, shortlist and manage all job applications in one place.</p>

  <?php if($dbErr):?>
  <div class="db-warn"><i class="fas fa-exclamation-triangle"></i> Demo data shown — run <strong>sql/migration_employer.sql</strong> to connect live data.</div>
  <?php endif;?>

  <div class="stats-row">
  <?php
  $statDefs=[
    ''=>['i'=>'fa-users','l'=>'All','c'=>'var(--red-pale)','n'=>$total],
    'Pending'=>['i'=>'fa-clock','l'=>'Pending','c'=>'var(--amber)','n'=>$sCounts['Pending']],
    'Reviewed'=>['i'=>'fa-eye','l'=>'Reviewed','c'=>'#7ab8f0','n'=>$sCounts['Reviewed']],
    'Shortlisted'=>['i'=>'fa-star','l'=>'Shortlisted','c'=>'#6ccf8a','n'=>$sCounts['Shortlisted']],
    'Hired'=>['i'=>'fa-check-circle','l'=>'Hired','c'=>'#cf8ae0','n'=>$sCounts['Hired']],
    'Rejected'=>['i'=>'fa-times-circle','l'=>'Rejected','c'=>'#ff8080','n'=>$sCounts['Rejected']],
  ];
  foreach($statDefs as $k=>$d):
    $act=($filterStatus===$k);
    $hr=$k?"employer_applicants.php?status={$k}":'employer_applicants.php';
  ?>
  <a class="stat-pill <?=$act?'active':''?>" href="<?=htmlspecialchars($hr)?>">
    <i class="fas <?=$d['i']?> sp-icon" style="color:<?=$d['c']?>"></i>
    <span class="sp-label"><?=$d['l']?></span>
    <span class="sp-count"><?=$d['n']?></span>
  </a>
  <?php endforeach;?>
  </div>

  <form method="get" action="employer_applicants.php">
    <?php if($filterStatus):?><input type="hidden" name="status" value="<?=htmlspecialchars($filterStatus)?>"><?php endif;?>
    <div class="toolbar">
      <div class="search-bar"><i class="fas fa-search si"></i><input type="text" name="q" placeholder="Search name, email or job…" value="<?=htmlspecialchars($search)?>"></div>
      <select name="job_id" class="fsel" onchange="this.form.submit()">
        <option value="">All Jobs</option>
        <?php foreach($jobsList as $j):?><option value="<?=$j['id']?>"<?=$filterJob===$j['id']?' selected':''?>><?=htmlspecialchars($j['title'])?></option><?php endforeach;?>
      </select>
      <button type="submit" class="btn primary"><i class="fas fa-search"></i> Search</button>
      <?php if($search||$filterJob):?><a href="employer_applicants.php<?=$filterStatus?"?status={$filterStatus}":''?>" class="btn"><i class="fas fa-times"></i> Clear</a><?php endif;?>
    </div>
  </form>

  <div class="app-list">
  <?php if(empty($applicants)):?>
  <div class="empty"><i class="fas fa-user-slash"></i><p>No applicants found<?=$filterStatus?" with status <strong>{$filterStatus}</strong>":''?>.</p></div>
  <?php else: foreach($applicants as $a):
    $ini=strtoupper(substr($a['seeker_name'],0,1));
    $sm=$smeta[$a['status']]??['c'=>'muted','i'=>'fa-circle'];
    $dA=date('M j, Y',strtotime($a['applied_at']));
    $dR=$a['reviewed_at']?date('M j, Y',strtotime($a['reviewed_at'])):'—';
  ?>
  <div class="app-card" id="card-<?=$a['app_id']?>">
    <div class="app-main">
      <a href="employer_view_applicant.php?id=<?=$a['seeker_id']?>" class="app-avatar" style="text-decoration:none;color:#fff;"><?=htmlspecialchars($ini)?></a>
      <div class="app-info">
        <a href="employer_view_applicant.php?id=<?=$a['seeker_id']?>" class="app-name" style="text-decoration:none;color:inherit;"><?=htmlspecialchars($a['seeker_name'])?></a>
        <div class="app-email"><?=htmlspecialchars($a['seeker_email'])?></div>
        <div class="app-meta">
          <span><i class="fas fa-briefcase"></i> <span class="app-job"><?=htmlspecialchars($a['job_title'])?></span></span>
          <span><i class="fas fa-calendar-alt"></i> Applied <?=$dA?></span>
          <?php if($a['has_interview']):?><span class="chip"><i class="fas fa-video" style="color:#6ccf8a;margin-right:3px"></i> Interview Scheduled</span><?php endif;?>
          <span class="sbadge <?=$sm['c']?>" id="badge-<?=$a['app_id']?>"><i class="fas <?=$sm['i']?>"></i> <?=$a['status']?></span>
        </div>
      </div>
      <div class="app-right">
        <span class="app-date"><?=$dA?></span>
        <div class="app-actions">
          <button class="btn" onclick="toggleExp(<?=$a['app_id']?>)"><i class="fas fa-chevron-down" id="chev-<?=$a['app_id']?>"></i> Review</button>
          <button class="btn amb" onclick="openInterview(<?=$a['app_id']?>,'<?=htmlspecialchars($a['seeker_name'],ENT_QUOTES)?>',<?=htmlspecialchars(json_encode($a['has_interview'] ? ['type'=>$a['iv_type']??'','date'=>$a['iv_date']??'','link'=>$a['iv_link']??'','venue'=>$a['iv_venue']??'','address'=>$a['iv_address']??'','map'=>$a['iv_map']??'','phone'=>$a['iv_phone']??'','contact'=>$a['iv_contact']??'','notes'=>$a['iv_notes']??''] : null),ENT_QUOTES,'UTF-8')?>)"><i class="fas <?=$a['has_interview']?'fa-edit':'fa-calendar-plus'?>"></i> <?=$a['has_interview']?'Edit Interview':'Schedule'?></button>
          <?php if($a['resume_url']):?><a href="<?=htmlspecialchars($a['resume_url'])?>" target="_blank" class="btn"><i class="fas fa-file-alt"></i> Resume</a><?php endif;?>
        </div>
      </div>
    </div>
    <div class="app-expand" id="exp-<?=$a['app_id']?>">
      <div class="exp-grid">
        <div>
          <div class="etitle"><i class="fas fa-envelope-open-text"></i> Cover Letter</div>
          <?php if(trim((string)$a['cover_letter'])):?>
          <p class="cover-text"><?=nl2br(htmlspecialchars($a['cover_letter']))?></p>
          <?php else:?><p class="cover-text" style="color:var(--text-muted);font-style:italic">No cover letter provided.</p><?php endif;?>
        </div>
        <div>
          <div class="etitle"><i class="fas fa-tasks"></i> Update Status</div>
          <div class="status-row">
            <select class="status-sel" id="sel-<?=$a['app_id']?>">
              <?php foreach(['Pending','Reviewed','Shortlisted','Rejected','Hired'] as $s):?>
              <option value="<?=$s?>"<?=$s===$a['status']?' selected':''?>><?=$s?></option>
              <?php endforeach;?>
            </select>
            <button class="save-btn" onclick="saveStatus(<?=$a['app_id']?>)">Save</button>
          </div>
          <?php if($a['employer_notes']):?>
          <div style="margin-top:12px;"><div class="etitle"><i class="fas fa-sticky-note"></i> Notes</div><p class="cover-text"><?=nl2br(htmlspecialchars($a['employer_notes']))?></p></div>
          <?php endif;?>
          <div style="margin-top:12px;"><div class="etitle"><i class="fas fa-info-circle"></i> Timeline</div><p class="cover-text">Applied: <?=$dA?><br>Reviewed: <?=$dR?></p></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif;?>
  </div>
</div>

<div class="modal-bd" id="iModal">
  <div class="modal-box">
    <button class="modal-close" onclick="document.getElementById('iModal').classList.remove('open')"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="iModalTitle"><i class="fas fa-calendar-check" style="color:var(--red-bright)"></i> Schedule Interview</div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:15px;" id="iName"></p>
    <input type="hidden" id="iAppId">
    <div class="frow">
      <div class="fg"><label class="fl">Date &amp; Time <span style="color:var(--red-pale)">*</span></label><input type="datetime-local" class="fi" id="iDate"></div>
      <div class="fg"><label class="fl">Type <span style="color:var(--red-pale)">*</span></label><select class="fi" id="iType" onchange="onInterviewTypeChange()"><option value="Online">Online (Video)</option><option value="Phone">Phone Call</option><option value="On-site">On-site</option></select></div>
    </div>

    <!-- Online (Video) fields -->
    <div id="fieldsOnline">
      <div class="fg"><label class="fl">Meeting Link <span style="color:var(--red-pale)">*</span></label><input type="url" class="fi" id="iLink" placeholder="https://meet.google.com/… or https://zoom.us/…"></div>
    </div>

    <!-- On-site fields -->
    <div id="fieldsOnsite" style="display:none;">
      <div class="fg"><label class="fl">Venue / Location Name <span style="color:var(--red-pale)">*</span></label><input type="text" class="fi" id="iVenue" placeholder="e.g. TechNova PH Main Office, 5th Floor"></div>
      <div class="fg"><label class="fl">Full Address <span style="color:var(--red-pale)">*</span></label><input type="text" class="fi" id="iAddress" placeholder="e.g. 123 Ayala Ave., Makati City, Metro Manila"></div>
      <div class="fg"><label class="fl">Google Maps Link <span style="color:var(--red-pale)">*</span></label><input type="url" class="fi" id="iMapLink" placeholder="https://maps.google.com/… or https://goo.gl/maps/…"></div>
    </div>

    <!-- Phone Call fields -->
    <div id="fieldsPhone" style="display:none;">
      <div class="fg"><label class="fl">Contact Phone Number <span style="color:var(--red-pale)">*</span></label><input type="tel" class="fi" id="iPhone" placeholder="e.g. +63 917 123 4567"></div>
      <div class="fg"><label class="fl">Contact Person / Interviewer <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label><input type="text" class="fi" id="iContactPerson" placeholder="e.g. Maria Santos, HR Manager"></div>
    </div>

    <div class="fg"><label class="fl">Notes / Instructions <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label><textarea class="fi" id="iNotes" rows="3" placeholder="What should they prepare? Any special instructions?"></textarea></div>
    <div id="iError" style="display:none;color:#ff8080;font-size:12px;font-weight:600;margin-bottom:10px;padding:8px 12px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);border-radius:6px;"></div>
    <div class="mfoot">
      <button class="btn" onclick="document.getElementById('iModal').classList.remove('open')">Cancel</button>
      <button class="btn primary" id="iSubmitBtn" onclick="submitInterview()"><i class="fas fa-paper-plane"></i> Send Invite</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  function toggleExp(id){var p=document.getElementById('exp-'+id),c=document.getElementById('chev-'+id),o=p.classList.toggle('open');c.style.transform=o?'rotate(180deg)':'';}
  function saveStatus(id){var s=document.getElementById('sel-'+id).value;doPost({action:'update_status',application_id:id,status:s},function(d){if(d.ok){var b=document.getElementById('badge-'+id),m={Pending:{c:'amber',i:'fa-clock'},Reviewed:{c:'blue',i:'fa-eye'},Shortlisted:{c:'green',i:'fa-star'},Rejected:{c:'red',i:'fa-times-circle'},Hired:{c:'purple',i:'fa-check-circle'}}[d.status]||{c:'muted',i:'fa-circle'};b.className='sbadge '+m.c;b.innerHTML='<i class="fas '+m.i+'"></i> '+d.status;toast('Status: '+d.status,'ok');}else{toast(d.msg||'Error','err');}});}

  // Dynamic interview type field switching
  function onInterviewTypeChange(){
    var type=document.getElementById('iType').value;
    document.getElementById('fieldsOnline').style.display = type==='Online' ? 'block' : 'none';
    document.getElementById('fieldsOnsite').style.display = type==='On-site' ? 'block' : 'none';
    document.getElementById('fieldsPhone').style.display  = type==='Phone' ? 'block' : 'none';
    // Clear error when switching
    var errEl=document.getElementById('iError');errEl.style.display='none';errEl.textContent='';
  }

  function openInterview(id,name,existingData){
    document.getElementById('iAppId').value=id;
    document.getElementById('iName').textContent='Applicant: '+name;
    var errEl=document.getElementById('iError');errEl.style.display='none';errEl.textContent='';
    var isEdit = existingData && existingData.type;
    // Update modal title and submit button text
    document.getElementById('iModalTitle').innerHTML='<i class="fas fa-calendar-check" style="color:var(--red-bright)"></i> '+(isEdit?'Edit Interview':'Schedule Interview');
    var submitBtn=document.getElementById('iSubmitBtn');
    submitBtn.innerHTML=isEdit?'<i class="fas fa-save"></i> Update Interview':'<i class="fas fa-paper-plane"></i> Send Invite';

    if(isEdit){
      // Prefill with existing data
      var d=existingData.date?existingData.date.replace(' ','T'):''; // convert MySQL datetime to input format
      if(d && d.length>16) d=d.slice(0,16);
      document.getElementById('iDate').value=d||'';
      document.getElementById('iType').value=existingData.type||'Online';
      document.getElementById('iLink').value=existingData.link||'';
      document.getElementById('iVenue').value=existingData.venue||'';
      document.getElementById('iAddress').value=existingData.address||'';
      document.getElementById('iMapLink').value=existingData.map||'';
      document.getElementById('iPhone').value=existingData.phone||'';
      document.getElementById('iContactPerson').value=existingData.contact||'';
      document.getElementById('iNotes').value=existingData.notes||'';
    } else {
      // New interview — set defaults
      var nd=new Date();nd.setDate(nd.getDate()+1);nd.setHours(10,0,0,0);
      document.getElementById('iDate').value=nd.toISOString().slice(0,16);
      document.getElementById('iType').value='Online';
      document.getElementById('iLink').value='';
      document.getElementById('iVenue').value='';
      document.getElementById('iAddress').value='';
      document.getElementById('iMapLink').value='';
      document.getElementById('iPhone').value='';
      document.getElementById('iContactPerson').value='';
      document.getElementById('iNotes').value='';
    }
    onInterviewTypeChange();
    document.getElementById('iModal').classList.add('open');
  }

  function showError(msg){
    var errEl=document.getElementById('iError');
    errEl.textContent=msg;errEl.style.display='block';
  }

  function submitInterview(){
    var dt=document.getElementById('iDate').value;
    var type=document.getElementById('iType').value;
    var errEl=document.getElementById('iError');errEl.style.display='none';

    if(!dt){showError('Please select a date and time.');return;}

    var postData={
      action:'schedule_interview',
      application_id:document.getElementById('iAppId').value,
      scheduled_at:dt,
      interview_type:type,
      notes:document.getElementById('iNotes').value
    };

    if(type==='Online'){
      var link=document.getElementById('iLink').value.trim();
      if(!link){showError('Meeting link is required for online interviews.');return;}
      if(!link.match(/^https?:\/\/.+/i)){showError('Please enter a valid meeting link starting with http:// or https://');return;}
      postData.meeting_link=link;
    } else if(type==='On-site'){
      var venue=document.getElementById('iVenue').value.trim();
      var addr=document.getElementById('iAddress').value.trim();
      var mapLink=document.getElementById('iMapLink').value.trim();
      if(!venue){showError('Venue / location name is required for on-site interviews.');return;}
      if(!addr){showError('Full address is required for on-site interviews.');return;}
      if(!mapLink){showError('Google Maps link is required for on-site interviews.');return;}
      if(!mapLink.match(/^https?:\/\/.+/i)){showError('Please enter a valid Google Maps link starting with http:// or https://');return;}
      postData.venue_name=venue;
      postData.full_address=addr;
      postData.map_link=mapLink;
    } else if(type==='Phone'){
      var phone=document.getElementById('iPhone').value.trim();
      if(!phone){showError('Contact phone number is required for phone call interviews.');return;}
      postData.phone_number=phone;
      postData.contact_person=document.getElementById('iContactPerson').value.trim();
    }

    var btn=document.getElementById('iSubmitBtn');
    btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Scheduling…';

    doPost(postData,function(d){
      btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Invite';
      if(d.ok){document.getElementById('iModal').classList.remove('open');toast(d.updated?'Interview updated!':'Interview scheduled!','ok');setTimeout(function(){location.reload();},1200);}
      else{showError(d.msg||'Error scheduling interview');}
    });
  }

  function doPost(data,cb){var b=Object.keys(data).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(data[k]);}).join('&');fetch('employer_applicants.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b}).then(function(r){return r.json();}).then(cb).catch(function(){toast('Network error','err');});}
  function toast(msg,type){var t=document.getElementById('toast');t.textContent=msg;t.className='toast show'+(type?' '+type:'');clearTimeout(t._t);t._t=setTimeout(function(){t.className='toast';},3000);}
  function setTheme(t){document.body.classList.toggle('light',t==='light');localStorage.setItem('ac-theme',t);document.getElementById('themeToggle').querySelector('i').className=t==='light'?'fas fa-sun':'fas fa-moon';}
  const _guard_themeToggle = document.getElementById('themeToggle'); if (_guard_themeToggle) _guard_themeToggle.addEventListener('click',function(){setTheme(document.body.classList.contains('light')?'dark':'light');});
  var hb=document.getElementById('hamburger'),mm=document.getElementById('mobileMenu');
  hb.addEventListener('click',function(e){e.stopPropagation();var o=mm.classList.toggle('open');hb.querySelector('i').className=o?'fas fa-times':'fas fa-bars';});
  const _guard_profileToggle = document.getElementById('profileToggle'); if (_guard_profileToggle) _guard_profileToggle.addEventListener('click',function(e){e.stopPropagation();document.getElementById('profileDropdown').classList.toggle('open');});
  document.addEventListener('click',function(e){if(!document.getElementById('profileWrap').contains(e.target))document.getElementById('profileDropdown').classList.remove('open');if(!mm.contains(e.target)&&e.target!==hb){mm.classList.remove('open');hb.querySelector('i').className='fas fa-bars';}});
  const _guard_iModal = document.getElementById('iModal'); if (_guard_iModal) _guard_iModal.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
  (function(){var p=new URLSearchParams(window.location.search).get('theme'),s=localStorage.getItem('ac-theme'),t=p||s||'light';if(p)localStorage.setItem('ac-theme',p);setTheme(t);})();
</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>
