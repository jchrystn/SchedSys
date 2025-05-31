<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");


if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head') {
    header("Location: /SchedSys3/php/login/login.php");
    exit();
}


unset($_POST['search_ay_code']);
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown';
$first_name = htmlspecialchars(isset($_SESSION["first_name"]) ? $_SESSION["first_name"] : '');
$middle_initial = htmlspecialchars(isset($_SESSION['middle_initial']) ? $_SESSION['middle_initial'] : '');
$last_name = htmlspecialchars(isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '');
$user_college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';


if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}

// Handle Academic Year options
$fetch_info_query = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = '$user_college_code' AND active = '1'";
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


$ay_code = $_POST['search_ay_code'] ?? $active_ay_code;
$semester = $_POST['search_semester'] ?? $active_semester;

// Handle Academic Year options
$ay_options = [];
$sql_ay = "SELECT DISTINCT ay_name, ay_code FROM tbl_ay";
$result_ay = $conn->query($sql_ay);

if ($result_ay->num_rows > 0) {
    while ($row_ay = $result_ay->fetch_assoc()) {
        $ay_options[] = [
            'ay_name' => $row_ay['ay_name'],
            'ay_code' => $row_ay['ay_code']
        ];
    }
}

// Handle the Academic Year selection
if (isset($_POST['search_ay_name'])) {
    $ay_name = $_POST['search_ay_name'];
    foreach ($ay_options as $option) {
        if ($option['ay_name'] == $ay_name) {
            $selected_ay_code = $option['ay_code'];
            $_SESSION['ay_code'] = $selected_ay_code;
            break;
        }
    }
} elseif (isset($_SESSION['ay_name'])) {
    $ay_name = $_SESSION['ay_name'];
    foreach ($ay_options as $option) {
        if ($option['ay_name'] == $ay_name) {
            $selected_ay_code = $option['ay_code'];
            break;
        }
    }
} else {
    if (!empty($ay_options)) {
        $ay_name = $ay_options[0]['ay_name'];
        $selected_ay_code = $ay_options[0]['ay_code'];
    }
}

// Handle the Semester selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_semester'])) {
    $semester = $_POST['search_semester'];
} elseif (isset($_SESSION['semester'])) {
    $semester = $_SESSION['semester'];
} else {
    // Default fallback if session value is not set
    $semester = '1st Semester'; // or any other default value
}

