<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config.php");

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Admin' && $_SESSION['user_type'] != 'Professor' && $_SESSION['user_type'] != 'Department Chairperson' && $_SESSION['user_type'] != 'Department Secretary') && $_SESSION['user_type'] != 'CCL Head') {
    header("Location: ../login/login.php");
    exit();
}

function formatTime($time)
{
    return date('g:i a', strtotime($time));
}

$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown';
$prof_code = isset($_SESSION['prof_code']) ? $_SESSION['prof_code'] : 'Unknown';
$prof_sched_code = isset($_SESSION['prof_sched_code']) ? $_SESSION['prof_sched_code'] : 'Unknown';
$user_college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$current_user_type = null;
// Get current user's email
$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');

$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
// Handle Academic Year options
$fetch_info_query = "SELECT ay_code, semester, ay_name FROM tbl_ay WHERE college_code = '$user_college_code' AND active = '1'";
$result = $conn->query($fetch_info_query);

$active_ay_code = null;
$active_semester = null;

// Check if query executed successfully and returned rows
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $active_ay_code = $row['ay_code'];
    $active_semester = $row['semester'];
    $active_ay_name = $row['ay_name'];
}

// Set the ay_code and semester based on active values from the query
$ay_code = $active_ay_code;
$semester = $active_semester;
$ay_name = $active_ay_name;

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}





