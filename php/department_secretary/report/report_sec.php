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



// Fetch the schedules based on filtering criteria
$sql = "
    SELECT tbl_schedstatus.section_sched_code, tbl_schedstatus.semester, tbl_schedstatus.dept_code, tbl_schedstatus.ay_code, tbl_secschedlist.section_code 
    FROM tbl_schedstatus 
    INNER JOIN tbl_secschedlist 
    ON tbl_schedstatus.section_sched_code = tbl_secschedlist.section_sched_code 
    WHERE tbl_schedstatus.status IN ('completed', 'public', 'private') 
    AND tbl_schedstatus.ay_code = ?
    AND tbl_schedstatus.semester = ?
    AND tbl_secschedlist.section_code COLLATE utf8mb4_general_ci LIKE ?
    AND tbl_schedstatus.dept_code = ?"; // Filter by dept_code

$search_section = isset($_POST['search_section']) ? '%' . $_POST['search_section'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $active_ay_code, $active_semester, $search_section, $dept_code);
$stmt->execute();
$result = $stmt->get_result();

if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['section_id'])) {
    $section_id = $_GET['section_id'];

    // Fetch the section schedule code based on the section_id, semester, and ay_code
    $sql = "SELECT * FROM tbl_secschedlist WHERE section_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $section_id, $dept_code, $active_ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_sched_code = $row['section_sched_code'];
        echo fetchScheduleForSec($section_sched_code, $active_ay_code, $active_semester);
    } else {
        echo "<p>No schedule found for this Section.</p>";
    }

    $stmt->close();
    $conn->close();
    exit;
}
// $sql_fetch = "SELECT table_start_time, table_end_time 
//               FROM tbl_timeslot_active 
//               WHERE active = 1 AND dept_code = ? AND semester = ? AND ay_code = ?";
// $stmt_fetch = $conn->prepare($sql_fetch);
// $stmt_fetch->bind_param("sss", $dept_code, $semester, $ay_code); // Assuming all are strings
// $stmt_fetch->execute();
// $result_fetch = $stmt_fetch->get_result();


