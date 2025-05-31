<?php
include "server.php";
session_start();

// Check if dept_code is set in the session, if not, redirect to login.php
if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head')) {
    header("Location: ../login/login.php");
    exit();
}


if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$user_dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '2425';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '1st Semester';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';
$_SESSION['last_page'] = 'sharedSchedule.php';


$fetch_info_query = "SELECT reg_adviser,college_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];

    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser";
    } else {
        $current_user_type = $user_type;
    }
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}



$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}

$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}


// echo $admin_college_code;
// echo $college_code;

$filter_type = isset($_POST['filter_type']) ? $_POST['filter_type'] : 'shared_with_you';

date_default_timezone_set('Asia/Manila');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
        $_SESSION['section_sched_code'] = $_POST['section_sched_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $_SESSION['section_code'] = $_POST['section_code'];
        $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';

        // Store the filter type in a session variable to access it after redirection
        $_SESSION['filter_type'] = $_POST['filter_type'];

        $section_code = $_POST['section_code'];
        $semester = $_POST['semester'];
        $sender_email = $_POST['sender_email'];
        $receiver_email = $_POST['receiver_email'];

        // Prepare the query to check if a notification for this section, sender, and receiver already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE section_code = ? AND semester = ? AND sender_email = ? AND receiver_email = ?");
        $stmt->bind_param("ssss", $section_code, $semester, $sender_email, $receiver_email);
        $stmt->execute();
        $stmt->bind_result($notification_count);
        $stmt->fetch();
        $stmt->close();

        // If no existing notification, insert a new one
        $receiver_email = $_POST['receiver_email'];

        // Check if the user is the sender of the shared schedule
        if ($sender_email !== $receiver_email) {
            // Prepare the query to check if a notification for this section, sender, and receiver already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_notifications WHERE section_code = ? AND semester = ? AND sender_email = ? AND receiver_email = ?");
            $stmt->bind_param("ssss", $section_code, $semester, $sender_email, $receiver_email);
            $stmt->execute();
            $stmt->bind_result($notification_count);
            $stmt->fetch();
            $stmt->close();
            // If no existing notification, insert a new one
            if ($notification_count == 0) {
                // Prepare the message and other notification details
                $message = "Schedule for $section_code has been opened.";
                $is_read = 0;
                $date_sent = date('Y-m-d H:i:s'); // Get current date and time
                // Insert the notification into the database
                $stmt = $conn->prepare("
                    INSERT INTO tbl_notifications 
                        (section_code, semester, sender_email, receiver_email, message, is_read, date_sent)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssis", $section_code, $semester, $sender_email, $receiver_email, $message, $is_read, $date_sent);
                $stmt->execute();
                $stmt->close();
            }
        }
        // Redirect based on the filter type
        if ($_SESSION['filter_type'] === 'shared_by_you') {
            header("Location: create_sched/plotSchedule.php");
            exit();
        } elseif ($_SESSION['filter_type'] === 'shared_with_you') {
            header("Location: create_sched/plotSchedule.php");
            exit();
        } else {
            // Handle invalid filter type if necessary
            echo "Invalid filter type!";
            exit();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $section_sched_code = $_POST['section_sched_code'];
        $semester = $_POST['semester'];
        $ay_code = $_POST['ay_code'];
        $section_code = $_POST['section_code'];
        $sender_email = $_POST['sender_email'];
        $receiver_email = $_POST['receiver_email'];
        $filter_type = $_POST['filter_type'];

        // Check filter type and prepare the appropriate SQL statement
        if ($filter_type === 'shared_by_you') {
            $sql = "
                DELETE FROM tbl_shared_sched
                WHERE shared_section = ? AND semester = ? AND receiver_email = ? AND sender_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $receiver_email, $sender_email);
        } elseif ($filter_type === 'shared_with_you') {
            $sql = "
                DELETE FROM tbl_shared_sched
                WHERE shared_section = ? AND semester = ? AND sender_email = ? AND receiver_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $sender_email, $receiver_email);
        } else {
            echo "<script>alert('Invalid filter type. No action taken.'); window.location.href='sharedSchedule.php';</script>";
            exit(); // Stop further execution
        }

        // Execute the prepared statement and handle the result
        if (!$stmt->execute()) {
            echo "<script>alert('Error deleting record: " . $conn->error . "'); window.location.href='sharedSchedule.php';</script>";
        }
        $stmt->close();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'stop') {
        $section_sched_code = $_POST['section_sched_code'];
        $semester = $_POST['semester'];
        $ay_code = $_POST['ay_code'];
        $section_code = $_POST['section_code'];
        $sender_email = $_POST['sender_email'];
        $receiver_email = $_POST['receiver_email'];
        $filter_type = $_POST['filter_type'];

        // Check filter type and prepare the appropriate SQL UPDATE statement
        if ($filter_type === 'shared_by_you') {
            $sql = "
            UPDATE tbl_shared_sched
            SET status = 'inactive'
            WHERE shared_section = ? AND semester = ? AND receiver_email = ? AND sender_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $receiver_email, $sender_email);
        } elseif ($filter_type === 'shared_with_you') {
            $sql = "
            UPDATE tbl_shared_sched
            SET status = 'inactive'
            WHERE shared_section = ? AND semester = ? AND sender_email = ? AND receiver_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $sender_email, $receiver_email);
        } else {
            echo "<script>alert('Invalid filter type. No action taken.'); window.location.href='sharedSchedule.php';</script>";

            exit(); // Stop further execution
        }

        // Execute the prepared statement and handle the result
        if (!$stmt->execute()) {
            echo "<script>alert('Error updating record: " . $conn->error . "'); window.location.href='sharedSchedule.php';</script>";
        } else {
            $messages[] = 'Shared schedule is now close for plotting.';

                        // Prepare notification details
            $message = "Schedule for $section_code is now close for plotting.";
            $is_read = 0;
            $date_sent = date('Y-m-d H:i:s');

            // Insert the notification into the database
            $stmt->close(); // Close the previous statement before reusing $stmt

            $stmt = $conn->prepare("
        INSERT INTO tbl_notifications 
            (section_code, semester, ay_code, sender_email, receiver_email, message, is_read, date_sent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
            $stmt->bind_param("ssssssis", $section_code, $semester, $ay_code, $sender_email, $receiver_email, $message, $is_read, $date_sent);
            $stmt->execute();
            $stmt->close(); // Only close once at the end
        }
        
    } elseif (isset($_POST['action']) && $_POST['action'] == 'continue') {
        $section_sched_code = $_POST['section_sched_code'];
        $semester = $_POST['semester'];
        $ay_code = $_POST['ay_code'];
        $section_code = $_POST['section_code'];
        $sender_email = $_POST['sender_email'];
        $receiver_email = $_POST['receiver_email'];
        $filter_type = $_POST['filter_type'];

        // Check filter type and prepare the appropriate SQL UPDATE statement
        if ($filter_type === 'shared_by_you') {
            $sql = "
            UPDATE tbl_shared_sched
            SET status = 'active'
            WHERE shared_section = ? AND semester = ? AND receiver_email = ? AND sender_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $receiver_email, $sender_email);
        } elseif ($filter_type === 'shared_with_you') {
            $sql = "
            UPDATE tbl_shared_sched
            SET status = 'active'
            WHERE shared_section = ? AND semester = ? AND sender_email = ? AND receiver_email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $section_sched_code, $semester, $sender_email, $receiver_email);
        } else {
            echo "<script>alert('Invalid filter type. No action taken.'); window.location.href='sharedSchedule.php';</script>";
            exit(); // Stop further execution
        }

        // Execute the prepared statement and handle the result
        if (!$stmt->execute()) {
            echo "<script>alert('Error updating record: " . $conn->error . "'); window.location.href='sharedSchedule.php';</script>";
        } else {
            $messages[] = 'Shared schedule is now open for plotting.';

            // Prepare notification details
            $message = "Schedule for $section_code is now open for plotting.";
            $is_read = 0;
            $date_sent = date('Y-m-d H:i:s');

            // Insert the notification into the database
            $stmt->close(); // Close the previous statement before reusing $stmt

            $stmt = $conn->prepare("
        INSERT INTO tbl_notifications 
            (section_code, semester, ay_code, sender_email, receiver_email, message, is_read, date_sent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
            $stmt->bind_param("ssssssis", $section_code, $semester, $ay_code, $sender_email, $receiver_email, $message, $is_read, $date_sent);
            $stmt->execute();
            $stmt->close(); // Only close once at the end

        }

    }

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
    }

}



