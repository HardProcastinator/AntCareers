<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin('employer');
$user        = getUser();
$fullName    = $user['fullName'];
$firstName   = $user['firstName'];
$initials    = $user['initials'];
$avatarUrl   = $user['avatarUrl'];
$companyName = $user['companyName'] ?: 'Your Company';
$navActive   = 'applicants';

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $allowed = ['Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered'];
    $action  = (string)($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $newS  = trim((string)($_POST['status'] ?? ''));
        if (!$appId || !in_array($newS, $allowed, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Invalid input']); exit;
        }
        try {
            $db  = getDB();
            $uid = (int)$_SESSION['user_id'];
            $chk = $db->prepare("SELECT a.id, a.seeker_id, j.id AS job_id, j.title AS job_title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.employer_id=?");
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

                    logActivity($row['seeker_id'], $uid, 'application_status_changed', 'application', $appId, "Status changed to Offered for job \"{$row['job_title']}\".");
                    $db->commit();
                    echo json_encode(['ok'=>true,'status'=>'Offered']); exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log('[AntCareers] employer offer error: ' . $e->getMessage());
                    echo json_encode(['ok'=>false,'msg'=>'Failed to process offer: '.$e->getMessage()]); exit;
                }
            }

            $db->prepare("UPDATE applications SET status=?,reviewed_at=NOW() WHERE id=?")->execute([$newS,$appId]);
            logActivity($row['seeker_id'], $uid, 'application_status_changed', 'application', $appId, "Status changed to {$newS} for job \"{$row['job_title']}\".");

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

            $chk = $db->prepare("SELECT a.seeker_id, a.status AS current_status, j.title AS job_title FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND j.employer_id=?");
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
            logActivity($row['seeker_id'], (int)$_SESSION['user_id'], 'application_status_changed', 'application', $appId, "Shortlisted via interview scheduling for job \"{$row['job_title']}\".");

            // Notify seeker of interview scheduling + shortlist
            if (in_array($row['current_status'] ?? '', ['Pending', 'Reviewed'], true)) {
                $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'interview_invite',?,?,'application')")
                   ->execute([$row['seeker_id'], (int)$_SESSION['user_id'], "Great news! Your application for \"{$row['job_title']}\" has been shortlisted and an interview has been scheduled.", $appId]);
            } else {
                $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (?,?,'interview_invite',?,?,'application')")
                   ->execute([$row['seeker_id'], (int)$_SESSION['user_id'], "An interview has been scheduled for your application to \"{$row['job_title']}\".", $appId]);
            }

            echo json_encode(['ok'=>true,'updated'=>(bool)$existingId]);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>'DB error: '.$e->getMessage()]); }
        exit;
    }

    /* ── get_applicant ── */
    if ($action === 'get_applicant') {
        $appId = (int)($_POST['application_id'] ?? 0);
        if (!$appId) { echo json_encode(['ok'=>false,'msg'=>'Invalid ID']); exit; }
        try {
            $db  = getDB();
            $uid = (int)$_SESSION['user_id'];
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
                WHERE a.id=? AND j.employer_id=?
                GROUP BY a.id
            ");
            $st->execute([$appId, $uid]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (!$r) { echo json_encode(['ok'=>false,'msg'=>'Not found']); exit; }
            echo json_encode(['ok'=>true,'data'=>$r]);
        } catch (Exception $e) {
            error_log('[AntCareers] employer get_applicant: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'DB error']);
        }
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
$sCounts      = ['Pending'=>0,'Reviewed'=>0,'Shortlisted'=>0,'Interviewed'=>0,'Rejected'=>0,'Offered'=>0,'Accepted'=>0,'Declined'=>0];
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

    $st=$db->prepare("SELECT a.id AS app_id,a.status,a.cover_letter,a.resume_url,a.applied_at,a.reviewed_at,a.employer_notes,u.id AS seeker_id,u.full_name AS seeker_name,u.email AS seeker_email,u.avatar_url AS seeker_avatar,j.id AS job_id,j.title AS job_title,j.job_type,j.setup,j.location AS job_location,sr.file_path AS resume_path,(SELECT COUNT(*) FROM interview_schedules i WHERE i.application_id=a.id AND i.status='Scheduled') AS has_interview,iv_cur.interview_type AS iv_type,iv_cur.scheduled_at AS iv_date,iv_cur.meeting_link AS iv_link,iv_cur.location AS iv_location,iv_cur.venue_name AS iv_venue,iv_cur.full_address AS iv_address,iv_cur.map_link AS iv_map,iv_cur.phone_number AS iv_phone,iv_cur.contact_person AS iv_contact,iv_cur.notes AS iv_notes FROM applications a JOIN jobs j ON j.id=a.job_id JOIN users u ON u.id=a.seeker_id LEFT JOIN seeker_resumes sr ON sr.user_id=u.id AND sr.is_active=1 LEFT JOIN interview_schedules iv_cur ON iv_cur.application_id=a.id AND iv_cur.status='Scheduled' WHERE j.employer_id=? GROUP BY a.id ORDER BY FIELD(a.status,'Pending','Reviewed','Shortlisted','Offered','Rejected'),a.applied_at DESC");
    $st->execute([$uid]);
    $applicants=$st->fetchAll(PDO::FETCH_ASSOC);
    $js=$db->prepare("SELECT id,title FROM jobs WHERE employer_id=? AND status='Active' ORDER BY created_at DESC");
    $js->execute([$uid]);
    $jobsList=$js->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dbErr=true;
    error_log('[AntCareers] applicants fetch: '.$e->getMessage());
}

$smeta=['Pending'=>['c'=>'amber','i'=>'fa-clock'],'Reviewed'=>['c'=>'blue','i'=>'fa-eye'],'Shortlisted'=>['c'=>'green','i'=>'fa-star'],'Interviewed'=>['c'=>'blue','i'=>'fa-video'],'Rejected'=>['c'=>'red','i'=>'fa-times-circle'],'Offered'=>['c'=>'purple','i'=>'fa-check-circle'],'Accepted'=>['c'=>'green','i'=>'fa-handshake'],'Declined'=>['c'=>'red','i'=>'fa-times']];
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
    .notif-btn-nav{position:relative;width:36px;height:36px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:0.2s;font-size:14px;color:var(--text-muted);flex-shrink:0;}
    .notif-btn-nav:hover{color:var(--red-pale);border-color:var(--red-vivid);}
    .notif-btn-nav .badge{position:absolute;top:-5px;right:-5px;min-width:17px;height:17px;border-radius:50%;background:var(--red-vivid);color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--soil-dark);overflow:hidden;padding:0 3px;}
    .badge{position:absolute;top:-4px;right:-4px;background:var(--red-vivid);color:#fff;font-size:9px;font-weight:700;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;}
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
    body.light{background:#FAF7F5;color:#1A0A09;--soil-dark:#F9F5F4;--soil-card:#FFFFFF;--soil-hover:#FEF0EE;--soil-line:#E0CECA;--text-light:#1A0A09;--text-mid:#4A2828;--text-muted:#7A5555;--amber-dim:#FFF4E0;--amber:#B8620A;}
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
    .search-bar .si{padding:0 14px;color:var(--text-muted);font-size:14px;}
    .search-bar input{flex:1;padding:13px 0;background:none;border:none;outline:none;font-family:var(--font-body);font-size:14px;color:#F5F0EE;}
    .search-bar input::placeholder{color:var(--text-muted);}
    body.light .search-bar{background:#fff;border-color:#E0CECA;}
    body.light .search-bar input{color:#1A0A09;}
    select.fsel{padding:13px 13px;border-radius:8px;background:var(--soil-card);border:1px solid var(--soil-line);color:var(--text-mid);font-family:var(--font-body);font-size:13px;cursor:pointer;outline:none;}
    select.fsel:focus{border-color:var(--red-vivid);}
    body.light select.fsel{background:#fff;border-color:#E0CECA;color:#3A2020;}
    .app-list{display:flex;flex-direction:column;gap:10px;}
    /* ── Content layout (sidebar + main) ── */
    .content-layout{display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start;}
    @media(max-width:900px){.content-layout{grid-template-columns:1fr;}}
    /* ── Filter sidebar ── */
    .filter-sidebar{position:sticky;top:72px;background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;padding:18px;}
    body.light .filter-sidebar{background:#fff;border-color:#E0CECA;}
    .fs-title{font-size:12px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px;display:flex;align-items:center;gap:7px;}
    .fs-title i{color:var(--red-bright);font-size:12px;}
    .fs-section{margin-bottom:14px;}
    .fs-section-label{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;}
    .filter-sidebar .search-bar{margin-bottom:0;}
    .filter-sidebar .search-bar input{padding:10px 0;font-size:13px;}
    /* ── Sidebar select dropdowns ── */
    .fs-select{width:100%;padding:9px 13px;border-radius:7px;background:var(--soil-hover);border:1px solid var(--soil-line);color:var(--text-mid);font-family:var(--font-body);font-size:13px;cursor:pointer;outline:none;transition:.2s;}
    .fs-select:focus{border-color:var(--red-vivid);box-shadow:0 0 0 3px rgba(209,61,44,.1);}
    body.light .fs-select{background:#F5EDEB;border-color:#D4B0AB;color:#3A2020;}
    body.light .fs-select option{background:#F5EDEB;color:#1A0A09;}
    .app-card{background:var(--soil-card);border:1px solid var(--soil-line);border-radius:12px;overflow:hidden;transition:border-color 0.2s,box-shadow 0.2s;}
    .app-card:hover{border-color:rgba(209,61,44,.4);box-shadow:0 6px 24px rgba(0,0,0,.3);}
    body.light .app-card{background:#fff;border-color:#E0CECA;}
    .app-main{display:grid;grid-template-columns:44px 1fr auto;gap:14px;padding:16px 18px;align-items:start;}
    .app-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--red-vivid),var(--red-deep));display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;}
    .app-avatar img{width:100%;height:100%;object-fit:cover;}
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
    .toolbar .btn.primary{padding:13px 24px;border-radius:10px;font-size:13px;}
    .btn.amb{border-color:rgba(212,148,58,.4);color:var(--amber);}
    .btn.amb:hover{background:rgba(212,148,58,.1);}
    body.light .btn{border-color:#D4B0AB;color:#5A4040;}
    body.light .btn.primary{color:#fff;}
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
    /* ── DETAIL MODAL ── */
    .detail-section { margin-bottom:16px; }
    .detail-section:last-child { margin-bottom:0; }
    .detail-header { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .detail-avatar { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:700; color:#fff; overflow:hidden; }
    .detail-avatar img { width:100%; height:100%; object-fit:cover; }
    .detail-name { font-family:var(--font-display); font-size:18px; font-weight:700; color:#F5F0EE; }
    .detail-meta { font-size:12px; color:var(--text-muted); margin-top:2px; }
    body.light .detail-name { color:#1A0A09; }
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
    .empty{text-align:center;padding:55px 20px;color:var(--text-muted);}
    .empty i{font-size:42px;margin-bottom:12px;color:var(--soil-line);display:block;}
    .empty p{font-size:14px;}
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

  <!-- STATS PILLS -->
  <div class="stats-row" id="statsRow">
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
  <div class="toolbar">
    <div class="search-bar">
      <i class="fas fa-search si"></i>
      <input type="text" id="searchInput" placeholder="Search name, email or job title…">
    </div>
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

  <!-- APPLICANT CARDS -->
  <div class="app-list" id="appList">
  <div class="empty" id="emptyMsg" style="<?=empty($applicants)?'':'display:none'?>"><i class="fas fa-user-slash"></i><p>No applicants match your filters.</p></div>
  <?php foreach($applicants as $a):
    $ini=strtoupper(substr($a['seeker_name'],0,1));
    $sm=$smeta[$a['status']]??['c'=>'muted','i'=>'fa-circle'];
    $dA=date('M j, Y',strtotime($a['applied_at']));
    $dR=$a['reviewed_at']?date('M j, Y',strtotime($a['reviewed_at'])):'—';
  ?>
  <div class="app-card" id="card-<?=$a['app_id']?>" data-status="<?=htmlspecialchars($a['status'])?>" data-job="<?=$a['job_id']?>" data-name="<?=htmlspecialchars(strtolower($a['seeker_name']))?>" data-email="<?=htmlspecialchars(strtolower($a['seeker_email']))?>" data-jobtitle="<?=htmlspecialchars(strtolower($a['job_title']))?>">
    <div class="app-main">
      <a href="employer_view_applicant.php?id=<?=$a['seeker_id']?>" class="app-avatar" style="text-decoration:none;color:#fff;"><?php if(!empty($a['seeker_avatar'])):?><img src="../<?=htmlspecialchars($a['seeker_avatar'])?>" alt=""><?php else:?><?=htmlspecialchars($ini)?><?php endif;?></a>
      <div class="app-info">
        <a href="javascript:void(0)" onclick="viewApplicant(<?=$a['app_id']?>)" class="app-name" style="text-decoration:none;color:inherit;"><?=htmlspecialchars($a['seeker_name'])?></a>
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
          <button class="btn" onclick="viewApplicant(<?=$a['app_id']?>)"><i class="fas fa-eye"></i> View</button>
          <button class="btn" onclick="toggleExp(<?=$a['app_id']?>)"><i class="fas fa-chevron-down" id="chev-<?=$a['app_id']?>"></i> Review</button>
          <button class="btn amb" onclick="openInterview(<?=$a['app_id']?>,'<?=htmlspecialchars($a['seeker_name'],ENT_QUOTES)?>',<?=htmlspecialchars(json_encode($a['has_interview'] ? ['type'=>$a['iv_type']??'','date'=>$a['iv_date']??'','link'=>$a['iv_link']??'','venue'=>$a['iv_venue']??'','address'=>$a['iv_address']??'','map'=>$a['iv_map']??'','phone'=>$a['iv_phone']??'','contact'=>$a['iv_contact']??'','notes'=>$a['iv_notes']??''] : null),ENT_QUOTES,'UTF-8')?>)"><i class="fas <?=$a['has_interview']?'fa-edit':'fa-calendar-plus'?>"></i> <?=$a['has_interview']?'Edit Interview':'Schedule'?></button>
          <?php $resumeHref=$a['resume_path']?:$a['resume_url']?:''; if($resumeHref):$resumeHref=(strpos($resumeHref,'http')===0||strpos($resumeHref,'../')===0)?$resumeHref:'../'.$resumeHref;?><a href="<?=htmlspecialchars($resumeHref)?>" target="_blank" class="btn"><i class="fas fa-file-alt"></i> Resume</a><?php endif;?>
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
              <?php foreach(['Pending','Reviewed','Shortlisted','Interviewed','Rejected','Offered'] as $s):?>
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
  <?php endforeach;?>
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


<!-- APPLICANT DETAIL MODAL -->
<div class="modal-bd" id="dModal">
  <div class="modal-box" style="max-width:640px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column;">
    <button class="modal-close" onclick="document.getElementById('dModal').classList.remove('open')"><i class="fas fa-times"></i></button>
    <div id="dContent" style="overflow-y:auto;flex:1;min-height:0;padding-right:6px;"><div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Loading…</div></div>
  </div>
</div>

<script>
  function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
  function toggleExp(id){var p=document.getElementById('exp-'+id),c=document.getElementById('chev-'+id),o=p.classList.toggle('open');c.style.transform=o?'rotate(180deg)':'';}
  function saveStatus(id){var s=document.getElementById('sel-'+id).value;if(s==='Offered'&&!confirm('Send an offer to this applicant? They will be notified via message and can accept or decline.'))return;doPost({action:'update_status',application_id:id,status:s},function(d){if(d.ok){var b=document.getElementById('badge-'+id),m={Pending:{c:'amber',i:'fa-clock'},Reviewed:{c:'blue',i:'fa-eye'},Shortlisted:{c:'green',i:'fa-star'},Interviewed:{c:'blue',i:'fa-video'},Rejected:{c:'red',i:'fa-times-circle'},Offered:{c:'purple',i:'fa-check-circle'},Accepted:{c:'green',i:'fa-handshake'},Declined:{c:'red',i:'fa-times'}}[d.status]||{c:'muted',i:'fa-circle'};b.className='sbadge '+m.c;b.innerHTML='<i class="fas '+m.i+'"></i> '+d.status;document.getElementById('card-'+id).setAttribute('data-status',d.status);filterCards();toast('Status: '+d.status,'ok');}else{toast(d.msg||'Error','err');}});}

  /* ── Client-side filtering (matches recruiter layout) ── */
  function filterCards(){
    var q=document.getElementById('searchInput').value.toLowerCase();
    var fj=document.getElementById('filterJob').value;
    var fs=document.getElementById('filterStatus').value;
    var cards=document.querySelectorAll('.app-card');
    var shown=0;
    cards.forEach(function(c){
      var matchStatus=!fs||c.getAttribute('data-status')===fs;
      var matchJob=!fj||c.getAttribute('data-job')===fj;
      var matchSearch=!q||(c.getAttribute('data-name')||'').indexOf(q)!==-1||(c.getAttribute('data-email')||'').indexOf(q)!==-1||(c.getAttribute('data-jobtitle')||'').indexOf(q)!==-1;
      if(matchStatus&&matchJob&&matchSearch){c.style.display='';shown++;}else{c.style.display='none';}
    });
    var empty=document.getElementById('emptyMsg');
    if(empty){empty.style.display=shown?'none':'block';}
  }
  document.getElementById('searchInput').addEventListener('input',filterCards);
  document.getElementById('filterJob').addEventListener('change',filterCards);
  document.getElementById('filterStatus').addEventListener('change',function(){
    var v=this.value;
    document.querySelectorAll('#statsRow .stat-pill').forEach(function(p){p.classList.toggle('active',p.getAttribute('data-filter')===v||(v===''&&p.getAttribute('data-filter')===''));});
    filterCards();
  });
  document.querySelectorAll('#statsRow .stat-pill').forEach(function(pill){
    pill.addEventListener('click',function(){
      var f=this.getAttribute('data-filter')||'';
      document.querySelectorAll('#statsRow .stat-pill').forEach(function(p){p.classList.remove('active');});
      this.classList.add('active');
      document.getElementById('filterStatus').value=f;
      filterCards();
    });
  });

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
  // Theme, hamburger, profile dropdown are now handled by navbar_employer.php shared script
  const _guard_iModal = document.getElementById('iModal'); if (_guard_iModal) _guard_iModal.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
  const _guard_dModal = document.getElementById('dModal'); if (_guard_dModal) _guard_dModal.addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});

  var _avatarGrads=['linear-gradient(135deg,#D13D2C,#7A1515)','linear-gradient(135deg,#D4943A,#8a5010)','linear-gradient(135deg,#4CAF70,#2E7D4C)','linear-gradient(135deg,#4A90D9,#1E5FAA)','linear-gradient(135deg,#9C27B0,#6A1B7A)','linear-gradient(135deg,#E85540,#B83525)'];
  function viewApplicant(id) {
    document.getElementById('dContent').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted)"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';
    document.getElementById('dModal').classList.add('open');
    doPost({action:'get_applicant',application_id:id}, function(d){
      if (!d.ok) { document.getElementById('dContent').innerHTML = '<p style="color:#ff8080">'+esc(d.msg||'Error loading details')+'</p>'; return; }
      var a = d.data;
      var ini = (a.full_name||'?').split(' ').map(function(w){return w[0]}).join('').substring(0,2).toUpperCase();
      var grad = _avatarGrads[id % _avatarGrads.length];
      var avatarHtml = a.avatar_url ? '<img src="../'+esc(a.avatar_url)+'" alt="">' : esc(ini);

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
  }

  /* ── Auto-open applicant detail if ?view=<appId> ── */
  var _autoView = new URLSearchParams(window.location.search).get('view');
  if (_autoView) { setTimeout(function(){ viewApplicant(parseInt(_autoView,10)); }, 350); }

</script>
<?php require_once dirname(__DIR__) . '/includes/employer_chat_system.php'; ?>
</body>
</html>
