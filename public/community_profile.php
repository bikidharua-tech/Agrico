<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$currentUser = current_user();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function ensure_forum_post_likes_table(): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS forum_post_likes (
            post_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id, user_id),
            CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
            CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_post_likes_post (post_id),
            INDEX idx_post_likes_user (user_id)
        )'
    );

    $ensured = true;
}

function ensure_user_follows_table(): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS user_follows (
            follower_user_id BIGINT UNSIGNED NOT NULL,
            followed_user_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (follower_user_id, followed_user_id),
            CONSTRAINT fk_user_follows_follower FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_follows_followed FOREIGN KEY (followed_user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_follows_followed (followed_user_id)
        )'
    );

    $ensured = true;
}

function forum_avatar_markup(array $person, string $class = 'forum-avatar'): string {
    $name = trim((string)($person['name'] ?? ''));
    $initial = strtoupper(substr($name, 0, 1) ?: 'A');
    $avatarPath = trim((string)($person['avatar_path'] ?? ''));
    $classes = trim($class);

    if ($avatarPath !== '') {
        $src = e(public_url($avatarPath));
        $alt = e($name !== '' ? $name : 'User avatar');
        return '<div class="' . e($classes) . ' forum-avatar-has-image"><span class="forum-avatar-letter">' . e($initial) . '</span><img src="' . $src . '" alt="' . $alt . '" onerror="this.remove();this.parentNode.classList.remove(\'forum-avatar-has-image\');this.parentNode.classList.add(\'forum-avatar-fallback\');"></div>';
    }

    return '<div class="' . e($classes) . ' forum-avatar-fallback"><span class="forum-avatar-letter">' . e($initial) . '</span></div>';
}

ensure_user_profile_schema();
ensure_user_follows_table();
ensure_forum_post_likes_table();

$profileUserId = (int)($_GET['user_id'] ?? $currentUser['id']);
if ($profileUserId <= 0) {
    $profileUserId = (int)$currentUser['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';
    $redirectUserId = (int)($_POST['profile_user_id'] ?? $profileUserId);
    $redirectPath = 'community_profile.php?user_id=' . $redirectUserId;

    if ($action === 'update_profile' && $redirectUserId === (int)$currentUser['id']) {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatarPath = (string)($currentUser['avatar_path'] ?? '');

        if ($name === '') {
            set_flash('error', 'Name is required.');
            redirect($redirectPath);
        }

        if (!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $imageInfo = @getimagesize($_FILES['avatar']['tmp_name']);
            $ext = strtolower((string)pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (!$imageInfo || !in_array($ext, $allowedExtensions, true)) {
                set_flash('error', 'Please upload a JPG, PNG, or WEBP image.');
                redirect($redirectPath);
            }

            $uploadDir = public_root() . '/uploads/avatars';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $fileName = 'avatar_' . (int)$currentUser['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                set_flash('error', 'Unable to upload profile photo.');
                redirect($redirectPath);
            }

            if ($avatarPath !== '') {
                $oldAvatar = public_file_path($avatarPath);
                if (is_file($oldAvatar)) {
                    unlink($oldAvatar);
                }
            }

            $avatarPath = 'uploads/avatars/' . $fileName;
        }

        db()->prepare('UPDATE users SET name = ?, bio = ?, avatar_path = ? WHERE id = ?')
            ->execute([$name, $bio !== '' ? $bio : null, $avatarPath !== '' ? $avatarPath : null, $currentUser['id']]);

        set_flash('success', 'Profile updated.');
        redirect($redirectPath);
    }

    if ($action === 'toggle_follow') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId > 0 && $targetUserId !== (int)$currentUser['id']) {
            $existsStmt = db()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $existsStmt->execute([$targetUserId]);

            if ($existsStmt->fetch()) {
                $followStmt = db()->prepare('SELECT 1 FROM user_follows WHERE follower_user_id = ? AND followed_user_id = ? LIMIT 1');
                $followStmt->execute([$currentUser['id'], $targetUserId]);

                if ($followStmt->fetch()) {
                    db()->prepare('DELETE FROM user_follows WHERE follower_user_id = ? AND followed_user_id = ?')
                        ->execute([$currentUser['id'], $targetUserId]);
                } else {
                    db()->prepare('INSERT INTO user_follows (follower_user_id, followed_user_id) VALUES (?, ?)')
                        ->execute([$currentUser['id'], $targetUserId]);
                }
            }
        }

        redirect($redirectPath);
    }
}

