<?php
include("../../php/config.php");

session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user'])) {
    
    header("Location: ../login/login.php"); 

    exit();
}

// Assuming the user's college_code is stored in a variable
$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

// fetching the academic year and semester
$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
} 

// counting for pending approvals
$sql = "SELECT COUNT(*) AS pending_count FROM tbl_prof_acc WHERE status = 'pending' AND college_code = ? AND semester = ? AND ay_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $college_code, $semester, $ay_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $pending_count = $row['pending_count'];
}

// Check if the modal should be shown
if (isset($_SESSION['show_academic_year_modal']) && $_SESSION['show_academic_year_modal'] === true) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = document.getElementById('yearModal');
                modal.style.display = 'flex'; // Show the modal centered
            });
          </script>";

    // Unset the flag so it doesn't show the modal again on refresh
    unset($_SESSION['show_academic_year_modal']);
}

$response = [
    'success' => false,
    'message' => ''
];


if (isset($_POST['addNewAY'])) {
    $new_ay_start_year = intval($_POST['new_ay_start']);
    $new_ay_end_year = intval($_POST['new_ay_end']);
    $college_code = $_SESSION['college_code'];
    $semester = '1st Semester';

    // Validate the academic year
    if ($new_ay_start_year == $new_ay_end_year) {
        $response['message'] = "The start and end years must not be the same.";
    } elseif ($new_ay_end_year < $new_ay_start_year) {
        $response['message'] = "The end year must be higher than the start year.";
    } elseif ($new_ay_end_year - $new_ay_start_year > 1) {
        $response['message'] = "The start and end years must not differ by more than 1 year.";
    } else {
        $ay_name = "$new_ay_start_year - $new_ay_end_year";
        $ay_code = substr($new_ay_start_year, 2) . substr($new_ay_end_year, 2);

        // Check if the academic year and semester already exist
        $check_sql = "SELECT ay_code FROM tbl_ay 
                      WHERE ay_code = '$ay_code' AND college_code = '$college_code' 
                      AND semester = '$semester'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $response['message'] = "The academic year with this semester already exists.";
        } else {
            // Deactivate all current academic years for this college
            $update_sql = "UPDATE tbl_ay SET active = 0 WHERE college_code = '$college_code'";
            if ($conn->query($update_sql) === TRUE) {
                // Insert the new academic year
                $sql = "INSERT INTO tbl_ay (college_code, ay_code, ay_name, semester, active) 
                        VALUES ('$college_code', '$ay_code', '$ay_name', '$semester', 1)";
                if ($conn->query($sql) === TRUE) {
                    // Step 1: Fetch the latest inactive ay_code
                    $prev_ay_query = "SELECT ay_code, semester 
                                        FROM tbl_ay 
                                        WHERE college_code = '$college_code' AND active = 0 
                                        ORDER BY ay_code DESC, semester DESC 
                                        LIMIT 1";
                    $prev_ay_result = $conn->query($prev_ay_query);

                    if ($prev_ay_result->num_rows > 0) {
                        $prev_ay_row = $prev_ay_result->fetch_assoc();
                        $prev_ay_code = $prev_ay_row['ay_code'];
                        $prev_semester_code = $prev_ay_row['semester'];
                    
                        // Step 2: Copy data from previous academic year to the new one
                        $sqlCopyProfAcc = "
                            INSERT INTO tbl_prof_acc (
                                college_code, dept_code, status_type, default_code, prof_code, last_name, first_name, 
                                middle_initial, suffix, cvsu_email, prof_type, academic_rank, prof_unit, 
                                user_type, reg_adviser, password, status, acc_status, semester, ay_code
                            )
                            SELECT 
                                college_code, dept_code, status_type, default_code, prof_code, last_name, first_name, 
                                middle_initial, suffix, cvsu_email, prof_type, academic_rank, prof_unit, 
                                user_type, reg_adviser, password, status, acc_status, '$semester', '$ay_code'
                            FROM tbl_prof_acc
                            WHERE semester = '$prev_semester_code' 
                              AND ay_code = '$prev_ay_code'
                              AND NOT EXISTS (
                                  SELECT 1 FROM tbl_prof_acc 
                                  WHERE college_code = '$college_code' 
                                    AND semester = '$semester' 
                                    AND ay_code = '$ay_code'
                                    AND prof_code = tbl_prof_acc.prof_code
                              )
                        ";

                        if ($conn->query($sqlCopyProfAcc) === TRUE) {
                            // Step 3: Update tbl_registration_adviser section codes for the new academic year
                            $sqlUpdateRegAdviser = "SELECT * FROM tbl_registration_adviser";
                            $resultRegAdviser = $conn->query($sqlUpdateRegAdviser);

                            if ($resultRegAdviser->num_rows > 0) {
                                while ($regRow = $resultRegAdviser->fetch_assoc()) {
                                    $section_code = $regRow['section_code'];
                                    $num_year = $regRow['num_year'];
                                    
                                    // Extract program, year, and section from format like "BSIT 1-1"
                                    // Split by space first to separate program from year-section
                                    $parts = explode(' ', $section_code);
                                    if (count($parts) >= 2) {
                                        $program = $parts[0]; // e.g., "BSIT"
                                        $year_section = $parts[1]; // e.g., "1-1"
                                        
                                        // Now split the year-section part by hyphen
                                        $year_section_parts = explode('-', $year_section);
                                        if (count($year_section_parts) >= 2) {
                                            $current_year = intval($year_section_parts[0]); // e.g., 1
                                            $section_number = $year_section_parts[1]; // e.g., "1"
                                            
                                            // Increment the year, but don't exceed num_year
                                            if ($current_year < $num_year) {
                                                $new_year = $current_year + 1;
                                                $new_section_code = $program . ' ' . $new_year . '-' . $section_number;
                                                
                                                // Update the record with the new section code and current_ay_code
                                                $updateRegAdviser = "
                                                    UPDATE tbl_registration_adviser 
                                                    SET section_code = ?, current_ay_code = ? 
                                                    WHERE dept_code = ? AND section_code = ? AND current_ay_code = ?
                                                ";
                                                $stmtUpdate = $conn->prepare($updateRegAdviser);
                                                $stmtUpdate->bind_param(
                                                    "sssss",
                                                    $new_section_code,
                                                    $ay_code,
                                                    $regRow['dept_code'],
                                                    $section_code,
                                                    $prev_ay_code
                                                );
                                                $stmtUpdate->execute();
                                                $stmtUpdate->close();
                                            } else {
                                                // If current_year >= num_year, delete the record (students have graduated)
                                                // $deleteRegAdviser = "
                                                //     DELETE FROM tbl_registration_adviser 
                                                //     WHERE dept_code = ? AND section_code = ?
                                                // ";
                                                // $stmtDelete = $conn->prepare($deleteRegAdviser);
                                                // $stmtDelete->bind_param(
                                                //     "ss",
                                                //     $regRow['dept_code'],
                                                //     $section_code
                                                // );
                                                // $stmtDelete->execute();
                                                // $stmtDelete->close();
                                            }
                                        }
                                    }
                                }
                            }

                            // Step 4: Update tbl_stud_acc section codes and remaining_years
                            $sqlUpdateStudAcc = "
                                SELECT * FROM tbl_registration_adviser 
                                WHERE current_ay_code = '$ay_code'
                            ";
                            $resultStudAcc = $conn->query($sqlUpdateStudAcc);

                            if ($resultStudAcc->num_rows > 0) {
                                while ($regAdviserRow = $resultStudAcc->fetch_assoc()) {
                                    $new_section_code = $regAdviserRow['section_code'];
                                    $reg_adviser = $regAdviserRow['reg_adviser'];
                                    
                                    // Find the original section code before the update
                                    // We need to reverse the increment to get the previous section code
                                    $parts = explode(' ', $new_section_code);
                                    if (count($parts) >= 2) {
                                        $program = $parts[0];
                                        $year_section = $parts[1];
                                        $year_section_parts = explode('-', $year_section);
                                        if (count($year_section_parts) >= 2) {
                                            $current_year = intval($year_section_parts[0]);
                                            $section_number = $year_section_parts[1];
                                            
                                            // Get the previous section code (before increment)
                                            $prev_year = $current_year - 1;
                                            $prev_section_code = $program . ' ' . $prev_year . '-' . $section_number;
                                            
                                            // Update students with matching reg_adviser and previous section code
                                            $updateStudents = "
                                                UPDATE tbl_stud_acc 
                                                SET section_code = ?, remaining_years = remaining_years - 1 
                                                WHERE reg_adviser = ? AND section_code = ?
                                            ";
                                            $stmtUpdateStudents = $conn->prepare($updateStudents);
                                            $stmtUpdateStudents->bind_param(
                                                "sss",
                                                $new_section_code,
                                                $reg_adviser,
                                                $prev_section_code
                                            );
                                            $stmtUpdateStudents->execute();
                                            $stmtUpdateStudents->close();
                                        }
                                    }
                                }
                            }

                            // Fetch all professors for the new semester and academic year
                            $fetchQuery = "
                                SELECT prof_unit, dept_code, acc_status 
                                FROM tbl_prof_acc 
                                WHERE college_code = '$college_code' AND semester = '$semester' AND ay_code = '$ay_code'
                            ";
                            $result = $conn->query($fetchQuery);

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $prof_unit = $row['prof_unit'];
                                    $dept_code = $row['dept_code'];
                                    $acc_status = $row['acc_status'];

                                    // Determine the current count for the prof_unit in tbl_prof
                                    $count_query = "
                                        SELECT COUNT(*) AS count 
                                        FROM tbl_prof 
                                        WHERE prof_unit = ? AND dept_code = ? AND semester = ? AND ay_code = ?
                                    ";
                                    $countStmt = $conn->prepare($count_query);
                                    $countStmt->bind_param("ssss", $prof_unit, $dept_code, $semester, $ay_code);
                                    $countStmt->execute();
                                    $countResult = $countStmt->get_result();
                                    $current_count = $countResult->fetch_assoc()['count'] ?? 0;
                                    $countStmt->close();

                                    // Increment the count for the new prof_code
                                    $current_count++;
                                    $prof_code = strtoupper($prof_unit) . " " . $current_count;

                                    // Insert the new professor record into tbl_prof
                                    $insertQuery = "
                                        INSERT INTO tbl_prof (
                                            prof_code, dept_code, prof_unit, acc_status, semester, ay_code
                                        ) VALUES (?, ?, ?, ?, ?, ?)
                                    ";
                                    $insertStmt = $conn->prepare($insertQuery);
                                    $insertStmt->bind_param("ssssss", $prof_code, $dept_code, $prof_unit, $acc_status, $semester, $ay_code);
                                    $insertStmt->execute();
                                    $insertStmt->close();
                                }
                            }
                        } else {
                            $response['message'] = "Error copying Instructor data to tbl_prof_acc: " . $conn->error;
                            error_log("Error copying Instructor data to tbl_prof_acc: " . $conn->error);
                        }
                    } else {
                        $response['message'] = "No previous academic year found for copying data.";
                    }                    
                } else {
                    $response['message'] = "Error adding new academic year: " . $conn->error;
                    error_log("Error adding new academic year: " . $conn->error);
                }

                // Fetch all department codes for the given college code
                $fetch_dept_query = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
                $dept_result = $conn->query($fetch_dept_query);

                if ($dept_result->num_rows > 0) {
                    while ($dept_row = $dept_result->fetch_assoc()) {
                        $dept_code = $dept_row['dept_code'];

                        // Fetch active academic year code
                        $fetch_info_query = "SELECT ay_code FROM tbl_ay WHERE college_code = '$college_code' AND active = '1'";
                        $result = $conn->query($fetch_info_query);

                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $ay_code = $row['ay_code'];

                            // Create tables dynamically for each department
                            $table_name_sched = "tbl_secsched_" . $dept_code . "_" . $ay_code;
                            $columns_sql_sched = "CREATE TABLE IF NOT EXISTS $table_name_sched (
                                sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                section_sched_code VARCHAR(200) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                day VARCHAR(50) NOT NULL,
                                curriculum VARCHAR(100) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                course_code VARCHAR(100) NOT NULL,
                                room_code VARCHAR(100) NOT NULL,
                                prof_code VARCHAR(100) NOT NULL,
                                prof_name VARCHAR(100) NOT NULL,
                                dept_code VARCHAR(100) NOT NULL,
                                ay_code VARCHAR(100) NOT NULL,
                                cell_color VARCHAR(100) NOT NULL,
                                shared_sched VARCHAR(100) NOT NULL,
                                shared_to VARCHAR(100) NOT NULL,
                                class_type VARCHAR(100) NOT NULL
                            )";
                            $conn->query($columns_sql_sched);

                            $table_name_contact = "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code;
                            $columns_sql_contact = "CREATE TABLE IF NOT EXISTS $table_name_contact (
                                sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                                prof_sched_code VARCHAR(200) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                ay_code VARCHAR(255) NOT NULL,
                                prof_code VARCHAR(255) NOT NULL,
                                dept_code VARCHAR(255) NOT NULL,
                                day VARCHAR(50) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                consultation_hrs_type VARCHAR(100) NOT NULL
                            )";
                            $conn->query($columns_sql_contact);

                            $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                            $create_room_table_sql = "CREATE TABLE IF NOT EXISTS $sanitized_room_dept_code (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                sec_sched_id INT(100),
                                room_sched_code VARCHAR(255) NOT NULL,
                                room_code VARCHAR(255) NOT NULL,
                                room_in_charge VARCHAR(255) NOT NULL,
                                day VARCHAR(255) NOT NULL,
                                curriculum VARCHAR(100) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                course_code VARCHAR(255) NOT NULL,
                                section_code VARCHAR(255) NOT NULL,
                                prof_code VARCHAR(255) NOT NULL,
                                prof_name VARCHAR(255) NOT NULL,
                                dept_code VARCHAR(255) NOT NULL,
                                room_type VARCHAR(255) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                ay_code VARCHAR(255) NOT NULL,
                                class_type VARCHAR(255) NOT NULL,
                                cell_color VARCHAR(100) NOT NULL
                            )";
                            $conn->query($create_room_table_sql);

                            $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $dept_code . "_" . $ay_code);
                            $create_prof_table_sql = "CREATE TABLE IF NOT EXISTS $sanitized_prof_dept_code (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                sec_sched_id INT NOT NULL,
                                prof_sched_code VARCHAR(255) NOT NULL,
                                prof_code VARCHAR(255) NOT NULL,
                                time_start TIME NOT NULL,
                                time_end TIME NOT NULL,
                                day VARCHAR(255) NOT NULL,
                                curriculum VARCHAR(100) NOT NULL,
                                course_code VARCHAR(255) NOT NULL,
                                section_code VARCHAR(255) NOT NULL,
                                room_code VARCHAR(255) NOT NULL,
                                semester VARCHAR(255) NOT NULL,
                                dept_code VARCHAR(255) NOT NULL,
                                ay_code VARCHAR(255) NOT NULL,
                                class_type VARCHAR(255) NOT NULL,
                                cell_color VARCHAR(100) NOT NULL
                            )";
                            $conn->query($create_prof_table_sql);

                            $sanitized_room_ceit_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
                                $create_room_ceit_table_sql = "CREATE TABLE IF NOT EXISTS $sanitized_room_ceit_code (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    sec_sched_id INT(100),
                                    room_sched_code VARCHAR(255) NOT NULL,
                                    room_code VARCHAR(255) NOT NULL,
                                    room_in_charge VARCHAR(255) NOT NULL,
                                    day VARCHAR(255) NOT NULL,
                                    curriculum VARCHAR(100) NOT NULL,
                                    time_start TIME NOT NULL,
                                    time_end TIME NOT NULL,
                                    course_code VARCHAR(255) NOT NULL,
                                    section_code VARCHAR(255) NOT NULL,
                                    prof_code VARCHAR(255) NOT NULL,
                                    prof_name VARCHAR(255) NOT NULL,
                                    dept_code VARCHAR(255) NOT NULL,
                                    room_type VARCHAR(255) NOT NULL,
                                    semester VARCHAR(255) NOT NULL,
                                    ay_code VARCHAR(255) NOT NULL,
                                    class_type VARCHAR(255) NOT NULL,
                                    cell_color VARCHAR(100) NOT NULL
                                )";
                                $conn->query($create_room_ceit_table_sql);
                            
                        }
                    }
                }

            } else {
                $response['message'] = "Error updating previous academic years: " . $conn->error;
                error_log("Error updating previous academic years: " . $conn->error);
            }
        }

    }

    // Redirect to a confirmation page or reload the page
    header("Location: index.php");
    exit();

}

