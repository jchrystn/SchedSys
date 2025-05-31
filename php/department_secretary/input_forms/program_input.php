<?php
include("../../config.php");
session_start();

// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../../login/login.php");
    exit();
}

$dept_code = $_SESSION['dept_code'];
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}


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
$sql = "SELECT MAX(id) AS last_id FROM tbl_program"; // Assuming your primary key is 'id'

// Step 3: Execute the query
$result = $conn->query($sql);

// Step 4: Fetch the result
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_inserted_id = $row['last_id'];
} else {
    echo "No records found.";
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $original_program_code = strtoupper($_POST['original_program_code'] ?? '');
    $dept_code = $_SESSION['dept_code'];
    $curriculum = $_POST['curriculum'] ?? '';
    $action = $_POST['action'] ?? '';
    $program_code = strtoupper($_POST['program_code'] ?? ''); //Convert to UPPERCASE here
    $num_year = $_POST['num_year'] ?? 0;
    $program_id = $_POST['program_id'] ?? '';
    $college_code = strtoupper($_POST['college_code'] ?? ''); //lso make college_code uppercase if needed

    function titleCase($string) {
        $smallWords = ['of', 'on', 'in', 'at', 'to', 'for', 'by', 'the', 'a', 'an', 'and', 'but', 'or', 'nor'];
        $words = explode(' ', strtolower(trim($string)));
        foreach ($words as $index => &$word) {
            if ($index == 0 || !in_array($word, $smallWords)) {
                $word = ucfirst($word);
            }
        }
        return implode(' ', $words);
    }
    
    $program_name = titleCase($_POST['program_name'] ?? '');


    if (isset($_POST['program_id'])) {
        // echo "Program ID: " . $_POST['program_id'];
    } else {
        // echo "Program ID is missing.";
    }


    if ($action == "add") {
        // Check if the program already exists (any one field matches)
        $check_sql = "SELECT * FROM tbl_program WHERE (program_code = ? OR program_name = ?) AND dept_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss",$program_code, $program_name);
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
        } else{
            // Insert new program
            $sql = "INSERT INTO tbl_program (college_code, program_code, program_name, dept_code, curriculum, num_year) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $college_code, $program_code, $program_name, $dept_code, $curriculum, $num_year);
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
                                  document.getElementById('successMessage').textContent = 'Error: " . $stmt->error . "';
                                  modal.show();
                              });
                          </script>";
            }
            $stmt->close();
        }
        $check_stmt->close();
    } elseif ($action === "update") {
        // Fetch the old program code for verification
        $old_program_code = '';
        $old_program_sql = "SELECT program_code FROM tbl_program WHERE id = ?";
        $old_program_stmt = $conn->prepare($old_program_sql);
        $old_program_stmt->bind_param("i", $program_id);
        $old_program_stmt->execute();
        $old_program_stmt->bind_result($old_program_code);
        $old_program_stmt->fetch();
        $old_program_stmt->close();

        // Update main program details
        $sql = "UPDATE tbl_program SET program_code = ?, program_name = ?, dept_code = ?, num_year = ?, curriculum = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $program_code, $program_name, $dept_code, $num_year, $curriculum, $program_id);

        if ($stmt->execute()) {
            $stmt->close();

            // Update program code in the section table
            $update_section_sql = "UPDATE tbl_section SET program_code = ? WHERE program_code = ? AND curriculum = ?";
            $update_stmt = $conn->prepare($update_section_sql);
            $update_stmt->bind_param("sss", $program_code, $old_program_code, $curriculum);

            if ($update_stmt->execute()) {
                $update_stmt->close();

                // Update section codes based on the new program code
                $section_code_sql = "UPDATE tbl_section SET section_code = CONCAT(?, ' ', CAST(year_level AS SIGNED), '-', section_no) WHERE program_code = ? AND dept_code = ? AND curriculum = ?";
                $section_code_stmt = $conn->prepare($section_code_sql);
                $section_code_stmt->bind_param("ssss", $program_code, $program_code, $dept_code, $curriculum);

                if ($section_code_stmt->execute()) {
                    $section_code_stmt->close();

                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var modal = new bootstrap.Modal(document.getElementById('successModal'));
                            document.getElementById('successMessage').textContent = 'Record Updated Successfully.';
                            modal.show();
                        });
                    </script>";

                    // Fetch all sections for schedule table updates
                    $fetch_sections_sql = "SELECT section_code, year_level, section_no, program_code FROM tbl_section WHERE dept_code = ? AND semester = ? AND curriculum = ?";
                    $fetch_stmt = $conn->prepare($fetch_sections_sql);
                    $fetch_stmt->bind_param("sss", $dept_code, $semester, $curriculum);
                    $fetch_stmt->execute();
                    $fetch_stmt->store_result();
                    $fetch_stmt->bind_result($existing_section_code, $year_level, $section_no, $program_code);

                    while ($fetch_stmt->fetch()) {
                        $original_section_code = strtoupper($existing_section_code);

                        if ($year_level && $section_no) {
                            $year_level_int = preg_match('/(\d+)/', $year_level, $matches) ? (int) $matches[1] : 0;
                            $old_section_code = $old_program_code . " " . $year_level_int . "-" . $section_no;
                            $new_section_code = $program_code . " " . $year_level_int . "-" . $section_no;

                            // Generate sanitized schedule table names
                            $old_section_sched_code = preg_replace("/-/", "_", "{$old_section_code}_{$ay_code}");
                            $section_sched_code = preg_replace("/-/", "_", "{$new_section_code}_{$ay_code}");

                            // Define sanitized table names
                            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

                            $check_room_sched_sql = "SELECT COUNT(*) FROM $sanitized_room_sched_code WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                            if ($check_room_sched_stmt = $conn->prepare($check_room_sched_sql)) {
                                $check_room_sched_stmt->bind_param("ssss", $old_section_sched_code, $semester, $dept_code, $curriculum);
                                $check_room_sched_stmt->execute();
                                $check_room_sched_stmt->bind_result($count_room_sched);
                                $check_room_sched_stmt->fetch();
                                $check_room_sched_stmt->close();

                                if ($count_room_sched > 0) {
                                    $update_room_sched_sql = "UPDATE $sanitized_room_sched_code SET section_code = ? WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                    if ($update_room_sched_stmt = $conn->prepare($update_room_sched_sql)) {
                                        $update_room_sched_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $dept_code, $curriculum);

                                        if ($update_room_sched_stmt->execute()) {
                                            $update_room_sched_stmt->close();
                                        }
                                    }
                                }
                            }
                            // echo "<br>Room Sched:   $sanitized_room_sched_code <br> ";
                            // echo "Old Section Sched Code: $old_section_sched_code<br>";
                            // echo "Section Sched Code: $section_sched_code<br>";


                            $check_section_sched_sql = "SELECT COUNT(*) FROM $sanitized_section_sched_code WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                            if ($check_section_sched_stmt = $conn->prepare($check_section_sched_sql)) {
                                $check_section_sched_stmt->bind_param("ssss", $old_section_sched_code, $semester, $dept_code, $curriculum);
                                $check_section_sched_stmt->execute();
                                $check_section_sched_stmt->bind_result($count_section_sched);
                                $check_section_sched_stmt->fetch();
                                $check_section_sched_stmt->close();

                                if ($count_section_sched > 0) {
                                    $update_section_sched_sql = "UPDATE $sanitized_section_sched_code SET section_sched_code = ? WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                    if ($update_section_sched_stmt = $conn->prepare($update_section_sched_sql)) {
                                        $update_section_sched_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $dept_code, $curriculum);

                                        if ($update_section_sched_stmt->execute()) {
                                            $update_section_sched_stmt->close();
                                        }
                                    }
                                }
                            }
                            // echo "<br>Room Sched:   $sanitized_section_sched_code <br> ";
                            // echo "Old Section Sched Code: $old_section_sched_code<br>";
                            // echo "Section Sched Code: $section_sched_code<br>";

                            // Check if the section_code exists in tbl_psched
                            $check_prof_sched_sql = "SELECT COUNT(*) FROM $sanitized_prof_sched_code WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                            if ($check_prof_sched_stmt = $conn->prepare($check_prof_sched_sql)) {
                                $check_prof_sched_stmt->bind_param("ssss", $old_section_sched_code, $semester, $dept_code, $curriculum);
                                $check_prof_sched_stmt->execute();
                                $check_prof_sched_stmt->bind_result($count_prof_sched);
                                $check_prof_sched_stmt->fetch();
                                $check_prof_sched_stmt->close();

                                if ($count_prof_sched > 0) {
                                    // Proceed to update tbl_psched if section_code exists
                                    $update_prof_sched_sql = "UPDATE $sanitized_prof_sched_code SET section_code = ? WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                    if ($update_prof_sched_stmt = $conn->prepare($update_prof_sched_sql)) {
                                        $update_prof_sched_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $dept_code, $curriculum);

                                        if ($update_prof_sched_stmt->execute()) {
                                            $update_prof_sched_stmt->close();
                                        }
                                    }
                                }

                                // echo "<br>Prof Sched:    $sanitized_prof_sched_code<br> ";
                                // echo "Old Section Sched Code: $old_section_sched_code<br>";
                                // echo "Section Sched Code: $section_sched_code<br>";


                                // Update section_sched_code in tbl_secschedlist
                                $update_secschedlist_sql = "UPDATE tbl_secschedlist SET section_sched_code = ? WHERE section_sched_code = ? AND ay_code = ? AND dept_code = ? AND curriculum = ?";
                                if ($update_secschedlist_stmt = $conn->prepare($update_secschedlist_sql)) {
                                    $update_secschedlist_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $ay_code, $dept_code, $curriculum);

                                    if ($update_secschedlist_stmt->execute()) {
                                        // echo "<br>Updated tbl_secschedlist successfully for Old Section Sched Code: $old_section_sched_code<br>";
                                    } else {
                                        echo "<br>Error updating tbl_secschedlist: " . $update_secschedlist_stmt->error . "<br>";
                                    }
                                    $update_secschedlist_stmt->close();
                                }

                                // Update section_sched_code in tbl_schedstatus
                                $update_schedstatus_sql = "UPDATE tbl_schedstatus SET section_sched_code = ? WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                if ($update_schedstatus_stmt = $conn->prepare($update_schedstatus_sql)) {
                                    $update_schedstatus_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $dept_code, $curriculum);

                                    if ($update_schedstatus_stmt->execute()) {
                                        // echo "<br>Updated tbl_schedstatus successfully for Old Section Sched Code: $old_section_sched_code<br>";
                                    } else {
                                        echo "<br>Error updating tbl_schedstatus: " . $update_schedstatus_stmt->error . "<br>";
                                    }
                                    $update_schedstatus_stmt->close();
                                }

                                // After updating section_code in tbl_section, check and update tbl_secschedlist
                                $check_secschedlist_sql = "SELECT COUNT(*) FROM tbl_secschedlist WHERE section_code = ? AND ay_code = ? AND dept_code = ? AND curriculum = ?";
                                if ($check_secschedlist_stmt = $conn->prepare($check_secschedlist_sql)) {
                                    $check_secschedlist_stmt->bind_param("ssss", $old_section_code, $ay_code, $dept_code, $curriculum);
                                    $check_secschedlist_stmt->execute();
                                    $check_secschedlist_stmt->bind_result($count_secschedlist);
                                    $check_secschedlist_stmt->fetch();
                                    $check_secschedlist_stmt->close();

                                    if ($count_secschedlist > 0) {
                                        // Update tbl_secschedlist if records with old_section_code exist
                                        $update_secschedlist_sql = "UPDATE tbl_secschedlist SET section_code = ? WHERE section_code = ? AND ay_code = ? AND dept_code = ? AND curriculum = ?";
                                        if ($update_secschedlist_stmt = $conn->prepare($update_secschedlist_sql)) {
                                            $update_secschedlist_stmt->bind_param("sssss", $new_section_code, $old_section_code, $ay_code, $dept_code, $curriculum);

                                            if ($update_secschedlist_stmt->execute()) {
                                                // echo "<br>Updated tbl_secschedlist successfully for Old Section Sched Code: $old_section_sched_code<br>";
                                            } else {
                                                echo "<br>Error updating tbl_secschedlist: " . $update_secschedlist_stmt->error . "<br>";
                                            }
                                            $update_secschedlist_stmt->close();
                                        }
                                    } else {
                                        // echo "<br>No records found in tbl_secschedlist for Old Section Sched Code: $old_section_sched_code<br>";
                                    }

                                    $check_shared_sql = "SELECT COUNT(*) FROM tbl_shared_sched WHERE sender_dept_Code = ? AND ay_code = ? AND semester = ?";
                                    if ($check_shared_stmt = $conn->prepare($check_shared_sql)) {
                                        $check_shared_stmt->bind_param("sss", $dept_code, $ay_code, $semester);
                                        $check_shared_stmt->execute();
                                        $check_shared_stmt->bind_result($count_shared);
                                        $check_shared_stmt->fetch();
                                        $check_shared_stmt->close();

                                        // echo "<br>$ay_code<br>";
                                        // echo "<br>$semester<br>";
                                        // echo "<br>$dept_code<br>";
                                        // echo "<br>$count_shared<br>";

                                        if ($count_shared > 0) {
                                            $update_shared_sql = "UPDATE tbl_shared_sched SET section_code = ?  WHERE section_code = ? AND ay_code = ? AND sender_dept_code = ?";
                                            if ($update_shared_stmt = $conn->prepare($update_shared_sql)) {
                                                $update_shared_stmt->bind_param("ssss", $new_section_code, $old_section_code, $ay_code, $dept_code);

                                                if ($update_shared_stmt->execute()) {
                                                    // echo "<br>Updated tbl_shared successfully for Old Section Sched Code: $old_section_sched_code<br>";
                                                } else {
                                                    echo "<br>Error updating tbl_shared: " . $update_shared_stmt->error . "<br>";
                                                }
                                                $update_shared_stmt->close();
                                            }
                                        } else {
                                            // echo "<br>No records found in tbl_shared for Old Section Sched Code: $old_section_sched_code<br>";
                                        }

                                        $update_sec_sql = "UPDATE tbl_shared_sched SET shared_section = ?  WHERE shared_section = ? AND ay_code = ? AND sender_dept_code = ?";
                                        if ($update_sec_stmt = $conn->prepare($update_sec_sql)) {
                                            $update_sec_stmt->bind_param("ssss", $section_sched_code, $old_section_sched_code, $ay_code, $dept_code);

                                            if ($update_sec_stmt->execute()) {
                                                // echo "<br>Updated tbl_shared successfully for Old Section Sched Code: $old_section_sched_code<br>";
                                            } else {
                                                echo "<br>Error updating tbl_sec: " . $update_sec_stmt->error . "<br>";
                                            }
                                            $update_sec_stmt->close();
                                        }
                                    } else {
                                        // echo "<br>No records found in tbl_shared for Old Section Sched Code: $old_section_sched_code<br>";
                                    }

                                    $check_shared_sql = "SELECT receiver_dept_code FROM tbl_shared_sched WHERE sender_dept_Code = ? AND ay_code = ? AND semester = ?";
                                    if ($check_shared_stmt = $conn->prepare($check_shared_sql)) {
                                        $check_shared_stmt->bind_param("sss", $dept_code, $ay_code, $semester);
                                        $check_shared_stmt->execute();
                                        $check_shared_stmt->bind_result($receiver_dept_code);

                                        // Fetch all receiver_dept_code values
                                        $receiver_dept_codes = [];
                                        while ($check_shared_stmt->fetch()) {
                                            $receiver_dept_codes[] = $receiver_dept_code;
                                        }
                                        $check_shared_stmt->close();

                                        // Process each receiver_dept_code
                                        foreach ($receiver_dept_codes as $receiver_dept_code) {

                                            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                                            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                                            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");

                                            // echo "<br>Sanitized Room Schedule Table: $sanitized_room_sched_code<br>";
                                            // echo "<br>Sanitized Prof Schedule Table: $sanitized_prof_sched_code<br>";
                                            // echo "<br>Sanitized Section Schedule Table: $sanitized_section_sched_code<br>";

                                            $shared_tables = [
                                                $sanitized_section_sched_code,
                                                $sanitized_room_sched_code,
                                                $sanitized_prof_sched_code,
                                            ];

                                            foreach ($shared_tables as $table) {

                                                $check_table_sql = "SHOW TABLES LIKE '$table'";
                                                $table_check_result = $conn->query($check_table_sql);

                                                // If the table doesn't exist, skip this iteration and move to the next table
                                                if ($table_check_result->num_rows == 0) {
                                                    continue;
                                                }

                                                // Prepare to check for existing records
                                                $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                                $check_shared_stmt = $conn->prepare($check_shared_sql);
                                                $check_shared_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $receiver_dept_code, $curriculum);
                                                $check_shared_stmt->execute();
                                                $check_shared_stmt->store_result(); // Store the result to avoid sync issues

                                                $check_shared_stmt->bind_result($shared_count);
                                                $check_shared_stmt->fetch();
                                                $check_shared_stmt->close();

                                                // Only update if records exist
                                                if ($shared_count > 0) {
                                                    if ($shared_count > 0) {
                                                        $update_shared_sql = "UPDATE $table SET section_code = ? WHERE section_code = ? AND semester = ? AND dept_code = ? AND curriculum = ?";
                                                        $update_shared_stmt = $conn->prepare($update_shared_sql);
                                                        if ($update_shared_stmt) {
                                                            $update_shared_stmt->bind_param("sssss", $section_sched_code, $old_section_sched_code, $semester, $receiver_dept_code, $curriculum);
                                                            if ($update_shared_stmt->execute()) {
                                                                echo "Updated room_code in table $table for receiver_dept_code: $receiver_dept_code<br>";
                                                            } else {
                                                                echo "Failed to update room_code in table $table for receiver_dept_code: $receiver_dept_code. Error: " . $update_shared_stmt->error . "<br>";
                                                            }
                                                            $update_shared_stmt->close();

                                                            // Display old and new room codes
                                                            // echo "Receiver Old Room Code: $old_room_code<br>";
                                                            // echo "Receiver New Room Code: $room_code<br>";
                                                            // echo "Receiver Dept Code: $receiver_dept_code<br><br>";
                                                        } else {
                                                            echo "Error preparing update statement for table $table: " . $conn->error . "<br>";
                                                        }
                                                    } else {
                                                        // No records found for the given room_code and dept_code
                                                        echo "No records found in table $table for room_code: $old_room_code and dept_code: $receiver_dept_code<br><br>";
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $fetch_stmt->free_result();
                }
            }
        }
        // echo "Program ID: $program_id, Program Code: $program_code, Program Name: $program_name";

        $tables = ['tbl_secschedlist'];
        $sql_template = "UPDATE %s SET program_code = ?, dept_code = ? WHERE program_code = ? AND curriculum = ?";

        foreach ($tables as $table) {
            $sql = sprintf($sql_template, $table);
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssss", $program_code, $dept_code, $original_program_code, $curriculum);

                if (!$stmt->execute()) {
                    echo "Error updating $table: " . $stmt->error;
                }
                $stmt->close();
            } else {
                echo "Error preparing statement for $table: " . $conn->error;
            }
        }

        $update_sql = "UPDATE tbl_course SET program_code = ? WHERE program_code = ? AND curriculum = ?";
        if ($update_stmt = $conn->prepare($update_sql)) {
            $update_stmt->bind_param("sss", $program_code, $original_program_code, $curriculum);

            if ($update_stmt->execute()) {
                //         echo "<script>
                //     console.log('tbl_course updated successfully.');
                // </script>";
            } else {
                echo "Error updating tbl_course: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            echo "Error preparing tbl_course statement: " . $conn->error;
        }
    } elseif ($action == "delete") {
        // Check for dependent records
        $check_sql = "SELECT * FROM tbl_section WHERE program_code=? AND dept_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $program_code, $dept_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $_SESSION['modal_message'] = 'Deletion not Allowed: There is already data stored for ' . $program_code . '.';
            header("Location: program_input.php");
            return; // Stop execution to prevent deletion
        } else {
            // No dependent records, proceed with deletion
            $sql = "DELETE FROM tbl_program WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $program_id);
            if ($stmt->execute()) {
                echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successMessage').textContent = 'Record successfully deleted';
                    modal.show();
                });
            </script>";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Program Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/program_input.css">

<body>

    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>

    <section class="program-input">

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="program-tab" href="program_input.php" aria-controls="program"
                    aria-selected="true">Program Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="course-tab" href="course_input.php" aria-controls="course"
                    aria-selected="false">Checklist Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="section-tab" href="section_input.php" aria-controls="section"
                    aria-selected="true">Section Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="room-tab" href="classroom_input.php" aria-controls="room"
                    aria-selected="false">Room Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="prof-tab" href="#" aria-controls="prof" aria-selected="false"
                    data-bs-toggle="modal" data-bs-target="#profUnitModal">Instructor Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sgintaory-tab" href="signatory_input.php" aria-controls="signatory"
                    aria-selected="false">Signatory Input</a>
            </li>
        </ul>

        <div class="text-center">
            <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                class="d-inline-block w-100">
                <input type="hidden" name="filter" value="true">
                <div class="filtering d-flex flex-wrap justify-content-center">

                    <!-- Program Code Filter -->
                    <div class="form-group col-md-3">
                        <select class="form-control" id="program_code_filter" name="program_code">
                            <option value="">All Program Codes</option>
                            <?php
                            // Fetch program codes
                            $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : '';
                            $sql = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("s", $dept_code);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            while ($row = $result->fetch_assoc()) {
                                $selected = (isset($_GET['program_code']) && $_GET['program_code'] == $row['program_code']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($row['program_code']) . "' $selected>" . htmlspecialchars($row['program_code']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Curriculum Filter -->
                    <div class="form-group col-md-3">
                        <select name="curriculum" id="filter_curriculum" class="form-control">
                            <option value="">All Curriculums</option>
                            <?php
                            $curriculumQuery = "SELECT DISTINCT curriculum FROM tbl_program WHERE dept_code = ?";
                            $curriculumStmt = $conn->prepare($curriculumQuery);
                            $curriculumStmt->bind_param("s", $dept_code);
                            $curriculumStmt->execute();
                            $curriculumResult = $curriculumStmt->get_result();

                            if ($curriculumResult && $curriculumResult->num_rows > 0) {
                                while ($row = $curriculumResult->fetch_assoc()) {
                                    $selected = (isset($_GET['curriculum']) && $_GET['curriculum'] == $row["curriculum"]) ? "selected" : "";
                                    echo "<option value='" . htmlspecialchars($row["curriculum"]) . "' $selected>" . htmlspecialchars($row["curriculum"]) . "</option>";
                                }
                            } else {
                                echo "<option value=\"\" disabled>No curriculums available</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Program Name Search -->
                    <div class="form-group col-md-3">
                        <input type="text" class="form-control" id="program_name_filter" name="program_name"
                            value="<?php echo isset($_GET['program_name']) ? htmlspecialchars($_GET['program_name']) : ''; ?>"
                            placeholder="Search Program Name">
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <script>
            // JavaScript to hide columns dynamically
            document.addEventListener("DOMContentLoaded", function () {
                const urlParams = new URLSearchParams(window.location.search);
                const filterProgram = urlParams.get("program_code");
                const filterCurriculum = urlParams.get("curriculum");
                const filterProgramName = urlParams.get("program_name");

                if (filterProgram) {
                    document.querySelectorAll(".program-column").forEach(col => col.style.display = "none");
                }
                if (filterCurriculum) {
                    document.querySelectorAll(".curriculum-column").forEach(col => col.style.display = "none");
                }
                if (filterProgramName) {
                    document.querySelectorAll(".program-column").forEach(col => col.style.display = "none");
                }
            });
        </script>




        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="title">Program Input</h5>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" id="input-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">

                    <input type="hidden" id="program_id" name="program_id" value="<?php echo $last_inserted_id; ?>"
                        readonly>


                    <div class="mb-3">
                        <input type="text" id="program_code" name="program_code" class="form-control"
                            placeholder="Program Code" autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <input type="text" id="program_name" name="program_name" class="form-control"
                            placeholder="Program Name" autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <input type="number" id="num_year" name="num_year" class="form-control"
                            placeholder="Number of Year Level" autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <select id="curriculum" name="curriculum" class="form-control" style="color: #6c757d;" required>
                            <option value="" disabled selected>Select Curriculum</option>
                            <option value="New">New</option>
                            <option value="Old">Old</option>
                        </select>
                    </div>


                    <input type="hidden" id="original_program_code" name="original_program_code"
                        value="<?php echo htmlspecialchars($original_program_code); ?>">

                    <div class="button-group">
                        <button type="submit" name="action" value="add" class="btn btn-add">Add</button>
                        <div class="btn-inline-group">
                            <button type="submit" name="action" value="update" class="btn btn-primary btn-update-delete"
                                style="display: none;">Update</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-update-delete"
                                style="display: none;">Delete</button>
                        </div>
                    </div>

                </form>
            </div>

                <div class="col-lg-8 mb-4">
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="program-column">Program Code</th>
                                    <th class="program_name-column">Program Name</th>
                                    <th class="num-column">No. of Year Level</th>
                                    <th class="curriculum-column">Curriculum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Check if the session variables for ay_code and semester are set
                                if (isset($_SESSION['ay_code']) && isset($_SESSION['semester']) && isset($_SESSION['dept_code'])) {
                                    $ay_code = $_SESSION['ay_code'];
                                    $semester = $_SESSION['semester'];
                                    $dept_code = $_SESSION['dept_code'];

                                    $filterProgram = '';
                                    $filterCurriculum = '';
                                    $filterProgramName = '';

                                    if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
                                        $filterProgram = $_GET['program_code'] ?? '';
                                        $filterCurriculum = $_GET['curriculum'] ?? '';
                                        $filterProgramName = $_GET['program_name'] ?? '';
                                    }

                                    $sql = "SELECT * FROM tbl_program WHERE dept_code = ?";
                                    $params = [$dept_code];
                                    $types = "s";

                                    if (!empty($filterProgram)) {
                                        $sql .= " AND program_code = ?";
                                        $params[] = $filterProgram;
                                        $types .= "s";
                                    }

                                    if (!empty($filterCurriculum)) {
                                        $sql .= " AND curriculum = ?";
                                        $params[] = $filterCurriculum;
                                        $types .= "s";
                                    }

                                    if (!empty($filterProgramName)) {
                                        $sql .= " AND program_name LIKE ?";
                                        $params[] = "%" . $filterProgramName . "%";
                                        $types .= "s";
                                    }

                                    if ($stmt = $conn->prepare($sql)) {
                                        $stmt->bind_param($types, ...$params);
                                        $stmt->execute();
                                        $result = $stmt->get_result();

                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<tr 
                                    data-program_id='" . $row["id"] . "' 
                                     onclick=\"fillForm('" . $row["id"] . "', '" . htmlspecialchars($row["program_code"], ENT_QUOTES) . "', '" . htmlspecialchars($row["program_name"], ENT_QUOTES) . "', '" . $row["num_year"] . "', '" . htmlspecialchars($row["curriculum"], ENT_QUOTES) . "')\" 
                                    ondblclick=\"window.location.href='course_input.php?id=" . urlencode($row["id"]) . "&program_code=" . urlencode($row["program_code"]) . "&curriculum=" . urlencode($row["curriculum"]) . "&num_year=" . urlencode($row["num_year"]) . "'\"> 
                                    <td class=\"program-column\">" . htmlspecialchars($row["program_code"]) . "</td>
                                    <td class=\"program_name-column\">" . htmlspecialchars($row["program_name"]) . "</td>
                                     <td class=\"num-column\">" . $row["num_year"] . "</td>
                                    <td class=\"curriculum-column\">" . htmlspecialchars($row["curriculum"]) . "</td>
                                  </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' style='text-align:center;'>No records found</td></tr>";
                                        }

                                        // Close the statement
                                        $stmt->close();
                                    } else {
                                        echo "Error preparing statement: " . $conn->error;
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>Session variables for academic year and semester not set</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // Function to handle form submission
                    const form = document.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function (e) {
                            // Add your form validation here if needed
                            return true;
                        });
                    }

                    // Pre-select the values if they exist in the URL
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.get('filter') === 'true') {
                        const programCode = urlParams.get('program_code');
                        const curriculum = urlParams.get('curriculum');
                        const programName = urlParams.get('program_name');

                        if (programCode) {
                            document.getElementById('program_code_filter').value = programCode;
                        }

                        if (curriculum) {
                            document.getElementById('filter_curriculum').value = curriculum;
                        }

                        if (programName) {
                            document.getElementById('program_name_filter').value = programName;
                        }
                    }
                });
            </script>

        </div>


        <!-- Bootstrap Success Modal -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-body">
                    <p id="successMessage"></p>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="profUnitModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Use 'modal-lg' for large modal size -->
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="row g-4">
                            <?php
                            if (isset($_SESSION['dept_code'], $_SESSION['semester'], $_SESSION['ay_code'])) {
                                $dept_code = $_SESSION['dept_code'];
                                $semester = $_SESSION['semester'];
                                $ay_code = $_SESSION['ay_code']; // Get session variables
                            
                                // Query to get program_units for the department
                                $stmt = $conn->prepare("
                            SELECT program_units 
                            FROM tbl_department 
                            WHERE dept_code = ? 
                              AND program_units IS NOT NULL 
                              AND program_units != ''
                        ");
                                $stmt->bind_param("s", $dept_code); // Bind the parameter
                                $stmt->execute(); // Execute the query
                                $result_unit = $stmt->get_result(); // Get the result
                            
                                if ($result_unit && $result_unit->num_rows > 0) {
                                    while ($row = $result_unit->fetch_assoc()) {
                                        $program_units = htmlspecialchars($row['program_units']);
                                        // Split program_units by comma
                                        $units = explode(',', $program_units);

                                        foreach ($units as $unit) {
                                            $unit = trim($unit); // Remove any extra spaces
                            
                                            // Query to count professors for each program_unit
                                            $count_stmt = $conn->prepare("
                                        SELECT COUNT(*) AS unit_count 
                                        FROM tbl_prof_acc 
                                        WHERE dept_code = ? 
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND prof_unit = ?
                                          AND status = 'approve'
                                    ");
                                            $count_stmt->bind_param("ssss", $dept_code, $semester, $ay_code, $unit);
                                            $count_stmt->execute();
                                            $count_result = $count_stmt->get_result();
                                            $unit_count = $count_result->fetch_assoc()['unit_count'] ?? 0; // Get the count
                            
                                            ?>
                                            <div class="col-md-3 col-lg-6">
                                                <div class="card shadow-sm h-100" onclick="redirectToProfInput('<?= $unit ?>')">
                                                    <div class="card-body text-center">
                                                        <div class="icon-container mb-3">
                                                            <i class="fas fa-users"></i>
                                                        </div>
                                                        <h6 class="card-title mb-1 fw-bold"><?= $unit ?></h6>
                                                        <p class="card-text text-muted"><?= $unit_count ?> Professors</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                            $count_stmt->close(); // Close the count statement
                                        }
                                    }
                                } else {
                                    echo '<div class="col-12 text-center text-muted">No program units available</div>';
                                }

                                $stmt->close(); // Close the statement
                            } else {
                                echo '<div class="col-12 text-center text-muted">Required session variables are missing</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <script>
            function redirectToProfInput(profUnit) {
                // Send the selected prof_unit directly to prof_input.php
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        // Redirect to the same page after setting the session
                        window.location.href = `http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php?prof_unit=${encodeURIComponent(profUnit)}`;
                    }
                };
                xhr.send(`set_session=true&prof_unit=${encodeURIComponent(profUnit)}`);
            }

        </script>


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                <?php if (isset($_SESSION['modal_message'])): ?>
                    var modal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successMessage').textContent = "<?php echo $_SESSION['modal_message']; ?>";
                    modal.show();
                    <?php unset($_SESSION['modal_message']); ?>
                <?php endif; ?>
            });
        </script>


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
            document.addEventListener('DOMContentLoaded', function () {
                let selectedRow = null; // Track the selected row

                // Function to fill the form with data from the selected table row
                function fillForm(programId, programCode, programName, numYear, curriculum) {
                    document.getElementById('program_id').value = programId;
                    document.getElementById('program_code').value = programCode;
                    document.getElementById('program_name').value = programName;
                    document.getElementById('original_program_code').value = programCode;
                    document.getElementById('num_year').value = numYear;
                    document.getElementById('curriculum').value = curriculum;

                    // Show the update and delete buttons, hide the add button
                    document.querySelector('.btn-add').style.display = 'none';
                    document.querySelectorAll('.btn-update-delete').forEach(btn => {
                        btn.style.display = 'inline-block';
                    });
                }

                document.querySelectorAll('table tbody tr').forEach(row => {
                    row.addEventListener('click', function (event) {
                        // Get data from the clicked row
                        const programId = this.getAttribute('data-program_id'); // Now works because data-program_id exists
                        const cells = this.getElementsByTagName('td');
                        fillForm(programId, cells[0].innerText, cells[1].innerText, cells[2].innerText, cells[3].innerText);

                        // Remove 'clicked-row' class from the previously selected row
                        if (selectedRow) {
                            selectedRow.classList.remove('clicked-row');
                        }

                        // Add 'clicked-row' class to the current row
                        this.classList.add('clicked-row');
                        selectedRow = this; // Update the selected row reference

                        // Prevent event propagation to document listener
                        event.stopPropagation();
                    });
                });


                // Click event to hide form when clicking outside of it
                document.addEventListener('click', function (event) {
                    const form = document.getElementById('input-form');
                    if (!form.contains(event.target)) {
                        // Reset the form and toggle buttons back
                        form.reset();
                        document.querySelector('.btn-add').style.display = 'inline-block';
                        document.querySelectorAll('.btn-update-delete').forEach(btn => {
                            btn.style.display = 'none';
                        });

                        // Remove 'clicked-row' class from the previously selected row
                        if (selectedRow) {
                            selectedRow.classList.remove('clicked-row');
                            selectedRow = null; // Reset selected row
                        }
                    }
                });

                // Prevent clicks inside the form from closing it
                document.getElementById('input-form').addEventListener('click', function (event) {
                    event.stopPropagation();
                });
            });
        </script>


</body>

</html>