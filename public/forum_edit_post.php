<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user = current_user();

$postId = (int)($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
if (!$postId) {
    redirect('forum.php');
}

$stmt = db()->prepare('SELECT * FROM forum_posts WHERE id = ? LIMIT 1');
$stmt->execute([$postId]);
$post = $stmt->fetch();
if (!$post) {
    redirect('forum.php');
}

$canEdit = ((int)$post['user_id'] === (int)$user['id']) || ($user['role'] === 'admin');
if (!$canEdit) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $plant = trim($_POST['plant_name'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    if ($title === '' || $content === '') {
        set_flash('error', t('forum.title_required'));
        redirect('forum_edit_post.php?post_id=' . $postId);
    }
    db()->prepare('UPDATE forum_posts SET title = ?, content = ?, plant_name = ?, tags = ? WHERE id = ?')
        ->execute([$title, $content, $plant ?: null, $tags ?: null, $postId]);
    set_flash('success', t('forum.post_updated'));
    redirect('forum.php?post=' . $postId);
}

$flash = get_flash();
$title = t('forum.edit_title');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section">
    <?php if ($flash): ?>
        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="card forum-form">
        <div class="forum-form-head">
            <h2 style="margin:0"><?= e(t('forum.edit_title')) ?></h2>
            <a class="btn btn-ghost" href="forum.php?post=<?= (int)$postId ?>"><?= e(t('auth.back')) ?></a>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int)$postId ?>">
            <label><?= e(t('forum.title_label')) ?>*<input class="input" name="title" value="<?= e($post['title']) ?>" required></label>
            <label><?= e(t('forum.content_label')) ?>*<textarea name="content" required><?= e($post['content']) ?></textarea></label>
            <div class="grid-2">
                <div><label><?= e(t('forum.plant_label')) ?><input class="input" name="plant_name" value="<?= e($post['plant_name'] ?? '') ?>"></label></div>
                <div><label><?= e(t('forum.tags_label')) ?><input class="input" name="tags" value="<?= e($post['tags'] ?? '') ?>"></label></div>
            </div>
            <button class="btn btn-grad" type="submit"><?= e(t('common.save')) ?></button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
