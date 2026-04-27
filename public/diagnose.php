<?php
require __DIR__ . '/../app/bootstrap.php';
require_login();

$flash = get_flash();
$diagnosisResult = $_SESSION['diagnosis_result'] ?? null;
unset($_SESSION['diagnosis_result']);
$title = t('diagnosis.title');
$hasResult = is_array($diagnosisResult);

$confidenceValue = 0.0;
if (is_array($diagnosisResult) && isset($diagnosisResult['confidence']) && is_numeric($diagnosisResult['confidence'])) {
    $confidenceValue = max(0.0, min(100.0, (float)$diagnosisResult['confidence']));
}
$initialDisease = (string)($diagnosisResult['disease_name'] ?? '');
$initialPlant = (string)($diagnosisResult['plant_name'] ?? '');
$initialGoogleQuery = trim($initialDisease . ' ' . $initialPlant . ' plant disease treatment');
$initialGoogleUrl = $initialGoogleQuery !== '' ? ('https://www.google.com/search?q=' . rawurlencode($initialGoogleQuery)) : 'https://www.google.com/search?q=plant+disease+treatment';

$diagnosisUi = [
    'analyze' => t('diagnosis.analyze'),
    'loading' => t('diagnosis.loading'),
    'loadingShort' => t('diagnosis.loading_short'),
    'chooseImage' => t('diagnosis.inline_choose_image'),
    'analyzeFailed' => t('diagnosis.inline_failed'),
    'geoDetecting' => t('diagnosis.geo_detecting'),
    'geoDetected' => t('diagnosis.geo_detected'),
    'geoUnavailable' => t('diagnosis.geo_unavailable'),
    'geoUnsupported' => t('diagnosis.geo_unsupported'),
    'unknownPlant' => t('diagnosis.unknown_plant'),
    'notAvailable' => t('diagnosis.not_available'),
    'labelConfidence' => t('diagnosis.label_confidence'),
    'defaultFertilizerTitle' => t('diagnosis.fertilizer_title'),
    'defaultFertilizerLink' => t('diagnosis.fertilizer_link'),
    'defaultVideoTitle' => t('diagnosis.video_title'),
    'defaultVideoLink' => t('diagnosis.video_link'),
];
?>

<?php require __DIR__ . '/../app/views/header.php'; ?>

