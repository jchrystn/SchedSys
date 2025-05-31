<?php
include("../config.php");
session_start();http://localhost/schedsys3/php/login/login.php


// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../login/login.php");
    exit();
}

// Get the current user's first name and department code from the session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
$email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : 'no email'; 
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ay_code = $row['ay_code'];
            $ay_name = $row['ay_name'];
            $semester = $row['semester'];
        } 


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_table'])) {
    $section_sched_code = $_POST['section_sched_code'];
    $section_code = $_POST['section_code'];
    $ay_code = $_POST['ay_code'];
    $semester = $_POST['semester'];
    $status = 'draft';
    $table_start_time = $_SESSION['table_start_time'];
    $table_end_time = $_SESSION['table_end_time'];
    if (empty($section_sched_code) || empty($section_code) || empty($ay_code) || empty($semester)) {
        echo "All fields are required.";
        exit;
    }
    // Fetch dept_code and program_code based on section_code
    $sql = "SELECT dept_code, program_code, college_code,curriculum, petition FROM tbl_section WHERE section_code = '$section_code' AND ay_code = '$ay_code' AND semester = '$semester'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        echo "Invalid section code.";
        exit;
    }
    $row = $result->fetch_assoc();
    $dept_code = $row['dept_code'];
    $program_code = $row['program_code'];
    $section_college_code = $row['college_code'];
    $section_curriculum = $row['curriculum'];
    $petition = $row['petition'];




    $fetch_info_query = "SELECT cell_color,status FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
    $result = $conn->query($fetch_info_query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $color = $row['cell_color'] ?? '#FFFFFF';
        $status = $row['status'];
    } else {
        $color = '#FFFFFF';
    }

    // Check if section_sched_code already exists
    $check_sql = "SELECT section_sched_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code' AND curriculum ='$section_curriculum'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows == 0) {
        // Insert into tbl_secschedlist
        $insert_sql = "INSERT INTO tbl_secschedlist (college_code,section_sched_code,curriculum,dept_code, program_code, section_code, ay_code,petition) 
                       VALUES ('$section_college_code','$section_sched_code','$section_curriculum', '$dept_code', '$program_code', '$section_code', '$ay_code','$petition')";

        if ($conn->query($insert_sql) !== TRUE) {
            echo "Error inserting record: " . $conn->error;
            exit;
        }
    }

    $checkSql = "SELECT 1 FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ? AND curriculum = ? ";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $checkStmt->bind_param("sssss", $section_sched_code, $semester, $dept_code, $ay_code, $section_curriculum);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            echo "This schedule already exists on draft.";
            $checkStmt->close();

        } else {
            // Prepare SQL query
            $sql = "INSERT INTO tbl_schedstatus (college_code,section_sched_code,curriculum, semester, dept_code, status, ay_code, cell_color,petition) VALUES (?,?,?, ?, ?, ?, ?,?,?)";

            // Initialize prepared statement
            if ($stmt = $conn->prepare($sql)) {
                // Bind parameters
                $stmt->bind_param("ssssssssi", $section_college_code,$section_sched_code,$section_curriculum, $semester, $dept_code, $status, $ay_code, $color,$petition);


                // Execute query
                if ($stmt->execute()) {
                    echo "Draft saved successfully.";
                } else {
                    echo "Error: " . $stmt->error;
                }

                // Close statement
                $stmt->close();
            }
        }
    }

   

    // Sanitize the table name
    $sanitized_section_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $section_code);
    $sanitized_academic_year = preg_replace("/[^a-zA-Z0-9_]/", "_", $ay_code);
    $table_name = "tbl_secsched_" . $dept_code . "_" . $sanitized_academic_year;

    // Check if table exists
    $table_check_sql = "SHOW TABLES LIKE '$table_name'";
    $table_check_result = $conn->query($table_check_sql);

    if ($table_check_result->num_rows == 1) {
        // Table exists, redirect to plotSchedule.php
        $_SESSION['section_sched_code'] = $section_sched_code;
        $_SESSION['semester'] = $semester;
        $_SESSION['section_code'] = $section_code;
        $_SESSION['table_name'] = $table_name;
        header("Location: ./create_sched/plotSchedule.php");
        exit();
    } else {
        // Define table fields
        $unique_id = time(); // Use a timestamp to ensure uniqueness
        $columns_sql = "sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                        section_sched_code VARCHAR(200) NOT NULL,
                        semester VARCHAR(255) NOT NULL,
                        day VARCHAR(50) NOT NULL,
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
                      ";

        $sql = "CREATE TABLE $table_name ($columns_sql)";

        if ($conn->query($sql) === TRUE) {
            echo "Table $table_name created successfully";
            // Redirect to plotSchedule.php
            $_SESSION['section_sched_code'] = $section_sched_code;
            $_SESSION['semester'] = $semester;
            $_SESSION['section_code'] = $section_code;
            $_SESSION['table_name'] = $table_name;
            
            header("Location: ./create_sched/plotSchedule.php");
            exit();
        } else {    
            echo "Error creating table: " . $conn->error;
        }
    }

    $conn->close();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_contact'])) {
    $prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : '';
    $prof_sched_code = isset($_POST['prof_sched_code']) ? $_POST['prof_sched_code'] : '';
    $ay_code = $_POST['ay_code'];
    $semester = $_POST['semester'];
    $dept_code = $_POST['dept_code'];
    $status = "draft";
    $prof_sched_code = $prof_code . '_' . $ay_code;
  
    // Fetch dept_code and program_code based on section_code
    $sql = "SELECT dept_code, prof_code FROM tbl_prof WHERE prof_code = '$prof_code'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 0) {
        echo "Invalid prof code.";
        exit;
    }
  
    $row = $result->fetch_assoc();
    $dept_code = $row['dept_code'];
    $prof_code = $row['prof_code'];
  
    // Check if section_sched_code already exists
    $check_sql = "SELECT prof_sched_code FROM tbl_psched WHERE prof_sched_code = '$prof_sched_code' AND semester = '$semester'";
    $check_result = $conn->query($check_sql);
  
    if ($check_result->num_rows == 0) {
        $insert_sql = "INSERT INTO tbl_psched (prof_sched_code, dept_code, prof_code, ay_code) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt === false) {
            die("Error in SQL preparation: " . $conn->error);
        }
        $stmt->bind_param("ssss", $prof_sched_code, $dept_code, $prof_code, $ay_code);
        if (!$stmt->execute()) {
            die("Error executing SQL query: " . $stmt->error);
        }
    }

    // Fetch the professor's consultation hours
    $fetch_prof_consultation_hrs_query = "SELECT consultation_hrs FROM tbl_psched_counter WHERE prof_sched_code = ? AND semester = ?";
    $stmt_prof_consultation_hrs = $conn->prepare($fetch_prof_consultation_hrs_query);
    $stmt_prof_consultation_hrs->bind_param("ss", $prof_sched_code, $semester);
    $stmt_prof_consultation_hrs->execute();
    $stmt_prof_consultation_hrs->bind_result($prof_consultation_hrs);
    $stmt_prof_consultation_hrs->fetch();
    $stmt_prof_consultation_hrs->close();

    // Set consultation hours to zero if NULL
    if ($prof_consultation_hrs === NULL) {
        $prof_consultation_hrs = 0;
    }

    $fetch_consultation_hrs_query = "SELECT current_consultation_hrs FROM tbl_pcontact_counter WHERE prof_code = ? AND semester = ?";
    $stmt_prof = $conn->prepare($fetch_consultation_hrs_query);
    $stmt_prof->bind_param("ss", $prof_code, $semester);
    $stmt_prof->execute();
    $stmt_prof->bind_result($current_consultation_hrs);
    $stmt_prof->fetch();
    $stmt_prof->close();

    // Set default value to zero if current consultation hours are NULL
    if ($current_consultation_hrs === NULL) {
        $current_consultation_hrs = 0;
    }

    // Initialize extension_hrs and research_hrs to zero if they are not defined
    $extension_hrs = isset($extension_hrs) ? $extension_hrs : 0;
    $research_hrs = isset($research_hrs) ? $research_hrs : 0;

    // Check if an entry in tbl_pcontact_counter exists
    $counter_check_sql = "SELECT COUNT(*) FROM tbl_pcontact_counter WHERE prof_sched_code = ?";
    $stmt_counter_check = $conn->prepare($counter_check_sql);
    $stmt_counter_check->bind_param("s", $prof_sched_code);
    $stmt_counter_check->execute();
    $stmt_counter_check->bind_result($counter_exists);
    $stmt_counter_check->fetch();
    $stmt_counter_check->close();

    if ($counter_exists == 0) {
        // Insert new record into tbl_pcontact_counter
        $insert_counter_sql = "INSERT INTO tbl_pcontact_counter (dept_code, prof_code, prof_sched_code, current_consultation_hrs, consultation_hrs,ay_code, semester, extension_hrs, research_hrs) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)";
        $stmt_insert_counter = $conn->prepare($insert_counter_sql);
        $stmt_insert_counter->bind_param("sssssssss", $dept_code, $prof_code, $prof_sched_code, $current_consultation_hrs, $prof_consultation_hrs,$ay_code, $semester, $extension_hrs, $research_hrs);

        if ($stmt_insert_counter->execute() === FALSE) {
            die("Error inserting into tbl_pcontact_counter: " . $stmt_insert_counter->error);
        }
        $stmt_insert_counter->close();
    } else {
        // Update consultation hours if prof_sched_code already exists
        $update_counter_sql = "UPDATE tbl_pcontact_counter SET consultation_hrs = ? WHERE prof_sched_code = ?";
        $stmt_update_counter = $conn->prepare($update_counter_sql);
        $stmt_update_counter->bind_param("ds", $prof_consultation_hrs, $prof_sched_code);

        if ($stmt_update_counter->execute() === FALSE) {
            die("Error updating consultation hours in tbl_pcontact_counter: " . $stmt_update_counter->error);
        }
        $stmt_update_counter->close();
    }
  
    // Sanitize the table name
    $sanitized_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $dept_code);
    $sanitized_academic_year = preg_replace("/[^a-zA-Z0-9_]/", "_", $ay_code);
    $table_name = "tbl_pcontact_sched_" . $sanitized_dept_code . "_" . $sanitized_academic_year;
  
    // Check if table exists
    $table_check_sql = "SHOW TABLES LIKE '$table_name'";
    $table_check_result = $conn->query($table_check_sql);
  
    if ($table_check_result->num_rows == 1) {
        // Table exists, now check if record in tbl_pcontact_schedstatus exists
        $check_schedstatus_sql = "SELECT * FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
        $stmt_check_schedstatus = $conn->prepare($check_schedstatus_sql);
        $stmt_check_schedstatus->bind_param("sss", $prof_sched_code, $semester, $dept_code);
        $stmt_check_schedstatus->execute();
        $stmt_check_schedstatus->store_result();
      
        if ($stmt_check_schedstatus->num_rows == 0) {
            // No record found, insert a new record
            $insert_schedstatus_sql = "INSERT INTO tbl_pcontact_schedstatus (prof_sched_code, prof_code, semester, dept_code, status, ay_code) 
                                       VALUES (?, ?, ?, ?, 'draft', ?)";
            $stmt_insert_schedstatus = $conn->prepare($insert_schedstatus_sql);
            $stmt_insert_schedstatus->bind_param("sssss", $prof_sched_code, $prof_code, $semester, $dept_code, $ay_code);
            $stmt_insert_schedstatus->execute();
            $stmt_insert_schedstatus->close();
        }
        // Redirect to plotSchedule.php
        $_SESSION['prof_sched_code'] = $prof_sched_code;
        $_SESSION['semester'] = $semester;
        $_SESSION['prof_code'] = $prof_code;
        $_SESSION['dept_code'] = $dept_code;
        $_SESSION['table_name'] = $table_name;
        header("Location: input_forms/contact_plot.php");
        exit();
    } else {
        // Create table if it doesn't exist
        $unique_id = time(); // Use a timestamp to ensure uniqueness
        $columns_sql = "sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                        prof_sched_code VARCHAR(200) NOT NULL,
                        semester VARCHAR(255) NOT NULL,
                        prof_code VARCHAR(255) NOT NULL,
                        dept_code VARCHAR(255) NOT NULL,
                        day VARCHAR(50) NOT NULL,
                        time_start TIME NOT NULL,
                        time_end TIME NOT NULL,
                        consultation_hrs_type VARCHAR(100) NOT NULL";
    
        $sql = "CREATE TABLE $table_name ($columns_sql)";
    
        if ($conn->query($sql) === TRUE) {
            echo "Table $table_name created successfully";
            // Redirect to plotSchedule.php
            $_SESSION['prof_sched_code'] = $prof_sched_code;
            $_SESSION['semester'] = $semester;
            $_SESSION['dept_code'] = $dept_code;
            $_SESSION['prof_code'] = $prof_code;
            $_SESSION['table_name'] = $table_name;
            
            header("Location: contact_plot.php");
            exit();
        } else {    
            echo "Error creating table: " . $conn->error;
        }
    }

    $conn->close();
}


