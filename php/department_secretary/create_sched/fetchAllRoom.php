<?php 
session_start();

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head') && $_SESSION['user_type'] != 'Department Chairperson') {
    header("Location: ../login/login.php");
    exit();
}


// Get the current user's first name and department code from the session
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
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

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['fetch']) && $_GET['fetch'] == 'rooms') {
    $dept_code = $_GET['dept_code'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    $user_type = $_GET['user_type'];
    echo fetchAllRooms($conn, $dept_code, $semester, $ay_code,$user_type);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['fetch']) && $_GET['fetch'] == 'old_rooms') {
    $dept_code = $_GET['dept_code'];
    $semester = $_GET['semester'];
    $ay_code = $_GET['ay_code'];
    $user_type = $_GET['user_type'];
    echo fetchAllRooms($conn, $dept_code, $semester, $ay_code,$user_type);
    exit();
}


// Function to fetch all rooms
function fetchAllRooms($conn, $dept_code, $semester, $ay_code,$user_type) {
    $options = '';
    $room_type_condition = '';

    // Adjust the room type condition based on user type
    if ($user_type === 'Department Secretary') {
        $room_type_condition = "AND (room_type = 'Lecture' OR room_type = 'Laboratory')";
        $dept_code_condition = "AND dept_code = ?"; // Include dept_code condition
    } elseif ($user_type === 'CCL Head') {
        $room_type_condition = "AND room_type = 'Computer Laboratory'";
        $dept_code_condition = ""; // No dept_code condition
    }
    
    // Combine the conditions into the query
    $rooms_sql = "SELECT room_code, room_name FROM tbl_room WHERE status = 'Available' $room_type_condition $dept_code_condition";
    
    $stmt = $conn->prepare($rooms_sql);
    
    // Bind parameters conditionally based on user type
    if ($user_type === 'Department Secretary') {
        $stmt->bind_param("s", $dept_code);
    }
    
    $stmt->execute();
    $rooms_result = $stmt->get_result();

    if ($rooms_result->num_rows > 0) {
        while ($room_row = $rooms_result->fetch_assoc()) {
            $options .= '<option value="' . htmlspecialchars($room_row['room_code'], ENT_QUOTES, 'UTF-8') . '">'. htmlspecialchars($room_row['room_code'], ENT_QUOTES, 'UTF-8') .' - ' . htmlspecialchars($room_row['room_name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
    } else {
        $options = '<option value="">No rooms available</option>';
    }
    $stmt->close();
    return $options;
}

$options = fetchAllRooms($conn, $dept_code,$user_type);
header('Content-Type: text/html');
?>
