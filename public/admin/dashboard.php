<?php
// ============================================================
// public/admin/dashboard.php
// Admin dashboard — view, mark collected, and delete items
// Security Foyer staff only
// ============================================================

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';
require_once '../../app/helpers.php';

requireAdminSession();
$admin = getAdmin();

$message      = '';
$message_type = 'success';

// ── Handle POST actions ──────────────────────────────────────

// Mark item as collected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect') {
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($item_id > 0) {
        $pdo->prepare("UPDATE found_items SET status = 'collected' WHERE item_id = ?")
            ->execute([$item_id]);
        logAction($pdo, $admin['id'], 'mark_collected', $item_id, 'Marked as collected by owner');
        $message = 'Item marked as collected.';
    }
}

// Delete item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $item_id = intval($_POST['item_id'] ?? 0);
    if ($item_id > 0) {
        // Get photo path before deleting
        $row = $pdo->prepare("SELECT photo_path FROM found_items WHERE item_id = ?");
        $row->execute([$item_id]);
        $photo = $row->fetchColumn();

        // Delete from DB
        $pdo->prepare("DELETE FROM found_items WHERE item_id = ?")->execute([$item_id]);

        // Delete photo file from disk
        if ($photo) deletePhotoFile($photo);

        logAction($pdo, $admin['id'], 'delete_item', $item_id, 'Item permanently deleted');
        $message      = 'Item deleted.';
        $message_type = 'warning';
    }
}

// ── Fetch all items ──────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$allowed_filters = ['all', 'available', 'collected'];
if (!in_array($filter, $allowed_filters, true)) $filter = 'all';

$sql    = "SELECT fi.*, c.name AS cat_name, c.icon
           FROM found_items fi
           JOIN categories c ON fi.category_id = c.category_id";
$params = [];

if ($filter !== 'all') {
    $sql    .= " WHERE fi.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY fi.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Stats
$available_count = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'available'")->fetchColumn();
$collected_count = $pdo->query("SELECT COUNT(*) FROM found_items WHERE status = 'collected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #f0f0f5; }
        .topbar { background: #1a1a2e; }
        .stat-card { border-radius: 14px; border: none; }
        .item-row { transition: background 0.15s; }
        .item-row:hover { background: #fafafa; }
        .thumb-sm {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .thumb-icon-sm {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: #EEEDFE;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .thumb-icon-sm i { font-size: 22px; color: #534AB7; }
    </style>
</head>
<body>

<!-- ── TOP NAV ─────────────────────────────────────────────── -->
<nav class="navbar topbar shadow-sm px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        <i class="ti ti-shield-lock text-white" style="font-size:22px;"></i>
        <div>
            <p class="mb-0 text-white-50" style="font-size:11px;line-height:1;">Security Foyer</p>
            <span class="text-white fw-semibold" style="font-size:15px;">Admin Dashboard</span>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center ms-auto">
        <a href="add.php" class="btn btn-sm fw-semibold" style="background:#752282;color:#fff;border-radius:8px;">
            <i class="ti ti-plus me-1"></i>Add item
        </a>
        <a href="logout.php" class="btn btn-sm btn-outline-light" style="border-radius:8px;font-size:12px;">
            Logout
        </a>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:700px;margin:0 auto;">

    <!-- Flash message -->
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'warning' ? 'warning' : 'success' ?> alert-dismissible fade show py-2 mb-3"
             style="font-size:13px;border-radius:10px;" role="alert">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-3">
        <div class="col-6">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="fw-bold mb-1" style="font-size:32px;color:#752282;">
                    <?= $available_count ?>
                </div>
                <div class="text-muted" style="font-size:12px;">
                    <i class="ti ti-package me-1"></i>Available items
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="fw-bold mb-1" style="font-size:32px;color:#0F6E56;">
                    <?= $collected_count ?>
                </div>
                <div class="text-muted" style="font-size:12px;">
                    <i class="ti ti-check me-1"></i>Collected total
                </div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex gap-2 mb-3">
        <?php foreach (['all' => 'All', 'available' => 'Available', 'collected' => 'Collected'] as $key => $label): ?>
            <a href="?filter=<?= $key ?>"
               class="btn btn-sm <?= $filter === $key ? 'text-white' : 'btn-outline-secondary' ?>"
               style="<?= $filter === $key ? 'background:#1a1a2e;border-color:#1a1a2e;' : '' ?>border-radius:8px;font-size:12px;">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
        <span class="ms-auto text-muted align-self-center" style="font-size:12px;">
            <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Items list -->
    <div class="card border shadow-sm" style="border-radius:14px;overflow:hidden;">

        <?php if (empty($items)): ?>
            <div class="text-center text-muted py-5">
                <i class="ti ti-inbox" style="font-size:40px;"></i>
                <p class="mt-2 mb-0" style="font-size:13px;">No items found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $i => $item): ?>
                <div class="item-row d-flex align-items-center gap-3 px-3 py-2
                            <?= $i > 0 ? 'border-top' : '' ?>">

                    <!-- Thumb -->
                    <?php if ($item['photo_path']): ?>
                        <img src="../<?= htmlspecialchars($item['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="" class="thumb-sm">
                    <?php else: ?>
                        <div class="thumb-icon-sm">
                            <i class="ti <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Info -->
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-semibold text-dark text-truncate" style="font-size:13px;">
                            <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-muted text-truncate" style="font-size:11px;">
                            <?= htmlspecialchars($item['cat_name'], ENT_QUOTES, 'UTF-8') ?>
                            &middot; <?= htmlspecialchars($item['location_found'], ENT_QUOTES, 'UTF-8') ?>
                            &middot; <?= formatDate($item['date_found']) ?>
                        </div>
                    </div>

                    <!-- Status badge + actions -->
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        <span class="badge rounded-pill" style="font-size:10px;
                            <?= $item['status'] === 'available'
                                ? 'background:#E1F5EE;color:#085041;'
                                : 'background:#eee;color:#666;' ?>">
                            <?= $item['status'] === 'available' ? 'Available' : 'Collected' ?>
                        </span>

                        <div class="d-flex gap-1">
                            <?php if ($item['status'] === 'available'): ?>
                                <!-- Mark collected -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Mark this item as collected by owner?')">
                                    <input type="hidden" name="action"  value="collect">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                                    <button class="btn btn-sm"
                                            style="background:#E1F5EE;color:#085041;border:none;border-radius:6px;padding:3px 8px;font-size:11px;">
                                        <i class="ti ti-check"></i> Collected
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Delete -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Permanently delete this item? This cannot be undone.')">
                                <input type="hidden" name="action"  value="delete">
                                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                                <button class="btn btn-sm"
                                        style="background:#FAECE7;color:#993C1D;border:none;border-radius:6px;padding:3px 8px;font-size:11px;">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Audit log link -->
    <div class="text-center mt-3">
        <a href="audit.php" class="text-muted" style="font-size:12px;text-decoration:none;">
            <i class="ti ti-history me-1"></i>View audit log
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
