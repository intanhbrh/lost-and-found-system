<?php
// ── public/admin/dashboard.php ──────────────────────────────
// Admin dashboard — view and manage all found items

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';

requireAdminSession();
$admin = getAdmin();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = intval($_POST['delete_id']);

    // Get photo path to delete file
    $stmt = $pdo->prepare("SELECT photo_path FROM found_items WHERE item_id = ?");
    $stmt->execute([$del_id]);
    $row = $stmt->fetch();

    if ($row && $row['photo_path']) {
        $file = '../' . ltrim($row['photo_path'], '/');
        if (file_exists($file)) unlink($file);
    }

    $pdo->prepare("DELETE FROM found_items WHERE item_id = ?")->execute([$del_id]);

    // Log action
    $pdo->prepare("INSERT INTO audit_log (admin_id, action, item_id, note) VALUES (?,?,?,?)")
        ->execute([$admin['id'], 'delete_item', $del_id, 'Item removed by admin']);

    header("Location: dashboard.php?deleted=1");
    exit;
}

// Handle mark as collected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_id'])) {
    $col_id = intval($_POST['collect_id']);
    $pdo->prepare("UPDATE found_items SET status='collected' WHERE item_id = ?")->execute([$col_id]);

    $pdo->prepare("INSERT INTO audit_log (admin_id, action, item_id, note) VALUES (?,?,?,?)")
        ->execute([$admin['id'], 'mark_collected', $col_id, 'Marked as collected by owner']);

    header("Location: dashboard.php?collected=1");
    exit;
}

// Fetch all items
$items = $pdo->query(
    "SELECT fi.*, c.name AS category_name
     FROM found_items fi
     JOIN categories c ON fi.category_id = c.category_id
     ORDER BY fi.created_at DESC"
)->fetchAll();

$available  = array_filter($items, fn($i) => $i['status'] === 'available');
$collected  = array_filter($items, fn($i) => $i['status'] === 'collected');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="background:#f4f4f8;">

<!-- TOP NAV -->
<nav class="navbar navbar-dark px-3" style="background:#752282;">
    <span class="navbar-brand mb-0">
        <i class="ti ti-shield-lock me-1"></i> Admin — Lost &amp; Found
    </span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-white-50 small"><?= htmlspecialchars($admin['name']) ?></span>
        <a href="add.php" class="btn btn-sm btn-light fw-semibold">
            <i class="ti ti-plus me-1"></i> Add item
        </a>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:700px;margin:0 auto;">

    <!-- Alerts -->
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success small py-2">Item added successfully.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-warning small py-2">Item deleted.</div>
    <?php endif; ?>
    <?php if (isset($_GET['collected'])): ?>
        <div class="alert alert-info small py-2">Item marked as collected.</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="card border text-center py-3">
                <div class="fw-bold" style="font-size:28px;color:#752282;"><?= count($available) ?></div>
                <div class="small text-muted">Available items</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card border text-center py-3">
                <div class="fw-bold" style="font-size:28px;color:#198754;"><?= count($collected) ?></div>
                <div class="small text-muted">Collected items</div>
            </div>
        </div>
    </div>

    <!-- Items table -->
    <div class="card border">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span class="fw-semibold small">All found items</span>
            <a href="add.php" class="btn btn-sm fw-semibold" style="background:#752282;color:#fff;">
                <i class="ti ti-plus me-1"></i> Add item
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($items)): ?>
                <p class="text-center text-muted py-4 small">No items yet. Add one above.</p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">

                        <!-- Photo -->
                        <?php if ($item['photo_path']): ?>
                            <img src="../<?= htmlspecialchars($item['photo_path']) ?>"
                                 class="rounded-2 object-fit-cover flex-shrink-0"
                                 style="width:46px;height:46px;">
                        <?php else: ?>
                            <div class="rounded-2 flex-shrink-0 d-flex align-items-center justify-content-center"
                                 style="width:46px;height:46px;background:#EEEDFE;">
                                <i class="ti ti-package" style="color:#534AB7;font-size:20px;"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Info -->
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small text-truncate">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </div>
                            <div class="text-muted" style="font-size:11px;">
                                <?= htmlspecialchars($item['category_name']) ?>
                                · <?= htmlspecialchars($item['location_found']) ?>
                                · <?= date('d M Y', strtotime($item['date_found'])) ?>
                            </div>
                        </div>

                        <!-- Status + actions -->
                        <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                            <span class="badge rounded-pill
                                <?= $item['status'] === 'available' ? 'bg-success' : 'bg-secondary' ?>
                                " style="font-size:10px;">
                                <?= ucfirst($item['status']) ?>
                            </span>
                            <div class="d-flex gap-1">
                                <?php if ($item['status'] === 'available'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="collect_id" value="<?= $item['item_id'] ?>">
                                        <button class="btn btn-outline-success btn-sm py-0 px-2"
                                                style="font-size:11px;"
                                                onclick="return confirm('Mark as collected?')">
                                            <i class="ti ti-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delete_id" value="<?= $item['item_id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm py-0 px-2"
                                            style="font-size:11px;"
                                            onclick="return confirm('Delete this item permanently?')">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>