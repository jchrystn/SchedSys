<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../login/login.php");
    exit();
}

$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown';
$user_college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';


if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}

// Query to fetch the college name based on the college code
$sql = "SELECT college_name FROM tbl_college WHERE college_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_college_code); // 's' is for string parameter
$stmt->execute();
$result_college = $stmt->get_result();

// Fetch the college name if exists
if ($result_college->num_rows > 0) {
    $row_college = $result_college->fetch_assoc();
    $user_college_name = $row_college['college_name'];
} else {
    $user_college_name = "College Not Found"; // Fallback if the college is not found
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



// Handle POST requests for Edit, Delete, and Fetch Schedule
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
        $_SESSION['prof_sched_code'] = $_POST['prof_sched_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $_SESSION['prof_code'] = $_POST['prof_code'];
        header("Location: ../create_sched/plotSchedule.php");
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['prof_id'])) {
    $prof_id = $_GET['prof_id'];
    $dept_code = $_SESSION['dept_code'];
    $active_ay_code = $_SESSION['ay_code'];

    $sql = "SELECT * FROM tbl_psched WHERE prof_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $prof_id, $dept_code, $active_ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $prof_sched_code = $row['prof_sched_code'];
        echo fetchScheduleForProf($prof_sched_code, $active_ay_code, $active_semester);
    } else {
        echo "<p>No schedule found for this Professor.</p>";
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
<p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">'
        . htmlspecialchars($user_college_name) .
        '</p>
        <p style="text-align: center; font-size: 8px; margin-bottom: 10px; font-family: Arial, sans-serif;">' . htmlspecialchars($dept_name) . '</p>
        <p style="text-align: center; font-size: 11px; margin: 0; font-weight: bold;">FACULTY CLASS SCHEDULE</p>
        <p style="text-align: center; font-size: 10px; margin-bottom: 10px;">' . htmlspecialchars($semester) . ', SY ' . htmlspecialchars($ay_name) . ' </p>
        <!-- Professor Details and Date -->
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 8px;">
            <p style="margin: 0;">Name: <strong>' . htmlspecialchars(strtoupper($prof_full_name)) . '</strong></p>
            <p style="margin: 0;">Date: <strong>' . $currentDate . '</strong></p>
        </div>
        <!-- Flexbox Section for Preparations and Contact Hours -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 8px;">
            <p style="margin: 0;">No. of Preparation/s: <strong>' . htmlspecialchars(strtoupper($no_prep_hrs)) . '</strong></p>
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
    $html .= '<th style="font-size: 7px; width: 20%; text-align: center; padding: 3px;">Time</th>';

    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 10%; text-align: center; font-size: 7px; padding: 3px;">' . $day_name . '</th>';
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
    $html .= '<th colspan = "2" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">Course Code</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">Section Code</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">Lec Hours</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">Lab Hours</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">Room</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 3px;">No. of Students</th>';
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
                $html .= '<td colspan = "2" rowspan="' . $rowspan . '" style="vertical-align: middle; font-size: 8px; text-align: center; padding: 3px;">' . htmlspecialchars($course_code) . '</td>';
                $first_row = false; // Set flag to false after first row
            }

            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_code) . '</td>'; // Section code
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_data['lec_count']) . '</td>'; // Lecture hours for this section
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($section_data['lab_count']) . '</td>'; // Lab hours for this section
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($rooms_str) . '</td>'; // Rooms for this section
            $html .= '<td style="padding: 3px; font-size: 8px;">' . htmlspecialchars($no_students) . '</td>'; // Rooms for this section
            $html .= '</tr>';
        }
    }

    // Add a footer row for the total lecture and lab hours
    $html .= '<tr>';
    $html .= '<td colspan="3"></td>';
    $html .= '<td style="text-align: right; font-size: 8px; font-weight: bold; padding: 3px;">Total:</td>';
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


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Instructor Report</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>


    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Gothic+A1&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>

    <link rel="stylesheet" href="../../../css/department_secretary/report/report_prof.css">
    <link rel="stylesheet" href="../../../css/department_secretary/navbar.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>

    <h2 class="title"><i class="fa-solid fa-file-alt"></i> REPORT</h2>

    <div class="container mt-5">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="section-tab" href="../library/lib_section.php" aria-controls="Section"
                    aria-selected="true">Section</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="../library/lib_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="professor-tab" href="../library/lib_professor.php" aria-controls="professor"
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
            <?php if ($user_type =='Department Secretary'): ?>
            <li class="nav-item">
                <a class="nav-link" id="vacant-room-tab" href="/SchedSys3/php/viewschedules/data_schedule_vacant.php" aria-controls="vacant-room" aria-selected="false">Vacant Room</a>
            </li>
            <?php endif; ?>
        </ul>


        <section class="content">
            <div class="row mb-4">
                <div class="col-md-3">
                </div>
                <div class="col-md-9">
                    <div class="search-bar-container">
                        <form method="POST" action="report_prof.php" class="row">
                            <div class="col-md-3"></div>
                            <div class="col-md-3"></div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search_prof"
                                    value="<?php echo isset($_POST['search_prof']) ? htmlspecialchars($_POST['search_prof']) : ''; ?>"
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
                        <thead class="bg-light">
                            <tr>
                                <th></th>
                                <th class="equal-width">Instructor Code</th>
                                <th class="equal-width">Instructor Name</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php

                            $search_prof = isset($_POST['search_prof']) ? '%' . $_POST['search_prof'] . '%' : '%';

                            $sql = "
                                SELECT tbl_prof_schedstatus.prof_sched_code, tbl_prof_schedstatus.semester, tbl_prof_schedstatus.dept_code, 
                                       tbl_prof_schedstatus.ay_code, tbl_psched.prof_code, tbl_prof.prof_name 
                                FROM tbl_prof_schedstatus
                                INNER JOIN tbl_psched
                                ON tbl_prof_schedstatus.prof_sched_code = tbl_psched.prof_sched_code 
                                INNER JOIN tbl_prof
                                ON tbl_psched.prof_code = tbl_prof.prof_code
                                WHERE tbl_prof_schedstatus.status IN ('completed', 'public', 'private', 'draft') 
                                AND tbl_prof_schedstatus.ay_code = ? 
                                AND tbl_prof_schedstatus.semester = ? 
                                AND tbl_prof.prof_name COLLATE utf8mb4_general_ci LIKE ? 
                                AND tbl_prof_schedstatus.dept_code = ?";

                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param('ssss', $active_ay_code, $active_semester, $search_prof, $dept_code);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-prof-id="<?php echo htmlspecialchars($row['prof_code']); ?>">
                                        <td>
                                            <div style="display: flex;">

                                                <?php
                                                // Fetch the status for the current schedule before rendering the form
                                                $prof_sched_code = htmlspecialchars($row['prof_sched_code']);
                                                $ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
                                                $semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
                                                $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';

                                                // Query the database to get the current status
                                                $checkQuery = "SELECT status FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                                                $stmt = $conn->prepare($checkQuery);
                                                $stmt->bind_param('ssss', $prof_sched_code, $dept_code, $semester, $ay_code);
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
                                                <form method="POST" action="report_prof.php">
                                                    <input type="hidden" name="prof_code"
                                                        value="<?php echo htmlspecialchars($row['prof_code']); ?>">
                                                    <input type="hidden" name="semester"
                                                        value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                    <input type="hidden" name="ay_code"
                                                        value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                    <input type="hidden" name="prof_sched_code"
                                                        value="<?php echo htmlspecialchars($row['prof_sched_code']); ?>">

                                                </form>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['prof_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['prof_name']); ?></td>
                                        <td>
                                            <!-- Edit Form -->
                                            <form method="POST" action="report_prof.php" style="display:inline;">
                                                <input type="hidden" name="prof_code"
                                                    value="<?php echo htmlspecialchars($row['prof_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="prof_sched_code"
                                                    value="<?php echo htmlspecialchars($row['prof_sched_code']); ?>">
                                            </form>
                                            <!-- Report Form -->
                                            <form method="POST" action="report_prof.php" style="display:inline;">
                                                <button type="button" name="report" id="report" class="report-btn"
                                                    value="report" data-toggle="modal" data-target="#scheduleModal"
                                                    data-section-code="<?php echo htmlspecialchars($row['prof_code']); ?>"
                                                    data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                    data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>">

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

        <!-- Combined Schedule and Prepared By Modal -->
        <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">Schedule Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
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

                worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
                let row = worksheet.getRow(rowIndex);
                row.getCell(1).value = "Name: " + profName;
                row.getCell(1).alignment = { horizontal: "left", vertical: "middle" };

                row.getCell(7).value = "Date: " + currentDate;
                row.getCell(7).alignment = { horizontal: "right", vertical: "middle" };

                rowIndex++;

                worksheet.mergeCells(rowIndex, 1, rowIndex, 6);
                row = worksheet.getRow(rowIndex);
                row.getCell(1).value = "No. of Preparations: " + no_prep_hrs;
                row.getCell(1).alignment = { horizontal: "left", vertical: "middle" };

                row.getCell(7).value = "Total no. of contact hours per week: " + teaching_hrs;
                row.getCell(7).alignment = { horizontal: "right", vertical: "middle" };

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


                const fileName = `Report for ${profCode || "Unknown"}.xlsx`;
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
                    var profCode = button.data('prof-code');
                    var semester = button.data('semester');
                    var ayCode = button.data('ay-code');

                    // Show the schedule modal
                    $('#scheduleModal').modal('show');
                    $('#scheduleContent').html('<p>Loading professor schedule details...</p>');  // Add loading message

                    // Fetch the professor schedule details via AJAX
                    $.ajax({
                        url: 'report_prof.php',
                        type: 'GET',
                        data: {
                            action: 'fetch_prof_schedule',
                            prof_code: profCode,
                            semester: semester,
                            ay_code: ayCode
                        },
                        success: function (response) {
                            console.log("Response: ", response);
                            $('#scheduleContent').html(response);  // Load the fetched schedule into the modal
                        },
                        error: function () {
                            console.error('Failed to fetch schedule for professor code: ' + profCode);
                            $('#scheduleContent').html('<p>Error loading professor schedule details.</p>');  // Handle errors
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
                // Check schedules for each prof when the page loads
                filterProfBySchedule();

                // Attach click event to rows in the table to show the schedule modal
                $('#scheduleTable').on('click', 'tr', function () {
                    var profId = $(this).data('prof-id'); // Get professor ID from the row's data attribute
                    var semester = $('#search_semester').val(); // Get the selected semester value

                    // Ensure that a valid profId is present
                    if (profId) {
                        // Load schedule content into the modal
                        $('#scheduleModal').modal('show'); // Show the modal immediately
                        $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                        // Make an AJAX request to fetch the schedule
                        $.ajax({
                            url: 'report_prof.php', // Your backend script
                            method: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                prof_id: profId,
                                semester: semester
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
                    var rowsVisible = false;

                    $('#scheduleTable tbody tr').each(function () {
                        var row = $(this);
                        var profId = row.data('prof-id'); // Get professor ID from the row's data attribute

                        $.ajax({
                            url: 'report_prof.php',
                            method: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                prof_id: profId,
                                semester: selectedSemester,
                                dept_code: $('#dept_code').val() // Include dept_code if necessary
                            },
                            success: function (response) {
                                if (response.trim().includes("No Available Professor Schedule")) {
                                    row.hide(); // Hide row if no schedule is available
                                } else {
                                    row.show(); // Show row if a schedule exists
                                    rowsVisible = true;
                                }
                            },
                            error: function () {
                                console.error('Failed to fetch schedule for professor ID: ' + profId);
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