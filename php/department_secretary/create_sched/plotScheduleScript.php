<?php

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head' && $_SESSION['user_type'] != 'Department Chairperson')) {
    header("Location: ./login/login.php");
    exit();
}

// Get the current user's first name and department code from the session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
// $user_dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
$section_sched_code = isset($_SESSION['section_sched_code']) ? $_SESSION['section_sched_code'] : '';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "schedsys";


// Create connection

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$fetch_info_query = "SELECT dept_code,status,college_code FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $section_dept_code = $row['dept_code'];
    $sched_status = $row['status'];
    $section_college_code = $row['college_code'];
    echo "<input type='hidden' id='college_code' value='" . htmlspecialchars($college_code) . "'>";
} else {
    echo "Dept: Error: No matching section schedule found for code '$section_sched_code'.";
}

$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$section_college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}

$fetch_info_query = "SELECT reg_adviser, college_code,dept_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];
    $user_dept_code = $row['dept_code'];


    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser";
    } else {
        $current_user_type = $user_type;
    }
}




$fetch_info_query_col = "SELECT college_code,dept_code FROM tbl_prof_acc WHERE user_type = 'CCL Head'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $ccl_college_code = $row_col['college_code'];
    // $ccl_dept_code = $row_col['dept_code'];

}

if (empty($ccl_college_code)) {
    $ccl_college_code = $college_code;
    // $ccl_dept_code = $user_dept_code;
}


$fetch_info_query = "SELECT cell_color,status FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $color = $row['cell_color'] ?? '#FFFFFF';
} else {
    die("Error: No matching section schedule found for code '$section_sched_code'.");
}


