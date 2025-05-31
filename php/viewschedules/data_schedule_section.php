<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config.php");

$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown Department';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$ay_code = $_SESSION['ay_code'] ?? null;

if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];

}

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $semester = $row['semester'];
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];

}

$stmt = $conn->prepare("SELECT dept_name FROM tbl_department WHERE dept_code = ?");
$stmt->bind_param("s", $dept_code);
$stmt->execute();
$result_dept_name = $stmt->get_result();
$dept_name = ($result_dept_name->num_rows > 0) ? $result_dept_name->fetch_assoc()['dept_name'] : "Department not found";
$stmt->close();

// Fetch the schedules based on filtering criteria
$sql = "
    SELECT tbl_schedstatus.section_sched_code, tbl_schedstatus.semester, tbl_schedstatus.dept_code, tbl_schedstatus.ay_code, tbl_secschedlist.section_code 
    FROM tbl_schedstatus 
    INNER JOIN tbl_secschedlist 
    ON tbl_schedstatus.section_sched_code = tbl_secschedlist.section_sched_code 
    WHERE tbl_schedstatus.status IN ('public') 
    AND tbl_schedstatus.ay_code = ? 
    AND tbl_schedstatus.semester = ? 
    AND tbl_secschedlist.section_code COLLATE utf8mb4_general_ci LIKE ? 
    AND tbl_schedstatus.dept_code = ?"; // Filter by dept_code

$search_section = isset($_POST['search_section']) ? '%' . $_POST['search_section'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $ay_code, $semester, $search_section, $dept_code);
$stmt->execute();
$result = $stmt->get_result();

// Check if a specific action is set and fetch the schedule for a section
if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['section_id'])) {
    $section_id = $_GET['section_id']; // Ensure section_id is coming from GET request
    $semester = $_GET['semester'];     // Ensure semester is coming from GET request

    // Fetch the section schedule code based on the section_id, dept_code, ay_code, and semester
    $sql = "SELECT * FROM tbl_secschedlist WHERE section_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $section_id, $dept_code, $ay_code); // Ensure correct variables are bound
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_sched_code = $row['section_sched_code'];
        echo "<h5 class='schedule_text'>Schedule for Section</5>";
        echo "<h5 class='data_text'>" . $section_id . "</h5><br>";

        echo fetchScheduleForSec($section_sched_code, $ay_code, $semester); // Pass the correct ay_code and semester
    } else {
        echo "<p>No schedule found for this Section.</p>";
    }

    $stmt->close();
    $conn->close();
    exit;
}

