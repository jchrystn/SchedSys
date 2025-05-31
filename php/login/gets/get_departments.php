<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../../config.php");

if (isset($_POST['college_code'])) {
    $college_code = $_POST['college_code'];

    $sql = "SELECT dept_code, dept_name FROM tbl_department WHERE college_code = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $college_code);
        $stmt->execute();
        $result = $stmt->get_result();

        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }

        header('Content-Type: application/json');
        echo json_encode($departments);
    } else {
        echo json_encode(['error' => 'Database query failed.']);
    }
} else {
    echo json_encode(['error' => 'No college code provided.']);
}
?>
