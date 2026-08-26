<?php
// ============================================================
//  KICKOFF — File Upload Handler
// ============================================================
require_once __DIR__ . '/../config/config.php';

/**
 * Handle a screenshot upload from $_FILES
 *
 * @param  string $fileKey     Key in $_FILES (e.g. 'screenshot')
 * @param  string $subfolder   Subfolder under uploads/ (e.g. 'results')
 * @param  string $prefix      Filename prefix (e.g. 'result_5_12')
 * @return array  ['success'=>bool, 'url'=>string|null, 'error'=>string|null]
 */
function handleScreenshotUpload(string $fileKey, string $subfolder, string $prefix): array {
    if (!isset($_FILES[$fileKey])) {
        return ['success' => false, 'url' => null, 'error' => 'No file uploaded.'];
    }

    $file = $_FILES[$fileKey];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit).',
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit).',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        ];
        return ['success' => false, 'url' => null,
                'error' => $errors[$file['error']] ?? 'Unknown upload error.'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'url' => null,
                'error' => 'File too large. Maximum size is 10MB.'];
    }

    $subfolder = trim(str_replace(['..', '\\'], ['', '/'], $subfolder), '/');
    $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);

    // Validate MIME type (check actual content, not just header)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_TYPES, true)) {
        return ['success' => false, 'url' => null,
                'error' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'];
    }

    // Get extension from MIME type (safer than trusting the filename)
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mimeType];

    $originalExt = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($originalExt, ALLOWED_EXTS, true)) {
        return ['success' => false, 'url' => null,
                'error' => 'Invalid file extension.'];
    }

    if (@getimagesize($file['tmp_name']) === false) {
        return ['success' => false, 'url' => null,
                'error' => 'The uploaded file is not a valid image.'];
    }

    // Build destination path
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destDir  = UPLOAD_DIR . $subfolder . '/';
    $destPath = $destDir . $filename;

    // Ensure directory exists
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $htaccess = $destDir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|asp|aspx|jsp|sh)$\">\nRequire all denied\n</FilesMatch>\n");
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'url' => null,
                'error' => 'Failed to save file. Check server permissions.'];
    }

    $url = UPLOAD_URL . $subfolder . '/' . $filename;
    return ['success' => true, 'url' => $url, 'error' => null];
}
