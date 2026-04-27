<?php
require __DIR__ . '/../app/bootstrap.php';
$title = t('home.hero_title');
$homeFeatures = [
    [
        'kind' => 'diagnosis',
        'title' => t('home.features_diagnosis_title'),
        'sub' => t('home.features_diagnosis_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'diagnose.php',
    ],
    [
        'kind' => 'leafbot',
        'title' => t('home.features_leafbot_title'),
        'sub' => t('home.features_leafbot_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'leafbot.php',
    ],
    [
        'kind' => 'weather',
        'title' => t('home.features_weather_title'),
        'sub' => t('home.features_weather_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'weather.php',
    ],
    [
        'kind' => 'community',
        'title' => t('home.features_community_title'),
        'sub' => t('home.features_community_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'forum.php',
    ],
    [
        'kind' => 'history',
        'title' => t('home.features_history_title'),
        'sub' => t('home.features_history_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'history.php',
    ],
    [
        'kind' => 'crop',
        'title' => t('home.features_crop_title'),
        'sub' => t('home.features_crop_sub'),
        'cta' => t('home.features_cta'),
        'href' => 'crop_recommendation.php',
    ],
];
require __DIR__ . '/../app/views/header.php';
?>
<section class="hero container">
    <div class="hero-media">
        <img src="https://images.unsplash.com/photo-1463936575829-25148e1db1b8?q=80&w=1000&auto=format&fit=crop" alt="<?= e(t('home.hero_img_alt')) ?>">
        <div class="badge-card">
            <div style="color:#e48585;font-weight:700"><?= e(t('home.badge_title')) ?></div>
            <div style="font:800 35px/1.05 'Manrope',sans-serif;margin:4px 0 10px;color:#2a6f59"><?= e(t('home.badge_plant_name')) ?></div>
            <div style="color:#65867d"><?= e(t('home.badge_desc')) ?></div>
            <a class="btn" style="background:linear-gradient(90deg,#6bd39f,#4fb88e);color:#fff;margin-top:12px" href="diagnose.php"><?= e(t('home.badge_cta')) ?></a>
        </div>
    </div>
    <div>
        <h1><?= e(t('home.hero_title')) ?></h1>
        <p class="lead"><?= e(t('home.hero_sub')) ?></p>
        <a href="diagnose.php" class="btn btn-grad" style="font-size:22px;padding:16px 48px"><?= e(t('home.diagnose_now')) ?></a>
    </div>
</section>
<section class="container section feature-hub">
    <div class="feature-hub-head">
        <div class="home-kicker"><?= e(t('home.features_kicker')) ?></div>
        <h2><?= e(t('home.features_title')) ?></h2>
    </div>
    <div class="feature-hub-grid">
        <?php foreach ($homeFeatures as $feature): ?>
            <a class="feature-tile feature-<?= e($feature['kind']) ?>" href="<?= e($feature['href']) ?>">
                <span class="feature-tile-icon" aria-hidden="true"></span>
                <strong><?= e($feature['title']) ?></strong>
                <span><?= e($feature['sub']) ?></span>
                <em><?= e($feature['cta']) ?></em>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<section class="container section blossom">
    <div class="blossom-head">
        <h2><strong><?= e(t('home.blossom_title')) ?></strong></h2>
        <p><?= e(t('home.blossom_sub')) ?></p>
    </div>
    <div class="blossom-grid">
        <a class="blossom-card blossom-start" href="diagnose.php">
            <span class="blossom-start-icon" aria-hidden="true"></span>
            <span class="blossom-start-text"><?= e(t('home.blossom_cta')) ?></span>
        </a>
        <div class="blossom-card blossom-photo">
            <img src="<?= e(public_url('assets/img/' . rawurlencode('WhatsApp Image 2026-04-09 at 11.26.44 PM.jpeg'))) ?>" alt="<?= e(t('home.blossom_img1_alt')) ?>">
        </div>
        <div class="blossom-card blossom-photo blossom-focus">
            <img src="<?= e(public_url('assets/img/' . rawurlencode('WhatsApp Image 2026-04-09 at 11.26.45 PM (1).jpeg'))) ?>" alt="<?= e(t('home.blossom_img2_alt')) ?>">
        </div>
        <div class="blossom-card blossom-photo">
            <img src="<?= e(public_url('assets/img/' . rawurlencode('WhatsApp Image 2026-04-09 at 11.26.45 PM.jpeg'))) ?>" alt="<?= e(t('home.blossom_img3_alt')) ?>">
        </div>
    </div>
</section>
<section id="diagnosis" class="home-section">
    <div class="container home-grid">
        <div class="home-media">
            <div class="diag-phones" id="diagPhones">
                <div class="diag-phone ghost phone-ghost-1 diag-phone-photo-ghost" aria-hidden="true">
                    <img src="<?= e(public_url('assets/img/d1.jpeg')) ?>" alt="">
                </div>
                <div class="diag-phone ghost phone-ghost-2" aria-hidden="true"></div>
                <div class="diag-phone">
                    <div class="diag-phone-notch"></div>
                    <img id="diagPhoneImg" src="<?= e(public_url('assets/img/' . rawurlencode('WhatsApp Image 2026-04-09 at 11.26.54 PM.jpeg'))) ?>" alt="<?= e(t('home.section_diagnosis_img_alt')) ?>">
                    <div class="diag-phone-overlay"></div>
                    <div class="diag-phone-caption" id="diagPhoneCaption"><?= e(t('home.section_diagnosis_phone_caption')) ?></div>
                </div>
            </div>
        </div>
        <div class="home-copy diag-copy">
            <div class="home-kicker"><?= e(t('home.section_diagnosis_kicker')) ?></div>
            <h2><?= e(t('home.section_diagnosis_title')) ?></h2>
            <p><?= e(t('home.section_diagnosis_sub')) ?></p>
            <ol class="diag-steps" id="diagSteps">
                <li class="is-active" role="button" tabindex="0" data-step="1" data-img="<?= e(public_url('assets/img/' . rawurlencode('WhatsApp Image 2026-04-09 at 11.26.54 PM.jpeg'))) ?>" data-caption="<?= e(t('home.section_diagnosis_caption1')) ?>">
                    <?= e(t('home.section_diagnosis_step1')) ?>
                </li>
                <li role="button" tabindex="0" data-step="2" data-img="assets/img/d1.jpeg" data-caption="<?= e(t('home.section_diagnosis_caption2')) ?>">
                    <?= e(t('home.section_diagnosis_step2')) ?>
                </li>
                <li role="button" tabindex="0" data-step="3" data-img="assets/img/3.jpeg" data-caption="<?= e(t('home.section_diagnosis_caption3')) ?>">
                    <?= e(t('home.section_diagnosis_step3')) ?>
                </li>
                <li role="button" tabindex="0" data-step="4" data-img="assets/img/d4.jpeg" data-caption="<?= e(t('home.section_diagnosis_caption4')) ?>">
                    <?= e(t('home.section_diagnosis_step4')) ?>
                </li>
                <li role="button" tabindex="0" data-step="5" data-img="assets/img/d5.jpeg" data-caption="<?= e(t('home.section_diagnosis_caption5')) ?>">
                    <?= e(t('home.section_diagnosis_step5')) ?>
                </li>
            </ol>
            <div class="home-actions">
                <a class="btn btn-grad" href="diagnose.php"><?= e(t('home.section_diagnosis_cta')) ?></a>
                <a class="btn btn-ghost" href="history.php"><?= e(t('home.section_diagnosis_cta_secondary')) ?></a>
            </div>
        </div>
    </div>
</section>
<script>
(() => {
  const steps = document.getElementById('diagSteps');
  const img = document.getElementById('diagPhoneImg');
  const caption = document.getElementById('diagPhoneCaption');
  if (!steps || !img || !caption) return;

  const phones = document.getElementById('diagPhones');
  const setStep = (li) => {
    steps.querySelectorAll('li').forEach((el) => el.classList.remove('is-active'));
    li.classList.add('is-active');
    if (phones) {
      const step = li.getAttribute('data-step');
      phones.classList.toggle('diag-slim', step === '2');
    }
    const nextImg = li.getAttribute('data-img') || img.src;
    const nextCaption = li.getAttribute('data-caption') || '';
    img.classList.remove('diag-fade-in');
    void img.offsetWidth;
    img.src = nextImg;
    caption.textContent = nextCaption;
    img.classList.add('diag-fade-in');
  };

  steps.addEventListener('click', (e) => {
    const li = e.target.closest('li');
    if (!li) return;
    setStep(li);
  });
  steps.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const li = e.target.closest('li');
    if (!li) return;
    e.preventDefault();
    setStep(li);
  });
})();
</script>

<section id="weather" class="home-section home-alt">
    <div class="container home-grid">
        <div class="home-media">
            <div class="home-card weather-card weather-card-feature">
                <div class="weather-card-top">
                    <div class="weather-place"><?= e(t('home.section_weather_place')) ?></div>
                    <div class="weather-pill"><?= e(t('home.section_weather_now')) ?></div>
                </div>
                <div class="weather-hero">
                    <div class="weather-orb">
                        <strong>29<span>&deg;C</span></strong>
                        <small><?= e(t('home.section_weather_live')) ?></small>
                    </div>
                    <div class="weather-hero-copy">
                        <div class="weather-temp">29<span>&deg;C</span></div>
                        <div class="weather-desc"><?= e(t('home.section_weather_desc')) ?></div>
                    </div>
                </div>
                <div class="weather-kpis">
                    <div><span><?= e(t('home.section_weather_kpi1')) ?></span><strong>62%</strong></div>
                    <div><span><?= e(t('home.section_weather_kpi2')) ?></span><strong>8 km/h</strong></div>
                    <div><span><?= e(t('home.section_weather_kpi3')) ?></span><strong>1.2 mm</strong></div>
                </div>
                <div class="weather-strip">
                    <span><?= e(t('home.section_weather_strip_1')) ?></span>
                    <span><?= e(t('home.section_weather_strip_2')) ?></span>
                    <span><?= e(t('home.section_weather_strip_3')) ?></span>
                </div>
            </div>
        </div>
        <div class="home-copy">
            <div class="home-kicker"><?= e(t('home.section_weather_kicker')) ?></div>
            <h2><?= e(t('home.section_weather_title')) ?></h2>
            <p><?= e(t('home.section_weather_sub')) ?></p>
            <div class="home-actions">
                <a class="btn btn-grad" href="weather.php"><?= e(t('home.section_weather_cta')) ?></a>
            </div>
            <div class="home-pills">
                <span class="home-pill"><?= e(t('home.section_weather_pill1')) ?></span>
                <span class="home-pill"><?= e(t('home.section_weather_pill2')) ?></span>
                <span class="home-pill"><?= e(t('home.section_weather_pill3')) ?></span>
            </div>
        </div>
    </div>
</section>

<section id="community" class="home-section">
    <div class="container home-grid">
        <div class="home-copy">
            <div class="home-kicker"><?= e(t('home.section_community_kicker')) ?></div>
            <h2><?= e(t('home.section_community_title')) ?></h2>
            <p><?= e(t('home.section_community_sub')) ?></p>
            <div class="home-actions">
                <a class="btn btn-grad" href="forum.php"><?= e(t('home.section_community_cta')) ?></a>
                <a class="btn btn-ghost" href="forum_create.php"><?= e(t('home.section_community_cta_secondary')) ?></a>
            </div>
            <div class="home-pills">
                <span class="home-pill"><?= e(t('home.section_community_pill1')) ?></span>
                <span class="home-pill"><?= e(t('home.section_community_pill2')) ?></span>
                <span class="home-pill"><?= e(t('home.section_community_pill3')) ?></span>
            </div>
        </div>
        <div class="home-media">
            <div class="home-card community-grid community-grid-accent">
                <div class="community-hero">
                    <div class="community-hero-top">
                        <span class="community-hero-badge"><?= e(t('home.section_community_badge')) ?></span>
                        <span class="community-hero-count"><?= e(t('home.section_community_count')) ?></span>
                    </div>
                    <div class="community-hero-title"><?= e(t('home.section_community_hero_title')) ?></div>
                    <p class="community-hero-sub"><?= e(t('home.section_community_hero_sub')) ?></p>
                </div>
                <div class="community-card">
                    <div class="community-title"><?= e(t('home.section_community_card1_title')) ?></div>
                    <div class="community-sub"><?= e(t('home.section_community_card1_sub')) ?></div>
                </div>
                <div class="community-card">
                    <div class="community-title"><?= e(t('home.section_community_card2_title')) ?></div>
                    <div class="community-sub"><?= e(t('home.section_community_card2_sub')) ?></div>
                </div>
                <div class="community-card">
                    <div class="community-title"><?= e(t('home.section_community_card3_title')) ?></div>
                    <div class="community-sub"><?= e(t('home.section_community_card3_sub')) ?></div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