if ($user_type != "Student") {
    $fetch_info_query = "SELECT reg_adviser,college_code,user_type,dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email' AND ay_code = '$ay_code' AND semester = '$semester'";
    $result_reg = $conn->query($fetch_info_query);

    if ($result_reg->num_rows > 0) {
        $row = $result_reg->fetch_assoc();
        $not_reg_adviser = $row['reg_adviser'];
        $user_college_code = $row['college_code'];
        $true_user_type = $row['user_type'];
        $account_dept_code = $row['dept_code'];


        if ($not_reg_adviser == 1) {
            $current_user_type = "Registration Adviser" ?? '';
        } else {
            $current_user_type = null;
        }
    }
} else {
    $fetch_info_query = "SELECT college_code,dept_code FROM tbl_stud_acc WHERE cvsu_email = '$current_user_email' AND ay_code = '$ay_code' AND semester = '$semester'";
    $result_reg = $conn->query($fetch_info_query);

    if ($result_reg->num_rows > 0) {
        $row = $result_reg->fetch_assoc();
        $not_reg_adviser = null;
        $user_college_code = $row['college_code'];
        $true_user_type = null;
        $account_dept_code = $row['dept_code'];
        $current_user_type = null;

    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
        // Retrieve and set session variables
        $_SESSION['prof_sched_code'] = $_POST['prof_sched_code'];
        $_SESSION['prof_name'] = $_POST['prof_name'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $_SESSION['dept_code'] = $_POST['dept_code'];
        $_SESSION['prof_code'] = $_POST['prof_code'];



        // Redirect to `contact_plot.php` with sanitized variables
        header("Location: /SchedSys3/php/department_secretary/input_forms/contact_plot.php?prof_code=" . urlencode($_SESSION['prof_code']) .
            "&prof_name=" . urlencode($_SESSION['prof_name']) .
            "&prof_sched_code=" . urlencode($_SESSION['prof_sched_code']) .
            "&semester=" . urlencode($_SESSION['semester']) .
            "&dept_code=" . urlencode($_SESSION['dept_code']));
        exit();
    }
}




function fetchScheduleForProf($prof_sched_code, $ay_code, $semester, $account_dept_code)
{
    global $conn;

    // Fetch professor info
    $sql_fetch_prof_info = "
    SELECT p.prof_code, p.ay_code, p.dept_code, d.dept_name, pr.prof_name, ps.prep_hrs, ps.teaching_hrs, a.ay_name, si.*, pr.academic_rank
    FROM tbl_psched AS p
    INNER JOIN tbl_department AS d ON p.dept_code = d.dept_code
    INNER JOIN tbl_prof AS pr ON p.prof_code = pr.prof_code
    INNER JOIN tbl_psched_counter AS ps ON p.prof_code = ps.prof_code
    INNER JOIN tbl_ay AS a ON p.ay_code = a.ay_code
    INNER JOIN tbl_signatory AS si ON d.dept_code = si.dept_code
    WHERE p.prof_sched_code = ? 
    AND p.dept_code = ? 
    AND p.ay_code = ?
    AND si.user_type = 'Department Secretary'";

    $stmt_prof_info = $conn->prepare($sql_fetch_prof_info);
    $stmt_prof_info->bind_param("sss", $prof_sched_code, $account_dept_code, $ay_code);
    $stmt_prof_info->execute();
    $result_prof_info = $stmt_prof_info->get_result();

    if (!$result_prof_info || $result_prof_info->num_rows === 0) {
        return '<p>No Availabffle Professor Schedule</p>';
    }


    $row_prof_info = $result_prof_info->fetch_assoc();
    $prof_code = $row_prof_info['prof_code'];
    $dept_code = $row_prof_info['dept_code'];
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
    $academic_rank = $row_prof_info['academic_rank'];

    // Fetch the professor's full name from tbl_prof_acc and tbl_prof using prof_code
    $sql_fetch_prof_name = "
SELECT pa.first_name, pa.middle_initial, pa.last_name, pa.suffix, pa.designation 
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
        $prof_name = trim(
            $row_prof_name['first_name'] . ' '
            . ($row_prof_name['middle_initial'] ? $row_prof_name['middle_initial'] . '. ' : '') // Add middle initial if it exists
            . $row_prof_name['last_name']
            . ($row_prof_name['suffix'] ? ', ' . $row_prof_name['suffix'] : '') // Add suffix if it exists
        );
        $designation = $row_prof_name['designation'];

    } else {
        $prof_name = 'No matching professor found';
    }



    // Sanitize schedule table name based on department and academic year
    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $row_prof_info['dept_code'] . "_" . $ay_code);

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
        $no_students = isset($row_schedule['no_students']) ? $row_schedule['no_students'] : null; // Set default if not available

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

        $schedule_data[$day][] = [
            'time_start' => $row_schedule['time_start'],
            'time_end' => $row_schedule['time_end'],
            'course_code' => isset($row_schedule['course_code']) ? $row_schedule['course_code'] : '',
            'room_code' => isset($row_schedule['room_code']) ? $row_schedule['room_code'] : '',
            'section_code' => isset($row_schedule['section_code']) ? $row_schedule['section_code'] : '',
            'class_type' => isset($row_schedule['class_type']) ? $row_schedule['class_type'] : '',
            'no_students' => $no_students,
            'cell_color' => $cell_color,
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
            // Fetch the specific hours (consultation, research, extension)
            $consultation_hrs = $row_schedule['current_consultation_hrs'];
            $research_hrs = $row_schedule['research_hrs'];
            $extension_hrs = $row_schedule['extension_hrs'];

            // Check for 'Consultation Hours' and format times if present
            if ($row_schedule['consultation_hrs_type'] === 'Consultation Hours') {
                $consultation_start_time = $row_schedule['time_start'];
                $consultation_end_time = $row_schedule['time_end'];
                $consultation_day = $row_schedule['day'];

                // Format start and end times to 12-hour format
                $formatted_start_time = date('g:i A', strtotime($consultation_start_time));
                $formatted_end_time = date('g:i A', strtotime($consultation_end_time));

                // Append consultation time without trailing '-'
                $consultation_entry = htmlspecialchars($consultation_day . ' ' . $formatted_start_time . ' to ' . $formatted_end_time);

                // Append with a separator only if there are multiple entries
                $consultation_loop .= (empty($consultation_loop) ? '' : ' - ') . $consultation_entry;
            } else {
                // Set to null if consultation_hrs_type is not 'Consultation Hours'
                $formatted_start_time = null;
                $formatted_end_time = null;
                $consultation_day = null;
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



    $consultation_hrs = isset($consultation_hrs) ? $consultation_hrs : null;
    $research_hrs = isset($research_hrs) ? $research_hrs : null;
    $extension_hrs = isset($extension_hrs) ? $extension_hrs : null;
    $formatted_start_time = isset($formatted_start_time) ? $formatted_start_time : '';
    $formatted_end_time = isset($formatted_end_time) ? $formatted_end_time : '';
    $consultation_day = isset($consultation_day) ? $consultation_day : '';
    $no_students = isset($no_students) ? $no_students : '';
    $academic_rank = isset($academic_rank) ? $academic_rank : '';

    // If schedule data is available, pass it to generateScheduleTable
    if (!empty($schedule_data)) {
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
function generateScheduleTable($schedule_data, $dept_name, $semester, $prof_code, $prof_name, $no_prep_hrs, $teaching_hrs, $ay_name, $consultation_hrs, $research_hrs, $extension_hrs, $formatted_start_time, $formatted_end_time, $consultation_day, $designation, $recommending, $reviewed, $approved, $prof_full_name, $position_approved, $position_recommending, $position_reviewed, $ay_code, $no_students, $consultation_loop)
{
    global $conn;

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
    <div class="logo-image">
        <img src="/SchedSys3/images/cvsu_logo.png" alt="CVSU Logo">
    </div>
    <!-- Text Section -->
    <div>
        <p style="text-align: center; font-size: 7px; margin: 0; font-family: "Century Gothic", Arial, sans-serif;">
            Republic of the Philippines
        </p>
        <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: \'Bookman Old Style\', Arial, sans-serif;">
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
            <p style="margin: 0;">Professor Name: <strong>' . htmlspecialchars(strtoupper($prof_full_name)) . '</strong></p>
        </div>
        <!-- Flexbox Section for Preparations and Contact Hours -->
        <div style="display: flex; align-items: center; margin-bottom: 10px; font-size: 8px;">
            <p style="margin: 0; margin-right: 270px">No. of Preparation/s: <strong>' . htmlspecialchars(strtoupper($no_prep_hrs)) . '</strong></p>
            <p style="margin: 0;">Total no. of contact hours per week: <strong>' . htmlspecialchars(strtoupper($teaching_hrs)) . '</strong></p>
        </div>
        </div>
    </div>
</div>

';

    $html .= '<div class="schedule-table-container" style="width: 100%; display: flex; justify-content: center; margin: 0 auto;">'; // Adjusted font size
    $html .= '<table class="table schedule-table" style="width: 100%; table-layout: fixed; border-collapse: collapse; overflow-x: auto; padding: 3px; " data-prof="' . htmlspecialchars($prof_name) . '">';
    $html .= '<thead>';


    $html .= '<tr>';
    $html .= '<th style="font-size: 9px; width: 20%; text-align: center; padding: 0px;">Time</th>';

    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 10%; text-align: center; font-size: 9px; padding: 0px;">' . $day_name . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';


    // Fetch the active time slot
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
        $html .= '<td class="time-slot" style=" text-align: center; font-size: 7px; padding: 0px;">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

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
                                $cell_content = "<span style='font-size: 7px; display: block; text-align: center; padding: 0px;'><b>{$schedule['consultation_hrs_type']}</b></span>";
                            } elseif ($class_type_display !== 'Lecture' && $class_type_display !== 'Laboratory') {
                                // If it's not Lecture or Laboratory, display course code, room, section, and class type
                                $cell_content = "<span style='font-size: 7px; display: block; text-align: center; padding: 0px;'><b>{$schedule['course_code']}<br></b>{$schedule['room_code']}<br>{$section_code}<br>{$class_type_display}</span>";
                            } else {
                                // For lectures and labs, just display course, room, and section info
                                $cell_content = "<span style='font-size: 7px; display: block; text-align: center; padding: 0px;'><b>{$schedule['course_code']}<br></b>{$schedule['room_code']}<br>{$section_code}<br>{$class_type_display}</span>";
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

                $html .= '<td ' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') . ' style="background-color: ' . $cell_color . ';">' . $cell_content . '</td>';
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
    $html .= '<th colspan = "2" style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">Course Code</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">Section Code</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">Lec Hours</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">Lab Hours</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">Room</th>';
    $html .= '<th style="text-align: center; font-size: 8px; vertical-align: middle; padding: 0px;">No. of Students</th>';
    $html .= '</tr>';


    // Loop through the courses and their sections
    foreach ($subject_list as $course_code => $course_data) {
        $rowspan = count($course_data['sections']); // Calculate how many rows the course code will span

        $first_row = true; // Flag to track the first row for each course

        foreach ($course_data['sections'] as $section_code => $section_data) {
            $rooms_str = implode(' | ', $section_data['rooms']); // Rooms for the specific section

            $sql = "SELECT no_students FROM tbl_no_students WHERE section_code = ? AND course_code = ? AND prof_code = ?  AND semester = ?";
            if ($stmt = $conn->prepare($sql)) {
                // Bind the parameters to the query
                $stmt->bind_param("ssss", $section_code, $course_code, $prof_code, $semester);

                // Execute the query
                $stmt->execute();

                // Bind the result to a variable
                $stmt->bind_result($no_students);

                // Fetch the result
                if ($stmt->fetch()) {
                } else {
                    // If no record found, set default value
                    $no_students = 0;
                }

                // Close the statement
                $stmt->close();
            } else {
                echo "Error preparing the SQL query.";
            }
            $html .= '<tr>';

            // Only show course code with rowspan on the first row
            if ($first_row) {
                $html .= '<td colspan = "2" rowspan="' . $rowspan . '" style="vertical-align: middle; font-size: 7px; text-align: center; padding: 0px;">' . htmlspecialchars($course_code) . '</td>';
                $first_row = false; // Set flag to false after first row
            }

            $html .= '<td id="section_code_' . $section_code . '" style="padding: 0px; font-size: 7px;" class="editable" data-type="section_code" data-section_code="' . $section_code . '">' . htmlspecialchars($section_code) . '</td>';
            $html .= '<td id="lec_count_' . $section_code . '" style="padding: 0px; font-size: 7px;" class="editable" data-type="lec_count" data-section_code="' . $section_code . '">' . htmlspecialchars($section_data['lec_count']) . '</td>';
            $html .= '<td id="lab_count_' . $section_code . '" style="padding: 0px; font-size: 7px;" class="editable" data-type="lab_count" data-section_code="' . $section_code . '">' . htmlspecialchars($section_data['lab_count']) . '</td>';
            $html .= '<td id="rooms_' . $section_code . '" style="padding: 0px; font-size: 7px;" class="editable" data-type="rooms" data-section_code="' . $section_code . '">' . htmlspecialchars($rooms_str) . '</td>';
            $html .= '<td id="no_students_' . $section_code . '" style="padding: 0px; font-size: 7px;" class="editable" data-type="no_students" data-section_code="' . $section_code . '">' . htmlspecialchars($no_students) . '</td>';

            $html .= '</tr>';
        }
    }

    // Add a footer row for the total lecture and lab hours
    $html .= '<tr>';
    $html .= '<td colspan="3" style="text-align: right; font-size: 8px; font-weight: bold; padding: 0px; right: 5px;">Total: </td>';
    $html .= '<td style="padding: 0px; font-size: 7px;">' .
        (isset($total_lec_hours) ?
            ($total_lec_hours > 0 ? htmlspecialchars($total_lec_hours) . ($total_lec_hours == 1 ? ' hour' : ' hours') : '0')
            : '') .
        '</td>'; // Total lecture hours

    $html .= '<td style="padding: 0px; font-size: 7px;">' .
        (isset($total_lab_hours) ?
            ($total_lab_hours > 0 ? htmlspecialchars($total_lab_hours) . ($total_lab_hours == 1 ? ' hour' : ' hours') : '0')
            : '') .
        '</td>'; // Total lab hours

    $html .= '<td style="padding: 0px;"></td>';
    $html .= '<td style="padding: 0px;"></td>';
    $html .= '</tr>';




    $html .= '</tbody></table></div>';
    // $html .= '<div style="page-break-after: always;"></div>'; // Add this line for page break


    $html .= '<div class="p">SCHEDULE</div>';
    $html .= '<div class="signature-section" style=" font-size: 9px;">';

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



if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    // Get the form values
    $section_code = $_POST['section_code'];
    $course_code = $_POST['course_code'];
    $no_students = $_POST['no_students'];

    // Sanitize the input to prevent SQL injection
    $section_code = mysqli_real_escape_string($conn, $section_code);
    $course_code = mysqli_real_escape_string($conn, $course_code);
    $no_students = (int) $_POST['no_students'];  // Ensure it's an integer

    // Check if the record already exists
    $check_sql = "SELECT * FROM tbl_no_students WHERE section_code = ? AND course_code = ? AND prof_code = ? AND ay_code = ? AND semester = ?";
    if ($check_stmt = $conn->prepare($check_sql)) {
        // Bind the parameters for the check query
        $check_stmt->bind_param("sssis", $section_code, $course_code, $prof_code, $ay_code, $semester);

        // Execute the check statement
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Record exists, update the number of students
            $update_sql = "UPDATE tbl_no_students SET no_students = ? WHERE section_code = ? AND course_code = ? AND prof_code = ? AND ay_code = ? AND semester = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                // Bind the parameters for the update statement
                $update_stmt->bind_param("isssis", $no_students, $section_code, $course_code, $prof_code, $ay_code, $semester);

                // Execute the update statement
                if ($update_stmt->execute()) {
                    // echo "<script>alert('Number of students updated successfully.');</script>";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    echo "<script>alert('Error updating number of students.');</script>";
                }

                // Close the update statement
                $update_stmt->close();
            } else {
                echo "<script>alert('Error preparing the update SQL statement.');</script>";
            }
        } else {
            // Record doesn't exist, insert a new record
            $insert_sql = "INSERT INTO tbl_no_students (dept_code,section_code, course_code, no_students,prof_code,ay_code,semester) VALUES (?, ?, ?,?,?,?,?)";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                // Bind the parameters for the insert statement
                $insert_stmt->bind_param("sssisis", $dept_code, $section_code, $course_code, $no_students, $prof_code, $ay_code, $semester);

                // Execute the insert statement
                if ($insert_stmt->execute()) {
                    // echo "<script>alert('New record inserted successfully.');</script>";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    echo "<script>alert('Error inserting new record.');</script>";
                }

                // Close the insert statement
                $insert_stmt->close();
            } else {
                echo "<script>alert('Error preparing the insert SQL statement.');</script>";
            }
        }

        // Close the check statement
        $check_stmt->close();
    } else {
        echo "<script>alert('Error preparing the check SQL statement.');</script>";
    }
}