$sql_fetch = "SELECT table_start_time, table_end_time,ay_code,semester FROM tbl_timeslot_active WHERE active = 1 AND dept_code = ? AND ay_code = ? AND semester = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("sis", $dept_code,$ay_code,$semester);
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows > 0) {
    // Fetch the active time slot
    $row_fetch = $result_fetch->fetch_assoc();
    $old_time_start = $row_fetch['table_start_time'];
    $old_time_end= $row_fetch['table_end_time'];
    $old_ay_code= $row_fetch['ay_code'];
    $old_semester= $row_fetch['semester'];

}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_table'])) {
        // Get the selected time slot
        $selected_slot = $_POST['table_time'];
        
        // Explode the time slot into start and end times
        list($start_time, $end_time) = explode(' - ', $selected_slot);

        // // Save to session
        // $_SESSION['table_start_time'] = $start_time;
        // $_SESSION['table_end_time'] = $end_time;


        // Prepare and execute the update query
        $sql_insert = "INSERT INTO tbl_timeslot_active (table_start_time, table_end_time, dept_code, ay_code, semester, active) 
               VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("sssss", $start_time, $end_time, $dept_code, $ay_code, $semester); // Assuming dept_code is already defined
        $stmt->execute();
        $stmt->close();

        $sql_delete = "DELETE FROM tbl_timeslot_active 
                       WHERE table_start_time = ? AND table_end_time = ? AND dept_code = ? AND ay_code = ? AND semester = ?";
        $stmt = $conn->prepare($sql_delete);
        $stmt->bind_param("sssss", $old_time_start, $old_time_end, $dept_code, $old_ay_code, $old_semester); // Assuming dept_code is already defined
        $stmt->execute();
        $stmt->close();


        // Redirect to create_sched.php
        header('Location: create_sched.php');
        exit; // Make sure to exit after header redirection
    }
    if (isset($_POST['add_time_slot'])) {
        // Get the new start and end times from the form
        $new_start_time = $_POST['table_start_time'];
        $new_end_time = $_POST['table_end_time'];
        $active = 0;
        $formatted_start_time = date("g:i A", strtotime($new_start_time));
        $formatted_end_time = date("g:i A", strtotime($new_end_time));
    
        $check_sql = "
        SELECT * FROM tbl_timeslot 
        WHERE dept_code = ? 
        AND table_start_time = ? 
        AND table_end_time = ?
    ";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $dept_code, $formatted_start_time, $formatted_end_time);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
    
        if ($check_result->num_rows > 0) {
            // Time slot already exists in the department
            $alertMessage = '<div class="alert alert-warning">Table format already exists.</div>';
        } else if (strtotime($formatted_start_time) >= strtotime($formatted_end_time)) {
            // Start time is earlier than or equal to end time
            $alertMessage = '<div class="alert alert-warning">Start time cannot be earlier than or the same as the end time.</div>';
        }else {
            $sql = "INSERT INTO tbl_timeslot (table_start_time, table_end_time, dept_code) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $formatted_start_time, $formatted_end_time, $dept_code);
        
            if ($stmt->execute()) {
                $alertMessage = '<div class="alert alert-success">Table format added successfully!</div>';
            } else {
                $alertMessage = '<div class="alert alert-danger">Error adding time slot: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
        $check_stmt->close();

        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = new bootstrap.Modal(document.getElementById("ChangeTableModal"));
            modal.show();

            // Reload page when modal is hidden (closed)
            modal._element.addEventListener("hidden.bs.modal", function() {
                window.location.href = window.location.href;
            });
        });
    </script>';
    }
    
}

