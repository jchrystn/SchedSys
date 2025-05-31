<?php 
session_start();

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head') && $_SESSION['user_type'] != 'Department Chairperson') {
    header("Location: ../login/login.php");
    exit();
}

// Get the current user's first name and department code from the session
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '2425';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '1st Semester';
$section_sched_code = isset($_SESSION['section_sched_code']) ? $_SESSION['section_sched_code'] : '';



if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
  // Replace with your actual success page URL
$error_redirect_url = 'plotSchedule.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['fetch']) && $_GET['fetch'] == 'professors') {
    $dept_code = $_GET['dept_code'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    echo fetchAllProfessors($conn, $dept_code, $semester, $ay_code);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['fetch']) && $_GET['fetch'] == 'old_professors') {
    $dept_code = $_GET['dept_code'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    echo fetchAllProfessors($conn, $dept_code, $semester, $ay_code);
    exit();
}


function fetchAllProfessors($conn, $dept_code, $semester, $ay_code) {
    $options = '';

    $sanitized_dept_code = $conn->real_escape_string($dept_code);
    $sanitized_semester = $conn->real_escape_string($semester);
    $sanitized_ay_code = $conn->real_escape_string($ay_code);

    $professors_sql = "SELECT p.prof_code, p.prof_name, 
                        COALESCE(c.teaching_hrs, 0) AS teaching_hrs
                    FROM tbl_prof p
                    LEFT JOIN tbl_psched ps ON p.prof_code = ps.prof_code
                    LEFT JOIN tbl_psched_counter c ON ps.prof_sched_code = c.prof_sched_code
                        AND c.semester = '$sanitized_semester' 
                        AND ps.ay_code = '$sanitized_ay_code'
                    WHERE p.dept_code = '$sanitized_dept_code' AND  p.acc_status = '1' AND p.ay_code = '$sanitized_ay_code' AND p.semester = '$sanitized_semester'
                    GROUP BY p.prof_code, p.prof_name
                    ORDER BY p.prof_code";

    $professors_result = $conn->query($professors_sql);

    if ($professors_result->num_rows > 0) {
        while ($prof_row = $professors_result->fetch_assoc()) {
            $prof_code = htmlspecialchars($prof_row['prof_code'], ENT_QUOTES, 'UTF-8');
            $prof_name = htmlspecialchars($prof_row['prof_name'], ENT_QUOTES, 'UTF-8');
            $current_teaching_hrs = htmlspecialchars($prof_row['teaching_hrs'], ENT_QUOTES, 'UTF-8');
            $options .= '<option value="' . $prof_code . '">' . $prof_code . ' - ' . ' (' . $current_teaching_hrs . ' hrs)</option>';
        }
    } else {
        $options = '<option value="">No professors available</option>';
    }
    return $options;
}

?>