$profileStmt = db()->prepare(
    'SELECT
        u.id,
        u.name,
        u.email,
        u.avatar_path,
        u.bio,
        u.created_at,
        (SELECT COUNT(*) FROM forum_posts fp WHERE fp.user_id = u.id) AS post_count,
        (SELECT COUNT(*) FROM user_follows uf WHERE uf.followed_user_id = u.id) AS follower_count,
        (SELECT COUNT(*) FROM user_follows uf WHERE uf.follower_user_id = u.id) AS following_count,
        EXISTS(
            SELECT 1 FROM user_follows uf
            WHERE uf.followed_user_id = u.id AND uf.follower_user_id = ?
        ) AS followed_by_me
     FROM users u
     WHERE u.id = ?
     LIMIT 1'
);
$profileStmt->execute([$currentUser['id'], $profileUserId]);
$profileUser = $profileStmt->fetch();

if (!$profileUser) {
    set_flash('error', 'Profile not found.');
    redirect('forum.php');
}

$isOwnProfile = ((int)$profileUser['id'] === (int)$currentUser['id']);
$flash = get_flash();

$followersStmt = db()->prepare(
    'SELECT u.id, u.name, u.avatar_path, u.bio, uf.created_at AS followed_at
     FROM user_follows uf
     JOIN users u ON u.id = uf.follower_user_id
     WHERE uf.followed_user_id = ?
     ORDER BY uf.created_at DESC
     LIMIT 24'
);
$followersStmt->execute([$profileUser['id']]);
$followers = $followersStmt->fetchAll();

$followingStmt = db()->prepare(
    'SELECT u.id, u.name, u.avatar_path, u.bio, uf.created_at AS followed_at
     FROM user_follows uf
     JOIN users u ON u.id = uf.followed_user_id
     WHERE uf.follower_user_id = ?
     ORDER BY uf.created_at DESC
     LIMIT 24'
);
$followingStmt->execute([$profileUser['id']]);
$following = $followingStmt->fetchAll();

$postsStmt = db()->prepare(
    'SELECT
        p.*,
        (SELECT image_path FROM forum_images fi WHERE fi.post_id = p.id ORDER BY fi.id ASC LIMIT 1) AS cover_image,
        (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id = p.id) AS comment_count,
        (SELECT COUNT(*) FROM forum_post_likes pl WHERE pl.post_id = p.id) AS like_count
     FROM forum_posts p
     WHERE p.user_id = ?
     ORDER BY p.created_at DESC'
);
$postsStmt->execute([$profileUser['id']]);
$posts = $postsStmt->fetchAll();

