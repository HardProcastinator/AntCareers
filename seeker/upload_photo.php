<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$type = $_POST['type'] ?? '';

if (!in_array($type, ['avatar', 'banner'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid type']);
    exit;
}

$file = $_FILES['photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
    exit;
}

// Validate file type
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Only JPEG, PNG, WebP and GIF images are allowed']);
    exit;
}

// Max 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large (max 5MB)']);
    exit;
}

$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg',
};

$folder = $type === 'avatar' ? 'avatars' : 'banners';
$filename = $type . '_' . $userId . '_' . time() . '.' . $ext;
$uploadDir = dirname(__DIR__) . '/uploads/' . $folder . '/';
$destPath = $uploadDir . $filename;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

$relativePath = 'uploads/' . $folder . '/' . $filename;

$db = getDB();

if ($type === 'avatar') {
    // avatar_url lives on the users table — always has a row
    $stmt = $db->prepare("UPDATE users SET avatar_url = :url WHERE id = :uid");
    $stmt->execute([':url' => $relativePath, ':uid' => $userId]);
} else {
    // banner_url lives on seeker_profiles — upsert so it works even if no profile row exists yet
    $stmt = $db->prepare("
        INSERT INTO seeker_profiles (user_id, banner_url, created_at, updated_at)
        VALUES (:uid, :url, NOW(), NOW())
        ON DUPLICATE KEY UPDATE banner_url = :url2, updated_at = NOW()
    ");
    $stmt->execute([':uid' => $userId, ':url' => $relativePath, ':url2' => $relativePath]);
}

echo json_encode(['ok' => true, 'url' => $relativePath]);
