<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user = current_user();

$commentId = (int)($_GET['comment_id'] ?? $_POST['comment_id'] ?? 0);
$postId = (int)($_GET['post_id'] ?? $_POST['post_id'] ?? 0);
if (!$commentId) {
    redirect('forum.php');
}

$stmt = db()->prepare('SELECT * FROM forum_comments WHERE id = ? LIMIT 1');
$stmt->execute([$commentId]);
$comment = $stmt->fetch();
if (!$comment) {
    redirect('forum.php');
}

$canEdit = ((int)$comment['user_id'] === (int)$user['id']) || ($user['role'] === 'admin');
if (!$canEdit) {
    http_response_code(403);
    exit('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $content = trim($_POST['content'] ?? '');
    if ($content === '') {
        set_flash('error', t('forum.comment_empty'));
        redirect('forum_edit_comment.php?comment_id=' . $commentId . '&post_id=' . (int)$comment['post_id']);
    }
    db()->prepare('UPDATE forum_comments SET content = ? WHERE id = ?')->execute([$content, $commentId]);
    set_flash('success', t('forum.comment_updated'));
    redirect('forum.php?post=' . (int)$comment['post_id']);
}

$flash = get_flash();
$title = t('forum.edit_comment');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section">
    <?php if ($flash): ?>
        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <div class="card forum-form">
        <div class="forum-form-head">
            <h2 style="margin:0"><?= e(t('forum.edit_comment')) ?></h2>
            <a class="btn btn-ghost" href="forum.php?post=<?= (int)$comment['post_id'] ?>"><?= e(t('auth.back')) ?></a>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="comment_id" value="<?= (int)$commentId ?>">
            <label><?= e(t('forum.edit_comment')) ?><textarea name="content" required><?= e($comment['content']) ?></textarea></label>
            <button class="btn btn-grad" type="submit"><?= e(t('common.save')) ?></button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
