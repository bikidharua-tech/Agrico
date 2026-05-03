<?php

$config = require __DIR__ . '/../config/config.php';

function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    return $forwardedProto === 'https';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/i18n.php';
if (!empty($_GET['lang'])) {
    set_locale((string)$_GET['lang']);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        if ($needle === '') {
            return true;
        }
        $length = strlen($needle);
        if ($length > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$length) === $needle;
    }
}

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $value) {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}

function db(): PDO {
    static $pdo = null;
    global $config;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['name'],
            $config['db']['charset']
        );
        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function is_duplicate_key_error(Throwable $e): bool {
    $code = (string)$e->getCode();
    if ($code === '23000' || $code === '1062') {
        return true;
    }

    $message = strtolower($e->getMessage());
    return str_contains($message, 'duplicate entry') || str_contains($message, 'unique constraint failed');
}

function is_database_connection_error(Throwable $e): bool {
    $code = (string)$e->getCode();
    if (in_array($code, ['2002', '2003', '2006', '1045'], true)) {
        return true;
    }

    $message = strtolower($e->getMessage());
    return str_contains($message, 'sqlstate[hy000] [2002]')
        || str_contains($message, 'connection refused')
        || str_contains($message, 'access denied for user')
        || str_contains($message, 'unknown mysql server host');
}

function auth_deployment_error_message(): string {
    return 'Authentication is unavailable because the database connection failed. Check your deployment DB_HOST, DB_NAME, DB_USER, and DB_PASS values.';
}

function table_has_column(string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = db()->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
    $stmt->execute([$column]);
    $cache[$key] = (bool)$stmt->fetch();

    return $cache[$key];
}

function ensure_user_profile_schema(): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    if (!table_has_column('users', 'avatar_path')) {
        db()->exec('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER status');
    }

    if (!table_has_column('users', 'bio')) {
        db()->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER avatar_path');
    }

    $ensured = true;
}

function ensure_predictions_schema(): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        if (!table_has_column('predictions', 'model_source')) {
            db()->exec("ALTER TABLE predictions ADD COLUMN model_source VARCHAR(60) NULL AFTER treatment_recommendation");
        }
    } catch (Throwable $e) {
        // Some environments may not allow ALTER TABLE for runtime user.
        // Keep app functional without blocking diagnosis flow.
    }

    $ensured = true;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    try {
        ensure_user_profile_schema();
        $stmt = db()->prepare('SELECT id, name, email, role, status, avatar_path, bio FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function admin_log(int $adminId, string $action, string $targetType, ?int $targetId = null, ?string $details = null): void {
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_user_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $targetType, $targetId, $details]);
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function site_origin(): string {
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower(trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO']));
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        global $config;
        $baseUrl = trim((string)($config['base_url'] ?? ''));
        if ($baseUrl !== '' && preg_match('#^https?://[^/]+#i', $baseUrl, $matches)) {
            return rtrim($matches[0], '/');
        }
        return '';
    }

    return $scheme . '://' . $host;
}

function site_url(string $path = ''): string {
    $origin = site_origin();
    $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $basePath = $scriptDir === '' ? '' : $scriptDir;

    $path = ltrim($path, '/');
    $url = rtrim($origin, '/');

    if ($basePath !== '' && $basePath !== '.') {
        $url .= $basePath;
    }

    if ($path !== '') {
        $url .= '/' . $path;
    }

    return $url;
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function public_root(): string {
    return realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
}

function normalize_public_path(string $path): string {
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $path = $parsedPath;
        }
    }

    $path = ltrim($path, '/');
    $publicPos = stripos($path, 'public/');
    if ($publicPos !== false) {
        $path = substr($path, $publicPos + 7);
    }

    return ltrim($path, '/');
}

function resolve_public_path(string $path): string {
    $normalized = normalize_public_path($path);
    $publicRoot = rtrim(public_root(), '/\\');

    if ($normalized !== '' && is_file($publicRoot . '/' . $normalized)) {
        return $normalized;
    }

    $fileName = basename(str_replace('\\', '/', $path));
    if ($fileName !== '' && $fileName !== '.' && $fileName !== '..') {
        foreach (['uploads/forum', 'uploads/diagnosis', 'uploads/avatars'] as $dir) {
            $candidate = $dir . '/' . $fileName;
            if (is_file($publicRoot . '/' . $candidate)) {
                return $candidate;
            }
        }
    }

    return $normalized;
}

function public_file_path(string $path): string {
    return rtrim(public_root(), '/\\') . '/' . resolve_public_path($path);
}

function public_url(string $path): string {
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return resolve_public_path($path);
}

