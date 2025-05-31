<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

$dept_code = $_SESSION['dept_code'] ?? null;
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : 'Unknown';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
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
} else {
    // If the page is refreshed, reset to active_ay_code
    $selected_ay_code = $active_ay_code;
}

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

// Ensure the user is logged in as a Department Secretary
// Ensure the user is logged in as a Department Secretary or Department Chairperson
if (
    !isset($_SESSION['user_type']) ||
    ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'Department Chairperson')
) {
    header("Location: ../../login/login.php");
    exit();
}

if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
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



    $prof_sched_code = $_POST['prof_sched_code'];
    $semester = $_POST['semester'];
    $prof_code = $_POST['prof_code'];
    $dept_code = $_SESSION['dept_code'];
    $ay_code = $_POST['ay_code'];
    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

    // Fetch all rows first
    $sql = "SELECT * FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $prof_sched_code, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row; // Store all rows in an array
    }
    $stmt->close();

    // Process deletions after fetching all data
    foreach ($rows as $row) {
        $sec_sched_id = $row['sec_sched_id'];
        $section_code = $row['section_code'];
        $room_code = $row['room_code'];
        $class_type = $row['class_type'];
        $curriculum = $row['curriculum'];
        $dept_code = $_SESSION['dept_code'];
        $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
        $section_sched_code = $row['section_code'];
        $room_sched_code = $room_code . "_" . $ay_code;

        // echo "section:  $sanitized_section_sched_code<br>";
        // echo "room: $sanitized_room_sched_code<br>";
        // echo "prof: $sanitized_prof_sched_code<br><br>";

        // Step 1: Delete from professor's schedule table
        $sql_delete_prof = "DELETE FROM $sanitized_prof_sched_code WHERE sec_sched_id = ? AND semester = ?";
        $stmt_delete_prof = $conn->prepare($sql_delete_prof);
        $stmt_delete_prof->bind_param('ss', $sec_sched_id, $semester);
        if (!$stmt_delete_prof->execute()) {
            echo "Error deleting from Instructor's schedule: " . $stmt_delete_prof->error;
        }
        $stmt_delete_prof->close();
        // Step 2: Check if there are any remaining schedules for this course in the professor's schedule table
        $check_prof_counter_sql = "SELECT COUNT(*) AS prof_count FROM $sanitized_pcontact_sched_code WHERE prof_sched_code = ? AND semester = ? AND prof_code = ? AND dept_code = ?";
        $stmt_check_prof_counter = $conn->prepare($check_prof_counter_sql);
        $stmt_check_prof_counter->bind_param('ssss', $prof_sched_code, $semester, $prof_code, $dept_code);
        $stmt_check_prof_counter->execute();
        $stmt_check_prof_counter->bind_result($prof_count);
        $stmt_check_prof_counter->fetch();
        $stmt_check_prof_counter->close();

        if ($prof_count > 0) {
            // Delete schedules from the sanitized table with the same professor schedule code and semester
            $delete_schedule_sql = "DELETE FROM $sanitized_pcontact_sched_code WHERE semester = ? AND prof_sched_code = ? AND dept_code = ?";
            $stmt_delete_schedule = $conn->prepare($delete_schedule_sql);
            $stmt_delete_schedule->bind_param("sss", $semester, $prof_sched_code, $dept_code);

            if ($stmt_delete_schedule->execute()) {
                // echo "Updated consultation for prof_code: $prof_code<br>";
                // echo "Updated consultation for dept_code: $dept_code<br>";
                // echo "Updated consultation for semester: $semester<br><br>";
            }
            $stmt_delete_schedule->close();
        }

        // Step 3: Update the counter if necessary
        $update_counter_sql = "UPDATE tbl_pcontact_counter SET current_consultation_hrs = 0, extension_hrs = 0, research_hrs = 0 WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
        $stmt_update_counter = $conn->prepare($update_counter_sql);
        $stmt_update_counter->bind_param("sss", $prof_sched_code, $semester, $dept_code);

        if ($stmt_update_counter->execute()) {
            // Check if there are any remaining entries in the sanitized table for the same semester and prof_sched_code
            $check_entries_sql = "SELECT COUNT(*) as count FROM $sanitized_pcontact_sched_code WHERE semester = ? AND prof_sched_code = ? AND dept_code = ?";
            $stmt_check_entries = $conn->prepare($check_entries_sql);
            $stmt_check_entries->bind_param("sss", $semester, $prof_sched_code, $dept_code);
            $stmt_check_entries->execute();
            $result = $stmt_check_entries->get_result();
            $row = $result->fetch_assoc();

            if ($row['count'] == 0) {
                // If no entries remain, delete the entry in tbl_pcontact_schedstatus
                $delete_schedstatus_sql = "DELETE FROM tbl_pcontact_schedstatus WHERE semester = ? AND prof_sched_code = ? AND dept_code = ?";
                $stmt_delete_schedstatus = $conn->prepare($delete_schedstatus_sql);
                $stmt_delete_schedstatus->bind_param("sss", $semester, $prof_sched_code, $dept_code);

                // if ($stmt_delete_schedstatus->execute()) {
                //     echo "<script>
                //             alert('Schedule Deleted Successfully');
                //             window.location.href = 'contact_plot.php';
                //         </script>";
                // } else {
                //     echo "<script>
                //             alert('Schedule Deleted Successfully, but failed to delete the corresponding status.');
                //             window.location.href = 'contact_plot.php';
                //         </script>";
                // }
                // $stmt_delete_schedstatus->close();
            }
        }
        // else {
        //     echo "<script>
        //             alert('Failed to update contact hours in tbl_pcontact_counter.');
        //             window.location.href = 'contact_plot.php';
        //         </script>";
        // }     


        // Query to fetch the professor's current teaching and prep hours
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


            // Calculate new teaching and consultation hours after deletion
            $new_teaching_hours = $current_teaching_hours - $current_teaching_hours;
            $consultation_hrs = $new_teaching_hours / 3;


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


        echo "Current Teaching Hours: $new_teaching_hours<br>";
        echo "Prep Hours: $prep_hours<br>";
        echo "consultation hrs: $consultation_hrs<br>";
        echo "Semester: $semester<br>";

        $sql_year_level = "SELECT year_level FROM tbl_course WHERE course_code = ? AND program_code = ? ";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("ss", $course_code, $program_code);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();

        // Step 2: Check if there are any remaining schedules for this course in the professor's schedule table
        $check_course_counter_sql = "SELECT COUNT(*) AS course_count FROM tbl_assigned_course WHERE semester = ? AND prof_code = ? AND dept_code = ?";
        $stmt_check_course_counter = $conn->prepare($check_course_counter_sql);
        $stmt_check_course_counter->bind_param('sss', $semester, $prof_code, $dept_code);
        $stmt_check_course_counter->execute();
        $stmt_check_course_counter->bind_result($course_count);
        $stmt_check_course_counter->fetch();
        $stmt_check_course_counter->close();


        if ($course_count > 0) {
            $update_course_query = "UPDATE tbl_assigned_course 
                                    SET course_counter = GREATEST(course_counter - 1, 0) 
                                    WHERE prof_code = ? AND semester = ? AND dept_code = ?";
            $stmt_update_course = $conn->prepare($update_course_query);
            $stmt_update_course->bind_param('sss', $prof_code, $semester, $dept_code);

            if (!$stmt_update_course->execute()) {
                echo "Error updating course_counter in tbl_assigned_course: " . $stmt_update_course->error;
            }
            $stmt_update_course->close();

            // Debugging Output
            // echo "<pre>
            // echo $year_level;
            // Updated receiver prof_code (tbl_assigned_course): $prof_code
            // Updated receiver course_code (tbl_assigned_course): $course_code
            // Updated dept_code (tbl_assigned_course): $dept_code
            // Updated receiver semester (tbl_assigned_course): $semester
            // </pre>";
        } else {
            // echo "<pre>No records found for course_code: $course_code, semester: $semester, prof_code: $prof_code, dept_code: $dept_code</pre>";
        }


        if (isset($sanitized_room_sched_code)) {
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
                        $stmt_room->bind_param('ssss', $room_sched_code, $semester, $sec_sched_id, $dept_code);
                        $stmt_room->execute();
                        $result_room = $stmt_room->get_result();

                        if ($result_room->num_rows > 0) {
                            // Delete the room schedule
                            $sql_delete_room = "UPDATE $sanitized_room_sched_code 
                                                SET prof_code = '', prof_name = '' 
                                                WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                            $stmt_delete_room = $conn->prepare($sql_delete_room);
                            $stmt_delete_room->bind_param('ssss', $sec_sched_id, $semester, $dept_code, $section_sched_code);

                            if ($stmt_delete_room->execute()) {
                                echo "Room schedule record deleted successfully from $sanitized_room_sched_code.<br>";
                            } else {
                                echo "Error deleting room schedule record: " . $stmt_delete_room->error . "<br>";
                            }
                            $stmt_delete_room->close();

                            // Check if the room schedule table is empty
                            $sql_check_empty_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_check_empty_room = $conn->prepare($sql_check_empty_room);
                            $stmt_check_empty_room->bind_param('sss', $room_sched_code, $semester, $dept_code);
                            if ($stmt_check_empty_room->execute()) {
                                $result_check_empty_room = $stmt_check_empty_room->get_result();
                                $row_count_room = $result_check_empty_room->fetch_assoc()['row_count'];
                                $stmt_check_empty_room->close();

                                // If no remaining room schedules, delete the room schedule status entry
                                if ($row_count_room == 0) {
                                    $sql_delete_room_status = "DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                                    $stmt_delete_room_status = $conn->prepare($sql_delete_room_status);
                                    $stmt_delete_room_status->bind_param('ssss', $room_sched_code, $semester, $ay_code, $dept_code);
                                    if ($stmt_delete_room_status->execute()) {
                                        echo "Room schedule status deleted successfully for $room_sched_code.<br>";
                                    } else {
                                        echo "Error deleting from tbl_room_schedstatus: " . $stmt_delete_room_status->error . "<br>";
                                    }
                                    $stmt_delete_room_status->close();
                                }
                            } else {
                                echo "Error checking room schedule table: " . $stmt_check_empty_room->error . "<br>";
                            }
                        } else {
                            // echo "No matching records found in table: $sanitized_room_sched_code<br>";
                        }
                        $stmt_room->close();
                    } else {
                        // echo "Table $sanitized_room_sched_code does not exist. Skipping.<br>";
                    }
                }
            }
        }

        if (isset($sanitized_section_sched_code)) {
            if ($section_sched_code !== 'TBA') {

                // Fetch and delete from the section schedule table
                $sql_section = "SELECT * FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                $stmt_section = $conn->prepare($sql_section);
                $stmt_section->bind_param('ssss', $section_sched_code, $semester, $sec_sched_id, $dept_code);
                $stmt_section->execute();
                $result_section = $stmt_section->get_result();

                if ($result_section->num_rows > 0) {
                    // Delete the section schedule
                    $sql_delete_section = "UPDATE $sanitized_section_sched_code 
                                                SET prof_code = '', prof_name = '' 
                                                WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_sched_code = ?";
                    $stmt_delete_section = $conn->prepare($sql_delete_section);
                    $stmt_delete_section->bind_param('ssss', $sec_sched_id, $semester, $dept_code, $section_sched_code);

                    if ($stmt_delete_section->execute()) {
                        echo "Room schedule record deleted successfully from $sanitized_section_sched_code.<br>";
                    } else {
                        echo "Error deleting section schedule record: " . $stmt_delete_section->error . "<br>";
                    }
                    $stmt_delete_section->close();

                    // Check if the section schedule table is empty
                    $sql_check_empty_section = "SELECT COUNT(*) AS row_count FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ? AND dept_code = ?";
                    $stmt_check_empty_section = $conn->prepare($sql_check_empty_section);
                    $stmt_check_empty_section->bind_param('sss', $section_sched_code, $semester, $dept_code);
                    if ($stmt_check_empty_section->execute()) {
                        $result_check_empty_section = $stmt_check_empty_section->get_result();
                        $row_count_section = $result_check_empty_section->fetch_assoc()['row_count'];
                        $stmt_check_empty_section->close();

                        // If no remaining section schedules, delete the section schedule status entry
                        if ($row_count_section == 0) {
                            $sql_delete_section_status = "DELETE FROM tbl_section_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_section_status = $conn->prepare($sql_delete_section_status);
                            $stmt_delete_section_status->bind_param('ssss', $section_sched_code, $semester, $ay_code, $dept_code);
                            if ($stmt_delete_section_status->execute()) {
                                echo "Room schedule status deleted successfully for $section_sched_code.<br>";
                            } else {
                                echo "Error deleting from tbl_section_schedstatus: " . $stmt_delete_section_status->error . "<br>";
                            }
                            $stmt_delete_section_status->close();
                        }
                    } else {
                        echo "Error checking section schedule table: " . $stmt_check_empty_section->error . "<br>";
                    }
                } else {
                    // echo "No matching records found in table: $sanitized_section_sched_code<br>";
                }
                $stmt_section->close();
            } else {
                // echo "Table $sanitized_section_sched_code does not exist. Skipping.<br>";
            }
        }
    }



    // Select current teaching hours and consultation hours from tbl_psched_counter
    $select_psched_counter = "SELECT teaching_hrs, consultation_hrs FROM tbl_psched_counter WHERE prof_code = ? AND dept_code = ? AND semester = ?";
    $stmt_select_psched_counter = $conn->prepare($select_psched_counter);
    $stmt_select_psched_counter->bind_param('sss', $prof_code, $dept_code, $semester);
    $stmt_select_psched_counter->execute();
    $stmt_select_psched_counter->bind_result($teaching_hrs_before, $consultation_hrs_before);
    $stmt_select_psched_counter->fetch();
    $stmt_select_psched_counter->close();

    if ($teaching_hrs_before > 0) {
        // Update teaching_hrs in tbl_psched_counter by subtracting total_hours
        $update_psched_counter = "UPDATE tbl_psched_counter SET teaching_hrs = teaching_hrs - ? WHERE prof_code = ? AND dept_code = ? AND semester = ?";
        $stmt_update_psched_counter = $conn->prepare($update_psched_counter);
        $stmt_update_psched_counter->bind_param('dsss', $total_hours, $prof_code, $dept_code, $semester);
        if (!$stmt_update_psched_counter->execute()) {
            echo "Error updating teaching hours in tbl_psched_counter: " . $stmt_update_psched_counter->error;
        } else {
            // echo "Teaching hours updated successfully for prof_code: $prof_code<br>";
        }
        $stmt_update_psched_counter->close();

        // Calculate the number of consultation hours to deduct (1 consultation hour per 3 teaching hours)
        $consultation_hrs_to_deduct = floor($total_hours / 3);

        // echo "Consultation hrs to delete: $consultation_hrs_to_deduct";

        // Update consultation_hrs if there's at least 1 hour to deduct
        if ($consultation_hrs_to_deduct > 0) {
            $update_consultation_hrs = "UPDATE tbl_psched_counter SET consultation_hrs = consultation_hrs - ? WHERE prof_code = ? AND dept_code = ? AND semester = ?";
            $stmt_update_consultation_hrs = $conn->prepare($update_consultation_hrs);
            $stmt_update_consultation_hrs->bind_param('isss', $consultation_hrs_to_deduct, $prof_code, $dept_code, $semester);
            if (!$stmt_update_consultation_hrs->execute()) {
                echo "Error updating consultation hours in tbl_psched_counter: " . $stmt_update_consultation_hrs->error;
            } else {
                // echo "Consultation hours updated successfully by $consultation_hrs_to_deduct hours for prof_code: $prof_code<br>";
            }
            $stmt_update_consultation_hrs->close();
        }
    }

    // Check if the professor's schedule table is empty
    $sql_check_empty_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND dept_code = ? AND semester = ?";
    $stmt_check_empty_prof = $conn->prepare($sql_check_empty_prof);
    $stmt_check_empty_prof->bind_param('sss', $prof_sched_code, $dept_code, $semester);
    if ($stmt_check_empty_prof->execute()) {
        $result_check_empty_prof = $stmt_check_empty_prof->get_result();
        $row_count_prof = $result_check_empty_prof->fetch_assoc()['row_count'];
        $stmt_check_empty_prof->close();

        // echo "count prof (sanitized_prof_sched_code): $prof_sched_code<br>";
        // echo "count dept_code  (sanitized_prof_sched_code): $dept_code<br>";
        // echo "semester count  (sanitized_prof_sched_code): $semester<br><br>";

        if ($row_count_prof == 0) {
            // Delete from tbl_prof_schedstatus
            $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
            $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $dept_code);
            if (!$stmt_delete_schedstatus->execute()) {
                echo "Error deleting from tbl_prof_schedstatus: " . $stmt_delete_schedstatus->error;
            }
            $stmt_delete_schedstatus->close();

            // echo "deleted in tbl_prof_schedstatus: $prof_sched_code<br>";
            // echo "deleted in tbl_prof_schedstatus: $dept_code<br>";
            // echo "deleted in tbl_prof_schedstatus: $semester<br>";
        }
    }


    echo "Schedule deleted successfully.";
    header("Location: lib_professor.php");
    exit();
}

