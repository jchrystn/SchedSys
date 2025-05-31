<?php
session_start();
include("../../config.php");


// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../../login/login.php");
    exit();
}
$dept_code = $_SESSION['dept_code'];
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : 'Unknown';
$program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
$curriculum = isset($_GET['curriculum']) ? $_GET['curriculum'] : '';
$num_year = isset($_GET['num_year']) ? $_GET['num_year'] : '';
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}



$search_semester = '';
$search_year = '';
$search_course_code_name = '';
$search_program = '';

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' AND active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];

    // Store ay_code and semester in session
    $_SESSION['ay_code'] = $ay_code;
    $_SESSION['semester'] = $semester;
}

// Step 2: Prepare the SQL statement to retrieve the last inserted ID from tbl_course
$sql = "SELECT MAX(id) AS last_id FROM tbl_course"; // Assuming your primary key is 'id'

// Step 3: Execute the query
$result = $conn->query($sql);

// Step 4: Fetch the result
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_inserted_id = $row['last_id'];
} else {
    echo "No records found in tbl_course.";
}


// Function to format year level based on a numeric value

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $program_codes = isset($_SESSION['program_code']) ? $_GET['program_code'] : "";
    $year_level = isset($_POST['year_level']) ? $_POST['year_level'] : "";
    $num_year = isset($_SESSION['num_year']) ? $_SESSION['num_year'] : "";
    $course_name = strtoupper($_POST['course_name']);
    $course_type = $_POST['course_type'];
    $credit = $_POST['credit'];
    $lec_hrs = $_POST['lec_hrs'];
    $lab_hrs = $_POST['lab_hrs'];
    $action = $_POST['action'];
    $course_id = $_POST['course_id'];
    $semester = $_SESSION['semester'];
    $ay_code = $_SESSION['ay_code'];
    $allowed_rooms = $_POST['allowed_rooms'];
    $petition = isset($_POST['petition']) ? 1 : 0;

    $course = strtoupper(trim($_POST['course'] ?? ''));
    $course_number = trim($_POST['course_number'] ?? '');

    // Combine with a space in between
    $course_code = $course . ' ' . $course_number;



    $computer_room = 0;
    if ($allowed_rooms === 'labR' || $allowed_rooms === 'lecR&labR') {
        $computer_room = 1;
    }
    if (isset($_GET['program_code']) && !isset($_SESSION['program_code'])) {
        $_SESSION['program_code'] = $_GET['program_code'];
    }
    if (isset($_GET['curriculum']) && !isset($_SESSION['curriculum'])) {
        $_SESSION['curriculum'] = $_GET['curriculum'];
    }
    $curriculum = isset($_SESSION['curriculum']) ? $_SESSION['curriculum'] : "";

    if (!is_array($program_codes)) {
        $program_codes = array_filter([$program_codes]); // Remove empty values
    }
    if ($course_type === 'Minor') {
        $input_dept_code = $_POST['dept_code'];
    } else {
        $input_dept_code = $_SESSION['dept_code'];
    }
    foreach ($program_codes as $program_code) {
        if ($action == 'add') {
            $check_sql = "SELECT * FROM tbl_course WHERE (course_code = ? OR course_name = ?) AND dept_code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sss", $course_code, $course_name, $dept_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                echo "<script>
                          document.addEventListener('DOMContentLoaded', function() {
                              var modal = new bootstrap.Modal(document.getElementById('successModal'));
                              document.getElementById('successMessage').textContent = 'Record already exists.'; 
                              modal.show();
                          });
                      </script>";
            } else {
                // Modified SQL to include allowed_rooms and computer_room
                $sql = "INSERT INTO tbl_course (dept_code, program_code, curriculum, year_level, semester, course_code, course_name, course_type, credit, lec_hrs, lab_hrs, allowed_rooms, computer_room, petition, ay_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssssssssss", $input_dept_code, $program_code, $curriculum, $year_level, $semester, $course_code, $course_name, $course_type, $credit, $lec_hrs, $lab_hrs, $allowed_rooms, $computer_room, $petition, $ay_code);

                if ($stmt->execute()) {
                    echo "<script>
                              document.addEventListener('DOMContentLoaded', function() {
                                  var modal = new bootstrap.Modal(document.getElementById('successModal'));
                                  document.getElementById('successMessage').textContent = 'Record added successfully.';
                                  modal.show();
                              });
                          </script>";
                } else {
                    echo "<script>
                              document.addEventListener('DOMContentLoaded', function() {
                                  var modal = new bootstrap.Modal(document.getElementById('successModal'));
                                  document.getElementById('successMessage').textContent = 'Error: " . $stmt->error . "' ;
                                  modal.show();
                              });
                          </script>";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } elseif ($action == 'update') {
            $old_course_code = '';
            $old_course_sql = "SELECT course_code FROM tbl_course WHERE id = ?";
            $old_course_stmt = $conn->prepare($old_course_sql);
            $old_course_stmt->bind_param("s", $course_id);
            $old_course_stmt->execute();
            $old_course_stmt->bind_result($old_course_code);
            $old_course_stmt->fetch();
            $old_course_stmt->close();

            $sql = "UPDATE tbl_course SET dept_code=?, curriculum=?, year_level=?, semester=?, course_name=?, course_type=?, credit=?, allowed_rooms=?, computer_room = ?, lec_hrs=?, lab_hrs=?, course_code=?, petition = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssssss",
                $input_dept_code,
                $curriculum,
                $year_level,
                $semester,
                $course_name,
                $course_type,
                $credit,
                $allowed_rooms,
                $computer_room,
                $lec_hrs,
                $lab_hrs,
                $course_code,
                $petition,
                $course_id
            );
            $stmt->execute();
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Record Updated Successfully.';
                modal.show();
            });
        </script>";

            // Check if records exist in tbl_assigned_course with the given criteria
            $check_assigned_course_sql = "SELECT COUNT(*) FROM tbl_assigned_course WHERE course_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
            $check_assigned_course_stmt = $conn->prepare($check_assigned_course_sql);
            $check_assigned_course_stmt->bind_param("ssss", $old_course_code, $semester, $ay_code, $input_dept_code);
            $check_assigned_course_stmt->execute();
            $check_assigned_course_stmt->bind_result($assigned_course_count);
            $check_assigned_course_stmt->fetch();
            $check_assigned_course_stmt->close();

            // If records exist, update the course_code in tbl_assigned_course
            if ($assigned_course_count > 0) {
                $update_assigned_course_sql = "UPDATE tbl_assigned_course SET course_code = ? WHERE course_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                $update_assigned_course_stmt = $conn->prepare($update_assigned_course_sql);
                $update_assigned_course_stmt->bind_param("sssss", $course_code, $old_course_code, $semester, $ay_code, $input_dept_code);

                if ($update_assigned_course_stmt->execute()) {
                    // echo "<script>
                    // document.addEventListener('DOMContentLoaded', function() {
                    //     var modal = new bootstrap.Modal(document.getElementById('successModal'));
                    //     document.getElementById('successMessage').textContent = 'Record Updated Successfully.';
                    //     modal.show();
                    // });
                    // </script>";
                }

                $update_assigned_course_stmt->close();
            } else {
                // No records found in tbl_assigned_course
                echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var modal = new bootstrap.Modal(document.getElementById('infoModal'));
                            document.getElementById('infoMessage').textContent = 'No matching records found in tbl_assigned_course to update.';
                            modal.show();
                        });
                        </script>";
            }


            if ($stmt->execute()) {
                // Get the sanitized schedule table names
                $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$input_dept_code}_{$ay_code}");
                $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$input_dept_code}_{$ay_code}");
                $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$input_dept_code}_{$ay_code}");

                // echo "sanitized Section Sced: $sanitized_section_sched_code<br>";
                // echo "sanitized room Sced: $sanitized_room_sched_code<br>";
                // echo "sanitized prof Sced: $sanitized_prof_sched_code<br>";


                // Update course code in related tables
                $tables = [
                    $sanitized_section_sched_code,
                    $sanitized_room_sched_code,
                    $sanitized_prof_sched_code,
                ];

                $any_update = false; // Flag to check if any updates were made
                foreach ($tables as $table) {
                    $check_table_sql = "SHOW TABLES LIKE '$table'";
                    $check_table_result = $conn->query($check_table_sql);

                    if ($check_table_result && $check_table_result->num_rows > 0) {

                        $check_sql = "SELECT COUNT(*) FROM $table WHERE course_code = ? AND dept_code = ? AND semester = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("sss", $old_course_code, $input_dept_code, $semester);
                        $check_stmt->execute();
                        $check_stmt->bind_result($count);
                        $check_stmt->fetch();
                        $check_stmt->close();

                        if ($count > 0) {
                            $update_sql = "UPDATE $table SET course_code = ? WHERE course_code = ? AND dept_code = ? AND semester = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param("ssss", $course_code, $old_course_code, $input_dept_code, $semester);

                                if ($update_stmt->execute()) {
                                    $any_update = true;
                                    /* echo "<script>alert('Record updated in schedules.'); window.location.href = 'course_input.php';</script>"; */
                                } else {
                                    $message .= "Error updating schedules: " . $update_stmt->error . " ";
                                }
                                $update_stmt->close();
                            } else {
                                $message .= "Error preparing update statement for schedules: " . $conn->error . " ";
                            }
                        }
                    } else {
                        // // Optionally log a message for missing tables
                        // $message .= "Table $table does not exist. Skipping. ";
                    }
                }

                // echo "$table old course code: $old_course_code<br>";
                // echo "$table new course code: $course_code<br>";
                // echo "$table semester: $semester<br>";
                // echo "$table dept code: $input_dept_code<br><br>";


                // If no updates were made, update the course_code in tbl_course
                if (!$any_update) {
                    $update_course_sql = "UPDATE tbl_course SET course_code = ? WHERE course_code = ? AND semester = ?";
                    $update_course_stmt = $conn->prepare($update_course_sql);
                    if ($update_course_stmt) {
                        $update_course_stmt->bind_param("sss", $course_code, $old_course_code, $semester);

                        if ($update_course_stmt->execute()) {
                            /* echo "<script>alert('Course code updated in tbl_course.'); window.location.href = 'course_input.php';</script>"; */
                        }
                        $update_course_stmt->close();
                    }
                }
            }

            $dept_code = $_SESSION['dept_code'];
            $receiver_dept_codes = [];

            $shared_sched_sql = "SELECT receiver_dept_code FROM tbl_shared_sched WHERE sender_dept_code = ? AND semester = ?";
            $shared_sched_stmt = $conn->prepare($shared_sched_sql);

            if ($shared_sched_stmt) {
                $shared_sched_stmt->bind_param("ss", $dept_code, $semester);
                $shared_sched_stmt->execute();
                $shared_sched_stmt->bind_result($receiver_dept_code);

                while ($shared_sched_stmt->fetch()) {
                    $receiver_dept_codes[] = $receiver_dept_code;
                }
                $shared_sched_stmt->close();
            }

            if (!empty($receiver_dept_codes)) {
                // echo "Receiver dept codes: " . implode(', ', $receiver_dept_codes) . "<br><br>";
                foreach ($receiver_dept_codes as $receiver_dept_code) {
                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");

                    // echo "sanitized Receiver Sender Section Sched: $sanitized_section_sched_code<br>";
                    // echo "sanitized Receiver Room Sched: $sanitized_room_sched_code<br>";
                    // echo "sanitized Receiver Prof Sched: $sanitized_prof_sched_code<br><br>";

                    $shared_tables = [
                        $sanitized_section_sched_code,
                        $sanitized_room_sched_code,
                        $sanitized_prof_sched_code,
                    ];

                    foreach ($shared_tables as $table) {

                        // Check if the table exists before proceeding
                        $check_table_sql = "SHOW TABLES LIKE '$table'";
                        $table_check_result = $conn->query($check_table_sql);

                        // If the table doesn't exist, skip this iteration and move to the next table
                        if ($table_check_result->num_rows == 0) {
                            continue;
                        }

                        // Check if the course_code and dept_code (receiver_dept_code) match
                        $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE course_code = ? AND dept_code = ? AND semester = ?";
                        $check_shared_stmt = $conn->prepare($check_shared_sql);
                        $check_shared_stmt->bind_param("sss", $old_course_code, $receiver_dept_code, $semester);
                        $check_shared_stmt->execute();
                        $check_shared_stmt->bind_result($shared_count);
                        $check_shared_stmt->fetch();
                        $check_shared_stmt->close();

                        if ($shared_count > 0) {
                            // Update course_code for the shared schedule
                            $update_shared_sql = "UPDATE $table SET course_code = ? WHERE course_code = ? AND dept_code = ? AND semester";
                            $update_shared_stmt = $conn->prepare($update_shared_sql);
                            if ($update_shared_stmt) {
                                $update_shared_stmt->bind_param("ssss", $course_code, $old_course_code, $receiver_dept_code, $semester);

                                if ($update_shared_stmt->execute()) {
                                    $any_update = true;
                                }

                                // echo "Receiver table Old Course Code: $old_course_code<br>";
                                // echo "Receiver table New Course Code: $course_code<br>";
                                // echo "Receiver Semester: $semester<br>";
                                // echo "Receiver table Dept Code: $receiver_dept_code<br><br>";

                                $update_shared_stmt->close();
                            } else {
                                echo "Error preparing update statement for table $table: " . $conn->error . "<br>";
                            }
                        }
                    }
                }
            }
            // echo "No receiver dept codes found.<br><br>";

            // Check for records where receiver_dept_code = $dept_code
            $sender_sched_sql = "SELECT sender_dept_code FROM tbl_shared_sched WHERE receiver_dept_code = ?";
            $sender_sched_stmt = $conn->prepare($sender_sched_sql);
            if ($sender_sched_stmt) {
                $sender_sched_stmt->bind_param("s", $dept_code);
                $sender_sched_stmt->execute();
                $sender_sched_stmt->bind_result($sender_dept_code);

                if ($sender_sched_stmt->fetch()) {
                    // If a record is found, use the sender_dept_code to update course_code
                    echo "Sender Dept Code found: $sender_dept_code<br>";

                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");

                    // Display sanitized schedule codes
                    // echo "Receiver Sanitized Section Sched: $sanitized_section_sched_code<br>";
                    // echo "Receiver Sanitized Room Sched: $sanitized_room_sched_code<br>";
                    // echo "Receiver Sanitized Prof Sched: $sanitized_prof_sched_code<br><br>";

                    $shared_tables = [
                        $sanitized_section_sched_code,
                        $sanitized_room_sched_code,
                        $sanitized_prof_sched_code,
                    ];

                    // Close the sender statement before proceeding to avoid sync issues
                    $sender_sched_stmt->close();

                    // Now handle the check and update process
                    foreach ($shared_tables as $table) {
                        // Prepare to check for existing records
                        $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE course_code = ? AND dept_code = ? AND semester = ?";
                        $check_shared_stmt = $conn->prepare($check_shared_sql);
                        $check_shared_stmt->bind_param("sss", $old_course_code, $dept_code, $semester);
                        $check_shared_stmt->execute();
                        $check_shared_stmt->store_result(); // Store result to avoid sync issues
                        $check_shared_stmt->bind_result($shared_count);
                        $check_shared_stmt->fetch();
                        $check_shared_stmt->close();

                        // Only update if records exist
                        if ($shared_count > 0) {
                            $update_shared_sql = "UPDATE $table SET course_code = ? WHERE course_code = ? AND dept_code = ? AND semester = ?";
                            $update_shared_stmt = $conn->prepare($update_shared_sql);
                            if ($update_shared_stmt) {
                                $update_shared_stmt->bind_param("ssss", $course_code, $old_course_code, $dept_code, $semester);
                                if ($update_shared_stmt->execute()) {
                                    echo "Updated course_code in table $table for receiver_dept_code: $sender_dept_code<br>";
                                } else {
                                    echo "Failed to update course_code in table $table for receiver_dept_code: $sender_dept_code. Error: " . $update_shared_stmt->error . "<br>";
                                }
                                $update_shared_stmt->close();

                                // // Display old and new room codes
                                // echo "Receiver Old Course Code: $old_course_code<br>";
                                // echo "Receiver New Course Code: $course_code<br>";
                                // echo "Receiver Semester: $semester<br>";
                                // echo "Receiver Dept Code: $receiver_dept_code<br><br>";
                            } else {
                                echo "Error preparing update statement for table $table: " . $conn->error . "<br>";
                            }
                        } else {
                            // No records found for the given course_code and dept_code
                            echo "No records found in table $table for course_code: $old_course_code and dept_code: $dept_code<br><br>";
                        }
                    }
                }


                // echo "<script>
                //         alert(\"There's plotted schedule in $room_code. You can't delete the record.\");
                //         window.location.href = 'classroom_input.php'; // Redirect back to the page
                //     </script>";
            }
        } elseif ($action === 'delete') {
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

            // echo "Sanitized Sender Section Sched: $sanitized_section_sched_code<br>";
            // echo "Sanitized Sender Room Sched: $sanitized_room_sched_code<br>";
            // echo "Sanitized Sender Prof Sched: $sanitized_prof_sched_code<br><br>";

            $shared_tables = [
                $sanitized_section_sched_code,
                $sanitized_room_sched_code,
                $sanitized_prof_sched_code,
            ];

            $canDelete = true;

            foreach ($shared_tables as $table) {
                $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE course_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                $check_shared_stmt = $conn->prepare($check_shared_sql);
                if ($check_shared_stmt) {
                    $check_shared_stmt->bind_param("ssss", $course_code, $dept_code, $semester, $ay_code);
                    $check_shared_stmt->execute();
                    $check_shared_stmt->bind_result($sched_count);
                    $check_shared_stmt->fetch();
                    $check_shared_stmt->close();
                }

                // echo "$course_code";
                // echo "$sched_count";

                if ($sched_count > 0) {
                    echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modal = new bootstrap.Modal(document.getElementById('successModal'));
                            document.getElementById('successMessage').textContent = 'Deletion not Allowed: There is a plotted schedule with $course_code.';
                        modal.show();
                    });
                </script>";
                    $canDelete = false;
                    break;
                }
            }
            if ($canDelete) {
                $delete_sql = "DELETE FROM tbl_course WHERE course_code=? AND semester = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                if ($delete_stmt === false) {
                    $message = "Error preparing statement: " . $conn->error;
                } else {
                    $delete_stmt->bind_param("ss", $course_code, $semester);
                    if ($delete_stmt->execute()) {
                        echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var modal = new bootstrap.Modal(document.getElementById('successModal'));
                            document.getElementById('successMessage').textContent = 'Record successfully deleted';
                            modal.show();
                        });
                    </script>";
                    }
                    $delete_stmt->close();
                }
            }
        } else {
            $message = "Invalid action";
        }
    }
}

