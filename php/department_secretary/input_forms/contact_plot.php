<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../../config.php");

// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'Professor' && $_SESSION['user_type'] != 'CCL Head') {
    header("Location: ../../login/login.php");
    exit();
}

$prof_code = isset($_SESSION['prof_code']) ? $_SESSION['prof_code'] : '';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$prof_sched_code = isset($_SESSION['prof_sched_code']) ? $_SESSION['prof_sched_code'] : '';
$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');
// Error redirect URL
$error_redirect_url = 'contact_plot.php';

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];

    // Store ay_code and semester in the session
    $_SESSION['ay_code'] = $ay_code;
    $_SESSION['semester'] = $semester;
}

$fetch_info_query = "SELECT reg_adviser,college_code,user_type,dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email' AND ay_code = '$ay_code' AND semester = '$semester'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];
    $true_user_type = $row['user_type'];
    $dept_code = $row['dept_code'];


    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser" ?? '';
    } else {
        $current_user_type = null;
    }
}

$fetch_status_query = "SELECT * FROM  tbl_pcontact_schedstatus WHERE dept_code = '$dept_code' and prof_code = '$prof_code' AND ay_code = '$ay_code'";
$result_status = $conn->query($fetch_status_query);

if ($result_status->num_rows > 0) {
    $status_row = $result_status->fetch_assoc();
    $schedule_status = $status_row['status'];
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}


// echo "$semester";
// echo "$ay_code";

// Generate a new token if one is not set
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// Store schedule code in session
if (!empty($prof_sched_code)) {
    $_SESSION['prof_sched_code'] = $prof_sched_code;
}


// Initialize variables
$current_consultation_hrs = 0;
$consultation_hrs = 0;

// Prepare the SQL statement to check for the entry in tbl_pcontact_counter
$sql = "SELECT current_consultation_hrs FROM tbl_pcontact_counter WHERE prof_code = ? AND ay_code = ? AND semester = ? AND dept_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $prof_code, $ay_code, $semester, $dept_code);
$stmt->execute();
$stmt->store_result();

// Check if any rows were returned
if ($stmt->num_rows > 0) {
    // If an entry exists, fetch the results
    $stmt->bind_result($current_consultation_hrs);
    $stmt->fetch();
}


// Fetch consultation_hrs from tbl_prof for the given prof_code
$sql_prof = "SELECT consultation_hrs FROM tbl_psched_counter WHERE prof_code = ? AND ay_code = ? AND semester = ? AND dept_code = ?";
$stmt_prof = $conn->prepare($sql_prof);
$stmt_prof->bind_param("ssss", $prof_code, $ay_code, $semester, $dept_code);
$stmt_prof->execute();
$stmt_prof->bind_result($consultation_hrs);
$stmt_prof->fetch();
$stmt_prof->close();

// Create the display text
$display_text = $prof_code . ' - ' . ' (' . $current_consultation_hrs . '/' . $consultation_hrs . ' hrs)';


$sql = "SELECT * FROM tbl_prof WHERE prof_code = '$prof_code' AND semester = '$semester' AND ay_code = '$ay_code' ";
$result = $conn->query($sql);

$row = $result->fetch_assoc();
$academic_rank = $row['academic_rank'] ?? '';

// Close the initial statement
$stmt->close();

