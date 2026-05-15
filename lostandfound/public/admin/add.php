<?php
// ============================================================
// public/admin/add.php
// Admin adds a new found item with photo
// Admin can also add a new category inline if not in the list
// ============================================================

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';
require_once '../../app/helpers.php';

requireAdminSession();
$admin = getAdmin();

// ── Auto-create uploads folder ───────────────────────────────
$upload_dir = dirname(__DIR__) . '/assets/uploads/';
if (!is_dir($upload_dir))   mkdir($upload_dir, 0777, true);
if (!is_writable($upload_dir)) chmod($upload_dir, 0777);

// ── Fetch existing categories ────────────────────────────────
$cats = $pdo->query(
    "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC"
)->fetchAll();

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old['item_name']      = trim($_POST['item_name']      ?? '');
    $old['category_id']    = intval($_POST['category_id']  ?? 0);
    $old['new_category']   = trim($_POST['new_category']   ?? '');
    $old['description']    = trim($_POST['description']    ?? '');
    $old['location_found'] = trim($_POST['location_found'] ?? '');
    $old['date_found']     = trim($_POST['date_found']     ?? '');

    // ── Validation ───────────────────────────────────────────
    if ($old['item_name'] === '')      $errors[] = 'Item name is required.';
    if ($old['description'] === '')    $errors[] = 'Description is required.';
    if ($old['location_found'] === '') $errors[] = 'Location is required.';
    if ($old['date_found'] === '')     $errors[] = 'Date found is required.';
    if ($old['date_found'] > date('Y-m-d')) $errors[] = 'Date found cannot be in the future.';

    // ── Handle category ──────────────────────────────────────
    // If admin chose "add_new" and typed a new category name
    $final_category_id = $old['category_id'];

    if ($old['category_id'] === -1) {
        // "Add new category" was selected
        if ($old['new_category'] === '') {
            $errors[] = 'Please enter a name for the new category.';
        } else {
            // Check if category already exists (case-insensitive)
            $check = $pdo->prepare(
                "SELECT category_id FROM categories WHERE LOWER(name) = LOWER(?)"
            );
            $check->execute([$old['new_category']]);
            $existing = $check->fetch();

            if ($existing) {
                // Use the existing one instead of creating duplicate
                $final_category_id = (int) $existing['category_id'];
            } else {
                // Insert the new category
                $pdo->prepare(
                    "INSERT INTO categories (name, icon, is_active) VALUES (?, 'ti-package', 1)"
                )->execute([$old['new_category']]);
                $final_category_id = (int) $pdo->lastInsertId();

                logAction(
                    $pdo, $admin['id'],
                    'add_category', null,
                    "New category added: {$old['new_category']}"
                );
            }
        }
    } elseif ($old['category_id'] === 0) {
        $errors[] = 'Please select a category.';
    }

    // ── Photo upload ─────────────────────────────────────────
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        try {
            $photo_path = handlePhotoUpload($_FILES['photo'], $upload_dir);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    // ── Save item to DB ──────────────────────────────────────
    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO found_items
             (category_id, item_name, description, location_found, date_found, photo_path)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $final_category_id,
            $old['item_name'],
            $old['description'],
            $old['location_found'],
            $old['date_found'],
            $photo_path,
        ]);
        $new_id = (int) $pdo->lastInsertId();

        logAction($pdo, $admin['id'], 'add_item', $new_id, "Added: {$old['item_name']}");

        header('Location: dashboard.php?added=1');
        exit;
    }

    // Re-fetch categories in case a new one was partially added
    $cats = $pdo->query(
        "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC"
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Found Item — Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.4.0/dist/tabler-icons.min.css">
    <style>
        body { background: #f0f0f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .topbar { background: #1a1a2e; }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            border: 1px solid #ddd;
        }
        .form-control:focus, .form-select:focus {
            border-color: #752282;
            box-shadow: 0 0 0 3px rgba(117,34,130,0.1);
        }
        .form-label { font-size: 13px; font-weight: 600; margin-bottom: 6px; }

        /* New category inline box */
        .new-cat-box {
            display: none;
            margin-top: 10px;
            background: #F3E9F7;
            border-radius: 12px;
            padding: 14px;
            border: 1px dashed #b07cc6;
        }
        .new-cat-box.show { display: block; }
        .new-cat-box label {
            font-size: 12px; font-weight: 600;
            color: #752282; margin-bottom: 6px;
        }
        .new-cat-box .form-control {
            border-color: #c49fd8;
            background: #fff;
        }
        .new-cat-box .form-control:focus {
            border-color: #752282;
            box-shadow: 0 0 0 3px rgba(117,34,130,0.12);
        }
        .new-cat-hint {
            font-size: 11px; color: #9a5ab0; margin-top: 6px;
        }

        /* Photo */
        .photo-drop {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .photo-drop:hover { border-color: #752282; }
        .photo-preview {
            width: 100%; max-height: 220px;
            object-fit: contain;
            border-radius: 10px;
            display: none; margin-bottom: 10px;
            background: #f5f5f5; padding: 8px;
        }

        .btn-submit {
            background: #1a1a2e; color: #fff;
            border: none; border-radius: 12px;
            padding: 13px; font-size: 14px; font-weight: 600;
            width: 100%; cursor: pointer;
        }
        .btn-submit:hover { background: #2d2d4e; }
        .card { border-radius: 16px; border: none; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<nav class="navbar topbar px-3 py-2 shadow-sm">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="text-white text-decoration-none">
            <i class="ti ti-arrow-left" style="font-size:22px;"></i>
        </a>
        <span class="text-white fw-semibold" style="font-size:15px;">Add Found Item</span>
    </div>
    <span class="text-white-50 ms-auto" style="font-size:12px;">
        <?= htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8') ?>
    </span>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:500px;margin:0 auto;">

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3" style="border-radius:12px;font-size:13px;">
            <div class="fw-semibold mb-1">
                <i class="ti ti-alert-circle me-1"></i>Please fix the following:
            </div>
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm p-4">
        <form method="POST" enctype="multipart/form-data" novalidate>

            <!-- Item name -->
            <div class="mb-3">
                <label for="item_name" class="form-label">
                    Item Name <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="item_name"
                       name="item_name"
                       class="form-control"
                       placeholder="Enter item name"
                       value="<?= htmlspecialchars($old['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       maxlength="150"
                       required autofocus>
            </div>

            <!-- ── CATEGORY with add new option ─────────────── -->
            <div class="mb-3">
                <label for="category_id" class="form-label">
                    Category <span class="text-danger">*</span>
                </label>

                <select id="category_id"
                        name="category_id"
                        class="form-select"
                        onchange="handleCategoryChange(this)"
                        required>
                    <option value="">Select a category…</option>

                    <?php foreach ($cats as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>"
                            <?= (isset($old['category_id']) && $old['category_id'] === (int)$cat['category_id'])
                                ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>

                    <!-- Special option to add new -->
                    <option value="-1"
                        <?= (isset($old['category_id']) && $old['category_id'] === -1)
                            ? 'selected' : '' ?>
                        style="color:#752282;font-weight:600;">
                        ＋ Add new category…
                    </option>
                </select>

                <!-- Inline new category input — shown only when "Add new" selected -->
                <div class="new-cat-box <?= (isset($old['category_id']) && $old['category_id'] === -1) ? 'show' : '' ?>"
                     id="newCatBox">
                    <label for="new_category">
                        <i class="ti ti-tag me-1"></i>New category name
                    </label>
                    <input type="text"
                           id="new_category"
                           name="new_category"
                           class="form-control"
                           placeholder="e.g. Umbrella, Charger, Watch…"
                           value="<?= htmlspecialchars($old['new_category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           maxlength="80">
                    <p class="new-cat-hint">
                        <i class="ti ti-info-circle me-1"></i>
                        This will be saved as a new category.
                    </p>
                </div>
            </div>

            <!-- Description -->
            <div class="mb-3">
                <label for="description" class="form-label">
                    Description <span class="text-danger">*</span>
                </label>
                <textarea id="description"
                          name="description"
                          class="form-control"
                          rows="3"
                          placeholder="Colour, brand, model, any visible markings or stickers…"
                          required><?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <!-- Location found -->
            <div class="mb-3">
                <label for="location_found" class="form-label">
                    Location Found <span class="text-danger">*</span>
                </label>
                <select id="location_found" name="location_found" class="form-select" required>
                    <option value="">Select location…</option>
                    <?php foreach (getCampusLocations() as $loc): ?>
                        <option value="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>"
                            <?= (isset($old['location_found']) && $old['location_found'] === $loc)
                                ? 'selected' : '' ?>>
                            <?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date found -->
            <div class="mb-3">
                <label for="date_found" class="form-label">
                    Date Found <span class="text-danger">*</span>
                </label>
                <input type="date"
                       id="date_found"
                       name="date_found"
                       class="form-control"
                       max="<?= date('Y-m-d') ?>"
                       value="<?= htmlspecialchars($old['date_found'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                       required>
            </div>

            <!-- Photo -->
            <div class="mb-4">
                <label class="form-label">
                    Photo <span class="text-muted fw-normal">(recommended)</span>
                </label>
                <img id="photo-preview" src="" alt="Preview" class="photo-preview">
                <div class="photo-drop" onclick="document.getElementById('photo-input').click()">
                    <i class="ti ti-camera" style="font-size:32px;color:#aaa;display:block;margin-bottom:8px;"></i>
                    <span style="font-size:13px;color:#888;">Tap to take a photo or choose from library</span><br>
                    <span style="font-size:11px;color:#bbb;">JPG, PNG, WEBP — max 5 MB</span>
                </div>
                <input type="file"
                       id="photo-input"
                       name="photo"
                       accept="image/jpeg,image/png,image/webp"
                       class="d-none"
                       onchange="previewPhoto(this)">
            </div>

            <button type="submit" class="btn-submit">
                <i class="ti ti-plus me-2"></i>Add Found Item
            </button>

        </form>
    </div>

</div>

<script>
// Show / hide the new category input box
function handleCategoryChange(select) {
    const box   = document.getElementById('newCatBox');
    const input = document.getElementById('new_category');

    if (select.value === '-1') {
        box.classList.add('show');
        input.required = true;
        input.focus();
    } else {
        box.classList.remove('show');
        input.required = false;
        input.value    = '';
    }
}

// Photo preview
function previewPhoto(input) {
    const preview = document.getElementById('photo-preview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src          = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
