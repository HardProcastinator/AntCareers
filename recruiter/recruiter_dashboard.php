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
$navActive   = 'dashboard';

$db  = getDB();
$uid = (int)$_SESSION['user_id'];

/* ── Look up recruiter record to get employer_id ── */
$recruiterId  = 0;
$employerId   = 0;
try {
    $s = $db->prepare("SELECT id, employer_id FROM recruiters WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $s->execute([$uid]);
    $rec = $s->fetch();
    if ($rec) {
        $recruiterId = (int)$rec['id'];
        $employerId  = (int)$rec['employer_id'];
    }
} catch (PDOException $e) { error_log('[AntCareers] recruiter lookup: ' . $e->getMessage()); }

/* ── Summary counts ── */
$activeJobCount   = 0;
$totalApplicants  = 0;
$interviewCount   = 0;
$messageCount     = 0;

try {
    $s = $db->prepare("SELECT COUNT(*) FROM jobs WHERE recruiter_id = ? AND status = 'Active' AND approval_status = 'approved'");
    $s->execute([$uid]);
    $activeJobCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM applications a JOIN jobs j ON j.id = a.job_id WHERE j.recruiter_id = ?");
    $s->execute([$uid]);
    $totalApplicants = (int)$s->fetchColumn();

    $s = $db->prepare("
        SELECT COUNT(*) FROM interview_schedules iv
        JOIN applications a ON a.id = iv.application_id
        JOIN jobs j ON j.id = a.job_id
        WHERE j.recruiter_id = ? AND iv.status = 'Scheduled' AND iv.scheduled_at >= NOW()
    ");
    $s->execute([$uid]);
    $interviewCount = (int)$s->fetchColumn();

    $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $s->execute([$uid]);
    $messageCount = (int)$s->fetchColumn();
} catch (PDOException $e) { error_log('[AntCareers] recruiter dashboard counts: ' . $e->getMessage()); }

/* ── Recent jobs (posted by this recruiter) ── */
$dashJobs = [];
try {
    $s = $db->prepare("
        SELECT j.id, j.title, j.status, j.approval_status, j.job_type, j.setup, j.created_at,
               (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicants
        FROM jobs j
        WHERE j.recruiter_id = ?
        ORDER BY j.created_at DESC
        LIMIT 5
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $dashJobs[] = [
            'id'             => (int)$r['id'],
            'title'          => $r['title'],
            'status'         => $r['status'],
            'approvalStatus' => $r['approval_status'],
            'type'           => $r['job_type'],
            'setup'          => $r['setup'],
            'posted'         => date('M j, Y', strtotime($r['created_at'])),
            'applicants'     => (int)$r['applicants'],
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] recruiter dashboard jobs: ' . $e->getMessage()); }

/* ── Recent applicants ── */
$dashApplicants = [];
try {
    $s = $db->prepare("
        SELECT a.id, u.full_name, u.avatar_url, u.id AS seeker_id, a.status, j.title AS job, a.applied_at
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = a.seeker_id
        WHERE j.recruiter_id = ?
        ORDER BY a.applied_at DESC
        LIMIT 5
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $parts = preg_split('/\s+/', $r['full_name']) ?: ['?'];
        $ini = count($parts) >= 2
            ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
            : strtoupper(substr($parts[0], 0, 2));
        $colors = [
            'linear-gradient(135deg,#D13D2C,#7A1515)',
            'linear-gradient(135deg,#4A90D9,#2A6090)',
            'linear-gradient(135deg,#4CAF70,#2A7040)',
            'linear-gradient(135deg,#9C27B0,#5A1070)',
            'linear-gradient(135deg,#D4943A,#8a5010)',
        ];
        $dashApplicants[] = [
            'id'        => (int)$r['id'],
            'seekerId'  => (int)$r['seeker_id'],
            'name'      => $r['full_name'],
            'initials'  => $ini,
            'avatarUrl' => !empty($r['avatar_url']) ? '../' . $r['avatar_url'] : '',
            'color'     => $colors[$r['id'] % count($colors)],
            'job'       => $r['job'],
            'date'      => date('M j, Y', strtotime($r['applied_at'])),
            'status'    => $r['status'],
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] recruiter dashboard applicants: ' . $e->getMessage()); }

/* ── Upcoming interviews ── */
$dashInterviews = [];
try {
    $s = $db->prepare("
        SELECT iv.id, iv.application_id, iv.seeker_id AS seeker_uid, u.full_name, u.avatar_url,
               j.title AS job, iv.scheduled_at, iv.interview_type,
               iv.meeting_link, iv.location, iv.notes
        FROM interview_schedules iv
        JOIN applications a ON a.id = iv.application_id
        JOIN jobs j ON j.id = a.job_id
        JOIN users u ON u.id = iv.seeker_id
        WHERE j.recruiter_id = ? AND iv.status = 'Scheduled' AND iv.scheduled_at >= NOW()
        ORDER BY iv.scheduled_at ASC
        LIMIT 3
    ");
    $s->execute([$uid]);
    foreach ($s->fetchAll() as $r) {
        $parts = preg_split('/\s+/', $r['full_name']) ?: ['?'];
        $ini = count($parts) >= 2
            ? strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1))
            : strtoupper(substr($parts[0], 0, 2));
        $colors = [
            'linear-gradient(135deg,#4A90D9,#2A6090)',
            'linear-gradient(135deg,#4CAF70,#2A7040)',
            'linear-gradient(135deg,#9C27B0,#5A1070)',
        ];
        $dt   = strtotime($r['scheduled_at']);
        $type = $r['interview_type'] ?? 'Online';
        $dashInterviews[] = [
            'id'            => (int)$r['id'],
            'applicationId' => (int)$r['application_id'],
            'seekerUid'     => (int)$r['seeker_uid'],
            'name'          => $r['full_name'],
            'job'           => $r['job'],
            'initials'      => $ini,
            'avatarUrl'     => !empty($r['avatar_url']) ? '../' . $r['avatar_url'] : '',
            'color'         => $colors[count($dashInterviews) % count($colors)],
            'type'          => $type,
            'meetingLink'   => $r['meeting_link'] ?? null,
            'location'      => $r['location'] ?? null,
            'mon'           => $dt ? date('M', $dt) : '?',
            'day'           => $dt ? date('d', $dt) : '?',
            'time'          => $dt ? date('g:i A', $dt) : '?',
        ];
    }
} catch (PDOException $e) { error_log('[AntCareers] recruiter dashboard interviews: ' . $e->getMessage()); }



$dashJobsJson          = json_encode($dashJobs ?: []);
$dashApplicantsJson    = json_encode($dashApplicants ?: []);
$dashInterviewsJson    = json_encode($dashInterviews ?: []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>AntCareers — Recruiter Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --red-deep:#7A1515; --red-mid:#B83525; --red-vivid:#D13D2C; --red-bright:#E85540; --red-pale:#F07060;
      --soil-dark:#0A0909; --soil-med:#131010; --soil-card:#1C1818; --soil-hover:#252020; --soil-line:#352E2E;
      --text-light:#F5F0EE; --text-mid:#D0BCBA; --text-muted:#927C7A;
      --amber:#D4943A; --amber-dim:#251C0E;
      --font-display:'Playfair Display',Georgia,serif; --font-body:'Plus Jakarta Sans',system-ui,sans-serif;
    }
    *,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
    html { overflow-x:hidden; }
    body { font-family:var(--font-body); background:var(--soil-dark); color:var(--text-light); overflow-x:hidden; min-height:100vh; -webkit-font-smoothing:antialiased; }

    /* ── BACKGROUND LAYERS ── */
    .tunnel-bg { position:fixed; inset:0; pointer-events:none; z-index:0; overflow:hidden; }
    .tunnel-bg svg { width:100%; height:100%; opacity:0.05; }
    .glow-orb { position:fixed; border-radius:50%; filter:blur(90px); pointer-events:none; z-index:0; }
    .glow-1 { width:600px; height:600px; background:radial-gradient(circle,rgba(209,61,44,0.13) 0%,transparent 70%); top:-100px; left:-150px; animation:orb1 18s ease-in-out infinite alternate; }
    .glow-2 { width:400px; height:400px; background:radial-gradient(circle,rgba(209,61,44,0.06) 0%,transparent 70%); bottom:0; right:-80px; animation:orb2 24s ease-in-out infinite alternate; }
    @keyframes orb1 { to { transform:translate(60px,80px) scale(1.1); } }
    @keyframes orb2 { to { transform:translate(-40px,-50px) scale(1.1); } }

    /* ── PAGE SHELL ── */
    .page-shell { max-width:1380px; margin:0 auto; padding:0 24px 40px; position:relative; z-index:2; }

    /* ── GREETING HEADER ── */
    .search-header { padding:24px 0 18px; }
    .search-greeting { font-family:var(--font-display); font-size:26px; font-weight:700; color:#F5F0EE; margin-bottom:4px; }
    .search-greeting em { color:var(--red-bright); font-style:italic; }
    .search-sub { font-size:13px; color:var(--text-muted); }

    /* ── CONTENT LAYOUT ── */
    .content-layout { display:block; }

    /* ── SECTION HEADERS ── */
    .sec-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .sec-title { font-family:var(--font-display); font-size:18px; font-weight:700; color:#F5F0EE; display:flex; align-items:center; gap:8px; }
    .sec-title i { color:var(--red-bright); font-size:14px; }
    .sec-count { font-size:11px; font-weight:600; color:var(--text-muted); background:var(--soil-hover); padding:2px 9px; border-radius:4px; letter-spacing:0.04em; }
    .see-more { font-size:12px; font-weight:600; color:var(--red-pale); cursor:pointer; background:none; border:none; font-family:var(--font-body); display:flex; align-items:center; gap:4px; transition:0.15s; letter-spacing:0.02em; text-decoration:none; }
    .see-more:hover { color:var(--red-bright); }

    /* ── SUMMARY CARDS ROW ── */
    .cards-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
    .sum-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:28px; display:flex; flex-direction:column; gap:14px; transition:all 0.2s; cursor:default; min-height:170px; }
    .sum-card:hover { border-color:rgba(209,61,44,0.4); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.25); }
    .sc-top { display:flex; align-items:center; justify-content:space-between; }
    .sc-icon { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; }
    .sc-icon.r { background:rgba(209,61,44,.12); color:var(--red-pale); }
    .sc-icon.a { background:rgba(212,148,58,.12); color:var(--amber); }
    .sc-icon.b { background:rgba(74,144,217,.1); color:#7ab8f0; }
    .sc-icon.g { background:rgba(76,175,112,.1); color:#6ccf8a; }
    .sc-icon.p { background:rgba(156,39,176,.1); color:#cf8ae0; }
    .sc-num { font-family:var(--font-display); font-size:32px; font-weight:700; color:#F5F0EE; line-height:1; }
    .sc-label { font-size:12px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
    .sc-btn { padding:8px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-family:var(--font-body); font-size:12px; font-weight:700; cursor:pointer; transition:0.18s; width:100%; display:block; text-align:center; text-decoration:none; }
    .sc-btn:hover { background:var(--soil-hover); border-color:var(--red-vivid); color:var(--red-pale); }

    /* ── JOB ROWS ── */
    .job-list { display:flex; flex-direction:column; gap:10px; }
    .job-row { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; padding:22px 24px; transition:all 0.18s; display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; position:relative; }
    .job-row:hover { border-color:rgba(209,61,44,0.5); background:var(--soil-hover); transform:translateX(2px); }
    .jr-top { display:flex; align-items:center; gap:8px; margin-bottom:4px; flex-wrap:wrap; }
    .jr-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; }
    .jr-new { font-size:10px; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:var(--red-pale); background:rgba(209,61,44,0.1); border:1px solid rgba(209,61,44,0.2); padding:2px 7px; border-radius:3px; }
    .jr-new.green { color:#6ccf8a; background:rgba(76,175,112,.1); border-color:rgba(76,175,112,.2); }
    .jr-new.amber { color:var(--amber); background:rgba(212,148,58,.1); border-color:rgba(212,148,58,.2); }
    .jr-new.blue { color:#7ab8f0; background:rgba(74,144,217,.1); border-color:rgba(74,144,217,.2); }
    .jr-new.muted { color:var(--text-muted); background:var(--soil-hover); border-color:var(--soil-line); }
    .jr-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:#927C7A; margin-bottom:8px; }
    .jr-meta span { display:flex; align-items:center; gap:4px; }
    .jr-meta i { font-size:10px; color:var(--red-bright); }
    .jr-chips { display:flex; gap:4px; flex-wrap:wrap; }
    .chip { font-size:11px; font-weight:500; padding:3px 8px; border-radius:4px; background:var(--soil-hover); color:#A09090; border:1px solid var(--soil-line); }
    .chip.green { background:rgba(76,175,112,.08); color:#6ccf8a; border-color:rgba(76,175,112,.2); }
    .chip.amber { background:rgba(212,148,58,.08); color:var(--amber); border-color:rgba(212,148,58,.2); }
    .chip.red { background:rgba(209,61,44,.08); color:var(--red-pale); border-color:rgba(209,61,44,.15); }
    .chip.blue { background:rgba(74,144,217,.08); color:#7ab8f0; border-color:rgba(74,144,217,.18); }
    .job-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
    .jr-salary { font-family:var(--font-body); font-size:14px; font-weight:700; color:#F5F0EE; white-space:nowrap; }
    .jr-actions { display:flex; gap:5px; align-items:center; flex-wrap:wrap; }
    .jr-btn { padding:5px 11px; border-radius:6px; background:transparent; border:1px solid var(--soil-line); color:var(--text-muted); font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.18s; white-space:nowrap; text-decoration:none; }
    .jr-btn:hover { background:var(--soil-hover); color:#F5F0EE; }
    .jr-btn.r:hover { border-color:var(--red-vivid); color:var(--red-pale); }
    .jr-btn.g:hover { border-color:rgba(76,175,112,.5); color:#6ccf8a; }
    .jr-btn.b:hover { border-color:rgba(74,144,217,.5); color:#7ab8f0; }
    .jr-apply { padding:6px 14px; border-radius:6px; background:var(--red-vivid); border:none; color:#fff; font-size:11px; font-weight:700; cursor:pointer; font-family:var(--font-body); transition:0.2s; }
    .jr-apply:hover { background:var(--red-bright); }

    /* ── INTERVIEW CARDS ── */
    .featured-scroll { display:flex; gap:12px; overflow-x:auto; padding:4px 4px 16px 4px; margin:-4px -4px 0 -4px; scrollbar-width:none; }
    .featured-scroll::-webkit-scrollbar { display:none; }
    .featured-card { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:14px; padding:22px; min-width:258px; max-width:258px; cursor:pointer; transition:all 0.25s; position:relative; overflow:hidden; flex-shrink:0; }
    .featured-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--red-vivid),var(--red-bright)); }
    .featured-card:hover { border-color:rgba(209,61,44,0.55); transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,0.4); }
    .fc-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--amber); background:var(--amber-dim); border:1px solid rgba(212,148,58,0.22); padding:2px 7px; border-radius:3px; margin-bottom:10px; }
    .fc-title { font-family:var(--font-display); font-size:15px; font-weight:700; color:#F5F0EE; margin-bottom:4px; line-height:1.3; }
    .fc-company { font-size:12px; color:var(--red-pale); font-weight:600; margin-bottom:14px; }
    .fc-chips { display:flex; flex-wrap:wrap; gap:4px; margin-bottom:10px; }
    .fc-footer { display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid var(--soil-line); }

    /* ── NOTIFICATIONS PANEL ── */
    .notif-panel { background:var(--soil-card); border:1px solid var(--soil-line); border-radius:12px; overflow:hidden; }
    .notif-item { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--soil-line); transition:background 0.15s; }
    .notif-item:last-child { border-bottom:none; }
    .notif-item:hover { background:var(--soil-hover); }
    .notif-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; flex-shrink:0; }
    .notif-icon.ni-info { background:rgba(74,144,217,.1); color:#7ab8f0; }
    .notif-icon.ni-success { background:rgba(76,175,112,.1); color:#6ccf8a; }
    .notif-icon.ni-warning { background:rgba(212,148,58,.12); color:var(--amber); }
    .notif-icon.ni-default { background:rgba(209,61,44,.12); color:var(--red-pale); }
    .notif-text { font-size:13px; color:var(--text-mid); line-height:1.4; }
    .notif-time { font-size:10px; color:var(--text-muted); margin-top:2px; }

    /* ── EMPTY STATE ── */
    .empty-state { text-align:center; padding:56px 20px; color:var(--text-muted); }
    .empty-state i { font-size:32px; margin-bottom:14px; display:block; color:var(--soil-line); }

    /* ── FOOTER ── */
    .footer { border-top:1px solid var(--soil-line); padding:20px 24px; max-width:1380px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; color:var(--text-muted); font-size:12px; position:relative; z-index:2; flex-wrap:wrap; gap:10px; }
    .footer-logo { font-family:var(--font-display); font-weight:700; color:var(--red-pale); font-size:15px; }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .anim { animation:fadeUp 0.4s ease both; }
    .anim-d1 { animation-delay:0.05s; }
    .anim-d2 { animation-delay:0.1s; }
    .anim-d3 { animation-delay:0.15s; }

    ::-webkit-scrollbar { width:5px; }
    ::-webkit-scrollbar-track { background:var(--soil-dark); }
    ::-webkit-scrollbar-thumb { background:var(--soil-line); border-radius:3px; }

    /* ── LIGHT THEME ── */
    html.theme-light body,body.light {
      --soil-dark:#F9F5F4; --soil-card:#FFFFFF; --soil-hover:#FEF0EE; --soil-line:#E0CECA;
      --text-light:#1A0A09; --text-mid:#4A2828; --text-muted:#7A5555;
      --amber-dim:#FFF4E0; --amber:#B8620A;
    }
    body.light .glow-orb { opacity:0.04; }
    body.light .search-greeting { color:#1A0A09; }
    body.light .search-sub { color:#7A5555; }
    body.light .sum-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .sc-num { color:#1A0A09; }
    body.light .sec-title { color:#1A0A09; }
    body.light .job-row { background:#FFFFFF; border-color:#E0CECA; }
    body.light .job-row:hover { background:#FEF0EE; box-shadow:0 4px 12px rgba(0,0,0,0.08); }
    body.light .jr-title { color:#1A0A09; }
    body.light .jr-meta { color:#7A5555; }
    body.light .chip { background:#F5EEEC; border-color:#E0CECA; color:#5A3838; }
    body.light .featured-card { background:#FFFFFF; border-color:#E0CECA; }
    body.light .fc-title { color:#1A0A09; }
    body.light .jr-btn { background:#F5EEEC; border-color:#E0CECA; color:#5A4040; }
    body.light .jr-btn:hover { background:#FEF0EE; color:var(--red-vivid); border-color:rgba(209,61,44,0.4); }
    body.light .jr-btn.r:hover { color:#D13D2C; border-color:rgba(209,61,44,0.5); }
    body.light .jr-btn.g:hover { color:#2E7D32; border-color:rgba(46,125,50,0.5); }
    body.light .jr-btn.b:hover { color:#1565C0; border-color:rgba(21,101,192,0.5); }
    body.light .jr-btn.a:hover { color:#B8620A; border-color:rgba(184,98,10,0.5); }
    body.light .sc-btn:hover { background:#FEF0EE; border-color:var(--red-vivid); color:var(--red-vivid); }
    body.light .chip.green { color:#2E7D46; background:rgba(76,175,112,.12); }
    body.light .chip.amber { color:#8B5500; background:rgba(212,148,58,.12); }
    body.light .chip.blue { color:#1565C0; background:rgba(74,144,217,.12); }
    body.light .featured-card:hover { box-shadow:0 12px 32px rgba(0,0,0,0.12); }

    @media(max-width:1060px) { .cards-row { grid-template-columns:repeat(2,1fr); } }
    @media(max-width:760px) {
      html,body{overflow-x:hidden;max-width:100vw}
      .page-shell,.content-layout,.dashboard-grid{max-width:100%;overflow-x:hidden}
      table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;white-space:nowrap}
      .modal,.modal-inner,.modal-box{width:100%!important;max-width:100vw!important;margin:0!important;border-radius:12px 12px 0 0!important;position:fixed!important;bottom:0!important;left:0!important;right:0!important;top:auto!important;max-height:90vh;overflow-y:auto}
      .nav-links { display:none; } .hamburger { display:flex; }
      .page-shell { padding:0 16px 40px; } .nav-inner { padding:0 10px; }
      .profile-name,.profile-role { display:none; } .profile-btn { padding:6px 8px; }
      .job-row { grid-template-columns:1fr; gap:10px; }
      .jr-icon { display:none; }
      .jr-chips{display:flex;flex-wrap:nowrap;overflow-x:auto;gap:6px;scrollbar-width:none;padding-bottom:4px}
      .jr-chips::-webkit-scrollbar{display:none}
      .jr-chips .chip{flex-shrink:0}
      .job-row-right { flex-direction:row; align-items:center; justify-content:space-between; }
      .job-description-preview,.card-description{display:none}
      .featured-scroll{-webkit-overflow-scrolling:touch}
      .featured-card{min-width:calc(100vw - 48px);max-width:calc(100vw - 48px)}
      .footer { flex-direction:column; text-align:center; padding:16px; }
      .cards-row { grid-template-columns:1fr 1fr; gap:12px; }
      .sum-card { padding:20px 16px; min-height:150px; }
      .sc-num { font-size:26px; }
    }
  </style>
</head>
<body id="pageBody">

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

  <!-- GREETING HEADER -->
  <div class="search-header anim">
    <div class="search-greeting"><span id="greetingText">Good morning</span>, <em><?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>.</em></div>
    <div class="search-sub">Welcome to your recruiter dashboard at <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.</div>
  </div>

  <div class="content-layout">
    <main>

      <!-- SUMMARY CARDS -->
      <div class="cards-row anim">
        <div class="sum-card">
          <div class="sc-top">
            <div class="sc-icon g"><i class="fas fa-briefcase"></i></div>
            <div class="sc-num"><?= $activeJobCount ?></div>
          </div>
          <div class="sc-label">My Active Jobs</div>
          <a class="sc-btn" href="recruiter_jobs.php">View Jobs</a>
        </div>
        <div class="sum-card">
          <div class="sc-top">
            <div class="sc-icon a"><i class="fas fa-users"></i></div>
            <div class="sc-num"><?= $totalApplicants ?></div>
          </div>
          <div class="sc-label">Total Applicants</div>
          <a class="sc-btn" href="recruiter_applicants.php">View Applicants</a>
        </div>
        <div class="sum-card">
          <div class="sc-top">
            <div class="sc-icon b"><i class="fas fa-calendar-check"></i></div>
            <div class="sc-num"><?= $interviewCount ?></div>
          </div>
          <div class="sc-label">Scheduled Interviews</div>
          <a class="sc-btn" href="recruiter_applicants.php">View Interviews</a>
        </div>
        <div class="sum-card">
          <div class="sc-top">
            <div class="sc-icon p"><i class="fas fa-envelope"></i></div>
            <div class="sc-num"><?= $messageCount ?></div>
          </div>
          <div class="sc-label">Unread Messages</div>
          <a class="sc-btn" href="recruiter_messages.php">Open Messages</a>
        </div>
      </div>

      <!-- RECENT JOBS -->
      <div id="section-jobs" class="anim anim-d1" style="margin-top:24px;">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-list-alt"></i> Recent Jobs <span class="sec-count" id="jobCount"><?= count($dashJobs) ?> job<?= count($dashJobs) !== 1 ? 's' : '' ?></span></div>
          <a class="see-more" href="recruiter_jobs.php">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="jobsContainer"></div>
      </div>

      <!-- RECENT APPLICANTS -->
      <div id="section-applicants" style="margin-top:40px;" class="anim anim-d1">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-user-clock"></i> Recent Applicants <span class="sec-count" id="appCount"><?= count($dashApplicants) ?> applicant<?= count($dashApplicants) !== 1 ? 's' : '' ?></span></div>
          <a class="see-more" href="recruiter_applicants.php">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="job-list" id="applicantsContainer"></div>
      </div>

      <!-- UPCOMING INTERVIEWS -->
      <div id="section-interviews" style="margin-top:40px;" class="anim anim-d2">
        <div class="sec-header">
          <div class="sec-title"><i class="fas fa-calendar-alt"></i> Upcoming Interviews</div>
        </div>
        <div class="featured-scroll" id="interviewsContainer"></div>
      </div>



    </main>
  </div>
</div>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-logo">AntCareers</div>
  <div>Recruiter Dashboard — <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></div>
  <div style="display:flex;gap:14px;color:var(--text-muted);">
    <a style="cursor:pointer;color:inherit;text-decoration:none;" href="../index.php">← Public Site</a>
    <span style="cursor:pointer;">Privacy</span>
    <span style="cursor:pointer;">Terms</span>
  </div>
</footer>

<script>
  // ── DATA (from PHP) ──
  const jobsData = <?= $dashJobsJson ?>;
  const applicantsData = <?= $dashApplicantsJson ?>;
  const interviewsData = <?= $dashInterviewsJson ?>;


  function escapeHtml(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ── RENDER JOBS ──
  function renderJobs(data) {
    const container = document.getElementById('jobsContainer');
    if (!data.length) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-briefcase"></i><p>No jobs posted yet.</p></div>';
      return;
    }
    container.innerHTML = data.map((j, i) => {
      const statusClass = j.status === 'Active' ? 'green' : j.status === 'Draft' ? 'muted' : j.status === 'Closed' ? 'red' : '';
      const approvalClass = j.approvalStatus === 'approved' ? 'green' : j.approvalStatus === 'pending' ? 'amber' : j.approvalStatus === 'rejected' ? 'red' : 'muted';
      return `
      <div class="job-row" style="animation:fadeUp 0.3s ${i * 0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div class="jr-title">${escapeHtml(j.title)}</div>
            <span class="jr-new ${statusClass}">${escapeHtml(j.status)}</span>
            <span class="jr-new ${approvalClass}">${escapeHtml(j.approvalStatus)}</span>
          </div>
          <div class="jr-meta">
            <span><i class="fas fa-building"></i> <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></span>
            ${j.type ? `<span><i class="fas fa-clock"></i> ${escapeHtml(j.type)}</span>` : ''}
            ${j.setup ? `<span><i class="fas fa-laptop-house"></i> ${escapeHtml(j.setup)}</span>` : ''}
            <span><i class="fas fa-calendar"></i> Posted ${escapeHtml(j.posted)}</span>
          </div>
          <div class="jr-chips">
            ${j.applicants > 0
              ? `<span class="chip green">${j.applicants} applicant${j.applicants !== 1 ? 's' : ''}</span>`
              : '<span class="chip">No applicants yet</span>'}
          </div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions">
            <a class="jr-btn" href="recruiter_jobs.php#job-${j.id}" style="text-decoration:none;">View</a>
            <a class="jr-btn" href="recruiter_jobs.php?edit=${j.id}" style="text-decoration:none;">Edit</a>
            ${j.status==='Active'?`<button class="jr-btn a" onclick="event.stopPropagation();closeJob(${j.id},this)">Close</button>`:''}
            <button class="jr-btn r" onclick="event.stopPropagation();deleteJob(${j.id},this)">Delete</button>
          </div>
        </div>
      </div>`;
    }).join('');
  }

  // ── RENDER APPLICANTS ──
  function renderApplicants(data) {
    const container = document.getElementById('applicantsContainer');
    if (!data.length) {
      container.innerHTML = '<div class="empty-state"><i class="fas fa-users"></i><p>No applicants yet.</p></div>';
      return;
    }
    const statusClass = { Reviewed:'blue', Shortlisted:'green', Pending:'amber', Rejected:'red', Hired:'green' };
    container.innerHTML = data.map((a, i) => `
      <div class="job-row" style="animation:fadeUp 0.3s ${i * 0.04}s both ease;">
        <div class="job-row-left">
          <div class="jr-top">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:34px;height:34px;border-radius:50%;background:${a.color};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;">
                ${a.avatarUrl ? `<img src="${escapeHtml(a.avatarUrl)}" style="width:100%;height:100%;object-fit:cover;">` : escapeHtml(a.initials)}
              </div>
              <div class="jr-title" style="font-size:14px;">${escapeHtml(a.name)}</div>
            </div>
            <span class="jr-new ${statusClass[a.status] || ''}">${escapeHtml(a.status)}</span>
          </div>
          <div class="jr-meta">
            <span><i class="fas fa-briefcase"></i> ${escapeHtml(a.job)}</span>
            <span><i class="fas fa-calendar"></i> Applied ${escapeHtml(a.date)}</span>
          </div>
        </div>
        <div class="job-row-right">
          <div class="jr-actions" id="appActions_${a.id}">
            <a class="jr-btn" href="recruiter_applicants.php?view=${a.id}" style="text-decoration:none;">View Profile</a>
            ${a.status !== 'Shortlisted' && a.status !== 'Rejected' && a.status !== 'Offered' ? `<button class="jr-btn g" onclick="updateAppStatus(${a.id},'Shortlisted',this)">Shortlist</button>` : ''}
            ${a.status !== 'Rejected' && a.status !== 'Offered' ? `<button class="jr-btn r" onclick="updateAppStatus(${a.id},'Rejected',this)">Reject</button>` : ''}
            <a class="jr-btn b" href="recruiter_messages.php?user_id=${a.seekerId}" style="text-decoration:none;">Message</a>
          </div>
        </div>
      </div>`).join('');
  }

  // ── RENDER INTERVIEWS ──
  function renderInterviews() {
    const el = document.getElementById('interviewsContainer');
    if (!interviewsData.length) {
      el.innerHTML = '<div class="empty-state" style="padding:30px 20px;width:100%;"><i class="fas fa-calendar-alt"></i><p>No upcoming interviews.</p></div>';
      return;
    }
    el.innerHTML = interviewsData.map(iv => {
      const typeBadge = iv.type === 'On-site'
        ? '<span class="chip green">On-site</span>'
        : iv.type === 'Phone'
          ? '<span class="chip amber">Phone Call</span>'
          : '<span class="chip blue">Online</span>';

      let detailChip = '';
      if (iv.type === 'On-site') {
        detailChip = `<span class="chip"><i class="fas fa-map-marker-alt" style="margin-right:3px;color:#6ccf8a;"></i>${escapeHtml(iv.location || 'On-site')}</span>`;
      } else if (iv.type === 'Phone') {
        detailChip = `<span class="chip"><i class="fas fa-phone" style="margin-right:3px;color:var(--amber);"></i>Phone</span>`;
      } else if (iv.meetingLink) {
        detailChip = `<a href="${escapeHtml(iv.meetingLink)}" target="_blank" rel="noopener" class="chip" style="text-decoration:none;color:#7ab8f0;cursor:pointer;"><i class="fas fa-video" style="margin-right:3px;"></i>Join Meeting</a>`;
      }

      return `
      <div class="featured-card">
        <div class="fc-badge"><i class="fas fa-calendar-check"></i> Scheduled</div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
          <div style="width:42px;height:42px;border-radius:50%;background:${iv.color};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff;flex-shrink:0;overflow:hidden;">
            ${iv.avatarUrl ? `<img src="${escapeHtml(iv.avatarUrl)}" style="width:100%;height:100%;object-fit:cover;">` : escapeHtml(iv.initials)}
          </div>
          <div>
            <div class="fc-title" style="font-size:14px;">${escapeHtml(iv.name)}</div>
            <div class="fc-company">${escapeHtml(iv.job)}</div>
          </div>
        </div>
        <div style="background:rgba(209,61,44,0.08);border:1px solid rgba(209,61,44,0.18);border-radius:6px;padding:10px 14px;margin-bottom:12px;">
          <div style="font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--text-light);line-height:1;">${escapeHtml(iv.mon)} ${escapeHtml(iv.day)}</div>
          <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><i class="fas fa-clock" style="color:var(--red-bright);margin-right:3px;"></i>${escapeHtml(iv.time)}</div>
        </div>
        <div class="fc-chips" style="flex-direction:column;gap:4px;">${typeBadge}${detailChip}</div>
        <div class="fc-footer" style="margin-top:12px;">
          <a class="jr-btn" style="font-size:11px;text-decoration:none;" href="recruiter_applicants.php?view=${iv.applicationId}&reschedule=1">Reschedule</a>
          <a class=\"jr-apply\" style=\"text-decoration:none;\" href=\"recruiter_messages.php?user_id=${iv.seekerUid}\">Message</a>
        </div>
      </div>`;
    }).join('');
  }




  // ── GREETING ──
  (function() {
    var h = new Date().getHours();
    var el = document.getElementById('greetingText');
    if (el) el.textContent = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
  })();

  // ── UPDATE APPLICANT STATUS (Shortlist / Reject) ──
  function updateAppStatus(appId, newStatus, btn) {
    if (!confirm('Set this applicant to ' + newStatus + '?')) return;
    btn.disabled = true;
    btn.textContent = '…';
    var fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('application_id', appId);
    fd.append('status', newStatus);
    fetch('recruiter_applicants.php', { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          // update the local data & re-render
          applicantsData.forEach(function(a){ if (a.id === appId) a.status = newStatus; });
          renderApplicants(applicantsData);
          showToast(newStatus === 'Shortlisted' ? 'Applicant shortlisted!' : 'Applicant rejected', newStatus === 'Shortlisted' ? 'fa-user-check' : 'fa-times-circle');
        } else {
          showToast(d.msg || 'Failed to update status', 'fa-exclamation-circle');
          btn.disabled = false;
          btn.textContent = newStatus === 'Shortlisted' ? 'Shortlist' : 'Reject';
        }
      })
      .catch(function() {
        showToast('Network error — try again', 'fa-wifi');
        btn.disabled = false;
        btn.textContent = newStatus === 'Shortlisted' ? 'Shortlist' : 'Reject';
      });
  }

  // ── CLOSE JOB ──
  function closeJob(jobId, btn) {
    if (!confirm('Close this job posting? It will no longer be visible to applicants.')) return;
    btn.disabled = true; btn.textContent = '…';
    var fd = new FormData();
    fd.append('action', 'toggle_status');
    fd.append('job_id', jobId);
    fetch('recruiter_jobs.php', { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          jobsData.forEach(function(j){ if (j.id===jobId) j.status = d.status; });
          renderJobs(jobsData);
          showToast('Job ' + (d.status==='Closed'?'closed':'reopened') + '!', 'fa-check-circle');
        } else {
          showToast(d.msg || 'Failed to update job', 'fa-exclamation-circle');
          btn.disabled = false; btn.textContent = 'Close';
        }
      })
      .catch(function(){ showToast('Network error','fa-wifi'); btn.disabled=false; btn.textContent='Close'; });
  }

  // ── DELETE JOB ──
  function deleteJob(jobId, btn) {
    if (!confirm('Are you sure you want to permanently delete this job? This cannot be undone.')) return;
    btn.disabled = true; btn.textContent = '…';
    var fd = new FormData();
    fd.append('action', 'delete_job');
    fd.append('job_id', jobId);
    fetch('recruiter_jobs.php', { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.ok) {
          var idx = jobsData.findIndex(function(j){ return j.id===jobId; });
          if (idx > -1) jobsData.splice(idx, 1);
          renderJobs(jobsData);
          showToast('Job deleted', 'fa-trash');
        } else {
          showToast(d.msg || 'Failed to delete job', 'fa-exclamation-circle');
          btn.disabled = false; btn.textContent = 'Delete';
        }
      })
      .catch(function(){ showToast('Network error','fa-wifi'); btn.disabled=false; btn.textContent='Delete'; });
  }

  // ── INIT RENDER ──
  renderJobs(jobsData);
  renderApplicants(applicantsData);
  renderInterviews();

</script>
</body>
</html>