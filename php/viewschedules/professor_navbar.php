<?php

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user session details
$dept_code = htmlspecialchars($_SESSION["dept_code"] ?? '');
$prof_code = htmlspecialchars($_SESSION["prof_code"] ?? 'student');
$first_name = htmlspecialchars($_SESSION["first_name"] ?? '');
$last_name = htmlspecialchars($_SESSION['last_name'] ?? '');
$middle_initial = htmlspecialchars($_SESSION['middle_initial'] ?? '');
$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';


$current_user_type = null;
// Get current user's email
$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');
date_default_timezone_set('Asia/Manila');

$current_date = date('Y-m-d');
$yesterday_date = date('Y-m-d', strtotime('yesterday'));  // Date yesterday
$one_month_ago = date('Y-m-d', strtotime('-1 month'));  // Date 1 month ago

if ($user_type == "Department Chairperson") {
    $notif_user_type = "professor";
} else {
    $notif_user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
}

$fetch_info_query_ay = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result_Ay = $conn->query($fetch_info_query_ay);

if ($result_Ay->num_rows > 0) {
    $row = $result_Ay->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
 

    $_SESSION['ay_code'] = $ay_code;
    $_SESSION['semester'] = $semester;
}



if ($user_type != "Student") {
    $fetch_info_query = "SELECT reg_adviser, college_code, user_type, dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
    $result_reg = $conn->query($fetch_info_query);

    if ($result_reg->num_rows > 0) {
        $row = $result_reg->fetch_assoc();
        $not_reg_adviser = $row['reg_adviser'];
        $user_college_code = $row['college_code'];
        $true_user_type = $row['user_type'];
        $account_dept_code = $row['dept_code'];


        if ($not_reg_adviser == 1) {
            $current_user_type = "Registration Adviser" ?? '';
        } else {
            $current_user_type = null;
        }
    }
} else {
    $fetch_info_query = "SELECT college_code, dept_code FROM tbl_stud_acc WHERE cvsu_email = '$current_user_email'";
    $result_reg = $conn->query($fetch_info_query);

    if ($result_reg->num_rows > 0) {
        $row = $result_reg->fetch_assoc();
        $not_reg_adviser = null;
        $user_college_code = $row['college_code'];
        $true_user_type = null;
        $account_dept_code = $row['dept_code'];
        $current_user_type = null;

    }
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}

// Ensure variables are set for admin users
if ($user_type == 'Admin') {
    $user_college_code = $admin_college_code;
    $account_dept_code = 'Admin'; 
    $user_type = 'Professor/Admin';
}

$new_notifications = [];
$yesterday_notifications = [];
$old_notifications = [];

// Query for notifications from tbl_notifications within the last month
$notification_sql = "SELECT id, message, DATE(date_sent) AS date_only, date_sent, is_read
                     FROM tbl_notifications 
                     WHERE receiver_email = ? 
                     AND DATE(date_sent) BETWEEN ? AND ?  -- Filter for notifications within the last month
                     AND ay_code = ? AND semester = ?  -- Filter for current AY code and semester
                     ORDER BY DATE(date_sent) DESC";
$notification_stmt = $conn->prepare($notification_sql);
$notification_stmt->bind_param('sssss', $current_user_email, $one_month_ago, $current_date, $ay_code, $semester);
$notification_stmt->execute();
$notification_result = $notification_stmt->get_result();
if ($notification_result->num_rows > 0) {
    while ($row = $notification_result->fetch_assoc()) {
        // Format the full date_sent for display
        $row['date_sent'] = date('h:i A, M d, Y', strtotime($row['date_sent']));

        // Categorize notifications based on date
        if ($row['date_only'] == $current_date) {
            $new_notifications[] = $row;  // Add to new notifications
        } elseif ($row['date_only'] == $yesterday_date) {
            $yesterday_notifications[] = $row;  // Add to yesterday notifications
        } else {
            $old_notifications[] = $row;  // Add to old notifications
        }
    }
}

