<?php
require __DIR__ . '/../app/bootstrap.php';

global $config;
$g = $config['oauth']['google'] ?? null;
if (
    !$g
    || str_starts_with($g['client_id'] ?? '', 'YOUR_')
) {
    set_flash('error', 'Google sign-in is not configured yet. Set GOOGLE_OAUTH_CLIENT_ID in your environment.');
    redirect('login.php');
}

$g['redirect_uri'] = site_url('oauth_google_callback.php');

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state_google'] = $state;

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
