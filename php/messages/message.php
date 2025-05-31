<?php
include("../config.php");
session_start();



// Handle message sending and replying
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_email = $_SESSION['cvsu_email'];
    $receiver_email = $_POST['receiver_email'];
    $message = isset($_POST['message']) ? $_POST['message'] : null;
    $reply_message = isset($_POST['reply_message']) ? $_POST['reply_message'] : null;
    $file = isset($_FILES['file']) ? $_FILES['file'] : (isset($_FILES['reply_file']) ? $_FILES['reply_file'] : null);

    // Handle file upload
    $file_url = handleFileUpload($file);

    if ($reply_message) {
        // Handle reply
        echo replyToMessage($sender_email, $receiver_email, $reply_message, $file_url);
    } else {
        // Handle new message
        echo sendMessage($sender_email, $receiver_email, $message, $file_url);
    }
}

// Function to check if the sender is allowed to send a message to the receiver
function canSendMessage($sender_email, $receiver_email)
{
    global $conn;

    // Query to get user types for sender and receiver
    $sender_query = "SELECT user_type FROM tbl_prof_acc WHERE cvsu_email = '$sender_email'";
    $receiver_query = "SELECT user_type FROM tbl_prof_acc WHERE cvsu_email = '$receiver_email'";

    // Fetch results
    $sender_type = mysqli_fetch_assoc(mysqli_query($conn, $sender_query))['user_type'];
    $receiver_type = mysqli_fetch_assoc(mysqli_query($conn, $receiver_query))['user_type'];

    // Logic for permissions
    if (($sender_type == 'Professor' && $receiver_type == 'Department Chairperson') ||
        ($sender_type == 'Department Chairperson' && $receiver_type == 'Professor')) {
        return false;
    }

    return true;
}

// Function to send a new message
function sendMessage($sender_email, $receiver_email, $message, $file_url = null)
{
    global $conn;

    if (!canSendMessage($sender_email, $receiver_email)) {
        return "You are not allowed to message this user.";
    }

    $query = "INSERT INTO tbl_messages (sender_email, receiver_email, message, file_url, timestamp) 
              VALUES ('$sender_email', '$receiver_email', '$message', '$file_url', NOW())";

    if (mysqli_query($conn, $query)) {
        return "Message sent successfully.";
    } else {
        return "Failed to send message: " . mysqli_error($conn);
    }
}

// Function to fetch messages for a user
function fetchMessages($receiver_email)
{
    global $conn;

    $query = "SELECT sender_email, message, timestamp, status 
              FROM tbl_messages 
              WHERE receiver_email = '$receiver_email'
              ORDER BY timestamp DESC";

    $result = mysqli_query($conn, $query);
    $messages = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $messages[] = $row;
    }

    return $messages;
}

// Function to reply to a message
function replyToMessage($sender_email, $receiver_email, $reply_message, $file_url = null)
{
    return sendMessage($sender_email, $receiver_email, $reply_message, $file_url);
}

function handleFileUpload($file)
{
    if ($file && $file['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = $file['type'];

        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = uniqid() . '_' . basename($file['name']);
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                return $file_path;
            }
        }
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<style>
    /* General styles */
    body {
        font-family: 'Arial', sans-serif;
        background-color: #f8f9fa;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    h3 {
        font-size: 24px;
        color: #333;
        margin-bottom: 20px;
    }

    /* Message Form */
    form {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    form label {
        font-size: 16px;
        color: #495057;
        margin-bottom: 8px;
    }

    form input,
    form textarea {
        width: 100%;
        padding: 10px;
        margin-bottom: 15px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: #e9ecef;
    }

    form button {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
    }

    form button:hover {
        background-color: #0056b3;
    }

    /* Inbox Styles */
    .inbox {
        margin-top: 40px;
    }

    .message {
        background-color: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .message p {
        margin: 0 0 10px;
        color: #333;
        font-size: 14px;
    }

    .message strong {
        color: #007bff;
    }

    .message form textarea {
        width: 100%;
        padding: 10px;
        margin-top: 10px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: #e9ecef;
    }

    .message form button {
        margin-top: 10px;
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
    }

    .message form button:hover {
        background-color: #5a6268;
    }

    hr {
        border-top: 1px solid #dee2e6;
    }
</style>

<body>
<div class="container mt-5">
    <!-- Message Form -->
    <form method="POST" enctype="multipart/form-data">
        <label for="receiver_email">To:</label>
        <input type="email" name="receiver_email" required class="form-control">

        <label for="message">Message:</label>
        <textarea name="message" required class="form-control"></textarea>

        <label for="file">Attach a file (image or PDF):</label>
        <input type="file" name="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">

        <button type="submit" class="btn btn-primary mt-3">Send Message</button>
    </form>

    <!-- Display Messages -->
    <h3 class="mt-5">Inbox</h3>
    <div class="inbox">
        <?php
        $messages = fetchMessages($_SESSION['cvsu_email']);

        if (!empty($messages)) {
            foreach ($messages as $msg) {
                echo "<div class='message mt-3'>";
                echo "<p><strong>From:</strong> " . $msg['sender_email'] . "</p>";
                echo "<p><strong>Message:</strong> " . $msg['message'] . "</p>";
                echo "<p><strong>Received on:</strong> " . $msg['timestamp'] . "</p>";

                // Check if there's an attached file
                if (!empty($msg['file_url'])) {
                    $file_extension = pathinfo($msg['file_url'], PATHINFO_EXTENSION);
                    echo "<p><strong>Attachment:</strong> ";
                    // If it's an image, display it, otherwise, provide a download link
                    if (in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                        echo "<img src='" . $msg['file_url'] . "' alt='Attachment' style='max-width:200px; max-height:200px;'>";
                    } else {
                        echo "<a href='" . $msg['file_url'] . "' download>Download attachment</a>";
                    }
                    echo "</p>";
                }

                // Reply form
                echo "<form method='POST' enctype='multipart/form-data'>
                        <input type='hidden' name='receiver_email' value='" . $msg['sender_email'] . "'>
                        <textarea name='reply_message' placeholder='Type your reply' class='form-control mt-2'></textarea>
                        <label for='reply_file'>Attach a file (image or PDF):</label>
                        <input type='file' name='reply_file' class='form-control mt-2' accept='.jpg,.jpeg,.png,.pdf'>
                        <button type='submit' class='btn btn-secondary mt-2'>Reply</button>
                      </form>";
                echo "</div><hr>";
            }
        } else {
            echo "<p>No messages found.</p>";
        }
        ?>
    </div>
</div>

</body>

</html>