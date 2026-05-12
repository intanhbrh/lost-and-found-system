<?php
// ============================================================
// public/item.php
// Shows full detail of a single found item
// Requires MSP session
// ============================================================

require_once '../app/auth.php';
require_once '../app/db.php';
require_once '../app/helpers.php';

requireMSPSession();
$user = getCurrentUser();

// Get item ID from URL
$id = intval($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: index.php');
    exit;
}

// Fetch item — only show available items to students
$stmt = $pdo->prepare(
    "SELECT fi.*, c.name AS cat_name, c.icon
     FROM found_items fi
     JOIN categories c ON fi.category_id = c.category_id
     WHERE fi.item_id = ?
     AND fi.status = 'available'"
);
$stmt->execute([$id]);
$item = $stmt->fetch();

// Item not found or already collected → back to list
if (!$item) {
    header('Location: index.php?msg=notfound');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($item['item_name']) ?> — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<!-- ── TOP NAV ─────────────────────────────────────────────── -->
<nav class="navbar sticky-top shadow-sm" style="background:#752282;">
    <div class="container-fluid px-3 d-flex align-items-center gap-3">
        <a href="index.php" class="text-white text-decoration-none">
            <i class="ti ti-arrow-left" style="font-size:22px;"></i>
        </a>
        <span class="text-white fw-semibold" style="font-size:15px;">Item Detail</span>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:640px;margin:0 auto;">

    <!-- Photo -->
    <?php if ($item['photo_path']): ?>
        <img src="<?= e($item['photo_path']) ?>"
             alt="Photo of <?= e($item['item_name']) ?>"
             class="w-100 rounded-4 mb-3 object-fit-cover shadow-sm"
             style="max-height:280px;">
    <?php else: ?>
        <div class="w-100 rounded-4 mb-3 d-flex align-items-center justify-content-center"
             style="height:200px;background:#EEEDFE;">
            <i class="ti <?= e($item['icon']) ?>" style="font-size:72px;color:#534AB7;"></i>
        </div>
    <?php endif; ?>

    <!-- Category badge -->
    <div class="mb-2">
        <span class="badge rounded-pill px-3 py-2 cat-badge">
            <i class="ti <?= e($item['icon']) ?> me-1"></i>
            <?= e($item['cat_name']) ?>
        </span>
    </div>

    <!-- Item name -->
    <h4 class="fw-bold mb-1"><?= e($item['item_name']) ?></h4>

    <!-- Date and location -->
    <p class="text-muted mb-3" style="font-size:13px;">
        <i class="ti ti-map-pin me-1" style="color:#752282;"></i>
        <?= e($item['location_found']) ?>
        &nbsp;&middot;&nbsp;
        <i class="ti ti-calendar me-1"></i>
        <?= formatDate($item['date_found']) ?>
    </p>

    <!-- Description card -->
    <div class="card border mb-3 shadow-sm">
        <div class="card-body">
            <h6 class="fw-semibold mb-2">
                <i class="ti ti-info-circle me-1" style="color:#752282;"></i>
                Description
            </h6>
            <p class="mb-0 text-muted" style="font-size:14px;line-height:1.6;">
                <?= nl2br(e($item['description'])) ?>
            </p>
        </div>
    </div>

    <!-- Date added -->
    <p class="text-muted text-center mb-3" style="font-size:12px;">
        Reported to security on <?= formatDate($item['created_at']) ?>
    </p>

    <!-- Collection instruction -->
    <div class="card mb-4 shadow-sm" style="border:1.5px solid #752282;border-radius:14px;">
        <div class="card-body d-flex align-items-start gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:46px;height:46px;background:#F3E9F7;">
                <i class="ti ti-map-pin" style="font-size:24px;color:#752282;"></i>
            </div>
            <div>
                <h6 class="fw-semibold mb-1">Is this your item?</h6>
                <p class="mb-0 text-muted" style="font-size:13px;line-height:1.5;">
                    Visit the <strong style="color:#752282;">Security Foyer</strong> to collect it.<br>
                    Bring your <strong>school ID card</strong> as proof of identity.
                </p>
            </div>
        </div>
    </div>

    <a href="index.php" class="btn w-100 fw-semibold mb-4" style="background:#752282;color:#fff;border-radius:12px;padding:13px;">
        <i class="ti ti-arrow-left me-1"></i> Back to found items
    </a>

</div>

<!-- Bottom nav -->
<nav class="bottom-nav d-flex justify-content-around align-items-center py-2 border-top bg-white">
    <a href="index.php" class="nav-tab active">
        <i class="ti ti-home"></i>
        <span>Home</span>
    </a>
    <a href="index.php" class="nav-tab">
        <i class="ti ti-search"></i>
        <span>Browse</span>
    </a>
</nav>

</body>
</html>