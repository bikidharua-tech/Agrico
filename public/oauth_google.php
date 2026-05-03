<?php
require __DIR__ . '/../app/bootstrap.php';

global $config;
$g = $config['oauth']['google'] ?? null;
if (
    !$g
    || str_starts_with($g['client_id'] ?? '', 'YOUR_')
    || str_starts_with($g['client_secret'] ?? '', 'YOUR_')
) {
    set_flash('error', 'Google sign-in is not configured yet. Set GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET in your environment.');
    redirect('login.php');
}

$g['redirect_uri'] = site_url('oauth_google_callback.php');

$statePayload = json_encode([
    'ts' => time(),
    'nonce' => bin2hex(random_bytes(16)),
], JSON_UNESCAPED_SLASHES);
if ($statePayload === false) {
    http_response_code(500);
    exit('Failed to create Google OAuth state.');
}
$stateSignature = hash_hmac('sha256', $statePayload, $g['client_secret'], true);
$state = base64url_encode($statePayload)
    . '.'
    . base64url_encode($stateSignature);

$params = [
    'client_id' => $g['client_id'],
    'redirect_uri' => $g['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'access_type' => 'online',
    'prompt' => 'select_account consent',
];

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
