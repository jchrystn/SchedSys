<?php
session_start();
include 'plotScheduleScript.php';


if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head') && $_SESSION['user_type'] != 'Department Chairperson') {
    header("Location: ../login/login.php");
    exit();
}

// Get the current user's first name and department code from the session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
// $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
$section_sched_code = isset($_SESSION['section_sched_code']) ? $_SESSION['section_sched_code'] : '';
$section_code = isset($_SESSION['section_code']) ? $_SESSION['section_code'] : '';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';
$filter_type = isset($_SESSION['filter_type']) ? $_SESSION['filter_type'] : null;
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';


if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}


// Replace with your actual success page URL
$error_redirect_url = 'plotSchedule.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}


$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

$fetch_info_query = "SELECT reg_adviser, college_code,dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];
    $dept_code = $row['dept_code'];


    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser";
    } else {
        $current_user_type = $user_type;
    }
}
// echo $user_college_code;


$query_college_code = "SELECT college_code FROM tbl_prof_acc WHERE user_type = 'CCL Head' AND ay_code = '$ay_code' AND semester = '$semester'";
$college_result = $conn->query($query_college_code);

if ($college_result->num_rows > 0) {
    $row = $college_result->fetch_assoc();
    $ccl_college_code = $row['college_code'] ?? $college_code;


}
if (empty($ccl_college_code)) {
    $ccl_college_code = $college_code;
}

echo "<input type='hidden' id='ccl_college_code' value='" . htmlspecialchars($ccl_college_code) . "'>";
// echo "<input type='hidden' id='sender_email' value='" . htmlspecialchars($sender_email) . "'>";


$fetch_info_query = "SELECT dept_code,status,college_code FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $section_dept_code = $row['dept_code'];
    $sched_status = $row['status'];
    $section_college_code = $row['college_code'];

    echo "<input type='hidden' id='college_code' value='" . htmlspecialchars($college_code) . "'>";
    echo "<input type='hidden' id='section_college_code' value='" . htmlspecialchars($section_college_code) . "'>";

} else {
    echo "Dept: Error: No matchingd section schedule found for code '$section_sched_code'.";
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
    echo "<input type='hidden' id='admin_college_code' value='" . htmlspecialchars($admin_college_code) . "'>";

}

$sql_secsched = "SELECT * FROM tbl_shared_sched WHERE receiver_email = ? AND shared_section = ? AND semester = ? AND ay_code =?";
$stmt = $conn->prepare($sql_secsched);


if ($stmt) {
    $stmt->bind_param("ssss", $current_user_email, $section_sched_code, $semester, $ay_code);
    $stmt->execute();
    $result_secsched = $stmt->get_result();

    if ($row_secsched = $result_secsched->fetch_assoc()) {
        $sender_dept_code = $row_secsched['sender_dept_code'];
        $sender_email = $row_secsched['sender_email'];
        $receiver_email = $row_secsched['receiver_email'];


        $query = "SELECT user_type FROM tbl_prof_acc WHERE cvsu_email = '$sender_email' AND ay_code = '$ay_code' AND semester = '$semester'";
        $result = mysqli_query($conn, $query);

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result); // Fetch the row as an associative array
            $sender_user_type = htmlspecialchars($row['user_type']); // Safely get the user_type
        } else {
            $sender_user_type = null; // Handle the case where no result is found
        }


    } else {
        $sender_dept_code = null;
        $sender_email = null;
    }

    $stmt->close();
} else {
    echo "Error preparing section schedule query: " . $conn->error . "<br>";
}



$curriculum_check_query = "SELECT * FROM tbl_section 
WHERE section_code = '$section_code' 
AND  dept_code = '$section_dept_code' AND ay_code = '$ay_code' AND semester = '$semester'";
$curriculum_result = $conn->query($curriculum_check_query);

if ($curriculum_result->num_rows > 0) {
    $curriculum_row = $curriculum_result->fetch_assoc();
    $curriculum = $curriculum_row['curriculum'];
    $year_level = $curriculum_row['year_level'];
    $program_code = $curriculum_row['program_code'];
    $petition = $curriculum_row['petition'];
}


$sql_fetch = "SELECT table_start_time, table_end_time 
              FROM tbl_timeslot_active 
              WHERE active = 1 AND dept_code = ? AND semester = ? AND ay_code = ?";

$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("sss", $section_dept_code, $semester, $ay_code); // Assuming all are strings
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows > 0) {
    // Fetch the active time slot
    $row_fetch = $result_fetch->fetch_assoc();
    $user_start_time = $row_fetch['table_start_time'];
    $user_end_time = $row_fetch['table_end_time'];
} else {
    // Defaults if no active time slot is found
    $_SESSION['table_start_time'] = '7:00 am';
    $_SESSION['table_end_time'] = '9:00 pm';
    $user_start_time = '7:00 am';
    $user_end_time = '9:00 pm';
}



$sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");





if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changeColor'])) {
    $new_color = $_POST['color'];
    $section_sched_code = $_POST['section_sched_code'];
    $dept_code = $_SESSION['dept_code'];
    $semester = $_SESSION['semester'];
    $sec_sched_id = $_POST['sec_sched_id'];

    $_SESSION['token'] = bin2hex(random_bytes(32)); // Regenerate token
    $sanitized_section_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $sanitized_section_sched_code);

    // Check if the dept_code exists in the section schedule
    $check_dept_code_query = "SELECT dept_code FROM $sanitized_section_sched_code WHERE dept_code = '$dept_code' AND section_sched_code = '$section_sched_code' AND semester = '$semester'";
    $check_dept_code_result = $conn->query($check_dept_code_query);

    // Execute the query to update tbl_schedstatus
    $update_schedstatus_query = "UPDATE tbl_schedstatus
                                 SET cell_color = '$new_color'
                                 WHERE dept_code = '$dept_code' AND section_sched_code = '$section_sched_code'
                                 AND semester = '$semester'";
    $update_schedstatus_result = $conn->query($update_schedstatus_query);

    // Execute the query to update the sanitized section schedule
    $update_section_sched_query = "UPDATE $sanitized_section_sched_code 
                                   SET cell_color = '$new_color' 
                                   WHERE dept_code = '$dept_code' AND section_sched_code = '$section_sched_code'
                                   AND semester = '$semester'";
    $update_section_sched_result = $conn->query($update_section_sched_query);


    // Function to check if a table exists
    function tableExists($conn, $tableName)
    {
        $checkTableQuery = "SHOW TABLES LIKE '$tableName'";
        $result = $conn->query($checkTableQuery);
        return $result && $result->num_rows > 0;
    }

    // Sanitize table names
    $sanitized_section_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $sanitized_section_sched_code);
    $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
    $sanitized_prof_dept_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");


    // Update room schedule table if it exists
    if (tableExists($conn, $sanitized_room_dept_code)) {
        $update_room_sched_query = "UPDATE $sanitized_room_dept_code 
                                SET cell_color = '$new_color' 
                                WHERE dept_code = '$dept_code' AND section_code = '$section_sched_code'
                                AND semester = '$semester'";
        $conn->query($update_room_sched_query);
    }

    // Update professor schedule table if it exists
    if (tableExists($conn, $sanitized_prof_dept_code)) {
        $update_prof_sched_query = "UPDATE $sanitized_prof_dept_code 
                                SET cell_color = '$new_color' 
                                WHERE dept_code = '$dept_code' AND section_code = '$section_sched_code'
                                AND semester = '$semester'";
        $conn->query($update_prof_sched_query);
    }

    // Check if both queries were successful
    if ($update_schedstatus_result && $update_section_sched_result) {
        //echo json_encode(['status' => 'success', 'color' => $new_color]);
    } else {
        $error_message = "Error: ";
        if (!$update_schedstatus_result) {
            $error_message .= "tbl_schedstatus - " . $conn->error;
        }
        if (!$update_section_sched_result) {
            $error_message .= " sanitized section schedule - " . $conn->error;
        }
        //echo json_encode(['status' => 'error', 'message' => $error_message]);
    }
}

$fetch_info_query = "SELECT status,cell_color FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $status = $row['status'];
    $color = $row['cell_color'] ?? '#FFFFFF';

} else {
    die("Error: No matching section schedule found for code '$section_sched_code'.");
}

$fetch_info_query = "SELECT cell_color FROM $sanitized_section_sched_code WHERE dept_code = '$user_dept_code' AND section_sched_code = '$section_sched_code' AND semester = '$semester'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $color = $row['cell_color'] ?? '#FFFFFF';
}

$professor_options = '';
$firstProfOption = '';
$room_options = '';
$firstRoomOption = '';
$hide = "";
$unhide = 'style="display:none;"';
$roomdisable = 'disabled';
$selected_course_code = '';
$selected_new_course_code = '';


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filter'])) {
    $roomdisable = '';
    $hide = 'style="display:none;"';
    $unhide = 'style="display:inline;"';
    $course_code = $_SESSION['course_code'] ?? '';
    $day = $_POST['day'];
    $time_start = isset($_POST['time_start']) ? $_POST['time_start'] : '';
    $time_end = isset($_POST['time_end']) ? $_POST['time_end'] : '';
    $semester = $_POST['semester'];
    $course_code = $_POST['course_code'] ?? '';
    $selected_course_code = $_POST['course_code'] ?? '';

    if ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
        $room_type = ["Lecture", "Laboratory"]; // Define room types as an array
    } elseif ($user_type === 'CCL Head') {
        $room_type = ["Computer Laboratory"]; // Single room type as an array
    } else {
        $room_type = []; // Default to an empty array
    }



    function isRoomAvailable($conn, $plot_room_dept_code, $room_code, $day, $time_start, $time_end, $semester, $ay_code)
    {

        // Dynamically generate the table name for room schedules
        $dynamic_table = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");

        // Check if the dynamic table exists
        $check_table_sql = "SHOW TABLES LIKE '$dynamic_table'";
        $check_table_result = $conn->query($check_table_sql);

        // If the table does not exist, consider the room vacant
        if ($check_table_result->num_rows == 0) {
            return true; // Room is available
        }

        // Check availability for the specified room code
        $sql = "SELECT * FROM `$dynamic_table` 
                WHERE room_code = ? 
                AND day = ? 
                AND semester = ? 
                AND ay_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $room_code, $day, $semester, $ay_code);
        $stmt->execute();
        $result = $stmt->get_result();

        // If the room code is not in the schedule, it is vacant
        if ($result->num_rows == 0) {
            return true; // No schedules found for this room
        }

        // Check for time conflicts
        while ($row = $result->fetch_assoc()) {
            $existing_start = $row['time_start'];
            $existing_end = $row['time_end'];

            // Check for time conflict
            if (
                ($time_start >= $existing_start && $time_start < $existing_end) ||
                ($time_end > $existing_start && $time_end <= $existing_end) ||
                ($time_start <= $existing_start && $time_end >= $existing_end)
            ) {
                error_log("Conflict detected for room: $room_code");
                return false; // Conflict detected
            }

        }

        return true; // Room is available if no conflicts are found
    }


    $fetch_info_query = "SELECT dept_code, ay_code,section_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $sec_dept_code = $row['dept_code'];
        $ay_code = $row['ay_code'];
        $section_code = $row['section_code'];
    } else {
        die("Error: No matching section schedule found for code '$section_sched_code'.");
    }



    // Example usage

    if (empty($section_sched_code) || empty($semester) || empty($day) || empty($time_start) || empty($time_end) || empty($course_code)) {
        $invalid_fields[] = "Course is empty";
    } else {
        $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("ssss", $section_code, $semester, $sec_dept_code, $ay_code);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();


        // Ensure time_end is not less than time_start
        $time_start_dt = new DateTime($time_start);
        $time_end_dt = new DateTime($time_end);



        if ($time_end_dt <= $time_start_dt) {
            $invalid_fields[] = "Invalid time range: End time (" . $time_end_dt->format('H:i') . ") cannot be earlier than or the same as start time (" . $time_start_dt->format('H:i') . ").";
        }
        if ($user_type === 'CCL Head') {
            // CCL Head: Select courses with 'lecR&labR' and 'labR'
            $course_dept_code = $section_dept_code;
        } elseif ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
            // Department Secretary: Select courses with 'lecR&labR' and 'lecR'
            $course_dept_code = $dept_code;
        }

        // Check if the course exists
        $fetch_course_query = "SELECT * FROM tbl_course WHERE dept_code = '$course_dept_code' AND course_code = '$course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
        $result_course = $conn->query($fetch_course_query);
        if ($result_course->num_rows === 0) {
            $invalid_fields[] = "Course does not exist";
        }

        // Check if the room exists
    }

    if (!empty($invalid_fields)) {
        // Output conflicts list in a modal using JavaScript
        echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function() {
                        var conflictList = document.getElementById('conflictList');";

        foreach ($invalid_fields as $invalid) {
            // Safely handle quotes to prevent JS errors
            $safe_invalid = htmlspecialchars($invalid, ENT_QUOTES, 'UTF-8');
            echo "var li = document.createElement('li');
                    li.textContent = '$safe_invalid';
                    conflictList.appendChild(li);";
        }

        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                myModal.show();
                });
            </script>";

        // Fetch previously selected room code from POST or SESSION
        $selected_room_code = isset($_POST['room_code']) ? $_POST['room_code'] : '';
        $selected_prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : '';



        // Generate options for room_code dropdown
        $sql = "SELECT room_code, room_name FROM tbl_room WHERE dept_code = '$dept_code' AND status = 'Available'";

        if (!empty($room_type)) {
            // Add the IN clause for room_type
            $room_types_in_clause = "'" . implode("','", $room_type) . "'";
            $sql .= " AND room_type IN ($room_types_in_clause)";
        }
        $result = $conn->query($sql);

        $room_options = ''; // Initialize the options string
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if the current room code matches the last selected room code
                $selected = $selected_room_code == $row['room_code'] ? 'selected' : '';
                $display_text = $row["room_code"] . ' - ' . $row["room_name"];
                $room_options .= '<option value="' . $row["room_code"] . '" ' . $selected . '>' . $display_text . '</option>';
            }
        } else {
            $room_options .= '<option value="">No rooms available</option>';
        }

        // Generate options for professor_code dropdown only if the filter form has not been submitted

        // Sanitize inputs to prevent SQL injection
        $sanitized_semester = $conn->real_escape_string($semester); // Replace with actual semester value
        $sanitized_ay_code = $conn->real_escape_string($ay_code); // Replace with actual ay_code value
        $sanitized_dept_code = $conn->real_escape_string($dept_code); // Replace with actual dept_code value

        if ($user_type === "Department Secretary" || $user_type === 'Department Chairperson') {
            // Query to fetch professor details along with teaching hours
            $professors_sql = "
                    SELECT p.prof_code, p.prof_name, 
                        COALESCE(c.teaching_hrs, 0) AS teaching_hrs
                    FROM tbl_prof p
                    LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                    LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
                        AND c.semester = '$sanitized_semester' 
                        AND ps.ay_code = '$sanitized_ay_code'
                    WHERE p.dept_code = '$sanitized_dept_code' AND p.acc_status = '1' AND p.ay_code = '$ay_code' AND p.semester = '$semester'
                    GROUP BY p.prof_code, p.prof_name
                    ORDER BY p.prof_code";

            $professors_result = $conn->query($professors_sql);

            $professor_options = ''; // Initialize the options string

            if ($professors_result->num_rows > 0) {
                while ($prof_row = $professors_result->fetch_assoc()) {
                    $prof_code = htmlspecialchars($prof_row['prof_code'], ENT_QUOTES, 'UTF-8');
                    $prof_name = htmlspecialchars($prof_row['prof_name'], ENT_QUOTES, 'UTF-8');
                    $current_teaching_hrs = htmlspecialchars($prof_row['teaching_hrs'], ENT_QUOTES, 'UTF-8');


                    // Check if the current professor code matches the last selected professor code
                    $selected = $selected_prof_code == $prof_code ? 'selected' : '';
                    $display_text = $prof_code . ' - ' . $prof_name . ' (' . $current_teaching_hrs . ' hrs)';
                    $professor_options .= '<option value="' . $prof_code . '" ' . $selected . '>' . $display_text . '</option>';
                }
            } else {
                $professor_options .= '<option value="">No professors available</option>';
            }
        }


    } else {

        // function getCourseType($conn, $course_code, $dept_code,$program_code,$semester) {
        //     $query = "SELECT lec_hrs, lab_hrs FROM tbl_course WHERE course_code = ? AND dept_code = ? AND program_code = ? AND semester = ?";
        //     $stmt = $conn->prepare($query);
        //     $stmt->bind_param('ssss', $course_code, $dept_code,$program_code,$semester);
        //     $stmt->execute();
        //     $result = $stmt->get_result();
        //     $course = $result->fetch_assoc();

        //     if ($course) {
        //         $lec_hours = $course['lec_hrs'];
        //         $lab_hours = $course['lab_hrs'];

        //         if ($lec_hours > 0 && $lab_hours > 0) {
        //             return 'both'; // Both lecture and lab components
        //         } elseif ($lec_hours > 0) {
        //             return 'lec'; // Only lecture component
        //         } elseif ($lab_hours > 0) {
        //             return 'lab'; // Only laboratory component
        //         }
        //     }

        //     return 'unknown'; // If course type is not found or is undefined
        // }

        // // echo $course_code;
        // // echo $dept_code;
        // // echo $program_code;
        // // echo $year_level;
        // // echo $semester;


        // // Fetch room options based on the course type
        // $course_type = getCourseType($conn, $course_code, $dept_code,$program_code,$semester);

        // // Define room types based on the course type
        // $roomTypes = [];
        // if ($course_type === 'both') {
        //     $roomTypes = ['Lecture', 'Laboratory']; // Include both types
        // } elseif ($course_type === 'lec') {
        //     $roomTypes = ['Lecture']; // Only lecture rooms
        // } elseif ($course_type === 'lab') {
        //     $roomTypes = ['Laboratory']; // Only laboratory rooms
        // }
        $room_status = 'Available';
        // echo $room_status;

        // Prepare SQL to fetch rooms that match the room types needed for the course
        // $rooms_sql = "SELECT room_code, room_name, room_type FROM tbl_room WHERE dept_code = ? AND status = ? AND room_type = ?";
        // $stmt_rooms = $conn->prepare($rooms_sql);
        // $stmt_rooms->bind_param('sss', $dept_code, $room_status, $room_type);
        // $stmt_rooms->execute();
        // $rooms_result = $stmt_rooms->get_result();

        $rooms_sql = "SELECT room_code, room_name,room_type FROM tbl_room WHERE status = 'Available'";

        if (!empty($room_type)) {
            if ($user_type == "CCL Head") {
                // Add the IN clause for room_type
                $room_types_in_clause = "'" . implode("','", $room_type) . "'";
                $rooms_sql .= " AND room_type IN ($room_types_in_clause)";
            } else {
                $room_types_in_clause = "'" . implode("','", $room_type) . "'";
                $rooms_sql .= " AND dept_code = '$dept_code' AND room_type IN ($room_types_in_clause)";
            }
        }

        $rooms_result = $conn->query($rooms_sql);

        $room_options = ''; // Initialize the options string for the datalist
        $first = true; // Flag to track the first option

        if ($rooms_result->num_rows > 0) {
            while ($room_row = $rooms_result->fetch_assoc()) {
                $room_code = $room_row['room_code'];
                $room_name = $room_row['room_name'];
                $room_type = $room_row['room_type'];

                $sql = "SELECT allowed_rooms, computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $course_code, $program_code, $year_level, $semester);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $allowed_rooms = $row['allowed_rooms'];
                    $computer_room = $row['computer_room'];
                }

                if ($computer_room == 1 && $user_type == "CCL Head") {
                    $plot_room_dept_code = $ccl_college_code;
                } else {
                    $plot_room_dept_code = $dept_code;
                }


                $dynamic_table = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");


                // Check if the dynamic table exists
                $check_table_sql = "SHOW TABLES LIKE '$dynamic_table'";
                $check_table_result = $conn->query($check_table_sql);

                // Proceed only if the table exists and the room is available
                if ($check_table_result->num_rows > 0 && isRoomAvailable($conn, $plot_room_dept_code, $room_code, $day, $time_start, $time_end, $semester, $ay_code)) {
                    $selected = $first ? 'selected' : ''; // Select the first option by default
                    $room_options .= '<option value="' . htmlspecialchars($room_code, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars($room_code . ' - ' . $room_name . ' (' . $room_type . ')', ENT_QUOTES, 'UTF-8') . '</option>';

                    if ($first) {
                        $firstRoomOption = htmlspecialchars($room_code, ENT_QUOTES, 'UTF-8'); // Store the first room code
                        $first = false; // Disable the flag after the first option
                    }
                }

            }
        }
        // If no available rooms were added, provide a default message
        if (empty($room_options)) {
            $room_options .= '<option value="">No rooms available</option>';
        }



        function isProfessorAvailable($conn, $prof_code, $day, $time_start, $time_end, $semester, $ay_code, $dept_code)
        {
            // Sanitize inputs
            $sanitized_prof_code = $conn->real_escape_string($prof_code);
            $sanitized_day = $conn->real_escape_string($day);
            $sanitized_time_start = $conn->real_escape_string($time_start);
            $sanitized_time_end = $conn->real_escape_string($time_end);
            $sanitized_semester = $conn->real_escape_string($semester);
            $sanitized_ay_code = $conn->real_escape_string($ay_code);
            $sanitized_dept_code = $conn->real_escape_string($dept_code);

            // Construct the dynamic table name for professor schedules
            $prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sanitized_dept_code}_{$sanitized_ay_code}");
            $pcontact_sched_code = "tbl_pcontact_sched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sanitized_dept_code}_{$sanitized_ay_code}");

            // Debug output for table name
            error_log("Checking table: $prof_sched_code");

            // Check if the dynamic table exists
            $check_table_sql = "SHOW TABLES LIKE '$prof_sched_code'";
            $check_table_result = $conn->query($check_table_sql);

            if ($check_table_result === false) {
                // Error occurred while checking table existence
                error_log("Error checking table existence: " . $conn->error);
                return false;
            }

            if ($check_table_result->num_rows > 0) {
                // If the table exists, check if the professor has any conflicts
                $sql = "SELECT * FROM `$prof_sched_code` 
                    WHERE prof_code = ? 
                    AND day = ? 
                    AND semester = ? 
                    AND ay_code = ?";

                error_log("SQL Query for prof schedule: $sql");

                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    error_log("Error preparing statement: " . $conn->error);
                    return false;
                }

                $stmt->bind_param('ssss', $sanitized_prof_code, $sanitized_day, $sanitized_semester, $sanitized_ay_code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result === false) {
                    error_log("Error executing query: " . $conn->error);
                    return false;
                }

                while ($row = $result->fetch_assoc()) {
                    $existing_start = $row['time_start'];
                    $existing_end = $row['time_end'];

                    error_log("Existing schedule (prof_sched): Start - $existing_start, End - $existing_end");

                    // Check for time conflict
                    if (($sanitized_time_start < $existing_end && $sanitized_time_end > $existing_start)) {
                        error_log("Conflict detected (prof_sched): Professor '$sanitized_prof_code' is already booked from $existing_start to $existing_end.");
                        return false;
                    }
                }
            }

            // Now, check the tbl_pcontact_sched for conflicts
            $contact_sql = "SELECT * FROM `$pcontact_sched_code` 
                        WHERE prof_code = ? 
                        AND day = ? 
                        AND semester = ? 
                        ";

            error_log("SQL Query for pcontact schedule: $contact_sql");

            $stmt = $conn->prepare($contact_sql);
            if ($stmt === false) {
                error_log("Error preparing pcontact schedule statement: " . $conn->error);
                return false;
            }

            $stmt->bind_param('sss', $sanitized_prof_code, $sanitized_day, $sanitized_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result === false) {
                error_log("Error executing pcontact schedule query: " . $conn->error);
                return false;
            }

            while ($row = $result->fetch_assoc()) {
                $existing_start = $row['time_start'];
                $existing_end = $row['time_end'];

                error_log("Existing schedule (pcontact_sched): Start - $existing_start, End - $existing_end");

                // Check for time conflict
                if (($sanitized_time_start < $existing_end && $sanitized_time_end > $existing_start)) {
                    error_log("Conflict detected (pcontact_sched): Professor '$sanitized_prof_code' is already booked from $existing_start to $existing_end.");
                    return false; // Conflict detected in contact schedule
                }
            }

            // No conflicts detected in either table
            error_log("No conflict detected for professor '$sanitized_prof_code' in both schedules.");
            return true;
        }


        // Fetch all professors with their teaching hours and course counts for the selected course
        $sanitized_dept_code = $conn->real_escape_string($dept_code);
        $sanitized_ay_code = $conn->real_escape_string($ay_code);
        $sanitized_semester = $conn->real_escape_string($semester);
        $sanitized_course_code = $conn->real_escape_string($course_code);


        if ($user_type === "Department Secretary" || $user_type === 'Department Chairperson') {
            $professors_sql = "SELECT p.prof_code, p.prof_name, 
            COALESCE(c.teaching_hrs, 0) AS teaching_hrs,
            COALESCE(ac.course_counter, 0) AS course_counter
            FROM tbl_prof p
            LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
            LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
            AND c.semester = '$sanitized_semester' 
            AND ps.ay_code = '$sanitized_ay_code'
            LEFT JOIN tbl_assigned_course ac ON p.prof_code = ac.prof_code
            AND ac.semester = '$sanitized_semester'
            AND ac.dept_code = '$sanitized_dept_code'
            AND ac.course_code = '$sanitized_course_code'
            WHERE p.dept_code = '$sanitized_dept_code' AND p.ay_code = '$ay_code' AND p.semester = '$semester'
            GROUP BY p.prof_code, p.prof_name
            ORDER BY course_counter DESC, p.prof_code";




            $professors_result = $conn->query($professors_sql);

            $professor_options = ''; // Initialize options string for the dropdown
            $first = true; // Flag to track the first option

            if ($professors_result->num_rows > 0) {
                while ($row = $professors_result->fetch_assoc()) {
                    $prof_code = $row["prof_code"];
                    $prof_name = $row["prof_name"];
                    $current_teaching_hrs = $row["teaching_hrs"];
                    // $course_counter = $row["course_counter"];
                    if (!empty($prof_name)) {
                        $course_counter = $row["course_counter"];
                    } else {
                        $course_counter = 0;
                    }


                    // Check if the professor is available
                    if (isProfessorAvailable($conn, $prof_code, $day, $time_start, $time_end, $semester, $ay_code, $dept_code)) {
                        $suggested = $course_counter > 0 ? " - recommended" : "";
                        $selected = $first ? 'selected' : ''; // Select the first option by default
                        $professor_options .= '<option value="' . $prof_code . '" ' . $selected . '>' .
                            $prof_code . ' - ' .
                            $prof_name . ' (' .
                            $current_teaching_hrs . 'hrs' . ')' .
                            $suggested .
                            '</option>';
                        if ($first) {
                            $firstProfOption = $prof_code; // Store the first professor code
                            $first = false; // Disable the flag after the first option
                        }
                    }
                }
            } else {
                $professor_options = '<option value="">No professors available</option>';
            }

        }
    }
}
////

