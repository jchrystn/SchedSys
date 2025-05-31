<?php

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../../php/config.php");

session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Assuming the user's college_code is stored in a variable
$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

// fetching the academic year and semester
$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
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

$department_code = $professor_code = $last_name = $first_name = $mi = $email = $professor_type = $user_type = "";

// Handle form submissions
if (isset($_POST['save'])) {
    // $prof_id = isset($_POST['prof_id']) ? mysqli_real_escape_string($conn, $_POST['prof_id']) : '';
    $department_code = mysqli_real_escape_string($conn, $_POST['department_code']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last-name']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first-name']);
    $mi = mysqli_real_escape_string($conn, $_POST['mi']);
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $prof_unit = mysqli_real_escape_string($conn, $_POST['prof_unit']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user-type']);
    $acc_status = mysqli_real_escape_string($conn, $_POST['acc_status']);

    // Get the value of reg_adviser from the select dropdown
    $reg_adviser = isset($_POST['reg_adviser']) ? $_POST['reg_adviser'] : '0'; 

    // Ensure the email always ends with '@cvsu.edu.ph'
    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        // Append '@cvsu.edu.ph' if missing
        $email = explode('@', $email)[0] . '@cvsu.edu.ph';
    }

    // Check in tbl_prof_acc for duplicate prof_code or cvsu_email
    $check_sql_acc = "SELECT * FROM tbl_prof_acc WHERE college_code = ? AND dept_code = ? AND cvsu_email = ? AND semester = ? AND ay_code = ?";
    $check_stmt_acc = $conn->prepare($check_sql_acc);
    $check_stmt_acc->bind_param("sssss", $college_code, $department_code, $email, $semester, $ay_code);
    $check_stmt_acc->execute();
    $check_result_acc = $check_stmt_acc->get_result();

    // Error messages based on the checks
    if ($check_result_acc->num_rows > 0) {
        $message = "Instructor Code already exists.";
    } elseif ($user_type === "Department Secretary") {
        $secretary_check_sql = "SELECT * FROM tbl_prof_acc WHERE college_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND user_type = 'Department Secretary'";
        $secretary_check_stmt = $conn->prepare($secretary_check_sql);
        $secretary_check_stmt->bind_param("ssss", $college_code, $department_code, $semester, $ay_code);
        $secretary_check_stmt->execute();
        $secretary_check_result = $secretary_check_stmt->get_result();

        if ($secretary_check_result->num_rows > 0) {
            $message = "A Department Secretary already exists in the selected department.";
            $secretary_check_stmt->close();
        }
    } elseif ($user_type === "CCL Head") {
        $head_check_sql = "SELECT * FROM tbl_prof_acc WHERE college_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? AND user_type = 'CCL Head'";
        $head_check_stmt = $conn->prepare($head_check_sql);
        $head_check_stmt->bind_param("ssss", $college_code, $department_code, $semester, $ay_code);
        $head_check_stmt->execute();
        $head_check_result = $head_check_stmt->get_result();

        if ($head_check_result->num_rows > 0) {
            $message = "A CCL Head already exists in the selected department.";
            $head_check_stmt->close();
        }
    } else {
        $ay_code = htmlspecialchars($ay_code); 
        $semester = htmlspecialchars($semester);

        function generateRandomPassword($length = 6)
        {
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $charactersLength = strlen($characters);
            $randomPassword = '';
            for ($i = 0; $i < $length; $i++) {
                $randomPassword .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomPassword;
        }

        $password = generateRandomPassword();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert data into tbl_prof_acc
        $sql1 = "INSERT INTO tbl_prof_acc (college_code, dept_code, status_type, last_name, first_name, middle_initial, suffix, cvsu_email, prof_unit, user_type, reg_adviser, password, status, acc_status, semester, ay_code) 
            VALUES (?, ?, 'Offline', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approve', ?, ?, ?)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ssssssssssssss", $college_code, $department_code, $last_name, $first_name, $mi, $suffix, $email, $prof_unit, $user_type, $reg_adviser, $hashed_password, $acc_status, $semester, $ay_code);

        if ($stmt1->execute()) {
            // Retrieve the last inserted ID from tbl_prof_acc
            $prof_id = $conn->insert_id;

            // Determine the current count for professors in the same unit and department
            $count_query = "SELECT COUNT(*) AS count FROM tbl_prof WHERE prof_unit = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
            $countStmt = $conn->prepare($count_query);
            $countStmt->bind_param("ssss", $prof_unit, $department_code, $semester, $ay_code);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $current_count = 0;

            if ($row_count = $countResult->fetch_assoc()) {
                $current_count = $row_count['count'];
            }
            $countStmt->close();

            // Increment the count and generate the new prof_code
            $current_count++;
            $prof_code = strtoupper($prof_unit) . " " . $current_count;

            // Check if the professor already exists in tbl_prof
            $check_query = "SELECT * FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
            $checkStmt = $conn->prepare($check_query);
            $checkStmt->bind_param("ssss", $prof_code, $department_code, $semester, $ay_code);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows == 0) {
                // Insert a new professor record
                $sql_insert = "INSERT INTO tbl_prof 
                    (id, dept_code, prof_unit, prof_code, acc_status, semester, ay_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($sql_insert);
                $insertStmt->bind_param("sssssss", $prof_id, $department_code, $prof_unit, $prof_code, $acc_status, $semester, $ay_code);
            
                if ($insertStmt->execute()) {
                    // Insert prof_code into tbl_prof_acc default_code
                    $sql_insert_acc = "UPDATE tbl_prof_acc SET default_code = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($sql_insert_acc);
                    $updateStmt->bind_param("si", $prof_code, $prof_id);
            
                    if ($updateStmt->execute()) {
                        $message = "Instructor has been added successfully.";
                    } else {
                        $message = "Instructor added, but error updating default_code: " . $conn->error;
                    }
            
                    $updateStmt->close();
                } else {
                    $message = "Error inserting Instructor record: " . $conn->error;
                }
                $insertStmt->close();
            } else {
                $message = "Instructor already exists with prof_code: $prof_code";
            }
            $checkStmt->close();            
        } else {
            $message = "Error adding Instructor: " . $conn->error;
        }
        
        $stmt1->close();

        // For Sending the code through email
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF;                         // Disable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'smtp.gmail.com';                       // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'nuestrojared305@gmail.com';            // SMTP username
            $mail->Password   = 'bfruqzcgrhgnsrgr';                     // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
            $mail->Port       = 587;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom('schedsys14@gmail.com', 'SchedSys');         // Sender's email address and name
            $mail->addAddress($email);                                  // Recipient's email address

            //Content
            $mail->isHTML(true);                                        // Set email format to HTML
            $mail->Subject = 'Account Details';
            $mail->Body = 'Dear ' . $last_name . ',<br><br>We are pleased to provide you with your login details for your account.<br><br>
                                <strong>CVSU Email: </strong>' . $email . '<br>
                                <strong>Password: </strong>' . $password . '<br><br>
                            Please make sure to keep this information safe and secure. If you have any questions or need further assistance, feel free to contact us.<br><br>
                            Thank You.<br>';
            $mail->send();
            echo '<script>
                    window.location.href="create_prof.php";
                </script>';
            exit();
        } catch (Exception $e) {
            $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}


if (isset($_POST['update'])) {
    // Retrieve POST data safely
    $prof_id = mysqli_real_escape_string($conn, $_POST['prof_id']);
    $default_code = mysqli_real_escape_string($conn, $_POST['default_code']);
    $prof_code = mysqli_real_escape_string($conn, $_POST['prof_code']);
    $department_code = mysqli_real_escape_string($conn, $_POST['department_code']);
    $prof_unit = mysqli_real_escape_string($conn, $_POST['prof_unit']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last-name']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first-name']);
    $mi = mysqli_real_escape_string($conn, $_POST['mi']);
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_type = mysqli_real_escape_string($conn, $_POST['user-type']);
    $acc_status = mysqli_real_escape_string($conn, $_POST['acc_status']);
    $reg_adviser = mysqli_real_escape_string($conn, $_POST['reg_adviser']);

    // Ensure the email always ends with '@cvsu.edu.ph'
    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        $email = explode('@', $email)[0] . '@cvsu.edu.ph';
    }

    // Check if any record in tbl_prof has employ_status not equal to 0 for the given semester and ay_code
    $status_check_query = "SELECT COUNT(*) AS count FROM tbl_prof WHERE employ_status != 0 AND semester = ? AND ay_code = ?";
    $stmt = $conn->prepare($status_check_query);
    $stmt->bind_param("ss", $semester, $ay_code); // Bind the parameters
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] > 0) {
        // Return an error message if any employ_status is not equal to 0
        $message = "Unable to update. Scheduling has already started for this semester.";
    } else {
        if (empty($prof_code)) {
            if ($user_type === "Department Secretary") {
                // SQL to fetch the current Department Secretary for the given department, semester, and academic year
                $secretary_check_sql = "SELECT id FROM tbl_prof_acc 
                                        WHERE college_code = ? 
                                          AND dept_code = ? 
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND user_type = 'Department Secretary'";
                $secretary_check_stmt = $conn->prepare($secretary_check_sql);
                $secretary_check_stmt->bind_param("ssss", $college_code, $department_code, $semester, $ay_code);
                $secretary_check_stmt->execute();
                $secretary_check_result = $secretary_check_stmt->get_result();
            
                // Check if there is an existing Department Secretary
                if ($secretary_check_result->num_rows > 0) {
                    $existing_secretary = $secretary_check_result->fetch_assoc();
                    $existing_secretary_id = $existing_secretary['id'];
            
                    // Allow update if the current professor ID matches the existing Department Secretary ID
                    if ($existing_secretary_id == $prof_id) {
                        // Update professor account
                        $update_query = "UPDATE tbl_prof_acc 
                                        SET dept_code = ?, prof_unit = ?, last_name = ?, first_name = ?, 
                                            middle_initial = ?, suffix = ?, cvsu_email = ?, user_type = ?, 
                                            acc_status = ?, reg_adviser = ? 
                                        WHERE id = ?
                                        AND semester = ?
                                        AND ay_code = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param(
                        "sssssssssssss",
                        $department_code,
                        $prof_unit,
                        $last_name,
                        $first_name,
                        $mi,
                        $suffix,
                        $email,
                        $user_type,
                        $acc_status,
                        $reg_adviser,
                        $prof_id,
                        $semester,
                        $ay_code
                        );
                        $update_success = $stmt->execute();
                        $stmt->close();
    
                        $message = "Instructor account updated.";
                    } else {
                        $message = "A Department Secretary already exists in the selected department.";
                        $secretary_check_stmt->close();
                        $_SESSION['message'] = $message;
                    }
                }
            
            } elseif ($user_type === "CCL Head") {
                // SQL to fetch the current Department Secretary for the given department, semester, and academic year
                $secretary_check_sql = "SELECT id FROM tbl_prof_acc 
                                        WHERE college_code = ?
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND user_type = 'CCL Head'";
                $secretary_check_stmt = $conn->prepare($secretary_check_sql);
                $secretary_check_stmt->bind_param("sss", $college_code, $semester, $ay_code);
                $secretary_check_stmt->execute();
                $secretary_check_result = $secretary_check_stmt->get_result();
            
                // Check if there is an existing Department Secretary
                if ($secretary_check_result->num_rows > 0) {
                    $existing_secretary = $secretary_check_result->fetch_assoc();
                    $existing_secretary_id = $existing_secretary['id'];
            
                    // Allow update if the current professor ID matches the existing Department Secretary ID
                    if ($existing_secretary_id == $prof_id) {
                        // Update professor account
                        $update_query = "UPDATE tbl_prof_acc 
                                        SET dept_code = ?, prof_unit = ?, last_name = ?, first_name = ?, 
                                            middle_initial = ?, suffix = ?, cvsu_email = ?, user_type = ?, 
                                            acc_status = ?, reg_adviser = ? 
                                        WHERE id = ?
                                        AND semester = ?
                                        AND ay_code = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param(
                        "sssssssssssss",
                        $department_code,
                        $prof_unit,
                        $last_name,
                        $first_name,
                        $mi,
                        $suffix,
                        $email,
                        $user_type,
                        $acc_status,
                        $reg_adviser,
                        $prof_id,
                        $semester,
                        $ay_code
                        );
                        $update_success = $stmt->execute();
                        $stmt->close();
    
                        $message = "Instructor account updated.";
                    } else {
                        $message = "A CCL Head already exists in this college.";
                        $secretary_check_stmt->close();
                        $_SESSION['message'] = $message;
                    }
                }
            } elseif ($user_type === "Department Chairperson") {
                // SQL to fetch the current Department Secretary for the given department, semester, and academic year
                $secretary_check_sql = "SELECT id FROM tbl_prof_acc 
                                        WHERE college_code = ?
                                          AND dept_code = ?
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND user_type = 'Department Chairperson'";
                $secretary_check_stmt = $conn->prepare($secretary_check_sql);
                $secretary_check_stmt->bind_param("ssss", $college_code, $department_code, $semester, $ay_code);
                $secretary_check_stmt->execute();
                $secretary_check_result = $secretary_check_stmt->get_result();
            
                // Check if there is an existing Department Secretary
                if ($secretary_check_result->num_rows > 0) {
                    $existing_secretary = $secretary_check_result->fetch_assoc();
                    $existing_secretary_id = $existing_secretary['id'];
            
                    // Allow update if the current professor ID matches the existing Department Secretary ID
                    if ($existing_secretary_id == $prof_id) {
                        // Update professor account
                        $update_query = "UPDATE tbl_prof_acc 
                                        SET dept_code = ?, prof_unit = ?, last_name = ?, first_name = ?, 
                                            middle_initial = ?, suffix = ?, cvsu_email = ?, user_type = ?, 
                                            acc_status = ?, reg_adviser = ? 
                                        WHERE id = ?
                                        AND semester = ?
                                        AND ay_code = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param(
                        "sssssssssssss",
                        $department_code,
                        $prof_unit,
                        $last_name,
                        $first_name,
                        $mi,
                        $suffix,
                        $email,
                        $user_type,
                        $acc_status,
                        $reg_adviser,
                        $prof_id,
                        $semester,
                        $ay_code
                        );
                        $update_success = $stmt->execute();
                        $stmt->close();
    
                        $message = "Instructor account updated.";
                    } else {
                        $message = "A Department Chairperson already exists in this college.";
                        $secretary_check_stmt->close();
                        $_SESSION['message'] = $message;
                    }
                }
            } else {
                // Fetch the current prof_unit of the professor
                $current_unit_query = "SELECT id, prof_unit FROM tbl_prof_acc WHERE default_code = ? AND semester = ? AND ay_code = ?";
                $stmt = $conn->prepare($current_unit_query);
                $stmt->bind_param("sss", $default_code, $semester, $ay_code);
                $stmt->execute();
                $result = $stmt->get_result();
                $professor_data = $result->fetch_assoc(); // Fetch data only once
                $current_prof_unit = $professor_data['prof_unit'] ?? null; // Retrieve prof_unit
                $stmt->close();
    
                // Update professor account
                $update_query = "UPDATE tbl_prof_acc 
                                SET dept_code = ?, prof_unit = ?, last_name = ?, first_name = ?, 
                                    middle_initial = ?, suffix = ?, cvsu_email = ?, user_type = ?, 
                                    acc_status = ?, reg_adviser = ? 
                                WHERE id = ?
                                AND semester = ?
                                AND ay_code = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param(
                    "sssssssssssss",
                    $department_code,
                    $prof_unit,
                    $last_name,
                    $first_name,
                    $mi,
                    $suffix,
                    $email,
                    $user_type,
                    $acc_status,
                    $reg_adviser,
                    $prof_id,
                    $semester,
                    $ay_code
                );
                $update_success = $stmt->execute();
                $stmt->close();
    
                if ($update_success) {
                    if ($current_prof_unit !== $prof_unit) {
                        // Step 1: Move the professor to the new prof_unit
                        updateProfessorUnit($conn, $default_code, $prof_unit, $department_code, $semester, $ay_code);
            
                        // Step 2: Reorder prof_codes in the old prof_unit after the professor is removed
                        reorderProfCodes($conn, $default_code, $current_prof_unit, $department_code, $semester, $ay_code);
            
                        // Step 3: Reorder prof_codes in the new prof_unit to ensure proper numbering
                        reorderProfCodes($conn, $prof_unit, $department_code, $semester, $ay_code);
                    }
                    
                    // // If the admin change details to the prof that have a prof_code
                    // // NO USE HERE BUT DON'T REMOVE
                    // // Concatenate full_name
                    // $full_name = trim("$last_name, $first_name $mi $suffix");
    
                    // // Query to check employ_status
                    // $check_status_query = "SELECT employ_status FROM tbl_prof WHERE prof_code = ?";
                    // $stmt1 = $conn->prepare($check_status_query);
                    // $stmt1->bind_param("s", $prof_code);
                    // $stmt1->execute();
                    // $stmt1->bind_result($employ_status);
                    // $stmt1->fetch();
                    // $stmt1->close();
    
                    // if ($employ_status === 1) { 
                    //     // Extract the existing components of the `prof_code`
                    //     list($prof_unit, $pt, $number_dash_lastname) = explode(' ', $prof_code, 3);
                    //     list($number, $old_last_name) = explode(' - ', $number_dash_lastname, 2);
    
                    //     // Preserve the first three parts of the `prof_code`
                    //     $new_prof_code = "$prof_unit $pt $number - $last_name";
    
                    //     // Update the `prof_code` in `tbl_prof_acc`
                    //     $update_query_acc = "UPDATE tbl_prof_acc SET prof_code = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt_update_acc = $conn->prepare($update_query_acc);
                    //     $stmt_update_acc->bind_param("ssss", $new_prof_code, $prof_code, $semester, $ay_code);
                    //     $stmt_update_acc->execute();
    
                    //     // Update the `prof_code` in `tbl_prof`
                    //     $update_query_prof = "UPDATE tbl_prof SET prof_name = ?, prof_code = ?, prof_unit = ?, acc_status = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt_update_prof = $conn->prepare($update_query_prof);
                    //     $stmt_update_prof->bind_param("sssssss", $full_name, $new_prof_code, $prof_unit, $acc_status, $prof_code, $semester, $ay_code);
                    //     $stmt_update_prof->execute();
    
                    //     // Ensure variables are set correctly before starting the updates
                    //     $old_prof_sched_code = $prof_code . '_' . $ay_code; // Fix variable usage
    
                    //     $update_queries = [
                    //         // tbl_assigned_course
                    //         "UPDATE tbl_assigned_course SET dept_code = ?, prof_code = ?, prof_name = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $full_name, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_counter
                    //         "UPDATE tbl_pcontact_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_schedstatus
                    //         "UPDATE tbl_pcontact_schedstatus SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_prof_schedstatus
                    //         "UPDATE tbl_prof_schedstatus SET dept_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $ay_code, $old_prof_sched_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched
                    //         "UPDATE tbl_psched SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched_counter
                    //         "UPDATE tbl_psched_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_stud_acc
                    //         "UPDATE tbl_stud_acc SET dept_code = ?, reg_adviser = ? WHERE reg_adviser = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code]
                    //         ],
                    //         // tbl_stud_prof_notif
                    //         "UPDATE tbl_stud_prof_notif SET dept_code = ?, receiver_type = ? WHERE receiver_type = ? AND semester = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code, $semester]
                    //         ]
                    //     ];
    
                    //     foreach ($update_queries as $query => $params_list) {
                    //         $stmt = $conn->prepare($query);
    
                    //         if ($stmt) {
                    //             foreach ($params_list as $params) {
                    //                 // Dynamically bind parameters
                    //                 $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    
                    //                 if (!$stmt->execute()) {
                    //                     $message = "Error updating record in query: $query\nError: " . $stmt->error . "\n";
                    //                     break 2; // Exit both loops
                    //                 }
                    //             }
                    //             $stmt->close();
                    //         } else {
                    //             $message = "Error preparing statement for query: $query\nError: " . $conn->error . "\n";
                    //             break;
                    //         }
                    //     }
    
                    // } elseif ($employ_status === 2) { 
                    //     // Generate the professor code
                    //     $first_initial = strtoupper(substr($first_name, 0, 1));
                    //     $middle_initial = strtoupper(substr($mi, 0, 1));
                    //     $last_part = ucfirst($last_name);
                    //     $new_prof_code = $first_initial . $middle_initial . $last_part;
    
                    //     if (!empty($suffix)) {
                    //         $new_prof_code .= " " . $suffix;
                    //     }
                        
                    //     // Update the `prof_code` in `tbl_prof`
                    //     $update_query_prof = "UPDATE tbl_prof SET prof_name = ?, prof_code = ?, prof_unit = ?, acc_status = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt_update_prof = $conn->prepare($update_query_prof);
                    //     $stmt_update_prof->bind_param("sssssss", $full_name, $new_prof_code, $prof_unit, $acc_status, $prof_code, $semester, $ay_code);
                    //     $stmt_update_prof->execute();
                        
                    //     // Update prof_code in tbl_prof_acc
                    //     $update_prof_acc_query = "UPDATE tbl_prof_acc SET prof_code = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt3 = $conn->prepare($update_prof_acc_query);
                    //     $stmt3->bind_param("ssss", $new_prof_code, $prof_code, $semester, $ay_code);
                    //     $stmt3->execute();
                    //     $stmt3->close();
    
                    //     // Ensure variables are set correctly before starting the updates
                    //     $old_prof_sched_code = $prof_code . '_' . $ay_code; // Fix variable usage
    
                    //     $update_queries = [
                    //         // tbl_assigned_course
                    //         "UPDATE tbl_assigned_course SET dept_code = ?, prof_code = ?, prof_name = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $full_name, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_counter
                    //         "UPDATE tbl_pcontact_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_schedstatus
                    //         "UPDATE tbl_pcontact_schedstatus SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_prof_schedstatus
                    //         "UPDATE tbl_prof_schedstatus SET dept_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $ay_code, $old_prof_sched_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched
                    //         "UPDATE tbl_psched SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched_counter
                    //         "UPDATE tbl_psched_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_stud_acc
                    //         "UPDATE tbl_stud_acc SET dept_code = ?, reg_adviser = ? WHERE reg_adviser = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code]
                    //         ],
                    //         // tbl_stud_prof_notif
                    //         "UPDATE tbl_stud_prof_notif SET dept_code = ?, receiver_type = ? WHERE receiver_type = ? AND semester = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code, $semester]
                    //         ]
                    //     ];
    
                    //     foreach ($update_queries as $query => $params_list) {
                    //         $stmt = $conn->prepare($query);
    
                    //         if ($stmt) {
                    //             foreach ($params_list as $params) {
                    //                 // Dynamically bind parameters
                    //                 $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    
                    //                 if (!$stmt->execute()) {
                    //                     $message = "Error updating record in query: $query\nError: " . $stmt->error . "\n";
                    //                     break 2; // Exit both loops
                    //                 }
                    //             }
                    //             $stmt->close();
                    //         } else {
                    //             $message = "Error preparing statement for query: $query\nError: " . $conn->error . "\n";
                    //             break;
                    //         }
                    //     }
    
                    // } elseif ($employ_status === 3) { 
                    //     // Extract the first and second parts of the current `prof_code`
                    //     list($prof_unit, $number_dash_lastname) = explode(' ', $prof_code, 2);
                    //     list($number, $old_last_name) = explode(' - ', $number_dash_lastname, 2);
    
                    //     // Preserve the existing count and number
                    //     $new_prof_code = "$prof_unit $number - $last_name";
    
                    //     // Update the `prof_code` in `tbl_prof_acc`
                    //     $update_query_acc = "UPDATE tbl_prof_acc SET prof_code = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt_update_acc = $conn->prepare($update_query_acc);
                    //     $stmt_update_acc->bind_param("ssss", $new_prof_code, $prof_code, $semester, $ay_code);
                    //     $stmt_update_acc->execute();
    
                    //     // Update the `prof_code` in `tbl_prof`
                    //     $update_query_prof = "UPDATE tbl_prof SET prof_name = ?, prof_code = ?, prof_unit = ?, acc_status = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    //     $stmt_update_prof = $conn->prepare($update_query_prof);
                    //     $stmt_update_prof->bind_param("sssssss", $full_name, $new_prof_code, $prof_unit, $acc_status, $prof_code, $semester, $ay_code);
                    //     $stmt_update_prof->execute();
    
                    //     // Ensure variables are set correctly before starting the updates
                    //     $old_prof_sched_code = $prof_code . '_' . $ay_code; // Fix variable usage
    
                    //     $update_queries = [
                    //         // tbl_assigned_course
                    //         "UPDATE tbl_assigned_course SET dept_code = ?, prof_code = ?, prof_name = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $full_name, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_counter
                    //         "UPDATE tbl_pcontact_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_pcontact_schedstatus
                    //         "UPDATE tbl_pcontact_schedstatus SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_prof_schedstatus
                    //         "UPDATE tbl_prof_schedstatus SET dept_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $ay_code, $old_prof_sched_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched
                    //         "UPDATE tbl_psched SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_psched_counter
                    //         "UPDATE tbl_psched_counter SET dept_code = ?, prof_code = ?, prof_sched_code = CONCAT(?, '_', ?) WHERE prof_code = ? AND semester = ? AND ay_code = ?" => [
                    //             [$department_code, $new_prof_code, $new_prof_code, $ay_code, $prof_code, $semester, $ay_code]
                    //         ],
                    //         // tbl_stud_acc
                    //         "UPDATE tbl_stud_acc SET dept_code = ?, reg_adviser = ? WHERE reg_adviser = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code]
                    //         ],
                    //         // tbl_stud_prof_notif
                    //         "UPDATE tbl_stud_prof_notif SET dept_code = ?, receiver_type = ? WHERE receiver_type = ? AND semester = ?" => [
                    //             [$department_code, $new_prof_code, $prof_code, $semester]
                    //         ]
                    //     ];
    
                    //     foreach ($update_queries as $query => $params_list) {
                    //         $stmt = $conn->prepare($query);
    
                    //         if ($stmt) {
                    //             foreach ($params_list as $params) {
                    //                 // Dynamically bind parameters
                    //                 $stmt->bind_param(str_repeat("s", count($params)), ...$params);
    
                    //                 if (!$stmt->execute()) {
                    //                     $message = "Error updating record in query: $query\nError: " . $stmt->error . "\n";
                    //                     break 2; // Exit both loops
                    //                 }
                    //             }
                    //             $stmt->close();
                    //         } else {
                    //             $message = "Error preparing statement for query: $query\nError: " . $conn->error . "\n";
                    //             break;
                    //         }
                    //     }
    
                    // } else {
                    //     // Assuming $prof_code, $last_name, $first_name, $middle_initial, and $suffix are set
                    //     $update_query_acc = "
                    //                         UPDATE tbl_prof_acc 
                    //                         SET 
                    //                             last_name = ?, 
                    //                             first_name = ?, 
                    //                             middle_initial = ?, 
                    //                             suffix = ? 
                    //                         WHERE id = ? AND semester = ? AND ay_code = ?
                    //                         ";
    
                    //     // Prepare the SQL statement
                    //     $stmt_update_acc = $conn->prepare($update_query_acc);
    
                    //     // Bind the parameters (Make sure you sanitize or validate these variables before use)
                    //     $stmt_update_acc->bind_param("sssssss", $last_name, $first_name, $mi, $suffix, $prof_id, $semester, $ay_code);
    
                    //     // Execute the statement
                    //     $stmt_update_acc->execute();
                    // }
            
                    $message = "Instructor account updated.";
                } else {
                    $message = "Error updating Instructor account: " . $conn->error;
                }
            }
        } else {
            $message = "Unable to update. Scheduling has already started for the selected Instructor.";
        }
    }

    
}