// Fetch departments for the dropdown
$departments = [];
$dept_sql = "SELECT dept_code, dept_name FROM tbl_department";
$dept_result = $conn->query($dept_sql);
if ($dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Fetch the academic year name
$ay_name = '';
if (!empty($ay_code)) {
    $sql = "SELECT ay_name FROM tbl_ay WHERE ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ay_code);
    $stmt->execute();
    $stmt->bind_result($ay_name);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['prof_sched_code'], $_POST['semester'], $_POST['prof_code'])) {
        $_SESSION['prof_sched_code'] = $_POST['prof_sched_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['prof_code'] = $_POST['prof_code'];
        $prof_sched_code = $_SESSION['prof_sched_code'];
        $semester = $_SESSION['semester'];
        $ay_code = $_SESSION['ay_code'];
        $prof_code = $_SESSION['prof_code'];
        $dept_code = $_SESSION['dept_code'];  // Make sure this is set properly
    }

    if (isset($_POST['day'], $_POST['time_start'], $_POST['time_end'], $_POST['consultation_hrs_type'])) {
        $day = $_POST['day'];
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $consultation_hrs_type = $_POST['consultation_hrs_type'];
    }

    if (isset($_POST['plot_schedule'])) {
        $time_start_dt = new DateTime($time_start);
        $time_end_dt = new DateTime($time_end);

        if ($time_end_dt <= $time_start_dt) {
            // Set a session or a flag for the modal
            $_SESSION['modal_message'] = 'End time cannot be earlier than or the same as start time.';
            header("Location: contact_plot.php");
            exit;
        }

        $interval = $time_start_dt->diff($time_end_dt);
        $hours = $interval->h + ($interval->i / 60.0);

        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
        $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);

        // Check if there is an entry in tbl_pcontact_schedstatus
        $check_schedstatus_sql = "SELECT COUNT(*) FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
        $stmt_schedstatus_check = $conn->prepare($check_schedstatus_sql);
        $stmt_schedstatus_check->bind_param("ss", $prof_sched_code, $semester);
        $stmt_schedstatus_check->execute();
        $stmt_schedstatus_check->bind_result($schedstatus_count);
        $stmt_schedstatus_check->fetch();
        $stmt_schedstatus_check->close();

        if ($schedstatus_count == 0) {
            $status = "draft";

            $insert_schedstatus_sql = "INSERT INTO tbl_pcontact_schedstatus (prof_sched_code, prof_code, semester,  ay_code,  dept_code, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_schedstatus_insert = $conn->prepare($insert_schedstatus_sql);
            $stmt_schedstatus_insert->bind_param("ssssss", $prof_sched_code, $prof_code, $semester, $ay_code, $dept_code, $status);

            if ($stmt_schedstatus_insert->execute() === FALSE) {
                $_SESSION['modal_message'] = 'Error inserting into tbl_pcontact_schedstatus: ' . $stmt_schedstatus_insert->error;
                header("Location: contact_plot.php");
                exit;
            }
            $stmt_schedstatus_insert->close();
        }

        // Check if the professor schedule table exists
        $table_check_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
        $table_check_result = $conn->query($table_check_sql);

        if ($table_check_result->num_rows == 0) {
            $create_table_sql = "CREATE TABLE $sanitized_pcontact_sched_code (
                                    sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                    prof_sched_code VARCHAR(200) NOT NULL,
                                    semester VARCHAR(255) NOT NULL,
                                    ay_code VARCHAR(255) NOT NULL,
                                    prof_code VARCHAR(255) NOT NULL,
                                    dept_code VARCHAR(255) NOT NULL,
                                    day VARCHAR(50) NOT NULL,
                                    time_start TIME NOT NULL,
                                    time_end TIME NOT NULL,
                                    consultation_hrs_type VARCHAR(100) NOT NULL
                                  )";
            if ($conn->query($create_table_sql) === FALSE) {
                $_SESSION['modal_message'] = 'Error creating table $sanitized_pcontact_sched_code: ' . $conn->error;
                header("Location: contact_plot.php");
                exit;
            }
        }

        // Check if $sanitized_prof_sched_code table exists
        $check_table_exists_sql = "SHOW TABLES LIKE '$sanitized_prof_sched_code'";
        $result_check_table_exists = $conn->query($check_table_exists_sql);
        $table_exists = $result_check_table_exists->num_rows > 0;
        $result_check_table_exists->close();

        // Only perform conflict check if the table exists
        if ($table_exists) {
            // Check for schedule conflicts in the prof schedule table
            $conflict_check_sql_prof_sched = "SELECT COUNT(*) FROM $sanitized_prof_sched_code
                                            WHERE prof_sched_code = ? AND day = ? AND time_start < ? AND time_end > ? AND semester = ? AND dept_code = ? AND ay_code = ?";
            $stmt_conflict_check_prof = $conn->prepare($conflict_check_sql_prof_sched);
            $stmt_conflict_check_prof->bind_param("sssssss", $prof_sched_code, $day, $time_end, $time_start, $semester, $dept_code, $ay_code);
            $stmt_conflict_check_prof->execute();
            $stmt_conflict_check_prof->bind_result($conflict_count_prof);
            $stmt_conflict_check_prof->fetch();
            $stmt_conflict_check_prof->close();
        } else {
            // No conflict since the table doesn't exist (no schedule has been plotted yet)
            $conflict_count_prof = 0;
        }

        // Check for conflicts in the pcontact schedule table (assuming it always exists)
        $conflict_check_sql_pcontact_sched = "SELECT COUNT(*) FROM $sanitized_pcontact_sched_code
                                            WHERE prof_sched_code = ? AND day = ? AND time_start < ? AND time_end > ? AND semester = ? AND dept_code = ? AND ay_code = ?";
        $stmt_conflict_check_pcontact = $conn->prepare($conflict_check_sql_pcontact_sched);
        $stmt_conflict_check_pcontact->bind_param("sssssss", $prof_sched_code, $day, $time_end, $time_start, $semester, $dept_code, $ay_code);
        $stmt_conflict_check_pcontact->execute();
        $stmt_conflict_check_pcontact->bind_result($conflict_count_pcontact);
        $stmt_conflict_check_pcontact->fetch();
        $stmt_conflict_check_pcontact->close();

        // Check if there's a conflict in either table
        if ($conflict_count_prof > 0 || $conflict_count_pcontact > 0) {
            $_SESSION['modal_message'] = 'Schedule conflict detected. Please choose a different time.';
            header("Location: contact_plot.php");
            exit;
        } else {
            if (isset($consultation_hrs_type) && $consultation_hrs_type === 'Consultation Hours') {
                $fetch_prof_consultation_hrs_query = "SELECT consultation_hrs FROM tbl_pcontact_counter WHERE prof_sched_code = ? AND semester = ?";
                $stmt_prof_consultation_hrs = $conn->prepare($fetch_prof_consultation_hrs_query);
                $stmt_prof_consultation_hrs->bind_param("ss", $prof_sched_code, $semester);
                $stmt_prof_consultation_hrs->execute();
                $stmt_prof_consultation_hrs->bind_result($prof_consultation_hrs);
                $stmt_prof_consultation_hrs->fetch();
                $stmt_prof_consultation_hrs->close();

                if ($prof_consultation_hrs === NULL) {
                    $_SESSION['modal_message'] = 'The consultation hours for this professor could not be found.';
                    header("Location: contact_plot.php");
                    exit;
                }

                $fetch_consultation_hrs_query = "SELECT current_consultation_hrs FROM tbl_pcontact_counter WHERE prof_code = ? AND semester = ?";
                $stmt_prof = $conn->prepare($fetch_consultation_hrs_query);
                $stmt_prof->bind_param("ss", $prof_code, $semester);
                $stmt_prof->execute();
                $stmt_prof->bind_result($current_consultation_hrs);
                $stmt_prof->fetch();
                $stmt_prof->close();

                if ($current_consultation_hrs === NULL) {
                    $current_consultation_hrs = 0.0;
                }

                $new_total_consultation_hrs = $current_consultation_hrs + $hours;

                if ($new_total_consultation_hrs > $prof_consultation_hrs) {
                    $_SESSION['modal_message'] = 'Unable to plot schedule, contact hours exceed the allowed limit.';
                    header("Location: contact_plot.php");
                    exit;
                } else {
                    $contact_code = time();
                    $insert_query = "INSERT INTO $sanitized_pcontact_sched_code (prof_sched_code, semester, ay_code, prof_code, dept_code, day, time_start, time_end, consultation_hrs_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("sssssssss", $prof_sched_code, $semester, $ay_code, $prof_code, $dept_code, $day, $time_start, $time_end, $consultation_hrs_type);

                    if ($stmt->execute() === FALSE) {
                        $_SESSION['modal_message'] = 'Error inserting into table $sanitized_pcontact_sched_code: " . $stmt->error . "';
                        header("Location: contact_plot.php");
                        exit;
                    }
                    $stmt->close();

                    // Update the current contact hours
                    $update_query = "UPDATE tbl_pcontact_counter SET current_consultation_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    $stmt_update = $conn->prepare($update_query);
                    $stmt_update->bind_param("dsss", $new_total_consultation_hrs, $prof_code, $semester, $ay_code);

                    if ($stmt_update->execute() === FALSE) {
                        $_SESSION['modal_message'] = 'Error updating tbl_pcontact_counter: " . $stmt_update->error . "';
                        header("Location: contact_plot.php");
                        exit;
                    }
                    $stmt_update->close();
                    $_SESSION['modal_message'] = 'Schedule plotted successfully';
                    header("Location: contact_plot.php");

                    exit;
                }
            } else {
                $fetch_consultation_hrs_query = "SELECT extension_hrs, research_hrs FROM tbl_pcontact_counter WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                $stmt_prof = $conn->prepare($fetch_consultation_hrs_query);
                $stmt_prof->bind_param("sss", $prof_code, $semester, $ay_code);
                $stmt_prof->execute();
                $stmt_prof->bind_result($current_extension_hrs, $current_research_hrs);
                $stmt_prof->fetch();
                $stmt_prof->close();

                // Determine which column to update based on contact hours type
                if ($consultation_hrs_type === 'Extension') {
                    $new_total_extension_hrs = $current_extension_hrs + $hours;
                    $update_query = "UPDATE tbl_pcontact_counter SET extension_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    $stmt_update = $conn->prepare($update_query);
                    $stmt_update->bind_param("dsss", $new_total_extension_hrs, $prof_code, $semester, $ay_code);
                } else if ($consultation_hrs_type === 'Research') {
                    $new_total_research_hrs = $current_research_hrs + $hours;
                    $update_query = "UPDATE tbl_pcontact_counter SET research_hrs = ? WHERE prof_code = ? AND semester = ? AND ay_code = ?";
                    $stmt_update = $conn->prepare($update_query);
                    $stmt_update->bind_param("dsss", $new_total_research_hrs, $prof_code, $semester, $ay_code);
                }

                $contact_code = time();
                $insert_query = "INSERT INTO $sanitized_pcontact_sched_code (prof_sched_code, semester, ay_code, prof_code, dept_code, day, time_start, time_end, consultation_hrs_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("sssssssss", $prof_sched_code, $semester, $ay_code, $prof_code, $dept_code, $day, $time_start, $time_end, $consultation_hrs_type);

                if ($stmt->execute() === FALSE) {
                    $_SESSION['modal_message'] = 'Error inserting into table $sanitized_pcontact_sched_code: " . $stmt->error . "';
                    header("Location: contact_plot.php");
                    exit;
                }
                $stmt->close();

                // Execute update query for current contact hours
                if ($stmt_update->execute() === FALSE) {
                    $_SESSION['modal_message'] = 'Error updating tbl_pcontact_counter: " . $stmt_update->error . "';
                    header("Location: contact_plot.php");
                    exit;
                }
                $stmt_update->close();
                $_SESSION['modal_message'] = 'Schedule plotted successfully';
                header("Location: contact_plot.php");
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_sched'])) {
    // Retrieve POST values
$prof_sched_code = $_POST['prof_sched_code'] ?? '';
$semester = $_POST['semester'] ?? '';

    // Retrieve session values
    $prof_code = $_SESSION['prof_code'];
    $dept_code = $_SESSION['dept_code'] ;
    $ay_code = $_SESSION['ay_code'];

    // Validate required data
    if (empty($prof_sched_code) || empty($semester) || empty($prof_code) || empty($dept_code) || empty($ay_code)) {
        // $_SESSION['modal_message'] = 'Missing required data. Please try again.';
        // header("Location: contact_plot.php");
            echo"$prof_sched_code, $semester, $prof_code, $dept_code, $ay_code";

        exit;
    }



  $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

// Check if the table exists
$check_table_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
$result = $conn->query($check_table_sql);

    if ($result->num_rows === 0) {
        $_SESSION['modal_message'] = 'There is no plotted schedule for the provided professor.';
        header("Location: contact_plot.php");
        exit;
    }

    // Delete matching schedules
    $delete_sql = "DELETE FROM $sanitized_pcontact_sched_code WHERE semester = ? AND prof_sched_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt_delete = $conn->prepare($delete_sql);
    $stmt_delete->bind_param("ssss", $semester, $prof_sched_code, $dept_code, $ay_code);
    $stmt_delete->execute();

    // Update contact counter to reset hours
    $update_counter_sql = "UPDATE tbl_pcontact_counter SET current_consultation_hrs = 0, extension_hrs = 0, research_hrs = 0 WHERE prof_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
    $stmt_update_counter = $conn->prepare($update_counter_sql);
    $stmt_update_counter->bind_param("ssss", $prof_sched_code, $semester, $dept_code, $ay_code);
    $stmt_update_counter->execute();

    // Check if any remaining entries exist for the same prof_sched_code & semester
    $check_entries_sql = "SELECT COUNT(*) AS count FROM $sanitized_pcontact_sched_code WHERE semester = ? AND prof_sched_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt_check_entries = $conn->prepare($check_entries_sql);
    $stmt_check_entries->bind_param("ssss", $semester, $prof_sched_code, $dept_code, $ay_code);
    $stmt_check_entries->execute();
    $entry_result = $stmt_check_entries->get_result();
    $entry_count = $entry_result->fetch_assoc()['count'];

    // // If no more entries, delete from schedule status table
    // if ($entry_count == 0) {
    //     $delete_status_sql = "DELETE FROM tbl_pcontact_schedstatus WHERE semester = ? AND prof_sched_code = ? AND dept_code = ? AND ay_code = ?";
    //     $stmt_delete_status = $conn->prepare($delete_status_sql);
    //     $stmt_delete_status->bind_param("ssss", $semester, $prof_sched_code, $dept_code, $ay_code);
    //     $stmt_delete_status->execute();
    // }

    $_SESSION['modal_message'] = 'Schedule Deleted Successfully.';
    header("Location: contact_plot.php");
    exit;
}


//delete and update indv. sched
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prof_sched_code = $_POST['prof_sched_code'] ?? '';
    $sec_sched_id = $_POST['sec_sched_id'] ?? ''; // Add ID to the POST data
    $semester = $_POST['semester'] ?? '';
    $dept_code = $_POST['dept_code'] ?? '';
    $prof_code = $_POST['prof_code'] ?? '';
    $ay_code = $_POST['ay_code'] ?? '';
    $day = $_POST['day'] ?? '';
    $time_start = $_POST['time_start'] ?? '';
    $time_end = $_POST['time_end'] ?? '';
    $consultation_hrs_type = $_POST['consultation_hrs_type'] ?? '';

    // Sanitize table names
    $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
    $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);

    // Function to check if a table exists
    function tableExists($conn, $table_name)
    {
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        return $result && $result->num_rows > 0;
    }

    // Handle update schedule action
    if (isset($_POST['update_schedule'])) {
        $no_prof_conflict = true;
        $no_pcontact_conflict = true;

        // Check if professor schedule table exists
        if (tableExists($conn, $sanitized_prof_sched_code)) {
            // Check for conflicts in professor schedule
            $prof_conflict_check_sql = "SELECT COUNT(*) FROM $sanitized_prof_sched_code
                                        WHERE prof_sched_code = ? AND day = ? AND time_start < ? AND time_end > ? AND semester = ?";
            $stmt_prof_conflict_check = $conn->prepare($prof_conflict_check_sql);
            if (!$stmt_prof_conflict_check) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_prof_conflict_check->bind_param("sssss", $prof_sched_code, $day, $time_end, $time_start, $semester);
            $stmt_prof_conflict_check->execute();
            $stmt_prof_conflict_check->bind_result($prof_conflict_count);
            $stmt_prof_conflict_check->fetch();
            $stmt_prof_conflict_check->close();

            $no_prof_conflict = ($prof_conflict_count == 0);
        }

        if (tableExists($conn, $sanitized_pcontact_sched_code)) {
            // Prepare the SQL query to check for professor schedule conflicts
            $conflict_check_sql_pcontact_sched = "SELECT * FROM $sanitized_pcontact_sched_code 
WHERE day = ? AND semester = ? AND prof_code = ? 
AND sec_sched_id != ? 
AND (
    (time_start <= ? AND time_end > ?) 
    OR 
    (time_start < ? AND time_end >= ?)
)";

            // Prepare the statement
            $stmt = $conn->prepare($conflict_check_sql_pcontact_sched);
            if (!$stmt) {
                die("Error preparing statement: " . $conn->error);
            }

            // Bind the parameters
            $stmt->bind_param(
                "ssssssss",
                $day,
                $semester,
                $prof_code,
                $sec_sched_id,
                $time_start,
                $time_start,
                $time_end,
                $time_end
            );

            // Execute the query
            $stmt->execute();
            $result_pcontact_sched = $stmt->get_result();

            // Check for conflicts
            $pcontact_conflict_count = $result_pcontact_sched->num_rows;
            if ($pcontact_conflict_count > 0) {
                $conflicts[] = "Professor schedule conflict detected.";
            }

            // Determine if there are no conflicts
            $no_pcontact_conflict = ($pcontact_conflict_count == 0);
        }

        if (!$no_prof_conflict || !$no_pcontact_conflict) {
            echo "<script>
                  document.addEventListener('DOMContentLoaded', function() {
                      var modal = new bootstrap.Modal(document.getElementById('successModal'));
                      document.getElementById('successMessage').textContent = 'Schedule conflict detected. Please choose a different time.';
                      modal.show();
                  });
              </script>";
        } else {
            // Define the field_map
            $field_map = [
                "Consultation Hours" => "current_consultation_hrs",
                "Research" => "research_hrs",
                "Extension" => "extension_hrs"
            ];

            // Step 1: Fetch the current hours and details of the schedule being updated
            $fetch_hours_sql = "SELECT time_start, time_end, consultation_hrs_type FROM $sanitized_pcontact_sched_code 
                    WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ?";
            $stmt_fetch = $conn->prepare($fetch_hours_sql);
            if (!$stmt_fetch) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_fetch->bind_param("ssi", $prof_sched_code, $semester, $sec_sched_id);
            $stmt_fetch->execute();
            $result = $stmt_fetch->get_result();
            if ($result->num_rows > 0) {
                $schedule = $result->fetch_assoc();
                $old_time_start = $schedule['time_start'];
                $old_time_end = $schedule['time_end'];
                $old_consultation_hrs_type = $schedule['consultation_hrs_type'];
                $old_hrs = (strtotime($old_time_end) - strtotime($old_time_start)) / 3600; // Calculate old schedule hours
            } else {
                die("Error fetching schedule details.");
            }
            $stmt_fetch->close();

            // Step 2: Update the schedule
            $update_schedule_sql = "UPDATE $sanitized_pcontact_sched_code
                        SET day = ?, time_start = ?, time_end = ?, consultation_hrs_type = ?
                        WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ?";
            $stmt_update = $conn->prepare($update_schedule_sql);
            if (!$stmt_update) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_update->bind_param("ssssssi", $day, $time_start, $time_end, $consultation_hrs_type, $prof_sched_code, $semester, $sec_sched_id);

            if ($stmt_update->execute() === FALSE) {
                die("Error updating schedule: " . $stmt_update->error);
            }
            $stmt_update->close();

            // Step 3: Update the contact hours in tbl_pcontact_counter
            // Calculate the new hours for the updated schedule
            $new_hrs = (strtotime($time_end) - strtotime($time_start)) / 3600;

            // Subtract the old hours from the old consultation type
            $update_old_type_sql = "UPDATE tbl_pcontact_counter SET {$field_map[$old_consultation_hrs_type]} = 
                        {$field_map[$old_consultation_hrs_type]} - ? WHERE prof_sched_code = ?";
            $stmt_old_type = $conn->prepare($update_old_type_sql);
            if (!$stmt_old_type) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_old_type->bind_param("ds", $old_hrs, $prof_sched_code);
            if ($stmt_old_type->execute() === FALSE) {
                die("Error updating old consultation hours: " . $stmt_old_type->error);
            }
            $stmt_old_type->close();

            // Add the new hours to the updated consultation type
            $update_new_type_sql = "UPDATE tbl_pcontact_counter SET {$field_map[$consultation_hrs_type]} = 
                        {$field_map[$consultation_hrs_type]} + ? WHERE prof_sched_code = ?";
            $stmt_new_type = $conn->prepare($update_new_type_sql);
            if (!$stmt_new_type) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_new_type->bind_param("ds", $new_hrs, $prof_sched_code);
            if ($stmt_new_type->execute() === FALSE) {
                die("Error updating new consultation hours: " . $stmt_new_type->error);
            }
            $stmt_new_type->close();


            $_SESSION['modal_message'] = 'Schedule Update Successfully.';
            header("Location: contact_plot.php");
        }
    }

    if (isset($_POST['delete_schedule'])) {
        $prof_sched_code = $_POST['prof_sched_code'] ?? '';
        $semester = $_SESSION['semester'] ?? '';
        $dept_code = $_SESSION['dept_code'] ?? '';
        $day = $_POST['day'] ?? '';
        $time_start = $_POST['time_start'] ?? '';
        $time_end = $_POST['time_end'] ?? '';
        $consultation_hrs_type = $_POST['consultation_hrs_type'] ?? ''; // Ensure consultation_hrs_type is passed

        // Construct table names
        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

        // Step 1: Calculate the total hours of the schedule to be deleted
        $fetch_hours_sql = "SELECT TIME_TO_SEC(TIMEDIFF(time_end, time_start)) / 3600 AS total_hours 
                            FROM $sanitized_pcontact_sched_code 
                            WHERE prof_sched_code = ? AND semester = ? AND day = ? AND time_start = ? AND time_end = ?";
        $stmt_fetch_hours = $conn->prepare($fetch_hours_sql);
        if (!$stmt_fetch_hours) {
            die("Prepare failed: " . $conn->error);
        }

        $stmt_fetch_hours->bind_param("sssss", $prof_sched_code, $semester, $day, $time_start, $time_end);
        $stmt_fetch_hours->execute();
        $stmt_fetch_hours->bind_result($total_hours);
        $stmt_fetch_hours->fetch();
        $stmt_fetch_hours->close();

        if ($total_hours === NULL) {
            header("Location: contact_plot.php");
            exit;
        }

        // Step 2: Update `tbl_pcontact_counter` based on `consultation_hrs_type`
        $field_map = [
            "Consultation Hours" => "current_consultation_hrs",
            "Research" => "research_hrs",
            "Extension" => "extension_hrs"
        ];

        if (isset($field_map[$consultation_hrs_type])) {
            $counter_field = $field_map[$consultation_hrs_type];
            $update_schedstatus_sql = "UPDATE tbl_pcontact_counter 
                                       SET $counter_field = $counter_field - ? 
                                       WHERE prof_code = ? AND semester = ?";
            $stmt_update_schedstatus = $conn->prepare($update_schedstatus_sql);
            if (!$stmt_update_schedstatus) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_update_schedstatus->bind_param("dss", $total_hours, $prof_code, $semester);

            if ($stmt_update_schedstatus->execute() === FALSE) {
                die("Error updating schedule status: " . $stmt_update_schedstatus->error);
            }
            $stmt_update_schedstatus->close();

            // Step 3: Delete the specific schedule for the specified `consultation_hrs_type`
            $delete_schedule_sql = "DELETE FROM $sanitized_pcontact_sched_code 
                                    WHERE prof_sched_code = ? 
                                    AND semester = ? 
                                    AND day = ? 
                                    AND time_start = ? 
                                    AND time_end = ? 
                                    AND consultation_hrs_type = ?";
            $stmt_delete = $conn->prepare($delete_schedule_sql);
            if (!$stmt_delete) {
                die("Prepare failed: " . $conn->error);
            }
            $stmt_delete->bind_param("ssssss", $prof_sched_code, $semester, $day, $time_start, $time_end, $consultation_hrs_type);

            if ($stmt_delete->execute() === FALSE) {
                die("Error deleting schedule: " . $stmt_delete->error);
            } else {
                echo "<script>
                      document.addEventListener('DOMContentLoaded', function() {
                          var modal = new bootstrap.Modal(document.getElementById('successModal'));
                          document.getElementById('successMessage').textContent = '$consultation_hrs_type schedule deleted successfully.';
                          modal.show();
                      });
                  </script>";
            }
            $stmt_delete->close();
        } else {
            die("Invalid contact hours type.");
        }

        // Step 4: Check if the table is now empty
        $check_empty_sql = "SELECT COUNT(*) FROM $sanitized_pcontact_sched_code";
        $result = $conn->query($check_empty_sql);
        if (!$result) {
            die("Error executing query: " . $conn->error);
        }
        $row = $result->fetch_row();
        $table_is_empty = ($row[0] == 0);
        $result->close();

        // If the table is empty, drop it
        if ($table_is_empty) {
            // $drop_table_sql = "DROP TABLE $sanitized_pcontact_sched_code";
            // if ($conn->query($drop_table_sql) === FALSE) {
            //     die("Error dropping table: " . $conn->error);
            // }

            // Step 5: Check if the $sanitized_pcontact_sched_code table exists
            $check_table_exists_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
            $result_check_table_exists = $conn->query($check_table_exists_sql);
            if (!$result_check_table_exists) {
                die("Error executing query: " . $conn->error);
            }
            $table_exists = $result_check_table_exists->num_rows > 0;
            $result_check_table_exists->close();

            // If the table doesn't exist, delete the entry in `tbl_pcontact_schedstatus`
            if (!$table_exists) {
                $delete_schedstatus_sql = "DELETE FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
                $stmt_delete_schedstatus = $conn->prepare($delete_schedstatus_sql);
                if (!$stmt_delete_schedstatus) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt_delete_schedstatus->bind_param("ss", $prof_sched_code, $semester);

                if ($stmt_delete_schedstatus->execute() === FALSE) {
                    die("Error deleting schedule status: " . $stmt_delete_schedstatus->error);
                }
                $stmt_delete_schedstatus->close();
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// completion of schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['status'])) {
        $status = $_POST['status'];
        $prof_sched_code = $_SESSION['prof_sched_code'];
        $semester = $_SESSION['semester']; // Assuming the semester is stored in session

        // Check the current status
        $status_check_sql = "SELECT status FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
        $stmt_status_check = $conn->prepare($status_check_sql);
        $stmt_status_check->bind_param("ss", $prof_sched_code, $semester);
        $stmt_status_check->execute();
        $stmt_status_check->bind_result($current_status);
        $stmt_status_check->fetch();
        $stmt_status_check->close();

        // If the schedule is already completed, do not update it again
        if ($current_status === 'public' && $status === 'public') {
            $_SESSION['modal_message'] = 'This Schedule is already completed.';
            header("Location: contact_plot.php");

        } else {
            // Update the status
            $sql = "UPDATE tbl_pcontact_schedstatus SET status = ? WHERE prof_sched_code = ? AND semester = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $status, $prof_sched_code, $semester);

            if ($stmt->execute()) {
                 $_SESSION['modal_message'] = 'Schedule Status is Updated to Completed Sucessfully.';
            header("Location: contact_plot.php");

            } else {
                echo 'Error updating schedule status: ' . $stmt->error;
            }
            $stmt->close();
        }
        exit();
    }
}


// For drafts
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_as_draft'])) {
        $prof_sched_code = $_POST['prof_sched_code'];
        $semester = $_POST['semester'];

        // Check if there is an entry in tbl_pcontact_schedstatus
        $check_schedstatus_sql = "SELECT status FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
        $stmt_schedstatus_check = $conn->prepare($check_schedstatus_sql);
        $stmt_schedstatus_check->bind_param("ss", $prof_sched_code, $semester);
        $stmt_schedstatus_check->execute();
        $stmt_schedstatus_check->bind_result($status);
        $stmt_schedstatus_check->fetch();
        $stmt_schedstatus_check->close();

        if ($status === null) {
            $status = "draft";

            $insert_schedstatus_sql = "INSERT INTO tbl_pcontact_schedstatus (prof_sched_code, prof_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_schedstatus_insert = $conn->prepare($insert_schedstatus_sql);
            $stmt_schedstatus_insert->bind_param("ssssss", $prof_sched_code, $prof_code, $semester, $dept_code, $status, $ay_code);

            if ($stmt_schedstatus_insert->execute() === FALSE) {
                die("Error inserting into tbl_pcontact_schedstatus: " . $stmt_schedstatus_insert->error);
            }
            $stmt_schedstatus_insert->close();
            echo "<script>
                    alert('Schedule Saved as Drafts');
                    window.location.href = 'contact_plot.php';
                  </script>";
        } elseif ($status === "public") {
            // Existing entry with status "completed", update to "draft"
            $update_schedstatus_sql = "UPDATE tbl_pcontact_schedstatus SET status = ? WHERE prof_sched_code = ? AND semester = ?";
            $stmt_schedstatus_update = $conn->prepare($update_schedstatus_sql);
            $new_status = "draft";
            $stmt_schedstatus_update->bind_param("sss", $new_status, $prof_sched_code, $semester);

            if ($stmt_schedstatus_update->execute() === FALSE) {
                die("Error updating tbl_pcontact_schedstatus: " . $stmt_schedstatus_update->error);
            }
            $stmt_schedstatus_update->close();

            // echo "<script>
            //         alert('Schedule Saved as Drafts');
            //         window.location.href = 'contact_plot.php';
            //       </script>";
        } else {
            // echo "<script>
            //         alert('Schedule Saved as Drafts');
            //         window.location.href = 'contact_plot.php';
            //       </script>";
        }
        exit();
    }
}

