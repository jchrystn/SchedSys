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


$course_code = isset($_GET['course_code']) ? $_GET['course_code'] : null;
$new_course_code = isset($_GET['new_course_code']) ? $_GET['new_course_code'] : null;
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : null;
$program_code = isset($_GET['program_code']) ? $_GET['program_code'] : null;
// $section_sched_code = isset($_GET['section_sched_code']) ? $_GET['section_sched_code'] : null;
// $user_dept_code = isset($_GET['user_dept_code']) ? $_GET['user_dept_code'] : null;
// $sched_dept_code = isset($_GET['sched_dept_code']) ? $_GET['sched_dept_code'] : null;
// $section_dept_code = isset($_GET['section_dept_code']) ? $_GET['section_dept_code'] : null;
// $ay_code = isset($_GET['ay_code']) ? $_GET['ay_code'] : null;
// $semester = isset($_GET['semester']) ? $_GET['semester'] : null;


$response = [
    'lec_available' => false,
    'lab_available' => false
];

// Function to check course availability
function checkCourseAvailability($conn, $course_code, $program_code, $year_level,&$response) {

    if ($course_code) {
        $sql = "SELECT lec_hrs, lab_hrs FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $course_code,$program_code,$year_level);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response['lec_available'] = ($row['lec_hrs'] > 0);
            $response['lab_available'] = ($row['lab_hrs'] > 0);
        }
        $stmt->close();
    }
}

checkCourseAvailability($conn, $new_course_code,$program_code,$year_level, $response);


// Close the connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);


?>