if (isset($_POST['section_code'])) {
    $section_code = $_POST['section_code'];

    // Sanitize the table name to prevent SQL injection
    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $account_dept_code . "_" . $ay_code);

    // SQL to fetch course codes for the selected section
    $sql_fetch_courses = "
        SELECT DISTINCT ps.course_code
        FROM $sanitized_psched_code AS ps
        INNER JOIN tbl_secschedlist AS se 
            ON ps.section_code = se.section_sched_code
        WHERE se.section_code = ? AND prof_code = ?";

    $stmt = $conn->prepare($sql_fetch_courses);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind the section_code parameter
    $stmt->bind_param("ss", $section_code, $prof_code);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the courses and store them in an array
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    // Return the result as JSON
    echo json_encode($courses);
    exit;  // Stop further execution
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Professor Library</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>

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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="../../../css/department_secretary/navbar.css">
    <link rel="stylesheet" href="profile.css">


</head>


<body>
    <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code == $user_college_code): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>
    <?php elseif ($_SESSION['user_type'] == 'CCL Head' && $admin_college_code == $user_college_code): ?>
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

    <h2 class="title"><i class="fa-sharp fa-regular fa-id-card"></i> Profile</h2>

    <div class="container">
        <?php
        // Fetch the professor's full name and designation
        $sql_fetch_prof_name = "
    SELECT *
    FROM tbl_prof_acc 
    WHERE cvsu_email = ? AND ay_code = ? AND semester = ?";

        $stmt_prof_name = $conn->prepare($sql_fetch_prof_name);
        $stmt_prof_name->bind_param("sss", $current_user_email, $ay_code, $semester);
        $stmt_prof_name->execute();
        $result_prof_name = $stmt_prof_name->get_result();

        $prof_name = '';
        $designation = '';
        $cvsu_email = '';
        $academic_rank = '';
        if ($result_prof_name->num_rows > 0) {
            $row_prof_name = $result_prof_name->fetch_assoc();
            $prof_name = trim(
                $row_prof_name['first_name'] . ' ' .
                ($row_prof_name['middle_initial'] ? $row_prof_name['middle_initial'] . '. ' : '') .
                $row_prof_name['last_name'] .
                ($row_prof_name['suffix'] ? ', ' . $row_prof_name['suffix'] : '')
            );
            $designation = $row_prof_name['designation'];
            $cvsu_email = $row_prof_name['cvsu_email'];

        } else {
            $prof_name = 'N/A';
            $designation = 'N/A';
            $cvsu_email = 'N/A';
        }

        $academic_rank = '';

        if (!empty($prof_code)) {
            $fetch_info_rank = "SELECT academic_rank FROM tbl_prof WHERE prof_code = '$prof_code' AND ay_code ='$ay_code' AND semester = '$semester'";
            $result_rank = $conn->query($fetch_info_rank);

            if ($result_rank->num_rows > 0) {
                $row_rank = $result_rank->fetch_assoc();
                $academic_rank = $row_rank['academic_rank'];
            }
        }

            $fetch_info_user_prof_code = "SELECT * FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email' AND ay_code ='$ay_code' AND semester = '$semester'";
            $result_user_prof_code = $conn->query($fetch_info_user_prof_code);

            if ($result_user_prof_code->num_rows > 0) {
                $row_user_prof_code = $result_user_prof_code->fetch_assoc();
                $user_prof_code = $row_user_prof_code['prof_code'];
                
            }

        ?>





    </div>



    <div class="container custom-container">


    <div class="info-container">
    <div class="info-group">
        <label class="info-label"><i class="fas fa-id-badge info-icon"></i> Professor Code</label>
        <div class="info-item"><?php echo !empty($user_prof_code) ? $user_prof_code : " "; ?></div>
    </div>

    <div class="info-group">
        <label class="info-label"><i class="fas fa-user info-icon"></i> Professor Name</label>
        <div class="info-item"><?php echo !empty($prof_name) ? $prof_name : " "; ?></div>
    </div>

    <div class="info-group">
        <label class="info-label"><i class="fas fa-graduation-cap info-icon"></i> Academic Rank</label>
        <div class="info-item"><?php echo !empty($academic_rank) ? $academic_rank : " "; ?></div>
    </div>

    <div class="info-group">
        <label class="info-label"><i class="fas fa-briefcase info-icon"></i> Designation</label>
        <div class="info-item" id="designationDiv">
            <?php echo !empty($designation) ? $designation : " "; ?>
        </div>
    </div>

    <div class="info-group">
        <label class="info-label"><i class="fas fa-envelope info-icon"></i> CvSU Email</label>
        <div class="info-item"><?php echo !empty($cvsu_email) ? $cvsu_email : " "; ?></div>
    </div>
