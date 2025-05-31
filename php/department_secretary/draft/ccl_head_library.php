<?php
include("../../config.php");
session_start();

// Check if dept_code is set in the session, if not, redirect to login.php

if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Department Secretary' && $_SESSION['user_type'] != 'CCL Head')) {
    header("Location: ./login/login.php");
    exit();
}


if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';
$_SESSION['last_page'] = 'ccl_head_library.php';



// Replace with your actual success page URL
$error_redirect_url = 'ccl_head_library.php';

$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['edit'])) {
        $_SESSION['section_sched_code'] = $_POST['section_sched_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
        $_SESSION['section_code'] = $_POST['section_code'];
        header("Location: ../create_sched/plotSchedule.php");
        exit();
    } elseif (isset($_POST['delete'])) {
        if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
            // Token is invalid, redirect to error page
            header("Location: $error_redirect_url");
            exit;
        }
        $_SESSION['token'] = bin2hex(random_bytes(32));


        $section_sched_code = $_POST['section_sched_code'];

        $fetch_info_query = "SELECT dept_code,section_code,program_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code' AND ay_code = '$ay_code'";
        $result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $section_dept_code = $row['dept_code'];
            $section_code = $row['section_code'];
            $program_code = $row['program_code'];
        } else {
            die("Error: No matching section schedule found for code '$section_sched_code'.");
        }

        $sanitized_dept_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$section_dept_code}_{$ay_code}");

        // Fetch data from section schedule table
        $sql = " SELECT  * FROM $sanitized_dept_code  WHERE section_sched_code = ? AND semester = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $section_sched_code, $semester);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows >= 0) {

            while ($row = $result->fetch_assoc()) {
                $sec_sched_id = $row['sec_sched_id'];
                $room_code = $row['room_code'];
                $prof_code = $row['prof_code'];
                $dept_code = $row['dept_code'];
                $class_type = $row['class_type'];

                $sql_secsched = "SELECT shared_sched, shared_to,course_code,dept_code FROM $sanitized_dept_code WHERE sec_sched_id = ? AND section_sched_code = ?";
                $stmt = $conn->prepare($sql_secsched);

                if ($stmt) {
                    $stmt->bind_param("ss", $sec_sched_id, $section_sched_code); // Assuming sec_sched_id is an integer
                    $stmt->execute();
                    $result_secsched = $stmt->get_result();

                    if ($row_secsched = $result_secsched->fetch_assoc()) {
                        $row_shared_sched = $row_secsched['shared_sched'];
                        $row_shared_to = $row_secsched['shared_to'];
                        $course_code = $row_secsched['course_code'];
                        $Cdepartment = $row_secsched['dept_code'];
                        $dept_code_internal = $row_secsched['dept_code'];

                        // Retrieve department code based on shared email
                        $sql_dept = "SELECT dept_code FROM tbl_prof_acc WHERE cvsu_email = ?";
                        $stmt_dept = $conn->prepare($sql_dept);

                        if ($stmt_dept) {
                            $stmt_dept->bind_param("s", $row_shared_to);
                            $stmt_dept->execute();
                            $result_dept = $stmt_dept->get_result();

                            if ($result_dept->num_rows > 0) {
                                $row_dept = $result_dept->fetch_assoc();
                                $row_shared_dept_code = $row_dept['dept_code'];
                            } else {
                                echo "No matching department found for the provided email.";
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

                $curriculum_check_query = "SELECT curriculum,program_code FROM tbl_section WHERE section_code = '$section_code' AND dept_code = '$section_dept_code'";
                $curriculum_result = $conn->query($curriculum_check_query);

                $section_curriculum = ''; // Initialize to store the curriculum type
                if ($curriculum_result->num_rows > 0) {
                    $curriculum_row = $curriculum_result->fetch_assoc();
                    $section_curriculum = $curriculum_row['curriculum'];
                    $program_code = $curriculum_row['program_code'];
                }

                $sql_year_level = "SELECT year_level FROM tbl_section WHERE section_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                $stmt_year_level = $conn->prepare($sql_year_level);
                $stmt_year_level->bind_param("ssss", $section_code, $section_dept_code, $semester, $ay_code);
                $stmt_year_level->execute();
                $stmt_year_level->bind_result($year_level);
                $stmt_year_level->fetch();
                $stmt_year_level->close();

                if (empty($row_shared_sched)) {
                    // If no shared schedule is defined
                    $RMdepartment = $dept_code_internal;
                    $PFdepartment = $dept_code_internal;
                    echo "empty";
                }
                if ($row_shared_sched === "room") {
                    // If the shared schedule is for rooms
                    if ($row_shared_to === $current_user_email) {
                        $PFdepartment = $dept_code_internal;
                    } else {
                        $PFdepartment = $dept_code;

                    }
                    $RMdepartment = $row_shared_dept_code;
                    echo "room";
                }

                if ($row_shared_sched === "prof") {
                    //DIT
                    if ($row_shared_to === $current_user_email) {
                        $RMdepartment = $dept_code_internal;

                    } else {
                        $RMdepartment = $dept_code;
                    }

                    $PFdepartment = $row_shared_dept_code;
                    echo "prof ";
                }
                // Output results
                // echo $RMdepartment;
                // echo $PFdepartment;
                // echo $row_shared_sched;
                // echo $row_shared_to;
                // echo $sanitized_dept_code;

                //     $fetch_info_query = "SELECT * FROM tbl_course 
                // WHERE dept_code = '$Cdepartment' 
                // AND course_code = '$course_code' 
                // AND curriculum = '$section_curriculum' 
                // AND semester = '$semester' 
                // AND program_code = '$program_code' 
                // AND year_level = '$year_level'";

                //     $result_course = $conn->query($fetch_info_query);

                //     if ($result_course->num_rows > 0) {
                //         $row = $result_course->fetch_assoc();
                //         $lec_hrs = $row['lec_hrs'];
                //         $lab_hrs = $row['lab_hrs'];
                //     } else {
                //         echo "<pre>Error: No matching course found for code '$section_sched_code'.</pre>";
                //     }

                //     if ($class_type === 'lec') {
                //         $teaching_hrs = $lec_hrs; // Use '=' for assignment
                //     } else {
                //         $teaching_hrs = $lab_hrs; // Use '=' for assignment
                //     }

                $sql_delete_section = "  DELETE FROM $sanitized_dept_code  WHERE sec_sched_id = ? AND semester = ? AND section_sched_code = ?";
                $stmt_delete_section = $conn->prepare($sql_delete_section);
                $stmt_delete_section->bind_param('sss', $sec_sched_id, $semester, $section_sched_code);
                if ($stmt_delete_section->execute()) {
                    echo "Section schedule record deleted successfully.<br>";
                } else {
                    echo "Error deleting section schedule record: " . $stmt_delete_section->error . "<br>";
                }
                $stmt_delete_section->close();


                // Delete from schedstatus table
                $sql_delete_schedstatus = " DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
                $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
                if ($stmt_delete_schedstatus->execute()) {
                    echo "Schedstatus record deleted successfully.<br>";
                } else {
                    echo "Error deleting schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
                }

                if ($room_code !== 'TBA') {
                    $sanitized_room_dept_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$RMdepartment}_{$ay_code}");
                    $room_sched_code = $room_code . "_" . $ay_code;
                    // Delete from section schedule table

                    $stmt_delete_schedstatus->close();
                    // Fetch and delete from room schedule table
                    $sql_room = " SELECT * FROM $sanitized_room_dept_code WHERE room_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code =?";
                    echo "SQL Statement: " . $sql_room . "<br>";
                    echo "room_sched_code: " . $room_sched_code . "<br>";
                    echo "sec_sched_id: " . $sec_sched_id . "<br>";
                    echo "semester: " . $semester . "<br>";
                    echo "dept_code: " . $RMdepartment . "<br>";
                    $stmt_room = $conn->prepare($sql_room);
                    $stmt_room->bind_param('ssss', $room_sched_code, $semester, $sec_sched_id, $RMdepartment);
                    $stmt_room->execute();
                    $result_room = $stmt_room->get_result();

                    if ($result_room->num_rows > 0) {

                        $sql_delete_room = "  DELETE FROM $sanitized_room_dept_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                        echo $sql_delete_room;
                        echo "SQL Statement: " . $sql_delete_room . "<br>";
                        echo "sec_sched_id: " . $sec_sched_id . "<br>";
                        echo "semester: " . $semester . "<br>";
                        echo "dept_code: " . $RMdepartment . "<br>";
                        $stmt_delete_room = $conn->prepare($sql_delete_room);
                        $stmt_delete_room->bind_param('ssss', $sec_sched_id, $semester, $RMdepartment, $section_sched_code);

                        if ($stmt_delete_room->execute()) {
                            echo "Room schedule record deleted successfully.<br>";
                        } else {
                            echo "Error deleting room schedule record: " . $stmt_delete_room->error . "<br>";
                        }
                        $stmt_delete_room->close();

                        // Drop the room schedule table if empty
                        $sql_check_empty_room = "SELECT COUNT(*) AS row_count FROM $sanitized_room_dept_code WHERE room_code = '$room_code' AND semester = '$semester'";
                        $stmt_check_empty_room = $conn->prepare($sql_check_empty_room);
                        $stmt_check_empty_room->execute();
                        $result_check_empty_room = $stmt_check_empty_room->get_result();
                        $row_count_room = $result_check_empty_room->fetch_assoc()['row_count'];
                        $stmt_check_empty_room->close();

                        if ($row_count_room == 0) {

                            $sql_delete_schedlist = " DELETE FROM tbl_rsched WHERE room_sched_code = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_schedlist = $conn->prepare($sql_delete_schedlist);
                            $stmt_delete_schedlist->bind_param('sss', $room_sched_code, $ay_code, $RMdepartment);
                            if ($stmt_delete_schedlist->execute()) {
                                echo "Room schedlist record deleted successfully.<br>";
                            } else {
                                echo "Error deleting room schedlist record: " . $stmt_delete_schedlist->error . "<br>";
                            }
                            $stmt_delete_schedlist->close();

                            // Delete corresponding entries from tbl_schedstatus
                            $sql_delete_schedstatus = " DELETE FROM tbl_room_schedstatus WHERE room_sched_code = ? AND semester = ? AND ay_code = ? AND dept_code = ?";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('ssss', $room_sched_code, $semester, $ay_code, $RMdepartment);
                            if ($stmt_delete_schedstatus->execute()) {
                                echo "Room schedstatus record deleted successfully.<br>";
                            } else {
                                echo "Error deleting room schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
                            }
                            $stmt_delete_schedstatus->close();
                        }
                    } else {
                        echo "No room schedule records found.<br>";
                    }
                    $stmt_room->close();
                }

                if ($prof_code !== 'TBA') {
                    $sanitized_prof_dept_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_Psched_" . $PFdepartment . "_" . $ay_code);
                    $sql_sched = "SELECT time_start, time_end FROM $sanitized_prof_dept_code WHERE sec_sched_id='$sec_sched_id' AND dept_code = '$PFdepartment' AND section_code = '$section_sched_code'";
                    $result_sched = $conn->query($sql_sched);
                    $row_sched = $result_sched->fetch_assoc();
                    $time_start = $row_sched['time_start'];
                    $time_end = $row_sched['time_end'];

                    $time_start_dt = new DateTime($time_start);
                    $time_end_dt = new DateTime($time_end);
                    $duration = $time_start_dt->diff($time_end_dt);
                    $duration_hours = $duration->h + ($duration->i / 60);


                    $prof_sched_code = $prof_code . "_" . $ay_code;
                    // Fetch and delete from professor schedule table
                    $sql_prof = " SELECT * FROM $sanitized_prof_dept_code WHERE prof_sched_code = ? AND semester = ? AND sec_sched_id = ? AND dept_code = ?";
                    $stmt_prof = $conn->prepare($sql_prof);
                    $stmt_prof->bind_param('ssss', $prof_sched_code, $semester, $sec_sched_id, $PFdepartment);
                    $stmt_prof->execute();
                    $result_prof = $stmt_prof->get_result();


                    echo "SQL Statement: " . $sql_room . "<br>";
                    echo "prof_sched_code: " . $prof_sched_code . "<br>";
                    echo "sec_sched_id: " . $sec_sched_id . "<br>";
                    echo "semester: " . $semester . "<br>";
                    echo "dept_code: " . $PFdepartment . "<br>";

                    if ($result_prof->num_rows > 0) {

                        $sql_delete_prof = " DELETE FROM $sanitized_prof_dept_code WHERE sec_sched_id = ? AND semester = ? AND dept_code = ? AND section_code = ?";
                        $stmt_delete_prof = $conn->prepare($sql_delete_prof);
                        $stmt_delete_prof->bind_param('ssss', $sec_sched_id, $semester, $PFdepartment, $section_sched_code);
                        if ($stmt_delete_prof->execute()) {
                            echo "Professor schedule record deleted successfully.<br>";
                        } else {
                            echo "Error deleting professor schedule record: " . $stmt_delete_prof->error . "<br>";
                        }


                        $fetch_prof_hours_query = "SELECT teaching_hrs, prep_hrs FROM tbl_psched_counter WHERE prof_sched_code = '$prof_sched_code' and semester='$semester'  AND dept_code = '$PFdepartment'";
                        $prof_hours_result = $conn->query($fetch_prof_hours_query);

                        if ($prof_hours_result->num_rows > 0) {
                            $prof_hours_row = $prof_hours_result->fetch_assoc();
                            $current_teaching_hours = $prof_hours_row['teaching_hrs'];
                            $prep_hours = $prof_hours_row['prep_hrs'];

                            $check_query_prep = "SELECT * FROM $sanitized_prof_dept_code 
                                WHERE prof_sched_code = '$prof_sched_code' 
                                AND course_code = '$course_code' 
                                AND semester = '$semester' 
                                AND curriculum = '$section_curriculum' AND class_type = '$class_type'";
                            $check_result_prep = $conn->query($check_query_prep);


                            // If the professor has not taught this course in the current curriculum, add 1 prep hour
                            if ($check_result_prep->num_rows === 0) {
                                while ($row = $check_result_prep->fetch_assoc()) {
                                    echo "<pre>";
                                    print_r($row);
                                    echo "</pre>";
                                }
                                $prep_hours = $prep_hours - 1;
                            } else {
                                $prep_hours = $prep_hours;
                            }


                            $prof_sched_code = $prof_code . "_" . $ay_code;
                            $new_teaching_hours = $current_teaching_hours - $duration_hours;

                            $sql_prof_type_consult = "SELECT prof_type FROM tbl_prof WHERE prof_code = ?  AND dept_code = ? ";
                            $stmt_consultation = $conn->prepare($sql_prof_type_consult);
                            $stmt_consultation->bind_param("ss", $prof_code, $PFdepartment);
                            $stmt_consultation->execute();
                            $result_consultation = $stmt_consultation->get_result();

                            if ($result_consultation->num_rows > 0) {
                                $row = $result_consultation->fetch_assoc();
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
                                echo "Consultation Hours: " . $consultation_hrs;
                            } else {
                                echo "Professor not found.";
                            }

                            $stmt_consultation->close();

                            $update_hours_query = "UPDATE tbl_psched_counter SET teaching_hrs = '$new_teaching_hours', prep_hrs = '$prep_hours', consultation_hrs = '$consultation_hrs' WHERE prof_sched_code = '$prof_sched_code' and semester ='$semester' AND dept_code = '$PFdepartment' ";

                            if ($conn->query($update_hours_query) === TRUE) {
                                echo "Teaching hours updated successfully for plotting.<br>";
                            } else {
                                echo "Error updating teaching hours: " . $conn->error . "<br>";
                            }
                        } else {
                            die("Error: Professor details not found.");
                        }

                        $course_counter_update_query = " UPDATE tbl_assigned_course  SET course_counter = course_counter - 1 WHERE prof_code = ? AND course_code = ? AND semester = ? AND dept_code = ?";
                        $stmt_course_counter = $conn->prepare($course_counter_update_query);
                        $stmt_course_counter->bind_param('ssss', $prof_code, $course_code, $semester, $PFdepartment);
                        if ($stmt_course_counter->execute()) {
                            echo "Course counter updated successfully.<br>";
                        } else {
                            echo "Error updating course counter: " . $stmt_course_counter->error . "<br>";
                        }
                        $stmt_course_counter->close();

                        //prof delete here


                        $stmt_delete_prof->close();
                        // Check if professor schedule table is empty


                        $sql_check_no_prof = "SELECT COUNT(*) AS row_count FROM $sanitized_prof_dept_code  WHERE prof_code = '$prof_code' AND semester = '$semester'";
                        echo "Executing SQL: $sql_check_no_prof<br>";
                        $stmt_check_no_prof = $conn->prepare($sql_check_no_prof);
                        $stmt_check_no_prof->execute();
                        $result_check_no_prof = $stmt_check_no_prof->get_result();
                        $row_count_prof_no = $result_check_no_prof->fetch_assoc()['row_count'];
                        $stmt_check_no_prof->close();

                        if ($row_count_prof_no == 0) {
                            $sql_delete_schedlist = "DELETE FROM tbl_psched WHERE prof_sched_code = ? AND ay_code = ? AND dept_code = ?";
                            echo "Executing SQL: $sql_delete_schedlist with prof_sched_code=$prof_sched_code, ay_code=$ay_code, dept_code=$dept_code<br>";
                            $stmt_delete_schedlist = $conn->prepare($sql_delete_schedlist);
                            $stmt_delete_schedlist->bind_param('sss', $prof_sched_code, $ay_code, $dept_code);
                            $stmt_delete_schedlist->execute();
                            $stmt_delete_schedlist->close();

                            // Delete from tbl_prof_schedstatus
                            $sql_delete_schedstatus = "DELETE FROM tbl_prof_schedstatus WHERE prof_sched_code = ? AND semester = ? AND ay_code = ?";
                            echo "Executing SQL: $sql_delete_schedstatus with prof_sched_code=$prof_sched_code, semester=$semester, and ay_code=$ay_code<br>";
                            $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                            $stmt_delete_schedstatus->bind_param('sss', $prof_sched_code, $semester, $ay_code);
                            $stmt_delete_schedstatus->execute();
                            $stmt_delete_schedstatus->close();
                        }

                    } else {
                        echo "No professor schedule records found.<br>";
                    }
                    $stmt_prof->close();
                }
            }

            $sql_check_empty = "SELECT COUNT(*) AS row_count FROM $sanitized_dept_code WHERE section_sched_code = '$section_sched_code' AND semester = '$semester' ";
            $stmt_check_empty = $conn->prepare($sql_check_empty);
            $stmt_check_empty->execute();
            $result_check_empty = $stmt_check_empty->get_result();
            $row_count = $result_check_empty->fetch_assoc()['row_count'];
            $stmt_check_empty->close();

            // Drop the table if it's empty
            if ($row_count == 0) {
                $sql_delete_schedstatus = " DELETE FROM tbl_secschedlist WHERE section_sched_code = ?  AND dept_code = ?";
                $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                $stmt_delete_schedstatus->bind_param('ss', $section_sched_code, $dept_code);
                $stmt_delete_schedstatus->execute();
                $stmt_delete_schedstatus->close();

                $sql_delete_schedstatus = " DELETE FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND ay_code = ?";
                $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
                if ($stmt_delete_schedstatus->execute()) {
                    echo "Schedstatus record deleted successfully.<br>";
                } else {
                    echo "Error deleting schedstatus record: " . $stmt_delete_schedstatus->error . "<br>";
                }
                $stmt_delete_schedstatus->close();

                $sql_delete_schedstatus = " DELETE FROM tbl_shared_sched WHERE shared_section = ? AND semester = ? AND ay_code = ?";
                $stmt_delete_schedstatus = $conn->prepare($sql_delete_schedstatus);
                $stmt_delete_schedstatus->bind_param('sss', $section_sched_code, $semester, $ay_code);
                $stmt_delete_schedstatus->execute();
                $stmt_delete_schedstatus->close();

            } else {
                echo "No section schedule records found.<br>";
            }
            //  header("Location: /SchedSys%20-%20abby/php/department_secretary/dept_sec.php");
// exit();
        }
    }

}



