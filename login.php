<?php
session_start();
include_once 'loadenv.php';
loadEnv('.env');


include_once 'db.php';
require_once 'vendor/autoload.php';

// ------------------ Regular Email/Password Login ------------------ //
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = $_POST['password'];

  $sql = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
  if (mysqli_num_rows($sql) > 0) {
    $user = mysqli_fetch_assoc($sql);
    if (password_verify($password, $user['password'])) {
      mysqli_query($conn, "UPDATE users SET status = 'Online now' WHERE unique_id = '{$user['unique_id']}'");

      $_SESSION['unique_id'] = $user['unique_id'];
      $_SESSION['user_id'] = $user['user_id'];
      $_SESSION['fname'] = $user['fname'];
      $_SESSION['lname'] = $user['lname'];
      $_SESSION['email'] = $user['email'];
      $_SESSION['image'] = $user['image'];
      $_SESSION['status'] = 'Online now';

      header("Location: index.php");
      exit;
    } else {
      echo "Invalid email or password.";
    }
  } else {
    echo "No user found with this email.";
  }
}


// ------------------ Facebook Login ------------------ //
$fb = new \Facebook\Facebook([
  'app_id' => $_ENV['FACEBOOK_APP_ID'],
  'app_secret' => $_ENV['FACEBOOK_APP_SECRET'],
  'default_graph_version' => 'v18.0',
]);

$helper = $fb->getRedirectLoginHelper();
$fb_login_url = $helper->getLoginUrl($_ENV['FACEBOOK_REDIRECT_URI'], ['email']);

?>



<?php
include_once './header.php';
?>

<body class="login-body">

  <div class="login-container">
    <div class="login-image">
      <img src="./ss/upscalemedia-transformed.png" alt="Login Image" style="width: 100%; height: auto; background-color: #fef1f1;">
    </div>

    <form class="login-form" action="login.php" method="POST">
      <h2>Login</h2>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" placeholder="Enter your email" required />
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" name="password" id="password" placeholder="Enter your password" required />
      </div>

      <div class="form-actions">
        <div></div>
        <a href="forgot-password.php">Forgot Password?</a>
      </div>

      <button type="submit" class="login-btn">Log In</button>

      <div class="divider"><span>OR</span></div>


      <div class="social-login">
        <a href="<?php echo htmlspecialchars($fb_login_url); ?>" class="facebook-btn">
          <img src="./ss/facebook.webp" class="me-2" alt="" height="27px" width="27px">
        </a>
        <a href="./google-login.php" class="google-btn">
          <img src="./ss/google.webp" class="me-2" alt="" height="37px" width="37px">
        </a>
      </div>


      <p class="signup-text">Don't have an account? <a href="./signup.php">Sign Up here</a></p>
    </form>
  </div>

  <?php
  include_once './footer.php'
  ?>