</div>

    </div>



    <!-- Modal -->
    <div class="modal fade" id="updateDesignationModal" tabindex="-1" aria-labelledby="updateDesignationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" id="content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateDesignationForm" method="POST" action="update.php">
                    <div class="modal-body">
                        <!-- Designation Input Field -->
                        <div class="mb-3">
                            <label for="designation" class="form-label">Update Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation" required>
                        </div>

                        <!-- Hidden input to store professor code -->
                        <input type="hidden" name="cvsu_email" id="cvsu_email">
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn-update">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="updateSuccessModal" tabindex="-1" aria-labelledby="updateSuccessModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                <p>The professor's information has been updated successfully!</p>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <div class="container sched-container">
        <div class="container-a4">
            <div class="container" id="scheduleContent">
                <div class="card-body schedule-content">
                    <?php


                    // Query for professor's full name and schedule
                    $sql = "
                    SELECT tbl_psched.prof_sched_code, tbl_pcontact_schedstatus.semester
                    FROM tbl_psched
                    JOIN tbl_pcontact_schedstatus ON tbl_psched.prof_sched_code = tbl_pcontact_schedstatus.prof_sched_code
                    WHERE tbl_psched.prof_code = ? 
                        AND tbl_pcontact_schedstatus.status = 'public'
                        AND tbl_psched.dept_code = ? 
                        AND tbl_psched.ay_code = ?";

                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        die("Error preparing statement: " . $conn->error);
                    }

                    $stmt->bind_param('sss', $prof_code, $account_dept_code, $ay_code);
                    if (!$stmt->execute()) {
                        die("Error executing query: " . $stmt->error);
                    }
                    // echo $prof_code
                    
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        // Fetch professor's full name
                        $row = $result->fetch_assoc();

                        // Loop through the schedules and display them
                        do {
                            $prof_sched_code = $row['prof_sched_code'];
                            $semester = $row['semester'];
                            echo fetchScheduleForProf($prof_sched_code, $ay_code, $semester, $account_dept_code);
                        } while ($row = $result->fetch_assoc());
                    } else {
                        echo "<p>No schedule found for this Professor.</p>";
                    }

                    // Sanitize the table name
                    
                    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $account_dept_code . "_" . $ay_code);


                    $sql_fetch_sections = "
                        SELECT DISTINCT se.section_code 
                        FROM $sanitized_psched_code AS ps
                        INNER JOIN tbl_secschedlist AS se 
                            ON ps.section_code = se.section_sched_code
                        LEFT JOIN tbl_no_students AS ns 
                            ON se.section_code = ns.section_code AND ps.course_code = ns.course_code
                        WHERE ps.semester = ? 
                        AND ps.prof_sched_code = ?";

                    $stmt = $conn->prepare($sql_fetch_sections);
                    if (!$stmt) {
                        die("Prepare failed: " . $conn->error);
                    }

                    // Bind the parameters
                    $stmt->bind_param("ss", $semester, $prof_sched_code);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        $sections[] = $row['section_code'];
                    }

                    $stmt->close();
                    ?>

                </div>
            </div>
        </div>


    </div>
    <div class="row text-end">
        <div class="col-md-11"> <!-- Adjusted column size for smaller width -->
            <form method="POST" action="profile.php" style="display:inline;">
                <!-- Ensure these values are fetched or assigned before rendering the form -->
                <input type="hidden" name="prof_code" value="<?php echo isset($user_prof_code) ? $user_prof_code : ''; ?>">
                <input type="hidden" name="prof_name" value="<?php echo isset($prof_name) ? $prof_name : ''; ?>">
                <input type="hidden" name="prof_sched_code"
                    value="<?php echo isset($prof_sched_code) ? $prof_sched_code : ''; ?>">
                <input type="hidden" name="semester" value="<?php echo isset($semester) ? $semester : ''; ?>">
                <input type="hidden" name="dept_code"
                    value="<?php echo isset($account_dept_code) ? $account_dept_code : ''; ?>">
                <input type="hidden" name="ay_code" value="<?php echo isset($ay_code) ? $ay_code : ''; ?>">
                <!-- Use 2425 if ay_code is not set -->
                <input type="hidden" name="academic_rank"
                    value="<?php echo isset($academic_rank) ? $academic_rank : ''; ?>">

                <button type="submit" name="edit" class="btn btn-edit" 
                    <?php echo empty($user_prof_code) ? 'disabled' : ''; ?>> Edit Contact Hours </button>

            </form>


            <!-- Trigger Button for Modal -->
            <button type="button" class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#updateModal">
                Update Number of Students
            </button>


            <!-- Modal Structure -->
            <div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content" id="content">
                        <!-- Modal Header -->
                        <div class="modal-header">
                            <h5 class="modal-title" id="updateModalLabel">Update Number of Students</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <!-- Modal Body -->
                        <div class="modal-body">
                            <form id="updateForm" method="POST" action="">
                                <!-- Section Code (Dropdown) -->
                                <div class="mb-3">
                                    <label for="section_code" class="form-label">Section Code</label>
                                    <select class="form-select" id="section_code" name="section_code" required>
                                        <option value="">Select a Section</option>
                                        <?php
                                        // Loop through the fetched section codes and create options
                                        foreach ($sections as $section_code) {
                                            echo '<option value="' . htmlspecialchars($section_code) . '">' . htmlspecialchars($section_code) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- Course Code (Dropdown) -->
                                <div class="mb-3">
                                    <label for="course_code" class="form-label">Course Code</label>
                                    <select class="form-select" id="course_code" name="course_code" required>
                                        <option value="">Select a Course</option>
                                        <!-- Courses will be dynamically populated based on section code -->
                                    </select>
                                </div>

                                <!-- Number of Students Input -->
                                <div class="mb-3">
                                    <label for="no_students" class="form-label">Number of Students</label>
                                    <input type="number" class="form-control" id="no_students" name="no_students"
                                        min="0" required>
                                </div>


                                <!-- Submit Button -->
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update" class="btn-update">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <button class="btn btn-edit" id="SchedulePDF">Generate PDF</button>
        </div>







    </div>


    <script>


        document.getElementById('SchedulePDF').addEventListener('click', function () {
            // Specify the correct ID of the element that contains the schedule content
            const element = document.getElementById('scheduleContent');

            if (!element) {
                console.error('Element with the specified ID not found');
                return;
            }

            // Generate PDF with visible content only
            html2pdf()
                .from(element)
                .set({
                    margin: [10, 10, 10, 10],
                    html2canvas: {
                        scale: 3,           // Higher scale for better quality
                        useCORS: true,      // Handle cross-origin images if present
                        scrollX: 0,
                        scrollY: 0,
                        windowWidth: document.body.scrollWidth,  // Ensure capturing the current visible width
                        windowHeight: document.body.scrollHeight // Capture the height based on visible content
                    },
                    jsPDF: {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    }
                })
                .outputPdf('blob')
                .then(function (blob) {
                    const pdfUrl = URL.createObjectURL(blob);
                    window.open(pdfUrl); // Opens the generated PDF in a new tab

                    // Create a download link for the PDF and click it
                    const link = document.createElement('a');
                    link.href = pdfUrl;
                    link.download = 'summary_schedule.pdf'; // Default file name for the download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link); // Clean up by removing the link after clicking
                })
                .catch(function (error) {
                    console.error('Error generating PDF:', error);
                });
        });


        document.getElementById('section_code').addEventListener('change', function () {
            const sectionCode = this.value;

            // Fetch courses dynamically
            if (sectionCode) {
                fetch('profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `section_code=${sectionCode}`
                })
                    .then(response => response.json())
                    .then(data => {
                        const courseCodeSelect = document.getElementById('course_code');
                        courseCodeSelect.innerHTML = '<option value="">Select a Course</option>';
                        data.forEach(course => {
                            const option = document.createElement('option');
                            option.value = course.course_code;
                            option.textContent = course.course_code;
                            courseCodeSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error:', error));
            }
        });

        $(document).ready(function () {

            $(document).ready(function () {
                $('#section_code').change(function () {
                    var section_code = $(this).val();
                    console.log('Section code selected:', section_code);

                    if (section_code) {
                        $.ajax({
                            type: 'POST',
                            url: 'profile.php',  // Make sure this is the correct file path
                            data: { section_code: section_code },
                            success: function (response) {
                                console.log('AJAX request successful');
                                console.log('Response:', response);
                                try {
                                    var courses = JSON.parse(response);
                                    var courseSelect = $('#course_code');
                                    courseSelect.empty();
                                    courseSelect.append('<option value="">Select a Course</option>');
                                    courses.forEach(function (course) {
                                        courseSelect.append('<option value="' + course.course_code + '">' + course.course_code + '</option>');
                                    });
                                } catch (e) {
                                    console.log('Error parsing response:', e);
                                }
                            },
                            error: function (xhr, status, error) {
                                console.log('AJAX request failed');
                                console.log('Error:', error);
                            }
                        });
                    } else {
                        console.log('No section code selected.');
                    }
                });
            });


        });



        $(document).ready(function () {
            // Handle the click event of the designation div
            $('#designationDiv').click(function () {
                var currentDesignation = $(this).text().trim();
                var cvsu_email = "<?php echo $cvsu_email; ?>"; // Get the professor code from PHP

                // Set the current designation in the input field of the modal
                $('#designation').val(currentDesignation);
                $('#cvsu_email').val(cvsu_email);

                // Show the modal
                $('#updateDesignationModal').modal('show');
            });

            // Handle the form submission using AJAX
            $('#updateDesignationForm').submit(function (e) {
                e.preventDefault(); // Prevent the default form submission

                var formData = $(this).serialize(); // Serialize the form data

                $.ajax({
                    type: 'POST',
                    url: 'update.php', // The PHP file that handles the form submission
                    data: formData,
                    success: function (response) {
                        console.log(response); // Log the response to the console for debugging

                        if (response.trim() === 'success') {
                            // Show success message inside the modal
                            $('#updateDesignationModal .modal-body').html('<p class="text-success">Designation updated successfully!</p>');
                            $('#updateDesignationModal .modal-footer').hide();
                        } else {
                            // Show error message inside the modal
                            $('#updateDesignationModal .modal-body').html('<p class="text-danger">Error: ' + response + '</p>');
                            $('#updateDesignationModal .modal-footer').hide();
                        }

                        // Reload the page after a short delay
                        setTimeout(function () {
                            window.location.href = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
                        }, 2000); // 2-second delay before reloading
                    },
                    error: function () {
                        // Show error message inside the modal for AJAX error
                        $('#updateDesignationModal .modal-body').html('<p class="text-danger">An error occurred while updating the designation.</p>');
                        $('#updateDesignationModal .modal-footer').hide();

                        // Reload the page after a short delay
                        setTimeout(function () {
                            window.location.href = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
                        }, 2000); // 2-second delay before reloading
                    }
                });
            });
        });




    </script>

</body>

</html>