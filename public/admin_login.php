<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = db()->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && $u['status'] === 'active' && $u['role'] === 'admin' && password_verify($password, $u['password_hash'])) {
        $_SESSION['user_id'] = (int)$u['id'];
        redirect('admin.php');
    }
    set_flash('error', t('flash.invalid_admin_credentials'));
}
$flash = get_flash();
$title = t('auth.admin_login');
require __DIR__ . '/../app/views/header.php';
?>
<section class="auth-blossom auth-blossom-admin">
    <div class="container auth-wrap">
        <div class="auth-left">
            <a class="auth-back" href="index.php"><?= e(t('auth.back')) ?></a>
            <img class="auth-illustration" src="assets/img/floral-circle.png" alt="<?= e(t('auth.illustration_alt')) ?>">
            <h2 class="auth-welcome"><?= e(t('auth.admin_access')) ?></h2>
            <p class="auth-tagline"><?= e(t('auth.admin_tagline')) ?></p>
        </div>
        <div class="auth-right">
            <div class="auth-panel">
                <h2 class="auth-title"><?= e(t('auth.admin_login')) ?></h2>
                <?php if ($flash): ?><div class="notice error"><?= e($flash['message']) ?></div><?php endif; ?>
                <form method="post" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <label class="field">
                        <span class="field-label"><?= e(t('auth.email')) ?></span>
                        <input class="input auth-input" type="email" name="email" placeholder="<?= e(t('auth.email')) ?>" required>
                    </label>

                    <label class="field">
                        <span class="field-label"><?= e(t('auth.password')) ?></span>
                        <div class="field-wrap">
                            <input class="input auth-input" id="admin_password" type="password" name="password" placeholder="<?= e(t('auth.password')) ?>" required>
                            <button class="field-icon" type="button" data-toggle-password="admin_password" aria-label="<?= e(t('auth.show_password')) ?>">
                                <span class="eye" aria-hidden="true"></span>
                            </button>
                        </div>
                    </label>

                    <button class="btn btn-grad auth-btn" type="submit"><?= e(t('auth.login_as_admin')) ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
