<?php
require __DIR__ . '/../app/bootstrap.php';

global $config;
$g = $config['oauth']['google'] ?? null;
if (
    !$g
    || str_starts_with($g['client_id'] ?? '', 'YOUR_')
    || str_starts_with($g['client_secret'] ?? '', 'YOUR_')
) {
    set_flash('error', 'Google sign-in is not configured yet. Please check the Google OAuth settings in your deployment.');
    redirect('login.php');
}

$g['redirect_uri'] = site_url('oauth_google_callback.php');

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
if (!$code || !$state || !hash_equals($_SESSION['oauth_state_google'] ?? '', $state)) {
    http_response_code(400);
    exit('Invalid OAuth state.');
}
unset($_SESSION['oauth_state_google']);

// Exchange code for access token
$tokenCh = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($tokenCh, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $g['client_id'],
        'client_secret' => $g['client_secret'],
        'redirect_uri' => $g['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_TIMEOUT => 20,
]);
$tokenResp = curl_exec($tokenCh);
$tokenCode = curl_getinfo($tokenCh, CURLINFO_HTTP_CODE);
curl_close($tokenCh);
if ($tokenResp === false || $tokenCode !== 200) {
    http_response_code(500);
    exit('Google token exchange failed.');
}
$tokenData = json_decode($tokenResp, true) ?: [];
$accessToken = $tokenData['access_token'] ?? '';
if (!$accessToken) {
    http_response_code(500);
    exit('Missing Google access token.');
}

// Fetch profile
$meCh = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($meCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_TIMEOUT => 20,
]);
$meResp = curl_exec($meCh);
$meCode = curl_getinfo($meCh, CURLINFO_HTTP_CODE);
curl_close($meCh);
if ($meResp === false || $meCode !== 200) {
    http_response_code(500);
    exit('Google userinfo failed.');
}
$me = json_decode($meResp, true) ?: [];
$email = trim((string)($me['email'] ?? ''));
$name = trim((string)($me['name'] ?? 'User'));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(500);
    exit('Google did not return a valid email.');
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
    exit('Google sign-in failed.');
}
