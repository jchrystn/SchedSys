<?php

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../config.php");
session_start(); 

function generateVerificationCode() {
    return mt_rand(100000, 999999);
}

$message = "";
$show_modal = false;

if (isset($_POST['continue'])) {
    if (isset($_POST['email'])) {
        $email = $_POST['email'];

        $fetch_info_query = "
            SELECT cvsu_email, acc_status FROM tbl_prof_acc WHERE cvsu_email = ?
            UNION 
            SELECT cvsu_email, acc_status FROM tbl_stud_acc WHERE cvsu_email = ?
        ";

        $stmt = $conn->prepare($fetch_info_query);
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            
            if ($row['acc_status'] == 1) {
                $_SESSION['reset_email'] = $email;
                $code = generateVerificationCode();
                
                $current_time = time();
                $expiry_time = $current_time + (3 * 60); 

                $sql_insert = "
                    INSERT INTO tbl_code (code, code_created_at, code_expiry, cvsu_email) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        code = VALUES(code), 
                        code_created_at = VALUES(code_created_at), 
                        code_expiry = VALUES(code_expiry)";
                
                $insert_stmt = $conn->prepare($sql_insert);
                $insert_stmt->bind_param("siis", $code, $current_time, $expiry_time, $email);
                
                if ($insert_stmt->execute()) {
                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'nuestrojared305@gmail.com';
                        $mail->Password   = 'bfruqzcgrhgnsrgr'; // Consider using App Passwords
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('schedsys14@gmail.com', 'SchedSys');
                        $mail->addAddress($email);

                        $mail->isHTML(true);
                        $mail->Subject = 'Verification Code';
                        $mail->Body = 'Your verification code is:<br><br><strong>' . $code . '</strong><br><br>This code will expire in 3 minutes.';

                        if (!$mail->send()) {
                            error_log('Mailer Error: ' . $mail->ErrorInfo);
                            $message = "Message could not be sent.";
                            $show_modal = true;
                        } else {
                            header("Location: verification-code.php");
                            exit();
                        }
                    } catch (Exception $e) {
                        error_log('PHPMailer Exception: ' . $e->getMessage());
                        $message = "Error sending email: " . $e->getMessage();
                        $show_modal = true;
                    }
                } else {
                    $message = "Database error occurred.";
                    $show_modal = true;
                }
                
                $insert_stmt->close();
            } else {
                $message = "Email address is inactive.";
                $show_modal = true;
            }
        } else {
            $message = "CvSU email address not found.";
            $show_modal = true;
        }
        
        $stmt->close();
    } else {
        $message = "Please provide a CvSU email address.";
        $show_modal = true;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSys</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/login/forgot_password.css">
    <style>
        /* Modal Background */
        .modal-background {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            display: flex;
            flex-direction: column;
            align-items: center; /* Horizontally center the button */
            justify-content: center;
            background-color: #fff;
            margin: 20% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 400px;
            border-radius: 30px;
            text-align: center;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content p {
            font-size: 14px;
            color: var(--color-dark);
        }

        .close-btn {
            justify-content: center;
            text-align: center;
            align-items: center;
            background-color: #FD7238;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin-top: 15px;
            border-radius: 3px;
            width: 100px;
        }

        .close-btn:hover {
            background-color: #FF5722;
        }
    </style>
</head>
<body>
    <div class="container1">
        <div class="row">
            <div class="col-md-4 offset-md-4 form">
                <form action="" method="POST" autocomplete="">
                    <br><h2 class="text-center">Forgot Password</h2><br>
                    <div class="form-group">
                        <input class="form-control" type="email" id="email" name="email" placeholder="Enter email address" required>
                    </div>
                    <div class="form-group">
                        <input class="form-control button" type="submit" name="continue" value="Continue">
                    </div>
                    <a href="login.php">Back to Login?</a>
                </form>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content">
            <p id="modalMessage"></p>
            <button class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // PHP variable to control modal display
        var showModal = <?php echo json_encode($show_modal); ?>;
        var message = <?php echo json_encode($message); ?>;

        // Function to open the modal
        function openModal(msg) {
            var modal = document.getElementById('messageModal');
            var modalMessage = document.getElementById('modalMessage');
            modalMessage.textContent = msg;
            modal.style.display = 'block';
        }

        // Function to close the modal
        function closeModal() {
            var modal = document.getElementById('messageModal');
            modal.style.display = 'none';
        }

        // Show modal if there's a message from PHP
        document.addEventListener('DOMContentLoaded', function() {
            if (showModal && message) {
                openModal(message);
            }
        });

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('messageModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>