<section class="container section diagnosis-x">
    <div class="diagnosis-x-hero">
        <div class="diagnosis-x-hero-copy">
            <span class="diagnosis-x-kicker"><?= e(t('diagnosis.hero_badge')) ?></span>
            <h1><?= e(t('diagnosis.title')) ?></h1>
            <p><?= e(t('diagnosis.hero_sub')) ?></p>
            <div class="diagnosis-x-pills">
                <span><?= e(t('diagnosis.metric_1_label')) ?></span>
                <span><?= e(t('diagnosis.metric_2_label')) ?></span>
                <span><?= e(t('diagnosis.metric_3_label')) ?></span>
            </div>
            <div class="diagnosis-x-note-card" aria-label="<?= e(t('diagnosis.summary_aria')) ?>">
                <p><?= e(t('diagnosis.summary_1')) ?></p>
                <p><?= e(t('diagnosis.summary_2')) ?></p>
                <p><?= e(t('diagnosis.summary_3')) ?></p>
                <p><?= e(t('diagnosis.summary_4')) ?></p>
            </div>
        </div>
        <div class="diagnosis-x-hero-art" aria-hidden="true">
            <img src="assets/img/d5.jpeg" alt="">
        </div>
    </div>

    <div class="diagnosis-x-layout">
        <div class="diagnosis-x-main">
            <div class="card diagnosis-x-upload-card">
                <div class="diagnosis-x-section-head">
                    <h2><?= e(t('diagnosis.upload')) ?></h2>
                    <p><?= e(t('diagnosis.form_sub')) ?></p>
                </div>

                <?php if ($flash): ?>
                    <div class="notice <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= e($flash['message']) ?></div>
                <?php endif; ?>
                <div class="notice error" id="diagInlineError" hidden></div>

                <form id="diagnosisForm" class="diagnosis-x-form" action="api_predict.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">

                    <label class="diagnosis-x-drop" id="diagDropZone" for="leafImageInput">
                        <div class="diagnosis-x-drop-placeholder" id="diagDropPlaceholder">
                            <strong><?= e(t('diagnosis.drop_title')) ?></strong>
                            <span><?= e(t('diagnosis.drop_browse')) ?></span>
                            <small><?= e(t('diagnosis.upload_hint')) ?></small>
                        </div>
                        <img id="diagnosisDropImage" class="diagnosis-x-drop-image" alt="<?= e(t('diagnosis.preview_alt')) ?>" hidden>
                        <input id="leafImageInput" class="input" type="file" name="leaf_image" accept="image/jpeg,image/png" required>
                    </label>

                    <div class="diagnosis-x-actions">
                        <button class="btn btn-grad diagnosis-x-submit" type="submit"><?= e(t('diagnosis.analyze')) ?></button>
                        <span class="diagnosis-x-status" id="diagGeoState"><?= e(t('diagnosis.geo_detecting')) ?></span>
                        <span class="diagnosis-x-loading" id="diagSubmitState" hidden><?= e(t('diagnosis.loading')) ?></span>
                    </div>
                </form>

                <p class="diagnosis-x-note"><?= e(t('diagnosis.location_note')) ?></p>
            </div>

            <div class="card diagnosis-x-result" id="diagnosisResultCard" <?= $hasResult ? '' : 'hidden' ?>>
                <div class="diagnosis-x-section-head diagnosis-x-result-head">
                    <h2><?= e(t('diagnosis.result_title')) ?></h2>
                    <span class="diagnosis-x-model" id="diagnosisResultModel"><?= e((string)($diagnosisResult['model'] ?? 'plant.id')) ?></span>
                </div>

                <div class="diagnosis-x-result-grid">
                    <div class="diagnosis-x-result-image" id="diagnosisResultImageWrap" <?= !empty($diagnosisResult['image_path']) ? '' : 'hidden' ?>>
                        <img id="diagnosisResultImage" src="<?= !empty($diagnosisResult['image_path']) ? e(public_url((string)$diagnosisResult['image_path'])) : '' ?>" alt="<?= e(t('diagnosis.result_image_alt')) ?>">
                    </div>
                    <div class="diagnosis-x-result-copy">
                        <h3 class="diagnosis-x-disease" id="diagnosisResultDisease"><?= e((string)($diagnosisResult['disease_name'] ?? t('diagnosis.not_available'))) ?></h3>
                        <p><strong><?= e(t('diagnosis.label_plant')) ?>:</strong> <span id="diagnosisResultPlantName"><?= e((string)($diagnosisResult['plant_name'] ?? t('diagnosis.unknown_plant'))) ?></span></p>
                        <div class="diagnosis-x-confidence-pill" id="diagnosisResultConfidencePill" role="img" aria-label="<?= e(t('diagnosis.label_confidence')) ?> <?= e(number_format($confidenceValue, 2)) ?>%">
                            <span id="diagnosisResultConfidence"><?= e(number_format($confidenceValue, 2)) ?></span>% <?= e(t('diagnosis.label_confidence')) ?>
                        </div>
                        <div class="diagnosis-x-confidence" id="diagnosisResultBar" role="img" aria-label="<?= e(t('diagnosis.label_confidence')) ?> <?= e(number_format($confidenceValue, 2)) ?>%">
                            <span id="diagnosisResultBarFill" style="width: <?= e((string)$confidenceValue) ?>%"></span>
                        </div>
                        <p><strong><?= e(t('diagnosis.label_description')) ?>:</strong> <span id="diagnosisResultDescription"><?= e((string)($diagnosisResult['description'] ?? t('diagnosis.not_available'))) ?></span></p>
                        <p><strong><?= e(t('diagnosis.label_cure')) ?>:</strong> <span id="diagnosisResultCureDetails"><?= e((string)($diagnosisResult['cure_details'] ?? ($diagnosisResult['treatment'] ?? t('diagnosis.not_available')))) ?></span></p>
                        <p>
                            <a class="diagnosis-x-google-link" id="diagnosisResultGoogleLink" href="<?= e($initialGoogleUrl) ?>" target="_blank" rel="noopener noreferrer">
                                <?= e(t('diagnosis.google_link')) ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="card diagnosis-x-fertilizer-panel" id="diagnosisResultFertilizer" <?= $hasResult ? '' : 'hidden' ?>>
                <div class="diagnosis-x-fertilizer-head">
                    <h4><?= e(t('diagnosis.fertilizer_title')) ?></h4>
                    <p><?= e(t('diagnosis.fertilizer_sub')) ?></p>
                </div>
                <div class="diagnosis-x-fertilizer-grid" id="diagnosisResultFertilizerGrid"></div>
                <div class="diagnosis-x-video-block" id="diagnosisResultVideos" hidden>
                    <div class="diagnosis-x-video-head">
                        <h5><?= e(t('diagnosis.video_title')) ?></h5>
                        <p><?= e(t('diagnosis.video_sub')) ?></p>
                    </div>
                    <div class="diagnosis-x-video-grid" id="diagnosisResultVideoGrid"></div>
                </div>
            </div>
        </div>

    </div>
