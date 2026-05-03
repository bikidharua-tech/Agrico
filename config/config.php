<?php
function load_dotenv_file(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $existing = getenv($key);
        if ($key === '' || ($existing !== false && $existing !== '')) {
            continue;
        }

        $first = $value[0] ?? '';
        $last = $value !== '' ? $value[strlen($value) - 1] : '';
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }

        $value = str_replace(["\\n", "\\r"], ["\n", "\r"], $value);
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$repoRoot = dirname(__DIR__);
load_dotenv_file($repoRoot . '/.env');

function env_value(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function env_bool(string $key, bool $default = false): bool {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    if ($value === false || $value === '') {
        return $default;
    }

    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function first_env_value(array $keys, ?string $default = null): ?string {
    foreach ($keys as $key) {
        $value = env_value((string)$key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function parse_mysql_database_url(string $url): ?array {
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return null;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['mysql', 'mariadb', 'mysql2'], true)) {
        return null;
    }

    $host = trim(rawurldecode((string)($parts['host'] ?? '')));
    $name = trim(ltrim(rawurldecode((string)($parts['path'] ?? '')), '/'));
    $user = trim(rawurldecode((string)($parts['user'] ?? '')));
    if ($host === '' || $name === '' || $user === '') {
        return null;
    }

    return [
        'host' => $host,
        'port' => (string)($parts['port'] ?? '3306'),
        'name' => $name,
        'user' => $user,
        'pass' => rawurldecode((string)($parts['pass'] ?? '')),
    ];
}

$renderBaseUrl = env_value('RENDER_EXTERNAL_URL', 'http://localhost');
$baseUrl = rtrim((string)env_value('APP_BASE_URL', $renderBaseUrl), '/');
$googleRedirect = env_value('GOOGLE_OAUTH_REDIRECT_URI', $baseUrl . '/oauth_google_callback.php');
$facebookRedirect = env_value('FACEBOOK_OAUTH_REDIRECT_URI', $baseUrl . '/oauth_facebook_callback.php');
$defaultGoogleClientId = '490242781883-u8cfjbvhqnc2am2kacde1s35a2nd6q6c.apps.googleusercontent.com';
$databaseUrl = first_env_value([
    'DATABASE_URL',
    'MYSQL_URL',
    'MYSQL_DATABASE_URL',
    'DB_URL',
]);
$parsedDatabaseUrl = $databaseUrl ? parse_mysql_database_url($databaseUrl) : null;

return [
    'app_name' => env_value('APP_NAME', 'Agrico'),
    'base_url' => $baseUrl,
    'db' => [
        'host' => $parsedDatabaseUrl['host'] ?? first_env_value(['DB_HOST', 'MYSQLHOST', 'MYSQL_HOST'], '127.0.0.1'),
        'port' => $parsedDatabaseUrl['port'] ?? first_env_value(['DB_PORT', 'MYSQLPORT', 'MYSQL_PORT'], '3306'),
        'name' => $parsedDatabaseUrl['name'] ?? first_env_value(['DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], 'agrico'),
        'user' => $parsedDatabaseUrl['user'] ?? first_env_value(['DB_USER', 'MYSQLUSER', 'MYSQL_USER'], 'root'),
        'pass' => $parsedDatabaseUrl['pass'] ?? first_env_value(['DB_PASS', 'MYSQLPASSWORD', 'MYSQL_PASSWORD'], ''),
        'charset' => 'utf8mb4'
    ],
    'python_api_url' => env_value('PYTHON_API_URL', 'http://127.0.0.1:8001'),
    'plant_id' => [
        'enabled' => env_bool('PLANT_ID_ENABLED', false),
        'api_url' => env_value('PLANT_ID_API_URL', 'https://plant.id/api/v3'),
        'api_key' => env_value('PLANT_ID_API_KEY', ''),
        'api_keys' => [],
        'model' => env_value('PLANT_ID_MODEL', 'latest'),
    ],
    'gemini_api_key' => env_value('GEMINI_API_KEY', ''),
    'gemini_model' => env_value('GEMINI_MODEL', 'gemini-2.5-flash'),
    'openai_api_key' => env_value('OPENAI_API_KEY', ''),
    'openai_model' => env_value('OPENAI_MODEL', 'gpt-4.1-mini'),
    'oauth' => [
        'google' => [
            'client_id' => env_value('GOOGLE_OAUTH_CLIENT_ID', $defaultGoogleClientId),
            'client_secret' => env_value('GOOGLE_OAUTH_CLIENT_SECRET', ''),
            'redirect_uri' => $googleRedirect
        ],
        'facebook' => [
            'client_id' => env_value('FACEBOOK_OAUTH_APP_ID', ''),
            'client_secret' => env_value('FACEBOOK_OAUTH_APP_SECRET', ''),
            'redirect_uri' => $facebookRedirect
        ]
    ],
    'weather' => [
        'default_city' => env_value('WEATHER_DEFAULT_CITY', '')
    ]
];
