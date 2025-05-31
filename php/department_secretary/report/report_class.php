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

// Dynamically modify query based on user type
if ($user_type === 'CCL Head') {
    $sql .= " AND tbl_rsched.room_type = 'Computer Laboratory'";
} elseif ($user_type === 'Department Secretary') {
    $sql .= " AND tbl_rsched.room_type IN ('Lecture', 'Laboratory')";
}

$search_room = isset($_POST['search_room']) ? '%' . $_POST['search_room'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $active_ay_code, $active_semester, $search_room, $dept_code);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $dept_code = $_SESSION['dept_code'];
    $active_ay_code = $_SESSION['ay_code'];

    // Fetch the room schedule code
    $sql = "SELECT * FROM tbl_rsched WHERE room_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $room_id, $dept_code, $active_ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $room_sched_code = $row['room_sched_code'];
        echo fetchScheduleForRoom($room_sched_code, $active_ay_code, $active_semester, $room_id);
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
        <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: \'Times New Roman\', Arial, sans-serif;">
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
                <p style="margin: 0; position: absolute; right: 0; top: 15px;">Date: <strong>' . $currentDate . '</strong></p>
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

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Classroom Report</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>



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

    <h2 class="title"><i class="fa-solid fa-file-alt"></i> SCHEDULES</h2>

    <div class="container mt-5">
        <?php if ($_SESSION['user_type'] == 'Department Secretary'): ?>
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link" id="section-tab" href="../library/lib_section.php" aria-controls="Section"
                        aria-selected="true">Section</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" id="classroom-tab" href="../library/lib_classroom.php"
                        aria-controls="classroom" aria-selected="false">Classroom</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="professor-tab" href="../library/lib_professor.php" aria-controls="professor"
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

        <?php endif; ?>

        <section class="content">
            <div class="row mb-4">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <div class="search-bar-container">
                        <form method="POST" action="report_class.php" class="row">
                            <div class="col-md-3"></div>
                            <div class="col-md-3"></div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search_room"
                                    value="<?php echo isset($_POST['search_room']) ? htmlspecialchars($_POST['search_room']) : ''; ?>"
                                    placeholder="Search by Room Type" autocomplete="off">
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
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "
                                SELECT tbl_room_schedstatus.room_sched_code, tbl_room_schedstatus.semester, tbl_room_schedstatus.dept_code, 
                                    tbl_room_schedstatus.ay_code, tbl_rsched.room_code, tbl_rsched.room_type
                                FROM tbl_room_schedstatus
                                INNER JOIN tbl_rsched
                                ON tbl_room_schedstatus.room_sched_code = tbl_rsched.room_sched_code
                                WHERE tbl_room_schedstatus.status IN ('draft', 'completed','public', 'private') 
                                AND tbl_room_schedstatus.ay_code = ?
                                AND tbl_room_schedstatus.semester = ?
                                AND tbl_rsched.room_type COLLATE utf8mb4_general_ci LIKE ?
                                AND tbl_room_schedstatus.dept_code = ?";

                            if ($user_type === 'CCL Head') {
                                $sql .= " AND tbl_rsched.room_type = 'Computer Laboratory'";
                            } elseif ($user_type === 'Department Secretary') {
                                $sql .= " AND tbl_rsched.room_type IN ('Lecture', 'Laboratory')";
                            }

                            $search_room = isset($_POST['search_room']) ? '%' . $_POST['search_room'] . '%' : '%';

                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param('ssss', $active_ay_code, $active_semester, $search_room, $dept_code);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0):
                                ?>

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
                                                <form method="POST" action="report_class.php">
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
                                        <td>
                                            <!-- Edit Form -->
                                            <form method="POST" action="report_class.php" style="display:inline;">
                                                <input type="hidden" name="room_code"
                                                    value="<?php echo htmlspecialchars($row['room_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="room_sched_code"
                                                    value="<?php echo htmlspecialchars($row['room_sched_code']); ?>">

                                            </form>
                                            <!-- Report Form -->
                                            <form method="POST" action="report_class.php" style="display:inline;">
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
                                    <td colspan="4" class="text-center">No Records Found3</td>
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
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>
                    <div class="modal-body">
                        <!-- Schedule content will be loaded here -->
                        <div id="scheduleContent"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn" style="background-color: #FD7238; color:#ffffff;"
                            id="SchedulePDF">PDF</button>
                        <button class="btn" style="background-color: #FD7238; color:#ffffff;"
                            onclick="fnExportToExcel('xlsx', 'MySchedule')">Excel</button>
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

                // Add "Date" in the same row, aligned to the right
                row.getCell(7).value = "Date: " + currentDate;
                row.getCell(7).alignment = { horizontal: "right", vertical: "middle" };

                rowIndex++; // Move to the next row

                try {
                    // Fetch image and convert it to Base64
                    const imageUrl = "http://localhost/SchedSys3/images/cvsu_logo.png"; // Update with your actual URL
                    const imageBase64 = await fetch(imageUrl)
                        .then(res => res.blob())
                        .then(blob => new Promise((resolve, reject) => {
                            const reader = new FileReader();
                            reader.onloadend = () => resolve(reader.result.split(",")[1]);
                            reader.onerror = reject;
                            reader.readAsDataURL(blob);
                        }));

                    // Add an image to the worksheet
                    const imageId = workbook.addImage({
                        base64: imageBase64,
                        extension: "png"
                    });

                    worksheet.addImage(imageId, {
                        tl: { col: 1.9, row: 1 }, // Position at D2 (0-based index)
                        ext: { width: 60, height: 50 }
                    });
                } catch (error) {
                    console.error("Error loading image:", error);
                }

                // Convert table rows to worksheet with auto column width
                let columnWidths = [];
                Array.from(table.rows).forEach((row, rowIndex) => {
                    let excelRow = worksheet.addRow([]);
                    Array.from(row.cells).forEach((cell, colIndex) => {
                        let cellValue = cell.innerText.replace(/<br\s*\/?>/g, "\n"); // Preserve line breaks
                        let excelCell = excelRow.getCell(colIndex + 1);
                        excelCell.value = cellValue;
                        excelCell.alignment = { horizontal: "center", vertical: "middle", wrapText: true };

                        // Get the cell color from the `data-cell-color` attribute
                        let cellColor = cell.getAttribute("data-cell-color");
                        if (cellColor && /^#([0-9A-F]{3}){1,2}$/i.test(cellColor)) {
                            // Convert HEX color to ExcelJS ARGB format
                            let hex = cellColor.replace("#", "");
                            if (hex.length === 3) {
                                hex = hex.split("").map(c => c + c).join(""); // Convert #RGB to #RRGGBB
                            }
                            excelCell.fill = {
                                type: "pattern",
                                pattern: "solid",
                                fgColor: { argb: `FF${hex.toUpperCase()}` } // ExcelJS requires 'FF' + HEX
                            };
                        }

                        columnWidths[colIndex] = Math.max(columnWidths[colIndex] || 10, cellValue.length + 2);
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
                        url: 'report_class.php',
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
                            url: 'report_class.php',
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
                            url: 'report_class.php',
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