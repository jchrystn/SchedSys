<?php
include("../../php/config.php");

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user'])) {
    
    header("Location: ../login/login.php"); 

    exit();
}

// Assuming the user's college_code is stored in a variable
$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

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

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset(); 
    session_destroy(); 
    echo '<script>window.location.href="../login/login.php";</script>'; 
    exit(); 
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="admin_sidebar.css">
    <title>SchedSys</title>
</head>
<body>
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
                <a href="/SchedSys3/php/messages/users.php" class="active">
                    <i class="fa-solid fa-message"></i>
                    <h3>Message</h3>
                </a>
                <a href="/SchedSys3/php/new-admin/approve.php">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Approval</h3>
                    <span class="message-count"><?php echo $pending_count; ?></span>
                </a>
                <a href="/SchedSys3/php/new-admin/settings.php">
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

    <script>
            // Open the modal
            function openModal(event) {
            event.preventDefault(); // Prevent default link action
            document.getElementById("logoutModal").style.display = "flex";
        }

        // Close the modal
        function closeModal() {
                console.log("Closing modal"); // Check if this message appears in the console
                const modal = document.getElementById("logoutModal");
                if (modal) {
                    modal.style.display = "none";
                    console.log("Modal display set to none");
                } else {
                    console.log("Modal not found");
                }
            }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "../login/login.php";
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById("logoutModal");   
            if (event.target === modal) {
                closeModal();
            }
        };
    </script>
</body>
</html>