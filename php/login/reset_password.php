<?php
include("../config.php");
session_start();

$message = "";
$show_modal = false;

if (isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];

    if (isset($_REQUEST['continue'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['cpassword'];

        // Check if passwords match
        if ($password !== $confirm_password) {
            $message = "Passwords do not match. Please try again.";
            $show_modal = true;
        } elseif (strlen($password) < 8) {
            $message = "Password must be at least 8 characters long.";
            $show_modal = true;
        } elseif (!preg_match('/[0-9]/', $password)) {
            $message = "Password must contain at least one number.";
            $show_modal = true;
        } elseif (!preg_match('/[\W_]/', $password)) { // \W matches non-word characters (symbols)
            $message = "Password must contain at least one symbol.";
            $show_modal = true;
        } else {
            // Sanitize the input
            $email = mysqli_real_escape_string($conn, $email);

            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Check if the email exists in tbl_stud_acc
            $sql_check_student = "SELECT * FROM tbl_stud_acc WHERE cvsu_email='$email'";
            $result_student = mysqli_query($conn, $sql_check_student);

            if (mysqli_num_rows($result_student) > 0) {
                // Update password in tbl_stud_acc with the hashed password
                $sql_update_student = "UPDATE tbl_stud_acc SET password='$hashed_password' WHERE cvsu_email='$email'";
                $result_update_student = mysqli_query($conn, $sql_update_student);

                if ($result_update_student) {
                    $message = "Your password changed successfully. You can now log in with your new password.";
                    $show_modal = true;
                    header("Location: login.php");
                    exit();
                } else {
                    $message = "Failed to change your password! " . mysqli_error($conn);
                    $show_modal = true;
                }
            } else {
                // Check if the email exists in tbl_prof_acc
                $sql_check_prof = "SELECT * FROM tbl_prof_acc WHERE cvsu_email='$email'";
                $result_prof = mysqli_query($conn, $sql_check_prof);

                if (mysqli_num_rows($result_prof) > 0) {
                    // Update password in tbl_prof_acc with the hashed password
                    $sql_update_prof = "UPDATE tbl_prof_acc SET password='$hashed_password' WHERE cvsu_email='$email'";
                    $result_update_prof = mysqli_query($conn, $sql_update_prof);

                    if ($result_update_prof) {
                        $message = "Your password changed successfully. You can now log in with your new password.";
                        $show_modal = true;
                        header("Location: login.php");
                        exit();
                    } else {
                        $message = "Failed to change your password! " . mysqli_error($conn);
                        $show_modal = true;
                    }
                } else {
                    $message = "Email not found.";
                    $show_modal = true;
                }
            }
        }
    }
} else {
    header("Location: forgot_password.php");
    exit();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../../css/login/forgot_password.css">
</head>
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
            font-size: 15px;
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
            border-radius: 3px;
        }

        .close-btn:hover {
            background-color: #FF5722;
        }
</style>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-4 offset-md-4 form">
                <form action="" method="POST" name="reset_pass" autocomplete="" onsubmit="return validateForm();">
                    
                    <br><h2 class="text-center">Reset Password</h2><br>
                    <div class="form-group">
                        <input class="form-control" type="password" id="password" name="password" placeholder="Password" required>
                        <i id="togglepassword" class="fa-regular fa-eye-slash password-icon1" onclick="togglePasswordVisibility('password', 'togglepassword')"></i>
                    </div>
                    <div class="form-group">
                        <input class="form-control" type="password" id="cpassword" name="cpassword" placeholder="Confirm Password" required>
                        <i id="togglepassword1" class="fa-regular fa-eye-slash password-icon2" onclick="togglePasswordVisibility('cpassword', 'togglepassword1')"></i>
                    </div>
                    <div class="form-group">
                        <input class="form-control button" type="submit" name="continue" value="Continue">
                    </div>
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

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            var input = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }
        
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