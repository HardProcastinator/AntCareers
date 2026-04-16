<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    echo json_encode(['ok' => false, 'error' => 'Only seekers can follow companies']);
    exit;
}

$userId     = (int)$_SESSION['user_id'];
$employerId = (int)($_POST['employer_id'] ?? 0);

if (!$employerId || $employerId === $userId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid employer']);
    exit;
}

$db = getDB();

// Ensure company_follows table exists (auto-migration)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS company_follows (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_user_id INT UNSIGNED NOT NULL,
        employer_user_id INT UNSIGNED NOT NULL,
        followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_follow (follower_user_id, employer_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $_) {}

try {
    $check = $db->prepare("SELECT id FROM company_follows WHERE follower_user_id = :uid AND employer_user_id = :eid LIMIT 1");
    $check->execute([':uid' => $userId, ':eid' => $employerId]);
    $existing = $check->fetchColumn();

    if ($existing) {
        $del = $db->prepare("DELETE FROM company_follows WHERE follower_user_id = :uid AND employer_user_id = :eid");
        $del->execute([':uid' => $userId, ':eid' => $employerId]);
        $following = false;
    } else {
        $ins = $db->prepare("INSERT IGNORE INTO company_follows (follower_user_id, employer_user_id) VALUES (:uid, :eid)");
        $ins->execute([':uid' => $userId, ':eid' => $employerId]);
        $following = true;
    }

    // Notify employer about follow/unfollow
    try {
        $nameStmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = :uid LIMIT 1");
        $nameStmt->execute([':uid' => $userId]);
        $u = $nameStmt->fetch();
        $followerName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Someone';
        $notifType = $following ? 'follow' : 'unfollow';
        $notifContent = htmlspecialchars($followerName) . ($following ? ' started following your company' : ' unfollowed your company');
        $db->prepare("INSERT INTO notifications (user_id, type, content, reference_id) VALUES (:uid, :type, :content, :ref)")
           ->execute([':uid' => $employerId, ':type' => $notifType, ':content' => $notifContent, ':ref' => $userId]);
    } catch (\Throwable $_) { /* notification failure should not block follow */ }

    $cnt = $db->prepare("SELECT COUNT(*) FROM company_follows WHERE employer_user_id = :eid");
    $cnt->execute([':eid' => $employerId]);
    $count = (int)$cnt->fetchColumn();

    echo json_encode(['ok' => true, 'following' => $following, 'count' => $count]);
} catch (\Throwable $e) {
    error_log('[AntCareers] follow_company: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
