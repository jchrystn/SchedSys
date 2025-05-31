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
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}



$search_room_type = '';
$search_room_code_name = '';
$search_status = '';

// Step 2: Prepare the SQL statement to retrieve the last inserted ID from tbl_course
$sql = "SELECT MAX(id) AS last_id FROM tbl_room";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_inserted_id = $row['last_id'];
} else {
    echo "No records found in room input.";
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


// Retrieve message from session if set
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $room_name = isset($_POST['room_name']) && trim($_POST['room_name']) !== '' ? $_POST['room_name'] : 'N/A'; 
    $room_in_charge = $_POST['room_in_charge'];
    $room_id = $_POST['room_id'];
    $status = $_POST['status'];
    $action = $_POST['action'];
    $user_type = $_SESSION['user_type'] ?? '';
    $room_type = $_POST['room_type'] ?? '';
    $room_type = $_POST['room_type'] ?? '';
    $college_code = $_SESSION['college_code'] ?? ''; // Example: Retrieve from session

    $room = $_POST['room'] ?? '';
    $room_number = $_POST['room_number'] ?? '';
    
    $room_code = strtoupper(trim($room . ' ' . $room_number)); // Combine and make UPPERCASE    


    if ($user_type === "CCL Head") {
        $room_type = "Computer Laboratory";
        $dept_code = $college_code;
    }

    if ($action == "add") {
            // Check if the room already exists
    $check_sql = "SELECT * FROM tbl_room WHERE (room_code = ? OR room_name = ?) AND dept_code = ?";
    $check_stmt = $conn->prepare($check_sql);

    if ($check_stmt === false) {
        $message = "Error preparing statement: " . $conn->error;
    } else {
        $check_stmt->bind_param("sss", $room_code, $room_name, $dep);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        // Allow insertion if room_name is "N/A"
        if ($check_result->num_rows > 0 && strtoupper(trim($room_name)) !== 'N/A') {
            echo "<script>
                      document.addEventListener('DOMContentLoaded', function() {
                          var modal = new bootstrap.Modal(document.getElementById('successModal'));
                          document.getElementById('successMessage').textContent = 'Record already exists.';
                          modal.show();
                      });
                  </script>";
        } else {
                // Insert new room
                $insert_sql = "INSERT INTO tbl_room (room_code, room_name, room_type, room_in_charge, dept_code, college_code, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                if ($insert_stmt === false) {
                    $message = "Error preparing statement: " . $conn->error;
                } else {
                    $insert_stmt->bind_param("sssssss", $room_code, $room_name, $room_type, $room_in_charge, $dept_code, $college_code, $status);
                    if ($insert_stmt->execute()) {
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
                    $insert_stmt->close();
                }
            }
            $check_stmt->close();
        }
    } elseif ($action == 'update') {
        // Fetch the old room_code based on $room_id
        $old_room_code = '';
        $old_room_sql = "SELECT room_code FROM tbl_room WHERE id = ?";
        $old_room_stmt = $conn->prepare($old_room_sql);
        $old_room_stmt->bind_param("s", $room_id);
        $old_room_stmt->execute();
        $old_room_stmt->bind_result($old_room_code);
        $old_room_stmt->fetch();
        $old_room_stmt->close();

        // Update existing room record
        $sql = "UPDATE tbl_room SET room_code = ?, room_name = ?, room_type = ?, room_in_charge = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $room_code, $room_name, $room_type, $room_in_charge, $room_id, $status);

        if ($stmt->execute()) {
            $new_room_sched_code = $room_code . "_" . $ay_code;
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
            $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

            // echo "sanitized Section Sched: $sanitized_section_sched_code<br>";
            // echo "sanitized Room Sched: $sanitized_room_sched_code<br>";
            // echo "sanitized Prof Sched: $sanitized_prof_sched_code<br><br>";

            // Update room code in related tables
            $tables = [
                $sanitized_section_sched_code,
                $sanitized_room_sched_code,
                $sanitized_prof_sched_code,
            ];

            $any_update = false;

            foreach ($tables as $table) {
                // Check if the old room code exists in the table
                $check_sql = "SELECT COUNT(*) FROM $table WHERE room_code = ? AND semester = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ss", $old_room_code, $semester);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();


                if ($count > 0) {
                    $update_sql = "UPDATE $table SET room_code = ? WHERE room_code = ? AND semester = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    if ($update_stmt) {
                        $update_stmt->bind_param("sss", $room_code, $old_room_code, $semester);

                        echo "main old course code: $old_room_code<br>";
                        echo "main new course code: $room_code<br>";
                        echo "main dept code: $dept_code<br><br>";

                        $old_room_sched_code = trim($old_room_code) . "_" . trim($ay_code);
                        $new_room_sched_code = trim($room_code) . "_" . trim($ay_code);

                        echo "old room sched code: $old_room_sched_code<br>";
                        echo "new sched code: $new_room_sched_code<br>";
                        // echo "main dept code: $dept_code<br><br>";

                        if ($update_stmt->execute()) {
                            $update_sched_code_sql = "UPDATE $sanitized_room_sched_code SET room_sched_code = ? WHERE room_code = ? AND dept_code = ? AND semester = ?";
                            $update_sched_code_stmt = $conn->prepare($update_sched_code_sql);
                            if ($update_sched_code_stmt) {
                                $update_sched_code_stmt->bind_param("ssss", $new_room_sched_code, $old_room_code, $dept_code, $semester);
                            }

                            if ($update_stmt->execute()) {
                                $update_sched_code_sql = "UPDATE $sanitized_room_sched_code SET room_sched_code = ? WHERE room_code = ? AND dept_code = ? AND semester = ?";
                                $update_sched_code_stmt = $conn->prepare($update_sched_code_sql);

                                if ($update_sched_code_stmt) {
                                    $update_sched_code_stmt->bind_param("ssss", $new_room_sched_code, $old_room_code, $dept_code, $semester);

                                    if ($update_sched_code_stmt->execute()) {
                                        echo "room_sched_code updated successfully to $new_room_sched_code.<br>";
                                    } else {
                                        echo "Error updating room_sched_code: " . $update_sched_code_stmt->error . "<br>";
                                    }
                                } else {
                                    // echo "Error preparing room_sched_code update statement.<br>";
                                }
                            }


                            // Update tbl_rsched first
                            $update_status_sql = "UPDATE tbl_rsched SET room_code = ?, room_sched_code = ? WHERE room_code = ? AND dept_code = ?";
                            $update_status_stmt = $conn->prepare($update_status_sql);

                            if ($update_status_stmt) {
                                $update_status_stmt->bind_param("ssss", $room_code, $new_room_sched_code, $old_room_code, $dept_code);
                                if (!$update_status_stmt->execute()) {
                                    echo "<script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var modal = new bootstrap.Modal(document.getElementById('successModal'));
                                        document.getElementById('successMessage').textContent = 'Failed to update room_code and room_sched_code in tbl_rsched: " . $update_status_stmt->error . "';
                                        modal.show();
                                    });
                                </script>";
                                    $message .= "Failed to update room_code and room_sched_code in tbl_rsched: " . $update_status_stmt->error . "<br>";
                                } else {
                                    $message .= "room_code and room_sched_code updated successfully in tbl_rsched.<br><br>";

                                    // Debugging: Check if old room_sched_code exists in tbl_room_schedstatus before updating
                                    $check_schedstatus_sql = "SELECT COUNT(*) FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ?";
                                    $check_schedstatus_stmt = $conn->prepare($check_schedstatus_sql);
                                    $check_schedstatus_stmt->bind_param("ss", $old_room_sched_code, $semester);
                                    $check_schedstatus_stmt->execute();
                                    $check_schedstatus_stmt->bind_result($schedstatus_count);
                                    $check_schedstatus_stmt->fetch();
                                    $check_schedstatus_stmt->close();

                                    //Debugging: Output the count of matching rows
                                    echo "Schedstatus count for old_room_sched_code: " . $schedstatus_count . "<br><br>";

                                    if ($schedstatus_count > 0) {
                                        // Now update room_sched_code in tbl_room_schedstatus
                                        $update_room_schedstatus_sql = "UPDATE tbl_room_schedstatus SET room_sched_code = ? WHERE room_sched_code = ?  AND semester = ?";
                                        $update_room_schedstatus_stmt = $conn->prepare($update_room_schedstatus_sql);

                                        if ($update_room_schedstatus_stmt) {
                                            $update_room_schedstatus_stmt->bind_param("sss", $new_room_sched_code, $old_room_sched_code, $semester);
                                            if (!$update_room_schedstatus_stmt->execute()) {
                                                $message .= "Failed to update room_sched_code in tbl_room_schedstatus: " . $update_room_schedstatus_stmt->error . "<br>";
                                            } else {
                                                $message .= "room_sched_code updated successfully in tbl_room_schedstatus.<br>";
                                            }
                                            $update_room_schedstatus_stmt->close();

                                            echo "tbl_room_schedstatus old room sched code: $old_room_sched_code<br>";
                                            echo "tbl_room_schedstatus old room sched code: $new_room_sched_code<br><br>";
                                        }
                                    } else {
                                        $message .= "No matching room_sched_code found in tbl_room_schedstatus.<br>";
                                    }
                                }
                                $update_status_stmt->close();
                            }
                        }
                        $update_stmt->close();
                    } else {
                        $message .= "No matching records found for room_code in $table. ";
                    }
                }
            }

            if (!$any_update) {
                $sql = "UPDATE tbl_room SET dept_code = ?, room_code = ?, room_name = ?, room_type = ?, room_in_charge = ?, status = ? WHERE id = ?";
                $update_room_stmt = $conn->prepare($sql);
                if ($update_room_stmt) {
                    // Bind the parameters to the prepared statement
                    $update_room_stmt->bind_param("ssssssi", $dept_code, $room_code, $room_name, $room_type, $room_in_charge, $status, $room_id);

                    // Execute the statement
                    if ($update_room_stmt->execute()) {
                        echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var modal = new bootstrap.Modal(document.getElementById('successModal'));
                            document.getElementById('successMessage').textContent = 'Record Updated Successfully.';
                            modal.show();
                        });
                    </script>";
                    } else {
                        $message .= "Error updating tbl_room: " . $update_room_stmt->error . " ";
                    }

                    // Close the statement
                    $update_room_stmt->close();
                }
            }
        }

        $dept_code = $_SESSION['dept_code'];
        $receiver_dept_codes = [];

        // Prepare SQL statement to fetch receiver department codes
        $shared_sched_sql = "SELECT receiver_dept_code FROM tbl_shared_sched WHERE sender_dept_code = ?";
        $shared_sched_stmt = $conn->prepare($shared_sched_sql);

        if ($shared_sched_stmt) {
            $shared_sched_stmt->bind_param("s", $dept_code);
            $shared_sched_stmt->execute();
            $shared_sched_stmt->bind_result($receiver_dept_code);

            while ($shared_sched_stmt->fetch()) {
                $receiver_dept_codes[] = $receiver_dept_code;
            }
            $shared_sched_stmt->close();
        }

        // Check for receiver department codes
        if (!empty($receiver_dept_codes)) {
            // echo "Receiver dept codes: " . implode(', ', $receiver_dept_codes) . "<br><br>";
            foreach ($receiver_dept_codes as $receiver_dept_code) {
                // Sanitize schedule codes
                $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");

                // Display sanitized schedule codes
                // echo "Receiver Sanitized Section Sched: $sanitized_section_sched_code<br>";
                // echo "Receiver Sanitized Room Sched: $sanitized_room_sched_code<br>";
                // echo "Receiver Sanitized Prof Sched: $sanitized_prof_sched_code<br><br>";

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

                    // Prepare to check for existing records
                    $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE room_code = ? AND dept_code = ?  AND semester = ?";
                    $check_shared_stmt = $conn->prepare($check_shared_sql);
                    $check_shared_stmt->bind_param("sss", $old_room_code, $receiver_dept_code, $semester);
                    $check_shared_stmt->execute();
                    $check_shared_stmt->store_result(); // Store the result to avoid sync issues

                    $check_shared_stmt->bind_result($shared_count);
                    $check_shared_stmt->fetch();
                    $check_shared_stmt->close();


                    // Only update if records exist
                    if ($shared_count > 0) {
                        $update_shared_sql = "UPDATE $table SET room_code = ? WHERE room_code = ? AND dept_code = ?  AND semester = ?";
                        $update_shared_stmt = $conn->prepare($update_shared_sql);
                        if ($update_shared_stmt) {
                            $update_shared_stmt->bind_param("ssss", $room_code, $old_room_code, $receiver_dept_code, $semester);
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

            // echo "No receiver dept codes found.<br><br>";


            // Check for records where receiver_dept_code = $dept_code
            $sender_sched_sql = "SELECT sender_dept_code FROM tbl_shared_sched WHERE receiver_dept_code = ?";
            $sender_sched_stmt = $conn->prepare($sender_sched_sql);
            if ($sender_sched_stmt) {
                $sender_sched_stmt->bind_param("s", $dept_code);
                $sender_sched_stmt->execute();
                $sender_sched_stmt->bind_result($sender_dept_code);

                if ($sender_sched_stmt->fetch()) {
                    // If a record is found, use the sender_dept_code to update room_code
                    echo "Sender Dept Code found: $sender_dept_code<br>";

                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$sender_dept_code}_{$ay_code}");

                    // // Display sanitized schedule codes
                    // echo "1Sender Sanitized Section Sched: $sanitized_section_sched_code<br>";
                    // echo "1Sender  Sanitized Room Sched: $sanitized_room_sched_code<br>";
                    // echo "1Sender  Sanitized Prof Sched: $sanitized_prof_sched_code<br><br>";

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
                        $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE room_code = ? AND dept_code = ?  AND semester = ?";
                        $check_shared_stmt = $conn->prepare($check_shared_sql);
                        $check_shared_stmt->bind_param("sss", $old_room_code, $dept_code, $semester);
                        $check_shared_stmt->execute();
                        $check_shared_stmt->store_result(); // Store result to avoid sync issues
                        $check_shared_stmt->bind_result($shared_count);
                        $check_shared_stmt->fetch();
                        $check_shared_stmt->close();

                        // Only update if records exist
                        if ($shared_count > 0) {
                            $update_shared_sql = "UPDATE $table SET room_code = ? WHERE room_code = ? AND dept_code = ?  AND semester = ?";
                            $update_shared_stmt = $conn->prepare($update_shared_sql);
                            if ($update_shared_stmt) {
                                $update_shared_stmt->bind_param("ssss", $room_code, $old_room_code, $dept_code, $semester);
                                if ($update_shared_stmt->execute()) {
                                    echo "Updated room_code in table $table for receiver_dept_code: $sender_dept_code<br>";
                                } else {
                                    echo "Failed to update room_code in table $table for receiver_dept_code: $sender_dept_code. Error: " . $update_shared_stmt->error . "<br>";
                                }
                                $update_shared_stmt->close();

                                // // Display old and new room codes
                                // echo "Receiver Old Room Code: $old_room_code<br>";
                                // echo "Receiver New Room Code: $room_code<br>";
                                // echo "Receiver Dept Code: $receiver_dept_code<br><br>";
                            } else {
                                echo "Error preparing update statement for table $table: " . $conn->error . "<br>";
                            }
                        } else {
                            // No records found for the given room_code and dept_code
                            echo "No records found in table $table for room_code: $old_room_code and dept_code: $dept_code<br><br>";
                        }
                    }
                }
            }
        }

        // echo "<script>alert('Record successfully updated.');</script>"; 
    } elseif ($action == "delete") {
        // Define the sanitized schedule codes
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

        $canDelete = true; // Flag to determine if deletion is allowed

        foreach ($shared_tables as $table) {
            $check_shared_sql = "SELECT COUNT(*) FROM $table WHERE room_code = ? AND dept_code = ?  AND semester = ? AND ay_code = ?";
            $check_shared_stmt = $conn->prepare($check_shared_sql);
            if ($check_shared_stmt) {
                $check_shared_stmt->bind_param("ssss", $room_code, $dept_code, $semester, $ay_code);
                $check_shared_stmt->execute();
                $check_shared_stmt->bind_result($sched_count);
                $check_shared_stmt->fetch();
                $check_shared_stmt->close();
            }

            if ($sched_count > 0) {
                // Ensure the modal script is executed after the DOM is fully loaded
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modal = new bootstrap.Modal(document.getElementById('successModal'));
                        document.getElementById('successMessage').textContent = 'Deletion not Allowed: There is a plotted schedule in $room_code.';
                        modal.show();
                    });
                </script>";
                $canDelete = false; // Set flag to false, as deletion is not allowed
                break;
            }
        }

        // Proceed with deletion only if there are no schedules
        if ($canDelete) {
            // Delete room if no schedules found
            $delete_sql = "DELETE FROM tbl_room WHERE room_code=? AND dept_code=?  ";
            $delete_stmt = $conn->prepare($delete_sql);
            if ($delete_stmt === false) {
                $message = "Error preparing statement: " . $conn->error;
            } else {
                $delete_stmt->bind_param("ss", $room_code, $dept_code,);
                if ($delete_stmt->execute()) {
                    echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modal = new bootstrap.Modal(document.getElementById('successModal'));
                        document.getElementById('successMessage').textContent = 'Record successfully deleted';
                        modal.show();
                    });
                </script>";
                } else {
                    $message = "Error: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            }
        }
    } else {
        $message = "Invalid action";
    }
}