$title = $isOwnProfile ? 'My Community Profile' : ($profileUser['name'] . ' - Community Profile');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section community-profile-page">
    <?php if ($flash): ?>
        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="community-profile-grid">
        <aside class="community-profile-sidebar card">
            <div class="community-profile-hero">
                <?= forum_avatar_markup($profileUser, 'forum-avatar forum-avatar-main community-profile-avatar') ?>
                <div class="community-profile-copy">
                    <p class="community-profile-kicker">Community Profile</p>
                    <h1><?= e($profileUser['name']) ?></h1>
                    <div class="community-profile-bio-card">
                        <span class="community-profile-bio-label">Bio</span>
                        <p class="community-profile-bio-text">
                            <?= nl2br(e(trim((string)($profileUser['bio'] ?? '')) !== '' ? (string)$profileUser['bio'] : 'This grower has not added a bio yet.')) ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="community-profile-stats">
                <div><strong><?= (int)$profileUser['post_count'] ?></strong><span>Posts</span></div>
                <div><strong><?= (int)$profileUser['follower_count'] ?></strong><span>Followers</span></div>
                <div><strong><?= (int)$profileUser['following_count'] ?></strong><span>Following</span></div>
            </div>

            <div class="community-profile-actions">
                <a class="btn btn-ghost" href="forum.php">Back to Community</a>
                <?php if (!$isOwnProfile): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_follow">
                        <input type="hidden" name="profile_user_id" value="<?= (int)$profileUser['id'] ?>">
                        <input type="hidden" name="target_user_id" value="<?= (int)$profileUser['id'] ?>">
                        <button class="btn btn-grad" type="submit"><?= !empty($profileUser['followed_by_me']) ? 'Following' : 'Follow' ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($isOwnProfile): ?>
                <details class="community-profile-edit card" id="edit-profile">
                    <summary class="btn btn-grad">Edit Profile</summary>
                    <form method="post" enctype="multipart/form-data" class="forum-profile-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="profile_user_id" value="<?= (int)$profileUser['id'] ?>">
                        <label class="community-profile-photo-editor">
                            <span class="community-profile-photo-label">Update Profile Photo</span>
                            <span class="community-profile-photo-preview">
                                <?= forum_avatar_markup($profileUser, 'forum-avatar community-profile-photo-avatar') ?>
                                <span class="community-profile-photo-hint">Choose new photo</span>
                            </span>
                            <input class="input community-profile-photo-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                        </label>
                        <label>Name
                            <input class="input" type="text" name="name" value="<?= e($currentUser['name']) ?>" required>
                        </label>
                        <label>Bio
                            <textarea name="bio" rows="4" placeholder="Tell the community about your growing interests"><?= e((string)($currentUser['bio'] ?? '')) ?></textarea>
                        </label>
                        <button class="btn btn-grad" type="submit">Save Profile</button>
                    </form>
                </details>
            <?php endif; ?>

        </aside>

        <div class="community-profile-content">
            <section class="card community-profile-section">
                <div class="community-profile-section-head">
                    <h2>Followers</h2>
                    <span><?= (int)$profileUser['follower_count'] ?></span>
                </div>
                <div class="community-people-grid">
                    <?php foreach ($followers as $person): ?>
                        <a class="community-person-card" href="community_profile.php?user_id=<?= (int)$person['id'] ?>">
                            <?= forum_avatar_markup($person, 'forum-avatar forum-avatar-post') ?>
                            <strong><?= e($person['name']) ?></strong>
                            <span><?= e(trim((string)($person['bio'] ?? '')) !== '' ? mb_strimwidth((string)$person['bio'], 0, 80, '...') : 'View community profile') ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$followers): ?>
                        <div class="community-empty-state">No followers yet.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card community-profile-section">
                <div class="community-profile-section-head">
                    <h2>Following</h2>
                    <span><?= (int)$profileUser['following_count'] ?></span>
                </div>
                <div class="community-people-grid">
                    <?php foreach ($following as $person): ?>
                        <a class="community-person-card" href="community_profile.php?user_id=<?= (int)$person['id'] ?>">
                            <?= forum_avatar_markup($person, 'forum-avatar forum-avatar-post') ?>
                            <strong><?= e($person['name']) ?></strong>
                            <span><?= e(trim((string)($person['bio'] ?? '')) !== '' ? mb_strimwidth((string)$person['bio'], 0, 80, '...') : 'View community profile') ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$following): ?>
                        <div class="community-empty-state">Not following anyone yet.</div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card community-profile-section">
                <div class="community-profile-section-head">
                    <h2>Posts</h2>
                    <span><?= (int)$profileUser['post_count'] ?></span>
                </div>
                <div class="community-profile-posts">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $cover = public_url($post['cover_image'] ?: 'assets/img/floral-circle.png');
                        $snippet = mb_strimwidth((string)$post['content'], 0, 200, '...');
                        ?>
                        <a class="community-profile-post-card" href="forum.php?post=<?= (int)$post['id'] ?>">
                            <img src="<?= e($cover) ?>" alt="post cover" onerror="this.onerror=null;this.src='assets/img/floral-circle.png';">
                            <div class="community-profile-post-copy">
                                <strong><?= e($post['title']) ?></strong>
                                <span><?= e($snippet) ?></span>
                                <div class="community-profile-post-meta">
                                    <span><?= (int)$post['like_count'] ?> likes</span>
                                    <span><?= (int)$post['comment_count'] ?> comments</span>
                                    <span><?= e($post['created_at']) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$posts): ?>
                        <div class="community-empty-state">No community posts yet.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
