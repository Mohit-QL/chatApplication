<?php
session_start();
include_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = mysqli_real_escape_string($conn, $_POST['fname']);
    $lname = mysqli_real_escape_string($conn, $_POST['lname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $profile_image = $_FILES['profile_image']['name'];

    if (!empty($fname) && !empty($lname) && !empty($email) && !empty($password) && !empty($profile_image)) {
        $check_email = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            echo "Email already registered!";
            exit;
        }

        $upload_dir = "uploads/profile/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $img_name = time() . '_' . basename($profile_image);
        $img_path = $upload_dir . $img_name;
        if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $img_path)) {
            echo "File upload failed!";
            exit;
        }

        $unique_id = uniqid();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (unique_id, fname, lname, email, password, status, image)
                VALUES ('$unique_id', '$fname', '$lname', '$email', '$hashed_password', 'Offline now', '$img_name')";

        if (mysqli_query($conn, $sql)) {
            header("Location: login.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "All fields are required!";
    }
}
?>




<?php
include_once './header.php';
?>

<body class="signup-body">
    <div class="container p-0">
        <div class="left-panel">
            <img src="./ss/Login-img.jpg" alt="Decorative Art" width="100%" height="100%" />
        </div>
        <div class="right-panel">
            <form class="signup-form" enctype="multipart/form-data" method="POST">
                <h2>Create Account</h2>
                <div class="name-fields">
                    <input type="text" placeholder="First Name" name="fname" required>
                    <input type="text" placeholder="Last Name" name="lname" required>
                </div>
                <input type="email" placeholder="Email" name="email" required>
                <input type="password" placeholder="Password" name="password" required>
                <input type="file" accept="image/*" class="profile-pic-input" name="profile_image" required>

                <button type="submit">Create Account</button>
                <p class="login-text">Already have an account? <a href="./login.php">Login</a>.</p>

                <div class="divider"><span>OR</span></div>

                <div class="signup-social-login">
                    <button class="sfacebook-btn"><img src="./ss/facebook.webp" class="me-2" alt="" height="20px" width="20px">Sign with Facebook</button>
                    <button class="sgoogle-btn"><img src="./ss/google.webp" class="me-2" alt="" height="30px" width="30px">Sign with Google</button>
                </div>
            </form>
        </div>
    </div>

    <?php
    include_once './footer.php'
    ?>