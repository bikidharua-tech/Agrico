<?php
require __DIR__ . '/../app/bootstrap.php';

global $config;
$f = $config['oauth']['facebook'] ?? null;
if (!$f || str_starts_with($f['client_id'] ?? '', 'YOUR_')) {
    http_response_code(500);
    exit('Facebook OAuth is not configured.');
}

$f['redirect_uri'] = site_url('oauth_facebook_callback.php');

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
if (!$code || !$state || !hash_equals($_SESSION['oauth_state_facebook'] ?? '', $state)) {
    http_response_code(400);
    exit('Invalid OAuth state.');
}
unset($_SESSION['oauth_state_facebook']);

// Exchange code for access token
$tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
    'client_id' => $f['client_id'],
    'redirect_uri' => $f['redirect_uri'],
    'client_secret' => $f['client_secret'],
    'code' => $code,
]);
$tokenResp = @file_get_contents($tokenUrl);
if ($tokenResp === false) {
    http_response_code(500);
    exit('Facebook token exchange failed.');
}
$tokenData = json_decode($tokenResp, true) ?: [];
$accessToken = $tokenData['access_token'] ?? '';
if (!$accessToken) {
    http_response_code(500);
    exit('Missing Facebook access token.');
}

// Fetch profile
$meUrl = 'https://graph.facebook.com/me?' . http_build_query([
    'fields' => 'id,name,email',
    'access_token' => $accessToken,
]);
$meResp = @file_get_contents($meUrl);
if ($meResp === false) {
    http_response_code(500);
    exit('Facebook userinfo failed.');
}
$me = json_decode($meResp, true) ?: [];
$email = trim((string)($me['email'] ?? ''));
$name = trim((string)($me['name'] ?? 'User'));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    exit('Facebook did not return an email (ensure app has email permission and user email exists).');
}

// Find or create local user
try {
    $stmt = db()->prepare('SELECT id, status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u) {
        if (($u['status'] ?? '') !== 'active') {
            http_response_code(403);
            exit('Account is inactive.');
        }
        $_SESSION['user_id'] = (int)$u['id'];
        redirect('index.php');
    }

    $randomPass = bin2hex(random_bytes(16));
    $hash = password_hash($randomPass, PASSWORD_BCRYPT);
    db()->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, \'user\', \'active\')')
        ->execute([$name ?: 'User', $email, $hash]);
    $_SESSION['user_id'] = (int)db()->lastInsertId();
    redirect('index.php');
} catch (Throwable $e) {
    if (is_database_connection_error($e)) {
        http_response_code(500);
        exit(auth_deployment_error_message());
    }

    if (is_duplicate_key_error($e)) {
        $stmt = db()->prepare('SELECT id, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if ($u && ($u['status'] ?? '') === 'active') {
            $_SESSION['user_id'] = (int)$u['id'];
            redirect('index.php');
        }
    }

    http_response_code(500);
    exit('Facebook sign-in failed.');
}
