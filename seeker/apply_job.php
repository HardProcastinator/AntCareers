<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
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
        $jobInfo = $db->prepare("SELECT j.title, j.employer_id, j.recruiter_id, u.first_name, u.last_name FROM jobs j JOIN users u ON u.id = :sid WHERE j.id = :jid LIMIT 1");
        $jobInfo->execute([':jid' => $jobId, ':sid' => $seekerId]);
        $ji = $jobInfo->fetch();
        if ($ji) {
            $seekerName = trim(($ji['first_name'] ?? '') . ' ' . ($ji['last_name'] ?? '')) ?: 'A job seeker';
            $notifContent = htmlspecialchars($seekerName) . ' applied for "' . htmlspecialchars($ji['title'] ?? 'a job') . '"';
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, type, content, reference_id) VALUES (:uid, 'new_application', :content, :ref)");
            // Notify employer
            if (!empty($ji['employer_id'])) {
                $notifStmt->execute([':uid' => $ji['employer_id'], ':content' => $notifContent, ':ref' => $jobId]);
            }
            // Notify assigned recruiter (if any)
            if (!empty($ji['recruiter_id']) && $ji['recruiter_id'] != ($ji['employer_id'] ?? 0)) {
                $notifStmt->execute([':uid' => $ji['recruiter_id'], ':content' => $notifContent, ':ref' => $jobId]);
            }
        }
    } catch (PDOException $e) { /* notification failure should not block application */ }

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