//change prof_code 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_table'])) {
    $prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : '';
    $prof_sched_code = isset($_POST['prof_sched_code']) ? $_POST['prof_sched_code'] : '';
    $ay_code = $_SESSION['ay_code'];
    $semester = $_SESSION['semester'];
    $dept_code = $_POST['dept_code'];


    $prof_sched_code = $prof_code . '_' . $ay_code;

    // Fetch dept_code and program_code
    $sql = "SELECT dept_code, prof_code FROM tbl_prof WHERE prof_code = '$prof_code'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('successModal'));
            document.getElementById('successMessage').textContent = 'Invalid prof code.';
            modal.show();
        });
    </script>";
        exit;
    }

    $row = $result->fetch_assoc();
    $dept_code = $row['dept_code'];
    $prof_code = $row['prof_code'];

    // Check if section_sched_code already exists
    $check_sql = "SELECT prof_sched_code FROM tbl_pcontact_schedstatus WHERE prof_sched_code = '$prof_sched_code'";
    $check_result = $conn->query($check_sql);

    $checkSql = "SELECT 1 FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $checkStmt->bind_param("ssss", $prof_sched_code, $semester, $dept_code, $ay_code);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // echo "<script>
            //         alert('already exists');
            //         window.location.href = 'contact_plot.php';
            //     </script>";
            $checkStmt->close();
        } else {
            $status = 'draft';
            $prof_sched_code = $prof_code . '_' . $ay_code;

            $sql = "INSERT INTO tbl_pcontact_schedstatus (prof_sched_code, prof_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?, ?)";
            // Initialize prepared statement
            if ($stmt = $conn->prepare($sql)) {
                // Bind parameters
                $stmt->bind_param("ssssss", $prof_sched_code, $prof_code, $semester, $dept_code, $status, $ay_code);
                if ($stmt->execute()) {
                    // echo "<script>
                    //     alert('Draft saved successfully.');
                    //     window.location.href = 'contact_plot.php';
                    // </script>";
                } else {
                    echo "Error: " . $stmt->error;
                }

                // Close statement
                $stmt->close();
            }
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

    echo "$prof_consultation_hrs";

    // Set consultation hours to zero if NULL
    if ($prof_consultation_hrs === NULL) {
        $prof_consultation_hrs = 0;
    }

    $fetch_consultation_hrs_query = "SELECT current_consultation_hrs FROM tbl_pcontact_counter WHERE prof_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
    $stmt_prof = $conn->prepare($fetch_consultation_hrs_query);
    $stmt_prof->bind_param("ssss", $prof_code, $semester, $ay_code, $dept_code);
    $stmt_prof->execute();
    $stmt_prof->bind_result($current_consultation_hrs);
    $stmt_prof->fetch();
    $stmt_prof->close();

    if ($current_consultation_hrs === NULL) {
        $current_consultation_hrs = 0;
    }

    // Initialize extension_hrs and research_hrs to zero if they are not defined
    $extension_hrs = isset($extension_hrs) ? $extension_hrs : 0;
    $research_hrs = isset($research_hrs) ? $research_hrs : 0;

    // Check if an entry in tbl_pcontact_counter exists
    $counter_check_sql = "SELECT COUNT(*) FROM tbl_pcontact_counter WHERE prof_sched_code = ? AND ay_code = ? AND semester = ? AND dept_code = ?";
    $stmt_counter_check = $conn->prepare($counter_check_sql);
    $stmt_counter_check->bind_param("ssss", $prof_sched_code, $ay_code, $semester, $dept_code);
    $stmt_counter_check->execute();
    $stmt_counter_check->bind_result($counter_exists);
    $stmt_counter_check->fetch();
    $stmt_counter_check->close();

    if ($counter_exists == 0) {
        $insert_counter_sql = "INSERT INTO tbl_pcontact_counter (dept_code, prof_code, prof_sched_code, current_consultation_hrs, consultation_hrs, semester, ay_code, extension_hrs, research_hrs) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_counter = $conn->prepare($insert_counter_sql);
        $stmt_insert_counter->bind_param("sssssssss", $dept_code, $prof_code, $prof_sched_code, $current_consultation_hrs, $prof_consultation_hrs, $semester, $ay_code, $extension_hrs, $research_hrs);

        if ($stmt_insert_counter->execute() === FALSE) {
            die("Error inserting into tbl_pcontact_counter: " . $stmt_insert_counter->error);
        }
        $stmt_insert_counter->close();
    } else {
        // Update consultation hours if prof_sched_code already exists
        $update_counter_sql = "UPDATE tbl_pcontact_counter SET consultation_hrs = ? WHERE prof_sched_code = ? AND ay_code = ? AND semester = ? AND dept_code = ?"; 
        $stmt_update_counter = $conn->prepare($update_counter_sql);
        $stmt_update_counter->bind_param("sssss", $consultation_hrs, $prof_sched_code, $ay_code, $semester, $dept_code);

        if ($stmt_update_counter->execute() === FALSE) {
            die("Error updating consultation hours in tbl_pcontact_counter: " . $stmt_update_counter->error);
        }
        $stmt_update_counter->close();
    }

    // Sanitize the table name
    $sanitized_prof_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $dept_code);
    $sanitized_academic_year = preg_replace("/[^a-zA-Z0-9_]/", "_", $ay_code);
    $table_name = "tbl_pcontact_sched_" . $sanitized_prof_code . "_" . $sanitized_academic_year;

    // Check if table exists
    $table_check_sql = "SHOW TABLES LIKE '$table_name'";
    $table_check_result = $conn->query($table_check_sql);

    if ($table_check_result->num_rows == 1) {
        // Table exists, redirect to plotSchedule.php
        $_SESSION['prof_sched_code'] = $prof_sched_code;
        $_SESSION['semester'] = $semester;
        $_SESSION['prof_code'] = $prof_code;
        $_SESSION['dept_code'] = $dept_code;
        $_SESSION['table_name'] = $table_name;
        header("Location: contact_plot.php");
        exit();
    } else {
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
            $_SESSION['prof_code'] = $prof_code;
            $_SESSION['dept_code'] = $dept_code;
            $_SESSION['table_name'] = $table_name;

            header("Location: contact_plot.php");
            exit();
        } else {
            echo "Error creating table: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Contact Hours Plotting</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="orig-logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/contact_plot.css">
</head>
<style>
    .filtering label {
        font-weight: bold;
        text-align: left;
    }
</style>

<body>

    <?php
    if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code == $user_college_code): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>
        <?php
    elseif ($_SESSION['user_type'] == 'CCL Head' && $admin_college_code == $user_college_code): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>
    <?php else: ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php");
        ?>
    <?php endif; ?>


    <div class="full-width-container mt-4">
        <form id="plot" action="" method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
            <div class="filtering ">
                <input type="hidden" id="prof_sched_code" name="prof_sched_code"
                    value="<?php echo htmlspecialchars($prof_sched_code); ?>" readonly>
                <div class="form-group col-md-3">
                    <label for="prof_code">Instructor Code:</label>
                    <input type="text" id="prof_code" name="prof_code"
                        value="<?php echo htmlspecialchars($display_text); ?>" readonly>
                </div>
                <div class="form-group col-md-3">
                    <label for="prof_name">Instructor Name:</label>
                    <?php
                    // Ensure that $prof_code is set before running this
                    if (isset($prof_code)) {
                        // Query to fetch professor name based on prof_code
                        $sql = "SELECT prof_name FROM tbl_prof WHERE prof_code = '$prof_code'";
                        $result = $conn->query($sql);

                        // Check if a result is found
                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $prof_name = $row['prof_name'];
                        } else {
                            $prof_name = 'No Professor Found';
                        }
                    } else {
                        // Handle the case where prof_code is not set
                        $prof_name = 'No Professor Code Set';
                    }
                    ?>
                    <input type="text" id="prof_name" name="prof_name"
                        value="<?php echo htmlspecialchars($prof_name); ?>" readonly>
                </div>

                <div class="form-group col-md-3">
                    <label for="acad_rank">Academic Rank:</label>
                    <input type="text" id="acad_rank" name="acad_rank"
                        value="<?php echo htmlspecialchars($academic_rank); ?>" readonly>
                </div>


                <div class="form-group col-md-3">
                    <label for="semester">Semester:</label>
                    <input type="text" id="semester" name="semester" value="<?php echo htmlspecialchars($semester); ?>"
                        readonly>
                </div>
                <?php
                if ($user_type === 'Department Secretary') {
                    $button_display = "style='display:inline;'";
                } else {
                    $button_display = "style='display:none;'";
                }
                ?>
