<?php
session_start();
include_once 'loadenv.php';
loadEnv('.env');

include 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

$fb = new \Facebook\Facebook([
    'app_id' => $_ENV['FACEBOOK_APP_ID'],        
    'app_secret' => $_ENV['FACEBOOK_APP_SECRET'], 
    'default_graph_version' => 'v17.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
    $accessToken = $helper->getAccessToken();
} catch (Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . $e->getMessage();
    exit;
} catch (Facebook\Exceptions\FacebookSDKException $e) {
    echo 'Facebook SDK returned an error: ' . $e->getMessage();
    exit;
}

if (!isset($accessToken)) {
    echo 'No OAuth data returned';
    exit;
}

$oAuth2Client = $fb->getOAuth2Client();
$tokenMetadata = $oAuth2Client->debugToken($accessToken);
$tokenMetadata->validateAppId($_ENV['FACEBOOK_APP_ID']);

try {
    $response = $fb->get('/me?fields=id,name,email', $accessToken);
} catch (Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Error: ' . $e->getMessage();
    exit;
}

$user = $response->getGraphUser();
$name = $user['name'];
$email = $user['email'] ?? $user['id'] . '@facebook.com';
$image = "https://graph.facebook.com/{$user['id']}/picture?type=large";

$full_name = explode(" ", $name, 2);
$fname = $full_name[0];
$lname = isset($full_name[1]) ? $full_name[1] : '';

$upload_dir = "uploads/profile/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$img_ext = pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION);
$img_name = time() . "_profile." . ($img_ext ?: "jpg");
$img_path = $upload_dir . $img_name;

$imageData = file_get_contents($image);
if ($imageData !== false) {
    file_put_contents($img_path, $imageData);
} else {
    error_log("Failed to download Facebook profile image. Using fallback.");
    $img_name = "default_profile.webp";
}

$image = $img_name;

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$existingUser = $result->fetch_assoc();

if (!$existingUser) {
    $unique_id = uniqid();
    $dummyPassword = password_hash('facebook_login', PASSWORD_DEFAULT);
    $status = 'Online now';

    $stmt = $conn->prepare("INSERT INTO users (unique_id, fname, lname, email, password, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $unique_id, $fname, $lname, $email, $dummyPassword, $image, $status);
    $stmt->execute();

    $userId = $stmt->insert_id;
    $newUser = [
        'user_id' => $userId,
        'unique_id' => $unique_id,
        'fname' => $fname,
        'lname' => $lname,
        'email' => $email,
        'image' => $image
    ];
} else {
    $newUser = $existingUser;
}

$_SESSION['unique_id'] = $newUser['unique_id'];
$_SESSION['user_id'] = $newUser['user_id'];
$_SESSION['fname'] = $newUser['fname'];
$_SESSION['lname'] = $newUser['lname'];
$_SESSION['email'] = $newUser['email'];
$_SESSION['image'] = $newUser['image'];
$_SESSION['status'] = 'Online now';

header("Location: index.php");
exit;
