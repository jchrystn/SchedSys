<?php
include("../config.php");

if (isset($_POST['department_code']) && isset($_POST['ay_code'])) {
    $department_code = $_POST['department_code'];
    $ay_code = $_POST['ay_code'];

    // Fetch registration advisers based on department_code and ay_code
    $sql_advisers = "SELECT prof_code FROM tbl_prof_acc WHERE dept_code = ? AND user_type = 'Registration Adviser' AND ay_code = ?";
    $stmt_advisers = $conn->prepare($sql_advisers);
    $stmt_advisers->bind_param("si", $department_code, $ay_code);
    $stmt_advisers->execute();
    $result_advisers = $stmt_advisers->get_result();

    $options_advisers = '<option value="" disabled selected> -- Select Registration Adviser -- </option>';
    while ($row = $result_advisers->fetch_assoc()) {
        $options_advisers .= '<option value="' . htmlspecialchars($row['prof_code']) . '">' . htmlspecialchars($row['prof_code']) . '</option>';
    }

    echo json_encode(['advisers' => $options_advisers]);
} else {
    echo json_encode(['error' => 'Department code or ay_code not provided']);
}
?>
