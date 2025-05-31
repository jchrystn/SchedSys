<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include "../config.php";

$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown Department';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';

$ay_code = $_SESSION['ay_code'] ?? null;

function formatTime($time)
{
    return date('g:i a', strtotime($time));
}

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $semester = $row['semester'];
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];

}

// Prepare and execute the query
$stmt = $conn->prepare("SELECT dept_name FROM tbl_department WHERE dept_code = ?");
$stmt->bind_param("s", $dept_code);
$stmt->execute();
$result_dept_name = $stmt->get_result();
$dept_name = ($result_dept_name->num_rows > 0) ? $result_dept_name->fetch_assoc()['dept_name'] : "Department not found";
$stmt->close();


$search_professor = isset($_POST['search_professor']) ? '%' . $_POST['search_professor'] . '%' : '%';

$sql_fetch_professors = "SELECT p.prof_code, p.prof_name
                         FROM tbl_prof p
                         JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                         JOIN tbl_prof_schedstatus pss ON ps.prof_sched_code = pss.prof_sched_code
                         WHERE p.dept_code = ? AND pss.status = 'public' AND pss.semester = ? AND p.ay_code = ? AND p.prof_code LIKE ?
                         ORDER BY p.prof_code ASC";

$stmt_fetch_professors = $conn->prepare($sql_fetch_professors);
$stmt_fetch_professors->bind_param("ssis", $dept_code, $semester, $ay_code, $search_professor);
$stmt_fetch_professors->execute();
$result = $stmt_fetch_professors->get_result();

// Fetch Professor Schedule
if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['prof_id'])) {
    $prof_id = $_GET['prof_id'];
    $semester = $_GET['semester'];

    $sql = "SELECT p.prof_code, p.prof_name, ps.prof_sched_code 
            FROM tbl_psched ps 
            JOIN tbl_prof p ON ps.prof_code = p.prof_code 
            JOIN tbl_prof_schedstatus pss ON ps.prof_sched_code = pss.prof_sched_code
            WHERE p.prof_code = ? AND pss.status = 'public' AND pss.semester = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $prof_id, $semester);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        die("SQL Error: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $prof_name = htmlspecialchars(string: $row['prof_name']);
        $prof_code = htmlspecialchars(string: $row['prof_code']);

        echo "<h5 class='schedule_text'>Schedule for Professor</h5>";
        if (empty($prof_name)) {
            echo "<h5 class='data_text'>" . $prof_code . "</h5><br>";
        } else {
            echo "<h5 class='data_text'>" . $prof_name . "</h5><br>";

        }

        $prof_sched_code = $row['prof_sched_code'];
        echo fetchScheduleForProf($prof_sched_code, $semester);
    } else {
        echo "<p>No public schedule found for this Professor.</p>";
    }

    $conn->close();
    exit;
}


