<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/countries.php';
requireLogin('recruiter');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'my-jobs';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── Look up recruiter record to get employer_id ── */
$employerId = 0;
try {
    $recRow = $db->prepare('SELECT r.employer_id, r.company_id FROM recruiters r WHERE r.user_id = :uid AND r.is_active = 1 LIMIT 1');
    $recRow->execute([':uid' => $uid]);
    $rec = $recRow->fetch(PDO::FETCH_ASSOC);
    if ($rec) {
        $employerId = (int)$rec['employer_id'];
    }
} catch (PDOException $e) {
    error_log('[AntCareers] recruiter lookup: ' . $e->getMessage());
}

/* ── Country → Currency map ── */
const COUNTRY_CURRENCIES = [
    'PH'=>'PHP','US'=>'USD','GB'=>'GBP','AU'=>'AUD','CA'=>'CAD','JP'=>'JPY','KR'=>'KRW','SG'=>'SGD',
    'HK'=>'HKD','MY'=>'MYR','TH'=>'THB','ID'=>'IDR','IN'=>'INR','CN'=>'CNY','NZ'=>'NZD','AE'=>'AED',
    'SA'=>'SAR','DE'=>'EUR','FR'=>'EUR','IT'=>'EUR','ES'=>'EUR','NL'=>'EUR','IE'=>'EUR','PT'=>'EUR',
    'AT'=>'EUR','BE'=>'EUR','FI'=>'EUR','GR'=>'EUR','LU'=>'EUR','MT'=>'EUR','CY'=>'EUR','EE'=>'EUR',
    'LV'=>'EUR','LT'=>'EUR','SK'=>'EUR','SI'=>'EUR','HR'=>'EUR','BG'=>'BGN','RO'=>'RON','PL'=>'PLN',
    'CZ'=>'CZK','HU'=>'HUF','SE'=>'SEK','DK'=>'DKK','NO'=>'NOK','CH'=>'CHF','BR'=>'BRL','MX'=>'MXN',
    'ZA'=>'ZAR','NG'=>'NGN','EG'=>'EGP','KE'=>'KES','GH'=>'GHS','TW'=>'TWD','VN'=>'VND','PK'=>'PKR',
    'BD'=>'BDT','LK'=>'LKR','NP'=>'NPR','QA'=>'QAR','KW'=>'KWD','BH'=>'BHD','OM'=>'OMR','JO'=>'JOD',
    'IL'=>'ILS','TR'=>'TRY','RU'=>'RUB','UA'=>'UAH','CL'=>'CLP','CO'=>'COP','PE'=>'PEN','AR'=>'ARS',
];