$email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : 'no email';
$search_ay_code = isset($_POST['search_ay_code']) ? trim($_POST['search_ay_code']) : $ay_code;
$search_semester = isset($_POST['search_semester']) ? trim($_POST['search_semester']) : $semester;
$search_section = isset($_POST['search_section']) ? trim($_POST['search_section']) : '';

// Get filter type from POST request or default to 'shared_with_you'
$sql = "
  SELECT DISTINCT
    tbl_shared_sched.shared_section, 
    tbl_shared_sched.semester, 
    tbl_shared_sched.receiver_dept_code, 
    tbl_shared_sched.sender_dept_code, 
    tbl_shared_sched.ay_code, 
    tbl_shared_sched.sender_email, 
    tbl_shared_sched.receiver_email, 
    tbl_shared_sched.status,  -- Added the status field
    tbl_secschedlist.section_code,
    tbl_schedstatus.dept_code AS dept_code, 
    sender_prof.college_code AS sender_college_code, 
    receiver_prof.college_code AS receiver_college_code
FROM tbl_shared_sched
INNER JOIN tbl_secschedlist 
    ON tbl_shared_sched.shared_section = tbl_secschedlist.section_sched_code
INNER JOIN tbl_schedstatus
    ON tbl_shared_sched.shared_section = tbl_schedstatus.section_sched_code 