function fetchScheduleForProf($prof_sched_code, $semester)
{
    global $conn;

    $sql_fetch_prof_info = "SELECT ps.dept_code, ps.ay_code 
                        FROM tbl_psched ps 
                        JOIN tbl_prof_schedstatus pss ON ps.prof_sched_code = pss.prof_sched_code
                        WHERE ps.prof_sched_code = ? AND pss.status = 'public' AND pss.semester = ?";

    $stmt_prof_info = $conn->prepare($sql_fetch_prof_info);
    $stmt_prof_info->bind_param("ss", $prof_sched_code, $semester);
    $stmt_prof_info->execute();
    $result_prof_info = $stmt_prof_info->get_result();

    if (!$result_prof_info) {
        return '<p>No Available Professor Schedule</p>';
    }

    if ($result_prof_info->num_rows > 0) {
        $row_prof_info = $result_prof_info->fetch_assoc();
        $dept_code = $row_prof_info['dept_code'];
        $ay_code = $row_prof_info['ay_code'];

        $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
        try {
            $sql_fetch_schedule = "SELECT * FROM `$sanitized_prof_sched_code` WHERE semester = ?  AND prof_sched_code = ?";
            $stmt_schedule = $conn->prepare($sql_fetch_schedule);
            $stmt_schedule->bind_param("ss", $semester, $prof_sched_code);
            $stmt_schedule->execute();
            $result_schedule = $stmt_schedule->get_result();

            if (!$result_schedule) {
                throw new Exception("Error fetching schedule data: " . $conn->error);
            }

            $schedule_data = [];
            if ($result_schedule->num_rows > 0) {
                while ($row_schedule = $result_schedule->fetch_assoc()) {
                    $day = ucfirst(strtolower($row_schedule['day']));
                    $time_start = $row_schedule['time_start'];
                    $time_end = $row_schedule['time_end'];
                    $course_code = $row_schedule['course_code'];
                    $class_type = $row_schedule['class_type'] ?? '';
                    $room_code = $row_schedule['room_code'];
                    $section_sched_code = $row_schedule['section_code'];
                    $contact_hrs_type = $row_schedule['contact_hrs_type'] ?? '';

                    // Fetch the section code and format it
                    $fetch_info_query = "SELECT section_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
                    $result = $conn->query($fetch_info_query);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $section_code = $row['section_code'];



                    } else {
                        die("Error: No matching section schedule found for code '$section_sched_code'.");
                    }

                    $fetch_color_query = "SELECT cell_color FROM $sanitized_prof_sched_code WHERE section_code = ? AND dept_code = ? AND semester = ?";
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
                        'time_start' => $time_start,
                        'time_end' => $time_end,
                        'course_code' => $course_code,
                        'section_code' => $section_code,
                        'room_code' => $room_code,
                        'contact_hrs_type' => $contact_hrs_type,
                        'cell_color' => $cell_color,
                        'class_type' => $class_type ?? '',

                    ];
                }
            } else {
                return '<p>No Available Schedule</p>';
            }


            $sql_fetch_pcontact_schedule = "SELECT * FROM $sanitized_pcontact_sched_code WHERE semester = ? AND prof_sched_code = ?";
            $stmt_pcontact_schedule = $conn->prepare($sql_fetch_pcontact_schedule);
            $stmt_pcontact_schedule->bind_param("ss", $semester, $prof_sched_code);
            $stmt_pcontact_schedule->execute();
            $result_pcontact_schedule = $stmt_pcontact_schedule->get_result();

            while ($row_schedule = $result_pcontact_schedule->fetch_assoc()) {
                $day = ucfirst(strtolower($row_schedule['day']));

                // Fetch the cell color for contact hours
                $fetch_color_query = "SELECT cell_color FROM tbl_schedstatus WHERE section_sched_code = ? AND dept_code = ? AND semester = ?";
                $stmt_color = $conn->prepare($fetch_color_query);
                $stmt_color->bind_param("sss", $row_schedule['section_code'], $dept_code, $semester);
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
                    'course_code' => $row_schedule['course_code'] ?? '',
                    'room_code' => $row_schedule['room_code'] ?? '',
                    'consultation_hrs_type' => $row_schedule['consultation_hrs_type'] ?? '',
                    'section_code' => $row_schedule['section_code'] ?? '',
                    'class_type' => $row_schedule['class_type'] ?? '',
                    'cell_color' => $cell_color,
                ];
            }


            // Schedule Design
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
            $time_slots = [];

            // Generate 30-minute time slots from 7:00 AM to 7:00 PM
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

            // Initialize remaining rowspan for each day
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

                    if (!empty($schedule_data[$day_name])) {
                        foreach ($schedule_data[$day_name] as $index => $schedule) {
                            $schedule_start = strtotime($schedule['time_start']);
                            $schedule_end = strtotime($schedule['time_end']);
                            $slot_start = strtotime($start_time);
                            $slot_end = strtotime($end_time);

                            if ($schedule_start <= $slot_start && $schedule_end >= $slot_end) {
                                $section_code = isset($schedule['section_code']) ? $schedule['section_code'] : '';

                                // Center the content and make the font smaller, bold for course_code only
                                $cell_content = "<div style='text-align: center; font-size: 13px; vertical-align: middle;'>
                                                    <span style='font-weight: bold;'>{$schedule['course_code']}</span><br>";

                                if (!empty($schedule['class_type'])) {
                                    $cell_content .= "{$schedule['class_type']}<br>";
                                }

                                // Only display consultation_hrs_type if it's from the pcontact schedule
                                if (!empty($schedule['consultation_hrs_type'])) {
                                    $cell_content .= "{$schedule['consultation_hrs_type']}<br>";
                                }


                                // Only display room_code if it's not empty
                                if (!empty($schedule['room_code'])) {
                                    $cell_content .= "{$schedule['room_code']}<br>";
                                }

                                $cell_content .= "{$section_code}</div>";

                                // Calculate the rowspan based on the interval in 30-minute increments
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

                    $html .= '<td style="width: 14.67%; background-color: ' . htmlspecialchars($cell_color) . '; text-align: center; vertical-align: middle; font-size: 13px"' . ($rowspan > 1 ? ' rowspan="' . $rowspan . '"' : '') . '>' . $cell_content . '</td>';
                }

                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
            return $html;

        } catch (Exception $e) {
            return '<p>Error: ' . $e->getMessage() . '</p>';
        }
    } else {
        return '<p>No Available Instructor Schedule</p>';
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
                <a class="nav-link active" id="professor-tab" data-toggle="tab" href="#professor" role="tab"
                    aria-controls="professor" aria-selected="true"
                    style="background-color: #FD7238; color: white;">Instructor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="data_schedule_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="section-tab" href="data_schedule_section.php" aria-controls="section"
                    aria-selected="false">Section</a>
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
                        <input type="text" class="form-control" id="search_professor" name="search_professor"
                            value="<?php echo isset($_POST['search_professor']) ? htmlspecialchars($_POST['search_professor']) : ''; ?>"
                            placeholder="Search Professor" autocomplete="off">
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
                                echo "<td class='text-data'>" . htmlspecialchars($row["prof_code"]) . "</td>";
                                echo "<td><button class='btn btn-success view-schedule' data-bs-toggle='modal' data-bs-target='#scheduleModal' data-prof-id='" . htmlspecialchars($row['prof_code']) . "'>View Schedule</button></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='2'>No Professors found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
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
                filterProfBySchedule();

                // Load schedule into modal when it's opened
                $('#scheduleModal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget);
                    var profId = button.data('prof-id');
                    var semester = $('#search_semester').val();

                    var modal = $(this);
                    $.ajax({
                        url: 'data_schedule_professor.php',
                        method: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            prof_id: profId,
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

                function filterProfBySchedule() {
                    var selectedSemester = $('#search_semester').val();
                    var rowsVisible = false;

                    $('#scheduleTable tbody tr').each(function () {
                        var row = $(this);
                        var profId = row.find('button[data-prof-id]').data('prof-id');

                        $.ajax({
                            url: 'data_schedule_professor.php',
                            method: 'GET',
                            data: {
                                action: 'fetch_schedule',
                                prof_id: profId,
                                semester: selectedSemester,
                                dept_code: $('#dept_code').val()
                            },
                            success: function (response) {
                                if (response.trim().includes("No Available Professor Schedule")) {
                                    row.hide();
                                } else {
                                    row.show();
                                    rowsVisible = true;
                                }
                            },
                            error: function () {
                                console.error('Failed to fetch schedule for professor ID: ' + profId);
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