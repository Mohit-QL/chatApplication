<?php
session_start();
include_once 'loadenv.php'; 
loadEnv('.env'); 


require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Google\Service\Oauth2;

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);        
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']); 
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);   
$client->addScope('email');
$client->addScope('profile');

$client->setPrompt('select_account consent');
$client->setAccessType('offline');

try {
    if (isset($_GET['code'])) {
        error_log("Received authorization code");

        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        if (!isset($token['access_token'])) {
            throw new RuntimeException('Failed to get access token. Response: ' . json_encode($token));
        }

        $client->setAccessToken($token);
        $tokenInfo = $client->verifyIdToken();

        if (!$tokenInfo) {
            throw new RuntimeException('Invalid ID token');
        }

        $oauth = new Oauth2($client);
        $userInfo = $oauth->userinfo->get();

        if (!$userInfo || empty($userInfo->email)) {
            throw new RuntimeException('Failed to retrieve user info from Google.');
        }

        $email = filter_var($userInfo->email, FILTER_SANITIZE_EMAIL);
        $fname = htmlspecialchars($userInfo->givenName ?? '', ENT_QUOTES, 'UTF-8');
        $lname = htmlspecialchars($userInfo->familyName ?? '', ENT_QUOTES, 'UTF-8');
        $googleImageUrl = filter_var($userInfo->picture ?? '', FILTER_SANITIZE_URL);
        $upload_dir = "uploads/profile/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $img_ext = pathinfo(parse_url($googleImageUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
        $img_name = time() . "_profile." . ($img_ext ?: "jpg");
        $img_path = $upload_dir . $img_name;

        $imageData = file_get_contents($googleImageUrl);
        if ($imageData !== false) {
            file_put_contents($img_path, $imageData);
        } else {
            error_log("Failed to download Google profile image. Using fallback.");
            $img_name = "default_profile.webp"; 
        }

        $image = $img_name;

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                $unique_id = uniqid();
                $status = 'Online now';
                $stmt = $conn->prepare(
                    "INSERT INTO users (unique_id, fname, lname, email, password, image, status) 
                     VALUES (?, ?, ?, ?, '', ?, ?)"
                );
                $stmt->bind_param("ssssss", $unique_id, $fname, $lname, $email, $image, $status);
                $stmt->execute();

                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            }

            session_regenerate_id(true);

            $_SESSION = [];
            $_SESSION['unique_id'] = $user['unique_id'];
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['image'] = $user['image'];
            $_SESSION['status'] = 'Online now';
            $_SESSION['oauth_provider'] = 'google';
            $_SESSION['oauth_token'] = $token;
            $_SESSION['last_activity'] = time();

            $conn->commit();

            error_log("User authenticated successfully: " . $email);
            error_log("Session data: " . print_r($_SESSION, true));

            header("Location: index.php");
            exit;
        } catch (Exception $dbException) {
            $conn->rollback();
            error_log("Database error during Google OAuth: " . $dbException->getMessage());
            throw $dbException;
        }
    } elseif (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error'] ?? 'Unknown error');
        $errorDesc = isset($_GET['error_description']) ? htmlspecialchars($_GET['error_description']) : 'No description provided';
        error_log("Google OAuth error: {$error}. Description: {$errorDesc}");
        throw new RuntimeException("Google OAuth error: {$error}. Description: {$errorDesc}");
    } else {
        error_log("Direct access to google-callback.php without OAuth parameters");
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Google authentication error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    session_unset();
    session_destroy();

    $errorMessage = "We couldn't log you in with Google. Please try again.";
    if (ini_get('display_errors')) {
        $errorMessage .= "\n\nDebug info: " . htmlspecialchars($e->getMessage());
    }

    session_start();
    $_SESSION['oauth_error'] = $errorMessage;
    header("Location: login.php?error=google_auth_failed");
    exit;
}
