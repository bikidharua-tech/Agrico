<?php
require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = [];
}

$_POST = $payload + $_POST;
validate_csrf();

$prompt = trim((string)($payload['prompt'] ?? ''));
if ($prompt === '') {
    http_response_code(422);
    echo json_encode(['error' => t('leafbot.error_prompt')]);
    exit;
}

global $config;
$openAiApiKey = trim((string)($config['openai_api_key'] ?? ''));
$openAiModel = trim((string)($config['openai_model'] ?? 'gpt-4.1-mini'));
$geminiApiKey = trim((string)($config['gemini_api_key'] ?? ''));
$geminiModel = trim((string)($config['gemini_model'] ?? 'gemini-2.5-flash'));

if ($openAiApiKey === '' && $geminiApiKey === '') {
    http_response_code(503);
    echo json_encode(['error' => t('leafbot.status_missing_key')]);
    exit;
}

$history = $payload['history'] ?? [];
$messages = [];

if (is_array($history)) {
    foreach (array_slice($history, -8) as $message) {
        if (!is_array($message)) {
            continue;
        }

        $role = (string)($message['role'] ?? '');
        $content = trim((string)($message['content'] ?? ''));
        if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $messages[] = [
            'role' => $role,
            'content' => $content,
        ];
    }
}

if (!$messages || end($messages)['role'] !== 'user' || end($messages)['content'] !== $prompt) {
    $messages[] = [
        'role' => 'user',
        'content' => $prompt,
    ];
}

$locale = current_locale();
if ($locale === 'hi') {
    $localeLanguage = 'Hindi';
} elseif ($locale === 'or') {
    $localeLanguage = 'Odia';
} elseif ($locale === 'mr') {
    $localeLanguage = 'Marathi';
} elseif ($locale === 'pa') {
    $localeLanguage = 'Punjabi';
} elseif ($locale === 'bn') {
    $localeLanguage = 'Bengali';
} elseif ($locale === 'ml') {
    $localeLanguage = 'Malayalam';
} else {
    $localeLanguage = 'English';
}

$systemInstructions = 'You are LeafBot, a concise and practical plant-care chatbot for Agrico users. '
    . 'Always reply in ' . $localeLanguage . ' unless the user explicitly asks for another language. '
    . 'Help with plant disease symptoms, watering, weather impact, prevention, recovery steps, and writing clear community questions. '
    . 'If the user asks for something outside plant care, gently redirect them back to plants.';
$reply = '';
$activeModel = '';
$lastError = t('leafbot.status_error');

if ($openAiApiKey !== '') {
    $requestBody = [
        'model' => $openAiModel,
        'input' => $messages,
        'instructions' => $systemInstructions,
        'max_output_tokens' => 500,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $openAiApiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $status >= 200 && $status < 300) {
        $data = json_decode($response, true);
        $reply = trim((string)($data['output_text'] ?? ''));

        if ($reply === '' && !empty($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                if (!is_array($item) || empty($item['content']) || !is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $contentItem) {
                    if (!is_array($contentItem)) {
                        continue;
                    }

                    $text = trim((string)($contentItem['text'] ?? ''));
                    if ($text !== '') {
                        $reply = $text;
                        break 2;
                    }
                }
            }
        }

        if ($reply !== '') {
            $activeModel = $openAiModel;
        }
    } else {
        if ($response) {
            $errorData = json_decode($response, true);
            if (!empty($errorData['error']['message'])) {
                $lastError = (string)$errorData['error']['message'];
            }
        } elseif ($curlError !== '') {
            $lastError = $curlError;
        }
    }
}

if ($reply === '' && $geminiApiKey !== '') {
    $geminiContents = [];
    foreach ($messages as $message) {
        $geminiContents[] = [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [
                ['text' => $message['content']],
            ],
        ];
    }

    $geminiRequestBody = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemInstructions],
            ],
        ],
        'contents' => $geminiContents,
        'generationConfig' => [
            'temperature' => 0.5,
            'maxOutputTokens' => 500,
        ],
    ];

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($geminiModel) . ':generateContent');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-goog-api-key: ' . $geminiApiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($geminiRequestBody, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 45,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $status >= 200 && $status < 300) {
        $data = json_decode($response, true);
        if (!empty($data['candidates']) && is_array($data['candidates'])) {
            foreach ($data['candidates'] as $candidate) {
                if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                    continue;
                }

                foreach ($candidate['content']['parts'] as $part) {
                    $text = trim((string)($part['text'] ?? ''));
                    if ($text !== '') {
                        $reply = $text;
                        $activeModel = $geminiModel;
                        break 2;
                    }
                }
            }
        }
    } else {
        if ($response) {
            $errorData = json_decode($response, true);
            if (!empty($errorData['error']['message'])) {
                $lastError = (string)$errorData['error']['message'];
            }
        } elseif ($curlError !== '') {
            $lastError = $curlError;
        }
    }
}

if ($reply === '') {
    http_response_code(502);
    echo json_encode(['error' => $lastError]);
    exit;
}

echo json_encode([
    'reply' => $reply,
    'model' => $activeModel,
], JSON_UNESCAPED_UNICODE);
