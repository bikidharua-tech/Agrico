<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
require_admin();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_user') {
        $uid = (int)$_POST['user_id'];
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        db()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$status, $uid]);
        admin_log($user['id'], 'toggle_user', 'user', $uid, 'Set status: ' . $status);
    }
    if ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        db()->prepare('DELETE FROM users WHERE id = ? AND role <> \'admin\'')->execute([$uid]);
        admin_log($user['id'], 'delete_user', 'user', $uid, 'Deleted user');
    }
    if ($action === 'delete_post') {
        $pid = (int)$_POST['post_id'];
        $imgs = db()->prepare('SELECT image_path FROM forum_images WHERE post_id = ?');
        $imgs->execute([$pid]);
        foreach ($imgs->fetchAll() as $img) {
            $path = public_file_path($img['image_path']);
            if (is_file($path)) unlink($path);
        }
        db()->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$pid]);
        admin_log($user['id'], 'delete_post', 'forum_post', $pid, 'Admin moderation delete');
    }
    if ($action === 'delete_image') {
        $iid = (int)$_POST['image_id'];
        $imgStmt = db()->prepare('SELECT image_path FROM forum_images WHERE id = ?');
        $imgStmt->execute([$iid]);
        $img = $imgStmt->fetch();
        if ($img) {
            $path = public_file_path($img['image_path']);
            if (is_file($path)) unlink($path);
            db()->prepare('DELETE FROM forum_images WHERE id = ?')->execute([$iid]);
            admin_log($user['id'], 'delete_image', 'forum_image', $iid, 'Admin moderation delete');
        }
    }
    if ($action === 'delete_comment') {
        $cid = (int)$_POST['comment_id'];
        db()->prepare('DELETE FROM forum_comments WHERE id = ?')->execute([$cid]);
        admin_log($user['id'], 'delete_comment', 'forum_comment', $cid, 'Admin moderation delete');
    }
    redirect('admin.php');
}

$users = db()->query('SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$posts = db()->query('SELECT p.id, p.title, p.created_at, u.name FROM forum_posts p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 20')->fetchAll();
$images = db()->query('SELECT fi.id, fi.image_path, fi.post_id, fp.title FROM forum_images fi JOIN forum_posts fp ON fp.id = fi.post_id ORDER BY fi.created_at DESC LIMIT 30')->fetchAll();
$comments = db()->query('SELECT c.id, c.content, c.created_at, u.name FROM forum_comments c JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC LIMIT 20')->fetchAll();
$predictions = db()->query('SELECT p.id, p.disease_name, p.confidence, p.created_at, u.name FROM predictions p JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC LIMIT 30')->fetchAll();
$title = t('admin.title');
require __DIR__ . '/../app/views/header.php';
?>
<section class="container section">
<div class="card"><h2><?= e(t('admin.dashboard')) ?></h2></div>
<div class="grid-2 section">
<div class="card"><h3><?= e(t('admin.manage_users')) ?></h3>
<table class="table"><thead><tr><th><?= e(t('admin.user')) ?></th><th><?= e(t('admin.role')) ?></th><th><?= e(t('admin.status')) ?></th><th><?= e(t('admin.action')) ?></th></tr></thead><tbody>
<?php foreach ($users as $u): ?>
<tr>
<td><?= e($u['name']) ?><br><small><?= e($u['email']) ?></small></td>
<td><?= e($u['role']) ?></td><td><?= e($u['status']) ?></td>
<td>
<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="vote"><?= $u['status'] === 'active' ? e(t('admin.deactivate')) : e(t('admin.activate')) ?></button></form>
<?php if ($u['role'] !== 'admin'): ?><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><button class="vote" data-confirm="<?= e(t('admin.confirm_delete_user')) ?>"><?= e(t('common.delete')) ?></button></form><?php endif; ?>
</td>
</tr>
<?php endforeach; ?></tbody></table></div>

<div class="card"><h3><?= e(t('admin.recent_predictions')) ?></h3>
<table class="table"><thead><tr><th><?= e(t('admin.user')) ?></th><th><?= e(t('admin.disease')) ?></th><th><?= e(t('admin.confidence')) ?></th><th><?= e(t('admin.date')) ?></th></tr></thead><tbody>
<?php foreach ($predictions as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['disease_name']) ?></td><td><?= e((string)$p['confidence']) ?>%</td><td><?= e($p['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>

<div class="grid-2 section">
<div class="card"><h3><?= e(t('admin.moderate_posts')) ?></h3>
<?php foreach ($posts as $p): ?>
<div><strong><?= e($p['title']) ?></strong> <small>(<?= e($p['name']) ?>)</small>
<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_post"><input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>"><button class="vote" data-confirm="<?= e(t('admin.confirm_delete_post')) ?>"><?= e(t('common.delete')) ?></button></form></div>
<?php endforeach; ?></div>
<div class="card"><h3><?= e(t('admin.moderate_comments')) ?></h3>
<?php foreach ($comments as $c): ?>
<div><span><?= e(substr($c['content'], 0, 80)) ?>...</span> <small>(<?= e($c['name']) ?>)</small>
<form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_comment"><input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>"><button class="vote" data-confirm="<?= e(t('admin.confirm_delete_comment')) ?>"><?= e(t('common.delete')) ?></button></form></div>
<?php endforeach; ?></div>
</div>

<div class="card section"><h3><?= e(t('admin.moderate_images')) ?></h3>
<div class="post-image-grid">
<?php foreach ($images as $img): ?>
<div>
<img src="<?= e(public_url($img['image_path'])) ?>" alt="forum image">
<small><?= e($img['title']) ?></small>
<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>"><button class="vote" data-confirm="<?= e(t('admin.confirm_delete_image')) ?>"><?= e(t('admin.delete_image')) ?></button></form>
</div>
<?php endforeach; ?>
</div>
</div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
