<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : 'Unknown';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';


if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}


// Ensure the user is logged in as a Department Secretary or Department Chairperson
if (
    !isset($_SESSION['user_type']) ||
    ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'Department Chairperson')
) {
    header("Location: ../../login/login.php");
    exit();
}


$fetch_info_query = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = '$college_code' AND active = '1'";
$result = $conn->query($fetch_info_query);

$active_ay_code = null;
$active_semester = null;

// Check if query executed successfully and returned rows
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $active_ay_code = $row['ay_code'];
    $active_semester = $row['semester'];
}

// Set the ay_code and semester based on session or active values from the query
$ay_code = $_POST['search_ay'] ?? $active_ay_code;
$semester = $_POST['search_semester'] ?? $active_semester;

// Handle Academic Year options
$ay_options = [];
$sql_ay = "SELECT DISTINCT ay_code, ay_name FROM tbl_ay"; // Fetch both ay_code and ay_name
$result_ay = $conn->query($sql_ay);

if ($result_ay->num_rows > 0) {
    while ($row_ay = $result_ay->fetch_assoc()) {
        // Store both ay_code and ay_name in the options array
        $ay_options[] = [
            'ay_code' => $row_ay['ay_code'],
            'ay_name' => $row_ay['ay_name']
        ];
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_ay'])) {
    $selected_ay_code = $_POST['search_ay']; // Get selected ay_code from the POST request
    $_SESSION['selected_ay_code'] = $selected_ay_code; // Store in session
} else {
    // If the page is refreshed, reset to active_ay_code
    $selected_ay_code = $active_ay_code;
}

// Debugging output
// echo "Selected AY Code: " . htmlspecialchars($selected_ay_code) . "<br>";

// Fetch the ay_name based on selected ay_code
if (!empty($selected_ay_code)) {
    $sql_ay_name = "SELECT ay_name FROM tbl_ay WHERE ay_code = ?";
    $stmt = $conn->prepare($sql_ay_name);
    $stmt->bind_param("s", $selected_ay_code);
    $stmt->execute();
    $result_ay_name = $stmt->get_result();

    if ($result_ay_name->num_rows > 0) {
        $row_ay_name = $result_ay_name->fetch_assoc();
        $selected_ay_name = $row_ay_name['ay_name']; // Get the ay_name based on ay_code
    } else {
        $selected_ay_name = 'Not Found'; // Fallback if no ay_name found for selected ay_code
    }

    $stmt->close();
} else {
    $selected_ay_name = 'Select Year'; // Default fallback
}


// Handle the Semester selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_semester'])) {
    $selected_semester = $_POST['search_semester'];
} else {
    // Default fallback if session value is not set
    $selected_semester = $active_semester; // or any other default value
}

if (isset($_SESSION['user_type'])) {
    $user_type = $_SESSION['user_type']; // Or fetch from database if needed
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}

$fetch_info_query = "SELECT reg_adviser, college_code, dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];
    $dept_code = $row['dept_code'];

    $_SESSION['dept_code'] = $dept_code; // Set dept_code in session

    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser";
    } else {
        $current_user_type = $user_type;
    }
}


if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}


