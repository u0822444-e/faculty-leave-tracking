<?php
require_once __DIR__ . '/../../config/database.php';

// ============================================
// Make sure PHP warnings/notices never corrupt JSON
// ============================================
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'error'   => 'Server error while processing the upload.',
            'details' => $err['message'] . ' in ' . $err['file'] . ':' . $err['line'],
        ]);
    }
});

header('Content-Type: application/json');

// ============================================
// Auth guard
// ============================================
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
$action = $_POST['action'] ?? '';

// ============================================
// Upload paths — declared ONCE at file scope
// ============================================
$uploadDir = __DIR__ . '/../../public/uploads/avatars/';
$uploadUrl = '/uploads/avatars/';

// ============================================
// Helper: delete an old avatar file from disk
// ============================================
if (!function_exists('deleteAvatarFile')) {
    function deleteAvatarFile(?string $filename, string $dir): void {
        if (!$filename) return;
        $path = $dir . $filename;
        if (is_file($path)) @unlink($path);
    }
}

// ============================================
// Action: remove
// ============================================
if ($action === 'remove') {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();

    deleteAvatarFile($old ?: null, $uploadDir);

    $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ?")
        ->execute([$userId]);

    $_SESSION['avatar'] = null;

    echo json_encode(['success' => true, 'message' => 'Profile picture removed.']);
    exit;
}

// ============================================
// Action: upload
// ============================================
if ($action === 'upload') {
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['error' => 'No file was uploaded.']);
        exit;
    }

    switch ($_FILES['avatar']['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            echo json_encode([
                'error' => 'The file exceeds the server\'s maximum upload size. '
                    . 'Ask your administrator to raise upload_max_filesize and post_max_size.'
            ]);
            exit;
        case UPLOAD_ERR_PARTIAL:
            echo json_encode(['error' => 'The upload was interrupted. Please try again.']);
            exit;
        case UPLOAD_ERR_NO_FILE:
            echo json_encode(['error' => 'No file was uploaded.']);
            exit;
        case UPLOAD_ERR_NO_TMP_DIR:
            echo json_encode(['error' => 'Server misconfiguration: missing temp directory.']);
            exit;
        case UPLOAD_ERR_CANT_WRITE:
            echo json_encode(['error' => 'Server could not write the file to disk.']);
            exit;
        case UPLOAD_ERR_EXTENSION:
            echo json_encode(['error' => 'A PHP extension blocked the upload.']);
            exit;
        default:
            echo json_encode(['error' => 'Upload failed (error code ' . $_FILES['avatar']['error'] . ').']);
            exit;
    }

    $file = $_FILES['avatar'];

    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['error' => 'File is too large. Maximum size is 10MB.']);
        exit;
    }

    // Detect MIME via finfo, with a fallback if the extension is disabled
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }
    if (!$mime && function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']) ?: '';
    }
    if (!$mime) {
        $mime = $file['type'] ?? '';
    }

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

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['error' => 'Server could not create the upload directory.']);
            exit;
        }
    }

    if (!is_writable($uploadDir)) {
        echo json_encode(['error' => 'Upload directory is not writable.']);
        exit;
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        echo json_encode(['error' => 'Could not save the uploaded file.']);
        exit;
    }

    // Delete the old avatar
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && $old !== $filename) {
        deleteAvatarFile($old, $uploadDir);
    }

    // Persist the new filename
    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")
        ->execute([$filename, $userId]);

    // Keep the session in sync so the topbar/sidebar update instantly
    $_SESSION['avatar'] = $filename;

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated.',
        'avatar'  => $uploadUrl . $filename,
    ]);
    exit;
}

// ============================================
// Unknown action
// ============================================
http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);