INNER JOIN tbl_prof_acc AS sender_prof 
    ON tbl_shared_sched.sender_email = sender_prof.cvsu_email
INNER JOIN tbl_prof_acc AS receiver_prof 
    ON tbl_shared_sched.receiver_email = receiver_prof.cvsu_email
WHERE tbl_shared_sched.ay_code = ? 
  AND tbl_shared_sched.semester = ?


"
;



// Modify the query based on the selected filter type
if ($filter_type === 'shared_by_you') {
    $sql .= " AND tbl_shared_sched.sender_email = ?";
} else {
    $sql .= " AND tbl_shared_sched.receiver_email = ?";
}


if (!empty($search_section)) {
    $sql .= " AND TRIM(tbl_secschedlist.section_code) LIKE '%$search_section%'";
}

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $ay_code, $search_semester, $email);
$stmt->execute();
$result = $stmt->get_result();

// Process the result set
$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = $row; // Collect all rows
}



if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['section_id'])) {
    $section_id = $_GET['section_id'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    $dept_code = $_GET['dept_code'];
    // $selected_ay_code = $_SESSION['selected_ay_code'];

    // Fetch the section schedule code based on the section_id, semester, and selected academic year
    $sql = "SELECT * FROM tbl_secschedlist WHERE section_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $stmt->bind_param("sss", $section_id, $dept_code, $ay_code);

    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_sched_code = $row['section_sched_code'];
        echo "<h5>Schedule for Section: " . htmlspecialchars($section_id) . "</h5>";
        echo fetchScheduleForSec($section_sched_code, $ay_code, $semester, $dept_code);
        // echo "$selected_ay_code";
    } else {
        echo "<p>No schedule found for this Section.</p>";

    }

    $stmt->close();
    $conn->close();
    exit;
}