$sec_sched_id = isset($_POST['sec_sched_id']) ? $_POST['sec_sched_id'] : '';
// Preserve sec_sched_id
$roomReadonly = '';
$profReadonly = '';
$courseReadonly = '';
$lastSelectedRoom = '';
$lastSelectedProf = '';
$first_option = '';
$prof_first_option = '';
$new_room_options = '';
$new_professor_options = '';
$shared_sched = '';
$btnDelete = 'style="display:none;"';
$btnUpdate = 'style="display:none;"';
$btnShare = 'style="display:none;"';
$btnUnShare = 'style="display:none;"';
$btnAdd = 'style="display:inline;"';
$disabled = '';
$newHide = 'style="display:none;"';
$newUnHide = 'style="display:none;"';
// Assuming $selected_class_type holds the previously selected value
$class_type = isset($_POST['class_type']) ? $_POST['class_type'] : null; // Default to 'lec'


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filterNew'])) {
    $newUnHide = 'style="display:inline;"';
    $roomdisable = '';
    $selected_new_course_code = $_POST['new_course_code'] ?? '';
    // Store previous values before applying the new filter
    $section_sched_code = $_POST['section_sched_code'];
    $sec_sched_id = $_POST['sec_sched_id'];
    $semester = $_POST['semester'];
    $shared_to = $_POST['shared_to'];
    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");
    $shared_to = $_POST['shared_to'];
    $shared_to = $_POST['shared_to'];
    $sec_sched_id = $_POST['sec_sched_id']; // Assuming sec_sched_id is being passed from the form

    if ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
        $room_type = ["Lecture", "Laboratory"]; // Define room types as an array
    } elseif ($user_type === 'CCL Head') {
        $room_type = ["Computer Laboratory"]; // Single room type as an array
    } else {
        $room_type = []; // Default to an empty array
    }

    // Initial values from POST request
    $time_start = !empty($_POST['time_start']) ? $_POST['time_start'] : null;
    $time_end = !empty($_POST['time_end']) ? $_POST['time_end'] : null;
    $day = !empty($_POST['day']) ? $_POST['day'] : null;

    // Query to get time_start, time_end, and day if not provided
    $sql_sched = "SELECT time_start, day, time_end, class_type,room_code, prof_code,dept_code FROM $sanitized_section_sched_code WHERE sec_sched_id='$sec_sched_id'";
    $result_sched = $conn->query($sql_sched);
    if ($result_sched && $result_sched->num_rows > 0) {
        $row_sched = $result_sched->fetch_assoc();
        $lastSelectedRoom = $row_sched['room_code'];
        $lastSelectedProf = $row_sched['prof_code'];
        $Cdepartment = $row_sched['dept_code'];

        // If day is empty, get it from the query result
        if (empty($day)) {
            $day = $row_sched['day'];
        }

        // If time_start is empty, get it from the query result
        if (empty($time_start)) {
            $time_start = $row_sched['time_start'];
            $selected_time_start = $row_sched['time_start'];
            // echo $time_start;
        }

        // If time_end is empty, get it from the query result
        if (empty($time_end)) {
            $time_end = $row_sched['time_end'];
            $selected_time_end = $row_sched['time_end'];
            // echo $time_end;
        }
        if (empty($class_type)) {
            $class_type = $row_sched['class_type'];

        }
    } else {
        // Handle the case where no result is found
        echo "No schedule found for sec_sched_id: $sec_sched_id";
    }


    // Retrieve the selected course code from the session
    $new_course_code = $_POST['new_course_code'] ?? '';
    $shared_sched = $_POST['shared_sched'];

    $sql = "SELECT allowed_rooms, computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $new_course_code, $program_code, $year_level, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $allowed_rooms = $row['allowed_rooms'];
        $computer_room = $row['computer_room'];

    }

    // Set read-only fields based on shared_sched


    if (!empty($shared_to)) {
        if ($shared_sched === 'prof' && $shared_to === $current_user_email) {
            $roomReadonly = 'readonly'; // Hide room input and datalist
            $courseReadonly = 'readonly';
        } elseif ($shared_sched === 'room' && $shared_to === $current_user_email) {
            $profReadonly = 'readonly'; // Hide professor input and datalist
            $courseReadonly = 'readonly';
        } elseif ($shared_sched === 'room' && $shared_to !== $current_user_email) {
            $courseReadonly = 'readonly';
            $roomReadonly = 'readonly';
        } elseif ($shared_sched === 'prof' && $shared_to !== $current_user_email) {
            $courseReadonly = 'readonly';
            $roomReadonly = ' ';
            $profReadonly = 'readonly';
        }
    }



    // if ($user_type === "Department Secretary"){
    //     $profReadonly = 'readonly';
    // }if ($user_type === "CCL Head"){
    //     $profReadonly = 'readonly';
    // }


    //$lastSelectedProf = isset($_POST['new_prof_code']) ? $_POST['new_prof_code'] : '';

    // Fetch section and academic year information
    $fetch_info_query = "SELECT dept_code, ay_code,section_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ay_code = $row['ay_code'];
        $section_code = $row['section_code'];
        $section_dept_code = $row['dept_code'];
    } else {
        die("Error: No matching section schedule found for code '$section_sched_code'.");
    }
    // Function to check room availability
    function isRoomAvailable($conn, $plot_room_dept_code, $room_code, $day, $time_start, $time_end, $semester, $ay_code)
    {

        // Dynamically generate the table name for room schedules
        $dynamic_table = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");
        // Check if the dynamic table exists
        $check_table_sql = "SHOW TABLES LIKE '$dynamic_table'";
        $check_table_result = $conn->query($check_table_sql);

        // If the table does not exist, consider the room vacant
        if ($check_table_result->num_rows == 0) {
            return true; // Room is available
        }

        // Check availability for the specified room code
        $sql = "SELECT * FROM `$dynamic_table` 
                WHERE room_code = ? 
                AND day = ? 
                AND semester = ? 
                AND ay_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $room_code, $day, $semester, $ay_code);
        $stmt->execute();
        $result = $stmt->get_result();

        // If the room code is not in the schedule, it is vacant
        if ($result->num_rows == 0) {
            return true; // No schedules found for this room
        }

        // Check for time conflicts
        while ($row = $result->fetch_assoc()) {
            $existing_start = $row['time_start'];
            $existing_end = $row['time_end'];

            // Check for time conflict
            if (
                ($time_start >= $existing_start && $time_start < $existing_end) ||
                ($time_end > $existing_start && $time_end <= $existing_end) ||
                ($time_start <= $existing_start && $time_end >= $existing_end)
            ) {
                error_log("Conflict detected for room: $room_code");
                return false; // Conflict detected
            }
        }

        return true; // Room is available if no conflicts are found
    }

    //echo $section_sched_code;
    // echo $semester;
    // echo $day;
    //echo $time_start;
    // echo $time_end;
    //  echo $new_course_code;
    // Fetch and display room options

    if (empty($section_sched_code) || empty($semester) || empty($day) || empty($time_start) || empty($time_end) || empty($new_course_code)) {
        $invalid_fields[] = "Course code is empty. ";
        // Semester: $semester, Section Schedule Code: $section_sched_code, Day: $day, Start Time: $time_start, End Time: $time_end, New Course Code: $new_course_code
    } else {

        $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ? AND ay_code = ? AND semester = ? ";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("ssss", $section_code, $section_dept_code, $ay_code, $semester);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();


        // Ensure time_end is not less than time_start
        $time_start_dt = new DateTime($time_start);
        $time_end_dt = new DateTime($time_end);

        if ($time_end_dt <= $time_start_dt) {
            $invalid_fields[] = "Invalid time range: End time (" . $time_end_dt->format('H:i') . ") cannot be earlier than or the same as start time (" . $time_start_dt->format('H:i') . ").";
        }

        if ($user_type === 'CCL Head') {
            // CCL Head: Select courses with 'lecR&labR' and 'labR'
            $course_dept_code = $section_dept_code;
        } elseif ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
            // Department Secretary: Select courses with 'lecR&labR' and 'lecR'
            $course_dept_code = $dept_code;
        }



        // Check if the course exists
        if ($shared_to != $current_user_email) {
            $fetch_course_query = "SELECT * FROM tbl_course WHERE dept_code = '$course_dept_code' AND course_code = '$new_course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
            $result_course = $conn->query($fetch_course_query);
            if ($result_course->num_rows === 0) {
                $invalid_fields[] = "Course does not exist";
            }
        }


        // Check if the room exists
        //   echo $new_course_code;
        //     echo $user_dept_code;
        //     echo $program_code;
        //     echo $year_level;
        //     echo $semester;


    }

    if (!empty($invalid_fields)) {
        // Output conflicts list in a modal using JavaScript
        echo "<script type='text/javascript'>
                document.addEventListener('DOMContentLoaded', function() {
                    var conflictList = document.getElementById('conflictList');";

        foreach ($invalid_fields as $invalid) {
            // Safely handle quotes to prevent JS errors
            $safe_invalid = htmlspecialchars($invalid, ENT_QUOTES, 'UTF-8');
            echo "var li = document.createElement('li');
                  li.textContent = '$safe_invalid';
                  conflictList.appendChild(li);";
        }

        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
              myModal.show();
            });
        </script>";

        // Fetch previously selected room code from POST or SESSION
        $selected_room_code = isset($_POST['room_code']) ? $_POST['room_code'] : '';
        $selected_prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : '';

        // Generate options for room_code dropdown
        $sql = "SELECT room_code, room_name FROM tbl_room WHERE dept_code = '$user_dept_code' AND status = 'Available'";

        if (!empty($room_type)) {
            // Add the IN clause for room_type
            $room_types_in_clause = "'" . implode("','", $room_type) . "'";
            $sql .= " AND room_type IN ($room_types_in_clause)";
        }
        $result = $conn->query($sql);
        $new_room_options = ''; // Initialize the options string
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if the current room code matches the last selected room code
                $selected = $selected_room_code == $row['room_code'] ? 'selected' : '';
                $display_text = $row["room_code"] . ' - ' . $row["room_name"];
                $new_room_options .= '<option value="' . $row["room_code"] . '" ' . $selected . '>' . $display_text . '</option>';
            }
        } else {
            $new_room_options .= '<option value="">No rooms available</option>';
        }

        // Generate options for professor_code dropdown only if the filter form has not been submitted

        // Sanitize inputs to prevent SQL injection
        $sanitized_semester = $conn->real_escape_string($semester); // Replace with actual semester value
        $sanitized_ay_code = $conn->real_escape_string($ay_code); // Replace with actual ay_code value
        $sanitized_dept_code = $conn->real_escape_string($dept_code); // Replace with actual dept_code value

        // Query to fetch professor details along with teaching hours
        if ($user_type === "Department Secretary" || $user_type === 'Department Chairperson') {
            $professors_sql = "
                SELECT p.prof_code, p.prof_name, 
                    COALESCE(c.teaching_hrs, 0) AS teaching_hrs
                FROM tbl_prof p
                LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
                    AND c.semester = '$sanitized_semester' 
                    AND ps.ay_code = '$sanitized_ay_code'
                WHERE p.dept_code = '$sanitized_dept_code' AND p.acc_status = '1' AND p.ay_code = '$ay_code' AND p.semester = '$semester'
                GROUP BY p.prof_code, p.prof_name
                ORDER BY p.prof_code";

            $professors_result = $conn->query($professors_sql);

            $new_professor_options = ''; // Initialize the options string

            if ($professors_result->num_rows > 0) {
                while ($prof_row = $professors_result->fetch_assoc()) {
                    $prof_code = htmlspecialchars($prof_row['prof_code'], ENT_QUOTES, 'UTF-8');
                    $prof_name = htmlspecialchars($prof_row['prof_name'], ENT_QUOTES, 'UTF-8');
                    $current_teaching_hrs = htmlspecialchars($prof_row['teaching_hrs'], ENT_QUOTES, 'UTF-8');


                    // Check if the current professor code matches the last selected professor code
                    $selected = $selected_prof_code == $prof_code ? 'selected' : '';
                    $display_text = $prof_code . ' - ' . $prof_name . ' (' . $current_teaching_hrs . ' hrs)';
                    $new_professor_options .= '<option value="' . $prof_code . '" ' . $selected . '>' . $display_text . '</option>';
                }
            } else {
                $new_professor_options .= '<option value="">No professors available</option>';
            }
        }


        $newUnHide = 'style="display:none;"';
    } else {


        // function getCourseType($conn, $new_course_code, $Cdepartment,$program_code) {
        //     $query = "SELECT lec_hrs, lab_hrs FROM tbl_course WHERE course_code = ? AND dept_code = ? AND program_code = ?";
        //     $stmt = $conn->prepare($query);
        //     $stmt->bind_param('sss', $new_course_code, $Cdepartment,$program_code);
        //     $stmt->execute();
        //     $result = $stmt->get_result();
        //     $course = $result->fetch_assoc();

        //     if ($course) {
        //         $lec_hours = $course['lec_hrs'];
        //         $lab_hours = $course['lab_hrs'];

        //         if ($lec_hours > 0 && $lab_hours > 0) {
        //             return 'both'; // Both lecture and lab components
        //         } elseif ($lec_hours > 0) {
        //             return 'lec'; // Only lecture component
        //         } elseif ($lab_hours > 0) {
        //             return 'lab'; // Only laboratory component
        //         }
        //     }

        //     return 'unknown'; // If course type is not found or is undefined
        // }

        // // Fetch room options based on the course type
        // $course_type = getCourseType($conn, $new_course_code, $Cdepartment,$program_code);
        // echo "Course Type: " . ucfirst($course_type); // Capitalizes the first letter

        // echo $new_course_code;
        // echo $program_code;


        // // Define room types based on the course type
        // $roomTypes = [];
        // if ($course_type === 'both') {
        //     $roomTypes = ['Lecture', 'Laboratory']; // Include both types
        // } elseif ($course_type === 'lec') {
        //     $roomTypes = ['Lecture']; // Only lecture rooms
        // } elseif ($course_type === 'lab') {
        //     $roomTypes = ['Laboratory']; // Only laboratory rooms
        // }

        // Prepare SQL to fetch rooms that match the room types needed for the course
        // $rooms_sql = "SELECT room_code, room_name, room_type FROM tbl_room WHERE dept_code = ? AND status = 'Available' AND room_type = ?";
        // $stmt_rooms = $conn->prepare($rooms_sql);
        // $stmt_rooms->bind_param('ss', $dept_code, $room_type);
        // $stmt_rooms->execute();
        // $rooms_result = $stmt_rooms->get_result();

        $rooms_sql = "SELECT room_code, room_name,room_type FROM tbl_room WHERE status = 'Available'";

        if (!empty($room_type)) {
            if ($user_type == "CCL Head") {
                // Add the IN clause for room_type
                $room_types_in_clause = "'" . implode("','", $room_type) . "'";
                $rooms_sql .= " AND room_type IN ($room_types_in_clause)";
            } else {
                $room_types_in_clause = "'" . implode("','", $room_type) . "'";
                $rooms_sql .= " AND dept_code = '$dept_code' AND room_type IN ($room_types_in_clause)";
            }
        }
        $rooms_result = $conn->query($rooms_sql);

        $new_room_options = ''; // Initialize the options string for the datalist
        $first = true; // Flag to track the first option

        if ($rooms_result->num_rows > 0) {
            while ($room_row = $rooms_result->fetch_assoc()) {
                $room_code = $room_row['room_code'];
                $room_name = $room_row['room_name'];
                $room_type = $room_row['room_type'];

                $sql = "SELECT allowed_rooms, computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $new_course_code, $program_code, $year_level, $semester);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $allowed_rooms = $row['allowed_rooms'];
                    $computer_room = $row['computer_room'];
                }

                if ($computer_room == 1 && $user_type == "CCL Head") {
                    $plot_room_dept_code = $ccl_college_code;
                } else {
                    $plot_room_dept_code = $dept_code;
                }


                $dynamic_table = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");


                // Check if the dynamic table exists
                $check_table_sql = "SHOW TABLES LIKE '$dynamic_table'";
                $check_table_result = $conn->query($check_table_sql);

                // Proceed only if the table exists and the room is available
                if ($check_table_result->num_rows > 0 && isRoomAvailable($conn, $plot_room_dept_code, $room_code, $day, $time_start, $time_end, $semester, $ay_code)) {
                    $selected = $first ? 'selected' : ''; // Select the first option by default
                    $new_room_options .= '<option value="' . htmlspecialchars($room_code, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars($room_code . ' - ' . $room_name . ' (' . $room_type . ')', ENT_QUOTES, 'UTF-8') . '</option>';

                    if ($first) {
                        $firstRoomOption = htmlspecialchars($room_code, ENT_QUOTES, 'UTF-8'); // Store the first room code
                        $first = false; // Disable the flag after the first option
                    }
                }
            }
        }
        // If no available rooms were added, provide a default message
        if (empty($new_room_options)) {
            $new_room_options .= '<option value="">No rooms available</option>';
        }



        function isProfessorAvailable($conn, $prof_code, $day, $time_start, $time_end, $semester, $ay_code, $dept_code)
        {
            // Sanitize inputs
            $sanitized_prof_code = $conn->real_escape_string($prof_code);
            $sanitized_day = $conn->real_escape_string($day);
            $sanitized_time_start = $conn->real_escape_string($time_start);
            $sanitized_time_end = $conn->real_escape_string($time_end);
            $sanitized_semester = $conn->real_escape_string($semester);
            $sanitized_ay_code = $conn->real_escape_string($ay_code);
            $sanitized_dept_code = $conn->real_escape_string($dept_code);

            // Construct the dynamic table name for professor schedules
            $prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sanitized_dept_code}_{$sanitized_ay_code}");
            $pcontact_sched_code = "tbl_pcontact_sched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sanitized_dept_code}_{$sanitized_ay_code}");

            // Debug output for table name
            error_log("Checking table: $prof_sched_code");

            // Check if the dynamic table exists
            $check_table_sql = "SHOW TABLES LIKE '$prof_sched_code'";
            $check_table_result = $conn->query($check_table_sql);

            if ($check_table_result === false) {
                // Error occurred while checking table existence
                error_log("Error checking table existence: " . $conn->error);
                return false;
            }

            if ($check_table_result->num_rows > 0) {
                // If the table exists, check if the professor has any conflicts
                $sql = "SELECT * FROM `$prof_sched_code` 
                        WHERE prof_code = ? 
                        AND day = ? 
                        AND semester = ? 
                        AND ay_code = ?";

                error_log("SQL Query for prof schedule: $sql");

                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    error_log("Error preparing statement: " . $conn->error);
                    return false;
                }

                $stmt->bind_param('ssss', $sanitized_prof_code, $sanitized_day, $sanitized_semester, $sanitized_ay_code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result === false) {
                    error_log("Error executing query: " . $conn->error);
                    return false;
                }

                while ($row = $result->fetch_assoc()) {
                    $existing_start = $row['time_start'];
                    $existing_end = $row['time_end'];

                    error_log("Existing schedule (prof_sched): Start - $existing_start, End - $existing_end");

                    // Check for time conflict
                    if (($sanitized_time_start < $existing_end && $sanitized_time_end > $existing_start)) {
                        error_log("Conflict detected (prof_sched): Professor '$sanitized_prof_code' is already booked from $existing_start to $existing_end.");
                        return false;
                    }
                }
            }

            // Now, check the tbl_pcontact_sched for conflicts
            $contact_sql = "SELECT * FROM `$pcontact_sched_code` 
                            WHERE prof_code = ? 
                            AND day = ? 
                            AND semester = ? 
                            ";

            error_log("SQL Query for pcontact schedule: $contact_sql");

            $stmt = $conn->prepare($contact_sql);
            if ($stmt === false) {
                error_log("Error preparing pcontact schedule statement: " . $conn->error);
                return false;
            }

            $stmt->bind_param('sss', $sanitized_prof_code, $sanitized_day, $sanitized_semester);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result === false) {
                error_log("Error executing pcontact schedule query: " . $conn->error);
                return false;
            }

            while ($row = $result->fetch_assoc()) {
                $existing_start = $row['time_start'];
                $existing_end = $row['time_end'];

                error_log("Existing schedule (pcontact_sched): Start - $existing_start, End - $existing_end");

                // Check for time conflict
                if (($sanitized_time_start < $existing_end && $sanitized_time_end > $existing_start)) {
                    error_log("Conflict detected (pcontact_sched): Professor '$sanitized_prof_code' is already booked from $existing_start to $existing_end.");
                    return false; // Conflict detected in contact schedule
                }
            }

            // No conflicts detected in either table
            error_log("No conflict detected for professor '$sanitized_prof_code' in both schedules.");
            return true;
        }

        // Fetch and display professor options


        $new_professor_options = '';

        $professors_sql = "SELECT p.prof_code, p.prof_name, 
                                 COALESCE(c.teaching_hrs, 0) AS teaching_hrs,
                                 COALESCE(ac.course_counter, 0) AS course_counter
                             FROM tbl_prof p
                             LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                             LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
                             AND c.semester = '$semester' 
                             AND ps.ay_code = '$ay_code'
                             LEFT JOIN tbl_assigned_course ac ON p.prof_code = ac.prof_code
                             AND ac.semester = '$semester'
                             AND ac.dept_code = '$dept_code'
                             AND ac.course_code = '$new_course_code'
                             WHERE p.dept_code = '$dept_code' AND p.acc_status = '1' AND p.ay_code = '$ay_code' AND p.semester = '$semester'
                             GROUP BY p.prof_code, p.prof_name
                             ORDER BY course_counter DESC, p.prof_code";

        $professors_result = $conn->query($professors_sql);

        if ($professors_result->num_rows > 0) {
            $first = true;
            while ($row = $professors_result->fetch_assoc()) {
                $prof_code = $row["prof_code"];
                $prof_name = $row["prof_name"];
                $current_teaching_hrs = $row["teaching_hrs"];

                if (!empty($prof_name)) {
                    $course_counter = $row["course_counter"];
                } else {
                    $course_counter = 0;
                }

                if (isProfessorAvailable($conn, $prof_code, $day, $time_start, $time_end, $semester, $ay_code, $dept_code)) {
                    $suggested = $course_counter > 0 ? " - recommended" : "";
                    $new_professor_options .= '<option value="' . $prof_code . '">' .
                        $prof_code . ' - ' .
                        $prof_name . ' (' .
                        $current_teaching_hrs . 'hrs' .
                        $suggested .
                        ')</option>';

                    if ($first) {
                        $firstProfOption = $prof_code; // Store the first professor option
                        $first = false; // Reset the flag
                    }
                }
            }
        } else {
            $new_professor_options .= '<option value="">No professors available</option>';
        }




        if ($user_type === "CCL Head" && $computer_room === "1") {
            $profReadonly = 'readonly';
            $lastSelectedProf = $lastSelectedProf;
        } elseif ($user_type === "CCL Head" && empty($shared_sched)) {
            $lastSelectedRoom = $firstRoomOption;
        }

        if (($user_type === "Department Secretary" || $user_type === 'Department Chairperson') && $computer_room === 1 && $class_type === "lab" && ($ccl_college_code === $section_college_code)) {
            if (empty($shared_sched)) {
                $roomReadonly = 'readonly';
            }
            $courseReadonly = 'readonly';
            $disabled = 'disabled';
            $btnDelete = 'style="display:none;"';
            $btnShare = 'style="display:none;"';
            $btnUpdate = 'style="display:inline;"';

            $lastSelectedRoom = isset($lastSelectedRoom) ? $lastSelectedRoom : $firstRoomOption;
            $lastSelectedProf = $firstProfOption;

        } elseif (($user_type === "Department Secretary" || $user_type === 'Department Chairperson') && empty($shared_sched) && $computer_room === 0) {
            $lastSelectedProf = $firstProfOption;
            $lastSelectedRoom = $firstRoomOption;
            $btnDelete = 'style="display:inline;"';
            $btnUpdate = 'style="display:inline;"';
            if ($user_type === 'Department Chairperson') {
                $btnShare = 'style="display:none;"';
            } else {
                $btnShare = 'style="display:inline;"';
            }

        } elseif ($user_type === "CCL Head") {
            $lastSelectedProf = isset($lastSelectedProf) ? $lastSelectedProf : $firstProfOption;
            $lastSelectedRoom = $firstRoomOption;
            $btnDelete = 'style="display:inline;"';
            $profReadonly = 'readonly';
            $btnShare = 'style="display:none;"';
            $btnUpdate = 'style="display:inline;"';

        } else {
            if (empty($shared_sched) && empty($shared_to)) {

                $btnDelete = 'style="display:inline;"';
                $btnUpdate = 'style="display:inline;"';
                $lastSelectedProf = $firstProfOption;
                $lastSelectedRoom = $firstRoomOption;

                if ($user_type === "Department Chairperson") {
                    $btnShare = 'style="display:none;"';
                } else {
                    $btnShare = 'style="display:inline;"';
                }
            }
        }


        // DIET == DIET
        if (!empty($shared_sched) && !empty($shared_to)) {
            if ($shared_to === $current_user_email) {
                if ($shared_sched === 'room') {
                    $profReadonly = 'readonly';
                    $btnDelete = 'style="display:none;"';
                    $btnUpdate = 'style="display:inline;"';
                    $btnUnShare = 'style="display:inline;"';
                    $disabled = 'disabled';
                    $btnShare = 'style="display:none;"';
                    // echo "dasxds";

                    // Hide professor input and datalist
                    $lastSelectedProf = isset($lastSelectedProf) ? $lastSelectedProf : $firstProfOption;
                    $lastSelectedRoom = $firstRoomOption;
                } elseif ($shared_sched === 'prof') {
                    $roomReadonly = 'readonly'; // Hide room input and datalist
                    $lastSelectedRoom = isset($lastSelectedRoom) ? $lastSelectedRoom : $firstRoomOption;
                    $lastSelectedProf = $firstProfOption;
                    $btnDelete = 'style="display:none;"';
                    $btnUpdate = 'style="display:inline;"';
                    $btnUnShare = 'style="display:inline;"';
                    $disabled = 'disabled';
                    $btnShare = 'style="display:none;"';
                    // echo "dasds";
                }

            }
            // DIET == DIT
            elseif (($shared_to !== $current_user_email) && $shared_sched === "room") {
                $lastSelectedRoom = isset($lastSelectedRoom) ? $lastSelectedRoom : $firstRoomOption;
                $lastSelectedProf = $firstProfOption;
                $btnDelete = 'style="display:none;"';
                $btnUpdate = 'style="display:inline;"';
                $btnUnShare = 'style="display:inline;"';
                $btnShare = 'style="display:none;"';
                $disabled = 'disabled';
            } elseif (($shared_to !== $current_user_email) && $shared_sched === "prof") {
                $lastSelectedProf = isset($lastSelectedProf) ? $lastSelectedProf : $firstProfOption;
                $lastSelectedRoom = $firstRoomOption;
                $btnDelete = 'style="display:none"';
                $btnUpdate = 'style="display:inline;"';
                $btnUnShare = 'style="display:inline;"';
                $disabled = 'disabled';
                $btnShare = 'style="display:none;"';

            }

            $btnAdd = 'style="display:none;"';

        }

        $_SESSION['show_buttons'] = true;

        // Start generating HTML/JavaScript content
        echo '<script>
