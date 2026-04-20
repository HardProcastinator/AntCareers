<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'msg' => 'Unauthorized']));
}

$db     = getDB();
$uid    = (int)$_SESSION['user_id'];
$role   = strtolower((string)($_SESSION['account_type'] ?? ''));
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

function inv_json(array $p, int $s = 200): void
{
    http_response_code($s);
    echo json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inv_table_has_column(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function inv_ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    if (!inv_table_has_column($db, $table, $column)) {
        $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

/* ── Auto-create table if needed ── */
try {
    $db->query('SELECT 1 FROM job_invitations LIMIT 0');
} catch (PDOException $e) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS job_invitations (
            id           INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id       INT(10) UNSIGNED NOT NULL,
            recruiter_id INT(10) UNSIGNED NOT NULL,
            jobseeker_id INT(10) UNSIGNED NOT NULL,
            status       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
            custom_note  TEXT DEFAULT NULL,
            sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_invite (job_id, jobseeker_id),
            KEY idx_inv_job (job_id),
        
            KEY idx_inv_recruiter (recruiter_id),
            KEY idx_inv_seeker (jobseeker_id),
            KEY idx_inv_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $ce) {
        error_log('[AntCareers] job_invitations create: ' . $ce->getMessage());
    }
}

inv_ensure_column($db, 'seeker_profiles', 'show_in_people_search', 'TINYINT(1) NOT NULL DEFAULT 1');
inv_ensure_column($db, 'notifications', 'actor_id', 'INT UNSIGNED DEFAULT NULL AFTER user_id');
inv_ensure_column($db, 'notifications', 'reference_type', "VARCHAR(50) DEFAULT NULL AFTER reference_id");

switch ($action) {

    /* ════════════════════════════════════════════════════════════
       search_seekers  GET  recruiter only
       ?action=search_seekers&job_id=X&q=...
       ════════════════════════════════════════════════════════════ */
    case 'search_seekers':
        if ($role !== 'recruiter' && $role !== 'employer') inv_json(['ok' => false, 'msg' => 'Forbidden'], 403);

        $jobId = (int)($_GET['job_id'] ?? 0);
        $q     = trim((string)($_GET['q'] ?? ''));
        if (!$jobId) inv_json(['ok' => false, 'msg' => 'job_id required']);

        try {
            /* ── Validate job belongs to recruiter/employer ── */
            $jobCheckStmt = $db->prepare("
                SELECT j.id, j.status, j.approval_status
                FROM jobs j
                WHERE j.id = ?
                  AND (
                    (? = 'recruiter' AND j.recruiter_id = ?)
                    OR (? = 'employer' AND j.employer_id = ?)
                  )
                LIMIT 1
            ");
            $jobCheckStmt->execute([$jobId, $role, $uid, $role, $uid]);
            $job = $jobCheckStmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                inv_json(['ok' => false, 'msg' => 'Job not found or not authorized'], 404);
            }

            if ($job['status'] !== 'Active') {
                inv_json(['ok' => false, 'msg' => 'This job is no longer active'], 400);
            }

            $likeQ = '%' . $q . '%';
            $stmt  = $db->prepare("
                SELECT
                    u.id, u.full_name, u.avatar_url,
                    sp.headline, sp.city_name, sp.country_name,
                    COALESCE(ss.skills, '') AS skills,
                    (SELECT 1 FROM applications a
                        WHERE a.job_id = ? AND a.seeker_id = u.id LIMIT 1) AS already_applied,
                    (SELECT status FROM job_invitations ji
                        WHERE ji.job_id = ? AND ji.jobseeker_id = u.id LIMIT 1) AS invite_status
                FROM users u
                LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
                LEFT JOIN (
                    SELECT user_id,
                           GROUP_CONCAT(DISTINCT skill_name ORDER BY skill_name SEPARATOR ',') AS skills
                    FROM seeker_skills
                    GROUP BY user_id
                ) ss ON ss.user_id = u.id
                WHERE u.account_type = 'seeker'
                  AND u.is_active    = 1
                  AND COALESCE(sp.show_in_people_search, 1) = 1
                  AND (
                    ? = ''
                    OR u.full_name    LIKE ?
                    OR sp.city_name   LIKE ?
                    OR sp.country_name LIKE ?
                    OR EXISTS (
                        SELECT 1 FROM seeker_skills sk2
                        WHERE sk2.user_id = u.id AND sk2.skill_name LIKE ?
                    )
                  )
                ORDER BY u.full_name ASC
                LIMIT 60
            ");
            $stmt->execute([$jobId, $jobId, $q, $likeQ, $likeQ, $likeQ, $likeQ]);

            $seekers = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $name     = $r['full_name'] ?? 'Unknown';
                $parts    = preg_split('/\s+/', trim($name)) ?: ['?'];
                $initials = strtoupper(
                    substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
                );
                $loc      = trim(implode(', ', array_filter([
                    $r['city_name']    ?? '',
                    $r['country_name'] ?? '',
                ])));
                $skills   = array_values(array_filter(
                    array_map('trim', explode(',', (string)($r['skills'] ?? '')))
                ));

                $seekers[] = [
                    'id'              => (int)$r['id'],
                    'name'            => $name,
                    'headline'        => $r['headline'] ?? '',
                    'location'        => $loc ?: 'Location not specified',
                    'skills'          => array_slice($skills, 0, 3),
                    'avatar_url'      => $r['avatar_url'] ? '../' . ltrim($r['avatar_url'], '/') : '',
                    'initials'        => $initials,
                    'already_applied' => (bool)$r['already_applied'],
                    'invite_status'   => $r['invite_status'] ?: null,
                ];
            }
            inv_json(['ok' => true, 'seekers' => $seekers]);
        } catch (PDOException $e) {
            error_log('[AntCareers] inv search_seekers: ' . $e->getMessage());
            // Temporarily show real error for debugging
            inv_json(['ok' => false, 'msg' => 'DB ERROR: ' . $e->getMessage()], 500);
        }
        break;

    /* ════════════════════════════════════════════════════════════
       send_invites  POST  recruiter only
       POST: job_id, seeker_ids (JSON array), custom_note
       ════════════════════════════════════════════════════════════ */
    case 'send_invites':
        if ($role !== 'recruiter' && $role !== 'employer') inv_json(['ok' => false, 'msg' => 'Forbidden'], 403);

        $jobId      = (int)($_POST['job_id'] ?? 0);
        $rawIds     = (string)($_POST['seeker_ids'] ?? '[]');
        $seekerIds  = json_decode($rawIds, true);
        $customNote = trim((string)($_POST['custom_note'] ?? ''));

        if (!$jobId || !is_array($seekerIds) || empty($seekerIds)) {
            inv_json(['ok' => false, 'msg' => 'job_id and seeker_ids required']);
        }

        /* Validate job belongs to this recruiter/employer and is active */
        try {
            if ($role === 'recruiter') {
                $jobStmt = $db->prepare("
                    SELECT j.id, j.title, j.employer_id, j.status, j.approval_status,
                           COALESCE(cp.company_name, eu.company_name, eu.full_name, 'Company') AS company_name
                    FROM jobs j
                    JOIN users eu ON eu.id = j.employer_id
                    LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
                    WHERE j.id = ? AND j.recruiter_id = ?
                    LIMIT 1
                ");
                $jobStmt->execute([$jobId, $uid]);
            } else {
                // employer role
                $jobStmt = $db->prepare("
                    SELECT j.id, j.title, j.employer_id, j.status, j.approval_status,
                           COALESCE(cp.company_name, eu.company_name, eu.full_name, 'Company') AS company_name
                    FROM jobs j
                    JOIN users eu ON eu.id = j.employer_id
                    LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
                    WHERE j.id = ? AND j.employer_id = ?
                    LIMIT 1
                ");
                $jobStmt->execute([$jobId, $uid]);
            }
            $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[AntCareers] inv send_invites job lookup: ' . $e->getMessage());
            inv_json(['ok' => false, 'msg' => 'DB error'], 500);
        }

        if (!$job) inv_json(['ok' => false, 'msg' => 'Job not found or not yours']);
        if ($job['status'] !== 'Active') {
            inv_json(['ok' => false, 'msg' => 'This job is no longer accepting invites']);
        }

        /* Get recruiter's display name */
        try {
            $rnStmt = $db->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
            $rnStmt->execute([$uid]);
            $rnRow = $rnStmt->fetch(PDO::FETCH_ASSOC);
            $recruiterName = $rnRow['full_name'] ?? 'A recruiter';
        } catch (PDOException $e) {
            $recruiterName = 'A recruiter';
        }

        $sent    = 0;
        $skipped = 0;

        $insInv = $db->prepare(
            "INSERT IGNORE INTO job_invitations
             (job_id, recruiter_id, jobseeker_id, status, custom_note, sent_at)
             VALUES (?, ?, ?, 'pending', ?, NOW())"
        );
        $insNotif = $db->prepare(
            "INSERT INTO notifications
             (user_id, actor_id, type, content, reference_id, reference_type)
             VALUES (?, ?, 'job_invite', ?, ?, 'invitation')"
        );

        foreach ($seekerIds as $rawId) {
            $seekerId = (int)$rawId;
            if ($seekerId <= 0) continue;

            try {
                /* Check already invited */
                $dup = $db->prepare(
                    "SELECT id FROM job_invitations WHERE job_id = ? AND jobseeker_id = ? LIMIT 1"
                );
                $dup->execute([$jobId, $seekerId]);
                if ($dup->fetch()) { $skipped++; continue; }

                $insInv->execute([$jobId, $uid, $seekerId, $customNote ?: null]);
                $invId = (int)$db->lastInsertId();

                if ($invId > 0) {
                    $notifContent = htmlspecialchars($recruiterName, ENT_QUOTES, 'UTF-8')
                        . ' invited you to apply for "'
                        . htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8')
                        . '" at '
                        . htmlspecialchars($job['company_name'], ENT_QUOTES, 'UTF-8');
                    $insNotif->execute([$seekerId, $uid, $notifContent, $invId]);
                    $sent++;
                } else {
                    $skipped++;
                }
            } catch (PDOException $e) {
                error_log('[AntCareers] inv send_invites loop: ' . $e->getMessage());
                $skipped++;
            }
        }

        inv_json(['ok' => true, 'sent' => $sent, 'skipped' => $skipped]);
        break;

    /* ════════════════════════════════════════════════════════════
       get_invite   GET  seeker only
       ?action=get_invite&id=X
       ════════════════════════════════════════════════════════════ */
    case 'get_invite':
        if ($role !== 'seeker') inv_json(['ok' => false, 'msg' => 'Forbidden'], 403);

        $invId = (int)($_GET['id'] ?? 0);
        if (!$invId) inv_json(['ok' => false, 'msg' => 'id required']);

        try {
            $stmt = $db->prepare("
                SELECT
                    ji.id, ji.job_id, ji.recruiter_id, ji.jobseeker_id,
                    ji.status, ji.custom_note, ji.sent_at, ji.responded_at,
                    j.title AS job_title, j.location AS job_location,
                    j.job_type, j.setup, j.experience_level,
                    j.salary_min, j.salary_max, j.salary_currency,
                    j.description, j.requirements, j.skills_required, j.industry,
                    j.status AS job_status, j.approval_status, j.employer_id,
                    COALESCE(cp.company_name, eu.company_name, eu.full_name, 'Unknown Company') AS company_name,
                    cp.logo_path  AS company_logo,
                    ru.full_name  AS recruiter_name,
                    ru.avatar_url AS recruiter_avatar,
                    (SELECT 1 FROM applications a
                        WHERE a.job_id = ji.job_id AND a.seeker_id = ji.jobseeker_id
                        LIMIT 1) AS already_applied
                FROM job_invitations ji
                JOIN jobs j         ON j.id   = ji.job_id
                JOIN users eu       ON eu.id   = j.employer_id
                LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
                JOIN users ru       ON ru.id   = ji.recruiter_id
                WHERE ji.id = ? AND ji.jobseeker_id = ?
                LIMIT 1
            ");
            $stmt->execute([$invId, $uid]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$inv) inv_json(['ok' => false, 'msg' => 'Invitation not found'], 404);
            inv_json(['ok' => true, 'invite' => $inv]);
        } catch (PDOException $e) {
            error_log('[AntCareers] inv get_invite: ' . $e->getMessage());
            inv_json(['ok' => false, 'msg' => 'DB error'], 500);
        }
        break;

    /* ════════════════════════════════════════════════════════════
       respond   POST  seeker only
       POST: id, response (accepted|declined)
       — On accept: auto-creates application + notifies recruiter+employer
       — On decline: notifies recruiter
       ════════════════════════════════════════════════════════════ */
    case 'respond':
        if ($role !== 'seeker') inv_json(['ok' => false, 'msg' => 'Forbidden'], 403);

        $invId    = (int)($_POST['id'] ?? 0);
        $response = (string)($_POST['response'] ?? '');

        if (!$invId || !in_array($response, ['accepted', 'declined'], true)) {
            inv_json(['ok' => false, 'msg' => 'Invalid parameters']);
        }

        try {
            $stmt = $db->prepare("
                SELECT ji.*, j.title AS job_title, j.employer_id, j.status AS job_status,
                       COALESCE(cp.company_name, eu.company_name, 'Company') AS company_name,
                       su.full_name AS seeker_name
                FROM job_invitations ji
                JOIN jobs j   ON j.id   = ji.job_id
                JOIN users eu ON eu.id  = j.employer_id
                LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
                JOIN users su ON su.id  = ji.jobseeker_id
                WHERE ji.id = ? AND ji.jobseeker_id = ?
                LIMIT 1
            ");
            $stmt->execute([$invId, $uid]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inv) inv_json(['ok' => false, 'msg' => 'Invitation not found'], 404);
            if ($inv['status'] !== 'pending') {
                inv_json(['ok' => false, 'msg' => 'Already responded to this invitation']);
            }

            /* Update invitation status */
            $db->prepare(
                "UPDATE job_invitations SET status = ?, responded_at = NOW() WHERE id = ? AND jobseeker_id = ?"
            )->execute([$response, $invId, $uid]);

            $seekerName  = htmlspecialchars($inv['seeker_name'] ?? 'A jobseeker', ENT_QUOTES, 'UTF-8');
            $jobTitle    = htmlspecialchars($inv['job_title'] ?? 'a position', ENT_QUOTES, 'UTF-8');
            $notifType   = ($response === 'accepted') ? 'invite_accepted' : 'invite_declined';
            $notifContent = ($response === 'accepted')
                ? $seekerName . ' accepted your invitation and applied for "' . $jobTitle . '"'
                : $seekerName . ' declined your invitation for "' . $jobTitle . '"';

            $insNotif = $db->prepare(
                "INSERT INTO notifications
                 (user_id, actor_id, type, content, reference_id, reference_type)
                 VALUES (?, ?, ?, ?, ?, 'job')"
            );

            /* Notify recruiter */
            $insNotif->execute([
                (int)$inv['recruiter_id'], $uid, $notifType,
                $notifContent, (int)$inv['job_id'],
            ]);

            /* Notify employer if different from recruiter (accept only) */
            if ($response === 'accepted' && !empty($inv['employer_id']) && (int)$inv['employer_id'] !== (int)$inv['recruiter_id']) {
                $insNotif->execute([
                    (int)$inv['employer_id'], $uid, $notifType,
                    $notifContent, (int)$inv['job_id'],
                ]);
            }

            /* Auto-create application on accept */
            $appCreated = false;
            if ($response === 'accepted' && $inv['job_status'] === 'Active') {
                $chk = $db->prepare(
                    "SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1"
                );
                $chk->execute([(int)$inv['job_id'], $uid]);

                if (!$chk->fetch()) {
                    $resumeUrl = null;
                    try {
                        $rStmt = $db->prepare(
                            "SELECT file_path FROM seeker_resumes
                             WHERE user_id = ? AND is_active = 1
                             ORDER BY uploaded_at DESC LIMIT 1"
                        );
                        $rStmt->execute([$uid]);
                        $rRow = $rStmt->fetch(PDO::FETCH_ASSOC);
                        if ($rRow) $resumeUrl = $rRow['file_path'];
                    } catch (PDOException $e) { /* no resume table */ }

                    $db->prepare(
                        "INSERT INTO applications (job_id, seeker_id, cover_letter, resume_url, status, applied_at)
                         VALUES (?, ?, NULL, ?, 'Pending', NOW())"
                    )->execute([(int)$inv['job_id'], $uid, $resumeUrl]);
                    $appCreated = true;
                }
            }

            inv_json(['ok' => true, 'response' => $response, 'app_created' => $appCreated]);
        } catch (PDOException $e) {
            error_log('[AntCareers] inv respond: ' . $e->getMessage());
            inv_json(['ok' => false, 'msg' => 'DB error'], 500);
        }
        break;

    /* ════════════════════════════════════════════════════════════
       list_for_job   GET  recruiter only — for tracking
       ?action=list_for_job&job_id=X
       ════════════════════════════════════════════════════════════ */
    case 'list_for_job':
        if ($role !== 'recruiter') inv_json(['ok' => false, 'msg' => 'Forbidden'], 403);
        $jobId = (int)($_GET['job_id'] ?? 0);
        if (!$jobId) inv_json(['ok' => false, 'msg' => 'job_id required']);
        try {
            $stmt = $db->prepare("
                SELECT ji.id, ji.jobseeker_id, ji.status, ji.sent_at, ji.responded_at,
                       u.full_name, u.avatar_url,
                       sp.headline, sp.city_name
                FROM job_invitations ji
                JOIN users u ON u.id = ji.jobseeker_id
                LEFT JOIN seeker_profiles sp ON sp.user_id = u.id
                WHERE ji.job_id = ? AND ji.recruiter_id = ?
                ORDER BY ji.sent_at DESC
            ");
            $stmt->execute([$jobId, $uid]);
            inv_json(['ok' => true, 'invitations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            error_log('[AntCareers] inv list_for_job: ' . $e->getMessage());
            inv_json(['ok' => false, 'msg' => 'DB error'], 500);
        }
        break;

    default:
        inv_json(['ok' => false, 'msg' => 'Unknown action'], 400);
}
