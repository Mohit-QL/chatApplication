<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['user_id'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $email = $_POST['email'];

    // Handle image upload
    $image = $_FILES['profile_image']['name'];
    $image_tmp = $_FILES['profile_image']['tmp_name'];

    if ($image) {
        $image_path = 'uploads/profile/' . $image;
        move_uploaded_file($image_tmp, $image_path);

        $stmt = $conn->prepare("UPDATE users SET fname=?, lname=?, email=?, image=? WHERE user_id=?");
        $stmt->bind_param("ssssi", $fname, $lname, $email, $image, $id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET fname=?, lname=?, email=? WHERE user_id=?");
        $stmt->bind_param("sssi", $fname, $lname, $email, $id);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: index.php?updated=true");
    exit;
}
?>
