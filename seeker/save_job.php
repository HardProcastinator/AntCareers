<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/antcareers_login.php');
    exit;
}
if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    header('Location: ../index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../seeker/antcareers_seekerJobs.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$jobId  = (int)($_POST['job_id'] ?? 0);

if (!$jobId) {
    header('Location: ../seeker/antcareers_seekerJobs.php');
    exit;
}

$db = getDB();
$wantsJson = isset($_POST['json']) || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

try {
    // Toggle: if already saved, remove; otherwise add
    $check = $db->prepare('SELECT id FROM saved_jobs WHERE user_id = :uid AND job_id = :jid LIMIT 1');
    $check->execute([':uid' => $userId, ':jid' => $jobId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $db->prepare('DELETE FROM saved_jobs WHERE user_id = :uid AND job_id = :jid')
           ->execute([':uid' => $userId, ':jid' => $jobId]);
    } else {
        $db->prepare('INSERT INTO saved_jobs (user_id, job_id) VALUES (:uid, :jid)')
           ->execute([':uid' => $userId, ':jid' => $jobId]);
    }

    // Always return JSON — all save/unsave actions are performed via fetch()
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'     => true,
        'success'=> true,
        'saved'  => !$existing,
        'action' => $existing ? 'removed' : 'saved',
    ]);
    exit;

} catch (Throwable $e) {
    error_log('[AntCareers] Save job error: ' . $e->getMessage());
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not update saved jobs']);
        exit;
    }
    header('Location: ../seeker/antcareers_seekerJobs.php?error=save');
    exit;
}
