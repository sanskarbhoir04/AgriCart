<?php
// =====================================================================
// includes/secure_upload.php — one reusable, secure image-upload
// function. Currently used by the payment-proof upload flow
// (pages/confirm_payment.php); written generically so other upload
// spots (products, equipment, profile photos) can adopt it later
// without changing behaviour for anything that doesn't call it.
//
// Guarantees:
//   - MIME type checked with finfo_file() against the ACTUAL bytes,
//     not the client-supplied Content-Type or filename extension.
//   - Extension is derived from the verified MIME type, never from
//     the uploaded filename, so "shell.php.jpg" style double
//     extensions can't smuggle through.
//   - Filename is a cryptographically random hex string — the
//     original filename is discarded entirely.
//   - Rejects anything that isn't a real image (getimagesize check)
//     and enforces a max dimension + max file size.
//   - Destination folder is fixed by the caller (never derived from
//     user input), so there's no directory-traversal surface.
// =====================================================================

if (!function_exists('agri_secure_upload_image')) {
    /**
     * @param array  $file       A single entry from $_FILES (e.g. $_FILES['screenshot']).
     * @param string $destDir    Absolute path to the destination folder. Must already exist.
     * @param string $webPrefix  Web-relative path prefix stored in the DB (e.g. 'assets/uploads/payments').
     * @param int    $maxBytes   Max allowed file size in bytes.
     * @return array{ok:bool, path?:string, error?:string} 'path' is the value to store in the DB.
     */
    function agri_secure_upload_image(array $file, string $destDir, string $webPrefix, int $maxBytes = 3 * 1024 * 1024): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['ok' => false, 'error' => 'Malformed upload.'];
        }
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file uploaded.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload failed (error code ' . (int)$file['error'] . ').'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }
        if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'File must be smaller than ' . round($maxBytes / 1024 / 1024, 1) . 'MB.'];
        }

        // Verify actual bytes, not the client-sent Content-Type.
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
            if ($finfo) finfo_close($finfo);
        }
        if (!$mime || !isset($allowedMimes[$mime])) {
            return ['ok' => false, 'error' => 'Only JPG, PNG or WEBP images are allowed.'];
        }

        // Confirm it's really a decodable image (blocks polyglot files
        // that pass the finfo check but aren't valid images).
        $dims = @getimagesize($file['tmp_name']);
        if ($dims === false) {
            return ['ok' => false, 'error' => 'File is not a valid image.'];
        }
        if ($dims[0] > 6000 || $dims[1] > 6000) {
            return ['ok' => false, 'error' => 'Image dimensions are too large.'];
        }

        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            return ['ok' => false, 'error' => 'Upload destination is not writable.'];
        }

        $ext = $allowedMimes[$mime];
        try {
            $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Exception $e) {
            $randomName = uniqid('img_', true) . '.' . $ext;
        }

        $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $randomName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['ok' => false, 'error' => 'Could not save uploaded file.'];
        }
        @chmod($destPath, 0644);

        return ['ok' => true, 'path' => rtrim($webPrefix, '/') . '/' . $randomName];
    }
}

if (!function_exists('agri_delete_uploaded_file')) {
    /**
     * Safely deletes a previously stored web-relative path (as returned
     * by agri_secure_upload_image) rooted at the project root. No-ops
     * on anything that isn't a plain relative path under assets/uploads.
     */
    function agri_delete_uploaded_file(?string $webPath): void
    {
        if (!$webPath) return;
        // Only ever allow deleting inside assets/uploads/ — refuse
        // absolute paths, parent traversal, or paths pointing elsewhere.
        $webPath = ltrim($webPath, '/');
        if (strpos($webPath, '..') !== false) return;
        if (strpos($webPath, 'assets/uploads/') !== 0) return;

        $fullPath = __DIR__ . '/../' . $webPath;
        $real = realpath($fullPath);
        $uploadsRoot = realpath(__DIR__ . '/../assets/uploads');
        if ($real && $uploadsRoot && strpos($real, $uploadsRoot) === 0 && is_file($real)) {
            @unlink($real);
        }
    }
}