$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';

$search_ay_code = isset($_POST['search_ay_code']) ? trim($_POST['search_ay_code']) : $ay_code;
$search_semester = isset($_POST['search_semester']) ? trim($_POST['search_semester']) : $semester;
$search_section = isset($_POST['search_section']) ? trim($_POST['search_section']) : '';
$search_level = isset($_POST['search_level']) ? trim($_POST['search_level']) : '';
$search_program = isset($_POST['search_program']) ? trim($_POST['search_program']) : '';
$search_department = isset($_POST['search_department']) ? trim($_POST['search_department']) : '';
$search_status = isset($_POST['search_status']) ? trim($_POST['search_status']) : '';


// Fetch schedules with draft status from the database based on search criteria and session data

// if (isset($_POST['dept_code'])) {
//     $dept_code = $_POST['dept_code'];

//     // Fetch programs based on department code
//     $sql = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $dept_code);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     $programs = '<option value="">Select Program</option>';

//     while ($row = $result->fetch_assoc()) {
//         $programs .= '<option value="' . htmlspecialchars($row['program_code']) . '">' . htmlspecialchars($row['program_code']) . '</option>';
//     }

//     // Return the program options as a JSON response
//     echo json_encode(['programs' => $programs]);
//     exit; // Stop further processing for AJAX requests
// }


