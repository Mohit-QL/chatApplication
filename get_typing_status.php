<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$receiver_id = $_SESSION['user_id'];
$sender_id = $_GET['receiver_id'] ?? 0;

// Get typing status from database
$stmt = $conn->prepare("SELECT is_typing FROM typing_status 
    WHERE sender_id = ? AND receiver_id = ? AND updated_at > DATE_SUB(NOW(), INTERVAL 3 SECOND)");
$stmt->bind_param("ii", $sender_id, $receiver_id);
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: application/json');
echo json_encode([
    'is_typing' => $result->num_rows > 0 ? $result->fetch_assoc()['is_typing'] : 0
]);