// Query for notifications from tbl_stud_prof_notif within the last month
$notifications_sql_2 = "SELECT id, message, DATE(date_sent) AS date_only, date_sent, is_read
                         FROM tbl_stud_prof_notif 
                         WHERE receiver_type = ? 
                         AND DATE(date_sent) BETWEEN ? AND ?  -- Filter for notifications within the last month
                         AND ay_code = ? AND semester = ?  -- Filter for current AY code and semester
                         ORDER BY DATE(date_sent) DESC";
$notification_stmt_2 = $conn->prepare($notifications_sql_2);
$notification_stmt_2->bind_param('sssss', $user_type, $one_month_ago, $current_date, $ay_code, $semester);
$notification_stmt_2->execute();
$notification_result_2 = $notification_stmt_2->get_result();

if ($notification_result_2->num_rows > 0) {
    while ($row = $notification_result_2->fetch_assoc()) {
        // Format the full date_sent for display
        $row['date_sent'] = date('h:i A, M d, Y', strtotime($row['date_sent']));

        // Categorize notifications based on date
        if ($row['date_only'] == $current_date) {
            $new_notifications[] = $row;  // Add to new notifications
        } elseif ($row['date_only'] == $yesterday_date) {
            $yesterday_notifications[] = $row;  // Add to yesterday notifications
        } else {
            $old_notifications[] = $row;  // Add to old notifications
        }
    }
}

// Sort the notifications (sorting by date, but it's already ordered by the database query)
usort($new_notifications, fn($a, $b) => strtotime($b['date_only']) - strtotime($a['date_only']));
usort($yesterday_notifications, fn($a, $b) => strtotime($b['date_only']) - strtotime($a['date_only']));
usort($old_notifications, fn($a, $b) => strtotime($b['date_only']) - strtotime($a['date_only']));


if (isset($_POST['mark_all_read']) && $_POST['mark_all_read'] == true) {
    $current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';
    $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';  // Assuming this is stored in session

    // Update all unread notifications in tbl_notifications for the current user (by receiver_email)
    $update_sql_1 = "UPDATE tbl_notifications SET is_read = 1 WHERE receiver_email = ? AND is_read = 0";
    $update_stmt_1 = $conn->prepare($update_sql_1);
    $update_stmt_1->bind_param('s', $current_user_email);

    // Update all unread notifications in tbl_stud_prof_notif for the current user (by receiver_type)
    $update_sql_2 = "UPDATE tbl_stud_prof_notif SET is_read = 1 WHERE receiver_type = ? AND is_read = 0";
    $update_stmt_2 = $conn->prepare($update_sql_2);
    $update_stmt_2->bind_param('s', $user_type);

    // Execute the updates
    $update_successful_1 = $update_stmt_1->execute();
    $update_successful_2 = $update_stmt_2->execute();

    if ($update_successful_1 && $update_successful_2) {
        // Fetch the new unread notification count for tbl_notifications
        $unread_count_sql = "SELECT COUNT(*) AS unread_count FROM tbl_notifications WHERE receiver_email = ? AND is_read = 0";
        $unread_count_stmt = $conn->prepare($unread_count_sql);
        $unread_count_stmt->bind_param('s', $current_user_email);
        $unread_count_stmt->execute();
        $unread_count_result = $unread_count_stmt->get_result();
        $unread_count = $unread_count_result->fetch_assoc()['unread_count'];

        // Return success and the updated count as JSON
        echo json_encode(['success' => true, 'unread_count' => $unread_count]);
    } else {
        // Return error response if any of the updates fails
        echo json_encode(['success' => false, 'error' => 'Failed to update notifications as read.']);
    }

    // Close statements
    $update_stmt_1->close();
    $update_stmt_2->close();
    $unread_count_stmt->close();
    exit();
}


if (isset($_GET['logout'])) {
    // Update the status in the tbl_prof_acc table to 'Offline now'
    $updateStatusQuery = "UPDATE tbl_prof_acc SET status_type = 'Offline' WHERE cvsu_email = ?";
    $update_stmt = $conn->prepare($updateStatusQuery);
    $update_stmt->bind_param('s', $current_user_email);
    $update_stmt->execute();
    $update_stmt->close();

    // Clear the session data
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page with a quick refresh
    echo "<script>
            window.location.href = '/SchedSys3/php/login/login.php';
          </script>";
    exit();
}

