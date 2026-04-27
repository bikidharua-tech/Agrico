<?php
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

if (!$lat || !$lon) {
    echo json_encode(['error' => t('weather.latlon_required')], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_numeric($lat) || !is_numeric($lon)) {
    echo json_encode(['error' => t('weather.latlon_numeric')], JSON_UNESCAPED_UNICODE);
    exit;
}
$lat = (float)$lat;
$lon = (float)$lon;
if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    echo json_encode(['error' => t('weather.latlon_range')], JSON_UNESCAPED_UNICODE);
    exit;
}

$units = $_GET['units'] ?? 'metric'; // metric | imperial
$tempUnit = '';
$windUnit = '';
$precipUnit = '';
if ($units === 'imperial') {
    $tempUnit = '&temperature_unit=fahrenheit';
    $windUnit = '&wind_speed_unit=mph';
    $precipUnit = '&precipitation_unit=inch';
}

$url =
    'https://api.open-meteo.com/v1/forecast?latitude=' . urlencode((string)$lat) .
    '&longitude=' . urlencode((string)$lon) .
    '&current=temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,wind_speed_10m,wind_direction_10m' .
    '&hourly=relative_humidity_2m,cloud_cover,wind_speed_10m,precipitation' .
    '&daily=weather_code,temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_probability_max,uv_index_max' .
    '&forecast_days=7&timezone=auto' .
    $tempUnit . $windUnit . $precipUnit;

$ctx = stream_context_create([
    'http' => [
        'timeout' => 8,
        'header' => "User-Agent: Agrico\r\n",
    ],
]);
$resp = @file_get_contents($url, false, $ctx);
if ($resp === false) {
    echo json_encode(['error' => t('weather.api_unavailable')], JSON_UNESCAPED_UNICODE);
    exit;
}

// Enrich the Open-Meteo payload with daily aggregates (humidity/cloud/wind/rain),
// so the UI can render a "details table" without exposing raw lat/lon inputs.
$data = json_decode($resp, true);
if (!is_array($data)) {
    echo json_encode(['error' => t('weather.invalid_response')], JSON_UNESCAPED_UNICODE);
    exit;
}

$dailyTimes = $data['daily']['time'] ?? [];
$hourly = $data['hourly'] ?? [];
$hourlyTimes = $hourly['time'] ?? [];

$agg = [];
for ($i = 0; $i < count($hourlyTimes); $i++) {
    $t = (string)$hourlyTimes[$i];
    $day = substr($t, 0, 10);
    if ($day === '') continue;

    if (!isset($agg[$day])) {
        $agg[$day] = [
            'hum_sum' => 0.0, 'hum_n' => 0,
            'cloud_sum' => 0.0, 'cloud_n' => 0,
            'wind_sum' => 0.0, 'wind_n' => 0,
            'rain_sum' => 0.0,
        ];
    }

    if (isset($hourly['relative_humidity_2m'][$i]) && is_numeric($hourly['relative_humidity_2m'][$i])) {
        $agg[$day]['hum_sum'] += (float)$hourly['relative_humidity_2m'][$i];
        $agg[$day]['hum_n'] += 1;
    }
    if (isset($hourly['cloud_cover'][$i]) && is_numeric($hourly['cloud_cover'][$i])) {
        $agg[$day]['cloud_sum'] += (float)$hourly['cloud_cover'][$i];
        $agg[$day]['cloud_n'] += 1;
    }
    if (isset($hourly['wind_speed_10m'][$i]) && is_numeric($hourly['wind_speed_10m'][$i])) {
        $agg[$day]['wind_sum'] += (float)$hourly['wind_speed_10m'][$i];
        $agg[$day]['wind_n'] += 1;
    }
    if (isset($hourly['precipitation'][$i]) && is_numeric($hourly['precipitation'][$i])) {
        $agg[$day]['rain_sum'] += (float)$hourly['precipitation'][$i];
    }
}

// Build daily arrays aligned with daily.time
$humMean = [];
$cloudMean = [];
$windMean = [];
$rainSum = [];
for ($d = 0; $d < count($dailyTimes); $d++) {
    $day = (string)$dailyTimes[$d];
    $a = $agg[$day] ?? null;
    if (!$a) {
        $humMean[] = null;
        $cloudMean[] = null;
        $windMean[] = null;
        $rainSum[] = null;
        continue;
    }

    $humMean[] = $a['hum_n'] ? round($a['hum_sum'] / $a['hum_n'], 1) : null;
    $cloudMean[] = $a['cloud_n'] ? round($a['cloud_sum'] / $a['cloud_n'], 1) : null;
    $windMean[] = $a['wind_n'] ? round($a['wind_sum'] / $a['wind_n'], 1) : null;
    $rainSum[] = round($a['rain_sum'], 1);
}

if (!isset($data['daily']) || !is_array($data['daily'])) $data['daily'] = [];
$data['daily']['relative_humidity_2m_mean'] = $humMean;
$data['daily']['cloud_cover_mean'] = $cloudMean;
$data['daily']['wind_speed_10m_mean'] = $windMean;
$data['daily']['precipitation_sum'] = $rainSum;

if (!isset($data['daily_units']) || !is_array($data['daily_units'])) $data['daily_units'] = [];
$data['daily_units']['relative_humidity_2m_mean'] = '%';
$data['daily_units']['cloud_cover_mean'] = '%';
$data['daily_units']['wind_speed_10m_mean'] = $data['current_units']['wind_speed_10m'] ?? '';
$data['daily_units']['precipitation_sum'] = $data['current_units']['precipitation'] ?? '';

echo json_encode($data, JSON_UNESCAPED_UNICODE);
