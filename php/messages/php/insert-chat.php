<?php
session_start();
if (isset($_SESSION['cvsu_email'])) {
    include_once "../../config.php";

    $incoming_id = mysqli_real_escape_string($conn, $_POST['incoming_id']);
    $outgoing_id = $_SESSION['cvsu_email'];
    $semester = mysqli_real_escape_string($conn, $_SESSION['semester']);
    $ay_code = mysqli_real_escape_string($conn, $_SESSION['ay_code']);
    $college_code = mysqli_real_escape_string($conn, $_SESSION['college_code']);

    // Fetch the latest active academic year and semester if not set
    if (empty($ay_code) || empty($semester)) {
        $fetch_info = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = '$college_code' AND active = '1' LIMIT 1";
        $result_ay = $conn->query($fetch_info);

        if ($result_ay->num_rows > 0) {
            $row = $result_ay->fetch_assoc();
            $ay_code = $row['ay_code'];
            $semester = $row['semester'];

            $_SESSION['ay_code'] = $ay_code;
            $_SESSION['semester'] = $semester;
        }
    }

    $max_file_size = 25 * 1024 * 1024; // 25 MB
    $allowed_extensions = ['pdf', 'xls', 'xlsx', 'doc', 'docx']; // Allowed file types

    // Flag to track whether data was inserted
    $dataInserted = false;

    // Check if the upload directory exists
    $upload_directory = '../uploads/';
    if (!is_dir($upload_directory)) {
        mkdir($upload_directory, 0777, true);
    }

    // Check if there's an uploaded file
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file_size = $_FILES['file']['size'];
        $file_name = $_FILES['file']['name']; // Keep the original filename
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION)); // Convert to lowercase
        $file_path = $upload_directory . $file_name;

        // Validate file size and extension
        if ($file_size > $max_file_size) {
            echo "File size exceeds the 25 MB limit.";
        } elseif (!in_array($file_ext, $allowed_extensions)) {
            echo "Invalid file type. Only PDF, Excel, and Word documents are allowed.";
        } else {
            // Move the uploaded file to the specified directory
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Insert file entry into the database
                $sql = mysqli_query($conn, "INSERT INTO tbl_messages (sender_email, receiver_email, file_url, semester, ay_code)
                                            VALUES ('$outgoing_id', '$incoming_id', '$file_name', '$semester', '$ay_code')")
                    or die("SQL Error: " . mysqli_error($conn));

                // Notify the recipient about the file
                $notification_message = "You have received a new file from " . $outgoing_id;
                $notification_sql = "INSERT INTO tbl_notifications (sender_email, receiver_email, message, semester, ay_code, date_sent) 
                                     VALUES (?, ?, ?, ?, ?, NOW())";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bind_param('sssss', $outgoing_id, $incoming_id, $notification_message, $semester, $ay_code);
                $notification_stmt->execute();
                $notification_stmt->close();

                echo "File uploaded and saved successfully.";
                $dataInserted = true; // Mark that data was inserted
            } else {
                echo "Failed to move the uploaded file.";
            }
        }
    }

    // Check for a message
    if (!$dataInserted && !empty($_POST['message'])) {
        $message = mysqli_real_escape_string($conn, $_POST['message']);

        // Insert message into the database
        $sql = mysqli_query($conn, "INSERT INTO tbl_messages (sender_email, receiver_email, message, semester, ay_code)
                                    VALUES ('$outgoing_id', '$incoming_id', '$message', '$semester', '$ay_code')") 
            or die("SQL Error: " . mysqli_error($conn));

        echo "Message sent successfully.";
        $dataInserted = true; // Mark that data was inserted

        // Notify the recipient about the message
        $notification_message = "You have received a new message from " . $outgoing_id;
        $notification_sql = "INSERT INTO tbl_notifications (sender_email, receiver_email, message, semester, ay_code, date_sent) 
                             VALUES (?, ?, ?, ?, ?, NOW())";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param('sssss', $outgoing_id, $incoming_id, $notification_message, $semester, $ay_code);
        $notification_stmt->execute();
        $notification_stmt->close();
    }

    // If no data was inserted
    if (!$dataInserted) {
        echo "No data was inserted.";
    }
} else {
    echo "Unauthorized access.";
}

?>