if (isset($_POST['delete'])) {
    $prof_id = mysqli_real_escape_string($conn, $_POST['prof_id']);
    $prof_code = mysqli_real_escape_string($conn, $_POST['prof_code']);
    $default_code = mysqli_real_escape_string($conn, $_POST['default_code']);
    $prof_unit = mysqli_real_escape_string($conn, $_POST['prof_unit']);
    $acc_status = mysqli_real_escape_string($conn, $_POST['acc_status']);

    // Check if there is data in tbl_psched for the given semester and ay_code
    $check_query = "SELECT COUNT(*) AS count FROM tbl_psched WHERE prof_code = ? AND semester = ? AND ay_code = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("sss", $prof_code, $semester, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] > 0) {
        $message = "Unable to delete. Scheduling has already started for the selected Instructor.";
    } else {
        // Fetch the current prof_unit and department of the professor
        $current_unit_query = "SELECT p.prof_unit, p.dept_code FROM tbl_prof p WHERE p.prof_code = ? AND p.semester = ? AND p.ay_code = ?";
        $stmt = $conn->prepare($current_unit_query);
        $stmt->bind_param("sss", $default_code, $semester, $ay_code);
        $stmt->execute();
        $result = $stmt->get_result();
        $professor = $result->fetch_assoc();
        $stmt->close();

        if ($professor) {
            $current_prof_unit = $professor['prof_unit'];
            $department_code = $professor['dept_code'];

            // Step 1: Delete the professor record from tbl_prof
            $delete_prof_query = "DELETE FROM tbl_prof WHERE prof_code = ? AND semester = ? AND ay_code = ?";
            $stmt = $conn->prepare($delete_prof_query);
            $stmt->bind_param("sss", $default_code, $semester, $ay_code);
            $delete_prof_success = $stmt->execute();
            $stmt->close();

            // Step 2: Delete the professor record from tbl_prof_acc
            $delete_prof_acc_query = "DELETE FROM tbl_prof_acc WHERE default_code = ? AND semester = ? AND ay_code = ?";
            $stmt = $conn->prepare($delete_prof_acc_query);
            $stmt->bind_param("sss", $default_code, $semester, $ay_code);
            $delete_prof_acc_success = $stmt->execute();
            $stmt->close();

            if ($delete_prof_success && $delete_prof_acc_success) {
                // Step 3: Reorder prof_codes in the unit where the professor was deleted
                reorderProfCodes($conn, $current_prof_unit, $department_code, $semester, $ay_code);

                $message = "Instructor account deleted successfully.";
            } else {
                $message = "Error deleting Instructor record: " . $conn->error;
            }
        } else {
            $message = "Instructor not found.";
        }
    }
}