document.addEventListener("DOMContentLoaded", function () {';

        if (isset($_SESSION['show_buttons']) && $_SESSION['show_buttons']) {
            echo '
        // Show and hide elements based on session state
        document.getElementById("new_filter").style.display = "inline";
        document.getElementById("new_course_code").style.display = "inline";
        document.getElementById("course_code").style.display = "none";
        document.getElementById("course_code_label").style.display = "none";
        document.getElementById("new_course_code_label").style.display = "inline";
        document.getElementById("plotScheduleBtn").style.display = "none";
        document.getElementById("filter").style.display = "none";

        // Call toggle functions
        toggleProfessorCodeInput();
        toggleRoomCodeInput();';

            // Unset the session variable after use
            unset($_SESSION['show_buttons']);
        }

        echo '
});
</script>';



        ////
    }

}


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


///////////


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_table'])) {
    $section_sched_code = $_POST['section_sched_code'];
    $section_code = $_POST['section_code'];
    $ay_code = $_POST['ay_code'];
    $semester = $_POST['semester'];
    $status = 'draft';
    // echo $ay_code;
    // echo $section_sched_code;
    // echo $section_code;
    if (empty($section_sched_code) || empty($section_code) || empty($ay_code) || empty($semester)) {
        echo "All fields are required.";
        exit;
    }

    // Fetch dept_code and program_code based on section_code
    $sql = "SELECT dept_code, program_code,curriculum,petition FROM tbl_section WHERE section_code = '$section_code' AND college_code ='$college_code'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "Invalid section code.";
        exit;
    }

    $row = $result->fetch_assoc();
    $dept_code = $row['dept_code'];
    $program_code = $row['program_code'];
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
                $stmt->bind_param("ssssssssi", $section_college_code, $section_sched_code, $section_curriculum, $semester, $dept_code, $status, $ay_code, $color, $petition);


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
        header("Location: plotSchedule.php");
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
                        class_type VARCHAR(100) NOT NULL,
                        CONSTRAINT fk_section_sched_code_{$sanitized_section_code}_{$unique_id} FOREIGN KEY (section_sched_code) REFERENCES tbl_secschedlist(section_sched_code)";

        $sql = "CREATE TABLE $table_name ($columns_sql)";

        if ($conn->query($sql) === TRUE) {
            echo "Table $table_name created successfully";
            // Redirect to plotSchedule.php
            $_SESSION['section_sched_code'] = $section_sched_code;
            $_SESSION['semester'] = $semester;
            $_SESSION['section_code'] = $section_code;
            $_SESSION['table_name'] = $table_name;

            header("Location: plotSchedule.php");
            exit();
        } else {
            echo "Error creating table: " . $conn->error;
        }
    }

    $conn->close();
}

