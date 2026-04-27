<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$user = current_user();
$script = basename($_SERVER['PHP_SELF'] ?? '');
$isAdminPage = in_array($script, ['admin.php', 'admin_login.php'], true);
$isAdminLoginPage = ($script === 'admin_login.php');
$isHome = ($script === 'index.php');
$isLeafbot = ($script === 'leafbot.php');
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Agrico') ?></title>
    <?php $styleVersion = @filemtime(__DIR__ . '/../../public/assets/css/style.css') ?: time(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int)$styleVersion ?>">
</head>
<body class="<?= e(trim(($isHome ? 'page-home ' : '') . ($isLeafbot ? 'page-leafbot' : ''))) ?>">
<header class="site-header">
    <div class="container nav-wrap">
        <a href="index.php" class="logo">Agrico</a>
        <button class="nav-toggle" type="button" aria-label="<?= e(t('nav.open_menu')) ?>" aria-expanded="false" data-nav-toggle>
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav data-site-nav>
                <?php if ($isAdminPage): ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <a href="admin.php"><?= e(t('nav.dashboard')) ?></a>
                    <a href="logout.php" aria-label="<?= e(t('nav.logout')) ?>"><img class="nav-user-icon" src="<?= e(public_url('assets/img/icon/user-logout.png')) ?>" alt="<?= e(t('nav.logout')) ?>"></a>
                <?php else: ?>
                    <?php if (!$isAdminLoginPage): ?>
                        <a href="admin_login.php"><?= e(t('nav.admin_login')) ?></a>
                        <a href="login.php"><?= e(t('nav.user_login')) ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <a href="diagnose.php"><?= e(t('nav.diagnosis')) ?></a>
                <a href="leafbot.php"><?= e(t('nav.leafbot')) ?></a>
                <a href="forum.php"><?= e(t('nav.community')) ?></a>
                <?php if (!$user): ?>
                    <div class="nav-dd" data-dd>
                        <button class="nav-dd-btn" type="button" data-dd-btn aria-haspopup="true" aria-expanded="false">
                            <img class="nav-user-icon" src="<?= e(public_url('assets/img/icon/user.png')) ?>" alt="<?= e(t('nav.login')) ?>">
                        </button>
                        <div class="nav-dd-menu" data-dd-menu role="menu" aria-label="<?= e(t('nav.login_menu')) ?>">
                            <a role="menuitem" href="login.php"><?= e(t('nav.user_login')) ?></a>
                            <a role="menuitem" href="admin_login.php"><?= e(t('nav.admin_login')) ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($user['role'] === 'admin'): ?><a href="admin.php"><?= e(t('nav.admin')) ?></a><?php endif; ?>
                    <a href="logout.php" aria-label="<?= e(t('nav.logout')) ?>"><img class="nav-user-icon" src="<?= e(public_url('assets/img/icon/user-logout.png')) ?>" alt="<?= e(t('nav.logout')) ?>"></a>
                <?php endif; ?>

                <div class="nav-dd" data-dd>
                    <button class="nav-dd-btn" type="button" data-dd-btn aria-haspopup="true" aria-expanded="false" style="background:#1f5d56">
                        <img class="nav-lang-icon" src="<?= e(public_url('assets/img/icon/translate1.png')) ?>" alt="<?= e(t('nav.language')) ?>">
                    </button>
                    <div class="nav-dd-menu" data-dd-menu role="menu" aria-label="<?= e(t('nav.language_menu')) ?>">
                        <a role="menuitem" href="<?= e(lang_url('en')) ?>"><?= e(t('lang.en')) ?></a>
                        <a role="menuitem" href="<?= e(lang_url('hi')) ?>"><?= e(t('lang.hi')) ?></a>
                        <a role="menuitem" href="<?= e(lang_url('or')) ?>"><?= e(t('lang.or')) ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>


