<?php
// ============================================================
// app/helpers.php
// Shared utility functions used across public and admin pages
// ============================================================

// ── Sanitise output to prevent XSS ──────────────────────────
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ── Format date nicely ───────────────────────────────────────
function formatDate(string $date): string {
    return date('d M Y', strtotime($date));
}

// ── Handle file upload and return saved path ─────────────────
// Returns relative path string on success, null on no file,
// or throws RuntimeException on validation failure.
function handlePhotoUpload(array $file, string $upload_dir): ?string {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file — that's fine, photo is optional
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size      = 5 * 1024 * 1024; // 5 MB

    // Validate MIME type using finfo (more reliable than $_FILES['type'])
    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!in_array($mime_type, $allowed_types, true)) {
        throw new RuntimeException('Photo must be a JPG, PNG, or WEBP file.');
    }

    if ($file['size'] > $max_size) {
        throw new RuntimeException('Photo must be under 5 MB.');
    }

    // Generate safe unique filename
    $ext      = match ($mime_type) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    };
    $filename = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $full_path = rtrim($upload_dir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $full_path)) {
        throw new RuntimeException('Could not save photo. Check folder permissions.');
    }

    // Return relative web path (relative to /public/)
    return 'assets/uploads/' . $filename;
}

// ── Log an admin action to audit_log ────────────────────────
function logAction(PDO $pdo, int $admin_id, string $action, ?int $item_id = null, string $note = ''): void {
    $stmt = $pdo->prepare(
        'INSERT INTO audit_log (admin_id, action, item_id, note) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$admin_id, $action, $item_id, $note]);
}

// ── Delete photo file from disk ──────────────────────────────
function deletePhotoFile(string $relative_path): void {
    // Build absolute path from /public/ folder
    $abs = dirname(__DIR__) . '/public/' . ltrim($relative_path, '/');
    if ($relative_path && file_exists($abs)) {
        unlink($abs);
    }
}

// ── Campus locations list ────────────────────────────────────
function getCampusLocations(): array {
    return [
        'Library',
        'Canteen',
        'Block A',
        'Block B',
        'Block C',
        'Sports Hall',
        'Car Park',
        'Foyer / Reception',
        'Toilet Block',
        'Classroom',
        'Science Lab',
        'Computer Lab',
        'Other',
    ];
}