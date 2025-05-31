<?php
session_start();
// Get the current user's first name, department code, ay_code, and semester from the session
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$user_dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';

$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '2425';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '1st Semester';
$color = isset($_SESSION['color']) ? $_SESSION['color'] : '#000000'; // Default to black if no color is set
$email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : 'no email'; 

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Step 1: Fetch all course codes and course names based on the user's department
$sql_courses = "SELECT course_code, course_name FROM tbl_course WHERE dept_code = ?";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->bind_param('s', $user_dept_code);
$stmt_courses->execute();
$result_courses = $stmt_courses->get_result();

$courses = [];
while ($row = $result_courses->fetch_assoc()) {
    $courses[] = $row;
}
$stmt_courses->close();



// Initialize an array to hold schedule summary data
$schedule_summary = [];

// Step 2: Collect all section_sched_code from tbl_schedstatus
$sql_schedstatus = "SELECT section_sched_code FROM tbl_schedstatus WHERE dept_code = ? AND ay_code = ? AND semester = ?";
$stmt_schedstatus = $conn->prepare($sql_schedstatus);
$stmt_schedstatus->bind_param('sss', $user_dept_code, $ay_code, $semester);
$stmt_schedstatus->execute();
$result_schedstatus = $stmt_schedstatus->get_result();

while ($row_schedstatus = $result_schedstatus->fetch_assoc()) {
    $section_sched_code = $row_schedstatus['section_sched_code'];

    // Step 3: Loop through all courses and check for schedules in dynamically named tables
    foreach ($courses as $course) {
        $sanitized_section_code = $conn->real_escape_string($section_sched_code);
        $sanitized_academic_year = $conn->real_escape_string($ay_code);

        // Dynamically generate the table name
        $table_name = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_secsched_" . $section_sched_code);
    
        // Check if the table exists (optional safety step)
        $table_exists_query = "SHOW TABLES LIKE '$table_name'";
        $table_exists_result = $conn->query($table_exists_query);
        if ($table_exists_result->num_rows > 0) {
            // Step 4: Fetch schedules from the dynamic table
            $sql_schedule = "SELECT * FROM $table_name WHERE course_code = ?";
            $stmt_schedule = $conn->prepare($sql_schedule);
            $stmt_schedule->bind_param('s', $course['course_code']);
            $stmt_schedule->execute();
            $result_schedule = $stmt_schedule->get_result();

            // Step 5: Collect and store the schedule data
            while ($row_schedule = $result_schedule->fetch_assoc()) {
                $schedule_summary[] = [
                    'course_code' => $course['course_code'],
                    'course_name' => $course['course_name'],
                    'section_sched_code' => $section_sched_code,
                    'schedule' => $row_schedule // This contains all the schedule details
                ];
            }

            $stmt_schedule->close();
        }
    }
}

$stmt_schedstatus->close();
$conn->close();

// Step 6: Display the summarized schedule data
echo "<h2>Schedule Summary for Department: " . $user_dept_code . "</h2>";
if (!empty($schedule_summary)) {
    echo "<div style='text-align: center; font-weight: bold; font-size: 18px;'>COMPUTER SCIENCE</div>";
    
    echo "<table border='1' cellspacing='0' cellpadding='5' style='border-collapse: collapse; width: 100%; text-align: center;'>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>
            <th>COURSE CODE</th>
            <th>COURSE TITLE</th>
            <th>SECTION</th>
            <th>LEC OR LAB</th>
            <th>ROOM</th>
            <th>INSTRUCTOR</th>
            <th>SCHEDULE</th>
          </tr>";

    foreach ($schedule_summary as $schedule) {
        // Extract relevant details from the schedule
        $course_code = $schedule['course_code'];
        $course_name = $schedule['course_name'];
        $section_sched_code = $schedule['section_sched_code'];
        $class_type = isset($schedule['schedule']['class_type']) ? $schedule['schedule']['class_type'] : 'N/A'; // Lec or Lab
        $room = isset($schedule['schedule']['room_code']) ? $schedule['schedule']['room_code'] : 'N/A';
        $prof = isset($schedule['schedule']['prof_code']) ? $schedule['schedule']['prof_code'] : 'N/A';
        $time_start = isset($schedule['schedule']['time_start']) ? $schedule['schedule']['time_start'] : 'N/A';
        $time_end = isset($schedule['schedule']['time_end']) ? $schedule['schedule']['time_end'] : 'N/A';
        $day = isset($schedule['schedule']['day']) ? $schedule['schedule']['day'] : 'N/A';

        // Combine time and day details
        $schedule_details = "$day $time_start-$time_end";

        echo "<tr>";
        echo "<td>" . $course_code . "</td>";
        echo "<td>" . $course_name . "</td>";
        echo "<td>" . $section_sched_code . "</td>";
        echo "<td>" . $class_type . "</td>";
        echo "<td>" . $room . "</td>";
        echo "<td>" . $prof . "</td>";
        echo "<td>" . $schedule_details . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<div style='text-align: center;'>No schedules found.</div>";
}



?>
