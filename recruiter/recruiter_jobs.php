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
    'AF'=>'AFN','AL'=>'ALL','DZ'=>'DZD','AD'=>'EUR','AO'=>'AOA','AG'=>'XCD','AR'=>'ARS','AM'=>'AMD',
    'AU'=>'AUD','AT'=>'EUR','AZ'=>'AZN','BS'=>'BSD','BH'=>'BHD','BD'=>'BDT','BB'=>'BBD','BY'=>'BYN',
    'BE'=>'EUR','BZ'=>'BZD','BJ'=>'XOF','BT'=>'BTN','BO'=>'BOB','BA'=>'BAM','BW'=>'BWP','BR'=>'BRL',
    'BN'=>'BND','BG'=>'BGN','BF'=>'XOF','BI'=>'BIF','KH'=>'KHR','CM'=>'XAF','CA'=>'CAD','CV'=>'CVE',
    'CF'=>'XAF','TD'=>'XAF','CL'=>'CLP','CN'=>'CNY','CO'=>'COP','KM'=>'KMF','CG'=>'XAF','CR'=>'CRC',
    'HR'=>'EUR','CU'=>'CUP','CY'=>'EUR','CZ'=>'CZK','DK'=>'DKK','DJ'=>'DJF','DM'=>'XCD','DO'=>'DOP',
    'EC'=>'USD','EG'=>'EGP','SV'=>'USD','GQ'=>'XAF','ER'=>'ERN','EE'=>'EUR','ET'=>'ETB','FJ'=>'FJD',
    'FI'=>'EUR','FR'=>'EUR','GA'=>'XAF','GM'=>'GMD','GE'=>'GEL','DE'=>'EUR','GH'=>'GHS','GR'=>'EUR',
    'GD'=>'XCD','GT'=>'GTQ','GN'=>'GNF','GW'=>'XOF','GY'=>'GYD','HT'=>'HTG','HN'=>'HNL','HU'=>'HUF',
    'IS'=>'ISK','IN'=>'INR','ID'=>'IDR','IR'=>'IRR','IQ'=>'IQD','IE'=>'EUR','IL'=>'ILS','IT'=>'EUR',
    'JM'=>'JMD','JP'=>'JPY','JO'=>'JOD','KZ'=>'KZT','KE'=>'KES','KI'=>'AUD','KW'=>'KWD','KG'=>'KGS',
    'LA'=>'LAK','LV'=>'EUR','LB'=>'LBP','LS'=>'LSL','LR'=>'LRD','LY'=>'LYD','LI'=>'CHF','LT'=>'EUR',
    'LU'=>'EUR','MG'=>'MGA','MW'=>'MWK','MY'=>'MYR','MV'=>'MVR','ML'=>'XOF','MT'=>'EUR','MH'=>'USD',
    'MR'=>'MRU','MU'=>'MUR','MX'=>'MXN','FM'=>'USD','MD'=>'MDL','MC'=>'EUR','MN'=>'MNT','ME'=>'EUR',
    'MA'=>'MAD','MZ'=>'MZN','MM'=>'MMK','NA'=>'NAD','NR'=>'AUD','NP'=>'NPR','NL'=>'EUR','NZ'=>'NZD',
    'NI'=>'NIO','NE'=>'XOF','NG'=>'NGN','KP'=>'KPW','MK'=>'MKD','NO'=>'NOK','OM'=>'OMR','PK'=>'PKR',
    'PW'=>'USD','PS'=>'ILS','PA'=>'PAB','PG'=>'PGK','PY'=>'PYG','PE'=>'PEN','PH'=>'PHP','PL'=>'PLN',
    'PT'=>'EUR','QA'=>'QAR','RO'=>'RON','RU'=>'RUB','RW'=>'RWF','KN'=>'XCD','LC'=>'XCD','VC'=>'XCD',
    'WS'=>'WST','SM'=>'EUR','ST'=>'STN','SA'=>'SAR','SN'=>'XOF','RS'=>'RSD','SC'=>'SCR','SL'=>'SLL',
    'SG'=>'SGD','SK'=>'EUR','SI'=>'EUR','SB'=>'SBD','SO'=>'SOS','ZA'=>'ZAR','KR'=>'KRW','SS'=>'SSP',
    'ES'=>'EUR','LK'=>'LKR','SD'=>'SDG','SR'=>'SRD','SE'=>'SEK','CH'=>'CHF','SY'=>'SYP','TW'=>'TWD',
    'TJ'=>'TJS','TZ'=>'TZS','TH'=>'THB','TL'=>'USD','TG'=>'XOF','TO'=>'TOP','TT'=>'TTD','TN'=>'TND',
    'TR'=>'TRY','TM'=>'TMT','TV'=>'AUD','UG'=>'UGX','UA'=>'UAH','AE'=>'AED','GB'=>'GBP','US'=>'USD',
    'UY'=>'UYU','UZ'=>'UZS','VU'=>'VUV','VA'=>'EUR','VE'=>'VES','VN'=>'VND','YE'=>'YER','ZM'=>'ZMW',
    'ZW'=>'ZWL','HK'=>'HKD',
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
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? min((float)$_POST['salary_min'], 9999999999) : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? min((float)$_POST['salary_max'], 9999999999) : null;
        if ($sMin !== null && $sMin < 0) $sMin = null;
        if ($sMax !== null && $sMax < 0) $sMax = null;
        if ($sMin !== null && $sMax !== null && $sMin > $sMax) { echo json_encode(['ok' => false, 'msg' => 'Min salary cannot be greater than max salary.']); exit; }
        $ind    = trim((string)($_POST['industry'] ?? ''));
        $exp    = (string)($_POST['experience_level'] ?? '') ?: null;
        $skills = trim((string)($_POST['skills'] ?? ''));
        $dl     = (string)($_POST['deadline'] ?? '') ?: null;
        $country  = trim((string)($_POST['country'] ?? ''));
        if (!$country) { echo json_encode(['ok' => false, 'msg' => 'Please select a country to post this job.']); exit; }
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
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? min((float)$_POST['salary_min'], 9999999999) : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? min((float)$_POST['salary_max'], 9999999999) : null;
        if ($sMin !== null && $sMin < 0) $sMin = null;
        if ($sMax !== null && $sMax < 0) $sMax = null;
        if ($sMin !== null && $sMax !== null && $sMin > $sMax) { echo json_encode(['ok' => false, 'msg' => 'Min salary cannot be greater than max salary.']); exit; }
        $ind    = trim((string)($_POST['industry'] ?? ''));
        $exp    = (string)($_POST['experience_level'] ?? '') ?: null;
        $skills = trim((string)($_POST['skills'] ?? ''));
        $dl     = (string)($_POST['deadline'] ?? '') ?: null;
        $country  = trim((string)($_POST['country'] ?? ''));
        if (!$country) { echo json_encode(['ok' => false, 'msg' => 'Please select a country to save this draft.']); exit; }
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
        $sMin   = ($_POST['salary_min'] ?? '') !== '' ? min((float)$_POST['salary_min'], 9999999999) : null;
        $sMax   = ($_POST['salary_max'] ?? '') !== '' ? min((float)$_POST['salary_max'], 9999999999) : null;
        if ($sMin !== null && $sMin < 0) $sMin = null;
        if ($sMax !== null && $sMax < 0) $sMax = null;
        if ($sMin !== null && $sMax !== null && $sMin > $sMax) { echo json_encode(['ok' => false, 'msg' => 'Min salary cannot be greater than max salary.']); exit; }
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

    /* ── delete_job (soft) ── */
    if ($action === 'delete_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $st = $db->prepare("UPDATE jobs SET deleted_at=NOW(), status='Closed', updated_at=NOW() WHERE id=? AND recruiter_id=? AND deleted_at IS NULL");
            $st->execute([$jobId, $uid]);
            if ($st->rowCount() === 0) {
                echo json_encode(['ok' => false, 'msg' => 'Job not found or already deleted']);
            } else {
                echo json_encode(['ok' => true]);
            }
        } catch (Exception $e) {
            error_log('[AntCareers] delete_job: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'DB error']);
        }
        exit;
    }

    /* ── restore_job ── */
    if ($action === 'restore_job') {
        $jobId = (int)($_POST['job_id'] ?? 0);
        try {
            $st = $db->prepare("UPDATE jobs SET deleted_at=NULL, status='Draft', approval_status='pending', updated_at=NOW() WHERE id=? AND recruiter_id=? AND deleted_at IS NOT NULL");
            $st->execute([$jobId, $uid]);
            if ($st->rowCount() === 0) {
                echo json_encode(['ok' => false, 'msg' => 'Job not found or not in Trash']);
            } else {
                echo json_encode(['ok' => true]);
            }
        } catch (Exception $e) {
            error_log('[AntCareers] recruiter restore_job: ' . $e->getMessage());
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
$deletedJobs = [];
$counts = ['total' => 0, 'Active' => 0, 'Closed' => 0, 'pending' => 0, 'Draft' => 0, 'deleted' => 0];
$dbErr  = false;
$perPage   = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $perPage;
$totalPages = 1;

try {
    /* Status counts (non-deleted only) */
    $sc = $db->prepare("SELECT status, COUNT(*) AS c FROM jobs WHERE recruiter_id=? AND deleted_at IS NULL GROUP BY status");
    $sc->execute([$uid]);
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['c'];
        $counts['total'] += (int)$r['c'];
    }
    $totalPages = max(1, (int)ceil($counts['total'] / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    /* Pending approval count (non-deleted only) */
    $pc = $db->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=? AND approval_status='pending' AND deleted_at IS NULL");
    $pc->execute([$uid]);
    $counts['pending'] = (int)$pc->fetchColumn();

    /* All active (non-deleted) jobs with app count */
    $st = $db->prepare("
        SELECT j.*, COUNT(a.id) AS app_count
        FROM jobs j
        LEFT JOIN applications a ON a.job_id = j.id
        WHERE j.recruiter_id = :uid AND j.deleted_at IS NULL
        GROUP BY j.id
        ORDER BY j.created_at DESC
        LIMIT :perPage OFFSET :offset
    ");
    $st->bindValue(':uid',     $uid,     PDO::PARAM_INT);
    $st->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset',  $offset,  PDO::PARAM_INT);
    $st->execute();
    $jobs = $st->fetchAll(PDO::FETCH_ASSOC);

    /* Deleted jobs */
    $dc = $db->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id=? AND deleted_at IS NOT NULL");
    $dc->execute([$uid]);
    $counts['deleted'] = (int)$dc->fetchColumn();
    if ($counts['deleted'] > 0) {
        $ds = $db->prepare("
            SELECT j.*, 0 AS app_count
            FROM jobs j
            WHERE j.recruiter_id = ? AND j.deleted_at IS NOT NULL
            ORDER BY j.deleted_at DESC
        ");
        $ds->execute([$uid]);
        $deletedJobs = $ds->fetchAll(PDO::FETCH_ASSOC);
    }
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
    .filter-toolbar{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:10px;padding:16px 18px;margin-bottom:18px;}
    .filter-form{display:flex;flex-direction:column;gap:12px;align-items:stretch;}
    .toolbar-row{width:100%;}
    .toolbar-row.status-row .stats-row{width:100%;margin-bottom:0;}
    .toolbar-row.controls-row .quick-filters{width:100%;}
    .filter-toolbar .search-wrap{flex:1;min-width:200px;position:relative;}
    .filter-toolbar .search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;}
    .filter-toolbar .search-wrap input{width:100%;padding:9px 13px 9px 34px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-light);font-family:var(--font-body);font-size:13px;outline:none;transition:.2s;}
    .filter-toolbar .search-wrap input:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .filter-toolbar .search-wrap input{background:#F5EEEC;border-color:#E0CECA;color:#1A0A09;}
    .filter-toolbar .quick-filters{display:flex;align-items:flex-end;gap:10px;flex-wrap:nowrap;}
    .filter-toolbar .quick-filters .search-wrap{flex:1;min-width:260px;}
    .filter-toolbar select{padding:9px 13px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-light);font-family:var(--font-body);font-size:13px;outline:none;cursor:pointer;transition:.2s;min-width:170px;}
    .filter-toolbar select:focus{border-color:var(--red-vivid);}
    body.light .filter-toolbar select{background:#F5EEEC;border-color:#E0CECA;color:#1A0A09;}
    body.light .filter-toolbar select option{background:#fff;color:#1A0A09;}
    .filter-toolbar .stat-pill{padding:5px 12px;border-radius:100px;background:var(--soil-hover);}
    .filter-toolbar .sp-label{font-size:12px;}
    .filter-toolbar .sp-count{font-size:12px;font-family:var(--font-body);font-weight:700;line-height:1;}
    @media(max-width:900px){
      .filter-toolbar .quick-filters{flex-direction:column;align-items:stretch;}
      .filter-toolbar .quick-filters .search-wrap,.filter-toolbar .quick-filters select{width:100%;min-width:0;}
    }

    /* ── Job list ── */
    .job-list{display:flex;flex-direction:column;gap:10px;}
    .job-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:14px;padding:22px 24px;display:flex;align-items:start;gap:16px;transition:all 0.25s;position:relative;}
    .job-card:hover{border-color:rgba(209,61,44,.4);box-shadow:0 8px 32px rgba(0,0,0,.3),0 0 0 1px rgba(209,61,44,.08);transform:translateY(-1px);}
    .job-card.hidden{display:none;}
    body.light .job-card{background:#fff;border-color:#E0CECA;}
    body.light .job-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.08),0 0 0 1px rgba(209,61,44,.12);}
    .job-icon{width:42px;height:42px;border-radius:10px;background:rgba(209,61,44,.1);border:1px solid rgba(209,61,44,.2);display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--red-bright);flex-shrink:0;}
    .job-body{flex:1;min-width:0;}
    .job-title{font-family:var(--font-display);font-size:18px;font-weight:700;color:#F5F0EE;margin-bottom:6px;letter-spacing:-0.01em;}
    body.light .job-title{color:#1A0A09;}
    .job-meta{display:flex;align-items:center;flex-wrap:wrap;gap:10px;font-size:12px;color:var(--text-muted);margin-bottom:9px;}
    .job-meta i{font-size:10px;color:var(--red-bright);}
    .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:2px;}
    .chip{font-size:11px;font-weight:600;padding:4px 10px;border-radius:6px;background:var(--soil-hover);color:#A09090;border:1px solid var(--soil-line);display:inline-flex;align-items:center;gap:4px;}
    body.light .chip{background:#F5EDEB;border-color:#D4B0AB;color:#6A4A4A;}
    .chip-type{background:rgba(59,130,246,.1);color:#60A5FA;border-color:rgba(59,130,246,.2);}
    body.light .chip-type{background:rgba(59,130,246,.08);color:#2563EB;border-color:rgba(59,130,246,.2);}
    .chip-setup{background:rgba(168,85,247,.1);color:#C084FC;border-color:rgba(168,85,247,.2);}
    body.light .chip-setup{background:rgba(168,85,247,.08);color:#7C3AED;border-color:rgba(168,85,247,.2);}
    .chip-level{background:rgba(212,148,58,.1);color:var(--amber);border-color:rgba(212,148,58,.2);}
    body.light .chip-level{background:rgba(212,148,58,.08);color:#B8620A;border-color:rgba(212,148,58,.2);}

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

    .job-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0;}
    .app-badge{display:flex;flex-direction:column;align-items:center;gap:2px;background:rgba(212,148,58,.06);border:1px solid rgba(212,148,58,.15);border-radius:10px;padding:10px 16px;min-width:72px;}
    body.light .app-badge{background:rgba(212,148,58,.06);border-color:rgba(212,148,58,.2);}
    .app-badge>i{font-size:14px;color:var(--amber);margin-bottom:2px;}
    .app-count{font-family:var(--font-display);font-size:22px;font-weight:700;color:#F5F0EE;text-align:center;}
    .app-count-lbl{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;}
    body.light .app-count{color:#1A0A09;}
    .job-actions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;}
    @media(max-width:760px){
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.main-content{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal-bd{z-index:9999!important;inset:0!important;position:fixed!important}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .job-card{flex-direction:column;gap:10px}
      .job-right{display:flex;flex-direction:column;width:100%;align-items:flex-end;gap:8px;border-top:1px solid var(--soil-line);padding-top:12px}
      .job-salary{white-space:normal;word-break:break-word;font-size:13px}
      .job-actions{width:100%;justify-content:flex-start;flex-wrap:wrap}
      .chips{display:flex;flex-wrap:wrap;gap:6px}
      .job-description-preview,.card-description{display:none}
      .profile-name{display:none}
    }
    @media(min-width:761px){
      .modal,.modal-inner,.modal-box{position:relative!important;bottom:auto!important;left:auto!important;right:auto!important;top:auto!important;border-radius:16px!important}
    }
    @media(max-width:620px){}

    /* ── Buttons ── */
    .btn{padding:7px 14px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);transition:.18s;border:1px solid var(--soil-line);background:transparent;color:var(--text-muted);white-space:nowrap;display:inline-flex;align-items:center;gap:5px;}
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
    body.light .modal-close:hover{background:#F5EDEB;color:#7A1515;border-color:#D4B0AB;}
    .fg{margin-bottom:14px;}
    .fl{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;display:flex;align-items:center;gap:4px;}
    .fl .req{color:var(--red-bright);font-size:13px;line-height:1;}
    .fi{width:100%;padding:10px 14px;border-radius:8px;background:var(--soil-hover);border:1px solid var(--soil-line);color:#F5F0EE;font-family:var(--font-body);font-size:13px;outline:none;transition:all .2s;}
    .fi:hover{border-color:var(--text-muted);}
    .fi:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .fi{background:#F5EDEB;border-color:#D4B0AB;color:#1A0A09;}
    body.light .fi:hover{border-color:#B89090;}
    body.light .fi option{background:#F5EDEB;color:#1A0A09;}
    body.light .fl{color:#7A5555;}
    textarea.fi{resize:vertical;min-height:80px;}
    .frow{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    @media(max-width:480px){.frow{grid-template-columns:1fr!important;}}
    .form-divider{display:flex;align-items:center;gap:10px;margin:20px 0 14px;font-size:11px;font-weight:700;color:var(--red-pale);text-transform:uppercase;letter-spacing:.08em;}
    .form-divider::after{content:'';flex:1;height:1px;background:var(--soil-line);}
    body.light .form-divider{color:var(--red-vivid);}
    body.light .form-divider::after{background:#E0CECA;}
    .mfoot{display:flex;justify-content:flex-end;gap:9px;margin-top:22px;padding-top:18px;border-top:1px solid var(--soil-line);flex-wrap:wrap;}
    body.light .mfoot{border-top-color:#E0CECA;}

    /* ── Confirm modal ── */
    .confirm-box{background:var(--soil-card);border:1px solid rgba(220,53,69,.3);border-radius:14px;padding:28px;width:100%;max-width:380px;text-align:center;margin:auto;}
    body.light .confirm-box{background:#fff;}
    .confirm-icon{font-size:38px;color:#ff8080;margin-bottom:12px;}
    .confirm-title{font-family:var(--font-display);font-size:18px;color:#F5F0EE;margin-bottom:7px;}
    body.light .confirm-title{color:#1A0A09;}
    .confirm-sub{font-size:13px;color:var(--text-muted);margin-bottom:18px;}
    .confirm-actions{display:flex;gap:10px;justify-content:center;}


    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .anim{animation:fadeUp 0.4s ease both;}.anim-d1{animation-delay:0.05s;}.anim-d2{animation-delay:0.1s;}

    /* ── Send Invite Button ── */
    .btn-send-invite{padding:7px 15px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font-body);white-space:nowrap;display:inline-flex;align-items:center;gap:6px;border:none;background:linear-gradient(135deg,var(--red-vivid),#B91C1C);color:#fff;box-shadow:0 2px 8px rgba(209,61,44,.35);transition:all .2s ease;letter-spacing:.01em;}
    .btn-send-invite:hover{background:linear-gradient(135deg,var(--red-bright),#DC2626);box-shadow:0 4px 16px rgba(209,61,44,.55);transform:translateY(-1px);}
    .btn-send-invite:active{transform:translateY(0);box-shadow:0 2px 6px rgba(209,61,44,.3);}
    .btn-send-invite i{font-size:11px;}
    /* keep .btn.blue for the modal send button */
    .btn.blue{background:linear-gradient(135deg,var(--red-vivid),#B91C1C);border-color:#DC2626;color:#fff;box-shadow:0 2px 8px rgba(209,61,44,.3);}
    .btn.blue:hover{background:linear-gradient(135deg,var(--red-bright),#DC2626);box-shadow:0 4px 14px rgba(209,61,44,.45);transform:translateY(-1px);}
    .btn.blue:disabled,.btn.blue[disabled]{background:#A85C5C;border-color:#A85C5C;opacity:.55;cursor:not-allowed;transform:none;box-shadow:none;}

    /* ── Premium Send Invitation CTA ── */
    #invSendBtn{padding:13px 30px;font-size:14px;font-weight:800;letter-spacing:.04em;gap:9px;border-radius:11px;background:linear-gradient(135deg,var(--red-vivid) 0%,#B91C1C 100%);border:none;box-shadow:0 4px 20px rgba(209,61,44,.52);transition:all .25s ease;position:relative;overflow:hidden;}
    #invSendBtn::after{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent);transition:left .55s ease;pointer-events:none;}
    #invSendBtn:not(:disabled){animation:invBtnPulse 3.5s ease infinite;}
    #invSendBtn:not(:disabled):hover{background:linear-gradient(135deg,var(--red-bright) 0%,#DC2626 100%);box-shadow:0 8px 30px rgba(209,61,44,.7);transform:translateY(-2px);}
    #invSendBtn:not(:disabled):hover::after{left:140%;}
    #invSendBtn:disabled{opacity:.55;cursor:not-allowed;box-shadow:none;animation:none;}
    @keyframes invBtnPulse{0%,100%{box-shadow:0 4px 20px rgba(209,61,44,.52);}55%{box-shadow:0 6px 28px rgba(209,61,44,.78),0 0 0 5px rgba(209,61,44,.13);}}
    #invSendBtn i{font-size:13px;transition:transform .22s;}
    #invSendBtn:not(:disabled):hover i{transform:translateX(3px) rotate(-8deg);}

    /* ══════ SEND INVITE MODAL ══════ */
    .inv-modal-box{background:var(--soil-card);border:1px solid var(--soil-line);border-top:3px solid #3B82F6;border-radius:16px;padding:0;width:100%;max-width:980px;position:relative;margin:auto;display:flex;flex-direction:column;max-height:90vh;box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;}
    body.light .inv-modal-box{background:#fff;border-color:#E0CECA;border-top-color:#2563EB;box-shadow:0 24px 60px rgba(0,0,0,.15);}
    .inv-modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 26px;border-bottom:1px solid var(--soil-line);background:linear-gradient(135deg,rgba(37,99,235,.11),rgba(37,99,235,.03));flex-shrink:0;gap:14px;}
    body.light .inv-modal-header{background:linear-gradient(135deg,rgba(37,99,235,.06),rgba(37,99,235,.01));border-bottom-color:#E0CECA;}
    .inv-modal-hicon{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,rgba(59,130,246,.3),rgba(29,78,216,.2));border:1px solid rgba(59,130,246,.35);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .inv-modal-hicon i{font-size:18px;color:#60A5FA;}
    body.light .inv-modal-hicon{background:linear-gradient(135deg,rgba(37,99,235,.12),rgba(37,99,235,.05));}
    .inv-modal-title{font-family:var(--font-display);font-size:20px;font-weight:700;color:#F5F0EE;line-height:1.2;}
    body.light .inv-modal-title{color:#1A0A09;}
    .inv-modal-subtitle{font-size:12px;color:var(--text-muted);margin-top:3px;}
    .inv-panels{display:grid;grid-template-columns:1fr 1fr;gap:18px;flex:1;overflow:hidden;min-height:0;padding:22px 26px;}
    @media(max-width:680px){.inv-panels{grid-template-columns:1fr;overflow:auto;}}
    .inv-panel{display:flex;flex-direction:column;overflow:hidden;}
    .inv-panel-title{font-size:10.5px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px;flex-shrink:0;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--soil-line);}
    body.light .inv-panel-title{border-bottom-color:#E8D9D7;}
    .inv-panel-title::before{content:'';display:inline-block;width:3px;height:13px;border-radius:2px;flex-shrink:0;}
    .inv-panel:first-child .inv-panel-title::before{background:linear-gradient(to bottom,#60A5FA,#1D4ED8);}
    .inv-panel.inv-right .inv-panel-title::before{background:linear-gradient(to bottom,var(--red-bright),var(--red-deep));}
    /* Left panel */
    .inv-search-wrap{position:relative;flex-shrink:0;margin-bottom:10px;}
    .inv-search-wrap i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;pointer-events:none;}
    .inv-search-wrap input{width:100%;padding:10px 14px 10px 34px;border-radius:9px;background:var(--soil-hover);border:1px solid var(--soil-line);color:#F5F0EE;font-family:var(--font-body);font-size:13px;outline:none;transition:.2s;}
    .inv-search-wrap input:focus{border-color:#3B82F6;box-shadow:0 0 0 3px rgba(59,130,246,.12);}
    body.light .inv-search-wrap input{background:#F5EDEB;border-color:#D4B0AB;color:#1A0A09;}
    .inv-seeker-list{flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:7px;padding-right:3px;min-height:0;}
    .inv-seeker-list::-webkit-scrollbar{width:4px;}
    .inv-seeker-list::-webkit-scrollbar-track{background:transparent;}
    .inv-seeker-list::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:4px;}
    .inv-seeker-card{display:flex;align-items:center;gap:12px;padding:11px 14px;border-radius:11px;border:1px solid var(--soil-line);cursor:pointer;transition:all .18s;background:var(--soil-hover);}
    .inv-seeker-card:hover:not(.disabled){border-color:rgba(59,130,246,.5);background:rgba(59,130,246,.07);}
    .inv-seeker-card.selected{border-color:rgba(59,130,246,.65);background:rgba(59,130,246,.12);border-left:3px solid #3B82F6;padding-left:12px;}
    .inv-seeker-card.disabled{opacity:.5;cursor:not-allowed;}
    body.light .inv-seeker-card{background:#F5EDEB;border-color:#D4B0AB;}
    body.light .inv-seeker-card:hover:not(.disabled){background:rgba(37,99,235,.05);}
    body.light .inv-seeker-card.selected{background:rgba(37,99,235,.09);border-left-color:#2563EB;}
    .inv-av{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#D13D2C,#7A1515);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;overflow:hidden;}
    .inv-av img{width:100%;height:100%;object-fit:cover;}
    .inv-seeker-info{flex:1;min-width:0;}
    .inv-seeker-name{font-size:13px;font-weight:600;color:#F5F0EE;display:flex;align-items:center;gap:5px;flex-wrap:wrap;}
    body.light .inv-seeker-name{color:#1A0A09;}
    .inv-seeker-hl{font-size:11px;color:var(--text-muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .inv-seeker-loc{font-size:11px;color:var(--text-muted);}
    .inv-seeker-loc i{font-size:9px;color:var(--red-bright);}
    .inv-skills-row{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;}
    .inv-skill{font-size:10px;padding:2px 7px;border-radius:5px;background:var(--soil-line);color:var(--text-muted);border:1px solid rgba(255,255,255,.05);}
    body.light .inv-skill{background:#E5D5D3;border-color:#C0A0A0;color:#5A4040;}
    .inv-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;letter-spacing:.03em;}
    .inv-badge.applied{background:rgba(76,175,112,.15);color:#5ec87a;border:1px solid rgba(76,175,112,.25);}
    .inv-badge.sent{background:rgba(37,99,235,.15);color:#93C5FD;border:1px solid rgba(37,99,235,.25);}
    .inv-badge.accepted{background:rgba(76,175,112,.15);color:#5ec87a;border:1px solid rgba(76,175,112,.25);}
    .inv-badge.declined{background:rgba(220,53,69,.15);color:#ff8080;border:1px solid rgba(220,53,69,.25);}
    .inv-check{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#3B82F6,#1D4ED8);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;flex-shrink:0;box-shadow:0 2px 6px rgba(37,99,235,.4);}
    .inv-counter{font-size:12px;font-weight:600;color:var(--text-muted);padding-top:9px;flex-shrink:0;}
    .inv-loading,.inv-empty{text-align:center;padding:34px 10px;color:var(--text-muted);font-size:13px;}
    .inv-empty i{font-size:32px;margin-bottom:8px;display:block;opacity:.65;}
    /* Right panel */
    .inv-right{overflow-y:auto;}
    .inv-right::-webkit-scrollbar{width:4px;}
    .inv-right::-webkit-scrollbar-thumb{background:var(--soil-line);border-radius:4px;}
    .inv-preview-card{background:var(--soil-hover);border:1px solid var(--soil-line);border-top:3px solid var(--red-vivid);border-radius:12px;padding:20px 22px;font-size:13px;line-height:1.72;color:var(--text-mid);position:relative;overflow:hidden;}
    .inv-preview-card::after{content:'\f0e0';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;bottom:-10px;right:10px;font-size:80px;color:rgba(209,61,44,.04);pointer-events:none;line-height:1;}
    body.light .inv-preview-card{background:#FFFBFA;border-color:#EBDAD8;border-top-color:var(--red-vivid);}
    body.light .inv-preview-card::after{color:rgba(209,61,44,.05);}
    .inv-preview-greeting{font-size:15px;font-weight:700;color:#F5F0EE;margin-bottom:14px;font-family:var(--font-display);}
    body.light .inv-preview-greeting{color:#1A0A09;}
    .inv-preview-card p{margin-bottom:10px;}
    .inv-apply-btn{display:inline-flex;align-items:center;gap:7px;margin:12px 0;padding:9px 20px;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));border-radius:8px;color:#fff;font-size:12px;font-weight:700;text-decoration:none;box-shadow:0 3px 12px rgba(209,61,44,.4);letter-spacing:.02em;}
    .inv-preview-sig{margin-top:14px;padding-top:13px;border-top:1px dashed var(--soil-line);font-size:12px;color:var(--text-muted);line-height:1.7;display:flex;flex-direction:column;gap:2px;}
    body.light .inv-preview-sig{border-top-color:#D4B0AB;}
    .inv-preview-sig strong{color:var(--text-mid);font-size:13px;}
    .inv-note-count{font-size:11px;color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0;}
    .inv-mfoot{display:flex;justify-content:space-between;align-items:center;padding:16px 26px;background:linear-gradient(to right,rgba(0,0,0,.18),rgba(0,0,0,.1));border-top:1px solid var(--soil-line);flex-shrink:0;flex-wrap:wrap;gap:12px;}
    body.light .inv-mfoot{border-top-color:#E0CECA;background:linear-gradient(to right,rgba(209,61,44,.04),rgba(209,61,44,.01));}
    .inv-mfoot .inv-cancel-btn{padding:12px 22px;font-size:13px;font-weight:600;border-radius:10px;border:1.5px solid var(--soil-line);background:transparent;color:var(--text-muted);cursor:pointer;font-family:var(--font-body);transition:all .2s;}
    .inv-mfoot .inv-cancel-btn:hover{background:var(--soil-hover);color:var(--text-mid);border-color:var(--text-muted);}
    body.light .inv-mfoot .inv-cancel-btn{border-color:#D4B0AB;color:#6A4A4A;}
    body.light .inv-mfoot .inv-cancel-btn:hover{background:#F5EDEB;color:#3A2020;}
    body.light #invSendBtn{background:linear-gradient(135deg,var(--red-vivid) 0%,#B91C1C 100%);box-shadow:0 4px 20px rgba(209,61,44,.35);color:#fff;}
    body.light #invSendBtn:not(:disabled):hover{background:linear-gradient(135deg,var(--red-bright) 0%,#DC2626 100%);box-shadow:0 8px 30px rgba(209,61,44,.5);color:#fff;}
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

  <!-- Toolbar -->
  <div class="filter-toolbar">
    <div class="filter-form">
      <div class="toolbar-row status-row">
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
          <div class="stat-pill" data-filter="closed" onclick="filterJobs('closed',this)">
            <i class="fas fa-times-circle sp-icon" style="color:#ff8080"></i>
            <span class="sp-label">Closed</span>
            <span class="sp-count" id="cntClosed"><?= $counts['Closed'] ?></span>
          </div>
          <div class="stat-pill" data-filter="draft" onclick="filterJobs('draft',this)">
            <i class="fas fa-pencil-alt sp-icon" style="color:var(--text-muted)"></i>
            <span class="sp-label">Drafts</span>
            <span class="sp-count" id="cntDraft"><?= $counts['Draft'] ?></span>
          </div>
          <div class="stat-pill" data-filter="deleted" onclick="filterJobs('deleted',this)">
            <i class="fas fa-trash-alt sp-icon" style="color:var(--red-pale)"></i>
            <span class="sp-label">Trash</span>
            <span class="sp-count" id="cntDeleted"><?= $counts['deleted'] ?></span>
          </div>
          <div class="stat-pill" style="cursor:default;">
            <i class="fas fa-users sp-icon" style="color:var(--amber)"></i>
            <span class="sp-label">Applicants</span>
            <span class="sp-count"><?= array_sum(array_column($jobs, 'app_count')) ?></span>
          </div>
        </div>
      </div>
      <div class="toolbar-row controls-row">
        <div class="quick-filters">
          <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search jobs by title, location, skills…" oninput="applyFilters()">
          </div>
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
            <option value="draft">Draft</option>
            <option value="deleted">Trash</option>
            <option value="pending">Pending Approval</option>
            <option value="rejected">Rejected</option>
          </select>
          <select id="approvalFilter" onchange="applyFilters()">
            <option value="all">All Approvals</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
    </div>
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
        $cur = currencySymbol($j['salary_currency'] ?? 'PHP');
        $mn  = $j['salary_min'] ? number_format((float)$j['salary_min']) : '';
        $mx  = $j['salary_max'] ? number_format((float)$j['salary_max']) : '';
        $sal  = $mn && $mx ? "{$cur}{$mn}–{$mx}" : ($mn ? "{$cur}{$mn}+" : "{$cur}up to {$mx}");
    }
    $pd = date('M j, Y', strtotime($j['created_at']));
  ?>
  <div class="job-card"
       id="jc-<?= $j['id'] ?>"
       data-title="<?= htmlspecialchars(strtolower($j['title']), ENT_QUOTES) ?>"
       data-location="<?= htmlspecialchars(strtolower($j['location'] ?? ''), ENT_QUOTES) ?>"
       data-skills="<?= htmlspecialchars(strtolower($j['skills_required'] ?? ''), ENT_QUOTES) ?>"
       data-status="<?= $sc ?>"
       data-approval="<?= $asc ?>"
       data-deleted="0">
    <div class="job-icon"><i class="fas fa-briefcase"></i></div>
    <div class="job-body">
      <div class="job-title"><?= htmlspecialchars($j['title']) ?></div>
      <div class="job-meta">
        <?php if ($j['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($j['location']) ?></span><?php endif; ?>
        <?php if ($sal): ?><span><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($sal) ?></span><?php endif; ?>
        <span><i class="fas fa-calendar-alt"></i> Posted <?= $pd ?></span>
      </div>
      <div class="chips">
        <span class="sbadge <?= $sc ?>"><?= htmlspecialchars($j['status']) ?></span>
        <span class="abadge <?= $asc ?>"><i class="fas fa-<?= $asc === 'approved' ? 'check' : ($asc === 'rejected' ? 'times' : 'clock') ?>"></i> <?= ucfirst($asc) ?></span>
        <span class="chip chip-type"><i class="fas fa-tag"></i> <?= htmlspecialchars($j['job_type']) ?></span>
        <span class="chip chip-setup"><i class="fas fa-laptop-house"></i> <?= htmlspecialchars($j['setup']) ?></span>
        <?php if ($j['experience_level']): ?><span class="chip chip-level"><?= htmlspecialchars($j['experience_level']) ?></span><?php endif; ?>
        <?php foreach (array_slice(explode(',', $j['skills_required'] ?? ''), 0, 3) as $sk): if (trim($sk)): ?><span class="chip"><?= htmlspecialchars(trim($sk)) ?></span><?php endif; endforeach; ?>
      </div>
    </div>
    <div class="job-right">
      <div class="app-badge">
        <i class="fas fa-users"></i>
        <div class="app-count"><?= (int)$j['app_count'] ?></div>
        <div class="app-count-lbl">Applicants</div>
      </div>
      <div class="job-actions">
        <a href="recruiter_applicants.php" class="btn grn"><i class="fas fa-users"></i> View</a>
        <button class="btn" onclick="editJob(<?= $j['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
        <?php if ($asc === 'approved'): ?>
        <button class="btn <?= $j['status'] === 'Active' ? 'red' : 'grn' ?>" onclick="toggleStatus(<?= $j['id'] ?>,'<?= $j['status'] ?>')" id="tbtn-<?= $j['id'] ?>">
          <?= $j['status'] === 'Active' ? '<i class="fas fa-lock"></i> Close' : '<i class="fas fa-lock-open"></i> Open' ?>
        </button>
        <?php endif; ?>
        <?php if ($sc === 'draft'): ?>
        <button class="btn grn" onclick="postDraft(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-paper-plane"></i> Post</button>
        <?php endif; ?>
        <?php if ($sc === 'active'): ?>
        <button class="btn-send-invite" onclick="openInvite(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-user-plus"></i> Send Invite</button>
        <?php endif; ?>
        <button class="btn red" onclick="confirmDel(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
  <?php foreach ($deletedJobs as $j):
    $pd = date('M j, Y', strtotime($j['deleted_at']));
  ?>
  <div class="job-card"
       id="jc-<?= $j['id'] ?>"
       data-title="<?= htmlspecialchars(strtolower($j['title']), ENT_QUOTES) ?>"
       data-location="<?= htmlspecialchars(strtolower($j['location'] ?? ''), ENT_QUOTES) ?>"
       data-skills="<?= htmlspecialchars(strtolower($j['skills_required'] ?? ''), ENT_QUOTES) ?>"
       data-status="closed"
       data-approval="n/a"
       data-deleted="1"
       style="opacity:0.65;border-color:rgba(146,124,122,0.25);">
    <div class="job-icon" style="opacity:0.5"><i class="fas fa-briefcase"></i></div>
    <div class="job-body">
      <div class="job-title"><?= htmlspecialchars($j['title']) ?></div>
      <div class="job-meta">
        <?php if ($j['location']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($j['location']) ?></span><?php endif; ?>
        <span style="color:var(--red-pale)"><i class="fas fa-trash-alt"></i> Moved to Trash <?= $pd ?></span>
      </div>
    </div>
    <div class="job-right">
      <div class="job-actions">
        <button class="btn grn" onclick="restoreJob(<?= $j['id'] ?>,'<?= htmlspecialchars($j['title'], ENT_QUOTES) ?>')"><i class="fas fa-trash-restore"></i> Restore</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>

  <div class="empty" id="noResults" style="display:none;"><i class="fas fa-search"></i><p>No jobs match your search or filter.</p></div>

  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin:16px 0;flex-wrap:wrap;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>" style="padding:7px 14px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:8px;color:var(--text-mid);text-decoration:none;font-size:13px;">← Prev</a>
    <?php endif; ?>
    <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
      <a href="?page=<?= $p ?>" style="padding:7px 14px;background:<?= $p === $page ? 'var(--red-vivid)' : 'var(--soil-card)' ?>;border:1px solid <?= $p === $page ? 'var(--red-vivid)' : 'var(--soil-line)' ?>;border-radius:8px;color:<?= $p === $page ? '#fff' : 'var(--text-mid)' ?>;text-decoration:none;font-size:13px;"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>" style="padding:7px 14px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:8px;color:var(--text-mid);text-decoration:none;font-size:13px;">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<!-- ═══════════════════════════════════════════════════════════
     Post / Edit Job Modal
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-bd" id="jobModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeJobModal()"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="mTitle"><i class="fas fa-plus-circle" style="color:var(--red-bright)"></i> Post New Job</div>
    <input type="hidden" id="eJobId">

    <div class="form-divider"><i class="fas fa-briefcase" style="font-size:10px"></i> Job Information</div>

    <div class="fg">
      <label class="fl">Industry <span class="req">*</span></label>
      <select class="fi" id="fInd" onchange="updateJobTitleOptions()"><option value="">— Select Industry —</option><option>Accounting</option><option>Administration &amp; Office Support</option><option>Advertising, Arts &amp; Media</option><option>Banking &amp; Financial Services</option><option>Call Centre &amp; Customer Service</option><option>CEO &amp; General Management</option><option>Community Services &amp; Development</option><option>Construction</option><option>Consulting &amp; Strategy</option><option>Design &amp; Architecture</option><option>Education &amp; Training</option><option>Engineering</option><option>Farming, Animals &amp; Conservation</option><option>Government &amp; Defence</option><option>Healthcare &amp; Medical</option><option>Hospitality &amp; Tourism</option><option>Human Resources &amp; Recruitment</option><option>Information &amp; Communication Technology</option><option>Insurance &amp; Superannuation</option><option>Legal</option><option>Manufacturing, Transport &amp; Logistics</option><option>Marketing &amp; Communications</option><option>Mining, Resources &amp; Energy</option><option>Real Estate &amp; Property</option><option>Retail &amp; Consumer Products</option><option>Sales</option><option>Science &amp; Technology</option><option>Self Employment</option><option>Sports &amp; Recreation</option><option>Trades &amp; Services</option></select>
    </div>

    <div class="fg">
      <label class="fl">Job Title <span class="req">*</span></label>
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

    <div class="form-divider"><i class="fas fa-map-marker-alt" style="font-size:10px"></i> Location & Compensation</div>

    <div class="frow" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="fg">
        <label class="fl">Country <span style="color:var(--red-vivid);">*</span></label>
        <select class="fi" id="fCountry" required onchange="onCountryChange()">
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
        <label class="fl" id="lblSMin">Min Salary</label>
        <input type="number" class="fi" id="fSMin" placeholder="e.g. 50000" min="0" max="9999999999">
      </div>
      <div class="fg">
        <label class="fl" id="lblSMax">Max Salary</label>
        <input type="number" class="fi" id="fSMax" placeholder="e.g. 90000" min="0" max="9999999999">
      </div>
    </div>

    <div class="frow">
      <div class="fg">
        <label class="fl">Application Deadline</label>
        <input type="date" class="fi" id="fDl">
      </div>
    </div>

    <div class="form-divider"><i class="fas fa-file-alt" style="font-size:10px"></i> Additional Details</div>

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
    <div class="confirm-title">Delete this job?</div>
    <div class="confirm-sub" id="confirmMsg">This cannot be undone.</div>
    <input type="hidden" id="delJobId">
    <div class="confirm-actions">
      <button class="btn" onclick="document.getElementById('confirmModal').classList.remove('open')">Cancel</button>
      <button class="btn red" onclick="doDelete()"><i class="fas fa-trash"></i> Delete</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     Send Invite Modal
     ═══════════════════════════════════════════════════════════ -->
<div class="modal-bd" id="inviteModal">
  <div class="inv-modal-box">
    <button class="modal-close" onclick="closeInviteModal()"><i class="fas fa-times"></i></button>
    <div class="inv-modal-header">
      <div class="inv-modal-hicon"><i class="fas fa-paper-plane"></i></div>
      <div style="flex:1;">
        <div class="inv-modal-title" id="invModalTitle">Send Invitation</div>
        <div class="inv-modal-subtitle">Select jobseekers to invite and preview the invitation below.</div>
      </div>
    </div>

    <div class="inv-panels">
      <!-- LEFT: Seeker Selection -->
      <div class="inv-panel">
        <div class="inv-panel-title"><i class="fas fa-users" style="font-size:9px;"></i> Jobseeker Selection</div>
        <div class="inv-search-wrap">
          <i class="fas fa-search"></i>
          <input type="text" id="invSearch" placeholder="Search by name, skills, or location…" oninput="invSearchDebounce()">
        </div>
        <div id="invSeekerList" class="inv-seeker-list">
          <div class="inv-loading"><i class="fas fa-circle-notch fa-spin"></i><br>Loading jobseekers…</div>
        </div>
        <div class="inv-counter" id="invCounter">0 jobseekers selected</div>
      </div>

      <!-- RIGHT: Preview -->
      <div class="inv-panel inv-right">
        <div class="inv-panel-title"><i class="fas fa-envelope-open-text" style="font-size:9px;"></i> Invitation Message Preview</div>
        <div class="inv-preview-card" id="invPreviewCard">
          <div class="inv-preview-greeting">Dear <strong>[Jobseeker Name],</strong></div>
          <p>We came across your profile on AntCareers and believe you would be an excellent fit for the <strong>[Job Title]</strong> position at <strong><?= htmlspecialchars($companyName, ENT_QUOTES) ?></strong>.</p>
          <p>We would like to formally invite you to review this opportunity and consider submitting your application.</p>
          <span class="inv-apply-btn">View Job Details &amp; Apply</span>
          <p style="margin-top:10px;">We look forward to hearing from you.</p>
          <div class="inv-preview-sig">Best regards,<br><strong><?= htmlspecialchars($fullName, ENT_QUOTES) ?></strong><br><?= htmlspecialchars($companyName, ENT_QUOTES) ?></div>
        </div>
        <div class="fg" style="margin-top:14px;flex-shrink:0;">
          <label class="fl">Add a personal note <span class="inv-note-count" id="invNoteCount">(optional · 0 / 300)</span></label>
          <textarea class="fi" id="invNote" rows="3" maxlength="300" placeholder="Add a personal message to accompany this invitation…" oninput="updateInvNoteCount()"></textarea>
        </div>
      </div>
    </div><!-- /.inv-panels -->

    <div class="inv-mfoot">
      <button class="inv-cancel-btn" onclick="closeInviteModal()"><i class="fas fa-times" style="font-size:11px;margin-right:6px;"></i>Cancel</button>
      <button class="btn blue" id="invSendBtn" onclick="sendInvites()" disabled><i class="fas fa-paper-plane"></i> Send Invitation</button>
    </div>
  </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   Client-Side Filtering
   ══════════════════════════════════════════════════════════════ */
/* Country → Currency map */
const COUNTRY_CURRENCY = {
  'AF':'AFN','AL':'ALL','DZ':'DZD','AD':'EUR','AO':'AOA','AG':'XCD','AR':'ARS','AM':'AMD',
  'AU':'AUD','AT':'EUR','AZ':'AZN','BS':'BSD','BH':'BHD','BD':'BDT','BB':'BBD','BY':'BYN',
  'BE':'EUR','BZ':'BZD','BJ':'XOF','BT':'BTN','BO':'BOB','BA':'BAM','BW':'BWP','BR':'BRL',
  'BN':'BND','BG':'BGN','BF':'XOF','BI':'BIF','KH':'KHR','CM':'XAF','CA':'CAD','CV':'CVE',
  'CF':'XAF','TD':'XAF','CL':'CLP','CN':'CNY','CO':'COP','KM':'KMF','CG':'XAF','CR':'CRC',
  'HR':'EUR','CU':'CUP','CY':'EUR','CZ':'CZK','DK':'DKK','DJ':'DJF','DM':'XCD','DO':'DOP',
  'EC':'USD','EG':'EGP','SV':'USD','GQ':'XAF','ER':'ERN','EE':'EUR','ET':'ETB','FJ':'FJD',
  'FI':'EUR','FR':'EUR','GA':'XAF','GM':'GMD','GE':'GEL','DE':'EUR','GH':'GHS','GR':'EUR',
  'GD':'XCD','GT':'GTQ','GN':'GNF','GW':'XOF','GY':'GYD','HT':'HTG','HN':'HNL','HU':'HUF',
  'IS':'ISK','IN':'INR','ID':'IDR','IR':'IRR','IQ':'IQD','IE':'EUR','IL':'ILS','IT':'EUR',
  'JM':'JMD','JP':'JPY','JO':'JOD','KZ':'KZT','KE':'KES','KI':'AUD','KW':'KWD','KG':'KGS',
  'LA':'LAK','LV':'EUR','LB':'LBP','LS':'LSL','LR':'LRD','LY':'LYD','LI':'CHF','LT':'EUR',
  'LU':'EUR','MG':'MGA','MW':'MWK','MY':'MYR','MV':'MVR','ML':'XOF','MT':'EUR','MH':'USD',
  'MR':'MRU','MU':'MUR','MX':'MXN','FM':'USD','MD':'MDL','MC':'EUR','MN':'MNT','ME':'EUR',
  'MA':'MAD','MZ':'MZN','MM':'MMK','NA':'NAD','NR':'AUD','NP':'NPR','NL':'EUR','NZ':'NZD',
  'NI':'NIO','NE':'XOF','NG':'NGN','KP':'KPW','MK':'MKD','NO':'NOK','OM':'OMR','PK':'PKR',
  'PW':'USD','PS':'ILS','PA':'PAB','PG':'PGK','PY':'PYG','PE':'PEN','PH':'PHP','PL':'PLN',
  'PT':'EUR','QA':'QAR','RO':'RON','RU':'RUB','RW':'RWF','KN':'XCD','LC':'XCD','VC':'XCD',
  'WS':'WST','SM':'EUR','ST':'STN','SA':'SAR','SN':'XOF','RS':'RSD','SC':'SCR','SL':'SLL',
  'SG':'SGD','SK':'EUR','SI':'EUR','SB':'SBD','SO':'SOS','ZA':'ZAR','KR':'KRW','SS':'SSP',
  'ES':'EUR','LK':'LKR','SD':'SDG','SR':'SRD','SE':'SEK','CH':'CHF','SY':'SYP','TW':'TWD',
  'TJ':'TJS','TZ':'TZS','TH':'THB','TL':'USD','TG':'XOF','TO':'TOP','TT':'TTD','TN':'TND',
  'TR':'TRY','TM':'TMT','TV':'AUD','UG':'UGX','UA':'UAH','AE':'AED','GB':'GBP','US':'USD',
  'UY':'UYU','UZ':'UZS','VU':'VUV','VA':'EUR','VE':'VES','VN':'VND','YE':'YER','ZM':'ZMW',
  'ZW':'ZWL','HK':'HKD'
};
function onCountryChange() {
  var code = document.getElementById('fCountry').value;
  var cur = code ? (COUNTRY_CURRENCY[code] || code) : '';
  document.getElementById('lblSMin').textContent = cur ? 'Min Salary (' + cur + ')' : 'Min Salary';
  document.getElementById('lblSMax').textContent = cur ? 'Max Salary (' + cur + ')' : 'Max Salary';
}

var currentFilter = 'all';

function filterJobs(type, el) {
  document.querySelectorAll('.stat-pill').forEach(function(p){ p.classList.remove('active'); });
  if (el) el.classList.add('active');

  var sel = document.getElementById('statusFilter');
  if (type === 'all')     sel.value = 'all';
  else if (type === 'active')  sel.value = 'active';
  else if (type === 'closed')  sel.value = 'closed';
  else if (type === 'pending') sel.value = 'pending';
  else if (type === 'draft')   sel.value = 'draft';
  else if (type === 'deleted') sel.value = 'deleted';

  currentFilter = type;
  applyFilters();
}

function applyFilters() {
  var search = document.getElementById('searchInput').value.trim().toLowerCase();
  var status = document.getElementById('statusFilter').value;
  var approval = document.getElementById('approvalFilter') ? document.getElementById('approvalFilter').value : 'all';
  var cards  = document.querySelectorAll('.job-card');
  var visible = 0;

  cards.forEach(function(c) {
    var cStatus   = c.getAttribute('data-status');
    var cApproval = c.getAttribute('data-approval');
    var cDeleted  = c.getAttribute('data-deleted') === '1';
    var cTitle    = c.getAttribute('data-title') || '';
    var cLoc      = c.getAttribute('data-location') || '';
    var cSkills   = c.getAttribute('data-skills') || '';

    var show = true;

    if (status === 'deleted') {
      if (!cDeleted) show = false;
    } else {
      if (cDeleted) show = false;
      if (show && status !== 'all') {
        if (status === 'pending') {
          if (cApproval !== 'pending') show = false;
        } else if (status === 'rejected') {
          if (cApproval !== 'rejected') show = false;
        } else {
          if (cStatus !== status) show = false;
        }
      }
    }

    /* Text search */
    if (show && search) {
      var haystack = cTitle + ' ' + cLoc + ' ' + cSkills;
      if (haystack.indexOf(search) === -1) show = false;
    }

    if (show && approval !== 'all' && cApproval !== approval) {
      show = false;
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

function validateSalary(d) {
  var mn = d.salary_min !== '' ? parseFloat(d.salary_min) : null;
  var mx = d.salary_max !== '' ? parseFloat(d.salary_max) : null;
  if (mn !== null && mx !== null && mn > mx) {
    toast('Min salary cannot be greater than max salary.', 'err');
    document.getElementById('fSMin').focus();
    return false;
  }
  return true;
}

/* Submit for Approval (Active + pending) */
function submitJob() {
  var d = getFormData();
  if (!d.title) { toast('Job title required', 'err'); return; }
  if (!d.country) { toast('Please select a country to post this job.', 'err'); return; }
  if (!validateSalary(d)) return;
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
  if (!d.country) { toast('Please select a country to save this draft.', 'err'); return; }
  if (!validateSalary(d)) return;
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
      toast('Job moved to Trash', 'ok');
      setTimeout(function(){ location.reload(); }, 700);
    } else { toast(d.msg || 'Error', 'err'); }
  });
}

function restoreJob(id, title) {
  if (!confirm('Restore "' + title + '" as a Draft?')) return;
  doPost({ action: 'restore_job', job_id: id }, function(d) {
    if (d.ok) {
      toast('Job restored to Drafts', 'ok');
      setTimeout(function(){ location.reload(); }, 700);
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

/* ══ Close modals on backdrop click ══ */
['jobModal', 'confirmModal', 'inviteModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
  });
});

/* ══ Auto-open post modal if ?postjob=1 ══ */
if (new URLSearchParams(window.location.search).get('postjob') === '1') openPost();

/* ══ Auto-open edit modal if ?edit=<jobId> ══ */
(function(){
  var editId = new URLSearchParams(window.location.search).get('edit');
  if (editId) { setTimeout(function(){ editJob(parseInt(editId,10)); }, 300); }
})();

/* ══════════════════════════════════════════════════════════════
   SEND INVITE FEATURE
   ══════════════════════════════════════════════════════════════ */
var _invJobId     = null;
var _invJobTitle  = '';
var _invSelected  = {};   /* { seekerId: seekerObj } */
var _invSeekers   = [];   /* current search results */
var _invTimer     = null;
var _invSearchQuery = '';  /* Track current search query for retry */
var _invCompany   = <?= json_encode($companyName) ?>;
var _invRecruiter = <?= json_encode($fullName) ?>;

function _esc(s) {
  var d = document.createElement('div');
  d.textContent = String(s || '');
  return d.innerHTML;
}

function openInvite(jobId, jobTitle) {
  _invJobId    = jobId;
  _invJobTitle = jobTitle;
  _invSelected = {};
  document.getElementById('invModalTitle').textContent = 'Send Invite — ' + jobTitle;
  document.getElementById('invSearch').value    = '';
  document.getElementById('invNote').value      = '';
  document.getElementById('invNoteCount').textContent = '(optional · 0 / 300)';
  invUpdateCounter();
  invUpdateSendBtn();
  invRenderPreview();
  document.getElementById('inviteModal').classList.add('open');
  invLoadSeekers('');
}

function closeInviteModal() {
  document.getElementById('inviteModal').classList.remove('open');
}

function invSearchDebounce() {
  clearTimeout(_invTimer);
  _invTimer = setTimeout(function () {
    invLoadSeekers(document.getElementById('invSearch').value.trim());
  }, 320);
}

function invLoadSeekers(q) {
  _invSearchQuery = q;  /* Store for retry */
  var list = document.getElementById('invSeekerList');
  list.innerHTML = '<div class="inv-loading"><i class="fas fa-circle-notch fa-spin"></i><br>Loading jobseekers…</div>';
  fetch('../api/job_invitations.php?action=search_seekers&job_id=' + _invJobId + '&q=' + encodeURIComponent(q))
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.ok) {
        var errorMsg = data.msg || 'Could not load jobseekers';
        list.innerHTML = '<div class="inv-empty"><i class="fas fa-exclamation-circle"></i><p>' + _esc(errorMsg) + '</p><button class="btn amber" style="margin-top:10px;" onclick="invLoadSeekers(\'' + _invSearchQuery.replace(/'/g, "\\'") + '\')"><i class="fas fa-redo"></i> Try Again</button></div>';
        return;
      }
      _invSeekers = data.seekers || [];
      invRenderList();
    })
    .catch(function (err) {
      console.error('Invite load error:', err);
      list.innerHTML = '<div class="inv-empty"><i class="fas fa-wifi"></i><p>Connection error. Please check your internet and try again.</p><button class="btn amber" style="margin-top:10px;" onclick="invLoadSeekers(\'' + _invSearchQuery.replace(/'/g, "\\'") + '\')"><i class="fas fa-redo"></i> Try Again</button></div>';
    });
}

function invRenderList() {
  var list = document.getElementById('invSeekerList');
  if (!_invSeekers.length) {
    list.innerHTML = '<div class="inv-empty"><i class="fas fa-users-slash"></i><p>No jobseekers found.<br><small>Try a different search term.</small></p></div>';
    return;
  }

  var html = '';
  _invSeekers.forEach(function (s) {
    var disabled  = s.already_applied || s.invite_status === 'pending' || s.invite_status === 'accepted';
    var selected  = !!_invSelected[s.id];
    var badgeHtml = '';
    if (s.already_applied)          badgeHtml = '<span class="inv-badge applied">Already Applied</span>';
    else if (s.invite_status === 'pending')  badgeHtml = '<span class="inv-badge sent">Invite Sent</span>';
    else if (s.invite_status === 'accepted') badgeHtml = '<span class="inv-badge accepted">Accepted</span>';
    else if (s.invite_status === 'declined') badgeHtml = '<span class="inv-badge declined">Declined</span>';

    var avatarHtml = s.avatar_url
      ? '<img src="' + _esc(s.avatar_url) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">'
      : _esc(s.initials);

    var skillsHtml = (s.skills || []).slice(0, 3).map(function (sk) {
      return '<span class="inv-skill">' + _esc(sk) + '</span>';
    }).join('');

    var checkHtml = selected ? '<div class="inv-check"><i class="fas fa-check"></i></div>' : '';

    html += '<div class="inv-seeker-card' + (disabled ? ' disabled' : '') + (selected ? ' selected' : '') + '"'
      + (disabled ? '' : ' onclick="invToggle(' + s.id + ')" ')
      + 'data-id="' + s.id + '">'
      + '<div class="inv-av" style="background:' + _invAvatarColor(s.id) + '">' + avatarHtml + '</div>'
      + '<div class="inv-seeker-info">'
        + '<div class="inv-seeker-name">' + _esc(s.name) + badgeHtml + '</div>'
        + (s.headline ? '<div class="inv-seeker-hl">' + _esc(s.headline) + '</div>' : '')
        + '<div class="inv-seeker-loc"><i class="fas fa-map-marker-alt"></i> ' + _esc(s.location) + '</div>'
        + (skillsHtml ? '<div class="inv-skills-row">' + skillsHtml + '</div>' : '')
      + '</div>'
      + checkHtml
      + '</div>';
  });

  list.innerHTML = html;
}

function _invAvatarColor(id) {
  var colors = [
    'linear-gradient(135deg,#D13D2C,#7A1515)',
    'linear-gradient(135deg,#2563EB,#1D4ED8)',
    'linear-gradient(135deg,#4CAF70,#2A7040)',
    'linear-gradient(135deg,#D4943A,#8A5A10)',
    'linear-gradient(135deg,#9C27B0,#5A0080)',
  ];
  return colors[id % colors.length];
}

function invToggle(seekerId) {
  var found = _invSeekers.find(function (s) { return s.id === seekerId; });
  if (!found) return;
  if (_invSelected[seekerId]) {
    delete _invSelected[seekerId];
  } else {
    _invSelected[seekerId] = found;
  }
  invRenderList();
  invUpdateCounter();
  invRenderPreview();
  invUpdateSendBtn();
}

function invUpdateCounter() {
  var n = Object.keys(_invSelected).length;
  document.getElementById('invCounter').textContent =
    n + ' jobseeker' + (n !== 1 ? 's' : '') + ' selected';
}

function invUpdateSendBtn() {
  document.getElementById('invSendBtn').disabled = Object.keys(_invSelected).length === 0;
}

function invRenderPreview() {
  var ids        = Object.keys(_invSelected);
  var seekerName = '[Jobseeker Name]';
  if (ids.length > 0) {
    var first = _invSelected[ids[0]];
    seekerName = first ? first.name : '[Jobseeker Name]';
    if (ids.length > 1) seekerName += ' + ' + (ids.length - 1) + ' more';
  }
  document.getElementById('invPreviewCard').innerHTML =
    '<div class="inv-preview-greeting">Dear <strong>' + _esc(seekerName) + ',</strong></div>'
    + '<p>We came across your profile on AntCareers and believe you would be an excellent fit for the <strong>' + _esc(_invJobTitle) + '</strong> position at <strong>' + _esc(_invCompany) + '</strong>.</p>'
    + '<p>We would like to formally invite you to review this opportunity and consider submitting your application.</p>'
    + '<span class="inv-apply-btn">View Job Details &amp; Apply</span>'
    + '<p style="margin-top:10px;">We look forward to hearing from you.</p>'
    + '<div class="inv-preview-sig">Best regards,<br><strong>' + _esc(_invRecruiter) + '</strong><br>' + _esc(_invCompany) + '</div>';
}

function updateInvNoteCount() {
  var n = document.getElementById('invNote').value.length;
  document.getElementById('invNoteCount').textContent = '(optional · ' + n + ' / 300)';
}

function sendInvites() {
  var ids = Object.keys(_invSelected).map(Number);
  if (!ids.length) return;

  var btn = document.getElementById('invSendBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending…';

  var note = document.getElementById('invNote').value.trim();
  var fd   = new FormData();
  fd.append('action',     'send_invites');
  fd.append('job_id',     _invJobId);
  fd.append('seeker_ids', JSON.stringify(ids));
  fd.append('custom_note', note);

  fetch('../api/job_invitations.php', { method: 'POST', body: fd })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invitation';
      if (d.ok) {
        closeInviteModal();
        var n = d.sent || 0;
        toast(n === 1 ? 'Invitation sent successfully!' : n + ' invitations sent successfully!', 'ok');
      } else {
        toast(d.msg || 'Error sending invitations', 'err');
      }
    })
    .catch(function () {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invitation';
      toast('Network error — please try again', 'err');
    });
}
</script>
</body>
</html>