// Handle POST requests for Edit of Schedule
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
        $_SESSION['section_sched_code'] = $_POST['section_sched_code'];
        $_SESSION['section_code'] = $_POST['section_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $_SESSION['prof_code'] = $_POST['prof_code'];
        header("Location: ../create_sched/plotSchedule.php");
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Get the form data
    $sender_dept_code = $_SESSION['dept_code'];
    $sender_email = $_SESSION['cvsu_email'];
    $receiver_dept_code = htmlspecialchars($_POST['receiver_dept_code']);
    $receiver_email = htmlspecialchars($_POST['recipient_email']);
    $semester = htmlspecialchars($_POST['modalSemester']);
    $section_code = $_POST['modalSectionCode'];
    $shared_section_code = htmlspecialchars(str_replace('-', '_', $_POST['modalSectionCode']), ENT_QUOTES, 'UTF-8');
    $ay_code = htmlspecialchars(str_replace('-', '_', $_POST['modalAyCode']), ENT_QUOTES, 'UTF-8');
    $sec_sched_code = $shared_section_code . '_' . $ay_code;

    if ($sender_email === $receiver_email) {
        echo "<script>
                alert('You cannot share a schedule with yourself.');
                window.location.href='lib_section.php';
              </script>";
        exit();
    }

    $check_sql = "SELECT COUNT(*) FROM tbl_shared_sched 
                  WHERE sender_dept_code = ? AND sender_email = ? AND receiver_dept_code = ? AND receiver_email = ? 
                  AND shared_section = ? AND section_code = ? AND semester = ? AND ay_code = ?";

    if ($check_stmt = $conn->prepare($check_sql)) {
        $check_stmt->bind_param("ssssssss", $sender_dept_code, $sender_email, $receiver_dept_code, $receiver_email, $sec_sched_code, $section_code, $semester, $ay_code);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        // Inside your existing code where you check if the schedule has been shared
        if ($count > 0) {
            // Set a session variable to indicate the modal should be shown
            $_SESSION['show_schedule_shared_modal'] = true;
            // Redirect to the same page (or wherever necessary)
            header('Location: lib_section.php');
            exit();
        } else {
            $share_status = 'active';
            $sql = "INSERT INTO tbl_shared_sched 
                    (sender_dept_code, sender_email, receiver_dept_code, receiver_email, shared_section, section_code, semester, ay_code,status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssssss", $sender_dept_code, $sender_email, $receiver_dept_code, $receiver_email, $sec_sched_code, $section_code, $semester, $ay_code, $share_status);

                if ($stmt->execute()) {
                    $message = "You have received a schedule for section " . $section_code . " for the " . $semester . " from " . $sender_email;
                    $notification_sql = "INSERT INTO tbl_notifications (section_code, semester, sender_email, receiver_email, message, date_sent,ay_code) 
                                         VALUES (?, ?, ?, ?, ?, NOW(),?)";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bind_param('ssssss', $section_code, $semester, $sender_email, $receiver_email, $message, $ay_code);
                    $notification_stmt->execute();
                    $notification_stmt->close();

                    $_SESSION['show_schedule_not_shared_modal'] = true;
                    // Redirect to the same page (or wherever necessary)
                    header('Location: lib_section.php');
                    exit();


                    $stmt->close();
                } else {
                    echo "<script>
                        alert('Error preparing the statement: " . $conn->error . "');
                      </script>";
                }
            }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {


    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $section_sched_code = $_POST['section_sched_code'];
    $semester = $_POST['semester'];
    $section_code = $_POST['section_code'];
    $dept_code = $_SESSION['dept_code'];
    $ay_code = $_SESSION['ay_code'];
    $sanitized_room_sched_code = null;
    $sanitized_prof_sched_code = null;

    $sql_program_code = "SELECT section_code  FROM tbl_secschedlist WHERE section_sched_code = ?";
    $stmt_program_code = $conn->prepare($sql_program_code);
    $stmt_program_code->bind_param("s", $section_sched_code);
    $stmt_program_code->execute();
    $stmt_program_code->bind_result($section_code);
    $stmt_program_code->fetch();
    $stmt_program_code->close();
    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

    // Fetch data from section schedule table
    $sql = "SELECT * FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $section_sched_code, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sec_sched_id = $row['sec_sched_id'];
            $room_code = $row['room_code'];
            $prof_code = $row['prof_code'];
            $class_type = $row['class_type'];
            $curriculum = $row['curriculum'] ?? '';
            $section_dept_code = $row['dept_code'];
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

            $room_sched_code = $room_code . "_" . $ay_code;
            $prof_sched_code = $prof_code . "_" . $ay_code;

            // Fetch details about shared schedules
            $sql_secsched = "SELECT shared_sched, shared_to, course_code FROM $sanitized_section_sched_code WHERE sec_sched_id = ? AND section_sched_code = ?";
            $stmt = $conn->prepare($sql_secsched);

            if ($stmt) {
                $stmt->bind_param("ss", $sec_sched_id, $section_sched_code); // Assuming sec_sched_id is an integer
                $stmt->execute();
                $result_secsched = $stmt->get_result();

                if ($row_secsched = $result_secsched->fetch_assoc()) {
                    $row_shared_sched = $row_secsched['shared_sched'];
                    $row_shared_to = $row_secsched['shared_to'];
                    $course_code = $row_secsched['course_code'];

                    // Retrieve department code based on shared email
                    $sql_dept = "SELECT dept_code FROM tbl_prof_acc WHERE cvsu_email = ?";
                    $stmt_dept = $conn->prepare($sql_dept);

                    if ($stmt_dept) {
                        $stmt_dept->bind_param("s", $row_shared_to);
                        $stmt_dept->execute();
                        $result_dept = $stmt_dept->get_result();

                        if ($result_dept->num_rows > 0) {
                            $row_dept = $result_dept->fetch_assoc();
                            $row_shared_dept_code = $row_dept['dept_code'];
                        } else {
                            //     echo "<script>
                            //     alert('No matching department found for the provided email.');
                            //     window.location.href='lib_section.php';
                            //   </script>";
                        }

                        $stmt_dept->close();
                    } else {
                        echo '<script>
                        alert("Error preparing department query: ' . $conn->error . '");
                        window.location.href="lib_section.php";
                      </script>';
                    }
                }
                $stmt->close();
            } else {
                echo '<script>
                alert("Error preparing section schedule query: ' . $conn->error . '");
                window.location.href="lib_section.php";
              </script>';
            }

            if (empty($row_shared_sched)) {
                // If no shared schedule is defined
                $RMdepartment = $dept_code;
                $PFdepartment = $dept_code;
            }
            if ($row_shared_sched === "room") {
                // If the shared schedule is for rooms
                $RMdepartment = $row_shared_dept_code;
                $PFdepartment = $dept_code;
            }

            // if ($row_shared_sched === "prof") {
            //     // If the shared schedule is for professors
            //     $RMdepartment = $dept_code;
            //     $PFdepartment = $row_shared_dept_code;
            // }

            if (empty($row_shared_sched) && ($section_dept_code != $dept_code)) {
                // If no shared schedule is defined
                $RMdepartment = $section_dept_code;
                $PFdepartment = $section_dept_code;
                // echo "empty section";
            }

            // // Output results
            // echo $RMdepartment;
            // echo $PFdepartment;
            // echo $row_shared_sched;
            // echo $row_shared_to;
            // echo $sanitized_section_sched_code;

            // Delete from section schedule table
            $sql_delete_section = "DELETE FROM $sanitized_section_sched_code WHERE sec_sched_id = ? AND semester = ?";
            $stmt_delete_section = $conn->prepare($sql_delete_section);
            $stmt_delete_section->bind_param('ss', $sec_sched_id, $semester);
            if (!$stmt_delete_section->execute()) {
                echo '<script>
                alert("Error deleting from section schedule:  ' . $stmt_delete_section->error . '");
                window.location.href="lib_section.php";
              </script>';
            }
            $stmt_delete_section->close();


            if ($room_code !== 'TBA') {
                // Define possible sanitized room schedule codes
                $sanitized_room_sched_codes = [
                    "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}"),
                    "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}")
                ];

                $room_sched_code = $room_code . "_" . $ay_code;

                foreach ($sanitized_room_sched_codes as $sanitized_room_sched_code) {
                    // Check if the table exists before proceeding
                    $sql_check_table = "SHOW TABLES LIKE '$sanitized_room_sched_code'";
                    $result_check_table = $conn->query($sql_check_table);

                    if ($result_check_table && $result_check_table->num_rows > 0) {
                        // Fetch and delete from the room schedule table
                        $sql_room = "SELECT * FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                        $stmt_room = $conn->prepare($sql_room);
                        $stmt_room->bind_param('ssss', $room_sched_code, $semester, $sec_sched_id, $PFdepartment);
                        $stmt_room->execute();
                        $result_room = $stmt_room->get_result();
                        echo $sanitized_room_sched_code;
                        if ($result_room->num_rows > 0) {
                            // Delete the room schedule
                            $sql_delete_room = "DELETE FROM $sanitized_room_sched_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                            $stmt_delete_room = $conn->prepare($sql_delete_room);
                            $stmt_delete_room->bind_param('ssss', $sec_sched_id, $semester, $PFdepartment, $section_sched_code);

                            if ($stmt_delete_room->execute()) {
                                echo "<script>
                                alert('Room schedule record deleted successfully from $sanitized_room_sched_code.');
                                window.location.href='lib_section.php';
                              </script>";
                            } else {
                                // echo "Error deleting room schedule record: " . $stmt_delete_room->error . "<br>";
                            }
                            $stmt_delete_room->close();

                            // Check if the room schedule table is empty
                            $sql_check_empty_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_check_empty_room = $conn->prepare($sql_check_empty_room);
                            $stmt_check_empty_room->bind_param('sss', $room_sched_code, $semester, $PFdepartment);
                            if ($stmt_check_empty_room->execute()) {
                                $result_check_empty_room = $stmt_check_empty_room->get_result();
                                $row_count_room = $result_check_empty_room->fetch_assoc()['row_count'];
                                $stmt_check_empty_room->close();

                                // If no remaining room schedules, delete the room schedule status entry
                                if ($row_count_room == 0) {
                                    $sql_delete_room_status = "DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                                    $stmt_delete_room_status = $conn->prepare($sql_delete_room_status);
                                    $stmt_delete_room_status->bind_param('ssss', $room_sched_code, $semester, $ay_code, $PFdepartment);
                                    if ($stmt_delete_room_status->execute()) {
                                        // echo "Room schedule status deleted successfully for $room_sched_code.<br>";
                                    } else {
                                        echo "Error deleting from tbl_room_schedstatus: " . $stmt_delete_room_status->error . "<br>";
                                    }
                                    $stmt_delete_room_status->close();
                                }
                            } else {
                                echo "Error checking room schedule table: " . $stmt_check_empty_room->error . "<br>";
                            }
                        } else {
                            echo "No matching records found in table: $sanitized_room_sched_code<br>";
                        }
                        $stmt_room->close();
                    } else {
                        echo "Table $sanitized_room_sched_code does not exist. Skipping.<br>";
                    }
                }
            }


            // Delete from professor schedule table
            if ($sanitized_prof_sched_code) {
                if ($prof_code !== 'TBA') {


                    $fetch_hours_sql = "SELECT TIME_TO_SEC(TIMEDIFF(time_end, time_start)) / 3600 AS total_hours, course_code 
                                    FROM $sanitized_prof_sched_code 
                                    WHERE prof_code = ? AND semester = ? AND sec_sched_id = ? AND ay_code = ?";
                    $stmt_fetch_hours = $conn->prepare($fetch_hours_sql);
                    if (!$stmt_fetch_hours) {
                        die("Prepare failed: " . $conn->error);
                    }
                    $stmt_fetch_hours->bind_param("ssss", $prof_code, $semester, $sec_sched_id, $ay_code);
                    $stmt_fetch_hours->execute();
                    $stmt_fetch_hours->bind_result($total_hours, $course_code);

                    if ($stmt_fetch_hours->fetch()) {
                        echo "Fetched Total Hours: $total_hours<br>";
                    } else {
                        echo "Error: Unable to fetch total hours. Please check the query or parameters.<br>";
                    }
                    $stmt_fetch_hours->close();

                    // echo "Fetched Prof Sched: $sanitized_prof_sched_code<br>";
                    // echo "Fetched Prof Sched Semester: $semester<br>";
                    // echo "Fetched Prof Sched Prof Code: $prof_code<br>";
                    // echo "Fetched Prof Sched Course Code: $course_code<br>";
                    // echo "Fetched Prof Sched Total Hours: $total_hours<br>";


                    $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);

                    // echo " <br>Santized prof sched: $sanitized_prof_sched_code<br>";
                    $prof_sched_code = $prof_code . "_" . $ay_code;

                    // Fetch and delete from professor schedule table
                    $sql_prof = " SELECT * FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                    $stmt_prof = $conn->prepare($sql_prof);
                    $stmt_prof->bind_param('ssss', $prof_sched_code, $semester, $sec_sched_id, $dept_code);
                    $stmt_prof->execute();
                    $result_prof = $stmt_prof->get_result();


                    // echo "prof_sched_code: " . $prof_sched_code . "<br>";
                    // echo "sec_sched_id: " . $sec_sched_id . "<br>";
                    // echo "semester: " . $semester . "<br>";
                    // echo "dept_code: " . $dept_code . "<br>";

                    if ($result_prof->num_rows > 0) {

                        $fetch_prof_hours_query = "SELECT * FROM tbl_psched_counter WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_fetch_prof_hours = $conn->prepare($fetch_prof_hours_query);
                        $stmt_fetch_prof_hours->bind_param("sss", $prof_sched_code, $semester, $dept_code);
                        $stmt_fetch_prof_hours->execute();
                        $prof_hours_result = $stmt_fetch_prof_hours->get_result();

                        if ($prof_hours_result->num_rows > 0) {
                            $prof_hours_row = $prof_hours_result->fetch_assoc();
                            $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                            $prep_hours = $prof_hours_row['prep_hrs'];
                            echo "Prep: $prep_hours";

                            // Calculate new teaching hours and consultation hours
                            $new_teaching_hours = $current_teaching_hours - $total_hours;
                            $consultation_hrs = $new_teaching_hours / 3;

                            // echo "Current Teaching Hours: $current_teaching_hours<br>";
                            // echo "Hours To Deduct: $total_hours<br>";
                            // echo "New Teaching Hours: $new_teaching_hours<br>";
                            // echo "Consultation Hours: $consultation_hrs<br>";

                            // Update query to set both teaching_hrs and consultation_hrs
                            // Update query to set both teaching_hrs and consultation_hrs
                            $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = ?, consultation_hrs = ? WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_update_hours = $conn->prepare($update_hours_query);
                            $stmt_update_hours->bind_param("dssss", $new_teaching_hours, $consultation_hrs, $prof_sched_code, $semester, $dept_code);

                            if ($stmt_update_hours->execute()) {
                                echo "Teaching hours and consultation hours updated successfully.<br>";
                            } else {
                                echo "Error updating teaching hours: " . $stmt_update_hours->error . "<br>";
                            }
                        } else {
                            die("Error: Instructor details not found.");
                        }

                        // Check if a schedule entry exists for the professor after deletion
                        $check_query = "SELECT * FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND course_code = ? AND semester = ? AND curriculum = ? AND class_type = ?";
                        $stmt_check = $conn->prepare($check_query);
                        $stmt_check->bind_param("sssss", $prof_sched_code, $course_code, $semester, $curriculum, $class_type);
                        $stmt_check->execute();
                        $check_result = $stmt_check->get_result();

                        if ($check_result->num_rows === 0) {
                            // If no matching schedule entry is found, decrement prep_hours
                            $prep_hours -= 1;

                            // Update the prep_hours in the database
                            $update_prep_hours_query = "UPDATE tbl_psched_counter SET prep_hrs = ? WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_update_prep_hours = $conn->prepare($update_prep_hours_query);
                            $stmt_update_prep_hours->bind_param("dsss", $prep_hours, $prof_sched_code, $semester, $dept_code);

                            if ($stmt_update_prep_hours->execute()) {
                                // echo "Prep hours updated successfully.<br>";
                            } else {
                                echo "Error updating prep hours: " . $stmt_update_prep_hours->error . "<br>";
                            }
                        } else {
                            // echo "Matching schedule entry exists, prep_hours remains the same.<br>";
                        }

                        $sql_prof_unit = "SELECT prof_unit FROM tbl_prof WHERE prof_code = ?";
                        $stmt_prof_unit = $conn->prepare($sql_prof_unit);
                        $stmt_prof_unit->bind_param('s', $prof_code);
                        $stmt_prof_unit->execute();
                        $result_prof_unit = $stmt_prof_unit->get_result();
                        $prof_unit_row = $result_prof_unit->fetch_assoc();
                        $prof_unit = $prof_unit_row['prof_unit'];
                        $stmt_prof_unit->close();

                        $course_counter_update_query = " UPDATE tbl_assigned_course  SET course_counter = course_counter - 1 WHERE prof_code = ? AND course_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_course_counter = $conn->prepare($course_counter_update_query);
                        $stmt_course_counter->bind_param('ssss', $prof_code, $course_code, $semester, $dept_code);
                        if ($stmt_course_counter->execute()) {
                            echo "Course counter updated successfully.<br>";
                        } else {
                            echo "Error updating course counter: " . $stmt_course_counter->error . "<br>";
                        }
                        $stmt_course_counter->close();

                        //prof delete here
                        $sql_delete_prof = " DELETE FROM $sanitized_prof_sched_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                        $stmt_delete_prof = $conn->prepare($sql_delete_prof);
                        $stmt_delete_prof->bind_param('ssss', $sec_sched_id, $semester, $dept_code, $section_sched_code);
                        if ($stmt_delete_prof->execute()) {
                            echo "Instructor schedule record deleted successfully.<br>";
                        } else {
                            echo "Error deleting Instructor schedule record: " . $stmt_delete_prof->error . "<br>";
                        }


                        $stmt_delete_prof->close();
                        // Check if Instructor schedule table is empty
                        $sql_check_empty_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_sched_code ";
                        $stmt_check_empty_prof = $conn->prepare($sql_check_empty_prof);
                        $stmt_check_empty_prof->execute();
                        $result_check_empty_prof = $stmt_check_empty_prof->get_result();
                        $row_count_prof = $result_check_empty_prof->fetch_assoc()['row_count'];
                        $stmt_check_empty_prof->close();

                        if ($row_count_prof == 0) {
                            // Delete from tbl_psched
                            $sql_delete_schedlist = "DELETE FROM tbl_psched WHERE prof_sched_code = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_schedlist = $conn->prepare($sql_delete_schedlist);
                            $stmt_delete_schedlist->bind_param('sss', $prof_sched_code, $ay_code, $dept_code);
                            $stmt_delete_schedlist->execute();
                            $stmt_delete_schedlist->close();

                            // Delete from tbl_prof_schedstatus
                            $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $ay_code);
                            $stmt_delete_schedstatus->execute();
                            $stmt_delete_schedstatus->close();
                        }
                    } else {
                        // echo "No Instructors schedule records found.<br>";
                    }
                    $stmt_prof->close();
                }
            }
        }
    }

    // Delete from tbl_schedstatus
    $sql_delete_schedstatus = "DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
    $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
    $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
    if (!$stmt_delete_schedstatus->execute()) {
        echo "Error deleting from tbl_schedstatus: " . $stmt_delete_schedstatus->error;
    }
    $stmt_delete_schedstatus->close();

    // Check if the section schedule table is empty
    $sql_check_empty_section = "SELECT COUNT(*) AS row_count FROM $sanitized_section_sched_code";
    $stmt_check_empty_section = $conn->prepare($sql_check_empty_section);
    if (!$stmt_check_empty_section->execute()) {
        echo "Error checking section schedule table: " . $stmt_check_empty_section->error;
    } else {
        $result_check_empty_section = $stmt_check_empty_section->get_result();
        $row_count_section = $result_check_empty_section->fetch_assoc()['row_count'];
        $stmt_check_empty_section->close();
    }

    // Check and delete from tbl_shared_sched if applicable
    $sql_check_shared_sched = "SELECT COUNT(*) AS row_count FROM tbl_shared_sched WHERE shared_section = ? AND semester = ? AND ay_code = ?";
    $stmt_check_shared_sched = $conn->prepare($sql_check_shared_sched);
    $stmt_check_shared_sched->bind_param('sss', $section_sched_code, $semester, $ay_code);
    $stmt_check_shared_sched->execute();
    $result_check_shared_sched = $stmt_check_shared_sched->get_result();
    $row_count_shared = $result_check_shared_sched->fetch_assoc()['row_count'];
    $stmt_check_shared_sched->close();

    // If entries exist, delete them
    if ($row_count_shared > 0) {
        $sql_delete_shared_sched = "DELETE FROM tbl_shared_sched WHERE shared_section = ? AND semester = ? AND ay_code = ?";
        $stmt_delete_shared_sched = $conn->prepare($sql_delete_shared_sched);
        $stmt_delete_shared_sched->bind_param('sss', $section_sched_code, $semester, $ay_code);
        if (!$stmt_delete_shared_sched->execute()) {
            echo "Error deleting from tbl_shared_sched: " . $stmt_delete_shared_sched->error;
        }
        $stmt_delete_shared_sched->close();
    }

    echo "Schedule deleted successfully.";
    header("Location: lib_section.php");
    exit();
}