function reorderProfCodes($conn, $prof_unit, $dept_code, $semester, $ay_code) {
    // Fetch all records for the given prof_unit and department ordered by the existing prof_code
    $query = "SELECT id, prof_code FROM tbl_prof 
              WHERE prof_unit = ? AND dept_code = ? AND semester = ? AND ay_code = ? 
              ORDER BY CAST(SUBSTRING_INDEX(prof_code, ' ', -1) AS UNSIGNED)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $prof_unit, $dept_code, $semester, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $count = 1; // Start counting at 1
    while ($row = $result->fetch_assoc()) {
        $new_prof_code = strtoupper($prof_unit) . " " . $count++;

        // Update prof_code in tbl_prof
        $update_prof_query = "UPDATE tbl_prof SET prof_code = ? WHERE id = ? AND semester = ? AND ay_code = ?";
        $update_stmt = $conn->prepare($update_prof_query);
        $update_stmt->bind_param("siss", $new_prof_code, $row['id'], $semester, $ay_code);
        $update_stmt->execute();
        $update_stmt->close();

        // Also update the default_code in tbl_prof_acc to maintain consistency
        $update_acc_query = "UPDATE tbl_prof_acc SET default_code = ? WHERE id = ? AND semester = ? AND ay_code = ?";
        $update_acc_stmt = $conn->prepare($update_acc_query);
        $update_acc_stmt->bind_param("siss", $new_prof_code, $row['id'], $semester, $ay_code);
        $update_acc_stmt->execute();
        $update_acc_stmt->close();
    }
    $stmt->close();
}


