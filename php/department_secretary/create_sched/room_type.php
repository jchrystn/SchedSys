<?php 
session_start();

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head') && $_SESSION['user_type'] != 'Department Chairperson') {
    header("Location: ../login/login.php");
    exit();
}

// Get the current user's first name and department code from the session

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

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['course_code']) && isset($_GET['room_type']) && isset($_GET['user_dept_code']) && isset($_GET['new_course_code'])&& isset($_GET['user_type'])) {
    $course_code = $_GET['course_code'];
    $room_type = $_GET['room_type'];
    $user_dept_code = $_GET['user_dept_code'];
    $year_level = $_GET['year_level'];
    $program_code = $_GET['program_code'];
    $new_course_code = $_GET['new_course_code'];
    $user_type = $_GET['user_type'];
    $class_type = $_GET['class_type'];
    $rooms = fetchRoomsByCourseAndType($conn, $course_code, $program_code, $year_level, $user_dept_code, $new_course_code,$user_type,$class_type);
    echo json_encode($rooms); // Return rooms as JSON
}

function fetchRoomsByCourseAndType($conn, $course_code, $program_code, $year_level, $user_dept_code, $new_course_code, $user_type, $class_type) {
    // Step 1: Fetch course details
    $sql = "SELECT allowed_rooms, lec_hrs, lab_hrs, computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo "Error preparing statement for tbl_course: " . $conn->error;
        return [];
    }
    
    $stmt->bind_param("sss", $course_code, $program_code, $year_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Handle case where no rows are returned
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lec_hrs = $row['lec_hrs'] ?? 0;
        $lab_hrs = $row['lab_hrs'] ?? 0;
        $computer_room = $row['computer_room'] ?? null;
    } else {
        $stmt->close();
        return []; // No course details found, return empty array
    }
    
    $stmt->close();

    // Step 2: Build room query
    $query = "SELECT room_code, room_name FROM tbl_room WHERE status = 'Available' AND ";
    $params = []; // Array to hold parameter values for prepared statement

    if ($user_type === 'Department Secretary') {
        if ($lec_hrs == 0 && $lab_hrs == 0) {
            $query .= "room_type = 'Lecture' AND dept_code = ? ";
            $params[] = $user_dept_code;
        } else {
            $query .= "(room_type = 'Lecture' OR room_type = 'Laboratory') AND dept_code = ? ";
            $params[] = $user_dept_code;
        }
    } elseif ($user_type === 'CCL Head') {
        $query .= "room_type = 'Computer Laboratory'";
    } else {
        // Return empty if no matching condition
        return [];
    }

    // Step 3: Execute room query
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo "Error preparing statement for tbl_room: " . $conn->error;
        return [];
    }
    
    if (!empty($params)) {
        $stmt->bind_param("s", ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $rooms = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['new_course_code'] = $new_course_code; // Add new_course_code to each room result
        $rooms[] = $row;
    }

    $stmt->close();
    return $rooms;
}



// if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['course_code']) && isset($_GET['room_type'])&& isset($_GET['user_dept_code'])) {
//     $course_code = $_GET['course_code'];
//     $room_type = $_GET['room_type'];
//     $user_dept_code = $_GET['user_dept_code'];
//     $year_level = $_GET['year_level'];
//     $program_code = $_GET['program_code'];
//     // Your logic to fetch rooms based on room_type and course_code
//     $rooms = fetchRoomsByCourseAndType($conn, $course_code, $program_code, $year_level, $user_dept_code);
//     echo json_encode($rooms); // Return rooms as JSON
// }


// function fetchRoomsByCourseAndType($conn, $course_code, $program_code, $year_level, $user_dept_code) {
//     // Check course's lecture and laboratory availability
//     $sql = "SELECT lec_hrs, lab_hrs FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("sss", $course_code, $program_code, $year_level);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     // Default room types to display
//     $show_lecture_rooms = false;
//     $show_lab_rooms = false;

//     // Set room types to display based on course lecture and laboratory hours
//     if ($result->num_rows > 0) {
//         $row = $result->fetch_assoc();
//         $show_lecture_rooms = ($row['lec_hrs'] > 0);
//         $show_lab_rooms = ($row['lab_hrs'] > 0);
//     }
//     $stmt->close();

//     // Base query with dept_code as a parameter
//     $query = "SELECT room_code, room_name FROM tbl_room WHERE dept_code = ?";

//     // Add room type conditions based on lecture and laboratory availability
//     if ($show_lecture_rooms && !$show_lab_rooms) {
//         $query .= " AND room_type = 'Lecture'";
//     } elseif (!$show_lecture_rooms && $show_lab_rooms) {
//         $query .= " AND room_type = 'Laboratory'";
//     } elseif ($show_lecture_rooms && $show_lab_rooms) {
//         $query .= " AND (room_type = 'Lecture' OR room_type = 'Laboratory')";
//     } else {
//         // No rooms are available if there are no lecture or lab hours
//         return [];
//     }

//     // Prepare and execute the room fetching query
//     $stmt = $conn->prepare($query);
//     if ($stmt) {
//         $stmt->bind_param("s", $user_dept_code);
//         $stmt->execute();
//         $result = $stmt->get_result();
//         $rooms = [];

//         while ($row = $result->fetch_assoc()) {
//             $rooms[] = $row;
//         }

//         $stmt->close();
//     } else {
//         echo "Error in preparing statement: " . $conn->error;
//         $rooms = [];
//     }

//     return $rooms;
// }



//     // Base query with dept_code as a parameter
//     $query = "SELECT room_code, room_name FROM tbl_room WHERE dept_code = ?";

//     // Add condition for room type if specified
//     if ($room_type === 'Lecture') {
//         $query .= " AND room_type = 'Lecture'";
//     } elseif ($room_type === 'Laboratory') {
//         $query .= " AND room_type = 'Laboratory'";
//     }

//     // Prepare the statement
//     $stmt = $conn->prepare($query);
//     if ($stmt) {
//         // Bind the parameters (assuming dept_code is a string, adjust if necessary)
//         $stmt->bind_param("s", $user_dept_code);

//         // Execute and fetch results
//         $stmt->execute();
//         $result = $stmt->get_result();
//         $rooms = [];

//         while ($row = $result->fetch_assoc()) {
//             $rooms[] = $row;
//         }

//         // Close the statement
//         $stmt->close();
//     } else {
//         // Handle errors in preparing the statement
//         echo "Error in preparing statement: " . $conn->error;
//         $rooms = [];
//     }

//     return $rooms;
// }

?>