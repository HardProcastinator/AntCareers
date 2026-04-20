<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/auth_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

// CSRF check
$csrfSent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!is_string($csrfSent) || !hash_equals(csrfToken(), $csrfSent)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token. Please refresh the page and try again.']));
}

$seekerId    = (int)$_SESSION['user_id'];
$jobId       = (int)($_POST['job_id'] ?? 0);
$coverLetter = trim((string)($_POST['cover_letter'] ?? ''));

if ($jobId <= 0) {
    exit(json_encode(['success' => false, 'message' => 'Invalid job ID.']));
}

try {
    $db = getDB();

    // Verify job is active
    $job = $db->prepare("SELECT id, status FROM jobs WHERE id = :id AND status = 'Active' LIMIT 1");
    $job->execute([':id' => $jobId]);
    if (!$job->fetch()) {
        exit(json_encode(['success' => false, 'message' => 'This job is no longer available.']));
    }

    // Check not already applied
    $check = $db->prepare("SELECT id FROM applications WHERE job_id = :jid AND seeker_id = :sid LIMIT 1");
    $check->execute([':jid' => $jobId, ':sid' => $seekerId]);
    if ($check->fetch()) {
        exit(json_encode(['success' => false, 'message' => 'You have already applied for this job.']));
    }

    // Get active resume URL if any
    $resumeUrl = null;
    try {
        $rStmt = $db->prepare("SELECT file_path FROM seeker_resumes WHERE user_id = :uid AND is_active = 1 ORDER BY uploaded_at DESC LIMIT 1");
        $rStmt->execute([':uid' => $seekerId]);
        $res = $rStmt->fetch();
        if ($res) $resumeUrl = $res['file_path'];
    } catch (PDOException $e) { /* resume table may not exist */ }

    // Insert application
    $ins = $db->prepare("
        INSERT INTO applications (job_id, seeker_id, cover_letter, resume_url, status, applied_at)
        VALUES (:jid, :sid, :cl, :ru, 'Pending', NOW())
    ");
    $ins->execute([
        ':jid' => $jobId,
        ':sid' => $seekerId,
        ':cl'  => $coverLetter ?: null,
        ':ru'  => $resumeUrl,
    ]);

    // Notify employer and assigned recruiter about new application
    try {
        $jobInfo = $db->prepare("SELECT j.title, j.employer_id, j.recruiter_id, u.full_name FROM jobs j JOIN users u ON u.id = :sid WHERE j.id = :jid LIMIT 1");
        $jobInfo->execute([':jid' => $jobId, ':sid' => $seekerId]);
        $ji = $jobInfo->fetch();
        if ($ji) {
            $seekerName = trim((string)($ji['full_name'] ?? '')) ?: 'A job seeker';
            $notifContent = htmlspecialchars($seekerName) . ' applied for "' . htmlspecialchars($ji['title'] ?? 'a job') . '"';
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type) VALUES (:uid, :actor, 'new_application', :content, :ref, 'job')");
            // Notify employer
            if (!empty($ji['employer_id'])) {
                $notifStmt->execute([':uid' => $ji['employer_id'], ':actor' => $seekerId, ':content' => $notifContent, ':ref' => $jobId]);
            }
            // Notify assigned recruiter (if any)
            if (!empty($ji['recruiter_id']) && $ji['recruiter_id'] != ($ji['employer_id'] ?? 0)) {
                $notifStmt->execute([':uid' => $ji['recruiter_id'], ':actor' => $seekerId, ':content' => $notifContent, ':ref' => $jobId]);
            }
        }
    } catch (PDOException $e) { /* notification failure should not block application */ }

    $appId = (int)$db->lastInsertId();
    logActivity($seekerId, $seekerId, 'application_made', 'application', $appId, "Seeker applied to job ID {$jobId}.");

    // If the seeker had a pending invitation for this job, mark it as accepted silently
    // (no extra notification — the employer already got the new_application notification above)
    try {
        $db->prepare("UPDATE job_invitations SET status = 'accepted', responded_at = NOW() WHERE job_id = ? AND jobseeker_id = ? AND status = 'pending'")
           ->execute([$jobId, $seekerId]);
    } catch (PDOException $e) { /* non-critical */ }

    exit(json_encode(['success' => true]));

} catch (PDOException $e) {
    error_log('[AntCareers] apply_job error: ' . $e->getMessage());
    // Check for duplicate key (race condition)
    if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() == 23000) {
        exit(json_encode(['success' => false, 'message' => 'You have already applied for this job.']));
    }
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Server error. Please try again.']));
}