// Check if dept_code is set (as we need it for fetching programs)
$lastSelectedProgram = isset($_POST['search_program']) ? $_POST['search_program'] : ''; // Get the last selected program from session
// echo $lastSelectedProgram;

// if (isset($_POST['dept_code'])) {
//     $dept_code = $_POST['dept_code'];

//     // Query to get programs for the selected department
//     $sql = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $dept_code);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     $programs = '<option value="">All Program</option>';
//     $lastSelectedProgram =isset($_POST['search_program']) ? $_POST['search_program'] : ''; // Get the last selected program from session
//     // Loop through programs and set the last selected program as selected
//     while ($row = $result->fetch_assoc()) {
//         $selected = ($row['program_code'] == $lastSelectedProgram) ? 'selected' : '';// Check if this is the last selected program
//         $programs .= '<option value="' . htmlspecialchars($row['program_code']) . '" ' . $selected . '>' . htmlspecialchars($row['program_code']) . '</option>';
//     }
//     if (empty($lastSelectedProgram) && $lastSelectedProgram === "" ) {
//         $programs = '<option value="">Select Program</option>';
//     }
//     // Return the program options and the last selected program as a JSON response
//     echo json_encode([
//         'programs' => $programs
//     ]);
//     exit; // Stop further processing for AJAX requests
// }

