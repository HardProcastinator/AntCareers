<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$followerId = (int)$_SESSION['user_id'];
$targetUserId = (int)($_POST['target_user_id'] ?? 0);

if ($targetUserId <= 0 || $targetUserId === $followerId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid user']);
    exit;
}

$db = getDB();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS user_follows (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        follower_user_id INT UNSIGNED NOT NULL,
        followed_user_id INT UNSIGNED NOT NULL,
        followed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_follow (follower_user_id, followed_user_id),
        KEY idx_followed_user (followed_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $_) {}

try {
    $targetStmt = $db->prepare("SELECT id, full_name, account_type, is_active FROM users WHERE id = :id LIMIT 1");
    $targetStmt->execute([':id' => $targetUserId]);
    $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser || (int)($targetUser['is_active'] ?? 0) !== 1) {
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit;
    }

    $check = $db->prepare("SELECT id FROM user_follows WHERE follower_user_id = :follower AND followed_user_id = :followed LIMIT 1");
    $check->execute([':follower' => $followerId, ':followed' => $targetUserId]);
    $existing = $check->fetchColumn();

    if ($existing) {
        $db->prepare("DELETE FROM user_follows WHERE follower_user_id = :follower AND followed_user_id = :followed")
            ->execute([':follower' => $followerId, ':followed' => $targetUserId]);
        $following = false;
    } else {
        $db->prepare("INSERT IGNORE INTO user_follows (follower_user_id, followed_user_id) VALUES (:follower, :followed)")
            ->execute([':follower' => $followerId, ':followed' => $targetUserId]);
        $following = true;
    }

    if ($following) {
        try {
            $nameStmt = $db->prepare("SELECT full_name FROM users WHERE id = :id LIMIT 1");
            $nameStmt->execute([':id' => $followerId]);
            $actorName = trim((string)$nameStmt->fetchColumn()) ?: 'Someone';

            $notifContent = htmlspecialchars($actorName, ENT_QUOTES, 'UTF-8') . ' started following you';
            $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type, is_read, created_at)
                         VALUES (:user_id, :actor_id, 'follow', :content, :reference_id, 'user', 0, NOW())")
               ->execute([
                   ':user_id' => $targetUserId,
                   ':actor_id' => $followerId,
                   ':content' => $notifContent,
                   ':reference_id' => $followerId,
               ]);
        } catch (Throwable $_) {
            // Notification failure should not block the follow action.
        }
    }

    $followingCountStmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_user_id = :id");
    $followingCountStmt->execute([':id' => $followerId]);
    $followingCount = (int)$followingCountStmt->fetchColumn();

    $followersCountStmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE followed_user_id = :id");
    $followersCountStmt->execute([':id' => $targetUserId]);
    $followersCount = (int)$followersCountStmt->fetchColumn();

    echo json_encode([
        'ok' => true,
        'following' => $following,
        'following_count' => $followingCount,
        'followers_count' => $followersCount,
    ]);
} catch (Throwable $e) {
    error_log('[AntCareers] follow_user: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}