// Handle POST requests for Edit of Schedule
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit'])) {
    $_SESSION['room_sched_code'] = $_POST['room_sched_code'];
    $_SESSION['semester'] = $_POST['semester'];
    $_SESSION['ay_code'] = $_POST['ay_code'];
    $_SESSION['room_code'] = $_POST['room_code'];
    header("Location: ../create_sched/plotSchedule.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $room_sched_code = $_POST['room_sched_code'];
    $semester = $_POST['semester'];
    $room_code = $_POST['room_code'];
    $dept_code = $_POST['dept_code'];
    $ay_code = $_POST['ay_code'];
    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

    $sanitized_section_sched_code = null;
    $sanitized_prof_sched_code = null;

    // Fetch data from the room schedule table
    $sql = "SELECT * FROM $sanitized_room_sched_code WHERE room_sched_code = ? AND semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $room_sched_code, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sec_sched_id = $row['sec_sched_id'];
            $section_sched_code = $row['section_sched_code'];
            $section_code = $row['section_code'];
            $prof_code = $row['prof_code'];
            $dept_code = $_POST['dept_code'];
            $ay_code = $_POST['ay_code'];
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $section_sched_code = $section_code . "_" . $ay_code;
            $prof_sched_code = $prof_code . "_" . $ay_code;

            // Delete from room schedule table
            $sql_delete_room = "DELETE FROM $sanitized_room_sched_code WHERE sec_sched_id = ? AND semester = ?";
            $stmt_delete_room = $conn->prepare($sql_delete_room);
            $stmt_delete_room->bind_param('ss', $sec_sched_id, $semester);
            if (!$stmt_delete_room->execute()) {
                echo "Error deleting from room schedule: " . $stmt_delete_room->error;
            }
            $stmt_delete_room->close();

            // Delete from professor schedule table
            if ($sanitized_prof_sched_code) {

                $fetch_hours_sql = "SELECT TIME_TO_SEC(TIMEDIFF(time_end, time_start)) / 3600 AS total_hours
                                    FROM $sanitized_prof_sched_code
                                    WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ?";
                $stmt_fetch_hours = $conn->prepare($fetch_hours_sql);
                if (!$stmt_fetch_hours) {
                    die("Prepare failed: " . $conn->error);
                }
                $stmt_fetch_hours->bind_param("sss", $prof_sched_code, $semester, $sec_sched_id);
                $stmt_fetch_hours->execute();
                $stmt_fetch_hours->bind_result($total_hours);
                $stmt_fetch_hours->fetch();
                $stmt_fetch_hours->close();

                $sql_delete_prof = "DELETE FROM $sanitized_prof_sched_code WHERE sec_sched_id = ? AND semester = ?";
                $stmt_delete_prof = $conn->prepare($sql_delete_prof);
                $stmt_delete_prof->bind_param('ss', $sec_sched_id, $semester);
                if (!$stmt_delete_prof->execute()) {
                    echo "Error deleting from professor's schedule: " . $stmt_delete_prof->error;
                }
                $stmt_delete_prof->close();

                // Update `tbl_psched_counter` by subtracting the total hours
                $update_schedstatus_sql = "UPDATE tbl_psched_counter
                                        SET current_teaching_hrs = current_teaching_hrs - ?
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

                // Check if the specific entry still exists in the professor's schedule table
                $sql_check_prof_status = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_sched_code WHERE prof_sched_code = ? AND semester = ?";
                $stmt_check_prof_status = $conn->prepare($sql_check_prof_status);
                $stmt_check_prof_status->bind_param('ss', $prof_sched_code, $semester);
                if (!$stmt_check_prof_status->execute()) {
                    echo "Error checking professor's schedule table: " . $stmt_check_prof_status->error;
                } else {
                    $result_check_prof_status = $stmt_check_prof_status->get_result();
                    $row_count_prof_status = $result_check_prof_status->fetch_assoc()['row_count'];
                    $stmt_check_prof_status->close();

                    // If no entries exist, delete from tbl_prof_schedstatus
                    if ($row_count_prof_status == 0) {
                        $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?";
                        $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                        $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $ay_code);
                        if (!$stmt_delete_schedstatus->execute()) {
                            echo "Error deleting from tbl_prof_schedstatus: " . $stmt_delete_schedstatus->error;
                        }
                        $stmt_delete_schedstatus->close();
                    }
                }

                // Check if the professor's schedule table is empty
                $sql_check_empty_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_sched_code";
                $stmt_check_empty_prof = $conn->prepare($sql_check_empty_prof);
                if (!$stmt_check_empty_prof->execute()) {
                    echo "Error checking professor's schedule table: " . $stmt_check_empty_prof->error;
                } else {
                    $result_check_empty_prof = $stmt_check_empty_prof->get_result();
                    $row_count_prof = $result_check_empty_prof->fetch_assoc()['row_count'];
                    $stmt_check_empty_prof->close();

                    // Drop the professor's schedule table if it's empty
                    if ($row_count_prof == 0) {
                        $sql_drop_prof_table = "DROP TABLE IF EXISTS $sanitized_prof_sched_code";
                        $stmt_drop_prof_table = $conn->prepare($sql_drop_prof_table);
                        if (!$stmt_drop_prof_table->execute()) {
                            echo "Error dropping professor's schedule table: " . $stmt_drop_prof_table->error;
                        }
                        $stmt_drop_prof_table->close();
                    }
                }
            }

            // Delete from section schedule table
            if ($sanitized_section_sched_code) {
                $sql_delete_section = "DELETE FROM $sanitized_section_sched_code WHERE semester = ? AND sec_sched_id = ?";
                $stmt_delete_section = $conn->prepare($sql_delete_section);
                $stmt_delete_section->bind_param('ss', $semester, $sec_sched_id);
                if (!$stmt_delete_section->execute()) {
                    echo "Error deleting from section's schedule table: " . $stmt_delete_section->error;
                }
                $stmt_delete_section->close();

                // Check if the section schedule table is empty
                $sql_check_empty_section = "SELECT COUNT(*) AS row_count FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ?";
                $stmt_check_empty_section = $conn->prepare($sql_check_empty_section);
                $stmt_check_empty_section->bind_param('ss', $section_code, $semester);
                if ($stmt_check_empty_section->execute()) {
                    $result_check_empty_section = $stmt_check_empty_section->get_result();
                    $row_count_section = $result_check_empty_section->fetch_assoc()['row_count'];
                    $stmt_check_empty_section->close();

                    if ($row_count_section == 0) {
                        $sql_drop_section_table = "DROP TABLE IF EXISTS $sanitized_section_sched_code";
                        $stmt_drop_section_table = $conn->prepare($sql_drop_section_table);
                        if (!$stmt_drop_section_table->execute()) {
                            echo "Error dropping section's schedule table: " . $stmt_drop_section_table->error;
                        }
                        $stmt_drop_section_table->close();

                        // Also delete from tbl_schedstatus if the table doesn't exist
                        $sql_delete_schedstatus = "DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
                        $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                        $stmt_delete_schedstatus->bind_param('sss', $section_code, $semester, $ay_code);
                        if (!$stmt_delete_schedstatus->execute()) {
                            echo "Error deleting from tbl_schedstatus: " . $stmt_delete_schedstatus->error;
                        }
                        $stmt_delete_schedstatus->close();
                    }
                }
            }
        }
    }

    // Delete from tbl_room_schedstatus
    $sql_delete_room_status = "DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ?";
    $stmt_delete_room_status = $conn->prepare($sql_delete_room_status);
    $stmt_delete_room_status->bind_param('sss', $room_sched_code, $semester, $ay_code);
    if (!$stmt_delete_room_status->execute()) {
        echo "Error deleting from tbl_room_schedstatus: " . $stmt_delete_room_status->error;
    }
    $stmt_delete_room_status->close();

    // Check if the room schedule table is empty
    $sql_check_empty_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_sched_code";
    $stmt_check_empty_room = $conn->prepare($sql_check_empty_room);
    if (!$stmt_check_empty_room->execute()) {
        echo "Error checking room schedule table: " . $stmt_check_empty_room->error;
    } else {
        $result_check_empty_room = $stmt_check_empty_room->get_result();
        $row_count_room = $result_check_empty_room->fetch_assoc()['row_count'];
        $stmt_check_empty_room->close();

        // Drop the room schedule table if it's empty
        if ($row_count_room == 0) {
            $sql_drop_room_table = "DROP TABLE IF EXISTS $sanitized_room_sched_code";
            $stmt_drop_room_table = $conn->prepare($sql_drop_room_table);
            if (!$stmt_drop_room_table->execute()) {
                echo "Error dropping room schedule table: " . $stmt_drop_room_table->error;
            }
            $stmt_drop_room_table->close();
        }
    }

    echo "Schedule deleted successfully.";
    header("Location: report_comlab.php");
    exit();
}

