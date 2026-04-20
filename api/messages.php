<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
$uid = (int) $_SESSION['user_id'];
$originalRole = strtolower((string)($_SESSION['account_type'] ?? ''));
$role = ($originalRole === 'recruiter') ? 'employer' : $originalRole;
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'threads');

function api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function relative_time_from_seconds(int $seconds): string
{
    if ($seconds < 0) {
        $seconds = 0;
    }

    if ($seconds < 45) {
        return 'Just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ago';
    }
    if ($seconds < 172800) {
        return 'Yesterday';
    }
    if ($seconds < 604800) {
        return floor($seconds / 86400) . 'd ago';
    }

    return '';
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '';
    }

    $diff = time() - $timestamp;
    return relative_time_from_seconds((int) $diff);
}

function table_has_column(PDO $db, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ensure_column(PDO $db, string $table, string $column, string $definition): void
{
    if (!table_has_column($db, $table, $column)) {
        $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function ensure_schema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_key VARCHAR(80) NOT NULL,
        participant_a_id INT UNSIGNED NOT NULL,
        participant_b_id INT UNSIGNED NOT NULL,
        latest_message_id INT UNSIGNED DEFAULT NULL,
        latest_message_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_conversation_key (conversation_key),
        INDEX idx_conversation_a (participant_a_id),
        INDEX idx_conversation_b (participant_b_id),
        INDEX idx_conversation_latest (latest_message_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $messagesExists = true;
    try {
        $db->query('SELECT 1 FROM messages LIMIT 0');
    } catch (PDOException $e) {
        $messagesExists = false;
    }

    if (!$messagesExists) {
        $db->exec("CREATE TABLE messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            message_type VARCHAR(20) NOT NULL DEFAULT 'text',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            seen_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_msg_conversation (conversation_id),
            INDEX idx_msg_sender (sender_id),
            INDEX idx_msg_receiver (receiver_id),
            INDEX idx_msg_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        ensure_column($db, 'messages', 'conversation_id', 'INT UNSIGNED DEFAULT NULL');
        ensure_column($db, 'messages', 'message_type', "VARCHAR(20) NOT NULL DEFAULT 'text'");
        ensure_column($db, 'messages', 'seen_at', 'DATETIME DEFAULT NULL');
        ensure_column($db, 'messages', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        if (!table_has_column($db, 'messages', 'subject')) {
            ensure_column($db, 'messages', 'subject', 'VARCHAR(255) DEFAULT NULL');
        }
    }

    try {
        $db->query('SELECT 1 FROM notifications LIMIT 0');
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE notifications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'general',
            content TEXT NOT NULL,
            reference_id INT UNSIGNED DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_notif_user (user_id),
            INDEX idx_notif_read (is_read),
            INDEX idx_notif_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Ensure new notification columns exist
    ensure_column($db, 'notifications', 'actor_id', 'INT UNSIGNED DEFAULT NULL AFTER user_id');
    ensure_column($db, 'notifications', 'reference_type', "VARCHAR(50) DEFAULT NULL AFTER reference_id");

    try {
        $db->query('SELECT 1 FROM conversations LIMIT 0');
    } catch (PDOException $e) {
        api_json(['success' => false, 'message' => 'Failed to initialize messaging tables'], 500);
    }

    $rows = $db->query("SELECT DISTINCT LEAST(sender_id, receiver_id) AS a_id, GREATEST(sender_id, receiver_id) AS b_id FROM messages")->fetchAll();
    $insertConversation = $db->prepare("INSERT IGNORE INTO conversations (conversation_key, participant_a_id, participant_b_id) VALUES (?, ?, ?)");
    foreach ($rows as $row) {
        $aId = (int) $row['a_id'];
        $bId = (int) $row['b_id'];
        if ($aId <= 0 || $bId <= 0) {
            continue;
        }
        $insertConversation->execute([conversation_key($aId, $bId), $aId, $bId]);
    }

    ensure_column($db, 'messages', 'conversation_id', 'INT UNSIGNED DEFAULT NULL');

    $db->exec("UPDATE messages m
        JOIN conversations c ON c.conversation_key = CONCAT('direct:', LEAST(m.sender_id, m.receiver_id), ':', GREATEST(m.sender_id, m.receiver_id))
        SET m.conversation_id = c.id
        WHERE m.conversation_id IS NULL");

    $conversationIds = $db->query('SELECT id FROM conversations')->fetchAll(PDO::FETCH_COLUMN);
    $latestStmt = $db->prepare("UPDATE conversations
        SET latest_message_id = (
            SELECT id FROM messages WHERE conversation_id = ? ORDER BY created_at DESC, id DESC LIMIT 1
        ),
        latest_message_at = (
            SELECT created_at FROM messages WHERE conversation_id = ? ORDER BY created_at DESC, id DESC LIMIT 1
        )
        WHERE id = ?");
    foreach ($conversationIds as $conversationId) {
        $latestStmt->execute([(int) $conversationId, (int) $conversationId, (int) $conversationId]);
    }
}

function conversation_key(int $aId, int $bId): string
{
    $min = min($aId, $bId);
    $max = max($aId, $bId);
    return 'direct:' . $min . ':' . $max;
}

function normalize_name(?string $name, string $fallback): string
{
    $trimmed = trim((string) $name);
    return $trimmed !== '' ? $trimmed : $fallback;
}

function initials_for(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: ['U'];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($parts[0], 0, 1));
}

function color_for_id(int $id): string
{
    $colors = [
        'linear-gradient(135deg,#D13D2C,#7A1515)',
        'linear-gradient(135deg,#4A90D9,#2A6090)',
        'linear-gradient(135deg,#4CAF70,#2A7040)',
        'linear-gradient(135deg,#D4943A,#8A5A10)',
        'linear-gradient(135deg,#9C27B0,#5A0080)',
    ];
    return $colors[$id % count($colors)];
}

function normalize_role(string $role): string
{
    return ($role === 'recruiter') ? 'employer' : $role;
}

function role_allows_direct_message(string $senderRole, string $receiverRole): bool
{
    return true;
}

function fetch_partner_context(PDO $db, int $currentUserId, string $role, int $partnerId): array
{
    $jobTitle = '';
    $appStatus = '';
    $actualRole = strtolower((string)($_SESSION['account_type'] ?? ''));

    try {
        if ($role === 'employer') {
            if ($actualRole === 'recruiter') {
                // Recruiter: match applications to any job in their company or posted by them
                $stmt = $db->prepare("SELECT j.title, a.status
                    FROM applications a
                    JOIN jobs j ON j.id = a.job_id
                    LEFT JOIN recruiters r ON r.user_id = ? AND r.is_active = 1
                    WHERE a.seeker_id = ? AND (j.recruiter_id = ? OR j.employer_id = r.employer_id)
                    ORDER BY a.applied_at DESC, a.id DESC LIMIT 1");
                $stmt->execute([$currentUserId, $partnerId, $currentUserId]);
            } else {
                $stmt = $db->prepare("SELECT j.title, a.status
                    FROM applications a
                    JOIN jobs j ON j.id = a.job_id
                    WHERE a.seeker_id = ? AND j.employer_id = ?
                    ORDER BY a.applied_at DESC, a.id DESC LIMIT 1");
                $stmt->execute([$partnerId, $currentUserId]);
            }
        } else {
            $stmt = $db->prepare("SELECT j.title, a.status
                FROM applications a
                JOIN jobs j ON j.id = a.job_id
                WHERE a.seeker_id = ? AND j.employer_id = ?
                ORDER BY a.applied_at DESC, a.id DESC LIMIT 1");
            $stmt->execute([$currentUserId, $partnerId]);
        }
        $row = $stmt->fetch();
        if ($row) {
            $jobTitle = (string) $row['title'];
            $appStatus = (string) $row['status'];
        }
    } catch (PDOException $e) {
        error_log('[AntCareers] messaging context: ' . $e->getMessage());
    }

    return [$jobTitle, $appStatus];
}

function get_or_create_conversation(PDO $db, int $userA, int $userB): array
{
    $key = conversation_key($userA, $userB);
    $stmt = $db->prepare('SELECT * FROM conversations WHERE conversation_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        $insert = $db->prepare('INSERT INTO conversations (conversation_key, participant_a_id, participant_b_id) VALUES (?, ?, ?)');
        $insert->execute([$key, min($userA, $userB), max($userA, $userB)]);
        $conversationId = (int) $db->lastInsertId();
        $stmt->execute([$key]);
        $conversation = $stmt->fetch();
        if (!$conversation) {
            api_json(['success' => false, 'message' => 'Unable to create conversation'], 500);
        }
        $conversation['id'] = $conversationId;
    }

    return $conversation;
}

function mark_message_notifications_read(PDO $db, int $userId, int $conversationId): void
{
    try {
        $stmt = $db->prepare("UPDATE notifications n
            JOIN messages m ON m.id = n.reference_id
            SET n.is_read = 1
            WHERE n.user_id = ?
              AND n.type = 'message'
              AND n.is_read = 0
              AND m.conversation_id = ?");
        $stmt->execute([$userId, $conversationId]);
    } catch (PDOException $e) {
        error_log('[AntCareers] api_messages mark_message_notifications_read: ' . $e->getMessage());
    }
}

function fetch_user(PDO $db, int $userId): ?array
{
    $stmt = $db->prepare('SELECT id, full_name, company_name, avatar_url, account_type FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

ensure_schema($db);

switch ($action) {
    case 'threads':
        try {
            $sql = "
                SELECT c.id AS conversation_id, c.latest_message_at, c.latest_message_id,
                       p.id AS partner_id, p.full_name, p.company_name, p.avatar_url, p.account_type,
                                             m.body, m.created_at, m.sender_id,
                                             TIMESTAMPDIFF(SECOND, m.created_at, NOW()) AS age_seconds,
                       (
                         SELECT COUNT(*)
                         FROM messages mm
                         WHERE mm.conversation_id = c.id AND mm.receiver_id = ? AND mm.is_read = 0
                       ) AS unread_count
                FROM conversations c
                JOIN messages m ON m.id = c.latest_message_id
                JOIN users p ON p.id = CASE
                    WHEN c.participant_a_id = ? THEN c.participant_b_id
                    ELSE c.participant_a_id
                END
                WHERE c.participant_a_id = ? OR c.participant_b_id = ?
                ORDER BY c.latest_message_at DESC, c.id DESC
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute([$uid, $uid, $uid, $uid]);

            $threads = [];
            foreach ($stmt->fetchAll() as $index => $row) {
                $displayName = normalize_name($row['full_name'], 'User');
                if ($row['account_type'] === 'employer' && !empty($row['company_name'])) {
                    $displayName = normalize_name($row['company_name'], $displayName);
                }

                [$jobTitle, $appStatus] = fetch_partner_context($db, $uid, $role, (int) $row['partner_id']);

                $threads[] = [
                    'conversation_id' => (int) $row['conversation_id'],
                    'partner_id'      => (int) $row['partner_id'],
                    'name'            => $displayName,
                    'initials'        => initials_for($displayName),
                    'avatar_url'      => $row['avatar_url'] ?: null,
                    'color'           => color_for_id((int) $row['partner_id']),
                    'preview'         => mb_substr((string) $row['body'], 0, 80),
                    'time'            => (($timeText = relative_time_from_seconds((int) ($row['age_seconds'] ?? 0))) !== '')
                        ? $timeText
                        : date('M j', strtotime((string) $row['created_at'])),
                    'latest_message_at' => (string) $row['created_at'],
                    'unread_count'    => (int) $row['unread_count'],
                    'job_title'       => $jobTitle,
                    'app_status'      => $appStatus,
                    'account_type'    => (string) $row['account_type'],
                    'is_sent'         => (int) $row['sender_id'] === $uid,
                ];
            }

            api_json(['success' => true, 'threads' => $threads]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages threads: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to load threads'], 500);
        }
        break;

    case 'messages':
        $partnerId = (int) ($_GET['user_id'] ?? $_GET['partner_id'] ?? $_POST['user_id'] ?? $_POST['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            api_json(['success' => false, 'message' => 'Invalid user_id'], 422);
        }

        $partner = fetch_user($db, $partnerId);
        if (!$partner) {
            api_json(['success' => false, 'message' => 'Recipient not found'], 404);
        }

        if (!role_allows_direct_message($role, strtolower((string) $partner['account_type']))) {
            api_json(['success' => false, 'message' => 'Messages are only allowed between employer and seeker accounts'], 403);
        }

        try {
            $conversation = get_or_create_conversation($db, $uid, $partnerId);

            $markRead = $db->prepare("UPDATE messages
                SET is_read = 1,
                    seen_at = COALESCE(seen_at, NOW())
                WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
            $markRead->execute([(int) $conversation['id'], $uid]);

            mark_message_notifications_read($db, $uid, (int) $conversation['id']);

            $stmt = $db->prepare("SELECT id, sender_id, receiver_id, body, message_type, is_read, created_at
                FROM messages
                WHERE conversation_id = ?
                ORDER BY created_at ASC, id ASC
                LIMIT 300");
            $stmt->execute([(int) $conversation['id']]);

            $messages = [];
            $lastDate = '';
            foreach ($stmt->fetchAll() as $row) {
                $messageDate = date('Y-m-d', strtotime((string) $row['created_at']));
                $messages[] = [
                    'id'        => (int) $row['id'],
                    'from'      => (int) $row['sender_id'] === $uid ? 'me' : 'them',
                    'body'      => (string) $row['body'],
                    'type'      => (string) $row['message_type'],
                    'time'      => date('g:i A', strtotime((string) $row['created_at'])),
                    'date'      => date('M j, Y', strtotime((string) $row['created_at'])),
                    'show_date' => $messageDate !== $lastDate,
                    'is_read'   => (int) $row['is_read'],
                ];
                $lastDate = $messageDate;
            }

            [$jobTitle, $appStatus] = fetch_partner_context($db, $uid, $role, $partnerId);

            $displayName = normalize_name($partner['full_name'], 'User');
            if (($partner['account_type'] ?? '') === 'employer' && !empty($partner['company_name'])) {
                $displayName = normalize_name($partner['company_name'], $displayName);
            }

            api_json([
                'success'  => true,
                'conversation_id' => (int) $conversation['id'],
                'messages' => $messages,
                'partner'  => [
                    'id'         => (int) $partner['id'],
                    'name'       => $displayName,
                    'avatar_url' => $partner['avatar_url'] ?: null,
                    'account_type' => $partner['account_type'],
                ],
                'job' => $jobTitle !== '' ? [
                    'title'  => $jobTitle,
                    'status' => $appStatus,
                ] : null,
            ]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages fetch: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to load messages'], 500);
        }
        break;

    case 'send':
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $receiverId = (int) ($input['receiver_id'] ?? 0);
        $body = trim((string) ($input['message'] ?? ''));

        if ($receiverId <= 0 || $body === '') {
            api_json(['success' => false, 'message' => 'Missing receiver_id or message'], 422);
        }

        $receiver = fetch_user($db, $receiverId);
        if (!$receiver) {
            api_json(['success' => false, 'message' => 'Recipient not found'], 404);
        }

        if (!role_allows_direct_message($role, strtolower((string) $receiver['account_type']))) {
            api_json(['success' => false, 'message' => 'Messages are only allowed between employer and seeker accounts'], 403);
        }

        try {
            $db->beginTransaction();

            $conversation = get_or_create_conversation($db, $uid, $receiverId);

            $insert = $db->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, body, message_type, is_read)
                VALUES (?, ?, ?, ?, 'text', 0)");
            $insert->execute([(int) $conversation['id'], $uid, $receiverId, $body]);
            $messageId = (int) $db->lastInsertId();

            $updateConversation = $db->prepare("UPDATE conversations SET latest_message_id = ?, latest_message_at = NOW() WHERE id = ?");
            $updateConversation->execute([$messageId, (int) $conversation['id']]);

            $sender = fetch_user($db, $uid);
            $senderName = normalize_name($sender['full_name'] ?? ($_SESSION['user_name'] ?? ''), 'Someone');
            if (($sender['account_type'] ?? '') === 'employer' && !empty($sender['company_name'])) {
                $senderName = normalize_name($sender['company_name'], $senderName);
            }

            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, actor_id, type, content, reference_id, reference_type, is_read, created_at)
                VALUES (?, ?, 'message', ?, ?, 'user', 0, NOW())");
            $notifStmt->execute([
                $receiverId,
                $uid,
                $senderName . ' sent you a new message.',
                $uid,
            ]);

            $db->commit();

            // Query back the actual created_at so the returned time is derived from the
            // same DB value the messages endpoint uses — prevents clock/timezone mismatch.
            $createdAtStmt = $db->prepare('SELECT created_at FROM messages WHERE id = ? LIMIT 1');
            $createdAtStmt->execute([$messageId]);
            $createdAt = (string) ($createdAtStmt->fetchColumn() ?: '');
            $timeStr = $createdAt !== ''
                ? date('g:i A', strtotime($createdAt))
                : date('g:i A');

            api_json([
                'success'         => true,
                'message_id'      => $messageId,
                'conversation_id' => (int) $conversation['id'],
                'time'            => $timeStr,
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[AntCareers] api_messages send: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to send message'], 500);
        }
        break;

    case 'mark_read':
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $conversationId = (int) ($input['conversation_id'] ?? $_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
        $partnerId = (int) ($input['user_id'] ?? $input['partner_id'] ?? $_GET['user_id'] ?? $_GET['partner_id'] ?? $_POST['user_id'] ?? $_POST['partner_id'] ?? 0);

        try {
            if ($conversationId <= 0 && $partnerId > 0) {
                $pairStmt = $db->prepare('SELECT id FROM conversations WHERE conversation_key = ? LIMIT 1');
                $pairStmt->execute([conversation_key($uid, $partnerId)]);
                $conversationId = (int) $pairStmt->fetchColumn();
            }

            if ($conversationId > 0) {
                $stmt = $db->prepare("UPDATE messages
                    SET is_read = 1,
                        seen_at = COALESCE(seen_at, NOW())
                    WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
                $stmt->execute([$conversationId, $uid]);

                mark_message_notifications_read($db, $uid, $conversationId);
            }

            api_json(['success' => true]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages mark_read: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to mark read'], 500);
        }
        break;

    case 'unread_count':
        try {
            $stmt = $db->prepare("SELECT COUNT(DISTINCT conversation_id) FROM messages WHERE receiver_id = ? AND is_read = 0");
            $stmt->execute([$uid]);
            $messageCount = (int) $stmt->fetchColumn();

            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$uid]);
            $notificationCount = (int) $stmt->fetchColumn();

            api_json([
                'success' => true,
                'messages' => $messageCount,
                'notifications' => $notificationCount,
            ]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages unread_count: ' . $e->getMessage());
            api_json(['success' => false, 'messages' => 0, 'notifications' => 0], 500);
        }
        break;

    case 'notifications':
        try {
            $stmt = $db->prepare("SELECT id, type, content, reference_id, reference_type, actor_id, is_read, created_at,
                       TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age_seconds
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC, id DESC
                LIMIT 30");
            $stmt->execute([$uid]);

            $notifications = [];
            foreach ($stmt->fetchAll() as $row) {
                $notifications[] = [
                    'id' => (int) $row['id'],
                    'type' => (string) $row['type'],
                    'content' => (string) $row['content'],
                    'reference_id' => $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
                    'reference_type' => $row['reference_type'] ?? null,
                    'actor_id' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
                    'is_read' => (int) $row['is_read'],
                    'time' => (($notifTime = relative_time_from_seconds((int) ($row['age_seconds'] ?? 0))) !== '')
                        ? $notifTime
                        : date('M j', strtotime((string) $row['created_at'])),
                    'created_at' => (string) $row['created_at'],
                ];
            }

            api_json(['success' => true, 'notifications' => $notifications]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages notifications: ' . $e->getMessage());
            api_json(['success' => true, 'notifications' => []]);
        }
        break;

    case 'mark_notif_read':
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $notifId = (int) ($input['id'] ?? $_GET['id'] ?? 0);

        try {
            if ($notifId > 0) {
                $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
                $stmt->execute([$notifId, $uid]);
            } else {
                $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
                $stmt->execute([$uid]);
            }

            api_json(['success' => true]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages mark_notif_read: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to mark notification read'], 500);
        }
        break;

    case 'clear_notifications':
        try {
            $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = ?');
            $stmt->execute([$uid]);
            api_json(['success' => true]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages clear_notifications: ' . $e->getMessage());
            api_json(['success' => false, 'message' => 'Failed to clear notifications'], 500);
        }
        break;

    case 'search_users':
        $query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
        if (mb_strlen($query) < 2) {
            api_json(['success' => true, 'users' => []]);
        }

        try {
            $like = '%' . $query . '%';
            $stmt = $db->prepare("SELECT id, full_name, company_name, avatar_url, account_type
                FROM users
                WHERE id != ? AND is_active = 1 AND (full_name LIKE ? OR company_name LIKE ?)
                ORDER BY full_name ASC
                LIMIT 15");
            $stmt->execute([$uid, $like, $like]);

            $users = [];
            foreach ($stmt->fetchAll() as $row) {
                $name = normalize_name($row['full_name'], 'User');
                if (($row['account_type'] ?? '') === 'employer' && !empty($row['company_name'])) {
                    $name = normalize_name($row['company_name'], $name);
                }

                $users[] = [
                    'id' => (int) $row['id'],
                    'name' => $name,
                    'initials' => initials_for($name),
                    'avatar_url' => $row['avatar_url'] ?: null,
                    'type' => (string) $row['account_type'],
                ];
            }

            api_json(['success' => true, 'users' => $users]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages search_users: ' . $e->getMessage());
            api_json(['success' => true, 'users' => []]);
        }
        break;

    default:
        api_json(['success' => false, 'message' => 'Unknown action'], 400);
}