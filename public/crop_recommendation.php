<?php
require __DIR__ . '/../app/bootstrap.php';
$title = t('crop.title');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section crop-rec-wrap">
    <div class="crop-rec-head">
        <h1><?= e(t('crop.title')) ?></h1>
        <p class="lead"><?= e(t('crop.lead')) ?></p>
    </div>

    <div class="crop-rec-grid">
        <div class="card crop-rec-card">
            <h2 class="crop-rec-title"><?= e(t('crop.field_inputs')) ?></h2>
            <form id="cropRecForm" class="crop-rec-form" novalidate>
                <div class="crop-rec-row">
                    <label>
                        <?= e(t('crop.nitrogen')) ?>
                        <input class="input" type="number" name="nitrogen" min="0" max="250" step="1" required>
                    </label>
                    <label>
                        <?= e(t('crop.phosphorus')) ?>
                        <input class="input" type="number" name="phosphorus" min="0" max="250" step="1" required>
                    </label>
                    <label>
                        <?= e(t('crop.potassium')) ?>
                        <input class="input" type="number" name="potassium" min="0" max="250" step="1" required>
                    </label>
                </div>

                <div class="crop-rec-row">
                    <label>
                        <?= e(t('crop.temperature')) ?>
                        <input class="input" type="number" name="temperature" min="-5" max="55" step="0.1" required>
                    </label>
                    <label>
                        <?= e(t('crop.humidity')) ?>
                        <input class="input" type="number" name="humidity" min="0" max="100" step="0.1" required>
                    </label>
                    <label>
                        <?= e(t('crop.rainfall')) ?>
                        <input class="input" type="number" name="rainfall" min="0" max="600" step="0.1" required>
                    </label>
                </div>

                <div class="crop-rec-row crop-rec-row-single">
                    <label>
                        <?= e(t('crop.ph')) ?>
                        <input class="input" type="number" name="ph" min="3" max="10" step="0.1" required>
                    </label>
                </div>

                <button type="submit" class="btn btn-grad"><?= e(t('crop.submit')) ?></button>
                <p id="cropRecError" class="crop-rec-error" hidden><?= e(t('crop.error')) ?></p>
            </form>
        </div>

        <div class="card crop-rec-card crop-rec-results">
            <h2 class="crop-rec-title"><?= e(t('crop.results_title')) ?></h2>
            <div id="cropRecOutput" class="crop-rec-output">
                <p class="crop-rec-placeholder"><?= e(t('crop.placeholder')) ?></p>
            </div>
        </div>
    </div>
</section>

<script>
const CROP_I18N = <?= json_encode([
  'reason_npk' => t('crop.reason_npk'),
  'reason_weather' => t('crop.reason_weather'),
  'reason_rain' => t('crop.reason_rain'),
  'reason_ph' => t('crop.reason_ph'),
  'reason_partial' => t('crop.reason_partial'),
  'badge_best' => t('crop.badge_best'),
  'badge_option' => t('crop.badge_option'),
  'match_suffix' => t('crop.match_suffix'),
], JSON_UNESCAPED_UNICODE) ?>;
const CROP_LOCALE = <?= json_encode(current_locale(), JSON_UNESCAPED_UNICODE) ?>;
const CROP_NAMES = {
  en: {
    Rice: 'Rice',
    Wheat: 'Wheat',
    Maize: 'Maize',
    Cotton: 'Cotton',
    Sugarcane: 'Sugarcane',
    Pulses: 'Pulses',
    Groundnut: 'Groundnut',
    Millet: 'Millet',
    Soybean: 'Soybean',
    Mustard: 'Mustard'
  },
  hi: {
    Rice: 'चावल',
    Wheat: 'गेहूं',
    Maize: 'मक्का',
    Cotton: 'कपास',
    Sugarcane: 'गन्ना',
    Pulses: 'दालें',
    Groundnut: 'मूंगफली',
    Millet: 'बाजरा',
    Soybean: 'सोयाबीन',
    Mustard: 'सरसों'
  },
  or: {
    Rice: 'ଚାଉଳ',
    Wheat: 'ଗହମ',
    Maize: 'ମକା',
    Cotton: 'ସୁତା',
    Sugarcane: 'ଅଖୁ',
    Pulses: 'ଡାଲି',
    Groundnut: 'ଚିନାବାଦାମ',
    Millet: 'ମାଣ୍ଡିଆ',
    Soybean: 'ସୋୟାବିନ',
    Mustard: 'ସୋରିଷ'
  }
};

