<?php
include("../config.php");
session_start();


// Handle form-based login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = $_POST['user'];
    $password = $_POST['password'];

    $query = "SELECT *  FROM tbl_stud_acc WHERE student_no = ? OR cvsu_email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $user, $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Check account status first
        if (isset($row['acc_status']) && $row['acc_status'] == 0) {
            $messages = ["Your account is inactive or pending approval."];
        }
        // Verify password if account is active
        else if (password_verify($password, $row['password'])) {
            $_SESSION['user'] = $user;
            $_SESSION['cvsu_email'] = $row['cvsu_email'];
            $_SESSION['college_code'] = $row['college_code'];
            $_SESSION['user_type'] = 'Student';
            $_SESSION['dept_code'] = $row['dept_code'];
            $_SESSION['first_name'] = $row['first_name'];
            $_SESSION['last_name'] = $row['last_name'];
            $_SESSION['middle_initial'] = $row['middle_initial'];
            $_SESSION['dept_code'] = $row['dept_code'];
            $_SESSION['suffix'] = $row['suffix'];
            header('Location: ../viewschedules/data_schedule_professor.php');
            exit();
        }
    }
    // Assuming the email is the user input
    $email = $user;

    // Check if the email has the @cvsu.edu.ph domain
    if (strpos($email, '@cvsu.edu.ph') !== false) {
        // Query the student account
        // $query = "SELECT * FROM tbl_stud_acc WHERE cvsu_email = ?";
        // $stmt = $conn->prepare($query);

        // if ($stmt === false) {
        //     die('Error preparing the statement: ' . $conn->error);
        // }

        // $stmt->bind_param("s", $email);
        // $stmt->execute();
        // $result = $stmt->get_result();

        // // Check if query executed and returned any results
        // if ($result === false) {
        //     die('Error executing the query: ' . $stmt->error);
        // }

        // // If student is found
        // if ($result->num_rows > 0) {
        //     // Student found
        //     $row = $result->fetch_assoc();
        //     $_SESSION['cvsu_email'] = $email;
        //     $_SESSION['user_type'] = 'Student';
        //     $_SESSION['college_code'] = $row['college_code'];
        //     $_SESSION['dept_code'] = $row['dept_code'];
        //     $_SESSION['first_name'] = $row['first_name'];
        //     $_SESSION['last_name'] = $row['last_name'];
        //     $_SESSION['middle_initial'] = $row['middle_initial'];

        //     // Redirect to student page
        //     header('Location: ../viewschedules/dashboard.php');
        //     exit();
        // } else {
        // Query the professor account
        $query_prof = "SELECT * FROM tbl_prof_acc WHERE cvsu_email = ?";
        $stmt_prof = $conn->prepare($query_prof);
        $stmt_prof->bind_param("s", $email);
        $stmt_prof->execute();
        $result_prof = $stmt_prof->get_result();

        // Query the admin account
        $query_admin = "SELECT * FROM tbl_admin WHERE cvsu_email = ?";
        $stmt_admin = $conn->prepare($query_admin);
        $stmt_admin->bind_param("s", $email);
        $stmt_admin->execute();
        $result_admin = $stmt_admin->get_result();


        // If admin is found
        if ($result_admin->num_rows > 0) {
            $row_admin = $result_admin->fetch_assoc();
            $admin_college_code = $row_admin['college_code'];
            // Verify password for admin
            if (password_verify($password, $row_admin['password'])) {
                $_SESSION['user'] = $email;
                $_SESSION['user_type'] = $row_admin['user_type'];
                $_SESSION['college_code'] = $row_admin['college_code'];
                $_SESSION['cvsu_email'] = $row_admin['cvsu_email'];
                $_SESSION['first_name'] = $row_admin['first_name'];
                $_SESSION['last_name'] = $row_admin['last_name'];
                $_SESSION['middle_initial'] = $row_admin['middle_initial'];
                $_SESSION['suffix'] = $row_admin['suffix'];


                // Update status as 'Online' for admin
                $updateStatusQueryAdmin = "UPDATE tbl_admin SET status_type = 'Online' WHERE cvsu_email = ?";
                $statusStmtAdmin = $conn->prepare($updateStatusQueryAdmin);
                $statusStmtAdmin->bind_param("s", $email);
                if (!$statusStmtAdmin->execute()) {
                    die("Error updating status for admin: " . $conn->error);
                }

                // Redirect to admin page
                header('Location: ../new-admin/index.php');
                exit();
            }
        }

        // If professor is found
        if ($result_prof->num_rows > 0) {
            $is_valid = false;
            while ($row_prof = $result_prof->fetch_assoc()) {
                // Check account status for professor
                if (isset($row_prof['acc_status']) && $row_prof['acc_status'] == 0) {
                    $messages = ["Your account is inactive or pending approval."];
                }
                // Verify password for professor if account is active
                else if (password_verify($password, $row_prof['password'])) {
                    $_SESSION['user'] = $email;
                    $_SESSION['user_type'] = $row_prof['user_type'];
                    $_SESSION['prof_code'] = $row_prof['prof_code'];
                    $_SESSION['current_prof_code'] = $row_prof['prof_code'];
                    $_SESSION['college_code'] = $row_prof['college_code'];
                    $_SESSION['dept_code'] = $row_prof['dept_code'];
                    $_SESSION['cvsu_email'] = $email;
                    $_SESSION['suffix'] = $row_prof['suffix'];
                    $_SESSION['first_name'] = $row_prof['first_name'];
                    $_SESSION['last_name'] = $row_prof['last_name'];
                    $_SESSION['middle_initial'] = $row_prof['middle_initial'];
                    $dept_code = $row_prof['dept_code'];
                    $college_code = $row_prof['college_code'];
                    $prof_college_code = $row_prof['college_code'];
                    $prof_name = $row_prof['first_name'] . ' ' . $row_prof['middle_initial'] . ' ' . $row_prof['last_name'] . ' ' . $row_prof['suffix'];
                    $_SESSION['prof_name'] = $prof_name;

                    // Update status as 'Online' for professor
                    $updateStatusQueryProf = "UPDATE tbl_prof_acc SET status_type = 'Online' WHERE cvsu_email = ?";
                    $statusStmtProf = $conn->prepare($updateStatusQueryProf);
                    $statusStmtProf->bind_param("s", $email);
                    if (!$statusStmtProf->execute()) {
                        die("Error updating status for Instructor: " . $conn->error);
                    }

                    $fetch_info_query = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
                    $result = $conn->query($fetch_info_query);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $admin_college_code = $row['college_code'];
                    }

                    // Redirect based on user type
                    switch ($row_prof['user_type']) {
                        case 'Admin':
                            header('Location: ../new-admin/index.php');
                            break;
                        case 'Department Secretary':
                            $fetch_info_query = "SELECT ay_code FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
                            $result = $conn->query($fetch_info_query);

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $ay_code = $row['ay_code'];
                            }
                            if ($admin_college_code == $prof_college_code) {
                                $table_name_sched = "tbl_secsched_" . $dept_code . "_" . $ay_code;
                                // SQL statement to create the table if it does not exist
                                $columns_sql_sched = "CREATE TABLE IF NOT EXISTS $table_name_sched (
                                sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                section_sched_code VARCHAR(200) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                day VARCHAR(50) NOT NULL,
                                curriculum VARCHAR(100) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                course_code VARCHAR(100) NOT NULL,
                                room_code VARCHAR(100) NOT NULL,
                                prof_code VARCHAR(100) NOT NULL,
                                prof_name VARCHAR(100) NOT NULL,
                                dept_code VARCHAR(100) NOT NULL,
                                ay_code VARCHAR(100) NOT NULL,
                                cell_color VARCHAR(100) NOT NULL,
                                shared_sched VARCHAR(100) NOT NULL,
                                shared_to VARCHAR(100) NOT NULL,
                                class_type VARCHAR(100) NOT NULL
                            )";
                                // Execute the query to create the table
                                if ($conn->query($columns_sql_sched) === FALSE) {
                                    echo "Table $table_name_sched created successfully or already exists.";
                                }

                                $table_name_contact = "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code;
                                $columns_sql_contact = "CREATE TABLE IF NOT EXISTS $table_name_contact  (sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                                    prof_sched_code VARCHAR(200) NOT NULL,
                                                    semester VARCHAR(255) NOT NULL,
                                                    ay_code VARCHAR(255) NOT NULL,
                                                    prof_code VARCHAR(255) NOT NULL,
                                                    dept_code VARCHAR(255) NOT NULL,
                                                    day VARCHAR(50) NOT NULL,
                                                    time_start TIME NOT NULL,
                                                    time_end TIME NOT NULL,
                                                    consultation_hrs_type VARCHAR(100) NOT NULL)";
                                if ($conn->query($columns_sql_contact) === FALSE) {
                                    echo "Table $table_name_contact created successfully or already exists.";
                                }
                                $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                                $create_room_table_sql = " CREATE TABLE IF NOT EXISTS $sanitized_room_dept_code  (id INT AUTO_INCREMENT PRIMARY KEY,
                                                      sec_sched_id INT(100),
                                                      room_sched_code VARCHAR(255) NOT NULL,
                                                      room_code VARCHAR(255) NOT NULL,
                                                      room_in_charge VARCHAR(255) NOT NULL,
                                                      day VARCHAR(255) NOT NULL,
                                                      curriculum VARCHAR(100) NOT NULL,
                                                      time_start TIME NOT NULL,
                                                      time_end TIME NOT NULL,
                                                      course_code VARCHAR(255) NOT NULL,
                                                      section_code VARCHAR(255) NOT NULL,
                                                      prof_code VARCHAR(255) NOT NULL,
                                                      prof_name VARCHAR(255) NOT NULL,
                                                      dept_code VARCHAR(255) NOT NULL,
                                                      room_type VARCHAR(255) NOT NULL,
                                                      semester VARCHAR(255) NOT NULL,
                                                      ay_code VARCHAR(255) NOT NULL,
                                                      class_type VARCHAR(255) NOT NULL,
                                                      cell_color VARCHAR(100) NOT NULL)";
                                if ($conn->query($create_room_table_sql) === FALSE) {
                                    echo "Table $sanitized_room_dept_code created successfully or already exists.";
                                }

                                $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
                                $create_room_table_sql = " CREATE TABLE IF NOT EXISTS $sanitized_room_dept_code  (id INT AUTO_INCREMENT PRIMARY KEY,
                                                      sec_sched_id INT(100),
                                                      room_sched_code VARCHAR(255) NOT NULL,
                                                      room_code VARCHAR(255) NOT NULL,
                                                      room_in_charge VARCHAR(255) NOT NULL,
                                                      day VARCHAR(255) NOT NULL,
                                                      curriculum VARCHAR(100) NOT NULL,
                                                      time_start TIME NOT NULL,
                                                      time_end TIME NOT NULL,
                                                      course_code VARCHAR(255) NOT NULL,
                                                      section_code VARCHAR(255) NOT NULL,
                                                      prof_code VARCHAR(255) NOT NULL,
                                                      prof_name VARCHAR(255) NOT NULL,
                                                      dept_code VARCHAR(255) NOT NULL,
                                                      room_type VARCHAR(255) NOT NULL,
                                                      semester VARCHAR(255) NOT NULL,
                                                      ay_code VARCHAR(255) NOT NULL,
                                                      class_type VARCHAR(255) NOT NULL,
                                                      cell_color VARCHAR(100) NOT NULL)";
                                if ($conn->query($create_room_table_sql) === FALSE) {
                                    echo "Table $sanitized_room_dept_code created successfully or already exists.";
                                }

                                $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);
                                $create_prof_table_sql = "CREATE TABLE IF NOT EXISTS $sanitized_prof_dept_code (id INT AUTO_INCREMENT PRIMARY KEY,
                                                      sec_sched_id INT NOT NULL,
                                                      prof_sched_code VARCHAR(255) NOT NULL,
                                                      prof_code VARCHAR(255) NOT NULL,
                                                      time_start TIME NOT NULL,
                                                      time_end TIME NOT NULL,
                                                      day VARCHAR(255) NOT NULL,
                                                      curriculum VARCHAR(100) NOT NULL,
                                                      course_code VARCHAR(255) NOT NULL,
                                                      section_code VARCHAR(255) NOT NULL,
                                                      room_code VARCHAR(255) NOT NULL,
                                                      semester VARCHAR(255) NOT NULL,
                                                      dept_code VARCHAR(255) NOT NULL,
                                                      ay_code VARCHAR(255) NOT NULL,
                                                      class_type VARCHAR(255) NOT NULL,
                                                      cell_color VARCHAR(100) NOT NULL)";
                                if ($conn->query($create_prof_table_sql) === FALSE) {
                                    echo "Table $sanitized_prof_dept_code created successfully or already exists.";
                                }
                                header('Location: ../department_secretary/dept_sec.php');
                            } else {
                                header('Location: ../viewschedules/dashboard.php');
                            }
                            break;

                        case 'CCL Head':
                            $fetch_info_query = "SELECT ay_code FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
                            $result = $conn->query($fetch_info_query);

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $ay_code = $row['ay_code'];
                            }
                            $table_name_sched = "tbl_secsched_" . $dept_code . "_" . $ay_code;
                            // SQL statement to create the table if it does not exist
                            $columns_sql_sched = "CREATE TABLE IF NOT EXISTS $table_name_sched (
                                sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                section_sched_code VARCHAR(200) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                day VARCHAR(50) NOT NULL,
                                curriculum VARCHAR(100) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                course_code VARCHAR(100) NOT NULL,
                                room_code VARCHAR(100) NOT NULL,
                                prof_code VARCHAR(100) NOT NULL,
                                prof_name VARCHAR(100) NOT NULL,
                                dept_code VARCHAR(100) NOT NULL,
                                ay_code VARCHAR(100) NOT NULL,
                                cell_color VARCHAR(100) NOT NULL,
                                shared_sched VARCHAR(100) NOT NULL,
                                shared_to VARCHAR(100) NOT NULL,
                                class_type VARCHAR(100) NOT NULL
                            )";
                            // Execute the query to create the table
                            if ($conn->query($columns_sql_sched) === FALSE) {
                                echo "Table $table_name_sched created successfully or already exists.";
                            }
                            $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
                            $create_room_table_sql = " CREATE TABLE IF NOT EXISTS $sanitized_room_dept_code  (id INT AUTO_INCREMENT PRIMARY KEY,
                                                      sec_sched_id INT(100),
                                                      room_sched_code VARCHAR(255) NOT NULL,
                                                      room_code VARCHAR(255) NOT NULL,
                                                      room_in_charge VARCHAR(255) NOT NULL,
                                                      day VARCHAR(255) NOT NULL,
                                                      curriculum VARCHAR(100) NOT NULL,
                                                      time_start TIME NOT NULL,
                                                      time_end TIME NOT NULL,
                                                      course_code VARCHAR(255) NOT NULL,
                                                      section_code VARCHAR(255) NOT NULL,
                                                      prof_code VARCHAR(255) NOT NULL,
                                                      prof_name VARCHAR(255) NOT NULL,
                                                      dept_code VARCHAR(255) NOT NULL,
                                                      room_type VARCHAR(255) NOT NULL,
                                                      semester VARCHAR(255) NOT NULL,
                                                      ay_code VARCHAR(255) NOT NULL,
                                                      class_type VARCHAR(255) NOT NULL,
                                                      cell_color VARCHAR(100) NOT NULL)";
                            if ($conn->query($create_room_table_sql) === FALSE) {
                                echo "Table $sanitized_room_dept_code created successfully or already exists.";
                            }
                            $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);
                            $create_prof_table_sql = "CREATE TABLE IF NOT EXISTS $sanitized_prof_dept_code (id INT AUTO_INCREMENT PRIMARY KEY,
                                                      sec_sched_id INT NOT NULL,
                                                      prof_sched_code VARCHAR(255) NOT NULL,
                                                      prof_code VARCHAR(255) NOT NULL,
                                                      time_start TIME NOT NULL,
                                                      time_end TIME NOT NULL,
                                                      day VARCHAR(255) NOT NULL,
                                                      curriculum VARCHAR(100) NOT NULL,
                                                      course_code VARCHAR(255) NOT NULL,
                                                      section_code VARCHAR(255) NOT NULL,
                                                      room_code VARCHAR(255) NOT NULL,
                                                      semester VARCHAR(255) NOT NULL,
                                                      dept_code VARCHAR(255) NOT NULL,
                                                      ay_code VARCHAR(255) NOT NULL,
                                                      class_type VARCHAR(255) NOT NULL,
                                                      cell_color VARCHAR(100) NOT NULL)";
                            if ($conn->query($create_prof_table_sql) === FALSE) {
                                echo "Table $sanitized_prof_dept_code created successfully or already exists.";
                            }
                            header('Location: ../department_secretary/ccl_head.php');
                            break;
                        case 'Professor':
                        case 'Department Chairperson':
                        case 'Registration Adviser':
                            header('Location: ../viewschedules/data_schedule_professor.php');
                            break;
                        default:
                            header('Location: login.php');
                    }
                    exit();
                }
            }
        }

        // If no user found or password incorrect
        $messages = [
            "Username or password is incorrect.",
            "Use your CvSu Email."
        ];

    } else {
        // Email domain is not valid
        $messages = ["Username or password is incorrect.", "Use your CvSu Email/Student Number."];
    }

    echo '
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const messages = ' . json_encode($messages) . ';
    const modalBody = document.querySelector("#loginErrorModal .modal-body");
    
    // Clear modal body and populate with messages
    modalBody.innerHTML = ""; 
    messages.forEach(message => {
      const paragraph = document.createElement("p");
      paragraph.textContent = message;
      modalBody.appendChild(paragraph);
    });

    // Show the modal
    var errorModal = new bootstrap.Modal(document.getElementById("loginErrorModal"));
    errorModal.show();
  });