// Fetch the schedules based on filtering criteria
$sql = "
    SELECT DISTINCT tbl_prof_schedstatus.prof_sched_code, 
           tbl_prof_schedstatus.semester, 
           tbl_prof_schedstatus.dept_code, 
           tbl_prof_schedstatus.ay_code, 
           tbl_psched.prof_code, 
           tbl_prof.prof_name 
    FROM tbl_prof_schedstatus
    INNER JOIN tbl_psched ON tbl_prof_schedstatus.prof_sched_code = tbl_psched.prof_sched_code 
    INNER JOIN tbl_prof ON tbl_psched.prof_code = tbl_prof.prof_code
    WHERE tbl_prof_schedstatus.status IN ('completed', 'public', 'private', 'draft') 
    AND tbl_prof_schedstatus.ay_code = ? 
    AND tbl_prof_schedstatus.semester = ? 
    AND tbl_psched.prof_code COLLATE utf8mb4_general_ci LIKE ? 
    AND tbl_prof_schedstatus.dept_code = ?";

$search_prof = isset($_POST['search_prof']) ? '%' . $_POST['search_prof'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $ay_code, $semester, $search_prof, $dept_code);
$stmt->execute();
$result = $stmt->get_result();


if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['prof_id'])) {
    $prof_id = $_GET['prof_id'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    // $selected_ay_code = $_SESSION['selected_ay_code'];


    $sql = "SELECT * FROM tbl_psched WHERE prof_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $prof_id, $dept_code, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $prof_sched_code = $row['prof_sched_code'];
        echo "<script>document.getElementById('scheduleModalLabel').innerHTML = 'Schedule for Instructor: " . htmlspecialchars($prof_id) . "';</script>";
        echo fetchScheduleForProf($prof_sched_code, $ay_code, $semester);
    } else {
        echo "<p>No schedule found for this Instructor.</p>";
    }

    $stmt->close();
    $conn->close();
    exit;
}


