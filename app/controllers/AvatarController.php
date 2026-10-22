<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/ActivityLogger.php';

header('Content-Type: application/json');

// Prevent PHP warnings from breaking the JSON response
ini_set('display_errors', 0); 
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$action = $_POST['action'] ?? '';

$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
$uploadUrl = '/uploads/avatars/';

$isPrivileged = ($role === 'hr');

function deleteAvatarFile(?string $filename, string $dir): void {
    if (!$filename) return;
    $path = $dir . $filename;
    if (is_file($path)) @unlink($path);
}

// ============================================
// REMOVE
// ============================================
if ($action === 'remove') {
    if (!$isPrivileged) {
        $stmt = $pdo->prepare("SELECT profile_edit_requested FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() === 1) {
            echo json_encode([
                'error' => 'You already have a pending profile change. Please wait for admin approval.'
            ]);
            exit;
        }
    }

    if ($isPrivileged) {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $old = $stmt->fetchColumn();

        deleteAvatarFile($old ?: null, $uploadDir);

        $pdo->prepare("UPDATE users SET avatar = NULL, pending_avatar = NULL WHERE id = ?")->execute([$userId]);
        $_SESSION['avatar'] = null;

        try {
            ActivityLogger::log($pdo, 'remove_avatar', "Removed own avatar (admin)", 'user', $userId);
        } catch (Throwable $e) { error_log($e->getMessage()); }

        echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
        exit;
    }

    // Faculty/Staff/HR — store pending removal (sentinel = empty string)
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE users SET pending_avatar = '', profile_edit_requested = 1 WHERE id = ?
        ")->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['error' => 'Could not save request: ' . $e->getMessage()]);
        exit;
    }

    try {
        ActivityLogger::log($pdo, 'request_remove_avatar', "Requested avatar removal (pending approval)", 'user', $userId);
    } catch (Throwable $e) { error_log($e->getMessage()); }

    echo json_encode([
        'success' => true,
        'pending' => true,
        'message' => 'Avatar removal submitted for admin approval.',
    ]);
    exit;
}

// ============================================
// UPLOAD
// ============================================
if ($action === 'upload') {
    if (!$isPrivileged) {
        $stmt = $pdo->prepare("SELECT profile_edit_requested FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() === 1) {
            echo json_encode([
                'error' => 'You already have a pending profile change. Please wait for admin approval.'
            ]);
            exit;
        }
    }

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No file uploaded or upload failed.']);
        exit;
    }

    $file = $_FILES['avatar'];

    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => 'File is too large. Maximum size is 10MB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        echo json_encode(['error' => 'Only JPG, PNG, WEBP, or GIF images are allowed.']);
        exit;
    }

    $ext      = $allowed[$mime];
    $filename = 'user_' . $userId . '_' . time() . '.' . $ext;

    // Ensure directory exists, and throw a clean JSON error if it fails
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['error' => 'Server Error: Upload directory could not be created.']);
            exit;
        }
    }

    // Ensure directory is writable
    if (!is_writable($uploadDir)) {
        echo json_encode(['error' => 'Server Error: Upload directory is not writable.']);
        exit;
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['error' => 'Could not save the uploaded file to the server.']);
        exit;
    }

    if ($isPrivileged) {
        // Admin — apply immediately, delete old avatar
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $old = $stmt->fetchColumn();
        if ($old && $old !== $filename) deleteAvatarFile($old, $uploadDir);

        $pdo->prepare("UPDATE users SET avatar = ?, pending_avatar = NULL WHERE id = ?")->execute([$filename, $userId]);
        $_SESSION['avatar'] = $filename;

        try {
            ActivityLogger::log($pdo, 'update_avatar', "Updated own avatar (admin)", 'user', $userId);
        } catch (Throwable $e) { error_log($e->getMessage()); }

        echo json_encode([
            'success' => true,
            'status'  => 'applied',
            'avatar'  => $uploadUrl . $filename,
            'message' => 'Profile picture updated.',
        ]);
        exit;
    }

    // Faculty/Staff/HR — store as pending; do NOT delete current avatar
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT avatar, pending_avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch();

        if (!empty($existing['pending_avatar']) && $existing['pending_avatar'] !== $existing['avatar']) {
            deleteAvatarFile($existing['pending_avatar'], $uploadDir);
        }

        $pdo->prepare("
            UPDATE users SET pending_avatar = ?, profile_edit_requested = 1 WHERE id = ?
        ")->execute([$filename, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        deleteAvatarFile($filename, $uploadDir);
        echo json_encode(['error' => 'Could not save request: ' . $e->getMessage()]);
        exit;
    }

    try {
        ActivityLogger::log($pdo, 'request_avatar_change', "Requested avatar change (pending approval)", 'user', $userId);
    } catch (Throwable $e) { error_log($e->getMessage()); }

    echo json_encode([
        'success'     => true,
        'status'      => 'pending',
        'pending_url' => $uploadUrl . $filename,
        'message'     => 'Avatar change submitted for admin approval.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);