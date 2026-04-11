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
    header('Location: antcareers_seekerProfile.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$resumeId = (int)($_POST['resume_id'] ?? 0);

if (!$resumeId) {
    header('Location: antcareers_seekerProfile.php?error=notfound');
    exit;
}

$db = getDB();

try {
    // Verify the resume belongs to this user
    $stmt = $db->prepare(
        'SELECT id, file_path FROM seeker_resumes WHERE id = :id AND user_id = :uid LIMIT 1'
    );
    $stmt->execute([':id' => $resumeId, ':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: antcareers_seekerProfile.php?error=notfound');
        exit;
    }

    // Delete the physical file
    $fullPath = dirname(__DIR__) . '/' . ltrim((string)$row['file_path'], '/');
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    // Delete the database record
    $del = $db->prepare('DELETE FROM seeker_resumes WHERE id = :id AND user_id = :uid');
    $del->execute([':id' => $resumeId, ':uid' => $userId]);

    header('Location: antcareers_seekerProfile.php?success=1');
    exit;

} catch (Throwable $e) {
    error_log('[AntCareers] Delete resume error: ' . $e->getMessage());
    header('Location: antcareers_seekerProfile.php?error=delete');
    exit;
}