function updateProfessorUnit($conn, $prof_code, $prof_unit, $dept_code, $semester, $ay_code) {
    // Fetch the current prof_unit for the professor
    $query = "SELECT prof_unit FROM tbl_prof WHERE prof_code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $prof_code);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_unit = $result->fetch_assoc()['prof_unit'];
    $stmt->close();

    // Update the professor's prof_unit and prof_code in tbl_prof
    $update_prof_query = "UPDATE tbl_prof 
                          SET prof_unit = ? 
                          WHERE prof_code = ?";
    $stmt = $conn->prepare($update_prof_query);
    $stmt->bind_param("ss", $prof_unit, $prof_code);
    $stmt->execute();
    $stmt->close();

    // Update the professor's prof_unit in tbl_prof_acc
    $update_prof_acc_query = "UPDATE tbl_prof_acc 
                              SET prof_unit = ? 
                              WHERE prof_code = ? AND semester = ? AND ay_code = ?";
    $stmt = $conn->prepare($update_prof_acc_query);
    $stmt->bind_param("ssss", $prof_unit, $prof_code, $semester, $ay_code);
    $stmt->execute();
    $stmt->close();

    // Reorder the prof_codes for the new prof_unit
    reorderProfCodes($conn, $prof_unit, $dept_code, $semester, $ay_code);

    // Reorder the prof_codes for the previous prof_unit
    if ($current_unit !== $prof_unit) {
        reorderProfCodes($conn, $current_unit, $dept_code, $semester, $ay_code);
    }
}