// Handle GET request for filtering
$search_semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$search_year = isset($_GET['year_level']) ? $_GET['year_level'] : '';
$search_program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
$search_course_code_name = isset($_GET['course_code_name']) ? $_GET['course_code_name'] : '';

// Initialize base SQL queries
$sql = "SELECT * FROM tbl_course WHERE 1=1"; // 'WHERE 1=1' is a trick to simplify appending conditions
$count_sql = "SELECT COUNT(*) as total FROM tbl_course WHERE 1=1";

$params = [];
$types = "";

// Fetch program codes for the dept_code
$sql_program_codes = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
$stmt_program_codes = $conn->prepare($sql_program_codes);
$stmt_program_codes->bind_param("s", $dept_code);
$stmt_program_codes->execute();
$result_program_codes = $stmt_program_codes->get_result();
$program_codes = [];
while ($row = $result_program_codes->fetch_assoc()) {
    $program_codes[] = $row['program_code'];
}
$stmt_program_codes->close();

// Check if program_codes array is empty
if (empty($program_codes)) {
    $no_program = "No program codes found for the specified department.";
} else {
    // Convert the array to a comma-separated string for placeholders
    $program_codes_placeholder = implode(',', array_fill(0, count($program_codes), '?'));

    $sql .= " AND program_code IN ($program_codes_placeholder)";
    $count_sql .= " AND program_code IN ($program_codes_placeholder)";

    // Add the program codes to the params array
    $params = array_merge($params, $program_codes);
    $types .= str_repeat('s', count($program_codes));

    // Apply additional filters
    if (!empty($search_semester) && $search_semester !== "ALL") {
        $sql .= " AND semester = ?";
        $count_sql .= " AND semester = ?";
        $params[] = $search_semester;
        $types .= "s";
    }

    if (!empty($search_year) && $search_year !== "ALL") {
        $sql .= " AND year_level = ?";
        $count_sql .= " AND year_level = ?";
        $params[] = $search_year;
        $types .= "s";
    }

    if (!empty($search_course_type) && $search_course_type !== "ALL") {
        $sql .= " AND course_type = ?";
        $count_sql .= " AND course_type = ?";
        $params[] = $search_course_type;
        $types .= "s";
    }

    if (!empty($search_program_code) && $search_program_code !== "ALL") {
        $sql .= " AND program_code = ?";
        $count_sql .= " AND program_code = ?";
        $params[] = $search_program_code;
        $types .= "s";
    } else {
        // When program_code is "ALL" or empty, don't filter by program_code
        $sql .= " AND (program_code IS NOT NULL)";
        $count_sql .= " AND (program_code IS NOT NULL)";
    }

    if (!empty($search_course_code_name)) {
        $sql .= " AND (course_code LIKE ? OR course_name LIKE ?)";
        $count_sql .= " AND (course_code LIKE ? OR course_name LIKE ?)";
        $params[] = "%$search_course_code_name%";
        $params[] = "%$search_course_code_name%";
        $types .= "ss";
    }

    // Prepare and execute the count statement
    $count_stmt = $conn->prepare($count_sql);
    if ($types) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_rows = $count_result->fetch_assoc()['total'];
    $count_stmt->close();

    // Prepare and execute the data retrieval statement
    $stmt = $conn->prepare($sql);

    // Bind the parameters
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}


