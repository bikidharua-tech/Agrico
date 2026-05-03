<?php
require __DIR__ . '/../app/bootstrap.php';
global $config;

$googleOauth = $config['oauth']['google'] ?? [];
$googleOauthReady = !empty($googleOauth['client_id'])
    && !str_starts_with((string)($googleOauth['client_id'] ?? ''), 'YOUR_');
$googleOauthHelp = 'Set GOOGLE_OAUTH_CLIENT_ID in your environment if you want to override the built-in Google client ID.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        $stmt = db()->prepare('SELECT id, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'active' && ($user['role'] ?? '') === 'user' && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            redirect('index.php');
        }
        if ($user && ($user['role'] ?? '') === 'admin') {
            set_flash('error', t('flash.admin_use_admin_login'));
        } else {
            set_flash('error', t('flash.invalid_credentials'));
        }
    } catch (Throwable $e) {
        set_flash('error', auth_deployment_error_message());
    }
}
$flash = get_flash();
$title = t('auth.login');
require __DIR__ . '/../app/views/header.php';
?>
<section class="auth-blossom">
    <div class="container auth-wrap">
        <div class="auth-left">
            <a class="auth-back" href="index.php"><?= e(t('auth.back')) ?></a>
            <img class="auth-illustration" src="assets/img/floral-circle.png" alt="<?= e(t('auth.illustration_alt')) ?>">
            <h2 class="auth-welcome"><?= e(t('auth.welcome')) ?></h2>
            <p class="auth-tagline"><?= e(t('auth.tagline')) ?></p>
        </div>
        <div class="auth-right">
            <div class="auth-panel">
                <h2 class="auth-title"><?= e(t('auth.login')) ?></h2>
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
                            <input class="input auth-input" id="login_password" type="password" name="password" placeholder="<?= e(t('auth.password')) ?>" required>
                            <button class="field-icon" type="button" data-toggle-password="login_password" aria-label="<?= e(t('auth.show_password')) ?>">
                                <span class="eye" aria-hidden="true"></span>
                            </button>
                        </div>
                    </label>

                    <div class="auth-hint"><?= e(t('auth.password_hint')) ?></div>
                    <button class="btn btn-grad auth-btn" type="submit"><?= e(t('auth.log_in')) ?></button>
                </form>

                <div class="auth-or"><span><?= e(t('auth.or')) ?></span></div>
                <div class="auth-hint">
                    <?php if ($googleOauthReady): ?>
                        Use your Google account below. If this is your first time, Agrico will create your account automatically.
                    <?php else: ?>
                        Google sign-in is not configured yet. <?= e($googleOauthHelp) ?>
                    <?php endif; ?>
                </div>
                <div class="auth-alt" aria-label="<?= e(t('auth.or')) ?> <?= e(t('auth.google')) ?> / <?= e(t('auth.facebook')) ?>">
                    <?php if ($googleOauthReady): ?>
                        <a class="alt-btn alt-btn-google" href="oauth_google.php" aria-label="<?= e(t('auth.google')) ?>">
                            <span class="alt-btn-mark" aria-hidden="true">G</span>
                            <span><?= e(t('auth.google')) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="alt-btn alt-btn-google alt-btn-disabled" aria-disabled="true" title="Google OAuth is not configured yet">
                            <span class="alt-btn-mark" aria-hidden="true">G</span>
                            <span><?= e(t('auth.google')) ?></span>
                        </span>
                    <?php endif; ?>
                    <a class="alt-btn alt-btn-facebook" href="oauth_facebook.php" aria-label="<?= e(t('auth.facebook')) ?>">
                        <span class="alt-btn-mark" aria-hidden="true">f</span>
                        <span><?= e(t('auth.facebook')) ?></span>
                    </a>
                </div>

                <p class="auth-foot"><?= e(t('auth.create_account')) ?> <a href="signup.php"><?= e(t('auth.sign_up')) ?></a></p>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>

