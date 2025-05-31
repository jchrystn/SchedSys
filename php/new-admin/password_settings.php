<?php

include("../../php/config.php");

session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user'])) {
    header("Location: ../login/login.php"); 
    exit();
}

// Retrieve session data
$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];
$last_name = $_SESSION['last_name'];
$first_name = $_SESSION['first_name'];
$middle_initial = $_SESSION['middle_initial'];
$cvsu_email = $_SESSION['cvsu_email'];

$message = '';

// Password change handling
if (isset($_POST['save-pass'])) {
    // Retrieve and sanitize inputs
    $current_password = trim($_POST['current-pass']);
    $new_password = trim($_POST['new-pass']);
    $re_password = trim($_POST['re-pass']);

    // Check if fields are not empty
    if (empty($current_password) || empty($new_password) || empty($re_password)) {
        $message = "All password fields are required.";
    } 
    // Check password requirements
    elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
    }
    elseif (!preg_match('/[0-9]/', $new_password)) {
        $message = "Password must contain at least one number.";
    }
    elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $message = "Password must contain at least one symbol.";
    }
    else {
        // Fetch the stored password for the logged-in user
        $sql = "SELECT password FROM tbl_admin WHERE college_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $college_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stored_password = $row['password'];

            // Verify the current password
            if (password_verify($current_password, $stored_password)) {
                // Check if new passwords match
                if ($new_password === $re_password) {
                    // Hash the new password
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update the password in the database
                    $update_sql = "UPDATE tbl_admin SET password = ? WHERE college_code = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $new_password_hashed, $college_code);

                    if ($update_stmt->execute()) {
                        $message = "Password updated successfully.";
                    } else {
                        $message = "Error updating password: " . $conn->error;
                    }
                } else {
                    $message = "New password and re-entered password do not match.";
                }
            } else {
                $message = "Current password is incorrect.";
            }
        } else {
            $message = "Error: College code not found.";
        }
    }
}

// counting for pending approvals
$sql = "SELECT COUNT(*) AS pending_count FROM tbl_prof_acc WHERE status = 'pending' AND college_code = ? AND semester = ? AND ay_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $college_code, $semester, $ay_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $pending_count = $row['pending_count'];
}

