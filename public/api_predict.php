<?php
require __DIR__ . '/../app/bootstrap.php';

function wants_json_response(): bool {
    $format = isset($_GET['format']) ? strtolower(trim((string)$_GET['format'])) : '';
    if ($format === 'json') {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return strpos($accept, 'application/json') !== false;
}

function respond_prediction(bool $ok, string $message, array $data = [], int $status = 200): void {
    if (wants_json_response()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    set_flash($ok ? 'success' : 'error', $message);
    if ($ok) {
        $_SESSION['diagnosis_result'] = $data;
    }
    redirect('diagnose.php');
}

function fail_prediction(string $message, int $status = 422): void {
    if (wants_json_response()) {
        respond_prediction(false, $message, [], $status);
    }

    set_flash('error', $message);
    redirect('diagnose.php');
}

function normalize_confidence_percent($value): float {
    if (is_string($value)) {
        $value = trim($value);
    }

    if (!is_numeric($value)) {
        return 0.0;
    }

    $confidence = (float)$value;
    if ($confidence > 1.0) {
        return max(0.0, min(100.0, $confidence));
    }

    return max(0.0, min(100.0, $confidence * 100.0));
}

function text_contains(string $haystack, string $needle): bool {
    return $needle === '' || stripos($haystack, $needle) !== false;
}

function disease_category(string $diseaseName): string {
    $normalized = strtolower(trim($diseaseName));

    if ($normalized === '' || text_contains($normalized, 'uncertain') || text_contains($normalized, 'unknown')) {
        return 'uncertain';
    }
    if (text_contains($normalized, 'healthy')) {
        return 'healthy';
    }
    if (preg_match('/deficien|chlorosis|yellowing|nutrient|iron|magnesium|zinc/', $normalized)) {
        return 'deficiency';
    }
    if (preg_match('/virus|viral|mosaic|curl/', $normalized)) {
        return 'viral';
    }
    if (preg_match('/bacter|canker|ooze/', $normalized)) {
        return 'bacterial';
    }
    if (preg_match('/nematode|wilt|root|fusarium|damping/', $normalized)) {
        return 'root';
    }
    if (preg_match('/blight|mildew|rust|spot|scab|anthracnose|fung|rot|alternaria|powdery|downy/', $normalized)) {
        return 'fungal';
    }

    return 'general';
}

function build_disease_copy(string $category): array {
    $copy = [
        'healthy' => [
            'name' => 'Healthy plant',
            'description' => 'The uploaded leaf does not show clear disease symptoms.',
            'treatment' => 'Keep regular watering, sunlight, and airflow.',
        ],
        'uncertain' => [
            'name' => 'Uncertain disease pattern',
            'description' => 'The image shows some stress signals, but the disease pattern is not yet clear.',
            'treatment' => 'Monitor the plant for a few days and re-upload a clearer image if symptoms worsen.',
        ],
        'fungal' => [
            'name' => 'Fungal disease pattern',
            'description' => 'The leaf appears to show symptoms often linked to fungal stress.',
            'treatment' => 'Remove infected leaves, improve airflow, avoid overhead watering, and use an appropriate fungicide if needed.',
        ],
        'bacterial' => [
            'name' => 'Bacterial disease pattern',
            'description' => 'The leaf appears to show symptoms often linked to bacterial infection.',
            'treatment' => 'Remove badly affected leaves, disinfect tools, avoid leaf wetness, and isolate the plant if the issue spreads.',
        ],
        'viral' => [
            'name' => 'Viral disease pattern',
            'description' => 'The leaf appears to show symptoms often linked to viral stress.',
            'treatment' => 'Remove heavily infected plants if needed, control insect vectors, and avoid using cuttings from affected plants.',
        ],
        'deficiency' => [
            'name' => 'Nutrient deficiency pattern',
            'description' => 'The leaf appears to show symptoms often linked to nutrient imbalance.',
            'treatment' => 'Adjust fertilization for the crop and make sure the plant receives the nutrients it lacks.',
        ],
        'root' => [
            'name' => 'Root stress pattern',
            'description' => 'The leaf appears to show symptoms often linked to root stress or wilting.',
            'treatment' => 'Check soil drainage, reduce overwatering, and inspect roots for rot or compaction.',
        ],
        'general' => [
            'name' => 'Plant stress pattern',
            'description' => 'The leaf appears to show general plant stress symptoms.',
            'treatment' => 'Observe the plant closely, improve growing conditions, and recheck symptoms after a few days.',
        ],
    ];

    return isset($copy[$category]) ? $copy[$category] : $copy['general'];
}

function build_fertilizer_recommendations(string $category, string $plantName): array {
    $baseSearch = rawurlencode(trim($plantName . ' ' . $category . ' fertilizer'));

    return [
        [
            'title' => 'Balanced NPK 19-19-19',
            'subtitle' => 'General support',
            'details' => 'Useful when the plant needs a balanced feeding program after stress.',
            'image_url' => 'assets/img/organic fertilizer.jpeg',
            'link_url' => 'https://www.google.com/search?q=' . $baseSearch,
            'link_label' => 'View fertilizer',
        ],
        [
            'title' => 'Vermicompost / Organic Compost',
            'subtitle' => 'Soil health booster',
            'details' => 'Helps improve soil structure and supports slow nutrient release.',
            'image_url' => 'assets/img/bannaa.jpg',
            'link_url' => 'https://www.google.com/search?q=' . rawurlencode($plantName . ' organic compost'),
            'link_label' => 'View fertilizer',
        ],
        [
            'title' => 'Seaweed Extract / Biostimulant',
            'subtitle' => 'Stress recovery support',
            'details' => 'Can help plants under stress maintain vigor and recover faster.',
            'image_url' => 'assets/img/yt.jpg',
            'link_url' => 'https://www.google.com/search?q=' . rawurlencode($plantName . ' seaweed extract'),
            'link_label' => 'View fertilizer',
        ],
    ];
}

function build_video_recommendations(string $diseaseName, string $plantName): array {
    return [];
}

function plantid_extract_prediction(array $decoded): array {
    $results = [];

    if (!empty($decoded['result']['classification']['suggestions']) && is_array($decoded['result']['classification']['suggestions'])) {
        $results = $decoded['result']['classification']['suggestions'];
    } elseif (!empty($decoded['suggestions']) && is_array($decoded['suggestions'])) {
        $results = $decoded['suggestions'];
    } elseif (!empty($decoded['result']['suggestions']) && is_array($decoded['result']['suggestions'])) {
        $results = $decoded['result']['suggestions'];
    }

    $top = is_array($results) && !empty($results) && is_array($results[0]) ? $results[0] : [];
    $plantName = trim((string)($top['plant_name'] ?? $top['scientific_name'] ?? $decoded['input']['plant_name'] ?? 'Unknown plant'));
    $diseaseName = trim((string)($top['name'] ?? $top['label'] ?? $top['scientific_name'] ?? 'Uncertain disease pattern'));
    $confidence = normalize_confidence_percent($top['probability'] ?? $top['confidence'] ?? 0);

    if ($plantName === '') {
        $plantName = 'Unknown plant';
    }
    if ($diseaseName === '') {
        $diseaseName = 'Uncertain disease pattern';
    }

    $category = disease_category($diseaseName);
    $copy = build_disease_copy($category);

    return [
        'plant_name' => $plantName,
        'disease_name' => $category === 'healthy' ? $copy['name'] : $diseaseName,
        'confidence' => $confidence,
        'description' => $copy['description'],
        'cure_details' => $copy['treatment'],
        'treatment' => $copy['treatment'],
        'fertilizer_recommendations' => build_fertilizer_recommendations($category, $plantName),
        'video_recommendations' => build_video_recommendations($diseaseName, $plantName),
        'model' => 'plant.id',
    ];
}

function call_plantid_health_assessment(string $imageFilePath, ?float $lat = null, ?float $lon = null): array {
    global $config;

    $plantConfig = isset($config['plant_id']) && is_array($config['plant_id']) ? $config['plant_id'] : [];
    $plantEnabled = !isset($plantConfig['enabled']) || (bool)$plantConfig['enabled'];
    if (!$plantEnabled) {
        throw new RuntimeException('Plant.id is disabled in config.');
    }

    $apiUrl = rtrim((string)($plantConfig['api_url'] ?? 'https://plant.id/api/v3'), '/');
    $apiKey = '';
    if (!empty($plantConfig['api_key']) && is_scalar($plantConfig['api_key'])) {
        $apiKey = trim((string)$plantConfig['api_key']);
    }
    if ($apiKey === '') {
        $envKey = getenv('PLANT_ID_API_KEY');
        if (is_string($envKey)) {
            $apiKey = trim($envKey);
        }
    }
    if ($apiKey === '') {
        throw new RuntimeException('Plant.id API key is missing in config.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for Plant.id integration.');
    }

    $binary = @file_get_contents($imageFilePath);
    if ($binary === false || $binary === '') {
        throw new RuntimeException('Failed to read uploaded image for diagnosis.');
    }

    $payload = [
        'images' => [base64_encode($binary)],
        'latitude' => $lat,
        'longitude' => $lon,
        'health' => 'all',
        'disease_model' => 'full',
        'classification_level' => 'species',
        'similar_images' => true,
        'symptoms' => true,
    ];

    $payload = array_filter($payload, static function ($value) {
        return $value !== null;
    });

    $ch = curl_init($apiUrl . '/identification');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Api-Key: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Plant.id cURL error: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        $preview = trim(preg_replace('/\s+/', ' ', substr((string)$response, 0, 220)));
        throw new RuntimeException('Plant.id returned non-JSON response' . ($contentType !== '' ? ' (' . $contentType . ')' : '') . ($preview !== '' ? ': ' . $preview : ''));
    }

    if ($status >= 400) {
        $detail = '';
        if (!empty($decoded['message']) && is_string($decoded['message'])) {
            $detail = $decoded['message'];
        } elseif (!empty($decoded['error']) && is_string($decoded['error'])) {
            $detail = $decoded['error'];
        } elseif (!empty($decoded['detail']) && is_string($decoded['detail'])) {
            $detail = $decoded['detail'];
        }

        throw new RuntimeException('Plant.id HTTP ' . $status . ($detail !== '' ? ' - ' . $detail : ''));
    }

    return plantid_extract_prediction($decoded);
}

function build_local_fallback_prediction(string $plantName = 'Unknown plant'): array {
    $copy = build_disease_copy('general');
    return [
        'plant_name' => $plantName,
        'disease_name' => 'Uncertain disease pattern',
        'confidence' => 55.0,
        'description' => $copy['description'],
        'cure_details' => $copy['treatment'],
        'treatment' => $copy['treatment'],
        'fertilizer_recommendations' => build_fertilizer_recommendations('general', $plantName),
        'video_recommendations' => [],
        'model' => 'local_fallback',
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail_prediction('Method not allowed', 405);
}

$payload = [];
$rawBody = file_get_contents('php://input');
if (is_string($rawBody) && trim($rawBody) !== '') {
    $decodedBody = json_decode($rawBody, true);
    if (is_array($decodedBody)) {
        $payload = $decodedBody;
        $_POST = $payload + $_POST;
    }
}

validate_csrf();

if (empty($_FILES['leaf_image']['name'])) {
    fail_prediction(t('flash.image_required'));
}
if ((int)($_FILES['leaf_image']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    fail_prediction(t('flash.image_required'));
}
if ((int)($_FILES['leaf_image']['size'] ?? 0) > 5 * 1024 * 1024) {
    fail_prediction(t('flash.image_size'));
}

$tmpPath = (string)($_FILES['leaf_image']['tmp_name'] ?? '');
if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
    fail_prediction(t('flash.image_required'));
}

$detectedMime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detectedMime = (string)finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
    }
}

