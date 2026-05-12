<?php
// ============================================================
// public/index.php
// Student / Staff dashboard — browse all found items
// Requires MSP session to access
// ============================================================

require_once '../app/auth.php';
require_once '../app/db.php';
require_once '../app/helpers.php';

requireMSPSession();
$user = getCurrentUser();

// ── Filters from GET ─────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$category_id = intval($_GET['cat']    ?? 0);

// ── Build query ──────────────────────────────────────────────
$sql    = "SELECT fi.*, c.name AS cat_name, c.icon
           FROM found_items fi
           JOIN categories c ON fi.category_id = c.category_id
           WHERE fi.status = 'available'";
$params = [];

if ($search !== '') {
    $sql     .= " AND (fi.item_name LIKE ? OR fi.description LIKE ? OR fi.location_found LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($category_id > 0) {
    $sql     .= " AND fi.category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY fi.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$total = count($items);

// ── Categories for filter chips ──────────────────────────────
$cats = $pdo->query(
    "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost &amp; Found — HELP International School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<!-- ── TOP NAV ─────────────────────────────────────────────── -->
<nav class="navbar sticky-top shadow-sm" style="background:#752282;">
    <div class="container-fluid px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="ti ti-search text-white" style="font-size:20px"></i>
            <div>
                <p class="mb-0 text-white-50" style="font-size:11px;line-height:1">My School Portal</p>
                <span class="text-white fw-semibold" style="font-size:15px">Lost &amp; Found</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Notification placeholder -->
            <i class="ti ti-bell text-white" style="font-size:20px"></i>
            <!-- User initials avatar -->
            <div class="rounded-circle d-flex align-items-center justify-content-center"
                 style="width:32px;height:32px;background:rgba(255,255,255,0.2);">
                <span class="text-white fw-semibold" style="font-size:12px">
                    <?= e(getInitials($user['name'])) ?>
                </span>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:640px;margin:0 auto;">

    <!-- Greeting -->
    <div class="mb-3">
        <p class="text-muted mb-0" style="font-size:13px">Good <?= date('G') < 12 ? 'morning' : (date('G') < 17 ? 'afternoon' : 'evening') ?>,</p>
        <h5 class="fw-semibold mb-0"><?= e($user['name']) ?></h5>
    </div>

    <!-- Search bar -->
    <form method="GET" action="index.php" class="mb-3" id="search-form">
        <div class="input-group shadow-sm">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="ti ti-search text-muted"></i>
            </span>
            <input type="text"
                   name="search"
                   id="search-input"
                   class="form-control border-start-0 ps-1"
                   placeholder="Search by item name or location…"
                   value="<?= e($search) ?>"
                   autocomplete="off">
            <?php if ($category_id > 0): ?>
                <input type="hidden" name="cat" value="<?= $category_id ?>">
            <?php endif; ?>
            <?php if ($search): ?>
                <a href="index.php<?= $category_id ? '?cat='.$category_id : '' ?>"
                   class="btn btn-outline-secondary border-start-0">
                    <i class="ti ti-x"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Category filter chips (horizontal scroll) -->
    <div class="d-flex gap-2 pb-2 mb-3" style="overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;">
        <a href="index.php<?= $search ? '?search='.urlencode($search) : '' ?>"
           class="badge rounded-pill text-decoration-none px-3 py-2 flex-shrink-0
                  <?= $category_id === 0 ? 'text-white' : 'bg-white text-dark border' ?>"
           style="<?= $category_id === 0 ? 'background:#752282;' : '' ?>font-size:12px;">
            All
        </a>
        <?php foreach ($cats as $cat): ?>
            <?php
            $is_active = $category_id === (int)$cat['category_id'];
            $href = '?cat=' . $cat['category_id'] . ($search ? '&search='.urlencode($search) : '');
            ?>
            <a href="<?= e($href) ?>"
               class="badge rounded-pill text-decoration-none px-3 py-2 flex-shrink-0
                      <?= $is_active ? 'text-white' : 'bg-white text-dark border' ?>"
               style="<?= $is_active ? 'background:#752282;' : '' ?>font-size:12px;">
                <?= e($cat['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Result count -->
    <p class="text-muted mb-2" style="font-size:12px;">
        <?= $total ?> item<?= $total !== 1 ? 's' : '' ?> found
        <?= $search ? ' for "' . e($search) . '"' : '' ?>
    </p>

    <!-- ── Item list ─────────────────────────────────────────── -->
    <?php if (empty($items)): ?>
        <div class="text-center py-5">
            <i class="ti ti-mood-empty text-muted" style="font-size:52px;"></i>
            <p class="text-muted mt-3">
                <?= $search || $category_id
                    ? 'No items match your search. Try different keywords.'
                    : 'No found items right now. Check back later.' ?>
            </p>
            <?php if ($search || $category_id): ?>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <a href="item.php?id=<?= (int)$item['item_id'] ?>" class="text-decoration-none">
                <div class="card mb-3 border item-card shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3 py-3">

                        <!-- Photo or icon thumb -->
                        <?php if ($item['photo_path']): ?>
                            <img src="<?= e($item['photo_path']) ?>"
                                 alt="Photo of <?= e($item['item_name']) ?>"
                                 class="rounded-3 flex-shrink-0 object-fit-cover"
                                 style="width:60px;height:60px;">
                        <?php else: ?>
                            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 thumb-icon">
                                <i class="ti <?= e($item['icon']) ?>"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Item info -->
                        <div class="flex-grow-1 min-width-0">
                            <h6 class="mb-1 fw-semibold text-dark text-truncate">
                                <?= e($item['item_name']) ?>
                            </h6>
                            <p class="mb-2 text-muted text-truncate" style="font-size:12px;">
                                <i class="ti ti-map-pin me-1"></i><?= e($item['location_found']) ?>
                                &nbsp;&middot;&nbsp;
                                <i class="ti ti-calendar me-1"></i><?= formatDate($item['date_found']) ?>
                            </p>
                            <span class="badge rounded-pill cat-badge">
                                <?= e($item['cat_name']) ?>
                            </span>
                        </div>

                        <i class="ti ti-chevron-right text-muted flex-shrink-0"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Footer note -->
    <div class="text-center mt-4 pb-4 border-top pt-3">
        <p class="text-muted mb-0" style="font-size:13px;">
            <i class="ti ti-map-pin me-1" style="color:#752282;"></i>
            Found your item? Collect it from the
            <strong style="color:#752282;">Security Foyer</strong>
            with your school ID.
        </p>
    </div>

</div>

<!-- Bottom nav bar -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Live search on typing -->
<script>
let searchTimer;
document.getElementById('search-input').addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        document.getElementById('search-form').submit();
    }, 400);
});
</script>

</body>
</html>