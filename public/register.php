<?php
require __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        set_flash('error', t('flash.register_invalid'));
    } elseif ($password !== $confirmPassword) {
        set_flash('error', t('flash.password_mismatch'));
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, "user", "active")');
            $stmt->execute([$name, $email, $hash]);
            set_flash('success', t('flash.registration_complete'));
            redirect('login.php');
        } catch (PDOException $e) {
            set_flash('error', t('flash.email_exists'));
        }
    }
}
$flash = get_flash();
$title = t('auth.sign_up');
require __DIR__ . '/../app/views/header.php';
?>
<section class="container section">
    <div class="auth-shell">
        <aside class="auth-side">
            <h2><?= e(t('auth.join')) ?></h2>
            <p><?= e(t('auth.signup_lead')) ?></p>
            <ul>
                <li><?= e(t('auth.signup_b1')) ?></li>
                <li><?= e(t('auth.signup_b2')) ?></li>
                <li><?= e(t('auth.signup_b3')) ?></li>
            </ul>
        </aside>
        <div class="auth-card">
            <h2><?= e(t('auth.create_user_account')) ?></h2>
            <p class="auth-sub"><?= e(t('auth.signup_sub')) ?></p>
            <?php if ($flash): ?><div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
            <form method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label><?= e(t('auth.full_name')) ?><input class="input" name="name" required></label>
                <label><?= e(t('auth.email_address')) ?><input class="input" type="email" name="email" required></label>
                <label><?= e(t('auth.password')) ?><input class="input" type="password" name="password" required></label>
                <label><?= e(t('auth.confirm_password')) ?><input class="input" type="password" name="confirm_password" required></label>
                <button class="btn btn-grad auth-btn" type="submit"><?= e(t('auth.sign_up')) ?></button>
            </form>
            <p class="auth-foot"><?= e(t('auth.already_have')) ?> <a href="login.php"><?= e(t('auth.sign_in')) ?></a></p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>

