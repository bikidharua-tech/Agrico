<?php
require __DIR__ . '/../app/bootstrap.php';

global $config;
$f = $config['oauth']['facebook'] ?? null;
if (!$f || str_starts_with($f['client_id'] ?? '', 'YOUR_')) {
    redirect($_SERVER['HTTP_REFERER'] ?? 'login.php');
}

$f['redirect_uri'] = site_url('oauth_facebook_callback.php');

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state_facebook'] = $state;

$params = [
    'client_id' => $f['client_id'],
    'redirect_uri' => $f['redirect_uri'],
    'response_type' => 'code',
    'scope' => 'email,public_profile',
    'state' => $state,
];

redirect('https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($params));
