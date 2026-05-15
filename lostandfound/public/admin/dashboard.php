<?php
// ============================================================
// public/admin/dashboard.php
// Admin dashboard — click thumbnail or View to open popup
// No separate page needed — modal shows full item detail
// ============================================================

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';
require_once '../../app/helpers.php';

requireAdminSession();
$admin = getAdmin();

$message      = '';
$message_type = 'success';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = intval($_POST['item_id'] ?? 0);
    $action  = $_POST['action'] ?? '';

    if ($item_id > 0 && $action === 'collect') {
        $pdo->prepare("UPDATE found_items SET status = 'collected' WHERE item_id = ?")
            ->execute([$item_id]);
        logAction($pdo, $admin['id'], 'mark_collected', $item_id, 'Marked as collected by owner');
        $message = 'Item marked as collected.';
    }

    if ($item_id > 0 && $action === 'delete') {
        $row = $pdo->prepare("SELECT photo_path FROM found_items WHERE item_id = ?");
        $row->execute([$item_id]);
        $photo = $row->fetchColumn();
        $pdo->prepare("DELETE FROM found_items WHERE item_id = ?")->execute([$item_id]);
        if ($photo) deletePhotoFile($photo);
        logAction($pdo, $admin['id'], 'delete_item', $item_id, 'Item permanently deleted');
        $message      = 'Item deleted.';
        $message_type = 'warning';
    }
}

// ── Fetch items ──────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all','available','collected'], true)) $filter = 'all';

$sql    = "SELECT fi.*, c.name AS cat_name, c.icon
           FROM found_items fi
           JOIN categories c ON fi.category_id = c.category_id";
