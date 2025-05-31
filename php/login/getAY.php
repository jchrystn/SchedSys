<?php
include("../config.php");

if (isset($_POST['college_code'])) {
    $college_code = $_POST['college_code'];

    // Query to get `ay_code` and `semester` based on `college_code`
    $sql = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $college_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['ay_code' => $row['ay_code'], 'semester' => $row['semester']]);
    } else {
        echo json_encode(['error' => 'No AY and Semester found for the selected college code']);
    }
} else {
    echo json_encode(['error' => 'College code not provided']);
}
?>