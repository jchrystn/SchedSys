<?php
include("../../php/config.php");

$first_name = htmlspecialchars(isset($_SESSION["first_name"]) ? $_SESSION["first_name"] : '');
$last_name = htmlspecialchars(isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '');
$last_name = htmlspecialchars(isset($_SESSION['middle_initial']) ? $_SESSION['middle_initial'] : '');



// Get current user's email
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';


// Fetch all notifications for the current user, regardless of read status
$notification_sql = "SELECT id, message, date_sent, is_read FROM tbl_notifications WHERE receiver_email = ? ORDER BY date_sent DESC LIMIT 5";
$notification_stmt = $conn->prepare($notification_sql);
$notification_stmt->bind_param('s', $current_user_email);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();
$notifications = [];

if ($notification_result->num_rows > 0) {
    while ($row = $notification_result->fetch_assoc()) {
        // Format the date to a 12-hour format
        $row['date_sent'] = date('h:i A, M d, Y', strtotime($row['date_sent']));
        $notifications[] = $row;
    }
}

// Count only unread notifications
$unread_notifications = array_filter($notifications, function ($notification) {
    return $notification['is_read'] == 0; // Only count unread notifications
});
$unread_count = count($unread_notifications);

if (isset($_POST['id']) && isset($_POST['mark_read'])) {
    $id = $_POST['id'];
    // Prepare an SQL statement to update the notification status
    $update_sql = "UPDATE tbl_notifications SET is_read = 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('i', $id);

    if ($update_stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $update_stmt->close();
}
if (isset($_GET['logout'])) {
    // Ensure cvsu_email is set in the session before using it
    // Update the status in the tbl_prof_acc table to 'Offline'
    $updateStatusQuery = "UPDATE tbl_prof_acc SET status_type = 'Offline' WHERE cvsu_email = ?";
    $stmt = $conn->prepare($updateStatusQuery);

    if ($stmt === false) {
        die('Error preparing the statement: ' . $conn->error);
    }

    $stmt->bind_param("s", $_SESSION['cvsu_email']);
    if (!$stmt->execute()) {
        die("Error updating status: " . $stmt->error);
    }
    $stmt->close();

    // Clear the session data
    $_SESSION = [];

    // Destroy the session
    session_destroy();

    // Redirect to login page with an alert
    echo "<script>
            window.location.href = '/SchedSys3/php/login/login.php'; // Adjust path as needed
          </script>";
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Professor Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/SchedSys3/images/logo.png">
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/font-awesome-6-pro-main/css/all.min.css">
    <script src="/SchedSys3/jquery.js"></script>
    <!-- Google Fonts -->
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <link rel="stylesheet" href="/SchedSys3/css/department_secretary/navbar.css">

    <div class="header">
        <nav class="navbar navbar-expand-sm">
            <div class="navbar-left">
                <div class="hamburger-menu " onclick="toggleDropdown(this)">
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                </div>
                <div class="dropdown ">
                    <div class="sidebar">
                        <div class="profile-container">
                            <div class="profile-image">
                                <img src="/SchedSys3/images/users.jpg" alt="Profile Picture">
                            </div>
                            <div class="profile-name">
                                <?php echo htmlspecialchars($_SESSION["first_name"]) . ' ' . htmlspecialchars($_SESSION["middle_initial"]) . ' ' . htmlspecialchars($_SESSION["last_name"]); ?>
                            </div>
                            <div class="profile-role"><?php echo htmlspecialchars($_SESSION["cvsu_email"]); ?></div>
                            <div class="profile-role"><?php echo htmlspecialchars($_SESSION["user_type"]); ?></div>
                        </div>

                        <a class="dropdown-item" href="/SchedSys3/php/new-admin/index.php"><i
                                class="far fa-home"></i> Dashboard</a>

                        <a class="dropdown-item" href="/SchedSys3/php/new-admin/user_list.php">
                        <i class="far fa-user"></i>
                         Users </a>
                <a class="dropdown-item" href="/SchedSys3/php/messages/users.php">
                    <i class=" far fa-message"></i>
                    Message
                </a>
                <a  class="dropdown-item" href="/SchedSys3/php/new-admin/approve.php">
                    <i class="far fa-list-check"></i>

                    Approval
                
                </a>

                        <div class="logout-container">
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=1" onclick="confirmLogout(event)"
                                class="logout-button"><i class="fa-regular fa-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="navbar-icons">
                <a href="/SchedSys3/php/new-admin/admin.php" class="nav-link"><i
                        class="far fa-home"></i></a>
                <a href="/SchedSys3/php/messages/users.php" class="nav-link"><i class="far fa-envelope"></i></a>

                <div class="dropdown">
                    <a class="nav-link " href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="far fa-bell"></i>
                        <?php if ($unread_count > 0): ?>
                            <span id="notifCount" class="badge bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-item" id="notif-<?php echo $notification['id']; ?>"
                                    style="color: <?php echo $notification['is_read'] ? 'gray' : 'black'; ?>;">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small
                                        style="float: right;"><?php echo htmlspecialchars($notification['date_sent']); ?></small>
                                    <form method="POST" action="" id="form">
                                        <input type="hidden" name="id"
                                            value="<?php echo htmlspecialchars($notification['id']); ?>">
                                        <button type="submit" name="mark_read" id="form-btn"
                                            class="<?php echo $notification['is_read'] ? 'disabled' : ''; ?>" <?php echo $notification['is_read'] ? 'disabled' : ''; ?>>
                                            <?php echo $notification['is_read'] ? 'Marked' : 'Mark as Read'; ?>
                                        </button>
                                    </form>

                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="notification-item">
                                <p>No new notifications.</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>


            </div>
        </nav>
    </div>

    <div id="logoutModal" class="lmodal">
        <div class="lmodal-content">
            <p>Are you sure you want to logout?</p>
            <button onclick="confirmLogoutAction()">Logout</button>
            <button id = "close" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>


    <script>
        function toggleDropdown(element) {
            element.classList.toggle("change");
            document.querySelector('.sidebar').classList.toggle('active');
        }

        function toggleDropdownContent(event) {
            event.preventDefault();
            const dropdownContent = event.target.nextElementSibling;
            if (dropdownContent) {
                dropdownContent.classList.toggle('show');
            }
        }

        window.onclick = function (event) {
            if (!event.target.closest('.hamburger-menu') && !event.target.closest('.sidebar')) {
                var sidebar = document.querySelector('.sidebar');
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }

                var hamburger = document.querySelector('.hamburger-menu');
                if (hamburger.classList.contains('change')) {
                    hamburger.classList.remove('change');
                }

                var dropdowns = document.querySelectorAll('.dropdown-content.show');
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        }
        const closeModalBtn = document.getElementById("closeModalBtn");
        closeModalBtn.addEventListener("click", () => {
            logoutModal.style.display = "none";
        });

        function confirmLogout(event) {
            event.preventDefault(); // Prevent default link behavior
            document.getElementById("logoutModal").style.display = "block"; // Show modal
        }

        function confirmLogoutAction() {
            // Redirect to logout URL when the user confirms
            window.location.href = "<?php echo $_SERVER['PHP_SELF']; ?>?logout=1";
        }

        function closeLogoutModal() {
            document.getElementById("logoutModal").style.display = "none"; // Hide modal
        }


    </script>