function fetchScheduleForProf($prof_sched_code, $ay_code, $semester)
{
    global $conn;
    $user_type = $_SESSION['user_type'];  // Get user_type from session

    $sql_fetch_prof_info = "
    SELECT p.*, d.dept_name, pr.prof_name, ps.prep_hrs, ps.teaching_hrs, a.ay_name,
           COALESCE(si.recommending, '') AS recommending,
           COALESCE(si.reviewed, '') AS reviewed,
           COALESCE(si.approved, '') AS approved,
           COALESCE(si.position_approved, '') AS position_approved,
           COALESCE(si.position_recommending, '') AS position_recommending,
           COALESCE(si.position_reviewed, '') AS position_reviewed
    FROM tbl_psched AS p
    INNER JOIN tbl_department AS d ON p.dept_code = d.dept_code
    INNER JOIN tbl_prof AS pr ON p.prof_code = pr.prof_code
    INNER JOIN tbl_psched_counter AS ps ON p.prof_code = ps.prof_code
    INNER JOIN tbl_ay AS a ON p.ay_code = a.ay_code
    LEFT JOIN tbl_signatory AS si ON d.dept_code = si.dept_code AND si.user_type = ?
    WHERE p.prof_sched_code = ? AND p.ay_code = ?";

    $stmt_prof_info = $conn->prepare($sql_fetch_prof_info);
    $stmt_prof_info->bind_param("sss", $user_type, $prof_sched_code, $ay_code);


    $stmt_prof_info->execute();
    $result_prof_info = $stmt_prof_info->get_result();

    // Check if any rows were returned
    if (!$result_prof_info || $result_prof_info->num_rows === 0) {
        return '<p>No Available Professor Schedule</p>';
    }

    // Fetch and assign values
    $row_prof_info = $result_prof_info->fetch_assoc();
    $dept_code = $row_prof_info['dept_code'];
    $_SESSION['dept_code'] = $dept_code;
    $prof_code = $row_prof_info['prof_code'];
    $dept_name = $row_prof_info['dept_name'];
    $prof_name = $row_prof_info['prof_name'];
    $no_prep_hrs = $row_prof_info['prep_hrs'];
    $teaching_hrs = $row_prof_info['teaching_hrs'];
    $ay_name = $row_prof_info['ay_name'];
    $recommending = $row_prof_info['recommending'];
    $reviewed = $row_prof_info['reviewed'];
    $approved = $row_prof_info['approved'];
    $position_approved = $row_prof_info['position_approved'];
    $position_recommending = $row_prof_info['position_recommending'];
    $position_reviewed = $row_prof_info['position_reviewed'];


    // Fetch the professor's full name from tbl_prof_acc and tbl_prof using prof_code
    $sql_fetch_prof_name = "
SELECT p.prof_name, pa.designation 
FROM tbl_prof_acc AS pa
INNER JOIN tbl_prof AS p ON pa.prof_code = p.prof_code
WHERE p.prof_code = ?";

    $stmt_prof_name = $conn->prepare($sql_fetch_prof_name);
    $stmt_prof_name->bind_param("s", $prof_code);
    $stmt_prof_name->execute();
    $result_prof_name = $stmt_prof_name->get_result();

    $prof_name = '';
    if ($result_prof_name->num_rows > 0) {
        $row_prof_name = $result_prof_name->fetch_assoc();
        $prof_name = $row_prof_name['prof_name'] ?? '';

        $designation = $row_prof_name['designation'] ?? 'No Designation';
    } else {
        $prof_name = $prof_code;
    }


    // Sanitize schedule table name based on department and academic year
    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $dept_code . "_" . $ay_code);

    // Fetch professor schedule with section code from tbl_secschedlist
    $sql_fetch_p_schedule = "
    SELECT ps.*, 
           se.section_code, 
           ns.no_students
    FROM $sanitized_psched_code AS ps
    INNER JOIN tbl_secschedlist AS se 
        ON ps.section_code = se.section_sched_code
    LEFT JOIN tbl_no_students AS ns 
        ON se.section_code = ns.section_code  AND ps.course_code = ns.course_code AND ps.prof_code = ns.prof_code
    WHERE ps.semester = ? 
      AND ps.prof_sched_code = ?";


    $stmt_p_schedule = $conn->prepare($sql_fetch_p_schedule);

    if (!$stmt_p_schedule) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt_p_schedule->bind_param("ss", $semester, $prof_sched_code);
    $stmt_p_schedule->execute();
    $result_p_schedule = $stmt_p_schedule->get_result();

    $schedule_data = [];

    // Process schedule results
    while ($row_schedule = $result_p_schedule->fetch_assoc()) {
        $day = ucfirst(strtolower($row_schedule['day']));
        $section_sched_code = $row_schedule['section_code'];
        $no_students = isset($row_schedule['no_students']) ? $row_schedule['no_students'] : ''; // Set default if not available

        // Fetch the section code and format it
        $fetch_info_query = "SELECT section_sched_code FROM tbl_secschedlist WHERE section_code = ?";
        $stmt_section = $conn->prepare($fetch_info_query);
        $stmt_section->bind_param("s", $section_sched_code);
        $stmt_section->execute();
        $result_section = $stmt_section->get_result();

        $section_sched_code = '';
        if ($result_section->num_rows > 0) {
            $row_section = $result_section->fetch_assoc();
            $section_sched_code = $row_section['section_sched_code'];
        }

        // Fetch the cell color from tbl_schedstatus based on section_code
        $fetch_color_query = "SELECT cell_color FROM $sanitized_psched_code WHERE section_code = ? AND dept_code = ? AND semester = ?";
        $stmt_color = $conn->prepare($fetch_color_query);
        $stmt_color->bind_param("sss", $section_sched_code, $dept_code, $semester);
        $stmt_color->execute();
        $result_color = $stmt_color->get_result();

        $cell_color = '';
        if ($result_color->num_rows > 0) {
            $row_color = $result_color->fetch_assoc();
            $cell_color = $row_color['cell_color'];
        }

        // Here, we're adding the cell color to the schedule data (sanitized_psched_code)
        // Prepare the data array to be used in your schedule
        $schedule_data[$day][] = [
            'time_start' => $row_schedule['time_start'],
            'time_end' => $row_schedule['time_end'],
            'course_code' => isset($row_schedule['course_code']) ? $row_schedule['course_code'] : '',
            'room_code' => isset($row_schedule['room_code']) ? $row_schedule['room_code'] : '',
            'section_code' => isset($row_schedule['section_code']) ? $row_schedule['section_code'] : '',
            'class_type' => isset($row_schedule['class_type']) ? $row_schedule['class_type'] : '',
            'cell_color' => $cell_color, // Add the cell color to the schedule data
        ];
    }

    $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $row_prof_info['dept_code'] . "_" . $ay_code);

    try {
        $sql_fetch_pcontact_schedule = "SELECT pc.time_start, pc.time_end, pc.consultation_hrs_type, pc.day, pcc.current_consultation_hrs, pcc.research_hrs, pcc.extension_hrs 
                                        FROM $sanitized_pcontact_sched_code AS pc
                                        INNER JOIN tbl_pcontact_counter AS pcc ON pc.prof_sched_code = pcc.prof_sched_code
                                        WHERE pc.semester = ? AND pc.prof_sched_code = ?";
        $stmt_pcontact_schedule = $conn->prepare($sql_fetch_pcontact_schedule);
        $stmt_pcontact_schedule->bind_param("ss", $semester, $prof_sched_code);
        $stmt_pcontact_schedule->execute();
        $result_pcontact_schedule = $stmt_pcontact_schedule->get_result();

        $consultation_start_time = '';
        $consultation_end_time = '';
        $consultation_day = '';
        $consultation_loop = null;


        // Process each row
        // Process each row
        while ($row_schedule = $result_pcontact_schedule->fetch_assoc()) {
            $day = ucfirst(strtolower($row_schedule['day']));

            $fetch_color_query = "SELECT cell_color FROM tbl_schedstatus WHERE section_sched_code = ? AND dept_code = ? AND semester = ?";
            $stmt_color = $conn->prepare($fetch_color_query);
            $stmt_color->bind_param("sss", $row_schedule['section_code'], $dept_code, $semester);
            $stmt_color->execute();
            $result_color = $stmt_color->get_result();

            $cell_color = ''; // Initialize cell_color in case there is no result
            if ($result_color->num_rows > 0) {
                $row_color = $result_color->fetch_assoc();
                $cell_color = $row_color['cell_color'];
            }
            $consultation_loop = '';
            $formatted_start_time = '';
            $formatted_end_time = '';
            $consultation_day = '';
            $consultation_hrs = '';
            $research_hrs = '';
            $extension_hrs = '';

            // Day abbreviation mapping
            $days_abbr = [
                'Monday' => 'Mon',
                'Tuesday' => 'Tue',
                'Wednesday' => 'Wed',
                'Thursday' => 'Thu',
                'Friday' => 'Fri',
                'Saturday' => 'Sat',
                'Sunday' => 'Sun'
            ];

            // Check for 'Consultation Hours' and format times if present
            if ($row_schedule['consultation_hrs_type'] === 'Consultation Hours') {
                $consultation_start_time = $row_schedule['time_start'];
                $consultation_end_time = $row_schedule['time_end'];
                $consultation_day = $row_schedule['day'];

                // Convert day to abbreviated form
                $abbreviated_day = $days_abbr[$consultation_day] ?? $consultation_day;

                // Format start and end times to 12-hour format
                $formatted_start_time = date('g:i A', strtotime($consultation_start_time));
                $formatted_end_time = date('g:i A', strtotime($consultation_end_time));

                // Append consultation time with new lines for separation
                $consultation_entry = $abbreviated_day . ' ' . $formatted_start_time . ' to ' . $formatted_end_time;

                // Add entry to the consultation loop, ensuring new lines for each consultation
                $consultation_loop .= (!empty($consultation_loop) ? " - " : '') . $consultation_entry;
            }



            // Append the contact schedule data for this day, including the consultation hours and cell color
            $schedule_data[$day][] = [
                'time_start' => $row_schedule['time_start'],
                'time_end' => $row_schedule['time_end'],
                'course_code' => $row_schedule['course_code'] ?? '',
                'room_code' => $row_schedule['room_code'] ?? '',
                'consultation_hrs_type' => $row_schedule['consultation_hrs_type'] ?? '',
                'section_code' => $row_schedule['section_code'] ?? '',
                'class_type' => $row_schedule['class_type'] ?? '',
                'cell_color' => $cell_color,
                'consultation_hours' => $consultation_hrs ?? null,
                'research_hours' => $research_hrs ?? null,
                'extension_hours' => $extension_hrs ?? null,
                'formatted_start_time' => $formatted_start_time, // Include formatted start time
                'formatted_end_time' => $formatted_end_time,
            ];
        }
    } catch (Exception $e) {
        // Log the error if needed, but do not stop the rest of the process
        error_log($e->getMessage());
    }



    $consultation_hrs = isset($consultation_hrs) ? $consultation_hrs : '';
    $research_hrs = isset($research_hrs) ? $research_hrs : '';
    $extension_hrs = isset($extension_hrs) ? $extension_hrs : '';
    $formatted_start_time = isset($formatted_start_time) ? $formatted_start_time : '';
    $formatted_end_time = isset($formatted_end_time) ? $formatted_end_time : '';
    $consultation_day = isset($consultation_day) ? $consultation_day : '';

    // If schedule data is available, pass it to generateScheduleTable
    if (!empty($schedule_data)) {
        $designation = $designation ?? '';
        return generateScheduleTable(
            $schedule_data,
            $dept_name,
            $semester,
            $prof_code,
            $prof_name,
            $no_prep_hrs,
            $teaching_hrs,
            $ay_name,
            $consultation_hrs,
            $research_hrs,
            $extension_hrs,
            $formatted_start_time,
            $formatted_end_time,
            $consultation_day,
            $designation,
            $recommending,
            $reviewed,
            $approved,
            $prof_name,
            $position_approved,
            $position_recommending,
            $position_reviewed,
            $ay_code,
            $no_students,
            $consultation_loop

        );
    } else {
        return '<p>No Available Professor Schedule</p>';
    }


}