// // If a program is selected, save it to the session
// if (isset($_POST['search_program'])) {
//     $program_code = $_POST['search_program'];
//     $_SESSION['search_program'] = $program_code; // Store the selected program in session
//     echo "sad";
// }

if (isset($_POST['search_program'])) {
    $program_code = $_POST['search_program'];
    $_SESSION['search_program'] = $program_code; // Store the selected program in session
    // echo "Program selected: " . $program_code;
}


$search_program = isset($_SESSION['search_program']) ? $_SESSION['search_program'] : ''; // Get the last selected program from session

if (isset($_POST['dept_code'])) {
    $dept_code = $_POST['dept_code'];

    // Query to get programs for the selected department
    $sql = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dept_code);
    $stmt->execute();
    $result = $stmt->get_result();

    // Add "All Program" as an option
    $programs = '<option value="">All Program</option>';

    // If there is a program selected, use it; otherwise, default to empty
    // Loop through programs and set the last selected program as selected
    while ($row = $result->fetch_assoc()) {
        // Set the selected attribute if this is the last selected program
        $selected = ($row['program_code'] == $search_program) ? 'selected' : '';
        $programs .= '<option value="' . htmlspecialchars($row['program_code']) . '" ' . $selected . '>' . htmlspecialchars($row['program_code']) . '</option>';
    }

    // Return the program options as a JSON response
    echo json_encode([
        'programs' => $programs
    ]);
    exit; // Stop further processing for AJAX requests
}

