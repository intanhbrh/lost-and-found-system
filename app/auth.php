<?php
// ============================================================
// app/auth.php  — MSP Session Integration
// HELP International School — Lost & Found System
//
// HOW IT WORKS (same pattern as ptm-system):
// 1. Student logs in to his.myschoolportal.co.uk
// 2. MSP authenticates via Google OAuth (google/apiclient)
// 3. MSP stores user info in PHP $_SESSION
// 4. Student clicks Lost & Found link inside MSP
// 5. This file checks for that session
// 6. If valid  → user goes straight to dashboard
// 7. If not    → redirect back to MSP login
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── MSP Portal URL ──────────────────────────────────────────
define('MSP_LOGIN_URL', 'https://his.myschoolportal.co.uk/login');

// ── MSP session variable names ───────────────────────────────
// These are the session keys set by My School Portal after
// a user logs in via Google OAuth.
// Confirm exact key names by checking ptm-system/app/ files
// with school IT — these are the most likely names based on
// how MSP and Google OAuth work together.
define('MSP_KEY_EMAIL', 'user_email');   // e.g. student@help.edu.my
define('MSP_KEY_NAME',  'user_name');    // e.g. Jamie Sutherland
define('MSP_KEY_ROLE',  'user_role');    // e.g. student / staff

// ── Main session check ───────────────────────────────────────
// Call requireMSPSession() at the top of index.php and item.php
// If no valid session → redirect to MSP login
function requireMSPSession(): void {

    // Check if MSP session variables exist
    if (
        empty($_SESSION[MSP_KEY_EMAIL]) ||
        empty($_SESSION[MSP_KEY_NAME])
    ) {
        // Build return URL so after MSP login, user comes back here
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      ? 'https' : 'http';
        $return_url = urlencode(
            $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
        );

        // Redirect to MSP login with return URL
        header('Location: ' . MSP_LOGIN_URL . '?returnUrl=' . $return_url);
        exit;
    }
}

// ── Get current logged-in user from MSP session ──────────────
function getCurrentUser(): array {
    return [
        'email' => $_SESSION[MSP_KEY_EMAIL] ?? '',
        'name'  => $_SESSION[MSP_KEY_NAME]  ?? 'Student',
        'role'  => $_SESSION[MSP_KEY_ROLE]  ?? 'student',
    ];
}

// ── Get user initials for avatar display ────────────────────
function getInitials(string $name): string {
    $parts  = explode(' ', trim($name));
    $result = '';
    foreach ($parts as $part) {
        $result .= strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($result) >= 2) break;
    }
    return $result ?: '?';
}


// ============================================================
// LOCAL TESTING ONLY — remove this block before going live
// ============================================================
// When running on localhost, MSP session doesn't exist.
// This block fakes a session so you can test without MSP.
// DELETE this entire block when deploying to school server.
if (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1'
) {
    if (empty($_SESSION[MSP_KEY_EMAIL])) {
        $_SESSION[MSP_KEY_EMAIL] = 'student@help.edu.my';
        $_SESSION[MSP_KEY_NAME]  = 'Test Student';
        $_SESSION[MSP_KEY_ROLE]  = 'student';
    }
}
// ============================================================