// Fetch the department code
$departmentCodes = [];
$query = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
$resultDept = $conn->query($query);
if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $departmentCodes[] = $row['dept_code'];
    }
}

// Fetch the program units
$programUnit = [];
$query = "SELECT dept_code, program_units FROM tbl_department WHERE college_code = '$college_code'";
$resultDept = $conn->query($query);

if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $units = explode(',', $row['program_units']);
        $units = array_map('trim', $units);
        if (!empty($units[0])) {
            $programUnit[$row['dept_code']] = $units;
        }
    }
}

// Modal Message
if (!empty($message)) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showModal('$message');
            });
          </script>";
}

// Logout
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
    <title>SchedSys</title>
    <!-- <link rel="stylesheet" href="/SchedSys/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <script src="/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="create_account.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Section -->
        <aside>
            <div class="toggle">
                <div class="logo">
                    <h2 class="logo-name">SchedSys</span></h2>
                </div>
            </div>
            <div class="sidebar">
                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    <h3>Home</h3>
                </a>
                <a href="/SchedSys3/php/viewschedules/dashboard.php">
                    <i class="fa-regular fa-calendar-days"></i>
                    <h3>Schedule</h3>
                </a>
                <a href="user_list.php">
                    <i class="fa-solid fa-user"></i>
                    <h3>Users</h3>
                </a>
                <a href="/SchedSys3/php/messages/users.php">
                    <i class="fa-solid fa-message"></i>
                    <h3>Message</h3>
                </a>
                <a href="approve.php">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Approval</h3>
                    <span class="message-count"><?php echo $pending_count; ?></span>
                </a>
                <a href="settings.php">
                    <i class="fa-solid fa-gear"></i>
                    <h3>Settings</h3>
                </a>
                <a onclick="openModal(event,'logoutModal')">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p><br>
                <div class="modal-buttons">
                    <button class="btn-logout" onclick="confirmLogout()">Logout</button>
                    <a href="create_prof.php"><button onclick="closeModal()">Cancel</button></a>
                </div>
            </div>
        </div>

        <div class="nav">
            <div class="profile">
                <div class="info">
                    <p><b>Admin</b></p>
                    <small class="text-muted"><?php echo htmlspecialchars($college_code); ?></small>
                </div>
                <div class="profile-photo">
                    <img src="../../images/user_profile.png">
                </div>
            </div>
        </div>

        <div class="main">
            <div class="content">
                <div class="user_account">
                    <h5 class="title" style="text-align: center">Create Instructor Account</h5><br>
                    <form id="professorForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="form-group">
                            <input type="text" class="form-control" id="prof_id" name="prof_id" style="display: none;">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" id="default_code" name="default_code" style="">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" id="prof_code" name="prof_code" style="display: none;">
                        </div>

                        <select class="form-control" id="department_code" name="department_code" onchange="loadProgramUnits(this)" required>
                            <option value="" disabled selected>Select Department</option>
                            <?php foreach ($departmentCodes as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <div class="form-group">
                            <select class="form-control" id="prof_unit" name="prof_unit" required>
                                <option value="" disabled selected>Select Program Unit</option>
                            </select>
                        </div><br>
                        <div class="form-group">
                            <input type="text" class="form-control" id="last-name" name="last-name"
                                placeholder="Last Name" oninput="validateLetters(this)" required>
                        </div><br>
                        <div class="form-group">
                            <input type="text" class="form-control" id="first-name" name="first-name"
                                placeholder="First Name" oninput="validateLetters(this)" required>
                        </div><br>

                        <div class="form-group-row">
                            <div class="form-group">
                                <input type="text" class="form-control" id="mi" name="mi" placeholder="Middle Initial" maxlength="1" oninput="validateMiddleInitial(this);this.value = this.value.toUpperCase();" pattern="[A-Za-z]{1}" required>
                            </div>
                            <div class="form-group">
                                <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Suffix" oninput="validateMiddleSuffix(this)" pattern="[A-Za-z]+">
                            </div>
                        </div><br>

                        <div class="form-group" style="position: relative;">
                            <input type="text" class="form-control" id="email" name="email"
                                placeholder="example" oninput="appendDomain()" style="padding-right: 110px;">
                            <div class="email-domain">@cvsu.edu.ph</div>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control" id="user-type" name="user-type" onchange="changeSelectColor(this)" required>
                                <option value="" disabled selected>Select User Type</option>
                                <option value="Professor">Instructor</option>
                                <option value="CCL Head">CCL Head</option>
                                <option value="Department Secretary">Department Secretary</option>
                                <option value="Department Chairperson">Department Chairperson</option>
                            </select>
                        </div><br>

                        <div class="form-group-row">
                            <div class="form-group">
                                <select class="form-control" id="acc_status" name="acc_status" onchange="changeSelectColor(this)" style="width: 100px;" required>
                                    <option value="" disabled selected>Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="reg_adviser" name="reg_adviser" onchange="changeSelectColor(this)" required>
                                    <option value="" disabled selected>Registration Adviser</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        
                        <br><br>

                        <!-- Button -->
                        <div class="btn">
                            <button type="submit" name="save" value="add"  id="addButton" class="btn-add btn-success" onclick="openModal(event, 'addModal')">Add</button>
                            <button type="submit" name="update" id="updateButton" value="update" class="btn-update btn-primary" onclick="openModal(event, 'updateModal')">Update</button>
                            <button type="submit" name="delete" id="deleteButton" value="delete" class="btn-delete btn-danger" onclick="openModal(event, 'deleteModal')">Delete</button>
                        </div>

                        <!-- Add Modal -->
                        <div id="addModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Add</h2>
                                <p>Are you sure you want to add this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="save" class="btn-add" onclick="finalizeAction('addModal')">Yes, Add</button>
                                    <a href="create_prof.php"><button class="closed" onclick="closeModal('addModal')">Cancel</button></a>
                                </div>
                            </div>
                        </div>

                        <!-- Update Modal -->
                        <div id="updateModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Update</h2>
                                <p>Are you sure you want to update this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="update" class="btn-update" onclick="finalizeAction('updateModal')">Yes, Update</button>
                                    <a href="create_prof.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div id="deleteModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Delete</h2>
                                <p>Are you sure you want to delete this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="delete" class="btn-delete" onclick="finalizeAction('deleteModal')">Yes, Delete</button>
                                    <a href="create_other_college.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>


                <div class="content-table">
                    
                    <div class="filtering-container">
                        <div class="form-group">
                            <select class="filtering" id="department" name="department">
                                <option value="" disabled selected>Search Department</option>
                                <option value="all">All</option>
                                <?php foreach ($departmentCodes as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <select class="filtering" id="user_type" name="type">
                                <option value="" disabled selected>Search User Type</option>
                                <option value="All">All</option>
                                <?php

                                $query = "SELECT DISTINCT user_type FROM tbl_prof_acc";
                                $result = $conn->query($query);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<option value="' . htmlspecialchars($row['user_type']) . '">' . htmlspecialchars($row['user_type']) . '</option>';
                                    }
                                } else {
                                    echo '<option value="" disabled>No User Types Available</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <input type="text" class="filtering" id="search_user" name="search_user" placeholder="Search Instructor" autocomplete="off">
                            <button type="submit" class="btn-add btn-search">Search</button>
                        </div>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style='width: 150px'>Department</th>
                                <th style='width: 100px;'>Unit</th>
                                <th style="width: 200px;">Name</th>
                                <th style="width: 200px">Email</th>
                                <th style='width: 150px'>User Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $query = "SELECT * FROM tbl_prof_acc 
                                      WHERE college_code = ? 
                                      AND semester = ? 
                                      AND ay_code = ? 
                                      ORDER BY id DESC";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("sss", $college_code, $semester, $ay_code);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            // Debugging: Check the query
                            if ($result === FALSE) {
                                echo "Error fetching data: " . $conn->error;
                                exit;
                            }

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    // Skip rows where status is 'Pending'
                                    if ($row["status"] === "pending") {
                                        continue;
                                    }

                                    // Determine account status based on "acc_status"
                                    $acc_status = ($row["acc_status"] === "1") ? 'Active' : 'Inactive';
                                    $reg_adviser = ($row["reg_adviser"] === "1") ? 'Yes' : 'No';

                                    $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];

                                    echo "<tr class='account-row'
                                            data-first-name='" . htmlspecialchars($row["first_name"]) . "'
                                            data-middle-initial='" . htmlspecialchars($row["middle_initial"]) . "'
                                            data-suffix='" . htmlspecialchars($row["suffix"]) . "'
                                            data-last-name='" . htmlspecialchars($row["last_name"]) . "'>
                                            <td style='display: none;'>" . htmlspecialchars($row["id"]) . "</td>
                                            <td style='display: none;'>" . htmlspecialchars($row["default_code"]) . "</td>
                                            <td style='display: none;'>" . htmlspecialchars($row["prof_code"]) . "</td>
                                            <td style='width: 150px'>" . htmlspecialchars($row["dept_code"]) . "</td>
                                            <td style='width: 100px;'>" . htmlspecialchars($row["prof_unit"]) . "</td>
                                            <td style='width: 200px;'>" . htmlspecialchars($full_name) . "</td>
                                            <td class='email-cell'>" . htmlspecialchars($row["cvsu_email"]) . "</td>
                                            <td style='width: 150px'>" . htmlspecialchars($row["user_type"] === "Professor" ? "Instructor" : $row["user_type"]) . "</td>

                                            <td>" . htmlspecialchars($acc_status) . "</td>
                                            <td style='display: none;'>" . htmlspecialchars($reg_adviser) . "</td>  
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9'>No records found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content">
            <p id="modalMessage">Your message here</p>
            <button class="close-btn" onclick="closeModal()" style="color: white; background-color: #FD7238;">Okay</button>
        </div>
    </div>

    <script>
        function appendDomain() {
            const emailInput = document.getElementById("email");
            const value = emailInput.value;

            // Prevent user from typing the domain manually
            if (value.includes("@cvsu.edu.ph")) {
                emailInput.value = value.split("@")[0];
            }
        }
        
        function validateLetters(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');
        }

        function validateMiddleInitial(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '').substring(0, 1);
        }

        function validateMiddleSuffix(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');
        }

        function changeSelectColor(selectElement) {
            if (selectElement.value) {
                selectElement.style.color = "black";
            } else {
                selectElement.style.color = "gray";
            }
        }

        // Open the modal
        function openModal(event, modalId) {
            event.preventDefault(); // Prevent the default form submission
            document.getElementById(modalId).style.display = "flex";
        }

        // Close the modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }

        // Finalize the action (form submission)
        function finalizeAction(modalId) {
            document.getElementById(modalId).style.display = "none";
            // The form will be submitted because the button has type="submit".
            console.log("Form submitted for modal: " + modalId);
        }

        // Close the modal if clicked outside the modal content
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach((modal) => {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            });
        });

        // Close the modal when clicking the close button
        document.querySelectorAll('.closed').forEach((closeButton) => {
            closeButton.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = "none";
                }
            });
        });
        
        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "index.php?logout=1";
        }

        // JavaScript to manage row selection and form population
        const table = document.querySelector('.table tbody');
        const addButton = document.getElementById('addButton');
        const updateButton = document.getElementById('updateButton');
        const deleteButton = document.getElementById('deleteButton');
        const formContainer = document.getElementById('professorForm');
        let selectedRow = null; // Track the currently active row

        // Disable update and delete buttons initially
        updateButton.disabled = true;
        deleteButton.disabled = true;

        // Function to clear the form
        function clearForm() {
            formContainer.reset();
            updateButton.disabled = true;
            deleteButton.disabled = true;
            addButton.disabled = false; // Enable Add button when the form is cleared

            // Remove active row styling
            if (selectedRow) {
                selectedRow.classList.remove('active-row');
            }
            selectedRow = null;
        }

        // Event listener: Populate input fields with row data when clicked
        table.addEventListener('click', (event) => {
            const row = event.target.closest('tr');
            if (!row) return;

            document.getElementById('prof_id').value = row.cells[0].innerText;
            document.getElementById('default_code').value = row.cells[1].innerText;
            document.getElementById('prof_code').value = row.cells[2].innerText;

            // Populate department_code
            const departmentCode = row.cells[3].innerText;
            const programUnit = row.cells[4].innerText;

            // Set department_code value
            const departmentCodeSelect = document.getElementById('department_code');
            departmentCodeSelect.value = departmentCode;

            // Populate prof_unit dropdown immediately
            const programUnits = <?= json_encode($programUnit); ?>; // Pass PHP data to JavaScript
            const profUnitSelect = document.getElementById('prof_unit');

            // Clear current options in prof_unit
            profUnitSelect.innerHTML = '<option value="" disabled selected>Select Program Unit</option>';

            // Populate options for the selected department
            if (programUnits[departmentCode]) {
                programUnits[departmentCode].forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = unit;
                    profUnitSelect.appendChild(option);
                });
            }

            // Set prof_unit value
            profUnitSelect.value = programUnit;

            // Populate other fields
            document.getElementById('last-name').value = row.dataset.lastName;
            document.getElementById('first-name').value = row.dataset.firstName;
            document.getElementById('mi').value = row.dataset.middleInitial;
            document.getElementById('suffix').value = row.dataset.suffix;

            const emailValue = row.cells[6].innerText;
            document.getElementById('email').value = emailValue.replace('@cvsu.edu.ph', '');

            let userTypeElement = document.getElementById('user-type');
            let userType = row.cells[7].innerText.trim(); // Get the text and remove extra spaces

            if (userType === "Instructor") {
                userTypeElement.value = "Professor";
            } else {
                userTypeElement.value = userType;
            }
            const accStatusValue = row.cells[8].innerText;
            const accStatusSelect = document.getElementById('acc_status');
            accStatusSelect.value = (accStatusValue === "Inactive" || accStatusValue === "0") ? "0" : "1";

            const regAdviserValue = row.cells[9].innerText;
            const regAdviserSelect = document.getElementById('reg_adviser');
            regAdviserSelect.value = (regAdviserValue === "No" || regAdviserValue === "0") ? "0" : "1";

            // Disable Add button when a row is selected
            addButton.disabled = true;

            // Enable update and delete buttons
            updateButton.disabled = false;
            deleteButton.disabled = false;

            // Highlight the selected row
            if (selectedRow) {
                selectedRow.classList.remove('active-row');
            }
            selectedRow = row;
            row.classList.add('active-row');
        });

        // Event listener to clear form if clicking outside the form container
        document.addEventListener('click', (event) => {
            if (!formContainer.contains(event.target) && !table.contains(event.target)) {
                clearForm();
            }
        });

        // Function to show the modal with a custom message
        function showModal(message) {
            document.getElementById("modalMessage").innerText = message;
            document.getElementById("messageModal").style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById("messageModal").style.display = "none";
        }

        // filtering
        document.addEventListener("DOMContentLoaded", function () {
            const departmentFilter = document.getElementById("department");
            const userTypeFilter = document.getElementById("user_type");
            const searchUserInput = document.getElementById("search_user");
            const tableBody = document.querySelector("tbody");
            const tableRows = tableBody.querySelectorAll("tr");

            // Create a "No results found" row
            const noResultsRow = document.createElement("tr");
            noResultsRow.classList.add("no-results-row");
            noResultsRow.innerHTML = `<td colspan="6">No results found.</td>`;
            noResultsRow.style.display = "none";
            tableBody.appendChild(noResultsRow);

            function filterTable() {
                const selectedDept = departmentFilter.value.toLowerCase();
                const selectedUserType = userTypeFilter.value.toLowerCase();
                const searchQuery = searchUserInput.value.toLowerCase();

                let visibleRowCount = 0;

                tableRows.forEach(row => {
                    if (row === noResultsRow) return;

                    // Gather all column text content for universal search
                    const rowText = Array.from(row.querySelectorAll("td")).map(td => td.textContent.toLowerCase()).join(" ");

                    const matchesDept = selectedDept === "all" || rowText.includes(selectedDept) || selectedDept === "";
                    const matchesProfType = selectedUserType === "all" || rowText.includes(selectedUserType) || selectedUserType === "";
                    const matchesSearch = searchQuery === "" || rowText.includes(searchQuery);

                    if (matchesDept && matchesProfType && matchesSearch) {
                        row.style.display = "";
                        visibleRowCount++;
                    } else {
                        row.style.display = "none";
                    }
                });

                noResultsRow.style.display = visibleRowCount === 0 ? "" : "none";
            }

            departmentFilter.addEventListener("change", filterTable);
            userTypeFilter.addEventListener("change", filterTable);
            searchUserInput.addEventListener("input", filterTable);
        });

        const programUnits = <?= json_encode($programUnit); ?>; // Passing PHP array to JavaScript

        function loadProgramUnits(departmentSelect) {
            const selectedDept = departmentSelect.value; // Get selected department
            const programUnitSelect = document.getElementById('prof_unit');

            

            // Clear existing options
            programUnitSelect.innerHTML = '<option value="" disabled selected>Select Program Unit</option>';

            // Check if the selected department has program units
            if (programUnits[selectedDept]) {
                programUnits[selectedDept].forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = unit;
                    programUnitSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>