function fetchScheduleForSec($section_sched_code, $ay_code, $semester, $dept_code)
{
    global $conn;
    // Fetch section information from tbl_secschedlist
    $sql_fetch_section_info = "SELECT section_code, program_code, ay_code, dept_code 
                               FROM tbl_secschedlist 
                               WHERE section_sched_code=? 
                               AND dept_code=? 
                               AND ay_code=?";
    $stmt_section_info = $conn->prepare($sql_fetch_section_info);
    $stmt_section_info->bind_param("sss", $section_sched_code, $dept_code, $ay_code);
    $stmt_section_info->execute();
    $result_section_info = $stmt_section_info->get_result();

    if (!$result_section_info || $result_section_info->num_rows === 0) {
        echo "<p>No Available Section Schedule</p>";
        echo "<pre>";
        echo "Debugging: Query returned no results!<br>";
        echo "AY Code Used: " . htmlspecialchars($ay_code) . "<br>";
        echo "Department Code Used: " . htmlspecialchars($section_sched_code) . "<br>";
        echo "</pre>";

    }

    $row_section_info = $result_section_info->fetch_assoc();
    $dept_code = $row_section_info['dept_code'];

    // Sanitize the table name for the section schedule
    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");


    try {
        // Fetch the section's schedule including cell_color from the sanitized table
        $sql_fetch_section_schedule = "SELECT day, time_start, time_end, course_code, room_code, prof_name, prof_code, class_type, cell_color 
                                       FROM $sanitized_section_sched_code 
                                       WHERE semester = ? AND section_sched_code = ? AND ay_code = ?";
        $stmt_section_schedule = $conn->prepare($sql_fetch_section_schedule);
        $stmt_section_schedule->bind_param("sss", $semester, $section_sched_code, $ay_code);
        $stmt_section_schedule->execute();
        $result_section_schedule = $stmt_section_schedule->get_result();

        if ($result_section_schedule->num_rows > 0) {
            $schedule_data = [];
            while ($row_schedule = $result_section_schedule->fetch_assoc()) {
                $day = ucfirst(strtolower($row_schedule['day']));
                $time_start = $row_schedule['time_start'];
                $time_end = $row_schedule['time_end'];
                $course_code = $row_schedule['course_code'];
                $room_code = $row_schedule['room_code'];
                $prof_name = $row_schedule['prof_name'];
                $prof_code = $row_schedule['prof_code'];
                $class_type = $row_schedule['class_type'];
                $cell_color = $row_schedule['cell_color'];

                // Format class_type for display
                $class_type = match ($class_type) {
                    "lec" => "Lecture",
                    "lab" => "Laboratory",
                    default => ucfirst($class_type),
                };

                // Populate the schedule data array
                $schedule_data[$day][] = [
                    'time_start' => $time_start,
                    'time_end' => $time_end,
                    'course_code' => $course_code,
                    'room_code' => $room_code,
                    'prof_name' => $prof_name,
                    'prof_code' => $prof_code,
                    'class_type' => $class_type,
                    'cell_color' => $cell_color, // Fetch and include cell color directly
                ];
            }
            return generateScheduleTable($conn, $schedule_data);
        } else {
            return '<p>No AvailableFFF Section Schedule</p>';

        }
    } catch (Exception $e) {
        return '<p>Error fetching schedule: ' . $e->getMessage() . '</p>';
    }
}


