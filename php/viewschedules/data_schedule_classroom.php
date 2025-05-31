<?php
include("../config.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown Department';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];

}

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$admin_college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];

}

$stmt = $conn->prepare("SELECT dept_name FROM tbl_department WHERE dept_code = ?");
$stmt->bind_param("s", $dept_code);
$stmt->execute();
$result_dept_name = $stmt->get_result();
$dept_name = ($result_dept_name->num_rows > 0) ? $result_dept_name->fetch_assoc()['dept_name'] : "Department not found";
$stmt->close();


if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}



// Fetch schedules based on filter criteria
$sql = "
    SELECT tbl_room_schedstatus.room_sched_code, tbl_room_schedstatus.semester, tbl_room_schedstatus.dept_code, tbl_room_schedstatus.ay_code, tbl_rsched.room_code , tbl_rsched.room_type
    FROM tbl_room_schedstatus 
    INNER JOIN tbl_rsched
    ON tbl_room_schedstatus.room_sched_code = tbl_rsched.room_sched_code 
    WHERE tbl_room_schedstatus.status = 'public'
    AND tbl_room_schedstatus.ay_code = ?
    AND tbl_room_schedstatus.semester = ?
    AND tbl_rsched.room_code COLLATE utf8mb4_general_ci LIKE ?
    AND tbl_room_schedstatus.dept_code = ?";

$search_room = isset($_POST['search_classroom']) ? '%' . $_POST['search_classroom'] . '%' : '%';

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $ay_code, $semester, $search_room, $dept_code);
$stmt->execute();
$result = $stmt->get_result();
if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $semester = $_GET['semester'];

    // Fetch the room schedule code
    $sql = "SELECT * FROM tbl_rsched WHERE room_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $room_id, $dept_code, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $room_sched_code = $row['room_sched_code'];
        echo "<h5 class='schedule_text'>Schedule for Classroom</h5>";
        echo "<h5 class='data_text'>" . $room_id . "</h5><br>";

        // For displaying the Schedule Table
        echo fetchScheduleForRoom($room_sched_code, $ay_code, $semester);


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
    $query_college_code = "SELECT college_code FROM tbl_prof_acc WHERE user_type = 'CCL Head' AND ay_code = '$ay_code' AND semester = '$semester'";
    $college_result = $conn->query($query_college_code);

    if ($college_result->num_rows > 0) {
        $row = $college_result->fetch_assoc();
        $ccl_college_code = $row['college_code'];

        echo "<input type='hidden' id='ccl_college_code' value='" . htmlspecialchars($ccl_college_code) . "'>";
    }

    

    $sql_fetch_room_info = "SELECT room_code, ay_code, dept_code,room_type FROM tbl_rsched WHERE room_sched_code = ? AND dept_code = ?";
    $stmt_room_info = $conn->prepare($sql_fetch_room_info);
    $stmt_room_info->bind_param("ss", $room_sched_code, $_SESSION['dept_code']);
    $stmt_room_info->execute();
    $result_room_info = $stmt_room_info->get_result();

    if ($result_room_info->num_rows === 0) {
        return '<p>No Available Classroom Schedule</p>' . htmlspecialchars($ay_code);
    }
    
 

    $row_room_info = $result_room_info->fetch_assoc();
    $room_code = $row_room_info['room_code'];
    $room_type = $row_room_info['room_type'];
    $dept_code = $row_room_info['dept_code'];

    if ($room_type == "Computer Laboratory") {
        $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$ccl_college_code}_{$ay_code}");
    } else {
        $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
    }


    $sql_fetch_schedule = "SELECT * FROM $sanitized_room_sched_code WHERE semester = ? AND room_code = ? AND dept_code = ?";
    $stmt_schedule = $conn->prepare($sql_fetch_schedule);
    $stmt_schedule->bind_param("sss", $semester, $room_code, $dept_code);
    $stmt_schedule->execute();
    $result_schedule = $stmt_schedule->get_result();

    if ($result_schedule->num_rows > 0) {
        $schedule_data = [];
        while ($row_schedule = $result_schedule->fetch_assoc()) {
            $day = ucfirst(strtolower($row_schedule['day']));
            $time_start = $row_schedule['time_start'];
            $time_end = $row_schedule['time_end'];
            $course_code = $row_schedule['course_code'];
            $prof_name = $row_schedule['prof_name'];
            $section_sched_code = $row_schedule['section_code'];
            $prof_code = $row_schedule['prof_code'];
            $class_type = $row_schedule['class_type'];

            $class_type = match ($class_type) {
                "lec" => "Lecture",
                "lab" => "Laboratory",
                default => ucfirst($class_type),
            };


            $fetch_info_query = "SELECT section_code FROM tbl_secschedlist WHERE section_sched_code = ?";
            $stmt_fetch_info = $conn->prepare($fetch_info_query);
            $stmt_fetch_info->bind_param("s", $section_sched_code);
            $stmt_fetch_info->execute();
            $result_info = $stmt_fetch_info->get_result();

            if ($result_info->num_rows > 0) {
                $row = $result_info->fetch_assoc();
                $section_code = $row['section_code'];
            } else {
                return "<p>Error: No matching section schedule found for code '$section_sched_code'.</p>";
            }
            $fetch_color_query = "SELECT cell_color FROM $sanitized_room_sched_code WHERE section_code = ? AND dept_code = ? AND semester = ?";
            $stmt_color = $conn->prepare($fetch_color_query);
            $stmt_color->bind_param("sss", $section_sched_code, $dept_code, $semester);
            $stmt_color->execute();
            $result_color = $stmt_color->get_result();
            $cell_color = $result_color->num_rows > 0 ? $result_color->fetch_assoc()['cell_color'] : '';

            $schedule_data[$day][] = [
                'time_start' => $time_start,
                'time_end' => $time_end,
                'course_code' => $course_code,
                'section_code' => $section_code,
                'prof_name' => $prof_name,
                'prof_code' => $prof_code,
                'cell_color' => $cell_color,
                'class_type' => $class_type, // Add class type display to the schedule data
            ];

        }
        return generateScheduleTable($conn, $schedule_data);
    } else {
        return '<p>No Available Classroom Schedule</p>';
    }
}