// Fetch schedules based on filter criteria
$sql = "
    SELECT tbl_room_schedstatus.room_sched_code, tbl_room_schedstatus.semester, tbl_room_schedstatus.dept_code, 
           tbl_room_schedstatus.ay_code, tbl_rsched.room_code, tbl_rsched.room_type
    FROM tbl_room_schedstatus 
    INNER JOIN tbl_rsched
    ON tbl_room_schedstatus.room_sched_code = tbl_rsched.room_sched_code 
    WHERE tbl_room_schedstatus.status IN ('completed', 'public', 'private', 'draft') 
    AND tbl_room_schedstatus.ay_code = ?
    AND tbl_room_schedstatus.semester = ?
    AND tbl_rsched.room_code COLLATE utf8mb4_general_ci LIKE ?
  ";

// Dynamically modify query based on user type
if ($user_type === 'CCL Head') {
    $sql .= " AND tbl_rsched.room_type = 'Computer Laboratory'";
}
$search_room = isset($_POST['search_room']) ? '%' . $_POST['search_room'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $selected_ay_code, $semester, $search_room);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $semester = $_GET['semester'];

    // Fetch the room schedule code
    $sql = "SELECT * FROM tbl_rsched WHERE room_code = ? AND room_type = 'Computer Laboratory' AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $room_id, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $room_sched_code = $row['room_sched_code'];
        echo fetchScheduleForRoom($room_sched_code, $ay_code, $semester, $room_id);
    } else {
        echo "<p>No schedule found for this Classroooooom.</p>";
    }

    $stmt->close();
    $conn->close();
    exit;
}