<div class="form-group col-md-3 d-flex align-items-end" style="gap:10px;">

    <!-- Change Button -->
    <button type="button" id="btnchange" class="btn change-btn" name="create_table"
        data-bs-toggle="modal" data-bs-target="#createTableModal" title="Change" <?php echo $button_display; ?>>
        <i class="fas fa-sync-alt text-success"></i>
    </button>



    <?php
$sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);

// Default: button enabled
$button_disabled = "";

// Check if table exists and has matching entries
$check_query = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
$check_result = mysqli_query($conn, $check_query);

if ($check_result && mysqli_num_rows($check_result) > 0) {
    // Table exists, now check if there are matching schedules
    $schedule_query = "SELECT * FROM `$sanitized_pcontact_sched_code` 
        WHERE prof_code = '$prof_code' 
        AND semester = '$semester' 
        AND ay_code = '$ay_code' 
        AND dept_code = '$dept_code'";
    
    $schedule_result = mysqli_query($conn, $schedule_query);

    if (!$schedule_result || mysqli_num_rows($schedule_result) === 0) {
        $button_disabled = "disabled";
    }
} else {
    // Table does not exist, disable the button
    $button_disabled = "disabled";
}
?>

    <!-- Save Button -->
    <button type="button" id="complete" class="btn icon-btn" title="Save"
        data-bs-toggle="modal" data-bs-target="#completeConfirmationModal" 
            style="border: none; background: transparent;"

        <?php echo $button_disabled; ?>>
        <i class="fas fa-save text-primary"></i>
    </button>