$sql_fetch = "SELECT table_start_time, table_end_time 
              FROM tbl_timeslot_active 
              WHERE active = 1 AND dept_code = ? AND semester = ? AND ay_code = ?";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("sss", $dept_code, $semester, $ay_code); // Assuming all are strings
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();


if ($result_fetch->num_rows > 0) {
    // Fetch the active time slot
    $row_fetch = $result_fetch->fetch_assoc();
    $_SESSION['table_start_time'] = $row_fetch['table_start_time'];
    $_SESSION['table_end_time'] = $row_fetch['table_end_time'];
} else {
    // Defaults if no active time slot is found
    $_SESSION['table_start_time'] = '7:00 am';
    $_SESSION['table_end_time'] = '7:00 pm';
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="../../css/department_secretary/create.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/"; 
    include($IPATH . "navbar.php");  ?>

    <h1 class="title">PLOT SCHEDULE</h1>
    <div class="form-group text-center">
        <label><?php echo $_SESSION['table_start_time']; ?>-<?php echo $_SESSION['table_end_time']; ?></label> <br>
        <button type="button" class="btn align-items-center mt-10" style = "width:15%; padding:0px; border: none;" id= "btntime" data-bs-toggle="modal" data-bs-target="#ChangeTableModal">
            Change Table Format
        </button>
    </div>
    <div class="container">
        <div class="data-section">
            <h2>Choose...</h2>
            <form method="POST" action="">
                <div class="data-items">
                <button type="button" value="create_new" class="data-item" data-bs-toggle="modal" data-bs-target="#createTableModal">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <h4>SECTION SCHEDULE</h4>
                </button>
                <?php if ($_SESSION['user_type'] == 'Department Secretary'): ?>
                    <button type="button" data-bs-toggle="modal" value="new_contact" data-bs-target="#createContactTableModal" class="data-item">
                        <i class="fa-solid fa-pen-to-square"></i> 
                        <h4>CONSULTATION HOURS</h4>
                    </button>
                <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    
<div class="modal fade" id="ChangeTableModal" tabindex="-1" aria-labelledby="ChangeTableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ChangeTableModalLabel">Schedule Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeModalButton"></button>
            </div>
            <div class="modal-body">
            <?php
                if (isset($alertMessage)) {
                    echo $alertMessage;
                }
                ?>
                
                <h6>Select Schedule Table</h6>
                <form method="POST" action="create_sched.php"> <!-- Specify the action page here -->
                    <div class="form-group mb-3">
                        <select class="form-control" id="table_time" name="table_time" required>
                            <option value="">Select a time slot</option>
                            <?php
                            // Fetch start and end times from the timeslot table
                            $sql = "SELECT table_start_time, table_end_time FROM tbl_timeslot WHERE dept_code = '$dept_code'";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $start_time = date("g:i A", strtotime($row["table_start_time"]));
                                    $end_time = date("g:i A", strtotime($row["table_end_time"]));
                                    
                                    // Display time slot in the format "Start Time - End Time"
                                    echo '<option value="' . $start_time . ' - ' . $end_time . '">' . $start_time . ' - ' . $end_time . '</option>';
                                }
                            } else {
                                echo '<option value="">No time slots available</option>';
                            }
                            ?>
                        </select>
                        <button type="submit" class="btn btn-sm mt-2" id="create" name="change_table">Change</button>
                    </div>
                </form>
                <!-- Section for adding a new time slot -->
                <hr>
                <h6>New Schedule Table</h6>
                <form method="POST" id= "newTimeSlotForm" action="create_sched.php">
                <div class="d-flex justify-content-between">
                    <div class="form-group mb-1 me-2" style="flex: 1;">
                        <select id="start_time" name="table_start_time" class="form-control form-control-sm">
                            <?php
                            // Loop through hours from 1 AM to 11 PM
                            for ($i = 1; $i <= 23; $i++) {
                                // Format the time in 24-hour format
                                $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":00:00";
                                // Convert to 12-hour format
                                $time_12 = date("g:i A", strtotime($time_24));
                                // Mark "07:00:00" as selected by default if needed
                                $selected = ($time_24 == "07:00:00") ? ' selected' : '';
                                echo '<option value="' . $time_24 . '"' . $selected . '>' . $time_12 . '</option>';
                            }
                            echo '<option value="00:00:00">12:00 AM</option>';
                            ?>
                        </select>
                    </div>

                    <div class="form-group mb-1" style="flex: 1;">
                        <select id="end_time" name="table_end_time" class="form-control form-control-sm">
                            <?php
                                for ($i = 1; $i <= 23; $i++) {
                                    // Format the time in 24-hour format
                                    $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":00:00";
                                    // Convert to 12-hour format
                                    $time_12 = date("g:i A", strtotime($time_24));
                                    // Mark "07:00:00" as selected by default if needed
                                    $selected = ($time_24 == "07:00:00") ? ' selected' : '';
                                    echo '<option value="' . $time_24 . '"' . $selected . '>' . $time_12 . '</option>';
                                }
                            echo '<option value="00:00:00">12:00 AM</option>';
                            ?>
                        </select>
                    </div>
                </div>
        
                <button type="submit" class="btn" id="create" name="add_time_slot" >Add New</button>
            </div>     
            <div id="alertMessage"> </div>        
        </form>
        </div>
    </div>