function fetchScheduleForRoom($room_sched_code, $ay_code, $semester,$room_id)
{
    global $conn;



    // Fetch room information including department and academic year details
    $sql_fetch_room_info = "
    SELECT r.room_code, r.ay_code, r.dept_code, d.dept_name, a.ay_name, ro.room_in_charge, si.*
    FROM tbl_rsched r
    INNER JOIN tbl_department d ON r.dept_code = d.dept_code
    INNER JOIN tbl_ay AS a ON r.ay_code = a.ay_code
    INNER JOIN tbl_room AS ro ON r.room_code = ro.room_code
    INNER JOIN tbl_signatory AS si ON d.dept_code = si.dept_code
    WHERE r.room_sched_code = ? 
      AND r.ay_code = ? 
      AND si.user_type = ?";

    $stmt_room_info = $conn->prepare($sql_fetch_room_info);
    $stmt_room_info->bind_param("sss", $room_sched_code, $ay_code,$_SESSION['user_type']);
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
    $recommending = $row_room_info['recommending'];
    $approved = $row_room_info['approved'];
    $reviewed = $row_room_info['reviewed'];
    $position_approved = $row_room_info['position_approved'];
    $position_recommending = $row_room_info['position_recommending'];
    $position_reviewed = $row_room_info['position_reviewed'];

    // Fetch the Department Secretary's name from tbl_prof_acc
    $sql_fetch_dept_sec = "
    SELECT first_name, middle_initial, last_name, suffix 
    FROM tbl_prof_acc 
    WHERE dept_code = ? 
    AND user_type = 'Department Secretary'";

    $stmt_dept_sec = $conn->prepare($sql_fetch_dept_sec);
    $stmt_dept_sec->bind_param("s", $dept_code);
    $stmt_dept_sec->execute();
    $result_dept_sec = $stmt_dept_sec->get_result();

    $dept_sec_name = '';
    if ($result_dept_sec->num_rows > 0) {
        $row_dept_sec = $result_dept_sec->fetch_assoc();
        $dept_sec_name = $row_dept_sec['first_name'] . ' ' . $row_dept_sec['middle_initial'] . '. ' . $row_dept_sec['last_name'] . ' ' . $row_dept_sec['suffix'];
    }


    $fetch_info_query_col = "SELECT * FROM tbl_prof_acc WHERE user_type = 'CCL Head'";
    $result_col = $conn->query($fetch_info_query_col);
    
    if ($result_col->num_rows > 0) {
        $row_col = $result_col->fetch_assoc();
        $ccl_college_code = $row_col['college_code'];
        $dept_sec_name = $row_col['first_name'] . ' ' . $row_col['middle_initial'] . '. ' . $row_col['last_name'] . ' ' . $row_col['suffix'];

        // $ccl_dept_code = $row_col['dept_code'];
    
    }


    // Sanitize the table name to ensure safe usage
    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$ccl_college_code}_{$ay_code}");

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

            // Collect the necessary schedule information
            $schedule_data[$day][] = [
                'time_start' => $row_schedule['time_start'],
                'time_end' => $row_schedule['time_end'],
                'course_code' => $row_schedule['course_code'],
                'section_code' => $row_schedule['section_code'],  // Retrieved from tbl_secschedlist
                'prof_name' => $row_schedule['prof_name'],
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

    $html = '
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
        <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">
            CAVITE STATE UNIVERSITY
        </p>
        <p style="text-align: center; font-size: 8px; font-weight: bold; margin: 0; font-family: "Century Gothic", Arial, sans-serif;">
            Don Severino de las Alas Campus
        </p>
        <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: "Century Gothic", Arial, sans-serif;">
            Indang, Cavite
        </p>
        <p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">
            COLLEGE OF ENGINEERING AND INFORMATION TECHNOLOGY
        </p>
        <p style="text-align: center; font-size: 8px; margin-bottom: 10px; font-family: Arial, sans-serif;">' . htmlspecialchars($dept_name) . '</p>
        <p style="text-align: center; font-size: 11px; line-height: 0.5; font-weight: bold;">ROOM UTILIZATION FORM</p>
        <p style="text-align: center; font-size: 10px; line-height: 0.5;">' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . ' </p>
            <div style="position: relative; font-size: 10px; line-height: 1.5;">
                <p style="margin: 0; text-align: left;">Room No.: <strong>' . htmlspecialchars(strtoupper($room_code)) . '</strong></p>
                <p style="margin: 0; text-align: left;">Room In-Charge: <strong>' . htmlspecialchars(strtoupper($room_in_charge)) . '</strong></p>
                <p style="margin: 0; position: absolute; right: 0; top: 15px;">Date: <strong>' . $currentDate . '</strong></p>
            </div>

    </div>
</div>

';

    $html .= '<div class="schedule-table-container" style="width: 100%; display: flex; justify-content: center; margin: 0 auto;">'; // Adjusted font size
    $html .= '<table class="table schedule-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; overflow-x: auto; padding: 3px; " data-room="' . htmlspecialchars($room_code) . '">';
    $html .= '<thead>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="7" style="position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style=" position: absolute; top: -9999px; left: -9999px; border: none;">Republic of the Philippines</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style=" position: absolute; top: -9999px; left: -9999px; border: none;">Cavite State University</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="5" style=" position: absolute; top: -9999px; left: -9999px; border: none;">Don Severino de las Alas Campus</th>
                <th style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style=" position: absolute; top: -9999px; left: -9999px; border: none;">Indang, Cavite</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="7" style="position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="5" style=" position: absolute; top: -9999px; left: -9999px; border: none;">COLLEGE OF ENGINEERING AND INFORMATION TECHNOLOGY</th>
                <th style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style=" position: absolute; top: -9999px; left: -9999px; border: none;">' . htmlspecialchars($dept_name) . '</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="7" style="position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none;">ROOM UTILIZATION FORM</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>
                <th colspan="3" style="position: absolute; top: -9999px; left: -9999px; border: none;">' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . '</th>
                <th colspan="2" style=" position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="7" style="position: absolute; top: -9999px; left: -9999px; border: none;"></th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="7" style="position: absolute; top: -9999px; left: -9999px; border: none;">Room No.: ' . htmlspecialchars(strtoupper($room_code)) . '</th>';
    $html .= '</tr>';
    $html .= '<tr class="noExport">';
    $html .= '<th colspan="4" style="position: absolute; top: -9999px; left: -9999px; border: none;">Room-In-Charge: ' . htmlspecialchars(strtoupper($room_in_charge)) . '</th>
                <th colspan="2" style="position: absolute; top: -9999px; left: -9999px; border: none;">Date: </th>
                <th style="position: absolute; top: -9999px; left: -9999px; border: none;">Date: <strong>' . $currentDate . '</strong></th>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<th style="font-size: 7px; width: 20%; text-align: center; padding: 3px;">Time</th>';

    // Define column headers for days
    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 10%; text-align: center; font-size: 7px; padding: 3px;">' . $day_name . '</th>';
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
                $html .= '<td' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') . '>' . $cell_content . '</td>';
            }
        }

        $html .= '</tr>';
    }

    $html .= '<tr>';
    $html .= '<th colspan="3" style=" text-align: center; vertical-align: middle; font-size: 10px; padding: 3px;">Course Code</th>';
    $html .= '<th colspan="4" style=" text-align: center; vertical-align: middle; font-size: 10px; padding: 3px;">Course Title</th>';
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
        $html .= '<td colspan="3" style="text-align: center; padding: 5px; font-size: 8px; padding: 3px">' . htmlspecialchars($course_code) . '</td>'; // Course code
        $html .= '<td colspan="4" style="text-align: center; padding: 5px; font-size: 8px; padding: 3px">' . htmlspecialchars($course_name) . '</td>'; // Course name
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

    $html .= '<div class="signature-section" style="margin-top: 40px; font-size: 12px; width: 90%; ">';
    $html .= '    <div style="width: 45%; float: left;">';
    $html .= '        <p style="line-height: 0.5; ">Prepared by:</p><br>';  // Adjusted line-height
    $html .= '        <p style="line-height: 1.5; text-align: left; margin: 0;"><strong>' . strtoupper(string: htmlspecialchars($dept_sec_name)) . '</strong></p>';  // Reduced line-height and margin
    $html .= '        <p style="line-height: 0.8; text-align: left; margin: 2px auto;"><span>CCL Head</span></p>';  // Adjusted line-height and margin
    $html .= '    </div>';

    $html .= '<div class="signature-section" style="margin-top: 40px; font-size: 12px; width: 90%; ">';
    $html .= '    <div style="width: 35%; float: right;">';
    $html .= '        <p style="line-height: 0.5;">Recommending Approval:</p><br>';  // Adjusted line-height
    $html .= '        <p style="line-height: 1.5; text-align: left; margin: 0;"><strong>' . strtoupper(htmlspecialchars($recommending)) . '</strong></p>';  // Reduced line-height and margin
    $html .= '        <p style="line-height: 0.8; text-align: left; margin: 2px auto;"><span>' . htmlspecialchars($position_recommending) . '</span></p>';  // Adjusted line-height and margin
    $html .= '    </div>';
    $html .= '</div>';

    $html .= '<div class="signature-section" style="margin-top: 40px; font-size: 12px; width: 90%; ">';
    $html .= '    <div style="width: 45%; float: left; margin-top: 40px;">';
    $html .= '        <p style="line-height: 0.5;">Reviewed by:</p><br>';  // Adjusted line-height
    $html .= '        <p style="line-height: 1.5; text-align: left; margin: 0;"><strong>' . strtoupper(htmlspecialchars($reviewed)) . '</strong></p>';  // Reduced line-height and margin
    $html .= '        <p style="line-height: 0.8; text-align: left; margin: 2px auto;"><span>' . htmlspecialchars($position_reviewed) . '</span></p>';  // Adjusted line-height and margin
    $html .= '    </div>';
    $html .= '</div>';

    $html .= '<div class="signature-section" style="margin-top: 40px; font-size: 12px; width: 90%; ">';
    $html .= '    <div style="width: 35%; float: right; margin-top: 40px;">';
    $html .= '        <p style="line-height: .5;">Approved by:</p><br>';  // Adjusted line-height
    $html .= '        <p style="line-height: 1.5; text-align: left; margin: 0;"><strong>' . strtoupper(htmlspecialchars($approved)) . '</strong></p>';  // Reduced line-height and margin
    $html .= '        <p style="line-height: 0.8; text-align: left; margin: 2px auto;"><span>' . htmlspecialchars($position_approved) . '</span></p>';  // Adjusted line-height and margin
    $html .= '    </div>';
    $html .= '</div>';







    $html .= '<div style="clear: both;"></div>';



    return $html;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Classroom Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>


    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Gothic+A1&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>

    <link rel="stylesheet" href="../../../css/department_secretary/report/report_class.css">
    <link rel="stylesheet" href="../../../css/department_secretary/navbar.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>

    <h2 class="title"><i class="fa-solid fa-file-alt"></i> REPORT</h2>

    <div class="container mt-5">

        <section class="content">
            <div class="row mb-4">
                <div class="col-md-3">
                </div>
                <div class="col-md-9">
                    <div class="search-bar-container">
                        <form method="POST" action="report_comlab.php" class="row">
                            <div class="col-md-3">
                                <select class="form-control" id="search_ay_name" name="search_ay_name">
                                    <?php foreach ($ay_options as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option['ay_name']); ?>" <?php echo ($option['ay_name'] == $ay_name) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($option['ay_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="search_semester" name="search_semester">
                                    <option value="1st Semester" <?php echo ($semester == '1st Semester') ? 'selected' : ''; ?>>1st Semester</option>
                                    <option value="2nd Semester" <?php echo ($semester == '2nd Semester') ? 'selected' : ''; ?>>2nd Semester</option>
                                    <!-- <option value="Summer" <?php echo ($semester == 'Summer') ? 'selected' : ''; ?>>Summer</option> -->
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search_room"
                                    value="<?php echo isset($_POST['search_room']) ? htmlspecialchars($_POST['search_room']) : ''; ?>"
                                    placeholder="Search" autocomplete="off">
                            </div>
                            <div class="col-md-3">
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
                            <tr>
                                <th></th>
                                <th class="equal-width">Room</th>
                                <th class="equal-width">Room Type</th>
                                <th class="equal-width">Semester</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-room-id="<?php echo htmlspecialchars($row['room_code']); ?>">
                                        <td>
                                            <div style="display: flex;">

                                                <?php
                                                // Fetch the status for the current schedule before rendering the form
                                                $room_sched_code = htmlspecialchars($row['room_sched_code']);
                                                $ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
                                                $semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
                                                $dept_code = $_SESSION['dept_code'];

                                                // Query the database to get the current status
                                                $checkQuery = "SELECT status FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ?";
                                                $stmt = $conn->prepare($checkQuery);
                                                $stmt->bind_param('sss', $room_sched_code, $semester, $ay_code);
                                                $stmt->execute();
                                                $statusResult = $stmt->get_result();
                                                $statusRow = $statusResult->fetch_assoc();

                                                // Set $currentStatus based on the query result
                                                if ($statusRow) {
                                                    $currentStatus = $statusRow['status'];
                                                } else {
                                                    // Handle case if no status is found (optional)
                                                    $currentStatus = 'private'; // Default status if no row found, adjust based on your logic
                                                }
                                                $stmt->close();
                                                ?>

                                                <!-- Now, the form with the correct button -->
                                                <form method="POST" action="report_comlab.php">
                                                    <input type="hidden" name="room_code"
                                                        value="<?php echo htmlspecialchars($row['room_code']); ?>">
                                                    <input type="hidden" name="semester"
                                                        value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                    <input type="hidden" name="ay_code"
                                                        value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                    <input type="hidden" name="room_sched_code"
                                                        value="<?php echo htmlspecialchars($row['room_sched_code']); ?>">


                                                </form>
                                            </div>
                                        <td>
                                            <?php echo htmlspecialchars($row['room_code']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                        <td>
                                            <!-- Edit Form -->
                                            <form method="POST" action="report_comlab.php" style="display:inline;">
                                                <input type="hidden" name="room_code"
                                                    value="<?php echo htmlspecialchars($row['room_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="room_sched_code"
                                                    value="<?php echo htmlspecialchars($row['room_sched_code']); ?>">
                                                <!-- <button type="submit" name="edit" class="edit-btn">
                                                    <i class="far fa-pencil-alt"></i>
                                                </button> -->
                                            </form>
                                            <!-- Report Form -->
                                            <form method="POST" action="report_comlab.php" style="display:inline;">
                                                <button type="button" name="report" id="report" class="report-btn"
                                                    value="report" data-toggle="modal" data-target="#scheduleModal"
                                                    data-section-code="<?php echo htmlspecialchars($row['room_code']); ?>"
                                                    data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                    data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>">

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

        <!-- Combined Schedule and Prepared By Modal -->
        <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">Schedule Details</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <!-- Schedule content will be loaded here -->
                        <div id="scheduleContent"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" onclick="submitPreparedBy()">Submit</button>
                        <button class="btn" style="background-color: #FD7238; color:#ffffff;" id="SchedulePDF">PDF</button>
                        <button class="btn" style="background-color: #FD7238; color:#ffffff;" onclick="fnExportToExcel('xlsx', 'MySchedule')">Excel</button>
                    </div>
                </div>
            </div>
        </div>

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

            function fnExportToExcel(fileExtension, filename) {
                // Get the section ID from the data-section attribute of the table
                var roomCode = document.querySelector(".schedule-table").dataset.room;

                // Default to a generic name if roomCode is not found
                var sheetName = roomCode || "Schedule";

                // Select the table
                var table = document.querySelector(".schedule-table");

                // Check if the table exists
                if (!table) {
                    console.error("Table not found.");
                    return;
                }

                // Convert the table to a worksheet
                var ws = XLSX.utils.table_to_sheet(table);

                // Create a new workbook
                var wb = XLSX.utils.book_new();

                // Append the worksheet to the workbook with the dynamically generated sheet name
                XLSX.utils.book_append_sheet(wb, ws, sheetName);

                // Create the filename with the roomCode or fallback to a generic name
                var fileName = "Report for " + (roomCode || "Unknown") + ".xlsx";

                // Write the workbook to a file with the dynamically created file name
                XLSX.writeFile(wb, fileName);
            }


            $(document).ready(function () {
                // Handle report button click to open the professor schedule modal
                $('#scheduleTable').on('click', '.report-btn', function () {
                    var button = $(this);  // Get the clicked report button

                    // Retrieve the data attributes for professor schedule
                    var roomId = button.data('room-id');
                    var semester = button.data('semester');
                    var ayCode = button.data('ay-code');

                    // Show the schedule modal
                    $('#scheduleModal').modal('show');
                    $('#scheduleContent').html('<p>Loading schedule details...</p>');  // Add loading message

                    // Fetch the professor schedule details via AJAX
                    $.ajax({
                        url: 'report_comlab.php',
                        type: 'GET',
                        data: {
                            action: 'fetch_room_schedule',
                            room_id: roomId,
                            semester: semester,
                            ay_code: ayCode
                        },
                        success: function (response) {
                            console.log("Response: ", response);
                            $('#scheduleContent').html(response);  // Load the fetched schedule into the modal
                        },
                        error: function () {
                            console.error('Failed to fetch schedule: ' + roomId);
                            $('#scheduleContent').html('<p>Error loading schedule details.</p>');  // Handle errors
                        }
                    });
                });

                // Ensure modal cleanup after it's closed
                $('#scheduleModal').on('hidden.bs.modal', function () {
                    // Reset modal content and ensure backdrop is removed
                    $('#scheduleContent').html('');  // Clear the content
                    $('body').removeClass('modal-open');  // Remove the 'modal-open' class from body
                    $('.modal-backdrop').remove();  // Remove any leftover backdrop

                    // Dispose of the modal to ensure no leftovers
                    $('#scheduleModal').modal('dispose');  // Properly remove modal instance
                });
            });

            $(document).ready(function () {
                filterClassroomBySchedule();

                $('#scheduleTable').on('click', 'tr', function (event) {
                    var roomId = $(this).data('room-id');
                    var semester = $('#search_semester').val();

                    if (roomId) {
                        $('#scheduleModal').modal('show'); // Show the modal immediately
                        $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                        $.ajax({
                            url: 'report_comlab.php',
                            type: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                room_id: roomId,
                                semester: semester
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
                    var rowsVisible = false;

                    $('#scheduleTable tbody tr').each(function () {
                        var row = $(this);
                        var roomId = row.data('room-id');

                        $.ajax({
                            url: 'report_comlab.php',
                            type: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                room_id: roomId,
                                semester: selectedSemester,
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


                
            });

        </script>
</body>

</html>