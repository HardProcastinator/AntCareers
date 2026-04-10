<?php
/**
 * AntCareers — Recruiter Management API
 * api/recruiters.php
 *
 * Handles all recruiter operations:
 *   - add_recruiter       (employer only)
 *   - list_recruiters     (employer only)
 *   - deactivate_recruiter (employer only)
 *   - reactivate_recruiter (employer only)
 *   - reset_password      (employer only)
 *   - reassign_jobs       (employer only)
 *   - recruiter_stats     (employer only)
 *   - mark_hired          (employer/recruiter)
 *   - force_password_change (recruiter first-login)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

$db     = getDB();
$userId = (int)$_SESSION['user_id'];
$role   = strtolower((string)($_SESSION['account_type'] ?? ''));
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure recruiter tables exist (auto-migrate)
ensureRecruiterTables($db);

switch ($action) {

    // ─── ADD RECRUITER ───
    case 'add_recruiter':
        requireEmployerRole($role);
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if (!$name || !$email) {
            jsonResponse(['success' => false, 'message' => 'Name and email are required.']);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Invalid email address.']);
        }

        // Check if email already exists
        $check = $db->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            jsonResponse(['success' => false, 'message' => 'An account with this email already exists.']);
        }

        // Get company profile
        $cp = $db->prepare('SELECT id FROM company_profiles WHERE user_id = :uid');
        $cp->execute([':uid' => $userId]);
        $company = $cp->fetch();
        if (!$company) {
            jsonResponse(['success' => false, 'message' => 'Company profile not found.']);
        }

        // Generate temp password
        $tempPassword = generateTempPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $db->beginTransaction();
        try {
            // Create user account
            $stmt = $db->prepare(
                'INSERT INTO users (email, password_hash, full_name, account_type, is_active, must_change_password)
                 VALUES (:email, :hash, :name, :type, 1, 1)'
            );
            $stmt->execute([
                ':email' => $email,
                ':hash'  => $passwordHash,
                ':name'  => $name,
                ':type'  => 'recruiter',
            ]);
            $recruiterId = (int)$db->lastInsertId();

            // Link to company
            $stmt = $db->prepare(
                'INSERT INTO recruiters (user_id, company_id, employer_id, role, is_active, accepted_at)
                 VALUES (:uid, :cid, :eid, :role, 1, NOW())'
            );
            $stmt->execute([
                ':uid'  => $recruiterId,
                ':cid'  => (int)$company['id'],
                ':eid'  => $userId,
                ':role' => 'recruiter',
            ]);

            // Create stats record
            $stmt = $db->prepare(
                'INSERT INTO recruiter_stats (recruiter_id) VALUES (:rid)'
            );
            $stmt->execute([':rid' => (int)$db->lastInsertId()]);

            // Create notification for admin
            $stmt = $db->prepare(
                'INSERT INTO notifications (user_id, type, content, reference_id)
                 VALUES (:uid, :type, :content, :ref)'
            );
            $stmt->execute([
                ':uid'     => $userId,
                ':type'    => 'recruiter_added',
                ':content' => "Recruiter {$name} ({$email}) has been added. Temporary password: {$tempPassword}",
                ':ref'     => $recruiterId,
            ]);

            $db->commit();

            // TODO: Send email with temp password (mail() or PHPMailer)
            // For now, return temp password in response for local dev
            jsonResponse([
                'success'       => true,
                'message'       => 'Recruiter created successfully.',
                'recruiter_id'  => $recruiterId,
                'temp_password' => $tempPassword, // Remove in production
                'email'         => $email,
                'name'          => $name,
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log('[AntCareers] add_recruiter error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Failed to create recruiter.'], 500);
        }
        break;

    // ─── LIST RECRUITERS ───
    case 'list_recruiters':
        requireEmployerRole($role);
        $cp = $db->prepare('SELECT id FROM company_profiles WHERE user_id = :uid');
        $cp->execute([':uid' => $userId]);
        $company = $cp->fetch();
        if (!$company) jsonResponse(['success' => false, 'message' => 'No company.']);

        $stmt = $db->prepare("
            SELECT r.id AS recruiter_id, r.user_id, r.role, r.is_active, r.invited_at, r.accepted_at, r.deactivated_at,
                   u.full_name, u.email, u.last_login_at, u.avatar_url,
                   COALESCE(rs.jobs_posted, 0) AS jobs_posted,
                   COALESCE(rs.applicants_reviewed, 0) AS applicants_reviewed,
                   COALESCE(rs.hires_made, 0) AS hires_made
            FROM recruiters r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN recruiter_stats rs ON rs.recruiter_id = r.id
            WHERE r.company_id = :cid
            ORDER BY r.is_active DESC, u.full_name ASC
        ");
        $stmt->execute([':cid' => (int)$company['id']]);
        $recruiters = $stmt->fetchAll();

        jsonResponse(['success' => true, 'recruiters' => $recruiters]);
        break;

    // ─── DEACTIVATE RECRUITER ───
    case 'deactivate_recruiter':
        requireEmployerRole($role);
        $recId = (int)($_POST['recruiter_id'] ?? 0);
        if (!$recId) jsonResponse(['success' => false, 'message' => 'Missing recruiter_id.']);

        $stmt = $db->prepare(
            'UPDATE recruiters SET is_active = 0, deactivated_at = NOW() WHERE id = :id AND employer_id = :eid'
        );
        $stmt->execute([':id' => $recId, ':eid' => $userId]);

        // Also deactivate the user account
        $stmt = $db->prepare(
            'UPDATE users SET is_active = 0 WHERE id = (SELECT user_id FROM recruiters WHERE id = :id)'
        );
        $stmt->execute([':id' => $recId]);

        jsonResponse(['success' => true, 'message' => 'Recruiter deactivated.']);
        break;

    // ─── REACTIVATE RECRUITER ───
    case 'reactivate_recruiter':
        requireEmployerRole($role);
        $recId = (int)($_POST['recruiter_id'] ?? 0);
        if (!$recId) jsonResponse(['success' => false, 'message' => 'Missing recruiter_id.']);

        $stmt = $db->prepare(
            'UPDATE recruiters SET is_active = 1, deactivated_at = NULL WHERE id = :id AND employer_id = :eid'
        );
        $stmt->execute([':id' => $recId, ':eid' => $userId]);

        $stmt = $db->prepare(
            'UPDATE users SET is_active = 1 WHERE id = (SELECT user_id FROM recruiters WHERE id = :id)'
        );
        $stmt->execute([':id' => $recId]);

        jsonResponse(['success' => true, 'message' => 'Recruiter reactivated.']);
        break;

    // ─── RESET RECRUITER PASSWORD ───
    case 'reset_password':
        requireEmployerRole($role);
        $recId = (int)($_POST['recruiter_id'] ?? 0);
        if (!$recId) jsonResponse(['success' => false, 'message' => 'Missing recruiter_id.']);

        $tempPassword = generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $stmt = $db->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 1
             WHERE id = (SELECT user_id FROM recruiters WHERE id = :id AND employer_id = :eid)'
        );
        $stmt->execute([':hash' => $hash, ':id' => $recId, ':eid' => $userId]);

        jsonResponse([
            'success'       => true,
            'message'       => 'Password reset. Recruiter must change on next login.',
            'temp_password' => $tempPassword, // Remove in production
        ]);
        break;

    // ─── REASSIGN JOBS ───
    case 'reassign_jobs':
        requireEmployerRole($role);
        $fromRecId = (int)($_POST['from_recruiter_id'] ?? 0);
        $toRecId   = (int)($_POST['to_recruiter_id'] ?? 0);
        if (!$fromRecId || !$toRecId) {
            jsonResponse(['success' => false, 'message' => 'Both recruiter IDs required.']);
        }

        // Get user_ids for the recruiters
        $stmt = $db->prepare('SELECT user_id FROM recruiters WHERE id = :id AND employer_id = :eid');
        $stmt->execute([':id' => $fromRecId, ':eid' => $userId]);
        $from = $stmt->fetch();

        $stmt->execute([':id' => $toRecId, ':eid' => $userId]);
        $to = $stmt->fetch();

        if (!$from || !$to) {
            jsonResponse(['success' => false, 'message' => 'Invalid recruiters.']);
        }

        $stmt = $db->prepare(
            'UPDATE jobs SET recruiter_id = :to WHERE recruiter_id = :from AND employer_id = :eid'
        );
        $stmt->execute([
            ':to'   => (int)$to['user_id'],
            ':from' => (int)$from['user_id'],
            ':eid'  => $userId,
        ]);

        $count = $stmt->rowCount();
        jsonResponse(['success' => true, 'message' => "{$count} jobs reassigned."]);
        break;

    // ─── MARK HIRED — triggers credential generation ───
    case 'mark_hired':
        if (!in_array($role, ['employer', 'recruiter'])) {
            jsonResponse(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $applicationId = (int)($_POST['application_id'] ?? 0);
        if (!$applicationId) jsonResponse(['success' => false, 'message' => 'Missing application_id.']);

        // Get application details
        $stmt = $db->prepare("
            SELECT a.id, a.seeker_id, a.job_id, a.status,
                   j.employer_id, j.recruiter_id, j.title AS job_title,
                   u.full_name AS seeker_name, u.email AS seeker_email,
                   COALESCE(cp.company_name, eu.company_name) AS company_name,
                   cp.id AS company_profile_id
            FROM applications a
            JOIN jobs j ON j.id = a.job_id
            JOIN users u ON u.id = a.seeker_id
            JOIN users eu ON eu.id = j.employer_id
            LEFT JOIN company_profiles cp ON cp.user_id = j.employer_id
            WHERE a.id = :aid
        ");
        $stmt->execute([':aid' => $applicationId]);
        $app = $stmt->fetch();

        if (!$app) jsonResponse(['success' => false, 'message' => 'Application not found.']);

        // Check ownership
        if ($role === 'recruiter') {
            if ((int)$app['recruiter_id'] !== $userId) {
                jsonResponse(['success' => false, 'message' => 'You can only manage your own job posts.'], 403);
            }
        } elseif ($role === 'employer') {
            if ((int)$app['employer_id'] !== $userId) {
                jsonResponse(['success' => false, 'message' => 'Not your job post.'], 403);
            }
        }

        // Check not already hired
        if ($app['status'] === 'Hired') {
            jsonResponse(['success' => false, 'message' => 'Applicant is already hired.']);
        }

        $db->beginTransaction();
        try {
            // Update application status
            $stmt = $db->prepare(
                'UPDATE applications SET status = :status, reviewed_at = NOW() WHERE id = :id'
            );
            $stmt->execute([':status' => 'Hired', ':id' => $applicationId]);

            // Generate recruiter credentials for the hired seeker
            $tempUsername = generateRecruiterUsername($app['seeker_name']);
            $tempPassword = generateTempPassword();
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Create a new recruiter account for the hired seeker
            $stmt = $db->prepare(
                'INSERT INTO users (email, password_hash, full_name, account_type, is_active, must_change_password)
                 VALUES (:email, :hash, :name, :type, 1, 1)'
            );
            // Use a variation email so it doesn't conflict
            $recruiterEmail = 'rec_' . $app['seeker_id'] . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '@' . parse_url(APP_URL, PHP_URL_HOST);
            $stmt->execute([
                ':email' => $recruiterEmail,
                ':hash'  => $passwordHash,
                ':name'  => $app['seeker_name'],
                ':type'  => 'recruiter',
            ]);
            $newRecruiterId = (int)$db->lastInsertId();

            // Link to company
            $stmt = $db->prepare(
                'INSERT INTO recruiters (user_id, company_id, employer_id, role, is_active, accepted_at)
                 VALUES (:uid, :cid, :eid, :role, 1, NOW())'
            );
            $stmt->execute([
                ':uid'  => $newRecruiterId,
                ':cid'  => (int)$app['company_profile_id'],
                ':eid'  => (int)$app['employer_id'],
                ':role' => 'recruiter',
            ]);

            // Store hired credentials record
            $stmt = $db->prepare(
                'INSERT INTO hired_credentials (application_id, seeker_id, recruiter_user_id, company_id, temp_username, temp_password_hash)
                 VALUES (:aid, :sid, :ruid, :cid, :uname, :phash)'
            );
            $stmt->execute([
                ':aid'   => $applicationId,
                ':sid'   => (int)$app['seeker_id'],
                ':ruid'  => $newRecruiterId,
                ':cid'   => (int)$app['company_profile_id'],
                ':uname' => $tempUsername,
                ':phash' => $passwordHash,
            ]);

            // Send notification to the hired seeker
            $notifContent = "Congratulations! You've been hired for \"{$app['job_title']}\" at {$app['company_name']}!\n\n"
                          . "Your recruiter credentials:\n"
                          . "Username: {$recruiterEmail}\n"
                          . "Temporary Password: {$tempPassword}\n\n"
                          . "Please log in and change your password immediately.";

            $stmt = $db->prepare(
                'INSERT INTO notifications (user_id, type, content, reference_id)
                 VALUES (:uid, :type, :content, :ref)'
            );
            $stmt->execute([
                ':uid'     => (int)$app['seeker_id'],
                ':type'    => 'hired_credential',
                ':content' => $notifContent,
                ':ref'     => $applicationId,
            ]);

            // Also send as a direct message
            $convKey = conversation_key_for($userId, (int)$app['seeker_id']);
            $convId  = getOrCreateConversation($db, $userId, (int)$app['seeker_id'], $convKey);

            $stmt = $db->prepare(
                'INSERT INTO messages (conversation_id, sender_id, receiver_id, subject, body, is_read)
                 VALUES (:cid, :sid, :rid, :subj, :body, 0)'
            );
            $stmt->execute([
                ':cid'  => $convId,
                ':sid'  => $userId,
                ':rid'  => (int)$app['seeker_id'],
                ':subj' => 'Congratulations — You\'re Hired!',
                ':body' => $notifContent,
            ]);

            // Update conversation latest
            $msgId = (int)$db->lastInsertId();
            $db->prepare('UPDATE conversations SET latest_message_id = :mid, latest_message_at = NOW() WHERE id = :cid')
               ->execute([':mid' => $msgId, ':cid' => $convId]);

            $db->commit();

            jsonResponse([
                'success'        => true,
                'message'        => 'Applicant marked as hired. Recruiter credentials generated and sent.',
                'recruiter_email'=> $recruiterEmail,
                'temp_password'  => $tempPassword, // Remove in production
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log('[AntCareers] mark_hired error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Failed to process hire.'], 500);
        }
        break;

    // ─── FORCE PASSWORD CHANGE (first login) ───
    case 'force_password_change':
        if (empty($_SESSION['must_change_password'])) {
            jsonResponse(['success' => false, 'message' => 'Not required.']);
        }

        $newPassword = trim((string)($_POST['new_password'] ?? ''));
        $confirm     = trim((string)($_POST['confirm_password'] ?? ''));

        if (strlen($newPassword) < 8) {
            jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        }
        if ($newPassword !== $confirm) {
            jsonResponse(['success' => false, 'message' => 'Passwords do not match.']);
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id'
        );
        $stmt->execute([':hash' => $hash, ':id' => $userId]);

        unset($_SESSION['must_change_password']);

        jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        break;

    // ─── RECRUITER STATS ───
    case 'recruiter_stats':
        requireEmployerRole($role);
        $recId = (int)($_GET['recruiter_id'] ?? 0);
        if (!$recId) jsonResponse(['success' => false, 'message' => 'Missing recruiter_id.']);

        $stmt = $db->prepare("
            SELECT rs.*, u.full_name, u.email, u.last_login_at, r.is_active
            FROM recruiter_stats rs
            JOIN recruiters r ON r.id = rs.recruiter_id
            JOIN users u ON u.id = r.user_id
            WHERE rs.recruiter_id = :id AND r.employer_id = :eid
        ");
        $stmt->execute([':id' => $recId, ':eid' => $userId]);
        $stats = $stmt->fetch();

        jsonResponse(['success' => true, 'stats' => $stats ?: []]);
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
}


// ─── Helper Functions ───

function requireEmployerRole(string $role): void
{
    if ($role !== 'employer') {
        jsonResponse(['success' => false, 'message' => 'Employer access required.'], 403);
    }
}

function generateTempPassword(): string
{
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < 10; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password . '!';
}

function generateRecruiterUsername(string $fullName): string
{
    $parts = preg_split('/\s+/', strtolower(trim($fullName)));
    $base = implode('.', array_slice($parts, 0, 2));
    return $base . '.' . substr(bin2hex(random_bytes(2)), 0, 4);
}

function conversation_key_for(int $a, int $b): string
{
    return 'conv_' . min($a, $b) . '_' . max($a, $b);
}

function getOrCreateConversation(PDO $db, int $senderId, int $receiverId, string $key): int
{
    $stmt = $db->prepare('SELECT id FROM conversations WHERE conversation_key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['id'];

    $stmt = $db->prepare(
        'INSERT INTO conversations (conversation_key, participant_a_id, participant_b_id) VALUES (:key, :a, :b)'
    );
    $stmt->execute([':key' => $key, ':a' => min($senderId, $receiverId), ':b' => max($senderId, $receiverId)]);
    return (int)$db->lastInsertId();
}

function ensureRecruiterTables(PDO $db): void
{
    try {
        $db->query('SELECT 1 FROM recruiters LIMIT 1');
    } catch (\PDOException $e) {
        // Tables don't exist yet — create them
        $migration = file_get_contents(dirname(__DIR__) . '/sql/migration_recruiter.sql');
        if ($migration) {
            // Execute each statement separately
            $statements = array_filter(array_map('trim', explode(';', $migration)));
            foreach ($statements as $sql) {
                if (!empty($sql) && !str_starts_with($sql, '--')) {
                    try {
                        $db->exec($sql);
                    } catch (\PDOException $ex) {
                        // Ignore individual statement errors (e.g. column already exists)
                        error_log('[AntCareers] recruiter migration statement: ' . $ex->getMessage());
                    }
                }
            }
        }
    }

    // Also ensure must_change_password column exists
    try {
        $db->query('SELECT must_change_password FROM users LIMIT 1');
    } catch (\PDOException $e) {
        try {
            $db->exec('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        } catch (\PDOException $ex) {
            // ignore
        }
    }
}
