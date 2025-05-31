<?php
include("../../config.php");
session_start();

// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../../login/login.php");
    exit();
}

$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$dept_code = $_SESSION['dept_code'];
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}


// Step 2: Prepare the SQL statement to retrieve the last inserted ID from tbl_course
$sql = "SELECT MAX(id) AS last_id FROM tbl_section"; // Assuming your primary key is 'id'

// Step 3: Execute the query
$result = $conn->query($sql);

// Step 4: Fetch the result
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_inserted_id = $row['last_id'];
} else {
    echo "No records found in tbl_section.";
}

$fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];

    // Store ay_code and semester in the session
    $_SESSION['ay_code'] = $ay_code;
    $_SESSION['semester'] = $semester;
}

// echo "$semester";
// echo "$ay_code";

// Pagination logic
$limit = 10; // Number of entries per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1; // Current page number
$offset = ($page - 1) * $limit; // Offset for SQL query

// Fetch total number of records
$total_sql = "SELECT COUNT(*) FROM tbl_section WHERE dept_code=?";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param("s", $dept_code);
$total_stmt->execute();
$total_stmt->bind_result($total_records);
$total_stmt->fetch();
$total_stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch existing records to display in the table
$sql = "SELECT program_code, section_code, year_level,dept_code,section_no  
        FROM tbl_section 
        WHERE dept_code IN (SELECT dept_code FROM tbl_department WHERE college_code = ?) 
        LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$result = $stmt->get_result();


// Fetch program codes to populate dropdown
$program_sql = "SELECT program_code FROM tbl_program WHERE dept_code = ?";
$program_stmt = $conn->prepare($program_sql);
$program_stmt->bind_param("s", $dept_code);
$program_stmt->execute();
$program_result = $program_stmt->get_result();