// Check if the request method is POST

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveDraftButton'])) {

    // Validate the token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to error page
        header("Location: $error_redirect_url");
        exit;
    }

    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Retrieve and sanitize POST data
    $sectionSchedCode = isset($_POST['section_sched_code']) ? $_POST['section_sched_code'] : '';
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '';
    $deptCode = isset($_POST['dept_code']) ? $_POST['dept_code'] : '';
    $status = "draft"; // Set status to "Draft"
    $ayCode = isset($_POST['ay_code']) ? $_POST['ay_code'] : '';


    // Check if the schedule already exists in the database
    $checkSql = "SELECT 1 FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $checkStmt->bind_param("ssss", $sectionSchedCode, $semester, $deptCode, $ayCode);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Schedule exists, update status to "Draft"
            $updateSql = "UPDATE tbl_schedstatus SET status = ? WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
            if ($updateStmt = $conn->prepare($updateSql)) {
                $updateStmt->bind_param("sssss", $status, $sectionSchedCode, $semester, $deptCode, $ayCode);

                // Execute update query
                if ($updateStmt->execute()) {
                    $success[] = "Schedule save as draft.";

                    if (!empty($success)) {
                        // Output conflicts list in a modal using JavaScript
                        echo "<script type='text/javascript'>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var conflictList = document.getElementById('conflictList');";

                        foreach ($success as $execute) {
                            // Safely handle quotes to prevent JS errors
                            $safe_execute = htmlspecialchars($execute, ENT_QUOTES, 'UTF-8');
                            echo "var li = document.createElement('li');
                                  li.textContent = '$safe_execute';
                                  conflictList.appendChild(li);";
                        }

                        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                              myModal.show();
                            });
                        </script>";
                    }
                } else {
                    echo "Error: " . $updateStmt->error;
                }

                // Close statement
                $updateStmt->close();
            } else {
                echo "Error preparing update statement: " . $conn->error;
            }
        }

        // Close check statement
        $checkStmt->close();
    } else {
        echo "Error preparing check statement: " . $conn->error;
    }

    // Close connection
    $conn->close();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveCompleteButton'])) {

    // Validate the token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to error page
        header("Location: $error_redirect_url");
        exit;
    }

    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Retrieve and sanitize POST data
    $sectionSchedCode = isset($_POST['section_sched_code']) ? $_POST['section_sched_code'] : '';
    $semester = isset($_POST['semester']) ? $_POST['semester'] : '';
    $deptCode = isset($_POST['dept_code']) ? $_POST['dept_code'] : '';
    $status = "private"; // Set status to "Complete"
    $ayCode = isset($_POST['ay_code']) ? $_POST['ay_code'] : '';
    $success = [];

    // Check if the schedule already exists in the database
    $checkSql = "SELECT 1 FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $checkStmt->bind_param("ssss", $sectionSchedCode, $semester, $deptCode, $ayCode);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            // Schedule exists, update status to "Complete"
            $updateSql = "UPDATE tbl_schedstatus SET status = ? WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
            if ($updateStmt = $conn->prepare($updateSql)) {
                $updateStmt->bind_param("sssss", $status, $sectionSchedCode, $semester, $deptCode, $ayCode);

                // Execute update query
                if ($updateStmt->execute()) {

                    $success[] = "Schedule save as complete";

                    if (!empty($success)) {
                        // Output conflicts list in a modal using JavaScript
                        echo "<script type='text/javascript'>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var conflictList = document.getElementById('conflictList');";

                        foreach ($success as $execute) {
                            // Safely handle quotes to prevent JS errors
                            $safe_execute = htmlspecialchars($execute, ENT_QUOTES, 'UTF-8');
                            echo "var li = document.createElement('li');
                                  li.textContent = '$safe_execute';
                                  conflictList.appendChild(li);";
                        }

                        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                              myModal.show();
                            });
                        </script>";
                    }
                } else {
                    echo "Error: " . $updateStmt->error;
                }

                // Close statement
                $updateStmt->close();
            } else {
                echo "Error preparing update statement: " . $conn->error;
            }
        }
        // Close check statement
        $checkStmt->close();
    } else {
        echo "Error preparing check statement: " . $conn->error;
    }

}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deleteButton'])) {

    $fetch_info_query = "SELECT dept_code,section_code,program_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code' AND ay_code = '$ay_code'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_dept_code = $row['dept_code'];
        $section_code = $row['section_code'];
        $program_code = $row['program_code'];
    } else {
        die("Error: No matching section schedule found for code '$section_sched_code'.");
    }

    $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

    // Fetch data from section schedule table
    $sql = " SELECT * FROM $sanitized_dept_code  WHERE section_sched_code = ? AND semester = ?";

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

            $sql_secsched = "SELECT shared_sched, shared_to, course_code,dept_code FROM $sanitized_dept_code WHERE sec_sched_id = ? AND section_sched_code = ?";
            $stmt = $conn->prepare($sql_secsched);

            if ($stmt) {
                $stmt->bind_param("ss", $sec_sched_id, $section_sched_code); // Assuming sec_sched_id is an integer
                $stmt->execute();
                $result_secsched = $stmt->get_result();

                if ($row_secsched = $result_secsched->fetch_assoc()) {
                    $row_shared_sched = $row_secsched['shared_sched'];
                    $row_shared_to = $row_secsched['shared_to'];
                    $course_code = $row_secsched['course_code'];
                    $Cdepartment = $row_secsched['dept_code'];
                    $dept_code_internal = $row_secsched['dept_code'];

                    // Retrieve department code based on shared email
                    $sql_dept = "SELECT dept_code FROM tbl_prof_acc WHERE cvsu_email = ? AND semester = ? AND ay_code = ?";
                    $stmt_dept = $conn->prepare($sql_dept);

                    if ($stmt_dept) {
                        $stmt_dept->bind_param("ssi", $row_shared_to, $semester, $ay_code);
                        $stmt_dept->execute();
                        $result_dept = $stmt_dept->get_result();

                        if ($result_dept->num_rows > 0) {
                            $row_dept = $result_dept->fetch_assoc();
                            $row_shared_dept_code = $row_dept['dept_code'];
                        } else {
                            // echo "No matching department found for the provided email.";
                        }

                        $stmt_dept->close();
                    } else {
                        echo "Error preparing department query: " . $conn->error;
                    }
                }
                $stmt->close();
            } else {
                echo "Error preparing section schedule query: " . $conn->error;
            }

            $curriculum_check_query = "SELECT curriculum,program_code FROM tbl_section 
            WHERE section_code = '$section_code' 
            AND dept_code = '$section_dept_code'";
            $curriculum_result = $conn->query($curriculum_check_query);

            $section_curriculum = ''; // Initialize to store the curriculum type
            if ($curriculum_result->num_rows > 0) {
                $curriculum_row = $curriculum_result->fetch_assoc();
                $section_curriculum = $curriculum_row['curriculum'];
                $program_code = $curriculum_row['program_code'];
            }

            $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
            $stmt_year_level = $conn->prepare($sql_year_level);
            $stmt_year_level->bind_param("ssss", $section_code, $section_dept_code, $semester, $ay_code);
            $stmt_year_level->execute();
            $stmt_year_level->bind_result($year_level);
            $stmt_year_level->fetch();
            $stmt_year_level->close();

            if (empty($row_shared_sched)) {
                // If no shared schedule is defined
                $RMdepartment = $dept_code_internal;
                $PFdepartment = $dept_code_internal;
                // echo "empty";
            }
            if ($row_shared_sched === "room") {
                // If the shared schedule is for rooms
                if ($row_shared_to === $current_user_email) {
                    $PFdepartment = $dept_code_internal;
                } else {
                    $PFdepartment = $dept_code;

                }
                $RMdepartment = $row_shared_dept_code;
                // echo "room";
            }

            if ($row_shared_sched === "prof") {
                //DIT
                if ($row_shared_to === $current_user_email) {
                    $RMdepartment = $dept_code_internal;

                } else {
                    $RMdepartment = $dept_code;
                }

                $PFdepartment = $row_shared_dept_code;
                echo "prof ";
            }
            // Output results
            // echo $RMdepartment;
            // echo $PFdepartment;
            // echo $row_shared_sched;
            // echo $row_shared_to;
            // echo $sanitized_dept_code;

            // $fetch_info_query = "SELECT * FROM tbl_course 
            // WHERE dept_code = '$Cdepartment' 
            // AND course_code = '$course_code' 
            // AND curriculum = '$section_curriculum' 
            // AND semester = '$semester' 
            // AND program_code = '$program_code' 
            // AND year_level = '$year_level'";

            // $result_course = $conn->query($fetch_info_query);

            // if ($result_course->num_rows > 0) {
            //     $row = $result_course->fetch_assoc();
            //     $lec_hrs = $row['lec_hrs'];
            //     $lab_hrs = $row['lab_hrs'];
            // } else {
            //     echo "<pre>Error: No matching course found for code '$section_sched_code'.</pre>";
            // }

            // if ($class_type === 'lec') {
            //     $teaching_hrs = $lec_hrs; // Use '=' for assignment
            // } else {
            //     $teaching_hrs = $lab_hrs; // Use '=' for assignment
            // }


            $sql_delete_section = "  DELETE FROM $sanitized_dept_code  WHERE sec_sched_id = ? AND semester = ? AND section_sched_code = ?";
            $stmt_delete_section = $conn->prepare($sql_delete_section);
            $stmt_delete_section->bind_param('sss', $sec_sched_id, $semester, $section_sched_code);
            if ($stmt_delete_section->execute()) {
                // echo "Section schedule record deleted successfully.<br>";
            } else {
                echo "Error deleting section schedule record: " . $stmt_delete_section->error . "<br>";
            }
            $stmt_delete_section->close();

            // Delete from schedstatus table
            $sql_delete_schedstatus = " DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
            $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
            if ($stmt_delete_schedstatus->execute()) {
                // echo "Schedstatus record deleted successfully.<br>";
            } else {
                echo "Error deleting schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
            }
            $stmt_delete_schedstatus->close();

            if (!empty($room_code)) {

                $sql_check_room = "SELECT allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
                $stmt_check_room = $conn->prepare($sql_check_room);
                $stmt_check_room->bind_param("ssss", $course_code, $program_code, $year_level, $semester);
                $stmt_check_room->execute();
                $result_check_room = $stmt_check_room->get_result();

                if ($result_check_room->num_rows > 0) {
                    $check_room_row = $result_check_room->fetch_assoc();
                    $allowed_rooms = $check_room_row['allowed_rooms'];
                    $computer_room = $check_room_row['computer_room'];
                }


                if ($computer_room == 1 && $class_type == 'lab') {
                    $plot_room_dept_code = $ccl_college_code;
                    // $plot_prof_dept_code = $room_dept_code;
                    // $prof_dept_code = $room_dept_code;
                    // $course_dept_code = $section_dept_code;
                } else {
                    $plot_room_dept_code = $RMdepartment;
                    // $plot_prof_dept_code = $user_dept_code;
                    // $prof_dept_code = $user_dept_code;
                    // $course_dept_code = $user_dept_code;
                }

                $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");
                $room_sched_code = $room_code . "_" . $ay_code;
                // Delete from section schedule table

                $database_name = $conn->real_escape_string($dbname);
                // Fetch and delete from room schedule table
                $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                $stmt_check = $conn->prepare($table_check_sql);
                $stmt_check->bind_param('ss', $database_name, $sanitized_room_dept_code);
                $stmt_check->execute();
                $stmt_check->bind_result($table_exists);
                $stmt_check->fetch();
                $stmt_check->close();
                echo "room_sched_code: " . $room_sched_code . "<br>";
                echo "sec_sched_id: " . $sec_sched_id . "<br>";
                echo "semester: " . $semester . "<br>";
                echo "dept_code: " . $RMdepartment . "<br>";
                echo "dept_code: " . $sanitized_room_dept_code . "<br>";

                // Proceed with the query if the table exists
                if ($table_exists > 0) {

                    $sql_room = " SELECT * FROM $sanitized_room_dept_code WHERE room_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code =?";

                    $stmt_room = $conn->prepare($sql_room);
                    $stmt_room->bind_param('ssss', $room_sched_code, $semester, $sec_sched_id, $RMdepartment);
                    $stmt_room->execute();
                    $result_room = $stmt_room->get_result();


                    if ($result_room->num_rows > 0) {

                        $sql_delete_room = "  DELETE FROM $sanitized_room_dept_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                        // echo $sql_delete_room;
                        // echo "SQL Statement: " . $sql_delete_room . "<br>";
                        // echo "sec_sched_id: " . $sec_sched_id . "<br>";
                        // echo "semester: " . $semester . "<br>";
                        // echo "dept_code: " . $RMdepartment . "<br>";
                        $stmt_delete_room = $conn->prepare($sql_delete_room);
                        $stmt_delete_room->bind_param('ssss', $sec_sched_id, $semester, $RMdepartment, $section_sched_code);

                        if ($stmt_delete_room->execute()) {
                            // echo "Room schedule record deleted successfully.<br>";
                        } else {
                            echo "Error deleting room schedule record: " . $stmt_delete_room->error . "<br>";
                        }
                        $stmt_delete_room->close();



                        $sql_check_no_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_dept_code WHERE room_code = '$room_code' AND semester = '$semester'";
                        $stmt_check_no_room = $conn->prepare($sql_check_no_room);
                        $stmt_check_no_room->execute();
                        $result_check_no_room = $stmt_check_no_room->get_result();
                        $row_count_room_no = $result_check_no_room->fetch_assoc()['row_count'];
                        $stmt_check_no_room->close();

                        if ($row_count_room_no == 0) {
                            $sql_delete_schedlist = " DELETE FROM tbl_rsched WHERE room_sched_code = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_schedlist = $conn->prepare($sql_delete_schedlist);
                            $stmt_delete_schedlist->bind_param('sss', $room_sched_code, $ay_code, $RMdepartment);
                            if ($stmt_delete_schedlist->execute()) {
                                // echo "Room schedlist record deleted successfully.<br>";
                            } else {
                                echo "Error deleting room schedlist record: " . $stmt_delete_schedlist->error . "<br>";
                            }
                            $stmt_delete_schedlist->close();

                            // Delete corresponding entries from tbl_schedstatus
                            $sql_delete_schedstatus = " DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('ssss', $room_sched_code, $semester, $ay_code, $RMdepartment);
                            if ($stmt_delete_schedstatus->execute()) {
                                // echo "Room schedstatus record deleted successfully.<br>";
                            } else {
                                echo "Error deleting room schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
                            }
                            $stmt_delete_schedstatus->close();
                        }
                    } else {
                        echo "No room schedule records found.<br>";
                    }

                    $stmt_room->close();
                }
            }


            if (!empty($prof_code)) {

                $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $PFdepartment . "_" . $ay_code);
                $prof_sched_code = $prof_code . "_" . $ay_code;
                // Fetch and delete from professor schedule table
                $database_name = $conn->real_escape_string($dbname);
                // Fetch and delete from room schedule table
                $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                $stmt_check = $conn->prepare($table_check_sql);
                $stmt_check->bind_param('ss', $database_name, $sanitized_prof_dept_code);
                $stmt_check->execute();
                $stmt_check->bind_result($table_exists);
                $stmt_check->fetch();
                $stmt_check->close();

                echo "SQL Statement: " . $sql_room . "<br>";
                echo "prof_sched_code: " . $prof_sched_code . "<br>";
                echo "sec_sched_id: " . $sec_sched_id . "<br>";
                echo "semester: " . $semester . "<br>";
                echo "dept_code: " . $PFdepartment . "<br>";
                echo "dept_code: " . $sanitized_prof_dept_code . "<br>";

                // Proceed with the query if the table exists
                if ($table_exists > 0) {
                    $sql_prof = " SELECT * FROM $sanitized_prof_dept_code WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                    $stmt_prof = $conn->prepare($sql_prof);
                    $stmt_prof->bind_param('ssss', $prof_sched_code, $semester, $sec_sched_id, $PFdepartment);
                    $stmt_prof->execute();
                    $result_prof = $stmt_prof->get_result();


                    if ($result_prof->num_rows > 0) {

                        $sql_sched = "SELECT time_start, time_end FROM $sanitized_prof_dept_code WHERE sec_sched_id='$sec_sched_id' AND dept_code = '$PFdepartment' AND section_code = '$section_sched_code'";
                        $result_sched = $conn->query($sql_sched);
                        $row_sched = $result_sched->fetch_assoc();
                        $time_start = $row_sched['time_start'];
                        $time_end = $row_sched['time_end'];

                        $time_start_dt = new DateTime($time_start);
                        $time_end_dt = new DateTime($time_end);
                        $duration = $time_start_dt->diff($time_end_dt);
                        $duration_hours = $duration->h + ($duration->i / 60);


                        $sql_delete_prof = " DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                        $stmt_delete_prof = $conn->prepare($sql_delete_prof);
                        $stmt_delete_prof->bind_param('ssss', $sec_sched_id, $semester, $PFdepartment, $section_sched_code);
                        if ($stmt_delete_prof->execute()) {
                            // echo "Professor schedule record deleted successfully.<br>";
                        } else {
                            echo "Error deleting professor schedule record: " . $stmt_delete_prof->error . "<br>";
                        }


                        $fetch_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester'  AND dept_code = '$PFdepartment'";
                        $prof_hours_result = $conn->query($fetch_prof_hours_query);

                        if ($prof_hours_result->num_rows > 0) {
                            $prof_hours_row = $prof_hours_result->fetch_assoc();
                            $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                            $prep_hours = $prof_hours_row['prep_hrs'];

                            $check_query_prep = "SELECT * FROM $sanitized_prof_dept_code 
                                                    WHERE prof_sched_code = '$prof_sched_code' 
                                                    AND course_code = '$course_code' 
                                                    AND semester = '$semester' 
                                                    AND curriculum = '$section_curriculum' AND class_type = '$class_type'";
                            $check_result_prep = $conn->query($check_query_prep);


                            // If the professor has not taught this course in the current curriculum, add 1 prep hour
                            if ($check_result_prep->num_rows === 0) {
                                while ($row = $check_result_prep->fetch_assoc()) {
                                    // echo "<pre>";
                                    // print_r($row);
                                    // echo "</pre>";
                                }
                                $prep_hours = $prep_hours - 1;
                            } else {
                                $prep_hours = $prep_hours;
                            }


                            $prof_sched_code = $prof_code . "_" . $ay_code;
                            $new_teaching_hours = $current_teaching_hours - $duration_hours;

                            $sql_prof_type_consult = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ? ";
                            $stmt_consultation = $conn->prepare($sql_prof_type_consult);
                            $stmt_consultation->bind_param("sssi", $prof_code, $PFdepartment, $semester, $ay_code);
                            $stmt_consultation->execute();
                            $result_consultation = $stmt_consultation->get_result();
                            // echo $semester . $PFdepartment;
                            if ($result_consultation->num_rows > 0) {
                                $row = $result_consultation->fetch_assoc();
                                $prof_type = $row['prof_type'];

                                if ($prof_type == 'Regular') {
                                    // If the professor is Regular, use the formula directly
                                    $consultation_hrs = $new_teaching_hours / 3;
                                } else {
                                    // If the professor is not Regular, check the teaching hours
                                    if ($new_teaching_hours >= 18) {
                                        // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                        $consultation_hrs = 2;
                                    } else {
                                        // If teaching hours are less than 18, set consultation hours to 0
                                        $consultation_hrs = 0;
                                    }
                                }

                                // Optional: Debugging output
                                // echo "Consultation Hours: " . $consultation_hrs;

                            } else {
                                echo "Professor not found.";
                            }

                            // echo $prof_type;

                            $stmt_consultation->close();

                            $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', prep_hrs = '$prep_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester ='$semester' AND dept_code = '$PFdepartment' ";

                            if ($conn->query($update_hours_query) === TRUE) {
                                // echo "Teaching hours updated successfully for plotting.<br>";
                            } else {
                                echo "Error updating teaching hours: " . $conn->error . "<br>";
                            }
                        } else {
                            die("Error: Professor details not found.");
                        }

                        $course_counter_update_query = " UPDATE tbl_assigned_course  SET course_counter = course_counter - 1 WHERE prof_code = ? AND course_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_course_counter = $conn->prepare($course_counter_update_query);
                        $stmt_course_counter->bind_param('ssss', $prof_code, $course_code, $semester, $PFdepartment);
                        if ($stmt_course_counter->execute()) {
                            echo "Course counter updated successfully.<br>";
                        } else {
                            echo "Error updating course counter: " . $stmt_course_counter->error . "<br>";
                        }
                        $stmt_course_counter->close();

                        //prof delete here


                        $stmt_delete_prof->close();
                        // Check if professor schedule table is empty


                        $sql_check_no_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_dept_code  WHERE prof_code = '$prof_code' AND semester = '$semester'";
                        // echo "Executing SQL: $sql_check_no_prof<br>";
                        $stmt_check_no_prof = $conn->prepare($sql_check_no_prof);
                        $stmt_check_no_prof->execute();
                        $result_check_no_prof = $stmt_check_no_prof->get_result();
                        $row_count_prof_no = $result_check_no_prof->fetch_assoc()['row_count'];
                        $stmt_check_no_prof->close();

                        if ($row_count_prof_no == 0) {
                            $sql_delete_schedlist = "
                        DELETE FROM tbl_psched 
                        WHERE prof_sched_code = ? AND ay_code = ? AND dept_code = ?";
                            // echo "Executing SQL: $sql_delete_schedlist with prof_sched_code=$prof_sched_code, ay_code=$ay_code, dept_code=$dept_code<br>";
                            $stmt_delete_schedlist = $conn->prepare($sql_delete_schedlist);
                            $stmt_delete_schedlist->bind_param('sss', $prof_sched_code, $ay_code, $dept_code);
                            $stmt_delete_schedlist->execute();
                            $stmt_delete_schedlist->close();

                            // Delete from tbl_prof_schedstatus
                            $sql_delete_schedstatus = "
                            DELETE FROM tbl_prof_schedstatus 
                            WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?";
                            // echo "Executing SQL: $sql_delete_schedstatus with prof_sched_code=$prof_sched_code, semester=$semester, and ay_code=$ay_code<br>";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $ay_code);
                            $stmt_delete_schedstatus->execute();
                            $stmt_delete_schedstatus->close();
                        }


                        // $sql_check_empty_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_dept_code ";
                        // echo "Executing SQL: $sql_check_empty_prof<br>";
                        // $stmt_check_empty_prof = $conn->prepare($sql_check_empty_prof);
                        // $stmt_check_empty_prof->execute();
                        // $result_check_empty_prof = $stmt_check_empty_prof->get_result();
                        // $row_count_prof = $result_check_empty_prof->fetch_assoc()['row_count'];
                        // $stmt_check_empty_prof->close();

                        // if ($row_count_prof == 0) {
                        //     $sql_drop_prof_table = "DROP TABLE IF EXISTS $sanitized_prof_dept_code";
                        //     echo "Executing SQL: $sql_drop_prof_table<br>";
                        //     $stmt_drop_prof_table = $conn->prepare($sql_drop_prof_table);
                        //     $stmt_drop_prof_table->execute();
                        //     $stmt_drop_prof_table->close();
                        // }

                    } else {
                        echo "No professor schedule records found.<br>";
                    }
                    $stmt_prof->close();
                }
            }

        }
        $sql_check_no = "SELECT COUNT(*) AS row_count FROM $sanitized_dept_code WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
        $stmt_check_no = $conn->prepare($sql_check_no);
        $stmt_check_no->execute();
        $result_check_no = $stmt_check_no->get_result();
        $row_count_sec = $result_check_no->fetch_assoc()['row_count'];
        $stmt_check_no->close();

        // Drop the table if it's emptysss
        if ($row_count_sec == 0) {
            $sql_delete_schedstatus = " DELETE FROM tbl_secschedlist WHERE section_sched_code = ?  AND dept_code = ?";
            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
            $stmt_delete_schedstatus->bind_param('ss', $section_sched_code, $dept_code);
            $stmt_delete_schedstatus->execute();
            $stmt_delete_schedstatus->close();

            // $sql_delete_schedstatus = " DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
            // $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
            // $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
            // if ($stmt_delete_schedstatus->execute()) {
            //     // echo "Schedstatus record deleted successfully.<br>";
            // } else {
            //     echo "Error deleting schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
            // }
            // $stmt_delete_schedstatus->close();

            $sql_delete_schedstatus = " DELETE FROM tbl_shared_sched WHERE shared_section = ? AND semester = ? AND ay_code = ?";
            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
            $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
            $stmt_delete_schedstatus->execute();
            $stmt_delete_schedstatus->close();

        } else {
            echo "No section schedule records found.<br>";
        }
        header("Location: /SchedSys3/php/department_secretary/dept_sec.php");
        exit();
    } else {
        $sql_delete_schedstatus = " DELETE FROM tbl_secschedlist WHERE section_sched_code = ?  AND dept_code = ?";
        $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
        $stmt_delete_schedstatus->bind_param('ss', $section_sched_code, $dept_code);
        $stmt_delete_schedstatus->execute();
        $stmt_delete_schedstatus->close();

        $sql_delete_schedstatus = " DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
        $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
        $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
        if ($stmt_delete_schedstatus->execute()) {
            // echo "Schedstatus record deleted successfully.<br>";
        } else {
            echo "Error deleting schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
        }
        $stmt_delete_schedstatus->close();

        header("Location: /SchedSys3/php/department_secretary/dept_sec.php");
        exit();
    }
}