if (isset($_POST['selectAY'])) {
    // Get selected academic year and semester from the form
    $ay_code = $conn->real_escape_string($_POST['ay_code']); // Sanitize input
    $semester = $conn->real_escape_string($_POST['semester']);
    $college_code = $conn->real_escape_string($college_code); // Assume $college_code is defined or sanitized

    // Set all current active rows in the selected college to 0
    $sqlDeactivate = "UPDATE tbl_ay SET active = 0 WHERE college_code = '$college_code'";
    $conn->query($sqlDeactivate);

    // Check if the selected academic year already has the selected semester
    $sqlCheck = "SELECT * FROM tbl_ay WHERE ay_code = '$ay_code' AND semester = '$semester' AND college_code = '$college_code'";
    $resultCheck = $conn->query($sqlCheck);

    if ($resultCheck->num_rows > 0) {
        // If the row exists, update it to set active = 1
        $sqlUpdate = "UPDATE tbl_ay SET active = 1 WHERE ay_code = '$ay_code' AND semester = '$semester' AND college_code = '$college_code'";
        $conn->query($sqlUpdate);
    } else {
        // Fetch ay_name before the INSERT operation
        $sqlGetAyName = "SELECT ay_name FROM tbl_ay WHERE ay_code = '$ay_code' LIMIT 1";
        $resultGetAyName = $conn->query($sqlGetAyName);

        if ($resultGetAyName->num_rows > 0) {
            $row = $resultGetAyName->fetch_assoc();
            $ay_name = $conn->real_escape_string($row['ay_name']);

            // Perform the INSERT with the retrieved ay_name
            $sqlInsert = "INSERT INTO tbl_ay (ay_code, ay_name, semester, college_code, active) 
                          VALUES ('$ay_code', '$ay_name', '$semester', '$college_code', 1)";
            $conn->query($sqlInsert);
        }
    }

    // Check if there are records for the current semester and academic year in tbl_prof_acc
    $sqlCheckProfAcc = "
                        SELECT 1 
                        FROM tbl_prof_acc 
                        WHERE ay_code = '$ay_code' AND semester = '$semester'
                        LIMIT 1
                        ";
    $resultCheckProfAcc = $conn->query($sqlCheckProfAcc);

    if ($resultCheckProfAcc->num_rows == 0) {
        // Step 1: Fetch the latest inactive ay_code
        $prev_ay_query = "SELECT ay_code, semester 
                            FROM tbl_ay 
                            WHERE college_code = '$college_code' AND active = 0 
                            ORDER BY ay_code DESC, semester DESC 
                            LIMIT 1";
        $prev_ay_result = $conn->query($prev_ay_query);

        if ($prev_ay_result->num_rows > 0) {
            $prev_ay_row = $prev_ay_result->fetch_assoc();
            $prev_ay_code = $prev_ay_row['ay_code'];
            $prev_semester_code = $prev_ay_row['semester'];
        
            // Step 2: Copy data from previous academic year to the new one
            $sqlCopyProfAcc = "
                INSERT INTO tbl_prof_acc (
                    college_code, dept_code, status_type, default_code, last_name, first_name, 
                    middle_initial, suffix, cvsu_email, prof_type, academic_rank, prof_unit, 
                    user_type, reg_adviser, password, status, acc_status, semester, ay_code
                )
                SELECT 
                    college_code, dept_code, status_type, default_code, last_name, first_name, 
                    middle_initial, suffix, cvsu_email, prof_type, academic_rank, prof_unit, 
                    user_type, reg_adviser, password, status, acc_status, '$semester', '$ay_code'
                FROM tbl_prof_acc
                WHERE semester = '$prev_semester_code' 
                  AND ay_code = '$prev_ay_code'
                  AND NOT EXISTS (
                      SELECT 1 FROM tbl_prof_acc 
                      WHERE college_code = '$college_code' 
                        AND semester = '$semester' 
                        AND ay_code = '$ay_code'
                  )
            ";

            if ($conn->query($sqlCopyProfAcc) === TRUE) {
                // Fetch all professors for the new semester and academic year
                $fetchQuery = "
                    SELECT prof_unit, dept_code, acc_status 
                    FROM tbl_prof_acc 
                    WHERE college_code = '$college_code' AND semester = '$semester' AND ay_code = '$ay_code'
                ";
                $result = $conn->query($fetchQuery);

                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $prof_unit = $row['prof_unit'];
                        $dept_code = $row['dept_code'];
                        $acc_status = $row['acc_status'];

                        // Determine the current count for the prof_unit in tbl_prof
                        $count_query = "
                            SELECT COUNT(*) AS count 
                            FROM tbl_prof 
                            WHERE prof_unit = ? AND dept_code = ? AND semester = ? AND ay_code = ?
                        ";
                        $countStmt = $conn->prepare($count_query);
                        $countStmt->bind_param("ssss", $prof_unit, $dept_code, $semester, $ay_code);
                        $countStmt->execute();
                        $countResult = $countStmt->get_result();
                        $current_count = $countResult->fetch_assoc()['count'] ?? 0;
                        $countStmt->close();

                        // Increment the count for the new prof_code
                        $current_count++;
                        $prof_code = strtoupper($prof_unit) . " " . $current_count;

                        // Insert the new professor record into tbl_prof
                        $insertQuery = "
                            INSERT INTO tbl_prof (
                                prof_code, dept_code, prof_unit, acc_status, semester, ay_code
                            ) VALUES (?, ?, ?, ?, ?, ?)
                        ";
                        $insertStmt = $conn->prepare($insertQuery);
                        $insertStmt->bind_param("ssssss", $prof_code, $dept_code, $prof_unit, $acc_status, $semester, $ay_code);
                        $insertStmt->execute();
                        $insertStmt->close();
                    }
                }
            } else {
                $response['message'] = "Error copying Instructor data to tbl_prof_acc: " . $conn->error;
                error_log("Error copying Instructor data to tbl_prof_acc: " . $conn->error);
            }
        } else {
            $response['message'] = "No previous academic year found for copying data.";
        }                
    } 

    // Redirect to a confirmation page or reload the page
    header("Location: index.php");
    exit();
}

