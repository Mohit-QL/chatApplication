<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit();
}

$message_id = $_POST['message_id'] ?? 0;
$new_message = trim($_POST['message'] ?? '');


// Verify the message belongs to the user
$stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || $result['sender_id'] != $_SESSION['user_id']) {
    header("HTTP/1.1 403 Forbidden");
    exit();
}

// Update the message
$stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ?");
$stmt->bind_param("si", $new_message, $message_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>