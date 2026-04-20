<?php
/**
 * AntCareers — Admin API Handler
 * POST /admin/api_admin.php
 *
 * All moderation actions performed by the System Admin.
 * Validates admin session + CSRF before every action.
 * Returns JSON { success, message }.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth guard ──────────────────────────────────────────────
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}

// ── GET requests — read-only data fetching ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = (string)($_GET['action'] ?? '');
    $adminId   = (int)$_SESSION['user_id'];
    $db        = getDB();

    if ($getAction === 'get_notifications') {
        try {
            $stmt = $db->prepare(
                "SELECT n.id, n.type, n.content, n.reference_id, n.reference_type, n.is_read, n.created_at
                 FROM notifications n
                 WHERE n.user_id = :uid
                 ORDER BY n.created_at DESC LIMIT 30"
            );
            $stmt->execute([':uid' => $adminId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            exit(json_encode(['success' => true, 'notifications' => $rows]));
        } catch (Throwable $e) {
            error_log('[AntCareers] api_admin get_notifications: ' . $e->getMessage());
            exit(json_encode(['success' => false, 'notifications' => []]));
        }
    }

    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Unknown GET action.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

// ── Parse body ───────────────────────────────────────────────
$raw  = json_decode(file_get_contents('php://input'), true);
$body = is_array($raw) ? $raw : $_POST;

// ── CSRF ─────────────────────────────────────────────────────
$csrf = (string)($body['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token.']));
}

$action  = (string)($body['action'] ?? '');
$adminId = (int)$_SESSION['user_id'];
$db      = getDB();

/**
 * Check if a column exists (used for self-healing schema upgrades).
 */
