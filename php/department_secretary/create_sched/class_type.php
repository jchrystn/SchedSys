<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : null;
$course_code = isset($_GET['course_code']) ? $_GET['course_code'] : null;
$new_course_code = isset($_GET['new_course_code']) ? $_GET['new_course_code'] : null;
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : null;
$program_code = isset($_GET['program_code']) ? $_GET['program_code'] : null;
$section_sched_code = isset($_GET['section_sched_code']) ? $_GET['section_sched_code'] : null;
$user_dept_code = isset($_GET['user_dept_code']) ? $_GET['user_dept_code'] : null;
$sched_dept_code = isset($_GET['sched_dept_code']) ? $_GET['sched_dept_code'] : null;
$section_dept_code = isset($_GET['section_dept_code']) ? $_GET['section_dept_code'] : null;
$ay_code = isset($_GET['ay_code']) ? $_GET['ay_code'] : null;
$semester = isset($_GET['semester']) ? $_GET['semester'] : null;


$response = [
    'lec_available' => false,
    'lab_available' => false
];

// // Function to check course availability
// function checkNewCourseAvailability($conn, $course_code, $program_code, $year_level,&$response) {

//     if ($course_code) {
//         $sql = "SELECT lec_hrs, lab_hrs FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? ";
//         $stmt = $conn->prepare($sql);
//         $stmt->bind_param("sss", $course_code,$program_code,$year_level);
//         $stmt->execute();
//         $result = $stmt->get_result();

//         if ($result->num_rows > 0) {
//             $row = $result->fetch_assoc();
//             $response['lec_available'] = ($row['lec_hrs'] > 0);
//             $response['lab_available'] = ($row['lab_hrs'] > 0);
//         }
//         $stmt->close();
//     }
// }

// checkNewCourseAvailability($conn, $new_course_code,$program_code,$year_level, $response);


function checkCourseAvailability(
    $conn,
    $course_code,
    $program_code,
    $year_level,
    $section_sched_code,
    $section_dept_code,
    $ay_code,
    $semester,
    $user_type,
    &$response
) {
    // Sanitize table name
    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");

    $fetch_info_query = "SELECT college_code FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $section_college_code = $row['college_code'];
    } else {
        echo "Dept: Error: No matching section schedule found for code '$section_sched_code'.";
    }

    $query_college_code = "SELECT college_code FROM tbl_prof_acc WHERE user_type = 'CCL Head'";
    $college_result = $conn->query($query_college_code);

    if ($college_result->num_rows > 0) {
        $row = $college_result->fetch_assoc();
        $ccl_college_code = $row['college_code'];
    } else {
        echo "Dept: Error: No matching section schedule found for code '$section_sched_code'.";
    }


    // Base course availability check
    if ($course_code) {
        // Fetch lecture and lab hours along with allowed room types
        $sql = "SELECT lec_hrs, lab_hrs, allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $course_code, $program_code, $year_level);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lec_hrs = $row['lec_hrs'];
            $lab_hrs = $row['lab_hrs'];
            // $allowed_rooms = $row['allowed_rooms'];
            $computer_room = $row['computer_room'];

            // Set initial availability based on user type
            if ($user_type === 'Department Secretary' && $section_college_code == $ccl_college_code ) {
                $response['lec_available'] = ($lec_hrs > 0);
                $response['lab_available'] = ($lab_hrs > 0 && $computer_room == 0);

            }elseif ($user_type === 'Department Secretary' && $section_college_code !=  $ccl_college_code ) {
                $response['lec_available'] = ($lec_hrs > 0);
                $response['lab_available'] = ($lab_hrs > 0 || $computer_room == 1);
            }elseif ($user_type === 'CCL Head') {
                $response['lec_available'] = false; // CCL Head does not handle lectures
                $response['lab_available'] = ($lab_hrs > 0);
            } else {
                $response['error'] = "Unauthorized user type.";
                return;
            }
        } else {
            $response['error'] = "Course not found.";
            return;
        }
        $stmt->close();
    } else {
        $response['error'] = "No course code provided.";
        return;
    }

    // Check if the course already has a scheduled lecture or lab
    $sql_check_schedule = "SELECT class_type, time_start, time_end 
                           FROM {$sanitized_section_sched_code} 
                           WHERE course_code = ? AND section_sched_code = ? AND semester = ?";
    $stmt_check = $conn->prepare($sql_check_schedule);
    $stmt_check->bind_param("sss", $course_code, $section_sched_code, $semester);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    $plotted_lec_hours = 0;
    $plotted_lab_hours = 0;


    // Calculate total plotted hours for lecture and lab
    while ($row_check = $result_check->fetch_assoc()) {
        $time_start_dt = new DateTime($row_check['time_start']);
        $time_end_dt = new DateTime($row_check['time_end']);
        $duration = $time_start_dt->diff($time_end_dt);
        $duration_hours = $duration->h + ($duration->i / 60);

        if ($row_check['class_type'] === 'lec') {
            $plotted_lec_hours += $duration_hours;
        } elseif ($row_check['class_type'] === 'lab') {
            $plotted_lab_hours += $duration_hours;
        }
    }

    // Check if the course's lecture or laboratory hours have been met
    $lec_met = $plotted_lec_hours >= $lec_hrs;
    $lab_met = $plotted_lab_hours >= $lab_hrs;

    // Update availability based on plotted hours
    $response['lec_available'] = $response['lec_available'] && !$lec_met;
    $response['lab_available'] = $response['lab_available'] && !$lab_met;

    // Add only the available components to the response
    if ($response['lec_available'] && !$response['lab_available']) {
        $response['available'] = 'Lecture';
    } elseif (!$response['lec_available'] && $response['lab_available']) {
        $response['available'] = 'Laboratory';
    } elseif ($response['lec_available'] && $response['lab_available']) {
        $response['available'] = 'Lecture and Laboratory';
    } else {
        $response['available'] = 'Lecture and Laboratory';
    }

    
    $stmt_check->close();
}


checkCourseAvailability(
    $conn,
    $course_code,
    $program_code,
    $year_level,
    $section_sched_code,
    $section_dept_code,
    $ay_code,
    $semester,
    $user_type,
    $response
);
checkCourseAvailability(
    $conn,
    $new_course_code,
    $program_code,
    $year_level,
    $section_sched_code,
    $section_dept_code,
    $ay_code,
    $semester,
    $user_type,
    $response
);






// Close the connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);


?>