function fetchScheduleForSec($section_sched_code, $ay_code, $semester)
{
    global $conn;

    // Fetch section information from tbl_secschedlist
    $sql_fetch_section_info = "SELECT section_code, program_code, ay_code, dept_code 
                               FROM tbl_secschedlist 
                               WHERE section_sched_code=? 
                               AND dept_code=? 
                               AND ay_code=?";
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

        // Sanitize the table name for the section schedule
        $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

        try {
            // Fetch schedule data from the sanitized section schedule table
            $sql_fetch_schedule = "
                SELECT sched.*, status.cell_color 
                FROM `$sanitized_section_sched_code` AS sched 
                INNER JOIN tbl_schedstatus AS status 
                ON sched.section_sched_code = status.section_sched_code 
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
                    $class_type = $row_schedule['class_type'];

                    $schedule_data[$day][] = [
                        'time_start' => $time_start,
                        'time_end' => $time_end,
                        'course_code' => $course_code,
                        'room_code' => $room_code,
                        'prof_name' => $prof_name,
                        'cell_color' => $cell_color,
                        'class_type' => $class_type,
                    ];
                }
            } else {
                return '<p>No Available Section Schedule</p>';
            }

            // Generate the HTML table
            $html = '<div class="schedule-table-container">';
            $html .= '<table class="table table-bordered schedule-table">';
            $html .= '<thead><tr><th style="width: 12%;">Time</th>';

            // Define column headers for days
            $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($day_names as $day_name) {
                $html .= '<th style="width: 14.67%;">' . $day_name . '</th>';
            }
            $html .= '</tr></thead>';
            $html .= '<tbody>';

            // Define time slots
            $active_timeslot_query = "SELECT table_start_time, table_end_time 
            FROM tbl_timeslot_active 
            WHERE dept_code = ? 
            AND semester = ? 
            AND active = 1";
            $stmt_timeslot = $conn->prepare($active_timeslot_query);
            $stmt_timeslot->bind_param("ss", $dept_code, $semester);
            $stmt_timeslot->execute();
            $result_timeslot = $stmt_timeslot->get_result();

            if ($row = $result_timeslot->fetch_assoc()) {
                $table_start_time = strtotime($row['table_start_time']);
                $table_end_time = strtotime($row['table_end_time']);
            } else {
                return '<p>No active time slot configuration found.</p>';
            }

            $time_slots = [];
            $current_time = $table_start_time;
            while ($current_time < $table_end_time) {
                $start_time = date('g:i A', $current_time); // 12-hour format with AM/PM
                $end_time = date('g:i A', $current_time + 1800); // Increment by 30 minutes
                $time_slots[] = [
                    'start' => $start_time,
                    'end' => $end_time,
                ];
                $current_time += 1800;
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
                $html .= '<td class="time-slot">' . $start_time_formatted . ' - ' . $end_time_formatted . '</td>';

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
                                    $cell_content = "<b>{$schedule['course_code']}</b> ({$schedule['class_type']})<br>{$schedule['room_code']}<br>{$schedule['prof_name']}";
                                    $rowspan = ceil(($schedule_end - $schedule_start) / 1800); // Calculate rowspan
                                    $remaining_rowspan[$day_name] = $rowspan - 1;
                                    $cell_color = $schedule['cell_color']; // Get the cell color
                                    break;
                                }
                            }
                        }

                        // Apply the cell color if available
                        $style = $cell_color ? ' style="background-color:' . htmlspecialchars($cell_color) . ';"' : '';
                        $html .= '<td' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') . $style . '>' . $cell_content . '</td>';
                    }
                }

                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
            return $html;

        } catch (Exception $e) {
            return '<p>Error fetching schedule: ' . $e->getMessage() . '</p>';
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>SchedSys</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="../../css/student/department.css">
</head>

<body>

    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
    include($IPATH . "professor_navbar.php"); ?>


    <div class="head-label">
        <h1 class="text-center"><?php echo htmlspecialchars($dept_name); ?></h1>
        <h5 class="text-center sem"><?php echo htmlspecialchars(string: $ay_name); ?> |
            <?php echo htmlspecialchars(string: $semester); ?>
        </h5>
    </div>

    <div class="container mt-5">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="data_schedule_professor.php" aria-controls="professor"
                    aria-selected="false">Instructor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="data_schedule_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="section-tab" href="data_schedule_section.php" aria-controls="section"
                    aria-selected="true" style="background-color: #FD7238; color: white;">Section</a>
            </li>
            <?php if ($user_type != 'Student'): ?>
                <li class="nav-item">
                    <a class="nav-link" id="vacant-room-tab" href="data_schedule_vacant.php" aria-controls="vacant-room"
                        aria-selected="false">Vacant Room</a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content mt-4">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <input type="hidden" id="search_ay_name" name="search_ay_name"
                            value="<?= htmlspecialchars($ay_name) ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <input type="hidden" id="search_semester" name="search_semester"
                            value="<?= htmlspecialchars($semester) ?>">
                    </div>

                    <div class="form-group col-md-3">
                        <input type="text" class="form-control" id="search_section" name="search_section"
                            value="<?php echo isset($_POST['search_section']) ? htmlspecialchars($_POST['search_section']) : ''; ?>"
                            placeholder="Search Section" autocomplete="off">
                    </div>

                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-search w-100"
                            style="background-color: #FD7238; color: white;">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="tab-content mt-4" id="myTabContent">
            <div class="container">
                <table class="table table-bordered" id="scheduleTable">
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='text-data'>" . htmlspecialchars($row['section_code']) . "</td>";
                                echo "<td><button class='btn btn-success' data-bs-toggle='modal' data-bs-target='#scheduleModal' data-section-id='" . htmlspecialchars($row['section_code']) . "'>View Schedule</button></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='2'>No Section found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header"> <!-- Corrected the header class here -->
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="scheduleContent">
                    <!-- Content will display here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Ensure the semester is defined
            var semester = '<?php echo $semester; ?>';

            $('#scheduleModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var sectionId = button.data('section-id');
                var modal = $(this);
                modal.find('.modal-body').html('Loading...'); // Show loading text while fetching data

                // Debugging: Log sectionId and semester
                console.log('Section ID:', sectionId);
                console.log('Semester:', semester);

                $.ajax({
                    url: 'data_schedule_section.php',
                    method: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        section_id: sectionId,
                        semester: semester
                    },
                    success: function (response) {
                        console.log('Response:', response); // Debug: Log the response
                        modal.find('#scheduleContent').html(response); // Update modal content
                    },
                    error: function () {
                        console.error('Failed to fetch schedule for section ID: ' + sectionId);
                        modal.find('#scheduleContent').html('<p>Error loading schedule.</p>');
                    }
                });
            });
        });

        document.getElementById("search_ay_input").addEventListener("input", function () {
            const searchTerm = this.value.toLowerCase();
            const selectElement = document.getElementById("search_ay_name");
            const options = selectElement.options;

            for (let i = 0; i < options.length; i++) {
                const optionText = options[i].text.toLowerCase();
                options[i].style.display = optionText.includes(searchTerm) ? "" : "none";
            }
        });
    </script>

</body>

</html>