$shared_sched = '';
$shared_sched = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send'])) {

    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Generate a session token for security (CSRF protection)
    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Retrieve form data
    $sender_dept_code = $dept_code; // Sender's department code from session
    $shared_by = $_SESSION['cvsu_email']; // Sender's email from session
    $receiver_email = htmlspecialchars($_POST['recipient_email']); // Receiver's email
    $section_sched_code = htmlspecialchars($_POST['section_sched_code']); // Section schedule code
    $sec_sched_id = htmlspecialchars($_POST['modal_sec_sched_id']); // Section schedule ID
    $room_code = $_POST['room_code'];
    $prof_code = $_POST['prof_code'];
    $shared_sched = htmlspecialchars($_POST['shared_sched']);

    $sql_dept_code = "SELECT dept_code, section_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
    $result_dept_code = $conn->query($sql_dept_code);

    // Check if the query returned a row
    if ($result_dept_code && $result_dept_code->num_rows > 0) {
        $row_dept_code = $result_dept_code->fetch_assoc();
        $row_section_dept_code = $row_dept_code['dept_code'];
        $row_section_code = $row_dept_code['section_code'];
    } else {
        // Handle the case where no row is found
        $row_section_dept_code = null;
    }

    $sql_dept = "SELECT dept_code, user_type FROM tbl_prof_acc WHERE cvsu_email='$receiver_email' AND ay_code = '$ay_code' AND semester = '$semester'";
    $result_dept = $conn->query($sql_dept);

    if ($result_dept->num_rows > 0) {
        $row_dept = $result_dept->fetch_assoc();
        $receiver_dept_code = $row_dept['dept_code'];
        $receiver_user_type = $row_dept['user_type'];
    }

    $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$row_section_dept_code}_{$ay_code}");

    $sql_secsched = "SELECT * FROM $sanitized_dept_code WHERE sec_sched_id ='$sec_sched_id' AND semester = '$semester' AND section_sched_code = '$section_sched_code'";
    $result_secsched = $conn->query($sql_secsched);

    // Check if the query returned a row
    if ($result_secsched && $result_secsched->num_rows > 0) {
        $row_secsched = $result_secsched->fetch_assoc();
        $row_shared_sched = $row_secsched['shared_sched'];
        $row_course_code = $row_secsched['course_code'];
        $row_class_type = $row_secsched['class_type'];
    } else {
        // Handle the case where no row is found
        $row_shared_sched = null;
    }

    $sql = "SELECT allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $row_course_code, $program_code, $year_level, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $allowed_rooms = $row['allowed_rooms'];
        $computer_room = $row['computer_room'];
    }


    // Sanitize the section schedule code for table name
    $messages = []; // Array to hold messages

    if ($sched_status === "draft") {
        $messages[] = 'You cannot share a Draft Schedule.';
    } else {

        if (!empty($room_code) && !empty($prof_code) && empty($row_shared_sched)) {
            $messages[] = 'No empty value';
        }

        if ($shared_sched === 'room' && empty($row_shared_sched)) {
            if (!empty($room_code)) {
                $messages[] = 'Empty the room code.';
            }
        }

        if ($shared_sched === 'prof' && empty($row_shared_sched)) {
            if (!empty($prof_code)) {
                $messages[] = 'Empty the prof code.';
            }
        }

        if (($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') && empty($row_shared_sched) && $allowed_rooms === 'lecR&labR' && $row_class_type === 'lab' && $shared_sched === 'room' && $computer_room === 1 && ($college_code === $ccl_college_code)) {
            $messages[] = 'You are not allowed to share this schedule.';
        }

        if ($receiver_user_type === 'CCL Head' && empty($row_shared_sched) && $allowed_rooms === 'lecR&labR' && $computer_room === 0) {
            $messages[] = 'The course is not allowed to use any Computer Laboratory.';
        }

        if ($receiver_user_type === 'CCL Head' && empty($row_shared_sched) && ($allowed_rooms !== 'lecR&labR' || $allowed_rooms !== 'labR') && $row_class_type !== 'lab') {
            $messages[] = 'The CCL Head cannot plot a lecture.';
        }

        if ($receiver_user_type === 'CCL Head' && empty($row_shared_sched) && $shared_sched === 'prof') {
            $messages[] = 'You cannot request a professor from the CCL Head';
        }
    }
    // If there are any messages, pass them to the modal
    if (!empty($messages)) {
        echo "<script type='text/javascript'>
                        document.addEventListener('DOMContentLoaded', function() {
                            var conflictList = document.getElementById('conflictList');";

        foreach ($messages as $message) {
            echo "var li = document.createElement('li');
                          li.textContent = '$message';
                          conflictList.appendChild(li);";
        }

        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                      myModal.show();
                    });
                </script>";
    } else {

        // Prepare the SQL statement to check for an existing shared record
        $check_sql = "SELECT COUNT(*) FROM $sanitized_dept_code
            WHERE sec_sched_id = ? AND shared_to = ? AND semester = ? AND section_sched_code = ?";

        if ($check_stmt = $conn->prepare($check_sql)) {
            // Bind the parameters: all are strings ("ssss")
            $check_stmt->bind_param("ssss", $sec_sched_id, $receiver_email, $semester, $section_sched_code);

            // Execute the statement
            if ($check_stmt->execute()) {
                // Bind the result to the $count variable
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();

                // Check if no existing record is found
                if ($count == 0) {
                    // SQL query to update the section schedule record
                    $update_sql = "UPDATE $sanitized_dept_code
                        SET shared_to = ?, shared_sched = ?
                        WHERE sec_sched_id = ? AND section_sched_code = ?";

                    if ($stmt = $conn->prepare($update_sql)) {
                        // Bind the parameters to the SQL query
                        $stmt->bind_param("ssss", $receiver_email, $shared_sched, $sec_sched_id, $section_sched_code);

                        // Execute the update statement
                        if ($stmt->execute()) {

                            $message = "You have received a schedule for section " . $section_code . " for the semester " . $semester . " from " . $shared_by;

                            $notification_sql = "INSERT INTO tbl_notifications (section_sched_code, section_code, semester, sender_email, receiver_email, message, is_read, date_sent) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
                            $notification_stmt = $conn->prepare($notification_sql);
                            $notification_stmt->bind_param('ssssss', $section_sched_code, $section_code, $semester, $shared_by, $receiver_email, $message);
                            $notification_stmt->execute();
                            $notification_stmt->close();


                            $success[] = ' Schedule shared successfully.';

                            echo "<script type='text/javascript'>
                document.addEventListener('DOMContentLoaded', function() {
                    var conflictList = document.getElementById('conflictList');";

                            foreach ($success as $success_messages) {
                                echo "var li = document.createElement('li');
                        li.textContent = '$success_messages';
                        conflictList.appendChild(li);";
                            }

                            echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                    myModal.show();
                    });
                </script>";

                        } else {
                            // Log or display the error if execution fails
                            echo "Error executing update: " . $stmt->error;
                        }

                        $stmt->close();
                    } else {
                        // Log or display the error if the statement preparation fails
                        echo "Error preparing update statement: " . $conn->error;
                    }
                } else {
                    echo "Record already exists with the specified details.";
                }
            } else {
                // Log or display the error if execution fails
                echo "Error executing check statement: " . $check_stmt->error;
            }
        } else {
            // Log or display the error if the statement preparation fails
            echo "Error preparing check statement: " . $conn->error;
        }



        $check_sql = "SELECT COUNT(*) FROM tbl_shared_sched 
            WHERE (sender_dept_code = ? AND sender_email = ? AND receiver_dept_code = ? AND receiver_email = ? AND shared_section = ?) 
            OR (sender_dept_code = ? AND sender_email = ? AND receiver_dept_code = ? AND receiver_email = ? AND shared_section = ?)";

        if ($check_stmt = $conn->prepare($check_sql)) {
            // Bind parameters for both sender-to-receiver and receiver-to-sender checks
            $check_stmt->bind_param(
                "ssssssssss",
                $sender_dept_code,
                $current_user_email,
                $receiver_dept_code,
                $receiver_email,
                $section_sched_code,
                $receiver_dept_code,
                $receiver_email,
                $sender_dept_code,
                $current_user_email,
                $section_sched_code
            );
            $check_stmt->execute();
            $check_stmt->bind_result($count);
            $check_stmt->fetch();
            $check_stmt->close();

            $share_status = 'active';
            if ($count == 0) {  // No duplicate found, proceed with insertion
                // Prepare the SQL statement for insertion
                $sql = "INSERT INTO tbl_shared_sched 
                                    (sender_dept_code, sender_email, receiver_dept_code, receiver_email, shared_section, section_code, semester, ay_code,status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?,?)";
                // echo $sender_dept_code;

                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("sssssssss", $sender_dept_code, $current_user_email, $receiver_dept_code, $receiver_email, $section_sched_code, $section_code, $semester, $ay_code, $share_status);
                    $stmt->execute(); // Execute the insertion
                    $stmt->close();  // Close the statement after execution
                }
            }
        }

    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['unsent'])) {
    // Generate a session token for security (CSRF protection)

    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Retrieve form data
    $sender_dept_code = $dept_code;
    $shared_by = $_SESSION['cvsu_email'];
    $section_sched_code = htmlspecialchars($_POST['section_sched_code']);
    $section_code = htmlspecialchars($_POST['section_code']);
    $sec_sched_id = htmlspecialchars($_POST['modal_sec_sched_id']);
    $semester = htmlspecialchars($_POST['semester']);
    $ay_code = htmlspecialchars($_POST['ay_code']);
    $room_code = $_POST['room_code'];
    $prof_code = $_POST['prof_code'];
    $null = '';
    $tba = null;
    $day = isset($_POST['day']) ? $_POST['day'] : null;
    $current_teaching_hours = null;
    $prep_hours = null;
    $consultation_hrs = null;


    $sql_dept_code = "SELECT dept_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
    $result_dept_code = $conn->query($sql_dept_code);

    // Check if the query returned a row
    if ($result_dept_code && $result_dept_code->num_rows > 0) {
        $row_dept_code = $result_dept_code->fetch_assoc();
        $row_section_dept_code = $row_dept_code['dept_code'];
    } else {
        // Handle the case where no row is found
        $row_section_dept_code = null;
    }

    $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$row_section_dept_code}_{$ay_code}");


    $sql = " SELECT  * FROM $sanitized_dept_code  WHERE section_sched_code = ? AND semester = ? AND sec_sched_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $section_sched_code, $semester, $sec_sched_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows >= 0) {

        $row = $result->fetch_assoc();
        $room_code = $row['room_code'];
        $prof_code = $row['prof_code'];
        $section_dept_code = $row['dept_code'];
        $class_type = $row['class_type'];
        $room_sched_code = $room_code . "_" . $ay_code;

        $sql_secsched = "SELECT shared_sched, shared_to, course_code FROM $sanitized_dept_code WHERE sec_sched_id = ? AND section_sched_code = ?";
        $stmt = $conn->prepare($sql_secsched);
        // echo $sec_sched_id;
        if ($stmt) {
            $stmt->bind_param("ss", $sec_sched_id, $section_sched_code); // Assuming sec_sched_id is an integer
            $stmt->execute();
            $result_secsched = $stmt->get_result();

            if ($row_secsched = $result_secsched->fetch_assoc()) {
                $row_shared_sched = $row_secsched['shared_sched'];
                $row_shared_to = $row_secsched['shared_to'];
                $course_code = $row_secsched['course_code'];

                // Retrieve department code based on shared email
                $sql_dept = "SELECT dept_code FROM tbl_prof_acc WHERE cvsu_email = ? AND ay_code = ? AND semester = ?";
                $stmt_dept = $conn->prepare($sql_dept);

                if ($stmt_dept) {
                    $stmt_dept->bind_param("sis", $row_shared_to, $ay_code, $semester);
                    $stmt_dept->execute();
                    $result_dept = $stmt_dept->get_result();

                    if ($result_dept->num_rows > 0) {
                        $row_dept = $result_dept->fetch_assoc();
                        $row_shared_dept_code = $row_dept['dept_code'];
                    } else {
                        echo "No matching department found for the provided email.";
                    }

                    $stmt_dept->close();
                } else {
                    echo "Error preparing department query: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            echo "Error preparing section schedule query: " . $conn->error;
        }

        if (empty($row_shared_sched)) {
            // If no shared schedule is defined
            $RMdepartment = $dept_code;
            $PFdepartment = $dept_code;
            $CDepartment = $dept_code;
            // echo "empty";
        }

        if ($row_shared_sched === "room") {
            // If the shared schedule is for rooms
            $RMdepartment = $row_shared_dept_code;
            $PFdepartment = $section_dept_code;

            // echo "room";
        }

        if ($row_shared_sched === "prof") {
            // If the shared schedule is for professors
            $RMdepartment = $section_dept_code;
            $PFdepartment = $row_shared_dept_code;
            $CDepartment = $row_section_dept_code;
            // echo "prof";
        }

        if (empty($row_shared_sched) && ($section_dept_code != $dept_code)) {
            // If no shared schedule is defined
            $RMdepartment = $section_dept_code;
            $PFdepartment = $section_dept_code;
            // echo "empty section";
        }


        // Output results
        // echo $RMdepartment;
        // echo $PFdepartment;
        // echo $row_shared_sched;
        // echo $row_shared_to;
        // echo $sanitized_dept_code;
        // echo $sec_sched_id;
        // echo $room_sched_code;
        // echo $section_sched_code;
    }

    $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$RMdepartment}_{$ay_code}");
    $sanitized_prof_dept_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$PFdepartment}_{$ay_code}");



    $time_start = isset($_POST['time_start']) ? $_POST['time_start'] : null;
    $time_end = isset($_POST['time_end']) ? $_POST['time_end'] : null;
    $sql_secsched = "SELECT time_start, day, time_end FROM $sanitized_dept_code WHERE sec_sched_id='$sec_sched_id' AND semester = '$semester' AND section_sched_code = '$section_sched_code'";
    $result_secsched = $conn->query($sql_secsched);

    if ($result_secsched && $result_secsched->num_rows > 0) {
        $row_secsched = $result_secsched->fetch_assoc();

        // Set values only if they are empty
        if (empty($time_start)) {
            $time_start = $row_secsched['time_start'];
        }
        if (empty($time_end)) {
            $time_end = $row_secsched['time_end'];
        }
        if (empty($day)) {
            $day = $row_secsched['day'];
        }
    }


    // Check if the schedule is already shared
    $check_sql = "SELECT COUNT(*) FROM $sanitized_dept_code WHERE sec_sched_id = ? AND semester = ? AND section_sched_code = ?";
    $check_stmt = $conn->prepare($check_sql);

    if ($check_stmt) {
        $check_stmt->bind_param("sss", $sec_sched_id, $semester, $section_sched_code);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        // If the schedule is shared, update the corresponding records
        if ($count > 0) {
            if ($row_shared_sched === 'room') {
                $database_name = $conn->real_escape_string($dbname);
                // Fetch and delete from room schedule table
                $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                $stmt_check = $conn->prepare($table_check_sql);
                $stmt_check->bind_param('ss', $database_name, $sanitized_room_dept_code);
                $stmt_check->execute();
                $stmt_check->bind_result($table_exists);
                $stmt_check->fetch();
                $stmt_check->close();

                // Proceed with the query if the table exists
                if ($table_exists > 0) {
                    if (!empty($room_code)) {
                        $delete_room_sql = "DELETE FROM $sanitized_room_dept_code WHERE sec_sched_id = ? AND dept_code = ? AND room_sched_code = ? AND section_code = ? AND semester =? ";
                        // echo $sanitized_room_dept_code;
                        $stmt_delete = $conn->prepare($delete_room_sql);
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("sssss", $sec_sched_id, $RMdepartment, $room_sched_code, $section_sched_code, $semester);
                            $stmt_delete->execute();
                            // echo "Room schedule deleted successfully.";
                            $stmt_delete->close();
                        } else {
                            echo "Error preparing room delete query: " . $conn->error;
                        }
                    }
                }
                $update_sql = "UPDATE $sanitized_dept_code SET shared_to = ?, shared_sched = ?, room_code = ? WHERE sec_sched_id = ? AND section_sched_code = ? AND semester = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("ssssss", $null, $null, $tba, $sec_sched_id, $section_sched_code, $semester);
                    $stmt_update->execute();
                    $stmt_update->close();
                }

                $database_name = $conn->real_escape_string($dbname);
                // Fetch and delete from room schedule table
                $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                $stmt_check = $conn->prepare($table_check_sql);
                $stmt_check->bind_param('ss', $database_name, $sanitized_prof_dept_code);
                $stmt_check->execute();
                $stmt_check->bind_result($table_exists);
                $stmt_check->fetch();
                $stmt_check->close();

                // Proceed with the query if the table exists
                if ($table_exists > 0) {
                    if ($section_college_code == $college_code) {
                        $update_sql = "UPDATE $sanitized_prof_dept_code SET room_code = ? WHERE sec_sched_id = ? AND section_code = ? AND semester = ?";
                        $stmt_update = $conn->prepare($update_sql);
                        if ($stmt_update) {
                            $stmt_update->bind_param("ssss", $tba, $sec_sched_id, $section_sched_code, $semester);
                            $stmt_update->execute();
                            $stmt_update->close();

                            // Delete previous room schedule
                        }
                    }
                }
            }

            if ($row_shared_sched === 'prof') {
                if (!empty($prof_code)) {

                    // Prepare the SQL statement for checking existing course
                    $check_sql = "
                            SELECT course_counter 
                            FROM tbl_assigned_course 
                            WHERE dept_code = ? 
                            AND prof_code = ? 
                            AND course_code = ? 
                            AND year_level = ? 
                            AND semester = ?";
                    // Bind parameters
                    $stmt_check = $conn->prepare($check_sql);
                    $stmt_check->bind_param("sssis", $PFdepartment, $prof_code, $course_code, $year_level, $semester);

                    // Execute the query and store the result
                    $stmt_check->execute();
                    $stmt_check->store_result();  // Required to count rows after executing the query

                    if ($stmt_check->num_rows > 0) {
                        // Bind the result
                        $stmt_check->bind_result($course_counter);
                        $stmt_check->fetch();

                        // Decrement the course_counter by 1, ensuring it does not go below 0
                        $course_counter = max(0, $course_counter - 1);

                        // Update the record with the decremented course_counter
                        $update_sql = "
                            UPDATE tbl_assigned_course 
                            SET course_counter = ?
                            WHERE dept_code = ? 
                            AND prof_code = ? 
                            AND course_code = ? 
                            AND year_level = ? 
                            AND semester = ?";

                        $stmt_update = $conn->prepare($update_sql);
                        $stmt_update->bind_param("isssis", $course_counter, $PFdepartment, $prof_code, $course_code, $year_level, $semester);
                        $stmt_update->execute();
                        $stmt_update->close();
                    }

                    $database_name = $conn->real_escape_string($dbname);
                    // Fetch and delete from room schedule table
                    $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                    $stmt_check = $conn->prepare($table_check_sql);
                    $stmt_check->bind_param('ss', $database_name, $sanitized_prof_dept_code);
                    $stmt_check->execute();
                    $stmt_check->bind_result($table_exists);
                    $stmt_check->fetch();
                    $stmt_check->close();

                    // Proceed with the query if the table exists
                    if ($table_exists > 0) {
                        $delete_prof_sql = "DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id = ? AND section_code = ? AND semester=?";
                        $stmt_delete = $conn->prepare($delete_prof_sql);
                        if ($stmt_delete) {
                            $stmt_delete->bind_param("sss", $sec_sched_id, $section_sched_code, $semester);
                            $stmt_delete->execute();
                            // echo "Prof schedule deleted successfully.";
                            $stmt_delete->close();
                        } else {
                            echo "Error preparing room delete query: " . $conn->error;
                        }
                    }



                    $update_sql = "UPDATE $sanitized_room_dept_code SET prof_name = ? , prof_code =? WHERE sec_sched_id = ? AND section_code = ? AND semester= ?";
                    $stmt_update = $conn->prepare($update_sql);
                    if ($stmt_update) {
                        $stmt_update->bind_param("sssss", $tba, $tba, $sec_sched_id, $section_sched_code, $semester);
                        $stmt_update->execute();
                        $stmt_update->close();
                    }


                    $sql_secsched = "SELECT section_code,ay_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
                    $result_secsched = $conn->query($sql_secsched);

                    if ($result_secsched->num_rows == 0) {
                        echo "Invalid section sched code.";
                        exit;
                    }
                    $row_secsched = $result_secsched->fetch_assoc();
                    $academic_year = $row_secsched['ay_code'];
                    $section = $row_secsched['section_code'];

                    $curriculum_check_query = "SELECT curriculum FROM tbl_section 
                        WHERE section_code = '$section' 
                        AND dept_code = '$row_section_dept_code'";
                    $curriculum_result = $conn->query($curriculum_check_query);

                    $section_curriculum = ''; // Initialize to store the curriculum type
                    if ($curriculum_result->num_rows > 0) {
                        $curriculum_row = $curriculum_result->fetch_assoc();
                        $section_curriculum = $curriculum_row['curriculum'];
                    }

                    $prof_sched_code = $prof_code . "_" . $academic_year;
                    $sanitized_prof_dept_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$PFdepartment}_{$ay_code}");

                    $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                    $stmt_check = $conn->prepare($table_check_sql);
                    $stmt_check->bind_param('ss', $database_name, $sanitized_prof_dept_code);
                    $stmt_check->execute();
                    $stmt_check->bind_result($table_exists);
                    $stmt_check->fetch();
                    $stmt_check->close();

                    // Proceed with the query if the table exists
                    if ($table_exists > 0) {
                        // Delete from professor schedule table based on sec_sched_id
                        $delete_prof_sql = "DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_code = '$section_sched_code' ;";
                        if ($conn->query($delete_prof_sql) === FALSE) {

                        }
                        echo "Error deleting schedule from $sanitized_prof_sched_code: " . $conn->error;
                    }

                    $time_start_dt = new DateTime($time_start);
                    $time_end_dt = new DateTime($time_end);
                    $duration = $time_start_dt->diff($time_end_dt);
                    $duration_hours = $duration->h + ($duration->i / 60);


                    $prof_sched_code = $prof_code . "_" . $ay_code;
                    // Fetch current teaching hours and maximum teaching hours of the professors
                    $fetch_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";
                    $prof_hours_result = $conn->query($fetch_prof_hours_query);

                    if ($prof_hours_result->num_rows > 0) {
                        $prof_hours_row = $prof_hours_result->fetch_assoc();
                        $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                        $prep_hours = $prof_hours_row['prep_hrs'];

                        // Check if adding the new schedule exceeds the maximum teaching hour
                    }


                    $database_name = $conn->real_escape_string($dbname);
                    // Fetch and delete from room schedule table
                    $table_check_sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
                    $stmt_check = $conn->prepare($table_check_sql);
                    $stmt_check->bind_param('ss', $database_name, $sanitized_prof_dept_code);
                    $stmt_check->execute();
                    $stmt_check->bind_result($table_exists);
                    $stmt_check->fetch();
                    $stmt_check->close();

                    // Proceed with the query if the table exists
                    if ($table_exists > 0) {
                        $check_query = "SELECT * FROM $sanitized_prof_dept_code 
                        WHERE prof_sched_code = '$prof_sched_code' 
                        AND course_code = '$course_code' 
                        AND semester = '$semester' 
                        AND curriculum = '$section_curriculum' AND class_type = '$class_type'";
                        $check_result = $conn->query($check_query);


                        // If the professor has not taught this course in the current curriculum, add 1 prep hour
                        if ($check_result->num_rows === 0) {
                            while ($row = $check_result->fetch_assoc()) {
                                // echo "<pre>";
                                // print_r($row);
                                // echo "</pre>";
                            }
                            $prep_hours = $prep_hours - 1;
                        } else {
                            $prep_hours = $prep_hours;
                        }

                    }
                    $prof_sched_code = $prof_code . "_" . $ay_code;
                    $new_teaching_hours = $current_teaching_hours - $duration_hours;

                    $sql_prof_type = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND ay_code = ? AND semester = ? ";
                    $stmt = $conn->prepare($sql_prof_type);
                    $stmt->bind_param("ssis", $prof_code, $PFdepartment, $ay_code, $semester);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $prof_type = $row['prof_type'];

                        if ($prof_type == 'Regular') {
                            // If the professor is Regular, use the formula directly
                            $consultation_hrs = $new_teaching_hours / 3;
                        } else {
                            // If the professor is not Regular, check the teaching hours
                            if ($new_teaching_hours >= 18) {
                                // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                $consultation_hrs = 2;
                            } else {
                                // If teaching hours are less than 18, set consultation hours to 0
                                $consultation_hrs = 0;
                            }
                        }

                        // Optional: Debugging output
                        // echo "Consultation Hours: " . $consultation_hrs;
                    } else {
                        echo "Professor not found.";
                    }

                    $stmt->close();


                    // $consultation_hrs = $new_teaching_hours / 3;

                    $update_hours_query = "UPDATE  tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', prep_hrs = '$prep_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester ='$semester' AND dept_code = '$PFdepartment'";

                    if ($conn->query($update_hours_query) === FALSE) {
                        echo "Teaching hours not updated for plotting.";
                    }




                }
                $update_sql = "UPDATE $sanitized_dept_code SET shared_to = ?, shared_sched = ?, prof_code = ?, prof_name = ? WHERE sec_sched_id = ? AND semester = ? AND section_sched_code = ?";
                $stmt_update = $conn->prepare($update_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param("sssssss", $null, $null, $tba, $tba, $sec_sched_id, $semester, $section_sched_code);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    echo "Error preparing professor update query: " . $conn->error;
                }




            }

            $messages[] = "The selected schedule is no longer shared.";
            echo "<script type='text/javascript'>
                            document.addEventListener('DOMContentLoaded', function() {
                                var conflictList = document.getElementById('conflictList');";

            foreach ($messages as $message) {
                echo "var li = document.createElement('li');
                              li.textContent = '$message';
                              conflictList.appendChild(li);";
            }

            echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                          myModal.show();
                        });
                    </script>";

        }

    }
}


if (isset($_POST['CopyButton'])) {


    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $_SESSION['token'] = bin2hex(random_bytes(32));

    $copy_section = $_POST['section_code'];  // selected from dropdown
    $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");

    $cleaned_section = str_replace('-', '_', $copy_section);
    $source_section = $cleaned_section . '_' . $ay_code;

    $check_schedule_exists_query = "SELECT COUNT(*) as total FROM $sanitized_dept_code WHERE section_sched_code = '$source_section'";
    $check_schedule_result = $conn->query($check_schedule_exists_query);
    $schedule_data = $check_schedule_result->fetch_assoc();

    if ($schedule_data['total'] == 0) {
        $messages[] = 'No schedule found.';
    }

    $prof_check_query = "SELECT COUNT(*) as conflict_count FROM $sanitized_dept_code 
        WHERE section_sched_code = '$section_dept_code' 
        AND (
            (prof_code IS NOT NULL AND prof_code != '') 
            OR 
            (room_code IS NOT NULL AND room_code != '')
        )";
    $prof_result = $conn->query($prof_check_query);
    $prof_data = $prof_result->fetch_assoc();

    if ($prof_data['conflict_count'] > 0) {
        $messages[] = 'Cannot copy schedule. A professor or room is already assigned in the source section.';

    }

    // Delete existing schedule of the target section
    $delete_existing_query = "DELETE FROM $sanitized_dept_code WHERE section_sched_code = '$section_sched_code'";
    $conn->query($delete_existing_query);

    // Fetch and copy schedule from the source section
    $get_schedule_query = "SELECT * FROM $sanitized_dept_code WHERE section_sched_code = '$source_section'";
    $schedule_result = $conn->query($get_schedule_query);

    if ($schedule_result->num_rows > 0) {
        while ($row = $schedule_result->fetch_assoc()) {
            $time_start = $row['time_start'];
            $time_end = $row['time_end'];
            $day = $row['day'];
            $curriculum = $row['curriculum'];
            $source_dept_code = $row['dept_code'];
            $course_code = $row['course_code'];
            $class_type = $row['class_type'];
            $cell_color = $row['cell_color'];

            $insert_query = "INSERT INTO $sanitized_dept_code 
                (section_sched_code, time_start, time_end, day, curriculum, dept_code, course_code, class_type, semester, cell_color, ay_code) 
                VALUES 
                ('$section_sched_code', '$time_start', '$time_end', '$day', '$curriculum', '$source_dept_code', '$course_code', '$class_type', '$semester', '$cell_color', '$ay_code')";
            $conn->query($insert_query);
        }
        $messages[] = 'Schedule of ' . $copy_section . ' copied successfully.';
    }

    echo "<script type='text/javascript'>
                    document.addEventListener('DOMContentLoaded', function() {
                        var conflictList = document.getElementById('conflictList');";

    foreach ($messages as $message) {
        echo "var li = document.createElement('li');
                      li.textContent = '$message';
                      conflictList.appendChild(li);";
    }

    echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                  myModal.show();
                });
            </script>";
}


?>



<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="plotSchedule.css">
    <script src="plotting.js"></script>


</head>

