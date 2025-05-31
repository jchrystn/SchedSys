<?php

session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";
    

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$program_code = $_GET['program_code'];
$curriculum = $_GET['curriculum'];
$year_level = $_GET['year_level'];
$ay_code = $_GET['ay_code'];
$semester = $_GET['semester'];

if ($program_code && $curriculum && $year_level && $ay_code && $semester) {
    $stmt = $conn->prepare("SELECT section_code FROM tbl_section WHERE program_code = ? AND curriculum = ? AND year_level = ? AND ay_code = ? AND semester = ?");
    $stmt->bind_param('ssiis', $program_code, $curriculum, $year_level,$ay_code,$semester);
    $stmt->execute();
    $result = $stmt->get_result();

    $sections = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
    }

    echo json_encode($sections);
}
?>