// if ($result_fetch->num_rows > 0) {
//     // Fetch the active time slot
//     $row_fetch = $result_fetch->fetch_assoc();
//     $user_start_time = $row_fetch['table_start_time'];
//     $user_end_time = $row_fetch['table_end_time'];
// } else {
//     // Defaults if no active time slot is found
//     $user_start_time = '7:00 am';
//     $user_end_time = '7:00 pm';
// }
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
                    <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: \'Times New Roman\', Arial, sans-serif;">
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
                    <div style="width: 90%; margin: 0 auto; text-align: right; font-size: 9px; margin-top: 10px;">
                        <p style="margin: 0; line-height: 0.5; ">Date: <strong>' . $currentDate . '</strong></p>
                    </div>
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
                        <th style="font-size: 8px; width: 20%; text-align: center;">Time</th>';


            // Define column headers for days
            $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($day_names as $day_name) {
                $html .= '<th style="width: 10%; text-align: center; font-size: 8px; padding: 3px;">' . $day_name . '</th>';
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
            $html .= '<td colspan="1" style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px; font-weight: bold;">Course Code</td>';
            $html .= '<th colspan="3" style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px;">Subjects</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px;">Lec</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px;">Lab</th>';
            $html .= '<th style="text-align: center; vertical-align: middle; font-size: 8px; padding: 3px;">Units</th>';

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
        s.time_end
    FROM tbl_course c
    LEFT JOIN $sanitized_section_sched_code s ON c.course_code = s.course_code 
    WHERE c.year_level = ? AND c.program_code = ? AND c.semester = ? 
    AND (s.section_sched_code = ? OR s.section_sched_code IS NULL)
";


            $stmt_combined = $conn->prepare($sql_combined);
            $stmt_combined->bind_param('ssss', $year_level, $program_code, $semester, $section_sched_code);
            $stmt_combined->execute();
            $result_combined = $stmt_combined->get_result();

            $schedule_data = []; // To hold aggregated data for each course

            // Loop through the result and aggregate schedule data
            while ($row = $result_combined->fetch_assoc()) {

                $course_code = $row['course_code'];
                $class_type = $row['class_type'];
                $start_time = !empty($row['time_start']) ? strtotime($row['time_start']) : null;
                $end_time = !empty($row['time_end']) ? strtotime($row['time_end']) : null;

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
                $html .= '<td colspan="1" style="text-align: center; padding: 3px; font-size: 8px; color: ' . $font_color . '; font-weight: bold;">' . htmlspecialchars($course_code) . '</td>'; // Course code
                $html .= '<td colspan="3" style="text-align: center; padding: 3px; font-size: 8px; color: ' . $font_color . ';">' . htmlspecialchars($data['course_name']) . '</td>'; // Course name
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($lec_hours) . '</td>'; // Lecture hours
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($lab_hours) . '</td>'; // Lab hours
                $html .= '<td style="padding: 3px; font-size: 8px; text-align: center; color: ' . $font_color . ';">' . htmlspecialchars($data['credit']) . '</td>'; // Credit
                $html .= '</tr>';
            }





            $html .= '<tr>';
            $html .= '<td colspan="3"</td>';
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






?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Section Report</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>
    <script src="/SchedSYS3/exceljs.min.js"></script>



    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Gothic+A1&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">


    <!-- <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script> -->
    <link rel="stylesheet" href="/SchedSys3/font-awesome-6-pro-main/css/all.min.css">
    <script src="/SchedSys3/jquery.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>


    <link rel="stylesheet" href="../../../css/department_secretary/report/report_sec.css">

</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>
    <h2 class="title"><i class="fa-solid fa-file-alt"></i> REPORT</h2>

    <div class="container mt-5">
    <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="section-tab" href="../library/lib_section.php" aria-controls="Section"
                    aria-selected="true">Section</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="../library/lib_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
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
                        <form method="POST" action="report_sec.php" class="row">
                            <div class="col-md-3">
                            </div>

                            <div class="col-md-3">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search_section"
                                    value="<?php echo isset($_POST['search_section']) ? htmlspecialchars($_POST['search_section']) : ''; ?>"
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
                                <th class="equal-width">Section</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr data-section-id="<?php echo htmlspecialchars($row['section_code']); ?>">
                                        <td>
                                            <div style="display: flex;">
                                                <?php
                                                // Fetch the status for the current schedule before rendering the form
                                                $section_sched_code = htmlspecialchars($row['section_sched_code']);
                                                $ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
                                                $semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
                                                $dept_code = $_SESSION['dept_code'];

                                                // Query the database to get the current status
                                                $checkQuery = "SELECT status FROM tbl_schedstatus WHERE section_sched_code  = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                                                $stmt = $conn->prepare($checkQuery);
                                                $stmt->bind_param('ssss', $section_sched_code, $semester, $ay_code, $dept_code);
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
                                                <form method="POST" action="report_sec.php">
                                                    <input type="hidden" name="section_code"
                                                        value="<?php echo htmlspecialchars($row['section_code']); ?>">
                                                    <input type="hidden" name="semester"
                                                        value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                    <input type="hidden" name="ay_code"
                                                        value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                    <input type="hidden" name="dept_code"
                                                        value="<?php echo htmlspecialchars($row['dept_code']); ?>">
                                                    <input type="hidden" name="section_sched_code"
                                                        value="<?php echo htmlspecialchars($row['section_sched_code']); ?>">
                                                </form>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['section_code']); ?></td>
                                        <td>
                                            <!-- Edit Form -->
                                            <form method="POST" action="report_sec.php" style="display:inline;">
                                                <input type="hidden" name="section_sched_code"
                                                    value="<?php echo htmlspecialchars($row['section_sched_code']); ?>">
                                                <input type="hidden" name="semester"
                                                    value="<?php echo htmlspecialchars($row['semester']); ?>">
                                                <input type="hidden" name="ay_code"
                                                    value="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                                <input type="hidden" name="section_code"
                                                    value="<?php echo htmlspecialchars($row['section_code']); ?>">
                                                <button type="submit" name="edit" class="edit-btn">
                                                    <i class="far fa-pencil-alt"></i>
                                                </button>
                                            </form>
                                            <!-- Report Form -->
                                            <form method="POST" action="report_sec.php" style="display:inline;">
                                                <button type="button" name="report" id="report" class="report-btn"
                                                    value="report" data-toggle="modal" data-target="#scheduleModal"
                                                    data-section-code="<?php echo htmlspecialchars($row['section_code']); ?>"
                                                    data-semester="<?php echo htmlspecialchars($row['semester']); ?>"
                                                    data-ay-code="<?php echo htmlspecialchars($row['ay_code']); ?>">
                                            </form>
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



    <!-- Schedule Modal -->
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
    <script>
        // Get the current date
        var today = new Date();

        // Format the date (e.g., MM/DD/YYYY)
        var formattedDate = (today.getMonth() + 1) + '/' + today.getDate() + '/' + today.getFullYear();

        // Display the date in the paragraph with id "currentDate"
        document.getElementById('currentDate').textContent = 'Date: ' + formattedDate;
    </script>

    <script>


        document.getElementById('SchedulePDF').addEventListener('click', function () {
            const element = document.getElementById('scheduleContent');

            // Get the section_id from the <p> tag where it is displayed
            const sectionTitleElement = document.getElementById('sectionTitle');

            // Extract the full sectionId from the <p> tag text content
            const sectionId = sectionTitleElement ? sectionTitleElement.textContent.trim() : 'section_schedule';

            const fileName = `${sectionId}`;  // Combine the section ID and custom name

            // Create a div for any custom text (if needed)
            const customTextDiv = document.createElement('div');
            customTextDiv.innerHTML = ``;  // Add any custom text if needed
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

            // Function to add a merged row
            function addMergedRow(text, rowNumber) {
                worksheet.mergeCells(rowNumber, 1, rowNumber, 7); // Merge from column 1 to 7
                let row = worksheet.getRow(rowNumber);
                row.getCell(1).value = text;
                row.getCell(1).alignment = { horizontal: "center", vertical: "middle" };
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
            addMergedRow(semester + ", SY " + academicYear, rowIndex++); // Dynamic semester and academic year
            rowIndex++; // Add space

            // Add date row, aligning it to the right
            worksheet.mergeCells(rowIndex, 1, rowIndex, 6); // Merge first 6 columns
            worksheet.getCell(rowIndex, 7).value = "Date: " + currentDate;
            worksheet.getCell(rowIndex, 7).alignment = { horizontal: "right", vertical: "middle" };
            rowIndex++;

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
                tl: { col: 1.9, row: 1 }, // Position at D2 (0-based index)
                ext: { width: 60, height: 50 }
            });

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
            const fileName = `Report for Section ${sectionId || "Unknown"}.xlsx`;
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = fileName;
            link.click();
        }



        function toggleDropdown(element) {
            element.classList.toggle("change");
            document.querySelector('.sidebar').classList.toggle('active');
        }

        function toggleDropdownContent(event) {
            event.preventDefault();
            const dropdownContent = event.target.nextElementSibling;
            if (dropdownContent) {
                dropdownContent.classList.toggle('show');
            }
        }

        window.onclick = function (event) {
            if (!event.target.closest('.hamburger-menu') && !event.target.closest('.sidebar')) {
                var sidebar = document.querySelector('.sidebar');
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }

                var hamburger = document.querySelector('.hamburger-menu');
                if (hamburger.classList.contains('change')) {
                    hamburger.classList.remove('change');
                }

                var dropdowns = document.querySelectorAll('.dropdown-content.show');
                dropdowns.forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            }
        }


        $(document).ready(function () {
            // Handle report button click to open the schedule modal
            $('#scheduleTable').on('click', '.report-btn', function () {
                var button = $(this); // Get the clicked report button

                // Retrieve the data attributes
                var sectionCode = button.data('section-code');
                var semester = button.data('semester');
                var ayCode = button.data('ay-code');

                // Show the schedule modal
                $('#scheduleModal').modal('show');
                $('#scheduleContent').html('<p>Loading schedule details...</p>'); // Add loading message

                // Fetch the schedule details via AJAX
                $.ajax({
                    url: 'report_sec.php',
                    type: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        section_id: sectionCode,
                        semester: semester,
                        ay_code: ayCode
                    },
                    success: function (response) {
                        console.log("Response: ", response);
                        $('#scheduleContent').html(
                            response); // Load the fetched schedule into the modal
                    },
                    error: function () {
                        console.error('Failed to fetch schedule for section ID: ' +
                            sectionCode);
                        $('#scheduleContent').html(
                            '<p>Error loading schedule details.</p>'); // Handle errors
                    }
                });
            });

            // Ensure modal cleanup after it's closed
            $('#scheduleModal').on('hidden.bs.modal', function () {
                // Reset modal content and ensure backdrop is removed
                $('#scheduleContent').html(''); // Clear the content
                $('body').removeClass('modal-open'); // Remove the 'modal-open' class from body
                $('.modal-backdrop').remove(); // Remove any leftover backdrop

                // Dispose of the modal to ensure no leftovers
                $('#scheduleModal').modal('dispose'); // Properly remove modal instance
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

                    // Ensure that a valid sectionId is present
                    if (sectionId) {
                        $('#scheduleModal').modal('show'); // Show the modal immediately
                        $('#scheduleContent').html('<p>Loading...</p>'); // Add loading text

                        // Fetch schedule via AJAX
                        $.ajax({
                            url: 'report_sec.php',
                            type: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                section_id: sectionId,
                                semester: semester
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
                var rowsVisible = false;

                $('#scheduleTable tbody tr').each(function () {
                    var row = $(this);
                    var sectionId = row.data('section-id');

                    $.ajax({
                        url: 'report_sec.php',
                        type: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            section_id: sectionId,
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




        });
    </script>
</body>

</html>