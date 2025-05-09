<?php
session_start();
include_once 'loadenv.php'; 
loadEnv('.env'); 

unset($_SESSION['oauth_token']);
session_write_close();

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);        
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']); 
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);   
$client->addScope('email');
$client->addScope('profile');

$client->setPrompt('select_account consent');
$client->setAccessType('offline');
$client->setIncludeGrantedScopes(true);

$authUrl = $client->createAuthUrl();
$authUrl .= '&state=' . bin2hex(random_bytes(16));

error_log("Redirecting to Google auth URL: " . $authUrl);

header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;
