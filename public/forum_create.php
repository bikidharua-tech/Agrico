<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $plant = trim($_POST['plant_name'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    if ($title === '' || $content === '') {
        set_flash('error', t('forum.title_required'));
        redirect('forum_create.php');
    }

    $stmt = db()->prepare('INSERT INTO forum_posts (user_id, title, content, plant_name, tags) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $title, $content, $plant ?: null, $tags ?: null]);
    $postId = (int)db()->lastInsertId();

    if (!empty($_FILES['images']['name'][0])) {
        if (count($_FILES['images']['name']) > 5) {
            set_flash('error', t('forum.max_images'));
            redirect('forum_create.php');
        }
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            $type = $_FILES['images']['type'][$i] ?? '';
            $size = (int)($_FILES['images']['size'][$i] ?? 0);
            if (!in_array($type, ['image/jpeg', 'image/png'], true) || $size > 5 * 1024 * 1024) {
                continue;
            }
            $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $fileName = uniqid('forum_', true) . '.' . strtolower($ext);
            $relPath = 'uploads/forum/' . $fileName;
            $dest = public_file_path($relPath);
            $dir = dirname($dest);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $dest)) {
                db()->prepare('INSERT INTO forum_images (post_id, image_path) VALUES (?, ?)')->execute([$postId, $relPath]);
            }
        }
    }

    set_flash('success', t('forum.post_created'));
    redirect('forum.php?post=' . $postId);
}

$flash = get_flash();
$title = t('forum.create_title');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section">
    <?php if ($flash): ?>
        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="card forum-form">
        <div class="forum-form-head">
            <h2 style="margin:0"><?= e(t('forum.create_title')) ?></h2>
            <a class="btn btn-ghost" href="forum.php"><?= e(t('common.back_to')) ?> <?= e(t('forum.title')) ?></a>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label><?= e(t('forum.title_label')) ?>*<input class="input" name="title" required></label>
            <label><?= e(t('forum.content_label')) ?>*<textarea name="content" required></textarea></label>
            <div class="grid-2">
                <div><label><?= e(t('forum.plant_label')) ?><input class="input" name="plant_name"></label></div>
                <div><label><?= e(t('forum.tags_label')) ?><input class="input" name="tags" placeholder="fungus, tomato"></label></div>
            </div>
            <label><?= e(t('forum.images_label')) ?><input class="input" type="file" name="images[]" multiple accept="image/jpeg,image/png"></label>
            <button class="btn btn-grad" type="submit"><?= e(t('forum.publish')) ?></button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
