<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config.php");

$dept_name = isset($_SESSION['dept_name']) ? $_SESSION['dept_name'] : 'Unknown Department';

$college_code = $_SESSION['college_code'];
$dept_code = $_SESSION['dept_code'];

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];

}

// fetching the academic year and semester
$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$admin_college_code' and active = '1'";
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


$query_college_code = "SELECT college_code FROM tbl_prof_acc WHERE user_type = 'CCL Head' AND ay_code = '$ay_code' AND semester = '$semester'";
$college_result = $conn->query($query_college_code);

if ($college_result->num_rows > 0) {
    $row = $college_result->fetch_assoc();
    $ccl_college_code = $row['college_code'];

    echo "<input type='hidden' id='ccl_college_code' value='" . htmlspecialchars($ccl_college_code) . "'>";
}




$sql_fetch = "SELECT table_start_time, table_end_time 
              FROM tbl_timeslot_active 
              WHERE active = 1 AND dept_code = ? AND semester = ? AND ay_code = ?";

$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("sss", $dept_code, $semester, $ay_code); // Assuming all are strings
$stmt_fetch->execute();
$result_fetch = $stmt_fetch->get_result();

if ($result_fetch->num_rows > 0) {
    // Fetch the active time slot
    $row_fetch = $result_fetch->fetch_assoc();
    $table_start_time = $row_fetch['table_start_time'];
    $table_end_time = $row_fetch['table_end_time'];
} else {
    // Defaults if no active time slot is found
    $_SESSION['table_start_time'] = '7:00 am';
    $_SESSION['table_end_time'] = '9:00 pm';
    $table_start_time = '7:00 am';
    $table_end_time = '9:00 pm';
}

$start_timestamp = strtotime($table_start_time);
$end_timestamp = strtotime($table_end_time);

$plot_start = date("H", $start_timestamp); // Converts to 24-hour format
$plot_end = date("H", $end_timestamp);





// Initialize variables
$vacant_rooms = [];
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $day = isset($_POST['day']) ? $_POST['day'] : '';
    $time_start = isset($_POST['search_time_start']) ? date('H:i:s', strtotime($_POST['search_time_start'])) : '';
    $time_end = isset($_POST['search_time_end']) ? date('H:i:s', strtotime($_POST['search_time_end'])) : '';

    // Validate time range
    if ($time_start >= $time_end) {
        $error_message = 'The start time must be earlier than the end time.';
    } else {
        $rooms_sql = "
            SELECT r.room_code, r.room_type
            FROM tbl_rsched r
            INNER JOIN tbl_room_schedstatus rs ON r.room_sched_code = rs.room_sched_code
            WHERE r.dept_code = ? AND rs.status = 'public' AND rs.semester = ? AND rs.ay_code = ?";
        $stmt = $conn->prepare($rooms_sql);
        $stmt->bind_param('sss', $dept_code, $semester, $ay_code);
        $stmt->execute();
        $rooms_result = $stmt->get_result();

        $vacant_rooms = [];
        if ($rooms_result->num_rows > 0) {
            while ($room_row = $rooms_result->fetch_assoc()) {
                $room_code = $room_row['room_code'];
                $room_type = $room_row['room_type'];
                $plot_room_dept_code = ($room_type == "Computer Laboratory") ? $ccl_college_code : $dept_code;

                $table = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");
                $check_table_sql = "SHOW TABLES LIKE '$table'";
                $check_table_result = $conn->query($check_table_sql);

                if ($check_table_result->num_rows > 0 && isRoomAvailable($conn, $table, $room_code, $day, $time_start, $time_end, $semester, $ay_code)) {
                    $vacant_rooms[] = $room_code;
                }
            }
        } else {
            $error_message = 'No rooms available.';
        }
    }
}

function isRoomAvailable($conn, $table, $room_code, $day, $time_start, $time_end, $semester, $ay_code)
{
    $sql = "SELECT * FROM `$table` 
            WHERE room_code = ? 
            AND day = ? 
            AND semester = ? 
            AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $room_code, $day, $semester, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        return true; // Room is available
    }

    while ($row = $result->fetch_assoc()) {
        $existing_start = $row['time_start'];
        $existing_end = $row['time_end'];
        if ($time_start < $existing_end && $time_end > $existing_start) {
            error_log("Conflict detected for room: $room_code ($existing_start - $existing_end)");
            return false; // Conflict detected
        }
    }

    return true; // No conflicts
}