/* ══════════════════════════════════════════════════════════════
   AJAX HANDLERS
   ══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_POST['action'] ?? '');

    /* ── post_job ── */
    if ($action === 'post_job') {
        $title = trim((string)($_POST['title'] ?? ''));
        if (!$title) { echo json_encode(['ok' => false, 'msg' => 'Title required']); exit; }
        if (!$employerId) { echo json_encode(['ok' => false, 'msg' => 'Recruiter not linked to employer']); exit; }
        $desc   = trim((string)($_POST['description'] ?? ''));
        $req    = trim((string)($_POST['requirements'] ?? ''));
        $loc    = trim((string)($_POST['location'] ?? ''));
        $type   = (string)($_POST['job_type'] ?? 'Full-time');
        $setup  = (string)($_POST['setup'] ?? 'On-site');
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? (float)$_POST['salary_min'] : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? (float)$_POST['salary_max'] : null;
        $ind    = trim((string)($_POST['industry'] ?? ''));
        $exp    = (string)($_POST['experience_level'] ?? '') ?: null;
        $skills = trim((string)($_POST['skills'] ?? ''));
        $dl     = (string)($_POST['deadline'] ?? '') ?: null;
        $country  = trim((string)($_POST['country'] ?? ''));
        $duration = trim((string)($_POST['recruitment_duration'] ?? '')) ?: null;
        $currency = $country ? (COUNTRY_CURRENCIES[$country] ?? 'PHP') : 'PHP';
        try {
            $db->prepare("INSERT INTO jobs (employer_id, recruiter_id, title, description, requirements, location, job_type, setup, salary_min, salary_max, salary_currency, industry, experience_level, skills_required, status, approval_status, deadline, country, recruitment_duration) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Active','pending',?,?,?)")
               ->execute([$employerId, $uid, $title, $desc, $req, $loc, $type, $setup, $sMin, $sMax, $currency, $ind, $exp, $skills, $dl, $country, $duration]);
            echo json_encode(['ok' => true, 'job_id' => (int)$db->lastInsertId(), 'title' => $title]);
        } catch (Exception $e) {
            error_log('[AntCareers] post_job: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── save_draft ── */
    if ($action === 'save_draft') {
        $title = trim((string)($_POST['title'] ?? ''));
        if (!$title) { echo json_encode(['ok' => false, 'msg' => 'Title required']); exit; }
        if (!$employerId) { echo json_encode(['ok' => false, 'msg' => 'Recruiter not linked to employer']); exit; }
        $desc   = trim((string)($_POST['description'] ?? ''));
        $req    = trim((string)($_POST['requirements'] ?? ''));
        $loc    = trim((string)($_POST['location'] ?? ''));
        $type   = (string)($_POST['job_type'] ?? 'Full-time');
        $setup  = (string)($_POST['setup'] ?? 'On-site');
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? (float)$_POST['salary_min'] : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? (float)$_POST['salary_max'] : null;
        $ind    = trim((string)($_POST['industry'] ?? ''));
        $exp    = (string)($_POST['experience_level'] ?? '') ?: null;
        $skills = trim((string)($_POST['skills'] ?? ''));
        $dl     = (string)($_POST['deadline'] ?? '') ?: null;
        $country  = trim((string)($_POST['country'] ?? ''));
        $duration = trim((string)($_POST['recruitment_duration'] ?? '')) ?: null;
        $currency = $country ? (COUNTRY_CURRENCIES[$country] ?? 'PHP') : 'PHP';
        try {
            $db->prepare("INSERT INTO jobs (employer_id, recruiter_id, title, description, requirements, location, job_type, setup, salary_min, salary_max, salary_currency, industry, experience_level, skills_required, status, approval_status, deadline, country, recruitment_duration) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft','pending',?,?,?)")
               ->execute([$employerId, $uid, $title, $desc, $req, $loc, $type, $setup, $sMin, $sMax, $currency, $ind, $exp, $skills, $dl, $country, $duration]);
            echo json_encode(['ok' => true, 'job_id' => (int)$db->lastInsertId(), 'title' => $title]);
        } catch (Exception $e) {
            error_log('[AntCareers] save_draft: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── update_job ── */
    if ($action === 'update_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        if (!$title) { echo json_encode(['ok' => false, 'msg' => 'Title required']); exit; }
        $desc   = trim((string)($_POST['description'] ?? ''));
        $req    = trim((string)($_POST['requirements'] ?? ''));
        $loc    = trim((string)($_POST['location'] ?? ''));
        $type   = (string)($_POST['job_type'] ?? 'Full-time');
        $setup  = (string)($_POST['setup'] ?? 'On-site');
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? (float)$_POST['salary_min'] : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? (float)$_POST['salary_max'] : null;
        $ind    = trim((string)($_POST['industry'] ?? ''));
        $exp    = (string)($_POST['experience_level'] ?? '') ?: null;
        $skills = trim((string)($_POST['skills'] ?? ''));
        $dl     = (string)($_POST['deadline'] ?? '') ?: null;
        $country  = trim((string)($_POST['country'] ?? ''));
        $duration = trim((string)($_POST['recruitment_duration'] ?? '')) ?: null;
        $currency = $country ? (COUNTRY_CURRENCIES[$country] ?? 'PHP') : 'PHP';
        try {
            $db->prepare("UPDATE jobs SET title=?, description=?, requirements=?, location=?, job_type=?, setup=?, salary_min=?, salary_max=?, salary_currency=?, industry=?, experience_level=?, skills_required=?, deadline=?, country=?, recruitment_duration=?, updated_at=NOW() WHERE id=? AND recruiter_id=?")
               ->execute([$title, $desc, $req, $loc, $type, $setup, $sMin, $sMax, $currency, $ind, $exp, $skills, $dl, $country, $duration, $jobId, $uid]);
            echo json_encode(['ok' => true, 'title' => $title]);
        } catch (Exception $e) {
            error_log('[AntCareers] update_job: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── delete_job (drafts only) ── */
    if ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $st = $db->prepare("DELETE FROM jobs WHERE id=? AND recruiter_id=? AND status='Draft'");
            $st->execute([$jobId, $uid]);
            if ($st->rowCount() === 0) {
                echo json_encode(['ok' => false, 'msg' => 'Only draft jobs can be deleted']);
            } else {
                echo json_encode(['ok' => true]);
            }
        } catch (Exception $e) {
            error_log('[AntCareers] delete_job: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── post_draft (publish a draft → Active + pending approval) ── */
    if ($action === 'post_draft') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $st = $db->prepare("UPDATE jobs SET status='Active', approval_status='pending', updated_at=NOW() WHERE id=? AND recruiter_id=? AND status='Draft'");
            $st->execute([$jobId, $uid]);
            if ($st->rowCount() === 0) {
                echo json_encode(['ok' => false, 'msg' => 'Only draft jobs can be posted']);
            } else {
                echo json_encode(['ok' => true]);
            }
        } catch (Exception $e) {
            error_log('[AntCareers] post_draft: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── toggle_status (approved jobs only) ── */
    if ($action === 'toggle_status') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $row = $db->prepare("SELECT status, approval_status FROM jobs WHERE id=? AND recruiter_id=?");
            $row->execute([$jobId, $uid]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            if (!$r) { echo json_encode(['ok' => false, 'msg' => 'Not found']); exit; }
            if ($r['approval_status'] !== 'approved') {
                echo json_encode(['ok' => false, 'msg' => 'Only approved jobs can be toggled']);
                exit;
            }
            $new = $r['status'] === 'Active' ? 'Closed' : 'Active';
            $db->prepare("UPDATE jobs SET status=?, updated_at=NOW() WHERE id=? AND recruiter_id=?")->execute([$new, $jobId, $uid]);
            echo json_encode(['ok' => true, 'status' => $new]);
        } catch (Exception $e) {
            error_log('[AntCareers] toggle_status: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── get_job ── */
    if ($action === 'get_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $st = $db->prepare("SELECT * FROM jobs WHERE id=? AND recruiter_id=?");
            $st->execute([$jobId, $uid]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { echo json_encode(['ok' => false, 'msg' => 'Not found']); exit; }
            echo json_encode(['ok' => true, 'job' => $r]);
        } catch (Exception $e) {
            error_log('[AntCareers] get_job: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    exit;
}

/* ══════════════════════════════════════════════════════════════
   FETCH JOBS & COUNTS
   ══════════════════════════════════════════════════════════════ */
$jobs   = [];
$counts = ['total' => 0, 'Active' => 0, 'pending' => 0, 'Draft' => 0];
$dbErr  = false;

try {
    /* Status counts */
    $sc = $db->prepare("SELECT status, COUNT(*) AS c FROM jobs WHERE recruiter_id=? GROUP BY status");
    $sc->execute([$uid]);
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['c'];
        $counts['total'] += (int)$r['c'];
    }

    /* Pending approval count */
    $pc = $db->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=? AND approval_status='pending'");
    $pc->execute([$uid]);
    $counts['pending'] = (int)$pc->fetchColumn();

    /* All jobs with app count */
    $st = $db->prepare("
        SELECT j.*, COUNT(a.id) AS app_count
        FROM jobs j
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.recruiter_id = ?
        GROUP BY j.id
        ORDER BY j.created_at DESC
    ");
    $st->execute([$uid]);
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbErr = true;
    error_log('[AntCareers] recruiter_jobs fetch: ' . $e->getMessage());
}

$jobsJson = json_encode($jobs ?: []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>AntCareers — My Jobs</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    :root{--red-deep:#7A1515;--red-mid:#B83525;--red-vivid:#D13D2C;--red-bright:#E85540;--red-pale:#F07060;--soil-dark:#0A0909;--soil-med:#131010;--soil-card:#1C1818;--soil-hover:#252020;--soil-line:#352E2E;--text-light:#F5F0EE;--text-mid:#D0BCBA;--text-muted:#927C7A;--amber:#D4943A;--amber-dim:#251C0E;--green:#4CAF70;--font-display:'Playfair Display',Georgia,serif;--font-body:'Plus Jakarta Sans',system-ui,sans-serif;}
    html{overflow-x:hidden;}
    body{font-family:var(--font-body);background:var(--soil-dark);color:var(--text-light);overflow-x:hidden;min-height:100vh;-webkit-font-smoothing:antialiased;}

    /* Glow orbs */
    .glow-orb{position:fixed;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
    .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%);top:-100px;left:-150px;animation:orb1 18s ease-in-out infinite alternate;}
    .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%);bottom:0;right:-80px;animation:orb2 24s ease-in-out infinite alternate;}
    @keyframes orb1{to{transform:translate(60px,80px) scale(1.1);}}
    @keyframes orb2{to{transform:translate(-40px,-50px) scale(1.1);}}

    /* ── Light theme ── */
    body.light{background:#FAF7F5;color:#1A0A09;--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A;}
    body.light .glow-orb{opacity:0.04;}

    /* ── Page shell ── */
    .page-shell{max-width:1380px;margin:0 auto;padding:28px 24px 60px;position:relative;z-index:1;}
    .ph{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;}
    .page-title{font-family:var(--font-display);font-size:28px;font-weight:700;color:#F5F0EE;margin-bottom:4px;}
    .page-title span{color:var(--red-bright);font-style:italic;}
    .page-sub{font-size:14px;color:var(--text-muted);}
    body.light .page-title{color:#1A0A09;}

    .db-warn{background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.3);border-radius:8px;padding:10px 16px;font-size:13px;color:var(--amber);margin-bottom:16px;display:flex;align-items:center;gap:8px;}

    /* ── Stats row ── */
    .stats-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;}
    .stat-pill{display:flex;align-items:center;gap:8px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:10px 15px;cursor:pointer;transition:all 0.18s;text-decoration:none;}
    .stat-pill:hover,.stat-pill.active{border-color:rgba(209,61,44,.45);background:rgba(209,61,44,.07);}
    .sp-icon{font-size:13px;width:17px;text-align:center;}
    .sp-label{font-size:12px;font-weight:600;color:var(--text-muted);}
    .sp-count{font-size:17px;font-weight:800;color:#F5F0EE;font-family:var(--font-display);}
    body.light .stat-pill{background:#fff;border-color:#E0CECA;}
    body.light .sp-count{color:#1A0A09;}

    /* ── Toolbar ── */
    .toolbar{display:flex;align-items:center;gap:10px;margin-bottom:18px;flex-wrap:wrap;}
    .toolbar-search{flex:1;min-width:200px;position:relative;}
    .toolbar-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .toolbar-search input{width:100%;padding:9px 13px 9px 34px;border-radius:7px;background:var(--soil-card);border:1px solid var(--soil-line);color:var(--text-light);font-family:var(--font-body);font-size:13px;outline:none;transition:.2s;}
    .toolbar-search input:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .toolbar-search input{background:#fff;border-color:#E0CECA;color:#1A0A09;}
    .toolbar-select{padding:9px 13px;border-radius:7px;background:var(--soil-card);border:1px solid var(--soil-line);color:var(--text-light);font-family:var(--font-body);font-size:13px;outline:none;cursor:pointer;transition:.2s;}
    .toolbar-select:focus{border-color:var(--red-vivid);}
    body.light .toolbar-select{background:#fff;border-color:#E0CECA;color:#1A0A09;}
    body.light .toolbar-select option{background:#fff;color:#1A0A09;}

    /* ── Job list ── */
    .job-list{display:flex;flex-direction:column;gap:10px;}
    .job-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:18px 20px;display:flex;align-items:start;gap:14px;transition:all 0.2s;position:relative;}
    .job-card:hover{border-color:rgba(209,61,44,.4);box-shadow:0 6px 24px rgba(0,0,0,.25);}
    .job-card.hidden{display:none;}
    body.light .job-card{background:#fff;border-color:#E0CECA;}
    .job-icon{width:42px;height:42px;border-radius:10px;background:rgba(209,61,44,.1);border:1px solid rgba(209,61,44,.2);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--red-bright);flex-shrink:0;}
    .job-body{flex:1;min-width:0;}
    .job-title{font-family:var(--font-display);font-size:16px;font-weight:700;color:#F5F0EE;margin-bottom:4px;}
    body.light .job-title{color:#1A0A09;}
    .job-meta{display:flex;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:var(--text-muted);margin-bottom:9px;}
    .job-meta i{font-size:10px;color:var(--red-bright);}
    .chips{display:flex;gap:5px;flex-wrap:wrap;}
    .chip{font-size:11px;font-weight:500;padding:3px 8px;border-radius:4px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);}
    body.light .chip{background:#F5EDEB;border-color:#D4B0AB;color:#6A4A4A;}

    /* Status badges */
    .sbadge{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;letter-spacing:.04em;}
    .sbadge.active{color:#6ccf8a;background:rgba(76,175,112,.1);border:1px solid rgba(76,175,112,.2);}
    .sbadge.closed{color:#ff8080;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);}
    .sbadge.draft{color:var(--amber);background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.2);}

    /* Approval badges */
    .abadge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;letter-spacing:.04em;text-transform:uppercase;}
    .abadge.pending{color:var(--amber);background:rgba(212,148,58,.1);border:1px solid rgba(212,148,58,.2);}
    .abadge.approved{color:#6ccf8a;background:rgba(76,175,112,.1);border:1px solid rgba(76,175,112,.2);}
    .abadge.rejected{color:#ff8080;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);}

    .job-right{display:flex;flex-direction:column;align-items:flex-end;gap:9px;flex-shrink:0;}
    .app-count{font-family:var(--font-display);font-size:22px;font-weight:700;color:#F5F0EE;text-align:right;}
    .app-count-lbl{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;}
    body.light .app-count{color:#1A0A09;}
    .job-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;}
    @media(max-width:620px){.job-right{display:none;}}

    /* ── Buttons ── */
    .btn{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:.18s;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);white-space:nowrap;}
    .btn:hover{background:var(--soil-hover);color:#F5F0EE;}
    .btn.primary{background:var(--red-vivid);border-color:var(--red-vivid);color:#fff;}
    .btn.primary:hover{background:var(--red-bright);}
    .btn.grn{border-color:rgba(76,175,112,.4);color:#6ccf8a;}
    .btn.grn:hover{background:rgba(76,175,112,.1);}
    .btn.red{border-color:rgba(220,53,69,.4);color:#ff8080;}
    .btn.red:hover{background:rgba(220,53,69,.1);}
    .btn.amber{border-color:rgba(212,148,58,.4);color:var(--amber);}
    .btn.amber:hover{background:rgba(212,148,58,.1);}
    body.light .btn{border-color:#D4B0AB;color:#5A4040;}
    body.light .btn.primary{background:var(--red-vivid);border-color:var(--red-vivid);color:#fff;}
    body.light .btn.primary:hover{background:var(--red-bright);}

    /* ── Empty state ── */
    .empty{text-align:center;padding:55px 20px;color:var(--text-muted);}
    .empty i{font-size:42px;margin-bottom:12px;color:var(--soil-line);display:block;}

    /* ── Modal ── */
    .modal-bd{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:600;display:none;align-items:flex-start;justify-content:center;padding:30px 16px;overflow-y:auto;}
    .modal-bd.open{display:flex;}
    .modal-box{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:16px;padding:26px;width:100%;max-width:620px;position:relative;margin:auto;}
    body.light .modal-box{background:#fff;border-color:#E0CECA;}
    .modal-title{font-family:var(--font-display);font-size:21px;font-weight:700;color:#F5F0EE;margin-bottom:20px;display:flex;align-items:center;gap:9px;}
    body.light .modal-title{color:#1A0A09;}
    .modal-close{position:absolute;top:14px;right:14px;width:28px;height:28px;border-radius:6px;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;}
    .modal-close:hover{background:var(--soil-hover);color:#F5F0EE;}
    .fg{margin-bottom:13px;}
    .fl{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;display:block;}
    .fi{width:100%;padding:9px 13px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:#F5F0EE;font-family:var(--font-body);font-size:13px;outline:none;transition:.2s;}
    .fi:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .fi{background:#F5EDEB;border-color:#D4B0AB;color:#1A0A09;}
    body.light .fi option{background:#F5EDEB;color:#1A0A09;}
    body.light .fl{color:#7A5555;}
    textarea.fi{resize:vertical;min-height:80px;}
    .frow{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    @media(max-width:480px){.frow{grid-template-columns:1fr!important;}}
    .mfoot{display:flex;justify-content:flex-end;gap:9px;margin-top:20px;flex-wrap:wrap;}

    /* ── Confirm modal ── */
    .confirm-box{background:var(--soil-card);border:1px solid rgba(220,53,69,.3);border-radius:14px;padding:28px;width:100%;max-width:380px;text-align:center;margin:auto;}
    body.light .confirm-box{background:#fff;}
    .confirm-icon{font-size:38px;color:#ff8080;margin-bottom:12px;}
    .confirm-title{font-family:var(--font-display);font-size:18px;color:#F5F0EE;margin-bottom:7px;}
    body.light .confirm-title{color:#1A0A09;}
    .confirm-sub{font-size:13px;color:var(--text-muted);margin-bottom:18px;}
    .confirm-actions{display:flex;gap:10px;justify-content:center;}

    /* ── Toast ── */
    .toast{position:fixed;bottom:22px;right:22px;background:var(--soil-card);border:1px solid var(--soil-line);border-left:4px solid var(--red-vivid);border-radius:8px;padding:11px 16px;font-size:13px;font-weight:600;color:#F5F0EE;z-index:900;transform:translateY(80px);opacity:0;transition:all .35s ease;max-width:300px;box-shadow:0 8px 32px rgba(0,0,0,.4);pointer-events:none;}
    .toast.show{transform:translateY(0);opacity:1;}
    .toast.ok{border-left-color:#4CAF70;}
    .toast.err{border-left-color:#E05555;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}
  </style>
</head>
<body>
<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<div class="page-shell anim">
  <div class="ph">
    <div>
      <h1 class="page-title">My <span>Jobs</span></h1>
      <p class="page-sub">Post, edit, and manage your job listings. Jobs require admin approval before going live.</p>
    </div>
    <button class="btn primary" onclick="openPost()" style="padding:9px 18px;font-size:13px;"><i class="fas fa-plus-circle"></i> Post New Job</button>
  </div>

  <?php if ($dbErr): ?>
  <div class="db-warn"><i class="fas fa-exclamation-triangle"></i> Could not load job data — please check your database connection.</div>
  <?php endif; ?>

  <!-- Stats Row -->
  <div class="stats-row">
    <div class="stat-pill active" data-filter="all" onclick="filterJobs('all',this)">
      <i class="fas fa-briefcase sp-icon" style="color:var(--red-pale)"></i>
      <span class="sp-label">Total Jobs</span>
      <span class="sp-count" id="cntTotal"><?= $counts['total'] ?></span>
    </div>
    <div class="stat-pill" data-filter="active" onclick="filterJobs('active',this)">
      <i class="fas fa-check-circle sp-icon" style="color:#6ccf8a"></i>
      <span class="sp-label">Active</span>
      <span class="sp-count" id="cntActive"><?= $counts['Active'] ?></span>
    </div>
    <div class="stat-pill" data-filter="pending" onclick="filterJobs('pending',this)">
      <i class="fas fa-clock sp-icon" style="color:var(--amber)"></i>
      <span class="sp-label">Pending Approval</span>
      <span class="sp-count" id="cntPending"><?= $counts['pending'] ?></span>
    </div>
    <div class="stat-pill" data-filter="draft" onclick="filterJobs('draft',this)">
      <i class="fas fa-pencil-alt sp-icon" style="color:var(--text-muted)"></i>
      <span class="sp-label">Drafts</span>
      <span class="sp-count" id="cntDraft"><?= $counts['Draft'] ?></span>
    </div>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <div class="toolbar-search">
      <i class="fas fa-search"></i>
      <input type="text" id="searchInput" placeholder="Search jobs by title, location, skills…" oninput="applyFilters()">
    </div>
    <select class="toolbar-select" id="statusFilter" onchange="applyFilters()">
      <option value="all">All Statuses</option>
      <option value="active">Active</option>
      <option value="closed">Closed</option>
      <option value="draft">Draft</option>
      <option value="pending">Pending Approval</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>

  <!-- Job list -->
  <div class="job-list" id="jobList">
  <?php if (empty($jobs)): ?>
    <div class="empty" id="emptyState"><i class="fas fa-briefcase"></i><p>No jobs found. <a href="#" onclick="openPost();return false;" style="color:var(--red-pale)">Post your first job →</a></p></div>
  <?php else: foreach ($jobs as $j):
    $sc  = strtolower($j['status']);
    $asc = strtolower($j['approval_status'] ?? 'pending');
    $sal  = '';
    if ($j['salary_min'] || $j['salary_max']) {
        $cur = $j['salary_currency'] ?? 'PHP';
        $mn  = $j['salary_min'] ? number_format((float)$j['salary_min']) : '';
        $mx  = $j['salary_max'] ? number_format((float)$j['salary_max']) : '';
        $sal  = $mn && $mx ? "{$cur} {$mn}–{$mx}" : ($mn ? "{$cur} {$mn}+" : "{$cur} up to {$mx}");
    }
    $pd = date('M j, Y', strtotime($j['created_at']));
  ?>
  <div class="job-card"
       id="jc-<?= $j['id'] ?>"
       data-title="<?= htmlspecialchars(strtolower($j['title']), ENT_QUOTES) ?>"
       data-location="<?= htmlspecialchars(strtolower($j['location'] ?? ''), ENT_QUOTES) ?>"
       data-skills="<?= htmlspecialchars(strtolower($j['skills_required'] ?? ''), ENT_QUOTES) ?>"
       data-status="<?= $sc ?>"
       data-approval="<?= $asc ?>">
    <div class="job-icon"><i class="fas fa-briefcase"></i></div>
    <div class="job-body">
      <div class="job-title"><?= htmlspecialchars($j['title']) ?></div>
      <div class="job-meta">
        <?php if ($j['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($j['location']) ?></span><?php endif; ?>
        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($j['job_type']) ?></span>
        <span><i class="fas fa-laptop-house"></i> <?= htmlspecialchars($j['setup']) ?></span>
        <?php if ($sal): ?><span><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($sal) ?></span><?php endif; ?>
        <span><i class="fas fa-calendar-alt"></i> Posted <?= $pd ?></span>
      </div>
      <div class="chips">
        <span class="sbadge <?= $sc ?>"><?= htmlspecialchars($j['status']) ?></span>
        <span class="abadge <?= $asc ?>"><i class="fas fa-<?= $asc === 'approved' ? 'check' : ($asc === 'rejected' ? 'times' : 'clock') ?>"></i> <?= ucfirst($asc) ?></span>
        <?php if ($j['experience_level']): ?><span class="chip"><?= htmlspecialchars($j['experience_level']) ?></span><?php endif; ?>
        <?php foreach (array_slice(explode(',', $j['skills_required'] ?? ''), 0, 3) as $sk): if (trim($sk)): ?><span class="chip"><?= htmlspecialchars(trim($sk)) ?></span><?php endif; endforeach; ?>
      </div>
    </div>
    <div class="job-right">
      <div>
        <div class="app-count"><?= (int)$j['app_count'] ?></div>
        <div class="app-count-lbl">Applicants</div>
      </div>
      <div class="job-actions">
        <button class="btn" onclick="editJob(<?= $j['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
        <?php if ($asc === 'approved'): ?>
        <button class="btn <?= $j['status'] === 'Active' ? 'red' : 'grn' ?>" onclick="toggleStatus(<?= $j['id'] ?>,'<?= $j['status'] ?>')" id="tbtn-<?= $j['id'] ?>">
          <?= $j['status'] === 'Active' ? '<i class="fas fa-lock"></i> Close' : '<i class="fas fa-lock-open"></i> Open' ?>
        </button>
        <?php endif; ?>
        <?php if ($sc === 'draft'): ?>
        <button class="btn grn" onclick="postDraft(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-paper-plane"></i> Post</button>
        <button class="btn red" onclick="confirmDel(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
  </div>

  <div class="empty" id="noResults" style="display:none;"><i class="fas fa-search"></i><p>No jobs match your search or filter.</p></div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Post / Edit Job Modal
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-bd" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeJobModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="mTitle"><i class="fas fa-plus-circle" style="color:var(--red-bright)"></i> Post New Job</div>
    <input type="hidden" id="eJobId">

    <div class="fg">
      <label class="fl">Industry *</label>
      <select class="fi" id="fInd" onchange="updateJobTitleOptions()"><option value="">— Select Industry —</option><option>Accounting</option><option>Administration &amp; Office Support</option><option>Advertising, Arts &amp; Media</option><option>Banking &amp; Financial Services</option><option>Call Centre &amp; Customer Service</option><option>CEO &amp; General Management</option><option>Community Services &amp; Development</option><option>Construction</option><option>Consulting &amp; Strategy</option><option>Design &amp; Architecture</option><option>Education &amp; Training</option><option>Engineering</option><option>Farming, Animals &amp; Conservation</option><option>Government &amp; Defence</option><option>Healthcare &amp; Medical</option><option>Hospitality &amp; Tourism</option><option>Human Resources &amp; Recruitment</option><option>Information &amp; Communication Technology</option><option>Insurance &amp; Superannuation</option><option>Legal</option><option>Manufacturing, Transport &amp; Logistics</option><option>Marketing &amp; Communications</option><option>Mining, Resources &amp; Energy</option><option>Real Estate &amp; Property</option><option>Retail &amp; Consumer Products</option><option>Sales</option><option>Science &amp; Technology</option><option>Self Employment</option><option>Sports &amp; Recreation</option><option>Trades &amp; Services</option></select>
    </div>

    <div class="fg">
      <label class="fl">Job Title *</label>
      <select class="fi" id="fTitle" onchange="toggleCustomTitle()"><option value="">— Select industry first —</option></select>
      <input type="text" class="fi" id="fTitleCustom" placeholder="Enter custom job title…" style="display:none;margin-top:7px;">
    </div>

    <div class="frow">
      <div class="fg">
        <label class="fl">Job Type</label>
        <select class="fi" id="fType">
          <option>Full-time</option>
          <option>Part-time</option>
          <option>Contract</option>
          <option>Freelance</option>
          <option>Internship</option>
        </select>
      </div>
      <div class="fg">
        <label class="fl">Setup</label>
        <select class="fi" id="fSetup">
          <option>On-site</option>
          <option>Remote</option>
          <option>Hybrid</option>
        </select>
      </div>
    </div>

    <div class="frow" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="fg">
        <label class="fl">Country</label>
        <select class="fi" id="fCountry" onchange="onCountryChange()">
          <option value="">— Select Country —</option>
          <?php foreach (getCountries() as $c): ?>
          <option value="<?= htmlspecialchars($c['code']) ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label class="fl">Location</label>
        <input type="text" class="fi" id="fLoc" placeholder="City, Province…">
      </div>
      <div class="fg">
        <label class="fl">Experience Level</label>
        <select class="fi" id="fExp">
          <option value="">— Any —</option>
          <option>Entry</option>
          <option>Junior</option>
          <option>Mid</option>
          <option>Senior</option>
          <option>Lead</option>
          <option>Executive</option>
        </select>
      </div>
    </div>



    <div class="frow">
      <div class="fg">
        <label class="fl" id="lblSMin">Min Salary (PHP)</label>
        <input type="number" class="fi" id="fSMin" placeholder="e.g. 50000">
      </div>
      <div class="fg">
        <label class="fl" id="lblSMax">Max Salary (PHP)</label>
        <input type="number" class="fi" id="fSMax" placeholder="e.g. 90000">
      </div>
    </div>

    <div class="frow">
      <div class="fg">
        <label class="fl">Application Deadline</label>
        <input type="date" class="fi" id="fDl">
      </div>
    </div>

    <div class="fg">
      <label class="fl">Required Skills (comma-separated)</label>
      <input type="text" class="fi" id="fSkills" placeholder="React, TypeScript, MySQL">
    </div>

    <div class="fg">
      <label class="fl">Job Description</label>
      <textarea class="fi" id="fDesc" rows="4" placeholder="What will this role involve?"></textarea>
    </div>

    <div class="fg">
      <label class="fl">Requirements</label>
      <textarea class="fi" id="fReq" rows="3" placeholder="Qualifications, experience…"></textarea>
    </div>

    <div class="mfoot" id="mFootPost">
      <button class="btn" onclick="closeJobModal()">Cancel</button>
      <button class="btn amber" onclick="submitDraft()"><i class="fas fa-pencil-alt"></i> Save as Draft</button>
      <button class="btn primary" onclick="submitJob()"><i class="fas fa-paper-plane"></i> Submit for Approval</button>
    </div>
    <div class="mfoot" id="mFootEdit" style="display:none;">
      <button class="btn" onclick="closeJobModal()">Cancel</button>
      <button class="btn primary" onclick="submitUpdate()"><i class="fas fa-save"></i> Update Job</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Confirm Delete Modal
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-bd" id="confirmModal">
  <div class="confirm-box">
    <div class="confirm-icon"><i class="fas fa-trash-alt"></i></div>
    <div class="confirm-title">Delete this draft?</div>
    <div class="confirm-sub" id="confirmMsg">This cannot be undone.</div>
    <input type="hidden" id="delJobId">
    <div class="confirm-actions">
      <button class="btn" onclick="document.getElementById('confirmModal').classList.remove('open')">Cancel</button>
      <button class="btn red" onclick="doDelete()"><i class="fas fa-trash"></i> Delete</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* ══════════════════════════════════════════════════════════════
   Client-Side Filtering
   ══════════════════════════════════════════════════════════════ */
/* Country → Currency map */
const COUNTRY_CURRENCY = {
  'PH':'PHP','US':'USD','GB':'GBP','AU':'AUD','CA':'CAD','JP':'JPY','KR':'KRW','SG':'SGD',
  'HK':'HKD','MY':'MYR','TH':'THB','ID':'IDR','IN':'INR','CN':'CNY','NZ':'NZD','AE':'AED',
  'SA':'SAR','DE':'EUR','FR':'EUR','IT':'EUR','ES':'EUR','NL':'EUR','IE':'EUR','PT':'EUR',
  'AT':'EUR','BE':'EUR','FI':'EUR','GR':'EUR','LU':'EUR','MT':'EUR','CY':'EUR','EE':'EUR',
  'LV':'EUR','LT':'EUR','SK':'EUR','SI':'EUR','HR':'EUR','BG':'BGN','RO':'RON','PL':'PLN',
  'CZ':'CZK','HU':'HUF','SE':'SEK','DK':'DKK','NO':'NOK','CH':'CHF','BR':'BRL','MX':'MXN',
  'ZA':'ZAR','NG':'NGN','EG':'EGP','KE':'KES','GH':'GHS','TW':'TWD','VN':'VND','PK':'PKR',
  'BD':'BDT','LK':'LKR','NP':'NPR','QA':'QAR','KW':'KWD','BH':'BHD','OM':'OMR','JO':'JOD',
  'IL':'ILS','TR':'TRY','RU':'RUB','UA':'UAH','CL':'CLP','CO':'COP','PE':'PEN','AR':'ARS'
};
function onCountryChange() {
  var code = document.getElementById('fCountry').value;
  var cur = COUNTRY_CURRENCY[code] || 'PHP';
  document.getElementById('lblSMin').textContent = 'Min Salary (' + cur + ')';
  document.getElementById('lblSMax').textContent = 'Max Salary (' + cur + ')';
}

var currentFilter = 'all';

function filterJobs(type, el) {
  document.querySelectorAll('.stat-pill').forEach(function(p){ p.classList.remove('active'); });
  if (el) el.classList.add('active');

  var sel = document.getElementById('statusFilter');
  if (type === 'all')     sel.value = 'all';
  else if (type === 'active')  sel.value = 'active';
  else if (type === 'pending') sel.value = 'pending';
  else if (type === 'draft')   sel.value = 'draft';

  currentFilter = type;
  applyFilters();
}

function applyFilters() {
  var search = document.getElementById('searchInput').value.trim().toLowerCase();
  var status = document.getElementById('statusFilter').value;
  var cards  = document.querySelectorAll('.job-card');
  var visible = 0;

  cards.forEach(function(c) {
    var cStatus   = c.getAttribute('data-status');
    var cApproval = c.getAttribute('data-approval');
    var cTitle    = c.getAttribute('data-title') || '';
    var cLoc      = c.getAttribute('data-location') || '';
    var cSkills   = c.getAttribute('data-skills') || '';

    var show = true;

    /* Status/approval filter */
    if (status !== 'all') {
      if (status === 'pending') {
        if (cApproval !== 'pending') show = false;
      } else if (status === 'rejected') {
        if (cApproval !== 'rejected') show = false;
      } else {
        if (cStatus !== status) show = false;
      }
    }

    /* Text search */
    if (show && search) {
      var haystack = cTitle + ' ' + cLoc + ' ' + cSkills;
      if (haystack.indexOf(search) === -1) show = false;
    }

    c.classList.toggle('hidden', !show);
    if (show) visible++;
  });

  var nr = document.getElementById('noResults');
  var es = document.getElementById('emptyState');
  if (nr) nr.style.display = (cards.length > 0 && visible === 0) ? '' : 'none';
  if (es && cards.length > 0) es.style.display = 'none';
}

/* ══════════════════════════════════════════════════════════════
   Industry → Job Title Dropdowns (matches employer form)
   ══════════════════════════════════════════════════════════════ */
const JOB_ROLES_POST={'Accounting':['Accounts Officers / Clerks','Accounts Payable','Accounts Receivable / Credit Control','Analysis & Reporting','Assistant Accountants','Audit - External','Audit - Internal','Bookkeeping & Small Practice Accounting','Business Services & Corporate Advisory','Company Secretaries','Compliance & Risk','Cost Accounting','Financial Accounting & Reporting','Financial Managers & Controllers','Forensic Accounting & Investigation','Insolvency & Corporate Recovery','Inventory & Fixed Assets','Management','Management Accounting & Budgeting','Payroll','Strategy & Planning','Systems Accounting & IT Audit','Taxation','Treasury','Other'],'Administration & Office Support':['Administrative Assistants','Client & Sales Administration','Contracts Administration','Data Entry & Word Processing','Office Management','PA, EA & Secretarial','Receptionists','Records Management & Document Control','Other'],'Advertising, Arts & Media':['Agency Account Management','Art Direction','Editing & Publishing','Event Management','Journalism & Writing','Management','Media Strategy, Planning & Buying','Other'],'Banking & Financial Services':['Account & Relationship Management','Analysis & Reporting','Banking - Business','Banking - Corporate & Institutional','Banking - Retail / Branch','Client Services','Compliance & Risk','Corporate Finance & Investment Banking','Credit','Financial Planning','Funds Management','Management','Mortgages','Settlements','Other'],'Call Centre & Customer Service':['Collections','Customer Service - Call Centre','Customer Service - Customer Facing','Management & Support','Sales - Inbound','Sales - Outbound','Supervisors / Team Leaders','Other'],'CEO & General Management':['Board Appointments','CEO','COO & MD','General / Business Unit Manager','Other'],'Community Services & Development':['Aged & Disability Support','Child Welfare, Youth & Family Services','Community Development','Employment Services','Fundraising','Housing & Homelessness Services','Indigenous & Multicultural Services','Management','Volunteer Coordination & Support','Other'],'Construction':['Contracts Management','Estimating','Foreperson / Supervisors','Health, Safety & Environment','Management','Planning & Scheduling','Plant & Machinery Operators','Project Management','Quality Assurance & Control','Surveying','Other'],'Consulting & Strategy':['Analysts','Corporate Development','Environment & Sustainability Consulting','Management & Change Consulting','Policy','Strategy & Planning','Other'],'Design & Architecture':['Architectural Drafting','Architecture','Fashion Design','Graphic Design','Interior Design','Landscape Architecture','Management','Product Design','Urban Design & Planning','Other'],'Education & Training':['Childcare & Outside School Hours Care','Library Services & Information Management','Management - Schools','Management - Universities','Management - Vocational','Research & Fellowships','Student Services','Teaching - Early Childhood','Teaching - Primary','Teaching - Secondary','Teaching - Tertiary','Teaching - Vocational','Teaching Aides & Special Needs','Tutoring','Workplace Training & Assessment','Other'],'Engineering':['Aerospace Engineering','Automotive Engineering','Building Services Engineering','Chemical Engineering','Civil/Structural Engineering','Electrical/Electronic Engineering','Engineering Drafting','Environmental Engineering','Field Engineering','Industrial Engineering','Maintenance','Management','Materials Handling Engineering','Mechanical Engineering','Process Engineering','Project Engineering','Project Management','Supervisors','Systems Engineering','Water & Waste Engineering','Other'],'Farming, Animals & Conservation':['Agronomy & Farm Services','Conservation, Parks & Wildlife','Farm Labour','Farm Management','Fishing & Aquaculture','Horticulture','Veterinary Services & Animal Welfare','Winery & Viticulture','Other'],'Government & Defence':['Air Force','Army','Emergency Services','Government - Federal','Government - Local','Government - State','Navy','Police & Corrections','Other'],'Healthcare & Medical':['Ambulance/Paramedics','Chiropractic & Osteopathic','Clinical/Medical Research','Dental','Dieticians','Environmental Services','General Practitioners','Management','Medical Administration','Medical Imaging','Medical Specialists','Natural Therapies & Alternative Medicine','Nursing - A&E, Critical Care & ICU','Nursing - Aged Care','Nursing - Community, Maternal & Child Health','Nursing - Educators & Facilitators','Nursing - General Medical & Surgical','Nursing - High Acuity','Nursing - Management','Nursing - Midwifery, Neo-Natal, SCN & NICU','Nursing - Paediatric & PICU','Nursing - Psych, Forensic & Correctional Health','Nursing - Theatre & Recovery','Optical','Pathology','Pharmaceuticals & Medical Devices','Pharmacy','Physiotherapy, OT & Rehabilitation','Psychology, Counselling & Social Work','Residents & Registrars','Sales','Speech Therapy','Other'],'Hospitality & Tourism':['Airlines','Bar & Beverage Staff','Chefs/Cooks','Front Office & Guest Services','Gaming','Housekeeping','Kitchen & Sandwich Hands','Management','Reservations','Tour Guides','Travel Agents/Consultants','Waiting Staff','Other'],'Human Resources & Recruitment':['Consulting & Generalist HR','Industrial & Employee Relations','Management - Agency','Management - Internal','Occupational Health & Safety','Organisational Development','Recruitment - Agency','Recruitment - Internal','Remuneration & Benefits','Training & Development','Other'],'Information & Communication Technology':['Architects','Computer Operators','Consultants','Database Development & Administration','Developers/Programmers','Engineering - Hardware','Engineering - Network','Engineering - Software','Help Desk & IT Support','Management','Networks & Systems Administration','Product Management & Development','Program & Project Management','Sales - Pre & Post','Security','Software Quality Assurance','System Services & Support','Systems Analysis & Modelling','Team Leaders','Technical Writing','Telecommunications','Testing & Quality Assurance','Other'],'Insurance & Superannuation':['Actuarial','Assessment','Brokerage','Claims','Management','Risk Management','Superannuation','Underwriting',"Workers' Compensation",'Other'],'Legal':['Banking & Financial Services Law','Construction Law','Corporate & Commercial Law','Criminal Law','Family Law','Generalists - In-house','Generalists - Law Firm','Industrial Relations & Employment Law','Insurance & Superannuation Law','Intellectual Property Law','Legal Secretaries','Litigation & Dispute Resolution','Management','Personal Injury Law','Property Law','Tax Law','Other'],'Manufacturing, Transport & Logistics':['Assembly & Process Work','Aviation Services','Couriers, Drivers & Postal Services','Fleet Management','Freight/Cargo Forwarding','Import/Export & Customs','Inventory & Stock Control','Machine Operators','Management','Methods & Quality Control','Operations','Production, Planning & Scheduling','Public Transport & Taxi Services','Purchasing, Procurement & Inventory','Rail Operations','Road Transport','Shipping','Warehouse, Storage & Distribution','Other'],'Marketing & Communications':['Brand Management','Digital & Search Marketing','Direct Marketing & CRM','Event Management','Internal Communications','Management','Market Research & Analysis','Marketing Assistants/Coordinators','Marketing Communications','Media Strategy, Planning & Buying','Product Management & Development','Public Relations & Corporate Affairs','Trade Marketing','Other'],'Mining, Resources & Energy':['Analysis & Reporting','Corporate Services','Engineering','Health, Safety & Environment','Management','Natural Resources & Water','Oil & Gas - Drilling','Oil & Gas - Exploration & Geoscience','Oil & Gas - Operations','Oil & Gas - Production & Refinement','Operations','Power Generation & Distribution','Project Management','Renewable Energy','Surveying','Other'],'Real Estate & Property':['Administration','Body Corporate & Facilities Management','Commercial Sales, Leasing & Property Mgmt','Management','Residential Leasing & Property Management','Residential Sales','Retail & Shopping Centre Management','Valuation','Other'],'Retail & Consumer Products':['Merchandisers','Management - Area/Multi-site','Management - Department/Assistant','Management - Store','Planning','Purchasing, Procurement & Inventory','Retail Assistants','Sales Representatives/Consultants','Visual Merchandising','Other'],'Sales':['Account & Relationship Management','Analysis & Reporting','Management','New Business Development','Sales Representatives/Consultants','Other'],'Science & Technology':['Biological & Biomedical Sciences','Biotechnology','Chemistry','Environmental, Earth & Geosciences','Food Technology & Safety','Laboratory & Technical Services','Materials Sciences','Mathematics, Statistics & Information Sciences','Modelling & Simulation','Physics','Other'],'Self Employment':['Self Employment'],'Sports & Recreation':['Coaching & Instruction','Fitness & Personal Training','Management','Other'],'Trades & Services':['Automotive Trades','Bakers & Pastry Cooks','Building Trades','Butchers','Caretakers & Handypersons','Cleaning Services','Electricians','Floristry','Gardening & Landscaping','Hair & Beauty Services','Labourers','Locksmiths','Maintenance & Handypersons','Management','Nannies & Babysitters','Painters & Sign Writers','Plumbers','Printing & Publishing Services','Security Services','Tailors & Dressmakers','Technicians','Upholstery & Textile Trades','Other']};

function updateJobTitleOptions(){var ind=document.getElementById('fInd').value;var sel=document.getElementById('fTitle');var prev=sel.value;sel.innerHTML='';var ci=document.getElementById('fTitleCustom');ci.style.display='none';ci.value='';var roles=JOB_ROLES_POST[ind]||[];if(!roles.length){sel.innerHTML='<option value="">\u2014 Select industry first \u2014</option>';return;}var opts='<option value="">\u2014 Select job title \u2014</option>';roles.forEach(function(r){opts+='<option value="'+r.replace(/"/g,'&quot;')+'">'+r.replace(/&/g,'&amp;')+'</option>';});sel.innerHTML=opts;if(prev&&roles.includes(prev)){sel.value=prev;toggleCustomTitle();}}
function toggleCustomTitle(){var v=document.getElementById('fTitle').value;var ci=document.getElementById('fTitleCustom');if(v==='Other'){ci.style.display='block';ci.focus();}else{ci.style.display='none';ci.value='';}}

/* ══════════════════════════════════════════════════════════════
   Post / Edit Modal
   ══════════════════════════════════════════════════════════════ */
function openPost() {
  clearForm();
  document.getElementById('eJobId').value = '';
  document.getElementById('mTitle').innerHTML = '<i class="fas fa-plus-circle" style="color:var(--red-bright)"></i> Post New Job';
  document.getElementById('mFootPost').style.display = '';
  document.getElementById('mFootEdit').style.display = 'none';
  document.getElementById('jobModal').classList.add('open');
}

function closeJobModal() {
  document.getElementById('jobModal').classList.remove('open');
}

function clearForm() {
  ['fLoc','fSMin','fSMax','fSkills','fDesc','fReq','fDl'].forEach(function(id){
    document.getElementById(id).value = '';
  });
  document.getElementById('fCountry').value = '';
  onCountryChange();
  document.getElementById('fInd').value   = '';
  document.getElementById('fTitle').innerHTML = '<option value="">\u2014 Select industry first \u2014</option>';
  document.getElementById('fTitleCustom').style.display = 'none';
  document.getElementById('fTitleCustom').value = '';
  document.getElementById('fType').value  = 'Full-time';
  document.getElementById('fSetup').value = 'On-site';
  document.getElementById('fExp').value   = '';
}

function getFormData() {
  var sel = document.getElementById('fTitle');
  var titleVal = (sel.value === 'Other' ? document.getElementById('fTitleCustom').value : sel.value).trim();
  return {
    title:            titleVal,
    description:      document.getElementById('fDesc').value,
    requirements:     document.getElementById('fReq').value,
    location:         document.getElementById('fLoc').value.trim(),
    country:          document.getElementById('fCountry').value,
    job_type:         document.getElementById('fType').value,
    setup:            document.getElementById('fSetup').value,
    salary_min:       document.getElementById('fSMin').value,
    salary_max:       document.getElementById('fSMax').value,
    industry:         document.getElementById('fInd').value.trim(),
    experience_level: document.getElementById('fExp').value,
    skills:           document.getElementById('fSkills').value,
    deadline:         document.getElementById('fDl').value
  };
}

/* Submit for Approval (Active + pending) */
function submitJob() {
  var d = getFormData();
  if (!d.title) { toast('Job title required', 'err'); return; }
  d.action = 'post_job';
  doPost(d, function(r) {
    if (r.ok) {
      closeJobModal();
      toast('Job "' + r.title + '" submitted for approval!', 'ok');
      setTimeout(function(){ location.reload(); }, 1200);
    } else { toast(r.msg || 'Error', 'err'); }
  });
}

/* Save as Draft */
function submitDraft() {
  var d = getFormData();
  if (!d.title) { toast('Job title required', 'err'); return; }
  d.action = 'save_draft';
  doPost(d, function(r) {
    if (r.ok) {
      closeJobModal();
      toast('Draft "' + r.title + '" saved!', 'ok');
      setTimeout(function(){ location.reload(); }, 1200);
    } else { toast(r.msg || 'Error', 'err'); }
  });
}

/* Edit modal */
function editJob(id) {
  doPost({ action: 'get_job', job_id: id }, function(d) {
    if (!d.ok) { toast(d.msg || 'Error', 'err'); return; }
    var j = d.job;
    document.getElementById('eJobId').value        = j.id;
    document.getElementById('fDesc').value          = j.description || '';
    document.getElementById('fReq').value           = j.requirements || '';
    document.getElementById('fLoc').value           = j.location || '';
    document.getElementById('fCountry').value        = j.country || '';
    onCountryChange();
    document.getElementById('fType').value          = j.job_type || 'Full-time';
    document.getElementById('fSetup').value         = j.setup || 'On-site';
    document.getElementById('fSMin').value          = j.salary_min || '';
    document.getElementById('fSMax').value          = j.salary_max || '';
    document.getElementById('fInd').value           = j.industry || '';
    updateJobTitleOptions();
    /* set title — if not in dropdown, treat as 'Other' */
    var _titleSel = document.getElementById('fTitle');
    var _titleVal = j.title || '';
    var _opts = [].slice.call(_titleSel.options).map(function(o){return o.value;});
    if (_titleVal && !_opts.includes(_titleVal) && _titleVal !== '') {
      _titleSel.value = 'Other';
      var _ci = document.getElementById('fTitleCustom');
      _ci.style.display = 'block'; _ci.value = _titleVal;
    } else {
      _titleSel.value = _titleVal;
      toggleCustomTitle();
    }
    document.getElementById('fExp').value           = j.experience_level || '';
    document.getElementById('fSkills').value        = j.skills_required || '';
    document.getElementById('fDl').value            = j.deadline || '';
    document.getElementById('mTitle').innerHTML     = '<i class="fas fa-edit" style="color:var(--red-bright)"></i> Edit Job';
    document.getElementById('mFootPost').style.display = 'none';
    document.getElementById('mFootEdit').style.display = '';
    document.getElementById('jobModal').classList.add('open');
  });
}

/* Update existing job */
function submitUpdate() {
  var d = getFormData();
  if (!d.title) { toast('Job title required', 'err'); return; }
  d.action = 'update_job';
  d.job_id = document.getElementById('eJobId').value;
  doPost(d, function(r) {
    if (r.ok) {
      closeJobModal();
      toast('Job updated!', 'ok');
      setTimeout(function(){ location.reload(); }, 1200);
    } else { toast(r.msg || 'Error', 'err'); }
  });
}

/* ══════════════════════════════════════════════════════════════
   Toggle Status
   ══════════════════════════════════════════════════════════════ */
function toggleStatus(id, cur) {
  doPost({ action: 'toggle_status', job_id: id }, function(d) {
    if (d.ok) {
      var b = document.getElementById('tbtn-' + id);
      if (b) {
        b.innerHTML = d.status === 'Active' ? '<i class="fas fa-lock"></i> Close' : '<i class="fas fa-lock-open"></i> Open';
        b.className = 'btn ' + (d.status === 'Active' ? 'red' : 'grn');
      }
      var card = document.getElementById('jc-' + id);
      if (card) {
        card.setAttribute('data-status', d.status.toLowerCase());
        var badge = card.querySelector('.sbadge');
        if (badge) { badge.className = 'sbadge ' + d.status.toLowerCase(); badge.textContent = d.status; }
      }
      toast('Job ' + d.status.toLowerCase() + '!', 'ok');
    } else { toast(d.msg || 'Error', 'err'); }
  });
}

/* ══════════════════════════════════════════════════════════════
   Post Draft (submit for approval)
   ══════════════════════════════════════════════════════════════ */
function postDraft(id, title) {
  if (!confirm('Submit "' + title + '" for approval? It will become active once approved.')) return;
  doPost({ action: 'post_draft', job_id: id }, function(d) {
    if (d.ok) {
      toast('Draft submitted for approval!', 'ok');
      setTimeout(function(){ location.reload(); }, 1200);
    } else { toast(d.msg || 'Error', 'err'); }
  });
}

/* ══════════════════════════════════════════════════════════════
   Delete (drafts only)
   ══════════════════════════════════════════════════════════════ */
function confirmDel(id, title) {
  document.getElementById('delJobId').value = id;
  document.getElementById('confirmMsg').textContent = 'Delete draft "' + title + '"? This cannot be undone.';
  document.getElementById('confirmModal').classList.add('open');
}

function doDelete() {
  var id = document.getElementById('delJobId').value;
  doPost({ action: 'delete_job', job_id: id }, function(d) {
    document.getElementById('confirmModal').classList.remove('open');
    if (d.ok) {
      var c = document.getElementById('jc-' + id);
      if (c) {
        c.style.opacity = '0';
        c.style.transform = 'translateX(20px)';
        c.style.transition = '.35s';
        setTimeout(function(){ c.remove(); applyFilters(); }, 350);
      }
      toast('Draft deleted', 'ok');
    } else { toast(d.msg || 'Error', 'err'); }
  });
}

/* ══════════════════════════════════════════════════════════════
   AJAX Helper
   ══════════════════════════════════════════════════════════════ */
function doPost(data, cb) {
  var body = Object.keys(data).map(function(k){
    return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
  }).join('&');
  fetch('recruiter_jobs.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body
  }).then(function(r){ return r.json(); }).then(cb).catch(function(){ toast('Network error', 'err'); });
}

/* ══════════════════════════════════════════════════════════════
   Toast
   ══════════════════════════════════════════════════════════════ */
function toast(msg, type) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (type ? ' ' + type : '');
  clearTimeout(t._t);
  t._t = setTimeout(function(){ t.className = 'toast'; }, 3000);
}

/* ══ Close modals on backdrop click ══ */
['jobModal', 'confirmModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
  });
});

/* ══ Auto-open post modal if ?postjob=1 ══ */
if (new URLSearchParams(window.location.search).get('postjob') === '1') openPost();
</script>
</body>
</html>