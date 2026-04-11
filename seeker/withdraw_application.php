<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in as a seeker
if (!isset($_SESSION['user_id']) || strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized.']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

$seekerId      = (int)$_SESSION['user_id'];
$applicationId = (int)($_POST['application_id'] ?? 0);

if ($applicationId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid application ID.']));
}

try {
    $db = getDB();

    // Verify the application belongs to this seeker and is not already rejected/hired
    $check = $db->prepare("
        SELECT id, status FROM applications
        WHERE id = :id AND seeker_id = :sid
        LIMIT 1
    ");
    $check->execute([':id' => $applicationId, ':sid' => $seekerId]);
    $app = $check->fetch();

    if (!$app) {
        http_response_code(404);
        exit(json_encode(['success' => false, 'message' => 'Application not found.']));
    }

    if (in_array(strtolower($app['status']), ['hired'], true)) {
        exit(json_encode(['success' => false, 'message' => 'Cannot withdraw a hired application.']));
    }

    // Delete the application (cascade will clean interview_schedules too)
    $del = $db->prepare("DELETE FROM applications WHERE id = :id AND seeker_id = :sid");
    $del->execute([':id' => $applicationId, ':sid' => $seekerId]);

    exit(json_encode(['success' => true]));

} catch (PDOException $e) {
    error_log('[AntCareers] withdraw_application error: ' . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Server error. Please try again.']));
}