// Query to fetch students, filtering by college_code
$studentQuery = "
    SELECT 'Student' AS user_type, dept_code, cvsu_email, last_name, first_name, middle_initial, student_no
    FROM tbl_stud_acc 
    WHERE college_code = ? AND status = 'approve'";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$studentResult = $stmt->get_result();

// Query to fetch professors (assuming they have user_type in their table)
$professorQuery = "
    SELECT user_type, dept_code, cvsu_email, first_name, last_name, middle_initial
    FROM tbl_prof_acc 
    WHERE college_code = ? AND status = 'approve' AND user_type != 'admin' AND acc_status = 1 AND ay_code = ? AND semester = ?";
$stmt = $conn->prepare($professorQuery);
$stmt->bind_param("sss", $college_code, $ay_code, $semester);
$stmt->execute();
$professorResult = $stmt->get_result();

// Query to count the number of students in the same college
$studentCountQuery = "
    SELECT COUNT(*) AS student_count 
    FROM tbl_stud_acc 
    WHERE status = 'approve' AND college_code = ?";
$stmt = $conn->prepare($studentCountQuery);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$studentCountResult = $stmt->get_result();
$studentCount = $studentCountResult->fetch_assoc()['student_count'];

// Query to count the number of professors in the same college
$professorCountQuery = "
    SELECT COUNT(*) AS professor_count 
    FROM tbl_prof_acc 
    WHERE status = 'approve' AND acc_status = 1 AND ay_code = ? AND semester = ?";