function generateScheduleTable(
    $schedule_data,
    $dept_name,
    $semester,
    $prof_code,
    $prof_name,
    $no_prep_hrs,
    $teaching_hrs,
    $ay_name,
    $consultation_hrs,
    $research_hrs,
    $extension_hrs,
    $formatted_start_time,
    $formatted_end_time,
    $consultation_day,
    $designation,
    $recommending,
    $reviewed,
    $approved,
    $prof_full_name,
    $position_approved,
    $position_recommending,
    $position_reviewed,
    $ay_code,
    $no_students,
    $consultation_loop
) {
    global $conn;
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
    <div><p style="text-align: right; font-family: Arial; font-size: 10px; font-style: italic;">VPAA-QF-11</p></div>
    <!-- Wrapper Section -->
    <div style="position: relative; text-align: center; padding-top: 10px; ">
    <!-- Image Section -->
    <div style="position: absolute; left: 180px; top: 0;">
        <img src="/SchedSys3/images/cvsu_logo.png" style="width: 70px; height: 60px;">
    </div>
    
    <!-- Text Section -->
    <div>
        <p style="margin: 0;"></p>
        <p style="text-align: center; font-size: 6px; margin: 0; font-family: "Century Gothic", Arial, sans-serif;">
            Republic of the Philippines
        </p>
        <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: \'Bookman Old Style\', serif;">
            CAVITE STATE UNIVERSITY
        </p>
        <p style="text-align: center; font-size: 8px; font-weight: bold; margin: 0; font-family: "Century Gothic", Arial, sans-serif;">
            Don Severino de las Alas Campus
        </p>
        <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: "Century Gothic", Arial, sans-serif;">
            Indang, Cavite
        </p>
<p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">'
        . htmlspecialchars($user_college_name) .
        '</p>
        <p style="text-align: center; font-size: 8px; margin-bottom: 10px; font-family: Arial, sans-serif;">' . htmlspecialchars($dept_name) . '</p>
        <p style="text-align: center; font-size: 11px; margin: 0; font-weight: bold;">FACULTY CLASS SCHEDULE</p>
        <p style="text-align: center; font-size: 10px; margin-bottom: 10px;">' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . ' </p>
        <!-- Professor Details and Date -->
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 8px;">
            <p style="margin: 0;">Name: <strong>' . htmlspecialchars(strtoupper($prof_full_name)) . '</strong></p>
        </div>
        <!-- Flexbox Section for Preparations and Contact Hours -->
        <div style="display: flex; align-items: center; margin-bottom: 10px; font-size: 8px;">
            <p style="margin: 0; margin-right: 300px;">No. of Preparation/s: <strong>' . htmlspecialchars(strtoupper($no_prep_hrs)) . '</strong></p>
            <p style="margin: 0;">Total no. of contact hours per week: <strong>' . htmlspecialchars(strtoupper($teaching_hrs)) . '</strong></p>
        </div>
        </div>
    </div>
</div>

';

    $html .= '<div class="schedule-table-container" style="width: 100%; display: flex; justify-content: center; margin: 0 auto;">'; // Adjusted font size
    $html .= '<table class="table schedule-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; overflow-x: auto; padding: 3px;" 
    data-prof="' . htmlspecialchars($prof_code) . '" 
    data-profname="' . htmlspecialchars($prof_full_name) . '" 
    data-college="' . htmlspecialchars($user_college_name) . '" 
    data-department="' . htmlspecialchars($dept_name) . '" 
    data-semester="' . htmlspecialchars($semester) . '" 
    data-ayname="' . htmlspecialchars($ay_name) . '" 
    data-preparation="' . htmlspecialchars($no_prep_hrs) . '" 
    data-teaching="' . htmlspecialchars($teaching_hrs) . '">';
    $html .= '<thead>';

    $html .= '<tr>';
    $html .= '<th style="font-size: 7px; width: 20%; text-align: center; padding: 3px; background-color:rgb(232, 232, 232);">Time</th>';

    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 10%; text-align: center; font-size: 7px; padding: 3px; background-color:rgb(232, 232, 232);">' . $day_name . '</th>';
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



    $remaining_rowspan = array_fill_keys($day_names, 0);
    $subject_counts = [];
    $class_type_counts = [];

    foreach ($time_slots as $slot) {
        $start_time = $slot['start'];
        $end_time = $slot['end'];
        $start_time_formatted = formatTime($start_time);
        $end_time_formatted = formatTime($end_time);

        $html .= '<tr>';
        $html .= '<td class="time-slot" style=" text-align: center; font-size: 7px; padding: 3px;">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

        foreach ($day_names as $day_name) {
            if ($remaining_rowspan[$day_name] > 0) {
                $remaining_rowspan[$day_name]--;
            } else {
                $cell_content = '';
                $rowspan = 1;
                $cell_color = '';

                if (isset($schedule_data[$day_name])) {
                    foreach ($schedule_data[$day_name] as $index => $schedule) {
                        $schedule_start = strtotime($schedule['time_start']);
                        $schedule_end = strtotime($schedule['time_end']);
                        $current_start = strtotime($start_time);
                        $current_end = strtotime($end_time);

                        if ($current_start < $schedule_end && $current_end > $schedule_start) {
                            $section_code = isset($schedule['section_code']) ? $schedule['section_code'] : '';
                            $class_type = $schedule['class_type'];

                            // Convert class_type for display
                            $class_type_display = ($class_type === 'lec') ? 'Lecture' : (($class_type === 'lab') ? 'Laboratory' : $class_type);
                            $room_code = (empty($schedule['room_code']) || $schedule['room_code'] === null) ? null : $schedule['room_code'];

                            // Collect subject counts
                            if (!isset($subject_counts[$schedule['course_code']])) {
                                $subject_counts[$schedule['course_code']] = [
                                    'lectures' => 0,
                                    'laboratories' => 0,
                                    'rooms' => [],
                                ];
                            }

                            // Count lectures and labs
                            if ($class_type_display === 'Lecture') {
                                $subject_counts[$schedule['course_code']]['lectures']++;
                            } elseif ($class_type_display === 'Laboratory') {
                                $subject_counts[$schedule['course_code']]['laboratories']++;
                            }

                            if (!in_array($room_code, $subject_counts[$schedule['course_code']]['rooms'])) {
                                $subject_counts[$schedule['course_code']]['rooms'][] = $room_code;
                            }

                            // Count class types
                            if (!isset($class_type_counts[$class_type])) {
                                $class_type_counts[$class_type] = 0;
                            }
                            $class_type_counts[$class_type]++;

                            // Prepare cell content with course code, section, and class type
                            if (!empty($schedule['consultation_hrs_type'])) {
                                // Display consultation hours separately, without including in the class type display
                                $cell_content = "<span style='font-size: 8px; display: block; text-align: center; padding: 3px;'><b>{$schedule['consultation_hrs_type']}</b></span>";
                            } elseif ($class_type_display !== 'Lecture' && $class_type_display !== 'Laboratory') {
                                // If it's not Lecture or Laboratory, display course code, room, section, and class type
                                $cell_content = "<span style='font-size: 8px; display: block; text-align: center; padding: 3px;'><b>{$schedule['course_code']}<br></b>{$schedule['room_code']}<br>{$schedule['section_code']}<br>{$class_type_display}</span>";
                            } else {
                                // For lectures and labs, just display course, room, and section info
                                $cell_content = "<span style='font-size: 8px; display: block; text-align: center; padding: 3px;'><b>{$schedule['course_code']}<br></b>{$schedule['room_code']}<br>{$schedule['section_code']}<br>{$class_type_display}</span>";
                            }

                            $intervals = ($schedule_end - $schedule_start) / 1800;
                            $rowspan = max($rowspan, $intervals);
                            $cell_color = $schedule['cell_color'];
                            break;
                        }
                    }
                }

                if ($rowspan > 1) {
                    $remaining_rowspan[$day_name] = $rowspan - 1;
                }

                $html .= '<td ' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') .
                    ' style="background-color: ' . $cell_color . ';"' .
                    ' data-cell-color="' . htmlspecialchars($cell_color) . '">' .
                    $cell_content .
                    '</td>';
            }
        }



        $html .= '</tr>';
    }


    $subject_list = [];
    $class_type_counts = [];
    $total_lec_hours = 0; // Initialize total lecture hours
    $total_lab_hours = 0; // Initialize total lab hours

    foreach ($schedule_data as $day => $schedules) {
        foreach ($schedules as $schedule) {
            // Check if it's a consultation hour type and skip counting lec/lab hours if true
            if (!empty($schedule['consultation_hrs_type'])) {
                // Skip counting lec and lab hours if it's a consultation hour
                continue;
            }

            $class_type = $schedule['class_type'];
            $course_code = $schedule['course_code'];
            $section_code = $schedule['section_code'];
            $room_code = isset($schedule['room_code']) ? $schedule['room_code'] : '';


            // Initialize the course entry if it doesn't exist
            if (!isset($subject_list[$course_code])) {
                $subject_list[$course_code] = [
                    'course_code' => $course_code,
                    'sections' => [],
                    'rooms' => [],
                ];
            }

            // Initialize the section entry if it doesn't exist
            if (!isset($subject_list[$course_code]['sections'][$section_code])) {
                $subject_list[$course_code]['sections'][$section_code] = [
                    'lec_count' => 0,
                    'lab_count' => 0,
                    'rooms' => [],
                ];
            }

            // Calculate the duration of the class
            $start_time = strtotime($schedule['time_start']);
            $end_time = strtotime($schedule['time_end']);
            $duration = ($end_time - $start_time) / 3600; // Convert seconds to hours

            // Update lecture or lab hours for the specific section and accumulate the total
            if ($class_type === 'lec') {
                $subject_list[$course_code]['sections'][$section_code]['lec_count'] += $duration;
                $total_lec_hours += $duration; // Add to total lecture hours
            } elseif ($class_type === 'lab') {
                $subject_list[$course_code]['sections'][$section_code]['lab_count'] += $duration;
                $total_lab_hours += $duration; // Add to total lab hours
            }

            // Add the room code if not already present for this section
            if (!in_array($room_code, $subject_list[$course_code]['sections'][$section_code]['rooms'])) {
                $subject_list[$course_code]['sections'][$section_code]['rooms'][] = $room_code;
            }
        }
    }

    // Start generating the HTML table
    $html .= '<tr>';
    $html .= '<th class="tdcolspan" colspan = "2" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">Course Code</th>';
    $html .= '<th class="tdcolspan" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">Section Code</th>';
    $html .= '<th class="tdcolspan" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">Lec Hours</th>';
    $html .= '<th class="tdcolspan" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">Lab Hours</th>';
    $html .= '<th class="tdcolspan" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">Room</th>';
    $html .= '<th class="tdcolspan" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px; background-color:rgb(232, 232, 232);">No. of Students</th>';
    $html .= '</tr>';


    // Loop through the courses and their sections
    foreach ($subject_list as $course_code => $course_data) {
        $rowspan = count($course_data['sections']); // Calculate how many rows the course code will span

        $first_row = true;

        foreach ($course_data['sections'] as $section_code => $section_data) {
            $rooms_str = implode(' | ', $section_data['rooms']); // Rooms for the specific section

            $html .= '<tr>';

            // Only show course code with rowspan on the first row
            if ($first_row) {
                $html .= '<td class="tdcolspan" colspan = "2" rowspan="' . $rowspan . '" style="vertical-align: middle; font-size: 8px; text-align: center; padding: 3px;">' . htmlspecialchars($course_code) . '</td>';
                $first_row = false; // Set flag to false after first row
            }

            $html .= '<td class="tdcolspan" style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_code) . '</td>'; // Section code
            $html .= '<td class="tdcolspan" style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_data['lec_count']) . '</td>'; // Lecture hours for this section
            $html .= '<td class="tdcolspan" style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_data['lab_count']) . '</td>'; // Lab hours for this section
            $html .= '<td class="tdcolspan" style="padding: 3px; font-size: 8px;">' . htmlspecialchars($rooms_str) . '</td>'; // Rooms for this section
            $html .= '<td class="tdcolspan" style="padding: 3px; font-size: 8px;">' . htmlspecialchars($no_students) . '</td>'; // Rooms for this section
            $html .= '</tr>';
        }
    }

    // Add a footer row for the total lecture and lab hours
    $html .= '<tr>';
    $html .= '<td class="tdcolspan" colspan="3"></td>';
    $html .= '<td class="tdcolspan" style="text-align: right; font-size: 8px; font-weight: bold; padding: 3px;">Total:</td>';
    $html .= '<td style="padding: 3px; font-size: 8px;">' .
        ($total_lec_hours == 0 ? '0' : htmlspecialchars($total_lec_hours) . ' ' . ($total_lec_hours == 1 ? 'hr' : 'hrs')) .
        '</td>'; // Total lecture hours
    $html .= '<td style="padding: 3px; font-size: 8px;">' .
        ($total_lab_hours == 0 ? '0' : htmlspecialchars($total_lab_hours) . ' ' . ($total_lab_hours == 1 ? 'hr' : 'hrs')) .
        '</td>'; // Total lab hours


    $html .= '<td style="padding: 3px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Consultation: <span>' . '  ' . htmlspecialchars($consultation_loop ?? '') . '</span></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Research: <span>' . '  ' . htmlspecialchars($research_hrs) . ' unit</span></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Designation: <span>' . '  ' . htmlspecialchars($designation) . '</span></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Extension: <span>' . '  ' . htmlspecialchars($extension_hrs) . ' unit</span></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Conforme:</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Recommending Approval:</td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($prof_full_name) . '</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($recommending) . '</td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Instructor</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($position_recommending) . '</td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Reviewed by:</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Approved by:</td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;"></td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($reviewed) . '</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($approved) . '</td>';
    $html .= '</tr>';

    $html .= '<tr class="noExport">';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($position_reviewed) . '</td>';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">' . htmlspecialchars($position_approved) . '</td>';
    $html .= '</tr>';


    $html .= '</tbody></table></div>';





    $html .= '<div class="p">SCHEDULE</div>';
    $html .= '<div class="signature-section">';

    // Conforme & Recommending Approval side by side
    $html .= '    <div class="signature-pair" style="display: flex; justify-content: space-between;">';
    $html .= '        <div class="signature-block" style="width: 48%;">';
    $html .= '            <p style="line-height: 1.5;">Consultation: ' . $consultation_loop . '</p>';
    $html .= '            <p>Designation: <span id="designationOutput">' . htmlspecialchars($designation) . '</span></p>';
    $html .= '            <p>Conforme:</p><br>';
    $html .= '            <p class="signature-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><strong>' . strtoupper(htmlspecialchars($prof_full_name)) . '</strong></p>';
    $html .= '            <p class="position-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><span>Instructor</span></p>';
    $html .= '        </div>';
    // Research & Extension block
    $html .= '        <div class="signature-block" style="width: 48%;">';
    $html .= '        <p>Research: <span>' . ($research_hrs > 0 ? htmlspecialchars($research_hrs) . ' ' . ($research_hrs == 1 ? 'unit' : 'units') : '') . '</span></p>';
    $html .= '        <p>Extension: <span>' . ($extension_hrs > 0 ? htmlspecialchars($extension_hrs) . ' ' . ($extension_hrs == 1 ? 'unit' : 'units') : '') . '</span></p>';
    $html .= '            <p>Recommending Approval:</p><br>';
    $html .= '            <p class="signature-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><strong>' . htmlspecialchars($recommending) . '</strong></p>';
    $html .= '            <p class="position-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><span>' . htmlspecialchars($position_recommending) . '</span></p>';
    $html .= '        </div>';
    $html .= '    </div>';

    // Reviewed by & Approved by side by side
    $html .= '    <div class="signature-pair">';
    $html .= '        <div class="signature-block">';
    $html .= '            <p>Reviewed by:</p><br>';
    $html .= '            <p class="signature-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><strong>' . htmlspecialchars($reviewed) . '</strong></p>';
    $html .= '            <p class="position-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><span>' . htmlspecialchars($position_reviewed) . '</span></p>';
    $html .= '        </div>';
    $html .= '        <div class="signature-block">';
    $html .= '            <p>Approved by:</p><br>';
    $html .= '            <p class="signature-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><strong>' . htmlspecialchars($approved) . '</strong></p>';
    $html .= '            <p class="position-name" style="line-height: 1.5; text-align: left; margin: 0; text-align: center;"><span>' . htmlspecialchars($position_approved) . '</span></p>';
    $html .= '        </div>';
    $html .= '    </div>';

    $html .= '</div>';





    $html .= '<div style="clear: both;"></div>';



    return $html;
}




// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'make_public') {
//     if (!empty($_POST['schedules'])) {
//         $schedules = $_POST['schedules'];
//         $messages = [];
//         $publicSchedules = 0;

//         foreach ($schedules as $schedule) {
//             $prof_sched_code = $schedule['prof_sched_code'];
//             $semester = $schedule['semester'];

//             // Check if the schedule exists in tbl_prof_schedstatus
//             $checkQuery = "SELECT * FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ?";
//             $stmt = $conn->prepare($checkQuery);
//             $stmt->bind_param('ss', $prof_sched_code, $semester);
//             $stmt->execute();
//             $result = $stmt->get_result();

//             if ($result->num_rows > 0) {
//                 // Update status to 'public' in tbl_prof_schedstatus
//                 $updateQuery = "UPDATE tbl_prof_schedstatus SET status = 'public' WHERE prof_sched_code = ? AND semester = ?";
//                 $stmt = $conn->prepare($updateQuery);
//                 $stmt->bind_param('ss', $prof_sched_code, $semester);
//                 $stmt->execute();

//                 if ($stmt->affected_rows > 0) {
//                     $publicSchedules++;

//                     // Check if there is a matching entry in tbl_pcontact_schedstatus
//                     $checkContactQuery = "SELECT * FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
//                     $stmt = $conn->prepare($checkContactQuery);
//                     $stmt->bind_param('ss', $prof_sched_code, $semester);
//                     $stmt->execute();
//                     $contactResult = $stmt->get_result();

