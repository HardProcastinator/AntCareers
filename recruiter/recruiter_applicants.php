<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('recruiter');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'applicants';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── AJAX HANDLERS ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_POST['action'] ?? '');

    /* ── update_status ── */
    if ($action === 'update_status') {
        $allowed = ['Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered'];
        $appId   = (int)($_POST['application_id'] ?? 0);
        $newS    = trim((string)($_POST['status'] ?? ''));
        if (!$appId || !in_array($newS, $allowed, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
        }
        try {
            $chk = $db->prepare("SELECT a.id, a.seeker_id, j.id AS job_id, j.title AS job_title, j.employer_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.recruiter_id=?");
            $chk->execute([$appId, $uid]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

            /* ── Offered trigger — update status, notify seeker, send offer message ── */
            if ($newS === 'Offered') {
                $db->beginTransaction();
                try {
                    $db->prepare("UPDATE applications SET status='Offered',reviewed_at=NOW() WHERE id=?")->execute([$appId]);

                    // Get seeker info
                    $su = $db->prepare("SELECT full_name, email FROM users WHERE id=?");
                    $su->execute([$row['seeker_id']]);
                    $seeker = $su->fetch(PDO::FETCH_ASSOC);
                    if (!$seeker) { $db->rollBack(); echo json_encode(['ok'=>false,'msg'=>'Seeker not found']); exit; }

                    // Send offer message to seeker via in-platform message
                    $convKey = 'direct:' . min($uid, $row['seeker_id']) . ':' . max($uid, $row['seeker_id']);
                    $cs = $db->prepare("SELECT id FROM conversations WHERE conversation_key=?");
                    $cs->execute([$convKey]);
                    $conv = $cs->fetch();
                    if ($conv) {
                        $convId = (int)$conv['id'];
                    } else {
                        $db->prepare("INSERT INTO conversations (conversation_key, participant_a_id, participant_b_id) VALUES (?,?,?)")
                           ->execute([$convKey, min($uid, $row['seeker_id']), max($uid, $row['seeker_id'])]);
                        $convId = (int)$db->lastInsertId();
                    }
                    $msgBody = "Hi {$seeker['full_name']},\n\nGreat news — you've been offered the position \"{$row['job_title']}\"!\n\nPlease review the offer details and respond at your earliest convenience. You can accept or decline this offer from your applications page.\n\nWe look forward to hearing from you!";
                    $db->prepare("INSERT INTO messages (sender_id, receiver_id, conversation_id, subject, body, is_read) VALUES (?,?,?,?,?,0)")
                       ->execute([$uid, $row['seeker_id'], $convId, 'Congratulations — You\'ve Been Offered!', $msgBody]);
                    $msgId = (int)$db->lastInsertId();
                    $db->prepare("UPDATE conversations SET latest_message_id=?, latest_message_at=NOW() WHERE id=?")
                       ->execute([$msgId, $convId]);

                    // Notification for seeker
                    $notifContent = "Congratulations! You've been offered the position \"{$row['job_title']}\". Check your messages for details.";
                    $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'offer',?,?,'application')")
                       ->execute([$row['seeker_id'], $uid, $notifContent, $appId]);

                    $db->commit();
                    echo json_encode(['ok'=>true,'status'=>'Offered']); exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log('[AntCareers] recruiter offer error: ' . $e->getMessage());
                    echo json_encode(['ok'=>false,'msg'=>'Failed to process offer: '.$e->getMessage()]); exit;
                }
            }

            $db->prepare("UPDATE applications SET status=?,reviewed_at=NOW() WHERE id=?")->execute([$newS,$appId]);

            // Notify seeker of status change
            $statusMessages = [
                'Reviewed'     => "Your application for \"{$row['job_title']}\" has been reviewed.",
                'Shortlisted'  => "Great news! Your application for \"{$row['job_title']}\" has been shortlisted.",
                'Interviewed'  => "Your application for \"{$row['job_title']}\" has progressed to interviewed.",
                'Rejected'     => "Your application for \"{$row['job_title']}\" was not successful this time.",
            ];
            if (isset($statusMessages[$newS])) {
                $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'application',?,?,'application')")
                   ->execute([$row['seeker_id'], $uid, $statusMessages[$newS], $appId]);
            }

            echo json_encode(['ok'=>true,'status'=>$newS]);
        } catch (Exception $e) {
            error_log('[AntCareers] recruiter update_status: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'DB error']);
        }
        exit;
    }

    /* ── schedule_interview ── */
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

        if ($type === 'Online' && $link === '') { echo json_encode(['ok'=>false,'msg'=>'Meeting link is required for online interviews']); exit; }
        if ($type === 'On-site') {
            if ($venueName === '') { echo json_encode(['ok'=>false,'msg'=>'Venue name is required for on-site interviews']); exit; }
            if ($fullAddress === '') { echo json_encode(['ok'=>false,'msg'=>'Full address is required for on-site interviews']); exit; }
            if ($mapLink === '') { echo json_encode(['ok'=>false,'msg'=>'Google Maps link is required for on-site interviews']); exit; }
            $location = $venueName;
        }
        if ($type === 'Phone' && $phoneNumber === '') { echo json_encode(['ok'=>false,'msg'=>'Phone number is required for phone call interviews']); exit; }

        try {
            // Auto-add columns if they don't exist
            try { $db->query("SELECT venue_name FROM interview_schedules LIMIT 0"); }
            catch (PDOException $e) {
                $db->exec("ALTER TABLE interview_schedules ADD COLUMN venue_name VARCHAR(300) DEFAULT NULL AFTER location, ADD COLUMN full_address VARCHAR(500) DEFAULT NULL AFTER venue_name, ADD COLUMN map_link VARCHAR(500) DEFAULT NULL AFTER full_address, ADD COLUMN phone_number VARCHAR(50) DEFAULT NULL AFTER map_link, ADD COLUMN contact_person VARCHAR(150) DEFAULT NULL AFTER phone_number");
            }

            $chk = $db->prepare("SELECT a.seeker_id, a.status AS current_status, j.employer_id, j.title AS job_title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.recruiter_id=?");
            $chk->execute([$appId, $uid]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok'=>false,'msg'=>'Unauthorized']); exit; }

            $existing = $db->prepare("SELECT id FROM interview_schedules WHERE application_id=? AND status='Scheduled' ORDER BY id DESC LIMIT 1");
            $existing->execute([$appId]);
            $existingId = $existing->fetchColumn();

            $ivData = [
                $dt, $type,
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
                $db->prepare("UPDATE interview_schedules SET scheduled_at=?,interview_type=?,meeting_link=?,location=?,venue_name=?,full_address=?,map_link=?,phone_number=?,contact_person=?,notes=?,updated_at=NOW() WHERE id=?")
                   ->execute(array_merge($ivData, [$existingId]));
            } else {
                $db->prepare("INSERT INTO interview_schedules (application_id,employer_id,seeker_id,scheduled_at,interview_type,meeting_link,location,venue_name,full_address,map_link,phone_number,contact_person,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute(array_merge([$appId, (int)$row['employer_id'], (int)$row['seeker_id']], $ivData));
            }

            $db->prepare("UPDATE applications SET status='Shortlisted',reviewed_at=NOW() WHERE id=? AND status IN ('Pending','Reviewed')")->execute([$appId]);

            // Notify seeker of interview scheduling + shortlist
            if (in_array($row['current_status'] ?? '', ['Pending', 'Reviewed'], true)) {
                $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'interview_invite',?,?,'application')")
                   ->execute([$row['seeker_id'], $uid, "Great news! Your application for \"{$row['job_title']}\" has been shortlisted and an interview has been scheduled.", $appId]);
            } else {
                $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'interview_invite',?,?,'application')")
                   ->execute([$row['seeker_id'], $uid, "An interview has been scheduled for your application to \"{$row['job_title']}\".", $appId]);
            }

            echo json_encode(['ok'=>true,'updated'=>(bool)$existingId]);
        } catch (Exception $e) {
            error_log('[AntCareers] recruiter schedule_interview: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'DB error']);
        }
        exit;
    }

    /* ── get_applicant ── */
    if ($action === 'get_applicant') {
        $appId = (int)($_POST['application_id'] ?? 0);
        if (!$appId) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID']); exit; }
        try {
            $st = $db->prepare("
                SELECT a.id, a.status, a.cover_letter, a.resume_url, a.applied_at,
                       u.full_name, u.email, u.avatar_url,
                       sp.headline, CONCAT_WS(', ', sp.city_name, sp.province_name) AS seeker_location,
                       sp.experience_level, sp.bio, sp.phone,
                       sp.nr_availability, sp.nr_work_types, sp.nr_right_to_work,
                       sp.nr_classification, sp.nr_salary, sp.nr_salary_period,
                       sp.professional_summary,
                       GROUP_CONCAT(DISTINCT sk.skill_name ORDER BY sk.sort_order SEPARATOR ',') AS skills,
                       sr.file_path AS resume_path, sr.original_filename AS resume_name
                FROM applications a
                JOIN jobs j ON j.id=a.job_id
                JOIN users u ON u.id=a.seeker_id
                LEFT JOIN seeker_profiles sp ON sp.user_id=u.id
                LEFT JOIN seeker_skills sk ON sk.user_id=u.id
                LEFT JOIN seeker_resumes sr ON sr.user_id=u.id AND sr.is_active=1
                WHERE a.id=? AND j.recruiter_id=?
                GROUP BY a.id
            ");
            $st->execute([$appId, $uid]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { echo json_encode(['ok'=>false,'msg'=>'Not found']); exit; }
            echo json_encode(['ok'=>true,'data'=>$r]);
        } catch (Exception $e) {
            error_log('[AntCareers] recruiter get_applicant: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'DB error']);
        }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']); exit;
}

/* ── FETCH DATA ── */
$applicants  = [];
$sCounts     = ['Pending'=>0,'Reviewed'=>0,'Shortlisted'=>0,'Interviewed'=>0,'Offered'=>0,'Rejected'=>0,'Accepted'=>0,'Declined'=>0];
$total       = 0;
$jobsList    = [];
$dbErr       = false;

try {
    // Status counts
    $sc = $db->prepare("SELECT a.status, COUNT(*) AS c FROM applications a JOIN jobs j ON j.id=a.job_id WHERE j.recruiter_id=? GROUP BY a.status");
    $sc->execute([$uid]);
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($sCounts[$r['status']])) $sCounts[$r['status']] = (int)$r['c'];
    }
    $total = array_sum($sCounts);

    // Applicants query
    $st = $db->prepare("
        SELECT a.id, a.status, a.applied_at, a.cover_letter, a.resume_url,
               u.id AS seeker_id, u.full_name, u.email, u.avatar_url,
               j.id AS job_id, j.title AS job_title, j.job_type, j.setup,
               sp.headline, CONCAT_WS(', ', sp.city_name, sp.province_name) AS seeker_location, sp.experience_level,
               sr.file_path AS resume_path,
               iv.scheduled_at AS interview_date, iv.interview_type, iv.status AS iv_status,
               iv.meeting_link AS iv_link, iv.location AS iv_location,
               iv.venue_name AS iv_venue, iv.full_address AS iv_address, iv.map_link AS iv_map,
               iv.phone_number AS iv_phone, iv.contact_person AS iv_contact, iv.notes AS iv_notes
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = a.seeker_id
        LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
        LEFT JOIN seeker_resumes sr ON sr.user_id = u.id AND sr.is_active = 1
        LEFT JOIN interview_schedules iv ON iv.application_id = a.id AND iv.status = 'Scheduled'
        WHERE j.recruiter_id = ?
        GROUP BY a.id
        ORDER BY a.applied_at DESC
    ");
    $st->execute([$uid]);
    $applicants = $st->fetchAll(PDO::FETCH_ASSOC);

    // Jobs list for filter
    $js = $db->prepare("SELECT id, title FROM jobs WHERE recruiter_id = ? ORDER BY title");
    $js->execute([$uid]);
    $jobsList = $js->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $dbErr = true;
    error_log('[AntCareers] recruiter applicants fetch: ' . $e->getMessage());
}

$applicantsJson = json_encode($applicants ?: []);
$jobsListJson   = json_encode($jobsList ?: []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <title>AntCareers — Recruiter Applicants</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --green:#4CAF70; --blue:#4A90D9;
      --font-display:'Playfair Display',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    *,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    /* ── BACKGROUND ── */
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:28px 24px 60px; position:relative; z-index:1; }
    .page-title { font-family:var(--font-display); font-size:28px; font-weight:700; color:#F5F0EE; margin-bottom:5px; }
    .page-title span { color:var(--red-bright); font-style:italic; }
    .page-sub { font-size:14px; color:var(--text-muted); margin-bottom:20px; }
    .db-warn { background:rgba(212,148,58,.1); border:1px solid rgba(212,148,58,.3); border-radius:8px; padding:10px 16px; font-size:13px; color:var(--amber); margin-bottom:16px; display:flex; align-items:center; gap:8px; }

    /* ── STATS ROW ── */
    .stats-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
    .stat-pill { display:flex; align-items:center; gap:8px; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; padding:10px 15px; cursor:pointer; transition:all 0.18s; text-decoration:none; }
    .stat-pill:hover,.stat-pill.active { border-color:rgba(209,61,44,.45); background:rgba(209,61,44,.07); }
    .sp-icon { font-size:13px; width:17px; text-align:center; }
    .sp-label { font-size:12px; font-weight:600; color:var(--text-muted); }
    .sp-count { font-size:17px; font-weight:800; color:#F5F0EE; font-family:var(--font-display); }

    /* ── TOOLBAR ── */
    .toolbar { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
    .search-bar { display:flex; align-items:center; background:var(--soil-card); border:1px solid var(--soil-line); border-radius:10px; overflow:hidden; width:100%; transition:0.25s; }
    .search-bar:focus-within { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,0.1); }
    .search-bar .si { padding:0 14px; color:var(--text-muted); font-size:14px; }
    .search-bar input { flex:1; padding:13px 0; background:none; border:none; outline:none; font-family:var(--font-body); font-size:14px; color:#F5F0EE; }
    .search-bar input::placeholder { color:var(--text-muted); }
    .filter-row { display:flex; gap:10px; }
    select.fsel { padding:13px 13px; border-radius:8px; background:var(--soil-card); border:1px solid var(--soil-line); color:var(--text-mid); font-family:var(--font-body); font-size:13px; cursor:pointer; outline:none; flex:1; }
    select.fsel:focus { border-color:var(--red-vivid); }

    /* ── APPLICANT CARDS ── */
    .app-list { display:flex; flex-direction:column; gap:10px; }
    .app-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; transition:border-color 0.2s,box-shadow 0.2s; }
    .app-card:hover { border-color:rgba(209,61,44,.4); box-shadow:0 6px 24px rgba(0,0,0,.3); }
    .app-main { display:grid; grid-template-columns:44px 1fr auto; gap:14px; padding:16px 18px; align-items:start; }
    .app-avatar { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; color:#fff; flex-shrink:0; overflow:hidden; }
    .app-avatar img { width:100%; height:100%; object-fit:cover; }
    .app-info { min-width:0; }
    .app-name { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:2px; text-decoration:none; transition:color 0.2s; display:block; }
    .app-name:hover { color:var(--red-bright); }
    .app-email { font-size:12px; color:var(--text-muted); margin-bottom:4px; }
    .app-headline { font-size:12px; color:var(--text-mid); margin-bottom:7px; }
    .app-meta { display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:12px; color:var(--text-muted); }
    .app-meta i { color:var(--red-bright); font-size:10px; }
    .app-job { font-weight:700; color:var(--red-pale); }
    .app-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0; }
    .app-date { font-size:11px; color:var(--text-muted); }
    .app-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }

    /* ── BADGES ── */
    .sbadge { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; letter-spacing:.04em; }
    .sbadge.amber { color:#D4943A; background:rgba(212,148,58,.1); border:1px solid rgba(212,148,58,.25); }
    .sbadge.blue { color:#4A90D9; background:rgba(74,144,217,.1); border:1px solid rgba(74,144,217,.2); }
    .sbadge.sblue { color:#7ab8f0; background:rgba(122,184,240,.1); border:1px solid rgba(122,184,240,.2); }
    .sbadge.purple { color:#cf8ae0; background:rgba(156,39,176,.1); border:1px solid rgba(156,39,176,.2); }
    .sbadge.green { color:#6ccf8a; background:rgba(76,175,112,.1); border:1px solid rgba(76,175,112,.2); }
    .sbadge.red { color:#ff8080; background:rgba(220,53,69,.1); border:1px solid rgba(220,53,69,.2); }
    .sbadge.muted { color:var(--text-muted); background:var(--soil-hover); border:1px solid var(--soil-line); }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); }

    /* ── BUTTONS ── */
    .btn { padding:6px 12px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:.18s; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); white-space:nowrap; }
    .btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .btn.primary { background:var(--red-vivid); border-color:var(--red-vivid); color:#fff; }
    .btn.primary:hover { background:var(--red-bright); }
    .btn.amb { border-color:rgba(212,148,58,.4); color:var(--amber); }
    .btn.amb:hover { background:rgba(212,148,58,.1); }
    .btn.green { border-color:rgba(76,175,112,.4); color:var(--green); }
    .btn.green:hover { background:rgba(76,175,112,.1); }
    .btn.danger { border-color:rgba(220,53,69,.4); color:#ff8080; }
    .btn.danger:hover { background:rgba(220,53,69,.1); }

    /* ── EXPAND PANEL ── */
    .app-expand { border-top:1px solid var(--soil-line); padding:16px 18px; display:none; background:var(--soil-hover); }
    .app-expand.open { display:block; }
    .exp-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    .etitle { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.07em; margin-bottom:7px; }
    .etitle i { color:var(--red-bright); margin-right:4px; }
    .cover-text { font-size:13px; color:var(--text-mid); line-height:1.6; white-space:pre-wrap; }
    .status-row { display:flex; align-items:center; gap:8px; margin-top:4px; }
    .status-sel { padding:7px 11px; border-radius:7px; background:var(--soil-card); border:1px solid var(--soil-line); color:#F5F0EE; font-family:var(--font-body); font-size:13px; cursor:pointer; outline:none; }
    .status-sel:focus { border-color:var(--red-vivid); }
    .save-btn { padding:7px 15px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; transition:.2s; }
    .save-btn:hover { background:var(--red-bright); }

    /* ── MODALS ── */
    .modal-bd { position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:600; display:none; align-items:flex-start; justify-content:center; padding:40px 20px; overflow-y:auto; }
    .modal-bd.open { display:flex; }
    .modal-box { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:16px; padding:26px; width:100%; max-width:520px; position:relative; margin:auto; }
    .modal-title { font-family:var(--font-display); font-size:20px; font-weight:700; color:#F5F0EE; margin-bottom:18px; display:flex; align-items:center; gap:9px; }
    .modal-close { position:absolute; top:14px; right:14px; width:28px; height:28px; border-radius:6px; border:1px solid var(--soil-line); background:transparent; color:var(--text-muted); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:13px; }
    .modal-close:hover { background:var(--soil-hover); color:#F5F0EE; }
    .fg { margin-bottom:13px; }
    .fl { font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; display:block; }
    .fi { width:100%; padding:9px 13px; border-radius:7px; background:var(--soil-hover); border:1px solid var(--soil-line); color:#F5F0EE; font-family:var(--font-body); font-size:13px; outline:none; transition:.2s; }
    .fi:focus { border-color:var(--red-vivid); box-shadow:0 0 0 3px rgba(209,61,44,.1); }
    textarea.fi { resize:vertical; min-height:72px; }
    .frow { display:grid; grid-template-columns:1fr 1fr; gap:11px; }
    .mfoot { display:flex; justify-content:flex-end; gap:9px; margin-top:18px; }

    /* ── DETAIL MODAL ── */
    .detail-section { margin-bottom:16px; }
    .detail-section:last-child { margin-bottom:0; }
    .detail-header { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .detail-avatar { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700; color:#fff; overflow:hidden; }
    .detail-avatar img { width:100%; height:100%; object-fit:cover; }
    .detail-name { font-family:var(--font-display); font-size:18px; font-weight:700; color:#F5F0EE; }
    .detail-meta { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .pm-section-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .pm-info-card { background:var(--soil-hover); border:1px solid var(--soil-line); border-radius:10px; padding:11px 14px; }
    .pm-info-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); margin-bottom:4px; }
    .pm-info-value { font-size:13px; font-weight:600; color:#F5F0EE; word-break:break-word; }
    .pm-about { font-size:13px; color:var(--text-mid); line-height:1.65; white-space:pre-wrap; }
    .pm-resume-btn { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:8px; border:1px solid rgba(76,175,112,0.3); background:rgba(76,175,112,0.08); color:#6ccf8a; font-size:12px; font-weight:700; font-family:var(--font-body); cursor:pointer; text-decoration:none; transition:0.18s; margin-top:8px; }
    .pm-resume-btn:hover { background:rgba(76,175,112,0.15); border-color:rgba(76,175,112,0.5); }
    .pm-resume-btn i { font-size:13px; }
    .pm-detail-row { font-size:12px; color:var(--text-muted); margin-top:3px; display:flex; align-items:center; gap:6px; }
    .pm-detail-row i { font-size:11px; color:var(--red-bright); }
    .person-skill-row { display:flex; flex-wrap:wrap; gap:6px; }
    .person-skill-chip { padding:4px 10px; border-radius:5px; background:var(--soil-hover); border:1px solid var(--soil-line); font-size:11px; font-weight:600; color:var(--text-mid); }
    .person-skill-empty { font-size:12px; color:var(--text-muted); font-style:italic; }
    .pm-status-badge { display:inline-block; font-size:11px; font-weight:700; padding:5px 14px; border-radius:20px; letter-spacing:.06em; text-transform:uppercase; margin-bottom:14px; }
    .pm-status-badge.seeking { background:rgba(209,61,44,0.08); border:1px solid rgba(209,61,44,0.2); color:var(--red-bright); }
    .pm-status-badge.hired { background:rgba(76,175,112,0.1); border:1px solid rgba(76,175,112,0.2); color:#6ccf8a; }
    .pm-status-badge.neutral { background:rgba(212,148,58,0.08); border:1px solid rgba(212,148,58,0.2); color:var(--amber); }
    body.light .pm-info-card { background:#F5EEEC; border-color:#E0CECA; }
    body.light .pm-info-value { color:#1A0A09; }
    body.light .pm-about { color:#4A2828; }
    body.light .pm-resume-btn { background:rgba(76,175,112,0.06); border-color:rgba(76,175,112,0.25); color:#2E7D4C; }
    @media(max-width:500px) { .pm-section-grid { grid-template-columns:1fr; } }

    /* ── EMPTY STATE ── */
    .empty { text-align:center; padding:55px 20px; color:var(--text-muted); }
    .empty i { font-size:42px; margin-bottom:12px; color:var(--soil-line); display:block; }
    .empty p { font-size:14px; }

    /* ── FOOTER ── */
    .footer { border-top:1px solid var(--soil-line); padding:20px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:15px; }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    /* ── LIGHT THEME ── */
    body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .glow-orb { opacity:0.04; }
    body.light .page-title { color:#1A0A09; }
    body.light .stat-pill { background:#fff; border-color:#E0CECA; }
    body.light .sp-count { color:#1A0A09; }
    body.light .search-bar { background:#fff; border-color:#E0CECA; }
    body.light .search-bar input { color:#1A0A09; }
    body.light select.fsel { background:#fff; border-color:#E0CECA; color:#3A2020; }
    body.light .app-card { background:#fff; border-color:#E0CECA; }
    body.light .app-name { color:#1A0A09; }
    body.light .app-expand { background:#FAF7F5; border-color:#E0CECA; }
    body.light .status-sel { background:#fff; border-color:#D4B0AB; color:#1A0A09; }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .btn { border-color:#D4B0AB; color:#5A4040; }
    body.light .btn.primary { color:#fff; }
    body.light .modal-box { background:#fff; border-color:#E0CECA; }
    body.light .modal-title { color:#1A0A09; }
    body.light .fi { background:#F5EDEB; border-color:#D4B0AB; color:#1A0A09; }
    body.light .detail-name { color:#1A0A09; }
    body.light .person-skill-chip { background:#F5EEEC; border-color:#E0CECA; color:#4A2828; }

    /* ── RESPONSIVE ── */
    @media(max-width:880px) { .nav-links { display:none; } .hamburger { display:flex; } }
    @media(max-width:760px) {
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.main-content{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .page-shell { padding:20px 16px 40px; }
      .nav-inner { padding:0 10px; }
      .profile-name,.profile-role { display:none; }
      .profile-btn { padding:6px 8px; }
      .exp-grid { grid-template-columns:1fr; }
      .app-main { grid-template-columns:44px 1fr; }
      .app-right { display:flex; flex-direction:row; flex-wrap:wrap; justify-content:flex-start; gap:6px; grid-column:1/-1; align-items:center; border-top:1px solid var(--soil-line); padding-top:10px; }
      .app-date { display:none; }
      .app-actions { flex-wrap:wrap; justify-content:flex-start; width:100%; }
      .person-skill-row{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:6px;scrollbar-width:none;padding-bottom:4px}
      .person-skill-row::-webkit-scrollbar{display:none}
      .footer { flex-direction:column; text-align:center; padding:16px; }
      .frow { grid-template-columns:1fr; }
      .pm-section-grid { grid-template-columns:1fr; }
      .detail-header { flex-direction:column; text-align:center; }
    }
    @media(max-width:480px) {
      .stats-row { gap:6px; }
      .stat-pill { padding:8px 10px; }
    }
  </style>
</head>
<body>

<div class="glow-orb glow-1"></div>
<div class="glow-orb glow-2"></div>

<?php require_once dirname(__DIR__) . '/includes/navbar_recruiter.php'; ?>

<div class="page-shell">
  <h1 class="page-title anim">Applicant <span>Pipeline</span></h1>
  <p class="page-sub anim">Review, shortlist and manage all job applicants assigned to your postings.</p>

  <?php if($dbErr): ?>
  <div class="db-warn"><i class="fas fa-exclamation-triangle"></i> Could not fetch applicant data — run <strong>sql/migration_recruiter.sql</strong> to set up tables.</div>
  <?php endif; ?>

  <!-- STATS PILLS -->
  <div class="stats-row anim" id="statsRow">
    <div class="stat-pill active" data-filter="">
      <i class="fas fa-users sp-icon" style="color:var(--red-pale)"></i>
      <span class="sp-label">All</span>
      <span class="sp-count" id="cnt-all"><?= $total ?></span>
    </div>
    <div class="stat-pill" data-filter="Pending">
      <i class="fas fa-clock sp-icon" style="color:#D4943A"></i>
      <span class="sp-label">Pending</span>
      <span class="sp-count" id="cnt-Pending"><?= $sCounts['Pending'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Shortlisted">
      <i class="fas fa-star sp-icon" style="color:#7ab8f0"></i>
      <span class="sp-label">Shortlisted</span>
      <span class="sp-count" id="cnt-Shortlisted"><?= $sCounts['Shortlisted'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Interviewed">
      <i class="fas fa-comments sp-icon" style="color:#cf8ae0"></i>
      <span class="sp-label">Interviewed</span>
      <span class="sp-count" id="cnt-Interviewed"><?= $sCounts['Interviewed'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Offered">
      <i class="fas fa-check-circle sp-icon" style="color:#6ccf8a"></i>
      <span class="sp-label">Offered</span>
      <span class="sp-count" id="cnt-Offered"><?= $sCounts['Offered'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Accepted">
      <i class="fas fa-handshake sp-icon" style="color:#6ccf8a"></i>
      <span class="sp-label">Accepted</span>
      <span class="sp-count" id="cnt-Accepted"><?= $sCounts['Accepted'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Declined">
      <i class="fas fa-times sp-icon" style="color:#ff8080"></i>
      <span class="sp-label">Declined</span>
      <span class="sp-count" id="cnt-Declined"><?= $sCounts['Declined'] ?></span>
    </div>
    <div class="stat-pill" data-filter="Rejected">
      <i class="fas fa-times-circle sp-icon" style="color:#ff8080"></i>
      <span class="sp-label">Rejected</span>
      <span class="sp-count" id="cnt-Rejected"><?= $sCounts['Rejected'] ?></span>
    </div>
  </div>

  <!-- FILTER TOOLBAR -->
  <div class="toolbar anim">
    <div class="search-bar">
      <i class="fas fa-search si"></i>
      <input type="text" id="searchInput" placeholder="Search name, email or job title…">
    </div>
    <div class="filter-row">
    <select class="fsel" id="filterJob">
      <option value="">All Jobs</option>
      <?php foreach($jobsList as $j): ?>
      <option value="<?= (int)$j['id'] ?>"><?= htmlspecialchars($j['title'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
    <select class="fsel" id="filterStatus">
      <option value="">All Statuses</option>
      <option value="Pending">Pending</option>
      <option value="Reviewed">Reviewed</option>
      <option value="Shortlisted">Shortlisted</option>
      <option value="Interviewed">Interviewed</option>
      <option value="Offered">Offered</option>
      <option value="Accepted">Accepted</option>
      <option value="Declined">Declined</option>
      <option value="Rejected">Rejected</option>
    </select>
    </div>
  </div>

  <!-- APPLICANT CARDS -->
  <div class="app-list" id="appList"></div>
</div>

<!-- INTERVIEW SCHEDULE MODAL -->
<div class="modal-bd" id="iModal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('iModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title" id="iModalTitle"><i class="fas fa-calendar-check" style="color:var(--red-bright)"></i> Schedule Interview</div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:15px;" id="iName"></p>
    <input type="hidden" id="iAppId">
    <div class="frow">
      <div class="fg"><label class="fl">Date &amp; Time <span style="color:var(--red-pale)">*</span></label><input type="datetime-local" class="fi" id="iDate"></div>
      <div class="fg"><label class="fl">Type <span style="color:var(--red-pale)">*</span></label><select class="fi" id="iType" onchange="onIvTypeChange()"><option value="Online">Online (Video)</option><option value="Phone">Phone Call</option><option value="On-site">On-site</option></select></div>
    </div>
    <div id="fieldsOnline">
      <div class="fg"><label class="fl">Meeting Link <span style="color:var(--red-pale)">*</span></label><input type="url" class="fi" id="iLink" placeholder="https://meet.google.com/… or https://zoom.us/…"></div>
    </div>
    <div id="fieldsOnsite" style="display:none;">
      <div class="fg"><label class="fl">Venue / Location Name <span style="color:var(--red-pale)">*</span></label><input type="text" class="fi" id="iVenue" placeholder="e.g. Main Office, 5th Floor"></div>
      <div class="fg"><label class="fl">Full Address <span style="color:var(--red-pale)">*</span></label><input type="text" class="fi" id="iAddress" placeholder="e.g. 123 Ayala Ave., Makati City"></div>
      <div class="fg"><label class="fl">Google Maps Link <span style="color:var(--red-pale)">*</span></label><input type="url" class="fi" id="iMapLink" placeholder="https://maps.google.com/…"></div>
    </div>
    <div id="fieldsPhone" style="display:none;">
      <div class="fg"><label class="fl">Contact Phone Number <span style="color:var(--red-pale)">*</span></label><input type="tel" class="fi" id="iPhone" placeholder="e.g. +63 917 123 4567"></div>
      <div class="fg"><label class="fl">Contact Person <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label><input type="text" class="fi" id="iContactPerson" placeholder="e.g. Maria Santos, HR Manager"></div>
    </div>
    <div class="fg"><label class="fl">Notes / Instructions <span style="color:var(--text-muted);font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label><textarea class="fi" id="iNotes" rows="3" placeholder="What should they prepare?"></textarea></div>
    <div id="iError" style="display:none;color:#ff8080;font-size:12px;font-weight:600;margin-bottom:10px;padding:8px 12px;background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.2);border-radius:6px;"></div>
    <div class="mfoot">
      <button class="btn" onclick="closeModal('iModal')">Cancel</button>
      <button class="btn primary" id="iSubmitBtn" onclick="submitInterview()"><i class="fas fa-paper-plane"></i> Send Invite</button>
    </div>
  </div>
</div>

<!-- APPLICANT DETAIL MODAL -->
<div class="modal-bd" id="dModal">
  <div class="modal-box" style="max-width:640px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;">
    <button class="modal-close" onclick="closeModal('dModal')"><i class="fas fa-times"></i></button>
    <div class="modal-title" style="flex-shrink:0;"><i class="fas fa-user" style="color:var(--red-bright)"></i> Applicant Details</div>
    <div id="dContent" style="overflow-y:auto;flex:1;min-height:0;padding-right:6px;"><div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Loading…</div></div>
  </div>
</div>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Applicant Tracking — Recruiter Portal</div>
</footer>

<script>
(function(){
  /* ── Data from PHP ── */
  const allApplicants = <?= $applicantsJson ?>;
  let currentFilter = { search:'', job:'', status:'' };
  window.interviewData = {};
  var interviewData = window.interviewData;

  const statusMeta = {
    Pending:     { cls:'amber',  icon:'fa-clock' },
    Reviewed:    { cls:'blue',   icon:'fa-eye' },
    Shortlisted: { cls:'sblue',  icon:'fa-star' },
    Interviewed: { cls:'purple', icon:'fa-comments' },
    Offered:     { cls:'green',  icon:'fa-check-circle' },
    Rejected:    { cls:'red',    icon:'fa-times-circle' },
    Accepted:    { cls:'green',  icon:'fa-handshake' },
    Declined:    { cls:'red',    icon:'fa-times' }
  };

  const avatarGradients = [
    'linear-gradient(135deg,#D13D2C,#7A1515)',
    'linear-gradient(135deg,#4A90D9,#2A6090)',
    'linear-gradient(135deg,#4CAF70,#2A7040)',
    'linear-gradient(135deg,#9C27B0,#5A1070)',
    'linear-gradient(135deg,#D4943A,#8a5010)'
  ];

  function esc(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
  }

  function getInitials(name) {
    var parts = (name || '?').trim().split(/\s+/);
    return parts.length >= 2
      ? (parts[0][0] + parts[1][0]).toUpperCase()
      : (parts[0].substring(0,2)).toUpperCase();
  }

  /* ── Render applicant cards ── */
  function render() {
    var q = currentFilter.search.toLowerCase();
    var fj = currentFilter.job;
    var fs = currentFilter.status;

    var filtered = allApplicants.filter(function(a) {
      if (fs && a.status !== fs) return false;
      if (fj && String(a.job_id) !== fj) return false;
      if (q) {
        var hay = ((a.full_name||'')+(a.email||'')+(a.job_title||'')).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      return true;
    });

    var container = document.getElementById('appList');
    if (!filtered.length) {
      container.innerHTML = '<div class="empty"><i class="fas fa-user-slash"></i><p>No applicants match your filters.</p></div>';
      return;
    }

    container.innerHTML = filtered.map(function(a, idx) {
      var sm = statusMeta[a.status] || { cls:'muted', icon:'fa-circle' };
      var ini = getInitials(a.full_name);
      var grad = avatarGradients[a.id % avatarGradients.length];
      var dA = formatDate(a.applied_at);
      var hasIv = a.interview_date && a.iv_status === 'Scheduled';
      if (hasIv) { interviewData[a.id] = {type:a.interview_type||'',date:a.interview_date||'',link:a.iv_link||'',venue:a.iv_venue||'',address:a.iv_address||'',map:a.iv_map||'',phone:a.iv_phone||'',contact:a.iv_contact||'',notes:a.iv_notes||''}; }

      var avatarHtml = a.avatar_url
        ? '<img src="../'+esc(a.avatar_url)+'" alt="">'
        : esc(ini);

      var headline = a.headline ? '<div class="app-headline">'+esc(a.headline)+'</div>' : '';
      var locChip = a.seeker_location ? ' <span><i class="fas fa-map-marker-alt"></i> '+esc(a.seeker_location)+'</span>' : '';
      var expChip = a.experience_level ? ' <span><i class="fas fa-briefcase"></i> '+esc(a.experience_level)+'</span>' : '';
      var ivChip = hasIv ? ' <span class="chip"><i class="fas fa-video" style="color:#6ccf8a;margin-right:3px"></i> Interview Scheduled</span>' : '';
      var resumeUrl = a.resume_path || a.resume_url || '';
      var resumeHref = resumeUrl ? (resumeUrl.indexOf('http')===0 || resumeUrl.indexOf('../')===0 ? resumeUrl : '../'+resumeUrl) : '';
      var resumeBtn = resumeHref ? ' <a href="'+esc(resumeHref)+'" target="_blank" class="btn"><i class="fas fa-file-alt"></i> Resume</a>' : '';

      var nextStatus = {Pending:'Shortlisted',Reviewed:'Shortlisted',Shortlisted:'Interviewed',Interviewed:'Offered'};
      var next = nextStatus[a.status];
      var advanceBtn = next ? '<button class="btn green" onclick="updateStatus('+a.id+',\''+next+'\')"><i class="fas fa-arrow-right"></i> '+next+'</button>' : '';
      var rejectBtn = a.status !== 'Rejected' && a.status !== 'Offered' ? '<button class="btn danger" onclick="updateStatus('+a.id+',\'Rejected\')"><i class="fas fa-times"></i> Reject</button>' : '';

      return '<div class="app-card" id="card-'+a.id+'" data-status="'+esc(a.status)+'" data-job="'+a.job_id+'" data-name="'+esc((a.full_name||'').toLowerCase())+'" data-email="'+esc((a.email||'').toLowerCase())+'" data-jobtitle="'+esc((a.job_title||'').toLowerCase())+'" style="animation:fadeUp 0.3s '+((idx*0.03))+'s both ease;">'
        +'<div class="app-main">'
        +'<div class="app-avatar" style="background:'+grad+'">'+avatarHtml+'</div>'
        +'<div class="app-info">'
        +'<a class="app-name" href="javascript:void(0)" onclick="viewApplicant('+a.id+')">'+esc(a.full_name)+'</a>'
        +'<div class="app-email">'+esc(a.email)+'</div>'
        +headline
        +'<div class="app-meta">'
        +'<span><i class="fas fa-briefcase"></i> <span class="app-job">'+esc(a.job_title)+'</span></span>'
        +'<span><i class="fas fa-calendar-alt"></i> Applied '+dA+'</span>'
        +locChip+expChip+ivChip
        +' <span class="sbadge '+sm.cls+'" id="badge-'+a.id+'"><i class="fas '+sm.icon+'"></i> '+esc(a.status)+'</span>'
        +'</div></div>'
        +'<div class="app-right">'
        +'<span class="app-date">'+dA+'</span>'
        +'<div class="app-actions">'
        +'<button class="btn" onclick="viewApplicant('+a.id+')"><i class="fas fa-eye"></i> View</button>'
        +'<button class="btn" onclick="toggleExp('+a.id+')"><i class="fas fa-chevron-down" id="chev-'+a.id+'"></i> Review</button>'
        +'<button class="btn amb" onclick="openInterview('+a.id+',\''+esc(a.full_name).replace(/'/g,"\\'")+'\','+(hasIv?'interviewData['+a.id+']':'null')+')"><i class="fas '+(hasIv?'fa-edit':'fa-calendar-plus')+'"></i> '+(hasIv?'Edit Interview':'Schedule')+'</button>'
        +resumeBtn
        +'</div></div></div>'
        +'<div class="app-expand" id="exp-'+a.id+'">'
        +'<div class="exp-grid"><div>'
        +'<div class="etitle"><i class="fas fa-envelope-open-text"></i> Cover Letter</div>'
        +(a.cover_letter ? '<p class="cover-text">'+esc(a.cover_letter).replace(/\n/g,'<br>')+'</p>' : '<p class="cover-text" style="color:var(--text-muted);font-style:italic">No cover letter provided.</p>')
        +'</div><div>'
        +'<div class="etitle"><i class="fas fa-tasks"></i> Update Status</div>'
        +'<div class="status-row">'
        +'<select class="status-sel" id="sel-'+a.id+'">'
        +['Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered'].map(function(s){return '<option value="'+s+'"'+(s===a.status?' selected':'')+'>'+s+'</option>';}).join('')
        +'</select>'
        +'<button class="save-btn" onclick="saveStatus('+a.id+')">Save</button>'
        +'</div></div></div></div>'
        +'</div>';
    }).join('');
  }

  function formatDate(d) {
    if (!d) return '—';
    var dt = new Date(d.replace(' ','T'));
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[dt.getMonth()]+' '+dt.getDate()+', '+dt.getFullYear();
  }

  /* ── Filter handlers ── */
  document.getElementById('searchInput').addEventListener('input', function(){ currentFilter.search = this.value; render(); });
  document.getElementById('filterJob').addEventListener('change', function(){ currentFilter.job = this.value; render(); });
  document.getElementById('filterStatus').addEventListener('change', function(){
    currentFilter.status = this.value;
    updateStatPills(this.value);
    render();
  });

  // Stats pills click filtering
  document.querySelectorAll('.stat-pill').forEach(function(pill){
    pill.addEventListener('click', function(){
      var f = this.getAttribute('data-filter');
      currentFilter.status = f;
      document.getElementById('filterStatus').value = f;
      updateStatPills(f);
      render();
    });
  });

  function updateStatPills(active) {
    document.querySelectorAll('.stat-pill').forEach(function(p){
      p.classList.toggle('active', p.getAttribute('data-filter') === active);
    });
  }

  /* ── AJAX helper ── */
  function doPost(data, cb) {
    var body = Object.keys(data).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(data[k]); }).join('&');
    fetch('recruiter_applicants.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:body
    }).then(function(r){ return r.json(); }).then(cb).catch(function(){ toast('Network error','err'); });
  }

  /* ── Status update ── */
  window.updateStatus = function(id, status) {
    if (status === 'Offered') {
      if (!confirm('Send an offer to this applicant? They will be notified via message and can accept or decline from their applications page.')) return;
    }
    if (status === 'Rejected') {
      if (!confirm('Reject this applicant?')) return;
    }
    doPost({action:'update_status', application_id:id, status:status}, function(d){
      if (d.ok) {
        // Update local data
        for (var i=0;i<allApplicants.length;i++) {
          if (allApplicants[i].id == id) { allApplicants[i].status = d.status; break; }
        }
        recountStats();
        render();
        toast('Status updated to '+d.status,'ok');
      } else { toast(d.msg||'Error','err'); }
    });
  };

  window.saveStatus = function(id) {
    var s = document.getElementById('sel-'+id).value;
    window.updateStatus(id, s);
  };

  function recountStats() {
    var counts = {Pending:0,Reviewed:0,Shortlisted:0,Interviewed:0,Offered:0,Accepted:0,Declined:0,Rejected:0};
    allApplicants.forEach(function(a){ if(counts.hasOwnProperty(a.status)) counts[a.status]++; });
    var total = 0; for(var k in counts) total += counts[k];
    var el = document.getElementById('cnt-all'); if(el) el.textContent = total;
    for(var k in counts) { el = document.getElementById('cnt-'+k); if(el) el.textContent = counts[k]; }
  }

  /* ── Interview modal ── */
  window.openInterview = function(id, name, existingData) {
    document.getElementById('iAppId').value = id;
    document.getElementById('iName').textContent = 'Applicant: '+name;
    var errEl = document.getElementById('iError'); errEl.style.display='none';

    var isEdit = existingData && existingData.type;
    document.getElementById('iModalTitle').innerHTML = '<i class="fas fa-calendar-check" style="color:var(--red-bright)"></i> '+(isEdit?'Edit Interview':'Schedule Interview');
    var btn = document.getElementById('iSubmitBtn');
    btn.innerHTML = isEdit ? '<i class="fas fa-save"></i> Update Interview' : '<i class="fas fa-paper-plane"></i> Send Invite';

    if (isEdit) {
      var d = existingData.date ? existingData.date.replace(' ','T') : '';
      if (d && d.length > 16) d = d.slice(0,16);
      document.getElementById('iDate').value = d||'';
      document.getElementById('iType').value = existingData.type||'Online';
      document.getElementById('iLink').value = existingData.link||'';
      document.getElementById('iVenue').value = existingData.venue||'';
      document.getElementById('iAddress').value = existingData.address||'';
      document.getElementById('iMapLink').value = existingData.map||'';
      document.getElementById('iPhone').value = existingData.phone||'';
      document.getElementById('iContactPerson').value = existingData.contact||'';
      document.getElementById('iNotes').value = existingData.notes||'';
    } else {
      var nd = new Date(); nd.setDate(nd.getDate()+1); nd.setHours(10,0,0,0);
      var _p=function(n){return String(n).padStart(2,'0');};
      document.getElementById('iDate').value = nd.getFullYear()+'-'+_p(nd.getMonth()+1)+'-'+_p(nd.getDate())+'T'+_p(nd.getHours())+':'+_p(nd.getMinutes());
      document.getElementById('iType').value = 'Online';
      document.getElementById('iLink').value = '';
      document.getElementById('iVenue').value = '';
      document.getElementById('iAddress').value = '';
      document.getElementById('iMapLink').value = '';
      document.getElementById('iPhone').value = '';
      document.getElementById('iContactPerson').value = '';
      document.getElementById('iNotes').value = '';
    }
    var _now=new Date(),_p2=function(n){return String(n).padStart(2,'0');};
    document.getElementById('iDate').min=_now.getFullYear()+'-'+_p2(_now.getMonth()+1)+'-'+_p2(_now.getDate())+'T'+_p2(_now.getHours())+':'+_p2(_now.getMinutes());
    onIvTypeChange();
    document.getElementById('iModal').classList.add('open');
  };

  window.onIvTypeChange = function() {
    var type = document.getElementById('iType').value;
    document.getElementById('fieldsOnline').style.display = type==='Online' ? 'block':'none';
    document.getElementById('fieldsOnsite').style.display = type==='On-site' ? 'block':'none';
    document.getElementById('fieldsPhone').style.display  = type==='Phone' ? 'block':'none';
    document.getElementById('iError').style.display = 'none';
  };

  function showIvError(msg) {
    var el = document.getElementById('iError');
    el.textContent = msg; el.style.display = 'block';
  }

  window.submitInterview = function() {
    var dt = document.getElementById('iDate').value;
    var type = document.getElementById('iType').value;
    document.getElementById('iError').style.display = 'none';

    if (!dt) { showIvError('Please select a date and time.'); return; }

    var post = { action:'schedule_interview', application_id:document.getElementById('iAppId').value, scheduled_at:dt, interview_type:type, notes:document.getElementById('iNotes').value };

    if (type === 'Online') {
      var link = document.getElementById('iLink').value.trim();
      if (!link) { showIvError('Meeting link is required for online interviews.'); return; }
      if (!/^https?:\/\/.+/i.test(link)) { showIvError('Please enter a valid meeting link.'); return; }
      post.meeting_link = link;
    } else if (type === 'On-site') {
      var venue = document.getElementById('iVenue').value.trim();
      var addr = document.getElementById('iAddress').value.trim();
      var mapLink = document.getElementById('iMapLink').value.trim();
      if (!venue) { showIvError('Venue name is required for on-site interviews.'); return; }
      if (!addr) { showIvError('Full address is required for on-site interviews.'); return; }
      if (!mapLink) { showIvError('Google Maps link is required for on-site interviews.'); return; }
      if (!/^https?:\/\/.+/i.test(mapLink)) { showIvError('Please enter a valid Google Maps link.'); return; }
      post.venue_name = venue; post.full_address = addr; post.map_link = mapLink;
    } else if (type === 'Phone') {
      var phone = document.getElementById('iPhone').value.trim();
      if (!phone) { showIvError('Phone number is required for phone call interviews.'); return; }
      post.phone_number = phone;
      post.contact_person = document.getElementById('iContactPerson').value.trim();
    }

    var btn = document.getElementById('iSubmitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scheduling…';

    doPost(post, function(d){
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invite';
      if (d.ok) {
        closeModal('iModal');
        toast(d.updated ? 'Interview updated!' : 'Interview scheduled!', 'ok');
        setTimeout(function(){ location.reload(); }, 1200);
      } else { showIvError(d.msg || 'Error scheduling interview'); }
    });
  };

  /* ── Toggle expand panel ── */
  window.toggleExp = function(id) {
    var p = document.getElementById('exp-'+id);
    var c = document.getElementById('chev-'+id);
    var o = p.classList.toggle('open');
    if (c) c.style.transform = o ? 'rotate(180deg)' : '';
  };

  /* ── View applicant detail ── */
  window.viewApplicant = function(id) {
    document.getElementById('dContent').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    document.getElementById('dModal').classList.add('open');

    doPost({action:'get_applicant', application_id:id}, function(d){
      if (!d.ok) { document.getElementById('dContent').innerHTML = '<p style="color:#ff8080">'+esc(d.msg||'Error loading details')+'</p>'; return; }
      var a = d.data;
      var ini = getInitials(a.full_name);
      var grad = avatarGradients[id % avatarGradients.length];
      var avatarHtml = a.avatar_url
        ? '<img src="../'+esc(a.avatar_url)+'" alt="">'
        : esc(ini);

      var html = '<div class="detail-header">'
        +'<div class="detail-avatar" style="background:'+grad+'">'+avatarHtml+'</div>'
        +'<div><div class="detail-name">'+esc(a.full_name)+'</div>'
        +'<div class="detail-meta">'+esc(a.email)+'</div>'
        +(a.headline ? '<div class="detail-meta" style="color:var(--text-mid)">'+esc(a.headline)+'</div>' : '')
        +(a.phone ? '<div class="pm-detail-row"><i class="fas fa-phone"></i> '+esc(a.phone)+'</div>' : '')
        +'</div></div>';

      var statusMap = {Pending:'neutral',Reviewed:'neutral',Shortlisted:'hired',Interviewed:'hired',Offered:'hired',Rejected:'seeking'};
      html += '<div class="pm-status-badge '+(statusMap[a.status]||'neutral')+'">'+esc(a.status)+'</div>';

      var aboutText = a.professional_summary || a.bio || '';
      if (aboutText) {
        html += '<div class="detail-section"><div class="etitle"><i class="fas fa-user-circle"></i> About</div><div class="pm-about">'+esc(aboutText)+'</div></div>';
      }

      var infoCards = '';
      if (a.experience_level) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Experience</div><div class="pm-info-value">'+esc(a.experience_level)+'</div></div>';
      if (a.nr_availability) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Availability</div><div class="pm-info-value">'+esc(a.nr_availability)+'</div></div>';
      if (a.nr_work_types) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Work Type</div><div class="pm-info-value">'+esc(a.nr_work_types)+'</div></div>';
      if (a.nr_right_to_work) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Right to Work</div><div class="pm-info-value">'+esc(a.nr_right_to_work)+'</div></div>';
      if (a.nr_classification) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Classification</div><div class="pm-info-value">'+esc(a.nr_classification)+'</div></div>';
      if (a.seeker_location) infoCards += '<div class="pm-info-card"><div class="pm-info-label">Location</div><div class="pm-info-value">'+esc(a.seeker_location)+'</div></div>';
      if (a.nr_salary) {
        var salaryDisplay = a.nr_salary + (a.nr_salary_period ? ' ' + a.nr_salary_period : '');
        infoCards += '<div class="pm-info-card"><div class="pm-info-label">Salary Expectation</div><div class="pm-info-value">'+esc(salaryDisplay)+'</div></div>';
      }
      infoCards += '<div class="pm-info-card"><div class="pm-info-label">Applied</div><div class="pm-info-value">'+esc(a.applied_at ? new Date(a.applied_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—')+'</div></div>';
      if (infoCards) html += '<div class="detail-section"><div class="etitle"><i class="fas fa-info-circle"></i> Details</div><div class="pm-section-grid">'+infoCards+'</div></div>';

      var skills = (a.skills||'').split(',').filter(Boolean);
      if (skills.length) {
        html += '<div class="detail-section"><div class="etitle"><i class="fas fa-tags"></i> Skills</div><div class="person-skill-row">'+skills.map(function(s){return '<span class="person-skill-chip">'+esc(s.trim())+'</span>';}).join('')+'</div></div>';
      }

      if (a.cover_letter) {
        html += '<div class="detail-section"><div class="etitle"><i class="fas fa-envelope-open-text"></i> Cover Letter</div><div class="pm-about">'+esc(a.cover_letter).replace(/\n/g,'<br>')+'</div></div>';
      }

      var resumeUrl = a.resume_path || a.resume_url || '';
      var resumeName = a.resume_name || 'Download Resume';
      if (resumeUrl) {
        var href = resumeUrl.startsWith('http')||resumeUrl.startsWith('../') ? resumeUrl : '../'+resumeUrl;
        html += '<div class="detail-section"><a class="pm-resume-btn" href="'+esc(href)+'" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> '+esc(resumeName)+'</a></div>';
      }

      document.getElementById('dContent').innerHTML = html;
    });
  };

  /* ── Modal helpers ── */
  window.closeModal = function(id) { document.getElementById(id).classList.remove('open'); };
  document.querySelectorAll('.modal-bd').forEach(function(m){
    m.addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
  });

  /* ── Greeting ── */
  var h = new Date().getHours();
  var greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  var greetEl = document.getElementById('greetingText');
  if (greetEl) greetEl.textContent = greet;

  /* ── Initial render ── */
  render();

  /* ── Auto-open applicant detail if ?view=<appId> ── */
  var _autoView = new URLSearchParams(window.location.search).get('view');
  if (_autoView) { setTimeout(function(){ viewApplicant(parseInt(_autoView,10)); }, 350); }
})();
</script>
</body>
</html>