</script>
';
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSys</title>
    <link rel="icon" type="image/png" href="../images/orig-logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script src="/SchedSys3/jquery.js"></script>
    <link rel="stylesheet" href="/SchedSys3/font-awesome-6-pro-main/css/all.min.css">
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <link rel="stylesheet" href="../../css/login/login.css">
</head>

<body>
    <div class="container">
        <div class="flex-container">
            <div class="left-column">
                <img class="logo" src="../../images/orig-logo.png">
            </div>
            <div class="right-column">
                <div class="form-container">
                    <form method="POST" action="login.php">
                        <h1>Welcome!</h1><br>
                        <input type="text" class="form-control" placeholder="Student Number / CVSU Email" name="user"
                            required>
                        <div class="password-container">
                            <input type="password" id="password" class="form-control" placeholder="Password"
                                name="password" required>
                            <i id="togglepassword" class="fa-regular fa-eye-slash password-icon"></i>
                        </div>

                        <div class="create-account" style="text-align:center;">
                            <a href="forgot_password.php">Forgot Your Password?</a><br>
                            <a href="register_prof.php">Sign Up</a>
                        </div>
                        <script src="../../javascript/login.js"></script>
                        <button type="submit" name="login">Login</button><br>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Structure -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">Create an Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Select the type of account you want to create:</p>
                    <div class="d-grid gap-2">
                        <a class="btn" href="register_stud.php">Register as Student</a>
                        <a class="btn" href="register_prof.php">Register as Instructor</a>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div class="modal fade" id="loginErrorModal" tabindex="-1" aria-labelledby="loginErrorModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginErrorModalLabel">Login Error</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Messages will be inserted dynamically here -->
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnNo" class="btn" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>