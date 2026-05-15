<?php
// ============================================================
// public/index.php — Student dashboard, image fits naturally
// ============================================================

require_once '../app/auth.php';
require_once '../app/db.php';
require_once '../app/helpers.php';

requireMSPSession();
$user = getCurrentUser();

$search      = trim($_GET['search']   ?? '');
$category_id = intval($_GET['cat']    ?? 0);

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

$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

function cardBg(string $cat): string {
    $c = strtolower($cat);
    if (str_contains($c, 'ipad') || str_contains($c, 'tablet')) return '#EEF0FE';
    if (str_contains($c, 'phone'))  return '#E8F8F0';
    if (str_contains($c, 'id'))     return '#FEF3E7';
    if (str_contains($c, 'bag'))    return '#FDE8E8';
    if (str_contains($c, 'ear'))    return '#E8F4FE';
    if (str_contains($c, 'key'))    return '#FFFBE7';
    return '#F3F3F3';
}
function cardIconColor(string $cat): string {
    $c = strtolower($cat);
    if (str_contains($c, 'ipad') || str_contains($c, 'tablet')) return '#534AB7';
    if (str_contains($c, 'phone'))  return '#0F6E56';
    if (str_contains($c, 'id'))     return '#C17A1A';
    if (str_contains($c, 'bag'))    return '#C02A2A';
    if (str_contains($c, 'ear'))    return '#1A72C0';
    return '#888';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost &amp; Found — HELP International School</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #F5F5F7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding-bottom: 80px;
        }

        /* ── Topbar ── */
        .topbar {
            background: #752282;
            padding: 14px 18px;
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar-title { color: #fff; font-size: 18px; font-weight: 700; }
        .topbar-sub   { color: rgba(255,255,255,.6); font-size: 11px; }
        .avatar {
            width: 35px; height: 35px; border-radius: 50%;
            background: rgba(255,255,255,.22);
            color: #fff; font-size: 13px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
        }
        /*search-*/ 
        .search-wrap { }

        /* ── Search ── */
        .search-wrap { padding: 14px 16px 0; }
        .search-box {
            display: flex; align-items: center; gap: 10px;
            background: #fff; border: 1px solid #e8e8e8;
            border-radius: 14px; padding: 11px 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
        }
        .search-box i { color: #bbb; font-size: 18px; flex-shrink: 0; }
        .search-box input {
            border: none; outline: none;
            font-size: 14px; color: #333;
            background: transparent; width: 100%;
        }
        .search-box input::placeholder { color: #c0c0c0; }

        /* ── Filter chips ── */
        .chips-scroll {
            display: flex; gap: 8px; padding: 12px 16px;
            overflow-x: auto; scrollbar-width: none;
        }
        .chips-scroll::-webkit-scrollbar { display: none; }
        .chip {
            white-space: nowrap; flex-shrink: 0;
            padding: 6px 15px; border-radius: 20px;
            font-size: 12px; font-weight: 500;
            border: 1.5px solid #e0e0e0;
            background: #fff; color: #666;
            text-decoration: none; transition: all .15s;
        }
        .chip.active { background: #752282; border-color: #752282; color: #fff; }

        /* ── Foyer notice ── */
        .foyer-notice {
            margin: 0 16px 14px;
            background: #F3E9F7; border-radius: 14px;
            padding: 11px 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .foyer-notice i { font-size: 20px; color: #752282; flex-shrink: 0; }
        .foyer-notice p { font-size: 12px; color: #5a1a6b; line-height: 1.4; margin: 0; }

        /* ── Row header ── */
        .row-header {
            padding: 0 16px 10px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .row-header span:first-child { font-size: 15px; font-weight: 600; color: #1a1a1a; }
        .row-header span:last-child  { font-size: 12px; color: #bbb; }

        /* ── CARDS GRID ── */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
            padding: 0 16px;
        }
        @media (min-width: 560px) { .cards-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 900px) { .cards-grid { grid-template-columns: repeat(4, 1fr); } }

        /* ── Single card ── */
        .item-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            text-decoration: none; color: inherit;
            display: flex; flex-direction: column;
            transition: transform .17s, box-shadow .18s;
        }
        .item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(117,34,130,.14);
        }
        .item-card:active { opacity: .88; transform: none; }

        /* ──────────────────────────────────────────────────────
           IMAGE AREA — fixed height square container
           image uses object-fit: CONTAIN so it fits fully
           without any cropping, on a soft background
        ────────────────────────────────────────────────────── */
        .card-photo {
            width: 100%;
            aspect-ratio: 1 / 1;       /* square box */
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .card-photo img {
            width: 100%;
            height: 100%;
            object-fit: contain;        /* ← CONTAIN not cover — shows full image */
            padding: 10px;              /* small padding so image doesn't touch edges */
        }
        .card-photo .icon-ph {
            font-size: 52px;
            opacity: .5;
        }

        /* Found pill */
        .found-badge {
            position: absolute; top: 8px; right: 8px;
            background: rgba(255,255,255,.92);
            border-radius: 20px; padding: 3px 9px;
            font-size: 10px; font-weight: 600; color: #1a7a4a;
            display: flex; align-items: center; gap: 3px;
        }
        .found-badge i { font-size: 11px; }

        /* Days ago */
        .days-badge {
            position: absolute; bottom: 8px; left: 8px;
            background: rgba(0,0,0,.4);
            border-radius: 20px; padding: 3px 9px;
            font-size: 10px; color: #fff;
        }

        /* Card body */
        .card-info { padding: 10px 12px 13px; }
        .card-name {
            font-size: 13px; font-weight: 600; color: #1a1a1a;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.35;
        }
        .card-loc {
            font-size: 11px; color: #aaa;
            display: flex; align-items: center; gap: 3px;
            margin-bottom: 7px;
        }
        .card-loc i { font-size: 12px; flex-shrink: 0; }
        .card-loc span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cat-pill {
            display: inline-block;
            background: #F3E9F7; color: #752282;
            font-size: 10px; font-weight: 500;
            padding: 3px 9px; border-radius: 20px;
        }

        /* ── Empty state ── */
        .empty-wrap { text-align: center; padding: 60px 20px; }
        .empty-wrap i { font-size: 56px; color: #ddd; display: block; margin-bottom: 14px; }
        .empty-wrap p { font-size: 14px; color: #bbb; }

        /* ── Bottom nav ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #fff; border-top: 1px solid #eee;
            display: flex; justify-content: space-around;
            padding: 8px 0 14px; z-index: 99;
        }
        .nav-tab {
            display: flex; flex-direction: column;
            align-items: center; gap: 2px;
            text-decoration: none; color: #ccc; font-size: 10px;
        }
        .nav-tab i { font-size: 23px; }
        .nav-tab.active { color: #752282; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div>
        <div class="topbar-sub">My School Portal</div>
        <div class="topbar-title">Lost &amp; Found</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <i class="ti ti-bell" style="font-size:22px;color:#fff;"></i>
        <div class="avatar"><?= e(getInitials($user['name'])) ?></div>
    </div>
</div>

<!-- SEARCH -->
<div class="search-wrap">
    <form method="GET" action="index.php" id="search-form">
        <div class="search-box">
            <i class="ti ti-search"></i>
            <input type="text"
                   id="search-input"
                   name="search"
                   placeholder="Search items, location…"
                   value="<?= e($search) ?>"
                   autocomplete="off">
            <?php if ($category_id > 0): ?>
                <input type="hidden" name="cat" value="<?= $category_id ?>">
            <?php endif; ?>
            <?php if ($search): ?>
                <a href="index.php<?= $category_id ? '?cat='.$category_id : '' ?>"
                   style="color:#ccc;text-decoration:none;flex-shrink:0;">
                    <i class="ti ti-x" style="font-size:18px;"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- FILTER CHIPS -->
<div class="chips-scroll">
    <a href="index.php<?= $search ? '?search='.urlencode($search) : '' ?>"
       class="chip <?= $category_id === 0 ? 'active' : '' ?>">All</a>
    <?php foreach ($cats as $cat): ?>
        <a href="?cat=<?= $cat['category_id'] ?><?= $search ? '&search='.urlencode($search) : '' ?>"
           class="chip <?= $category_id === (int)$cat['category_id'] ? 'active' : '' ?>">
            <?= e($cat['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- FOYER NOTICE -->
<div class="foyer-notice">
    <i class="ti ti-map-pin-filled"></i>
    <p>Items are held at the <strong>Security Foyer</strong>. Bring your school's student card to collect.</p>
</div>

<!-- ROW HEADER -->
<div class="row-header">
    <span>Found items</span>
    <span><?= $total ?> item<?= $total !== 1 ? 's' : '' ?></span>
</div>
<div class ="row-header" style ="padding-top:0;">

<!-- CARDS -->
<?php if (empty($items)): ?>
    <div class="empty-wrap">
        <i class="ti ti-mood-empty"></i>
        <p><?= ($search || $category_id)
            ? 'No items match your search. Try different keywords.'
            : 'No found items right now. Check back later.' ?></p>
        <?php if ($search || $category_id): ?>
            <a href="index.php" style="color:#752282;font-size:13px;text-decoration:none;">Clear filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="cards-grid">
        <?php foreach ($items as $item):
            $bg  = cardBg($item['cat_name']);
            $ico = cardIconColor($item['cat_name']);
            $days = (int) floor((time() - strtotime($item['date_found'])) / 86400);
            $days_label = $days === 0 ? 'Today' : ($days === 1 ? '1 day ago' : "{$days} days ago");
        ?>
        <a href="item.php?id=<?= (int)$item['item_id'] ?>" class="item-card">

            <!-- Image — contain so full image shows -->
            <div class="card-photo" style="background:<?= $bg ?>;">
                <?php if ($item['photo_path']): ?>
                    <img src="<?= e($item['photo_path']) ?>"
                         alt="<?= e($item['item_name']) ?>">
                <?php else: ?>
                    <i class="ti <?= e($item['icon']) ?> icon-ph"
                       style="color:<?= $ico ?>;"></i>
                <?php endif; ?>

                <div class="found-badge">
                    <i class="ti ti-circle-check"></i> Found
                </div>
                <div class="days-badge"><?= $days_label ?></div>
            </div>

            <!-- Info -->
            <div class="card-info">
                <div class="card-name"><?= e($item['item_name']) ?></div>
                <div class="card-loc">
                    <i class="ti ti-map-pin"></i>
                    <span><?= e($item['location_found']) ?></span>
                </div>
                <span class="cat-pill"><?= e($item['cat_name']) ?></span>
            </div>

        </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="height:20px;"></div>


<script>
let t;
document.getElementById('search-input').addEventListener('input', function () {
    clearTimeout(t);
    t = setTimeout(() => document.getElementById('search-form').submit(), 420);
});
</script>
</body>
</html>