</section>

<script>
(() => {
  'use strict';

  const ui = <?= json_encode($diagnosisUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const initialResult = <?= json_encode(is_array($diagnosisResult) ? $diagnosisResult : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  const dom = {
    input: document.getElementById('leafImageInput'),
    form: document.getElementById('diagnosisForm'),
    dropZone: document.getElementById('diagDropZone'),
    dropPlaceholder: document.getElementById('diagDropPlaceholder'),
    dropImage: document.getElementById('diagnosisDropImage'),
    geoState: document.getElementById('diagGeoState'),
    submitState: document.getElementById('diagSubmitState'),
    submitButton: document.querySelector('.diagnosis-x-submit'),
    inlineError: document.getElementById('diagInlineError'),
    resultCard: document.getElementById('diagnosisResultCard'),
    resultModel: document.getElementById('diagnosisResultModel'),
    resultImageWrap: document.getElementById('diagnosisResultImageWrap'),
    resultImage: document.getElementById('diagnosisResultImage'),
    resultDisease: document.getElementById('diagnosisResultDisease'),
    resultPlantName: document.getElementById('diagnosisResultPlantName'),
    resultConfidence: document.getElementById('diagnosisResultConfidence'),
    resultConfidencePill: document.getElementById('diagnosisResultConfidencePill'),
    resultBar: document.getElementById('diagnosisResultBar'),
    resultBarFill: document.getElementById('diagnosisResultBarFill'),
    resultDescription: document.getElementById('diagnosisResultDescription'),
    resultCureDetails: document.getElementById('diagnosisResultCureDetails'),
    resultGoogleLink: document.getElementById('diagnosisResultGoogleLink'),
    fertilizerPanel: document.getElementById('diagnosisResultFertilizer'),
    fertilizerGrid: document.getElementById('diagnosisResultFertilizerGrid'),
    videoBlock: document.getElementById('diagnosisResultVideos'),
    videoGrid: document.getElementById('diagnosisResultVideoGrid'),
    latitude: document.getElementById('latitude'),
    longitude: document.getElementById('longitude'),
  };

  let previewObjectUrl = '';

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    }[char]));
  }

  function resolveUrl(path) {
    const value = String(path ?? '').trim();
    if (!value) return '';

    try {
      return new URL(value, window.location.href).href;
    } catch {
      return value;
    }
  }

  function formatConfidence(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) return '0.00';
    return Math.max(0, Math.min(100, num)).toFixed(2);
  }

  function normalizeItems(items) {
    return Array.isArray(items) ? items.filter((item) => item && typeof item === 'object') : [];
  }

  function buildGoogleUrl(result) {
    const source = result && typeof result === 'object' ? result : {};
    const disease = String(source.disease_name || '').trim();
    const plant = String(source.plant_name || '').trim();
    const query = [disease, plant, 'plant disease treatment'].filter(Boolean).join(' ').trim();
    const fallback = 'plant disease treatment';
    return `https://www.google.com/search?q=${encodeURIComponent(query || fallback)}`;
  }

  function setText(node, value) {
    if (node) node.textContent = String(value ?? '');
  }

  function setInlineError(message) {
    if (!dom.inlineError) return;
    const text = String(message ?? '').trim();
    dom.inlineError.textContent = text;
    dom.inlineError.hidden = text === '';
  }

  function setBusy(isBusy) {
    const busy = Boolean(isBusy);

    if (dom.submitButton) {
      dom.submitButton.disabled = busy;
      dom.submitButton.textContent = busy
        ? (ui.loadingShort || ui.loading || 'Analyzing...')
        : (ui.analyze || 'Analyze');
    }

    if (dom.submitState) {
      dom.submitState.hidden = !busy;
      dom.submitState.textContent = busy
        ? (ui.loading || 'Analyzing image...')
        : '';
    }

    if (dom.input) {
      dom.input.disabled = busy;
    }

    if (dom.form) {
      dom.form.classList.toggle('is-busy', busy);
      dom.form.setAttribute('aria-busy', busy ? 'true' : 'false');
    }
  }

  function clearPreview() {
    if (previewObjectUrl) {
      URL.revokeObjectURL(previewObjectUrl);
      previewObjectUrl = '';
    }

    if (dom.dropImage) {
      dom.dropImage.hidden = true;
      dom.dropImage.removeAttribute('src');
    }

    if (dom.dropPlaceholder) {
      dom.dropPlaceholder.hidden = false;
    }

    if (dom.dropZone) {
      dom.dropZone.classList.remove('is-dragover', 'has-image');
    }
  }

  function showUploadedInPlace(file) {
    if (!file) {
      clearPreview();
      return;
    }

    clearPreview();
    previewObjectUrl = URL.createObjectURL(file);

    if (dom.dropImage) {
      dom.dropImage.src = previewObjectUrl;
      dom.dropImage.hidden = false;
    }

    if (dom.dropPlaceholder) {
      dom.dropPlaceholder.hidden = true;
    }

    if (dom.dropZone) {
      dom.dropZone.classList.add('has-image');
    }
  }

  function renderFertilizerGrid(items) {
    const list = normalizeItems(items);
    if (!list.length) return '';

    const defaultTitle = ui.defaultFertilizerTitle || 'View fertilizer';
    const defaultLink = ui.defaultFertilizerLink || 'View fertilizer';

    return list.map((item) => {
      const title = escapeHtml(item.title || defaultTitle);
      const subtitle = escapeHtml(item.subtitle || '');
      const details = escapeHtml(item.details || '');
      const imageUrl = resolveUrl(item.image_url || '');
      const linkUrl = escapeHtml(resolveUrl(item.link_url || '#'));
      const linkLabel = escapeHtml(item.link_label || defaultLink);

      return `
        <article class="diagnosis-x-fertilizer-card">
          <div class="diagnosis-x-fertilizer-media">
            <img src="${imageUrl}" alt="${title}">
          </div>
          <div class="diagnosis-x-fertilizer-copy">
            <h5>${title}</h5>
            ${subtitle ? `<div class="diagnosis-x-fertilizer-sub">${subtitle}</div>` : ''}
            ${details ? `<p>${details}</p>` : ''}
            <a class="diagnosis-x-fertilizer-link" href="${linkUrl}" target="_blank" rel="noopener noreferrer">${linkLabel}</a>
          </div>
        </article>
      `;
    }).join('');
  }

  function renderVideoGrid(items) {
    const list = normalizeItems(items);
    if (!list.length) return '';

    const defaultLink = ui.defaultVideoLink || 'Watch on YouTube';

    return list.map((item) => {
      const title = escapeHtml(item.title || ui.defaultVideoTitle || 'Related video');
      const subtitle = escapeHtml(item.subtitle || '');
      const details = escapeHtml(item.search_query || '');
      const imageUrl = resolveUrl(item.image_url || '');
      const embedUrl = escapeHtml(resolveUrl(item.embed_url || item.link_url || ''));
      const linkUrl = escapeHtml(resolveUrl(item.link_url || '#'));
      const linkLabel = escapeHtml(item.link_label || defaultLink);

      return `
        <article class="diagnosis-x-video-card">
          <div class="diagnosis-x-video-thumb-wrap">
            <img class="diagnosis-x-video-thumb" src="${escapeHtml(imageUrl)}" alt="${title}">
            <span class="diagnosis-x-video-thumb-play" aria-hidden="true"></span>
          </div>
          <div class="diagnosis-x-video-frame-wrap">
            <iframe
              class="diagnosis-x-video-preview"
              src="${embedUrl}"
              title="${title}"
              loading="lazy"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              referrerpolicy="strict-origin-when-cross-origin"
            ></iframe>
          </div>
          <div class="diagnosis-x-fertilizer-copy diagnosis-x-video-copy">
            <h5>${title}</h5>
            ${subtitle ? `<div class="diagnosis-x-fertilizer-sub">${subtitle}</div>` : ''}
            ${details ? `<p>${details}</p>` : ''}
            <a class="diagnosis-x-fertilizer-link" href="${linkUrl}" target="_blank" rel="noopener noreferrer">${linkLabel}</a>
          </div>
        </article>
      `;
    }).join('');
  }

  function renderFertilizerRecommendations(items) {
    if (!dom.fertilizerPanel || !dom.fertilizerGrid) return;

    const html = renderFertilizerGrid(items);
    dom.fertilizerGrid.innerHTML = html;
    dom.fertilizerPanel.hidden = html === '';
  }

  function renderVideoRecommendations(items) {
    if (!dom.videoBlock || !dom.videoGrid) return;

    const html = renderVideoGrid(items);
    dom.videoGrid.innerHTML = html;
    dom.videoBlock.hidden = html === '';
  }

  function renderResult(result, scrollIntoView = false) {
    if (!result || typeof result !== 'object') return;

    if (dom.resultCard) {
      dom.resultCard.hidden = false;
    }

    setText(dom.resultModel, result.model || 'plant.id');

    if (dom.resultImageWrap && dom.resultImage) {
      const imagePath = String(result.image_path ?? '').trim();
      if (imagePath) {
        dom.resultImage.src = resolveUrl(imagePath);
        dom.resultImageWrap.hidden = false;
      } else {
        dom.resultImage.removeAttribute('src');
        dom.resultImageWrap.hidden = true;
      }
    }

    setText(dom.resultDisease, result.disease_name || ui.notAvailable || '');
    setText(dom.resultPlantName, result.plant_name || ui.unknownPlant || '');

    const confidence = formatConfidence(result.confidence);
    setText(dom.resultConfidence, confidence);

    if (dom.resultConfidencePill) {
      dom.resultConfidencePill.setAttribute(
        'aria-label',
        `${ui.labelConfidence || 'Confidence'} ${confidence}%`
      );
    }

    if (dom.resultBarFill) {
      dom.resultBarFill.style.width = `${confidence}%`;
    }

    if (dom.resultBar) {
      dom.resultBar.setAttribute(
        'aria-label',
        `${ui.labelConfidence || 'Confidence'} ${confidence}%`
      );
    }

    setText(dom.resultDescription, result.description || ui.notAvailable || '');
    setText(dom.resultCureDetails, result.cure_details || result.treatment || ui.notAvailable || '');

    if (dom.resultGoogleLink) {
      dom.resultGoogleLink.href = buildGoogleUrl(result);
    }

    renderFertilizerRecommendations(result.fertilizer_recommendations);
    renderVideoRecommendations(result.video_recommendations);

    if (scrollIntoView && dom.resultCard && typeof dom.resultCard.scrollIntoView === 'function') {
      dom.resultCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function updateGeoState(message) {
    setText(dom.geoState, message);
  }

  function detectLocation() {
    if (!dom.latitude || !dom.longitude || !dom.geoState) return;

    if (!navigator.geolocation) {
      updateGeoState(ui.geoUnsupported || 'Geolocation not supported');
      return;
    }

    updateGeoState(ui.geoDetecting || 'Detecting location...');

    navigator.geolocation.getCurrentPosition(
      (position) => {
        dom.latitude.value = String(position.coords.latitude);
        dom.longitude.value = String(position.coords.longitude);
        updateGeoState(ui.geoDetected || 'Location detected');
      },
      () => {
        updateGeoState(ui.geoUnavailable || 'Location unavailable');
      },
      {
        enableHighAccuracy: false,
        timeout: 10000,
        maximumAge: 300000,
      }
    );
  }

  function syncSelectedFile() {
    if (!dom.input) return;

    const file = dom.input.files && dom.input.files[0] ? dom.input.files[0] : null;
    if (file) {
      setInlineError('');
      showUploadedInPlace(file);
      return;
    }

    clearPreview();
  }

  function assignFile(file) {
    if (!dom.input || !file) return;

    try {
      const dataTransfer = new DataTransfer();
      dataTransfer.items.add(file);
      dom.input.files = dataTransfer.files;
    } catch {
      // If the browser blocks DataTransfer assignment, we still keep the preview.
    }

    syncSelectedFile();
  }

  function bindDragAndDrop() {
    if (!dom.dropZone) return;

    const setDragState = (isDragging) => {
      dom.dropZone.classList.toggle('is-dragover', Boolean(isDragging));
    };

    ['dragenter', 'dragover'].forEach((eventName) => {
      dom.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        setDragState(true);
      });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
      dom.dropZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        setDragState(false);
      });
    });

    dom.dropZone.addEventListener('drop', (event) => {
      const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
      if (file) {
        assignFile(file);
      }
    });
  }

  function bindFormSubmit() {
    if (!dom.form) return;

    dom.form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const file = dom.input && dom.input.files ? dom.input.files[0] : null;
      if (!file) {
        setInlineError(ui.chooseImage || 'Choose an image first.');
        return;
      }

      setInlineError('');
      const formData = new FormData(dom.form);
      setBusy(true);

      try {
        const response = await fetch('api_predict.php?format=json', {
          method: 'POST',
          body: formData,
          headers: {
            Accept: 'application/json',
          },
        });

        let payload = null;
        try {
          payload = await response.json();
        } catch {
          payload = null;
        }

        if (!response.ok || !payload || payload.ok === false) {
          const message = payload && payload.message ? payload.message : (ui.analyzeFailed || 'Could not analyze the image.');
          throw new Error(message);
        }

        renderResult(payload.data && typeof payload.data === 'object' ? payload.data : payload, true);
      } catch (error) {
        setInlineError(error instanceof Error && error.message ? error.message : (ui.analyzeFailed || 'Could not analyze the image.'));
      } finally {
        setBusy(false);
      }
    });
  }

  function init() {
    if (!dom.form || !dom.input) return;

    bindDragAndDrop();
    bindFormSubmit();

    dom.input.addEventListener('change', syncSelectedFile);
    window.addEventListener('beforeunload', () => {
      if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = '';
      }
    });

    if (initialResult) {
      renderResult(initialResult, false);
    }

    detectLocation();
    setBusy(false);
    setInlineError('');
    syncSelectedFile();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
</script>
<?php require __DIR__ . '/../app/views/footer.php'; ?>
