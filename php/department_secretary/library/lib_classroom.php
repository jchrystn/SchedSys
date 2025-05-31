<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

$dept_code = $_SESSION['dept_code'] ?? null;
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : 'Unknown';
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
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



/* // Handle POST requests for Edit of Schedule
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit'])) {
    $_SESSION['room_sched_code'] = $_POST['room_sched_code'];
    $_SESSION['semester'] = $_POST['semester'];
    $_SESSION['ay_code'] = $_POST['ay_code'];
    $_SESSION['room_code'] = $_POST['room_code'];
    header("Location: ../create_sched/plotSchedule.php");
    exit();
}*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));


    $room_sched_code = $_POST['room_sched_code'];
    $semester = $_SESSION['semester'];
    $room_code = $_POST['room_code'];
    $dept_code = $_SESSION['dept_code'];
    $ay_code = $_POST['ay_code'];
    $sanitized_section_sched_code = null;
    $sanitized_prof_sched_code = null;

    // Query to fetch the room type from tbl_rsched
    $sql_room_type = "SELECT room_type FROM tbl_rsched WHERE room_sched_code = ?";
    $stmt_room_type = $conn->prepare($sql_room_type);
    $stmt_room_type->bind_param('s', $room_sched_code);
    $stmt_room_type->execute();
    $result_room_type = $stmt_room_type->get_result();

    if ($result_room_type->num_rows > 0) {
        $room_type = $result_room_type->fetch_assoc()['room_type'];
        $stmt_room_type->close();

        // Determine which sanitized_room_sched_code to use based on room type
        if ($room_type === "Computer Laboratory") {
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
        } else {
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        }

        // Proceed with fetching data from the determined room schedule table
        $sql = "SELECT * FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $room_sched_code, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sec_sched_id = $row['sec_sched_id'];
                $section_code = $row['section_code'];
                $prof_code = $row['prof_code'];
                $dept_code = $_SESSION['dept_code'];
                $class_type = $row['class_type'];
                $curriculum = $row['curriculum'] ?? '';
                $ay_code = $_POST['ay_code'];
                $section_sched_code = $row['section_code'];
                $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                $prof_sched_code = $prof_code . "_" . $ay_code;

                echo "Sanitized Section:  $sanitized_section_sched_code<br>";
                echo "Sanitized Room: $sanitized_room_sched_code<br>";
                echo "Sanitized Prof: $sanitized_prof_sched_code<br><br>";

                // Delete from room schedule table
                $sql_delete_room = "DELETE FROM $sanitized_room_sched_code WHERE sec_sched_id = ? AND semester = ?";
                $stmt_delete_room = $conn->prepare($sql_delete_room);
                $stmt_delete_room->bind_param('ss', $sec_sched_id, $semester);
                if (!$stmt_delete_room->execute()) {
                    echo "Error deleting from room schedule: " . $stmt_delete_room->error;
                }
                $stmt_delete_room->close();

                echo "Deleted Room Sched: $sanitized_room_sched_code<br>";
                echo "Deleted Room Sched sched id: $sec_sched_id<br>";
                echo "Deleted Room Sched semester: $semester<br>";

                // Delete from professor schedule table
                if ($prof_code !== "TBA") {
                    if ($sanitized_prof_sched_code) {
                        $dept_code = $_SESSION['dept_code'];

                        $fetch_hours_sql = "SELECT TIME_TO_SEC(TIMEDIFF(time_end, time_start)) / 3600 AS total_hours, course_code 
                                    FROM $sanitized_prof_sched_code 
                                    WHERE prof_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                        $stmt_fetch_hours = $conn->prepare($fetch_hours_sql);
                        if (!$stmt_fetch_hours) {
                            die("Prepare failed: " . $conn->error);
                        }
                        $stmt_fetch_hours->bind_param("ssss", $prof_code, $semester, $sec_sched_id, $dept_code);
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

                        // // Log current course_counter before update
                        // $select_course_counter = "SELECT course_counter FROM tbl_assigned_course WHERE course_code = ? AND prof_code = ? AND semester = ? AND dept_code = ?";
                        // $stmt_select_course_counter = $conn->prepare($select_course_counter);
                        // $stmt_select_course_counter->bind_param('ssss', $course_code, $prof_code, $semester, $dept_code);
                        // $stmt_select_course_counter->execute();
                        // $stmt_select_course_counter->bind_result($course_counter_before);
                        // $stmt_select_course_counter->fetch();
                        // $stmt_select_course_counter->close();

                        // echo "Prof Sched tbl_assigned_course Course Code: $course_code<br>";
                        // echo "Prof Sched tbl_assigned_course Prof Code: $prof_code<br>";
                        // echo "Prof Sched tbl_assigned_course Semester: $semester<br>";
                        // echo "Prof Sched tbl_assigned_course dept code: $dept_code<br>";
                        // echo "Prof Sched tbl_assigned_course course counter: $course_counter_before<br><br>";

                        // if ($course_counter_before > 0) {
                        //     $update_course_query = "UPDATE tbl_assigned_course SET course_counter = course_counter - 1 WHERE prof_code = ? AND course_code = ? AND semester = ? AND dept_code = ?";
                        //     $stmt_update_course = $conn->prepare($update_course_query);
                        //     $stmt_update_course->bind_param('ssss', $prof_code, $course_code, $semester, $dept_code);
                        //     if (!$stmt_update_course->execute()) {
                        //         echo "Error updating course_counter in tbl_assigned_course: " . $stmt_update_course->error;
                        //     } else {
                        //         echo "Course counter updated successfully for course_code: $course_code<br>";
                        //     }
                        //     $stmt_update_course->close();

                        //     echo "Updated Prof Sched tbl_assigned_course Course Code: $course_code<br>";
                        //     echo "Updated Prof Sched tbl_assigned_course Prof Code: $prof_code<br>";
                        //     echo "Updated Prof Sched tbl_assigned_course Semester: $semester<br>";
                        //     echo "Updated Prof Sched tbl_assigned_course dept code: $dept_code<br><br>";
                        // }

                        $sql_update_prof = " UPDATE $sanitized_prof_sched_code SET room_code = '' WHERE sec_sched_id = ? AND semester = ?";
                        $stmt_update_prof = $conn->prepare($sql_update_prof);
                        $stmt_update_prof->bind_param('ss', $sec_sched_id, $semester);
                        if (!$stmt_update_prof->execute()) {
                            echo "Error deleting from prof's schedule: " . $stmt_update_prof->error;
                        }
                        $stmt_update_prof->close();


                        // echo "Deleted Prof Sched: $sanitized_prof_sched_code<br>";
                        // echo "Deleted Prof Sched Sched Id: $sec_sched_id<br>";
                        // echo "Deleted Prof Sched Semester: $semester<br>";
                        // echo "Deleted Prof Sched Dept Code: $dept_code<br><br>";

                        //     $fetch_prof_hours_query = "SELECT * FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester'";
                        //     $prof_hours_result = $conn->query($fetch_prof_hours_query);

                        //     if ($prof_hours_result->num_rows > 0) {
                        //         $prof_hours_row = $prof_hours_result->fetch_assoc();
                        //         $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                        //         $prep_hours = $prof_hours_row['prep_hrs'];
                        //     }

                        //     // Calculate new teaching hours and consultation hours
                        //     $new_teaching_hours = $current_teaching_hours - $total_hours;
                        //     $consultation_hrs = $new_teaching_hours / 3;

                        //     echo "Current Teaching Hours: $current_teaching_hours<br>";
                        //     echo "Hours To Deduct: $total_hours<br>";
                        //     echo "New Teaching Hours: $new_teaching_hours<br>";
                        //     echo "Consultation Hours: $consultation_hrs<br>";

                        //     // Update query to set both teaching_hrs and consultation_hrs
                        //     $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' AND semester = '$semester' AND dept_code = '$dept_code'";
                        //     if ($conn->query($update_hours_query) === TRUE) {
                        //         echo "Teaching hours and consultation hours updated successfully.<br>";
                        //     } else {
                        //         echo "Error updating teaching hours: " . $conn->error . "<br>";
                        //     }
                        // } else {
                        //     die("Error: Professor details not found.");
                        // }

                        // // Check if a schedule entry exists for the professor after deletion
                        // $check_query = "SELECT * FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND course_code = ? AND semester = ? AND curriculum = ? AND class_type = ?";
                        // $stmt_check = $conn->prepare($check_query);
                        // $stmt_check->bind_param("sssss", $prof_sched_code, $course_code, $semester, $curriculum, $class_type);
                        // $stmt_check->execute();
                        // $check_result = $stmt_check->get_result();

                        // if ($check_result->num_rows === 0) {
                        //     // If no matching schedule entry is found, decrement prep_hours
                        //     $prep_hours -= 1;

                        //     // Update the prep_hours in the database
                        //     $update_prep_hours_query = "UPDATE tbl_psched_counter SET prep_hrs = ? WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                        //     $stmt_update_prep_hours = $conn->prepare($update_prep_hours_query);
                        //     $stmt_update_prep_hours->bind_param("dsss", $prep_hours, $prof_sched_code, $semester, $dept_code);

                        //     if ($stmt_update_prep_hours->execute()) {
                        //         echo "Prep hours updated successfully.<br>";
                        //     } else {
                        //         echo "Error updating prep hours: " . $stmt_update_prep_hours->error . "<br>";
                        //     }
                        // } else {
                        //     echo "Matching schedule entry exists, prep_hours remains the same.<br>";
                        // }


                        // Check if the schedule still exists in the professor's schedule table
                        $sql_check_prof_status = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_check_prof_status = $conn->prepare($sql_check_prof_status);
                        $stmt_check_prof_status->bind_param('sss', $prof_sched_code, $semester, $dept_code);
                        if (!$stmt_check_prof_status->execute()) {
                            echo "Error checking Instructor's schedule table: " . $stmt_check_prof_status->error;
                        } else {
                            $result_check_prof_status = $stmt_check_prof_status->get_result();
                            $row_count_prof_status = $result_check_prof_status->fetch_assoc()['row_count'];
                            $stmt_check_prof_status->close();

                            echo "Counted Prof Sched: $sanitized_prof_sched_code<br>";
                            echo "Counted Prof Sched Code: $prof_sched_code<br>";
                            echo "Counted Prof Sched Sched Id: $sec_sched_id<br>";
                            echo "Counted Prof Sched Semester: $semester<br>";
                            echo "Counted Prof Sched Dept Code: $dept_code<br><br>";

                            // If no schedule remains for this professor, delete the status in `tbl_prof_schedstatus`
                            if ($row_count_prof_status == 0) {
                                $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                                $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                                $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $dept_code);
                                if (!$stmt_delete_schedstatus->execute()) {
                                    echo "Error deleting from tbl_prof_schedstatus: " . $stmt_delete_schedstatus->error;
                                }
                                $stmt_delete_schedstatus->close();

                                echo "Deleted tbl_prof_schedstatus Prof Sched: $sanitized_prof_sched_code<br>";
                                echo "Deleted tbl_prof_schedstatus Prof Sched Code: $prof_sched_code<br>";
                                echo "Deleted tbl_prof_schedstatus Prof Sched Sched Id: $sec_sched_id<br>";
                                echo "Deleted tbl_prof_schedstatus Prof Sched Semester: $semester<br>";
                                echo "Deleted tbl_prof_schedstatus Prof Sched Dept Code: $dept_code<br><br>";
                            }
                        }

                        // Fetch schedules based on the receiver department code
                        if (!empty($receiver_dept_codes)) {
                            foreach ($receiver_dept_codes as $receiver_dept_code) {
                                // Create sanitized receiver prof schedule code
                                $sanitized_receiver_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");

                                // Check if the receiver schedule table exists (you can query the information schema or just check if it's not empty)
                                $check_table_sql = "SELECT 1 FROM $sanitized_receiver_prof_sched_code LIMIT 1";
                                $result = $conn->query($check_table_sql);

                                if ($result === false) {
                                    // Skip this receiver if the table doesn't exist or isn't available
                                    echo "Receiver table not found: $sanitized_receiver_prof_sched_code. Skipping...<br><br>";
                                    continue; // Skip this iteration and move to the next receiver_dept_code
                                }

                                echo "Sanitized Receiver Prof Sched: $sanitized_receiver_prof_sched_code<br><br>";

                                // Check if there are schedules in the receiver's professor schedule
                                $sql_check_shared_sched = "SELECT COUNT(*) AS row_count FROM $sanitized_receiver_prof_sched_code WHERE sec_sched_id = ? AND semester = ?";
                                $stmt_check_shared_sched = $conn->prepare($sql_check_shared_sched);
                                $stmt_check_shared_sched->bind_param('ss', $sec_sched_id, $semester);
                                $stmt_check_shared_sched->execute();
                                $stmt_check_shared_sched->bind_result($row_count_shared);
                                $stmt_check_shared_sched->fetch();
                                $stmt_check_shared_sched->close();

                                echo "Counted Receiver Prof Sched: $sanitized_receiver_prof_sched_code<br>";
                                echo "Counted Receiver Prof Sched sched id: $sec_sched_id<br>";
                                echo "Counted Receiver Prof Sched semester: $semester<br>";

                                // If records exist, proceed with the schedule updates
                                if ($row_count_shared > 0) {
                                    // Step 3: Fetch the total teaching hours to be subtracted and the course_code to update in tbl_assigned_course
                                    $fetch_hours_sql = "SELECT TIME_TO_SEC(TIMEDIFF(time_end, time_start)) / 3600 AS total_hours, course_code FROM $sanitized_receiver_prof_sched_code WHERE prof_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                                    $stmt_fetch_hours = $conn->prepare($fetch_hours_sql);
                                    $stmt_fetch_hours->bind_param("ssss", $prof_code, $semester, $sec_sched_id, $receiver_dept_code);
                                    $stmt_fetch_hours->execute();
                                    $stmt_fetch_hours->bind_result($total_hours, $course_code);
                                    $stmt_fetch_hours->fetch();
                                    $stmt_fetch_hours->close();

                                    echo "Fetched Receiver Prof Sched: $sanitized_receiver_prof_sched_code<br>";
                                    echo "Fetched  Receiver Prof Sched Semester: $semester<br>";
                                    echo "Fetched  Receiver Prof Sched Dept Code: $receiver_dept_code<br>";
                                    echo "Fetched  Receiver Prof Sched Prof Code: $prof_code<br><br>";

                                    // Ensure valid hours
                                    if ($total_hours > 0) {
                                        // Step 4: Delete from the receiver's schedule if records exist
                                        $sql_delete_shared = "UPDATE $sanitized_receiver_prof_sched_code SET room_code = '' WHERE sec_sched_id = ? AND semester = ? AND dept_code = ?";
                                        $stmt_delete_shared = $conn->prepare($sql_delete_shared);
                                        $stmt_delete_shared->bind_param('sss', $sec_sched_id, $semester, $receiver_dept_code);
                                        if (!$stmt_delete_shared->execute()) {
                                            echo "Error deleting from receiver's schedule table: " . $stmt_delete_shared->error;
                                        }
                                        $stmt_delete_shared->close();

                                        echo "Deleted Receiver Prof Sched: $sanitized_receiver_prof_sched_code<br>";
                                        echo "Deleted Receiver Prof Sched Sched Id: $sec_sched_id<br>";
                                        echo "Deleted Receiver Prof Sched Semester: $semester<br>";
                                        echo "Deleted Receiver Prof Sched Receiver Dept Code: $receiver_dept_code<br><br>";

                                        $fetch_prof_hours_query = "SELECT * FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$receiver_dept_code'";
                                        $prof_hours_result = $conn->query($fetch_prof_hours_query);

                                        if ($prof_hours_result->num_rows > 0) {
                                            $prof_hours_row = $prof_hours_result->fetch_assoc();
                                            $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                                            $prep_hours = $prof_hours_row['prep_hrs'];

                                            // Calculate new teaching hours and consultation hours
                                            $new_teaching_hours = $current_teaching_hours - $total_hours;
                                            $consultation_hrs = $new_teaching_hours / 3;

                                            echo "Current Teaching Hours: $current_teaching_hours<br>";
                                            echo "Hours To Deduct: $total_hours<br>";
                                            echo "New Teaching Hours: $new_teaching_hours<br>";
                                            echo "Consultation Hours: $consultation_hrs<br>";

                                            // Update query to set both teaching_hrs and consultation_hrs
                                            $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' AND semester = '$semester' AND dept_code = '$$receiver_dept_code'";
                                            if ($conn->query($update_hours_query) === TRUE) {
                                                echo "Teaching hours and consultation hours updated successfully.<br>";
                                            } else {
                                                echo "Error updating teaching hours: " . $conn->error . "<br>";
                                            }
                                        } else {
                                            die("Error: Instructor details not found.");
                                        }

                                        // Check if a schedule entry already exists for the professor
                                        $check_query = "SELECT * FROM $sanitized_prof_sched_code WHERE prof_sched_code = '$prof_sched_code' AND course_code = '$course_code' AND semester = '$semester' AND curriculum = '$curriculum' AND class_type = '$class_type'";
                                        $check_result = $conn->query($check_query);

                                        if ($check_result->num_rows === 0) {
                                            while ($row = $check_result->fetch_assoc()) {
                                                echo "<pre>";
                                                print_r($row);
                                                echo "</pre>";
                                            }
                                            $prep_hours = $prep_hours - 1;

                                            // Update prep_hours in the database
                                            $update_prep_hours_query = "UPDATE tbl_psched_counter SET prep_hrs = '$prep_hours' WHERE prof_sched_code = '$prof_sched_code' AND semester = '$semester' AND dept_code = '$receiver_dept_code'";
                                            if ($conn->query($update_prep_hours_query) === TRUE) {
                                                echo "Prep hours updated successfully.<br>";
                                            } else {
                                                echo "Error updating prep hours: " . $conn->error . "<br>";
                                            }
                                        } else {
                                            // If schedule entry exists, keep the prep_hours unchanged
                                            $prep_hours = $prep_hours;
                                        }

                                        // echo "Prep Hours: $prep_hours<br>";


                                        // Step 2: Check if there are any remaining schedules for this course in the professor's schedule table
                                        $check_course_counter_sql = "SELECT COUNT(*) AS course_count FROM tbl_assigned_course WHERE course_code = ? AND semester = ? AND prof_code = ? AND dept_code = ?";
                                        $stmt_check_course_counter = $conn->prepare($check_course_counter_sql);
                                        $stmt_check_course_counter->bind_param('ssss', $course_code, $semester, $prof_code, $receiver_dept_code);
                                        $stmt_check_course_counter->execute();
                                        $stmt_check_course_counter->bind_result($course_count);
                                        $stmt_check_course_counter->fetch();
                                        $stmt_check_course_counter->close();

                                        echo "Receiver Prof Sched tbl_assigned_course Course Code: $course_code<br>";
                                        echo "Receiver Prof Sched tbl_assigned_course Prof Code: $prof_code<br>";
                                        echo "Receiver Prof Sched tbl_assigned_course Semester: $semester<br>";
                                        echo "Receiver Prof Sched tbl_assigned_course Course Count: $$course_count<br>";
                                        echo "Receiver Prof Sched tbl_assigned_course dept code: $receiver_dept_code<br><br>";

                                        $update_course_query = "UPDATE tbl_assigned_course SET course_counter = course_counter - 1 WHERE prof_code = ? AND course_code = ? AND semester = ? AND dept_code = ?";
                                        $stmt_update_course = $conn->prepare($update_course_query);
                                        $stmt_update_course->bind_param('ssss', $prof_code, $course_code, $semester, $receiver_dept_code);
                                        if (!$stmt_update_course->execute()) {
                                            echo "Error updating course_counter in tbl_assigned_course: " . $stmt_update_course->error;
                                        }
                                        $stmt_update_course->close();

                                        echo "Updated Receiver Prof Sched tbl_assigned_course Course Code: $course_code<br>";
                                        echo "Updated Receiver Prof Sched tbl_assigned_course Prof Code: $prof_code<br>";
                                        echo "Updated Receiver Prof Sched tbl_assigned_course Semester: $semester<br>";
                                        echo "Updated Receiver Prof Sched tbl_assigned_course dept code: $receiver_dept_code<br><br>";
                                    }

                                    // Step 7: Check if there are no more shared schedules for this receiver
                                    $sql_check_remaining_sched = "SELECT COUNT(*) AS row_count FROM $sanitized_receiver_prof_sched_code WHERE semester = ? AND prof_sched_code = ? AND dept_code = ?";
                                    $stmt_check_remaining_sched = $conn->prepare($sql_check_remaining_sched);
                                    $stmt_check_remaining_sched->bind_param('sss', $semester, $prof_sched_code, $receiver_dept_code);
                                    $stmt_check_remaining_sched->execute();
                                    $stmt_check_remaining_sched->bind_result($row_count_remaining);
                                    $stmt_check_remaining_sched->fetch();
                                    $stmt_check_remaining_sched->close();


                                    // Step 8: If no remaining schedules, delete from tbl_shared_sched
                                    if ($row_count_remaining == 0) {
                                        $delete_shared_sched_sql = "DELETE FROM tbl_shared_sched WHERE receiver_dept_code = ? AND sender_dept_code = ?";
                                        $stmt_delete_shared_sched = $conn->prepare($delete_shared_sched_sql);
                                        $stmt_delete_shared_sched->bind_param('ss', $receiver_dept_code, $sender_dept_code);
                                        $stmt_delete_shared_sched->execute();
                                        $stmt_delete_shared_sched->close();

                                        echo "Deleted shared schedule between $sender_dept_code and $receiver_dept_code<br>";

                                        $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ?";
                                        $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                                        $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $receiver_dept_code);
                                        if (!$stmt_delete_schedstatus->execute()) {
                                            echo "Error deleting from tbl_prof_schedstatus: " . $stmt_delete_schedstatus->error;
                                        }
                                        $stmt_delete_schedstatus->close();

                                        // echo "Deleted tbl_prof_schedstatus Prof Sched: $sanitized_prof_sched_code<br>";
                                        // echo "Deleted tbl_prof_schedstatus Prof Sched Code: $prof_sched_code<br>";
                                        // echo "Deleted tbl_prof_schedstatus Prof Sched Sched Id: $sec_sched_id<br>";
                                        // echo "Deleted tbl_prof_schedstatus Prof Sched Semester: $semester<br>";
                                        // echo "Deleted tbl_prof_schedstatus Prof Sched Dept Code: $dept_code<br><br>";
                                    }
                                }
                            }
                        }
                    }
                }

                // Delete from section schedule table
                if ($sanitized_section_sched_code) {
                    $sql_shared_sched = "SELECT sender_dept_code FROM tbl_shared_sched WHERE receiver_dept_code = ? AND semester = ? AND ay_code = ?";
                    $stmt_shared_sched = $conn->prepare($sql_shared_sched);
                    $stmt_shared_sched->bind_param('sss', $receiver_dept_code, $semester, $ay_code);
                    $stmt_shared_sched->execute();
                    $stmt_shared_sched->bind_result($sender_dept_code);
                    $stmt_shared_sched->fetch();
                    $stmt_shared_sched->close();

                    echo "sender: $sender_dept_code<br>";
                    echo "receiver: $receiver_dept_code<br><br>";

                    if ($sender_dept_code) {
                        // Construct sanitized section schedule code for the sender's department
                        $sanitized_sender_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");

                        echo "sanitized sender: $sanitized_sender_section_sched_code<br><br>";

                        // Check if there are schedules in that table with the same sec_sched_id and semester
                        $sql_check_shared_sched = "SELECT COUNT(*) AS row_count FROM $sanitized_sender_section_sched_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ?";
                        $stmt_check_shared_sched = $conn->prepare($sql_check_shared_sched);
                        $stmt_check_shared_sched->bind_param('sss', $sec_sched_id, $semester, $sender_dept_code);
                        $stmt_check_shared_sched->execute();
                        $stmt_check_shared_sched->bind_result($row_count_shared);
                        $stmt_check_shared_sched->fetch();
                        $stmt_check_shared_sched->close();

                        echo "row count section (sanitized_sender_section_sched_code):  $section_sched_code<br>";
                        echo "row count ay_code (sanitized_sender_section_sched_code):  $sender_dept_code<br>";
                        echo "row count semester (sanitized_sender_section_sched_code): $semester<br><br>";

                        if ($row_count_shared > 0) {
                            // Update the shared schedule to set prof_name and prof_code to 'TBA' if it exists
                            $sql_update_shared = "UPDATE $sanitized_sender_section_sched_code 
                        SET room_code = ''
                        WHERE sec_sched_id = ? AND semester = ?";
                            $stmt_update_shared = $conn->prepare($sql_update_shared);
                            $stmt_update_shared->bind_param('ss', $sec_sched_id, $semester);

                            if (!$stmt_update_shared->execute()) {
                                echo "Error updating shared section's schedule table: " . $stmt_update_shared->error;
                            }

                            $stmt_update_shared->close();
                        }

                        echo "deleted sched status section (sanitized_sender_section_sched_code):  $section_sched_code<br>";
                        echo "deleted sched status ay_code (sanitized_sender_section_sched_code):  $ay_code<br>";
                        echo "deleted sched status semester (sanitized_sender_section_sched_code): $semester<br><br>";

                        // Check if the section schedule table is empty
                        $sql_check_empty_section = "SELECT COUNT(*) AS row_count FROM $sanitized_sender_section_sched_code WHERE section_sched_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_check_empty_section = $conn->prepare($sql_check_empty_section);
                        $stmt_check_empty_section->bind_param('sss', $section_sched_code, $semester, $receiver_dept_code);
                        $stmt_check_empty_section->execute();
                        $result_check_empty_section = $stmt_check_empty_section->get_result();
                        $row_count_section = $result_check_empty_section->fetch_assoc()['row_count'];
                        $stmt_check_empty_section->close();

                        echo "row count section (sanitized_sender_section_sched_code):  $section_sched_code<br>";
                        echo "row count dept code (sanitized_sender_section_sched_code):  $dept_code<br>";
                        echo "row count semester (sanitized_sender_section_sched_code): $semester<br><br>";

                        // If the table is empty, drop it and delete from tbl_schedstatus
                        if ($row_count_section == 0) {
                            // Delete from tbl_schedstatus if no schedules exist in the section schedule table
                            $sql_delete_schedstatus = "DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $dept_code);
                            if (!$stmt_delete_schedstatus->execute()) {
                                echo "Error deleting from tbl_schedstatus: " . $stmt_delete_schedstatus->error;
                            }
                            $stmt_delete_schedstatus->close();

                            echo "sched status section (sanitized_sender_section_sched_code):  $section_sched_code<br>";
                            echo "sched status semester (sanitized_sender_section_sched_code): $semester<br>";
                            echo "sched status semester (sanitized_sender_section_sched_code): $dept_code<br><br>";
                        }
                    } else {

                        // Update the record in the section schedule table to set prof_name and prof_code to 'TBA'
                        $sql_update_section = "UPDATE $sanitized_section_sched_code 
                                            SET room_code = ''
                                            WHERE semester = ? AND sec_sched_id = ?";
                        $stmt_update_section = $conn->prepare($sql_update_section);
                        $stmt_update_section->bind_param('ss', $semester, $sec_sched_id);

                        if (!$stmt_update_section->execute()) {
                            echo "Error updating section's schedule table: " . $stmt_update_section->error;
                        }

                        $stmt_update_section->close();

                        echo "section:  $section_sched_code<br>";
                        echo "ay_code:  $ay_code<br>";
                        echo "semester: $semester<br><br>";

                        // Check if the section schedule table is empty
                        $sql_check_empty_section = "SELECT COUNT(*) AS row_count FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_check_empty_section = $conn->prepare($sql_check_empty_section);
                        $stmt_check_empty_section->bind_param('sss', $section_sched_code, $semester, $dept_code);
                        $stmt_check_empty_section->execute();
                        $result_check_empty_section = $stmt_check_empty_section->get_result();
                        $row_count_section = $result_check_empty_section->fetch_assoc()['row_count'];
                        $stmt_check_empty_section->close();

                        echo "row count section ($sanitized_section_sched_code):  $section_sched_code<br>";
                        echo "row count dept code ($sanitized_section_sched_code):  $dept_code<br>";
                        echo "row count semester ($sanitized_section_sched_code): $semester<br><br>";

                        // If the table is empty, drop it and delete from tbl_schedstatus
                        if ($row_count_section == 0) {
                            // Delete from tbl_schedstatus if no schedules exist in the section schedule table
                            $sql_delete_schedstatus = "DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ?";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $dept_code);
                            if (!$stmt_delete_schedstatus->execute()) {
                                echo "Error deleting from tbl_schedstatus: " . $stmt_delete_schedstatus->error;
                            }
                            $stmt_delete_schedstatus->close();

                            echo "sched status section ($sanitized_section_sched_code):  $section_sched_code<br>";
                            echo "sched status semester($sanitized_section_sched_code): $semester<br>";
                            echo "sched status semester ($sanitized_section_sched_code): $dept_code<br><br>";
                        }
                    }
                }
            }
        }
    }


    // Check if the room schedule table is empty for the specific room_sched_code
    $sql_check_empty_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ?";
    $stmt_check_empty_room = $conn->prepare($sql_check_empty_room);
    $stmt_check_empty_room->bind_param('ss', $room_sched_code, $semester);  // Make sure to check for the same room_sched_code and semester
    if (!$stmt_check_empty_room->execute()) {
        echo "Error checking room schedule table: " . $stmt_check_empty_room->error;
    } else {
        $result_check_empty_room = $stmt_check_empty_room->get_result();
        $row_count_room = $result_check_empty_room->fetch_assoc()['row_count'];
        $stmt_check_empty_room->close();

        // If no remaining room schedules, delete the room_sched_status entry
        if ($row_count_room == 0) {
            // Delete from tbl_room_schedstatus
            $sql_delete_room_status = "DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ?";
            $stmt_delete_room_status = $conn->prepare($sql_delete_room_status);
            $stmt_delete_room_status->bind_param('sss', $room_sched_code, $semester, $ay_code);
            if (!$stmt_delete_room_status->execute()) {
                echo "Error deleting from tbl_room_schedstatus: " . $stmt_delete_room_status->error;
            }
            $stmt_delete_room_status->close();

            echo "room:  $room_sched_code<br>";
            echo "ay_code:  $ay_code<br>";
            echo "semester: $semester<br><br>";
        }
    }

    echo "Schedule deleted successfully.";
    header("Location: lib_classroom.php");
    exit();
}

// Fetch schedules based on filter criteria
$sql = "
    SELECT tbl_room_schedstatus.room_sched_code, tbl_room_schedstatus.semester, tbl_room_schedstatus.dept_code, tbl_room_schedstatus.ay_code, tbl_rsched.room_code , tbl_rsched.room_type
    FROM tbl_room_schedstatus 
    INNER JOIN tbl_rsched
    ON tbl_room_schedstatus.room_sched_code = tbl_rsched.room_sched_code 
    WHERE tbl_room_schedstatus.status IN ('draft', 'completed','public', 'private') 
    AND tbl_room_schedstatus.ay_code = ?
    AND tbl_room_schedstatus.semester = ?
    AND tbl_rsched.room_code COLLATE utf8mb4_general_ci LIKE ?
    AND tbl_room_schedstatus.dept_code = ?";

$search_room = isset($_POST['search_room']) ? '%' . $_POST['search_room'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $ay_code, $semester, $search_room, $dept_code);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    // $selected_ay_code = $_SESSION['selected_ay_code'];

    // Fetch the room schedule code
    $sql = "SELECT * FROM tbl_rsched WHERE room_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $room_id, $dept_code, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $room_sched_code = $row['room_sched_code'];
        echo "<script>document.getElementById('scheduleModalLabel').innerHTML = 'Schedule for Room: " . htmlspecialchars($room_id) . "';</script>";
        echo fetchScheduleForRoom($room_sched_code, $ay_code, $semester, $room_id);
    } else {
        echo "<p>No schedule found for this Classroom.</p>";
    }

    $stmt->close();
    $conn->close();
    exit;
}


function fetchScheduleForRoom($room_sched_code, $ay_code, $semester)
{
    global $conn;
    $dept_code = $_SESSION['dept_code'];
    $user_type = $_SESSION['user_type'];


    $signatory_check_query = "SELECT 1 FROM tbl_signatory WHERE dept_code = ? AND user_type = ?";
    $stmt_check = $conn->prepare($signatory_check_query);
    $stmt_check->bind_param("ss", $dept_code, $user_type);
    $stmt_check->execute();
    $signatory_exists = $stmt_check->get_result()->num_rows > 0;
    $stmt_check->close();

    // 2. Dynamically build SQL query
    $sql_fetch_room_info = "
    SELECT r.room_code, r.ay_code, r.dept_code, d.dept_name, a.ay_name, ro.room_in_charge";

    // Add signatory fields only if it exists
    if ($signatory_exists) {
        $sql_fetch_room_info .= ",
        COALESCE(si.recommending, '') AS recommending,
        COALESCE(si.reviewed, '') AS reviewed,
        COALESCE(si.approved, '') AS approved,
        COALESCE(si.position_approved, '') AS position_approved,
        COALESCE(si.position_recommending, '') AS position_recommending,
        COALESCE(si.position_reviewed, '') AS position_reviewed";
    }
 
    $sql_fetch_room_info .= "
    FROM tbl_rsched r
    INNER JOIN tbl_department d ON r.dept_code = d.dept_code
    INNER JOIN tbl_ay a ON r.ay_code = a.ay_code
    INNER JOIN tbl_room ro ON r.room_code = ro.room_code";

    // Join signatory table only if data exists
    if ($signatory_exists) {
        $sql_fetch_room_info .= " 
    INNER JOIN tbl_signatory si ON d.dept_code = si.dept_code AND si.user_type = ?";
    }

    $sql_fetch_room_info .= "
    WHERE r.room_sched_code = ?
      AND r.ay_code = ?";

    // 3. Prepare and bind
    $stmt_room_info = $conn->prepare($sql_fetch_room_info);

    // Bind parameters conditionally
    if ($signatory_exists) {
        $stmt_room_info->bind_param("sss", $user_type, $room_sched_code, $ay_code);
    } else {
        $stmt_room_info->bind_param("ss", $room_sched_code, $ay_code);
    }

    $stmt_room_info->execute();
    $result_room_info = $stmt_room_info->get_result();

    if (!$result_room_info || $result_room_info->num_rows === 0) {
        return '<p>No Available Classroom Schedule</p>';
    }


    $row_room_info = $result_room_info->fetch_assoc();
    $room_code = $row_room_info['room_code'];
    $dept_code = $row_room_info['dept_code'];
    $dept_name = $row_room_info['dept_name'];
    $ay_name = $row_room_info['ay_name'];
    $room_in_charge = $row_room_info['room_in_charge'];
    $recommending = isset($row_room_info['recommending']) ? $row_room_info['recommending'] : '';
    $approved = isset($row_room_info['approved']) ? $row_room_info['approved'] : '';
    $reviewed = isset($row_room_info['reviewed']) ? $row_room_info['reviewed'] : '';
    $position_approved = isset($row_room_info['position_approved']) ? $row_room_info['position_approved'] : '';
    $position_recommending = isset($row_room_info['position_recommending']) ? $row_room_info['position_recommending'] : '';
    $position_reviewed = isset($row_room_info['position_reviewed']) ? $row_room_info['position_reviewed'] : '';



    // Fetch the Department Secretary's name from tbl_prof_acc
    $sql_fetch_dept_sec = "
    SELECT first_name, middle_initial, last_name, suffix 
    FROM tbl_prof_acc 
    WHERE dept_code = ? 
    AND user_type = ?";

    $stmt_dept_sec = $conn->prepare($sql_fetch_dept_sec);
    $stmt_dept_sec->bind_param("ss", $dept_code, $_SESSION['user_type']);
    $stmt_dept_sec->execute();
    $result_dept_sec = $stmt_dept_sec->get_result();

    $dept_sec_name = '';
    if ($result_dept_sec->num_rows > 0) {
        $row_dept_sec = $result_dept_sec->fetch_assoc();
        $dept_sec_name = $row_dept_sec['first_name'] . ' ' . $row_dept_sec['middle_initial'] . '. ' . $row_dept_sec['last_name'] . ' ' . $row_dept_sec['suffix'];
    }

    // Sanitize the table name to ensure safe usage
    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
    // Fetch room schedule and join with section schedule to retrieve section_code
    $sql_fetch_schedule = "
    SELECT rs.*, se.section_code, c.course_name
    FROM $sanitized_room_sched_code AS rs
    INNER JOIN tbl_secschedlist AS se ON rs.section_code = se.section_sched_code
    INNER JOIN tbl_course AS c ON rs.course_code = c.course_code
    WHERE rs.semester = ? 
    AND rs.room_code = ? 
    AND rs.dept_code = ?";

    $stmt_schedule = $conn->prepare($sql_fetch_schedule);

    if (!$stmt_schedule) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt_schedule->bind_param("sss", $semester, $room_code, $dept_code);
    $stmt_schedule->execute();
    $result_schedule = $stmt_schedule->get_result();


    // Process schedule data
    if ($result_schedule->num_rows > 0) {
        $schedule_data = [];
        while ($row_schedule = $result_schedule->fetch_assoc()) {
            $day = ucfirst(strtolower($row_schedule['day']));
            $section_sched_code = $row_schedule['section_code'];


            // Fetch the section code
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
            $fetch_color_query = "SELECT cell_color FROM $sanitized_room_sched_code WHERE section_code = ? AND dept_code = ? AND semester = ?";
            $stmt_color = $conn->prepare($fetch_color_query);
            $stmt_color->bind_param("sss", $section_sched_code, $dept_code, $semester);
            $stmt_color->execute();
            $result_color = $stmt_color->get_result();

            $cell_color = '';
            if ($result_color->num_rows > 0) {
                $row_color = $result_color->fetch_assoc();
                $cell_color = $row_color['cell_color'];
            }

            // Collect the necessary schedule information
            $schedule_data[$day][] = [
                'time_start' => $row_schedule['time_start'],
                'time_end' => $row_schedule['time_end'],
                'course_code' => $row_schedule['course_code'],
                'section_code' => $row_schedule['section_code'],  // Retrieved from tbl_secschedlist
                'prof_name' => $row_schedule['prof_name'],
                'cell_color' => $cell_color,
                'class_type' => $row_schedule['class_type'],
                'course_name' => $row_schedule['course_name'],
            ];
        }



        // Call the generateScheduleTable function with the processed data
        return generateScheduleTable(
            $schedule_data,
            $ay_code,
            $dept_name,
            $semester,
            $ay_name,
            $room_code,
            $room_in_charge,
            $recommending,
            $approved,
            $reviewed,
            $dept_sec_name,
            $position_approved,
            $position_recommending,
            $position_reviewed
        );
    } else {
        return '<p>No Available Classroom Schedule</p>';
    }
}



function generateScheduleTable($schedule_data, $ay_code, $dept_name, $semester, $ay_name, $room_code, $room_in_charge, $recommending, $approved, $reviewed, $dept_sec_name, $position_approved, $position_recommending, $position_reviewed)
{
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
    <div><p style="text-align: right; font-family: Arial; font-size: 10px; font-style: italic;">CEIT-QF-08</p></div>
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
        <p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">
            ' . htmlspecialchars($user_college_name) . '
        </p>
        <p style="text-align: center; font-size: 8px; margin-bottom: 10px; font-family: Arial, sans-serif;">' . htmlspecialchars($dept_name) . '</p>
        <p style="text-align: center; font-size: 11px; line-height: 0.5; font-weight: bold;">ROOM UTILIZATION FORM</p>
        <p style="text-align: center; font-size: 10px; line-height: 0.5;">' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . ' </p>
            <div style="position: relative; font-size: 10px; line-height: 1.5;">
                <p style="margin: 0; text-align: left;">Room No.: <strong>' . htmlspecialchars(strtoupper($room_code)) . '</strong></p>
                <p style="margin: 0; text-align: left;">Room In-Charge: <strong>' . htmlspecialchars(strtoupper($room_in_charge)) . '</strong></p>
            </div>

    </div>
</div>

';

    $html .= '<div class="schedule-table-container" style="width: 100%; display: flex; justify-content: center; margin: 0 auto;">'; // Adjusted font size
    $html .= '<table class="table schedule-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; overflow-x: auto; padding: 3px;" 
    data-room="' . htmlspecialchars($room_code) . '" 
    data-college="' . htmlspecialchars($user_college_name) . '" 
    data-department="' . htmlspecialchars($dept_name) . '" 
    data-semester="' . htmlspecialchars($semester) . '"
    data-ayname="' . htmlspecialchars($ay_name) . '"
    data-roomincharge="' . htmlspecialchars($room_in_charge) . '">';
    $html .= '<thead>';
    $html .= '<tr>';
    $html .= '<th style="font-size: 7px; width: 20%; text-align: center; padding: 3px; background-color:rgb(232, 232, 232);">Time</th>';

    // Define column headers for days
    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 10%; text-align: center; font-size: 7px; padding: 3px; background-color:rgb(232, 232, 232);">' . $day_name . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    $time_slots = [];

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

    foreach ($time_slots as $slot) {
        $start_time = $slot['start'];
        $end_time = $slot['end'];
        $start_time_formatted = formatTime($start_time);
        $end_time_formatted = formatTime($end_time);

        $html .= '<tr>';
        $html .= '<td class="time-slot" style="font-size: 7px; padding: 3px;">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

        foreach ($day_names as $day_name) {
            $cell_content = '';
            $rowspan = 1;
            $cell_color = '';
            // Track whether the current time slot is already covered by a previous rowspan
            $is_covered = false;

            if (isset($schedule_data[$day_name])) {
                foreach ($schedule_data[$day_name] as $index => $schedule) {
                    $schedule_start = strtotime($schedule['time_start']);
                    $schedule_end = strtotime($schedule['time_end']);
                    $current_start = strtotime($start_time);
                    $current_end = strtotime($end_time);

                    // Ensure accurate matching of time slots and avoid duplication of columns
                    if ($schedule_start == $current_start) {
                        $class_type_display = ($schedule['class_type'] === 'lec') ? 'Lecture' : 'Laboratory';

                        $cell_content = "<span style='font-size: 8px; display: block; text-align: center; padding: 3px;'><b>{$schedule['course_code']}</b><br>
                                         {$schedule['section_code']}<br>
                                         {$schedule['prof_name']}<br>
                                         {$class_type_display}</span>";
                        $intervals = ($schedule_end - $schedule_start) / 1800;
                        $rowspan = max($rowspan, $intervals);
                        $schedule_data[$day_name][$index]['rowspan'] = $rowspan;
                        $cell_color = $schedule['cell_color'];
                        break;
                    } elseif ($current_start < $schedule_end && $current_end > $schedule_start) {
                        // The current slot is covered by a rowspan from a previous time slot
                        $is_covered = true;
                        break;
                    }
                }
            }

            // Add the cell content if the slot is not covered by a previous rowspan
            if (!$is_covered) {
                $html .= '<td style="width: 14.67%; background-color: ' . $cell_color . '; text-align: center; vertical-align: middle; font-size: 10px;" ' .
                    ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') .
                    ' data-cell-color="' . htmlspecialchars($cell_color) . '">' . $cell_content . '</td>';
            }
        }

        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<th class="tdcolspan" colspan="3" style=" text-align: center; vertical-align: middle; font-size: 10px; padding: 3px;">Course Code</th>';
    $html .= '<th class="tdcolspan" colspan="4" style=" text-align: center; vertical-align: middle; font-size: 10px; padding: 3px;">Course Title</th>';
    $html .= '</tr>';



    $subject_list = []; // Initialize subject list

    foreach ($schedule_data as $schedules) {
        foreach ($schedules as $schedule) {
            $course_code = $schedule['course_code'];
            $course_name = $schedule['course_name'];

            // Only add the course if it hasn't been added yet
            if (!isset($subject_list[$course_code])) {
                $subject_list[$course_code] = $course_name;
            }
        }
    }
    // Output the data for each subject
    foreach ($subject_list as $course_code => $course_name) {
        $html .= '<tr>';
        $html .= '<td class="tdcolspan" colspan="3" style="text-align: center; padding: 5px; font-size: 8px; padding: 3px">' . htmlspecialchars($course_code) . '</td>'; // Course code
        $html .= '<td class="tdcolspan" colspan="4" style="text-align: center; padding: 5px; font-size: 8px; padding: 3px">' . htmlspecialchars($course_name) . '</td>'; // Course name
        $html .= '</tr>';
    }
    $html .= '<tr></tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 20px; font-size: 10px;">Prepared by:</td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; padding-top: 20px; font-size: 10px;">Recommending Approval:</td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;"><strong>' . htmlspecialchars($dept_sec_name) . '</strong></td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;"><strong>' . htmlspecialchars($recommending) . '</strong></td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;">Department Secretary</td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;">' . htmlspecialchars($position_recommending) . '</td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 40px; font-size: 10px;">Reviewed by:</td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: left; padding-top: 40px; font-size: 10px;">Approved by:</td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px; border-bottom: 1px solid #000; height: 20px;"></td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;"><strong>' . htmlspecialchars($reviewed) . '</strong></td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;"><strong>' . htmlspecialchars($approved) . '</strong></td>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<td colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;">' . htmlspecialchars($position_reviewed) . '</td>';
    $html .= '<td colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none; text-align: center; font-size: 10px;">' . htmlspecialchars($position_approved) . '</td>';
    $html .= '</tr>';


    $html .= '</tbody></table></div>';



    // $html .= '<div style="page-break-after: always;"></div>'; // Add this line for page break

    $html .= '<div class="signature-wrapper">';

    $html .= '    <div class="signature-block">';
    $html .= '        <p>Prepared by:</p><br>';
    $html .= '        <p><strong>' . strtoupper(htmlspecialchars($dept_sec_name)) . '</strong></p>';
    $html .= '        <p><span>Department Secretary</span></p>';
    $html .= '    </div>';

    $html .= '    <div class="signature-block right">';
    $html .= '        <p>Recommending Approval:</p><br>';
    $html .= '        <p><strong>' . strtoupper(htmlspecialchars($recommending)) . '</strong></p>';
    $html .= '        <p><span>' . ucwords(strtolower(htmlspecialchars($position_recommending))) . '</span></p>';
    $html .= '    </div>';

    $html .= '    <div class="signature-block">';
    $html .= '        <p>Reviewed by:</p><br>';
    $html .= '        <p><strong>' . strtoupper(htmlspecialchars($reviewed)) . '</strong></p>';
    $html .= '        <p><span>' . ucwords(strtolower(htmlspecialchars($position_reviewed))) . '</span></p>';
    $html .= '    </div>';

    $html .= '    <div class="signature-block right">';
    $html .= '        <p>Approved by:</p><br>';
    $html .= '        <p><strong>' . strtoupper(htmlspecialchars($approved)) . '</strong></p>';
    $html .= '        <p><span>' . ucwords(strtolower(htmlspecialchars($position_approved))) . '</span></p>';
    $html .= '    </div>';

    $html .= '</div>';








    $html .= '<div style="clear: both;"></div>';



    return $html;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'make_public') {
    if (!empty($_POST['schedules'])) {
        $schedules = $_POST['schedules'];
        $messages = [];
        $publicSchedules = 0;

        foreach ($schedules as $schedule) {
            $room_sched_code = $schedule['room_sched_code'];
            $semester = $schedule['semester'];

            // Check if the schedule exists in tbl_prof_schedstatus
            $checkQuery = "SELECT * FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param('ss', $room_sched_code, $semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update status to 'public' in tbl_prof_schedstatus
                $updateQuery = "UPDATE tbl_room_schedstatus SET status = 'public' WHERE room_sched_code = ? AND semester = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param('ss', $room_sched_code, $semester);
                $stmt->execute();

                // Increment public schedule count
                $publicSchedules++;
            } else {
                $messages[] = "No matching schedules found for room_sched_code: $room_sched_code and semester: $semester.";
            }
        }

        if ($publicSchedules > 0) {
            $messages[] = "$publicSchedules schedules have been made public.";
        }

        // Output all messages as a single string
        if (!empty($messages)) {
            echo implode("<br>", $messages);
        }
    }
    exit;
}

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
//     $room_sched_code = $_POST['room_sched_code'] ?? '';
//     $semester = $_POST['semester'] ?? '';
//     $statusAction = $_POST['action']; // Either 'public' or 'private'

//     if (!empty($room_sched_code) && !empty($semester)) {
//         // Check if the schedule exists in tbl_room_schedstatus
//         $checkQuery = "SELECT * FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ?";
//         $stmt = $conn->prepare($checkQuery);
//         $stmt->bind_param('ss', $room_sched_code, $semester);
//         $stmt->execute();
//         $result = $stmt->get_result();

//         if ($result->num_rows > 0) {
//             // Update status based on action
//             $newStatus = ($statusAction === 'private') ? 'private' : 'public';
//             $updateQuery = "UPDATE tbl_room_schedstatus SET status = ? WHERE room_sched_code = ? AND semester = ?";
//             $stmt = $conn->prepare($updateQuery);
//             $stmt->bind_param('sss', $newStatus, $room_sched_code, $semester);

//             if ($stmt->execute()) {
//                 echo json_encode(['status' => $newStatus]);  // Return the new status in JSON
//             } else {
//                 echo json_encode(['error' => "Failed to update schedule status."]);
//             }
//         } else {
//             echo json_encode(['error' => "No matching schedules found."]);
//         }
//     } else {
//         echo json_encode(['error' => "Invalid request parameters."]);
//     }
//     exit;
// }

//with notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $room_sched_code = $_POST['room_sched_code'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $statusAction = $_POST['action'];

    if (!empty($room_sched_code) && !empty($semester)) {
        $checkQuery = "
            SELECT r.*, rs.room_code, rs.dept_code 
            FROM tbl_room_schedstatus r
            JOIN tbl_rsched rs ON r.room_sched_code = rs.room_sched_code 
            WHERE r.room_sched_code = ? AND r.semester = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ss', $room_sched_code, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $room_code = $row['room_code'];
            $dept_code = $row['dept_code'];

            $newStatus = $statusAction === 'private' ? 'private' : 'public';
            $updateQuery = "UPDATE tbl_room_schedstatus SET status = ? WHERE room_sched_code = ? AND semester = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('sss', $newStatus, $room_sched_code, $semester);
            $statusUpdateSuccessful = $stmt->execute();

            if ($statusUpdateSuccessful) {
                $notificationMessage = "The schedule for room {$room_code} has been changed to {$newStatus}.";
                $sender = $_SESSION['cvsu_email'];

                $receiver = 'student';
                $insertNotificationQuery = "INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code) 
                                            VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?)";
                $notificationStmt = $conn->prepare($insertNotificationQuery);
                $notificationStmt->bind_param('sssssss', $notificationMessage, $room_sched_code, $receiver, $sender, $room_code, $semester, $dept_code);
                $notificationStmt->execute();

                $receiver = 'professor';
                $notificationStmt->bind_param('sssssss', $notificationMessage, $room_sched_code, $receiver, $sender, $room_code, $semester, $dept_code);
                $notificationStmt->execute();

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

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Classroom Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <!-- <link rel="stylesheet" href="/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/SchedSys3/font-awesome-6-pro-main/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script> -->

    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>
    <link rel="stylesheet" href="../../../css/department_secretary/report/report_class.css">
    <link rel="stylesheet" href="../../../css/department_secretary/navbar.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="../../../css/department_secretary/library/lib_class.css">
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
                <a class="nav-link active" id="classroom-tab" href="lib_classroom.php" aria-controls="classroom"
                    aria-selected="true">Classroom</a>
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
                        <form method="POST" action="lib_classroom.php" class="row">
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
                                <input type="text" class="form-control" name="search_room"
                                    value="<?php echo isset($_POST['search_room']) ? htmlspecialchars($_POST['search_room']) : ''; ?>"
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
                            <th>Room Code</th>
                            <th>Room Type</th>
                            <th></th>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-room-id="<?php echo htmlspecialchars($row['room_code']); ?>">
                                        <td style="width: 80px;">
                                            <div style="display: flex;">
                                                <?php if ($user_type != "Department Chairperson" && $row['ay_code'] == $active_ay_code) { ?>
                                                    <form method="POST" action="lib_classroom.php">
                                                        <input type="hidden" name="room_code"
                                                            value="<?php echo htmlspecialchars($row['room_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="semester"
                                                            value="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="ay_code"
                                                            value="<?php echo htmlspecialchars($row['ay_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="status"
                                                            value="<?php echo htmlspecialchars($row['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="room_sched_code"
                                                            value="<?php echo htmlspecialchars($row['room_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                                                        <?php
                                                        // Fetch the current status from the database for the selected schedule
                                                        $query = "SELECT status FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ?";
                                                        $stmt = $conn->prepare($query);
                                                        $stmt->bind_param("ss", $row['room_sched_code'], $row['semester']);
                                                        $stmt->execute();
                                                        $statusResult = $stmt->get_result();
                                                        $statusRow = $statusResult->fetch_assoc();
                                                        $currentStatus = $statusRow['status'] ?? '';
                                                        ?>

                                                        <!-- Display lock button if the status is not public -->
                                                        <button type="button" class="change-btn lock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-room-sched-code="<?php echo htmlspecialchars($row['room_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus == 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye-slash"></i>

                                                        </button>

                                                        <!-- Display unlock button if the status is public -->
                                                        <button type="button" class="change-btn unlock-btn"
                                                            data-schedule-id="<?php echo htmlspecialchars($row['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-room-sched-code="<?php echo htmlspecialchars($row['room_sched_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-semester="<?php echo htmlspecialchars($row['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="event.stopPropagation();" <?php echo ($currentStatus != 'public') ? 'style="display:none;"' : ''; ?>>
                                                            <i class="far fa-eye"></i>
                                                        </button>
                                                    </form>
                                                <?php } ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['room_code']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                        <td>
                                            <!-- Edit Form -->
                                            <form method="POST" action="lib_classroom.php" style="display:inline;">
                                                <input type="hidden" name="room_code"
                                                    value="<?php echo htmlspecialchars($row['room_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="room_sched_code"
                                                    value="<?php echo htmlspecialchars($row['room_sched_code']); ?>">

                                            </form>
                                            <!-- Delete Form -->
                                            <form method="POST" action="lib_classroom.php" style="display:inline;">
                                                <input type="hidden" name="token"
                                                    value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                                                <input type="hidden" name="room_code"
                                                    value="<?php echo htmlspecialchars($row['room_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="room_sched_code"
                                                    value="<?php echo htmlspecialchars($row['room_sched_code']); ?>">
                                                <!-- Delete Button -->
                                                <button type="button" class="delete-btn" data-bs-toggle="modal"
                                                    data-room-code="<?php echo htmlspecialchars($row['room_code']); ?>"
                                                    data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                    data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>"
                                                    data-room-sched-code="<?php echo htmlspecialchars($row['room_sched_code']); ?>"
                                                    data-bs-target="#deleteModal" onclick="event.stopPropagation();">
                                                    <i class="fa-light fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No Records Found</td>
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
                    <form id="deleteForm" method="POST" action="lib_classroom.php" style="display:inline;">
                        <input type="hidden" name="token" id="deleteToken">
                        <input type="hidden" name="room_code" id="deleteRoomCode">
                        <input type="hidden" name="semester" id="deleteSemester">
                        <input type="hidden" name="ay_code" id="deleteAyCode">
                        <input type="hidden" name="room_sched_code" id="deleteRoomSchedCode">
                        <button type="submit" name="action" value="delete" class="btn btn-danger">Confirm</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openScheduleModal(roomId, semester, ayCode) {
            // Load schedule data via AJAX
            $.ajax({
                url: 'lib_classroom.php',
                type: 'GET',
                data: {
                    action: 'fetch_schedule',
                    room_id: roomId,
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
                const roomCode = button.getAttribute('data-room-code');
                const semester = button.getAttribute('data-semester');
                const ayCode = button.getAttribute('data-ay-code'); 
                const roomSchedCode = button.getAttribute('data-room-sched-code');

                // Set the values in the form
                document.getElementById('deleteRoomCode').value = roomCode;
                document.getElementById('deleteSemester').value = semester;
                document.getElementById('deleteAyCode').value = ayCode;
                document.getElementById('deleteRoomSchedCode').value = roomSchedCode;
                document.getElementById('deleteToken').value = "<?php echo htmlspecialchars($_SESSION['token']); ?>";
            });
        });
    </script>

    <script>

        document.getElementById('SchedulePDF').addEventListener('click', function () {
            const element = document.getElementById('scheduleContent');

            // Get the room code from the HTML (assuming it’s in a specific element with an id or class)
            const roomCodeElement = document.querySelector('p strong'); // This selects the <strong> tag inside the <p> with room details
            const roomCode = roomCodeElement ? roomCodeElement.textContent.trim() : "classroom_schedule"; // Default to "classroom_schedule" if not found

            // Add "schedule report" to the roomCode for the file name
            const fileName = `${roomCode} Schedule Report` || prompt("Enter file name for the PDF:", "classroom_schedule");

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
            var roomCode = document.querySelector(".schedule-table").dataset.room;
            var deptName = document.querySelector(".schedule-table").dataset.department;
            var semester = document.querySelector(".schedule-table").dataset.semester;
            var academicYear = document.querySelector(".schedule-table").dataset.ayname;
            var roominCharge = document.querySelector(".schedule-table").dataset.roomincharge;
            var currentDate = new Date().toLocaleDateString();
            var sheetName = roomCode || "Schedule";
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

            worksheet.getCell('G1').value = "CEIT-QF-08";
            worksheet.getCell('G1').alignment = { horizontal: "right", vertical: "middle" };
            worksheet.getCell('G1').font = { italic: true, size: 8, name: 'Arial' };

            // Function to add a merged row
            function addMergedRow(text, rowNumber) {
            worksheet.mergeCells(rowNumber, 1, rowNumber, 7); // Merge from column 1 to 7
            let row = worksheet.getRow(rowNumber);
            row.getCell(1).value = text;
            row.getCell(1).alignment = { horizontal: "center", vertical: "middle" };
            }

            let rowIndex = 2; // Start from row 2

            addMergedRow("Republic of the Philippines", rowIndex++);
            addMergedRow("Cavite State University", rowIndex++);
            addMergedRow("Don Severino de las Alas Campus", rowIndex++);
            addMergedRow("Indang, Cavite", rowIndex++);
            rowIndex++;
            addMergedRow("COLLEGE OF ENGINEERING AND INFORMATION TECHNOLOGY", rowIndex++);
            addMergedRow(deptName, rowIndex++); // Dynamic department name
            rowIndex++; // Add space
            addMergedRow("ROOM UTILIZATION FORM", rowIndex++);
            addMergedRow(semester + ", SY " + academicYear, rowIndex++); // Dynamic semester and academic year
            rowIndex++; // Add space

            // Merge first 6 columns for "Room No."
            worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
            let roomRow = worksheet.getRow(rowIndex);
            roomRow.getCell(1).value = "Room No.: " + roomCode;
            roomRow.getCell(1).alignment = { horizontal: "left", vertical: "middle" };

            rowIndex++; // Move to the next row

            worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
            let row = worksheet.getRow(rowIndex);
            row.getCell(1).value = "Room In-Charge: " + roominCharge;
            row.getCell(1).alignment = { horizontal: "left", vertical: "middle" };

            rowIndex++; // Move to the next row

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
            tl: { col: 1, row: 1 }, // Position at D2 (0-based index)
            ext: { width: 60, height: 50 }
            });

            // Convert table rows to worksheet with auto column width
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

            // Save the workbook
            const fileName = `Report for ${roomCode || "Unknown"}.xlsx`;
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            link.click();
        }

        $(document).ready(function () {
            filterClassroomBySchedule();

            $('#scheduleTable').on('click', 'tr', function (event) {
                var roomId = $(this).data('room-id');
                var semester = $('#search_semester').val();
                var ay_code = $('#search_ay').val();

                if (roomId) {
                    $('#scheduleModal').modal('show'); // Show the modal immediately
                    $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                    $.ajax({
                        url: 'lib_classroom.php',
                        type: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            room_id: roomId,
                            semester: semester,
                            ay_code: ay_code
                        },
                        success: function (response) {
                            console.log("Response: ", response); // Log the response for debugging
                            // Display the schedule in the modal
                            $('#scheduleContent').html(response);
                        },
                        error: function () {
                            console.error('Failed to fetch schedule for section ID: ' + sectionId);
                            $('#scheduleContent').html('<p>Error loading schedule.</p>'); // Show error message
                        }
                    });
                }
            });

            function filterClassroomBySchedule() {
                var selectedSemester = $('#search_semester').val();
                var selectedAY = $('#search_ay').val();
                var rowsVisible = false;

                $('#scheduleTable tbody tr').each(function () {
                    var row = $(this);
                    var roomId = row.data('room-id');

                    $.ajax({
                        url: 'lib_classroom.php',
                        type: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            room_id: roomId,
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
                            console.error('Failed to fetch schedule for section ID: ' + sectionId);
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
                    var roomSchedCode = row.find('input[name="room_sched_code"]').val();
                    var semester = row.find('input[name="semester"]').val();

                    selectedSchedules.push({
                        room_sched_code: roomSchedCode,
                        semester: semester
                    });
                });

                if (selectedSchedules.length === 0) {
                    alert("Please select at least one schedule.");
                    return;
                }

                $.ajax({
                    url: 'lib_classroom.php',
                    method: 'POST',
                    data: {
                        action: 'make_public',
                        schedules: selectedSchedules
                    },
                    success: function (response) {
                        if (response.trim() !== "") {
                            alert(response); // Display alert with the response
                        }

                        // After the alert, reload the page and switch to classroom-tab
                        window.location.href = 'lib_classroom.php';
                    },
                    error: function () {
                        alert("An error occurred while processing your request.");
                    }
                });
            });

            // On page load, switch to the classroom-tab if indicated
            var urlParams = new URLSearchParams(window.location.search);
            var activeTab = urlParams.get('activeTab');
            if (activeTab) {
                $('.nav-tabs a[href="#' + activeTab + '"]').tab('show');
            }
        });

        $(document).ready(function () {
            // Handle lock button (public)
            $('.lock-btn').on('click', function (event) {
                event.stopPropagation();

                var button = $(this);
                var roomSchedCode = button.data('room-sched-code');
                var semester = button.data('semester');

                $.ajax({
                    url: 'lib_classroom.php',
                    method: 'POST',
                    dataType: 'json', // Ensure the response is treated as JSON
                    data: {
                        action: 'public',
                        room_sched_code: roomSchedCode,
                        semester: semester
                    },
                    success: function (response) {
                        if (response.status === 'public') {
                            // Show modal instead of alert
                            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                            successModal.show();

                            // Hide lock button and show unlock button
                            button.hide();
                            button.siblings('.unlock-btn').show();
                        } else if (response.error) {
                            alert(response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("An error occurred: " + error);
                    }
                });
            });

            // Handle unlock button (private)
            $('.unlock-btn').on('click', function (event) {
                event.stopPropagation();

                var button = $(this);
                var roomSchedCode = button.data('room-sched-code');
                var semester = button.data('semester');

                $.ajax({
                    url: 'lib_classroom.php',
                    method: 'POST',
                    dataType: 'json', // Ensure the response is treated as JSON
                    data: {
                        action: 'private',
                        room_sched_code: roomSchedCode,
                        semester: semester
                    },
                    success: function (response) {
                        if (response.status === 'private') {
                            // Show modal instead of alert
                            var privateModal = new bootstrap.Modal(document.getElementById('privateModal'));
                            privateModal.show();

                            // Hide unlock button and show lock button
                            button.hide();
                            button.siblings('.lock-btn').show();
                        } else if (response.error) {
                            alert(response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        alert("An error occurred: " + error);
                    }
                });
            });
        });
    </script>
</body>

</html>