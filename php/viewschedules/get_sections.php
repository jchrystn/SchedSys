<?php
include("../config.php");
session_start();

$program_code = $_GET['program_code'];
$curriculum = $_GET['curriculum'];
$year_level = $_GET['year_level'];
$ay_code = $_GET['ay_code'];
$semester = $_GET['semester'];
$prof_name = $_GET['prof_name'];

if ($program_code && $curriculum && $year_level && $ay_code && $semester && $prof_name) {
    $stmt = $conn->prepare("
        SELECT s.section_code 
        FROM tbl_section s
        INNER JOIN tbl_registration_adviser r ON s.section_code = r.section_code
        WHERE s.program_code = ? 
          AND s.curriculum = ? 
          AND s.year_level = ? 
          AND s.ay_code = ? 
          AND s.semester = ?
          AND r.reg_adviser = ?
    ");
    $stmt->bind_param('ssisss', $program_code, $curriculum, $year_level, $ay_code, $semester, $prof_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }

    echo json_encode($sections);
}
?>