function generateSectionCode($program_code, $section_code)
{
    // If section_code is numeric, concatenate program_code and section_code
    if (is_numeric($section_code)) {
        return $program_code . ' ' . $section_code;
    } else {
        return $section_code;
    }
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    // Retrieve POST data
    $program_code = $_POST['program_code'] ?? null;
    $section_no_input = $_POST['section_no'] ?? null; // User input for the total number of sections
    $section_no = $_POST['single_section_no'] ?? '';
    $year_level = $_POST['year_level'] ?? null; // e.g., "1", "2", etc.
    $action = $_POST['action'] ?? null;
    $curriculum = $_POST['curriculum'] ?? '';
    $section_id = $_POST['section_id'] ?? '';
    $ay_code = $_SESSION['ay_code'];
    $original_section_code = strtoupper($_POST['original_section_code'] ?? '');
    $petition = isset($_POST['petition']) ? 1 : 0;
    $number_of_sections = isset($_POST['number_of_section']) ? intval($_POST['number_of_section']) : 1;

    if (!isset($original_section_code)) {
        $original_section_code = '';
    }

    // Function to convert year level to an integer
    function convertYearLevelToInt($year_level)
    {
        preg_match('/(\d+)/', $year_level, $matches);
        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    // Convert year level to integer
    $year_level_int = convertYearLevelToInt($year_level);



    // var_dump([
    //     'college_code' => $college_code,
    //     'program_code' => $program_code,
    //     'year_level' => $year_level_int,
    //     'section_no' => $section_no_input,
    //     'dept_code' => $dept_code,
    //     'curriculum' => $curriculum,
    //     'ay_code' => $ay_code,
    //     'semester' => $semester,
    //     'petition' => $petition
    // ]);


    if ($program_code && ($section_no_input || $section_no) && $year_level && $action) {
        if ($action == "add") {

            $check_sql = "SELECT * FROM tbl_section WHERE section_code = ? AND program_code = ? AND ay_code = ? AND semester = ?";
            $check_stmt = $conn->prepare($check_sql);

            $success = true;
            $duplicate_sections = [];

            $insert_sql = "INSERT INTO tbl_section (college_code, program_code, section_code, year_level, section_no, dept_code, curriculum, ay_code, semester, petition)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);

            if ($petition == 1) {
                // PETITION section (single entry)
                $section_no = $_POST['single_section_no'] ?? '';
                $section_code = $program_code . " " . $year_level_int . "-" . $section_no;

                // echo"You are here";

                $check_stmt->bind_param("ssss", $section_code, $program_code, $ay_code, $semester);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $duplicate_sections[] = $section_code;
                } else {
                    $insert_stmt->bind_param(
                        "sssssssssi",
                        $college_code,
                        $program_code,
                        $section_code,
                        $year_level,
                        $section_no,
                        $dept_code,
                        $curriculum,
                        $ay_code,
                        $semester,
                        $petition
                    );
                    if (!$insert_stmt->execute()) {
                        error_log("Insert failed for $section_code: " . $insert_stmt->error);
                        echo "Insert error: " . $insert_stmt->error;
                        $success = false;
                    }
                }

                // echo"$year_level";
            } else {
                $section_count = intval($_POST['section_no'] ?? 1);


                for ($i = 1; $i <= $section_count; $i++) {
                    $section_no = $i;
                    $section_code = $program_code . " " . $year_level_int . "-" . $section_no;

                    $check_stmt->bind_param("ssss", $section_code, $program_code, $ay_code, $semester);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $duplicate_sections[] = $section_code;
                        continue;
                    }

                    $insert_stmt->bind_param(
                        "sssssssssi",
                        $college_code,
                        $program_code,
                        $section_code,
                        $year_level,
                        $section_no,
                        $dept_code,
                        $curriculum,
                        $ay_code,
                        $semester,
                        $petition
                    );

                    if ($insert_stmt->execute()) {
                        $copy_section = $section_code;
                        echo "$copy_section";

                        // Construct current table for inserting copied data
                        $section_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $section_code . "_" . $ay_code);
                        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

                        // Clean section code
$parts = preg_split('/\s+/', trim($copy_section)); // splits by any whitespace

if (count($parts) === 2) {
    $program = $parts[0];          // e.g., 'BSIT'
    $year_section = $parts[1];     // e.g., '3-1'
    $year_section = str_replace('-', '_', $year_section); // '3_1'

    $cleaned_section = $program . ' ' . $year_section;    // 'BSIT 3_1'
} else {
    // Fallback
    $cleaned_section = str_replace('-', ' ', $copy_section);

}


    echo "Cleaned Section: $cleaned_section<br>";

                        // Determine previous academic year
                        $ay_parts = explode(' - ', $ay_name);
                        $prev_start = intval($ay_parts[0]) - 1;
                        $prev_end = intval($ay_parts[1]) - 1;

                        // Get last two digits
                        $previous_ay_code = substr($prev_start, -2) . substr($prev_end, -2);

                        echo "prev ay_code: $previous_ay_code";

                        // Determine where to copy from
                        if ($semester == '1st Semester') {
                            $previous_semester = '1st Semester';
                            $source_ay_code = $previous_ay_code;
                        } else {
                            $previous_semester = '2nd Semester';
                            $source_ay_code = $ay_code;
                        }

// Construct table names and section codes
$sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", strtolower("{$dept_code}_{$ay_code}"));
$sanitized_source_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", strtolower("{$dept_code}_{$source_ay_code}"));
$source_section = $cleaned_section . '_' . $source_ay_code;
$updated_section_code = $cleaned_section . '_' . $ay_code;




// // // Debug Information
// echo "<br><strong>Debug Info:</strong><br>";
// echo "Current Table: $sanitized_dept_code <br>";
// echo "Source Table: $sanitized_source_dept_code <br>";
// echo "Cleaned Section: $cleaned_section<br>";
// echo "updated_section_code: $updated_section_code<br>";
// echo "Source Section: $source_section<br>";
// echo "Copying From AY: $source_ay_code, Semester: $previous_semester<br>";
// echo "To AY: $ay_code, Semester: $semester<br><br>";

// $messages = [];

// 1. Check if source table exists
$table_check_query = "SHOW TABLES LIKE '$sanitized_source_dept_code'";
$table_check_result = $conn->query($table_check_query);

if ($table_check_result->num_rows == 0) {
       $_SESSION['modal_message'] = "Source table '$sanitized_source_dept_code' does not exist. Skipping schedule copy.";
                    header("Location: section_input.php");
} else {
    // 2. Check if schedule exists in source table
    $check_schedule_exists_query = "
        SELECT COUNT(*) as total 
        FROM $sanitized_source_dept_code 
        WHERE section_sched_code = '$source_section' 
        AND semester = '$previous_semester'";

    $check_schedule_result = $conn->query($check_schedule_exists_query);

    if (!$check_schedule_result) {
        echo "Error checking schedule existence: " . $conn->error;
    } else {
        $schedule_data = $check_schedule_result->fetch_assoc();

        if ($schedule_data['total'] == 0) {
            // echo "No schedule found for section '$source_section' in AY $source_ay_code semester $previous_semester.";
        } else {
            // 3. Check for conflict in the target section
            $prof_check_query = "
                SELECT COUNT(*) as conflict_count 
                FROM $sanitized_dept_code 
                WHERE section_sched_code = '$section_sched_code' 
                AND (
                    (prof_code IS NOT NULL AND prof_code != '') 
                    OR 
                    (room_code IS NOT NULL AND room_code != '')
                )";

            $prof_result = $conn->query($prof_check_query);
            $prof_data = $prof_result->fetch_assoc();

            if ($prof_data['conflict_count'] > 0) {
                 $_SESSION['modal_message'] = "Cannot copy schedule. A professor or room is already assigned in the source section.";
                    header("Location: section_input.php");
            }

            // 4. Delete existing schedule of the target section
            $delete_existing_query = "
                DELETE FROM $sanitized_dept_code 
                WHERE section_sched_code = '$section_sched_code'";
            $conn->query($delete_existing_query);

            // 5. Fetch and insert rows from source table
            $get_schedule_query = "
                SELECT * FROM $sanitized_source_dept_code 
                WHERE section_sched_code = '$source_section' 
                AND semester = '$previous_semester'";

            $schedule_result = $conn->query($get_schedule_query);

            if (!$schedule_result) {
                echo "Error fetching schedule rows: " . $conn->error;
            } elseif ($schedule_result->num_rows > 0) {
                while ($row = $schedule_result->fetch_assoc()) {
                    $time_start = $row['time_start'];
                    $time_end = $row['time_end'];
                    $day = $row['day'];
                    $curriculum = $row['curriculum'];
                    $source_dept_code = $row['dept_code'];
                    $course_code = $row['course_code'];
                    $class_type = $row['class_type'];
                    $cell_color = $row['cell_color'];

                    $insert_query = "
                        INSERT INTO $sanitized_dept_code 
                        (section_sched_code, time_start, time_end, day, curriculum, dept_code, course_code, class_type, semester, cell_color, ay_code) 
                        VALUES 
                        ('$updated_section_code', '$time_start', '$time_end', '$day', '$curriculum', '$source_dept_code', '$course_code', '$class_type', '$semester', '$cell_color', '$ay_code')";

                    if (!$conn->query($insert_query)) {
                        echo "Insert failed: " . $conn->error . "<br>Query: $insert_query";
                    }
                }

                // echo "Schedule of section '$copy_section' copied successfully from AY $source_ay_code semester $previous_semester.";

// 6. Insert into tbl_schedstatus if not exists
$check_schedstatus_query = "
    SELECT COUNT(*) AS count 
    FROM tbl_schedstatus
    WHERE section_sched_code = '$section_sched_code' 
    AND semester = '$semester'
    AND ay_code = '$ay_code'";

$schedstatus_result = $conn->query($check_schedstatus_query);
$schedstatus_data = $schedstatus_result->fetch_assoc();

if ($schedstatus_data['count'] == 0) {
    $insert_schedstatus_query = "
        INSERT INTO tbl_schedstatus 
        (section_sched_code, curriculum, dept_code, college_code, semester, ay_code, status, cell_color, petition) 
        VALUES 
        ('$updated_section_code', '$curriculum', '$dept_code', '$college_code', '$semester', '$ay_code', 'draft', '#FFFFFF', 0)";

    if (!$conn->query($insert_schedstatus_query)) {
        echo "Failed to insert into tbl_schedstatus: " . $conn->error;
    } else {
         $_SESSION['modal_message'] = "Section Added Successfully";
                    header("Location: section_input.php");

        // 7. Insert into tbl_secschedlist if not exists
        $check_secschedlist_query = "
            SELECT COUNT(*) AS count 
            FROM tbl_secschedlist
            WHERE section_sched_code = '$section_sched_code' 
            AND ay_code = '$ay_code'";

        $secschedlist_result = $conn->query($check_secschedlist_query);
        $secschedlist_data = $secschedlist_result->fetch_assoc();

        if ($secschedlist_data['count'] == 0) {
            $insert_secschedlist_query = "
                INSERT INTO tbl_secschedlist 
                (section_sched_code, curriculum, dept_code, college_code, program_code, section_code, ay_code, petition) 
                VALUES 
                ('$updated_section_code', '$curriculum', '$dept_code', '$college_code', '$program_code', '$section_code', '$ay_code', 0)";

            if (!$conn->query($insert_secschedlist_query)) {
                echo "Failed to insert into tbl_secschedlist: " . $conn->error;
            } else {
               $_SESSION['modal_message'] = "Section Added Successfully";
                    header("Location: section_input.php");
                    }
        } else {
            $_SESSION['modal_message'] = "Section already exists for AY $ay_code.";
                    header("Location: section_input.php");
        }
    }
} else {
      $_SESSION['modal_message'] = "Section already exists for AY $ay_code and semester $semester.";
                    header("Location: section_input.php");
}

            } else {
                echo "No schedule rows found to copy.";
            }
        }
    }
}
                    }
                }
            }



            // Output results in modal
            echo "<script type='text/javascript'>
document.addEventListener('DOMContentLoaded', function() {
    var conflictList = document.getElementById('conflictList');
";

            foreach ($messages as $message) {
                $safe_message = htmlspecialchars($message, ENT_QUOTES);
                echo "var li = document.createElement('li');
          li.textContent = '$safe_message';
          conflictList.appendChild(li);";
            }

            echo "
    var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
    myModal.show();
});
</script>";



            echo $year_level;
            // Modal feedback
            if ($success) {
                $message = 'Section(s) added successfully.';
                if (!empty($duplicate_sections)) {
                     $_SESSION['modal_message'] = "\\nThe following sections already exist and were skipped:\\n" . implode(", ", $duplicate_sections);
                    header("Location: section_input.php");
                }
            } else {
                $message = 'Error adding sections. Check logs.';
            }

            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var modal = new bootstrap.Modal(document.getElementById('successModal'));
                        document.getElementById('successMessage').textContent = `$message`;
                        modal.show();
                    });
                  </script>";

            $insert_stmt->close();
            $check_stmt->close();
        } elseif ($action === 'update' && !empty($section_id)) {
            // Assuming you're using section_id to fetch the original section code
            $query = "SELECT section_code FROM tbl_section WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $section_id);
            $stmt->execute();
            $stmt->bind_result($existing_section_code);
            $stmt->fetch();
            $stmt->close();

            $original_section_code = strtoupper($existing_section_code);


            if (!isset($original_section_code)) {
                $original_section_code = '';
            }

            // Ensure section_code and section_no are in the required format before updating
            $update_sql = "UPDATE tbl_section SET program_code=?, curriculum=?, dept_code=?, section_code=?, year_level=?, section_no=? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssssi", $program_code, $curriculum, $dept_code, $section_code, $year_level, $section_no, $section_id);

            if ($update_stmt->execute()) {
                if ($update_stmt->affected_rows > 0) {
                    $_SESSION['modal_message'] = 'Record Updated Successfully.';
                    header("Location: section_input.php");
                } else {
                    // echo "No rows were updated. Check if the data is the same or if the ID exists.";
                }
            } else {
                echo "Error updating record in tbl_section: " . $update_stmt->error . "<br>";
            }
            $update_stmt->close();

            // echo "Updated Program Code : $program_code<br>";
            // echo "Updated Curriculum : $curriculum<br>";
            // echo "Updated section code : $section_code<br>";
            // echo "Updated section no : $section_no<br>";
            // echo "Updated Year Level: $year_level<br>";

            // Debugging: Check if the section code is correctly set
            // echo "Original Section Code: $original_section_code<br>";

            if (isset($_SESSION['ay_code'])) {
                $ay_code = $_SESSION['ay_code']; // Get the ay_code from session

                $section_code = $program_code . " " . $year_level_int . "-" . $code;
                $section_sched_code = preg_replace("/-/", "_", "{$section_code}_{$ay_code}");
                $old_section_sched_code = preg_replace("/-/", "_", "{$original_section_code}_{$ay_code}");

                // echo "<br>Original Section Code: $original_section_code<br>";
                // echo "Section Code: $section_code<br>";
                // echo "AY Code: $ay_code<br>";
                // echo "Section Schedule Code: $section_sched_code<br>";
                // echo "Old Section Schedule Code: $old_section_sched_code<br>";


                // Function to check if a table exists
                function tableExists($conn, $tableName)
                {
                    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
                    return $result && $result->num_rows > 0;
                }

                // Sanitize the table names
                $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

                // Update section schedule code in the department-specific table
                if (tableExists($conn, $sanitized_section_sched_code)) {
                    $update_sql = "UPDATE $sanitized_section_sched_code SET section_sched_code=? WHERE section_sched_code=? AND semester = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("sss", $section_sched_code, $old_section_sched_code, $semester);

                    if ($update_stmt->execute()) {
                        // Successfully updated section schedule code
                    } else {
                        echo "Error updating section schedule code for AY code $ay_code: " . $update_stmt->error . "<br>";
                    }
                    $update_stmt->close();
                }


                // Loop through each table and perform the update if they exist
                $tables = [$sanitized_room_sched_code, $sanitized_prof_sched_code];
                foreach ($tables as $table) {
                    if (tableExists($conn, $table)) {
                        $update_sql = "UPDATE $table SET section_code=? WHERE section_code=? AND semester = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("sss", $section_sched_code, $old_section_sched_code, $semester);

                        if (!$update_stmt->execute()) {
                            echo "Error updating section_sched_code for table $table: " . $update_stmt->error . "<br>";
                        } else {
                            // Successfully updated section code in the table
                        }
                        $update_stmt->close();
                    }
                }

                // Update other relevant fields in tbl_secschedlist
                $update_sql = "UPDATE tbl_secschedlist SET program_code=?, section_code=?, section_sched_code=?, dept_code=? WHERE section_code=? AND ay_code=?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssssss", $program_code, $section_code, $section_sched_code, $dept_code, $original_section_code, $ay_code);

                if (!$update_stmt->execute()) {
                    echo "Error updating tbl_secschedlist for AY code $ay_code: " . $update_stmt->error . "<br>";
                }
                $update_stmt->close();

                // Update relevant fields in tbl_notifications
                $update_sql = "UPDATE tbl_notifications SET section_code = ?, section_sched_code = ? WHERE section_code = ? AND semester = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $section_code, $section_sched_code, $original_section_code, $semester);

                if ($update_stmt->execute()) {
                    // echo "Updated tbl_notifications successfully for AY code $ay_code.<br>";
                } else {
                    echo "Error updating tbl_notifications for AY code $ay_code: " . $update_stmt->error . "<br>";
                }

                $update_stmt->close();

                // Update relevant fields in tbl_notifications
                $update_sql = "UPDATE tbl_stud_prof_notif SET sec_ro_prof_code = ?, sched_code = ? WHERE sec_ro_prof_code = ? AND semester = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $section_code, $section_sched_code, $original_section_code, $semester);

                if ($update_stmt->execute()) {
                    // echo "Updated tbl_notifications successfully for AY code $ay_code.<br>";
                } else {
                    echo "Error updating tbl_notifications for AY code $ay_code: " . $update_stmt->error . "<br>";
                }

                $update_stmt->close();


                $update_sql = "UPDATE tbl_schedstatus SET section_sched_code=?, dept_code=? WHERE section_sched_code=? AND semester=?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $section_sched_code, $dept_code, $old_section_sched_code, $semester);

                if (!$update_stmt->execute()) {
                    echo "Error updating tbl_schedstatus for AY code $ay_code: " . $update_stmt->error . "<br>";
                }
                $update_stmt->close();

                // Check if the original_section_code exists in tbl_shared_sched and update if necessary
                $check_sql = "SELECT receiver_dept_code FROM tbl_shared_sched WHERE section_code=?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $original_section_code);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $row = $check_result->fetch_assoc();
                    $receiver_dept_code = $row['receiver_dept_code'];

                    // Update the relevant tables if receiver_dept_code exists
                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$receiver_dept_code}_{$ay_code}");

                    // Loop through and update the section schedule for the receiver department
                    if (tableExists($conn, $sanitized_section_sched_code)) {
                        $update_sql = "UPDATE $sanitized_section_sched_code SET section_sched_code=? WHERE section_sched_code=? AND semester=?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param("sss", $section_sched_code, $old_section_sched_code, $semester);

                        if (!$update_stmt->execute()) {
                            echo "Error updating section schedule for receiver department: " . $update_stmt->error . "<br>";
                        }
                        $update_stmt->close();
                    }

                    // Loop through and update other relevant tables for the receiver department
                    $tables = [$sanitized_room_sched_code, $sanitized_prof_sched_code];
                    foreach ($tables as $table) {
                        if (tableExists($conn, $table)) {
                            $update_sql = "UPDATE $table SET section_code=? WHERE section_code=? AND semester=? AND ay_code=?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("ssss", $section_sched_code, $old_section_sched_code, $semester, $ay_code);

                            if (!$update_stmt->execute()) {
                                echo "Error updating section schedule code for table $table: " . $update_stmt->error . "<br>";
                            }
                            $update_stmt->close();
                        }
                    }
                }
                $check_stmt->close();

                // Update the tbl_shared_sched for the receiver department
                $update_sql = "UPDATE tbl_shared_sched SET shared_section=?, section_code=? WHERE section_code=?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sss", $section_sched_code, $section_code, $original_section_code);

                if (!$update_stmt->execute()) {
                    echo "Error updating tbl_shared_sched: " . $update_stmt->error . "<br>";
                }
                $update_stmt->close();
            }
        } elseif ($action === "delete") {
            $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
            $section_sched_code = $section_code . "_" . $ay_code;
            // echo "Checking for section schedule table: $sanitized_section_sched_code<br>";

            // Check if the table exists
            $check_table_sql = "SHOW TABLES LIKE '$sanitized_section_sched_code'";
            $check_table_result = $conn->query($check_table_sql);

            if ($check_table_result && $check_table_result->num_rows > 0) {
                // echo "Table exists: $sanitized_section_sched_code<br>";

                // Retrieve section_sched_code values
                $debug_sql = "SELECT section_sched_code FROM `$sanitized_section_sched_code`";
                $debug_result = $conn->query($debug_sql);

                if ($debug_result) {
                    while ($row = $debug_result->fetch_assoc()) {
                        $full_section_sched_code = $row['section_sched_code'];

                        // Extract section_code from section_sched_code
                        $parts = explode("_", $full_section_sched_code);
                        if (count($parts) >= 2) {
                            $extracted_section_code = $parts[0] . "-" . str_replace("_", "-", $parts[1]); // Convert "4_1" to "4-1"
                        } else {
                            $extracted_section_code = $parts[0]; // Fallback
                        }

                        // If a match is found, prevent deletion and show the modal
                        if ($extracted_section_code === $section_code) {
                            $_SESSION['modal_message'] = 'Deletion not Allowed: There is a schedule plotted to this Section.';
                            header("Location: section_input.php");
                            return; // Stop execution to prevent deletion
                        }
                    }
                } else {
                    echo "Failed to retrieve section_sched_code values.<br>";
                }
            }

            // If no match was found, proceed with deletion
            // echo "No matching section_code found. Proceeding with deletion...<br>";

            $delete_sql = "DELETE FROM tbl_section WHERE section_code = ? AND dept_code = ? AND ay_code = ? AND semester = ?";
            $delete_stmt = $conn->prepare($delete_sql);

            if ($delete_stmt) {
                $delete_stmt->bind_param("ssss", $section_code, $dept_code, $ay_code, $semester);

                if ($delete_stmt->execute()) {
                    $_SESSION['modal_message'] = 'Record successfully deleted';
                    header("Location: section_input.php");
                } else {
                    echo "ERROR: Deletion failed - " . $delete_stmt->error . "<br>";
                }
            } else {
                echo "ERROR: Failed to prepare DELETE statement.<br>";
            }
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Section Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">

    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/section_input.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>


    <section class="section-input">

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="program-tab" href="program_input.php" aria-controls="program" aria-selected="false">Program Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="course-tab" href="course_input.php" aria-controls="course" aria-selected="false">Checklist Input</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="section-tab" href="section_input.php" aria-controls="section" aria-selected="true">Section Input</a>
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
                <input type="hidden" name="filter" value="true">
                <div class="filtering d-flex flex-wrap justify-content-center">
                    <div class="form-group col-md-3">
                        <select name="program_code" id="filter_program_code" class="form-control" style="color: #6c757d;">
                            <option value="" disabled selected style="color: #6c757d;">Filter Program Code</option>
                            <option value="">All</option>
                            <?php
                            // Fetch distinct program codes for the dropdown
                            $sql = "SELECT DISTINCT program_code FROM tbl_section WHERE dept_code = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("s", $dept_code);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $selected = (isset($_GET['program_code']) && $_GET['program_code'] == $row["program_code"]) ? "selected" : "";
                                    echo "<option value='" . htmlspecialchars($row["program_code"]) . "' $selected>" . htmlspecialchars($row["program_code"]) . "</option>";
                                }
                            } else {
                                echo "<option value=\"\" disabled>No program codes available</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <select name="curriculum" id="filter_curriculum" class="form-control" style="color: #6c757d;">
                            <option value="" disabled selected style="color: #6c757d;">Filter Curriculum</option>
                            <option value="">All</option>
                            <?php
                            $curriculumQuery = "SELECT DISTINCT curriculum FROM tbl_section";
                            $curriculumResult = $conn->query($curriculumQuery);
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
                    <div class="form-group col-md-3">
                        <input type="text" name="section_code" id="section_code" class="form-control" placeholder="Enter Section" value="<?php echo isset($_GET['section_code']) ? htmlspecialchars($_GET['section_code']) : ''; ?>">
                    </div>
                    <div class="form-group col-md-2">
                        <button type="submit" class="btn w-100" style="border: none;">Search</button>
                    </div>
                </div>
            </form>
        </div>

        <script>
            // JavaScript to hide columns dynamically
            document.addEventListener("DOMContentLoaded", function() {
                const urlParams = new URLSearchParams(window.location.search);
                const filterProgram = urlParams.get("program_code");
                const filterCurriculum = urlParams.get("curriculum");
                const filterSectionCode = urlParams.get("section_code");

                if (filterProgram) {
                    document.querySelectorAll(".program-column").forEach(col => col.style.display = "none");
                }
                if (filterCurriculum) {
                    document.querySelectorAll(".curriculum-column").forEach(col => col.style.display = "none");
                }
                if (filterSectionCode) {
                    document.querySelectorAll(".section-column").forEach(col => col.style.display = "none");
                }
            });
        </script>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="title">
                    <h5>Section Input</h5>
                </div>
                <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" id="sectionForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                    <input type="hidden" id="section_id" name="section_id" value="<?php echo $last_inserted_id; ?>" readonly>

                    <?php
                    // Fetch unique program_code, num_year, and curriculum values from the database
                    $query = "SELECT DISTINCT program_code, num_year, curriculum FROM tbl_program WHERE dept_code = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("s", $dept_code);
                    $stmt->execute();
                    $program_result = $stmt->get_result();
                    $programs = [];

                    if ($program_result->num_rows > 0) {
                        while ($row = $program_result->fetch_assoc()) {
                            $programs[] = $row; // Store unique program_code, num_year, and curriculum
                        }
                    }

                    // Initialize variables for search criteria (if provided via GET/POST)
                    $search_program_code = isset($_GET['program_code']) ? $_GET['program_code'] : '';
                    $search_curriculum = isset($_GET['curriculum']) ? $_GET['curriculum'] : '';
                    ?>

                    <div class="mt-4">
                        <select class="form-control w-100" name="program_code" id="program_code" style="color: #6c757d;" required>
                            <option value="" disabled selected>Program Code</option>
                            <?php
                            // Display unique program_code in the dropdown
                            foreach (array_unique(array_column($programs, 'program_code')) as $program_code): ?>
                                    <option value="<?php echo htmlspecialchars($program_code); ?>"
                                        <?php if ($program_code == $search_program_code)
                                            echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($program_code); ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mt-4">
                        <select class="form-control w-100" name="curriculum" id="curriculum" style="color: #6c757d;" required>
                            <option value="" disabled selected>Curriculum</option>
                        </select>
                    </div>

                    <div class="mt-4">
                        <select class="form-control" id="year_level" name="year_level" style="color: #6c757d;" required>
                            <option value="" disabled selected>Year Level</option>
                        </select>
                        <input type="hidden" id="original_section_code" name="original_section_code"
                            value="<?php echo isset($_POST['original_section_code']) ? htmlspecialchars($_POST['original_section_code']) : ''; ?>">
                    </div>


<div class="mt-3" id="petition_container" style="display: none;">
  <input type="checkbox" id="petition_checkbox" name="petition" >
  <label for="petition_checkbox">Is this for petition?</label>
</div>

                    <script>
                        const programs = <?php echo json_encode($programs); ?>;

                        function getSuffix(num) {
                            const lastDigit = num % 10;
                            const lastTwoDigits = num % 100;

                            if (lastDigit === 1 && lastTwoDigits !== 11) {
                                return 'st';
                            } else if (lastDigit === 2 && lastTwoDigits !== 12) {
                                return 'nd';
                            } else if (lastDigit === 3 && lastTwoDigits !== 13) {
                                return 'rd';
                            } else {
                                return 'th';
                            }
                        }

                        function populateYearLevels(selectedProgramCode, selectedCurriculum) {
                            const yearLevelDropdown = document.getElementById('year_level');
                            yearLevelDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>';

                            const filteredPrograms = programs.filter(program =>
                                program.program_code === selectedProgramCode && program.curriculum === selectedCurriculum
                            );

                            if (filteredPrograms.length > 0) {
                                const numYears = filteredPrograms[0].num_year;

                                for (let i = 1; i <= numYears; i++) {
                                    const suffix = getSuffix(i);
                                    const yearLevelText = `${i}${suffix} Year`;
                                    yearLevelDropdown.innerHTML += `<option value="${yearLevelText}">${yearLevelText}</option>`;
                                }
                            }
                        }

                        document.getElementById('program_code').addEventListener('change', function() {
                            const selectedProgramCode = this.value;
                            const curriculumDropdown = document.getElementById('curriculum');

                            curriculumDropdown.innerHTML = '<option value="" disabled selected>Curriculum</option>';

                            const selectedPrograms = programs.filter(program => program.program_code === selectedProgramCode);
                            selectedPrograms.forEach(program => {
                                curriculumDropdown.innerHTML += `<option value="${program.curriculum}">${program.curriculum}</option>`;
                            });

                            const selectedCurriculum = curriculumDropdown.value;
                            if (selectedCurriculum) {
                                populateYearLevels(selectedProgramCode, selectedCurriculum);
                            }
                        });

                        document.getElementById('curriculum').addEventListener('change', function() {
                            const selectedProgramCode = document.getElementById('program_code').value;
                            const selectedCurriculum = this.value;

                            if (selectedProgramCode && selectedCurriculum) {
                                populateYearLevels(selectedProgramCode, selectedCurriculum);
                            }
                        });

                        document.getElementById('year_level').addEventListener('change', function() {
                            const selectedYear = this.value;
                            const allOptions = this.options;
                            const lastYearOption = allOptions[allOptions.length - 1].value;
                            const petitionContainer = document.getElementById('petition_container');

                            console.log('Selected Year:', selectedYear);
                            console.log('Last Year Option:', lastYearOption);

                            if (selectedYear === lastYearOption) {
                                petitionContainer.style.display = 'block';
                            } else {
                                petitionContainer.style.display = 'none';
                            }
                        });
                    </script>

                  <!-- For petition section (single section) -->
<div class="mt-4" id="section_input" style="display: none;">
    <input type="text" class="form-control" name="single_section_no" autocomplete="off" placeholder="Section">
</div>

<!-- For non-petition section (multiple sections) -->
<div class="mt-4" id="number_of_section">
    <input type="number" class="form-control" name="section_no" autocomplete="off" placeholder="Number of Sections">
</div>

                    <div class="button-group">
                        <button type="submit" name="action" value="add" class="btn btn-add">Add</button>
                        <div class="btn-inline-group">
                            <!-- <button type="submit" name="action" value="update" class="btn btn-primary btn-update-delete" style="display: none;">Update</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-update-delete" style="display: none;">Delete</button> -->
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-8 mb-4">
                <div class="table-wrapper">
                    <table class="table" id="sectionTable">
                        <thead>
                            <tr>
                                <th class="program-column">Program Code </th>
                                <th class="section-column">Section Code</th>
                                <th class="year-column">Year Level</th>
                                <th class="curriculum-column">Curriculum</th>
                                <th class="petition-column">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Check if the session variables for ay_code and semester are set
                            if (isset($_SESSION['ay_code']) && isset($_SESSION['semester'])) {
                                $ay_code = $_SESSION['ay_code'];
                                $semester = $_SESSION['semester'];
                                $dept_code = $_SESSION['dept_code'];

                                // Initialize filter variables with empty values
                                $filterProgram = '';
                                $filterCurriculum = '';
                                $filterSectionCode = '';

                                // Check if the filter form has been submitted
                                if (isset($_GET['filter']) && $_GET['filter'] == 'true') {
                                    // Get filter values from form submission (if any)
                                    $filterProgram = isset($_GET['program_code']) ? $_GET['program_code'] : '';
                                    $filterCurriculum = isset($_GET['curriculum']) ? $_GET['curriculum'] : '';
                                    $filterSectionCode = isset($_GET['section_code']) ? $_GET['section_code'] : '';
                                }

                                // Prepare the SQL query with optional filters
                                $sql = "SELECT * FROM tbl_section WHERE semester = ? AND ay_code = ? AND dept_code = ?";
                                $params = [$semester, $ay_code, $dept_code];
                                $types = "sss";

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

                                if (!empty($filterSectionCode)) {
                                    $sql .= " AND section_no LIKE ?";
                                    $params[] = "%" . $filterSectionCode . "%";
                                    $types .= "s";
                                }

                                if ($stmt = $conn->prepare($sql)) {
                                    $stmt->bind_param($types, ...$params);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr onclick=\"fillForm('" . $row["id"] . "', '" . $row["program_code"] . "', '" . $row["section_code"] . "', '" . $row["year_level"] . "', '" . $row["curriculum"] . "', '" . $row["petition"] . "')\">
                                                <td class=\"program-column\">" . htmlspecialchars($row["program_code"]) . "</td>
                                                <td class=\"section-column\">" . htmlspecialchars($row["section_code"]) . "</td>
                                                <td class=\"year-column\">" . htmlspecialchars($row["year_level"]) . "</td>
                                                <td class=\"curriculum-column\">" . htmlspecialchars($row["curriculum"]) . "</td>
                                                <td class=\"petition-column\">" . ($row["petition"] == 1 ? "Petition" : ($row["petition"] == 0 ? " " : "")) . "</td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' style='text-align:center;'>No records found</td></tr>";
                                    }

                                    $stmt->close();
                                } else {
                                    echo "Error preparing statement: " . $conn->error;
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        let selectedRow = null;

        function getSuffix(num) {
            if (num === 1) return 'st';
            if (num === 2) return 'nd';
            if (num === 3) return 'rd';
            return 'th';
        }

        function populateYearLevels(selectedProgramCode, selectedCurriculum, selectedYearLevel = '') {
            const yearLevelDropdown = document.getElementById('year_level');
            yearLevelDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>';

            const filteredPrograms = programs.filter(program =>
                program.program_code === selectedProgramCode && program.curriculum === selectedCurriculum
            );

            if (filteredPrograms.length > 0) {
                const numYears = filteredPrograms[0].num_year;

                for (let i = 1; i <= numYears; i++) {
                    const suffix = getSuffix(i);
                    const yearLevelText = `${i}${suffix} Year`;
                    yearLevelDropdown.innerHTML += `<option value="${yearLevelText}">${yearLevelText}</option>`;
                }

                // Set year level after populating options
                if (selectedYearLevel) {
                    yearLevelDropdown.value = selectedYearLevel;
                }
            }
        }

        function fillForm(sectionId, program_code, section_code, year_level, curriculum, petition, section_no) {
            document.getElementById('section_id').value = sectionId;
            document.getElementById('program_code').value = program_code;

            const curriculumDropdown = document.getElementById('curriculum');
            curriculumDropdown.innerHTML = '<option value="" disabled selected>Curriculum</option>';
            const selectedPrograms = programs.filter(program => program.program_code === program_code);

            selectedPrograms.forEach(program => {
                curriculumDropdown.innerHTML += `<option value="${program.curriculum}">${program.curriculum}</option>`;
            });

            curriculumDropdown.value = curriculum || '';

            populateYearLevels(program_code, curriculum, year_level);

            const code = section_code.split('-').pop().trim();
            document.getElementById('section_no').value = code;

            document.querySelector('.btn-add').style.display = 'none';
            document.querySelectorAll('.btn-update-delete').forEach(button => {
                button.style.display = 'inline-block';
            });

            const petitionContainer = document.getElementById('petition_container');
            const petitionCheckbox = document.getElementById('petition_checkbox');

            if (petition === '1') {
                petitionContainer.style.display = 'block';
                petitionCheckbox.checked = true;
                document.getElementById('section_input').style.display = 'block';
                document.getElementById('number_of_section').style.display = 'none';
            } else {
                petitionContainer.style.display = 'none';
                petitionCheckbox.checked = false;
                document.getElementById('section_input').style.display = 'none';
                document.getElementById('number_of_section').style.display = 'block';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('curriculum').addEventListener('change', function() {
                const selectedProgramCode = document.getElementById('program_code').value;
                const selectedCurriculum = this.value;
                populateYearLevels(selectedProgramCode, selectedCurriculum);
            });

            document.getElementById('petition_checkbox').addEventListener('change', function() {
                const isChecked = this.checked;
                document.getElementById('section_input').style.display = isChecked ? 'block' : 'none';
                document.getElementById('number_of_section').style.display = isChecked ? 'none' : 'block';
            });

            // Handle row click in the table
            document.querySelectorAll('.table tbody tr').forEach(row => {
                row.addEventListener('click', function() {
                    const sectionId = this.children[0].textContent.trim();
                    const programCode = this.children[1].textContent.trim();
                    const sectionNo = this.children[2].textContent.trim();
                    const yearLevel = this.children[3].textContent.trim();
                    const curriculum = this.children[4].textContent.trim();
                    const petition = this.children[5].textContent.trim();

                    fillForm(sectionId, programCode, sectionNo, yearLevel, curriculum, petition);

                    if (selectedRow) {
                        selectedRow.classList.remove('clicked-row');
                    }
                    selectedRow = this;
                    selectedRow.classList.add('clicked-row');
                });
            });

            // Clear form when clicking outside table and form
            document.addEventListener('click', function(event) {
                const sectionForm = document.querySelector('form');
                const table = document.querySelector('table');

                if (!sectionForm.contains(event.target) && !table.contains(event.target)) {
                    sectionForm.reset();

                    document.getElementById('program_code').value = '';
                    document.getElementById('curriculum').innerHTML = '<option value="" disabled selected>Curriculum</option>';
                    document.getElementById('year_level').innerHTML = '<option value="" disabled selected>Year Level</option>';
                    document.getElementById('section_no').value = '';

                    document.getElementById('petition_container').style.display = 'none';
                    document.getElementById('petition_checkbox').checked = false;

                    document.querySelector('.btn-add').style.display = 'inline-block';
                    document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'none');

                    document.querySelectorAll('.row-checkbox').forEach(checkbox => {
                        checkbox.style.display = 'none';
                    });

                    if (selectedRow) {
                        selectedRow.classList.remove('clicked-row');
                        selectedRow = null;
                    }
                }
            });
        });



        // To make sure the inputs inside the form are not cleared when clicking inside
        sectionForm.addEventListener('click', function(event) {
            event.stopPropagation(); // This prevents the click event from propagating to the document

        });
        // AJAX request to update the record without reloading the page
        document.querySelector('#updateButton').addEventListener('click', function(event) {
            event.preventDefault(); // Prevent form submission

            let formData = new FormData(document.querySelector('form')); // Get the form data

            // Send the form data to the server using AJAX
            fetch('section_input.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(result => {
                    // Handle the response from the server (e.g., update table rows or show a message)
                    alert('Record updated successfully');

                    // Optionally, update the table with the latest data or reset filters
                    loadTableData();
                })
                .catch(error => console.error('Error:', error));
        });
    </script>


</body>

</html>