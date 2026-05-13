<?php
// ============================================================
// public/admin/login.php
// Security Foyer staff login — separate from MSP
// ============================================================

require_once '../../app/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['admin_id']   = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Incorrect username or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <style>
        body {
            background: #1a1a2e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 36px 32px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .shield-icon {
            width: 68px;
            height: 68px;
            background: #1a1a2e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        .shield-icon i {
            font-size: 32px;
            color: #fff;
        }
        .form-control {
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid #ddd;
        }
        .form-control:focus {
            border-color: #752282;
            box-shadow: 0 0 0 3px rgba(117,34,130,0.1);
        }
        .btn-login {
            background: #1a1a2e;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 14px;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #2d2d4e; }
        .input-icon-wrap {
            position: relative;
        }
        .input-icon-wrap i {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 18px;
            pointer-events: none;
        }
        .input-icon-wrap input {
            padding-left: 40px;
        }
        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 18px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }
    </style>
</head>
<body>

<div class="login-card">

    <!-- Icon + title -->
    <div class="shield-icon">
        <i class="ti ti-shield-lock"></i>
    </div>
    <h5 class="text-center fw-bold mb-1">Security Admin Login</h5>
    <p class="text-center text-muted mb-4" style="font-size:13px;">
        HELP International School<br>Lost &amp; Found System
    </p>

    <!-- Error message -->
    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3" style="font-size:13px;border-radius:10px;">
            <i class="ti ti-alert-circle flex-shrink-0"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Login form -->
    <form method="POST" novalidate>

        <div class="mb-3">
            <label for="username" class="form-label fw-semibold" style="font-size:13px;">Username</label>
            <div class="input-icon-wrap">
                <i class="ti ti-user"></i>
                <input type="text"
                       id="username"
                       name="username"
                       class="form-control"
                       placeholder="Enter your username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="username"
                       required
                       autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-semibold" style="font-size:13px;">Password</label>
            <div class="input-icon-wrap">
                <i class="ti ti-lock"></i>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       placeholder="Enter your password"
                       autocomplete="current-password"
                       required>
                <button type="button" class="toggle-password" onclick="togglePassword()" tabindex="-1">
                    <i class="ti ti-eye" id="eye-icon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="ti ti-login me-2"></i>Login to Admin Panel
        </button>

    </form>

    <hr class="my-4">

    <p class="text-center text-muted mb-0" style="font-size:12px;">
        Not a security staff member?<br>
        <a href="../index.php" style="color:#752282;text-decoration:none;font-weight:500;">
            Browse found items
        </a> — no login needed.
    </p>

</div>

<script>
function togglePassword() {
    const input   = document.getElementById('password');
    const icon    = document.getElementById('eye-icon');
    const showing = input.type === 'text';
    input.type    = showing ? 'password' : 'text';
    icon.className = showing ? 'ti ti-eye' : 'ti ti-eye-off';
}
</script>

</body>
</html>
