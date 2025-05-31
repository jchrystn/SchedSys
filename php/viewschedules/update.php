<?php 
include("../config.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['designation'], $_POST['cvsu_email'])) {
        $designation = $_POST['designation'];
        $cvsu_email = $_POST['cvsu_email'];

        // Sanitize inputs
        $designation = mysqli_real_escape_string($conn, $designation);
        $cvsu_email = mysqli_real_escape_string($conn, $cvsu_email);

        // Fetch stored semester and ay_code
        $semester = $_SESSION['semester'] ?? '';
        $ay_code = $_SESSION['ay_code'] ?? '';

        // Fetch the latest active academic year and semester if not set
        if (empty($ay_code) || empty($semester)) {
            $fetch_info = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = 'DSS' AND active = '1' LIMIT 1";
            $result_ay = $conn->query($fetch_info);

            if ($result_ay->num_rows > 0) {
                $row = $result_ay->fetch_assoc();
                $ay_code = $row['ay_code'];
                $semester = $row['semester'];

                $_SESSION['ay_code'] = $ay_code;
                $_SESSION['semester'] = $semester;
            }
        }

        // Ensure the professor exists for the given semester and academic year
        $sql_check = "SELECT * FROM tbl_prof_acc WHERE cvsu_email = ? AND semester = ? AND ay_code = ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("sss", $cvsu_email, $semester, $ay_code);
            $stmt_check->execute();
            $result = $stmt_check->get_result();

            if ($result->num_rows > 0) {
                // Update the designation if the professor exists in the given semester & ay_code
                $sql_update_designation = "UPDATE tbl_prof_acc SET designation = ? WHERE cvsu_email = ? AND semester = ? AND ay_code = ?";
                if ($stmt_update = $conn->prepare($sql_update_designation)) {
                    $stmt_update->bind_param("ssss", $designation, $cvsu_email, $semester, $ay_code);
                    if ($stmt_update->execute()) {
                        echo 'success';  // Return success response
                    } else {
                        echo 'Error executing query: ' . $stmt_update->error;  // Return error message
                    }
                    $stmt_update->close();
                }
            } else {
                echo 'Professor not found for the given semester and academic year.';
            }
            $stmt_check->close();
        } else {
            echo 'Error preparing query: ' . $conn->error;
        }
    } else {
        echo 'Missing required parameters.';
    }

    $conn->close();
} else {
    echo 'Invalid request method.';
}