/**
 * Function to check if a time is out of the allowed range
 */

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
    <?php
    if ($_SESSION['user_type'] == 'Department Secretary'): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>
        <h2 id="title"><i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i> VIEW VACANT ROOM</h2>

    <?php else: ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php");
        ?>

        <div class="head-label">
            <h1 class="text-center"><?php echo htmlspecialchars($dept_name); ?></h1>
            <h5 class="text-center sem"><?php echo htmlspecialchars(string: $ay_name); ?> |
                <?php echo htmlspecialchars(string: $semester); ?>
            </h5>
        </div>
    <?php endif; ?>



    <div class="container mt-5">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <?php if ($user_type == 'Department Secretary'): ?>
                <li class="nav-item">
                    <a class="nav-link" id="section-tab" href="/SchedSys3/php/department_secretary/library/lib_section.php"
                        aria-controls="Section" aria-selected="false">Section</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="classroom-tab"
                        href="/SchedSys3/php/department_secretary/library/lib_classroom.php" aria-controls="classroom"
                        aria-selected="false">Classroom</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " id="professor-tab"
                        href="/SchedSys3/php/department_secretary/library/lib_professor.php" aria-controls="professor"
                        aria-selected="false">Instructor</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="professor-tab" href="/SchedSys3/php/department_secretary/report/majorsub_summary.php" aria-controls="professor"
                        aria-selected="false">Major Subject Summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="professor-tab" href="/SchedSys3/php/department_secretary/report/minorsub_summary.php" aria-controls="professor"
                        aria-selected="false">Minor Subject Summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link " id="professor-tab" href="/SchedSys3/php/department_secretary/report/room_summary.php"
                        aria-controls="professor" aria-selected="false">Classroom Summary</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="professor-tab" href="/SchedSys3/php/department_secretary/report/prof_summary.php" aria-controls="professor"
                        aria-selected="false">Instructor Summary</a>
                </li>
                <?php if ($user_type == 'Department Secretary'): ?>
                    <li class="nav-item">
                        <a class="nav-link active" id="vacant-room-tab"
                            href="/SchedSys3/php/viewschedules/data_schedule_vacant.php" aria-controls="vacant-room"
                            aria-selected="true" style="background-color: #FD7238; color: white;">Vacant Room</a>
                    </li>
                <?php endif; ?>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" id="professor-tab" href="data_schedule_professor.php" role="tab"
                        aria-controls="professor" aria-selected="false">Instructor</a>
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
                        <a class="nav-link active" id="vacant-room-tab" href="data_schedule_vacant.php"
                            aria-controls="vacant-room" aria-selected="false"
                            style="background-color: #FD7238; color: white;">Vacant Room</a>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>

        <div class="tab-content mt-4">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label for="day">Day</label>
                        <select class="form-control" id="day" name="day" required>
                            <option value="Monday" <?php echo (isset($day) && $day == 'Monday') ? 'selected' : ''; ?>>
                                Monday</option>
                            <option value="Tuesday" <?php echo (isset($day) && $day == 'Tuesday') ? 'selected' : ''; ?>>
                                Tuesday</option>
                            <option value="Wednesday" <?php echo (isset($day) && $day == 'Wednesday') ? 'selected' : ''; ?>>Wednesday</option>
                            <option value="Thursday" <?php echo (isset($day) && $day == 'Thursday') ? 'selected' : ''; ?>>
                                Thursday</option>
                            <option value="Friday" <?php echo (isset($day) && $day == 'Friday') ? 'selected' : ''; ?>>
                                Friday</option>
                            <option value="Saturday" <?php echo (isset($day) && $day == 'Saturday') ? 'selected' : ''; ?>>
                                Saturday</option>
                        </select>
                    </div>

                    <div class="form-group col-md-3">
                        <label for="search_time_start">Start Time</label>
                        <select class="form-control" id="search_time_start" name="search_time_start">
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
                    </div>

                    <div class="form-group col-md-3">
                        <label for="search_time_end">End Time</label>
                        <select class="form-control" id="search_time_end" name="search_time_end">
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

                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-search w-100"
                            style="background-color: #FD7238; color: white;">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="tab-content mt-4">
            <div class="container">
                <table class="table table-bordered">
                    <tbody>
                        <?php
                        if (!empty($vacant_rooms) && count($vacant_rooms) > 0) {
                            foreach ($vacant_rooms as $vacant_room) {
                                echo "<tr>";
                                echo "<td class='text-data'>" . htmlspecialchars($vacant_room) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            if (empty($_POST)) {
                                echo "<tr><td colspan='2'>Please fill up all input fields.</td></tr>";
                            } else {
                                echo "<tr><td colspan='1' class='text-center'>" . htmlspecialchars($error_message) . "</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>