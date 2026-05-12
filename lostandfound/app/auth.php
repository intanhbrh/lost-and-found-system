<?php
// ============================================================
// app/auth.php
// My School Portal (MSP) session check
// Students/staff must be logged into MSP to view the system
// Pattern copied from ptm-system (same school, same portal)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// MSP login URL — redirects here if no session found
define('MSP_LOGIN_URL', 'https://his.myschoolportal.co.uk/login');

// ── Check MSP session ────────────────────────────────────────
// Call this at the top of any page students/staff access.
// If no valid MSP session → redirect to MSP login page.
// NOTE: Confirm exact session key names with school IT /
//       by checking the ptm-system auth code.
function requireMSPSession() {
    if (empty($_SESSION['msp_email']) || empty($_SESSION['msp_name'])) {
        // Build return URL so MSP redirects back here after login
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $return_url = urlencode($protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        header('Location: ' . MSP_LOGIN_URL . '?return=' . $return_url);
        exit;
    }
}

// ── Get current user from MSP session ───────────────────────
function getCurrentUser(): array {
    return [
        'name'  => $_SESSION['msp_name']  ?? 'Student',
        'email' => $_SESSION['msp_email'] ?? '',
        'role'  => $_SESSION['msp_role']  ?? 'student',
    ];
}

// ── Helper: get user initials for avatar ────────────────────
function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = '';
    foreach ($parts as $p) {
        $init .= strtoupper(mb_substr($p, 0, 1));
        if (strlen($init) >= 2) break;
    }
    return $init ?: '?';
}