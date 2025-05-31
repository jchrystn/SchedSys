<?php
// Include your database connection file
include("../config.php");

// Check if section_code is provided
if (isset($_POST['section_code'])) {
    $section_code = $_POST['section_code'];

    // Sanitize the table name
    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $_SESSION['dept_code'] . "_" . $ay_code);

    // Prepare the SQL query to fetch distinct course codes for the selected section code
    $sql_fetch_courses = "
        SELECT DISTINCT ps.course_code
        FROM $sanitized_psched_code AS ps
        INNER JOIN tbl_secschedlist AS se 
            ON ps.section_code = se.section_sched_code
        WHERE se.section_code = ?";

    $stmt = $conn->prepare($sql_fetch_courses);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind the section code parameter
    $stmt->bind_param("s", $section_code);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the courses and return as JSON
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    // Return the result as JSON
    echo json_encode($courses);
} else {
    // If no section_code is provided, return an empty array
    echo json_encode([]);
}
?>