// Fetch the schedules based on filtering criteria
$sql = "
    SELECT tbl_schedstatus.section_sched_code, tbl_schedstatus.semester, tbl_schedstatus.dept_code, tbl_schedstatus.ay_code, tbl_secschedlist.section_code 
    FROM tbl_schedstatus 
    INNER JOIN tbl_secschedlist 
    ON tbl_schedstatus.section_sched_code = tbl_secschedlist.section_sched_code 
    WHERE tbl_schedstatus.status IN ('draft', 'public', 'private') 
    AND tbl_schedstatus.ay_code = ? -- Ensure this uses the correct AY
    AND tbl_schedstatus.semester = ? 
    AND tbl_secschedlist.section_code COLLATE utf8mb4_general_ci LIKE ?
    AND tbl_schedstatus.dept_code = ?";

$search_section = isset($_POST['search_section']) ? '%' . $_POST['search_section'] . '%' : '%';
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $ay_code, $semester, $search_section, $dept_code);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['section_id'])) {
    $section_id = $_GET['section_id'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    // $selected_ay_code = $_SESSION['selected_ay_code'];

    // Fetch the section schedule code based on the section_id, semester, and selected academic year
    $sql = "SELECT * FROM tbl_secschedlist WHERE section_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("sss", $section_id, $dept_code, $ay_code);

    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_sched_code = $row['section_sched_code'];
        echo "<script>document.getElementById('scheduleModalLabel').innerHTML = 'Schedule for Section: " . htmlspecialchars($section_id) . "';</script>";
        echo fetchScheduleForSec($section_sched_code, $ay_code, $semester);
    } else {
        echo "<p>No schedule found for this Section.</p>";
        //         echo "<pre>";
        // echo "Debugging: Query returned no results!<br>";
        // echo "AY Code Used: " . htmlspecialchars($selected_ay_code) . "<br>";
        // echo "Section ID Used: " . htmlspecialchars($section_id) . "<br>";
        // echo "Department Code Used: " . htmlspecialchars($dept_code) . "<br>";
        // echo "</pre>";
    }

    $stmt->close();
    $conn->close();
    exit;
}

