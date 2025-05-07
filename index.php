<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'], $_POST['message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $receiver_id, $message);
        $stmt->execute();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?chat_with=" . $receiver_id);
    exit();
}

$chat_with = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : 0;

$contacts_stmt = $conn->prepare("
    SELECT user_id, fname, lname, status, image
    FROM users 
    WHERE user_id != ?
");
$contacts_stmt->bind_param("i", $user_id);
$contacts_stmt->execute();
$contacts_result = $contacts_stmt->get_result();

$messages = [];
$chat_partner = null;
if ($chat_with > 0) {
    $partner_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $partner_stmt->bind_param("i", $chat_with);
    $partner_stmt->execute();
    $chat_partner = $partner_stmt->get_result()->fetch_assoc();

    // Update "seen" field when user views the messages
    $update_seen_stmt = $conn->prepare("UPDATE messages SET seen = 1 WHERE sender_id = ? AND receiver_id = ? AND seen = 0");
    $update_seen_stmt->bind_param("ii", $chat_with, $user_id);
    $update_seen_stmt->execute();

    $msg_stmt = $conn->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msg_stmt->bind_param("iiii", $user_id, $chat_with, $chat_with, $user_id);
    $msg_stmt->execute();
    $messages = $msg_stmt->get_result();
}

if (isset($_GET['logout'])) {
    session_destroy();
    mysqli_query($conn, "UPDATE users SET status = 'Offline now' WHERE unique_id = '{$user['unique_id']}'");
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Messages</title>
    <link rel="stylesheet" href="./css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body>

    <div class="main-content">
        <!-- Sidebar -->
        <div class="chat-list">
            <div class="mb-4 pb-3 border-bottom text-center d-flex align-items-center justify-content-center">
                <img src="uploads/profile/<?= htmlspecialchars($user['image']) ?>" class="rounded-circle me-4" width="70" height="70" alt="User">
                <div class="">
                    <h6 class="mb-0 text-start"><?= htmlspecialchars($user['fname']) . ' ' . htmlspecialchars($user['lname']) ?></h6>
                    <p class="text-muted mb-0 text-start">@<?= htmlspecialchars(strtolower($user['fname'] . $user['lname'])) ?></p>
                    <a href="?logout=true" class="btn btn-sm mt-2 rounded-pill text-white" style="font-size: 14px; padding: 4px 20px; background: linear-gradient(to right, #fbb199, #f28b82);
">Logout</a>
                </div>
            </div>

            <?php while ($row = $contacts_result->fetch_assoc()): ?>
                <div class="mb-3 contact">
                    <a href="?chat_with=<?= $row['user_id'] ?>" class="d-flex align-items-center">
                        <img src="uploads/profile/<?= htmlspecialchars($row['image']) ?>" alt="Contact">
                        <div class="ms-2">
                            <strong><?= htmlspecialchars($row['fname']) . ' ' . htmlspecialchars($row['lname']) ?></strong><br>
                            <small class="text-muted"><?= $row['status'] ?></small>
                        </div>
                    </a>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Chat window -->
        <div class="chat-window">
            <?php if ($chat_partner): ?>
                <div class="chat-header mb-4">
                    <img src="uploads/profile/<?= htmlspecialchars($chat_partner['image']) ?>" alt="Partner">
                    <h6 class="mb-0"><?= htmlspecialchars($chat_partner['fname'] . ' ' . $chat_partner['lname']) ?>
                        <?php if ($chat_partner['status'] === 'Online now') { ?>
                            <span class="text-success ms-2">●</span>
                        <?php } else { ?>
                            <span class="text-muted ms-2">●</span>
                        <?php } ?>
                    </h6>
                </div>

                <div class="messages">
                    <?php
                    $messages_array = [];
                    while ($row = $messages->fetch_assoc()) {
                        $messages_array[] = $row;
                    }

                    $total_messages = count($messages_array);

                    for ($i = 0; $i < $total_messages; $i++):
                        $msg = $messages_array[$i];
                        $is_last_from_sender = (
                            $i == $total_messages - 1 ||
                            $messages_array[$i + 1]['sender_id'] != $msg['sender_id']
                        );
                        $is_sent = $msg['sender_id'] == $user_id;
                        $img_src = $is_sent ? $user['image'] : $chat_partner['image'];
                    ?>
                        <div class="message <?= $is_sent ? 'sent' : 'received' ?>">
                            <div class="text">
                                <?php
                                // Break the message into chunks of 10 words
                                $words = explode(' ', htmlspecialchars($msg['message']));
                                foreach (array_chunk($words, 10) as $line) {
                                    echo implode(' ', $line) . '<br>';
                                }
                                ?>
                            </div>

                            <?php if ($is_last_from_sender): ?>
                                <div class="message-footer d-flex align-items-center justify-content-end mt-1">
                                    <?php if ($is_sent): ?>
                                        <small class="text-white me-1"><?= date('H:i', strtotime($msg['created_at'])) ?></small>
                                        <!-- Show double tick for sent messages -->
                                        <i class="fa-solid fa-check-double <?= ($msg['seen'] == 1) ? 'text-white' : 'text-dark' ?>"></i>
                                    <?php else: ?>
                                        <small class="text-muted"><?= date('H:i', strtotime($msg['created_at'])) ?></small>

                                    <?php endif; ?>

                                    <img class="msg-profile-pic ms-2" src="uploads/profile/<?= htmlspecialchars($img_src) ?>" alt="User" />
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>

                    <?php if ($total_messages === 0): ?>
                        <div class="no-messages-yet">
                            <h5>No messages yet</h5>
                            <p class="text-muted">Say hello to start the conversation!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <form class="chat-input" method="POST">
                    <input type="hidden" name="receiver_id" value="<?= $chat_partner['user_id'] ?>">
                    <input type="text" name="message" placeholder="Type your message..." required>
                    <button type="submit"><i class="fas fa-paper-plane"></i></button>
                </form>
            <?php else: ?>
                <div class="empty-chat-message">
                    <div class="text-box">
                        <h4>Select a contact to start chatting</h4>
                        <p class="text-muted">Choose someone from your contact list to begin a conversation.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>