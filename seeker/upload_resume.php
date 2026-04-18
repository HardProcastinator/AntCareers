<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

$isAjax = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

function jsonError(string $msg, int $code = 400): void {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    if ($isAjax) jsonError('Not authenticated', 401);
    header('Location: ../auth/antcareers_login.php');
    exit;
}

if (strtolower((string)($_SESSION['account_type'] ?? '')) !== 'seeker') {
    if ($isAjax) jsonError('Not authorized', 403);
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) jsonError('Method not allowed', 405);
    header('Location: antcareers_seekerProfile.php');
    exit;
}

// CSRF validation
$_csrfSubmitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_csrfSubmitted)) {
    if ($isAjax) jsonError('Invalid request. Please refresh the page and try again.', 403);
    header('Location: antcareers_seekerProfile.php');
    exit;
}

if (!isset($_FILES['resume']) || !is_array($_FILES['resume'])) {
    if ($isAjax) jsonError('No file provided');
    header('Location: antcareers_seekerProfile.php?error=upload');
    exit;
}

$file = $_FILES['resume'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    if ($isAjax) jsonError('Upload error');
    header('Location: antcareers_seekerProfile.php?error=upload');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$maxSize = MAX_UPLOAD_BYTES;
$originalName = (string)($file['name'] ?? '');
$tmpName = (string)($file['tmp_name'] ?? '');
$fileSize = (int)($file['size'] ?? 0);

if ($fileSize <= 0 || $fileSize > $maxSize) {
    if ($isAjax) jsonError('File too large (max 5MB)');
    header('Location: antcareers_seekerProfile.php?error=size');
    exit;
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'doc', 'docx'];

if (!in_array($extension, $allowedExtensions, true)) {
    if ($isAjax) jsonError('Invalid file type');
    header('Location: antcareers_seekerProfile.php?error=type');
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? (string)finfo_file($finfo, $tmpName) : '';
if ($finfo) {
    finfo_close($finfo);
}

$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
    if ($isAjax) jsonError('Invalid file type');
    header('Location: antcareers_seekerProfile.php?error=type');
    exit;
}

$uploadDir = dirname(__DIR__) . '/uploads/resumes';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        if ($isAjax) jsonError('Server error', 500);
        header('Location: antcareers_seekerProfile.php?error=move');
        exit;
    }
}

$storedFilename = 'resume_' . $userId . '_' . time() . '.' . $extension;
$destination = $uploadDir . '/' . $storedFilename;
$relativePath = 'uploads/resumes/' . $storedFilename;

if (!move_uploaded_file($tmpName, $destination)) {
    if ($isAjax) jsonError('Failed to save file', 500);
    header('Location: antcareers_seekerProfile.php?error=move');
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    $deactivate = $db->prepare("
        UPDATE seeker_resumes
        SET is_active = 0
        WHERE user_id = :user_id
    ");
    $deactivate->execute([':user_id' => $userId]);

    $insert = $db->prepare("
        INSERT INTO seeker_resumes (
            user_id,
            original_filename,
            stored_filename,
            file_path,
            file_size,
            mime_type,
            is_active,
            uploaded_at
        ) VALUES (
            :user_id,
            :original_filename,
            :stored_filename,
            :file_path,
            :file_size,
            :mime_type,
            1,
            NOW()
        )
    ");

    $insert->execute([
        ':user_id' => $userId,
        ':original_filename' => $originalName,
        ':stored_filename' => $storedFilename,
        ':file_path' => $relativePath,
        ':file_size' => $fileSize,
        ':mime_type' => $mimeType !== '' ? $mimeType : null,
    ]);

    $db->commit();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'filename' => $originalName, 'size' => $fileSize]);
        exit;
    }
    header('Location: antcareers_seekerProfile.php?success=1');
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[AntCareers] Resume upload error: ' . $e->getMessage());
    if ($isAjax) jsonError($e->getMessage(), 500);
    header('Location: antcareers_seekerProfile.php?error=upload');
    exit;
}