function fetchScheduleForSec($section_sched_code, $ay_code, $semester)
{
    global $conn;


    // Fetch section information along with dept_name from tbl_secschedlist and tbl_department
    $sql_fetch_section_info = "SELECT s.section_code, s.program_code, s.ay_code, s.dept_code, d.dept_name
                               FROM tbl_secschedlist AS s
                               INNER JOIN tbl_department AS d ON s.dept_code = d.dept_code
                               WHERE s.section_sched_code=? 
                               AND s.dept_code=? 
                               AND s.ay_code=?";
    $stmt_section_info = $conn->prepare($sql_fetch_section_info);
    $stmt_section_info->bind_param("sss", $section_sched_code, $_SESSION['dept_code'], $ay_code);
    $stmt_section_info->execute();
    $result_section_info = $stmt_section_info->get_result();

    if (!$result_section_info) {
        return '<p>No Available Section Schedule</p>';
    }

    if ($result_section_info->num_rows > 0) {
        $row_section_info = $result_section_info->fetch_assoc();
        $ay_code = $row_section_info['ay_code'];
        $dept_code = $row_section_info['dept_code'];
        $program_code = $row_section_info['program_code'];

        $section_id = $_GET['section_id'];
        $dept_name = $row_section_info['dept_name']; // Get the department name

        $sql_fetch_ay_name = "SELECT ay_name FROM tbl_ay WHERE ay_code = ?";
        $stmt_ay_name = $conn->prepare($sql_fetch_ay_name);
        $stmt_ay_name->bind_param("s", $ay_code);
        $stmt_ay_name->execute();
        $result_ay_name = $stmt_ay_name->get_result();

        $ay_name = ''; // Initialize ay_name
        if ($result_ay_name->num_rows > 0) {
            $row_ay_name = $result_ay_name->fetch_assoc();
            $ay_name = $row_ay_name['ay_name']; // Get the academic year name
        }

        // Sanitize the table name for the section schedule
        $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

        try {
            // Fetch schedule data from the sanitized section schedule table
            $sql_fetch_schedule = "
                SELECT sched.*, status.cell_color, c.course_name, c.credit 
                FROM $sanitized_section_sched_code AS sched 
                
                INNER JOIN tbl_schedstatus AS status 
                ON sched.section_sched_code = status.section_sched_code 
                INNER JOIN tbl_course AS c ON sched.course_code = c.course_code
                WHERE sched.semester = ? 
                AND sched.section_sched_code = ?";

            // Adding section_sched_code to ensure schedule belongs to the correct section
            $stmt = $conn->prepare($sql_fetch_schedule);
            $stmt->bind_param('ss', $semester, $section_sched_code);
            $stmt->execute();
            $result_schedule = $stmt->get_result();

            if (!$result_schedule) {
                throw new Exception("Error fetching schedule data: " . $conn->error);
            }

            $schedule_data = [];
            if ($result_schedule->num_rows > 0) {
                // Populate the schedule data array by day
                while ($row_schedule = $result_schedule->fetch_assoc()) {
                    $day = ucfirst(strtolower($row_schedule['day']));
                    $time_start = $row_schedule['time_start'];
                    $time_end = $row_schedule['time_end'];
                    $course_code = $row_schedule['course_code'];
                    $prof_name = $row_schedule['prof_name'];
                    $room_code = $row_schedule['room_code'];
                    $cell_color = $row_schedule['cell_color'];
                    $class_type_display = ($row_schedule['class_type'] === 'lec') ? 'Lecture' : 'Laboratory';
                    $course_name = $row_schedule['course_name'];
                    $credit = $row_schedule['credit'];


                    $schedule_data[$day][] = [
                        'time_start' => $time_start,
                        'time_end' => $time_end,
                        'course_code' => $course_code,
                        'room_code' => $room_code,
                        'prof_name' => $prof_name,
                        'cell_color' => $cell_color,
                        'class_type' => $class_type_display,
                        'course_name' => $course_name,
                        'credit' => $credit,

                    ];
                }
            } else {
                return '<p>No Available Section Schedule</p>';
            }

            $currentDate = date('m-d-Y');
            // Fetch the college name based on dept_name or dept_code
            $user_college_name = '';
            $query = "SELECT college_name FROM tbl_college WHERE college_code = (SELECT college_code FROM tbl_department WHERE dept_name = ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $dept_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $user_college_name = $row['college_name'];
            }

            $html = '
            <!-- Wrapper Section -->
            <div style="position: relative; text-align: center; padding: 10px;">
                <!-- Image Section -->
                <div style="position: absolute; left: 180px; top: 0;">
                    <img src="/SchedSys3/images/cvsu_logo.png" style="width: 70px; height: 60px;">
                </div>
                
                <!-- Text Section -->
                <div>
                    <p style="margin: 0;"></p>
                    <p style="text-align: center; font-size: 6px; margin: 0; font-family: \'Century Gothic\', Arial, sans-serif;">
                        Republic of the Philippines
                    </p>
                    <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: \'Bookman Old Style\', serif;">
            CAVITE STATE UNIVERSITY
        </p>
                    <p style="text-align: center; font-size: 8px; font-weight: bold; margin: 0; font-family: \'Century Gothic\', Arial, sans-serif;">
                        Don Severino de las Alas Campus
                    </p>
                    <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: \'Century Gothic\', Arial, sans-serif;">
                        Indang, Cavite
                    </p>
                    <p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">
                        ' . htmlspecialchars(string: $user_college_name) . '
                    </p>
                    <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: Arial, sans-serif;">
                        ' . htmlspecialchars($dept_name) . '
                    </p>
                    <p id="sectionTitle" style="text-align: center; font-size: 9px; margin: 0; font-weight: bold; font-family: Arial, sans-serif;">
                        ' . htmlspecialchars($section_id) . ' CLASS SCHEDULE
                    </p>
                    <p style="text-align: center; font-size: 8px; margin: 0; font-family: Arial, sans-serif;">
                        ' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . '
                    </p>
                </div>
            </div>
            

        
            <!-- Table Container -->
            <div class="schedule-table-container" style="width: 90%; display: flex; justify-content: center; margin: 0 auto; ">
        ';


            $html .= '<table class="table schedule-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; overflow-x: auto; padding: 3px;" 
        data-section="' . htmlspecialchars($section_id) . '" 
        data-college="' . htmlspecialchars($user_college_name) . '" 
        data-department="' . htmlspecialchars($dept_name) . '" 
        data-semester="' . htmlspecialchars($semester) . '"
        data-ayname="' . htmlspecialchars($ay_name) . '" >';

            $html .= '<thead>';
            $html .= '<tr>
            <th style="font-size: 8px; width: 20%; text-align: center; background-color:rgb(232, 232, 232);">Time</th>';

            $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($day_names as $day_name) {
                $html .= '<th style="width: 10%; text-align: center; font-size: 8px; padding: 3px; background-color:rgb(232, 232, 232);">' . $day_name . '</th>';
            }
            $html .= '</tr></thead>';

            $html .= '<tbody>';



            $sql_fetch = "SELECT table_start_time, table_end_time 
            FROM tbl_timeslot_active 
            WHERE active = 1 AND dept_code = ? AND semester = ? AND ay_code = ?";

            $stmt_fetch = $conn->prepare($sql_fetch);
            $stmt_fetch->bind_param("sss", $_SESSION['dept_code'], $semester, $ay_code); // Assuming all are strings
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();

            if ($result_fetch->num_rows > 0) {
                // Fetch the active time slot
                $row_fetch = $result_fetch->fetch_assoc();
                $user_start_time = strtotime($row_fetch['table_start_time']);
                $user_end_time = strtotime($row_fetch['table_end_time']);
            } else {
                // Defaults if no active time slot is found
                $user_start_time = strtotime('7:00 am');
                $user_end_time = strtotime('7:00 pm');
            }

            // Generate dynamic 30-minute time slots based on active time range
            $time_slots = [];
            for ($current_time = $user_start_time; $current_time < $user_end_time; $current_time += 1800) {
                $start_time = date('H:i', $current_time);
                $end_time = date('H:i', $current_time + 1800); // 30 minutes increment
                $time_slots[] = [
                    'start' => $start_time,
                    'end' => $end_time,
                ];
            }

            // Initialize rowspan tracking for each day
            $remaining_rowspan = array_fill_keys($day_names, 0);

            // Iterate over each time slot to fill the table
            foreach ($time_slots as $slot) {
                $start_time = $slot['start'];
                $end_time = $slot['end'];
                $start_time_formatted = formatTime($start_time);
                $end_time_formatted = formatTime($end_time);
                $html .= '<tr>';
                $html .= '<td class="time-slot" style=" text-align: center; font-size: 8px; padding: 3px;">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

                foreach ($day_names as $day_name) {
                    if ($remaining_rowspan[$day_name] > 0) {
                        // If rowspan is active, skip this cell
                        $remaining_rowspan[$day_name]--;
                    } else {
                        $cell_content = '';
                        $rowspan = 1;
                        $cell_color = ''; // Initialize cell color

                        // Check if there is schedule data for this day
                        if (isset($schedule_data[$day_name])) {
                            foreach ($schedule_data[$day_name] as $index => $schedule) {
                                $schedule_start = strtotime($schedule['time_start']);
                                $schedule_end = strtotime($schedule['time_end']);
                                $current_start = strtotime($start_time);
                                $current_end = strtotime($end_time);

                                // Check if the current time slot overlaps with the schedule
                                if (($current_start < $schedule_end && $current_end > $schedule_start)) {
                                    $class_type_display = $schedule['class_type']; // Already formatted as Lecture or Laboratory
                                    $prof_name = $schedule['prof_name'];


                                    $prof_name = ($schedule['prof_name'] === 'null') ? null : $schedule['prof_name'];

                                    $cell_content = "<span style='font-size: 8px; display: block; text-align: center; padding: 3px;'>{$schedule['course_code']}<br>\n{$schedule['room_code']}<br>\n{$prof_name}<br>\n{$schedule['class_type']}</span>";
                                    $rowspan = ceil(($schedule_end - $schedule_start) / 1800); // Calculate rowspan
                                    $remaining_rowspan[$day_name] = $rowspan - 1;
                                    $cell_color = $schedule['cell_color']; // Get the cell color

                                    break;
                                }
                            }
                        }

                        // Apply the cell color if available
                        $style = $cell_color ? ' style="background-color: ' . htmlspecialchars($cell_color) . '; vertical-align: middle;"'
                            : ' style="vertical-align: middle; padding: 3px;"';

                        $html .= '<td' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') .
                            ' data-cell-color="' . htmlspecialchars($cell_color) . '"' . $style . '>' . $cell_content . '</td>';



                    }
                }

                $html .= '</tr>';
            }


            $html .= '<tr>';
            $html .= '<td class="tdcolspan" colspan="1" style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; font-weight: bold; background-color:rgb(232, 232, 232);">Course Code</td>';
            $html .= '<th class="tdcolspan" colspan="3" style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; background-color:rgb(232, 232, 232);">Subjects</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; background-color:rgb(232, 232, 232);">Lec</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; background-color:rgb(232, 232, 232);">Lab</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; background-color:rgb(232, 232, 232);">Units</th>';

            $html .= '</tr>';



            $subject_list = []; // Initialize subject list
            $total_lec = 0;
            $total_lab = 0;
            $total_credit = 0;

            // foreach ($schedule_data as $day => $schedules) {
            //     foreach ($schedules as $schedule) {
            //         $course_code = $schedule['course_code'];
            //         $course_name = $schedule['course_name'];
            //         $credit = $schedule['credit'];
            //         $class_type = $schedule['class_type'];
            //         $start_time = strtotime($schedule['time_start']);
            //         $end_time = strtotime($schedule['time_end']);
            //         $duration = ($end_time - $start_time) / 3600;

            //         // Initialize subject data if not already set
            //         if (!isset($subject_list[$course_code])) {
            //             $subject_list[$course_code] = [
            //                 'course_name' => $course_name,
            //                 'credit' => $credit,
            //                 'lec_hours' => 0,
            //                 'lab_hours' => 0,
            //             ];
            //         }

            //         // Count lecture and lab hours
            //         if ($class_type === 'Lecture') {
            //             $subject_list[$course_code]['lec_hours'] += $duration;
            //         } elseif ($class_type === 'Laboratory') {
            //             $subject_list[$course_code]['lab_hours'] += $duration;
            //         }
            //     }
            // }

            // Fetch checklist data for the same program, year level, and semester


            $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ?";
            $stmt_year_level = $conn->prepare($sql_year_level);
            $stmt_year_level->bind_param("ss", $section_id, $dept_code);
            $stmt_year_level->execute();
            $stmt_year_level->bind_result($year_level);
            $stmt_year_level->fetch();
            $stmt_year_level->close();

            // Query to select courses matching the criteria

            // $sql_combined = "
//     SELECT 
//         c.course_code, 
//         c.course_name, 
//         c.credit, 
//         c.year_level, 
//         c.program_code, 
//         c.semester, 
//         c.lec_hrs, 
//         c.lab_hrs,
//         s.class_type, 
//         s.time_start, 
//         s.time_end
//     FROM tbl_course c
//     LEFT JOIN $sanitized_section_sched_code s ON c.course_code = s.course_code 
//     WHERE c.year_level = ? AND c.program_code = ? AND c.semester = ? 
//     AND (s.section_sched_code = ? OR s.section_sched_code IS NULL)
// ";

            $sql_combined = "
    SELECT 
        c.course_code, 
        c.course_name, 
        c.credit, 
        c.year_level, 
        c.program_code, 
        c.semester, 
        c.lec_hrs, 
        c.lab_hrs,
        s.class_type, 
        s.time_start, 
        s.time_end,
        s.section_sched_code
    FROM tbl_course c
    LEFT JOIN $sanitized_section_sched_code s 
        ON c.course_code = s.course_code 
        AND (s.section_sched_code = ? OR s.section_sched_code IS NULL)
    WHERE c.year_level = ? 
        AND c.program_code = ? 
        AND c.semester = ?
";


            $stmt_combined = $conn->prepare($sql_combined);
            $stmt_combined->bind_param('ssss', $section_sched_code, $year_level, $program_code, $semester);
            $stmt_combined->execute();
            $result_combined = $stmt_combined->get_result();

            $schedule_data = []; // To hold aggregated data for each course

            // Loop through the result and aggregate schedule data
            while ($row = $result_combined->fetch_assoc()) {

                $course_code = $row['course_code'];
                $class_type = $row['class_type'];
                $start_time = !empty($row['time_start']) ? strtotime($row['time_start']) : null;
                $end_time = !empty($row['time_end']) ? strtotime(datetime: $row['time_end']) : null;

                // echo "Course Code: " . htmlspecialchars($row['course_code']) . "\n";
                // echo "Course Name: " . htmlspecialchars($row['course_name']) . "\n";
                // Initialize schedule data if not already set

                if (!isset($schedule_data[$course_code])) {
                    $schedule_data[$course_code] = [
                        'course_name' => $row['course_name'],
                        'credit' => $row['credit'],
                        'lec_hrs' => $row['lec_hrs'],
                        'lab_hrs' => $row['lab_hrs'],
                        'sessions' => [
                            'lec' => ['total_duration' => 0, 'sessions' => []],
                            'lab' => ['total_duration' => 0, 'sessions' => []]
                        ]
                    ];
                }

                // Aggregate schedule data by class type (Lecture or Laboratory)
                if ($class_type) {
                    $duration = ($end_time - $start_time) / 3600; // Duration in hours
                    $schedule_data[$course_code]['sessions'][$class_type]['total_duration'] += $duration;
                    $schedule_data[$course_code]['sessions'][$class_type]['sessions'][] = [
                        'time_start' => $row['time_start'],
                        'time_end' => $row['time_end']
                    ];
                }
            }


            // Now output the data for each course
            foreach ($schedule_data as $course_code => $data) {
                $total_lec_duration = $data['sessions']['lec']['total_duration'];
                $total_lab_duration = $data['sessions']['lab']['total_duration'];
                $lec_hours = $data['lec_hrs'];
                $lab_hours = $data['lab_hrs'];

                $total_lec += $lec_hours;
                $total_lab += $lab_hours;
                $total_credit += $data['credit'];

                // Check if the computed durations meet the required hours
                $font_color = 'black'; // Default color
                if ($total_lec_duration < $lec_hours || $total_lab_duration < $lab_hours) {
                    $font_color = 'red'; // Change to red if the total duration is less than required
                }

                // Display the result in a table with color condition
                $html .= '<tr>';
                $html .= '<td class="tdcolspan" colspan="1" style="text-align: center; padding: 3px; font-size: 8px; color: ' . $font_color . '; font-weight: bold;">' . htmlspecialchars($course_code) . '</td>'; // Course code
                $html .= '<td class="tdcolspan" colspan="3" style="text-align: center; padding: 3px; font-size: 8px; color: ' . $font_color . ';">' . htmlspecialchars($data['course_name']) . '</td>'; // Course name
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($lec_hours) . '</td>'; // Lecture hours
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($lab_hours) . '</td>'; // Lab hours
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($data['credit']) . '</td>'; // Credit
                $html .= '</tr>';
            }





            $html .= '<tr>';
            $html .= '<td class="tdcolspan" colspan="3"</td>';
            $html .= '<td style="text-align: right; padding: 3px; font-size: 8px;"><b>Total:</b></td>';
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($total_lec) . '</td>';
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($total_lab) . '</td>';
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($total_credit) . '</td>';
            $html .= '</tr>';




            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';


            return $html;

        } catch (Exception $e) {
            return '<p>Error fetching schedule: ' . $e->getMessage() . '</p>';
        }
    }
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'make_public') {
//     if (!empty($_POST['schedules'])) {
//         $schedules = $_POST['schedules'];
//         $messages = [];
//         $publicSchedules = 0;

//         foreach ($schedules as $schedule) {
//             $section_sched_code = $schedule['section_sched_code'];
//             $semester = $schedule['semester'];

//             // Check if the schedule exists in tbl_schedstatus
//             $checkQuery = "SELECT * FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ?";
//             $stmt = $conn->prepare($checkQuery);
//             $stmt->bind_param('ss', $section_sched_code, $semester);
//             $stmt->execute();
//             $result = $stmt->get_result();

//             if ($result->num_rows > 0) {
//                 // Update status to 'public' in tbl_schedstatus
//                 $updateQuery = "UPDATE tbl_schedstatus SET status = 'public' WHERE section_sched_code = ? AND semester = ?";
//                 $stmt = $conn->prepare($updateQuery);
//                 $stmt->bind_param('ss', $section_sched_code, $semester);
//                 $stmt->execute();

//                 // Increment public schedule count
//                 $publicSchedules++;
//             } else {
//                 $messages[] = "No matching schedules found for section_sched_code: $section_sched_code and semester: $semester.";
//             }
//         }

//         if ($publicSchedules > 0) {
//             $messages[] = "$publicSchedules schedules have been made public.";
//         }

//         // Output all messages as a single string
//         if (!empty($messages)) {
//             echo implode("<br>", $messages);
//         }
//     }
//     exit;
// }


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $section_sched_code = $_POST['section_sched_code'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $statusAction = $_POST['action']; // 'public' or 'private'

    if (!empty($section_sched_code) && !empty($semester)) {
        // Check if schedule exists
        $checkQuery = "
            SELECT ss.*, sl.section_code, sl.dept_code 
            FROM tbl_schedstatus ss
            JOIN tbl_secschedlist sl ON ss.section_sched_code = sl.section_sched_code 
            WHERE ss.section_sched_code = ? AND ss.semester = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ss', $section_sched_code, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Fetch schedule info
            $row = $result->fetch_assoc();
            $section_code = $row['section_code'];
            $dept_code = $row['dept_code'];

            // Determine new status based on action
            $newStatus = ($statusAction === 'public') ? 'public' : 'private';

            // Update the schedule status
            $updateQuery = "UPDATE tbl_schedstatus SET status = ? WHERE section_sched_code = ? AND semester = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('sss', $newStatus, $section_sched_code, $semester);

            if ($stmt->execute()) {
                // Notifications after successful status update
                $notificationMessage = "The schedule for section {$section_sched_code} has been changed to {$newStatus}.";
                $sender = $_SESSION['cvsu_email'];

                // Notification for students
                $receiver = 'student';
                $notificationQuery = "
                    INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code) 
                    VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?)";
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param('sssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code);
                $notificationStmt->execute();

                // Notification for professors
                $receiver = 'professor';
                $notificationStmt->bind_param('sssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code);
                $notificationStmt->execute();

                // Return JSON response after updating status and sending notifications
                echo json_encode(['status' => $newStatus]);
            } else {
                // echo json_encode(['error' => "Failed to update status."]);
            }
        } else {
            // echo json_encode(['error' => "No matching schedules found."]);
        }
    } else {
        // echo json_encode(['error' => "Missing required parameters."]);
    }
    exit;
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Section Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>

    <link rel="stylesheet" href="../../../css/department_secretary/report/report_sec.css">
    <link rel="stylesheet" href="../../../css/department_secretary/library/lib_sec.css">

</head>

<body>


    <?php
    if ($_SESSION['user_type'] == 'Department Chairperson' && $admin_college_code == $user_college_code): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php");
        ?>
    <?php else: ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>
    <?php endif; ?>


    <h2 class="title"> <i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i> SCHEDULES</h2>

    <div class="container mt-5">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="section-tab" href="lib_section.php" aria-controls="Section"
                    aria-selected="true">Section</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="lib_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="lib_professor.php" aria-controls="professor"
                    aria-selected="false">Instructor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/majorsub_summary.php" aria-controls="professor"
                    aria-selected="false">Major Subject Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/minorsub_summary.php" aria-controls="professor"
                    aria-selected="false">Minor Subject Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/room_summary.php" aria-controls="professor"
                    aria-selected="false">Classroom Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/prof_summary.php" aria-controls="professor"
                    aria-selected="false">Instructor Summary</a>
            </li>
            <?php if ($user_type == 'Department Secretary'): ?>
                <li class="nav-item">
                    <a class="nav-link" id="vacant-room-tab" href="/SchedSys3/php/viewschedules/data_schedule_vacant.php"
                        aria-controls="vacant-room" aria-selected="false">Vacant Room</a>
                </li>
            <?php endif; ?>
        </ul>

        <section class="content">
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="search-bar-container">
                        <form method="POST" action="lib_section.php" class="row">
                            <div class="col-md-3 mb-3">
                                <!-- Added mb-3 for margin-bottom -->
                                <select class="form-control" id="search_ay" name="search_ay">
                                    <?php foreach ($ay_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option['ay_code']); ?>" <?php echo ($selected_ay_code == $option['ay_code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['ay_name']); ?>
                                            <!-- Display ay_name -->
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <!-- Added mb-3 for margin-bottom -->
                                <select class="form-control" id="search_semester" name="search_semester">
                                    <option value="1st Semester" <?php echo ($selected_semester == '1st Semester') ? 'selected' : ''; ?>>
                                        1st Semester
                                    </option>
                                    <option value="2nd Semester" <?php echo ($selected_semester == '2nd Semester') ? 'selected' : ''; ?>>
                                        2nd Semester
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <!-- Added mb-3 for margin-bottom -->
                                <input type="text" class="form-control" name="search_section"
                                    value="<?php echo isset($_POST['search_section']) ? htmlspecialchars($_POST['search_section']) : ''; ?>"
                                    placeholder="Search" autocomplete="off">
                            </div>
                            <div class="col-md-3 mb-3">
                                <!-- Added mb-3 for margin-bottom -->
                                <button type="submit" class="btn w-100">Search</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


            <div class="card-body">
                <div class="table-container">
                    <button id="viewSelectedSchedules" class="btn" data-bs-toggle="modal"
                        data-bs-target="#selectedSchedulesModal">
                        View Selected Schedules
                    </button>

                    <table class="table" id="scheduleTable">
                        <thead>
                            <th>
                                <span id="selectAllContainer">
                                    <input type="checkbox" id="checkAll"> Select All
                                </span>
                            </th>
                            <th>Section</th>
                            <th></th>

                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>

                                    <?php
                                    // Extract year level from section_code like "BSIT 4-1"
                                    preg_match('/\b(\d)\b/', $row['section_code'], $matches);
                                    $section_year_level = $matches[1] ?? null;

                                    $year_level_string = '';
                                    if ($section_year_level) {
                                        switch ($section_year_level) {
                                            case '1':
                                                $year_level_string = '1st Year';
                                                break;
                                            case '2':
                                                $year_level_string = '2nd Year';
                                                break;
                                            case '3':
                                                $year_level_string = '3rd Year';
                                                break;
                                            case '4':
                                                $year_level_string = '4th Year';
                                                break;
                                            default:
                                                $year_level_string = $section_year_level . 'th Year';
                                        }
                                    }

                                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                                    // Default row style
                                    $row_style = "";

                                    $curriculum = '';
                                    $program_code = '';

                                    // First, try to get from tbl_schedstatus
                                    $currQuery = "SELECT curriculum FROM tbl_schedstatus WHERE section_sched_code = ?";
                                    $stmtCurr = $conn->prepare($currQuery);
                                    $stmtCurr->bind_param("s", $row['section_sched_code']);
                                    $stmtCurr->execute();
                                    $currResult = $stmtCurr->get_result();
                                    $currRow = $currResult->fetch_assoc();

                                    if ($currRow) {
                                        $curriculum = $currRow['curriculum'];
                                    }

                                    // If program_code still not found, try to get it from tbl_secschedlist
                                    if (empty($program_code)) {
                                        $programQuery = "SELECT program_code FROM tbl_secschedlist WHERE section_sched_code = ?";
                                        $stmtProg = $conn->prepare($programQuery);
                                        $stmtProg->bind_param("s", $row['section_sched_code']);
                                        $stmtProg->execute();
                                        $progResult = $stmtProg->get_result();
                                        $progRow = $progResult->fetch_assoc();

                                        if ($progRow) {
                                            $program_code = $progRow['program_code'];
                                        }
                                    }


                                    if ($year_level_string) {
                                        $courseQuery = "SELECT SUM(lec_hrs) AS total_lec_hrs, SUM(lab_hrs) AS total_lab_hrs 
                                        FROM tbl_course 
                                        WHERE year_level = ? AND semester = ? AND curriculum = ? AND dept_code = ? AND program_code =?";
                                        $stmtCourse = $conn->prepare($courseQuery);
                                        $stmtCourse->bind_param("issss", $section_year_level, $semester, $curriculum, $dept_code, $program_code); // make sure section_year_level is integer
                                        $stmtCourse->execute();
                                        $courseResult = $stmtCourse->get_result();
                                        $courseRow = $courseResult->fetch_assoc();

                                        $required_lec_hrs = $courseRow['total_lec_hrs'] ?? 0;
                                        $required_lab_hrs = $courseRow['total_lab_hrs'] ?? 0;

                                        // echo "section_year_level: $year_level_string<br>";
                                        // echo "program code: $program_code<br>";
                                        // echo "dept code: $dept_code<br>";
                                        // echo "Semester: $semester<br>";
                                        // echo "Curriculum: $curriculum<br>";
                                        // echo "required_lec_hrs:  $required_lec_hrs<br>";
                                        // echo "required_lab_hrs: $required_lab_hrs<br>";
                            
                                        // Fetch accomplished lec/lab hours from tbl_schedule
                                        $schedQuery = "
                                            SELECT class_type, time_start, time_end, day 
                                            FROM $sanitized_section_sched_code 
                                            WHERE section_sched_code = ? AND semester = ? AND curriculum = ? AND dept_code = ?
                                        ";

                                        $stmtSched = $conn->prepare($schedQuery);
                                        $stmtSched->bind_param("ssss", $row['section_sched_code'], $semester, $curriculum, $dept_code);
                                        $stmtSched->execute();
                                        $schedResult = $stmtSched->get_result();

                                        $accomplished_lec = 0;
                                        $accomplished_lab = 0;

                                        while ($schedRow = $schedResult->fetch_assoc()) {
                                            $start = strtotime($schedRow['time_start']);
                                            $end = strtotime($schedRow['time_end']);
                                            $duration = ($end - $start) / 3600; // duration in hours
                            
                                            if (strtolower($schedRow['class_type']) === 'lec') {
                                                $accomplished_lec += $duration;
                                            } elseif (strtolower($schedRow['class_type']) === 'lab') {
                                                $accomplished_lab += $duration;
                                            }
                                        }

                                        // Debug output
                                        // echo "Lec hours: $accomplished_lec<br>";
                                        // echo "Lab hours: $accomplished_lab<br>";
                            
                                        $cell_style = '';
                                        if ($accomplished_lec < $required_lec_hrs || $accomplished_lab < $required_lab_hrs) {
                                            $cell_style = 'style="color:rgb(240, 48, 64);"';
                                        }
                                    }
                                    ?>

                                    <tr data-section-id="<?php echo htmlspecialchars($row['section_code'] ?? ''); ?>">
                                        <td style="width: 80px;" <?php echo $cell_style; ?>>
                                            <div style="display: flex;">
                                                <?php if ($user_type != "Department Chairperson" && ($row['ay_code'] ?? '') == $active_ay_code): ?>
                                                    <form method="POST" action="lib_section.php">
                                                        <!-- hidden inputs -->
                                                        <input type="hidden" name="semester"
                                                            value="<?php echo htmlspecialchars($row['semester'] ?? ''); ?>">
                                                        <input type="hidden" name="ay_code"
                                                            value="<?php echo htmlspecialchars($row['ay_code'] ?? ''); ?>">
                                                        <input type="hidden" name="status"
                                                            value="<?php echo htmlspecialchars($row['status'] ?? ''); ?>">
                                                        <input type="hidden" name="section_sched_code"
                                                            value="<?php echo htmlspecialchars($row['section_sched_code'] ?? ''); ?>">

                                                        <?php
                                                        $query = "SELECT status FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ?";
                                                        $stmt = $conn->prepare($query);
                                                        $stmt->bind_param("ss", $row['section_sched_code'], $row['semester']);
                                                        $stmt->execute();
                                                        $statusResult = $stmt->get_result();
                                                        $statusRow = $statusResult->fetch_assoc();
                                                        $currentStatus = $statusRow['status'] ?? '';
                                                        ?>

                                                        <input type="checkbox" class="schedule-checkbox"
                                                            data-section-code="<?php echo htmlspecialchars($row['section_code']); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                            data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>"
                                                            onclick="event.stopPropagation();" />



                                                        <button type="button" class="change-btn lock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? ''); ?>"
                                                            data-section-sched-code="<?php echo htmlspecialchars($row['section_sched_code'] ?? ''); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? ''); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus == 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye-slash"></i>
                                                        </button>


                                                        <button type="button" class="change-btn unlock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? ''); ?>"
                                                            data-section-sched-code="<?php echo htmlspecialchars($row['section_sched_code'] ?? ''); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? ''); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus != 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye"></i>
                                                        </button>


                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td <?php echo $cell_style; ?>>
                                            <?php echo htmlspecialchars($row['section_code']); ?>

                                        </td>

                                        <td <?php echo $cell_style; ?>>
                                            <div class="button-group">
                                                <?php if ($user_type != "Department Chairperson" && $row['ay_code'] == $active_ay_code): ?>
                                                    <form method="POST" action="lib_section.php" style="display:inline;">
                                                        <button type="button" class="share-btn" data-bs-toggle="modal"
                                                            data-bs-target="#shareSchedule"
                                                            data-section-code="<?php echo htmlspecialchars($row['section_code']); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                            data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                            <i class="fa-light fa-share-nodes"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php
                                                $search_ay_name = $_POST['search_ay_name'] ?? $_SESSION['ay_code'];
                                                $search_semester = $_POST['search_semester'] ?? $_SESSION['semester'];

                                                if (
                                                    isset($_SESSION['semester'], $_SESSION['ay_code'], $search_ay_name, $search_semester) &&
                                                    $search_ay_name === $_SESSION['ay_code'] &&
                                                    $search_semester === $_SESSION['semester']
                                                ) {
                                                    if ($row['ay_code'] == $active_ay_code): ?>
                                                        <form method="POST" action="lib_section.php" style="display:inline;">
                                                            <input type="hidden" name="section_sched_code"
                                                                value="<?php echo htmlspecialchars($row['section_sched_code']); ?>">
                                                            <input type="hidden" name="semester"
                                                                value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                            <input type="hidden" name="ay_code"
                                                                value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                            <input type="hidden" name="section_code"
                                                                value="<?php echo htmlspecialchars($row['section_code']); ?>">
                                                            <button type="submit" name="edit" class="edit-btn"
                                                                onclick="event.stopPropagation();">
                                                                <i class="fa-light fa-pencil"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif;
                                                }
                                                ?>

                                                <form method="POST" action="lib_section.php" style="display:inline;">
                                                    <input type="hidden" name="token"
                                                        value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                                                    <input type="hidden" name="section_code"
                                                        value="<?php echo htmlspecialchars($row['section_code']); ?>">
                                                    <input type="hidden" name="semester"
                                                        value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                    <input type="hidden" name="ay_code"
                                                        value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                    <input type="hidden" name="section_sched_code"
                                                        value="<?php echo htmlspecialchars($row['section_sched_code']); ?>">
                                                    <button type="button" class="delete-btn" data-bs-toggle="modal"
                                                        data-bs-target="#deleteModal"
                                                        data-section-code="<?php echo htmlspecialchars($row['section_code']); ?>"
                                                        data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                        data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>"
                                                        data-section-sched-code="<?php echo htmlspecialchars($row['section_sched_code']); ?>"
                                                        onclick="event.stopPropagation();">
                                                        <i class="fa-light fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No Records Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>

                    </table>
                    <p id="noRecordsMessage" class="text-center" style="display: none;">No Records Found</p>
                </div>
            </div>
    </div>
    </section>

    <!-- MODAL -->
    <div class="modal fade" id="selectedSchedulesModal" tabindex="-1" aria-labelledby="selectedSchedulesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="selectedSchedulesLabel">Selected Schedules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="selectedSchedulesContent">
                    <!-- Selected schedules will be dynamically inserted here -->
                </div>
                <div class="modal-footer">
                    <button class="btn " id="exportSelectedSchedulesExcel"
                        style="background-color: #FD7238; color:#ffffff;">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.getElementById('exportSelectedSchedulesExcel').addEventListener('click', async function () {
            const selectedCheckboxes = Array.from(document.querySelectorAll('.schedule-checkbox:checked'));
            const tables = document.querySelectorAll('#selectedSchedulesContent table');

            if (tables.length === 0 || selectedCheckboxes.length === 0) {
                alert('No schedules to export.');
                return;
            }

            if (tables.length !== selectedCheckboxes.length) {
                alert('Mismatch between selected schedules and loaded schedules.');
                return;
            }

            const workbook = new ExcelJS.Workbook();

            function getSheetName(sectionCodes) {
                let name = sectionCodes.join('-').substring(0, 31);
                return name || 'Schedules';
            }

            let worksheet1Sections = selectedCheckboxes.slice(0, 3).map(cb => cb.dataset.sectionCode || 'Sec');
            const worksheet1Name = getSheetName(worksheet1Sections);
            const worksheet1 = workbook.addWorksheet(worksheet1Name, {
                pageSetup: {
                    paperSize: 9,
                    orientation: 'landscape',
                    fitToPage: true,
                    fitToWidth: 1,
                    fitToHeight: 0,
                    margins: {
                        left: 0.25, right: 0.25,
                        top: 0.5, bottom: 0.5,
                        header: 0.3, footer: 0.3
                    }
                }
            });

            let worksheet2 = null;
            if (tables.length > 3) {
                let worksheet2Sections = selectedCheckboxes.slice(3).map(cb => cb.dataset.sectionCode || 'Sec');
                const worksheet2Name = getSheetName(worksheet2Sections);
                worksheet2 = workbook.addWorksheet(worksheet2Name, {
                    pageSetup: {
                        paperSize: 9,
                        orientation: 'landscape',
                        fitToPage: true,
                        fitToWidth: 1,
                        fitToHeight: 0,
                        margins: {
                            left: 0.25, right: 0.25,
                            top: 0.5, bottom: 0.5,
                            header: 0.3, footer: 0.3
                        }
                    }
                });
            }

            function cleanText(rawHtml) {
                let cleaned = rawHtml
                    .replace(/<br\s*\/?>/gi, '\n')               // Convert <br> to line break
                    .replace(/<\/?[^>]+(>|$)/g, '')              // Remove all other HTML tags
                    .replace(/\n\s+/g, '\n')                     // Remove extra spaces after new lines
                    .replace(/\s+\n/g, '\n')                     // Remove spaces before new lines
                    .replace(/^\s+|\s+$/g, '');                  // Trim leading/trailing whitespace
                return cleaned;
            }

            function addScheduleToSheet(sheet, table, selectedCheckbox, startCol) {
                const sectionCode = selectedCheckbox.dataset.sectionCode || `Schedule`;
                const semester = selectedCheckbox.dataset.semester || '';
                const ayCode = selectedCheckbox.dataset.ayCode || '';
                const ayName = document.querySelector(".schedule-table").dataset.ayname;

                const tableColSpan = table.rows[0].cells.length;
                const titleStartRow = 1;
                const ayRow = titleStartRow + 1;
                const tableStartRow = titleStartRow + 2;

                // Title row
                sheet.getCell(titleStartRow, startCol).value = `${sectionCode}`;
                sheet.mergeCells(titleStartRow, startCol, titleStartRow, startCol + tableColSpan - 1);
                const titleCell = sheet.getCell(titleStartRow, startCol);
                titleCell.font = { bold: true, size: 12 };
                titleCell.alignment = { vertical: 'middle', horizontal: 'center' };
                titleCell.border = {
                    top: { style: 'thin' }, left: { style: 'thin' },
                    bottom: { style: 'thin' }, right: { style: 'thin' }
                };

                // Semester row
                sheet.getCell(ayRow, startCol).value = `${semester}, S.Y. ${ayName}`;
                sheet.mergeCells(ayRow, startCol, ayRow, startCol + tableColSpan - 1);
                const ayCell = sheet.getCell(ayRow, startCol);
                ayCell.font = { italic: true, size: 10 };
                ayCell.alignment = { vertical: 'middle', horizontal: 'center' };
                ayCell.border = {
                    top: { style: 'thin' }, left: { style: 'thin' },
                    bottom: { style: 'thin' }, right: { style: 'thin' }
                };

                const rows = table.rows;
                let maxColWidths = [];

                for (let r = 0; r < rows.length; r++) {
                    const row = sheet.getRow(r + tableStartRow);
                    const htmlRow = rows[r];
                    const cells = htmlRow.cells;
                    let colOffset = 0;

                    for (let c = 0; c < cells.length; c++) {
                        const cell = cells[c];
                        const rawHtml = cell.innerHTML.trim();
                        const cellText = cleanText(rawHtml);

                        const colspan = cell.classList.contains('tdcolspan') ? parseInt(cell.getAttribute('colspan') || '1', 10) : 1;
                        const targetCell = row.getCell(startCol + colOffset);

                        targetCell.value = cellText;
                        targetCell.alignment = { wrapText: true, vertical: 'middle', horizontal: 'center' };

                        // Bold headers
                        if (htmlRow.parentElement.tagName.toLowerCase() === 'thead' || cell.tagName.toLowerCase() === 'th') {
                            targetCell.font = { bold: true };
                            targetCell.fill = {
                                type: 'pattern',
                                pattern: 'solid',
                                fgColor: { argb: 'FFD9D9D9' }
                            };
                        }

                        // Background color if specified
                        const color = cell.getAttribute('data-cell-color');
                        if (color) {
                            const hex = color.replace('#', '').toUpperCase();
                            targetCell.fill = {
                                type: 'pattern',
                                pattern: 'solid',
                                fgColor: { argb: `FF${hex}` }
                            };
                        }

                        // Merge if colspan exists
                        if (colspan > 1) {
                            sheet.mergeCells(r + tableStartRow, startCol + colOffset, r + tableStartRow, startCol + colOffset + colspan - 1);
                        }

                        // Estimate column width
                        for (let w = 0; w < colspan; w++) {
                            const colIndex = startCol + colOffset + w;
                            const lineLengths = cellText.split('\n').map(line => line.length);
                            const maxLine = Math.max(...lineLengths, 0);
                            const estWidth = Math.min(maxLine + 2, 25);
                            maxColWidths[colIndex] = Math.max(maxColWidths[colIndex] || 0, estWidth);
                        }

                        colOffset += colspan;
                    }
                }

                // Fill empty cells with borders and alignment (only for the table columns)
                const fullRows = table.rows.length;
                const fullCols = tableColSpan;
                for (let r = 0; r < fullRows; r++) {
                    const row = sheet.getRow(r + tableStartRow);
                    for (let c = 0; c < fullCols; c++) {
                        const cell = row.getCell(startCol + c);
                        if (!cell.value) {
                            cell.value = '';
                            cell.alignment = { wrapText: true, vertical: 'middle', horizontal: 'center' };
                        }
                        cell.border = {
                            top: { style: 'thin' }, left: { style: 'thin' },
                            bottom: { style: 'thin' }, right: { style: 'thin' }
                        };
                    }
                }

                // Apply column widths for the table columns
                Object.entries(maxColWidths).forEach(([colIndex, width]) => {
                    sheet.getColumn(Number(colIndex)).width = width;
                });

                return tableColSpan;
            }

            // Helper to add the gap column with **no borders** and fixed width
            function addGapColumn(sheet, col, startRow, rowCount) {
                sheet.getColumn(col).width = 2; // narrow gap column
                for (let r = startRow; r < startRow + rowCount; r++) {
                    const cell = sheet.getRow(r).getCell(col);
                    cell.value = '';
                    cell.border = {};   // no border at all
                    cell.alignment = { vertical: 'middle', horizontal: 'center' };
                }
            }

            let startCol = 1;
            for (let i = 0; i < Math.min(3, tables.length); i++) {
                const colSpan = addScheduleToSheet(worksheet1, tables[i], selectedCheckboxes[i], startCol);
                addGapColumn(worksheet1, startCol + colSpan, 1, tables[i].rows.length + 2);  // +2 for title and semester rows
                startCol += colSpan + 1;
            }

            if (worksheet2) {
                startCol = 1;
                for (let i = 3; i < tables.length; i++) {
                    const colSpan = addScheduleToSheet(worksheet2, tables[i], selectedCheckboxes[i], startCol);
                    addGapColumn(worksheet2, startCol + colSpan, 1, tables[i].rows.length + 2);
                    startCol += colSpan + 1;
                }
            }


            const buffer = await workbook.xlsx.writeBuffer();
            saveAs(new Blob([buffer]), 'Student Chart.xlsx');
        });


    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <script>
        // Check/uncheck all checkboxes
        $('#checkAll').on('change', function () {
            $('.schedule-checkbox').prop('checked', $(this).is(':checked'));
        });

        $('#viewSelectedSchedules').on('click', function () {
            const selected = $('.schedule-checkbox:checked');
            if (selected.length === 0) {
                $('#selectedSchedulesContent').html('<p>Please select at least one section.</p>');
                return;
            }

            $('#selectedSchedulesContent').html('<p>Loading schedules...</p>');

            let requests = [];

            selected.each(function () {
                const sectionId = $(this).data('section-code');
                const semester = $(this).data('semester');
                const ay_code = $(this).data('ay-code');

                const request = $.ajax({
                    url: 'lib_section.php',
                    type: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        section_id: sectionId,
                        semester: semester,
                        ay_code: ay_code
                    }
                });

                requests.push(request);
            });

            Promise.all(requests)
                .then(responses => {
                    $('#selectedSchedulesContent').html(responses.join('<hr>')); // Display each schedule separated by <hr>
                })
                .catch(error => {
                    console.error(error);
                    $('#selectedSchedulesContent').html('<p>Failed to load schedules.</p>');
                });
        });



    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const selectAllContainer = document.getElementById("selectAllContainer");
            const viewButton = document.getElementById("viewSelectedSchedules");
            const scheduleCheckboxes = document.querySelectorAll(".schedule-checkbox");

            function updateUI() {
                const anyChecked = Array.from(scheduleCheckboxes).some(cb => cb.checked);
                selectAllContainer.style.display = anyChecked ? "inline-flex" : "none";
                viewButton.style.display = anyChecked ? "inline-block" : "none";
            }

            scheduleCheckboxes.forEach(cb => {
                cb.addEventListener("change", updateUI);
            });

            updateUI(); // check on load in case of pre-checked values
        });
    </script>



    <!-- Success Modal for Public -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                This section is successfully made public.
                <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> -->
            </div>
        </div>
    </div>
    </div>



    <!-- Success Modal for Private -->
    <div class="modal fade" id="privateModal" tabindex="-1" aria-labelledby="privateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                This section is successfully private again.
                <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button> -->
            </div>
        </div>
    </div>
    </div>


    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </button>
                </div>
                <div class="modal-body">
                    <div class="schedule-table-container" id="scheduleContent">
                        <!-- Schedule content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn" style="background-color: #FD7238; color:#ffffff;" id="SchedulePDF">PDF</button>
                    <button class="btn" style="background-color: #FD7238; color:#ffffff;"
                        onclick="fnExportToExcel('xlsx', 'MySchedule')">Excel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scheduleSharedModal" tabindex="-1" aria-labelledby="scheduleSharedModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                This schedule has already been shared.
                <!-- <a href="lib_section.php" class="btn" id="closeBtn">Close</a> -->
            </div>
        </div>
    </div>
    </div>

    <div class="modal fade" id="scheduleSuccessSharedModal" tabindex="-1" aria-labelledby="scheduleSharedModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                This schedule successfully shared.
                <!-- <a href="lib_section.php" class="btn" id="closeBtn">Close</a> -->
            </div>
        </div>
    </div>
    </div>

    <!-- Modal Structure -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    Are you sure you want to delete this record?
                </div>
                <div class="delete">
                    <form id="deleteModalForm" method="POST" action="lib_section.php" style="display:inline;">
                        <input type="hidden" name="token" id="deleteToken">
                        <input type="hidden" name="section_code" id="deleteSectionCode">
                        <input type="hidden" name="semester" id="deleteSemester">
                        <input type="hidden" name="ay_code" id="deleteAyCode">
                        <input type="hidden" name="section_sched_code" id="deleteSectionSchedCode">
                        <button type="submit" name="action" value="delete" class="btn btn-danger">Confirm</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
        function openScheduleModal(sectionId, semester, ayCode) {
            // Load schedule data via AJAX
            $.ajax({
                url: 'lib_section.php',
                type: 'GET',
                data: {
                    action: 'fetch_schedule',
                    section_id: sectionId,
                    semester: semester,
                    ay_code: ayCode
                },
                success: function (response) {
                    $('#scheduleContent').html(response);
                    $('#scheduleModal').modal('show');
                },
                error: function () {
                    alert('Error loading schedule data.');
                }
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget; // Button that triggered the modal
                const sectionCode = button.getAttribute('data-section-code');
                const semester = button.getAttribute('data-semester');
                const ayCode = button.getAttribute('data-ay-code');
                const sectionSchedCode = button.getAttribute('data-section-sched-code');

                // Set the values in the modal's form
                document.getElementById('deleteSectionCode').value = sectionCode;
                document.getElementById('deleteSemester').value = semester;
                document.getElementById('deleteAyCode').value = ayCode;
                document.getElementById('deleteSectionSchedCode').value = sectionSchedCode;
                document.getElementById('deleteToken').value =
                    "<?php echo htmlspecialchars($_SESSION['token']); ?>";
            });
        });
    </script>

    <script>
        window.onload = function () {
            // Check if the session variable is set to show the schedule shared modal
            <?php if (isset($_SESSION['show_schedule_shared_modal'])): ?>
                var sharedModal = new bootstrap.Modal(document.getElementById('scheduleSharedModal'));
                sharedModal.show();
                <?php unset($_SESSION['show_schedule_shared_modal']); // Clear the session variable 
                    ?>
            <?php endif; ?>

            // Check if the session variable is set to show the schedule not shared modal
            <?php if (isset($_SESSION['show_schedule_not_shared_modal'])): ?>
                var notSharedModal = new bootstrap.Modal(document.getElementById('scheduleSuccessSharedModal'));
                notSharedModal.show();
                <?php unset($_SESSION['show_schedule_not_shared_modal']); // Clear the session variable 
                    ?>
            <?php endif; ?>
        };
    </script>

    <style>
        #scheduleSharedModal .modal-dialog {
            max-width: 30%;
            margin: 1.5rem auto;
        }

        #scheduleSharedModal .modal-dialog {
            max-width: 30%;
            margin: 1.5rem auto;
        }

        .modal-content {
            border-radius: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-body {
            padding: 1rem;
        }
    </style>

    <!-- Modal for Sharing Schedule -->
    <div class="modal fade" id="shareSchedule" tabindex="-1" role="dialog" aria-labelledby="shareScheduleLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareScheduleLabel">Share Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Form for Sharing Schedule -->
                    <form id="shareForm" method="POST" action="lib_section.php">
                        <?php
                        // Assuming you have a database connection in $conn
                        
                        // Get the current user's email from the session
                        $current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

                        // Query to get all cvsu_email and department of Department Secretaries
                        $query = "SELECT cvsu_email, dept_code 
                                    FROM tbl_prof_acc 
                                    WHERE user_type = 'Department Secretary' 
                                        AND cvsu_email IS NOT NULL 
                                        AND dept_code != ? AND ay_code = ? AND semester = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("sss", $dept_code, $ay_code, $semester); // Bind the $dept_code variable
                        $stmt->execute();
                        $result = $stmt->get_result();


                        if ($result && mysqli_num_rows($result) > 0) {
                            echo '<label for="recipient_email" style="float:left;">Recipient\'s Email:</label>';
                            echo '<input list="email_list" id="recipient_email" name="recipient_email" placeholder="Type or select an email" required>';
                            echo '<datalist id="email_list">';
                            echo '<option value="">Select Recipient Email</option>'; // Default option
                        
                            // Loop through the result and create options
                            while ($row = mysqli_fetch_assoc($result)) {
                                $email = htmlspecialchars($row['cvsu_email']); // Sanitize email
                                $department = htmlspecialchars($row['dept_code']); // Sanitize department
                        
                                // Check if the email is the same as the current user's email
                                if ($email !== $current_user_email) {
                                    echo '<option data-dept-code="' . $department . '" value="' . $email . '">' . $email . ' (' . $department . ')</option>';
                                }
                            }

                            echo '</datalist><br><br>';
                        } else {
                            echo '<p>No department secretary emails found.</p>';
                        }
                        ?>

                </div>
                <div class="modal-footer">
                    <form id="shareForm" method="POST">
                        <input type="hidden" id="modalSectionCode" name="modalSectionCode">
                        <input type="hidden" id="modalSemester" name="modalSemester">
                        <input type="hidden" id="modalAyCode" name="modalAyCode">
                        <input type="hidden" id="receiver_dept_code" name="receiver_dept_code">
                        <button type="submit" name="send" value="send" id="closeBtn" class="btn">Send</button>
                    </form>
                </div>
            </div>
        </div>


        <script>
            document.getElementById('SchedulePDF').addEventListener('click', function () {
                const element = document.getElementById('scheduleContent');

                // Get the section_id from the <p> tag where it is displayed
                const sectionTitleElement = document.getElementById('sectionTitle');

                // Extract the full sectionId from the <p> tag text content
                const sectionId = sectionTitleElement ? sectionTitleElement.textContent.trim() : 'section_schedule';

                const fileName = `${sectionId}`; // Combine the section ID and custom name

                // Create a div for any custom text (if needed)
                const customTextDiv = document.createElement('div');
                customTextDiv.innerHTML = ``; // Add any custom text if needed
                element.prepend(customTextDiv);

                // Generate PDF as a Blob
                html2pdf().from(element).set({
                    margin: [0.5, 0.5, 0.5, 0.5],
                    html2canvas: {
                        scale: 3
                    },
                    jsPDF: {
                        unit: 'in',
                        format: 'a4',
                        orientation: 'portrait'
                    }
                }).outputPdf('blob').then(function (blob) {
                    const pdfUrl = URL.createObjectURL(blob);
                    window.open(pdfUrl);

                    const link = document.createElement('a');
                    link.href = pdfUrl;
                    link.download = `${fileName}.pdf`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    // Clean up by removing the custom text div after PDF is generated
                    element.removeChild(customTextDiv);
                }).catch(function (error) {
                    console.error('Error generating PDF:', error);
                });
            });


            async function fnExportToExcel(fileExtension, filename) {
                var sectionId = document.querySelector(".schedule-table").dataset.section;
                var deptName = document.querySelector(".schedule-table").dataset.department;
                var collegeName = document.querySelector(".schedule-table").dataset.college;
                var semester = document.querySelector(".schedule-table").dataset.semester;
                var academicYear = document.querySelector(".schedule-table").dataset.ayname;
                var currentDate = new Date().toLocaleDateString();
                var sheetName = sectionId || "Schedule";
                var table = document.querySelector(".schedule-table");

                if (!table) {
                    console.error("Table not found.");
                    return;
                }

                var workbook = new ExcelJS.Workbook();
                var worksheet = workbook.addWorksheet(sheetName);

                // Set page setup for A4 and fit to 1 page
                worksheet.pageSetup = {
                    paperSize: 9, // 9 = A4
                    orientation: 'portrait',
                    fitToPage: true,
                    fitToWidth: 1,
                    fitToHeight: 1,
                    margins: {
                        left: 0.3,
                        right: 0.3,
                        top: 0.5,
                        bottom: 0.5,
                        header: 0.2,
                        footer: 0.2
                    }
                };

                // Function to add a merged row
                function addMergedRow(text, rowNumber) {
                    worksheet.mergeCells(rowNumber, 1, rowNumber, 7); // Merge from column 1 to 7
                    let row = worksheet.getRow(rowNumber);
                    row.getCell(1).value = text;
                    row.getCell(1).alignment = {
                        horizontal: "center",
                        vertical: "middle"
                    };
                }

                let rowIndex = 2; // Start from row 1

                addMergedRow("Republic of the Philippines", rowIndex++);
                addMergedRow("Cavite State University", rowIndex++);
                addMergedRow("Don Severino de las Alas Campus", rowIndex++);
                addMergedRow("Indang, Cavite", rowIndex++);
                rowIndex++; // Add space
                addMergedRow(collegeName, rowIndex++);
                addMergedRow(deptName, rowIndex++); // Dynamic department name
                rowIndex++; // Add space
                addMergedRow(sectionId + " CLASS SCHEDULE", rowIndex++); // Dynamic section
                addMergedRow(semester + ", S.Y. " + academicYear, rowIndex++); // Dynamic semester and academic year
                rowIndex++; // Add space

                // Fetch image and convert it to Base64
                const imageUrl = "http://localhost/SchedSys3/images/cvsu_logo.png"; // Update with your actual URL
                const imageBase64 = await fetch(imageUrl)
                    .then(res => res.blob())
                    .then(blob => new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result.split(",")[1]);
                        reader.readAsDataURL(blob);
                    }));

                // Add an image to the worksheet
                const imageId = workbook.addImage({
                    base64: imageBase64,
                    extension: "png"
                });

                worksheet.addImage(imageId, {
                    tl: {
                        col: 1.9,
                        row: 1
                    }, // Position at D2 (0-based index)
                    ext: {
                        width: 60,
                        height: 50
                    }
                });

                // Convert table rows to worksheet with auto column width
                let columnWidths = [];
                Array.from(table.rows).forEach((row, rowIdx) => {
                    let excelRow = worksheet.addRow([]);
                    let colOffset = 0;
                    for (let colIndex = 0; colIndex < row.cells.length; colIndex++) {
                        let cell = row.cells[colIndex];
                        let cellValue = cell ? cell.innerText.replace(/<br\s*\/?>/g, "\n") : "";
                        let excelCell = excelRow.getCell(colIndex + 1 + colOffset);

                        // Handle colspan for tdcolspan
                        let colspan = 1;
                        if (cell && cell.classList.contains('tdcolspan')) {
                            colspan = parseInt(cell.getAttribute('colspan')) || 1;
                            if (colspan > 1) {
                                worksheet.mergeCells(excelRow.number, colIndex + 1 + colOffset, excelRow.number,
                                    colIndex + colspan + colOffset);
                            }
                        }

                        excelCell.value = cellValue;
                        excelCell.alignment = {
                            horizontal: "center",
                            vertical: "middle",
                            wrapText: true
                        };

                        // Set background fill color for each cell in row 12 (assuming columns 1 to 7)
                        if (rowIdx === 10) { // row 12 in Excel (0-based index)
                            for (let col = 1; col <= 7; col++) {
                                let cell12 = worksheet.getRow(12).getCell(col);
                                cell12.fill = {
                                    type: 'pattern',
                                    pattern: 'solid',
                                    fgColor: {
                                        argb: 'FFE8E8E8'
                                    }
                                };
                            }
                            worksheet.getRow(12).eachCell(cell => {
                                cell.font = {
                                    color: {
                                        argb: 'FF000000'
                                    },
                                    bold: true
                                };
                            });
                        }

                        excelCell.border = {
                            top: {
                                style: "thin"
                            },
                            left: {
                                style: "thin"
                            },
                            bottom: {
                                style: "thin"
                            },
                            right: {
                                style: "thin"
                            }
                        };

                        // Set background color from data-cell-color if available
                        if (cell) {
                            let cellColor = cell.getAttribute("data-cell-color");
                            if (cellColor && /^#([0-9A-F]{3}){1,2}$/i.test(cellColor)) {
                                let hex = cellColor.replace("#", "");
                                if (hex.length === 3) {
                                    hex = hex.split("").map(c => c + c).join("");
                                }
                                excelCell.fill = {
                                    type: "pattern",
                                    pattern: "solid",
                                    fgColor: {
                                        argb: `FF${hex.toUpperCase()}`
                                    }
                                };
                            }
                            columnWidths[colIndex + colOffset] = Math.max(columnWidths[colIndex + colOffset] ||
                                10, cellValue.length + 2);
                        }

                        // If colspan, skip the next columns accordingly
                        if (colspan > 1) {
                            colOffset += (colspan - 1);
                        }
                    }
                });

                // Set auto column width with a minimum value
                columnWidths.forEach((width, index) => {
                    worksheet.getColumn(index + 1).width = Math.max(10, width);
                });

                // Save the workbook
                const fileName = `Report for Section ${sectionId || "Unknown"}.xlsx`;
                const buffer = await workbook.xlsx.writeBuffer();
                const blob = new Blob([buffer], {
                    type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = fileName;
                link.click();
            }

            $('#shareSchedule').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget); // Button that triggered the modal

                // Retrieve the data attributes
                var sectionCode = button.data('section-code');
                var semester = button.data('semester');
                var ayCode = button.data('ay-code');

                // Set the hidden inputs' values
                $('#modalSectionCode').val(sectionCode);
                $('#modalSemester').val(semester);
                $('#modalAyCode').val(ayCode);

                // Automatically set the receiver's department code based on selected email
                $('#recipient_email').on('input', function () {
                    var selectedOption = $('#email_list option[value="' + $(this).val() + '"]');
                    var deptCode = selectedOption.data('dept-code');
                    $('#receiver_dept_code').val(deptCode || ''); // Set the dept code or empty if not found
                });
            });


            $(document).ready(function () {
                filterSectionBySchedule();

                // Only trigger the scheduleModal when clicking the table row, not the share button
                $('#scheduleTable').on('click', 'tr', function (event) {
                    if (!$(event.target).closest('.share-btn').length) {
                        var sectionId = $(this).data('section-id'); // Get section ID from data attribute
                        var semester = $('#search_semester')
                            .val(); // Get the selected semester from the dropdown
                        var ay_code = $('#search_ay').val(); // Get the selected semester from the dropdown

                        // Ensure that a valid sectionId is present
                        if (sectionId) {
                            $('#scheduleModal').modal('show'); // Show the modal immediately
                            $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                            // Fetch schedule via AJAX
                            $.ajax({
                                url: 'lib_section.php',
                                type: 'GET',
                                data: {
                                    action: 'fetch_schedule',
                                    section_id: sectionId,
                                    semester: semester,
                                    ay_code: ay_code
                                },
                                success: function (response) {
                                    console.log("Response: ",
                                        response); // Log the response for debugging
                                    // Display the schedule in the modal
                                    $('#scheduleContent').html(response);
                                },
                                error: function () {
                                    console.error('Failed to fetch schedule for section ID: ' +
                                        sectionId);
                                    $('#scheduleContent').html(
                                        '<p>Error loading schedule.</p>'); // Show error message
                                }
                            });
                        }
                    }
                });



                // Filter classrooms by selected semester
                function filterSectionBySchedule() {
                    var selectedSemester = $('#search_semester').val();
                    var selectedAY = $('#search_ay').val();

                    var rowsVisible = false;

                    $('#scheduleTable tbody tr').each(function () {
                        var row = $(this);
                        var sectionId = row.data('section-id');

                        $.ajax({
                            url: 'lib_section.php',
                            type: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                section_id: sectionId,
                                semester: selectedSemester,
                                ay_code: selectedAY,
                                dept_code: $('#dept_code').val()
                            },
                            success: function (response) {
                                if (response.trim().includes("No Available Section Schedule")) {
                                    row.hide();
                                } else {
                                    row.show();
                                    rowsVisible = true;
                                }
                            },
                            error: function () {
                                console.error('Failed to fetch schedule for section ID: ' +
                                    sectionId);
                            },
                            complete: function () {
                                // Show or hide "No Records Found" message
                                if (rowsVisible) {
                                    $('#noRecordsMessage').hide();
                                } else {
                                    $('#noRecordsMessage').show();
                                }
                            }
                        });
                    });
                }

                // Select All checkbox and individual checkboxes
                let main = document.getElementById('SelectAll');
                let checkboxes = document.querySelectorAll('.select-checkbox');
                let delBtn = document.querySelector('.del-btn');
                let pubBtn = document.querySelector('.pub-btn');

                // Function to toggle action buttons based on checkbox selections
                function toggleActionButtons() {
                    let anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
                    delBtn.style.display = anyChecked ? 'block' : 'none';
                    pubBtn.style.display = anyChecked ? 'block' : 'none';
                }

                // Event listener for "Select All" checkbox
                main.addEventListener('click', () => {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = main.checked;
                    });
                    toggleActionButtons(); // Update button visibility
                });

                // Event listener for individual checkbox changes
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        // Check if all checkboxes are selected to update "Select All" checkbox
                        let allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
                        main.checked = allChecked;

                        toggleActionButtons(); // Update button visibility
                    });
                });

                // jQuery alternative for Select All (if using jQuery)
                $('#SelectAll').on('click', function () {
                    let checked = $(this).is(':checked');
                    $('input[name="schedule_id"]').prop('checked', checked);
                    toggleActionButtons();
                });

                $('input[name="schedule_id"]').on('change', function () {
                    let anyChecked = $('input[name="schedule_id"]:checked').length > 0;
                    $('.del-btn, .pub-btn').toggle(anyChecked);
                });
            });


            $(document).ready(function () {
                $('#makePublicButton').on('click', function () {
                    var selectedSchedules = [];

                    // Collect selected schedule IDs along with prof_sched_code and semester
                    $('input[name="schedule_id"]:checked').each(function () {
                        var row = $(this).closest('tr');
                        var sectionSchedCode = row.find('input[name="section_sched_code"]').val();
                        var semester = row.find('input[name="semester"]').val();

                        selectedSchedules.push({
                            section_sched_code: sectionSchedCode,
                            semester: semester
                        });
                    });

                    if (selectedSchedules.length === 0) {
                        alert("Please select at least one schedule.");
                        return;
                    }

                    $.ajax({
                        url: 'lib_section.php',
                        method: 'POST',
                        data: {
                            action: 'make_public',
                            schedules: selectedSchedules
                        },
                        success: function (response) {
                            if (response.trim() !== "") {
                                alert(response);

                                window.location.href = 'lib_section.php';
                            }
                            location.reload();
                        },
                        error: function () {
                            alert("An error occurred while processing your request.");
                        }
                    });
                });
            });



            $(document).ready(function () {
                $('.lock-btn').on('click', function (event) {
                    event.stopPropagation(); // Remove this temporarily if needed for testing

                    var button = $(this);
                    var sectionSchedCode = button.data('section-sched-code');
                    var semester = button.data('semester');

                    console.log('Lock button clicked'); // Debugging line
                    console.log('sectionSchedCode:', sectionSchedCode);
                    console.log('semester:', semester);

                    $.ajax({
                        url: 'lib_section.php',
                        method: 'POST',
                        dataType: 'json', // Ensure the response is treated as JSON
                        data: {
                            action: 'public',
                            section_sched_code: sectionSchedCode,
                            semester: semester
                        },
                        success: function (response) {
                            if (response.status === 'public') {
                                // Show modal instead of alert
                                var successModal = new bootstrap.Modal(document.getElementById(
                                    'successModal'));
                                successModal.show();

                                button.hide();
                                button.siblings('.unlock-btn').show();
                            } else if (res.error) {
                                alert(res.error);
                            }
                        },
                    });
                });

                $('.unlock-btn').on('click', function (event) {
                    event.stopPropagation();

                    var button = $(this);
                    var sectionSchedCode = button.data('section-sched-code');
                    var semester = button.data('semester');

                    console.log('Unlock button clicked'); // Debugging line
                    console.log('sectionSchedCode:', sectionSchedCode);
                    console.log('semester:', semester);

                    $.ajax({
                        url: 'lib_section.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'private',
                            section_sched_code: sectionSchedCode,
                            semester: semester
                        },
                        success: function (response) {

                            if (response.status === 'private') {
                                // Show modal instead of alert
                                var privateModal = new bootstrap.Modal(document.getElementById(
                                    'privateModal'));
                                privateModal.show();

                                button.hide();
                                button.siblings('.lock-btn').show();
                            } else if (res.error) {
                                alert(res.error);
                            }
                        },
                    });
                });
            });
        </script>
</body>

</html>