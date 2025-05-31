<?php 

error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../../config.php");

if (isset($_POST['dept_code'])) {
    $dept_code = $_POST['dept_code'];

    $sql = "SELECT dept_code, program_name, years FROM tbl_program WHERE dept_code = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("s", $dept_code);
        $stmt->execute();
        $result = $stmt->get_result();

        $programs = [];
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }

        echo json_encode($programs);
    } else {
        echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
    }
} else {
    echo json_encode(['error' => 'No department code provided.']);
}

?>