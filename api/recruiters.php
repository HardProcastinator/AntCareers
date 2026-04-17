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
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName  = trim((string)($_POST['last_name'] ?? ''));
        $position  = trim((string)($_POST['position'] ?? ''));
        $personalEmail = trim((string)($_POST['personal_email'] ?? ''));

        if (!$firstName || !$lastName || !$personalEmail) {
            jsonResponse(['success' => false, 'message' => 'First name, last name, and personal email are required.']);
        }
        if (!filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => 'Invalid email address.']);
        }

        // Get company profile + company name for platform email
        $cp = $db->prepare('SELECT id, company_name FROM company_profiles WHERE user_id = :uid');
        $cp->execute([':uid' => $userId]);
        $company = $cp->fetch();
        if (!$company) {
            // Auto-create company_profiles from users.company_name
            $uq = $db->prepare('SELECT company_name FROM users WHERE id = :uid');
            $uq->execute([':uid' => $userId]);
            $uRow = $uq->fetch();
            $fallbackName = $uRow && $uRow['company_name'] ? $uRow['company_name'] : '';
            if (!$fallbackName) {
                jsonResponse(['success' => false, 'message' => 'No company name found. Please set up your company profile first.']);
            }
            $db->prepare('INSERT INTO company_profiles (user_id, company_name) VALUES (:uid, :cn)')
               ->execute([':uid' => $userId, ':cn' => $fallbackName]);
            $company = ['id' => (int)$db->lastInsertId(), 'company_name' => $fallbackName];
        }

        // Generate platform email: f.lastname@company.work
        $companySlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $company['company_name']));
        if ($companySlug === '') $companySlug = 'company';
        $platformEmail = strtolower(substr($firstName, 0, 1)) . '.' . strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName)) . '@' . $companySlug . '.work';

        // Check if platform email already exists → add number suffix
        $check = $db->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute([':email' => $platformEmail]);
        if ($check->fetch()) {
            $suffix = 1;
            do {
                $try = strtolower(substr($firstName, 0, 1)) . '.' . strtolower(preg_replace('/[^a-zA-Z]/', '', $lastName)) . $suffix . '@' . $companySlug . '.work';
                $check->execute([':email' => $try]);
                $suffix++;
            } while ($check->fetch());
            $platformEmail = $try;
        }

        $name = $firstName . ' ' . $lastName;

        // Generate temp password
        $tempPassword = generateTempPassword();
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        $db->beginTransaction();
        try {
            // Create user account with the platform email
            $stmt = $db->prepare(
                'INSERT INTO users (email, password_hash, full_name, account_type, is_active, must_change_password)
                 VALUES (:email, :hash, :name, :type, 1, 1)'
            );
            $stmt->execute([
                ':email' => $platformEmail,
                ':hash'  => $passwordHash,
                ':name'  => $name,
                ':type'  => 'recruiter',
            ]);
            $newUserId = (int)$db->lastInsertId();

            // Link to company
            $stmt = $db->prepare(
                'INSERT INTO recruiters (user_id, company_id, employer_id, role, is_active, accepted_at)
                 VALUES (:uid, :cid, :eid, :role, 1, NOW())'
            );
            $stmt->execute([
                ':uid'  => $newUserId,
                ':cid'  => (int)$company['id'],
                ':eid'  => $userId,
                ':role' => 'recruiter',
            ]);
            $recruitersRowId = (int)$db->lastInsertId();

            // Create recruiter profile with position and personal email
            $stmt = $db->prepare(
                'INSERT INTO recruiter_profiles (user_id, personal_email, position)
                 VALUES (:uid, :pemail, :pos)'
            );
            $stmt->execute([
                ':uid'    => $newUserId,
                ':pemail' => $personalEmail,
                ':pos'    => $position,
            ]);

            // Create stats record
            $stmt = $db->prepare(
                'INSERT INTO recruiter_stats (recruiter_id) VALUES (:rid)'
            );
            $stmt->execute([':rid' => $recruitersRowId]);

            // ── Deliver credentials via in-platform messaging to personal email account ──
            // Find user account with the personal email (seeker/employer account)
            $recipientStmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $recipientStmt->execute([':email' => $personalEmail]);
            $recipient = $recipientStmt->fetch();

            if ($recipient) {
                $recipientId = (int)$recipient['id'];
                // Create conversation using the existing direct:MIN:MAX format
                $convKey = 'direct:' . min($userId, $recipientId) . ':' . max($userId, $recipientId);
                $cs = $db->prepare('SELECT id FROM conversations WHERE conversation_key = :key LIMIT 1');
                $cs->execute([':key' => $convKey]);
                $conv = $cs->fetch();
                if ($conv) {
                    $convId = (int)$conv['id'];
                } else {
                    $db->prepare('INSERT INTO conversations (conversation_key, participant_a_id, participant_b_id) VALUES (:key, :a, :b)')
                       ->execute([':key' => $convKey, ':a' => min($userId, $recipientId), ':b' => max($userId, $recipientId)]);
                    $convId = (int)$db->lastInsertId();
                }

                $msgBody = "Hello {$firstName},\n\n"
                         . "You have been added as a Recruiter for {$company['company_name']}.\n\n"
                         . "Here are your login credentials:\n"
                         . "• Platform Email: {$platformEmail}\n"
                         . "• Temporary Password: {$tempPassword}\n\n"
                         . "Please log in at " . APP_URL . " and change your password immediately.\n\n"
                         . "— {$company['company_name']} Admin";

                $db->prepare('INSERT INTO messages (sender_id, receiver_id, conversation_id, subject, body, is_read) VALUES (:sid, :rid, :cid, :subj, :body, 0)')
                   ->execute([
                       ':sid'  => $userId,
                       ':rid'  => $recipientId,
                       ':cid'  => $convId,
                       ':subj' => 'Your Recruiter Account Credentials',
                       ':body' => $msgBody,
                   ]);
                $msgId = (int)$db->lastInsertId();
                $db->prepare('UPDATE conversations SET latest_message_id = :mid, latest_message_at = NOW() WHERE id = :cid')
                   ->execute([':mid' => $msgId, ':cid' => $convId]);

                // Also send a notification
                $db->prepare('INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (:uid, :actor, :type, :content, :ref, :reftype)')
                   ->execute([
                       ':uid'     => $recipientId,
                       ':actor'   => $userId,
                       ':type'    => 'recruiter_credentials',
                       ':content' => "You've been added as a recruiter for {$company['company_name']}. Check your messages for login credentials.",
                       ':ref'     => $newUserId,
                       ':reftype' => 'user',
                   ]);
            }

            // Notification for admin (self)
            $db->prepare('INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (:uid, :actor, :type, :content, :ref, :reftype)')
               ->execute([
                   ':uid'     => $userId,
                   ':actor'   => $userId,
                   ':type'    => 'recruiter_added',
                   ':content' => "Recruiter {$name} ({$platformEmail}) has been added.",
                   ':ref'     => $newUserId,
                   ':reftype' => 'user',
               ]);

            $db->commit();

            jsonResponse([
                'success'        => true,
                'message'        => 'Recruiter created successfully. Credentials sent via message.',
                'recruiter'      => [
                    'name'           => $name,
                    'platform_email' => $platformEmail,
                    'temp_password'  => $tempPassword,
                    'personal_email' => $personalEmail,
                    'position'       => $position,
                ],
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            error_log('[AntCareers] add_recruiter error: ' . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Failed to create recruiter: ' . $e->getMessage()], 500);
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
                   COALESCE(rs.hires_made, 0) AS hires_made,
                   rp.position, rp.personal_email
            FROM recruiters r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN recruiter_stats rs ON rs.recruiter_id = r.id
            LEFT JOIN recruiter_profiles rp ON rp.user_id = r.user_id
            WHERE r.company_id = :cid
            ORDER BY r.is_active DESC, u.full_name ASC
        ");
        $stmt->execute([':cid' => (int)$company['id']]);
        $rawRecruiters = $stmt->fetchAll();

        // Map to frontend-expected field names
        $recruiters = array_map(function ($r) {
            return [
                'id'                 => (int)$r['recruiter_id'],
                'user_id'            => (int)$r['user_id'],
                'name'               => $r['full_name'],
                'email'              => $r['email'],
                'personal_email'     => $r['personal_email'] ?? '',
                'position'           => $r['position'] ?? '',
                'role_label'         => ucfirst($r['role']),
                'status'             => $r['is_active'] ? 'active' : 'inactive',
                'avatar_url'         => $r['avatar_url'],
                'jobs_posted'        => (int)$r['jobs_posted'],
                'applicants_reviewed'=> (int)$r['applicants_reviewed'],
                'hired_count'        => (int)$r['hires_made'],
                'joined_at'          => $r['accepted_at'] ?? $r['invited_at'],
                'last_login_at'      => $r['last_login_at'],
                'deactivated_at'     => $r['deactivated_at'],
            ];
        }, $rawRecruiters);

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

        jsonResponse(['success' => true, 'message' => 'Password changed successfully.', 'redirect' => '../recruiter/recruiter_profile.php?setup=1']);
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
    return 'direct:' . min($a, $b) . ':' . max($a, $b);
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