$params = [];
if ($filter !== 'all') { $sql .= " WHERE fi.status = ?"; $params[] = $filter; }
$sql .= " ORDER BY fi.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

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
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: #f0f0f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .topbar { background: #1a1a2e; }
        .stat-card { border-radius: 14px; border: none; }

        /* ── Item row ── */
        .item-row {
            display: flex; align-items: center;
            gap: 12px; padding: 11px 16px;
            transition: background .12s;
        }
        .item-row:hover { background: #fafafa; }

        /* Thumbnail — clickable */
        .thumb-wrap {
            flex-shrink: 0; cursor: pointer;
            position: relative;
        }
        .thumb-wrap:hover .thumb-overlay {
            opacity: 1;
        }
        .thumb {
            width: 54px; height: 54px;
            border-radius: 10px;
            object-fit: contain;
            background: #EEEDFE; padding: 4px;
            display: block;
        }
        .thumb-icon {
            width: 54px; height: 54px;
            border-radius: 10px; background: #EEEDFE;
            display: flex; align-items: center; justify-content: center;
        }
        .thumb-icon i { font-size: 24px; color: #534AB7; }

        /* Eye icon overlay on hover */
        .thumb-overlay {
            position: absolute; inset: 0;
            background: rgba(26,26,46,.55);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity .15s;
        }
        .thumb-overlay i { font-size: 20px; color: #fff; }

        /* Action buttons */
        .action-row { display: flex; gap: 5px; align-items: center; }

        .btn-view {
            display: inline-flex; align-items: center; gap: 4px;
            background: #1a1a2e; color: #fff;
            border: none; border-radius: 7px;
            padding: 5px 11px; font-size: 11px; font-weight: 500;
            cursor: pointer; white-space: nowrap;
        }
        .btn-view:hover { background: #2d2d4e; }

        .btn-collect {
            display: inline-flex; align-items: center; gap: 4px;
            background: #E1F5EE; color: #085041;
            border: none; border-radius: 7px;
            padding: 5px 10px; font-size: 11px; font-weight: 500;
            cursor: pointer; white-space: nowrap;
        }
        .btn-collect:hover { background: #c2eedd; }

        .btn-del {
            display: inline-flex; align-items: center;
            background: #FAECE7; color: #993C1D;
            border: none; border-radius: 7px;
            padding: 5px 10px; font-size: 11px;
            cursor: pointer;
        }
        .btn-del:hover { background: #f5d0c2; }

        .badge-avail     { background:#E1F5EE; color:#085041; font-size:10px; padding:3px 9px; border-radius:20px; font-weight:500; display:inline-block; }
        .badge-collected { background:#eee;    color:#666;    font-size:10px; padding:3px 9px; border-radius:20px; font-weight:500; display:inline-block; }

        /* ── POPUP MODAL ── */
        .modal-backdrop-custom {
            display: none;
            position: fixed; inset: 0; z-index: 1000;
            background: rgba(0,0,0,.55);
            align-items: center; justify-content: center;
            padding: 20px;
        }
        .modal-backdrop-custom.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 22px;
            width: 100%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            animation: popIn .18s ease;
            position: relative;
        }
        @keyframes popIn {
            from { transform: scale(.92); opacity: 0; }
            to   { transform: scale(1);  opacity: 1; }
        }

        /* Close button */
        .modal-close {
            position: absolute; top: 14px; right: 14px;
            width: 32px; height: 32px;
            background: rgba(0,0,0,.08);
            border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; z-index: 2;
            transition: background .15s;
        }
        .modal-close:hover { background: rgba(0,0,0,.15); }
        .modal-close i { font-size: 18px; color: #444; }

        /* Modal image — fits fully, no crop */
        .modal-photo-wrap {
            width: 100%;
            background: #F0EFFE;
            border-radius: 22px 22px 0 0;
            display: flex; align-items: center; justify-content: center;
            min-height: 200px;
            overflow: hidden;
        }
        .modal-photo-wrap img {
            width: 100%;
            height: auto;
            max-height: 320px;
            object-fit: contain;    /* full image, no crop */
            padding: 20px;
            display: block;
        }
        .modal-photo-wrap .modal-icon {
            font-size: 90px; opacity: .4; color: #534AB7;
            padding: 40px 0;
        }

        /* Modal body */
        .modal-body-content { padding: 18px 20px 22px; }

        .modal-cat-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: #F3E9F7; color: #752282;
            font-size: 11px; font-weight: 500;
            padding: 4px 11px; border-radius: 20px;
            margin-bottom: 8px;
        }
        .modal-item-name {
            font-size: 19px; font-weight: 700;
            color: #1a1a1a; margin-bottom: 12px; line-height: 1.2;
        }

        /* Detail rows */
        .detail-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 10px;
        }
        .detail-row i {
            font-size: 17px; color: #752282;
            flex-shrink: 0; margin-top: 1px;
        }
        .detail-row div { font-size: 13px; color: #333; line-height: 1.45; }
        .detail-row .detail-label { font-size: 11px; color: #aaa; margin-bottom: 1px; }

        .modal-divider { border: none; border-top: 1px solid #f0f0f0; margin: 14px 0; }

        /* Status badge inside modal */
        .modal-status-avail     { background:#E1F5EE; color:#085041; font-size:12px; padding:5px 13px; border-radius:20px; font-weight:600; display:inline-block; }
        .modal-status-collected { background:#eee;    color:#555;    font-size:12px; padding:5px 13px; border-radius:20px; font-weight:600; display:inline-block; }

        /* Modal action buttons */
        .modal-actions { display: flex; gap: 8px; margin-top: 16px; }
        .modal-btn-collect {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
            background: #E1F5EE; color: #085041;
            border: none; border-radius: 11px;
            padding: 11px; font-size: 13px; font-weight: 600;
            cursor: pointer;
        }
        .modal-btn-collect:hover { background: #c2eedd; }
        .modal-btn-delete {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
            background: #FAECE7; color: #993C1D;
            border: none; border-radius: 11px;
            padding: 11px; font-size: 13px; font-weight: 600;
            cursor: pointer;
        }
        .modal-btn-delete:hover { background: #f5d0c2; }
    </style>
</head>
<body>

<!-- TOPBAR -->
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
        <a href="logout.php" class="btn btn-sm btn-outline-light" style="border-radius:8px;font-size:12px;">Logout</a>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:720px;margin:0 auto;">

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
                <div class="fw-bold mb-1" style="font-size:32px;color:#752282;"><?= $available_count ?></div>
                <div class="text-muted" style="font-size:12px;"><i class="ti ti-package me-1"></i>Available</div>
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-card shadow-sm text-center py-3">
                <div class="fw-bold mb-1" style="font-size:32px;color:#0F6E56;"><?= $collected_count ?></div>
                <div class="text-muted" style="font-size:12px;"><i class="ti ti-check me-1"></i>Collected</div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex gap-2 mb-3 align-items-center">
        <?php foreach (['all' => 'All', 'available' => 'Available', 'collected' => 'Collected'] as $key => $label): ?>
            <a href="?filter=<?= $key ?>" class="btn btn-sm"
               style="border-radius:8px;font-size:12px;
                      <?= $filter === $key
                          ? 'background:#1a1a2e;color:#fff;border-color:#1a1a2e;'
                          : 'background:#fff;color:#555;border:1px solid #ddd;' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
        <span class="ms-auto text-muted" style="font-size:12px;">
            <?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Items list -->
    <div class="card border-0 shadow-sm" style="border-radius:16px;overflow:hidden;">

        <?php if (empty($items)): ?>
            <div class="text-center text-muted py-5">
                <i class="ti ti-inbox" style="font-size:40px;"></i>
                <p class="mt-2 mb-0" style="font-size:13px;">No items found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($items as $i => $item):
                $photo_url = $item['photo_path']
                    ? '../' . ltrim($item['photo_path'], '/')
                    : null;
            ?>
            <div class="item-row <?= $i > 0 ? 'border-top' : '' ?>">

                <!-- Clickable thumbnail -->
                <div class="thumb-wrap"
                     onclick="openModal(<?= htmlspecialchars(json_encode([
                         'id'          => $item['item_id'],
                         'name'        => $item['item_name'],
                         'cat'         => $item['cat_name'],
                         'icon'        => $item['icon'],
                         'desc'        => $item['description'],
                         'location'    => $item['location_found'],
                         'date'        => formatDate($item['date_found']),
                         'reported'    => formatDate($item['created_at']),
                         'status'      => $item['status'],
                         'photo'       => $photo_url,
                     ]), ENT_QUOTES, 'UTF-8') ?>)">
                    <?php if ($photo_url): ?>
                        <img src="<?= htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8') ?>"
                             class="thumb" alt="">
                    <?php else: ?>
                        <div class="thumb-icon">
                            <i class="ti <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                        </div>
                    <?php endif; ?>
                    <div class="thumb-overlay"><i class="ti ti-eye"></i></div>
                </div>

                <!-- Item info -->
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="fw-semibold text-truncate" style="font-size:13px;color:#1a1a1a;">
                        <?= htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div class="text-muted text-truncate" style="font-size:11px;">
                        <?= htmlspecialchars($item['cat_name'],      ENT_QUOTES, 'UTF-8') ?>
                        &middot; <?= htmlspecialchars($item['location_found'], ENT_QUOTES, 'UTF-8') ?>
                        &middot; <?= formatDate($item['date_found']) ?>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
                    <span class="<?= $item['status'] === 'available' ? 'badge-avail' : 'badge-collected' ?>">
                        <?= $item['status'] === 'available' ? 'Available' : 'Collected' ?>
                    </span>
                    <div class="action-row">

                        <!-- View popup button -->
                        <button class="btn-view"
                                onclick="openModal(<?= htmlspecialchars(json_encode([
                                    'id'       => $item['item_id'],
                                    'name'     => $item['item_name'],
                                    'cat'      => $item['cat_name'],
                                    'icon'     => $item['icon'],
                                    'desc'     => $item['description'],
                                    'location' => $item['location_found'],
                                    'date'     => formatDate($item['date_found']),
                                    'reported' => formatDate($item['created_at']),
                                    'status'   => $item['status'],
                                    'photo'    => $photo_url,
                                ]), ENT_QUOTES, 'UTF-8') ?>)">
                            <i class="ti ti-eye" style="font-size:12px;"></i> View
                        </button>

                        <!-- Mark collected -->
                        <?php if ($item['status'] === 'available'): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Mark this item as collected by the owner?')">
                                <input type="hidden" name="action"  value="collect">
                                <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                                <button class="btn-collect" type="submit">
                                    <i class="ti ti-check" style="font-size:12px;"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Delete -->
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Permanently delete this item? This cannot be undone.')">
                            <input type="hidden" name="action"  value="delete">
                            <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                            <button class="btn-del" type="submit">
                                <i class="ti ti-trash" style="font-size:12px;"></i>
                            </button>
                        </form>

                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <div class="text-center mt-3">
        <a href="audit.php" class="text-muted" style="font-size:12px;text-decoration:none;">
            <i class="ti ti-history me-1"></i>View audit log
        </a>
    </div>

</div>

<!-- ════════════════════════════════════════════════════════
     POPUP MODAL — click thumbnail or View button to open
     ════════════════════════════════════════════════════════ -->
<div class="modal-backdrop-custom" id="itemModal" onclick="closeModalOutside(event)">
    <div class="modal-box" id="modalBox">

        <!-- Close button -->
        <button class="modal-close" onclick="closeModal()">
            <i class="ti ti-x"></i>
        </button>

        <!-- Image area -->
        <div class="modal-photo-wrap" id="modalPhotoWrap">
            <!-- filled by JS -->
        </div>

        <!-- Body -->
        <div class="modal-body-content">

            <div class="modal-cat-pill" id="modalCat">
                <!-- filled by JS -->
            </div>

            <div class="modal-item-name" id="modalName"><!-- filled by JS --></div>

            <div class="detail-row">
                <i class="ti ti-map-pin"></i>
                <div>
                    <div class="detail-label">Location found</div>
                    <span id="modalLocation"></span>
                </div>
            </div>

            <div class="detail-row">
                <i class="ti ti-calendar"></i>
                <div>
                    <div class="detail-label">Date found</div>
                    <span id="modalDate"></span>
                </div>
            </div>

            <div class="detail-row">
                <i class="ti ti-clock"></i>
                <div>
                    <div class="detail-label">Reported on</div>
                    <span id="modalReported"></span>
                </div>
            </div>

            <div class="detail-row">
                <i class="ti ti-notes"></i>
                <div>
                    <div class="detail-label">Description</div>
                    <span id="modalDesc"></span>
                </div>
            </div>

            <hr class="modal-divider">

            <div class="d-flex align-items-center justify-content-between">
                <span style="font-size:13px;color:#888;">Status</span>
                <span id="modalStatus"></span>
            </div>

            <!-- Action buttons inside modal -->
            <div class="modal-actions" id="modalActions">
                <!-- filled by JS -->
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Open modal and fill with item data ────────────────────────
function openModal(item) {
    // ── Photo area
    const photoWrap = document.getElementById('modalPhotoWrap');
    if (item.photo) {
        photoWrap.innerHTML = `<img src="${escHtml(item.photo)}" alt="${escHtml(item.name)}">`;
    } else {
        photoWrap.innerHTML = `<i class="ti ${escHtml(item.icon)} modal-icon"></i>`;
    }

    // ── Category pill
    document.getElementById('modalCat').innerHTML =
        `<i class="ti ${escHtml(item.icon)}"></i> ${escHtml(item.cat)}`;

    // ── Text fields
    document.getElementById('modalName').textContent     = item.name;
    document.getElementById('modalLocation').textContent = item.location;
    document.getElementById('modalDate').textContent     = item.date;
    document.getElementById('modalReported').textContent = item.reported;
    document.getElementById('modalDesc').textContent     = item.desc;

    // ── Status badge
    const statusEl = document.getElementById('modalStatus');
    if (item.status === 'available') {
        statusEl.className = 'modal-status-avail';
        statusEl.textContent = 'Available';
    } else {
        statusEl.className = 'modal-status-collected';
        statusEl.textContent = 'Collected';
    }

    // ── Action buttons
    const actionsEl = document.getElementById('modalActions');
    let btns = '';

    if (item.status === 'available') {
        btns += `
            <form method="POST" style="flex:1;" onsubmit="return confirm('Mark as collected by owner?')">
                <input type="hidden" name="action"  value="collect">
                <input type="hidden" name="item_id" value="${item.id}">
                <button type="submit" class="modal-btn-collect" style="width:100%;">
                    <i class="ti ti-check"></i> Mark Collected
                </button>
            </form>`;
    }

    btns += `
        <form method="POST" style="flex:1;" onsubmit="return confirm('Permanently delete this item? This cannot be undone.')">
            <input type="hidden" name="action"  value="delete">
            <input type="hidden" name="item_id" value="${item.id}">
            <button type="submit" class="modal-btn-delete" style="width:100%;">
                <i class="ti ti-trash"></i> Delete
            </button>
        </form>`;

    actionsEl.innerHTML = btns;

    // ── Show modal
    document.getElementById('itemModal').classList.add('open');
    document.body.style.overflow = 'hidden'; // prevent background scroll
}

// ── Close modal ───────────────────────────────────────────────
function closeModal() {
    document.getElementById('itemModal').classList.remove('open');
    document.body.style.overflow = '';
}

// Close when clicking outside the modal box
function closeModalOutside(event) {
    if (event.target === document.getElementById('itemModal')) {
        closeModal();
    }
}

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// ── Escape HTML to prevent XSS in JS ─────────────────────────
function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str || '')));
    return d.innerHTML;
}
</script>

</body>
</html>