//                     if ($contactResult->num_rows > 0) {
//                         // Update status to 'public' in tbl_pcontact_schedstatus
//                         $updateContactQuery = "UPDATE tbl_pcontact_schedstatus SET status = 'public' WHERE prof_sched_code = ? AND semester = ?";
//                         $stmt = $conn->prepare($updateContactQuery);
//                         $stmt->bind_param('ss', $prof_sched_code, $semester);
//                         $stmt->execute();
//                     }
//                 } 
//             } else {
//                 $messages[] = "No matching schedules found for prof_sched_code: $prof_sched_code and semester: $semester.";
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

    // if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
    //     header("Location: " . $_SERVER['PHP_SELF']);
    //     exit;
    // }    
    // // Regenerate a new token to prevent reuse
    // $_SESSION['token'] = bin2hex(random_bytes(32));



    $prof_sched_code = $_POST['prof_sched_code'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $statusAction = $_POST['action']; // Either 'public' or 'private'

    if (!empty($prof_sched_code) && !empty($semester)) {
        // Join tbl_prof_schedstatus with tbl_psched to fetch prof_code and dept_code
        $checkQuery = "
            SELECT ps.*, p.prof_code, p.dept_code 
            FROM tbl_prof_schedstatus ps
            JOIN tbl_psched p ON ps.prof_sched_code = p.prof_sched_code 
            WHERE ps.prof_sched_code = ? AND ps.semester = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ss', $prof_sched_code, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $prof_code = $row['prof_code'];  // Fetch prof_code from tbl_psched
            $dept_code = $row['dept_code'];  // Fetch dept_code from tbl_psched

            // Update status based on action (either 'private' or 'public')
            $newStatus = $statusAction === 'private' ? 'private' : 'public';
            $updateQuery = "UPDATE tbl_prof_schedstatus SET status = ? WHERE prof_sched_code = ? AND semester = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('sss', $newStatus, $prof_sched_code, $semester);
            $statusUpdateSuccessful = $stmt->execute();

            if ($statusUpdateSuccessful) {
                // Check if there is a matching entry in tbl_pcontact_schedstatus
                $checkContactQuery = "SELECT * FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
                $stmt = $conn->prepare($checkContactQuery);
                $stmt->bind_param('ss', $prof_sched_code, $semester);
                $stmt->execute();
                $contactResult = $stmt->get_result();

                if ($contactResult->num_rows > 0) {
                    // Update status in tbl_pcontact_schedstatus
                    $updateContactQuery = "UPDATE tbl_pcontact_schedstatus SET status = ? WHERE prof_sched_code = ? AND semester = ?";
                    $stmt = $conn->prepare($updateContactQuery);
                    $stmt->bind_param('sss', $newStatus, $prof_sched_code, $semester);
                    $stmt->execute();
                }

                // Prepare notification message
                $notificationMessage = "The schedule for Instructor {$prof_code} has been changed to {$newStatus}.";
                $sender = $_SESSION['cvsu_email']; // Assuming sender's email is stored in the session

                // Insert notification for students
                $receiver = 'student';
                $insertNotificationQuery = "INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code) 
                                            VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?)";
                $notificationStmt = $conn->prepare($insertNotificationQuery);
                $notificationStmt->bind_param('sssssss', $notificationMessage, $prof_sched_code, $receiver, $sender, $prof_code, $semester, $dept_code);
                $notificationStmt->execute();

                // Insert notification for professors
                $receiver = 'professor';
                $notificationStmt->bind_param('sssssss', $notificationMessage, $prof_sched_code, $receiver, $sender, $prof_code, $semester, $dept_code);
                $notificationStmt->execute();

                // Return JSON response after updating status and sending notifications
                echo json_encode(['status' => $newStatus]);
            } else {
                echo json_encode(['error' => "Failed to update status."]);
            }
        } else {
            echo json_encode(['error' => "No matching schedules found."]);
        }
    } else {
        echo json_encode(['error' => "Missing required parameters."]);
    }
    exit;
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
    $prof_code = $_POST['prof_code'];
    $prof_sched_code = $_POST['prof_sched_code']; 

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

    $counter_check_sql = "SELECT COUNT(*) FROM tbl_pcontact_counter WHERE prof_sched_code = ? AND semester = ?";
    $stmt_counter_check = $conn->prepare($counter_check_sql);
    $stmt_counter_check->bind_param("ss", $prof_sched_code,$semester);
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
     
   
        header("Location: ../input_forms/contact_plot.php");
        exit();
    }
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Instructor Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>
    <link rel="stylesheet" href="../../../css/department_secretary/report/report_prof.css">
    <link rel="stylesheet" href="../../../css/department_secretary/navbar.css">
    <link rel="stylesheet" href="../../../css/department_secretary/library/lib_prof.css">
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
                <a class="nav-link" id="section-tab" href="lib_section.php" aria-controls="Section"
                    aria-selected="false">Section</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="lib_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="professor-tab" href="lib_professor.php" aria-controls="professor"
                    aria-selected="true">Instructor</a>
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
                        <form method="POST" action="lib_professor.php" class="row">

                            <div class="col-md-3 mb-3">
                                <select class="form-control" id="search_ay" name="search_ay">
                                    <?php foreach ($ay_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option['ay_code']); ?>" <?php echo ($selected_ay_code == $option['ay_code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['ay_name']); ?> <!-- Display ay_name -->
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
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
                                <input type="text" class="form-control" name="search_prof"
                                    value="<?php echo isset($_POST['search_prof']) ? htmlspecialchars($_POST['search_prof']) : ''; ?>"
                                    placeholder="Search" autocomplete="off">
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="submit" class="btn w-100">Search</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-container">
                    <table class="table" id="scheduleTable">
                        <thead>
                            <th></th>
                            <th>Instructor Code</th>
                            <th>Instructor Name</th>
                            <th></th>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-prof-id="<?php echo htmlspecialchars($row['prof_code']); ?>">
                                        <td style="width: 80px;">
                                            <div style="display: flex;">
                                                <!-- <input type="checkbox" class="select-checkbox" name="schedule_id" value="<?php echo htmlspecialchars($row['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();"> -->
                                                <?php if ($user_type != "Department Chairperson" && $row['ay_code'] == $active_ay_code) { ?>

                                                    <form method="POST" action="lib_professor.php">
                                                        <input type="hidden" name="prof_code"
                                                            value="<?php echo htmlspecialchars($row['prof_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="semester"
                                                            value="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="ay_code"
                                                            value="<?php echo htmlspecialchars($row['ay_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="status"
                                                            value="<?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="prof_sched_code"
                                                            value="<?php echo htmlspecialchars($row['prof_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                                                        <?php
                                                        // Fetch the current status from the database for the selected schedule
                                                        $query = "SELECT status FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ?";
                                                        $stmt = $conn->prepare($query);
                                                        $stmt->bind_param("ss", $row['prof_sched_code'], $row['semester']);
                                                        $stmt->execute();
                                                        $statusResult = $stmt->get_result();
                                                        $statusRow = $statusResult->fetch_assoc(); // Save status separately to avoid overwriting the original row
                                                        $currentStatus = $statusRow['status'] ?? '';
                                                        ?>

                                                        <!-- Display lock button if the status is not public -->
                                                        <button type="button" class="change-btn lock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-prof-sched-code="<?php echo htmlspecialchars($row['prof_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus == 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye-slash"></i>

                                                        </button>

                                        
                                                        <button type="button" class="change-btn unlock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-prof-sched-code="<?php echo htmlspecialchars($row['prof_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus != 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye"></i>
                                                        </button>
                                                    </form>  
                                                   
                                                <?php } ?>

                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['prof_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['prof_name']); ?></td>
                                        <td>
                                                <?php if ($user_type != "Department Chairperson" && $row['ay_code'] == $active_ay_code): ?>

        <form method="POST" action="lib_professor.php" style="display:inline;">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
            <input type="hidden" name="prof_code" value="<?php echo htmlspecialchars($row['prof_code']); ?>">
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($row['semester']); ?>">
            <input type="hidden" name="ay_code" value="<?php echo htmlspecialchars($row['ay_code']); ?>">
            <input type="hidden" name="prof_sched_code" value="<?php echo htmlspecialchars($row['prof_sched_code']); ?>">
            <!-- Delete Button -->
            <button type="submit" name="edit" class="edit-btn"><i class="fa-light fa-pencil mr-8"></i></button>
        </form>
        <?php endif; ?>

                                                <button type="button" class="delete-btn ml-5" data-bs-toggle="modal"
                                                    data-prof-code="<?php echo htmlspecialchars($row['prof_code']); ?>"
                                                    data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                    data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>"
                                                    data-prof-sched-code="<?php echo htmlspecialchars($row['prof_sched_code']); ?>"
                                                    data-bs-target="#deleteModal" onclick="event.stopPropagation();">
                                                    <i class="fa-light fa-trash"></i>
                                                </button>

                                            </form>

                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">No Records Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p id="noRecordsMessage" class="text-center" style="display: none;">No Records Found</p>
                </div>
            </div>
        </section>

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

    <!-- Combined Schedule and Prepared By Modal -->
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
                    <!-- Schedule content will be loaded here -->
                    <div id="scheduleContent"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn" style="background-color: #FD7238; color:#ffffff;" id="SchedulePDF">PDF</button>
                    <button class="btn" style="background-color: #FD7238; color:#ffffff;"
                        onclick="fnExportToExcel('xlsx', 'MySchedule')">Excel</button>
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
                    <form id="deleteForm" method="POST" action="lib_professor.php" style="display:inline;">
                        <input type="hidden" name="token" id="deleteToken">
                        <input type="hidden" name="prof_code" id="deleteProfCode">
                        <input type="hidden" name="semester" id="deleteSemester">
                        <input type="hidden" name="ay_code" id="deleteAyCode">
                        <input type="hidden" name="prof_sched_code" id="deleteProfSchedCode">
                        <button type="submit" name="action" value="delete" class="btn btn-danger">Confirm</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <script>
        function openScheduleModal(profId, semester, ayCode) {
            // Load schedule data via AJAX
            $.ajax({
                url: 'lib_professor.php',
                type: 'GET',
                data: {
                    action: 'fetch_schedule',
                    prof_id: profId,
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
                const profCode = button.getAttribute('data-prof-code');
                const semester = button.getAttribute('data-semester');
                const ayCode = button.getAttribute('data-ay-code');
                const profSchedCode = button.getAttribute('data-prof-sched-code');

                // Set the values in the form
                document.getElementById('deleteProfCode').value = profCode;
                document.getElementById('deleteSemester').value = semester;
                document.getElementById('deleteAyCode').value = ayCode;
                document.getElementById('deleteProfSchedCode').value = profSchedCode;
                document.getElementById('deleteToken').value = "<?php echo htmlspecialchars($_SESSION['token']); ?>";
            });
        });
    </script>
    <script>
        document.getElementById('SchedulePDF').addEventListener('click', function () {
            const element = document.getElementById('scheduleContent');
            const profNameElement = document.querySelector('p strong'); // This selects the <strong> tag inside the <p> with room details
            const profName = profNameElement ? profNameElement.textContent.trim() : "professor_schedule";

            const fileName = `${profName} Faculty Schedule Report` || prompt("Enter file name for the PDF:", "professor_schedule");

            // Create a div for the custom text (optional, customize as needed)
            const customTextDiv = document.createElement('div');
            customTextDiv.innerHTML = ``; // Add any specific custom text if needed

            // Prepend the custom text to the scheduleContent
            element.prepend(customTextDiv);

            // Generate PDF as a Blob
            html2pdf().from(element).set({
                margin: [0.5, 0.5, 0.5, 0.5],
                html2canvas: { scale: 3 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
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
            var profCode = document.querySelector(".schedule-table").dataset.prof;
            var profName = document.querySelector(".schedule-table").dataset.profname;
            var collegeName = document.querySelector(".schedule-table").dataset.college;
            var deptName = document.querySelector(".schedule-table").dataset.department;
            var semester = document.querySelector(".schedule-table").dataset.semester;
            var academicYear = document.querySelector(".schedule-table").dataset.ayname;
            var currentDate = new Date().toLocaleDateString();
            var no_prep_hrs = document.querySelector(".schedule-table").dataset.preparation;
            var teaching_hrs = document.querySelector(".schedule-table").dataset.teaching;
            var sheetName = profCode || "Schedule";
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
                margins: { left: 0.3, right: 0.3, top: 0.5, bottom: 0.5, header: 0.2, footer: 0.2 }
            };

            worksheet.getCell('G1').value = "VPAA-QF-11";
            worksheet.getCell('G1').alignment = { horizontal: "right", vertical: "middle" };
            worksheet.getCell('G1').font = { italic: true, size: 8, name: 'Arial' };

            // Function to add a merged row
            function addMergedRow(text, rowNumber) {
                worksheet.mergeCells(rowNumber, 1, rowNumber, 7);
                let row = worksheet.getRow(rowNumber);
                row.getCell(1).value = text;
                row.getCell(1).alignment = { horizontal: "center", vertical: "middle" };
            }

            let rowIndex = 2;
            addMergedRow("Republic of the Philippines", rowIndex++);
            addMergedRow("Cavite State University", rowIndex++);
            addMergedRow("Don Severino de las Alas Campus", rowIndex++);
            addMergedRow("Indang, Cavite", rowIndex++);
            rowIndex++;
            addMergedRow(collegeName, rowIndex++); // Updated to include the College Name
            addMergedRow(deptName, rowIndex++);
            rowIndex++;
            addMergedRow("FACULTY CLASS SCHEDULE", rowIndex++);
            addMergedRow(semester + ", SY " + academicYear, rowIndex++);
            rowIndex++;

            worksheet.mergeCells(rowIndex, 1, rowIndex, 3);
            let row = worksheet.getRow(rowIndex);
            row.getCell(1).value = "Name: " + profName;
            row.getCell(1).alignment = { horizontal: "left", vertical: "middle" };
            rowIndex++;

            worksheet.mergeCells(rowIndex, 1, rowIndex, 3);
            row = worksheet.getRow(rowIndex);
            row.getCell(1).value = "No. of Preparations: " + no_prep_hrs;
            row.getCell(1).alignment = { horizontal: "left", vertical: "middle" };

            // Merge columns D, E, F for "Total no. of contact hours per week"
            worksheet.mergeCells(rowIndex, 4, rowIndex, 7);
            row.getCell(4).value = "Total no. of contact hours per week: " + teaching_hrs;
            row.getCell(4).alignment = { horizontal: "left", vertical: "middle" };

            rowIndex++;

            try {
                const imageUrl = "http://localhost/SchedSys3/images/cvsu_logo.png";
                const imageBase64 = await fetch(imageUrl)
                    .then(res => res.blob())
                    .then(blob => new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result.split(",")[1]);
                        reader.onerror = reject;
                        reader.readAsDataURL(blob);
                    }));

                const imageId = workbook.addImage({
                    base64: imageBase64,
                    extension: "png"
                });

                worksheet.addImage(imageId, {
                    tl: { col: 1.9, row: 1 },
                    ext: { width: 60, height: 50 }
                });
            } catch (error) {
                console.error("Error loading image:", error);
            }

            let columnWidths = [];
            // Add cell color for thead (set to FFE8E8E8)
            // Handle thead
            Array.from(table.tHead.rows).forEach((row) => {
                let excelRow = worksheet.addRow([]);
                Array.from(row.cells).forEach((cell, colIndex) => {
                    let cellValue = cell.innerText.replace(/<br\s*\/?>/g, "\n");
                    let excelCell = excelRow.getCell(colIndex + 1);
                    excelCell.value = cellValue;
                    excelCell.alignment = { horizontal: "center", vertical: "middle", wrapText: true };
                    // Set background color for thead and make it bold
                    excelCell.fill = {
                        type: "pattern",
                        pattern: "solid",
                        fgColor: { argb: "FFE8E8E8" }
                    };
                    excelCell.font = { bold: true };
                    // Add border until column G
                    if (colIndex < 7) {
                        excelCell.border = {
                            top: { style: 'thin' },
                            left: { style: 'thin' },
                            bottom: { style: 'thin' },
                            right: { style: 'thin' }
                        };
                    }
                    columnWidths[colIndex] = Math.max(columnWidths[colIndex] || 10, cellValue.length + 2);
                });
            });

            // Handle tbody with colspan support
            Array.from(table.tBodies[0].rows).forEach((row) => {
                // Add noExport class as a comment in the first cell if present
                let isNoExport = row.classList.contains('noExport');
                let excelRow = worksheet.addRow([]);
                let colPointer = 1;
                Array.from(row.cells).forEach((cell, cellIndex) => {
                    let cellValue = cell.innerText.replace(/<br\s*\/?>/g, "\n");
                    let colspan = parseInt(cell.getAttribute("colspan") || "1", 10);
                    let excelCell = excelRow.getCell(colPointer);
                    excelCell.value = cellValue;
                    excelCell.alignment = { horizontal: "center", vertical: "middle", wrapText: true };

                    // Set background color if defined
                    let cellColor = cell.getAttribute("data-cell-color");
                    if (cellColor && /^#([0-9A-F]{3}){1,2}$/i.test(cellColor)) {
                        let hex = cellColor.replace("#", "");
                        if (hex.length === 3) {
                            hex = hex.split("").map(c => c + c).join("");
                        }
                        excelCell.fill = {
                            type: "pattern",
                            pattern: "solid",
                            fgColor: { argb: `FF${hex.toUpperCase()}` }
                        };
                    }

                    // If this cell has class tdcolspan and colspan > 1, merge cells
                    if (cell.classList.contains("tdcolspan") && colspan > 1) {
                        worksheet.mergeCells(
                            excelRow.number,
                            colPointer,
                            excelRow.number,
                            colPointer + colspan - 1
                        );
                    }

                    // Add border to all cells except those in noExport rows, but only until column G
                    if (!isNoExport && colPointer <= 7) {
                        excelCell.border = {
                            top: { style: 'thin' },
                            left: { style: 'thin' },
                            bottom: { style: 'thin' },
                            right: { style: 'thin' }
                        };
                    }

                    // If this is the first cell and row has noExport, add a comment
                    if (isNoExport && cellIndex === 0) {
                        excelCell.note = "noExport";
                    }

                    columnWidths[colPointer - 1] = Math.max(columnWidths[colPointer - 1] || 10, cellValue.length + 2);

                    colPointer += colspan;
                });
            });

            // Set auto column width with a minimum value
            columnWidths.forEach((width, index) => {
                worksheet.getColumn(index + 1).width = Math.max(10, width);
            });


            const fileName = `Report for ${profCode || "Unknown"}.xlsx`;
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            link.click();
        }

        $(document).ready(function () {
            filterProfBySchedule();

            // Attach click event to rows in the table to show the schedule modal
            $('#scheduleTable').on('click', 'tr', function () {
                var profId = $(this).data('prof-id'); // Get professor ID from the row's data attribute
                var semester = $('#search_semester').val(); // Get the selected semester value
                var ay_code = $('#search_ay').val(); // Get the selected semester value

                if (profId) {
                    // Load schedule content into the modal
                    $('#scheduleModal').modal('show'); // Show the modal immediately
                    $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                    // Make an AJAX request to fetch the schedule
                    $.ajax({
                        url: 'lib_professor.php', // Your backend script
                        method: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            prof_id: profId,
                            semester: semester,
                            ay_code: ay_code
                        },
                        success: function (response) {
                            console.log("Response: ", response); // Log the response for debugging
                            // Display the schedule in the modal
                            $('#scheduleContent').html(response);
                        },
                        error: function () {
                            console.error('Failed to fetch schedule for professor ID: ' + profId);
                            $('#scheduleContent').html('<p>Error loading schedule.</p>'); // Show error message
                        }
                    });
                }
            });

            // Function to filter professors by schedule based on selected semester
            function filterProfBySchedule() {
                var selectedSemester = $('#search_semester').val();
                var selectedAY = $('#search_ay').val();
                var rowsVisible = false;

                $('#scheduleTable tbody tr').each(function () {
                    var row = $(this);
                    var profId = row.data('prof-id'); // Get professor ID from the row's data attribute

                    $.ajax({
                        url: 'lib_professor.php',
                        method: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            prof_id: profId,
                            semester: selectedSemester,
                            ay_code: selectedAY,
                            dept_code: $('#dept_code').val() // Include dept_code if necessary
                        },
                        success: function (response) {
                            if (response.trim().includes("No Available Instructor Schedule")) {
                                row.hide(); // Hide row if no schedule is available
                            } else {
                                row.show(); // Show row if a schedule exists
                                rowsVisible = true;
                            }
                        },
                        error: function () {
                            console.error('Failed to fetch schedule for Instructor ID: ' + profId);
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
                    var profSchedCode = row.find('input[name="prof_sched_code"]').val();
                    var semester = row.find('input[name="semester"]').val();

                    selectedSchedules.push({
                        prof_sched_code: profSchedCode,
                        semester: semester
                    });
                });

                if (selectedSchedules.length === 0) {
                    alert("Please select at least one schedule.");
                    return;
                }

                $.ajax({
                    url: 'lib_professor.php',
                    method: 'POST',
                    data: {
                        action: 'make_public',
                        schedules: selectedSchedules
                    },
                    success: function (response) {
                        if (response.trim() !== "") {
                            alert(response);

                            window.location.href = 'lib_professor.php';
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
            // Handle lock button (public)
            $('.lock-btn').on('click', function (event) {
                event.stopPropagation();

                var button = $(this);
                var profSchedCode = $(this).data('prof-sched-code');
                var semester = $(this).data('semester');

                $.ajax({
                    url: 'lib_professor.php',
                    method: 'POST',
                    data: {
                        action: 'public',
                        prof_sched_code: profSchedCode,
                        semester: semester
                    },
                    success: function (response) {
                        var res = JSON.parse(response);
                        if (res.status === 'public') {
                            // Show modal instead of alert
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();

                            // Hide lock button and show unlock button
                            button.hide();
                            button.siblings('.unlock-btn').show();
                        }
                    },
                    error: function () {
                        alert("An error occurred while processing your request.");
                    }
                });
            });

            // Handle unlock button (private)
            $('.unlock-btn').on('click', function (event) {
                event.stopPropagation();

                var button = $(this);
                var profSchedCode = $(this).data('prof-sched-code');
                var semester = $(this).data('semester');

                $.ajax({
                    url: 'lib_professor.php',
                    method: 'POST',
                    data: {
                        action: 'private',
                        prof_sched_code: profSchedCode,
                        semester: semester
                    },
                    success: function (response) {
                        var res = JSON.parse(response);
                        if (res.status === 'private') {
                            // Show modal instead of alert
                            var privateModal = new bootstrap.Modal(document.getElementById('privateModal'));
                            privateModal.show();

                            // Hide unlock button and show lock button
                            button.hide();
                            button.siblings('.lock-btn').show();
                        }
                    },
                    error: function () {
                        alert("An error occurred while processing your request.");
                    }
                });
            });
        });
    </script>
</body>

</html>