</div>




    <div class="modal fade" id="createTableModal" tabindex="-1" role="dialog" aria-labelledby="createTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTableModalLabel">Plot Section Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm" action="" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" id="section_sched_code" name="section_sched_code" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="ay_code">
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($ay_name); ?><br>
                                <strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?><br><br>
                            </label>  
                        </div>

                        <?php
            // Fetch unique program_code, num_year, and curriculum values from the database
                            $query = "SELECT DISTINCT program_code, num_year, curriculum FROM tbl_program WHERE dept_code = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("s", $dept_code);
                            $stmt->execute();
                            $program_result = $stmt->get_result();
                            $programs = [];

                            if ($program_result->num_rows > 0) {
                                while ($row = $program_result->fetch_assoc()) {
                                    $programs[] = $row; // Store unique program_code, num_year, and curriculum
                                }
                            }

                            // Initialize variables for search criteria (if provided via GET/POST)
                            $search_program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
                            $search_curriculum = isset($_GET['curriculum']) ? $_GET['curriculum'] : '';
                        ?>

                        <div class="form-group">
                            <select class="form-control w-100" name="program_code" id="program_code" required>
                                <option value="" disabled selected>Program Code</option>
                                <?php 
                                // Display unique program_code in the dropdown
                                foreach (array_unique(array_column($programs, 'program_code')) as $program_code): ?>
                                    <option value="<?php echo htmlspecialchars($program_code); ?>" 
                                        <?php if ($program_code == $search_program_code) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($program_code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div><br>
                        <div class="form-group">       
                            <select class="form-control w-100" name="curriculum" id="curriculum" required>
                                <option value="" disabled selected>Curriculum</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <!-- Year Level Dropdown -->
                            <select class="form-control" id="year_level" name="year_level" required>
                                <option value="" disabled selected>Year Level</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control" id="section_code" name="section_code" required>
                                <option value="">Select a section</option>
                                <?php
                                // Assuming $sections is an array containing section codes
                                if (!empty($sections)) {
                                    foreach ($sections as $section) {
                                        // Use htmlspecialchars to escape special characters for safe HTML output
                                        echo '<option value="' . htmlspecialchars($section) . '">' . htmlspecialchars($section) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No sections available</option>';
                                }
                                ?>
                            </select>
                        </div><br>
                        <div class="form-group">
                            <input type="hidden" id="ay_code" name="ay_code" value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester" value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>
                        <button type="submit" name="create_table" id ="create" class="btn">Plot Schedule</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function submitTimeSlot() {
    const form = document.getElementById('newTimeSlotForm');
    const formData = new FormData(form);

    fetch('create_sched.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => response.text())
    .then(data => {
        const alertMessage = document.getElementById('alertMessage');
        
        if (data.includes("alert-danger")) {
            // Show the danger alert without closing the modal
            alertMessage.innerHTML = data;
        } else {
            // Success: Show success alert and close the modal after a delay
            alertMessage.innerHTML = data;
            setTimeout(() => {
                $('#ChangeTableModal').modal('hide');
            }, 2000); // Close after 2 seconds
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

    const programs = <?php echo json_encode($programs); ?>;

    // Function to get the appropriate suffix for year levels
    function getSuffix(num) {
        const lastDigit = num % 10;
        const lastTwoDigits = num % 100;

        if (lastDigit === 1 && lastTwoDigits !== 11) {
            return 'st';
        } else if (lastDigit === 2 && lastTwoDigits !== 12) {
            return 'nd';
        } else if (lastDigit === 3 && lastTwoDigits !== 13) {
            return 'rd';
        } else {
            return 'th';
        }
    }

    // Function to populate Year Levels based on selected program_code and curriculum
    function populateYearLevels(selectedProgramCode, selectedCurriculum) {
        const yearLevelDropdown = document.getElementById('year_level');
        yearLevelDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>';

        const filteredPrograms = programs.filter(program => 
            program.program_code === selectedProgramCode && program.curriculum === selectedCurriculum
        );

        if (filteredPrograms.length > 0) {
            const numYears = filteredPrograms[0].num_year; // Get num_year for the selected program

            // Populate year level options based on num_year with correct suffix
            for (let i = 1; i <= numYears; i++) {
                const suffix = getSuffix(i); // Get appropriate suffix
                const yearLevelText = `${i}${suffix} Year`; // e.g., "1st Year", "2nd Year"
                yearLevelDropdown.innerHTML += `<option value="${i}">${yearLevelText}</option>`;
            }
        }
    }

    // Function to populate Section Codes based on selected inputs
        function populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel) {
        const sectionDropdown = document.getElementById('section_code');
        const ayCode = document.getElementById('ay_code').value; // Correctly retrieve the value
        const semester = document.getElementById('semester').value; // Retrieve the value
        sectionDropdown.innerHTML = '<option value="">Select a section</option>'; // Reset dropdown

        if (selectedProgramCode && selectedCurriculum && selectedYearLevel && ayCode && semester) {
            // Fetch sections based on the provided input values
            fetch(`get_sections.php?program_code=${selectedProgramCode}&curriculum=${selectedCurriculum}&year_level=${selectedYearLevel}&ay_code=${ayCode}&semester=${semester}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(section => {
                            sectionDropdown.innerHTML += `<option value="${section.section_code}">${section.section_code}</option>`;
                        });
                    } else {
                        sectionDropdown.innerHTML = '<option value="">No sections available</option>';
                    }
                })
                .catch(error => console.error('Error fetching sections:', error));
        }
    }

    // Populate Year Levels and Curriculums based on selected program_code
    document.getElementById('program_code').addEventListener('change', function() {
        const selectedProgramCode = this.value;
        const curriculumDropdown = document.getElementById('curriculum');
        
        // Clear existing options in curriculum dropdown
        curriculumDropdown.innerHTML = '<option value="" disabled selected>Curriculum</option>';

        // Populate curriculum based on the selected program
        const selectedPrograms = programs.filter(program => program.program_code === selectedProgramCode);
        selectedPrograms.forEach(program => {
            curriculumDropdown.innerHTML += `<option value="${program.curriculum}">${program.curriculum}</option>`;
        });
    });

    // Add event listener for curriculum changes
    document.getElementById('curriculum').addEventListener('change', function() {
        const selectedProgramCode = document.getElementById('program_code').value;
        const selectedCurriculum = this.value;

        if (selectedProgramCode && selectedCurriculum) {
            populateYearLevels(selectedProgramCode, selectedCurriculum);
        }
    });

    // Add event listener for year level changes
    document.getElementById('year_level').addEventListener('change', function() {
        const selectedProgramCode = document.getElementById('program_code').value;
        const selectedCurriculum = document.getElementById('curriculum').value;
        const selectedYearLevel = this.value;

        if (selectedProgramCode && selectedCurriculum && selectedYearLevel) {
            populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel);
        }
    });
</script>

<?php

// Fetch the current ay_code and semester from session or set defaults
$current_ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$current_semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
?>

<!-- Modal HTML -->
<div class="modal fade" id="createContactTableModal" tabindex="-1" role="dialog" aria-labelledby="createContactTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createContactTableModalLabel">Plot Schedule for Professor Consultation Hours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm" action="" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" id="prof_sched_code" name="prof_sched_code" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="ay_code">
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($ay_name); ?><br>
                                <strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?><br><br>
                            </label>
                        </div>
                        <div class="form-group">
                            <input list="prof_code_list" class="form-control" id="prof_code" name="prof_code" autocomplete="off" placeholder="Select or Type Professor Name" required>
                            <datalist id="prof_code_list">
                                <option value="">Select Professor Name</option>
                                <?php
                                // Fetch professor names
                                $sql = "SELECT prof_name, prof_code FROM tbl_prof WHERE dept_code = '$dept_code' AND semester = '$semester' AND ay_code = '$ay_code'";
                                $result = $conn->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<option value="' . $row["prof_code"] . '">' . $row["prof_name"] . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No Professor Available</option>';
                                }
                                ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="ay_code" name="ay_code" value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester" value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="dept_code" name="dept_code" value="<?php echo htmlspecialchars($dept_code); ?>" readonly>
                        </div>
                        <button type="submit" name="create_contact" class="btn" id ="create">Plot Consultation Hours</button>
                    </form>
                </div>
            </div>
        </div>
    </div>



<script>    
function updateSectionSchedCode() {
    const sectionCode = document.getElementById('section_code').value;
    const ayCode = document.getElementById('ay_code').value;
    const sectionSchedCode = sectionCode.replace('-', '_') + '_' + ayCode;
    document.getElementById('section_sched_code').value = sectionSchedCode;
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('section_code').addEventListener('change', updateSectionSchedCode);
    document.getElementById('ay_code').addEventListener('change', updateSectionSchedCode);
});
</script>
</body>
</html>
