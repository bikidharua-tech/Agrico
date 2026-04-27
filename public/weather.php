<?php
require __DIR__ . '/../app/bootstrap.php';
$title = t('nav.weather');
require __DIR__ . '/../app/views/header.php';
?>

<section class="container section">
    <div class="card wx2">
        <div class="wx2-top" id="wxTop" hidden>
            <div class="wx2-place">
                <span class="wx2-pin" aria-hidden="true"></span>
                <div>
                    <div class="wx2-place-name" id="wxPlaceName"><?= e(t('weather.location')) ?></div>
                    <div class="wx2-place-sub" id="wxPlaceSub"><?= e(t('weather.current_weather')) ?></div>
                </div>
            </div>
            <div class="wx2-top-actions">
                <button class="btn btn-ghost" type="button" id="wxUseDeviceTop"><?= e(t('weather.use_device_location')) ?></button>
                <button class="btn btn-grad" type="button" id="wxEnterLocation"><?= e(t('weather.enter_location')) ?></button>
                <button class="btn btn-ghost wx2-mini" type="button" id="wxRefresh" title="<?= e(t('weather.refresh')) ?>" aria-label="<?= e(t('weather.refresh')) ?>">&#8635;</button>
            </div>
        </div>

        <div class="wx2-locpanel" id="wxLocPanel" hidden>
            <div class="wx2-locpanel-head">
                <div class="wx2-locpanel-title"><?= e(t('weather.enter_your_location')) ?></div>
                <button class="wx2-locpanel-close" type="button" id="wxLocClose" aria-label="<?= e(t('common.close')) ?>">x</button>
            </div>
            <div class="wx2-locpanel-body">
                <label class="wx2-label"><?= e(t('weather.city')) ?>
                    <input id="wxCity" class="input" placeholder="<?= e(t('weather.city_placeholder')) ?>" autocomplete="off">
                </label>
                <div class="wx2-hint"><?= e(t('weather.type_city_pick')) ?></div>
                <div class="wx2-locpanel-actions">
                    <button class="btn btn-ghost" type="button" id="wxLocDevice"><?= e(t('weather.use_device_location')) ?></button>
                    <button class="btn btn-grad" type="button" id="wxLocSearch"><?= e(t('common.search')) ?></button>
                </div>
                <div id="wxCityResults" class="wx2-results" hidden></div>
            </div>
        </div>

        <div class="wx2-body">
            <div id="wxStatus" class="notice" hidden></div>

            <div id="wxGate" class="wx2-gate">
                <div class="wx2-gate-ico" aria-hidden="true"></div>
                <h2 class="wx2-gate-title"><?= e(t('weather.get_location')) ?></h2>
                <p class="wx2-gate-sub"><?= e(t('weather.gate_sub')) ?></p>
                <div class="wx2-gate-actions">
                    <button class="btn btn-grad wx2-allow" type="button" id="wxAllow"><?= e(t('weather.allow_location')) ?></button>
                </div>
            </div>

            <div id="wxContent" hidden>
                <div id="wxNow" class="wx2-now"></div>

                <div class="wx2-table-wrap">
                    <table class="wx2-table" id="wxTable">
                        <thead>
                        <tr>
                            <th><?= e(t('weather.date')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-hi" aria-hidden="true"></span><?= e(t('weather.max')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-lo" aria-hidden="true"></span><?= e(t('weather.min')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-avg" aria-hidden="true"></span><?= e(t('weather.avg')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-hum" aria-hidden="true"></span><?= e(t('weather.humidity')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-wind" aria-hidden="true"></span><?= e(t('weather.wind')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-rain" aria-hidden="true"></span><?= e(t('weather.rain')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-uv" aria-hidden="true"></span><?= e(t('weather.uv')) ?></th>
                            <th><span class="wx2-th-ico wx2-i-cloud" aria-hidden="true"></span><?= e(t('weather.cloud')) ?></th>
                        </tr>
                        </thead>
                        <tbody id="wxTbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const I18N = <?= json_encode([
  'selected_location' => t('weather.selected_location'),
  'current_weather' => t('weather.current_weather'),
  'feels_like' => t('weather.feels_like'),
  'humidity' => t('weather.humidity'),
  'wind' => t('weather.wind'),
  'rain' => t('weather.rain'),
  'updated' => t('weather.updated'),
  'no_results' => t('weather.no_results'),
  'request_failed' => t('weather.request_failed'),
  'geo_not_supported' => t('weather.geo_not_supported'),
  'permission_denied' => t('weather.permission_denied'),
  'city_search_failed' => t('weather.city_search_failed'),
  'your_location' => t('weather.your_location'),
], JSON_UNESCAPED_UNICODE) ?>;
const LOCALE = <?= json_encode(current_locale(), JSON_UNESCAPED_UNICODE) ?>;

const el = (id) => document.getElementById(id);
const statusEl = el('wxStatus');
const gateEl = el('wxGate');
const contentEl = el('wxContent');
const topEl = el('wxTop');
const placeNameEl = el('wxPlaceName');
const placeSubEl = el('wxPlaceSub');
const nowEl = el('wxNow');
const tbodyEl = el('wxTbody');

const locPanelEl = el('wxLocPanel');
const cityEl = el('wxCity');
const resultsEl = el('wxCityResults');

let state = {
  lat: null,
  lon: null,
  label: '',
  country: '',
  timezone: ''
};

function setStatus(type, msg) {
  statusEl.hidden = !msg;
  statusEl.classList.toggle('error', type === 'error');
  statusEl.textContent = msg || '';
}

function setBusy(busy) {
  for (const id of ['wxAllow','wxUseDeviceTop','wxEnterLocation','wxRefresh','wxLocDevice','wxLocSearch','wxLocClose']) {
    const b = el(id);
    if (b) b.disabled = busy;
  }
  document.body.classList.toggle('wx2-busy', busy);
}

function safeNum(n) { const x = Number(n); return Number.isFinite(x) ? x : null; }
function round1(n) { return Math.round(n * 10) / 10; }

function fmtDay(iso) {
  const d = new Date(iso + 'T00:00:00');
  if (String(d) === 'Invalid Date') return iso;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function degToCompass(deg) {
  const d = safeNum(deg);
  if (d === null) return '-';
  const dirs = ['N','NE','E','SE','S','SW','W','NW'];
  const idx = Math.round(((d % 360) / 45)) % 8;
  return dirs[idx];
}

function showWeatherShell() {
  gateEl.hidden = true;
  contentEl.hidden = false;
  topEl.hidden = false;
}
function showGate() {
  gateEl.hidden = false;
  contentEl.hidden = true;
  topEl.hidden = true;
}

function showLocPanel(show) {
  locPanelEl.hidden = !show;
  if (show) {
    resultsEl.hidden = true;
    resultsEl.innerHTML = '';
    setTimeout(() => cityEl.focus(), 0);
  }
}

function setPlaceLabel() {
  placeNameEl.textContent = state.label || I18N.selected_location;
  placeSubEl.textContent = I18N.current_weather + (state.timezone ? (' - ' + state.timezone) : '');
}

function renderNow(data) {
  const c = data.current || {};
  const cu = data.current_units || {};

  const temp = safeNum(c.temperature_2m);
  const feels = safeNum(c.apparent_temperature);
  const hum = safeNum(c.relative_humidity_2m);
  const wind = safeNum(c.wind_speed_10m);
  const windDir = degToCompass(c.wind_direction_10m);
  const precip = safeNum(c.precipitation);

  nowEl.innerHTML = `
    <div class="wx2-now-main">
      <div class="wx2-now-temp">${temp === null ? '-' : round1(temp)}<span>${cu.temperature_2m || ''}</span></div>
      <div class="wx2-now-sub">${feels === null ? '' : (I18N.feels_like + ' ' + round1(feels) + (cu.apparent_temperature || cu.temperature_2m || ''))}</div>
    </div>
    <div class="wx2-now-kpis">
      <div class="wx2-kpi"><span>${I18N.humidity}</span><strong>${hum === null ? '-' : hum + (cu.relative_humidity_2m || '%')}</strong></div>
      <div class="wx2-kpi"><span>${I18N.wind}</span><strong>${wind === null ? '-' : round1(wind) + ' ' + (cu.wind_speed_10m || '')} ${windDir}</strong></div>
      <div class="wx2-kpi"><span>${I18N.rain}</span><strong>${precip === null ? '-' : round1(precip) + ' ' + (cu.precipitation || '')}</strong></div>
      <div class="wx2-kpi"><span>${I18N.updated}</span><strong>${c.time ? new Date(c.time).toLocaleString() : '-'}</strong></div>
    </div>
  `;
}

function renderTable(data) {
  const d = data.daily || {};
  const du = data.daily_units || {};

  const time = Array.isArray(d.time) ? d.time : [];
  const tMax = Array.isArray(d.temperature_2m_max) ? d.temperature_2m_max : [];
  const tMin = Array.isArray(d.temperature_2m_min) ? d.temperature_2m_min : [];
  const tMean = Array.isArray(d.temperature_2m_mean) ? d.temperature_2m_mean : [];
  const hum = Array.isArray(d.relative_humidity_2m_mean) ? d.relative_humidity_2m_mean : [];
  const wind = Array.isArray(d.wind_speed_10m_mean) ? d.wind_speed_10m_mean : [];
  const rain = Array.isArray(d.precipitation_sum) ? d.precipitation_sum : [];
  const uv = Array.isArray(d.uv_index_max) ? d.uv_index_max : [];
  const cloud = Array.isArray(d.cloud_cover_mean) ? d.cloud_cover_mean : [];

  const tUnit = du.temperature_2m_max || '';
  const humUnit = du.relative_humidity_2m_mean || '%';
  const windUnit = du.wind_speed_10m_mean || '';
  const rainUnit = du.precipitation_sum || '';
  const cloudUnit = du.cloud_cover_mean || '%';

  tbodyEl.innerHTML = '';
  for (let i = 0; i < time.length; i++) {
    const hi = safeNum(tMax[i]);
    const lo = safeNum(tMin[i]);
    const avg = safeNum(tMean[i]);
    const avgFallback = (hi !== null && lo !== null) ? round1((hi + lo) / 2) : null;
    const h = safeNum(hum[i]);
    const w = safeNum(wind[i]);
    const r = safeNum(rain[i]);
    const u = safeNum(uv[i]);
    const cc = safeNum(cloud[i]);

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="wx2-td-date">${fmtDay(time[i])}</td>
      <td>${hi === null ? '-' : round1(hi)}${tUnit}</td>
      <td>${lo === null ? '-' : round1(lo)}${tUnit}</td>
      <td>${avg === null ? (avgFallback === null ? '-' : avgFallback) : round1(avg)}${tUnit}</td>
      <td>${h === null ? '-' : round1(h)}${humUnit}</td>
      <td>${w === null ? '-' : round1(w)} ${windUnit}</td>
      <td>${r === null ? '-' : round1(r)} ${rainUnit}</td>
      <td>${u === null ? '-' : round1(u)}</td>
      <td>${cc === null ? '-' : round1(cc)}${cloudUnit}</td>
    `;
    tbodyEl.appendChild(tr);
  }
}

async function fetchWeather() {
  if (state.lat === null || state.lon === null) return;

  setStatus('', '');
  setBusy(true);
  try {
    const res = await fetch(`api_weather.php?lat=${encodeURIComponent(state.lat)}&lon=${encodeURIComponent(state.lon)}&units=metric`);
    const data = await res.json();
    if (data.error) {
      setStatus('error', data.error);
      showGate();
      return;
    }

    state.timezone = data.timezone || '';
    setPlaceLabel();
    showWeatherShell();
    renderNow(data);
    renderTable(data);
  } catch (e) {
    setStatus('error', I18N.request_failed);
    showGate();
  } finally {
    setBusy(false);
  }
}

function useDeviceLocation() {
  setStatus('', '');
  if (!navigator.geolocation) {
    setStatus('error', I18N.geo_not_supported);
    return;
  }
  setBusy(true);
  navigator.geolocation.getCurrentPosition(
    (p) => {
      state.lat = round1(p.coords.latitude);
      state.lon = round1(p.coords.longitude);
      state.label = I18N.your_location;
      showLocPanel(false);
      fetchWeather();
    },
    () => {
      setStatus('error', I18N.permission_denied);
      setBusy(false);
    },
    { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }
  );
}

async function searchCity() {
  const q = cityEl.value.trim();
  if (!q) return;

  setStatus('', '');
  setBusy(true);
  try {
    const res = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(q)}&count=6&language=${encodeURIComponent(LOCALE)}&format=json`);
    const data = await res.json();
    const results = data.results || [];

    resultsEl.innerHTML = '';
    resultsEl.hidden = false;

    if (!results.length) {
      resultsEl.innerHTML = `<div class="wx2-empty">${I18N.no_results}</div>`;
      return;
    }

    for (const r of results) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'wx2-result';
      const label = [r.name, r.admin1, r.country].filter(Boolean).join(', ');
      btn.innerHTML = `<strong>${label}</strong>`;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        state.lat = round1(r.latitude);
        state.lon = round1(r.longitude);
        state.label = label;
        showLocPanel(false);
        fetchWeather();
      });
      resultsEl.appendChild(btn);
    }
  } catch (e) {
    setStatus('error', I18N.city_search_failed);
  } finally {
    setBusy(false);
  }
}

el('wxAllow').addEventListener('click', useDeviceLocation);
el('wxUseDeviceTop').addEventListener('click', useDeviceLocation);
el('wxEnterLocation').addEventListener('click', () => showLocPanel(true));
el('wxLocClose').addEventListener('click', () => showLocPanel(false));
el('wxLocDevice').addEventListener('click', useDeviceLocation);
el('wxLocSearch').addEventListener('click', searchCity);
el('wxRefresh').addEventListener('click', fetchWeather);
cityEl.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    searchCity();
  }
});

// Default: show gate until user picks location.
showGate();
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