function generateScheduleTable($conn, $schedule_data)
{
    $dept_code = $_SESSION['dept_code'];
    $semester = $_SESSION['semester'];

    // Fetch active time slot configuration
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
        $table_start_time = strtotime('7:00 AM');
        $table_end_time = strtotime('7:00 PM');
    }

    // Generate the HTML table
    $html = '<div class="schedule-table-container">';
    $html .= '<table class="table table-bordered schedule-table">';
    $html .= '<thead><tr><th style="width: 10%;">Time</th>';

    // Define column headers for days
    $day_names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($day_names as $day_name) {
        $html .= '<th style="width: 14.67%;">' . $day_name . '</th>';
    }
    $html .= '</tr></thead>';
    $html .= '<tbody>';

    // Generate 30-minute time slots from start to end time
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

            if (isset($schedule_data[$day_name])) {
                foreach ($schedule_data[$day_name] as $index => $schedule) {
                    $schedule_start = strtotime($schedule['time_start']);
                    $schedule_end = strtotime($schedule['time_end']);
                    $current_start = strtotime($start_time);
                    $current_end = strtotime($end_time);

                    if ($current_start < $schedule_end && $current_end > $schedule_start) {
                        $cell_content = "<b>{$schedule['course_code']}</b><span style='font-weight: bold;'> <br>{$schedule['class_type']}</span><br>";

                        // Only display room_code if it's not empty
                        if (!empty($schedule['room_code'])) {
                            $cell_content .= "{$schedule['room_code']}<br>";
                        }

                        if (!empty($schedule['prof_name'])) {
                            $cell_content .= "{$schedule['prof_name']}";
                        } else {
                            $cell_content .= "{$schedule['prof_code']}"; // Display prof_code if prof_name is empty
                        }

                        $cell_content .= "</div>";

                        $rowspan = ceil(($schedule_end - $schedule_start) / 1800);
                        $remaining_rowspan[$day_name] = $rowspan - 1;
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
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS - Draft</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="../../css/department_secretary/sharedschedule.css">
</head>

<body>


    <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code == $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
    <?php endif; ?>

    <?php if ($_SESSION['user_type'] == 'Department Secretary' && $admin_college_code != $user_college_code): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php"); ?>
    <?php endif; ?>

    <!-- CONTENT -->

    <div class="container mt-3">
        <h2 class="title"> <i class="fa-solid fa-user-group" style="color: #FD7238;"></i> SHARED SCHEDULE</h2>
        <br><br>
        <form method="POST" action="" class="row mb-4"><br>
            <div class="col-md-3">
            </div>
            <div class="col-md-3">
                <?php if ($admin_college_code == $college_code): ?>
                    <select class="form-control" name="filter_type">
                        <option value="shared_with_you" id="dropdown-item" <?php echo ($filter_type === 'shared_with_you') ? 'selected' : ''; ?>>
                            Shared with You</option>
                        <option value="shared_by_you" id="dropdown-item" <?php echo ($filter_type === 'shared_by_you') ? 'selected' : ''; ?>>
                            Shared by You</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="col-md-3">
                <input type="text" class="form-control" name="search_section"
                    value="<?php echo htmlspecialchars($search_section); ?>" placeholder="Section">
            </div>

            <div class="col-md-3">
                <button type="submit" id="searchbtn" class="btn w-100">Search</button>
            </div>
        </form>
        <table class="table">
            <thead id="thead">
                <tr>
                    <th>Section</th>
                    <th>Email</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($schedules)) {
                    foreach ($schedules as $row) {
                        $status = htmlspecialchars($row['status']);
                        $isInactive = ($status === 'inactive');
                        $showButtons = ($user_dept_code === htmlspecialchars($row['dept_code']));

                        echo "<tr id = 'schedule_modal' class='clickable-row' style='cursor:pointer;'
            data-section-id='" . htmlspecialchars($row['section_code']) . "'
            data-dept-code='" . htmlspecialchars($row['dept_code']) . "'
            data-semester='" . htmlspecialchars($row['semester']) . "'
            data-ay-code='" . htmlspecialchars($row['ay_code']) . "'>";

                        echo "<td>" . htmlspecialchars($row['section_code']) . "</td>";

                        echo "<td>";
                        if ($filter_type === 'shared_by_you') {
                            echo "Shared with: " . htmlspecialchars($row['receiver_email']) . "<br>";
                            echo "College: " . htmlspecialchars($row['receiver_college_code']) . "<br>";
                        } else {
                            echo "Shared by: " . htmlspecialchars($row['sender_email']) . "<br>";
                            echo "College: " . htmlspecialchars($row['sender_college_code']) . "<br>";
                            echo "Department: " . htmlspecialchars($row['sender_dept_code']) . "<br>";
                        }
                        echo "</td>";

                        echo "<td>";
                        echo "<form method='POST' action=''>
            <input type='hidden' name='filter_type' value='" . htmlspecialchars($filter_type) . "'>
            <input type='hidden' name='sender_email' value='" . htmlspecialchars($current_user_email) . "'>
            <input type='hidden' name='receiver_email' value='" . htmlspecialchars($row['sender_email']) . "'>
            <input type='hidden' name='section_sched_code' value='" . htmlspecialchars($row['shared_section']) . "'>
            <input type='hidden' name='semester' value='" . htmlspecialchars($row['semester']) . "'>
            <input type='hidden' name='ay_code' value='" . htmlspecialchars($row['ay_code']) . "'>
            <input type='hidden' name='section_code' value='" . htmlspecialchars($row['section_code']) . "'>
            <input type='hidden' name='edit' value='1'>";

                        if ($showButtons || !$isInactive) {
                            echo "<div class='btn-group'>
                <button type='button' class='edit-btn' onclick='startEditing(this); event.stopPropagation();'>
                    <i class='fa-solid fa-pen-to-square'></i>
                </button>";
                        } else {
                            echo "<div class='btn-group'>";
                        }

                        if ($showButtons) {
                            echo "<button type='button' class='delete-btn' data-bs-toggle='modal' data-bs-target='#deleteConfirmationModal'
                onclick=\"event.stopPropagation(); confirmDelete(
                    '" . addslashes(htmlspecialchars($row['shared_section'])) . "',
                    '" . addslashes(htmlspecialchars($row['semester'])) . "',
                    '" . addslashes(htmlspecialchars($row['ay_code'])) . "',
                    '" . addslashes(htmlspecialchars($row['section_code'])) . "',
                    '" . addslashes(htmlspecialchars($row['sender_email'])) . "',
                    '" . addslashes(htmlspecialchars($row['receiver_email'])) . "',
                    '" . addslashes(htmlspecialchars($filter_type)) . "'
                )\">
                <i class='fa-solid fa-trash'></i>
            </button>";

                            if ($row['status'] === 'active') {
                                echo "<button type='button' class='stop-btn' data-bs-toggle='modal' data-bs-target='#stopConfirmationModal'
                    onclick=\"event.stopPropagation(); confirmStop(
                        '" . addslashes(htmlspecialchars($row['shared_section'])) . "',
                        '" . addslashes(htmlspecialchars($row['semester'])) . "',
                        '" . addslashes(htmlspecialchars($row['ay_code'])) . "',
                        '" . addslashes(htmlspecialchars($row['section_code'])) . "',
                        '" . addslashes(htmlspecialchars($row['sender_email'])) . "',
                        '" . addslashes(htmlspecialchars($row['receiver_email'])) . "',
                        '" . addslashes(htmlspecialchars($filter_type)) . "'
                    )\">
                    <i class='fa-solid fa-ban'></i>
                </button>";
                            } else {
                                echo "<button type='button' class='stop-btn' data-bs-toggle='modal' data-bs-target='#continueConfirmationModal'
                    onclick=\"event.stopPropagation(); confirmContinue(
                        '" . addslashes(htmlspecialchars($row['shared_section'])) . "',
                        '" . addslashes(htmlspecialchars($row['semester'])) . "',
                        '" . addslashes(htmlspecialchars($row['ay_code'])) . "',
                        '" . addslashes(htmlspecialchars($row['section_code'])) . "',
                        '" . addslashes(htmlspecialchars($row['sender_email'])) . "',
                        '" . addslashes(htmlspecialchars($row['receiver_email'])) . "',
                        '" . addslashes(htmlspecialchars($filter_type)) . "'
                    )\">
                    <i class='fa-solid fa-rotate-right'></i>
                </button>";
                            }
                        }

                        echo "</div></form>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No records found</td></tr>";
                }
                ?>

            </tbody>


        </table>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method='POST' action=''>
                    <div class="modal-body">
                        <p>Are you sure you want to remove this schedule?</p>
                        <input type='hidden' id='modal_section_sched_code' name='section_sched_code'>
                        <input type='hidden' id='modal_semester' name='semester'>
                        <input type='hidden' id='modal_ay_code' name='ay_code'>
                        <input type='hidden' id='modal_section_code' name='section_code'>
                        <input type='hidden' id='modal_sender_email' name='sender_email'>
                        <input type='hidden' id='modal_receiver_email' name='receiver_email'>
                        <input type='hidden' id='modal_filter_type' name='filter_type'>
                    </div>
                    <div class="modal-footer">
                        <button type='submit' name='action' value='delete' class='btn' id="btnYes">Yes</button>
                        <button type="button" class="btn" data-bs-dismiss="modal" id="btnNo">No</button>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="stopConfirmationModal" tabindex="-1" aria-labelledby="stopConfirmationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stopConfirmationModalLabel">Stop Sharing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method='POST' action=''>
                    <div class="modal-body">
                        <p>Are you sure you want to stop sharing this schedule?</p>
                        <input type='hidden' id='modal_section_sched_code_stop' name='section_sched_code'>
                        <input type='hidden' id='modal_semester_stop' name='semester'>
                        <input type='hidden' id='modal_ay_code_stop' name='ay_code'>
                        <input type='hidden' id='modal_section_code_stop' name='section_code'>
                        <input type='hidden' id='modal_sender_email_stop' name='sender_email'>
                        <input type='hidden' id='modal_receiver_email_stop' name='receiver_email'>
                        <input type='hidden' id='modal_filter_type_stop' name='filter_type'>
                    </div>
                    <div class="modal-footer">
                        <button type='submit' name='action' value='stop' class='btn' id="btnYes">Yes</button>
                        <button type="button" class="btn" data-bs-dismiss="modal" id="btnNo">No</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="continueConfirmationModal" tabindex="-1"
        aria-labelledby="continueConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="continueConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method='POST' action=''>
                    <div class="modal-body">
                        <p>Are you sure you want to open this schedule?</p>
                        <input type='hidden' id='modal_section_sched_code_continue' name='section_sched_code'>
                        <input type='hidden' id='modal_semester_continue' name='semester'>
                        <input type='hidden' id='modal_ay_code_continue' name='ay_code'>
                        <input type='hidden' id='modal_section_code_continue' name='section_code'>
                        <input type='hidden' id='modal_sender_email_continue' name='sender_email'>
                        <input type='hidden' id='modal_receiver_email_continue' name='receiver_email'>
                        <input type='hidden' id='modal_filter_type_continue' name='filter_type'>
                    </div>
                    <div class="modal-footer">
                        <button type='submit' name='action' value='continue' class='btn' id="btnYes">Yes</button>
                        <button type="button" class="btn" data-bs-dismiss="modal" id="btnNo">No</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewScheduleModal" tabindex="-1" aria-labelledby="viewScheduleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewScheduleModalLabel">Section Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modal-schedule-content">
                    <div class="text-center">
                        <div class="spinner-border" role="status" id="loadingSpinner" style="display: none;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
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


    <style>
        #deleteConfirmationModal .modal-dialog {
            max-width: 30%;
            margin: 1.5rem auto;
        }

        .modal-content {
            border-radius: 1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-body {
            padding: 1rem;
        }
    </style>

    <script>


        function redirectToPlotSchedule(params) {
            history.pushState(null, null, 'sharedSchedule.php');
            window.location.href = '/SchedSys3/php/department_secretary/create_sched/plotSchedule.php?' + params.toString();
        }

        function startEditing(button) {
            const form = button.closest('form');
            const formData = new FormData(form);

            fetch('sharedSchedule.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text())
                .then(() => {
                    const params = new URLSearchParams();
                    formData.forEach((value, key) => {
                        params.append(key, value);
                    });

                    redirectToPlotSchedule(params);
                }).catch(error => {
                    console.error('Error logging notification:', error);
                    const params = new URLSearchParams();
                    formData.forEach((value, key) => {
                        params.append(key, value);
                    });

                    redirectToPlotSchedule(params);
                });
        }


        function confirmDelete(section_sched_code, semester, ay_code, section_code, sender_email, receiver_email, filter_type) {
            // Set the values in the modal
            document.getElementById('modal_section_sched_code').value = section_sched_code;
            document.getElementById('modal_semester').value = semester;
            document.getElementById('modal_ay_code').value = ay_code;
            document.getElementById('modal_section_code').value = section_code;
            document.getElementById('modal_sender_email').value = sender_email;
            document.getElementById('modal_receiver_email').value = receiver_email;
            document.getElementById('modal_filter_type').value = filter_type;
        }

        function confirmStop(section_sched_code, semester, ay_code, section_code, sender_email, receiver_email, filter_type) {
            // Set the values in the modal
            document.getElementById('modal_section_sched_code_stop').value = section_sched_code;
            document.getElementById('modal_semester_stop').value = semester;
            document.getElementById('modal_ay_code_stop').value = ay_code;
            document.getElementById('modal_section_code_stop').value = section_code;
            document.getElementById('modal_sender_email_stop').value = sender_email;
            document.getElementById('modal_receiver_email_stop').value = receiver_email;
            document.getElementById('modal_filter_type_stop').value = filter_type;
        }

        function confirmContinue(section_sched_code, semester, ay_code, section_code, sender_email, receiver_email, filter_type) {
            // Set the values in the modal
            document.getElementById('modal_section_sched_code_continue').value = section_sched_code;
            document.getElementById('modal_semester_continue').value = semester;
            document.getElementById('modal_ay_code_continue').value = ay_code;
            document.getElementById('modal_section_code_continue').value = section_code;
            document.getElementById('modal_sender_email_continue').value = sender_email;
            document.getElementById('modal_receiver_email_continue').value = receiver_email;
            document.getElementById('modal_filter_type_continue').value = filter_type;
        }

        $(document).ready(function () {
            $('.clickable-row').on('click', function (e) {
                if ($(e.target).closest('button').length) {
                    return;
                }

                var sectionId = $(this).data('section-id');
                var semester = $(this).data('semester');
                var ayCode = $(this).data('ay-code');
                var DeptCode = $(this).data('dept-code');

                $('#loadingSpinner').show();
                $('#modal-schedule-content').html('');

                $('#viewScheduleModal').modal('show');

                $.ajax({
                    url: 'sharedSchedule.php', // make sure you have this file
                    type: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        section_id: sectionId,
                        semester: semester,
                        ay_code: ayCode,
                        dept_code: DeptCode

                    },
                    success: function (response) {
                        $('#loadingSpinner').hide();
                        $('#modal-schedule-content').html(response);
                    },
                    error: function (xhr, status, error) {
                        $('#loadingSpinner').hide();
                        $('#modal-schedule-content').html('<p class="text-danger">Error loading schedule. Please try again.</p>');
                    }
                });
            });
        });


    </script>



    </script>
</body>

</html>