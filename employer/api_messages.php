<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/messages.php';
__halt_compiler();
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/messages.php';<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/messages.php';
$uid = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: time ago ──────────────────────────────────────────────────────
function timeAgo(string $datetime): string {
    $now  = time();
    $diff = $now - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

// Auto-create notifications table if missing
try { $db->query("SELECT 1 FROM notifications LIMIT 0"); }
catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'general',
        content TEXT NOT NULL,
        reference_id INT UNSIGNED DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_notif_user (user_id),
        INDEX idx_notif_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

switch ($action) {

    // ── GET CONVERSATION THREADS ──────────────────────────────────────────
    case 'threads':
        try {
            // Get latest message per conversation partner
            $sql = "
                SELECT m.id, m.sender_id, m.receiver_id, m.body, m.created_at, m.is_read,
                       u.id AS partner_id, u.full_name, u.avatar_url,
                       (SELECT COUNT(*) FROM messages
                        WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0
                       ) AS unread_count
                FROM messages m
                JOIN users u ON u.id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
                WHERE m.id IN (
                    SELECT MAX(id) FROM messages
                    WHERE sender_id = ? OR receiver_id = ?
                    GROUP BY IF(sender_id = ?, receiver_id, sender_id)
                )
                ORDER BY m.created_at DESC
            ";
            $s = $db->prepare($sql);
            $s->execute([$uid, $uid, $uid, $uid, $uid]);
            $threads = [];
            $colors = ['#4A90D9','#9B59B6','#27AE60','#E74C3C','#D4943A','#3498DB','#E67E22','#1ABC9C'];
            foreach ($s->fetchAll() as $i => $r) {
                $parts = preg_split('/\s+/', $r['full_name'] ?? 'User') ?: ['U'];
                $ini = count($parts) >= 2
                    ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1))
                    : strtoupper(substr($parts[0],0,2));

                // Try to find related job application
                $jobTitle = '';
                $appStatus = '';
                try {
                    $js = $db->prepare("
                        SELECT j.title, a.status
                        FROM applications a
                        JOIN jobs j ON j.id = a.job_id
                        WHERE a.seeker_id = ? AND j.employer_id = ?
                        ORDER BY a.applied_at DESC LIMIT 1
                    ");
                    $js->execute([(int)$r['partner_id'], $uid]);
                    $jr = $js->fetch();
                    if ($jr) {
                        $jobTitle = $jr['title'];
                        $appStatus = $jr['status'];
                    }
                } catch (PDOException $e) {}

                $threads[] = [
                    'partner_id'   => (int)$r['partner_id'],
                    'name'         => $r['full_name'],
                    'initials'     => $ini,
                    'avatar_url'   => $r['avatar_url'],
                    'color'        => $colors[$i % count($colors)],
                    'preview'      => mb_substr($r['body'], 0, 80),
                    'time'         => timeAgo($r['created_at']),
                    'unread_count' => (int)$r['unread_count'],
                    'job_title'    => $jobTitle,
                    'app_status'   => $appStatus,
                    'is_sent'      => (int)$r['sender_id'] === $uid,
                ];
            }
            echo json_encode(['success' => true, 'threads' => $threads]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages threads: '.$e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to load threads']);
        }
        break;

    // ── GET MESSAGES WITH A USER ──────────────────────────────────────────
    case 'messages':
        $partnerId = (int)($_GET['user_id'] ?? 0);
        if ($partnerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user_id']);
            break;
        }
        try {
            // Mark incoming messages as read
            $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
               ->execute([$partnerId, $uid]);

            // Fetch conversation
            $s = $db->prepare("
                SELECT m.id, m.sender_id, m.receiver_id, m.body, m.subject, m.is_read, m.created_at
                FROM messages m
                WHERE (m.sender_id = ? AND m.receiver_id = ?)
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC
                LIMIT 200
            ");
            $s->execute([$uid, $partnerId, $partnerId, $uid]);
            $messages = [];
            $lastDate = '';
            foreach ($s->fetchAll() as $r) {
                $msgDate = date('Y-m-d', strtotime($r['created_at']));
                $showDate = ($msgDate !== $lastDate);
                $lastDate = $msgDate;

                $messages[] = [
                    'id'        => (int)$r['id'],
                    'from'      => (int)$r['sender_id'] === $uid ? 'me' : 'them',
                    'body'      => $r['body'],
                    'time'      => date('g:i A', strtotime($r['created_at'])),
                    'date'      => date('M j, Y', strtotime($r['created_at'])),
                    'show_date' => $showDate,
                    'is_read'   => (int)$r['is_read'],
                ];
            }

            // Get partner info
            $ps = $db->prepare("SELECT id, full_name, avatar_url FROM users WHERE id = ?");
            $ps->execute([$partnerId]);
            $partner = $ps->fetch();

            // Get application/job info
            $jobInfo = null;
            try {
                $js = $db->prepare("
                    SELECT j.title, a.status
                    FROM applications a JOIN jobs j ON j.id = a.job_id
                    WHERE a.seeker_id = ? AND j.employer_id = ?
                    ORDER BY a.applied_at DESC LIMIT 1
                ");
                $js->execute([$partnerId, $uid]);
                $jobInfo = $js->fetch();
            } catch (PDOException $e) {}

            echo json_encode([
                'success'  => true,
                'messages' => $messages,
                'partner'  => $partner ? [
                    'id'        => (int)$partner['id'],
                    'name'      => $partner['full_name'],
                    'avatar_url'=> $partner['avatar_url'],
                ] : null,
                'job' => $jobInfo ? [
                    'title'  => $jobInfo['title'],
                    'status' => $jobInfo['status'],
                ] : null,
            ]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages fetch: '.$e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to load messages']);
        }
        break;

    // ── SEND A MESSAGE ────────────────────────────────────────────────────
    case 'send':
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $receiverId = (int)($input['receiver_id'] ?? 0);
        $body       = trim((string)($input['message'] ?? ''));

        if ($receiverId <= 0 || $body === '') {
            echo json_encode(['success' => false, 'message' => 'Missing receiver_id or message']);
            break;
        }

        // Verify receiver exists
        $check = $db->prepare("SELECT id FROM users WHERE id = ?");
        $check->execute([$receiverId]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Recipient not found']);
            break;
        }

        try {
            $s = $db->prepare("INSERT INTO messages (sender_id, receiver_id, body) VALUES (?, ?, ?)");
            $s->execute([$uid, $receiverId, $body]);
            $msgId = (int)$db->lastInsertId();

            // Create notification for receiver
            try {
                $senderName = $_SESSION['user_name'] ?? 'Someone';
                $notifContent = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') . ' sent you a new message.';
                $ns = $db->prepare("INSERT INTO notifications (user_id, type, content, reference_id) VALUES (?, 'message', ?, ?)");
                $ns->execute([$receiverId, $notifContent, $msgId]);
            } catch (PDOException $e) {
                error_log('[AntCareers] notification insert: '.$e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message_id' => $msgId,
                'time' => date('g:i A'),
            ]);
        } catch (PDOException $e) {
            error_log('[AntCareers] api_messages send: '.$e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to send message']);
        }
        break;

    // ── MARK MESSAGES AS READ ─────────────────────────────────────────────
    case 'mark_read':
        $partnerId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
        if ($partnerId > 0) {
            $db->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
               ->execute([$partnerId, $uid]);
        }
        echo json_encode(['success' => true]);
        break;

    // ── UNREAD COUNT ──────────────────────────────────────────────────────
    case 'unread_count':
        try {
            $s = $db->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
            $s->execute([$uid]);
            $msgCount = (int)$s->fetchColumn();

            $notifCount = 0;
            try {
                $ns = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $ns->execute([$uid]);
                $notifCount = (int)$ns->fetchColumn();
            } catch (PDOException $e) {}

            echo json_encode([
                'success' => true,
                'messages' => $msgCount,
                'notifications' => $notifCount,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'messages' => 0, 'notifications' => 0]);
        }
        break;

    // ── GET NOTIFICATIONS ─────────────────────────────────────────────────
    case 'notifications':
        try {
            $s = $db->prepare("
                SELECT id, type, content, reference_id, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 30
            ");
            $s->execute([$uid]);
            $notifs = [];
            foreach ($s->fetchAll() as $r) {
                $notifs[] = [
                    'id'        => (int)$r['id'],
                    'type'      => $r['type'],
                    'content'   => $r['content'],
                    'is_read'   => (int)$r['is_read'],
                    'time'      => timeAgo($r['created_at']),
                    'created_at'=> $r['created_at'],
                ];
            }
            echo json_encode(['success' => true, 'notifications' => $notifs]);
        } catch (PDOException $e) {
            error_log('[AntCareers] notifications fetch: '.$e->getMessage());
            echo json_encode(['success' => true, 'notifications' => []]);
        }
        break;

    // ── MARK NOTIFICATION AS READ ─────────────────────────────────────────
    case 'mark_notif_read':
        $notifId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($notifId > 0) {
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
               ->execute([$notifId, $uid]);
        } else {
            // Mark all as read
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
               ->execute([$uid]);
        }
        echo json_encode(['success' => true]);
        break;

    // ── SEARCH USERS TO MESSAGE ───────────────────────────────────────────
    case 'search_users':
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2) {
            echo json_encode(['success' => true, 'users' => []]);
            break;
        }
        try {
            $s = $db->prepare("
                SELECT id, full_name, avatar_url, account_type
                FROM users
                WHERE id != ? AND full_name LIKE ? AND is_active = 1
                LIMIT 10
            ");
            $s->execute([$uid, '%'.$q.'%']);
            $users = [];
            foreach ($s->fetchAll() as $r) {
                $parts = preg_split('/\s+/', $r['full_name']) ?: ['U'];
                $ini = count($parts) >= 2
                    ? strtoupper(substr($parts[0],0,1).substr($parts[1],0,1))
                    : strtoupper(substr($parts[0],0,2));
                $users[] = [
                    'id'       => (int)$r['id'],
                    'name'     => $r['full_name'],
                    'initials' => $ini,
                    'type'     => $r['account_type'],
                ];
            }
            echo json_encode(['success' => true, 'users' => $users]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'users' => []]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
