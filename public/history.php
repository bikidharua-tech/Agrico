<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user = current_user();
$isAdmin = $user['role'] === 'admin';
$order = ($_GET['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
ensure_predictions_schema();

function history_delete_uploaded_image_if_unused(string $imagePath): void {
    $normalizedPath = resolve_public_path($imagePath);
    if ($normalizedPath === '') {
        return;
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM predictions WHERE image_path = ? OR image_path = ?');
    $countStmt->execute([$imagePath, $normalizedPath]);
    $remaining = (int)$countStmt->fetchColumn();
    if ($remaining > 0) {
        return;
    }

    $filePath = public_file_path($normalizedPath);
    $realPublicRoot = realpath(public_root());
    $realDir = realpath(dirname($filePath));
    if ($realPublicRoot === false || $realDir === false) {
        return;
    }

    $realDir = rtrim(str_replace('\\', '/', $realDir), '/');
    $realPublicRoot = rtrim(str_replace('\\', '/', $realPublicRoot), '/');
    if (strpos($realDir, $realPublicRoot . '/uploads/diagnosis') !== 0) {
        return;
    }

    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $action = (string)($_POST['action'] ?? '');
    $sortQuery = ($_POST['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

    if ($action === 'delete_one') {
        $predictionId = isset($_POST['prediction_id']) ? (int)$_POST['prediction_id'] : 0;
        if ($predictionId <= 0) {
            set_flash('error', t('history.flash_invalid'));
            redirect('history.php?sort=' . $sortQuery);
        }

        if ($isAdmin) {
            $stmt = db()->prepare('SELECT id, image_path FROM predictions WHERE id = ? LIMIT 1');
            $stmt->execute([$predictionId]);
        } else {
            $stmt = db()->prepare('SELECT id, image_path FROM predictions WHERE id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$predictionId, $user['id']]);
        }

        $prediction = $stmt->fetch();
        if (!$prediction) {
            set_flash('error', t('history.flash_not_found'));
            redirect('history.php?sort=' . $sortQuery);
        }

        db()->prepare('DELETE FROM predictions WHERE id = ?')->execute([$predictionId]);
        history_delete_uploaded_image_if_unused((string)$prediction['image_path']);
        set_flash('success', t('history.flash_deleted_one'));
        redirect('history.php?sort=' . $sortQuery);
    }

    if ($action === 'clear_all') {
        if ($isAdmin) {
            $rowsToDelete = db()->query('SELECT image_path FROM predictions')->fetchAll();
            db()->exec('DELETE FROM predictions');
        } else {
            $rowsStmt = db()->prepare('SELECT image_path FROM predictions WHERE user_id = ?');
            $rowsStmt->execute([$user['id']]);
            $rowsToDelete = $rowsStmt->fetchAll();

            $deleteStmt = db()->prepare('DELETE FROM predictions WHERE user_id = ?');
            $deleteStmt->execute([$user['id']]);
        }

        foreach ($rowsToDelete as $rowToDelete) {
            history_delete_uploaded_image_if_unused((string)($rowToDelete['image_path'] ?? ''));
        }

        set_flash('success', t('history.flash_cleared'));
        redirect('history.php?sort=' . $sortQuery);
    }

    set_flash('error', t('history.flash_invalid'));
    redirect('history.php?sort=' . $sortQuery);
}

if ($isAdmin) {
    $stmt = db()->query("SELECT p.*, u.name AS user_name FROM predictions p JOIN users u ON u.id = p.user_id ORDER BY p.created_at $order");
} else {
    $stmt = db()->prepare("SELECT p.*, u.name AS user_name FROM predictions p JOIN users u ON u.id = p.user_id WHERE p.user_id = ? ORDER BY p.created_at $order");
    $stmt->execute([$user['id']]);
}
$rows = $stmt->fetchAll();
$flash = get_flash();
$title = $isAdmin ? t('history.title_all') : t('history.title_my');
require __DIR__ . '/../app/views/header.php';
?>
<section class="container section">
<div class="card">
<h2><?= e($isAdmin ? t('history.title_all') : t('history.title_my')) ?></h2>
<?php if ($flash): ?><div class="notice <?= ($flash['type'] ?? '') === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="history-toolbar">
<a class="btn" href="?sort=desc" style="border:1px solid #cde3db"><?= e(t('history.newest')) ?></a>
<a class="btn" href="?sort=asc" style="border:1px solid #cde3db"><?= e(t('history.oldest')) ?></a>
<?php if (!empty($rows)): ?>
<form method="post" class="history-inline-form" onsubmit="return confirm('<?= e(t('history.confirm_clear_all')) ?>');">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="clear_all">
<input type="hidden" name="sort" value="<?= e(strtolower($order)) ?>">
<button class="btn history-danger-btn" type="submit"><?= e(t('history.clear_all')) ?></button>
</form>
<?php endif; ?>
</div>
<div class="table-wrap">
<table class="table history-table">
<thead><tr><th><?= e(t('history.col_date')) ?></th><th><?= e(t('history.col_user')) ?></th><th><?= e(t('history.col_disease')) ?></th><th><?= e(t('history.col_conf')) ?></th><th><?= e(t('history.col_image')) ?></th><th><?= e(t('history.col_location')) ?></th><th><?= e(t('history.col_actions')) ?></th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['created_at']) ?></td>
<td><?= e($r['user_name']) ?></td>
<td>
    <?= e($r['disease_name']) ?>
    <?php
      $modelSource = (string)($r['model_source'] ?? '');
      $modelLabel = t('history.model_unknown');
      $modelClass = 'is-unknown';
      if (stripos($modelSource, 'plantid') !== false) { $modelLabel = t('history.model_plantid'); $modelClass = 'is-plantid'; }
      elseif (stripos($modelSource, 'gemini_vision') !== false) { $modelLabel = t('history.model_gemini_vision'); $modelClass = 'is-gemini'; }
      elseif (stripos($modelSource, 'plantvillage') !== false) { $modelLabel = t('history.model_local_cnn'); $modelClass = 'is-local'; }
      elseif (stripos($modelSource, 'local_diagnosis') !== false || stripos($modelSource, 'php_fallback_basic') !== false || stripos($modelSource, 'heuristic') !== false) { $modelLabel = t('history.model_local_diagnosis'); $modelClass = 'is-heuristic'; }
      if (stripos($modelSource, 'gemini_advice') !== false) { $modelLabel .= ' + ' . t('history.model_gemini'); }
    ?>
    <span class="model-source-badge <?= e($modelClass) ?>"><?= e($modelLabel) ?></span>
</td>
<td><?= e((string)$r['confidence']) ?>%</td>
<td><img src="<?= e(public_url($r['image_path'])) ?>" alt="<?= e(t('history.image_alt')) ?>" style="height:56px;border-radius:8px"></td>
<td><?= $r['latitude'] ? e($r['latitude'] . ', ' . $r['longitude']) : e(t('history.na')) ?></td>
<td>
    <form method="post" class="history-inline-form" onsubmit="return confirm('<?= e(t('history.confirm_delete_one')) ?>');">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_one">
        <input type="hidden" name="prediction_id" value="<?= e((string)$r['id']) ?>">
        <input type="hidden" name="sort" value="<?= e(strtolower($order)) ?>">
        <button class="btn history-danger-btn" type="submit"><?= e(t('history.delete_one')) ?></button>
    </form>
</td>
</tr>
<tr><td colspan="7"><strong><?= e(t('history.desc')) ?>:</strong> <?= e($r['disease_description'] ?? t('history.na')) ?><br><strong><?= e(t('history.treat')) ?>:</strong> <?= e($r['treatment_recommendation'] ?? t('history.na')) ?></td></tr>
<?php endforeach; ?>
<?php if (empty($rows)): ?>
<tr><td colspan="7"><?= e(t('history.empty')) ?></td></tr>
<?php endif; ?>
</tbody></table>
</div>
</div></section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>

