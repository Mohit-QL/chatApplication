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
    $message = trim($_POST['message'] ?? '');
    $attachment_path = null;

    error_log('File upload data: ' . print_r($_FILES, true));

    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = basename($_FILES['attachment']['name']);
        $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $safe_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
        $target_path = $upload_dir . $safe_name;

        $allowed_types = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'video/mp4',
            'video/quicktime',
            'video/x-msvideo',
            'application/pdf'
        ];
        $max_size = 100 * 1024 * 1024;
        if (
            in_array($_FILES['attachment']['type'], $allowed_types) &&
            $_FILES['attachment']['size'] <= $max_size &&
            move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)
        ) {
            $attachment_path = $target_path;
        } else {
            error_log('File upload failed: ' . print_r($_FILES, true));
        }
    }

    error_log("Message: $message, Attachment: " . ($attachment_path ?? 'none'));

    if (!empty($message) || $attachment_path) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, attachment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $user_id, $receiver_id, $message, $attachment_path);

        if (!$stmt->execute()) {
            error_log("Database error: " . $stmt->error);
        }
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


$contacts_stmt = $conn->prepare("
    SELECT u.user_id, u.fname, u.lname, u.status, u.image,
        (SELECT message FROM messages WHERE 
            (sender_id = u.user_id AND receiver_id = ?) OR 
            (sender_id = ? AND receiver_id = u.user_id) 
            ORDER BY created_at DESC LIMIT 1) AS last_message,
        (SELECT created_at FROM messages WHERE 
            (sender_id = u.user_id AND receiver_id = ?) OR 
            (sender_id = ? AND receiver_id = u.user_id) 
            ORDER BY created_at DESC LIMIT 1) AS last_message_time,
        (SELECT COUNT(*) FROM messages 
            WHERE sender_id = u.user_id AND receiver_id = ? AND seen = 0) AS unread_count
    FROM users u
    WHERE u.user_id != ?
");
$contacts_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$contacts_stmt->execute();
$contacts_result = $contacts_stmt->get_result();



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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Application</title>
    <link rel="stylesheet" href="./css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="icon" href="./ss/Chat-App-logo-design-Graphics-5233742-1-1-580x387-removebg-preview.png" type="image/png">
</head>

<body>

    <div class="main-content">
        <!-- Sidebar -->
        <div class="chat-list">
            <div class="mb-4 pb-3 border-bottom text-center d-flex align-items-center justify-content-center"
                id="profile-box" style="cursor: pointer;">
                <img src="uploads/profile/<?= htmlspecialchars($user['image']) ?>" class="rounded-circle me-4" width="70" height="70" alt="User">
                <div>
                    <h6 class="mb-0 text-start"><?= htmlspecialchars($user['fname']) . ' ' . htmlspecialchars($user['lname']) ?></h6>
                    <p class=" mb-0 text-start">@<?= htmlspecialchars(strtolower($user['fname'] . $user['lname'])) ?></p>
                    <a href="?logout=true" class="btn btn-sm mt-2 rounded-pill text-white" style="font-size: 14px; padding: 4px 20px; background: linear-gradient(to right, #fbb199, #f28b82);">Logout</a>
                </div>
            </div>

            <div class="custom-search-group">
                <form onsubmit="event.preventDefault();" role="search">
                    <label for="search">Search for stuff</label>
                    <input id="search" type="search" placeholder="Search..." autofocus required />
                    <button type="submit">Go</button>
                </form>
            </div>

            <div class="contact-scroll">
                <?php while ($row = $contacts_result->fetch_assoc()): ?>
                    <div class="mb-3 contact mx-2 px-3 py-2">
                        <a href="?chat_with=<?= $row['user_id'] ?>" class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <img src="uploads/profile/<?= htmlspecialchars($row['image']) ?>" alt="Contact">
                                <div class="ms-2">
                                    <strong><?= htmlspecialchars($row['fname']) . ' ' . htmlspecialchars($row['lname']) ?></strong><br>
                                    <!-- <small class="text-muted"><?= $row['status'] ?></small><br> -->
                                    <small class=" d-block"><?= htmlspecialchars($row['last_message'] ?? 'No messages yet') ?></small>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php if (!empty($row['last_message_time'])): ?>
                                    <small class=""><?= date('h:i A', strtotime($row['last_message_time'])) ?> </small><br>
                                <?php endif; ?>
                                <?php if ($row['unread_count'] > 0): ?>
                                    <span class="badge rounded-pill"><?= $row['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>

            </div>
        </div>


        <!-- Chat window -->
        <div class="chat-window">
            <?php if ($chat_partner): ?>
                <div class="chat-header">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>

                    <img src="uploads/profile/<?= htmlspecialchars($chat_partner['image']) ?>"
                        alt="Partner"
                        class="me-2">

                    <div class="chat-header-content">
                        <div class="chat-header-top-row">
                            <div class="chat-header-user">
                                <h6><?= htmlspecialchars($chat_partner['fname'] . ' ' . $chat_partner['lname']) ?></h6>
                                <span class="status-indicator <?= $chat_partner['status'] === 'Online now' ? 'text-online' : 'text-offline' ?>">
                                    ● <?= $chat_partner['status'] === 'Online now' ? 'Online' : 'Offline' ?>
                                </span>
                            </div>
                            <button id="themeToggle" class="theme-toggle">
                                <i class="fas fa-moon"></i>
                            </button>
                        </div>

                        <div id="typingIndicator" class="typing-indicator" style="display: none;">
                            <span>typing...</span>
                        </div>
                    </div>
                </div>


                <div class="messages" id="chatMessages">
                    <!-- Messages will be loaded here via JavaScript -->
                </div>



                <form class="chat-input" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="receiver_id" value="<?= $chat_partner['user_id'] ?>">
                    <div class="input-wrapper">
                        <input type="text" name="message" placeholder="Type your message..." id="messageInput">
                        <label for="fileInput" class="attachment-btn">
                            <i class="fa-solid fa-paperclip"></i>
                        </label>
                        <input type="file" id="fileInput" name="attachment" style="display: none;" accept="image/*, video/*, .pdf">
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </div>
                    <div id="filePreview" class="file-preview"></div>
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

    <!-- Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="profileModalLabel">My Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="update-profile.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center">
                                <img src="uploads/profile/<?= htmlspecialchars($user['image']) ?>" class="rounded-circle" width="120" height="120" alt="Profile Image">
                                <input type="file" name="profile_image" class="form-control mt-3">
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label>First Name</label>
                                    <input type="text" name="fname" class="form-control" value="<?= htmlspecialchars($user['fname']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Last Name</label>
                                    <input type="text" name="lname" class="form-control" value="<?= htmlspecialchars($user['lname']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                                </div>
                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn text-white" style="background: linear-gradient(to right, #fbb199, #f28b82);">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const chatWith = <?= $chat_partner ? $chat_partner['user_id'] : 'null' ?>;
        const userId = <?= $user_id ?>;
        const userImage = "uploads/profile/<?= htmlspecialchars($user['image']) ?>";
        const partnerImage = "uploads/profile/<?= $chat_partner ? htmlspecialchars($chat_partner['image']) : '' ?>";

        let autoScroll = true;



        let lastMessageId = 0;

        function loadMessages() {
            if (!chatWith) return;

            fetch('get_messages.php?chat_with=' + chatWith)
                .then(response => response.json())
                .then(messages => {
                    const container = document.getElementById('chatMessages');
                    const wasAtBottom = isScrolledToBottom(container);

                    for (let i = 0; i < messages.length; i++) {
                        const msg = messages[i];
                        if (msg.id <= lastMessageId) continue;

                        lastMessageId = msg.id;
                        const isSent = msg.sender_id == userId;
                        const isLastFromSender = (i === messages.length - 1 || messages[i + 1].sender_id !== msg.sender_id);
                        const image = isSent ? userImage : partnerImage;

                        let attachmentHTML = '';
                        const fileExt = msg.attachment?.split('.').pop().toLowerCase();

                        if (msg.attachment) {
                            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                                attachmentHTML = `<div class="attachment-container mt-2"><img src="${msg.attachment}" class="message-attachment" style="max-width: 100%; max-height: 300px; border-radius: 10px;"></div>`;
                            } else if (['mp4', 'mov', 'avi'].includes(fileExt)) {
                                const mimeType = fileExt === 'mov' ? 'video/quicktime' : fileExt === 'avi' ? 'video/x-msvideo' : 'video/mp4';
                                attachmentHTML = `<div class="attachment-container mt-2"><video controls class="message-attachment" style="max-width: 100%; max-height: 300px; border-radius: 10px;"><source src="${msg.attachment}" type="${mimeType}">Your browser does not support the video tag.</video></div>`;
                            } else if (fileExt === 'pdf') {
                                attachmentHTML = `<div class="attachment-container mt-2"><a href="${msg.attachment}" target="_blank" class="d-flex align-items-center text-decoration-none"><i class="fas fa-file-pdf me-2" style="font-size: 1.5rem;"></i><span>View PDF</span></a></div>`;
                            } else {
                                attachmentHTML = `<div class="attachment-container mt-2"><a href="${msg.attachment}" target="_blank" class="d-flex align-items-center text-decoration-none"><i class="fas fa-file-download me-2" style="font-size: 1.5rem;"></i><span>Download File</span></a></div>`;
                            }
                        }

                        const messageDiv = document.createElement('div');

                        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
                        messageDiv.dataset.messageId = msg.id; // Add this line

                        messageDiv.innerHTML = `
                                                ${msg.message ? `<div class="text">${msg.message}</div>` : ''}
                                                ${attachmentHTML}
                                                                <div class="message-options">
                                                                    <button class="edit-message">Edit</button>
                                                                    <button class="delete-message">Delete</button>
                                                                </div>
                                                                ${isLastFromSender ? `
                                                                <div class="message-footer d-flex align-items-center justify-content-${isSent ? 'end' : 'start'} mt-1">
                                                                    <small style="" class="${isSent ? 'me-1' : ''}">${formatTime(msg.created_at)}</small>
                                                                    ${isSent ? `<i class="fa-solid fa-check-double ${msg.seen == 1 ? 'text-white' : 'text-dark'}"></i>` : ''}
                                                                    <img class="msg-profile-pic ms-2" src="${image}" alt="User" />
                                                                </div>` : ''}`;

                        container.appendChild(messageDiv);
                    }

                    if (wasAtBottom || autoScroll) {
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        function checkTypingStatus() {
            if (!chatWith) return;

            fetch('get_typing_status.php?receiver_id=' + chatWith)
                .then(response => response.json())
                .then(data => {
                    const typingIndicator = document.getElementById('typingIndicator');
                    if (data.is_typing) {
                        typingIndicator.style.display = 'block';
                    } else {
                        typingIndicator.style.display = 'none';
                    }
                });
        }

        setInterval(checkTypingStatus, 1000);

        function isScrolledToBottom(element) {
            return element.scrollHeight - element.scrollTop === element.clientHeight;
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        loadMessages();

        setInterval(loadMessages, 1000);

        document.getElementById('chatMessages').addEventListener('scroll', function() {
            const container = this;
            autoScroll = isScrolledToBottom(container);
        });

        function handleResize() {
            const chatWindow = document.querySelector('.chat-window');
            const messagesContainer = document.getElementById('chatMessages');

            if (window.innerWidth < 992) {
                messagesContainer.style.height = `calc(100vh - ${chatWindow.offsetTop + 120}px)`;
            } else {
                messagesContainer.style.height = 'calc(100vh - 180px)';
            }
        }

        handleResize();

        window.addEventListener('resize', handleResize);

        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                if (window.innerWidth < 768) {
                    this.style.fontSize = '16px';
                }
            });
        });
        setupMessageOptions();
    </script>

    <script>
        document.getElementById('profile-box').addEventListener('click', function() {
            var profileModal = new bootstrap.Modal(document.getElementById('profileModal'));
            profileModal.show();
        });
    </script>

    <script>
        document.getElementById('search').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const contacts = document.querySelectorAll('.contact');

            contacts.forEach(contact => {
                const name = contact.querySelector('strong').textContent.toLowerCase();
                contact.style.display = name.includes(filter) ? '' : 'none';
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            const messageInput = document.getElementById('messageInput');
            const form = document.querySelector('.chat-input');

            // Handle file selection
            fileInput.addEventListener('change', function() {
                filePreview.innerHTML = '';

                if (this.files.length > 0) {
                    filePreview.style.display = 'block';

                    for (let i = 0; i < this.files.length; i++) {
                        const file = this.files[i];
                        const fileItem = document.createElement('div');
                        fileItem.className = 'file-preview-item';

                        let iconClass = 'fa-file';
                        if (file.type.startsWith('image/')) {
                            iconClass = 'fa-image';
                        } else if (file.type.startsWith('video/')) {
                            iconClass = 'fa-video';
                        } else if (file.type === 'application/pdf') {
                            iconClass = 'fa-file-pdf';
                        }

                        fileItem.innerHTML = `
                            <i class="fas ${iconClass}"></i>
                            <span>${file.name}</span>
                            <span class="remove-file" data-index="${i}">&times;</span>
                        `;

                        filePreview.appendChild(fileItem);
                    }
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // Handle file removal
            filePreview.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-file')) {
                    const index = e.target.getAttribute('data-index');
                    const dt = new DataTransfer();
                    const {
                        files
                    } = fileInput;

                    for (let i = 0; i < files.length; i++) {
                        if (i !== parseInt(index)) {
                            dt.items.add(files[i]);
                        }
                    }

                    fileInput.files = dt.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });

            // Update your form submission handler
            form.addEventListener('submit', function(e) {
                // Validate either message or file exists
                if (messageInput.value.trim() === '' && fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Please enter a message or attach a file');
                    return;
                }

                // Create FormData object to properly handle file uploads
                const formData = new FormData(form);

                // Submit via AJAX for better handling
                e.preventDefault();

                fetch('', { // Empty string submits to same URL
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.ok) {
                            // Reload messages after successful submission
                            loadMessages();
                            // Clear form
                            messageInput.value = '';
                            fileInput.value = '';
                            filePreview.innerHTML = '';
                            filePreview.style.display = 'none';
                        } else {
                            throw new Error('Network response was not ok');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error sending message. Please try again.');
                    });
            });
        });
    </script>

    <script>
        // Add touch event handlers for mobile
        function setupMessageOptions() {
            const messagesContainer = document.getElementById('chatMessages');
            let longPressTimer;
            let activeMessage = null;

            // For touch devices - long press
            messagesContainer.addEventListener('touchstart', function(e) {
                const messageElement = e.target.closest('.message.sent');
                if (messageElement) {
                    activeMessage = messageElement;
                    longPressTimer = setTimeout(() => {
                        showMessageOptions(messageElement);
                        e.preventDefault();
                    }, 1000);
                }
            });

            messagesContainer.addEventListener('touchend', function(e) {
                clearTimeout(longPressTimer);
            });

            messagesContainer.addEventListener('touchmove', function(e) {
                clearTimeout(longPressTimer);
            });

            // For desktop - right click
            messagesContainer.addEventListener('contextmenu', function(e) {
                const messageElement = e.target.closest('.message.sent');
                if (messageElement) {
                    e.preventDefault();
                    showMessageOptions(messageElement);
                }
            });

            // Hide options when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.message-options') && !e.target.closest('.message.sent')) {
                    hideMessageOptions();
                }
            });

            // Handle option clicks
            messagesContainer.addEventListener('click', function(e) {
                const optionsElement = e.target.closest('.message-options');
                if (!optionsElement) return;

                const messageElement = optionsElement.closest('.message.sent');
                if (!messageElement) return;

                const messageId = messageElement.dataset.messageId;
                if (!messageId) return;

                const editBtn = e.target.closest('.edit-message');
                const deleteBtn = e.target.closest('.delete-message');

                if (editBtn) {
                    e.preventDefault();
                    editMessage(messageId, messageElement);
                    hideMessageOptions();
                } else if (deleteBtn) {
                    e.preventDefault();
                    deleteMessage(messageId, messageElement);
                    hideMessageOptions();
                }
            });
        }

        function showMessageOptions(messageElement) {
            // Hide any currently shown options
            hideMessageOptions();

            // Show options for this message
            const options = messageElement.querySelector('.message-options');
            if (options) {
                options.style.display = 'block';
            }
        }

        function hideMessageOptions() {
            document.querySelectorAll('.message-options').forEach(options => {
                options.style.display = 'none';
            });
        }

        function editMessage(messageId, messageElement) {
            const textElement = messageElement.querySelector('.text');
            const originalText = textElement ? textElement.textContent : '';

            // Create an input field
            const input = document.createElement('input');
            input.type = 'text';
            input.value = originalText;
            input.className = 'form-control';

            // Replace text with input field
            if (textElement) {
                textElement.innerHTML = '';
                textElement.appendChild(input);
                input.focus();
            }

            // Handle saving
            input.addEventListener('blur', function() {
                const newText = input.value.trim();
                if (newText && newText !== originalText) {
                    // Send update to server
                    fetch('update_message.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `message_id=${messageId}&message=${encodeURIComponent(newText)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (textElement) {
                                    textElement.textContent = newText;
                                }
                            } else {
                                if (textElement) {
                                    textElement.textContent = originalText;
                                }
                                alert('Failed to update message');
                            }
                        })
                        .catch(error => {
                            if (textElement) {
                                textElement.textContent = originalText;
                            }
                            console.error('Error:', error);
                        });
                } else {
                    if (textElement) {
                        textElement.textContent = originalText;
                    }
                }
            });

            // Also save on Enter key
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    input.blur();
                }
            });
        }

        function deleteMessage(messageId, messageElement) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            fetch('delete_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `message_id=${messageId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageElement.remove();
                        // If this was the last message, show "no messages" placeholder
                        const container = document.getElementById('chatMessages');
                        if (container.children.length === 0) {
                            container.innerHTML = `
                    <div class="no-messages-yet">
                        <h5>No messages yet</h5>
                        <p class="text-muted">Say hello to start the conversation!</p>
                    </div>`;
                        }
                    } else {
                        alert('Failed to delete message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        // Call this function after loading messages
        setupMessageOptions();
    </script>

    <script>
        let typingTimer;
        let isTyping = false;
        let lastTypingTime = 0;

        messageInput.addEventListener('input', () => {
            const now = Date.now();

            if (now - lastTypingTime > 300) {
                if (!isTyping) {
                    isTyping = true;
                    sendTypingStatus(true);
                }

                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    isTyping = false;
                    sendTypingStatus(false);
                }, 1500);
            }

            lastTypingTime = now;
        });

        messageInput.addEventListener('blur', () => {
            if (isTyping) {
                isTyping = false;
                sendTypingStatus(false);
                clearTimeout(typingTimer);
            }
        });

        function sendTypingStatus(typing) {
            if (!chatWith) return;

            fetch('typing_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `receiver_id=${chatWith}&typing=${typing ? 1 : 0}`
            }).catch(error => console.error('Error sending typing status:', error));
        }

        function checkTypingStatus() {
            if (!chatWith) return;

            fetch(`get_typing_status.php?receiver_id=${chatWith}&_=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    const typingIndicator = document.getElementById('typingIndicator');
                    typingIndicator.style.display = data.is_typing ? 'flex' : 'none';
                })
                .catch(error => console.error('Error checking typing status:', error));
        }

        let typingCheckInterval = setInterval(checkTypingStatus, 1000);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(typingCheckInterval);
            } else {
                clearInterval(typingCheckInterval);
                typingCheckInterval = setInterval(checkTypingStatus, 1000);
                checkTypingStatus();
            }
        });
    </script>

    <script>
        document.querySelector('.sidebar-toggle').addEventListener('click', function() {
            const chatList = document.querySelector('.chat-list');
            const profileBox = document.getElementById('profile-box');

            chatList.classList.toggle('active');

            if (chatList.classList.contains('active')) {
                profileBox.classList.add('fixed');
            } else {
                profileBox.classList.remove('fixed');
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const icon = themeToggle.querySelector('i');

            function updateIconStyle(isDark) {
                if (isDark) {
                    icon.classList.replace('fa-moon', 'fa-sun');
                    icon.style.color = '#FFC107';
                } else {
                    icon.classList.replace('fa-sun', 'fa-moon');
                    icon.style.color = '#000';
                }
            }

            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = savedTheme === 'dark' || (!savedTheme && systemPrefersDark);

            if (isDark) {
                document.body.classList.add('dark-theme');
            }
            updateIconStyle(isDark);

            themeToggle.addEventListener('click', function() {
                const darkMode = document.body.classList.toggle('dark-theme');
                localStorage.setItem('theme', darkMode ? 'dark' : 'light');
                updateIconStyle(darkMode);
            });
        });
    </script>



</body>

</html>