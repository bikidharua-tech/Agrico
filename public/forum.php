<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();
$user = current_user();

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

function community_profile_url(int $userId): string {
    return 'community_profile.php?user_id=' . $userId;
}

ensure_forum_post_likes_table();
ensure_user_follows_table();
ensure_user_profile_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatarPath = (string)($user['avatar_path'] ?? '');

        if ($name === '') {
            set_flash('error', 'Name is required.');
            redirect('forum.php');
        }

        if (!empty($_FILES['avatar']['name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $imageInfo = @getimagesize($_FILES['avatar']['tmp_name']);
            $ext = strtolower((string)pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (!$imageInfo || !in_array($ext, $allowedExtensions, true)) {
                set_flash('error', 'Please upload a JPG, PNG, or WEBP image.');
                redirect('forum.php');
            }

            $uploadDir = public_root() . '/uploads/avatars';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $fileName = 'avatar_' . (int)$user['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = $uploadDir . '/' . $fileName;

            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                set_flash('error', 'Unable to upload profile photo.');
                redirect('forum.php');
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
            ->execute([$name, $bio !== '' ? $bio : null, $avatarPath !== '' ? $avatarPath : null, $user['id']]);

        set_flash('success', 'Profile updated.');
        redirect('forum.php');
    }

    if ($action === 'toggle_follow') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        if ($targetUserId > 0 && $targetUserId !== (int)$user['id']) {
            $existsStmt = db()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $existsStmt->execute([$targetUserId]);

            if ($existsStmt->fetch()) {
                $followStmt = db()->prepare('SELECT 1 FROM user_follows WHERE follower_user_id = ? AND followed_user_id = ? LIMIT 1');
                $followStmt->execute([$user['id'], $targetUserId]);

                if ($followStmt->fetch()) {
                    db()->prepare('DELETE FROM user_follows WHERE follower_user_id = ? AND followed_user_id = ?')
                        ->execute([$user['id'], $targetUserId]);
                } else {
                    db()->prepare('INSERT INTO user_follows (follower_user_id, followed_user_id) VALUES (?, ?)')
                        ->execute([$user['id'], $targetUserId]);
                }
            }
        }

        redirect($_SERVER['HTTP_REFERER'] ?? 'forum.php');
    }

    if ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt = db()->prepare('SELECT user_id FROM forum_posts WHERE id = ?');
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        if (!$post) {
            redirect('forum.php');
        }
        if ((int)$post['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
            http_response_code(403);
            exit('Forbidden');
        }
        $imgs = db()->prepare('SELECT image_path FROM forum_images WHERE post_id = ?');
        $imgs->execute([$postId]);
        foreach ($imgs->fetchAll() as $img) {
            $p = public_file_path($img['image_path']);
            if (is_file($p)) {
                unlink($p);
            }
        }
        db()->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$postId]);
        if ($user['role'] === 'admin') {
            admin_log($user['id'], 'delete_post', 'forum_post', $postId, 'Admin removed post');
        }
        set_flash('success', t('forum.post_deleted'));
        redirect('forum.php');
    }

    if ($action === 'comment') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($postId && $content !== '') {
            db()->prepare('INSERT INTO forum_comments (post_id, user_id, content) VALUES (?, ?, ?)')
                ->execute([$postId, $user['id'], $content]);
        }
        redirect('forum.php?post=' . $postId);
    }

    if ($action === 'delete_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $c = db()->prepare('SELECT user_id, post_id FROM forum_comments WHERE id = ?');
        $c->execute([$commentId]);
        $row = $c->fetch();
        if ($row && ((int)$row['user_id'] === (int)$user['id'] || $user['role'] === 'admin')) {
            db()->prepare('DELETE FROM forum_comments WHERE id = ?')->execute([$commentId]);
            if ($user['role'] === 'admin') {
                admin_log($user['id'], 'delete_comment', 'forum_comment', $commentId, 'Admin removed comment');
            }
            redirect('forum.php?post=' . (int)$row['post_id']);
        }
        redirect('forum.php');
    }

    if ($action === 'vote') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $vote = (int)($_POST['vote'] ?? 0);
        if (!$commentId || !in_array($vote, [-1, 1], true)) {
            redirect('forum.php');
        }
        $stmt = db()->prepare('INSERT INTO comment_votes (comment_id, user_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote)');
        $stmt->execute([$commentId, $user['id'], $vote]);
        redirect($_SERVER['HTTP_REFERER'] ?? 'forum.php');
    }

    if ($action === 'toggle_post_like') {
        $postId = (int)($_POST['post_id'] ?? 0);
        if (!$postId) {
            redirect('forum.php');
        }

        $stmt = db()->prepare('SELECT id FROM forum_posts WHERE id = ? LIMIT 1');
        $stmt->execute([$postId]);
        if (!$stmt->fetch()) {
            redirect('forum.php');
        }

        $likeStmt = db()->prepare('SELECT 1 FROM forum_post_likes WHERE post_id = ? AND user_id = ? LIMIT 1');
        $likeStmt->execute([$postId, $user['id']]);

        if ($likeStmt->fetch()) {
            db()->prepare('DELETE FROM forum_post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $user['id']]);
        } else {
            db()->prepare('INSERT INTO forum_post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $user['id']]);
        }

        redirect($_SERVER['HTTP_REFERER'] ?? ('forum.php?post=' . $postId));
    }
}

$flash = get_flash();
$q = trim($_GET['q'] ?? '');
$sort = ($_GET['sort'] ?? 'latest') === 'oldest' ? 'oldest' : 'latest';
$order = $sort === 'oldest' ? 'ASC' : 'DESC';
$postIdView = (int)($_GET['post'] ?? 0);
$commentFocus = ($_GET['focus'] ?? '') === 'comments';
$likedByUserSql = 'EXISTS(SELECT 1 FROM forum_post_likes pl2 WHERE pl2.post_id = p.id AND pl2.user_id = ' . (int)$user['id'] . ') AS liked_by_me';
$followedByUserSql = 'EXISTS(SELECT 1 FROM user_follows uf2 WHERE uf2.followed_user_id = p.user_id AND uf2.follower_user_id = ' . (int)$user['id'] . ') AS follows_author';
if ($q !== '') {
    $likeQ = '%' . $q . '%';
    $stmt = db()->prepare(
        "SELECT p.*, u.name, u.avatar_path,
            (SELECT image_path FROM forum_images fi WHERE fi.post_id = p.id ORDER BY fi.id ASC LIMIT 1) AS cover_image,
            (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id = p.id) AS comment_count,
            (SELECT COUNT(*) FROM forum_post_likes pl WHERE pl.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM user_follows uf WHERE uf.followed_user_id = p.user_id) AS author_follower_count,
            $likedByUserSql,
            $followedByUserSql
         FROM forum_posts p
         JOIN users u ON u.id = p.user_id
         WHERE p.title LIKE ?
            OR p.content LIKE ?
            OR COALESCE(p.plant_name, '') LIKE ?
            OR COALESCE(p.tags, '') LIKE ?
            OR u.name LIKE ?
         ORDER BY p.created_at $order"
    );
    $stmt->execute([$likeQ, $likeQ, $likeQ, $likeQ, $likeQ]);
    $posts = $stmt->fetchAll();
} else {
    $posts = db()->query(
        "SELECT p.*, u.name, u.avatar_path,
            (SELECT image_path FROM forum_images fi WHERE fi.post_id = p.id ORDER BY fi.id ASC LIMIT 1) AS cover_image,
            (SELECT COUNT(*) FROM forum_comments fc WHERE fc.post_id = p.id) AS comment_count,
            (SELECT COUNT(*) FROM forum_post_likes pl WHERE pl.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM user_follows uf WHERE uf.followed_user_id = p.user_id) AS author_follower_count,
            $likedByUserSql,
            $followedByUserSql
         FROM forum_posts p
         JOIN users u ON u.id = p.user_id
         ORDER BY p.created_at $order"
    )->fetchAll();
}

$modalPost = null;
$modalImages = [];
$modalComments = [];
if ($postIdView) {
    $s = db()->prepare('SELECT p.*, u.name, u.avatar_path FROM forum_posts p JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1');
    $s->execute([$postIdView]);
    $modalPost = $s->fetch();
    if ($modalPost) {
        $imgStmt = db()->prepare('SELECT * FROM forum_images WHERE post_id = ? ORDER BY id ASC');
        $imgStmt->execute([$postIdView]);
        $modalImages = $imgStmt->fetchAll();

        $cStmt = db()->prepare(
            'SELECT c.*, u.name, COALESCE(SUM(v.vote),0) AS score
             FROM forum_comments c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN comment_votes v ON v.comment_id = c.id
             WHERE c.post_id = ?
             GROUP BY c.id
             ORDER BY c.created_at DESC'
        );
        $cStmt->execute([$postIdView]);
        $modalComments = $cStmt->fetchAll();
    }
}

$title = t('forum.title');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/forum.php'));
$scriptDir = $scriptDir === '.' ? '' : rtrim($scriptDir, '/');
$forumBaseUrl = $scheme . '://' . $host . $scriptDir . '/forum.php';
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section">
    <?php if ($flash): ?>
        <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <div class="forum-shell">
        <div class="forum-toolbar card">
                <div class="forum-toolbar-main">
                    <div class="forum-toolbar-identity">
                        <a class="forum-profile forum-profile-link" href="<?= e(community_profile_url((int)$user['id'])) ?>">
                            <?= forum_avatar_markup($user, 'forum-avatar forum-avatar-main') ?>
                            <div class="forum-profile-copy">
                                <div class="forum-profile-title"><?= e(t('forum.title')) ?></div>
                                <div class="forum-profile-sub">Welcome, <?= e($user['name']) ?></div>
                                <div class="forum-profile-bio">Open your profile to edit details, see followers, and view your posts.</div>
                            </div>
                        </a>
                    </div>

                    <div class="forum-toolbar-actions">
                        <a class="btn btn-ghost" href="<?= e(community_profile_url((int)$user['id'])) ?>">My Profile</a>
                        <a class="btn btn-grad forum-compose" href="forum_create.php"><?= e(t('forum.new_post')) ?></a>
                    </div>
                </div>

            <form method="get" action="forum.php" class="forum-search">
                <input class="input forum-search-input" name="q" placeholder="<?= e(t('forum.search_ph')) ?>" value="<?= e($q) ?>">
                <select class="input forum-sort" name="sort">
                    <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>><?= e(t('forum.latest')) ?></option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>><?= e(t('forum.oldest')) ?></option>
                </select>
                <button class="btn btn-ghost" type="submit"><?= e(t('common.search')) ?></button>
            </form>
        </div>

        <div class="forum-feed">
            <?php foreach ($posts as $p): ?>
                <?php
                $cover = public_url($p['cover_image'] ?: 'assets/img/floral-circle.png');
                $snippet = mb_strimwidth((string)$p['content'], 0, 220, '...');
                $initial = strtoupper(substr(trim((string)$p['name']), 0, 1) ?: 'A');
                $handle = strtolower((string)preg_replace('/[^a-z0-9]+/i', '', (string)$p['name']));
                $tags = array_filter(array_map('trim', explode(',', (string)($p['tags'] ?? ''))));
                $postShareUrl = $forumBaseUrl . '?post=' . (int)$p['id'];
                $likedByMe = !empty($p['liked_by_me']);
                $followsAuthor = !empty($p['follows_author']);
                $canManagePost = ((int)$p['user_id'] === (int)$user['id']) || ($user['role'] === 'admin');
                ?>
                <article class="forum-card card">
                    <div class="forum-card-side">
                        <a class="forum-profile-link" href="<?= e(community_profile_url((int)$p['user_id'])) ?>" aria-label="Open <?= e($p['name']) ?> profile">
                            <?= forum_avatar_markup($p, 'forum-avatar forum-avatar-post') ?>
                        </a>
                        <div class="forum-rail"></div>
                    </div>

                    <div class="forum-body">
                        <div class="forum-topline">
                            <div class="forum-authorline">
                                <a class="forum-author-link" href="<?= e(community_profile_url((int)$p['user_id'])) ?>">
                                    <strong class="forum-author"><?= e($p['name']) ?></strong>
                                </a>
                                <span class="forum-handle">@<?= e($handle !== '' ? $handle : 'grower') ?></span>
                                <span class="dot">&bull;</span>
                                <span><?= e($p['created_at']) ?></span>
                                <span class="dot">&bull;</span>
                                <span><?= (int)$p['author_follower_count'] ?> followers</span>
                            </div>
                            <div class="forum-top-actions">
                                <?php if ((int)$p['user_id'] !== (int)$user['id']): ?>
                                    <form method="post" class="forum-inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle_follow">
                                        <input type="hidden" name="target_user_id" value="<?= (int)$p['user_id'] ?>">
                                        <button class="forum-follow-btn <?= $followsAuthor ? 'is-active' : '' ?>" type="submit">
                                            <?= e($followsAuthor ? 'Following' : 'Follow') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canManagePost): ?>
                                    <details class="forum-post-menu">
                                        <summary class="forum-post-menu-toggle" aria-label="Post options">...</summary>
                                        <div class="forum-post-menu-panel">
                                            <a href="forum_edit_post.php?post_id=<?= (int)$p['id'] ?>"><?= e(t('common.edit')) ?></a>
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete_post">
                                                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                                <button type="submit" data-confirm="<?= e(t('forum.confirm_delete_post')) ?>"><?= e(t('common.delete')) ?></button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="forum-title"><?= e($p['title']) ?></div>
                        <div class="forum-snippet"><?= e($snippet) ?></div>

                        <?php if ($tags): ?>
                            <div class="forum-tagrow">
                                <?php foreach (array_slice($tags, 0, 4) as $tag): ?>
                                    <span class="forum-tag">#<?= e($tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($p['plant_name'])): ?>
                            <div class="forum-plantpill"><?= e($p['plant_name']) ?></div>
                        <?php endif; ?>

                        <div class="forum-cover">
                            <img src="<?= e($cover) ?>" alt="post cover" onerror="this.onerror=null;this.src='assets/img/floral-circle.png';">
                        </div>

                        <div class="forum-engagement">
                            <form method="post" class="forum-like-form" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_post_like">
                                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                <button class="forum-engage forum-engage-like <?= $likedByMe ? 'is-liked' : '' ?>" type="submit" aria-label="Like post" aria-pressed="<?= $likedByMe ? 'true' : 'false' ?>">
                                    <span class="forum-engage-badge forum-heart-badge">
                                        <img class="forum-heart-icon forum-heart-icon-outline" src="<?= e(public_url('assets/img/icon/heart.png')) ?>" alt="" aria-hidden="true">
                                        <img class="forum-heart-icon forum-heart-icon-filled" src="<?= e(public_url('assets/img/icon/heart-filled.svg')) ?>" alt="" aria-hidden="true">
                                        <span><?= (int)$p['like_count'] ?></span>
                                    </span>
                                    <span class="forum-engage-label">Likes</span>
                                </button>
                            </form>
                            <a class="forum-engage forum-engage-comment" href="forum.php?post=<?= (int)$p['id'] ?>&focus=comments" aria-label="<?= e(t('forum.comments')) ?>">
                                <span class="forum-engage-badge forum-comment-badge">
                                    <img class="forum-comment-icon" src="<?= e(public_url('assets/img/icon/chat-bubble.png')) ?>" alt="" aria-hidden="true">
                                    <span><?= (int)$p['comment_count'] ?></span>
                                </span>
                                <span class="forum-engage-label"><?= e(t('forum.comments')) ?></span>
                            </a>
                            <button class="forum-engage forum-engage-share" type="button" data-share-url="<?= e($postShareUrl) ?>" data-share-title="<?= e($p['title']) ?>" data-copy-label="<?= e(t('forum.link_copied')) ?>" aria-label="<?= e(t('forum.share')) ?>">
                                <span class="forum-engage-badge forum-share-badge">
                                    <img class="forum-share-icon" src="<?= e(public_url('assets/img/icon/send.png')) ?>" alt="" aria-hidden="true">
                                </span>
                                <span class="forum-engage-label"><?= e(t('forum.share')) ?></span>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>

            <?php if (!$posts): ?>
                <div class="notice"><?= e(t('forum.no_posts')) ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($modalPost): ?>
    <?php
    $closeQuery = [];
    if ($q !== '') {
        $closeQuery['q'] = $q;
    }
    if ($sort !== 'latest') {
        $closeQuery['sort'] = $sort;
    }
    $closeHref = 'forum.php' . ($closeQuery ? ('?' . http_build_query($closeQuery)) : '');
    $modalShareUrl = $forumBaseUrl . '?post=' . (int)$modalPost['id'];
    ?>
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-head">
                <div class="modal-title"><?= e(t('forum.post_details')) ?></div>
                <a class="modal-close" href="<?= e($closeHref) ?>">x</a>
            </div>

            <?php if (!$commentFocus): ?>
                <div class="modal-media">
                    <?php if (!empty($modalImages[0]['image_path'])): ?>
                        <img src="<?= e(public_url($modalImages[0]['image_path'])) ?>" alt="post image" onerror="this.onerror=null;this.src='assets/img/floral-circle.png';">
                    <?php else: ?>
                        <img src="<?= e(public_url('assets/img/floral-circle.png')) ?>" alt="post image">
                    <?php endif; ?>
                </div>

                <div class="modal-section">
                    <h3 style="margin:0 0 6px"><?= e($modalPost['title']) ?></h3>
                    <div class="forum-sub" style="margin-bottom:10px">
                        <span><?= e(t('forum.by')) ?> <strong><?= e($modalPost['name']) ?></strong></span>
                        <span class="dot">&bull;</span>
                        <span><?= e($modalPost['created_at']) ?></span>
                    </div>
                    <p style="margin:0;color:#356860;line-height:1.6"><?= nl2br(e($modalPost['content'])) ?></p>
                    <div class="forum-sharebar">
                        <button class="btn btn-ghost forum-share-trigger" type="button" data-share-url="<?= e($modalShareUrl) ?>" data-share-title="<?= e($modalPost['title']) ?>" data-copy-label="<?= e(t('forum.link_copied')) ?>"><?= e(t('forum.share')) ?></button>
                    </div>
                </div>

                <?php if (count($modalImages) > 1): ?>
                    <div class="modal-thumbs">
                        <?php foreach ($modalImages as $img): ?>
                            <img src="<?= e(public_url($img['image_path'])) ?>" alt="thumb" onerror="this.onerror=null;this.src='assets/img/floral-circle.png';">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="modal-section" id="forum-comments-section">
                <h3><?= e(t('forum.comments')) ?></h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="comment">
                    <input type="hidden" name="post_id" value="<?= (int)$modalPost['id'] ?>">
                    <textarea name="content" required placeholder="<?= e(t('forum.write_comment')) ?>"></textarea>
                    <button class="btn btn-ghost" style="border-color:#cde4dc"><?= e(t('forum.add_comment')) ?></button>
                </form>

                <div class="comment-list">
                    <?php foreach ($modalComments as $c): ?>
                        <?php $canEditComment = (((int)$c['user_id'] === (int)$user['id']) || $user['role'] === 'admin'); ?>
                        <div class="comment-card">
                            <div class="comment-body"><?= e($c['content']) ?></div>
                            <div class="comment-meta">
                                <span><strong><?= e($c['name']) ?></strong></span>
                                <span class="dot">&bull;</span>
                                <span><?= e(t('forum.score')) ?>: <?= (int)$c['score'] ?></span>
                            </div>
                            <div class="comment-actions">
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="vote">
                                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="vote" value="1">
                                    <button class="vote"><?= e(t('forum.upvote')) ?></button>
                                </form>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="vote">
                                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                    <input type="hidden" name="vote" value="-1">
                                    <button class="vote"><?= e(t('forum.downvote')) ?></button>
                                </form>
                                <?php if ($canEditComment): ?>
                                    <a class="vote" href="forum_edit_comment.php?comment_id=<?= (int)$c['id'] ?>&post_id=<?= (int)$modalPost['id'] ?>"><?= e(t('common.edit')) ?></a>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_comment">
                                        <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                        <button class="vote" data-confirm="<?= e(t('forum.confirm_delete_comment')) ?>"><?= e(t('common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$modalComments): ?>
                        <div class="notice" style="margin-top:10px"><?= e(t('forum.no_comments')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
<?php if ($modalPost && $commentFocus): ?>
window.addEventListener('load', function () {
    var commentsSection = document.getElementById('forum-comments-section');
    if (commentsSection) {
        commentsSection.scrollIntoView({ block: 'start' });
    }
});
<?php endif; ?>

document.addEventListener('click', async function (event) {
    var trigger = event.target.closest('[data-share-url]');
    if (!trigger) {
        return;
    }

    var url = trigger.getAttribute('data-share-url');
    var title = trigger.getAttribute('data-share-title') || document.title;
    var copiedLabel = trigger.getAttribute('data-copy-label') || 'Link copied';
    var labelNode = trigger.querySelector('.forum-engage-label');
    var originalLabel = labelNode ? labelNode.textContent : trigger.textContent;

    try {
        if (navigator.share) {
            await navigator.share({ title: title, url: url });
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            var input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }

        if (labelNode) {
            labelNode.textContent = copiedLabel;
            window.setTimeout(function () {
                labelNode.textContent = originalLabel;
            }, 1800);
        } else {
            trigger.textContent = copiedLabel;
            window.setTimeout(function () {
                trigger.textContent = originalLabel;
            }, 1800);
        }
    } catch (error) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