$pending_count_sql = "SELECT COUNT(*) AS pending_count FROM tbl_stud_acc WHERE status = 'Pending' AND dept_code = ? AND reg_adviser = ?";
$pending_count_stmt = $conn->prepare($pending_count_sql);
$pending_count_stmt->bind_param('ss', $dept_code, $prof_code);
$pending_count_stmt->execute();
$pending_count_result = $pending_count_stmt->get_result();
$pending_count = $pending_count_result->fetch_assoc()['pending_count'];

// $stmt->close(); // Close the statement after use
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/SchedSys3/images/logo.png">
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/font-awesome-6-pro-main/css/all.min.css">
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <link rel="stylesheet" href="/SchedSys3/css/department_secretary/navbar.css">
    <script src="/SchedSys3/jquery.js"></script>

    <div class="header">
        <nav class="navbar navbar-expand-sm">
            <div class="navbar-left">
                <div class="dropdown ">
                    <div class="hamburger-menu" onclick="toggleDropdown(this)">
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                    </div>

                    <div class="sidebar">
                        <div class="profile-container">
                            <div class="profile-dept">
                                <?php echo $account_dept_code; ?>
                            </div>
                            <div class="profile-image">
                                <img src="/SchedSys3/images/users.jpg" alt="Profile Picture">
                            </div>
                            <div class="profile-name">
                                <?php echo htmlspecialchars($_SESSION["first_name"]) . ' ' . htmlspecialchars($_SESSION["middle_initial"]) . ' ' . htmlspecialchars($_SESSION["last_name"]); ?>
                            </div>
                            <div class="profile-role"><?php echo htmlspecialchars($_SESSION["cvsu_email"]); ?></div>
                            <div class="profile-role"><?php echo $user_type; ?></div>
                            <div class="profile-role"><?php echo $current_user_type; ?></div>
                        </div>
                        
                        <?php if ($_SESSION['user_type'] != 'Student' && $_SESSION['user_type'] != 'Admin' && $admin_college_code == $user_college_code): ?>
                            <a class="dropdown-item" href="/SchedSys3/php/viewschedules/profile.php">
                                <i class="fa-sharp fa-regular fa-id-card"></i> Profile</a>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_type'] == 'Admin'): ?>
                            <a class="dropdown-item" href="/SchedSys3/php/new-admin/index.php"><i
                                    class="far fa-home"></i> Home</a>
                        <?php else: ?>
                            <a class="dropdown-item" href="/SchedSys3/php/viewschedules/dashboard.php"><i
                                    class="far fa-home"></i> Dashboard</a>
                        <?php endif; ?>


                        <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code != $user_college_code): ?>
                            <a class="dropdown-item" href="/SchedSys3/php/department_secretary/sharedSchedule.php">
                                <i class="fa-regular fa-user-group"></i> Shared Schedule</a>
                            <a class="dropdown-item"
                                href="/SchedSys3/php/department_secretary/report/summary_othercollege.php"><i
                                    class="fa-regular fa-file-spreadsheet"></i> Minor Summary</a>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_type'] == 'Department Chairperson' && $admin_college_code == $user_college_code): ?>
                            <button class="dropdown-btn" onclick="toggleDropdownContent(event)"><i
                                    class="far fa-books"></i>Library</button>
                            <div class="dropdown-content">
                                <a class="dropdown-item"
                                    href="/SchedSys3/php/department_secretary/library/lib_section.php">Section</a>
                                <a class="dropdown-item"
                                    href="/SchedSys3/php/department_secretary/library/lib_professor.php">Professor</a>
                                <a class="dropdown-item"
                                    href="/SchedSys3/php/department_secretary/library/lib_classroom.php">Classroom</a>
                            </div>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profUnitModal">
                                <i class="far fa-list-alt"></i> Professor List
                            </a>
                        <?php endif; ?>

                        <?php if ($current_user_type === 'Registration Adviser'): ?>
                            <a class="dropdown-item" href="/SchedSys3/php/viewschedules/user_list.php">
                                <i class="far fa-copy"></i>
                                Students List
                            </a>
                        <?php endif; ?>


                        <div class="logout-container">
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=1" onclick="confirmLogout(event)"
                                class="logout-button"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="schedsys" id="schedsys">
                <p style="color: #ffff;">Schedule Management System</p>
            </div>
 
            <div class="navbar-icons">
                <a href="/SchedSys3/php/viewschedules/dashboard.php" class="nav-link"><i class="far fa-home"></i></a>
                
                <?php if ($_SESSION['user_type'] !== 'Student'): ?>
                    <a href="/SchedSys3/php/messages/users.php" class="nav-link"><i class="far fa-envelope"></i></a>
                <?php endif; ?>


                <div class="dropdown">
                    <a class="nav-link" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="far fa-bell"></i>
                        <!-- Red dot only if there are unread notifications -->
                        <?php if (count(array_filter($new_notifications, fn($notif) => !$notif['is_read'])) > 0): ?>
                            <span class="red-dot"></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdown">
                        <!-- New Notifications -->
                        <?php if (!empty($new_notifications)): ?>
                            <li class="notification-category">
                                <strong>New Notifications</strong>
                            </li>
                            <?php foreach ($new_notifications as $notification): ?>
                                <li class="notification-item" id="notif-<?php echo $notification['id']; ?>"
                                    style="color: <?php echo $notification['is_read'] ? 'gray' : 'black'; ?>;">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small><?php echo htmlspecialchars($notification['date_sent']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Yesterday's Notifications -->
                        <?php if (!empty($yesterday_notifications)): ?>
                            <li class="notification-category">
                                <strong>Yesterday's Notifications</strong>
                            </li>
                            <?php foreach ($yesterday_notifications as $notification): ?>
                                <li class="notification-item" id="notif-<?php echo $notification['id']; ?>"
                                    style="color: <?php echo $notification['is_read'] ? 'gray' : 'black'; ?>;">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small
                                        style="float: right;"><?php echo htmlspecialchars($notification['date_sent']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Old Notifications -->
                        <?php if (!empty($old_notifications)): ?>
                            <li class="notification-category">
                                <strong>Old Notifications</strong>
                            </li>
                            <?php foreach ($old_notifications as $notification): ?>
                                <li class="notification-item" id="notif-<?php echo $notification['id']; ?>"
                                    style="color: <?php echo $notification['is_read'] ? 'gray' : 'black'; ?>;">
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <small
                                        style="float: right;"><?php echo htmlspecialchars($notification['date_sent']); ?></small>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (empty($new_notifications) && empty($yesterday_notifications) && empty($old_notifications)): ?>
                            <li class="notification-item">
                                <p>No new notifications.</p>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <!-- Modal -->


    <div id="logoutModal" class="lmodal">
        <div class="lmodal-content">
            <p>Are you sure you want to logout?</p>
            <button onclick="confirmLogoutAction()">Logout</button>
            <button onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>

    <div class="modal fade" id="profUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Use 'modal-lg' for large modal size -->
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row g-4">
                        <?php
                        if (isset($_SESSION['dept_code'], $_SESSION['semester'], $_SESSION['ay_code'])) {
                            $dept_code = $_SESSION['dept_code'];
                            $semester = $_SESSION['semester'];
                            $ay_code = $_SESSION['ay_code']; // Get session variables
                        
                            // Query to get program_units for the department
                            $stmt = $conn->prepare("
                            SELECT program_units 
                            FROM tbl_department 
                            WHERE dept_code = ? 
                              AND program_units IS NOT NULL 
                              AND program_units != ''
                        ");
                            $stmt->bind_param("s", $dept_code); // Bind the parameter
                            $stmt->execute(); // Execute the query
                            $result_unit = $stmt->get_result(); // Get the result
                        
                            if ($result_unit && $result_unit->num_rows > 0) {
                                while ($row = $result_unit->fetch_assoc()) {
                                    $program_units = htmlspecialchars($row['program_units']);
                                    // Split program_units by comma
                                    $units = explode(',', $program_units);

                                    foreach ($units as $unit) {
                                        $unit = trim($unit); // Remove any extra spaces
                        
                                        // Query to count professors for each program_unit
                                        $count_stmt = $conn->prepare("
                                        SELECT COUNT(*) AS unit_count 
                                        FROM tbl_prof_acc 
                                        WHERE dept_code = ? 
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND prof_unit = ?
                                          AND status = 'approve'
                                    ");
                                        $count_stmt->bind_param("ssss", $dept_code, $semester, $ay_code, $unit);
                                        $count_stmt->execute();
                                        $count_result = $count_stmt->get_result();
                                        $unit_count = $count_result->fetch_assoc()['unit_count'] ?? 0; // Get the count
                        
                                        ?>
                                        <div class="col-md-3 col-lg-6">
                                            <div class="card shadow-sm h-100" onclick="redirectToProfInput('<?= $unit ?>')">
                                                
                                                    <div class="icon-container mb-3">
                                                        <i class="fas fa-users"></i>
                                                    </div>
                                                    <h6 class="card-title mt-2 fw-bold"><?= $unit ?></h6>
                                                    <p class="card-text text-muted"><?= $unit_count ?> Instructors</p>
                                                
                                            </div>
                                        </div>
                                        <?php
                                        $count_stmt->close(); // Close the count statement
                                    }
                                }
                            } else {
                                echo '<div class="col-12 text-center text-muted">No program units available</div>';
                            }

                            $stmt->close(); // Close the statement
                        } else {
                            echo '<div class="col-12 text-center text-muted">Required session variables are missing</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        function redirectToProfInput(profUnit) {
            // Send the selected prof_unit directly to prof_input.php
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "/SchedSys3/php/department_secretary/input_forms/prof_input.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // Redirect to the same page after setting the session
                    window.location.href = `/SchedSys3/php/department_secretary/input_forms/prof_input.php?prof_unit=${encodeURIComponent(profUnit)}`;
                }
            };
            xhr.send(`set_session=true&prof_unit=${encodeURIComponent(profUnit)}`);
        }

    </script>



    <script>
        // Function to show the modal
        function showProfUnitModal() {
            var modal = new bootstrap.Modal(document.getElementById('profUnitModal'));
            modal.show();
        }
    </script>


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
        document.getElementById('notifDropdown').addEventListener('click', function () {
            // Send AJAX request to mark all notifications as read
            fetch('dashboard.php', {  // Ensure the PHP file is correct
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=true'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to mark notifications as read
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.style.color = 'black';  // Change color to gray (read status)
                        });

                        // Remove the red dot indicating unread notifications
                        const redDot = document.querySelector('.red-dot');
                        if (redDot) redDot.remove();

                        // Optionally, update the notification count in the UI
                        updateNotificationCount(data.unread_count);
                    } else {
                        console.error('Error:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        });
        document.getElementById('notifDropdown').addEventListener('click', function () {
            // Send AJAX request to mark all notifications as read
            fetch('professor_navbar.php', {  // Ensure the PHP file is correct
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=true'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI to mark notifications as read
                        document.querySelectorAll('.notification-item').forEach(item => {
                            item.style.color = 'black';  // Change color to gray (read status)
                        });

                        // Remove the red dot indicating unread notifications
                        const redDot = document.querySelector('.red-dot');
                        if (redDot) redDot.remove();

                        // Optionally, update the notification count in the UI
                        updateNotificationCount(data.unread_count);
                    } else {
                        console.error('Error:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
        });

        // Function to update notification count in the UI
        function updateNotificationCount(count) {
            const countElement = document.getElementById('notifCount');
            if (countElement) {
                countElement.innerText = count > 0 ? count : ''; // Show count or hide if 0
            }
        }

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