<!-- Delete Button -->
<button type="button" id="delete" class="btn icon-btn" title="Delete"
    data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal" 
    style="border: none; background: transparent;"
    <?php echo $button_disabled; ?>>
    <i class="fas fa-trash-alt text-danger"></i>
</button>

<div id="dropdown" class="w-100" style="display: none;">
    <button class="btn dropdown-toggle" type="button" id="btndraft" data-bs-toggle="dropdown"
        aria-haspopup="true" aria-expanded="false">
        Save as
    </button>
    <div class="dropdown-menu" aria-labelledby="btndraft">
        <a class="dropdown-item" href="#" id="saveDraft">Draft</a>
        <a class="dropdown-item" href="#" id="complete">Complete</a>
        <a class="dropdown-item" href="#" id="delete">Delete</a>
    </div>
</div>


</div>




                <input type="hidden" id="status" value="<?php echo $schedule_status; ?>" readonly />

            </div>

            <script>
                // Fetch status value from the hidden input populated by PHP
                var status = document.getElementById('status').value;

                // Function to hide or show "Complete" and "Draft" buttons based on status
                function toggleButtonsBasedOnStatus(status) {
                    var completeButton = document.getElementById('complete');
                    var draftButton = document.getElementById('saveDraft');

                  
                    // Hide Draft button if status is "draft"
                    if (status === "draft") {
                        draftButton.style.display = 'none'; // Hide Draft button
                    } else {
                        draftButton.style.display = 'block'; // Show Draft button
                    }
                }
                // Call the function on page load with the current status
                toggleButtonsBasedOnStatus(status);
            </script>
            <div class="row">
                <div class="col-md-4" id="inputForms">
                    <div class="input-form mt-4">
                        <div class="form-group">
                            <label class="input-title" for="day">Day:</label>
                            <select id="day" name="day" required>
                                <option value="Monday" <?php echo (isset($day) && $day == 'Monday') ? 'selected' : ''; ?>>
                                    Monday</option>
                                <option value="Tuesday" <?php echo (isset($day) && $day == 'Tuesday') ? 'selected' : ''; ?>>Tuesday</option>
                                <option value="Wednesday" <?php echo (isset($day) && $day == 'Wednesday') ? 'selected' : ''; ?>>Wednesday</option>
                                <option value="Thursday" <?php echo (isset($day) && $day == 'Thursday') ? 'selected' : ''; ?>>Thursday</option>
                                <option value="Friday" <?php echo (isset($day) && $day == 'Friday') ? 'selected' : ''; ?>>
                                    Friday</option>
                                <option value="Saturday" <?php echo (isset($day) && $day == 'Saturday') ? 'selected' : ''; ?>>Saturday</option>
                            </select>
                        </div>
                        <label for="time_start">Duration:</label>
                        <div class="time-selection">
                            <select id="time_start" name="time_start" <?php echo $disabled; ?>>
                                <?php
                                for ($i = 7; $i <= 19; $i++) {
                                    for ($j = 0; $j < 60; $j += 30) {
                                        if ($i == 19 && $j > 0) {
                                            break;
                                        }
                                        $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":" . str_pad($j, 2, "0", STR_PAD_LEFT) . ":00";
                                        $time_12 = date("g:i A", strtotime($time_24));
                                        echo '<option value="' . $time_24 . '"' . (($time_start == $time_24) ? ' selected' : '') . '>' . $time_12 . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <label>-</label>
                            <select id="time_end" name="time_end" <?php echo $disabled; ?>>
                                <?php
                                for ($i = 7; $i <= 19; $i++) {
                                    for ($j = 0; $j < 60; $j += 30) {
                                        if ($i == 19 && $j > 0) {
                                            break;
                                        }

                                        $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":" . str_pad($j, 2, "0", STR_PAD_LEFT) . ":00";
                                        $time_12 = date("g:i A", strtotime($time_24));
                                        echo '<option value="' . $time_24 . '"' . (($time_end == $time_24) ? ' selected' : '') . '>' . $time_12 . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="input-title" for="contact-type">Contact Hours Type</label>
                            <select id="contact_type" name="consultation_hrs_type" class="form-control" required>
                                <option value="">Select Contact Hours Type</option>
                                <option value="Consultation Hours" <?php echo (isset($consultation_hrs_type) && $consultation_hrs_type == 'Consultation Hours') ? 'selected' : ''; ?>>Consultation
                                    Hours</option>
                                <option value="Research" <?php echo (isset($consultation_hrs_type) && $consultation_hrs_type == 'Research') ? 'selected' : ''; ?>>Research</option>
                                <option value="Extension" <?php echo (isset($consultation_hrs_type) && $consultation_hrs_type == 'Extension') ? 'selected' : ''; ?>>Extension</option>
                            </select>
                        </div>


                        <input type="hidden" id="schedule_id" name="sec_sched_id" value="">
                        <input type="hidden" id="dept_code" name="dept_code" value="">
                    </div>
                    <div class="btn w-100">
                        <input type="submit" id="plotScheduleBtn" name="plot_schedule" value="Plot Schedule">


                        <div class="btn-inline-group">
                            <form id="scheduleForm" method="post" action="contact_plot.php">
                                <!-- Hidden fields for schedule IDs and other data -->
                                <input type="hidden" id="prof_sched_code" name="prof_sched_code"
                                    value="<?php echo htmlspecialchars($prof_sched_code); ?>">
                                <input type="hidden" id="semester" name="semester"
                                    value="<?php echo htmlspecialchars($semester); ?>">
                                <input type="hidden" id="prof_code" name="prof_code"
                                    value="<?php echo htmlspecialchars($prof_code); ?>">
                                <input type="hidden" id="ay_code" name="ay_code"
                                    value="<?php echo htmlspecialchars($ay_code); ?>">
                                <input type="hidden" id="dept_code" name="dept_code"
                                    value="<?php echo htmlspecialchars($dept_code); ?>">
                                <input type="submit" id="updateScheduleBtn" name="update_schedule" style="display:none;"
                                    value="Update Schedule">
                                <input type="submit" id="deleteScheduleBtn" name="delete_schedule" style="display:none;"
                                    value="Delete Schedule">
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <?php
                    /// Retrieve session values or initialize them
                    $prof_sched_code = isset($_SESSION['prof_sched_code']) ? $_SESSION['prof_sched_code'] : '';
                    $semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
                    $prof_code = isset($_SESSION['prof_code']) ? $_SESSION['prof_code'] : '';
                    $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';

                    if ($_SERVER["REQUEST_METHOD"] == "POST") {
                        $_SESSION['prof_sched_code'] = isset($_SESSION['prof_sched_code']) ? $_SESSION['prof_sched_code'] : '';
                        $_SESSION['semester'] = $_SESSION['semester'];
                        $semester = $_SESSION['semester'];
                        $prof_code = $_SESSION['prof_code'];
                        $dept_code = $_SESSION['dept_code'];
                    }

                    // Fetch professor info
                    $sql_fetch_prof_info = "SELECT prof_code, ay_code, dept_code FROM tbl_pcontact_schedstatus WHERE prof_sched_code='$prof_sched_code'";
                    $result_prof_info = $conn->query($sql_fetch_prof_info);

                    if (!$result_prof_info) {
                        die("Error fetching professor info: " . $conn->error);
                    }

                    $schedule_data = [];

                    if ($result_prof_info->num_rows > 0) {
                        $row_prof_info = $result_prof_info->fetch_assoc();
                        $prof_code = $row_prof_info['prof_code'];
                        $ay_code = $row_prof_info['ay_code'];
                        $dept_code = isset($row_prof_info['dept_code']) ? $row_prof_info['dept_code'] : '';

                        // Sanitize table names
                        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
                        $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $dept_code . "_" . $ay_code);

                        // Check if tables exist
                        $table_exists = $conn->query("SHOW TABLES LIKE '$sanitized_pcontact_sched_code'")->num_rows > 0;
                        $prof_sched_table_exists = $conn->query("SHOW TABLES LIKE '$sanitized_prof_sched_code'")->num_rows > 0;

                        // Fetch from consultation schedule
                        if ($table_exists) {
                            $sql_fetch_schedule = "SELECT * FROM $sanitized_pcontact_sched_code WHERE semester='$semester' AND dept_code = '$dept_code' AND prof_sched_code = '$prof_sched_code'";
                            $result_schedule = $conn->query($sql_fetch_schedule);

                            if ($result_schedule->num_rows > 0) {
                                while ($row_schedule = $result_schedule->fetch_assoc()) {
                                    $day = $row_schedule['day'];
                                    $time_start = $row_schedule['time_start'];
                                    $time_end = $row_schedule['time_end'];
                                    $consultation_hrs_type = $row_schedule['consultation_hrs_type'];
                                    $sec_sched_id = $row_schedule['sec_sched_id'];
                                    $semester = $row_schedule['semester'];
                                    $dept_code = $row_schedule['dept_code'];

                                    if (!empty($consultation_hrs_type)) {
                                        if (!isset($schedule_data[$day])) {
                                            $schedule_data[$day] = [];
                                        }

                                        $schedule_data[$day][] = [
                                            'prof_sched_code' => $prof_sched_code,
                                            'sec_sched_id' => $sec_sched_id,
                                            'semester' => $semester,
                                            'day' => $day,
                                            'time_start' => $time_start,
                                            'time_end' => $time_end,
                                            'consultation_hrs_type' => $consultation_hrs_type,
                                            'dept_code' => $dept_code,
                                            'source' => 'consultation'
                                        ];
                                    }
                                }
                            }
                        }

                        // Fetch from professor schedule table
                        if ($prof_sched_table_exists) {
                            $sql_fetch_prof_schedule = "SELECT * FROM $sanitized_prof_sched_code WHERE semester = ? AND prof_sched_code = ?";
                            $stmt_prof_schedule = $conn->prepare($sql_fetch_prof_schedule);
                            $stmt_prof_schedule->bind_param("ss", $semester, $prof_sched_code);
                            $stmt_prof_schedule->execute();
                            $result_prof_schedule = $stmt_prof_schedule->get_result();

                            if ($result_prof_schedule->num_rows > 0) {
                                while ($row_prof_sched = $result_prof_schedule->fetch_assoc()) {
                                    $day = $row_prof_sched['day'];
                                    $time_start = $row_prof_sched['time_start'];
                                    $time_end = $row_prof_sched['time_end'];
                                    $course_code = $row_prof_sched['course_code'];
                                    $room_code = $row_prof_sched['room_code'];
                                    $section_sched_code = $row_prof_sched['section_code'];
                                    $class_type = $row_prof_sched['class_type'];

                                    // Format class type
                                    if ($class_type === "lec") {
                                        $class_type_display = "Lecture";
                                    } elseif ($class_type === "lab") {
                                        $class_type_display = "Laboratory";
                                    } else {
                                        $class_type_display = ucfirst($class_type);
                                    }

                                    // Fetch section code
                                    $section_code = 'N/A';
                                    $fetch_info_query = "SELECT section_code FROM tbl_secschedlist WHERE section_sched_code = ?";
                                    $stmt_section = $conn->prepare($fetch_info_query);
                                    $stmt_section->bind_param("s", $section_sched_code);
                                    $stmt_section->execute();
                                    $result = $stmt_section->get_result();
                                    if ($result->num_rows > 0) {
                                        $row = $result->fetch_assoc();
                                        $section_code = $row['section_code'];
                                    }

                                    // Fetch cell color
                                    $cell_color = '';
                                    $fetch_info_query = "SELECT cell_color FROM tbl_schedstatus WHERE section_sched_code = ? AND dept_code = ? AND semester = ?";
                                    $stmt_info = $conn->prepare($fetch_info_query);
                                    $stmt_info->bind_param("sss", $section_sched_code, $dept_code, $semester);
                                    $stmt_info->execute();
                                    $result_info = $stmt_info->get_result();
                                    if ($result_info->num_rows > 0) {
                                        $row_info = $result_info->fetch_assoc();
                                        $cell_color = $row_info['cell_color'];
                                    }

                                    if (!isset($schedule_data[$day])) {
                                        $schedule_data[$day] = [];
                                    }

                                    $schedule_data[$day][] = [
                                        'course_code' => $course_code,
                                        'room_code' => $room_code,
                                        'section_code' => $section_code,
                                        'day' => $day,
                                        'time_start' => $time_start,
                                        'time_end' => $time_end,
                                        'dept_code' => $dept_code,
                                        'class_type' => $class_type_display,
                                        'cell_color' => $cell_color,
                                        'non_clickable' => true,
                                        'source' => 'prof_schedule'
                                    ];
                                }
                            }
                        }

                        // Function to format time
                        function formatTime($time)
                        {
                            return date('h:i A', strtotime($time));
                        }

                        $html = '<div class="schedule-table-container">';
                        $html .= '<table class="table table-bordered schedule-table">';
                        $html .= '<thead><tr><th style="width: 12%;">Time</th>';

                        // Define column headers with equal width for each day
                        $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($day_names as $day_name) {
                            $html .= '<th style="width: 14.67%;">' . $day_name . '</th>';
                        }
                        $html .= '</tr></thead>';
                        $html .= '<tbody>';

                        for ($hour = 7; $hour <= 18; $hour++) {
                            for ($minute = 0; $minute < 60; $minute += 30) {
                                $start_time = sprintf("%02d:%02d", $hour, $minute);
                                $end_time = date('H:i', strtotime($start_time) + 1800);
                                $time_slots[] = [
                                    'start' => $start_time,
                                    'end' => $end_time,
                                ];
                            }
                        }

                        // Initialize the array to track the remaining rowspan for each column
                        $remaining_rowspan = array_fill_keys($day_names, 0);

                        foreach ($time_slots as $slot) {
                            $start_time = $slot['start'];
                            $end_time = $slot['end'];
                            $start_time_formatted = formatTime($start_time);
                            $end_time_formatted = formatTime($end_time);

                            $html .= '<tr>';
                            $html .= '<td class="time-slot">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

                            foreach ($day_names as $day_name) {
                                if ($remaining_rowspan[$day_name] > 0) {
                                    $remaining_rowspan[$day_name]--;
                                } else {
                                    $cell_content = '';
                                    $rowspan = 1;
                                    $cell_details = [];

                                    if (isset($schedule_data[$day_name])) {
                                        foreach ($schedule_data[$day_name] as $index => $schedule) {
                                            $schedule_start = strtotime($schedule['time_start']);
                                            $schedule_end = strtotime($schedule['time_end']);
                                            $current_start = strtotime($start_time);
                                            $current_end = strtotime($end_time);

                                            if (($current_start < $schedule_end && $current_end > $schedule_start)) {
                                                $consultation_hrs_type = isset($schedule['consultation_hrs_type']) ? $schedule['consultation_hrs_type'] : '';
                                                $section_code = isset($schedule['section_code']) ? $schedule['section_code'] : '';
                                                $non_clickable = isset($schedule['non_clickable']) ? 'non-clickable' : '';

                                                // Use isset() to check if keys exist before accessing them
                                                $course_code = isset($schedule['course_code']) ? $schedule['course_code'] : '';
                                                $class_type = isset($schedule['class_type']) ? $schedule['class_type'] : '';
                                                $room_code = isset($schedule['room_code']) ? $schedule['room_code'] : '';

                                                $cell_content = "<div style='display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; font-size: 12px; height: 100%; margin-top:-15px;'>
                                                <span style='font-weight: bold;'>{$course_code}</span>
                                                {$class_type}<br>";

                                                // Only display consultation_hrs_type if it's from the pcontact schedule
                                                if (!empty($schedule['consultation_hrs_type'])) {
                                                    $cell_content .= "{$schedule['consultation_hrs_type']}<br>";
                                                }

                                                $cell_content .= "{$room_code}<br>
                                                {$section_code}</div>";
                                                $intervals = ($schedule_end - $schedule_start) / 1800;
                                                $rowspan = max($intervals, 1);
                                                $cell_details = $schedule;

                                                unset($schedule_data[$day_name][$index]);
                                                $schedule_data[$day_name] = array_values($schedule_data[$day_name]);
                                                break;
                                            }
                                        }
                                    }

                                    if ($cell_content) {
                                        $html .= '<td class="shaded-cell ' . ($non_clickable ? 'non-clickable' : '') . ' centered-content" data-details=\'' . htmlspecialchars(json_encode($cell_details ?? [])) . '\' rowspan="' . $rowspan . '">' . $cell_content . '</td>';
                                        $remaining_rowspan[$day_name] = $rowspan - 1;
                                    } else {
                                        $html .= '<td class="free-slot"></td>';
                                    }
                                }
                            }
                            $html .= '</tr>';
                        }

                        $html .= '</tbody></table>';
                        $html .= '</div>';

                        if (empty($schedule_data)) {
                            echo '<div style="text-align: center; font-weight: bold; margin-top: 20px;">No Contact Hours Schedules found for the Selected Professor.</div>';
                        } else {
                            echo $html; // Only output the schedule table if there are schedules to show
                        }
                    } else {
                        echo '<div style="text-align: center; font-weight: bold; margin-top: 20px;">No schedule found for the professor.</div>';
                    }
                    ?>
                </div>
            </div>
    </div>

    <div class="modal fade" id="createTableModal" tabindex="-1" role="dialog" aria-labelledby="createTableModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTableModalLabel">Plot Schedule for Instructor Consultation Hours
                    </h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm" action="" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" id="prof_sched_code" name="prof_sched_code"
                                readonly required>
                            <input type="hidden" class="form-control" id="dept_code" name="dept_code" readonly required>
                        </div>
                        <div class="form-group">
                            <label for="ay_code">
                                <span>Academic Year:</span> <?php echo htmlspecialchars($ay_name); ?><br>
                                <span>Semester:</span> <?php echo htmlspecialchars($semester); ?>
                            </label>
                        </div>
                        <div class="form-group">
                            <input list="prof_code_list" class="form-control" id="prof_code" name="prof_code"
                                autocomplete="off" placeholder="Select or Type Instructor Name" required>
                            <datalist id="prof_code_list">
                                <option value="">Select Instructor Name</option>
                                <?php
                                // Fetch professor names
                                $sql = "SELECT prof_name, prof_code FROM tbl_prof WHERE dept_code = '$dept_code'";
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
                            <input type="hidden" id="ay_code" name="ay_code"
                                value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester"
                                value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="dept_code" name="dept_code"
                                value="<?php echo htmlspecialchars($dept_code); ?>" readonly>
                        </div>
                    
                        <button type="submit" name="create_table" class="btn" id="create">Plot Consulation
                            Hours</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Save as Draft Confirmation Modal -->
    <div class="modal fade" id="draftConfirmationModal" tabindex="-1" aria-labelledby="draftConfirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="draftConfirmationModalLabel">Confirm Save as Draft</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to save this schedule as draft?</p>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                    <button type="button" id="confirmDraftBtn" class="btn">Yes</button>
                    <button type="button" class="btn" id="btnNo" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successDraftModal" tabindex="-1" aria-labelledby="successDraftModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successDraftModalLabel">Success</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Schedule Saved as Draft Successfully.</p>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                    <button type="button" class="btn col-md-2" id="btnNo" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Refresh the page when the modal is closed
        const successDraftModal = document.getElementById('successDraftModal');
        successDraftModal.addEventListener('hidden.bs.modal', function () {
            location.reload(); // Reload the page
        });
    </script>


<div class="modal fade" id="completeConfirmationModal" tabindex="-1"
    aria-labelledby="completeConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST"> <!-- Form to trigger PHP code -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completeConfirmationModalLabel">Confirm Completion</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to save this schedule as complete?</p>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                    <!-- Hidden input to carry the 'public' status -->
                    <input type="hidden" name="status" value="public">
                    <button type="submit" id="confirmCompleteBtn" class="btn col-md-2">Yes</button>
                    <button type="button" class="btn col-md-2" id="btnNo" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </form>
    </div>
</div>



    <!-- Success Modal -->
    <div class="modal fade" id="completeSuccessModal" tabindex="-1" aria-labelledby="completeSuccessModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completeSuccessModalLabel">Success</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Schedule Saved as Completed Successfully.</p>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">

                    <button type="button" class="btn col-md-2" id="btnNo" data-bs-dismiss="modal">OK</button>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Refresh the page when the modal is closed
        const completeSuccessModal = document.getElementById('completeSuccessModal');
        completeSuccessModal.addEventListener('hidden.bs.modal', function () {
            location.reload(); // Reload the page
        });
    </script>



<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog"
    aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST"> <!-- Wrap modal content in form -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this?</p>
                    
                <input type="hidden" name="prof_sched_code" id="modalProfSchedCode" value="<?php echo htmlspecialchars($prof_sched_code); ?>">
                <input type="hidden" name="semester" id="modalSemester" value="<?php echo htmlspecialchars($semester); ?>">
                <input type="hidden" name="prof_code" id="modalProfCode" value="<?php echo htmlspecialchars($prof_code); ?>">

                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                    
                    <button type="submit" name="delete_sched" id="confirmDeleteBtn" class="btn btn-danger col-md-2">Yes</button>
                    <button type="button" class="btn btn-secondary col-md-2" data-bs-dismiss="modal">No</button>
                </div>
            </div>
        </form>
    </div>
</div>

    

    <!-- Delete Success Modal -->
    <div class="modal fade" id="deleteSuccessModal" tabindex="-1" role="dialog"
        aria-labelledby="deleteSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSuccessModalLabel">Success</h5>
                    <button type="button" class="close ms-auto" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Professor Contact Hours Schedule Deleted Successfully.</p>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">

                    <button type="button" class="btn col-md-2" id="okBtn">OK</button>
                </div>
            </div>
        </div>
    </div>



    <!-- Bootstrap Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                <p id="successMessage"></p>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['modal_message'])): ?>
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = "<?php echo $_SESSION['modal_message']; ?>";
                modal.show();
                <?php unset($_SESSION['modal_message']); ?>
            <?php endif; ?>
        });
    </script>


    <script>
        document.getElementById('saveDraft').addEventListener('click', function () {
            $('#draftConfirmationModal').modal('show');
        });

        document.getElementById('confirmDraftBtn').addEventListener('click', function () {
            $.ajax({
                url: 'contact_plot.php',
                type: 'POST',
                data: {
                    save_as_draft: true,
                    prof_sched_code: "<?php echo htmlspecialchars($prof_sched_code); ?>",
                    semester: "<?php echo htmlspecialchars($semester); ?>"
                },
                success: function (response) {
                    $('#draftConfirmationModal').modal('hide');
                    $('#successDraftModal').modal('show'); // Show the success modal
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error saving schedule as draft.');
                }
            });
        });


        document.getElementById('complete').addEventListener('click', function () {
            $('#completeConfirmationModal').modal('show');
        });

        document.getElementById('confirmCompleteBtn').addEventListener('click', function () {
            $.ajax({
                url: 'contact_plot.php',
                type: 'POST',
                data: {
                    status: 'public'
                },
                success: function (response) {
                    $('#completeConfirmationModal').modal('hide'); // Hide the confirmation modal
                    $('#completeSuccessModal').modal('show');
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error updating schedule status.');
                }
            });
        });

        // Show the delete confirmation modal when "Delete" is clicked
        document.getElementById('delete').addEventListener('click', function () {
            $('#deleteConfirmationModal').modal('show');
        });

        // Handle the deletion when the user confirms it
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const consultation_hrs_type = document.getElementById('contact_type').value;
            const dept_code = "<?php echo $dept_code; ?>";
            const prof_code = "<?php echo $prof_code; ?>";

            // Perform the AJAX request to delete the schedule
            $.ajax({
                url: 'contact_plot.php',
                type: 'POST',
                data: {
                    delete_sched: true,
                    consultation_hrs_type: consultation_hrs_type,
                    prof_sched_code: "<?php echo htmlspecialchars($prof_sched_code); ?>",
                    semester: "<?php echo htmlspecialchars($semester); ?>",
                    dept_code: dept_code,
                    prof_code: prof_code
                },
                success: function (response) {
                    $('#deleteConfirmationModal').modal('hide');
                    $('#deleteSuccessModal').modal('show');
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error deleting schedule.');
                }
            });
        });

        // Redirect to contact_plot.php when the "OK" button in the success modal is clicked
        document.getElementById('okBtn').addEventListener('click', function () {
            window.location.href = 'contact_plot.php';
        });
        document.addEventListener("DOMContentLoaded", function () {
            // Add click event listeners to all table cells with class "shaded-cell"
            document.querySelectorAll('.shaded-cell').forEach(function (cell) {
                cell.addEventListener('click', function (event) {
                    // Ignore clicks on non-clickable cells
                    if (cell.classList.contains('non-clickable')) {
                        return;
                    }

                    // Extract the details from the clicked cell (data-* attributes)
                    var details = JSON.parse(this.dataset.details);

                    // Populate form fields with the extracted details
                    document.getElementById('day').value = details.day;
                    document.getElementById('time_start').value = details.time_start;
                    document.getElementById('time_end').value = details.time_end;
                    document.getElementById('contact_type').value = details.consultation_hrs_type;

                    // Set the hidden input with the schedule_id value
                    document.getElementById('schedule_id').value = details.sec_sched_id;

                    // Update button visibility
                    document.getElementById('plotScheduleBtn').style.display = 'none';
                    document.getElementById('updateScheduleBtn').style.display = 'inline-block';
                    document.getElementById('deleteScheduleBtn').style.display = 'inline-block';

                    // Show the input form
                    document.getElementById('inputForms').style.display = 'block';

                    // Stop event propagation to prevent triggering the document click listener
                    event.stopPropagation();
                });
            });

            // Hide input forms when clicking anywhere outside the forms, but not on the forms themselves
            document.addEventListener('click', function (event) {
                const inputForms = document.getElementById('inputForms'); // Adjust to your form's container ID

                // Check if the click target is outside the form
                if (!inputForms.contains(event.target)) {
                    // Reset form fields to default state
                    document.getElementById('day').value = "Monday"; // Default to the first day
                    document.getElementById('time_start').value = "07:00:00"; // Default to the earliest time
                    document.getElementById('time_end').value = "07:00:00"; // Default to a reasonable end time
                    document.getElementById('contact_type').value = ""; // Reset the contact type
                    document.getElementById('schedule_id').value = ""; // Clear the hidden schedule ID

                    // Reset button visibility
                    document.getElementById('plotScheduleBtn').style.display = 'inline-block';
                    document.getElementById('updateScheduleBtn').style.display = 'none';
                    document.getElementById('deleteScheduleBtn').style.display = 'none';
                }
            });

            // Prevent clicks inside the form from propagating to the document click listener
            const inputForms = document.getElementById('inputForms');
            inputForms.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });
    </script>
</body>

</html>