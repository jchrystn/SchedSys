<?php

if (isset($_POST['section_code'])) {
    $section_code = $_POST['section_code'];

    // Sanitize the table name to prevent SQL injection
    $sanitized_psched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $dept_code . "_" . $ay_code);

    // SQL to fetch course codes for the selected section
    $sql_fetch_courses = "
        SELECT DISTINCT ps.course_code
        FROM $sanitized_psched_code AS ps
        INNER JOIN tbl_secschedlist AS se 
            ON ps.section_code = se.section_sched_code
        WHERE se.section_code = ? AND prof_code = ?";

    $stmt = $conn->prepare($sql_fetch_courses);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind the section_code parameter
    $stmt->bind_param("ss", $section_code, $prof_code);
    $stmt->execute();
    $result = $stmt->get_result();

    // Fetch the courses and store them in an array
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    // Return the result as JSON
    echo json_encode($courses);
    exit;  // Stop further execution
} 

?>