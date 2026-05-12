<?php
// ── public/admin/login.php ──────────────────────────────────
// Admin login — Security Foyer staff only

require_once '../../app/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in
if (!empty($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id']   = $admin['admin_id'];
            $_SESSION['admin_name'] = $admin['name'];
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body style="background:#f4f4f8; min-height:100vh; display:flex; align-items:center;">

<div class="container" style="max-width:380px;">
    <div class="text-center mb-4">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
             style="width:64px;height:64px;background:#752282;">
            <i class="ti ti-lock text-white" style="font-size:28px;"></i>
        </div>
        <h5 class="fw-bold">Security Admin</h5>
        <p class="text-muted small">HELP International School — Lost &amp; Found</p>
    </div>

    <div class="card border shadow-sm">
        <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Username</label>
                    <input type="text"
                           name="username"
                           class="form-control"
                           placeholder="Enter username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password"
                           name="password"
                           class="form-control"
                           placeholder="Enter password"
                           required>
                </div>
                <button type="submit"
                        class="btn w-100 fw-semibold"
                        style="background:#752282;color:#fff;">
                    <i class="ti ti-login me-1"></i> Login
                </button>
            </form>

        </div>
    </div>

    <p class="text-center text-muted small mt-3">
        Staff login only. Students — <a href="../index.php">browse found items</a>.
    </p>
</div>

</body>
</html>