function table_has_column_admin(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Helper — send an in-app notification to a user.
 */
$notify = static function (int $toUserId, int $fromUserId, string $type, string $content, int $refId, string $refType) use ($db): void {
    try {
        $db->prepare(
            'INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type, is_read, created_at)
             VALUES (:uid, :actor, :type, :content, :ref_id, :ref_type, 0, NOW())'
        )->execute([
            ':uid'      => $toUserId,
            ':actor'    => $fromUserId,
            ':type'     => $type,
            ':content'  => $content,
            ':ref_id'   => $refId,
            ':ref_type' => $refType,
        ]);
    } catch (Throwable) {}
};

// ═══════════════════════════════════════════════════════════
// COMPANY ACTIONS
// ═══════════════════════════════════════════════════════════

/* ── approve_company ── */
if ($action === 'approve_company') {
    $userId = (int)($body['user_id'] ?? 0);
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    try {
        // Activate user account
        $db->prepare(
            "UPDATE users
             SET account_status = 'active', is_active = 1,
                 status_reason = NULL, status_updated_by = :admin, status_updated_at = NOW()
             WHERE id = :uid AND account_type = 'employer'"
        )->execute([':admin' => $adminId, ':uid' => $userId]);

        // Mark company profile as verified
        $db->prepare(
            "UPDATE company_profiles
             SET is_verified = 1, approval_updated_by = :admin, approval_updated_at = NOW()
             WHERE user_id = :uid"
        )->execute([':admin' => $adminId, ':uid' => $userId]);

        // Fetch company name for notification
        $row = $db->prepare("SELECT company_name, full_name FROM users WHERE id = :uid LIMIT 1");
        $row->execute([':uid' => $userId]);
        $u = $row->fetch();
        $cName = $u ? (string)($u['company_name'] ?? $u['full_name'] ?? '') : '';

        $notify($userId, $adminId, 'general',
            "Your company account ({$cName}) has been approved. You can now log in and post jobs.",
            $userId, 'user');

        // Mark the pending approval notification as read for all admins
        $db->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE type = 'company_approval_request' AND reference_id = :uid AND reference_type = 'user'"
        )->execute([':uid' => $userId]);

        logActivity($userId, $adminId, 'company_approved', 'user', $userId,
            "Admin approved company account: {$cName} (user #{$userId}).");

        exit(json_encode(['success' => true, 'message' => 'Company approved successfully.']));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin approve_company: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── reject_company ── */
if ($action === 'reject_company') {
    $userId = (int)($body['user_id'] ?? 0);
    $reason = trim((string)($body['reason'] ?? ''));
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    try {
        $db->prepare(
            "UPDATE users
             SET account_status = 'banned', is_active = 0,
                 status_reason = :reason, status_updated_by = :admin, status_updated_at = NOW()
             WHERE id = :uid AND account_type = 'employer'"
        )->execute([':reason' => $reason ?: null, ':admin' => $adminId, ':uid' => $userId]);

        $db->prepare(
            "UPDATE company_profiles
             SET is_verified = 0,
                 approval_reason = :reason, approval_updated_by = :admin, approval_updated_at = NOW()
             WHERE user_id = :uid"
        )->execute([':reason' => $reason ?: null, ':admin' => $adminId, ':uid' => $userId]);

        $row = $db->prepare("SELECT company_name, full_name FROM users WHERE id = :uid LIMIT 1");
        $row->execute([':uid' => $userId]);
        $u = $row->fetch();
        $cName = $u ? (string)($u['company_name'] ?? $u['full_name'] ?? '') : '';

        $msg = "Your company registration ({$cName}) has been rejected.";
        if ($reason !== '') {
            $msg .= " Reason: {$reason}";
        }
        $notify($userId, $adminId, 'general', $msg, $userId, 'user');

        // Mark the pending approval notification as read for all admins
        $db->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE type = 'company_approval_request' AND reference_id = :uid AND reference_type = 'user'"
        )->execute([':uid' => $userId]);

        logActivity($userId, $adminId, 'company_rejected', 'user', $userId,
            "Admin rejected company account: {$cName} (user #{$userId}). Reason: {$reason}");

        exit(json_encode(['success' => true, 'message' => 'Company registration rejected.']));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin reject_company: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

// ═══════════════════════════════════════════════════════════
// JOB POST ACTIONS
// ═══════════════════════════════════════════════════════════

/* ── approve_job ── */
if ($action === 'approve_job') {
    $jobId = (int)($body['job_id'] ?? 0);
    if ($jobId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid job ID.']));
    }
    try {
        $db->prepare(
            "UPDATE jobs
             SET approval_status = 'approved', approval_reason = NULL,
                 approved_by = :admin, approved_at = NOW()
             WHERE id = :jid"
        )->execute([':admin' => $adminId, ':jid' => $jobId]);

        $jRow = $db->prepare("SELECT j.title, j.employer_id, j.industry, j.experience_level, j.skills_required, j.location, j.setup, u.full_name, COALESCE(cp.company_name, u.company_name, u.full_name, 'A company') AS company_label FROM jobs j JOIN users u ON u.id = j.employer_id LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id WHERE j.id = :jid LIMIT 1");
        $jRow->execute([':jid' => $jobId]);
        $j = $jRow->fetch();

        if ($j) {
            $notify((int)$j['employer_id'], $adminId, 'general',
                "Your job post \"{$j['title']}\" has been approved and is now live.",
                $jobId, 'job');

            // ── Notify matching seekers (4-criteria job match) ─────────────────
            try {
                require_once dirname(__DIR__) . '/includes/job_match_mailer.php';

                // Ensure is_expired column exists (self-healing migration)
                if (!table_has_column_admin($db, 'notifications', 'is_expired')) {
                    $db->exec("ALTER TABLE notifications ADD COLUMN is_expired TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read");
                }

                $jobExpLevel  = strtolower(trim((string)($j['experience_level'] ?? '')));
                $jobIndustry  = strtolower(trim((string)($j['industry'] ?? '')));
                $jobLocation  = strtolower(trim((string)($j['location'] ?? '')));
                $jobSetup     = strtolower(trim((string)($j['setup'] ?? '')));
                $jobTitleLow  = strtolower(trim((string)($j['title'] ?? '')));
                $jobSkillsLow = strtolower((string)($j['skills_required'] ?? ''));
                $companyLabel = (string)($j['company_label'] ?? 'A company');

                // Fetch all opted-in seekers who have at least one relevant profile field
                $seekerStmt = $db->prepare("
                    SELECT u.id, u.email, u.full_name,
                           sp.experience_level, sp.industry, sp.country_name, sp.desired_position
                    FROM users u
                    JOIN seeker_profiles sp ON sp.user_id = u.id
                    LEFT JOIN user_preferences up ON up.user_id = u.id
                    WHERE u.account_type = 'seeker'
                      AND u.is_active = 1
                      AND COALESCE(up.notif_relevant_jobs, 1) = 1
                      AND (
                        sp.experience_level IS NOT NULL
                        OR sp.industry IS NOT NULL
                        OR sp.desired_position IS NOT NULL
                      )
                ");
                $seekerStmt->execute();
                $seekers = $seekerStmt->fetchAll();

                // Prepared statements used inside the loop
                $dupChk = $db->prepare(
                    "SELECT COUNT(*) FROM notifications
                     WHERE user_id = ? AND type = 'relevant_job'
                       AND reference_id = ? AND reference_type = 'job'"
                );
                $insNotif = $db->prepare(
                    "INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type, is_read, created_at)
                     VALUES (?, ?, 'relevant_job', ?, ?, 'job', 0, NOW())"
                );

                $count = 0;
                foreach ($seekers as $seeker) {
                    if ($count >= 200) break;

                    $sExp     = strtolower(trim((string)($seeker['experience_level'] ?? '')));
                    $sInd     = strtolower(trim((string)($seeker['industry'] ?? '')));
                    $sCountry = strtolower(trim((string)($seeker['country_name'] ?? '')));
                    $sDesired = strtolower(trim((string)($seeker['desired_position'] ?? '')));

                    // Skip seekers whose profile has no relevant data (incomplete profile)
                    if ($sExp === '' && $sInd === '' && $sDesired === '') continue;

                    // ── Score 4 criteria ────────────────────────────────────────────
                    $expMatch = ($sExp !== '' && $jobExpLevel !== '' && $sExp === $jobExpLevel);
                    $indMatch = ($sInd !== '' && $jobIndustry !== '' && $sInd === $jobIndustry);
                    $score    = (int)$expMatch + (int)$indMatch;

                    // Country match: job location contains seeker's country OR job is remote
                    if ($sCountry !== '' && ($jobSetup === 'remote' || str_contains($jobLocation, $sCountry))) {
                        $score++;
                    }

                    // Title/skills keyword: desired_position words overlap with job title or skills
                    if ($sDesired !== '') {
                        $words = array_filter(
                            preg_split('/[\s,\/\-]+/', $sDesired) ?: [],
                            static fn($w) => strlen($w) > 3
                        );
                        foreach ($words as $word) {
                            if (str_contains($jobTitleLow, $word) || str_contains($jobSkillsLow, $word)) {
                                $score++;
                                break;
                            }
                        }
                    }

                    // Notify: score >= 2 OR experience AND industry both match (hard filter)
                    if ($score < 2 && !($expMatch && $indMatch)) continue;

                    // Duplicate prevention — skip if already notified
                    $dupChk->execute([(int)$seeker['id'], $jobId]);
                    if ((int)$dupChk->fetchColumn() > 0) continue;

                    // Insert in-app notification
                    $content = 'New job match: "' . $j['title'] . '" at ' . $companyLabel . '.';
                    $insNotif->execute([(int)$seeker['id'], $adminId, $content, $jobId]);

                    // Send email notification
                    sendJobMatchEmail(
                        (string)$seeker['email'],
                        (string)$seeker['full_name'],
                        [
                            'id'               => $jobId,
                            'title'            => (string)($j['title'] ?? ''),
                            'company'          => $companyLabel,
                            'location'         => (string)($j['location'] ?? ''),
                            'experience_level' => (string)($j['experience_level'] ?? ''),
                        ],
                        APP_URL
                    );

                    $count++;
                }
            } catch (Throwable $matchErr) {
                error_log('[AntCareers] relevant job notification matching: ' . $matchErr->getMessage());
            }
        }

        // Mark the job approval request notification as read for all admins
        $db->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE type = 'job_approval_request' AND reference_id = :jid AND reference_type = 'job'"
        )->execute([':jid' => $jobId]);

        logActivity(
            $j ? (int)$j['employer_id'] : null,
            $adminId,
            'job_approved', 'job', $jobId,
            "Admin approved job post ID #{$jobId}: \"" . ($j['title'] ?? '') . '".'
        );

        exit(json_encode(['success' => true, 'message' => 'Job post approved.']));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin approve_job: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── reject_job ── */
if ($action === 'reject_job') {
    $jobId  = (int)($body['job_id'] ?? 0);
    $reason = trim((string)($body['reason'] ?? ''));
    if ($jobId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid job ID.']));
    }
    try {
        $db->prepare(
            "UPDATE jobs
             SET approval_status = 'rejected', approval_reason = :reason,
                 approved_by = :admin, approved_at = NOW()
             WHERE id = :jid"
        )->execute([':reason' => $reason ?: null, ':admin' => $adminId, ':jid' => $jobId]);

        $jRow = $db->prepare("SELECT j.title, j.employer_id FROM jobs j WHERE j.id = :jid LIMIT 1");
        $jRow->execute([':jid' => $jobId]);
        $j = $jRow->fetch();

        if ($j) {
            $msg = "Your job post \"{$j['title']}\" was not approved.";
            if ($reason !== '') {
                $msg .= " Reason: {$reason}";
            }
            $notify((int)$j['employer_id'], $adminId, 'general', $msg, $jobId, 'job');
        }

        // Expire any unread relevant_job notifications for this job
        try {
            $db->prepare(
                "UPDATE notifications SET is_expired = 1
                 WHERE type = 'relevant_job' AND reference_id = :jid
                   AND reference_type = 'job' AND is_read = 0"
            )->execute([':jid' => $jobId]);
        } catch (Throwable) {}

        // Mark the job approval request notification as read for all admins
        $db->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE type = 'job_approval_request' AND reference_id = :jid AND reference_type = 'job'"
        )->execute([':jid' => $jobId]);

        logActivity(
            $j ? (int)$j['employer_id'] : null,
            $adminId,
            'job_rejected', 'job', $jobId,
            "Admin rejected job post ID #{$jobId}. Reason: {$reason}"
        );

        exit(json_encode(['success' => true, 'message' => 'Job post rejected.']));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin reject_job: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── remove_job ── */
if ($action === 'remove_job') {
    $jobId  = (int)($body['job_id'] ?? 0);
    $reason = trim((string)($body['reason'] ?? ''));
    if ($jobId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid job ID.']));
    }
    try {
        $jRow = $db->prepare("SELECT title, employer_id FROM jobs WHERE id = :jid LIMIT 1");
        $jRow->execute([':jid' => $jobId]);
        $j = $jRow->fetch();

        $db->prepare(
            "UPDATE jobs SET status = 'Closed', approval_status = 'rejected',
                             approval_reason = :reason, approved_by = :admin, approved_at = NOW()
             WHERE id = :jid"
        )->execute([':reason' => $reason ?: null, ':admin' => $adminId, ':jid' => $jobId]);

        if ($j) {
            $msg = "Your job post \"{$j['title']}\" has been removed by an administrator.";
            if ($reason !== '') {
                $msg .= " Reason: {$reason}";
            }
            $notify((int)$j['employer_id'], $adminId, 'general', $msg, $jobId, 'job');
        }

        // Expire any unread relevant_job notifications for this job
        try {
            $db->prepare(
                "UPDATE notifications SET is_expired = 1
                 WHERE type = 'relevant_job' AND reference_id = :jid
                   AND reference_type = 'job' AND is_read = 0"
            )->execute([':jid' => $jobId]);
        } catch (Throwable) {}

        logActivity(
            $j ? (int)$j['employer_id'] : null,
            $adminId,
            'job_removed', 'job', $jobId,
            "Admin removed job post ID #{$jobId}: \"" . ($j['title'] ?? '') . '". Reason: ' . $reason
        );

        exit(json_encode(['success' => true, 'message' => 'Job post removed.']));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin remove_job: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

// ═══════════════════════════════════════════════════════════
// USER ACCOUNT ACTIONS
// ═══════════════════════════════════════════════════════════

/* ── suspend_user ── */
if ($action === 'suspend_user') {
    $userId    = (int)($body['user_id'] ?? 0);
    $reason    = trim((string)($body['reason'] ?? ''));
    $expiresAt = trim((string)($body['expires_at'] ?? ''));
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    if ($userId === $adminId) {
        exit(json_encode(['success' => false, 'message' => 'You cannot suspend your own account.']));
    }
    try {
        $db->prepare(
            "UPDATE users
             SET account_status = 'suspended', is_active = 0,
                 status_reason = :reason, status_expires_at = :exp,
                 status_updated_by = :admin, status_updated_at = NOW()
             WHERE id = :uid"
        )->execute([
            ':reason' => $reason ?: null,
            ':exp'    => $expiresAt !== '' ? $expiresAt : null,
            ':admin'  => $adminId,
            ':uid'    => $userId,
        ]);

        $uRow = $db->prepare("SELECT full_name, email FROM users WHERE id = :uid LIMIT 1");
        $uRow->execute([':uid' => $userId]);
        $u = $uRow->fetch();
        $uName = $u ? (string)$u['full_name'] : "User #{$userId}";

        logActivity($userId, $adminId, 'user_suspended', 'user', $userId,
            "Admin suspended {$uName} (#{$userId}). Reason: {$reason}. Expires: " . ($expiresAt ?: 'indefinite'));

        exit(json_encode(['success' => true, 'message' => "{$uName} has been suspended."]));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin suspend_user: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── ban_user ── */
if ($action === 'ban_user') {
    $userId = (int)($body['user_id'] ?? 0);
    $reason = trim((string)($body['reason'] ?? ''));
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    if ($userId === $adminId) {
        exit(json_encode(['success' => false, 'message' => 'You cannot ban your own account.']));
    }
    try {
        $db->prepare(
            "UPDATE users
             SET account_status = 'banned', is_active = 0,
                 status_reason = :reason, status_expires_at = NULL,
                 status_updated_by = :admin, status_updated_at = NOW()
             WHERE id = :uid"
        )->execute([':reason' => $reason ?: null, ':admin' => $adminId, ':uid' => $userId]);

        $uRow = $db->prepare("SELECT full_name FROM users WHERE id = :uid LIMIT 1");
        $uRow->execute([':uid' => $userId]);
        $u = $uRow->fetch();
        $uName = $u ? (string)$u['full_name'] : "User #{$userId}";

        logActivity($userId, $adminId, 'user_banned', 'user', $userId,
            "Admin permanently banned {$uName} (#{$userId}). Reason: {$reason}");

        exit(json_encode(['success' => true, 'message' => "{$uName} has been permanently banned."]));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin ban_user: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── unsuspend_user / unban_user ── */
if ($action === 'unsuspend_user' || $action === 'unban_user') {
    $userId = (int)($body['user_id'] ?? 0);
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    try {
        $db->prepare(
            "UPDATE users
             SET account_status = 'active', is_active = 1,
                 status_reason = NULL, status_expires_at = NULL,
                 status_updated_by = :admin, status_updated_at = NOW()
             WHERE id = :uid"
        )->execute([':admin' => $adminId, ':uid' => $userId]);

        $uRow = $db->prepare("SELECT full_name FROM users WHERE id = :uid LIMIT 1");
        $uRow->execute([':uid' => $userId]);
        $u = $uRow->fetch();
        $uName = $u ? (string)$u['full_name'] : "User #{$userId}";

        $actType = $action === 'unsuspend_user' ? 'user_unsuspended' : 'user_unbanned';
        logActivity($userId, $adminId, $actType, 'user', $userId,
            "Admin reinstated {$uName} (#{$userId}).");

        exit(json_encode(['success' => true, 'message' => "{$uName}'s account has been reinstated."]));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin unsuspend/unban: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── force_password_reset ── */
if ($action === 'force_password_reset') {
    $userId = (int)($body['user_id'] ?? 0);
    if ($userId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid user ID.']));
    }
    if ($userId === $adminId) {
        exit(json_encode(['success' => false, 'message' => 'You cannot reset your own password this way.']));
    }
    try {
        $db->prepare("UPDATE users SET must_change_password = 1 WHERE id = :uid")
           ->execute([':uid' => $userId]);
        $uRow = $db->prepare("SELECT full_name FROM users WHERE id = :uid LIMIT 1");
        $uRow->execute([':uid' => $userId]);
        $u = $uRow->fetch();
        $uName = $u ? (string)$u['full_name'] : "User #{$userId}";
        logActivity($userId, $adminId, 'force_password_reset', 'user', $userId,
            "Admin forced password reset for {$uName} (#{$userId}).");
        exit(json_encode(['success' => true, 'message' => "{$uName} will be prompted to reset their password on next login."]));
    } catch (Throwable $e) {
        error_log('[AntCareers] admin force_password_reset: ' . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── mark_notification_read ── */
if ($action === 'mark_notification_read') {
    $notifId = (int)($body['notification_id'] ?? 0);
    if ($notifId <= 0) {
        exit(json_encode(['success' => false, 'message' => 'Invalid notification ID.']));
    }
    try {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
           ->execute([':id' => $notifId, ':uid' => $adminId]);
        exit(json_encode(['success' => true]));
    } catch (Throwable) {
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── mark_all_notifications_read ── */
if ($action === 'mark_all_notifications_read') {
    try {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")
           ->execute([':uid' => $adminId]);
        exit(json_encode(['success' => true]));
    } catch (Throwable) {
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

/* ── delete_all_notifications ── */
if ($action === 'delete_all_notifications') {
    try {
        $db->prepare("DELETE FROM notifications WHERE user_id = :uid")
           ->execute([':uid' => $adminId]);
        exit(json_encode(['success' => true]));
    } catch (Throwable) {
        exit(json_encode(['success' => false, 'message' => 'Database error.']));
    }
}

// Unknown action
http_response_code(400);
exit(json_encode(['success' => false, 'message' => 'Unknown action.']));
