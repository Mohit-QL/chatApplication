<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'] ?? 0;
$typing = $_POST['typing'] ?? 0;

// Store typing status in database
$stmt = $conn->prepare("INSERT INTO typing_status (sender_id, receiver_id, is_typing) VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = NOW()");
$stmt->bind_param("iii", $sender_id, $receiver_id, $typing);
$stmt->execute();