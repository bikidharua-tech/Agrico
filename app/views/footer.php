<?php $showFooter = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php'; ?>
</main>
<?php if ($showFooter): ?>
<footer class="site-footer">
    <div class="container site-footer-wrap">
        <div class="site-footer-main">
            <a href="index.php" class="site-footer-brand">Agrico</a>
            <p class="site-footer-copy"><?= e(t('footer.copy')) ?></p>
        </div>
        <div class="site-footer-grid">
            <div class="site-footer-block">
                <div class="site-footer-heading"><?= e(t('footer.explore')) ?></div>
                <div class="site-footer-links">
                    <a href="diagnose.php"><?= e(t('nav.diagnosis')) ?></a>
                    <a href="weather.php"><?= e(t('nav.weather')) ?></a>
                    <a href="crop_recommendation.php"><?= e(t('footer.crop_recommendation')) ?></a>
                    <a href="leafbot.php"><?= e(t('nav.leafbot')) ?></a>
                    <a href="forum.php"><?= e(t('nav.community')) ?></a>
                </div>
            </div>
            <div class="site-footer-block">
                <div class="site-footer-heading"><?= e(t('footer.support')) ?></div>
                <div class="site-footer-meta">
                    <span><?= e(t('footer.support_item_1')) ?></span>
                    <span><?= e(t('footer.support_item_2')) ?></span>
                    <span><?= e(t('footer.support_item_3')) ?></span>
                </div>
            </div>
            <div class="site-footer-block">
                <div class="site-footer-heading"><?= e(t('footer.contact')) ?></div>
                <div class="site-footer-meta">
                    <span><?= e(t('footer.contact_item_1')) ?></span>
                    <span><?= e(t('footer.contact_item_2')) ?></span>
                    <span><?= e(t('footer.contact_item_3')) ?></span>
                </div>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>
<script src="assets/js/main.js"></script>
</body>
</html>

