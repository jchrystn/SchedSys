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

if (isset($_POST['save'])) {
    // Get the new values from the form
    $lastName = $_POST['last-name'];
    $firstName = $_POST['first-name'];
    $middleInitial = $_POST['middle-initial'];
    $cvsuEmail = $_POST['cvsu-email'];

    // Ensure the email always ends with '@cvsu.edu.ph'
    if (!str_ends_with($cvsuEmail, '@cvsu.edu.ph')) {
        // Append '@cvsu.edu.ph' if missing
        $cvsuEmail = explode('@', $cvsuEmail)[0] . '@cvsu.edu.ph';
    }

    // Check if the email ends with '@cvsu.edu.ph'
    if (filter_var($cvsuEmail, FILTER_VALIDATE_EMAIL) && str_ends_with($cvsuEmail, '@cvsu.edu.ph')) {
        // Update query to save account changes
        $sql = "UPDATE tbl_admin SET last_name = '$lastName', first_name = '$firstName', middle_initial = '$middleInitial', cvsu_email = '$cvsuEmail' WHERE college_code = '$college_code'";

        if ($conn->query($sql) === TRUE) {
            $_SESSION['last_name'] = $lastName;
            $_SESSION['first_name'] = $firstName;
            $_SESSION['middle_initial'] = $middleInitial;
            $_SESSION['cvsu_email'] = $cvsuEmail;

            // Set the success message
            $message = "Account has been updated successfully";
        } else {
            $message = "Error updating record: " . $conn->error;
        }
    } else {
        // Display error message for invalid email
        $message = "Invalid email address. Please use a valid CvSU email (example@cvsu.edu.ph).";
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

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .col {
            flex: 1;
            margin-right: 10px;
        }

        .form-group {
            margin-bottom: 20px;
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

        .email-domain {
            position: absolute;
            right: 45%;
            top: 45px;
            color: #555;
            pointer-events: none;
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
        <div class="card" style="border-radius: 20px;">
            <div class="row no-gutters row-bordered row-border-light w-100">
                <div class="col-md-2 pt-0" style="border-radius: 20px;">
                    <div class="list-group list-group-flush account-settings-links" style="border-top-left-radius: 20px;">
                        <div style="display: flex; align-items: center; padding-bottom: 3vh;">
                            <i class="fa-solid fa-arrow-left"  onclick="goBack()" style="cursor: pointer; font-size: 20px; margin-top: 8px; margin-left: 15px;"></i>
                            <h5 class="mt-4 mb-3" style="font-weight: bold; margin-left: 15px;">Settings</h5>
                        </div>
                        <a class="list-group-item list-group-item-action active">Account</a>
                        <a class="list-group-item list-group-item-action" href="password_settings.php">Password</a>
                    </div>
                </div>

                <div class="content">
                    <h5>Account Settings</h5>
                    <form method="POST" action="">
                        <hr>
                        <div class="card-body">
                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label>College</label>
                                        <input type="text" class="form-control" value="<?php echo $college_code; ?>" disabled>
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label>User Type</label>
                                        <input type="text" class="form-control" value="<?php echo $user_type; ?>" disabled>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col">
                                    <div class="form-group">
                                        <label>Last Name</label>
                                        <input type="text" name="last-name" class="form-control" oninput="validateLetters(this)" value="<?php echo $last_name; ?>">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="first-name" class="form-control" oninput="validateLetters(this)" value="<?php echo $first_name; ?>">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <label>Middle Initial</label>
                                        <input type="text" name="middle-initial" class="form-control" maxlength="1" oninput="validateMiddleInitial(this)" value="<?php echo $middle_initial; ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="position: relative;">
                                <label>CvSU E-mail</label>
                                <input type="text" class="form-control" name="cvsu-email" 
                                    value="<?php echo str_replace('@cvsu.edu.ph', '', $cvsu_email); ?>" 
                                    style="padding-right: 110px; width: 50%;">
                                <div class="email-domain">@cvsu.edu.ph</div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary" name="save">Save changes</button>
                                <button type="reset" class="btn-default">Cancel</button>
                            </div>
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
            window.history.back();
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

        // Open the modal
        function openModal(event) {
            event.preventDefault(); 
            document.getElementById("logoutModal").style.display = "flex";
        }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "settings.php?logout=1";
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById("logoutModal");
            if (event.target === modal) {
                closeModal();
            }
        };

        function validateLetters(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');  // Allow spaces as well
        }

        function validateMiddleInitial(input) {
            input.value = input.value.replace(/[^A-Za-z]/g, '').substring(0, 1);
        }
    </script>
</body>
</html>