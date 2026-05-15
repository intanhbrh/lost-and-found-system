<?php
// ============================================================
// public/admin/audit.php
// Audit log — shows all admin actions (add, collect, delete)
// Security Foyer staff only
// ============================================================

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';
require_once '../../app/helpers.php';

requireAdminSession();
$admin = getAdmin();

// Fetch audit log — newest first
$logs = $pdo->query(
    "SELECT al.*, a.name AS admin_name
     FROM audit_log al
     JOIN admins a ON al.admin_id = a.admin_id
     ORDER BY al.created_at DESC
     LIMIT 100"
)->fetchAll();

// Map action keys to readable labels + colours
function actionLabel(string $action): array {
    return match ($action) {
        'add_item'       => ['Added item',      'success'],
        'mark_collected' => ['Marked collected','primary'],
        'delete_item'    => ['Deleted item',    'danger'],
        default          => [$action,           'secondary'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <style>
        body { background: #f0f0f5; }
        .topbar { background: #1a1a2e; }
        .card { border-radius: 14px; border: none; }
    </style>
</head>
<body>

<nav class="navbar topbar shadow-sm px-3 py-2">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="text-white text-decoration-none">
            <i class="ti ti-arrow-left" style="font-size:22px;"></i>
        </a>
        <span class="text-white fw-semibold" style="font-size:15px;">Audit Log</span>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:600px;margin:0 auto;">

    <p class="text-muted mb-3" style="font-size:12px;">
        Showing last <?= count($logs) ?> actions. All times are server local time.
    </p>

    <div class="card shadow-sm" style="overflow:hidden;">
        <?php if (empty($logs)): ?>
            <div class="text-center text-muted py-5" style="font-size:13px;">
                <i class="ti ti-history" style="font-size:36px;"></i>
                <p class="mt-2">No actions logged yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($logs as $i => $log):
                [$label, $color] = actionLabel($log['action']);
            ?>
                <div class="d-flex align-items-start gap-3 px-3 py-2
                            <?= $i > 0 ? 'border-top' : '' ?>">
                    <span class="badge bg-<?= $color ?> mt-1" style="font-size:10px;min-width:90px;text-align:center;">
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <div class="flex-grow-1">
                        <div style="font-size:12px;color:#333;">
                            <?= htmlspecialchars($log['note'] ?: '—', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div style="font-size:11px;color:#999;">
                            By <?= htmlspecialchars($log['admin_name'], ENT_QUOTES, 'UTF-8') ?>
                            &middot; <?= date('d M Y, H:i', strtotime($log['created_at'])) ?>
                            <?= $log['item_id'] ? '&middot; Item #' . (int)$log['item_id'] : '' ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