$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Course Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="orig-logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/course_input.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>


    <body>

        <section class="course-input">

            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link " id="program-tab" href="program_input.php" aria-controls="program" aria-selected="true">Program Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" id="course-tab" href="course_input.php" aria-controls="course" aria-selected="false">Checklist Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="section-tab" href="section_input.php" aria-controls="section" aria-selected="true">Section Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="room-tab" href="classroom_input.php" aria-controls="room" aria-selected="false">Room Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="prof-tab" href="#" aria-controls="prof" aria-selected="false" data-bs-toggle="modal" data-bs-target="#profUnitModal">Instructor Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="sgintaory-tab" href="signatory_input.php" aria-controls="signatory" aria-selected="false">Signatory Input</a>
                </li>
            </ul>

            <div class="text-center">
                <form method="GET" action="" class="d-inline-block w-100">
                    <div class="filtering d-flex flex-wrap justify-content-center">
                        <!-- Year Level Filter -->
                        <div class="form-group col-md-3">
                            <select class="form-control w-100" id="year_filter" name="year_level" style="color: #6c757d;">
                                <option value="" disabled selected style="color: #6c757d;">Year Level</option>
                                <option value="">All</option>
                                <?php
                                // Fetch year levels based on program_code and curriculum
                                if ($program_code && $curriculum) {
                                    $sql = "SELECT num_year FROM tbl_program WHERE program_code = ? AND curriculum = ?";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->bind_param("ss", $program_code, $curriculum);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result->num_rows > 0) {
                                        $row = $result->fetch_assoc();
                                        $num_year = $row['num_year'];

                                        for ($i = 1; $i <= $num_year; $i++) {
                                            // Determine the label for the year level
                                            $year_label = ($i == 1) ? "1st Year" : (($i == 2) ? "2nd Year" : (($i == 3) ? "3rd Year" : (($i == 4) ? "4th Year" : "$i" . "th Year")));
                                            $selected = ($search_year == $year_label) ? 'selected' : ''; // Adjust selection check for label-based values
                                            echo "<option value=\"$year_label\" $selected>$year_label</option>";
                                        }
                                    } else {
                                        echo "<option value=\"\" disabled>No year levels found for the selected program.</option>";
                                    }
                                    $stmt->close();
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Course Type Filter -->
                        <div class="form-group col-md-3">
                            <select class="form-control w-100" id="course_type_filter" name="course_type" style="color: #6c757d;">
                                <option value="" disabled selected style="color: #6c757d;">Course Type</option>
                                <option value="">All</option>
                                <?php
                                // Check if course_type is set in the form submission (POST or GET)
                                $selected_course_type = isset($_POST['course_type']) ? $_POST['course_type'] : (isset($_GET['course_type']) ? $_GET['course_type'] : '');

                                // Fetch distinct course types from the database
                                $sql = "SELECT DISTINCT course_type FROM tbl_course";
                                $result = $conn->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $course_type = $row['course_type'];
                                        // Set selected if the current course type matches the selected value
                                        $selected = ($selected_course_type == $course_type) ? "selected" : "";
                                        echo "<option value='" . htmlspecialchars($course_type) . "' $selected>" . htmlspecialchars($course_type) . "</option>";
                                    }
                                } else {
                                    echo "<option value=\"\" disabled>No course types available</option>";
                                }
                                ?>
                            </select>
                        </div>


                        <!-- Course Code/Name Filter -->
                        <div class="form-group col-md-3">
                            <input type="text" class="form-control w-100" id="course_code_name_filter" name="course_code_name" value="<?php echo htmlspecialchars($search_course_code_name); ?>" placeholder="Course Code or Name">
                        </div>

                        <!-- Submit Button -->
                        <div class="form-group col-md-2 d-flex align-items-end">
                            <!-- Preserve program_code and curriculum in hidden inputs -->
                            <input type="hidden" name="program_code" value="<?php echo htmlspecialchars($program_code); ?>">
                            <input type="hidden" name="curriculum" value="<?php echo htmlspecialchars($curriculum); ?>">

                            <button type="submit" class="btn btn-success w-100" style="border: none;">Search</button>
                        </div>
                    </div>
                </form>
            </div>


            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="title">Checklist Input</h5>
                    <form action="" method="POST" id="courseForm" required>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                        <input type="hidden" id="course_id" name="course_id" value="<?php echo $last_inserted_id; ?>" readonly>

                        <?php
                        $_SESSION['program_code'] = $_GET['program_code'] ?? '';
                        $_SESSION['curriculum'] = $_GET['curriculum'] ?? '';
                        $_SESSION['num_year'] = $_GET['num_year'] ?? '';


                        // echo "$program_code";

                        if ($program_code && $curriculum) {
                            // Fetch num_year from the database based on program_code and curriculum
                            $sql = "SELECT num_year FROM tbl_program WHERE program_code = ? AND curriculum = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ss", $program_code, $curriculum);

                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $num_year = $row['num_year'];
                                } else {
                                    $num_year = 0;
                                    echo "No record found.<br>";
                                }
                            } else {
                                echo "Error executing query.<br>";
                            }
                        }
                        ?>

                        <input type='text' id='program_code' name='program_code' value='<?php echo htmlspecialchars($program_code); ?>' readonly class='input-field'><br>
                        <input type='text' id='curriculum' name='curriculum' value='<?php echo htmlspecialchars($curriculum); ?>' readonly class='input-field'><br>

                        <div class="mb-3">
                            <select class="form-control" id="year_level" name="year_level" style="color: #6c757d;" required>
                                <option value="" disabled selected>Year Level</option>
                            </select>
                            <input type="hidden" id="original_section_code" name="original_section_code" value="">
                        </div>

                        <script>
                            var numYear = <?php echo $num_year; ?>;
                            console.log("Number of years: " + numYear); // Logging the value in the browser console.

                            function getSuffix(num) {
                                if (num === 1) return 'st';
                                if (num === 2) return 'nd';
                                if (num === 3) return 'rd';
                                return 'th';
                            }

                            function populateYearLevels() {
                                var yearDropdown = document.getElementById("year_level");
                                yearDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>'; // Clear previous options

                                // Loop through the number of years and populate the dropdown
                                for (var i = 1; i <= numYear; i++) {
                                    var option = document.createElement("option");
                                    var suffix = getSuffix(i); // Get the correct suffix for the year
                                    var yearText = i + suffix + " Year";
                                    option.value = yearText; // Set the value to the text (e.g., "1st Year")
                                    option.textContent = yearText; // Display text
                                    yearDropdown.appendChild(option);
                                }
                            }
                            populateYearLevels();
                        </script>

                        <div class="mb-3" id="petitionContainer" style="display: none;">
                            <input type="checkbox" id="petition" name="petition" value="1">
                            <label for="petition">Is this program for petition?</label>
                        </div>

                        <script>
                            function getSuffix(num) {
                                if (num >= 11 && num <= 13) {
                                    return "th";
                                }
                                switch (num % 10) {
                                    case 1:
                                        return "st";
                                    case 2:
                                        return "nd";
                                    case 3:
                                        return "rd";
                                    default:
                                        return "th";
                                }
                            }

                            function populateYearLevels() {
                                var yearDropdown = document.getElementById("year_level");
                                var petitionContainer = document.getElementById("petitionContainer");

                                yearDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>'; // Clear previous options

                                for (var i = 1; i <= numYear; i++) {
                                    var option = document.createElement("option");
                                    var suffix = getSuffix(i);
                                    var yearText = i + suffix + " Year";
                                    option.value = yearText; // Store the formatted text
                                    option.textContent = yearText;
                                    yearDropdown.appendChild(option);
                                }

                                yearDropdown.addEventListener("change", function() {
                                    if (this.value.includes(numYear)) {
                                        petitionContainer.style.display = "block";
                                    } else {
                                        petitionContainer.style.display = "none";
                                    }
                                });
                            }

                            populateYearLevels();
                        </script>

                        <div class="mb-3 position-relative">
                            <select class="form-control" id="course_type" name="course_type" style="color: #6c757d;" required>
                                <option value="">Course Type</option>
                                <option value="Minor">Minor Subject</option>
                                <option value="Major">Major Subject</option>
                            </select>
                        </div>

                        <div class="mb-3 position-relative" id="dept_select_container" style="display:none;">
                            <select class="form-control" id="dept_code" name="dept_code" style="color: #6c757d;">
                                <option value="">Select College</option>
                                <?php

                                $excluded_college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : '';

                                // Query to select colleges excluding the one in the session
                                $query = "SELECT college_name, college_code FROM tbl_college WHERE college_code != ?";
                                $stmt = mysqli_prepare($conn, $query);

                                if ($stmt) {
                                    // Bind the excluded college_code parameter
                                    mysqli_stmt_bind_param($stmt, "s", $excluded_college_code);
                                    mysqli_stmt_execute($stmt);
                                    $results = mysqli_stmt_get_result($stmt);

                                    // Check if there are results
                                    if ($results && mysqli_num_rows($results) > 0) {
                                        while ($row = mysqli_fetch_assoc($results)) {
                                            $college_name = htmlspecialchars($row['college_name']);
                                            $college_code = htmlspecialchars($row['college_code']);
                                            echo "<option value='" . $college_code . "'>" . "($college_code)" . " - " . $college_name . "</option>";
                                        }
                                    } else {
                                        echo '<option value="">No colleges found</option>';
                                    }

                                    mysqli_stmt_close($stmt);
                                } else {
                                    echo '<option value="">Error preparing statement</option>';
                                }
                                ?>

                            </select>
                        </div>


                        <div class="mb-3 d-flex gap-2">
                            <div style="flex: 1;">
                                <input type="text" class="form-control" id="course" name="course" placeholder="Enter Course (e.g., IT, CS)" autocomplete="off" style="color: #6c757d;" required>
                            </div>
                            <div style="flex: 1;">
                                <input type="text" class="form-control" id="course_number" name="course_number" placeholder="Enter Course Number (e.g., 101)" autocomplete="off" style="color: #6c757d;" required>
                            </div>
                        </div>


                        <div class="mb-3">
                            <!-- <label for="department-code">Course Name</label> -->
                            <input type="text" class="form-control" id="course_name" placeholder="Enter Course Name" name="course_name"
                                autocomplete="off" style="color: #6c757d;" required>
                        </div>

                        <div class="mb-3">
                            <!-- <label for="department-code">Credit</label> -->
                            <input type="number" class="form-control" id="credit" placeholder="Enter Credit" name="credit"
                                autocomplete="off" style="color: #6c757d;" required>
                        </div>

                        <div class="mb-3">
                            <select id="allowed_rooms" name="allowed_rooms" class="form-control" style="color: #6c757d;" required>
                                <option value="" disabled selected>Select Room Allowed</option>
                                <option value="lecR">Lecture</option>
                                <option value="labR">Laboratory</option>
                                <option value="lecR&labR">Both</option>
                            </select>
                        </div>

                        <?php
                        // Assuming you have fetched the `computer_room` value from the database
                        $computer_room_value = 1; // Example value fetched from `tbl_course`

                        ?>
                        <!-- Checkbox container, initially hidden -->
                        <div id="computer_room_container" class="mb-3" style="display: none;">
                            <input type="checkbox" id="computer_room" name="computer_room"
                                <?php echo $computer_room_value == 1 ? 'checked' : ''; ?>>
                            <label for="computer_room">Is a computer room required for this?</label>
                        </div>


                        <script>
                            // Get elements
                            const allowedRooms = document.getElementById('allowed_rooms');
                            const computerRoomContainer = document.getElementById('computer_room_container');
                            const computerRoomCheckbox = document.getElementById('computer_room');

                            document.getElementById('allowed_rooms').addEventListener('change', function() {
                                const container = document.getElementById('computer_room_container');
                                if (this.value === 'labR' || this.value === 'lecR&labR') {
                                    container.style.display = 'block';
                                } else {
                                    container.style.display = 'none';
                                }
                            });

                            // Optionally, handle the checkbox state when submitting the form
                            function getComputerRoomValue() {
                                if (computerRoomCheckbox.checked) {
                                    return 1; // Set computer_room value to 1 if checked
                                }
                                return 0; // Set to 0 if not checked
                            }



                            // You can use `getComputerRoomValue()` to send the data to the server (e.g., via AJAX or form submission)
                        </script>

                        <div class="mb-3" id="computer_room_container" style="display: none;">
                            <input type="checkbox" id="computer_room" name="computer_room">
                            <label for="computer_room">Is this a Computer Room?</label>
                        </div>

                        <script>
                            document.getElementById("allowed_rooms").addEventListener("change", function() {
                                var selectedValue = this.value;
                                var computerRoomContainer = document.getElementById("computer_room_container");

                                if (selectedValue === "labR" || selectedValue === "lecR&labR") {
                                    computerRoomContainer.style.display = "block";
                                } else {
                                    computerRoomContainer.style.display = "none";
                                }
                            });
                        </script>


                        <div class="mb-3">
                            <!-- <label for="department-code">Lecture Hours</label> -->
                            <input type="number" class="form-control" id="lec_hrs" placeholder="Enter Lecture Hours" name="lec_hrs"
                                autocomplete="off" style="color: #6c757d;" required>
                        </div>

                        <div class="mb-3">
                            <!-- <label for="department-code">Laboratory Hours</label> -->
                            <input type="number" class="form-control" id="lab_hrs" placeholder="Enter Laboratory Hours" name="lab_hrs"
                                autocomplete="off" style="color: #6c757d;" required>
                        </div>

                        <div class="button-group">
                            <button type="submit" name="action" value="add" class="btn btn-add">Add</button>
                            <div class="btn-inline-group">
                                <button type="submit" name="action" value="update" class="btn btn-primary btn-update-delete" style="display: none;">Update</button>
                                <button type="submit" name="action" value="delete" class="btn btn-danger btn-update-delete" style="display: none;">Delete</button>
                            </div>
                        </div>

                    </form>
                </div>

                <div class="col-lg-8 mb-4">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="filterable" data-column="year_level">Year Level</th>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th class="filterable" data-column="course_type">Course Type</th>
                                    <th>Credit</th>
                                    <th>Lecture Hours</th>
                                    <th>Lab Hours</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Start session if not already started
                                if (session_status() == PHP_SESSION_NONE) {
                                    session_start();
                                }

                                $search_semester = isset($_GET['semester']) ? $_GET['semester'] : '';
                                $search_year = isset($_GET['year_level']) ? $_GET['year_level'] : '';
                                $search_program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
                                $search_course_code_name = isset($_GET['course_code_name']) ? $_GET['course_code_name'] : '';
                                $search_course_type = isset($_GET['course_type']) ? $_GET['course_type'] : '';

                                // Initialize base SQL queries with dept_code and semester filtering
                                $sql = "SELECT * FROM tbl_course WHERE program_code = ? AND semester = ? and curriculum = ?";
                                $count_sql = "SELECT COUNT(*) as total FROM tbl_course WHERE program_code = ? AND semester = ? AND curriculum = ?";

                                $params = [];
                                $types = "sss";

                                // Fetch program codes for the dept_code
                                $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';
                                $semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
                                $program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
                                $curriculum = isset($_GET['curriculum']) ? $_GET['curriculum'] : '';
                                $search_course_type = isset($_GET['course_type']) ? $_GET['course_type'] : '';
                                $num_year = isset($_GET['num_year']) ? $_GET['num_year'] : '';

                                $params[] = $program_code;
                                $params[] = $semester;
                                $params[] = $curriculum;

                                // echo "DEPT CODE: $dept_code<br>";
                                // echo "SEMESTER: $semester<br>";

                                // Apply additional filters
                                if (!empty($search_semester) && $search_semester !== "ALL") {
                                    $sql .= " AND semester = ?";
                                    $count_sql .= " AND semester = ?";
                                    $params[] = $search_semester;
                                    $types .= "s";
                                }

                                if (!empty($search_year) && $search_year !== "ALL") {
                                    $sql .= " AND year_level = ?";
                                    $count_sql .= " AND year_level = ?";
                                    $params[] = $search_year;
                                    $types .= "s";
                                }

                                if (!empty($search_program_code) && $search_program_code !== "ALL") {
                                    $sql .= " AND program_code = ?";
                                    $count_sql .= " AND program_code = ?";
                                    $params[] = $search_program_code;
                                    $types .= "s";
                                }

                                if (!empty($search_course_code_name)) {
                                    $sql .= " AND (course_code LIKE ? OR course_name LIKE ?)";
                                    $count_sql .= " AND (course_code LIKE ? OR course_name LIKE ?)";
                                    $params[] = "%$search_course_code_name%";
                                    $params[] = "%$search_course_code_name%";
                                    $types .= "ss";
                                }

                                if (!empty($search_course_type) && $search_course_type !== "ALL") {
                                    $sql .= " AND course_type = ?";
                                    $count_sql .= " AND course_type = ?";
                                    $params[] = $search_course_type;
                                    $types .= "s";
                                }

                                // Prepare and execute the count statement
                                $count_stmt = $conn->prepare($count_sql);
                                if ($types) {
                                    $count_stmt->bind_param($types, ...$params);
                                }
                                $count_stmt->execute();
                                $count_result = $count_stmt->get_result();
                                $total_rows = $count_result->fetch_assoc()['total'];
                                $count_stmt->close();

                                // Prepare and execute the data retrieval statement
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param($types, ...$params);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                // Check if results are returned
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                ?>
                                        <tr data-course_id="<?php echo htmlspecialchars($row['id']); ?>"
                                            data-dept_code="<?php echo htmlspecialchars($row['dept_code']); ?>"
                                            data-program_code="<?php echo htmlspecialchars($row['program_code']); ?>"
                                            data-curriculum="<?php echo htmlspecialchars($row['curriculum']); ?>"
                                            data-year_level="<?php echo htmlspecialchars($row['year_level']); ?>"
                                            data-course_code="<?php echo htmlspecialchars($row['course_code']); ?>"
                                            data-course_name="<?php echo htmlspecialchars($row['course_name']); ?>"
                                            data-course_type="<?php echo htmlspecialchars($row['course_type']); ?>"
                                            data-credit="<?php echo htmlspecialchars($row['credit']); ?>"
                                            data-allowed_rooms="<?php echo htmlspecialchars($row['allowed_rooms']); ?>"
                                            data-lec_hrs="<?php echo htmlspecialchars($row['lec_hrs']); ?>"
                                            data-lab_hrs="<?php echo htmlspecialchars($row['lab_hrs']); ?>"
                                            data-petition="<?php echo htmlspecialchars($row['petition']); ?>"
                                            data-computer_room="<?php echo htmlspecialchars($row['computer_room']); ?>">


                                            <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                                            <td><?php echo htmlspecialchars($row['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['course_type']); ?></td>
                                            <td><?php echo htmlspecialchars($row['credit']); ?></td>

                                            <td><?php echo htmlspecialchars($row['lec_hrs']); ?></td>
                                            <td><?php echo htmlspecialchars($row['lab_hrs']); ?></td>
                                            <td><?php echo $row['petition'] == 1 ? 'Petition' : ($row['petition'] == 0 ? ' ' : ''); ?></td>
                                        </tr>
                                <?php
                                    }
                                } else {
                                    echo "<tr><td colspan='10'>No records found</td></tr>";
                                }

                                $stmt->close();
                                $conn->close();
                                ?>

                            </tbody>
                        </table>

                    </div>
                </div>
        </section>


        <!-- Bootstrap Success Modal -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-body">
                    <p id="successMessage"></p>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
        <script>
            function redirectToProfInput(profUnit) {
                // Send the selected prof_unit directly to prof_input.php
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        // Redirect to the same page after setting the session
                        window.location.href = `http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php?prof_unit=${encodeURIComponent(profUnit)}`;
                    }
                };
                xhr.send(`set_session=true&prof_unit=${encodeURIComponent(profUnit)}`);
            }
        </script>



        <script>
            // Function to show the modal
            function showProfUnitModal() {
                var modal = new bootstrap.Modal(document.getElementById('profUnitModal'));
                modal.show();
            }
        </script>


        <?php if (!empty($message)): ?>
            <script>
                alert("<?php echo $message; ?>");
            </script>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let selectedRow = null;

                function fillForm(courseId, deptCode, yearLevel, courseCode, courseName, courseType, credit, allowedRoom, lecHrs, labHrs, petition, computerRoom) {
                    console.log("Filling form with data");
                    document.getElementById('course_id').value = courseId;
                    document.getElementById('dept_code').value = deptCode;
                    document.getElementById('year_level').value = yearLevel;

                    // --- UPDATE THIS PART ---
                    const parts = courseCode.trim().split(' ');
                    document.getElementById('course').value = parts[0] || '';
                    document.getElementById('course_number').value = parts[1] || '';

                    document.getElementById('course_name').value = courseName;
                    document.getElementById('course_type').value = courseType;
                    document.getElementById('credit').value = credit;
                    document.getElementById('allowed_rooms').value = allowedRoom;
                    document.getElementById('lec_hrs').value = lecHrs;
                    document.getElementById('lab_hrs').value = labHrs;

                    toggleDepartmentSection(courseType);

                    // Hide Add button and show Update/Delete buttons
                    document.querySelector('.btn-add').style.display = 'none';
                    document.querySelectorAll('.btn-update-delete').forEach(button => {
                        button.style.display = 'inline-block';
                    });

                    // Show or hide computer room checkbox based on allowed_rooms value
                    if (allowedRoom === 'lecR&labR' || allowedRoom === 'labR') {
                        document.getElementById('computer_room_container').style.display = 'block';

                        // Check if computerRoom is '1', then check the checkbox
                        if (computerRoom === '1') {
                            document.getElementById('computer_room').checked = true;
                        } else {
                            document.getElementById('computer_room').checked = false;
                        }
                    } else {
                        document.getElementById('computer_room_container').style.display = 'none';
                        document.getElementById('computer_room').checked = false;
                    }

                    // Handle petition checkbox visibility and state
                    if (petition === '1') {
                        document.getElementById('petitionContainer').style.display = 'block';
                        document.getElementById('petition').checked = true;
                    } else {
                        document.getElementById('petitionContainer').style.display = 'none';
                        document.getElementById('petition').checked = false;
                    }
                }

                function toggleDepartmentSection(courseType) {
                    const departmentSection = document.getElementById('dept_code').parentElement;
                    const departmentLabel = document.getElementById('dept_label');
                    if (courseType === 'Minor') {
                        departmentSection.style.display = 'block';
                        if (departmentLabel) {
                            departmentLabel.style.display = 'block';
                        }
                    } else {
                        departmentSection.style.display = 'none';
                        if (departmentLabel) {
                            departmentLabel.style.display = 'none';
                        }
                    }
                }

                document.querySelectorAll('table tbody tr').forEach(row => {
                    row.addEventListener('click', function(event) {
                        document.querySelectorAll('.clicked-row').forEach(function(row) {
                            row.classList.remove('clicked-row');
                        });

                        this.classList.add('clicked-row');

                        selectedRow = this;
                        fillForm(
                            this.getAttribute('data-course_id'),
                            this.getAttribute('data-dept_code'),
                            this.getAttribute('data-year_level'),
                            this.getAttribute('data-course_code'),
                            this.getAttribute('data-course_name'),
                            this.getAttribute('data-course_type'),
                            this.getAttribute('data-credit'),
                            this.getAttribute('data-allowed_rooms'),
                            this.getAttribute('data-lec_hrs'),
                            this.getAttribute('data-lab_hrs'),
                            this.getAttribute('data-petition'),
                            this.getAttribute('data-computer_room') // Make sure this is retrieved
                        );
                        event.stopPropagation();
                    });
                });

                document.addEventListener('click', function(event) {
                    if (!event.target.closest('#courseForm') && !event.target.closest('table')) {
                        if (selectedRow) {
                            document.getElementById('course_id').value = '';
                            document.getElementById('dept_code').value = '';
                            document.getElementById('year_level').value = '';

                            // --- FIXED HERE: clear course and course_number separately ---
                            document.getElementById('course').value = '';
                            document.getElementById('course_number').value = '';

                            document.getElementById('course_name').value = '';
                            document.getElementById('course_type').value = '';
                            document.getElementById('credit').value = '';
                            document.getElementById('allowed_rooms').value = '';
                            document.getElementById('lec_hrs').value = '';
                            document.getElementById('lab_hrs').value = '';

                            document.querySelector('.btn-add').style.display = 'inline-block';
                            document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'none');

                            selectedRow.classList.remove('clicked-row');
                            selectedRow = null;

                            toggleDepartmentSection('');

                            document.getElementById('computer_room_container').style.display = 'none';
                            document.getElementById('computer_room').checked = false;

                            // Hide petition checkbox
                            document.getElementById('petitionContainer').style.display = 'none';
                            document.getElementById('petition').checked = false;
                        }
                    }
                });

                document.addEventListener('click', function(event) {
                    if (!event.target.closest('#courseForm') && !event.target.closest('table')) {
                        document.getElementById('computer_room_container').style.display = 'none';
                    }
                });
            });
        </script>

        <script>
            document.getElementById('course_type').addEventListener('change', function() {
                const deptSelectContainer = document.getElementById('dept_select_container');
                if (this.value === 'Minor') {
                    deptSelectContainer.style.display = 'block';
                } else {
                    deptSelectContainer.style.display = 'none';
                }
            });

            // Function to hide the columns based on filter criteria
            function hideFilteredColumns() {
                // Get the filter values from the URL or your form (example for course_type and year_level)
                const urlParams = new URLSearchParams(window.location.search);
                const courseTypeFilter = urlParams.get('course_type') || '';
                const yearLevelFilter = urlParams.get('year_level') || '';

                // Hide the "Course Type" column if a filter is applied
                if (courseTypeFilter && courseTypeFilter !== "ALL") {
                    hideColumn('Course Type');
                }

                // Hide the "Year Level" column if a filter is applied
                if (yearLevelFilter && yearLevelFilter !== "ALL") {
                    hideColumn('Year Level');
                }
            }

            // Function to hide the column by header text
            function hideColumn(columnName) {
                const headers = document.querySelectorAll('table th');
                let columnIndex = -1;

                // Find the column index by header text
                headers.forEach((th, index) => {
                    if (th.textContent.trim() === columnName) {
                        columnIndex = index;
                    }
                });

                // If the column exists, hide it
                if (columnIndex !== -1) {
                    const rows = document.querySelectorAll('table tr');
                    rows.forEach(row => {
                        const cells = row.querySelectorAll('td, th');
                        cells[columnIndex].style.display = 'none'; // Hide the cell
                    });
                }
            }

            // Call the function when the page is loaded
            document.addEventListener('DOMContentLoaded', hideFilteredColumns);
        </script>



    </body>

</html>