<body>
    <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code == $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
    <?php endif; ?>

    <?php if ($_SESSION['user_type'] == 'Department Chairperson' && $admin_college_code == $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
    <?php endif; ?>

    <?php if ($_SESSION['user_type'] == 'CCL Head' && $admin_college_code == $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
    <?php endif; ?>


    <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code != $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php"); ?>
    <?php endif; ?>



    <h2 class="title">PLOT SCHEDULE</h2>
    <form id="plot" action="" method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
        <div class="filtering ">
            <input type="hidden" id="section_sched_code" name="section_sched_code"
                value="<?php echo htmlspecialchars($section_sched_code); ?>" readonly>
            <input type="hidden" id="section_dept_code" value="<?php echo htmlspecialchars($section_dept_code); ?>"
                readonly>
            <div class="form-group col-md-2">
                <label for="section_code" id="header">Section:</label>
                <input type="text" id="section_code" name="section_code"
                    value="<?php echo htmlspecialchars($section_code); ?>" readonly>
            </div>
            <div class="form-group col-md-2">
                <label for="semester" id="header">Semester:</label>
                <input type="text" id="semester" name="semester" value="<?php echo htmlspecialchars($semester); ?>"
                    readonly>
            </div>

            <div class="form-group col-md-3">
                <label for="program" id="header">Academic Year:</label>
                <input type="hidden" id="ay_code" value="<?php echo $ay_code; ?>" readonly>
                <input type="text" id="ay_name" value="<?php echo $ay_name; ?>" readonly>
                <input type="hidden" id="program_code" value="<?php echo $program_code; ?>" readonly>
                <input type="hidden" id="year_level" value="<?php echo $year_level; ?>" readonly>
                <input type="hidden" id="user_type" value="<?php echo $user_type; ?>" readonly>
                <!-- <input type="text" id="section_college_code" value="<?php echo $section_college_code; ?>" readonly> -->
            </div>
            <?php if ($user_type === "Department Secretary" && $dept_code == $section_dept_code && $user_type != "CCL Head" && $admin_college_code == $user_college_code): ?>

                <div class="form-group col-md-1 d-flex align-items-end"
                    style="gap:80px; justify-content: flex-end; padding-right: 100px;">
                    <button type="button" id="btnchange" class="btn" data-bs-toggle="modal" title="Change Section"
                        data-bs-target="#createTableModal">
                        <i class="fas fa-sync-alt custom-icon"></i></i>
                    </button>
                <?php endif; ?>
                <?php if ($user_type === "Department Secretary" && $dept_code == $section_dept_code && $admin_college_code == $user_college_code): ?>
                    <a class="dropdown-item" href="#" id="copy" data-bs-toggle="modal" data-bs-target="#CopyModal"
                        title="Copy"><i class="far fa-copy custom-icon"></i></a>
                    <a class="dropdown-item" href="#" id="delete" data-bs-toggle="modal" data-bs-target="#deleteModal"
                        title="Delete">
                        <i class="far fa-trash-alt custom-icon "></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <input type="hidden" id="status" value="<?php echo $status; ?>">
        </div>

        <script>
            // Fetch status value from the hidden input populated by PHP
            var status = document.getElementById('status').value;

            // Function to hide or show "Complete" and "Draft" buttons based on status
            function toggleButtonsBasedOnStatus(status) {
                var completeButton = document.getElementById('complete');
                var draftButton = document.getElementById('saveDraft');

                // Hide Complete button if status is "completed" or "public"
                if (status === "private" || status === "public" || status === "completed") {
                    completeButton.style.display = 'none'; // Hide Complete button
                } else {
                    completeButton.style.display = 'block'; // Show Complete button
                }

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
            <script>
                document.getElementById('plot').addEventListener('submit', function () {
                    localStorage.setItem('scrollPosition', window.scrollY);
                });

                window.addEventListener('load', function () {
                    var scrollPosition = localStorage.getItem('scrollPosition');
                    if (scrollPosition) {
                        // Set the scroll position without triggering smooth scrolling or any movement
                        window.scrollTo(0, scrollPosition);
                        localStorage.removeItem('scrollPosition');
                    }
                });
            </script>


            <div class="col-md-4">
                <?php
                // Check if section_sched_code is set in $_POST
                if (isset($_POST['section_sched_code'])) {

                    $conn = new mysqli($servername, $username, $password, $dbname);

                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }

                    $section_sched_code = $_POST['section_sched_code'];

                    // Query to get section_code and ay_code from tbl_secschedlist
                    $sql_secsched = "SELECT section_code, ay_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
                    $result_secsched = $conn->query($sql_secsched);
                    if ($section_college_code === $college_code) {
                        if ($result_secsched->num_rows > 0) {
                            $row_secsched = $result_secsched->fetch_assoc();
                            $section_code = $row_secsched['section_code'];
                            $ay_code = $row_secsched['ay_code'];

                            // Sanitize section_code and ay_code for table name
                            $sanitized_section_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $section_code);
                            $sanitized_academic_year = preg_replace("/[^a-zA-Z0-9_]/", "_", $ay_code);
                            $table_name = "tbl_secsched_" . $dept_code . "_" . $sanitized_academic_year;

                            // Query to get the maximum sec_sched_id from your specific table
                            $sql_max_id = "SELECT MAX(sec_sched_id) AS max_id FROM $table_name";
                            $result_max_id = $conn->query($sql_max_id);

                            if ($result_max_id->num_rows > 0) {
                                $row_max_id = $result_max_id->fetch_assoc();
                                // Calculate the next ID (increment the maximum ID by 1)
                                $next_id = $row_max_id['max_id'] + 1;
                            } else {
                                // If no rows are returned, start with 1 or handle as per your system requirements
                                $next_id = 1;
                            }
                        } else {
                            echo "Invalid section sched code.";
                        }
                    }
                }
                ?>

                <?php
                // Example of processing the form
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    // Check if 'sec_sched_id' is set in the POST request
                    if (isset($_POST['sec_sched_id'])) {
                        // Retrieve the submitted value
                        $sec_sched_id = $_POST['sec_sched_id'];
                    } else {
                        // Handle the case where 'sec_sched_id' is not set
                        $sec_sched_id = ''; // You can set a default value or handle it differently
                    }
                    // Set the variables for display in the HTML                
                    $next_id = $sec_sched_id;
                }
                ?>

                <input type="hidden" id="sec_sched_id" name="sec_sched_id"
                    value="<?php echo isset($next_id) ? htmlspecialchars($next_id) : ''; ?>" required readonly>
                <input type="hidden" id="sched_dept_code" readonly>
                <input type="hidden" id="computer_room" readonly>
                <input type="hidden" id="allowed_rooms" readonly>
                <input type="hidden" id="user_dept_code" value="<?php echo $user_dept_code ?>" readonly>
                <input type="hidden" id="user_email" value="<?php echo $_SESSION['cvsu_email']; ?>">
                <input type="hidden" id="shared_to" name="shared_to" value="<?= $_POST['shared_to'] ?? ''; ?>" readonly>
                <input type="hidden" id="shared_sched" name="shared_sched" value="<?= $_POST['shared_sched'] ?? ''; ?>"
                    readonly>

                <?php if ($user_type == 'CCL Head') {
                    $display_color = 'style= "display:none"';
                } else {
                    $display_color = null;

                } ?>

                <div class="form-group col-md-3"
                    style="display: flex; justify-content: flex-end; align-items: center; float:right;">
                    <input type="color" id="color" name="color" value="<?php echo $color ?>" <?php echo $display_color ?>>
                    <button type="submit" name="changeColor" value="Save"
                        style="background-color:transparent; border:none; width:20px;">
                        <i class="fa-light fa-pencil" <?php echo $display_color ?>></i>
                    </button>
                </div>
                <br>
                <label for="day">Day:</label>
                <select id="day" name="day" required <?php echo $disabled; ?>>
                    <option value="Monday" <?php echo (isset($day) && $day == 'Monday') ? 'selected' : ''; ?>>Monday
                    </option>
                    <option value="Tuesday" <?php echo (isset($day) && $day == 'Tuesday') ? 'selected' : ''; ?>>Tuesday
                    </option>
                    <option value="Wednesday" <?php echo (isset($day) && $day == 'Wednesday') ? 'selected' : ''; ?>>
                        Wednesday</option>
                    <option value="Thursday" <?php echo (isset($day) && $day == 'Thursday') ? 'selected' : ''; ?>>Thursday
                    </option>
                    <option value="Friday" <?php echo (isset($day) && $day == 'Friday') ? 'selected' : ''; ?>>Friday
                    </option>
                    <option value="Saturday" <?php echo (isset($day) && $day == 'Saturday') ? 'selected' : ''; ?>>Saturday
                    </option>
                </select>
                <?php
                // $time_start = isset($_POST['time_start']) ? $_POST['time_start'] : '';
                // $time_end = isset($_POST['time_end']) ? $_POST['time_end'] : '';
                $start_timestamp = strtotime($user_start_time);
                $end_timestamp = strtotime($user_end_time);

                $plot_start = date("H", $start_timestamp); // Converts to 24-hour format
                $plot_end = date("H", $end_timestamp);

                ?>
                <label for="time_start">Duration:</label>
                <div class="time-selection">
                    <select id="time_start" name="time_start" <?php echo $disabled; ?>>
                        <?php
                        // Loop through hours from 7 AM to 7 PM
                        for ($i = $plot_start; $i <= $plot_end; $i++) {
                            // Loop through minutes in intervals of 30
                            for ($j = 0; $j < 60; $j += 30) {
                                // Ensure that the loop only includes the 7:00 PM time once
                                if ($i == $plot_end && $j > 0) {
                                    break;
                                }

                                // Format the time in 24-hour format
                                $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":" . str_pad($j, 2, "0", STR_PAD_LEFT) . ":00";
                                // Convert to 12-hour format
                                $time_12 = date("g:i A", strtotime($time_24));
                                // Output the option element
                                echo '<option value="' . $time_24 . '"' . (($time_start == $time_24) ? ' selected' : '') . '>' . $time_12 . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <label>-</label>
                    <select id="time_end" name="time_end" <?php echo $disabled; ?>>
                        <?php
                        // Loop through hours from 7 AM to 7 PM
                        for ($i = $plot_start; $i <= $plot_end; $i++) {
                            // Loop through minutes in intervals of 30
                            for ($j = 0; $j < 60; $j += 30) {
                                // Ensure that the loop only includes the 7:00 PM time once
                                if ($i == $plot_end && $j > 0) {
                                    break;
                                }

                                // Format the time in 24-hour format
                                $time_24 = str_pad($i, 2, "0", STR_PAD_LEFT) . ":" . str_pad($j, 2, "0", STR_PAD_LEFT) . ":00";
                                // Convert to 12-hour format
                                $time_12 = date("g:i A", strtotime($time_24));
                                // Output the option element
                                echo '<option value="' . $time_24 . '"' . (($time_end == $time_24) ? ' selected' : '') . '>' . $time_12 . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>


                <?php
                // Function to get the program code from tbl_program based on section code
                function getProgramCode($conn, $section_code)
                {
                    $sql = "SELECT program_code,year_level FROM tbl_section WHERE section_code='$section_code'";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        return $row['program_code'];
                        return $row['year_level'];
                    } else {
                        return null; // Return null if no matching program code is found
                    }
                }

                function getYearLevel($conn, $section_code)
                {
                    $sql = "SELECT year_level FROM tbl_section WHERE section_code='$section_code'";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        return $row['year_level'];
                    } else {
                        return null; // Return null if no matching program code is found
                    }
                }
                // Get the program code for the given section code
                $program_code = getProgramCode($conn, $section_code);
                $year_level = getYearLevel($conn, $section_code);
                ?>


                <?php
                // Initialize options strings
                $course_options = ''; // For datalist options
                $priority_courses = []; // Array to store priority courses
                $fetch_info_query = "SELECT dept_code,status,college_code,petition FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
                $result = $conn->query($fetch_info_query);

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $section_dept_code = $row['dept_code'];
                    $petition = $row['petition'];
                }

                $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");
                $all_courses_plotted = false; // Flag to check if all courses are plotted
                $no_courses_available = false;
                $response = [];
                // Query to get all courses for the given program code, year level, and semester
                

                $query_college_code = "SELECT college_code FROM tbl_prof_acc WHERE user_type = 'CCL Head' AND ay_code = '$ay_code' AND semester = '$semester'";
                $college_result = $conn->query($query_college_code);

                if ($college_result->num_rows > 0) {
                    $row = $college_result->fetch_assoc();
                    $ccl_college_code = $row['college_code'];
                }

                $sql = "SELECT course_code, course_name, lec_hrs, lab_hrs, allowed_rooms,computer_room FROM tbl_course WHERE program_code = ? AND year_level = ?  AND dept_code = ?  AND curriculum = ? AND semester = ?";

                if ($petition == 1) {
                    $sql .= " AND ay_code = $ay_code AND petition = 1";
                } else {
                    $sql .= " AND petition = 0 ";
                }

                if ($user_type === 'CCL Head') {
                    // CCL Head: Select courses with 'lecR&labR' and 'labR'
                    $sql .= " AND (computer_room = 1)";
                    if ($ccl_college_code === $section_college_code) {
                        $course_dept_code = $section_dept_code;
                    } else {
                        $course_dept_code = null;
                    }

                    // echo $ccl_college_code.$section_college_code;
                } elseif ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
                    // Department Secretary: Select courses with 'lecR&labR' and 'lecR'
                    $sql .= " ";
                    $course_dept_code = $user_dept_code;
                }
                // AND (allowed_rooms = 'lecR&labR' OR allowed_rooms = 'lecR')
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssss", $program_code, $year_level, $course_dept_code, $curriculum, $semester);
                $stmt->execute();
                $result = $stmt->get_result();
                // echo $course_dept_code.$program_code.$year_level.$curriculum.$semester;
                // Loop through all the courses and check availability
                while ($row = $result->fetch_assoc()) {
                    $course_code = htmlspecialchars($row['course_code'], ENT_QUOTES, 'UTF-8');
                    $course_name = htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8');
                    $lec_hrs = $row['lec_hrs'];
                    $lab_hrs = $row['lab_hrs'];
                    $allowed_rooms = $row['allowed_rooms'];
                    $computer_room = $row['computer_room'];

                    // Default availability flags
                    $lec_available = ($lec_hrs > 0);
                    $lab_available = ($lab_hrs > 0);

                    // Initialize availability for this course
                    $course_data = [
                        'course_code' => $course_code,
                        'course_name' => $course_name,
                        'lec_available' => false,
                        'lab_available' => false
                    ];

                    // Check allowed rooms based on user type
                    if ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
                        if ($allowed_rooms === 'lecR') {
                            // If lecture rooms only, check if course has lecture and lab hours
                            if ($lec_available) {
                                $course_data['lec_available'] = true;
                            }
                            if ($lab_available) {
                                $course_data['lab_available'] = true;
                            }
                        } elseif ($allowed_rooms === 'lecR&labR' && !empty($ccl_college_code)) {
                            // If both lecture and lab rooms are allowed, display both if available
                            if ($lec_available) {
                                $course_data['lec_available'] = true;
                            }
                            if ($lab_available && $computer_room === 0 && $section_college_code === $ccl_college_code) {
                                $course_data['lab_available'] = true;
                            }
                            if ($lab_available && $computer_room === 1 && $section_college_code !== $ccl_college_code) {
                                $course_data['lab_available'] = true;
                            }
                        } else {
                            $course_data['error'] = "Invalid room type for Department Secretary.";
                        }
                    } elseif ($user_type === 'CCL Head') {
                        // If CCL Head, include only lab availability
                        if ($lab_available) {
                            $course_data['lab_available'] = true;
                        }
                    } else {
                        $course_data['error'] = "Unauthorized user type.";
                    }



                    // Check if the course already has a scheduled lecture or lab
                    $sql_check_schedule = "SELECT class_type, time_start, time_end 
                       FROM {$sanitized_section_sched_code} 
                       WHERE course_code = ? 
                       AND section_sched_code = ? 
                       AND semester = ?";
                    $stmt_check = $conn->prepare($sql_check_schedule);
                    $stmt_check->bind_param("sss", $course_code, $section_sched_code, $semester);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();

                    $plotted_lec_hours = 0;
                    $plotted_lab_hours = 0;

                    // Calculate total plotted hours for the course
                    while ($row_check = $result_check->fetch_assoc()) {
                        $time_start_dt = new DateTime($row_check['time_start']);
                        $time_end_dt = new DateTime($row_check['time_end']);
                        $duration = $time_start_dt->diff($time_end_dt);
                        $duration_hours = $duration->h + ($duration->i / 60);

                        if ($row_check['class_type'] === 'lec') {
                            $plotted_lec_hours += $duration_hours;
                        } elseif ($row_check['class_type'] === 'lab') {
                            $plotted_lab_hours += $duration_hours;
                        }
                    }

                    // Check if the course's lecture or laboratory hours have been met
                    $lec_met = $plotted_lec_hours >= $lec_hrs;
                    $lab_met = $plotted_lab_hours >= $lab_hrs;
                    $is_course_available = null;

                    if ($lec_hrs == 0 && $lab_hrs == 0) {
                        $check_course = "SELECT * 
                                                 FROM $sanitized_section_sched_code
                                                 WHERE course_code = '$course_code' AND semester = '$semester'";
                        $result_course = $conn->query($check_course);

                        if ($result_course->num_rows > 0) {
                            $is_course_available = null;
                        } else {
                            // Course is not plotted
                            $is_course_available = 0;
                        }
                    }


                    // Adjust availability based on existing records and hours met
                    // $course_data['lec_available'] = !$lec_met && $course_data['lec_available'];
                    // $course_data['lab_available'] = !$lab_met && $course_data['lab_available'];
                
                    $course_data['lec_available'] = (!$lec_met && $course_data['lec_available']) || ($lec_hrs === $is_course_available);
                    $course_data['lab_available'] = (!$lab_met && $course_data['lab_available']) || ($lab_hrs === $is_course_available);

                    // Only add to response if either lecture or lab is available
                    if ($course_data['lec_available'] || $course_data['lab_available']) {
                        $response[] = $course_data;

                        $display_text = '';  // Start with an empty string.
                
                        if ($lec_hrs > 0) {
                            $display_text .= 'Lec: ' . $plotted_lec_hours . '/' . $lec_hrs;
                        }

                        if ($lab_hrs > 0) {
                            $display_text .= ' - Lab: ' . $plotted_lab_hours . '/' . $lab_hrs;
                        }

                        // Add the course name at the end
                        $display_text .= ' - ' . $course_name;

                        // Add tooltip to the <option> using the title attribute
                        $course_options .= '<option value="' . $course_code . '" title="' . htmlspecialchars($display_text) . '">' . $display_text . '</option>';
                    }

                    // Close the check schedule statement
                    $stmt_check->close();
                }

                // Close the main course query statement
                $stmt->close();

                // If no courses are available
                if (empty($response)) {
                    // Set the flag indicating all courses are plotted
                    if (!empty($receiver_email)) {
                        if ($receiver_email == $current_user_email) {
                            $all_courses_plotted = true;
                        }
                    }
                    $no_courses_available = true;
                    $course_options .= '<option value="">No course available</option>';
                }



                if (isset($_POST['action']) && $_POST['action'] === 'send_notification') {
                    // Get the data from the AJAX request
                    $section_code = $_POST['section_code'];
                    $semester = $_POST['semester'];
                    $sender_email = $_POST['sender_email'];
                    $receiver_email = $_POST['receiver_email'];

                    

                    $response = array();

                    // Check for null or empty receiver email
                    if (empty($sender_email)) {
                        $response = array('status' => 'error', 'message' => 'No recipient email found');
                        echo json_encode($response);
                        exit;
                    }

                    // Only proceed if sender is not the same as receiver
                    if ($sender_email == $current_user_email) {
                        $message = "$current_user_email finished plotting the schedule for $section_code.";
                        $is_read = 0;
                        $date_sent = date('Y-m-d H:i:s');

                        // Insert the notification into the database
                        $stmt = $conn->prepare("
                                INSERT INTO tbl_notifications 
                                    (section_code, semester, sender_email, receiver_email, message, is_read, date_sent)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                        $stmt->bind_param("sssssis", $section_code, $semester, $sender_email, $receiver_email, $message, $is_read, $date_sent);
                        $success = $stmt->execute();
                        $stmt->close();

                        if ($success) {
                            // ✅ Now mark the schedule as inactive
                            $updateStmt = $conn->prepare("
                UPDATE tbl_shared_sched 
                SET status = 'inactive' 
                WHERE section_code = ? AND semester = ?
            ");
                            $updateStmt->bind_param("ss", $section_code, $semester);
                            $updateSuccess = $updateStmt->execute();
                            $updateStmt->close();

                            if ($updateSuccess) {
                                $response = array('status' => 'success', 'message' => 'Notification sent and schedule marked as inactive.');
                            } else {
                                $response = array('status' => 'warning', 'message' => 'Notification sent but failed to update schedule status.');
                            }
                        } else {
                            $response = array('status' => 'error', 'message' => 'Failed to send notification');
                        }
                    } else {
                        $response = array('status' => 'info', 'message' => 'Sender and receiver are the same user');
                    }

                    // Return JSON response
                    echo json_encode($response);
                    exit;
                }


                ?>
                <!-- Bootstrap 5 modal -->
                <div class="modal fade" id="scheduleCompletionModal" tabindex="-1" aria-hidden="true"
                    aria-labelledby="scheduleCompletionModalLabel">
                    <div class="modal-dialog modal-dialog-centered" style="max-width: 500px;">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="scheduleCompletionModalLabel">Schedule Completion</h5>
                                <button type="button" class="btn-close" aria-label="Close"
                                    onclick="closeCompletionModal()"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you done plotting this schedule?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success col-md-2"
                                    onclick="confirmScheduleCompletion()">Yes</button>
                                <button type="button" class="btn btn-danger col-md-2"
                                    onclick="closeCompletionModal()">No</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    // Initialize Bootstrap modal instance
                    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleCompletionModal'));

                    // Function to determine if all courses are plotted
                    function allCoursesPlotted() {
                        return <?php echo isset($all_courses_plotted) && $all_courses_plotted ? 'true' : 'false'; ?>;
                    }

                    // Show the modal using Bootstrap API
                    function showCompletionModal() {
                        scheduleModal.show();
                    }

                    // Close the modal using Bootstrap API
                    function closeCompletionModal() {
                        scheduleModal.hide();
                    }

                    // Confirm completion and send notification
                    function confirmScheduleCompletion() {

                        const section_code = document.getElementById('section_code').value;
                        const semester = document.getElementById('semester').value;
                        const sender_email = document.getElementById('sender_email').value;
                        const receiver_email = document.getElementById('receiver_email').value;

                        $.ajax({
                            type: "POST",
                            url: window.location.href,
                            data: {
                                section_code: section_code,
                                semester: semester,
                                sender_email: sender_email,
                                receiver_email: receiver_email,
                                action: 'send_notification'
                            },
                            success: function (response) {
                                try {
                                    const result = JSON.parse(response);
                                    alert(result.message);
                                } catch (e) {
                                    closeCompletionModal();
                                    window.location.href = "http://localhost/SchedSys3/php/department_secretary/sharedSchedule.php";

                                }

                            },
                            error: function (xhr, status, error) {
                                alert("Error sending notification: " + error);
                            }
                        });
                    }

                    // Run on page load
                    $(document).ready(function () {
                        const section_code = document.getElementById('section_code').value;
                        const semester = document.getElementById('semester').value;
                        const modalKey = `modalShown_${section_code}_${semester}`;

                        if (allCoursesPlotted()) {
                            if (!sessionStorage.getItem(modalKey)) {
                                setTimeout(showCompletionModal, 500);
                                sessionStorage.setItem(modalKey, 'true');
                            }
                        } else {
                            sessionStorage.removeItem(modalKey);
                        }
                    });
                </script>




                <input type="hidden" id="section_code"
                    value="<?php echo isset($_POST['section_code']) ? $_POST['section_code'] : ''; ?>">
                <input type="hidden" id="semester"
                    value="<?php echo isset($_POST['semester']) ? $_POST['semester'] : ''; ?>">
                <input type="hidden" id="section_code"
                    value="<?php echo isset($_POST['section_code']) ? $_POST['section_code'] : ''; ?>">
                <input type="hidden" id="semester"
                    value="<?php echo isset($_POST['semester']) ? $_POST['semester'] : ''; ?>">
                <input type='hidden' id='sender_email' name='sender_email'
                    value='<?php echo htmlspecialchars($current_user_email); ?>'>
                <input type='hidden' id='receiver_email' name='receiver_email'
                    value='<?php echo htmlspecialchars($sender_email); ?>'>



                <label for="course_code" id="course_code_label" style="display: inline;">Course Code:
                    <?php if ($no_courses_available): ?><a style="font-size: 10px;">All courses for this section have
                            been plotted.</a><?php endif; ?></label>
                <input list="course_codes" id="course_code" name="course_code" autocomplete="off"
                    value="<?php echo $selected_course_code; ?>" <?php echo $no_courses_available ? 'disabled' : ''; ?>
                    style="display: inline;" <?php echo $courseReadonly; ?>>
                <datalist id="course_codes">
                    <?php echo $course_options; ?>
                </datalist>
                <label for="new_course_code" id="new_course_code_label" style="display: none;">New Course Code:</label>
                <input list="course_codes" id="new_course_code" name="new_course_code" autocomplete="off"
                    value="<?php echo $selected_new_course_code; ?>" style="display: none;" <?php echo $courseReadonly; ?>>
                <datalist id="course_codes">
                    <?php echo $course_options; ?>
                </datalist>

                <label for="class_type">Subject Type:</label>
                <select name="class_type" id="class_type" <?php echo $disabled; ?>>
                    <option value="lec" <?php echo ($class_type == 'lec') ? 'selected' : ''; ?>>Lec</option>
                    <option value="lab" <?php echo ($class_type == 'lab') ? 'selected' : ''; ?>>Lab</option>
                    <option value="n/a" style="display:none;">No other class type available</option>
                </select>


                <div class="filterGroup">
                    <form id="filter_form">
                        <div class="d-flex justify-content-between align-items-center mb-0">
                            <!-- Label aligned to the left -->
                            <label id="room_code_label" for="room_code">
                                Room Code: <span
                                    style="font-size: 12px; color: gray; font-style: italic;">(optional)</span>
                            </label> <label for="new_room_code" id="new_room_code_label" style="display: none;">New Room
                                Code: <span
                                    style="font-size: 12px; color: gray; font-style: italic;">(optional)</span></label>

                            <!-- Buttons aligned to the right -->

                            <?php
                            $filter_display = ''; // Initialize with a default value
                            if ($section_college_code != $college_code) {
                                $filter_display = "display:none;";
                            }

                            ?>
                            <div>
                                <button type="submit" id="filter" name="filter" class="btn btn-link filter-btn"
                                    style="background-color:transparent; color:#FD7238; <?php echo $filter_display; ?>">
                                    <i class="fa-light fa-filter" id="filter_icon"></i>
                                </button>
                                <button type="submit" id="new_filter" name="filterNew" class="btn btn-link filter-btn"
                                    style="background-color:transparent; color:#FD7238; display:none;">
                                    <i class="fa-light fa-filter" id="new_filter_icon"></i>
                                </button>
                            </div>

                            <?php
                            $filter_submitted = isset($_POST['filter']);
                            if (!$filter_submitted) {
                                // Fetch previously selected room code from POST or SESSION
                                $selected_room_code = isset($_POST['room_code']) ? $_POST['room_code'] : '';
                                if ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
                                    $room_type = ["Lecture", "Laboratory"];
                                } elseif ($user_type === 'CCL Head') {
                                    $room_type = "Computer Laboratory";
                                }

                                if (is_array($room_type)) {
                                    // Convert array to a comma-separated string for SQL IN clause
                                    $room_type_condition = "'" . implode("','", $room_type) . "'";
                                    $sql = "SELECT room_code, room_name 
                                            FROM tbl_room 
                                            WHERE dept_code = '$dept_code' 
                                              AND status = 'Available' 
                                              AND room_type IN ($room_type_condition)";
                                } else {
                                    $sql = "SELECT room_code, room_name 
                                            FROM tbl_room 
                                            WHERE status = 'Available' 
                                              AND room_type = '$room_type'";
                                }

                                $result = $conn->query($sql);
                                $room_options = ''; // Initialize the options string
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        // Check if the current room code matches the last selected room code
                                        $selected = $selected_room_code == $row['room_code'] ? 'selected' : '';
                                        $display_text = $row["room_code"] . ' - ' . $row["room_name"];
                                        $room_options .= '<option value="' . $row["room_code"] . '" ' . $selected . '>' . $display_text . '</option>';
                                    }
                                } else {
                                    $room_options .= '<option value="">No rooms available</option>';
                                }
                            }

                            // Fetch previously selected professor code from POST or SESSION
                            $selected_prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : '';

                            // Generate options for professor_code dropdown only if the filter form has not been submitted
                            if (!$filter_submitted) {
                                // Sanitize inputs to prevent SQL injection
                                $sanitized_semester = $conn->real_escape_string($semester); // Replace with actual semester value
                                $sanitized_ay_code = $conn->real_escape_string($ay_code); // Replace with actual ay_code value
                                $sanitized_dept_code = $conn->real_escape_string($dept_code); // Replace with actual dept_code value
                            
                                if ($user_type === "Department Secretary" || $user_type === 'Department Chairperson') {// Query to fetch professor details along with teaching hours
                                    $professors_sql = "
                                    SELECT p.prof_code, p.prof_name, 
                                        COALESCE(c.teaching_hrs, 0) AS teaching_hrs
                                
                                    FROM tbl_prof p
                                    LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                                    LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
                                        AND c.semester = '$sanitized_semester' 
                                        AND ps.ay_code = '$sanitized_ay_code'
                                    WHERE p.dept_code = '$sanitized_dept_code' AND p.acc_status = '1' AND p.ay_code = '$sanitized_ay_code' AND p.semester = '$sanitized_semester'
                                    GROUP BY p.prof_code, p.prof_name
                                    ORDER BY p.prof_code";

                                    $professors_result = $conn->query($professors_sql);

                                    $professor_options = ''; // Initialize the options string
                            
                                    if ($professors_result->num_rows > 0) {
                                        while ($prof_row = $professors_result->fetch_assoc()) {
                                            $prof_code = htmlspecialchars($prof_row['prof_code'], ENT_QUOTES, 'UTF-8');
                                            $prof_name = htmlspecialchars($prof_row['prof_name'], ENT_QUOTES, 'UTF-8');
                                            $current_teaching_hrs = htmlspecialchars($prof_row['teaching_hrs'], ENT_QUOTES, 'UTF-8');


                                            // Check if the current professor code matches the last selected professor code
                                            $selected = $selected_prof_code == $prof_code ? 'selected' : '';
                                            $display_text = $prof_code . ' - ' . ' (' . $current_teaching_hrs . ' hrs)';
                                            $professor_options .= '<option value="' . $prof_code . '" ' . $selected . '>' . $display_text . '</option>';
                                        }
                                    } else {
                                        $professor_options .= '<option value="">No Instructor available</option>';
                                    }
                                }

                            }
                            ?>

                        </div>
                    </form>

                    <script>
                        function fetchClassTypeAvailability(courseCode, currentClassType = null) {
                            const programCode = document.getElementById('program_code').value;
                            const yearLevel = document.getElementById('year_level').value;
                            const userDeptCode = document.getElementById('user_dept_code').value;
                            const sectionSchedCode = document.getElementById('section_sched_code').value;
                            const schedDeptCode = document.getElementById('sched_dept_code').value;
                            const sectionDeptCode = document.getElementById('section_dept_code').value;
                            const ayCode = document.getElementById('ay_code').value;
                            const semester = document.getElementById('semester').value;
                            const userType = document.getElementById('user_type').value;

                            // Make sure courseCode has a value before making a fetch call
                            if (courseCode) {
                                fetch(`class_type.php?course_code=${courseCode}&program_code=${programCode}&year_level=${yearLevel}&user_dept_code=${userDeptCode}&section_sched_code=${sectionSchedCode}&sched_dept_code=${schedDeptCode}&section_dept_code=${sectionDeptCode}&ay_code=${ayCode}&semester=${semester}&user_type=${userType}`)
                                    .then(response => response.json())
                                    .then(data => {
                                        updateClassTypeSelect(data, currentClassType);

                                        // After updating class types, fetch room availability based on class type
                                        fetchRoomAvailability(courseCode, data, currentClassType);
                                    })
                                    .catch(error => console.error('Error fetching course availability:', error));
                            }
                        }

                        // Function to update class type select based on fetched data
                        function updateClassTypeSelect(data, currentClassType) {
                            const classTypeSelect = document.getElementById('class_type');
                            const lecOption = classTypeSelect.querySelector('option[value="lec"]');
                            const labOption = classTypeSelect.querySelector('option[value="lab"]');
                            const noAvailable = classTypeSelect.querySelector('option[value="n/a"]');

                            // Reset options: enable them first
                            lecOption.style.display = 'block';
                            labOption.style.display = 'block';
                            noAvailable.style.display = 'none';

                            // Hide options based on availability
                            if (!data.lec_available) {
                                lecOption.style.display = 'none';
                            }
                            if (!data.lab_available) {
                                labOption.style.display = 'none';
                            }
                            if (!data.lec_available && !data.lab_available) {
                                noAvailable.style.display = 'block';
                                noAvailable.disabled = true;
                            }

                            // Set the class type based on the currentClassType if available and valid
                            if (currentClassType && (currentClassType === 'lec' || currentClassType === 'lab')) {
                                classTypeSelect.value = currentClassType;
                            } else if (!data.lec_available && data.lab_available) {
                                classTypeSelect.value = 'lab';
                            } else if (data.lec_available && !data.lab_available) {
                                classTypeSelect.value = 'lec';
                            } else {
                                lecOption.style.display = 'block';
                                labOption.style.display = 'block';
                                noAvailable.style.display = 'none';
                            }
                        }



                        // Function to fetch room availability based on class type
                        function fetchRoomAvailability(courseCode, data, currentClassType) {
                            let roomType = '';

                            // Determine room type based on availability
                            if (data.lec_available && data.lab_available) {
                                roomType = 'all'; // Fetch all rooms if both lecture and lab are available
                            } else if (data.lec_available) {
                                roomType = 'Lecture'; // Fetch lecture rooms only
                            } else if (data.lab_available) {
                                roomType = 'Laboratory'; // Fetch lab rooms only
                            } else {
                                roomType = 'all';
                            }

                            if (roomType) {
                                // Call a function to fetch rooms based on the determined room type
                                fetchRooms(courseCode, roomType);
                            }
                        }

                        // Fetch rooms only if the filter button has NOT been clicked
                        // function fetchRooms(courseCode, roomType) {
                        //     const userDeptCode = document.getElementById('user_dept_code').value;
                        //     const programCode = document.getElementById('program_code').value;
                        //     const yearLevel = document.getElementById('year_level').value;
                        //     fetch(`room_type.php?course_code=${courseCode}&room_type=${roomType}&user_dept_code=${userDeptCode}&program_code=${programCode}&year_level=${yearLevel}`)
                        //         .then(response => response.json())
                        //         .then(rooms => {
                        //             // Always populate rooms on the start
                        //             populateRoomSelect(rooms);
                        //             populateOldRoomSelect(rooms);
                        //         })
                        //         .catch(error => console.error('Error fetching room availability:', error));
                        // }

                        function fetchRooms(courseCode, roomType) {
                            const userDeptCode = document.getElementById('user_dept_code').value;
                            const programCode = document.getElementById('program_code').value;
                            const yearLevel = document.getElementById('year_level').value;
                            const newCourseCode = document.getElementById('new_course_code').value;
                            const userType = document.getElementById('user_type').value;
                            const classTypeElement = document.getElementById('class_type');

                            // Fetch rooms with the initial value of class_type
                            function fetchWithUpdatedClassType() {
                                const classType = classTypeElement.value; // Always get the latest value
                                console.log("Fetching with Class Type:", classType);

                                fetch(`room_type.php?course_code=${courseCode}&room_type=${roomType}&user_dept_code=${userDeptCode}&program_code=${programCode}&year_level=${yearLevel}&new_course_code=${newCourseCode}&user_type=${userType}&class_type=${classType}`)
                                    .then(response => response.json())
                                    .then(rooms => {
                                        populateRoomSelect(rooms);
                                        populateOldRoomSelect(rooms);
                                    })
                                    .catch(error => console.error('Error fetching room availability:', error));
                            }

                            // Call fetch initially
                            fetchWithUpdatedClassType();

                            // Listen for changes to class_type and fetch again when it changes
                            classTypeElement.addEventListener('change', fetchWithUpdatedClassType);
                        }


                        // Function to handle room population
                        function populateRoomSelect(rooms) {
                            const roomDatalist = document.getElementById('old_rooms');
                            roomDatalist.innerHTML = ''; // Clear existing options

                            // Add room options to the datalist
                            if (rooms.length > 0) {
                                rooms.forEach(room => {
                                    const option = document.createElement('option');
                                    option.value = room.room_code;
                                    option.textContent = `${room.room_code} - ${room.room_name}`;
                                    roomDatalist.appendChild(option);
                                });
                            } else {
                                // Handle the case where no rooms are available
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'No rooms available';
                                roomDatalist.appendChild(option);
                            }
                        }

                        function populateOldRoomSelect(rooms) {
                            const roomDatalist = document.getElementById('rooms');
                            roomDatalist.innerHTML = ''; // Clear existing options

                            // Add room options to the datalist
                            if (rooms.length > 0) {
                                rooms.forEach(room => {
                                    const option = document.createElement('option');
                                    option.value = room.room_code;
                                    option.textContent = `${room.room_code} - ${room.room_name}`;
                                    roomDatalist.appendChild(option);
                                });
                            } else {
                                // Handle the case where no rooms are available
                                const option = document.createElement('option');
                                option.value = '';
                                option.textContent = 'No rooms available';
                                roomDatalist.appendChild(option);
                            }
                        }

                        // Function to handle row clicks and fetch the availability
                        function handleRowClick(event) {
                            const cell = event.currentTarget;
                            const cellDetails = JSON.parse(cell.getAttribute('data-details'));

                            const courseCode = cellDetails.course_code;
                            const newCourseCode = cellDetails.new_course_code;
                            const classType = cellDetails.class_type; // Get the class type from cell details

                            // Set the class_type select value based on the row's current class type
                            document.getElementById('class_type').value = classType;

                            // Fetch availability based on course code
                            if (courseCode) {
                                fetchClassTypeAvailability(courseCode, classType);
                            } else if (newCourseCode) {
                                fetchClassTypeAvailability(newCourseCode, classType);
                            }
                        }

                        // Event listener for all shaded cells (rows)
                        document.addEventListener('DOMContentLoaded', () => {
                            const shadedCells = document.querySelectorAll('.shaded-cell');

                            shadedCells.forEach(cell => {
                                cell.addEventListener('click', handleRowClick);
                            });

                            // Fetch class type availability on page load if course codes are pre-filled
                            checkAndFetchAvailability();
                        });
                        // Function to check and fetch class type availability for pre-filled inputs
                        function checkAndFetchAvailability() {
                            const courseCode = document.getElementById('course_code').value;
                            const newCourseCode = document.getElementById('new_course_code').value;
                            const classType = document.getElementById('class_type').value;

                            if (courseCode) {
                                fetchClassTypeAvailability(courseCode, classType);
                            } else if (newCourseCode) {
                                fetchClassTypeAvailability(newCourseCode, classType);
                            }
                        }


                        // Event listener for the course_code input
                        document.getElementById('course_code').addEventListener('input', function () {
                            fetchClassTypeAvailability(this.value);
                        });


                        // Event listener for the new_course_code input
                        document.getElementById('new_course_code').addEventListener('input', function () {
                            fetchClassTypeAvailability(this.value);
                        });

                        // Filter button-specific functionality
                        (function () {
                            // Set up a flag to track if the filter button has been clicked
                            let isFilterButtonClicked = false;

                            // Get the filter button by its ID
                            const filterButton = document.getElementById('filter');

                            // Set the flag to true when the filter button is clicked
                            filterButton.addEventListener('click', (event) => {
                                // Set the flag
                                isFilterButtonClicked = true; // Update the flag

                                // The form will be submitted naturally since it's a submit button
                                // If you need to handle additional logic before the form submits, 
                                // you can uncomment the line below:
                                // event.preventDefault(); 
                                // fetchRooms(courseCode, roomType); // Call your function if needed
                            });

                            // Function to fetch rooms based on filter state
                            function fetchRoomsOnFilterClick(courseCode, roomType) {
                                // If the filter button has NOT been clicked, fetch rooms
                                if (!isFilterButtonClicked) {
                                    fetchRooms(courseCode, roomType);
                                }
                            }

                            // Expose fetchRoomsOnFilterClick function to be called externally
                            window.fetchRoomsOnFilterClick = fetchRoomsOnFilterClick;

                            // Additional logic can be added here to handle other events or states
                        })();
                    </script>
                    <input type="text" name="room_code" id="room_code" list="old_rooms" autocomplete="off"
                        value="<?php echo htmlspecialchars($firstRoomOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $hide; ?>>
                    <datalist id="old_rooms">
                        <?php echo $room_options; ?>
                    </datalist>
                    <input type="text" name="room_code" id="room_code_filtered" list="filtered" autocomplete="off"
                        value="<?php echo htmlspecialchars($firstRoomOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $unhide; ?> <?php echo $roomdisable; ?>>
                    <datalist id="filtered">
                        <?php echo $room_options; ?>
                    </datalist>
                    <input type="text" name="new_room_code" id="new_room_code" list="rooms" autocomplete="off"
                        value="<?php echo htmlspecialchars($lastSelectedRoom, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $roomReadonly; ?> <?php echo $newHide; ?>>
                    <datalist id="rooms">
                        <?php echo $new_room_options; ?>
                    </datalist>
                    <input type="text" name="new_room_code" id="new_room_code_filtered" list="filtered_new_rooms"
                        autocomplete="off"
                        value="<?php echo htmlspecialchars($lastSelectedRoom, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $roomReadonly; ?> <?php echo $newUnHide; ?> <?php echo $roomdisable; ?>>
                    <datalist id="filtered_new_rooms">
                        <?php echo $new_room_options; ?>
                    </datalist>

                    <?php
                    if ($user_type === "CCL Head") {
                        $cclreadonly = "readonly";
                    } else {
                        $cclreadonly = null;
                    }
                    ?>

                    <?php if ($user_college_code == $section_college_code): ?>
                        <label id="prof_code_label" for="prof_code">Instructor Code: <span
                                style="font-size: 12px; color: gray; font-style: italic;">(optional)</span></label>
                        <input type="text" id="prof_code" name="prof_code" list="old_professors" autocomplete="off"
                            value="<?php echo htmlspecialchars($firstProfOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $cclreadonly; ?>>
                        <datalist id="old_professors">
                            <?php echo $professor_options; ?>
                        </datalist>
                    <?php endif; ?>


                    <?php if ($user_college_code != $section_college_code): ?>
                        <label id="prof_code_label" for="prof_code">Instructor: <span
                                style="font-size: 12px; color: gray; font-style: italic;">(optional)</></label>
                        <input type="text" id="prof_code" name="prof_code" list="old_professors" autocomplete="off"
                            placeholder='First Name, MI, Last Name or Initials'
                            value="<?php echo htmlspecialchars($firstProfOption, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>


                    <?php if ($user_college_code == $section_college_code): ?>
                        <label for="new_prof_code" id="new_prof_code_label" style="display: none;">New Instructor
                            Code: <span style="font-size: 12px; color: gray; font-style: italic;">(optional)</span></label>
                        <input type="text" id="new_prof_code" name="new_prof_code" list="professors" autocomplete="off"
                            value="<?php echo htmlspecialchars($lastSelectedProf, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $profReadonly; ?> style="display: none;">
                        <datalist id="professors">
                            <?php echo $new_professor_options; ?>
                        </datalist>
                    <?php endif; ?>

                    <?php if ($user_college_code != $section_college_code): ?>
                        <label for="new_prof_code" id="new_prof_code_label" style="display: none;">New Instructor <span
                                style="font-size: 12px; color: gray; font-style: italic;">(optional)</span></label>
                        <input type="text" id="new_prof_code" name="new_prof_code" list="professors" autocomplete="off"
                            placeholder='First Name, MI, Last Name or Initials'
                            value="<?php echo htmlspecialchars($lastSelectedProf, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $profReadonly; ?> style="display: none;">
                    <?php endif; ?>
                </div>

                <div class="btn" id="button">
                    <input type="submit" id="plotScheduleBtn" name="plot_schedule" value="Plot Schedule" <?php echo $btnAdd; ?>>
                    <input type="submit" id="updateScheduleBtn" name="update_schedule" value="Update" <?php echo $btnUpdate; ?>>
                    <input type="submit" id="deleteScheduleBtn" name="delete_schedule" value="Delete" <?php echo $btnDelete; ?>>

                    <input type="button" data-bs-toggle="modal" data-bs-target="#shareScheduleModal"
                        id="shareScheduleBtn" name="share_schedule" <?php echo $btnShare; ?> value="Share">

                    <input type="button" data-bs-toggle="modal" data-bs-target="#UnShareScheduleModal"
                        id="UnShareScheduleBtn" name="unshare_schedule" value="Shared" <?php echo $btnUnShare; ?>>

                </div>
            </div>


    </form>

    <div class="col-md-8">

        <div class="table-container">
            <?php

            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "schedsys";

            // Create connection
            $conn = new mysqli($servername, $username, $password, $dbname);

            // Check connection
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }

            // Fetch section_code and ay_code from tbl_secschedlist
            $sql_fetch_section_info = "SELECT section_code, ay_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
            $result_section_info = $conn->query($sql_fetch_section_info);

            if (!$result_section_info) {
                die("Error fetching section info: " . $conn->error);
            }

            if ($result_section_info->num_rows > 0) {
                $row_section_info = $result_section_info->fetch_assoc();
                $section_code = $row_section_info['section_code'];


                $sql_secsched = "SELECT sender_dept_code, sender_email FROM tbl_shared_sched WHERE receiver_email = ? AND shared_section = ?";
                $stmt = $conn->prepare($sql_secsched);


                if ($stmt) {
                    $stmt->bind_param("ss", $current_user_email, $section_sched_code);
                    $stmt->execute();
                    $result_secsched = $stmt->get_result();

                    if ($row_secsched = $result_secsched->fetch_assoc()) {
                        $sender_dept_code = $row_secsched['sender_dept_code'];
                        $sender_email = $row_secsched['sender_email'];

                        $query = "SELECT user_type FROM tbl_prof_acc WHERE cvsu_email = '$sender_email' AND ay_code = '$ay_code' AND semester = '$semester'";
                        $result = mysqli_query($conn, $query);

                        if ($result && mysqli_num_rows($result) > 0) {
                            $row = mysqli_fetch_assoc($result); // Fetch the row as an associative array
                            $sender_user_type = htmlspecialchars($row['user_type']); // Safely get the user_type
                        } else {
                            $sender_user_type = null; // Handle the case where no result is found
                        }


                        echo "Shared by: " . htmlspecialchars($sender_dept_code) . ", " . htmlspecialchars($sender_user_type) . ", " . htmlspecialchars($sender_email) . "<br>";
                    } else {
                        $sender_dept_code = null;
                        $sender_email = null;
                    }

                    $stmt->close();
                } else {
                    echo "Error preparing section schedule query: " . $conn->error . "<br>";
                }
                // Sanitize table name for section schedule
                $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");

                // Sanitize table name for section schedule
                // $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            
                // Query to fetch schedule data for the selected semester
                $sql_fetch_schedule = "SELECT * FROM $sanitized_dept_code WHERE semester='$semester' AND section_sched_code='$section_sched_code' AND ay_code = '$ay_code'";
                $result_schedule = $conn->query($sql_fetch_schedule);

                if (!$result_schedule) {
                    die("Error fetching schedule data: " . $conn->error);
                }

                if ($result_schedule->num_rows > 0) {
                    $schedule_data = [];
                    while ($row_schedule = $result_schedule->fetch_assoc()) {
                        $day = $row_schedule['day'];
                        $time_start = $row_schedule['time_start'];
                        $time_end = $row_schedule['time_end'];
                        $course_code = $row_schedule['course_code'];
                        $room_code = $row_schedule['room_code'];
                        $prof_code = $row_schedule['prof_code'];
                        $sec_sched_id = $row_schedule['sec_sched_id'];
                        $sched_dept_code = $row_schedule['dept_code'];
                        $semester = $row_schedule['semester'];
                        $prof_name = $row_schedule['prof_name'];
                        $class_type = $row_schedule['class_type'];
                        $shared_to = isset($row_schedule['shared_to']) ? $row_schedule['shared_to'] : null;
                        $shared_sched = isset($row_schedule['shared_sched']) ? $row_schedule['shared_sched'] : null;

                        $sql_fetch_schedule_id = "SELECT * FROM $sanitized_dept_code 
                        WHERE semester='$semester' 
                        AND section_sched_code='$section_sched_code' 
                        AND ay_code='$ay_code' 
                        AND sec_sched_id='$sec_sched_id'";

                        $result_schedule_id = $conn->query($sql_fetch_schedule_id);


                        if (!isset($schedule_data[$day])) {
                            $schedule_data[$day] = [];
                        }

                        $sql_allowed_rooms = "SELECT allowed_rooms,computer_room
                        FROM tbl_course 
                        WHERE dept_code = ? 
                          AND course_code = ? 
                          AND program_code = ? 
                          AND year_level = ?";

                        // Prepare the SQL statement
                        $stmt_allowed_rooms = $conn->prepare($sql_allowed_rooms);


                        if ($stmt_allowed_rooms) {
                            // Bind parameters to the query
                            $stmt_allowed_rooms->bind_param("ssss", $sched_dept_code, $course_code, $program_code, $year_level);

                            // Execute the query
                            $stmt_allowed_rooms->execute();

                            // Get the result
                            $result_allowed_rooms = $stmt_allowed_rooms->get_result();

                            // Check if a row was returned
                            if ($result_allowed_rooms->num_rows > 0) {
                                // Fetch the allowed_rooms value
                                $row = $result_allowed_rooms->fetch_assoc();
                                $allowed_rooms = $row['allowed_rooms']; // e.g., 'lecR', 'lecR&labR', etc.
                                $computer_room = $row['computer_room'];
                            } else {
                                // Handle case where no result is found
                                $allowed_rooms = null; // Or set a default value
                                error_log("No allowed_rooms found for the given course and department.");
                            }

                            // Close the statement
                            $stmt_allowed_rooms->close();
                        }

                        $schedule_data[$day][] = [
                            'section_sched_code' => $section_sched_code,
                            'sec_sched_id' => $sec_sched_id,
                            'semester' => $semester,
                            'day' => $day,
                            'time_start' => $time_start,
                            'time_end' => $time_end,
                            'course_code' => $course_code,
                            'room_code' => $room_code,
                            'prof_code' => $prof_code,
                            'prof_name' => $prof_name,
                            'section_code' => $section_code,
                            'dept_code' => $sched_dept_code,
                            'shared_to' => $shared_to,
                            'shared_sched' => $shared_sched,
                            'class_type' => $class_type,
                            'allowed_rooms' => $allowed_rooms,
                            'computer_room' => $computer_room,
                        ];
                    }

                    // Function to format time
                    function formatTime($time)
                    {
                        return date('h:i A', strtotime($time));
                    }

                    // Fetch user-selected colors for departments
                    $dept_colors = [];
                    $sql_fetch_colors = "SELECT dept_code, cell_color FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
                    $result_colors = $conn->query($sql_fetch_colors);

                    if ($result_colors && $result_colors->num_rows > 0) {
                        while ($row_color = $result_colors->fetch_assoc()) {
                            $dept_colors[$row_color['dept_code']] = $row_color['cell_color'];
                        }
                    }

                    // Fetch default colors from tbl_sanitize_section_code
                    $sanitize_section_colors = [];
                    $sql_fetch_sanitize_colors = "SELECT dept_code, cell_color FROM $sanitized_dept_code WHERE section_sched_code = '$section_sched_code' AND semester = '$semester' ";
                    $result_sanitize_colors = $conn->query($sql_fetch_sanitize_colors);

                    if ($result_sanitize_colors && $result_sanitize_colors->num_rows > 0) {
                        while ($row_sanitize_color = $result_sanitize_colors->fetch_assoc()) {
                            $sanitize_section_colors[$row_sanitize_color['dept_code']] = $row_sanitize_color['cell_color'];
                        }
                    }
                    // Default color if not set
                    $default_color = '#FFFFFF';
                    // Sort schedule data by start time for each day
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



                    // Convert user input to timestamps for easier manipulation
                    $start_timestamp = strtotime($user_start_time);
                    $end_timestamp = strtotime($user_end_time);

                    // Validate input: ensure end time is after start time
                    if ($start_timestamp >= $end_timestamp) {
                        echo "End time must be later than start time.";
                        exit;
                    }

                    $time_slots = [];
                    // Set the interval in seconds (e.g., 1800 seconds = 30 minutes)
                    $interval = 1800;


                    // Loop through the time range to create time slots
                    for ($current_time = $start_timestamp; $current_time < $end_timestamp; $current_time += $interval) {
                        $start_time = date('H:i', $current_time);
                        $end_time = date('H:i', $current_time + $interval);

                        // Format start and end times for display
                        $start_time_display = date("g:i A", strtotime($start_time));
                        $end_time_display = date("g:i A", strtotime($end_time));

                        $time_slots[] = [
                            'start' => $start_time,
                            'end' => $end_time,
                            'start_display' => $start_time_display,
                            'end_display' => $end_time_display
                        ];
                    }



                    // Initialize the array to track the remaining rowspan for each column
                    $remaining_rowspan = array_fill_keys($day_names, 0);

                    foreach ($time_slots as $slot) {
                        $start_time = $slot['start'];
                        $end_time = $slot['end'];
                        $start_time_formatted = formatTime($start_time);
                        $end_time_formatted = formatTime($end_time);

                        $start_time_display = date("H:i:s", strtotime($start_time));

                        $html .= '<tr>';
                        $html .= '<td class="time-slot">' . $slot['start_display'] . ' - ' . $end_time_formatted . '</td>';
                        $slot_display = $slot['start_display'];

                        foreach ($day_names as $day_name) {
                            if ($remaining_rowspan[$day_name] > 0) {
                                // This column is already covered by a rowspan cell, so decrement the counter and skip this cell
                                $remaining_rowspan[$day_name]--;
                            } else {
                                $cell_content = '';
                                $rowspan = 1;
                                $cell_details = [];
                                $background_color = $default_color; // Default color
            
                                if (isset($schedule_data[$day_name])) {
                                    foreach ($schedule_data[$day_name] as $index => $schedule) {
                                        $schedule_start = strtotime($schedule['time_start']);
                                        $schedule_end = strtotime($schedule['time_end']);
                                        $current_start = strtotime($start_time);
                                        $current_end = strtotime($end_time);


                                        if (($current_start < $schedule_end && $current_end > $schedule_start)) {
                                            $shared = !empty($schedule['shared_sched']) ? "Shared" : ""; // Check if shared_sched is not empty
                                            $cell_content = "";

                                            if (!empty($schedule['course_code'])) {
                                                $cell_content .= "<b>{$schedule['course_code']}</b>";
                                            }

                                            if (!empty($schedule['class_type'])) {
                                                $cell_content .= " ({$schedule['class_type']})<br>";
                                            }

                                            if (!empty($schedule['room_code'])) {
                                                $cell_content .= "{$schedule['room_code']}<br>";
                                            }

                                            if (!empty($schedule['prof_name'])) {
                                                $cell_content .= "{$schedule['prof_name']}<br>";
                                            }

                                            if (empty($schedule['prof_name'])) {
                                                $cell_content .= "{$schedule['prof_code']}<br>";
                                            }

                                            if (!empty($schedule['shared_sched']) && $schedule['shared_to'] == $current_user_email) {
                                                $cell_content .= "<b><i style='font-size:9px;'>Shared to You</i></b>";
                                            } else if (!empty($schedule['shared_sched']) && $user_type == "CCL Head") {
                                                $cell_content .= "<b><i style='font-size:9px;' ></i></b>";
                                            } else if (!empty($schedule['shared_sched'])) {
                                                $cell_content .= "<b><i style='font-size:9px;' >Shared by You</i></b>";
                                            }


                                            // Trim any trailing <br> if necessary
                                            $cell_content = rtrim($cell_content, '<br>');
                                            $intervals = ($schedule_end - $schedule_start) / 1800;
                                            $rowspan = max($intervals, 1);
                                            $cell_details = $schedule;
                                            // Apply the user-selected color if dept_code matches
                                            if (isset($dept_colors[$schedule['dept_code']])) {
                                                $background_color = $dept_colors[$schedule['dept_code']];
                                            } elseif (isset($sanitize_section_colors[$schedule['dept_code']])) {
                                                // Apply the fallback color from tbl_sanitize_section_code
                                                $background_color = $sanitize_section_colors[$schedule['dept_code']];
                                            }
                                            unset($schedule_data[$day_name][$index]);
                                            $schedule_data[$day_name] = array_values($schedule_data[$day_name]);
                                            break; // Only need to process one schedule per cell
                                        }
                                    }
                                }

                                if ($cell_content) {
                                    $html .= '<td class="shaded-cell" data-details=\'' . htmlspecialchars(json_encode($cell_details ?? [])) . '\' rowspan="' . $rowspan . '" style="background-color: ' . htmlspecialchars($background_color, ENT_QUOTES, 'UTF-8') . '; text-align: center; vertical-align: middle;">' . $cell_content . '</td>';
                                    $remaining_rowspan[$day_name] = $rowspan - 1;
                                } else {
                                    $html .= '<td class="blankCells" data-day="' . $day_name . '" data-start-time="' . $start_time_display . '"></td>';

                                }
                            }
                        }

                        $html .= '</tr>';
                    }

                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                    echo $html;
                } else {
                    echo "No schedules found for the selected semester.";
                }
            }
            ?>

        </div>



        <!-- Modal Structure -->
        <div class="modal fade" id="shareScheduleModal" tabindex="-1" role="dialog" aria-labelledby="shareScheduleLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered " role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shareScheduleLabel">Share Schedule</h5>
                        <button type="button" class="btn-close" class="btn-close custom-close-btn"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Form for Sharing Schedule -->
                        <form id="shareForm" method="POST" action="plotSchedule.php">
                            <?php
                            // Fetch department secretary emails and dept_code
                            $query = "SELECT cvsu_email, dept_code,college_code,user_type FROM tbl_prof_acc WHERE user_type = 'Department Secretary' AND ay_code = '$ay_code' AND semester = '$semester'
                                AND cvsu_email IS NOT NULL 
                                AND dept_code != '$dept_code'";
                            $result = mysqli_query($conn, $query);
                            if ($result && mysqli_num_rows($result) > 0) {
                                echo '<label for="recipient_email" style="text-align:left;">Share to:</label>';
                                echo '<input list="email_list" id="recipient_email" name="recipient_email" placeholder="Type or select an email" autocomplete = "off" required>';
                                echo '<datalist id="email_list">';
                                echo '<option value="">Select Recipient Email</option>';
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $email = htmlspecialchars($row['cvsu_email']);
                                    $department = htmlspecialchars($row['dept_code']);
                                    $college = htmlspecialchars($row['college_code']);
                                    $userType = htmlspecialchars($row['user_type']);
                                    if ($email !== $current_user_email) {
                                        echo '<option data-dept-code="' . $department . '" value="' . $email . '">' . $email . ' (' . $userType . ' - ' . $department . '-' . $college . ')</option>';
                                    }
                                }
                                echo '</datalist>';
                            } else {
                                echo '<p>No department secretary emails found.</p>';
                            }
                            ?>
                            <label style="text-align:left;">Request for: </label>
                            <input type="test" id="Room" name="Room" value="Room" readonly>
                            <input type="hidden" id="shared_sched" name="shared_sched" value="room">

                            <!-- <select class="" id="shared_sched" name="shared_sched" required>
                                <option value="prof">Professor</option>
                                <option value="room">Room</option>
                            </select> -->
                            <!-- Hidden Inputs for additional data -->
                            <input type="hidden" id="section_sched_code" name="section_sched_code"
                                value="<?php echo $section_sched_code; ?>">
                            <input type="hidden" id="modal_sec_sched_id" name="modal_sec_sched_id">
                            <input type="hidden" id="dept_code" name="dept_code" value="<?php echo $dept_code; ?>">
                            <input type="hidden" id="status" name="status" value="draft">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                            <input type="hidden" id="modal_room_code" name="room_code" readonly>
                            <input type="hidden" id="modal_prof_code" name="prof_code" readonly>
                            <br>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" id="btnYes" name="send" value="send" class="btn col-md-2 ">Send</button>
                    </div>
                    </form>

                </div>
            </div>
        </div>
        <div class="modal fade" id="UnShareScheduleModal" tabindex="-1" role="dialog"
            aria-labelledby="UnShareScheduleLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="UnShareScheduleLabel">Share Schedule</h5>
                        <button type="button" class="btn-close" class="btn-close custom-close-btn"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Form for Sharing Schedule -->
                        <form id="UnShareForm" method="POST" action="plotSchedule.php">
                            <div class="form-group">
                                <label for="statement">Are you sure you want to stop sharing this schedule?</label>
                            </div>
                    </div>
                    <!-- Hidden Inputs for additional data -->
                    <input type="hidden" id="section_sched_code" name="section_sched_code"
                        value="<?php echo $section_sched_code; ?>" readonly>
                    <input type="hidden" id="modal_sched_id" name="modal_sec_sched_id" readonly>
                    <input type="hidden" id="dept_code" name="dept_code" value="<?php echo $dept_code; ?>" readonly>
                    <input type="hidden" id="semester" name="semester" value="<?php echo $semester; ?>" readonly>
                    <input type="hidden" id="ay_code" name="ay_code" value="<?php echo $ay_code; ?>" readonly>
                    <input type="hidden" id="section_code" name="section_code" value="<?php echo $section_code; ?>"
                        readonly>
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>" readonly>
                    <input type="hidden" id="modal_room" name="room_code" readonly>
                    <input type="hidden" id="modal_prof" name="prof_code" readonly>
                    <br>
                    <div class="modal-footer">
                        <button type="submit" id="btnYes" class="btn col-md-2" id="unsent" name="unsent">Yes</button>
                        <button type="button" id="btnNo" class="btn col-md-2" data-bs-dismiss="modal">No</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('shareScheduleBtn').addEventListener('click', function () {
            // Get the value of the sec_sched_id from the hidden input
            var sec_sched_id = document.getElementById('sec_sched_id').value;
            var roomCode = document.getElementById('room_code').value;
            var profCode = document.getElementById('prof_code').value;

            // Set the value of the hidden input in the modal
            document.getElementById('modal_sec_sched_id').value = sec_sched_id;
            document.getElementById('modal_room_code').value = roomCode;
            document.getElementById('modal_prof_code').value = profCode;

        });

        document.getElementById('UnShareScheduleBtn').addEventListener('click', function () {
            // Get the value of the sec_sched_id from the hidden input
            var sec_sched_id = document.getElementById('sec_sched_id').value;
            var roomCode = document.getElementById('room_code').value;
            var profCode = document.getElementById('prof_code').value;

            // Set the value of the hidden input in the modal
            document.getElementById('modal_sched_id').value = sec_sched_id;
            document.getElementById('modal_room').value = roomCode;
            document.getElementById('modal_prof').value = profCode;

        });

    </script>


    <div class="modal fade" id="createTableModal" tabindex="-1" role="dialog" aria-labelledby="createTableModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTableModalLabel">Plot Section Schedule</h5>
                    <button type="button" class="btn-close" id="btnClose" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm" action="" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" id="modal_section_sched_code"
                                name="section_sched_code" readonly required>
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
                            <select class="form-control w-100" name="program_code" id="modal_program_code" required>
                                <option value="" disabled selected>Program Type</option>
                                <?php
                                // Display unique program_code in the dropdown
                                foreach (array_unique(array_column($programs, 'program_code')) as $program_code): ?>
                                    <option value="<?php echo htmlspecialchars($program_code); ?>" <?php if ($program_code == $search_program_code)
                                           echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($program_code); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control w-100" name="curriculum" id="modal_curriculum" required>
                                <option value="" disabled selected>Curriculum</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <!-- Year Level Dropdown -->
                            <select class="form-control" id="modal_year_level" name="year_level" required>
                                <option value="" disabled selected>Year Level</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control" id="modal_section_code" name="section_code" required>
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
                            <input type="hidden" id="ay_code" name="ay_code"
                                value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester"
                                value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>
                        <button type="submit" name="create_table" id="create" class="btn">Plot Schedule</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>

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
            const yearLevelDropdown = document.getElementById('modal_year_level');
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
            const sectionDropdown = document.getElementById('modal_section_code');
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
        document.getElementById('modal_program_code').addEventListener('change', function () {
            const selectedProgramCode = this.value;
            const curriculumDropdown = document.getElementById('modal_curriculum');

            // Clear existing options in curriculum dropdown
            curriculumDropdown.innerHTML = '<option value="" disabled selected>Curriculum</option>';

            // Populate curriculum based on the selected program
            const selectedPrograms = programs.filter(program => program.program_code === selectedProgramCode);
            selectedPrograms.forEach(program => {
                curriculumDropdown.innerHTML += `<option value="${program.curriculum}">${program.curriculum}</option>`;
            });
        });

        // Add event listener for curriculum changes
        document.getElementById('modal_curriculum').addEventListener('change', function () {
            const selectedProgramCode = document.getElementById('modal_program_code').value;
            const selectedCurriculum = this.value;

            if (selectedProgramCode && selectedCurriculum) {
                populateYearLevels(selectedProgramCode, selectedCurriculum);
            }
        });

        // Add event listener for year level changes
        document.getElementById('modal_year_level').addEventListener('change', function () {
            const selectedProgramCode = document.getElementById('modal_program_code').value;
            const selectedCurriculum = document.getElementById('modal_curriculum').value;
            const selectedYearLevel = this.value;

            if (selectedProgramCode && selectedCurriculum && selectedYearLevel) {
                populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel);
            }
        });

    </script>




    <!-- Save to Draft Modal -->
    <div class="modal fade" id="saveToDraftModal" tabindex="-1" aria-labelledby="saveToDraftModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saveToDraftModalLabel">Draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="draftForm" method="POST" action=" ">
                        <input type="hidden" id="section_sched_code" name="section_sched_code"
                            value="<?php echo $section_sched_code; ?>">
                        <input type="hidden" id="dept_code" name="dept_code" value="<?php echo $dept_code; ?>">
                        <input type="hidden" id="semester" name="semester" value="<?php echo $semester; ?>">
                        <input type="hidden" id="ay_code" name="ay_code" value="<?php echo $ay_code; ?>">
                        <input type="hidden" id="section_code" name="section_code" value="<?php echo $section_code; ?>">
                        <input type="hidden" id="status" name="status" value="draft">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <label for="drafstatement">Are you sure you want to save this schedule to Draft?</label>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="btnYes" class="btn col-md-2 " id="saveDraftButton"
                        name="saveDraftButton">Yes</button>
                    <button type="button" id="btnNo" class="btn col-md-2 " data-bs-dismiss="modal">No</button>
                </div>

                </form>
            </div>
        </div>
    </div>
    </div>

    <!-- Complete Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completeModalLabel">Complete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="completeForm" method="POST" action="">
                        <input type="hidden" id="section_sched_code" name="section_sched_code"
                            value="<?php echo $section_sched_code; ?>">
                        <input type="hidden" id="dept_code" name="dept_code" value="<?php echo $dept_code; ?>">
                        <input type="hidden" id="semester" name="semester" value="<?php echo $semester; ?>">
                        <input type="hidden" id="ay_code" name="ay_code" value="<?php echo $ay_code; ?>">
                        <input type="hidden" id="section_code" name="section_code" value="<?php echo $section_code; ?>">
                        <input type="hidden" id="status" name="status" value="private">
                        <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        <label for="completestatement">Are you sure you want to mark this as complete?</label>
                </div>
                <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                    <button type="submit" id="btnYes" class="btn col-md-2" id="saveCompleteButton"
                        name="saveCompleteButton">Yes</button>
                    <button type="button" id="btnNo" class="btn col-md-2 " data-bs-dismiss="modal">No</button>
                </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="plotSchedule.php">
                        Are you sure you want to delete this?
                        <input type="hidden" name="item_id" id="item_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="submit" id="btnYes" class="btn col-md-2" name="deleteButton">Yes</button>
                    <button type="button" id="btnNo" class="btn col-md-2" data-bs-dismiss="modal">No</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="CopyModal" tabindex="-1" aria-labelledby="CopyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="CopyModalLabel">Copy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="plotSchedule.php">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">

                        <?php

                        $curriculum_check_query = "SELECT * FROM tbl_section 
                        WHERE section_code = '$section_code' AND  dept_code = '$section_dept_code' AND ay_code = '$ay_code' AND semester = '$semester'";
                        $curriculum_result = $conn->query($curriculum_check_query);

                        if ($curriculum_result->num_rows > 0) {
                            $curriculum_row = $curriculum_result->fetch_assoc();
                            $curriculum = $curriculum_row['curriculum'];
                            $year_level = $curriculum_row['year_level'];
                            $program_code = $curriculum_row['program_code'];
                             $petition = $curriculum_row['petition'];
                        }



                        $curriculum_check_query = "SELECT * FROM tbl_section 
                        WHERE dept_code = '$section_dept_code' 
                        AND ay_code = '$ay_code' 
                        AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level' AND curriculum = '$curriculum' AND petition = '$petition' AND section_code != '$section_code' ";

                        $curriculum_result = $conn->query($curriculum_check_query);
                        ?>

                        <div class="mb-3">
                            <select class="form-select" name="section_code" id="sectionSelect" required>
                                <option value="" disabled selected>Select a section</option>
                                <?php
                                if ($curriculum_result->num_rows > 0) {
                                    while ($row = $curriculum_result->fetch_assoc()) {
                                        echo '<option value="' . $row['section_code'] . '">' . $row['section_code'] . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No sections found</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <input type="hidden" name="item_id" id="item_id" value="">

                </div>
                <div class="modal-footer">
                    <button type="submit" id="btnYes" class="btn col-md-2" name="CopyButton">Copy</button>
                    <button type="button" id="btnNo" class="btn col-md-2" data-bs-dismiss="modal">No</button>
                </div>
                </form>



            </div>
        </div>
    </div>



    <div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="conflictModalLabel">Alert</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding: 50px; text-align: center;">
                    <!-- Conflicts will be injected here -->
                    <ul id="conflictList" style="list-style-type: none; padding: 0; margin: 0; text-decoration: none;">
                        <!-- List items will be dynamically injected here -->
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnNo" class="btn col-md-2" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>



    <div id="deletePopup" class="popup">
        Deleted
    </div>

    <!-- Plot Popup with Embedded Modal -->
    <div id="plotPopup" class="popup" style="display:none;">
        Plotted

    </div>





    <div id="updatePopup" class="popup">
        Updated
    </div>
</body>

</html>