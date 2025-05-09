<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['chat_with'])) {
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_with = (int)$_GET['chat_with'];

// $msg_stmt = $conn->prepare("
//     SELECT * FROM messages 
//     WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
//     ORDER BY created_at ASC
// ");
// $msg_stmt->bind_param("iiii", $user_id, $chat_with, $chat_with, $user_id);
// $msg_stmt->execute();
// $result = $msg_stmt->get_result();

// $messages = [];
// while ($row = $result->fetch_assoc()) {
//     $messages[] = $row;
// }

// echo json_encode($messages);


$stmt = $conn->prepare("
    SELECT *, 
           CASE 
               WHEN sender_id = ? THEN 'sent' 
               ELSE 'received' 
           END AS message_type
    FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?) 
    ORDER BY created_at ASC
");
$stmt->bind_param("iiiii", $user_id, $user_id, $chat_with, $chat_with, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

header('Content-Type: application/json');
echo json_encode($messages);