$prof_type = 'Job Order';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['plot_schedule'])) {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $section_sched_code = $_POST['section_sched_code'];
    $sec_sched_id = $_POST['sec_sched_id'];
    $day = $_POST['day'];
    $time_start = isset($_POST['time_start']) ? $_POST['time_start'] : '';
    $time_end = isset($_POST['time_end']) ? $_POST['time_end'] : '';
    $course_code = strtoupper($_POST['course_code'] ?? '');
    $room_code = strtoupper($_POST['room_code']);
    $prof_code = strtoupper($_POST['prof_code']);
    $class_type = $_POST['class_type'] ?? 'n/a';
    $room_type = '';
    $prof_name = null;
    $room_sched_code = '';
    $invalid_fields = [];
    $computer_room = '';
    $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $user_dept_code . "_" . $ay_code);

    $lec_hrs = null;
    $lab_hrs = null;
    if (empty($section_sched_code) || empty($semester) || empty($day) || empty($time_start) || empty($time_end) || empty($course_code) || empty($class_type)) {
        $invalid_fields[] = "All fields are required";
    } else {
        $fetch_info_query = "SELECT dept_code,section_code,program_code,college_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code' AND ay_code = '$ay_code'";
        $result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dept_code = $row['dept_code'];
            $section_code = $row['section_code'];
            $program_code = $row['program_code'];
        } else {
            die("Error: No matching section schedule found for code '$section_sched_code'.");
        }

        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");


        $curriculum_check_query = "SELECT curriculum FROM tbl_section 
        WHERE section_code = '$section_code' 
        AND dept_code = '$dept_code'";
        $curriculum_result = $conn->query($curriculum_check_query);

        $section_curriculum = ''; // Initialize to store the curriculum type
        if ($curriculum_result->num_rows > 0) {
            $curriculum_row = $curriculum_result->fetch_assoc();
            $section_curriculum = $curriculum_row['curriculum'];
        }

        $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ?";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("ss", $section_code, $dept_code);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();

        $time_start_dt = new DateTime($time_start);
        $time_end_dt = new DateTime($time_end);
        $duration = $time_start_dt->diff($time_end_dt);
        $duration_hours = $duration->h + ($duration->i / 60);

        $sql = "SELECT lec_hrs, lab_hrs, allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $course_code, $program_code, $year_level);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lec_hrs = $row['lec_hrs'];
            $lab_hrs = $row['lab_hrs'];
            $computer_room = $row['computer_room'];
        }

        $sql_check_schedule = "SELECT class_type, time_start, time_end 
        FROM {$sanitized_dept_code} 
        WHERE course_code = ? 
        AND section_sched_code = ? 
        AND semester = ?";
        $stmt_check = $conn->prepare($sql_check_schedule);
        $stmt_check->bind_param("sss", $course_code, $section_sched_code, $semester);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        $plotted_lec_hours = 0;
        $plotted_lab_hours = 0;
        // Accumulate total plotted hours from the database
        if ($result_check->num_rows > 0) {
            while ($row_check = $result_check->fetch_assoc()) {
                $old_time_start_dt = new DateTime($row_check['time_start']);
                $old_time_end_dt = new DateTime($row_check['time_end']);
                $old_duration = $old_time_start_dt->diff($old_time_end_dt);
                $old_duration_hours = $old_duration->h + ($old_duration->i / 60);

                if ($row_check['class_type'] === 'lec') {
                    $plotted_lec_hours += $old_duration_hours; // Accumulate lecture hours
                } elseif ($row_check['class_type'] === 'lab') {
                    $plotted_lab_hours += $old_duration_hours; // Accumulate laboratory hours
                }
            }
        } else {
            // No previous schedules exist for this course
            $plotted_lec_hours = 0;
            $plotted_lab_hours = 0;
        }

        // Close the statement
        $stmt_check->close();


        if ($class_type === 'lec') {
            $plotted_lec_hours += $duration_hours; // Add new lecture hours
        } elseif ($class_type === 'lab') {
            $plotted_lab_hours += $duration_hours; // Add new laboratory hours
        }

        // // Optional: Debugging output


        // Check if the course's lecture or laboratory hours have been met


        if ($lec_hrs == 0 && $lab_hrs == 0) {
            $check_course = "SELECT * 
                             FROM $sanitized_dept_code
                             WHERE course_code = '$course_code' AND semester = '$semester' AND section_sched_code = '$section_sched_code'";
            $result_course = $conn->query($check_course);

            if ($result_course && $result_course->num_rows == 0) {
                if ($plotted_lec_hours > 1 || $plotted_lab_hours > 1) {
                    if ($class_type === 'lec') {
                        $invalid_fields[] = "Lecture hours have already been met.";
                    } elseif ($class_type === 'lab') {
                        $invalid_fields[] = "Laboratory hours have already been met.";
                    } else {
                        $invalid_fields[] = "Invalid class type.";
                    }
                }
            } else {
                if ($class_type === 'lec') {
                    $invalid_fields[] = "Lecture hours have already been met.";
                } elseif ($class_type === 'lab') {
                    $invalid_fields[] = "Laboratory hours have already been met.";
                } else {
                    $invalid_fields[] = "Invalid class type.";
                }
            }
        } else {

            if ($plotted_lec_hours > $lec_hrs || $plotted_lab_hours > $lab_hrs) {

                if ($class_type === 'lec') {
                    $invalid_fields[] = "Lecture hours have already been met.";
                } else {
                    $invalid_fields[] = "Laboratory hours have already been met.";
                }
            } else {
                if (empty($class_type) && $lec_hour == 0 && $lab_hrs == 0) {
                    $invalid_fields[] = "No Subject type";
                }
            }


        }


        // echo $lec_hrs.$lab_hrs.$plotted_lec_hours.$course_code.$semester.$section_sched_code;


        if ($time_end_dt <= $time_start_dt) {
            $invalid_fields[] = "Invalid time range: End time (" . $time_end_dt->format('H:i') . ") cannot be earlier than or the same as start time (" . $time_start_dt->format('H:i') . ").";
        }

        if ($user_type === 'CCL Head') {
            // CCL Head: Select courses with 'lecR&labR' and 'labR'
            $course_dept_code = $dept_code;
        } elseif ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
            // Department Secretary: Select courses with 'lecR&labR' and 'lecR'
            $course_dept_code = $user_dept_code;
        }



        // Check if the course exists
        $fetch_course_query = "SELECT * FROM tbl_course WHERE dept_code = '$course_dept_code' AND course_code = '$course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
        $result_course = $conn->query($fetch_course_query);
        if ($result_course->num_rows === 0) {
            $invalid_fields[] = "Course does not exist";
        }

        // Check if the room exists
        if ($section_college_code === $college_code) {
            if (!empty($room_code)) {
                if ($computer_room == 1 && $class_type == 'lab') {
                    $fetch_room_query = "SELECT * FROM tbl_room WHERE room_code = '$room_code' AND room_type = 'Computer Laboratory'";
                    $result_room = $conn->query($fetch_room_query);
                    if ($result_room->num_rows === 0) {
                        $invalid_fields[] = "Room does not exist";
                    }
                } else {
                    $fetch_room_query = "SELECT * FROM tbl_room WHERE dept_code = '$user_dept_code' AND room_code = '$room_code'";
                    $result_room = $conn->query($fetch_room_query);
                    if ($result_room->num_rows === 0) {
                        $invalid_fields[] = "Room does not exist";
                    }
                }
            }
        }


        if ($section_college_code === $college_code) {
            // Check if the professor exists
            if (!empty($prof_code)) {
                $fetch_prof_query = "SELECT * FROM tbl_prof WHERE dept_code = '$user_dept_code' AND  prof_code = '$prof_code' AND acc_status = '1' AND ay_code = '$ay_code' AND semester = '$semester'";
                $result_prof = $conn->query($fetch_prof_query);
                if ($result_prof->num_rows === 0) {
                    $invalid_fields[] = "Professor does not exist";
                }
            }
        }
    }
    if (!empty($invalid_fields)) {
        // Output conflicts list in a modal using JavaScript
        echo "<script type='text/javascript'>
            document.addEventListener('DOMContentLoaded', function() {
                var conflictList = document.getElementById('conflictList');";

        foreach ($invalid_fields as $invalid) {
            // Safely handle quotes to prevent JS errors
            $safe_invalid = htmlspecialchars($invalid, ENT_QUOTES, 'UTF-8');
            echo "var li = document.createElement('li');
              li.textContent = '$safe_invalid';
              conflictList.appendChild(li);";
        }

        echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
          myModal.show();
        });
    </script>";
    } else {
        // Fetch dept_code and ay_code
        $fetch_info_query = "SELECT dept_code, ay_code,section_code,program_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
        $result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $dept_code = $row['dept_code'];
            $ay_code = $row['ay_code'];
            $section_code = $row['section_code'];
            $program_code = $row['program_code'];
        } else {
            die("Error: No matching section schedule found for code '$section_sched_code'.");
        }

        // Check section conflict
        $conflicts = [];
        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
        $sql_section_conflict = "SELECT * FROM $sanitized_dept_code 
            WHERE day='$day' AND semester='$semester' AND ay_code = '$ay_code' AND section_sched_code = '$section_sched_code'
            AND ((time_start <= '$time_start' AND time_end > '$time_start') OR 
            (time_start < '$time_end' AND time_end >= '$time_end') OR 
            (time_start >= '$time_start' AND time_end <= '$time_end'))";
        $result_section_conflict = $conn->query($sql_section_conflict);

        if ($result_section_conflict->num_rows > 0) {
            $conflicts[] = "Schedule conflict detected in section schedule.";
        }

        // Check conflicts only if room_code and prof_code are not 'TBA'
        if ($section_college_code === $college_code) {
            if (!empty($room_code)) {
                if ($computer_room == 1 && $user_type == "CCL Head") {
                    $plot_room_dept_code = $ccl_college_code;
                } else {
                    $plot_room_dept_code = $user_dept_code;
                }

                $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $plot_room_dept_code . "_" . $ay_code);

                $sql_room_conflict = "SELECT * FROM $sanitized_room_dept_code 
                WHERE day='$day' AND semester='$semester'AND ay_code='$ay_code' AND room_code = '$room_code'
                AND ((time_start <= '$time_start' AND time_end > '$time_start') OR 
                (time_start < '$time_end' AND time_end >= '$time_end')) ";
                $result_room_conflict = $conn->query($sql_room_conflict);


                if ($result_room_conflict->num_rows > 0) {
                    $conflicts[] = "Schedule conflict detected in room schedule.";
                }
            }

            if (!empty($prof_code)) {
                // echo "Department Code: " . $user_dept_code . "<br>";
                // echo "Curriculum: " . $section_curriculum . "<br>";
                // echo "Course Code: " . $course_code . "<br>";
                // echo "Semester: " . $semester . "<br>";
                // echo "Program Code: " . $program_code . "<br>";
                // echo "Year Level: " . $year_level . "<br>";

                $fetch_info_query = "SELECT * FROM tbl_course WHERE dept_code = '$user_dept_code' AND  curriculum = '$section_curriculum' AND course_code = '$course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
                $result = $conn->query($fetch_info_query);

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $lec_hrs = $row['lec_hrs'];
                    $lab_hrs = $row['lab_hrs'];
                } else {
                    die("Error: No matching course found for code '$section_sched_code'.");
                }

                // Initialize the $teaching_hrs variable correctly
                if ($class_type === 'lec') {
                    $teaching_hrs = $lec_hrs; // Use '=' for assignment
                } else {
                    $teaching_hrs = $lab_hrs; // Use '=' for assignment
                }

                $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $user_dept_code . "_" . $ay_code);

                $sql_prof_conflict = "SELECT * FROM $sanitized_prof_dept_code 
                WHERE day='$day' AND semester='$semester' AND dept_code = '$user_dept_code' AND ay_code='$ay_code' AND prof_code = '$prof_code'
                AND ((time_start <= '$time_start' AND time_end > '$time_start') OR 
                (time_start < '$time_end' AND time_end >= '$time_end'))";
                $result_prof_conflict = $conn->query($sql_prof_conflict);

                if ($result_prof_conflict->num_rows > 0) {
                    $conflicts[] = "Schedule conflict detected in professor schedule.";
                }

                $check_table_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
                $table_result = $conn->query($check_table_sql);

                if ($table_result && $table_result->num_rows > 0) {
                    // Execute the conflict check query if the table exists
                    $conflict_check_sql_pcontact_sched = "SELECT * FROM $sanitized_pcontact_sched_code 
                        WHERE day='$day' AND semester='$semester' AND prof_code = '$prof_code'
                        AND ((time_start <= '$time_start' AND time_end > '$time_start') 
                        OR (time_start < '$time_end' AND time_end >= '$time_end'))";

                    $result_pcontact_sched = $conn->query($conflict_check_sql_pcontact_sched);

                    // Check for conflicts
                    if ($result_pcontact_sched && $result_pcontact_sched->num_rows > 0) {
                        $conflicts[] = "Schedule conflict detected in professor schedule.";
                    }
                }


                $prof_sched_code = $prof_code . "_" . $ay_code;

                // Query to check if a record with the same values already exists
                $select_sql = "SELECT * FROM tbl_psched_counter 
                WHERE prof_sched_code = '$prof_sched_code' 
                AND semester = '$semester' 
                AND dept_code = '$user_dept_code' 
                AND prof_code = '$prof_code'";

                // Execute the query
                $result = $conn->query($select_sql);

                // Check if any rows are returned
                if ($result && $result->num_rows == 0) {
                    // No matching record found, so proceed with the insertion
                    $insert_sql = "INSERT INTO tbl_psched_counter (prof_sched_code, semester, dept_code, prof_code,ay_code) 
                    VALUES ('$prof_sched_code', '$semester', '$user_dept_code', '$prof_code','$ay_code')";

                    // Execute the insertion
                    if ($conn->query($insert_sql) === FALSE) {
                        echo "Error inserting record into tbl_psched_counter: " . $conn->error;
                    }
                } else {
                    // Matching record already exists or there was an error executing the SELECT query
                    if ($result === FALSE) {
                        echo "Error checking for existing record: " . $conn->error;
                    } else {
                        // Optional: You could add a message or log here if a record already exists
                        // echo "Record already exists, not inserting.";
                    }
                }



                $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $prof_code . "_" . $ay_code);

                // Fetch current teaching hours and maximum teaching hours of the professors

            }

        }

        if (!empty($conflicts)) {
            echo "<script type='text/javascript'>
                        document.addEventListener('DOMContentLoaded', function() {
                            var conflictList = document.getElementById('conflictList');";

            foreach ($conflicts as $conflict) {
                echo "var li = document.createElement('li');
                          li.textContent = '$conflict';
                          conflictList.appendChild(li);";
            }

            echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                      myModal.show();
                    });
                </script>";
        } else {
            // Insert into section schedule
            $prof_name = null;
            if ($section_college_code === $college_code) {
                $status = "draft";
                $fetch_prof_name_query = "SELECT prof_name FROM tbl_prof WHERE prof_code = '$prof_code' AND dept_code = '$user_dept_code' AND ay_code = '$ay_code' AND semester = '$semester'";
                $prof_result = $conn->query($fetch_prof_name_query);
                if ($prof_result->num_rows > 0) {
                    $prof_row = $prof_result->fetch_assoc();
                    $prof_name = $prof_row['prof_name'];
                }
            } else {
                $prof_name = strtoupper($_POST['prof_code']);
            }

            if ($user_type == "CCL Head") {
                $plot_dept_code = $section_dept_code;
            } else {
                $plot_dept_code = $user_dept_code;
            }

            $fetch_info_query_room = "SELECT dept_code FROM tbl_room WHERE college_code = '$ccl_college_code' AND room_code = '$room_code' AND room_type = 'Computer Laboratory'";
            $result_room = $conn->query($fetch_info_query_room);
            $room_loc_code = null;

            if ($result_room->num_rows > 0) {
                $row = $result_room->fetch_assoc();
                $room_loc_code = $row['dept_code'] ?? null;
            }


            $insert_sql = "INSERT INTO $sanitized_dept_code (section_sched_code, semester, day,curriculum, time_start, time_end, course_code, room_code, prof_code, prof_name, dept_code,ay_code,class_type,cell_color) 
                               VALUES ('$section_sched_code', '$semester', '$day','$section_curriculum' ,'$time_start', '$time_end', '$course_code', '$room_code', '$prof_code', '$prof_name', '$plot_dept_code','$ay_code','$class_type','$color')";

            if ($conn->query($insert_sql) === TRUE) {
                $sec_sched_id = $conn->insert_id;

                if ($computer_room == 1 && $class_type == 'lab') {
                    $plot_room_dept_code = $user_college_code;
                    $room_dept_code = $room_loc_code;

                } else {
                    $plot_room_dept_code = $user_dept_code;
                    $room_dept_code = $user_dept_code;
                }


                if ($section_college_code === $college_code) {
                    // Insert into room schedule if room_code is not 'TBA'
                    if (!empty($room_code)) {

                        if ($computer_room == 0) {
                            $sql_room = "SELECT room_in_charge, room_type FROM tbl_room WHERE room_code='$room_code' AND dept_code = '$user_dept_code' AND status = 'available'";
                            $result_room = $conn->query($sql_room);
                        } else {
                            $sql_room = "SELECT room_in_charge, room_type FROM tbl_room WHERE room_code='$room_code' AND college_code = '$user_college_code' AND status = 'available'";
                            $result_room = $conn->query($sql_room);
                        }

                        if ($result_room->num_rows > 0) {
                            $row_room = $result_room->fetch_assoc();
                            $room_in_charge = $row_room['room_in_charge'];
                            $room_type = $row_room['room_type'];
                            $room_sched_code = $room_code . "_" . $ay_code;

                        }
                        $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_room_dept_code}_{$ay_code}");

                        $insert_room_sql = "INSERT INTO $sanitized_room_dept_code (sec_sched_id, room_code, room_sched_code, semester, ay_code, room_in_charge, day,curriculum, time_start, time_end, course_code, section_code, prof_code, prof_name, room_type,dept_code,class_type,cell_color) 
                                            VALUES ('$sec_sched_id', '$room_code', '$room_sched_code', '$semester', '$ay_code', '$room_in_charge', '$day','$section_curriculum', '$time_start', '$time_end', '$course_code', '$section_sched_code','$prof_code', '$prof_name', '$room_type','$section_dept_code','$class_type','$color')";
                        if ($conn->query($insert_room_sql) === FALSE) {
                            echo "Error plotting room schedule: " . $conn->error;
                        }

                        /// ROOM draft

                        $checkSql = "SELECT 1 FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
                        if ($checkStmt = $conn->prepare($checkSql)) {
                            $checkStmt->bind_param("ssss", $room_sched_code, $semester, $room_dept_code, $ay_code);
                            $checkStmt->execute();
                            $checkStmt->store_result();

                            if ($checkStmt->num_rows > 0) {
                                $checkStmt->close();
                            } else {
                                // Prepare SQL query
                                $sql = "INSERT INTO tbl_room_schedstatus (room_sched_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?)";

                                // Initialize prepared statement
                                if ($stmt = $conn->prepare($sql)) {
                                    // Bind parameters
                                    $stmt->bind_param("sssss", $room_sched_code, $semester, $room_dept_code, $status, $ay_code);

                                    // Execute query
                                    if ($stmt->execute()) {
                                    } else {
                                        echo "Error: " . $stmt->error;
                                    }

                                    // Close statement
                                    $stmt->close();
                                }
                            }
                        }
                        ///TBL_RSCHED
                        $insert_sql = "INSERT IGNORE INTO tbl_rsched (room_sched_code, dept_code, room_code, room_type, ay_code) 
                        VALUES ('$room_sched_code', '$room_dept_code', '$room_code', '$room_type', '$ay_code')";

                        if ($conn->query($insert_sql) === FALSE) {
                            echo "Error inserting record into rsched: " . $conn->error;
                        }

                    }

                    // Insert into professor schedule if prof_code is not 'TBA'
                    if (!empty($prof_code)) {
                        $prof_sched_code = $prof_code . "_" . $ay_code;
                        $sanitized_prof_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $prof_code . "_" . $ay_code);

                        $fetch_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$user_dept_code'";
                        $prof_hours_result = $conn->query($fetch_prof_hours_query);

                        if ($prof_hours_result->num_rows > 0) {
                            $prof_hours_row = $prof_hours_result->fetch_assoc();
                            $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                            $current_prep_hrs = $prof_hours_row['prep_hrs'];
                            // echo $current_prep_hrs;
                            // $max_teaching_hours = $prof_hours_row['teaching_hrs'];

                            // Check if adding the new schedule exceeds the maximum teaching hours
                            // if ($current_teaching_hours + $duration_hours > $max_teaching_hours) {
                            //     $conflicts[] = "Adding this schedule exceeds the maximum allowed teaching hours for the professor.";
                            // }
                        }
                        // Check if the professor has already taught this course in the current curriculum
                        $check_query = "SELECT * FROM $sanitized_prof_dept_code 
                        WHERE prof_sched_code = '$prof_sched_code' 
                        AND course_code = '$course_code' 
                        AND semester = '$semester' 
                        AND curriculum = '$section_curriculum' AND class_type = '$class_type'";
                        $check_result = $conn->query($check_query);

                        // Display the fetched data
                        if ($check_result->num_rows > 0) {
                            // echo "no +1";
                            $new_prep_hrs = $current_prep_hrs;
                        } else {
                            // echo "+1";
                            $new_prep_hrs = $current_prep_hrs + 1;
                        }

                        // echo $new_prep_hrs;

                        // COUNTER FOR CONTACT HOURS AND PREP HOURS
                        $prof_sched_code = $prof_code . "_" . $ay_code;
                        $new_teaching_hours = $current_teaching_hours + $duration_hours;

                        $sql_prof_type = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND ay_code = ? AND semester = ? ";
                        $stmt = $conn->prepare($sql_prof_type);
                        $stmt->bind_param("ssis", $prof_code, $user_dept_code, $ay_code, $semester);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $prof_type = $row['prof_type'];

                            if ($prof_type == 'Regular') {
                                // If the professor is Regular, use the formula directly
                                $consultation_hrs = $new_teaching_hours / 3;
                            } else {
                                // If the professor is not Regular, check the teaching hours
                                if ($new_teaching_hours >= 18) {
                                    // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                    $consultation_hrs = 2;
                                } else {
                                    // If teaching hours are less than 18, set consultation hours to 0
                                    $consultation_hrs = 0;
                                }
                            }

                            // Optional: Debugging output
                            // echo "Consultation Hours: " . $consultation_hrs;
                        } else {
                            echo "Professor not found.";
                        }

                        $stmt->close();

                        $update_hours_query = "UPDATE  tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', prep_hrs = '$new_prep_hrs', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester ='$semester' AND dept_code = '$user_dept_code'";

                        if ($conn->query($update_hours_query) === FALSE) {
                            echo "Teaching hours not updated for plotting.";
                        }

                        $insert_prof_sql = "INSERT INTO $sanitized_prof_dept_code (sec_sched_id, prof_sched_code, prof_code, time_start, time_end, day,curriculum, course_code, section_code, room_code, semester,dept_code,ay_code,class_type,cell_color) 
                                            VALUES ('$sec_sched_id', '$prof_sched_code', '$prof_code','$time_start', '$time_end', '$day','$section_curriculum', '$course_code', '$section_sched_code', '$room_code', '$semester','$user_dept_code','$ay_code','$class_type','$color')";

                        if ($conn->query($insert_prof_sql) === FALSE) {
                            echo "Error plotting professor schedule: " . $conn->error;
                        }

                        $prof_sched_code = $prof_code . "_" . $ay_code;
                        $checkSql = "SELECT 1 FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
                        if ($checkStmt = $conn->prepare($checkSql)) {
                            $checkStmt->bind_param("ssss", $prof_sched_code, $semester, $user_dept_code, $ay_code);
                            $checkStmt->execute();
                            $checkStmt->store_result();

                            if ($checkStmt->num_rows > 0) {
                                $checkStmt->close();
                            } else {
                                // Prepare SQL query
                                $sql = "INSERT INTO tbl_prof_schedstatus (prof_sched_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?)";

                                // Initialize prepared statement
                                if ($stmt = $conn->prepare($sql)) {
                                    // Bind parameters
                                    $stmt->bind_param("sssss", $prof_sched_code, $semester, $user_dept_code, $status, $ay_code);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                            }
                        }



                        // Fetch the curriculum type of the current section being plotted


                        $insert_sql = "INSERT IGNORE INTO tbl_psched (prof_sched_code, dept_code, prof_code, ay_code,semester) VALUES ('$prof_sched_code', '$user_dept_code', '$prof_code', '$ay_code','$semester')";

                        if ($conn->query($insert_sql) === FALSE) {
                            echo "Error inserting record into tbl_psched: " . $conn->error;
                        }

                        $prof_code = $_POST['prof_code'];
                        $section_code = $_POST['section_code'];
                        $course_code = $_POST['course_code'];
                        $semester = $_POST['semester'];

                        // Fetch program_code based on section_code
                        $sql_program_code = "SELECT program_code FROM tbl_section WHERE section_code = ? AND dept_code = ? AND ay_code = ? AND semester = ?";
                        $stmt_program_code = $conn->prepare($sql_program_code);
                        $stmt_program_code->bind_param("ssss", $section_code, $user_dept_code, $ay_code, $semester);
                        $stmt_program_code->execute();
                        $stmt_program_code->bind_result($program_code);
                        $stmt_program_code->fetch();
                        $stmt_program_code->close();

                        // Fetch year_level based on course_code
                        $sql_year_level = "SELECT year_level FROM tbl_course WHERE course_code = ? AND dept_code=? AND program_code =?";
                        $stmt_year_level = $conn->prepare($sql_year_level);
                        $stmt_year_level->bind_param("sss", $course_code, $user_dept_code, $program_code);
                        $stmt_year_level->execute();
                        $stmt_year_level->bind_result($year_level);
                        $stmt_year_level->fetch();
                        $stmt_year_level->close();

                        // Prepare the SQL statement for insertion
                        $check_sql = "
                    SELECT course_counter 
                    FROM tbl_assigned_course 
                    WHERE dept_code = ? 
                      AND prof_code = ? 
                      AND course_code = ? 
                      AND year_level = ? 
                      AND semester = ? AND ay_code = ?";

                        $stmt_check = $conn->prepare($check_sql);
                        $stmt_check->bind_param("sssssi", $user_dept_code, $prof_code, $course_code, $year_level, $semester, $ay_code);
                        $stmt_check->execute();
                        $stmt_check->store_result();

                        if ($stmt_check->num_rows > 0) {
                            // Record exists, update the course_counter
                            $stmt_check->bind_result($course_counter);
                            $stmt_check->fetch();

                            // Increment the course_counter by 1
                            $course_counter += 1;

                            // Update the record
                            $update_sql = "
                        UPDATE tbl_assigned_course 
                        SET course_counter = ?
                        WHERE dept_code = ? 
                          AND prof_code = ? 
                          AND course_code = ? 
                          AND year_level = ? 
                          AND semester = ? AND ay_code = ?";

                            $stmt_update = $conn->prepare($update_sql);
                            $stmt_update->bind_param("isssisi", $course_counter, $user_dept_code, $prof_code, $course_code, $year_level, $semester, $ay_code);

                            if ($stmt_update->execute()) {
                                echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var popup = document.getElementById('plotPopup');
                            popup.style.display = 'block';
                            setTimeout(function() {
                                popup.style.display = 'none';
                            }, 2000); // 2 seconds
                        });
                      </script>";
                            } else {
                                echo "Error updating record: " . $stmt_update->error;
                            }

                            $stmt_update->close();
                        } else {
                            // Record does not exist, insert a new one with course_counter set to 1
                            $course_counter = 1;

                            $insert_prof_sql = "
                        INSERT INTO tbl_assigned_course (dept_code, prof_code,prof_name, course_code, year_level, semester, course_counter,ay_code) 
                        VALUES (?, ?,?, ?, ?, ?,?,?)";

                            $stmt_insert = $conn->prepare($insert_prof_sql);
                            $stmt_insert->bind_param("ssssssii", $user_dept_code, $prof_code, $prof_name, $course_code, $year_level, $semester, $course_counter, $ay_code);
                            $stmt_insert->execute();
                            $stmt_insert->close();
                        }

                    }
                }
                //notif plot pwede na siguro dito basta lagyan mo lang ng condition na kapag section_dept_code != user_dept_code

            }
            //notif
            //notifications
            $sql_secsched = "SELECT status FROM tbl_schedstatus WHERE section_sched_code='$section_sched_code'";
            $result_secsched = $conn->query($sql_secsched);

            if ($result_secsched === false) {
                // SQL error handling
                echo "SQL error: " . $conn->error;
            } elseif ($result_secsched->num_rows == 0) {
                // No matching schedule found
                echo "Invalid section code.";
            } else {
                // Fetch the result and get the status
                $row_secsched = $result_secsched->fetch_assoc();
                $status = trim($row_secsched['status']); // Trim whitespace

                // Debugging: log the status to verify
                error_log("Schedule Status: '$status'"); // Check the server logs for this value

                // Only send notification if the status is NOT 'private' or 'draft'
                if ($status !== 'private' && $status !== 'draft') {
                    // Define the notification message and details
                    $notificationMessage = "A new schedule for $section_code in the $semester has been added.";
                    $sender = $_SESSION['cvsu_email'];  // Assuming the sender is the current user (department secretary or admin)

                    $receiver = 'student';
                    $insertNotificationQuery = "INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code,ay_code) 
                                    VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?,?)";
                    $notificationStmt = $conn->prepare($insertNotificationQuery);
                    $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code, $ay_code);
                    $notificationStmt->execute();

                    $receiver = $prof_code;
                    $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code, $ay_code);
                    $notificationStmt->execute();
                } else {
                    // Log message indicating that notifications were skipped due to private/draft status
                    // echo "No notification sent. Status is '$status'.";
                }
            }
            ///popup
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var popup = document.getElementById('plotPopup');
                popup.style.display = 'block';
                setTimeout(function() {
                    popup.style.display = 'none';
                }, 2000); // 2 seconds
            });
          </script>";
        }
    }
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['update_schedule'])) {

        // Validate token
        if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
            // Token is invalid, redirect to the same page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Regenerate a new token to prevent reuse
        $_SESSION['token'] = bin2hex(random_bytes(32));

        $section_sched_code = $_POST['section_sched_code'];


        // Fetch section and academic year information from tbl_secschedlist

        $sql_secsched = "SELECT section_code,ay_code,dept_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
        $result_secsched = $conn->query($sql_secsched);

        if ($result_secsched->num_rows == 0) {
            echo "Invalid section sched code.";
            exit;
        }

        $row_secsched = $result_secsched->fetch_assoc();
        $ay_code = $row_secsched['ay_code'];
        $dept_code = $row_secsched['dept_code'];
        $section = $row_secsched['section_code'];

        // default 
        $course_code = '';
        $new_course_code = '';
        $new_room_code = '';
        $new_room_sched_code = '';
        $new_prof_code = '';
        $new_counter_code = '';
        $updated_old_teaching_hours = null;
        $old_prep_hours = null;
        $updated_old_teaching_hours = null;
        $old_consultation_hrs = null;
        $new_consultation_hrs = null;
        $row_shared_to = null;
        $shared_sched = null;
        $prof_name = null;
        $computer_room = null;
        $lec_hrs = null;
        $lab_hrs = null;





        $sec_sched_id = $_POST['sec_sched_id'];
        $semester = $_POST['semester'];
        $day = isset($_POST['day']) ? $_POST['day'] : null;
        $time_start = isset($_POST['time_start']) ? $_POST['time_start'] : null;
        $time_end = isset($_POST['time_end']) ? $_POST['time_end'] : null;
        $new_course_code = strtoupper($_POST['new_course_code']);
        $new_room_code = strtoupper($_POST['new_room_code']);
        $new_prof_code = strtoupper($_POST['new_prof_code']);
        $new_room_sched_code = $new_room_code . "_" . $ay_code;
        $new_class_type = isset($_POST['class_type']) ? $_POST['class_type'] : null;
        $color = $_POST['color'];
        $new_prof_sched_code = $new_prof_code . "_" . $ay_code;


        $updated_new_teaching_hours = 0;
        $new_max_teaching_hours = 0;
        $status = "draft";
        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

        $shared_to = $_POST['shared_to'];
        $prof_name = null;

        $fetch_details_query = "SELECT prof_code, class_type, room_code, shared_sched ,course_code FROM $sanitized_dept_code WHERE sec_sched_id = '$sec_sched_id'";
        $details_result = $conn->query($fetch_details_query);

        if (!$details_result) {
            die("Error: Query failed to execute: " . $conn->error);
        }
        if ($details_result->num_rows > 0) {
            $details_row = $details_result->fetch_assoc();
            $prof_code = $details_row['prof_code'];
            $room_code = $details_row['room_code'];
            $class_type = $details_row['class_type'];
            $course_code = $details_row['course_code'];
        }

        $fetch_info_query = "SELECT dept_code, ay_code,section_code,program_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
        $result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $section_dept_code = $row['dept_code'];
            $ay_code = $row['ay_code'];
            $section_code = $row['section_code'];
            $program_code = $row['program_code'];
        } else {
            die("Error: No matching section schedule found for code '$section_sched_code'.");
        }




        $curriculum_check_query = "SELECT curriculum FROM tbl_section 
                WHERE section_code = '$section_code' 
                AND dept_code = '$section_dept_code' AND ay_code = '$ay_code' AND semester = '$semester'";
        $curriculum_result = $conn->query($curriculum_check_query);

        $section_curriculum = ''; // Initialize to store the curriculum type
        if ($curriculum_result->num_rows > 0) {
            $curriculum_row = $curriculum_result->fetch_assoc();
            $section_curriculum = $curriculum_row['curriculum'];
        }


        $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ?";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("ss", $section_code, $section_dept_code);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();



        $sql_secsched = "SELECT shared_sched, shared_to, course_code, dept_code FROM $sanitized_dept_code WHERE sec_sched_id = ? AND section_sched_code = ?";
        $stmt = $conn->prepare($sql_secsched);

        if ($stmt) {
            $stmt->bind_param("ss", $sec_sched_id, $section_sched_code); // Assuming sec_sched_id is an integer
            $stmt->execute();
            $result_secsched = $stmt->get_result();

            if ($row_secsched = $result_secsched->fetch_assoc()) {
                $shared_sched = $row_secsched['shared_sched'];
                $row_shared_to = $row_secsched['shared_to'];
                $course_code = $row_secsched['course_code'];
                $dept_code_internal = $row_secsched['dept_code'];


                // Retrieve department code based on shared email
                $sql_dept = "SELECT dept_code FROM tbl_prof_acc WHERE cvsu_email = ? AND ay_code = ? AND semester = ?";
                $stmt_dept = $conn->prepare($sql_dept);

                if ($stmt_dept) {
                    $stmt_dept->bind_param("sis", $row_shared_to, $ay_code, $semester);
                    $stmt_dept->execute();
                    $result_dept = $stmt_dept->get_result();

                    if ($result_dept->num_rows > 0) {
                        $row_dept = $result_dept->fetch_assoc();
                        $row_shared_dept_code = $row_dept['dept_code'];
                    } else {
                        // echo "No matching department found for the provided email.";
                    }

                    $stmt_dept->close();
                } else {
                    echo "Error preparing department query: " . $conn->error;
                }
            }
            $stmt->close();
        } else {
            echo "Error preparing section schedule query: " . $conn->error;
        }




        $sql = "SELECT lec_hrs, lab_hrs, allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $new_course_code, $program_code, $year_level, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lec_hrs = $row['lec_hrs'];
            $lab_hrs = $row['lab_hrs'];
            $computer_room = $row['computer_room'];

        }
        if (empty($shared_sched)) {
            // If no shared schedule is defined
            $room_dept_code = null;

            if ($computer_room == 1 & $class_type == 'lab') {
                $fetch_info_query_room = "SELECT dept_code FROM tbl_room WHERE college_code = '$ccl_college_code' AND room_code = '$new_room_code'";
                $result_room = $conn->query($fetch_info_query_room);


                if ($result_room->num_rows > 0) {
                    $row = $result_room->fetch_assoc();
                    $room_dept_code = $row['dept_code'];

                }


                $CDepartment = $section_dept_code;
                $RMdepartment = $room_dept_code;
                $PFdepartment = $section_dept_code;
            } else {
                $CDepartment = $user_dept_code;
                $PFdepartment = $user_dept_code;
                $RMdepartment = $user_dept_code;

            }
            // echo "empty";
        }
        if ($shared_sched === "room") {
            // If the shared schedule is for rooms
            if ($shared_to === $current_user_email) {
                $PFdepartment = $dept_code_internal;
            } else {
                $PFdepartment = $user_dept_code;
            }
            $RMdepartment = $row_shared_dept_code;
            $CDepartment = $dept_code_internal;

            // echo "room" . $RMdepartment;
            // echo "prof" . $PFdepartment;
        }
        if ($shared_sched === "prof") {
            if ($shared_to === $current_user_email) {
                $RMdepartment = $dept_code_internal;

            } else {
                $RMdepartment = $user_dept_code;
            }

            $PFdepartment = $row_shared_dept_code;
            $CDepartment = $dept_code_internal;
            // echo "prof";
            // echo $CDepartment;
        }


        $sql_secsched = "SELECT time_start, day, time_end,class_type FROM $sanitized_dept_code WHERE sec_sched_id='$sec_sched_id'";
        $result_secsched = $conn->query($sql_secsched);

        if ($result_secsched && $result_secsched->num_rows > 0) {
            $row_secsched = $result_secsched->fetch_assoc();
            $old_day = $row_secsched['day'];

            // Set values only if they are empty
            if (empty($time_start)) {
                $time_start = $row_secsched['time_start'];

            }
            if (empty($time_end)) {
                $time_end = $row_secsched['time_end'];
            }
            if (empty($day)) {
                $day = $row_secsched['day'];
            }
            if (empty($new_class_type)) {
                $new_class_type = $row_secsched['class_type'];
            }

            $time_start_id = $row_secsched['time_start'];
            $time_end_id = $row_secsched['time_end'];

            $time_start_dt_id = new DateTime($time_start_id);
            $time_end_dt_id = new DateTime($time_end_id);
            $duration_id = $time_start_dt_id->diff($time_end_dt_id);
            $duration_hours_id = $duration_id->h + ($duration_id->i / 60);
            // echo $duration_hours_id;
            // echo $time_start.$time_end;
        }
        if (empty($section_sched_code) || empty($semester) || empty($day) || empty($time_start) || empty($time_end) || empty($new_course_code) || empty($new_class_type)) {
            $invalid_fields[] = "All fields are required";
        } else {
            $sanitized_pcontact_sched_code_new = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $PFdepartment . "_" . $ay_code);


            // Ensure time_end is not less than time_start
            $time_start_dt = new DateTime($time_start);
            $time_end_dt = new DateTime($time_end);
            $duration = $time_start_dt->diff($time_end_dt);
            $duration_hours = $duration->h + ($duration->i / 60);
            // $duration_hours = number_format($duration_hours, 2, '.', '');






            /////

            $sql_check_schedule = "SELECT class_type, time_start, time_end, sec_sched_id, day
            FROM {$sanitized_dept_code} 
            WHERE course_code = ? AND section_sched_code = ? AND class_type = ? AND sec_sched_id!= ?";
            $stmt_check = $conn->prepare($sql_check_schedule);
            $stmt_check->bind_param("sssi", $new_course_code, $section_sched_code, $class_type, $sec_sched_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();


            // Initialize variables to track previously plotted hours
            $prev_plotted_lec_hours = 0;
            $prev_plotted_lab_hours = 0;
            $total_plotted_hours = 0;
            $old_duration_hours = 0;
            $plotted_lab_hours = 0;
            // echo $sec_sched_id;

            while ($row_check = $result_check->fetch_assoc()) {

                $old_time_start_dt = new DateTime($row_check['time_start']);
                $old_time_end_dt = new DateTime($row_check['time_end']);
                $old_duration = $old_time_start_dt->diff($old_time_end_dt);
                $old_duration_hours = $old_duration->h + ($old_duration->i / 60); // Convert to decimal hours
                $total_plotted_hours += $old_duration_hours;

                // Display the current iteration's details
                // Display the current iteration's details including the course code, class type, start time, and day
                // echo "Course Code: {$new_course_code}, Class Type: {$row_check['class_type']}, Duration (hours): {$old_duration_hours}, Time Start: {$row_check['time_start']},Time End: {$row_check['time_end']},Day: {$row_check['day']}<br>";


                // Accumulate hours based on class type (lecture or lab)
                if ($row_check['class_type'] === 'lec') {
                    $prev_plotted_lec_hours += $old_duration_hours; // Accumulate previous lecture hours
                    // echo "Accumulated Lecture Hours: {$prev_plotted_lec_hours}<br>";
                } elseif ($row_check['class_type'] === 'lab') {
                    $prev_plotted_lab_hours += $old_duration_hours; // Accumulate previous lab hours
                    // echo "Accumulated Lab Hours: {$prev_plotted_lab_hours}<br>";
                }
            }

            // Display total hours for lecture and lab
            // echo "Total Lecture Hours: {$prev_plotted_lec_hours}<br>";
            // echo "Lecture Hours: {$lec_hrs}<br>";
            // echo "Lab Hours: {$lab_hrs}<br>";
            // echo "Total Lab Hours: {$prev_plotted_lab_hours}<br>";
            // echo "Total Combined Plotted Hours: {$total_plotted_hours}<br>";
            // echo "Total Duration Hours for new course: {$duration_hours}<br>";

            // Calculate the new duration for the course after subtracting the previously plotted hours
            $new_duration_hours = $duration_hours - $duration_hours_id;
            // echo "New Duration Hours for {$new_course_code}: {$new_duration_hours}<br>";
            // echo "Total Plotted Hours for Course {$new_course_code}: {$total_plotted_hours} <br>";

            // Check if the new course's lecture or lab hours exceed the allowed maximum hours
            if ($lec_hrs == 0 && $lab_hrs == 0) {
                $check_course = "SELECT * 
                                 FROM $sanitized_dept_code
                                 WHERE course_code = '$new_course_code' AND semester = '$semester' AND section_sched_code = '$section_sched_code'";
                $result_course = $conn->query($check_course);
                $plotted_lec_hours = null;
                // $plotted_lab_hours = '';
                if ($result_course && $result_course->num_rows == 0) {
                    if ($plotted_lec_hours > 1 || $plotted_lab_hours > 1) {
                        if ($class_type === 'lec') {
                            $invalid_fields[] = "Lecture hours have already been met.";
                        } elseif ($class_type === 'lab') {
                            $invalid_fields[] = "Laboratory hours have already been met.";
                        } else {
                            $invalid_fields[] = "Invalid class type.";
                        }
                    }
                } else {
                    if ($duration_hours > 1) {
                        if ($class_type === 'lec') {
                            $invalid_fields[] = "Lecture hours have already been met.";
                        } elseif ($class_type === 'lab') {
                            $invalid_fields[] = "Laboratory hours have already been met.";
                        } else {
                            $invalid_fields[] = "Invalid class type.";
                        }
                    }
                }
            } else {
                if ($class_type == 'lec') {
                    if ($course_code === $new_course_code) {
                        // Update lecture hours with total plotted + new hours
                        $plotted_lec_hours = $total_plotted_hours + $duration_hours;
                    } else {
                        $plotted_lec_hours = $prev_plotted_lec_hours + $duration_hours;
                    }

                    // echo "Total Plotted Hours value : {$total_plotted_hours} + {$duration_hours}<br>";

                    // Check if total exceeds allowable lecture hours
                    if ($plotted_lec_hours > $lec_hrs) {
                        // echo ("Error: Total lecture hours for {$new_course_code} exceed the maximum allowed ({$lec_hrs} hours).");
                        $invalid_fields[] = "Lecture hours have already been met.";
                    }
                } elseif ($class_type === 'lab') {

                    if ($course_code === $new_course_code) {
                        $plotted_lab_hours = $total_plotted_hours + $new_duration_hours;
                    } else {
                        $plotted_lab_hours = $prev_plotted_lab_hours + $duration_hours;
                    }
                    // Check if total exceeds allowable lab hours
                    if ($plotted_lab_hours > $lab_hrs) {
                        // echo ("Error: Total laboratory hours for {$new_course_code} exceed the maximum allowed ({$lab_hrs} hours).");
                        $invalid_fields[] = "Laboratory hours have already been met.";
                    }
                }
            }
            $stmt_check->close();



            // if ($lec_hrs != 0 && $lab_hrs != 0) {
            //     // Check if the course's lecture or laboratory hours have been met
            //     if (
            //         ($plotted_lec_hours > $lec_hrs && $class_type === 'lec') ||
            //         ($plotted_lab_hours > $lab_hrs && $class_type === 'lab')
            //     ) {
            //         if ($class_type === 'lec') {
            //             $invalid_fields[] = "Lecture hours have already been met.";
            //         } else {
            //             $invalid_fields[] = "Laboratory hours have already been met.";
            //         }
            //     }
            // }



            if ($time_end_dt <= $time_start_dt) {
                $invalid_fields[] = "Invalid time range: End time (" . $time_end_dt->format('H:i') . ") cannot be earlier than or the same as start time (" . $time_start_dt->format('H:i') . ").";
            }

            // Check if the course exists
            // echo $shared_to . $current_user_email;
            if (($section_college_code == $college_code)) {
                if ($user_type === 'CCL Head') {
                    // CCL Head: Select courses with 'lecR&labR' and 'labR'
                    $course_dept_code = $section_dept_code;
                } elseif ($user_type === 'Department Secretary' || $user_type === 'Department Chairperson') {
                    // Department Secretary: Select courses with 'lecR&labR' and 'lecR'
                    $course_dept_code = $user_dept_code;
                }
                // echo $course_dept_code;  

                if (empty($shared_sched)) {
                    $fetch_course_query = "SELECT * FROM tbl_course WHERE dept_code = '$course_dept_code' AND course_code = '$new_course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
                    $result_course = $conn->query($fetch_course_query);
                    if ($result_course->num_rows === 0) {
                        $invalid_fields[] = "Course does not exist";
                    }
                }


                if (!empty($new_room_code)) {
                    // Check if the room exists
                    if ($user_type == "CCL Head") {
                        $RMdepartment = $section_dept_code;
                    } else {
                        $RMdepartment = $RMdepartment;
                    }
                    $fetch_room_query = "SELECT * FROM tbl_room WHERE dept_code = '$RMdepartment' AND room_code = '$new_room_code' AND status = 'available' ";
                    $result_room = $conn->query($fetch_room_query);
                    if ($result_room->num_rows === 0) {
                        $invalid_fields[] = "Room does not exist";
                    }

                }

                if (!empty($new_prof_code) && empty($shared_sched)) {
                    if ($user_type == "CCL Head") {
                        $PFdepartment = $section_dept_code;
                    } else {
                        $PFdepartment = $PFdepartment;
                    }
                    $fetch_prof_query = "SELECT * FROM tbl_prof WHERE dept_code = '$PFdepartment' AND  prof_code = '$new_prof_code' AND acc_status = '1' AND ay_code = '$ay_code' AND semester = '$semester' ";
                    $result_prof = $conn->query($fetch_prof_query);
                    if ($result_prof->num_rows === 0) {
                        $invalid_fields[] = "Professor does not exist";
                    }

                }
            }
        }
        if (!empty($invalid_fields)) {
            // Output conflicts list in a modal using JavaScript
            echo "<script type='text/javascript'>
                            document.addEventListener('DOMContentLoaded', function() {
                                var conflictList = document.getElementById('conflictList');";

            foreach ($invalid_fields as $invalid) {
                // Safely handle quotes to prevent JS errors
                $safe_invalid = htmlspecialchars($invalid, ENT_QUOTES, 'UTF-8');
                echo "var li = document.createElement('li');
                              li.textContent = '$safe_invalid';
                              conflictList.appendChild(li);";
            }

            echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                          myModal.show();
                        });
                    </script>";
        } else {
            $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $PFdepartment . "_" . $ay_code);
            $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $RMdepartment . "_" . $ay_code);


            $fetch_original_duration_query = "SELECT time_start, time_end, prof_code FROM $sanitized_dept_code WHERE sec_sched_id = '$sec_sched_id'";
            $original_duration_result = $conn->query($fetch_original_duration_query);
            if ($original_duration_result->num_rows > 0) {
                $original_duration_row = $original_duration_result->fetch_assoc();
                $original_time_start = new DateTime($original_duration_row['time_start']);
                $original_time_end = new DateTime($original_duration_row['time_end']);
                $original_prof_code = $original_duration_row['prof_code'];
                $original_duration = $original_time_start->diff($original_time_end);
                $original_duration_hours = $original_duration->h + ($original_duration->i / 60);
            } else {
                // If original schedule is not found, adjust sec_sched_id and retry
                $sec_sched_id = (int) $sec_sched_id - 1;
                $fetch_original_duration_query = "SELECT time_start, time_end, prof_code FROM $sanitized_dept_code WHERE sec_sched_id = '$sec_sched_id'";
                $original_duration_result = $conn->query($fetch_original_duration_query);


                if ($original_duration_result->num_rows > 0) {
                    $original_duration_row = $original_duration_result->fetch_assoc();
                    $original_time_start = new DateTime($original_duration_row['time_start']);
                    $original_time_end = new DateTime($original_duration_row['time_end']);
                    $original_prof_code = $original_duration_row['prof_code'];
                    $original_duration = $original_time_start->diff($original_time_end);
                    $original_duration_hours = $original_duration->h + ($original_duration->i / 60);
                } else {
                    // If still not found, display the query and terminate
                    echo "Query: " . $fetch_original_duration_query . "<br>";
                    die("Error: Original schedule not found after adjusting sec_sched_id.");
                }
            }

            // Calculate new duration
            $new_duration = $time_start_dt->diff($time_end_dt);
            $new_duration_hours = $new_duration->h + ($new_duration->i / 60);

            if ($college_code === $section_college_code) {
                if (!empty($prof_code)) {

                    $fetch_info_query = "SELECT lec_hrs,lab_hrs FROM tbl_course WHERE dept_code = '$CDepartment' AND curriculum = '$section_curriculum' AND course_code = '$course_code' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
                    $result = $conn->query($fetch_info_query);

                    // echo "Department: " . $CDepartment . "<br>";
                    // echo "Curriculum: " . $section_curriculum . "<br>";
                    // echo "Course Code: " . $course_code . "<br>";
                    // echo "Semester: " . $semester . "<br>";
                    // echo "Program Code: " . $program_code . "<br>";
                    // echo "Year Level: " . $year_level . "<br>";


                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $old_lec_hrs = $row['lec_hrs'];
                        $old_lab_hrs = $row['lab_hrs'];
                    } else {
                        die("Error: No matching lec and lab found for cdode '$course_code'.");
                    }

                    // Initialize the $teaching_hrs variable correctly
                    if ($class_type === 'lec') {
                        $old_teaching_hrs = $old_lec_hrs; // Use '=' for assignment
                    } else {
                        $old_teaching_hrs = $old_lab_hrs; // Use '=' for assignment
                    }




                    $prof_sched_code = $prof_code . "_" . $ay_code;
                    // Fetch the current teaching hours of the old professor
                    $fetch_old_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE  prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";
                    $old_prof_hours_result = $conn->query($fetch_old_prof_hours_query);

                    if (!$old_prof_hours_result) {
                        die("Error: Query failed to execute: " . $conn->error);
                    }

                    if ($old_prof_hours_result->num_rows > 0) {
                        $old_prof_hours_row = $old_prof_hours_result->fetch_assoc();
                        $old_current_teaching_hours = $old_prof_hours_row['teaching_hrs'];
                        $old_prep_hours = $old_prof_hours_row['prep_hrs'];

                        // Ensure $original_duration_hours is set and valid
                        if (!isset($original_duration_hours)) {
                            die("Error: $original_duration_hours is not defined.");
                        }
                        // Calculate updated teaching hours for the old professor
                        $updated_old_teaching_hours = $old_current_teaching_hours - $original_duration_hours;
                    }
                }

            }
            if ($college_code === $section_college_code) {
                if (!empty($new_prof_code)) {
                    $fetch_info_query = "SELECT * FROM tbl_course WHERE dept_code = '$CDepartment' AND course_code = '$new_course_code' AND curriculum = '$section_curriculum' AND semester = '$semester' AND program_code = '$program_code' AND year_level = '$year_level'";
                    $result = $conn->query($fetch_info_query);

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $new_lec_hrs = $row['lec_hrs'];
                        $new_lab_hrs = $row['lab_hrs'];
                    } else {
                        die("Error: No matching lec and lab for code '$new_course_code'.");
                    }
                    // Initialize the $teaching_hrs variable correctly
                    if ($class_type === 'lec') {
                        $new_teaching_hrs = $new_lec_hrs; // Use '=' for assignment
                    } else {
                        $new_teaching_hrs = $new_lab_hrs; // Use '=' for assignment
                    }

                    if (empty($shared_sched) || $shared_sched == "prof") {

                        $fetch_prof_name_query = "SELECT prof_name FROM tbl_prof WHERE prof_code = '$new_prof_code' AND dept_code = '$PFdepartment' AND ay_code = '$ay_code' AND semester = '$semester'";
                        $prof_result = $conn->query($fetch_prof_name_query);

                        if ($prof_result->num_rows > 0) {
                            $prof_row = $prof_result->fetch_assoc();
                            $prof_name = $prof_row['prof_name'];
                        } else {
                            $prof_name = null;
                        }

                        $select_sql = "SELECT * FROM tbl_psched_counter 
                                        WHERE prof_sched_code = '$new_prof_sched_code' 
                                        AND semester = '$semester' 
                                        AND dept_code = '$PFdepartment' 
                                        AND prof_code = '$new_prof_code'";


                        // Execute the query
                        $result = $conn->query($select_sql);

                        // Check if any rows are returned
                        if ($result && $result->num_rows == 0) {
                            // No matching record found, so proceed with the insertion
                            $insert_sql = "INSERT INTO tbl_psched_counter (prof_sched_code, semester, dept_code, prof_code,ay_code) 
                                            VALUES ('$new_prof_sched_code', '$semester', '$PFdepartment', '$new_prof_code','$ay_code')";

                            // Execute the insertion
                            if ($conn->query($insert_sql) === FALSE) {
                                echo "Error inserting record into tbl_psched_counter: " . $conn->error;
                            }
                        } else {
                            // Matching record already exists or there was an error executing the SELECT query
                            if ($result === FALSE) {
                                echo "Error checking for existing record: " . $conn->error;
                            } else {
                                // Optional: You could add a message or log here if a record already exists
                                // echo "Record already exists, not inserting.";
                            }
                        }
                    }

                    // Fetch current teaching hours of the new professor
                    $fetch_new_prof_hours_query = "SELECT teaching_hrs,prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$new_prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";
                    $new_prof_hours_result = $conn->query($fetch_new_prof_hours_query);

                    if ($new_prof_hours_result->num_rows > 0) {
                        $new_prof_hours_row = $new_prof_hours_result->fetch_assoc();
                        $new_teaching_hours = $new_prof_hours_row['teaching_hrs'];
                        $new_prep_hours = $new_prof_hours_row['prep_hrs'];

                        // Calculate updated teaching hours for the new professor
                        if ($prof_code == $new_prof_code) {
                            // If the old and new professor are the same, adjust teaching hours
                            $updated_new_teaching_hours = $updated_old_teaching_hours + $new_duration_hours;
                        } else {
                            // If the old and new professors are different
                            $updated_new_teaching_hours = $new_teaching_hours + $new_duration_hours;
                        }
                    }
                }
            }
            // if ($updated_new_teaching_hours > 35) {
            //     $exceed[] = "Updating this schedule exceeds the maximum allowed teaching hours for the professor.";
            // }

            if (!empty($exceed)) {
                echo "<script type='text/javascript'>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var conflictList = document.getElementById('conflictList');";

                foreach ($exceed as $max) {
                    echo "var li = document.createElement('li');
                                  li.textContent = '$max';
                                  conflictList.appendChild(li);";
                }

                echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                              myModal.show();
                            });
                        </script>";
            } else {

                // Create the table for room schedule if it does not exist
                if ($college_code == $section_college_code && (empty($shared_sched) || $shared_sched == "room")) {
                    if (!empty($new_room_code)) {

                        if ($computer_room == 1 && $class_type == 'lab') {
                            $plot_room_dept_code = $ccl_college_code;
                        } else {
                            $plot_room_dept_code = $user_dept_code;
                        }



                        $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $plot_room_dept_code . "_" . $ay_code);
                        $conflict_room_sql = "SELECT * FROM $sanitized_room_dept_code
                                                      WHERE day='$day' AND semester='$semester' AND dept_code = '$RMdepartment' AND ay_code='$ay_code' AND room_code = '$new_room_code' AND sec_sched_id != '$sec_sched_id'
                                                      AND ((time_start <= '$time_start' AND time_end > '$time_start') 
                                                      OR (time_start < '$time_end' AND time_end >= '$time_end'))";
                        $result_conflict_room = $conn->query($conflict_room_sql);

                        if ($result_conflict_room->num_rows > 0) {
                            $conflicts[] = "Room schedule conflict detected.";
                        }

                    }

                }

                if ($college_code == $section_college_code && (empty($shared_sched) || $shared_sched == "prof")) {
                    // Check for professor conflict if new_prof_code is not 'TBA'
                    if (!empty($new_prof_code)) {
                        $conflict_prof_sql = "SELECT * FROM $sanitized_prof_dept_code 
                                                              WHERE day='$day' AND semester='$semester' AND dept_code = '$PFdepartment'  AND ay_code='$ay_code' AND prof_code = '$new_prof_code' AND sec_sched_id != '$sec_sched_id'
                                                              AND ((time_start <= '$time_start' AND time_end > '$time_start') 
                                                              OR (time_start < '$time_end' AND time_end >= '$time_end'))";
                        $result_conflict_prof = $conn->query($conflict_prof_sql);

                        if ($result_conflict_prof->num_rows > 0) {
                            $conflicts[] = "Professor schedule conflict detected.";
                        }



                        $check_table_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code_new'";
                        $table_result = $conn->query($check_table_sql);

                        if ($table_result && $table_result->num_rows > 0) {
                            // Execute the conflict check query if the table exists
                            $conflict_check_sql_pcontact_sched = "SELECT * FROM $sanitized_pcontact_sched_code_new 
                                                WHERE day='$day' AND semester='$semester' AND prof_code = '$new_prof_code'
                                                AND ((time_start <= '$time_start' AND time_end > '$time_start') 
                                                OR (time_start < '$time_end' AND time_end >= '$time_end'))";

                            $result_pcontact_sched = $conn->query($conflict_check_sql_pcontact_sched);

                            // Check for conflicts
                            if ($result_pcontact_sched && $result_pcontact_sched->num_rows > 0) {
                                $conflicts[] = "Professor schedule conflict detected.";
                            }
                        }

                    }

                }

                $sql_section_conflict = "SELECT * FROM $sanitized_dept_code 
                                                        WHERE day='$day' AND semester='$semester' AND section_sched_code = '$section_sched_code' AND ay_code ='$ay_code'
                                                        AND sec_sched_id != '$sec_sched_id' 
                                                        AND ((time_start <= '$time_start' AND time_end > '$time_start') 
                                                        OR (time_start < '$time_end' AND time_end >= '$time_end') 
                                                        OR (time_start >= '$time_start' AND time_end <= '$time_end'))";

                $result_section_conflict = $conn->query($sql_section_conflict);

                if ($result_section_conflict->num_rows > 0) {
                    $conflicts[] = "Schedule conflict detected in section schedule.";

                }

                // Display conflicts if any
                if (!empty($conflicts)) {
                    echo "<script type='text/javascript'>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                var conflictList = document.getElementById('conflictList');";

                    foreach ($conflicts as $conflict) {
                        echo "var li = document.createElement('li');
                                              li.textContent = '$conflict';
                                              conflictList.appendChild(li);";
                    }

                    echo "var myModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                                          myModal.show();
                                        });
                                    </script>";
                } else {
                    // Echo the room delete query for debugging
                    if ($college_code === $section_college_code && (empty($shared_sched) || $shared_sched == "room")) {

                        if ($computer_room == 1 && $class_type == 'lab') {
                            $plot_room_dept_code = $ccl_college_code;
                        } else {
                            $plot_room_dept_code = $RMdepartment;
                        }

                        $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $plot_room_dept_code . "_" . $ay_code);


                        $delete_old_room_sql = "DELETE FROM $sanitized_room_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_code = '$section_sched_code' AND semester = '$semester' AND ay_code = '$ay_code';";
                        if ($conn->query($delete_old_room_sql) === FALSE) {
                            echo "Error deleting previous room schedule from $sanitized_room_dept_code: " . $conn->error;
                        }


                    }

                    if ($college_code === $section_college_code && (empty($shared_sched) || $shared_sched == "prof")) {
                        if (!empty($prof_code)) {
                            $prof_sched_code = $prof_code . "_" . $ay_code;
                            $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_psched_" . $PFdepartment . "_" . $ay_code);
                            // Fetch teaching hours of the current professor
                            // First query to fetch teaching hours by professor code


                            $fetch_prof_code_query = "SELECT prof_code FROM $sanitized_dept_code WHERE sec_sched_id = '$sec_sched_id'";
                            $sched_result = $conn->query($fetch_prof_code_query);

                            if (!$sched_result) {
                                die("Error: Query failed to execute: " . $conn->error);
                            }

                            if ($sched_result->num_rows > 0) {
                                $sched_row = $sched_result->fetch_assoc();
                                $prof_code = $sched_row['prof_code'];

                                // Fetch teaching hours for the obtained prof_code
                                // $fetch_teaching_hrs_query = "SELECT teaching_hrs FROM tbl_prof WHERE prof_code = '$prof_code' AND dept_code ='$PFdepartment'";
                                // $prof_result = $conn->query($fetch_teaching_hrs_query);

                                // if (!$prof_result) {
                                //     die("Error: Query failed to execute: " . $conn->error);
                                // }

                                // if ($prof_result->num_rows > 0) {
                                //     $prof_row = $prof_result->fetch_assoc();
                                //     $teaching_hrs = $prof_row['teaching_hrs'];
                                //     // Debug output
                                //    // echo "Teaching hours of professor $prof_code: $teaching_hrs <br>";
                                // } else {

                                // }
                            } else {
                                die("Error: No matching schedule found for sec_sched_id '$sec_sched_id'.");
                            }



                            // Echo the prof_code to ensure the condition is being met
                            //echo "prof_code: " . $prof_code . "<br>";

                            // Echo the delete query for debugging
                            $delete_prof_sql = "DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_code = '$section_sched_code' AND semester='$semester' AND ay_code = '$ay_code';";
                            // echo "Professor Delete Query: " . $delete_prof_sql . "<br>";  // Echo the query

                            // Execute the delete query
                            if ($conn->query($delete_prof_sql) === FALSE) {
                                echo "Error deleting previous professor schedule from $sanitized_prof_dept_code: " . $conn->error;
                            }


                            $check_query = "SELECT * FROM $sanitized_prof_dept_code 
                                        WHERE prof_sched_code = '$prof_sched_code' 
                                        AND course_code = '$course_code' 
                                        AND semester = '$semester' 
                                        AND curriculum = '$section_curriculum' AND class_type= '$class_type'";
                            $check_result = $conn->query($check_query);


                            // If the professor has not taught this course in the current curriculum, add 1 prep hour
                            if ($check_result->num_rows === 0) {
                                while ($row = $check_result->fetch_assoc()) {
                                    // echo "<pre>";
                                    // print_r($row);
                                    // echo "</pre>";
                                }
                                $update_old_prep_hrs = $old_prep_hours - 1;
                            } else {
                                $update_old_prep_hrs = $old_prep_hours;
                            }

                            // echo "Updated old professor teaching hours: $updated_old_teaching_hours <br>";

                            $sql_prof_type = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                            $stmt = $conn->prepare($sql_prof_type);
                            $stmt->bind_param("sssi", $prof_code, $PFdepartment, $semester, $ay_code);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $prof_type = $row['prof_type'];

                                if ($prof_type == 'Regular') {
                                    // If the professor is Regular, use the formula directly
                                    $old_consultation_hrs = $updated_old_teaching_hours / 3;
                                } else {
                                    // If the professor is not Regular, check the teaching hours
                                    if ($updated_old_teaching_hours >= 18) {
                                        // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                        $old_consultation_hrs = 2;
                                    } else {
                                        // If teaching hours are less than 18, set consultation hours to 0
                                        $old_consultation_hrs = 0;
                                    }
                                }

                                // Optional: Debugging output
                                //    echo "Consultation Hours: " . $consultation_hrs;
                            }

                            $stmt->close();

                            $update_old_prof_sql = "UPDATE tbl_psched_counter SET teaching_hrs='$updated_old_teaching_hours', prep_hrs = '$update_old_prep_hrs',consultation_hrs = '$old_consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";

                            if ($conn->query($update_old_prof_sql) === FALSE) {
                                echo "Error updating old professor's teaching hours: " . $conn->error;
                            }

                        }
                    }
                    // Construct the update query for tbl_secsched
                    if (empty($prof_name)) {
                        $prof_name = strtoupper($_POST['new_prof_code']);
                    }

                    $update_secsched_sql = "UPDATE $sanitized_dept_code SET 
                                        semester='$semester', day='$day', time_start='$time_start', time_end='$time_end', class_type = '$new_class_type',
                                        course_code='$new_course_code', room_code='$new_room_code', prof_code='$new_prof_code', prof_name='$prof_name'
                                        WHERE sec_sched_id='$sec_sched_id' AND section_sched_code = '$section_sched_code'; ";

                    if ($conn->query($update_secsched_sql) === TRUE) {

                        // Delete previous schedules from tbl_roomsched for old room code

                        if ($college_code === $section_college_code && (empty($shared_sched) || $shared_sched == "room")) {

                            if (!empty($new_room_code)) {

                                // if ($computer_room === 1 && $new_class_type == "lab"){
                                //     $room_dept_code = $ccl_dept_code;
                                // }else{
                                //     $room_dept_code = $RMdepartment;
                                // }

                                if ($computer_room == 1 && $class_type == 'lab') {
                                    $plot_room_dept_code = $ccl_college_code;
                                } else {
                                    $plot_room_dept_code = $RMdepartment;
                                }

                                if ($computer_room == 0) {
                                    $sql_room = "SELECT room_in_charge, room_type FROM tbl_room WHERE room_code='$new_room_code' AND dept_code = '$plot_room_dept_code' AND status = 'available' ";
                                    $result_room = $conn->query($sql_room);
                                } else {
                                    $sql_room = "SELECT room_in_charge, room_type FROM tbl_room WHERE room_code='$new_room_code' AND college_code = '$user_college_code' AND status = 'available'";
                                    $result_room = $conn->query($sql_room);
                                }

                                if ($result_room->num_rows > 0) {
                                    $row_room = $result_room->fetch_assoc();
                                    $room_in_charge = $row_room['room_in_charge'];
                                    $room_type = $row_room['room_type'];

                                    $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $plot_room_dept_code . "_" . $ay_code);

                                    // Fetch professor name for the new professor code
                                    // Insert new schedule into the room schedule table for the new room code
                                    $insert_room_sql = "INSERT INTO $sanitized_room_dept_code (sec_sched_id, room_code, room_sched_code, semester, ay_code, room_in_charge, day, curriculum, time_start, time_end, course_code, section_code, prof_name, prof_code,room_type,dept_code,class_type) 
                                                                    VALUES ('$sec_sched_id', '$new_room_code', '$new_room_sched_code', '$semester', '$ay_code', '$room_in_charge', '$day','$section_curriculum', '$time_start', '$time_end', '$new_course_code', '$section_sched_code', '$prof_name', '$new_prof_code','$room_type','$RMdepartment','$new_class_type')";


                                    if ($conn->query($insert_room_sql) === FALSE) {
                                        echo "Error inserting room schedule: " . $conn->error;
                                    }
                                } else {
                                    echo "Room details not found for new_room_code: $new_room_code";
                                }


                                $checkSql = "SELECT 1 FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $checkStmt->bind_param("ssss", $new_room_sched_code, $semester, $RMdepartment, $ay_code);
                                    $checkStmt->execute();
                                    $checkStmt->store_result();

                                    if ($checkStmt->num_rows > 0) {
                                        $checkStmt->close();
                                    } else {
                                        // Prepare SQL query
                                        $sql = "INSERT INTO tbl_room_schedstatus (room_sched_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?)";

                                        // Initialize prepared statement
                                        if ($stmt = $conn->prepare($sql)) {
                                            // Bind parameters
                                            $stmt->bind_param("sssss", $new_room_sched_code, $semester, $RMdepartment, $status, $ay_code);

                                            // Execute query
                                            $stmt->execute();

                                            $stmt->close();
                                        }
                                    }
                                }

                                $insert_sql = "INSERT IGNORE INTO tbl_rsched (room_sched_code, dept_code, room_code, room_type, ay_code) 
                                            VALUES ('$new_room_sched_code', '$RMdepartment', '$new_room_code', '$room_type', '$ay_code')";

                                if ($conn->query($insert_sql) === FALSE) {
                                    echo "Error inserting record into tbl_rsched: " . $conn->error;
                                }
                            }

                        }


                        if ($college_code != $section_college_code && $shared_sched == "room") {
                            if (!empty($new_room_code)) {
                                $sql_room = "SELECT room_in_charge, room_type FROM tbl_room WHERE room_code='$new_room_code' AND dept_code = '$RMdepartment' AND status = 'available' ";
                                $result_room = $conn->query($sql_room);


                                if ($result_room->num_rows > 0) {
                                    $row_room = $result_room->fetch_assoc();
                                    $room_in_charge = $row_room['room_in_charge'];
                                    $room_type = $row_room['room_type'];

                                    // Fetch professor name for the new professor code
                                    // Insert new schedule into the room schedule table for the new room code
                                    $update_room_sql = "
                                    UPDATE $sanitized_room_dept_code 
                                    SET 
                                        room_code = '$new_room_code',
                                        room_sched_code = '$new_room_sched_code',
                                        semester = '$semester',
                                        ay_code = '$ay_code',
                                        room_in_charge = '$room_in_charge',
                                        day = '$day',
                                        curriculum = '$section_curriculum',
                                        time_start = '$time_start',
                                        time_end = '$time_end',
                                        course_code = '$new_course_code',
                                        section_code = '$section_sched_code',
                                        prof_name = '$prof_name',
                                        prof_code = '$new_prof_code',
                                        room_type = '$room_type',
                                        dept_code = '$RMdepartment',
                                        class_type = '$new_class_type'
                                    WHERE 
                                        sec_sched_id = '$sec_sched_id' 
                                        AND semester = '$semester' 
                                        AND ay_code = '$ay_code';
                                ";


                                    if ($conn->query($update_room_sql) === FALSE) {
                                        echo "Error inserting room schedule: " . $conn->error;
                                    }
                                } else {
                                    echo "Room details not found for new_room_code: $new_room_code";
                                }

                            }

                        }


                        if ($college_code === $section_college_code && (empty($shared_sched) || $shared_sched == "prof")) {
                            if (!empty($new_prof_code)) {
                                $fetch_new_prof_hours_query = "SELECT teaching_hrs,prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$new_prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";
                                $new_prof_hours_result = $conn->query($fetch_new_prof_hours_query);

                                if ($new_prof_hours_result->num_rows > 0) {
                                    $new_prof_hours_row = $new_prof_hours_result->fetch_assoc();
                                    $new_prep_hours = $new_prof_hours_row['prep_hrs'];
                                }

                                $check_query = "SELECT * FROM $sanitized_prof_dept_code 
                                            WHERE prof_sched_code = '$new_prof_sched_code' 
                                            AND course_code = '$new_course_code' 
                                            AND semester = '$semester' 
                                            AND curriculum = '$section_curriculum' AND class_type = '$new_class_type'";
                                $check_result = $conn->query($check_query);


                                // If the professor has not taught this course in the current curriculum, add 1 prep hour
                                if ($check_result->num_rows > 0) {
                                    $updated_new_prep_hrs = $new_prep_hours;

                                } else {
                                    $updated_new_prep_hrs = $new_prep_hours + 1;
                                }


                                $new_prof_sched_code = $new_prof_code . "_" . $ay_code;

                                $sql_prof_type = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND ay_code = ? AND semester = ?";
                                $stmt = $conn->prepare($sql_prof_type);
                                $stmt->bind_param("ssis", $new_prof_code, $PFdepartment, $ay_code, $semester);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $prof_type = $row['prof_type'];

                                    if ($prof_type == 'Regular') {
                                        // If the professor is Regular, use the formula directly
                                        $new_consultation_hrs = $updated_new_teaching_hours / 3;
                                    } else {
                                        // If the professor is not Regular, check the teaching hours
                                        if ($updated_new_teaching_hours >= 18) {
                                            // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                            $new_consultation_hrs = 2;
                                        } else {
                                            // If teaching hours are less than 18, set consultation hours to 0
                                            $new_consultation_hrs = 0;
                                        }
                                    }

                                    // Optional: Debugging output
                                    // echo "Consultation Hours: " . $consultation_hrs;
                                } else {

                                    if ($updated_new_teaching_hours >= 18) {
                                        // If teaching hours are greater than or equal to 18, set consultation hours to 2
                                        $new_consultation_hrs = 2;
                                    } else {
                                        // If teaching hours are less than 18, set consultation hours to 0
                                        $new_consultation_hrs = 0;
                                    }
                                }

                                $stmt->close();
                                $update_prof_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs='$updated_new_teaching_hours', prep_hrs = '$updated_new_prep_hrs', consultation_hrs = '$new_consultation_hrs' WHERE prof_sched_code = '$new_prof_sched_code' and semester='$semester' AND dept_code = '$PFdepartment'";
                                $conn->query($update_prof_hours_query);

                                $insert_prof_sql = "INSERT INTO $sanitized_prof_dept_code (sec_sched_id, prof_sched_code,prof_code, time_start, time_end, day, curriculum, course_code, section_code, room_code, semester,dept_code,ay_code,class_type) 
                                            VALUES ('$sec_sched_id', '$new_prof_sched_code', '$new_prof_code', '$time_start', '$time_end', '$day','$section_curriculum', '$new_course_code', '$section_sched_code', '$new_room_code', '$semester', '$PFdepartment','$ay_code','$new_class_type')";

                                if ($conn->query($insert_prof_sql) === FALSE) {
                                    echo "Error updating professor schedule: " . $conn->error;
                                }

                                $checkSql = "SELECT 1 FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ?";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $checkStmt->bind_param("ssss", $new_prof_sched_code, $semester, $PFdepartment, $ay_code);
                                    $checkStmt->execute();
                                    $checkStmt->store_result();

                                    if ($checkStmt->num_rows > 0) {
                                        $checkStmt->close();
                                    } else {
                                        // Prepare SQL query
                                        $sql = "INSERT INTO tbl_prof_schedstatus (prof_sched_code, semester, dept_code, status, ay_code) VALUES (?, ?, ?, ?, ?)";

                                        // Initialize prepared statement
                                        if ($stmt = $conn->prepare($sql)) {
                                            $stmt->bind_param("sssss", $new_prof_sched_code, $semester, $PFdepartment, $status, $ay_code);
                                            $stmt->execute();
                                            $stmt->close();
                                        }
                                    }
                                }

                                // SQL query to insert data into tbl_rsched
                                $insert_sql = "INSERT IGNORE INTO tbl_psched (prof_sched_code, dept_code, prof_code, ay_code,semester) 
                                                           VALUES ('$new_prof_sched_code', '$PFdepartment', '$new_prof_code', '$ay_code','$semester')";

                                if ($conn->query($insert_sql) === FALSE) {
                                    echo "Error inserting record into tbl_psched: " . $conn->error;
                                }
                            }
                        }

                        if ($college_code === $section_college_code && (empty($shared_sched) || $shared_sched == "prof")) {

                            $fetch_prof_name_query = "SELECT prof_name FROM tbl_prof WHERE prof_code = '$new_prof_code' AND dept_code = '$PFdepartment' AND ay_code = '$ay_code' AND semester = '$semester'";
                            $prof_result = $conn->query($fetch_prof_name_query);

                            if ($prof_result->num_rows > 0) {
                                $prof_row = $prof_result->fetch_assoc();
                                $prof_name = $prof_row['prof_name'];
                            } else {
                                $prof_name = null;
                            }

                            if (!empty($new_prof_code)) {
                                $course_counter = 0;
                                // Check if assignment exists for the new professor
                                $check_sql = "SELECT id FROM tbl_assigned_course 
                                                WHERE dept_code = ? 
                                                AND course_code = ? AND year_level = ? AND semester = ? AND ay_code = ? AND prof_code = ?";
                                $stmt_check = $conn->prepare($check_sql);
                                $stmt_check->bind_param("ssssis", $PFdepartment, $new_course_code, $year_level, $semester, $ay_code, $new_prof_code);
                                $stmt_check->execute();
                                $stmt_check->store_result();

                                $prof_name = "";
                                if ($stmt_check->num_rows == 0) {
                                    // Insert the record if it does not exist
                                    $insert_sql = "INSERT INTO tbl_assigned_course 
                                                    (dept_code, prof_code,prof_name, course_code, year_level, semester, course_counter,ay_code) 
                                                    VALUES (?, ?, ?, ?, ?, ?,?,?)";
                                    $stmt_insert = $conn->prepare($insert_sql);
                                    $stmt_insert->bind_param("ssssssii", $PFdepartment, $new_prof_code, $prof_name, $new_course_code, $year_level, $semester, $course_counter, $ay_code);
                                    $stmt_insert->execute();
                                    $stmt_insert->close();
                                    // echo "Inserted new assignment for prof_code: $new_prof_code<br>";
                                }

                                $stmt_check->close();
                                // Increment course_counter for the new professor
                                $increment_counter_sql = "UPDATE tbl_assigned_course 
                                                            SET course_counter = course_counter + 1 
                                                            WHERE dept_code = ? AND course_code = ? AND prof_code = ? AND ay_code = ?";
                                $stmt_increment = $conn->prepare($increment_counter_sql);
                                $stmt_increment->bind_param("sssi", $PFdepartment, $new_course_code, $new_prof_code, $ay_code);
                                $stmt_increment->execute();
                                if ($stmt_increment->error) {
                                    echo "Error incrementing course_counter: " . $stmt_increment->error . "<br>";
                                } else {
                                    // echo "Incremented course_counter for prof_code: $new_prof_code,dept_code: $PFdepartment, course_code: $new_course_code<br>";
                                }
                                $stmt_increment->close();
                            }


                            // Decrement course_counter for the previous professor
                            if (!empty($prof_code)) {
                                $decrement_counter_sql = "UPDATE tbl_assigned_course 
                                                            SET course_counter = course_counter - 1 
                                                            WHERE prof_code = ? AND dept_code = ? AND course_code = ? AND ay_code =?";
                                $stmt_decrement = $conn->prepare($decrement_counter_sql);
                                $stmt_decrement->bind_param("sssi", $prof_code, $PFdepartment, $course_code, $ay_code);
                                $stmt_decrement->execute();
                                if ($stmt_decrement->error) {
                                    echo "Error decrementing course_counter: " . $stmt_decrement->error . "<br>";
                                } else {
                                    // echo "Decremented course_counter for prof_code: $prof_code, dept_code: $PFdepartment, course_code: $course_code<br>";
                                }
                                $stmt_decrement->close();
                            }
                        }
                    }

                    //notif update pwede na siguro dito basta lagyan mo lang ng condition na kapag section_dept_code != user_dept_code

                    echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var popup = document.getElementById('updatePopup');
                        popup.style.display = 'block';
                        setTimeout(function() {
                            popup.style.display = 'none';
                        }, 2000); // 2 seconds
                    });
                  </script>";
                }


            }



            //notifications for TBA prof
            $sql_secsched = "SELECT status FROM tbl_schedstatus WHERE section_sched_code='$section_sched_code'";
            $result_secsched = $conn->query($sql_secsched);

            if ($result_secsched === false) {
                // SQL error handling
                echo "SQL error: " . $conn->error;
            } elseif ($result_secsched->num_rows == 0) {
                // No matching schedule found
                echo "Invalid section code.";
            } else {
                // Fetch the result and get the status
                $row_secsched = $result_secsched->fetch_assoc();
                $status = trim($row_secsched['status']); // Trim whitespace

                // Debugging: log the status to verify
                // error_log("Schedule Status: '$status'"); // Check the server logs for this value

                // Only send notification if the status is NOT 'private' or 'draft'
                if ($status !== 'private' && $status !== 'draft') {
                    // Define the notification message and details
                    $notificationMessage = "The schedule of $course_code for $section in the $semester has been updated.";
                    $sender = $_SESSION['cvsu_email'];  // Assuming the sender is the current user (department secretary or admin)

                    $receiver = 'student';
                    $insertNotificationQuery = "INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code,ay_code) 
                                                    VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?,?)";
                    $notificationStmt = $conn->prepare($insertNotificationQuery);
                    $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code, $ay_code);
                    $notificationStmt->execute();

                    $receiver = $new_prof_code;
                    $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section_code, $semester, $dept_code, $ay_code);
                    $notificationStmt->execute();
                } else {
                    // Log message indicating that notifications were skipped due to private/draft status
                    // echo "No notification sent. Status is '$status'.";
                }
            }

        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_schedule'])) {

        // Validate token
        if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
            // Token is invalid, redirect to the same page
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        // Regenerate a new token to prevent reuse
        $_SESSION['token'] = bin2hex(random_bytes(32));


        // Retrieve and sanitize POST variables
        $sec_sched_id = isset($_POST['sec_sched_id']) ? $_POST['sec_sched_id'] : '';
        $section_sched_code = isset($_POST['section_sched_code']) ? $_POST['section_sched_code'] : '';
        $room_code = isset($_POST['room_code']) ? $_POST['room_code'] : '';
        $new_room_code = isset($_POST['new_room_code']) ? $_POST['new_room_code'] : '';
        $prof_code = isset($_POST['prof_code']) ? $_POST['prof_code'] : 'null';
        $time_start = $_POST['time_start'] ?? '';
        $time_end = $_POST['time_end'] ?? '';
        $room_sched_code = $room_code . "_" . $ay_code;
        $course_code = $_POST['new_course_code'];
        $class_type = $_POST['class_type'] ?? 'n/a';
        $computer_room ='';
        $fetch_info_query = "SELECT ay_code,dept_code,program_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code'";
        $result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ay_code = $row['ay_code'];
            $dept_code = $row['dept_code'];
            $program_code = $row['program_code'];
        } else {
            die("Error: No matching section schedule found for code '$section_sched_code'.");
        }

        $sql_year_level = "SELECT year_level FROM tbl_course WHERE course_code = ? AND dept_code=? AND program_code =?";
        $stmt_year_level = $conn->prepare($sql_year_level);
        $stmt_year_level->bind_param("sss", $course_code, $section_dept_code, $program_code);
        $stmt_year_level->execute();
        $stmt_year_level->bind_result($year_level);
        $stmt_year_level->fetch();
        $stmt_year_level->close();

        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

        $sql_secsched = "SELECT * FROM $sanitized_dept_code WHERE sec_sched_id='$sec_sched_id'";
        $result_secsched = $conn->query($sql_secsched);

        if ($result_secsched && $result_secsched->num_rows > 0) {
            $row_secsched = $result_secsched->fetch_assoc();
            $old_day = $row_secsched['day'];

            // Set values only if they are empty
            if (empty($time_start)) {
                $time_start = $row_secsched['time_start'];
            }
            if (empty($time_end)) {
                $time_end = $row_secsched['time_end'];
            }
            if (empty($course_code)) {
                $course_code = $row_secsched['course_code'];
            }
        }

        $sql_secsched = "SELECT section_code,ay_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
        $result_secsched = $conn->query($sql_secsched);

        if ($result_secsched->num_rows == 0) {
            echo "Invalid section sched code.";
            exit;
        }
        $row_secsched = $result_secsched->fetch_assoc();
        $academic_year = $row_secsched['ay_code'];
        $section = $row_secsched['section_code'];


        $sql = "SELECT lec_hrs, lab_hrs, allowed_rooms,computer_room FROM tbl_course WHERE course_code = ? AND program_code = ? AND year_level = ? AND semester = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $course_code, $program_code, $year_level, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lec_hrs = $row['lec_hrs'];
            $lab_hrs = $row['lab_hrs'];
            $computer_room = $row['computer_room'];

        }

        // Sanitize section schedule code

        // Delete from primary schedule table
        $delete_sql = "DELETE FROM $sanitized_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_sched_code = '$section_sched_code'";
        if ($conn->query($delete_sql) === FALSE) {
            echo "Error deleting schedule from $sanitized_dept_code: " . $conn->error;
        }

        //notif delete pwede na siguro dito basta lagyan mo lang ng condition na kapag section_dept_code != user_dept_code


        $fetch_info_query_room = "SELECT dept_code FROM tbl_room WHERE college_code = '$ccl_college_code' AND room_code = '$new_room_code'";
        $result_room = $conn->query($fetch_info_query_room);
        // $room_dept_code = null;

        if ($result_room->num_rows > 0) {
            $row = $result_room->fetch_assoc();
            $room_dept_code = $row['dept_code'];
        }
        if ($computer_room == 1 && $user_type == "CCL Head") {
            $plot_room_dept_code = $ccl_college_code;
            $plot_prof_dept_code = $room_dept_code;
            $prof_dept_code = $room_dept_code;
            $course_dept_code = $section_dept_code;
        } else {
            $plot_room_dept_code = $user_dept_code;
            $plot_prof_dept_code = $user_dept_code;
            $prof_dept_code = $user_dept_code;
            $course_dept_code = $user_dept_code;
        }

        if ($college_code === $section_college_code) {
            if (!empty($room_code)) {
                // Sanitize room schedule code

                $sanitized_room_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_roomsched_" . $plot_room_dept_code . "_" . $ay_code);


                $delete_old_room_sql = "DELETE FROM $sanitized_room_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_code = '$section_sched_code' AND semester = '$semester' AND ay_code = '$ay_code';";
                if ($conn->query($delete_old_room_sql) === FALSE) {
                    echo "Error deleting previous room schedule from $sanitized_room_dept_code: " . $conn->error;
                }
            }


            if (!empty($prof_code)) {

                $curriculum_check_query = "SELECT curriculum FROM tbl_section 
            WHERE section_code = '$section' 
            AND dept_code = '$dept_code'";
                $curriculum_result = $conn->query($curriculum_check_query);

                $section_curriculum = ''; // Initialize to store the curriculum type
                if ($curriculum_result->num_rows > 0) {
                    $curriculum_row = $curriculum_result->fetch_assoc();
                    $section_curriculum = $curriculum_row['curriculum'];
                }


                $fetch_info_query = "SELECT * FROM tbl_course 
            WHERE dept_code = '$course_dept_code' 
            AND course_code = '$course_code' 
            AND curriculum = '$section_curriculum' 
            AND semester = '$semester' 
            AND program_code = '$program_code' 
            AND year_level = '$year_level'";

                // Echo the query for debugging

                $result = $conn->query($fetch_info_query);

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $lec_hrs = $row['lec_hrs'];
                    $lab_hrs = $row['lab_hrs'];

                    // Display fetched values
                    // echo "<pre>Fetched Data:</pre>";
                    // echo "<pre>";
                    // print_r($row);
                    // echo "</pre>";
                } else {
                    echo "<pre>Error: No matching course found for code '$section_sched_code'.</pre>";
                }

                if ($class_type === 'lec') {
                    $teaching_hrs = $lec_hrs; // Use '=' for assignment
                } else {
                    $teaching_hrs = $lab_hrs; // Use '=' for assignment
                }




                $prof_sched_code = $prof_code . "_" . $academic_year;
                $sanitized_prof_dept_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$plot_prof_dept_code}_{$ay_code}");

                // Delete from professor schedule table based on sec_sched_id
                $delete_prof_sql = "DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id='$sec_sched_id' AND section_code = '$section_sched_code' AND semester = '$semester' ;";
                if ($conn->query($delete_prof_sql) === FALSE) {
                    echo "Error deleting schedule from $sanitized_prof_sched_code: " . $conn->error;
                }

                $time_start_dt = new DateTime($time_start);
                $time_end_dt = new DateTime($time_end);
                $duration = $time_start_dt->diff($time_end_dt);
                $duration_hours = $duration->h + ($duration->i / 60);


                // $sql_secsched = "SELECT section_code,ay_code FROM tbl_secschedlist WHERE section_sched_code='$section_sched_code'";
                // $result_secsched = $conn->query($sql_secsched);

                $prof_sched_code = $prof_code . "_" . $ay_code;
                // Fetch current teaching hours and maximum teaching hours of the professors
                $fetch_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester' AND dept_code = '$prof_dept_code'";
                $prof_hours_result = $conn->query($fetch_prof_hours_query);

                if ($prof_hours_result->num_rows > 0) {
                    $prof_hours_row = $prof_hours_result->fetch_assoc();
                    $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                    $prep_hours = $prof_hours_row['prep_hrs'];

                    // Check if adding the new schedule exceeds the maximum teaching hour
                } else {
                    die("Error: Professor details not found.");
                }

                $check_query = "SELECT * FROM $sanitized_prof_dept_code 
            WHERE prof_sched_code = '$prof_sched_code' 
            AND course_code = '$course_code' 
            AND semester = '$semester' 
            AND curriculum = '$section_curriculum' AND class_type = '$class_type'";
                $check_result = $conn->query($check_query);


                // If the professor has not taught this course in the current curriculum, add 1 prep hour
                if ($check_result->num_rows === 0) {
                    while ($row = $check_result->fetch_assoc()) {
                        // echo "<pre>";
                        // print_r($row);
                        // echo "</pre>";
                    }
                    $prep_hours = $prep_hours - 1;
                } else {
                    $prep_hours = $prep_hours;
                }

                $prof_sched_code = $prof_code . "_" . $ay_code;
                $new_teaching_hours = $current_teaching_hours - $duration_hours;

                $sql_prof_type = "SELECT prof_type FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND ay_code = ? AND semester = ? ";
                $stmt = $conn->prepare($sql_prof_type);
                $stmt->bind_param("ssis", $prof_code, $prof_dept_code, $ay_code, $semester);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $prof_type = $row['prof_type'];

                    if ($prof_type == 'Regular') {
                        // If the professor is Regular, use the formula directly
                        $consultation_hrs = $new_teaching_hours / 3;
                    } else {
                        // If the professor is not Regular, check the teaching hours
                        if ($new_teaching_hours >= 18) {
                            // If teaching hours are greater than or equal to 18, set consultation hours to 2
                            $consultation_hrs = 2;
                        } else {
                            // If teaching hours are less than 18, set consultation hours to 0
                            $consultation_hrs = 0;
                        }
                    }

                    // Optional: Debugging output
                    // echo "Consultation Hours: " . $consultation_hrs;
                }

                $stmt->close();


                // $consultation_hrs = $new_teaching_hours / 3;

                $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', prep_hrs = '$prep_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester ='$semester' AND dept_code = '$prof_dept_code'";

                if ($conn->query($update_hours_query) === FALSE) {
                    echo "Teaching hours not updated for plotting.";
                }




                // Prepare the SQL statement for checking existing course
                $check_sql = "
            SELECT course_counter 
            FROM tbl_assigned_course 
            WHERE dept_code = ? 
            AND prof_code = ? 
            AND course_code = ? 
            AND year_level = ? 
            AND semester = ? AND ay_code = ?";
                // Bind parameters
                $stmt_check = $conn->prepare($check_sql);
                $stmt_check->bind_param("sssisi", $prof_dept_code, $prof_code, $course_code, $year_level, $semester, $ay_code);


                // Execute the query and store the result
                $stmt_check->execute();
                $stmt_check->store_result();  // Required to count rows after executing the query

                if ($stmt_check->num_rows > 0) {
                    // Bind the result
                    $stmt_check->bind_result($course_counter);
                    $stmt_check->fetch();

                    // Decrement the course_counter by 1, ensuring it does not go below 0
                    $course_counter = max(0, $course_counter - 1);

                    // Update the record with the decremented course_counter
                    $update_sql = "
                UPDATE tbl_assigned_course 
                SET course_counter = ?
                WHERE dept_code = ? 
                AND prof_code = ? 
                AND course_code = ? 
                AND year_level = ? 
                AND semester = ? AND ay_code = ?";

                    $stmt_update = $conn->prepare($update_sql);
                    $stmt_update->bind_param("isssisi", $course_counter, $prof_dept_code, $prof_code, $course_code, $year_level, $semester, $ay_code);
                    $stmt_update->execute();
                    $stmt_update->close();
                } else {
                    echo "No matching record found.<br>";
                    echo "Values: <br>";
                    echo "Course Counter: " . htmlspecialchars($course_counter) . "<br>";
                    echo "Department Code: " . htmlspecialchars($prof_dept_code) . "<br>";
                    echo "Professor Code: " . htmlspecialchars($prof_code) . "<br>";
                    echo "Course Code: " . htmlspecialchars($course_code) . "<br>";
                    echo "Year Level: " . htmlspecialchars($year_level) . "<br>";
                    echo "Semester: " . htmlspecialchars($semester) . "<br>";
                }
                $stmt_check->close();
            }
        }
        //notification
        $sql_secsched = "SELECT status FROM tbl_schedstatus WHERE section_sched_code='$section_sched_code'";
        $result_secsched = $conn->query($sql_secsched);

        if ($result_secsched === false) {
            // SQL error handling
            echo "SQL error: " . $conn->error;
        } elseif ($result_secsched->num_rows == 0) {
            // No matching schedule found
            echo "Invalid section code.";
        } else {
            // Fetch the result and get the status
            $row_secsched = $result_secsched->fetch_assoc();
            $status = trim($row_secsched['status']); // Trim whitespace

            // Debugging: log the status to verify
            error_log("Schedule Status: '$status'"); // Check the server logs for this value

            // Only send notification if the status is NOT 'private' or 'draft'
            if ($status !== 'private' && $status !== 'draft') {
                // Define the notification message and details
                $notificationMessage = "A schedule of $course_code for $section in the $semester has been deleted. ";
                $sender = $_SESSION['cvsu_email'];  // Assuming the sender is the current user (department secretary or admin)
                $receiver = 'student';
                $insertNotificationQuery = "INSERT INTO tbl_stud_prof_notif (message, sched_code, receiver_type, sender_email, is_read, date_sent, sec_ro_prof_code, semester, dept_code,ay_code) 
                            VALUES (?, ?, ?, ?, 0, NOW(), ?, ?, ?,?)";
                $notificationStmt = $conn->prepare($insertNotificationQuery);
                $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section, $semester, $dept_code, $ay_code);
                $notificationStmt->execute();
                if (!empty($prof_code)) {
                    $receiver = $prof_code;
                    $notificationStmt->bind_param('ssssssss', $notificationMessage, $section_sched_code, $receiver, $sender, $section, $semester, $dept_code, $ay_code);
                    $notificationStmt->execute();
                    echo $sender;
                }
            } else {
                // Log message indicating that notifications were skipped due to private/draft status
                // echo "No notification sent. Status is '$status'.";
            }
        }

        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            var popup = document.getElementById('deletePopup');
            popup.style.display = 'block';
            setTimeout(function() {
                popup.style.display = 'none';
            }, 2000); // 2 seconds
        });
      </script>";
    }
}
?>