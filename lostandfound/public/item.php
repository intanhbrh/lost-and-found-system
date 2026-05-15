<?php
// ============================================================
// public/item.php — Item detail, image fits fully (contain)
// ============================================================

require_once '../app/auth.php';
require_once '../app/db.php';
require_once '../app/helpers.php';

requireMSPSession();
$user = getCurrentUser();

$id = intval($_GET['id'] ?? 0);
if ($id === 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT fi.*, c.name AS cat_name, c.icon
     FROM found_items fi
     JOIN categories c ON fi.category_id = c.category_id
     WHERE fi.item_id = ? AND fi.status = 'available'"
);
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { header('Location: index.php'); exit; }

$days = (int) floor((time() - strtotime($item['date_found'])) / 86400);
$days_label = $days === 0 ? 'Today' : ($days === 1 ? '1 day ago' : "{$days} days ago");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($item['item_name']) ?> — Lost &amp; Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #F5F5F7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            padding-bottom: 90px;
        }

        /* ── Hero image box ── */
        .hero-box {
            width: 100%;
            background: #EEEDFE;
            display: flex; align-items: center; justify-content: center;
            position: relative;
            /* No fixed aspect ratio — grows with image height */
            min-height: 220px;
            max-height: 380px;
            overflow: hidden;
        }
        .hero-box img {
            width: 100%;
            height: auto;
            max-height: 380px;
            object-fit: contain;     /* ← full image, no cropping */
            padding: 16px;
            display: block;
        }
        .hero-box .hero-icon {
            font-size: 110px;
            opacity: .45;
            color: #534AB7;
            padding: 40px 0;
        }

        /* Floating buttons over hero */
        .back-btn {
            position: absolute; top: 14px; left: 14px;
            width: 38px; height: 38px;
            background: rgba(255,255,255,.88);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
            z-index: 2;
        }
        .back-btn i { font-size: 20px; }

        .hero-found-badge {
            position: absolute; top: 14px; right: 14px;
            background: rgba(255,255,255,.92);
            border-radius: 20px; padding: 5px 12px;
            font-size: 11px; font-weight: 600; color: #1a7a4a;
            display: flex; align-items: center; gap: 4px;
            z-index: 2;
        }
        .hero-found-badge i { font-size: 13px; }

        .hero-days {
            position: absolute; bottom: 12px; left: 14px;
            background: rgba(0,0,0,.42);
            border-radius: 20px; padding: 4px 11px;
            font-size: 11px; color: #fff;
            z-index: 2;
        }

        /* ── Content ── */
        .content {
            padding: 20px 18px 0;
            max-width: 640px;
            margin: 0 auto;
        }

        .cat-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: #F3E9F7; color: #752282;
            font-size: 12px; font-weight: 500;
            padding: 5px 12px; border-radius: 20px;
            margin-bottom: 10px;
        }

        .item-name {
            font-size: 22px; font-weight: 700;
            color: #1a1a1a; line-height: 1.2;
            margin-bottom: 12px;
        }

        /* Meta pills row */
        .meta-row {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-bottom: 18px;
        }
        .meta-pill {
            display: inline-flex; align-items: center; gap: 5px;
            background: #fff; border-radius: 20px;
            padding: 6px 12px;
            font-size: 12px; color: #555;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .meta-pill i { font-size: 14px; color: #752282; }

        /* Description */
        .desc-card {
            background: #fff; border-radius: 18px;
            padding: 16px 18px; margin-bottom: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }
        .desc-card h6 {
            font-size: 11px; font-weight: 600; color: #aaa;
            letter-spacing: .05em; text-transform: uppercase;
            margin-bottom: 8px;
        }
        .desc-card p { font-size: 14px; color: #333; line-height: 1.65; }

        /* CTA collect card */
        .collect-card {
            background: #752282; border-radius: 18px;
            padding: 18px;
            display: flex; align-items: flex-start; gap: 14px;
            margin-bottom: 16px;
            box-shadow: 0 4px 18px rgba(117,34,130,.22);
        }
        .collect-card i { font-size: 28px; color: #fff; flex-shrink: 0; margin-top: 2px; }
        .collect-card h5 { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 4px; }
        .collect-card p  { font-size: 13px; color: rgba(255,255,255,.8); line-height: 1.5; margin: 0; }

        /* Back button */
        .btn-back {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
            background: #fff; color: #752282;
            border: 2px solid #752282;
            border-radius: 14px; padding: 13px;
            font-size: 14px; font-weight: 600;
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .btn-back:hover { background: #752282; color: #fff; }

        /* Bottom nav */
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

<!-- HERO IMAGE — fits fully, no crop -->
<div class="hero-box">
    <?php if ($item['photo_path']): ?>
        <img src="<?= e($item['photo_path']) ?>"
             alt="<?= e($item['item_name']) ?>">
    <?php else: ?>
        <i class="ti <?= e($item['icon']) ?> hero-icon"></i>
    <?php endif; ?>

    <a href="index.php" class="back-btn">
        <i class="ti ti-arrow-left"></i>
    </a>
    <div class="hero-found-badge">
        <i class="ti ti-circle-check"></i> Found
    </div>
    <div class="hero-days"><?= $days_label ?></div>
</div>

<!-- CONTENT -->
<div class="content">

    <div class="cat-pill">
        <i class="ti <?= e($item['icon']) ?>"></i>
        <?= e($item['cat_name']) ?>
    </div>

    <div class="item-name"><?= e($item['item_name']) ?></div>

    <div class="meta-row">
        <div class="meta-pill">
            <i class="ti ti-map-pin"></i>
            Found at
            <?= e($item['location_found']) ?>
        </div>
        <div class="meta-pill">
            <i class="ti ti-calendar"></i>
            <?= formatDate($item['date_found']) ?>
        </div>
        <div class="meta-pill">
            <i class="ti ti-clock"></i>
            Reported <?= formatDate($item['created_at']) ?>
        </div>
    </div> 

    <div class="desc-card">
        <h6>Description</h6>
        <p><?= nl2br(e($item['description'])) ?></p>
    </div>

    <div class="collect-card">
        <i class="ti ti-map-pin-filled"></i>
        <div>
            <h5>Is this yours?</h5>
            <p>
                Visit the <strong>Security Foyer</strong> to collect this item.<br>
                Bring your <strong>school ID card</strong> as proof of identity.
            </p>
        </div>
    </div>

    <a href="index.php" class="btn-back">
        <i class="ti ti-arrow-left"></i> Back to found items
    </a>

</div>


</body>
</html>