<?php
// ============================================================
// app/admin_auth.php
// Admin (Security Foyer) session check
// Separate login from MSP — only for security staff
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Require admin to be logged in ───────────────────────────
// Call at top of every admin page.
// If not logged in → redirect to admin login page.
function requireAdminSession(): void {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_name'])) {
        header('Location: /lostandfound/public/admin/login.php');
        exit;
    }
}

// ── Get current admin info ───────────────────────────────────
function getAdmin(): array {
    return [
        'id'   => (int) ($_SESSION['admin_id']   ?? 0),
        'name' => $_SESSION['admin_name'] ?? 'Admin',
    ];
}

// ── Destroy admin session and redirect ──────────────────────
function logoutAdmin(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: /lostandfound/public/admin/login.php');
    exit;
}