// // If a program is selected, save it to the session
// if (isset($_POST['search_level'])) {
//     $search_level = $_POST['search_level'];
//     $_SESSION['search_level'] = $search_level; // Store the selected program in session
//     // echo "search_level selected: " . $search_level;
// }

// $search_level = isset($_SESSION['search_level']) ? $_SESSION['search_level'] : ''; // Get the last selected year level from session

// if (isset($_POST['program_code'])) {
//     $program_code = $_POST['program_code'];

//     // Query to get the maximum number of years for the selected program
//     $sql = "SELECT MAX(num_year) AS max_num_year FROM tbl_program WHERE program_code = ?";
//     $stmt = $conn->prepare($sql);
//     $stmt->bind_param("s", $program_code);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     $max_num_year = 0;
//     if ($row = $result->fetch_assoc()) {
//         $max_num_year = $row['max_num_year'];
//     }

//     // Return the maximum number of years and the last selected year level
//     echo json_encode([
//         'num_year' => $max_num_year
//     ]);

//     exit; // Stop further processing for AJAX requests
// }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS - Draft</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/draft/draft.css">



</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php"); ?>


    <div class="header">
        <h2 class="title"> <i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i> SECTION SCHEDULES</h2>
        <div id="create" class="form-group text-center">
            <label><?php echo "AY:" . " " . $ay_name; ?></label> <br> <label><?php echo $semester; ?></label> <br>
        </div>
    </div>

    <div class="container mt-8">
        <br>
        <form method="POST" action="ccl_head_library.php" class="row mb-4">
            <div class="col-md-2">

            </div>
            <div class="col-md-2">
                <select class="form-control" id="search_department" name="search_department">
                    <option value="">All Department</option>
                    <?php
                    // Fetch departments for the initial dropdown
                    $sql = "SELECT dept_code, dept_name FROM tbl_department WHERE college_code = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $college_code); // Assuming $college_code is defined
                    $stmt->execute();
                    $result_dept = $stmt->get_result();

                    // Get the last selected department from localStorage or session
                    $lastSelectedDepartment = isset($_POST['search_department']) ? $_POST['search_department'] : ''; // Example of fetching from session
                    
                    while ($row = $result_dept->fetch_assoc()) {
                        $selected = ($row['dept_code'] == $lastSelectedDepartment) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($row['dept_code']) . '" ' . $selected . '>' . htmlspecialchars($row['dept_code']) . '</option>';
                    }
                    $stmt->close();
                    ?>
                </select>

            </div>
            <div class="col-md-2">
                <select class="form-control" id="search_program" name="search_program">
                    <option value="">All Program</option>
                </select>
            </div>
            <?php
            // Save the selected status to the session when the form is submitted
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_status'])) {
                $_SESSION['search_status'] = $_POST['search_status'];
            }

            // Retrieve the last selected status from the session
            $selected_status = $_SESSION['search_status'] ?? '';
            ?>

            <div class="col-md-2">
                <select class="form-control" id="search_status" name="search_status">
                    <option value="" <?= $selected_status === '' ? 'selected' : '' ?>>All Status</option>
                    <option value="draft" <?= $selected_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="private" <?= $selected_status === 'private' ? 'selected' : '' ?>>Private</option>
                    <option value="public" <?= $selected_status === 'public' ? 'selected' : '' ?>>Public</option>
                </select>
            </div>



            <script>
                $(document).ready(function () {
                    // Function to fetch programs based on the selected department
                    function loadPrograms(deptCode) {
                        const programDropdown = $('#search_program');

                        // Reset the program dropdown
                        programDropdown.html('<option value="">All Program</option>');

                        if (deptCode) {
                            // Perform AJAX request to fetch programs for the selected department
                            $.ajax({
                                type: 'POST',
                                url: '', // Use the same file
                                data: { dept_code: deptCode },
                                dataType: 'json',
                                success: function (response) {
                                    if (response.programs) {
                                        programDropdown.html(response.programs); // Populate the dropdown with programs
                                    }
                                },
                                error: function (xhr, status, error) {
                                    console.error('AJAX Error:', error);
                                }
                            });
                        }
                    }

                    // Trigger department change when the page loads if a department is pre-selected
                    const lastSelectedDepartment = $('#search_department').val();
                    loadPrograms(lastSelectedDepartment); // Load programs based on the current value of dept_code

                    // Trigger department change when the department selection changes
                    $('#search_department').change(function () {
                        const deptCode = $(this).val(); // Get the selected department code
                        loadPrograms(deptCode); // Load programs for the selected department
                    });
                });


                // $(document).ready(function () {
                //     // Function to load year levels based on the selected program
                //     function loadYearLevels(programCode) {
                //         const levelDropdown = $('#search_level'); // Reference the year level dropdown
                //         console.log('Loading year levels for program:', programCode);
                //         // Reset the year level dropdown
                //         levelDropdown.html('<option value="">Select Year Level</option>');

                //         if (programCode) {
                //             // Perform AJAX request to fetch num_year
                //             $.ajax({
                //                 type: 'POST',
                //                 url: '', // URL to the PHP file that will handle this request
                //                 data: { program_code: programCode },
                //                 dataType: 'json',
                //                 success: function (response) {
                //                     const numYears = response.num_year; // Extract num_year
                //                     if (numYears > 0) {
                //                         // Populate the year levels
                //                         for (let i = 1; i <= numYears; i++) {
                //                             const suffix = getSuffix(i);
                //                             const yearLabel = `${i}${suffix} Year`;
                //                             levelDropdown.append(`<option value="${i}">${yearLabel}</option>`);
                //                         }
                //                     } else {
                //                         levelDropdown.html('<option value="">No Year Levels Available</option>');
                //                     }
                //                 },
                //                 error: function (xhr, status, error) {
                //                     console.error('AJAX Error:', error);
                //                     levelDropdown.html('<option value="">Error loading year levels</option>');
                //                 }
                //             });
                //         }
                //     }

                //     // Function to determine the correct suffix for year levels (1st, 2nd, etc.)
                //     function getSuffix(number) {
                //         if (number === 1) return 'st';
                //         if (number === 2) return 'nd';
                //         if (number === 3) return 'rd';
                //         return 'th';
                //     }

                //     const lastSelectedProgram = document.getElementById('search_program').value;
                //     console.log('Program Value (Native JS):', lastSelectedProgram);
                //     loadYearLevels(lastSelectedProgram);

                //     // Trigger loadYearLevels on dropdown change
                //     $('#search_program').change(function () {
                //         const programCode = $(this).val();
                //         console.log('Selected Program on Change:', programCode);
                //         loadYearLevels(programCode);
                //     });
                // });

            </script>
            <div class="col-md-2">
                <input type="text" class="form-control" name="search_section"
                    value="<?php echo htmlspecialchars($search_section); ?>" placeholder="Section">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn w-100" id="searchbtn">Search</button>
            </div>
        </form>
        <table class="table">
            <thead id="thead">
                <tr>
                    <?php if (empty($search_department)): ?>
                        <th>Department</th>
                    <?php endif; ?>
                    <th>Section</th>
                    <th></th>

                </tr>
            </thead>
            <tbody>

                <?php
             $sql_list = "
             SELECT 
                 tbl_schedstatus.section_sched_code, 
                 tbl_schedstatus.semester, 
                 tbl_schedstatus.ay_code,  -- ← added ay_code here
                 tbl_schedstatus.dept_code, 
                 tbl_secschedlist.program_code, 
                 tbl_secschedlist.section_code 
             FROM tbl_schedstatus 
             INNER JOIN tbl_secschedlist 
                 ON tbl_schedstatus.section_sched_code = tbl_secschedlist.section_sched_code 
             INNER JOIN tbl_department 
                 ON tbl_schedstatus.dept_code = tbl_department.dept_code 
             INNER JOIN tbl_section 
                 ON tbl_secschedlist.section_code = tbl_section.section_code
             WHERE tbl_schedstatus.semester = ?  AND tbl_secschedlist.ay_code = ?
               AND tbl_department.college_code = ?";
                        
                if (!empty($search_section)) {
                    $sql_list .= " AND TRIM(tbl_secschedlist.section_code) LIKE '%$search_section%'";
                }
                if (!empty($search_department)) {
                    $sql_list .= " AND TRIM(tbl_department.dept_code) LIKE '%$search_department%'";
                }
                if (!empty($search_program)) {
                    $sql_list .= " AND TRIM(tbl_secschedlist.program_code) LIKE '%$search_program%'";
                }
                if (!empty($search_status)) {
                    // Now referencing year_level from tbl_section
                    $sql_list .= " AND TRIM(tbl_schedstatus.status) LIKE '%$search_status%'";
                }

                // Optional: Debugging output
                // echo $sql_list;
                
                $stmt = $conn->prepare($sql_list);
                $stmt->bind_param('sss', $semester, $ay_code,$college_code );
                $stmt->execute();


                $result_list = $stmt->get_result();

                if ($result_list->num_rows > 0) {
                    // Output data of each row
                    while ($row = $result_list->fetch_assoc()) {
                        ?>
                        <tr id="row">
                            <?php if (empty($search_department)): ?>
                                <td><?php echo $row['dept_code']; ?></td>
                            <?php endif; ?>
                            <td><?php echo $row['section_code']; ?></td>
                            <td>
                                <form method='POST' action='ccl_head_library.php' style="display:inline;">
                                    <div class="icons">
                                        <div class="button1">
                                            <button type='submit' name='edit' class='btn edit-btn ' onclick='redirectToCCLSchedule();'><i
                                                    class="fa-light fa-pencil"></i></button>
                                        </div>
                                        <div class="button1"></div>
                                        <?php if ($user_type === "Department Secretary"): ?>
                                            <button type="button" id="delete" class="btn delete-btn" data-bs-toggle="modal"
                                                data-bs-target="#deleteConfirmationModal"
                                                onclick="openDeleteModal('<?php echo htmlspecialchars($row['section_sched_code']); ?>', 
                                                                        '<?php echo htmlspecialchars($row['semester']); ?>', 
                                                                        '<?php echo htmlspecialchars($row['ay_code']); ?>', 
                                                                        '<?php echo htmlspecialchars($row['section_code']); ?>', 
                                                                        '<?php echo htmlspecialchars($_SESSION['token'] ?? ''); ?>')"><i
                                                    class="fa-light fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <input type='hidden' name='section_sched_code'
                                        value='<?php echo htmlspecialchars($row['section_sched_code']); ?>'>
                                    <input type='hidden' name='semester'
                                        value='<?php echo htmlspecialchars($row['semester']); ?>'>
                                    <input type='hidden' name='ay_code'
                                        value='<?php echo htmlspecialchars($row['ay_code']); ?>'>
                                    <input type='hidden' name='section_code'
                                        value='<?php echo htmlspecialchars($row['section_code']); ?>'>
                                    <?php if (isset($_SESSION['token'])): ?>
                                        <input type='hidden' name='token'
                                            value='<?php echo htmlspecialchars($_SESSION['token']); ?>'>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='3' style='text-align: left; padding: 20px;'>No draft schedules found.</td></tr>";

                }
                $conn->close();
                ?>

            </tbody>
        </table>
    </div>


    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog"
        aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="POST" action="plotSchedule.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteConfirmationModalLabel">Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this?
                        <input type="hidden" name="item_id" id="item_id" value="">
                        <input type='hidden' id='modal_section_sched_code' name='section_sched_code'>
                        <input type='hidden' id='modal_semester' name='semester'>
                        <input type='hidden' id='modal_ay_code' name='ay_code'>
                        <input type='hidden' id='modal_section_code' name='section_code'>
                        <input type='hidden' id='modal_token' name='token'>
                    </div>
                    <div class="modal-footer d-flex justify-content-end" style="gap: 10px;">
                        <button type="button" class="btn btn-danger btn-sm col-md-2" id="confirmDeleteBtn">Yes</button>
                        <button type="button" class="btn btn-secondary btn-sm col-md-2"
                            data-bs-dismiss="modal">No</button>
                    </div>
                </form>
            </div>
        </div>
    </div>




    <script>

function redirectToCCLSchedule() {
            // Add a new entry to the browser's history
            history.pushState(null, null, 'ccl_head_library.php'); // Change the URL without reloading
        }


        function openDeleteModal(sectionSchedCode, semester, ayCode, sectionCode, token) {
            // Set the values in the modal's hidden inputs
            document.getElementById('modal_section_sched_code').value = sectionSchedCode;
            document.getElementById('modal_semester').value = semester;
            document.getElementById('modal_ay_code').value = ayCode;
            document.getElementById('modal_section_code').value = sectionCode;
            document.getElementById('modal_token').value = token;
        }

        // Handle the delete confirmation button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            // Submit the delete form with the hidden values
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = ''; // Your action URL if needed

            // Append hidden inputs from the modal to the form
            ['section_sched_code', 'semester', 'ay_code', 'section_code', 'token'].forEach(function (name) {
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = document.getElementById('modal_' + name).value;
                form.appendChild(input);
            });

            // Add a hidden input to indicate this is a delete action
            let deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete';
            deleteInput.value = 'true';
            form.appendChild(deleteInput);

            document.body.appendChild(form);
            form.submit();
        });
    </script>
</body>

</html>