if (!empty($message)) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showModal('$message');
            });
          </script>";
}

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset(); 
    session_destroy(); 
    echo '<script>window.location.href="../login/login.php";</script>'; 
    exit(); 
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSys</title>
    <link rel="stylesheet" href="settings.css">    
    <link rel="stylesheet" href="../new-admin/admin_sidebar.css">
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .content {
            width: 70%;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
        }

        h5 {
            font-weight: bold;
            margin-bottom: 20px;
        }

        hr {
            border: none;
            border-top: 1px solid #ccc;
        }

        .card-body {
            padding: 18px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .form-actions {
            text-align: right;
        }

        .btn-primary {
            background-color: #FD7238;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-default {
            background-color: #e0e0e0;
            color: black;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-primary:hover, .btn-default:hover {
            opacity: 0.8;
        }

        .card {
            border-radius: 20px;
        }

        .password-requirements {
            margin-top: 15px;
            padding: 10px;
            /* background-color: #f8f9fa; */
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .requirement {
            margin-bottom: 5px;
        }
        
        /* Password eye icon styles */
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 40px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .password-toggle:hover {
            color: #495057;
        }
    </style>
</head>
<body style="background-color: #f6f6f9;">
    <!-- Sidebar Section -->
    <aside>
        <div class="toggle">
            <div class="logo">
                <h2 class="logo-name">SchedSys</span></h2>
            </div>
        </div>
        <div class="sidebar">
            <a href="/SchedSys3/php/new-admin/index.php">
                <i class="fa-solid fa-house"></i>
                <h3>Home</h3>
            </a>
            <a href="/SchedSys3/php/viewschedules/dashboard.php">
                    <i class="fa-regular fa-calendar-days"></i>
                    <h3>Schedule</h3>
                </a>
            <a href="/SchedSys3/php/new-admin/user_list.php">
                <i class="fa-solid fa-user"></i>
                <h3>Users</h3>
            </a>
            <a href="/SchedSys3/php/messages/users.php">
                <i class="fa-solid fa-message"></i>
                <h3>Message</h3>
            </a>
            <a href="/SchedSys3/php/new-admin/approve.php">
                <i class="fa-solid fa-list-check"></i>
                <h3>Approval</h3>
                <span class="message-count"><?php echo $pending_count; ?></span>
            </a>
            <a href="settings.php" class="active">
                <i class="fa-solid fa-gear"></i>
                <h3>Settings</h3>
            </a>
            <a onclick="openModal(event)">
                <i class="fa-solid fa-right-from-bracket"></i>
                <h3>Logout</h3>
            </a>
        </div>
    </aside>

    <div class="right-section">
        <!-- Profile -->
        <div class="nav">
            <div class="profile">
                <div class="info">
                    <p><b><?php echo htmlspecialchars($user_type); ?></b></p>
                    <small class="text-muted"><?php echo htmlspecialchars($college_code); ?></small>
                </div>
                <div class="profile-photo">
                    <img src="../../images/user_profile.png">
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="modal" style="display: none;">
        <div class="modal-content1">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p>
            <div class="modal-buttons">
                <button class="modal-btn logout-btn" onclick="confirmLogout()">Logout</button>
                <button class="modal-btn cancel-btn" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div class="container light-style">
        <div class="card">
            <div class="row no-gutters row-bordered row-border-light w-100">
                <div class="col-md-2 pt-0">
                    <div class="list-group list-group-flush account-settings-links">
                        <div style="display: flex; align-items: center; padding-bottom: 3vh;">
                            <i class="fa-solid fa-arrow-left" onclick="goBack()" style="cursor: pointer; font-size: 20px; margin-top: 8px; margin-left: 15px;"></i>
                            <h5 class="mt-4 mb-3" style="font-weight: bold; margin-left: 15px;">Settings</h5>
                        </div>
                        <a class="list-group-item list-group-item-action" href="settings.php">Account</a>
                        <a class="list-group-item list-group-item-action active">Password</a>
                    </div>
                </div>

                <div class="content">
                    <h5>Change Password</h5>
                    <form method="POST" action="">
                        <hr><br>
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current-pass" id="current-pass" class="form-control">
                            <i class="fa-solid fa-eye-slash password-toggle" id="current-pass-toggle" onclick="togglePasswordVisibility('current-pass')"></i>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new-pass" id="new-pass" class="form-control" oninput="checkPasswordStrength()">
                            <i class="fa-solid fa-eye-slash password-toggle" id="new-pass-toggle" onclick="togglePasswordVisibility('new-pass')"></i>
                        </div>
                        <div class="form-group">
                            <label>Re-enter Password</label>
                            <input type="password" name="re-pass" id="re-pass" class="form-control" oninput="checkPasswordMatch()">
                            <i class="fa-solid fa-eye-slash password-toggle" id="re-pass-toggle" onclick="togglePasswordVisibility('re-pass')"></i>
                            <!-- <span id="password-match-message"></span> -->
                        </div>
                        
                        <div class="password-requirements">
                            <h6>Password Requirements:</h6>
                            <div class="requirement" id="length-req">• Minimum 8 characters</div>
                            <div class="requirement" id="number-req">• At least one number</div>
                            <div class="requirement" id="symbol-req">• At least one symbol</div>
                        </div><br>

                        <div class="form-actions">
                            <button type="submit" class="btn-primary" name="save-pass">Save changes</button>
                            <button type="reset" class="btn-default">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content" 
            style="background-color: #fefefe;
                    padding: 30px;
                    border-radius: 30px;
                    width: 400px;
                    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
                    text-align: center;">
            <p id="modalMessage">Your message here</p>
            <button class="close-btn" onclick="closeModal();" style="background-color: #FD7238; color: white; border: 1px solid #FD7238">Close</button>
        </div>
    </div>

    <script>
        function goBack() {
            window.location.href = "settings.php";
        }

        // Function to show the modal with a custom message
        function showModal(message) {
            document.getElementById("modalMessage").innerText = message;
            document.getElementById("messageModal").style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById("messageModal").style.display = "none";
            document.getElementById("logoutModal").style.display = "none";
        }

        // Open the logout modal
        function openModal(event) {
            event.preventDefault(); 
            document.getElementById("logoutModal").style.display = "flex";
        }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "password_settings.php?logout=1";
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById("logoutModal");
            if (event.target === modal) {
                closeModal();
            }
        };

        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById("new-pass").value;
            
            // Check length
            if (password.length >= 8) {
                document.getElementById("length-req").style.color = "green";
            } else {
                document.getElementById("length-req").style.color = "red";
            }
            
            // Check for number
            if (/[0-9]/.test(password)) {
                document.getElementById("number-req").style.color = "green";
            } else {
                document.getElementById("number-req").style.color = "red";
            }
            
            // Check for special character
            if (/[^A-Za-z0-9]/.test(password)) {
                document.getElementById("symbol-req").style.color = "green";
            } else {
                document.getElementById("symbol-req").style.color = "red";
            }
        }

        // Check if passwords match
        function checkPasswordMatch() {
            const password = document.getElementById("new-pass").value;
            const confirmPassword = document.getElementById("re-pass").value;
            const messageSpan = document.getElementById("password-match-message");
            
            if (password === confirmPassword && confirmPassword !== '') {
                messageSpan.innerHTML = "Passwords match!";
                messageSpan.style.color = "green";
            } else if (confirmPassword !== '') {
                messageSpan.innerHTML = "Passwords do not match!";
                messageSpan.style.color = "red";
            } else {
                messageSpan.innerHTML = "";
            }
        }
        
        // Toggle password visibility function
        function togglePasswordVisibility(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(inputId + "-toggle");
            
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("fa-eye-slash");
                toggleIcon.classList.add("fa-eye");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("fa-eye");
                toggleIcon.classList.add("fa-eye-slash");
            }
        }
    </script>
</body>
</html>