// Handle GET request for filtering
if (isset($_GET['room_type'])) {
    $search_room_type = $_GET['room_type'];
}
if (isset($_GET['room_code_name'])) {
    $search_room_code_name = $_GET['room_code_name'];
}
if (isset($_GET['status'])) {
    $search_status = $_GET['status'];
}

// Fetch records from the database with filtering
$sql = "SELECT * FROM tbl_room WHERE dept_code = ?";
$params = [$dept_code];
$types = "s";

if (!empty($search_room_type)) {
    $sql .= " AND room_type = ?";
    $params[] = $search_room_type;
    $types .= "s";
}

if (!empty($search_room_code_name)) {
    $sql .= " AND (room_code LIKE ? OR room_name LIKE ? OR room_in_charge LIKE ?)";
    $params[] = "%$search_room_code_name%";
    $params[] = "%$search_room_code_name%";
    $params[] = "%$search_room_code_name%";
    $types .= "sss";
}

if (!empty($search_status)) {
    $sql .= " AND status = ?";
    $params[] = $search_status;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch total records for other purposes (if needed)
$count_sql = "SELECT COUNT(*) as total FROM tbl_room WHERE dept_code = ?";
$count_params = [$dept_code];
$count_types = "s";

if (!empty($search_room_type)) {
    $count_sql .= " AND room_type = ?";
    $count_params[] = $search_room_type;
    $count_types .= "s";
}

if (isset($_GET['status'])) {
    $search_status = $_GET['status'];
}

if (!empty($search_room_code_name)) {
    $count_sql .= " AND (room_code LIKE ? OR room_name LIKE ? OR room_in_charge LIKE ?)";
    $count_params[] = "%$search_room_code_name%";
    $count_params[] = "%$search_room_code_name%";
    $count_params[] = "%$search_room_code_name%";
    $count_types .= "sss";
}

$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$count_stmt->close();


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Classroom Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/room_input.css">
</head>


<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>


    <section class="class-input">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <?php if ($user_type !== "CCL Head") { ?>
                <li class="nav-item">
                    <a class="nav-link" id="program-tab" href="program_input.php" aria-controls="program" aria-selected="false">Program Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="course-tab" href="course_input.php" aria-controls="course" aria-selected="false">Checklist Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="section-tab" href="section_input.php" aria-controls="section" aria-selected="false">Section Input</a>
                </li>
            <?php } ?>
            <li class="nav-item">
                <a class="nav-link active" id="room-tab" href="classroom_input.php" aria-controls="room" aria-selected="true">Room Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="prof-tab" href="#" aria-controls="prof" aria-selected="false" data-bs-toggle="modal" data-bs-target="#profUnitModal">Instructor Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="signatory-tab" href="signatory_input.php" aria-controls="signatory" aria-selected="false">Signatory Input</a>
            </li>
        </ul>



        <div class="text-center">
            <form method="GET" action="" class="d-inline-block w-100">
                <div class="filtering d-flex flex-wrap justify-content-center">
                    <div class="form-group col-md-3">
                        <select class="form-select" id="status_filter" name="status" style="color: #6c757d;">
                            <option value="" disabled selected style="color: #6c757d;">Filter Room Status</option>
                            <option value="">All</option>

                            <?php
                            // Fetch room statuses from the database
                            $sql = "SELECT DISTINCT status FROM tbl_room";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                // Loop through the statuses and create option elements
                                while ($row = $result->fetch_assoc()) {
                                    $status = $row['status'];
                                    $selected = ($search_status == $status) ? 'selected' : '';
                                    echo "<option value=\"$status\" $selected>$status</option>";
                                }
                            } else {
                                // If no statuses are found, display a default option
                                echo "<option value=\"\" disabled>No statuses available</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Room Type Filter -->
                    <div class="form-group col-md-3">
                        <select class="form-select" id="room_type_filter" name="room_type" style="color: #6c757d;">
                            <option value="" disabled selected style="color: #6c757d;">Filter Room Type</option>
                            <option value="">All</option>

                            <?php
                            $sql = "SELECT DISTINCT room_type FROM tbl_room";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $room_type = $row['room_type'];
                                    $selected = ($search_room_type == $room_type) ? 'selected' : '';
                                    echo "<option value=\"$room_type\" $selected>$room_type</option>";
                                }
                            } else {
                                // If no room types are found, display a default option
                                echo "<option value=\"\" disabled>No room types available</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Room Code or Name Filter -->
                    <div class="form-group col-md-3">
                        <input type="text" class="form-control" id="room_code_name_filter" name="room_code_name" value="<?php echo htmlspecialchars($search_room_code_name); ?>" placeholder="Room Code or Name">
                    </div>

                    <!-- Search Button -->
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn w-100" style="border: none;">Search</button>
                    </div>

                </div>
            </form>
        </div>


        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="title">Room Input</h5>
                <form action="" method="POST" id="room-form" required>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                    <input type="hidden" id="room_id" name="room_id" value="<?php echo $last_inserted_id; ?>" readonly>
                    <input type="hidden" id="user_type" value="<?php echo $user_type; ?>">



                    <div class="mb-3 d-flex gap-2">
                        <div style="flex: 1;">
                            <input type="text" class="form-control" id="room" name="room" placeholder="Enter Room (e.g., CL, Lab)" autocomplete="off" style="color: #6c757d;" required>
                        </div>
                        <div style="flex: 1;">
                            <input type="text" class="form-control" id="room_number" name="room_number" placeholder="Enter Room Number (e.g., 101)" autocomplete="off" style="color: #6c757d;" required>
                        </div>
                    </div>


                    <div class="mb-3">
                        <!-- <label for="room_name">Room Name</label> -->
                        <input type="text" class="form-control" id="room_name" placeholder="Enter Room Name"
                            name="room_name" autocomplete="off" style="color: #6c757d;">
                    </div>

                    <div class="mb-3">
                        <!-- <label for="room_in_charge">Room in Charge</label> -->
                        <input type="text" class="form-control" id="room_in_charge" placeholder="Enter Room In Charge"
                            name="room_in_charge" autocomplete="off" style="color: #6c757d;">
                    </div>


                    <?php if ($user_type !== "CCL Head") { ?>
                        <div class="mb-3">
                            <select class="form-select" id="room_type" name="room_type" style="color: #6c757d;" required>
                                <option value="">Select Room Type</option>
                                <option value="Lecture">Lecture</option>
                                <option value="Laboratory">Laboratory</option>
                            </select>
                        </div>
                    <?php } ?>

                    <div class="mb-3">
                        <!-- <label for="status">Room Status</label> -->
                        <select class="form-select" id="status" name="status" style="color: #6c757d;" required>
                            <option value="">Select Room Status</option>
                            <option value="Available">Available</option>
                            <option value="Not Available">Not Available</option>
                        </select>
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

            <div class="col-lg-8">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Room Code</th>
                                <th>Room Name</th>
                                <th class="filterable" data-column="room_type">Room Type</th>
                                <th>Room In Charge</th>
                                <th class="filterable" data-column="status">Room Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Modify query to fetch room data without pagination
                            $sql = "SELECT * FROM tbl_room WHERE dept_code = ? AND room_type IN ('Lecture', 'Laboratory')";
                            $params = [$dept_code];
                            $types = "s";


                            // Apply additional filters
                            if (!empty($search_room_type)) {
                                $sql .= " AND room_type = ?";
                                $params[] = $search_room_type;
                                $types .= "s";
                            }
                            if (!empty($search_status)) {
                                $sql .= " AND status = ?";
                                $params[] = $search_status;
                                $types .= "s";
                            }

                            if (!empty($search_room_code_name)) {
                                $sql .= " AND (room_code LIKE ? OR room_name LIKE ? OR room_in_charge LIKE ?)";
                                $params[] = "%$search_room_code_name%";
                                $params[] = "%$search_room_code_name%";
                                $params[] = "%$search_room_code_name%";
                                $types .= "sss";
                            }

                            // Prepare and execute the data retrieval statement
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            // Display room data in table rows
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr 
                                        data-room_id='" . htmlspecialchars($row['id']) . "'
                                        data-room_code='" . htmlspecialchars($row['room_code']) . "'
                                        data-room_name='" . htmlspecialchars($row['room_name']) . "'
                                        data-room_type='" . htmlspecialchars($row['room_type']) . "'
                                        data-room_in_charge='" . htmlspecialchars($row['room_in_charge']) . "'
                                        data-status='" . htmlspecialchars($row['status']) . "'>
                                        <td>" . htmlspecialchars($row['room_code']) . "</td>
                                        <td>" . htmlspecialchars($row['room_name']) . "</td>
                                        <td>" . htmlspecialchars($row['room_type']) . "</td>
                                        <td>" . htmlspecialchars($row['room_in_charge']) . "</td>
                                        <td>" . htmlspecialchars($row['status']) . "</td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>No records found</td></tr>";
                            }

                            $stmt->close();
                            ?>
                        </tbody>
                    </table>
                </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            let selectedRow = null;

            function fillForm(roomId, roomCode, roomName, roomInCharge, roomType, roomStatus) {
                console.log('Filling form with:', roomId, roomCode, roomName, roomInCharge, roomType, roomStatus);

                document.getElementById('room_id').value = roomId;
                document.getElementById('room_name').value = roomName;
                document.getElementById('room_in_charge').value = roomInCharge;
                document.getElementById('room_type').value = roomType;
                document.getElementById('status').value = roomStatus;

                // Split roomCode into room and room_number
                if (roomCode) {
                    const parts = roomCode.split(' ');
                    document.getElementById('room').value = parts[0] ?? '';
                    document.getElementById('room_number').value = parts[1] ?? '';
                } else {
                    document.getElementById('room').value = '';
                    document.getElementById('room_number').value = '';
                }

                // Hide "Add" button, show "Update/Delete" buttons
                document.querySelector('.btn-add').style.display = 'none';
                document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'inline-block');
            }

            document.querySelectorAll('table tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    const roomId = this.getAttribute('data-room_id');
                    const cells = this.getElementsByTagName('td');

                    fillForm(
                        roomId,
                        cells[0]?.innerText.trim() || '',
                        cells[1]?.innerText.trim() || '',
                        cells[3]?.innerText.trim() || '',
                        cells[2]?.innerText.trim() || '',
                        cells[4]?.innerText.trim() || ''
                    );

                    if (selectedRow) {
                        selectedRow.classList.remove('clicked-row');
                    }

                    this.classList.add('clicked-row');
                    selectedRow = this;
                });
            });

            document.addEventListener('click', function(event) {
                const roomForm = document.querySelector('#room-form');
                const table = document.querySelector('table');
                const userType = document.getElementById('user_type').value;

                if (!roomForm.contains(event.target) && !table.contains(event.target)) {
                    if (selectedRow) {
                        clearForm();
                        selectedRow.classList.remove('clicked-row');
                        selectedRow = null;
                    }

                    if (userType === "CCL Head") {
                        clearForm();
                    }
                }
            });

            function clearForm() {
                document.getElementById('room_id').value = '';
                document.getElementById('room').value = '';
                document.getElementById('room_number').value = '';
                document.getElementById('room_name').value = '';
                document.getElementById('room_in_charge').value = '';
                document.getElementById('room_type').value = '';
                document.getElementById('status').value = '';

                document.querySelector('.btn-add').style.display = 'inline-block';
                document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'none');
            }
        });



        // Function to hide columns based on filter criteria
        function hideFilteredColumns() {
            const urlParams = new URLSearchParams(window.location.search);
            const roomTypeFilter = urlParams.get('room_type') || '';
            const roomStatusFilter = urlParams.get('status') || '';

            if (roomTypeFilter && roomTypeFilter !== "ALL") {
                hideColumn('Room Type');
            }

            if (roomStatusFilter && roomStatusFilter !== "ALL") {
                hideColumn('Room Status');
            }
        }

        function hideColumn(columnName) {
            const headers = document.querySelectorAll('table th');
            let columnIndex = -1;

            headers.forEach((th, index) => {
                if (th.textContent.trim() === columnName) {
                    columnIndex = index;
                }
            });

            if (columnIndex !== -1) {
                const rows = document.querySelectorAll('table tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td, th');
                    cells[columnIndex].style.display = 'none';
                });
            }
        }
        document.addEventListener('DOMContentLoaded', hideFilteredColumns);
    </script>
</body>

</html>