function generateScheduleTable($conn,$schedule_data)
{

    $dept_code = $_SESSION['dept_code'];
    $semester = $_SESSION['semester'];

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

    $html = '<div class="schedule-table-container">';
    $html .= '<table class="table table-bordered schedule-table">';
    $html .= '<thead><tr><th style="width: 12%;">Time</th>';

    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 14.67%;">' . $day_name . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    $time_slots = [];

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

    $remaining_rowspan = array_fill_keys($day_names, 0);


   
    foreach ($time_slots as $slot) {
        $start_time = $slot['start'];
        $end_time = $slot['end'];

        $html .= '<tr>';
        $html .= '<td class="time-slot">' . $start_time . ' - ' . $end_time . '</td>';

        foreach ($day_names as $day_name) {
            if ($remaining_rowspan[$day_name] > 0) {
                $remaining_rowspan[$day_name]--;
                continue;
            }

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
                        $section_code = isset($schedule['section_code']) ? $schedule['section_code'] : '';

                        $cell_content = "<div style='text-align: center; font-size: 12px; vertical-align: center;  ;'>
                        <span style='font-weight: bold;'>{$schedule['course_code']}<br> {$schedule['section_code']}<br>  {$schedule['class_type']}</span><br>";

                        // Check if prof_name is available, otherwise show prof_code
                        if (!empty($schedule['prof_name'])) {
                            $cell_content .= "{$schedule['prof_name']}";
                        } else {
                            $cell_content .= "{$schedule['prof_code']}"; // Display prof_code if prof_name is empty
                        }

                        $cell_content .= "</div>";

                        $intervals = ($schedule_end - $schedule_start) / 1800;
                        $rowspan = max($rowspan, $intervals);
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
                $html .= '<td style="width: 14.67%; background-color: ' . $cell_color . '; text-align: center; vertical-align: middle; font-size: 10px;"' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') . '>' . $cell_content . '</td>';
            }
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

function formatTime($time)
{
    return date('g:i A', strtotime($time));
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
                <a class="nav-link " id="professor-tab" href="data_schedule_professor.php" role="tab"
                    aria-controls="professor" aria-selected="false">Instructor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="classroom-tab" href="data_schedule_classroom.php"
                    aria-controls="classroom" aria-selected="true"
                    style="background-color: #FD7238; color: white;">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="section-tab" href="data_schedule_section.php" aria-controls="section"
                    aria-selected="false">Section</a>
            </li>
            <?php if ($user_type !='Student'): ?>
            <li class="nav-item">
                <a class="nav-link" id="vacant-room-tab" href="data_schedule_vacant.php" aria-controls="vacant-room" aria-selected="false">Vacant Room</a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content mt-4">
            <form method="POST">
                <div class="form-row">
                <div class="form-group col-md-3">
                    <input type="hidden" id="search_ay_name" name="search_ay_name" value="<?= htmlspecialchars($ay_name) ?>">
                    </div>
                    <div class="form-group col-md-3">
                    <input type="hidden" id="search_semester" name="search_semester" value="<?= htmlspecialchars($semester) ?>">
                    </div>

                    <div class="form-group col-md-3">
                        <input type="text" class="form-control" id="search_classroom" name="search_classroom"
                            value="<?php echo isset($_POST['search_classroom']) ? htmlspecialchars($_POST['search_classroom']) : ''; ?>"
                            placeholder="Search classroom" autocomplete="off">
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
                <table class="table table-bordered">
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='text-data'>" . htmlspecialchars($row["room_code"]) . "</td>";
                                echo "<td><button class='btn btn-success' data-bs-toggle='modal' data-bs-target='#scheduleModal' data-room-id='" . htmlspecialchars($row['room_code']) . "'>View Schedule</button></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='2'>No Classroom found</td></tr>";
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
            filterroomBySchedule();

            // Load schedule into modal when it's opened
            $('#scheduleModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var roomId = button.data('room-id');
                var semester = $('#search_semester').val();

                var modal = $(this);
                $.ajax({
                    url: 'data_schedule_classroom.php',
                    method: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        room_id: roomId,
                        semester: semester
                    },
                    success: function (response) {
                        modal.find('#scheduleContent').html(response);
                    },
                    error: function () {
                        modal.find('#scheduleContent').html('<p>Error loading schedule.</p>');
                    }
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

            function filterroomBySchedule() {
                var selectedSemester = $('#search_semester').val();
                var rowsVisible = false;

                $('#scheduleTable tbody tr').each(function () {
                    var row = $(this);
                    var roomId = row.find('button[data-room-id]').data('room-id');

                    $.ajax({
                        url: 'data_schedule_classroom.php',
                        method: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            room_id: roomId,
                            semester: selectedSemester,
                            dept_code: $('#dept_code').val()
                        },
                        success: function (response) {
                            if (response.trim().includes("No Available classroom Schedule")) {
                                row.hide();
                            } else {
                                row.show();
                                rowsVisible = true;
                            }
                        },
                        error: function () {
                            console.error('Failed to fetch schedule for classroom ID: ' + roomId);
                        },
                        complete: function () {
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