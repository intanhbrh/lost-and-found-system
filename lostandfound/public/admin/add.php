<?php
// ── public/admin/add.php ────────────────────────────────────
// Admin adds a new found item with photo

require_once '../../app/admin_auth.php';
require_once '../../app/db.php';

requireAdminSession();
$admin = getAdmin();

$errors = [];
$success = false;

// Get categories
$cats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name      = trim($_POST['item_name']      ?? '');
    $category_id    = intval($_POST['category_id']  ?? 0);
    $description    = trim($_POST['description']    ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found     = trim($_POST['date_found']     ?? '');

    // Validate
    if (!$item_name)      $errors[] = "Item name is required.";
    if (!$category_id)    $errors[] = "Please select a category.";
    if (!$description)    $errors[] = "Description is required.";
    if (!$location_found) $errors[] = "Location is required.";
    if (!$date_found)     $errors[] = "Date found is required.";

    // Handle photo upload
    $photo_path = null;
    if (!empty($_FILES['photo']['name'])) {
        $allowed   = ['image/jpeg','image/png','image/webp'];
        $max_size  = 5 * 1024 * 1024; // 5MB
        $file_type = mime_content_type($_FILES['photo']['tmp_name']);
        $file_size = $_FILES['photo']['size'];

        if (!in_array($file_type, $allowed)) {
            $errors[] = "Photo must be JPG, PNG, or WEBP.";
        } elseif ($file_size > $max_size) {
            $errors[] = "Photo must be under 5MB.";
        } else {
            $ext        = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename   = 'item_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $upload_dir = '../assets/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
            $photo_path = 'assets/uploads/' . $filename;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO found_items
             (category_id, item_name, description, location_found, date_found, photo_path)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$category_id, $item_name, $description, $location_found, $date_found, $photo_path]);
        $new_id = $pdo->lastInsertId();

        // Log
        $pdo->prepare("INSERT INTO audit_log (admin_id, action, item_id, note) VALUES (?,?,?,?)")
            ->execute([$admin['id'], 'add_item', $new_id, "Added: $item_name"]);

        header("Location: dashboard.php?added=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Found Item — Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
</head>
<body style="background:#f4f4f8;">

<nav class="navbar navbar-dark px-3" style="background:#752282;">
    <a href="dashboard.php" class="btn btn-sm text-white p-0 me-2">
        <i class="ti ti-arrow-left" style="font-size:20px"></i>
    </a>
    <span class="navbar-brand mb-0">Add Found Item</span>
</nav>

<div class="container-fluid px-3 py-3" style="max-width:500px;margin:0 auto;">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger small py-2">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card border shadow-sm">
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Item name <span class="text-danger">*</span></label>
                    <input type="text"
                           name="item_name"
                           class="form-control"
                           placeholder="e.g. iPad, Black Backpack, Student ID"
                           value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>"
                           required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select category…</option>
                        <?php foreach ($cats as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>"
                                <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="description"
                              class="form-control"
                              rows="3"
                              placeholder="Colour, brand, any visible markings…"
                              required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Location found <span class="text-danger">*</span></label>
                    <select name="location_found" class="form-select" required>
                        <option value="">Select location…</option>
                        <?php
                        $locations = ['Library','Canteen','Block A','Block B','Block C',
                                      'Sports Hall','Car Park','Foyer','Classroom','Toilet','Other'];
                        foreach ($locations as $loc):
                        ?>
                            <option value="<?= $loc ?>"
                                <?= (isset($_POST['location_found']) && $_POST['location_found'] === $loc) ? 'selected' : '' ?>>
                                <?= $loc ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Date found <span class="text-danger">*</span></label>
                    <input type="date"
                           name="date_found"
                           class="form-control"
                           max="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($_POST['date_found'] ?? date('Y-m-d')) ?>"
                           required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold">Photo</label>
                    <input type="file"
                           name="photo"
                           class="form-control"
                           accept="image/jpeg,image/png,image/webp">
                    <div class="form-text small">JPG, PNG or WEBP — max 5MB. Recommended.</div>
                </div>

                <button type="submit"
                        class="btn w-100 fw-semibold"
                        style="background:#752282;color:#fff;">
                    <i class="ti ti-plus me-1"></i> Add found item
                </button>

            </form>
        </div>
    </div>

</div>
</body>
</html>