$stmt = $conn->prepare($professorCountQuery);
$stmt->bind_param("ss", $ay_code, $semester);
$stmt->execute();
$professorCountResult = $stmt->get_result();
$professorCount = $professorCountResult->fetch_assoc()['professor_count'];

// Query to count the number of departments in the same college
$departmentCountQuery = "
    SELECT COUNT(*) AS department_count 
    FROM tbl_department 
    WHERE college_code = ?";
$stmt = $conn->prepare($departmentCountQuery);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$departmentResult = $stmt->get_result();
$departmentCount = $departmentResult->fetch_assoc()['department_count'];

$stmt->close();

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset(); 
    session_destroy(); 
    echo '<script>window.location.href="../login/login.php";</script>'; 
    exit(); 
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="admin_style.css">
    <title>SchedSys</title>
</head>

<body>
    <div class="container">
        <!-- Sidebar Section -->
        <aside>
            <div class="toggle">
                <div class="logo">
                    <h2 class="logo-name">SchedSys</span></h2>
                </div>
            </div>
            <div class="sidebar">
                <a href="#" class="active">
                    <i class="fa-solid fa-house"></i>
                    <h3>Home</h3>
                </a>
                <a href="/SchedSys3/php/viewschedules/dashboard.php">
                    <i class="fa-regular fa-calendar-days"></i>
                    <h3>Schedule</h3>
                </a>
                <a href="user_list.php">
                    <i class="fa-solid fa-user"></i>
                    <h3>Users</h3>
                </a>
                <a href="/SchedSys3/php/messages/users.php">
                    <i class="fa-solid fa-message"></i>
                    <h3>Message</h3>
                </a>
                <a href="approve.php">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Approval</h3>
                    <span class="message-count"><?php echo $pending_count; ?></span>
                </a>
                <a href="settings.php">
                    <i class="fa-solid fa-gear"></i>
                    <h3>Settings</h3>
                </a>
                <a onclick="openModal(event)">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p><br>
                <div class="modal-buttons">
                <button class="modal-btn logout-btn" onclick="confirmLogout()">Logout</button>
                <button class="modal-btn cancel-btn" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>

        <div class="main">
            <!-- Total Numbers -->
            <div class="analyse">
                <ul class="box-info">
                    <li>
                        <i class="fa-solid fa-user-graduate" style="color: #3C91E6;"></i>
                        <span class="text">
                            <h3><?php echo $studentCount; ?></h3>
                            <p>Students</p>
                        </span>
                    </li>
                    <li>
                        <i class="fa-solid fa-chalkboard-user" style="color: #FF0060;"></i>
                        <span class="text">
                            <h3><?php echo $professorCount; ?></h3>
                            <p>Instructors</p>
                        </span>
                    </li>
                    <li>
                        <i class="fa-solid fa-building" style="color: #1B9C85;"></i>
                        <span class="text">
                            <h3><?php echo $departmentCount; ?></h3>
                            <p>Departments</p>
                        </span>
                    </li>
                </ul>
            </div>
            
            <!-- Table for User Account -->
            <div class="user-accounts">
                <?php if ($studentResult->num_rows == 0 && $professorResult->num_rows == 0): ?>
                    <div class="no-data">
                        <i class="fa-solid fa-users-slash"></i>
                        <p>You don't have any user records</p>
                    </div>
                <?php else: ?>
                <h2>Recently Added</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>User Type</th>
                                <th>Cvsu Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $count = 0;

                                // Prepare and execute the student query
                                $studentQuery = "SELECT id, dept_code, cvsu_email FROM tbl_stud_acc WHERE college_code = ? AND status = 'approve' ORDER BY id DESC";
                                $studentStmt = $conn->prepare($studentQuery);
                                $studentStmt->bind_param("s", $college_code);
                                $studentStmt->execute();
                                $studentResult = $studentStmt->get_result();

                                // Prepare and execute the professor query
                                $professorQuery = "SELECT id, dept_code, user_type, cvsu_email FROM tbl_prof_acc WHERE status = 'approve' AND semester = ? AND ay_code = ? ORDER BY id DESC";
                                $professorStmt = $conn->prepare($professorQuery);
                                $professorStmt->bind_param("ss", $semester, $ay_code);
                                $professorStmt->execute();
                                $professorResult = $professorStmt->get_result();

                                $allResults = [];
                                
                                if ($professorResult->num_rows > 0) {
                                    while ($row = $professorResult->fetch_assoc()) {
                                        $allResults[] = $row;
                                    }
                                }
                                
                                if ($studentResult->num_rows > 0) {
                                    while ($row = $studentResult->fetch_assoc()) {
                                        $row['user_type'] = 'Student';
                                        $allResults[] = $row;
                                    }
                                }

                                usort($allResults, function($a, $b) {
                                    return $b['id'] - $a['id'];
                                });

                                foreach (array_slice($allResults, 0, 10) as $row) {
                                    echo "<tr>";
                                    echo "<td><p>" . htmlspecialchars($row['dept_code']) . "</p></td>";
                                    echo "<td><p>" . htmlspecialchars($row["user_type"] === "Professor" ? "Instructor" : $row["user_type"]) . "</p></td>";
                                    echo "<td><p>" . htmlspecialchars($row['cvsu_email']) . "</p></td>";
                                    echo "</tr>";
                                }
                            ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-section">
            <!-- Profile -->
            <div class="nav">
                <div class="profile">
                    <div class="info">
                        <p><b><?php echo htmlspecialchars($user_type); ?></b></p>
                        <small class="text-muted"><?php echo htmlspecialchars($college_code); ?></small>
                    </div>
                    <div class="profile-photo">
                        <img src="../../images/user_profile.png" alt="">
                    </div>
                </div>
            </div>
            
            <!-- Academic Year Selection -->
            <ul class="calendar">
                <li>
                    <i class="fa-solid fa-calendar" style="color: #FD7238;"></i>
                    <span class="text">
                        <?php
                        // Assume $college_code is already defined in your context
                        $query = "SELECT * FROM tbl_ay WHERE college_code = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("s", $college_code);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        // Check if any data was returned for the given college_code
                        if ($result->num_rows > 0):
                            $row = $result->fetch_assoc();
                        ?>
                            <label for="academicYearAdd" style="font-size: 13px">Academic Year:</label>
                            <p id="academicYearDisplay"><?php echo $ay_name; ?></p><br>
                            <label for="semester" style="font-size: 13px">Semester:</label>
                            <p><?php echo $semester; ?></p>
                            <!-- Button to open modal -->
                            <button type="button" name="change" id="openModalBtn" style="display: <?php echo $isLoggedIn ? 'inline-block' : 'none'; ?>;">Change</button>
                        <?php else: ?>
                            <!-- Display Add button if no records found -->
                            <button type="button" name="add" id="openAddYearModal">Add Academic Year</button>
                        <?php endif; ?>
                    </span>
                </li>
            </ul>

            <!-- Select Academic Year Modal -->
            <div id="yearModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Select Academic Year</h2>
                    <form id="yearSelectForm" method="post" action="">
                        <select id="academicYearModal" name="ay_code" required>
                            <?php
                                // SQL query to fetch distinct ay_code and ay_name for the given college_code
                                $sql = "SELECT DISTINCT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code'";
                                $result = $conn->query($sql);

                                // To track displayed academic year names
                                $displayedNames = [];

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        // Check if the ay_name is already displayed
                                        if (!in_array($row['ay_name'], $displayedNames)) {
                                            $selected = (isset($_SESSION['ay_code']) && $row['ay_code'] == $_SESSION['ay_code']) ? 'selected' : '';
                                            echo "<option value='" . htmlspecialchars($row['ay_code']) . "' $selected>" . htmlspecialchars($row['ay_name']) . "</option>";
                                            // Add the name to the displayed list
                                            $displayedNames[] = $row['ay_name'];
                                        }
                                    }
                                }
                            ?>
                        </select>

                        <select id="semester" name="semester" required>
                            <option value="" disabled <?= $semester == '' ? 'selected' : '' ?>>Select Semester</option>
                            <option value="1st Semester" <?= $semester == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd Semester" <?= $semester == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="Mid Year" <?= $semester == 'Mid Year' ? 'selected' : '' ?>>Mid Year</option>
                        </select><br>

                        <div class="btn-container">
                            <button type="submit" class="btn-select-year" name="selectAY">Select</button>
                            <button type="button" id="openAddYearModal" class="btn-add-year">Add New</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Academic Year Modal -->
            <div id="addYearModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <!-- <span class="close" onclick="closeModal()">&times;</span> -->
                    <h2>Add New Academic Year</h2>
                    <div class="modal-body">
                        <div id="responseMessage" class="response-message">
                            <?php echo $response['message'] ?? ''; ?>
                        </div><br>
                        <form id="addNewAYForm" method="post" action="">
                            <div class="form-row">
                                <input class="ay_year" type="number" id="new_ay_start" name="new_ay_start" required min="" max="2100" placeholder="Start Year" oninput="setMinEndYear()">
                                <span class="dash">-</span>
                                <input class="ay_year" type="number" id="new_ay_end" name="new_ay_end" required min="" max="2100" placeholder="End Year">
                            </div><br>
                            <button type="submit" class="btn" name="addNewAY">Add</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Create Button -->
            <div class="btn-create">
                <div class="header">
                    <h2>Create New</h2>
                </div>

                <!-- Department -->
                <a href="department_input.php">
                    <div class="department">
                        <div class="icon">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Department</h3>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Appointment Record -->
                <a href="create_appointment.php">
                    <div class="student">
                        <div class="icon">
                            <i class="fa-solid fa-ranking-star"></i>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Academic Rank</h3>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Professor Account -->
                <a href="create_prof.php">
                    <div class="prof">
                        <div class="icon">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Instructor Account</h3>
                            </div>
                        </div>
                    </div>
                </a>
                
                <!-- Student Account -->
                <!-- <a href="create_stud.php">
                    <div class="student">
                        <div class="icon">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Student Account</h3>
                            </div>
                        </div>
                    </div>
                </a> -->
                
                <!-- Other Colleges Account -->
                <a href="create_other_college.php">
                    <div class="student">
                        <div class="icon">
                            <i class="fa-solid fa-school"></i>
                        </div>
                        <div class="content">
                            <div class="info">
                                <h3>Other College Account</h3>
                            </div>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>

    <script> 

        // Open the modal
        function openModal(event) {
            event.preventDefault(); // Prevent default link action
            document.getElementById("logoutModal").style.display = "flex";
        }

        // Close the modal
        function closeModal() {
            console.log("Closing modal"); // Check if this message appears in the console
            const modal = document.getElementById("logoutModal");
            if (modal) {
                modal.style.display = "none";
                console.log("Modal display set to none");
            } else {
                console.log("Modal not found");
            }
        }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "index.php?logout=1";
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById("logoutModal");   
            if (event.target === modal) {
                closeModal();
            }
        };

        document.addEventListener("DOMContentLoaded", function () {
            // Elements
            const yearModal = document.getElementById("yearModal");
            const addYearModal = document.getElementById("addYearModal");
            const openModalBtn = document.getElementById("openModalBtn");
            const openAddYearModalBtn = document.getElementById("openAddYearModal");
            const academicYearDisplay = document.getElementById("academicYearDisplay");
            const responseMessage = "<?php echo $response['message'] ?? ''; ?>";

            // Display response message if exists
            if (responseMessage) {
                document.getElementById('responseMessage').innerText = responseMessage;
                document.getElementById("addYearModal").style.display = "flex";
            }

            // Event listeners
            openModalBtn?.addEventListener("click", () => yearModal.style.display = "flex");
            openAddYearModalBtn?.addEventListener("click", () => {
                addYearModal.style.display = "flex";
                yearModal.style.display = "none";
            });

            document.querySelectorAll(".close").forEach(btn => {
                btn.onclick = function () { btn.closest(".modal").style.display = "none"; };
            });

            window.onclick = function (event) {
                if (event.target === yearModal) yearModal.style.display = "none";
                if (event.target === addYearModal) addYearModal.style.display = "none";
            };

            // Dynamic end date adjustment
            function setMinEndDate() {
                const startDate = document.getElementById("new_ay_start").value;
                const endDateInput = document.getElementById("new_ay_end");
                if (startDate) {
                    const nextYear = new Date(startDate);
                    nextYear.setFullYear(nextYear.getFullYear() + 1);
                    endDateInput.min = nextYear.toISOString().split("T")[0];
                }
            }
        });

        // Function to set the minimum year for both inputs
        function setMinYears() {
            const currentYear = new Date().getFullYear(); // Get the current year
            const startYearInput = document.getElementById('new_ay_start');
            const endYearInput = document.getElementById('new_ay_end');

            startYearInput.min = currentYear; // Set min for start year to current year
            endYearInput.min = currentYear + 1; // Set min for end year to current year + 1
        }

        // Function to update the minimum end year based on start year
        function setMinEndYear() {
            const startYear = document.getElementById('new_ay_start').value;
            const endYearInput = document.getElementById('new_ay_end');
            
            if (startYear) {
                endYearInput.min = parseInt(startYear) + 1; // Set minimum end year to start year + 1
            } else {
                endYearInput.min = currentYear + 1; // Reset to current year + 1 if no start year
            }
        }

        // Call setMinYears on page load
        document.addEventListener("DOMContentLoaded", setMinYears);

    </script>

</body>

</html>