$allowedMimes = ['image/jpeg', 'image/png'];
$mime = in_array($detectedMime, $allowedMimes, true) ? $detectedMime : (string)($_FILES['leaf_image']['type'] ?? '');
if (!in_array($mime, $allowedMimes, true)) {
    fail_prediction(t('flash.image_type'));
}

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$fileName = uniqid('diag_', true) . '.' . $ext;
$relPath = 'uploads/diagnosis/' . $fileName;
$dest = public_file_path($relPath);
$dir = dirname($dest);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

if (!move_uploaded_file($tmpPath, $dest)) {
    fail_prediction(t('flash.image_required'));
}

$lat = ($_POST['latitude'] ?? '') !== '' ? (float)$_POST['latitude'] : null;
$lon = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;
ensure_predictions_schema();

try {
    $prediction = call_plantid_health_assessment($dest, $lat, $lon);
} catch (Throwable $e) {
    error_log('[Plant.id] ' . $e->getMessage());
    $prediction = build_local_fallback_prediction();
}

if (!empty($prediction['invalid_image'])) {
    if (is_file($dest)) {
        @unlink($dest);
    }
    fail_prediction((string)($prediction['error_message'] ?? t('diagnosis.not_available')), 422);
}

$user = current_user();
$hasModelSource = table_has_column('predictions', 'model_source');
if ($hasModelSource) {
    $stmt = db()->prepare('INSERT INTO predictions (user_id, image_path, disease_name, confidence, disease_description, treatment_recommendation, model_source, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'],
        $relPath,
        (string)$prediction['disease_name'],
        (float)($prediction['confidence'] ?? 0),
        $prediction['description'] ?? null,
        $prediction['treatment'] ?? null,
        (string)($prediction['model'] ?? 'plant.id'),
        $lat,
        $lon,
    ]);
} else {
    $stmt = db()->prepare('INSERT INTO predictions (user_id, image_path, disease_name, confidence, disease_description, treatment_recommendation, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'],
        $relPath,
        (string)$prediction['disease_name'],
        (float)($prediction['confidence'] ?? 0),
        $prediction['description'] ?? null,
        $prediction['treatment'] ?? null,
        $lat,
        $lon,
    ]);
}

$confidence = round((float)($prediction['confidence'] ?? 0), 2);
$result = [
    'disease_name' => (string)$prediction['disease_name'],
    'plant_name' => (string)($prediction['plant_name'] ?? 'Unknown plant'),
    'confidence' => $confidence,
    'description' => (string)($prediction['description'] ?? ''),
    'cure_details' => (string)($prediction['cure_details'] ?? ($prediction['treatment'] ?? '')),
    'treatment' => (string)($prediction['treatment'] ?? ''),
    'fertilizer_recommendations' => is_array($prediction['fertilizer_recommendations'] ?? null) ? $prediction['fertilizer_recommendations'] : [],
    'video_recommendations' => is_array($prediction['video_recommendations'] ?? null) ? $prediction['video_recommendations'] : [],
    'model' => (string)($prediction['model'] ?? 'plant.id'),
    'image_path' => $relPath,
];

$_SESSION['diagnosis_result'] = $result;
respond_prediction(true, t('flash.diagnosis_done') . ': ' . $result['disease_name'] . ' (' . $confidence . '%)', $result, 200);