(() => {
  const cropLabel = (name) => (CROP_NAMES[CROP_LOCALE] && CROP_NAMES[CROP_LOCALE][name]) || CROP_NAMES.en[name] || name;

  const cropDatabase = [
    { name: 'Rice', n: [80, 110], p: [35, 55], k: [35, 55], t: [20, 35], h: [70, 95], r: [150, 300], ph: [5.0, 7.0] },
    { name: 'Wheat', n: [70, 100], p: [30, 50], k: [30, 50], t: [10, 25], h: [50, 70], r: [50, 120], ph: [6.0, 7.5] },
    { name: 'Maize', n: [70, 100], p: [35, 55], k: [25, 45], t: [18, 32], h: [50, 80], r: [60, 180], ph: [5.5, 7.5] },
    { name: 'Cotton', n: [100, 140], p: [40, 65], k: [40, 65], t: [21, 35], h: [40, 70], r: [50, 150], ph: [5.8, 8.0] },
    { name: 'Sugarcane', n: [130, 170], p: [50, 80], k: [50, 80], t: [20, 38], h: [55, 85], r: [100, 250], ph: [6.0, 8.0] },
    { name: 'Pulses', n: [15, 40], p: [40, 60], k: [15, 40], t: [18, 30], h: [40, 75], r: [30, 120], ph: [6.0, 7.5] },
    { name: 'Groundnut', n: [10, 35], p: [30, 55], k: [30, 55], t: [20, 32], h: [45, 75], r: [40, 120], ph: [5.8, 7.0] },
    { name: 'Millet', n: [25, 55], p: [10, 30], k: [10, 30], t: [20, 35], h: [35, 65], r: [20, 90], ph: [5.5, 7.5] },
    { name: 'Soybean', n: [20, 45], p: [45, 70], k: [30, 55], t: [20, 30], h: [50, 80], r: [60, 160], ph: [6.0, 7.5] },
    { name: 'Mustard', n: [45, 75], p: [20, 40], k: [10, 30], t: [10, 28], h: [35, 65], r: [20, 90], ph: [6.0, 7.5] }
  ];

  const weights = {
    n: 0.20,
    p: 0.17,
    k: 0.18,
    t: 0.15,
    h: 0.12,
    r: 0.12,
    ph: 0.06
  };

  const form = document.getElementById('cropRecForm');
  const output = document.getElementById('cropRecOutput');
  const error = document.getElementById('cropRecError');
  if (!form || !output || !error) return;

  const esc = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

  const scoreByRange = (value, min, max) => {
    if (value >= min && value <= max) return 100;
    const span = Math.max(1, max - min);
    const tolerance = (span * 0.75) + 10;
    const diff = value < min ? (min - value) : (value - max);
    return Math.max(0, 100 - ((diff / tolerance) * 100));
  };

  const scoreCrop = (inputs, crop) => {
    const parts = {
      n: scoreByRange(inputs.nitrogen, crop.n[0], crop.n[1]),
      p: scoreByRange(inputs.phosphorus, crop.p[0], crop.p[1]),
      k: scoreByRange(inputs.potassium, crop.k[0], crop.k[1]),
      t: scoreByRange(inputs.temperature, crop.t[0], crop.t[1]),
      h: scoreByRange(inputs.humidity, crop.h[0], crop.h[1]),
      r: scoreByRange(inputs.rainfall, crop.r[0], crop.r[1]),
      ph: scoreByRange(inputs.ph, crop.ph[0], crop.ph[1])
    };

    const total =
      (parts.n * weights.n) +
      (parts.p * weights.p) +
      (parts.k * weights.k) +
      (parts.t * weights.t) +
      (parts.h * weights.h) +
      (parts.r * weights.r) +
      (parts.ph * weights.ph);

    return {
      ...crop,
      score: Math.round(total),
      details: parts
    };
  };

  const reason = (item) => {
    const tags = [];
    if (item.details.n >= 80 && item.details.p >= 80 && item.details.k >= 80) tags.push(CROP_I18N.reason_npk);
    if (item.details.t >= 80 && item.details.h >= 80) tags.push(CROP_I18N.reason_weather);
    if (item.details.r >= 80) tags.push(CROP_I18N.reason_rain);
    if (item.details.ph >= 80) tags.push(CROP_I18N.reason_ph);
    return tags.length ? tags.join(', ') + '.' : CROP_I18N.reason_partial;
  };

  const readInputs = () => {
    const fd = new FormData(form);
    const values = {
      nitrogen: Number(fd.get('nitrogen')),
      phosphorus: Number(fd.get('phosphorus')),
      potassium: Number(fd.get('potassium')),
      temperature: Number(fd.get('temperature')),
      humidity: Number(fd.get('humidity')),
      rainfall: Number(fd.get('rainfall')),
      ph: Number(fd.get('ph'))
    };

    const valid = Object.values(values).every((v) => Number.isFinite(v));
    return valid ? values : null;
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const inputs = readInputs();
    if (!inputs) {
      error.hidden = false;
      return;
    }
    error.hidden = true;

    const ranked = cropDatabase
      .map((crop) => scoreCrop(inputs, crop))
      .sort((a, b) => b.score - a.score)
      .slice(0, 3);

    output.innerHTML = ranked.map((item, idx) => {
      const badge = idx === 0 ? CROP_I18N.badge_best : `${CROP_I18N.badge_option} ${idx + 1}`;
      return `
        <article class="crop-rec-item">
          <div class="crop-rec-item-top">
            <h3>${esc(cropLabel(item.name))}</h3>
            <span class="crop-rec-score">${item.score}${esc(CROP_I18N.match_suffix)}</span>
          </div>
          <p class="crop-rec-badge">${esc(badge)}</p>
          <p class="crop-rec-note">${esc(reason(item))}</p>
        </article>
      `;
    }).join('');
  });
})();
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
