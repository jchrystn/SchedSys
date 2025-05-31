<?php
include("../config.php");
session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'CCL Head') {
    header("Location: /SchedSys3/php/login/login.php");
    exit();
}


$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
// Get the current user's first name and department code from the session
$prof_name = isset($_SESSION['prof_name']) ? $_SESSION['prof_name'] : 'Unknown';
$dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$ay_code = isset($_SESSION['ay_code']) ? $_SESSION['ay_code'] : '';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : '';
$email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : 'no email';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';


$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}

// Handle form submission for navigation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $redirects = [
        'create' => 'create_sched.php',
        'draft' => './draft/ccl_head_library.php',
        'comlab' => './library/lib_comlab.php'
    ];
    if (array_key_exists($action, $redirects)) {
        header('Location: ' . $redirects[$action]);
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_table'])) {
    $section_sched_code = $_POST['section_sched_code'];
    $section_code = $_POST['section_code'];
    $ay_code = $_POST['ay_code'];
    $semester = $_POST['semester'];
    $status = 'draft';
    // echo $ay_code;
    // echo $section_sched_code;
    // echo $section_code;
    if (empty($section_sched_code) || empty($section_code) || empty($ay_code) || empty($semester)) {
        echo "All fields are required.";
        exit;
    }

    // Fetch dept_code and program_code based on section_code
    $sql = "SELECT dept_code, program_code,curriculum FROM tbl_section WHERE section_code = '$section_code' AND college_code ='$college_code'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "Invalid section code.";
        exit;
    }

    $row = $result->fetch_assoc();
    $dept_code = $row['dept_code'];
    $program_code = $row['program_code'];
    $section_curriculum = $row['curriculum'];
    $fetch_info_query = "SELECT cell_color,status FROM tbl_schedstatus WHERE section_sched_code = '$section_sched_code' AND semester = '$semester'";
    $result = $conn->query($fetch_info_query);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $color = $row['cell_color'] ?? '#FFFFFF';
        $status = $row['status'];
    } else {
        $color = '#FFFFFF';
    }

    // Check if section_sched_code already exists
    $check_sql = "SELECT section_sched_code FROM tbl_secschedlist WHERE section_sched_code = '$section_sched_code' AND curriculum ='$section_curriculum'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows == 0) {
        // Insert into tbl_secschedlist
        $insert_sql = "INSERT INTO tbl_secschedlist (college_code,section_sched_code,curriculum,dept_code, program_code, section_code, ay_code) 
                       VALUES ('$college_code','$section_sched_code','$section_curriculum', '$dept_code', '$program_code', '$section_code', '$ay_code')";

        if ($conn->query($insert_sql) !== TRUE) {
            echo "Error inserting record: " . $conn->error;
            exit;
        }
    }

    $checkSql = "SELECT 1 FROM tbl_schedstatus WHERE section_sched_code = ? AND semester = ? AND dept_code = ? AND ay_code = ? AND curriculum = ? ";
    if ($checkStmt = $conn->prepare($checkSql)) {
        $checkStmt->bind_param("sssss", $section_sched_code, $semester, $dept_code, $ay_code, $section_curriculum);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            echo "This schedule already exists on draft.";
            $checkStmt->close();

        } else {
            // Prepare SQL query
            $sql = "INSERT INTO tbl_schedstatus (college_code,section_sched_code,curriculum, semester, dept_code, status, ay_code, cell_color) VALUES (?,?,?, ?, ?, ?, ?,?)";

            // Initialize prepared statement
            if ($stmt = $conn->prepare($sql)) {
                // Bind parameters
                $stmt->bind_param("ssssssss", $college_code,$section_sched_code,$section_curriculum, $semester, $dept_code, $status, $ay_code, $color);


                // Execute query
                if ($stmt->execute()) {
                    echo "Draft saved successfully.";
                } else {
                    echo "Error: " . $stmt->error;
                }

                // Close statement
                $stmt->close();
            }
        }
    }

    // Sanitize the table name
    $sanitized_section_code = preg_replace("/[^a-zA-Z0-9_]/", "_", $section_code);
    $sanitized_academic_year = preg_replace("/[^a-zA-Z0-9_]/", "_", $ay_code);
    $table_name = "tbl_secsched_" . $dept_code . "_" . $sanitized_academic_year;

    // Check if table exists
    $table_check_sql = "SHOW TABLES LIKE '$table_name'";
    $table_check_result = $conn->query($table_check_sql);

    if ($table_check_result->num_rows == 1) {
        // Table exists, redirect to plotSchedule.php
        $_SESSION['section_sched_code'] = $section_sched_code;
        $_SESSION['semester'] = $semester;
        $_SESSION['section_code'] = $section_code;
        $_SESSION['table_name'] = $table_name;
        header("Location: create_sched/plotSchedule.php");
        exit();
    } else {
        // Define table fields
        $unique_id = time(); // Use a timestamp to ensure uniqueness
        $columns_sql = "sec_sched_id INT AUTO_INCREMENT PRIMARY KEY,
                        section_sched_code VARCHAR(200) NOT NULL,
                        semester VARCHAR(255) NOT NULL,
                        day VARCHAR(50) NOT NULL,
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
                        class_type VARCHAR(100) NOT NULL,
                        CONSTRAINT fk_section_sched_code_{$sanitized_section_code}_{$unique_id} FOREIGN KEY (section_sched_code) REFERENCES tbl_secschedlist(section_sched_code)";

        $sql = "CREATE TABLE $table_name ($columns_sql)";

        if ($conn->query($sql) === TRUE) {
            echo "Table $table_name created successfully";
            // Redirect to plotSchedule.php
            $_SESSION['section_sched_code'] = $section_sched_code;
            $_SESSION['semester'] = $semester;
            $_SESSION['section_code'] = $section_code;
            $_SESSION['table_name'] = $table_name;

            header("Location: create_sched/plotSchedule.php");
            exit();
        } else {
            echo "Error creating table: " . $conn->error;
        }
    }

    $conn->close();
}


// ========== COUNT DISPLAY =============//
// Draft Count
$draftCountQuery = "
    SELECT COUNT(ts.status) AS draft_count 
    FROM tbl_schedstatus ts
    INNER JOIN tbl_department td ON ts.dept_code = td.dept_code
    WHERE ts.college_code = ? AND ts.ay_code = ? AND ts.semester = ? AND ts.status = 'draft'";

$stmt = $conn->prepare($draftCountQuery);
$stmt->bind_param("sss", $college_code, $ay_code, $semester);
$stmt->execute();

$draftCountResult = $stmt->get_result();
$draftCount = $draftCountResult->fetch_assoc()['draft_count'];


$labRoomCountQuery = "
    SELECT COUNT(*) AS lab_room_count
    FROM tbl_room
    WHERE room_type = 'Computer Laboratory'";

$result = $conn->query($labRoomCountQuery);
$labRoomCount = $result->fetch_assoc()['lab_room_count'];



$stmt->close();

// Public Count
$completedCountQuery = "
    SELECT COUNT(ts.status) AS private_count 
    FROM tbl_schedstatus ts
    INNER JOIN tbl_department td ON ts.dept_code = td.dept_code
    WHERE td.dept_code = ? AND ts.ay_code = ?  AND ts.semester = ? AND ts.status IN ('private', 'completed')";

$stmt = $conn->prepare($completedCountQuery);
$stmt->bind_param("sss", $dept_code, $ay_code, $semester);
$stmt->execute();
$completedCountResult = $stmt->get_result();
$completedCount = $completedCountResult->fetch_assoc()['private_count'];

// Public Count
$publicCountQuery = "
    SELECT COUNT(ts.status) AS public_count 
    FROM tbl_schedstatus ts
    INNER JOIN tbl_department td ON ts.dept_code = td.dept_code
    WHERE td.dept_code = ? AND ts.ay_code = ? AND ts.semester = ?  AND  ts.status = 'public'";

$stmt = $conn->prepare($publicCountQuery);
$stmt->bind_param("sss", $dept_code, $ay_code, $semester);
$stmt->execute();
$publicCountResult = $stmt->get_result();
$publicCount = $publicCountResult->fetch_assoc()['public_count'];


// Fetch programs based on `dept_code`
if (isset($_GET['dept_code'])) {
    $dept_code = $_GET['dept_code'];

    $query = "SELECT DISTINCT program_code FROM tbl_program WHERE dept_code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $dept_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $programs = [];
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($programs);
    exit;
}

// Fetch curriculums and year levels based on `program_code`
if (isset($_GET['program_code'])) {
    $program_code = $_GET['program_code'];

    $query = "SELECT DISTINCT curriculum, num_year FROM tbl_program WHERE program_code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $program_code);
    $stmt->execute();
    $result = $stmt->get_result();

    $curriculums = [];
    while ($row = $result->fetch_assoc()) {
        $curriculums[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($curriculums);
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <link rel="stylesheet" href="../../css/department_secretary/dept_sec.css">
</head>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php"); ?>


    <div class="container">
        <div class="head-title">
            <div class="left">
                <h1><?php echo $user_type ?></h1>
            </div>
        </div>

        <ul class="box-info">
            <li>
                <i class="fa-solid fa-building" style="color: #FD7238;"></i>
                <span class="text">
                    <h6><?php echo $ay_name; ?></h6>
                    <p> <?php echo $semester; ?> </p>
                </span>
            </li>
            <li>
                <i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i>
                <span class="text">
                    <h3><?php echo $draftCount; ?></h3>
                    <p>Draft</p>
                </span>
            </li>
            <li>
                <i class="fas fa-desktop" style="color: #FD7238;"></i>
                <span class="text">
                    <h3><?php echo $labRoomCount; ?></h3>
                    <p>Computer Laboratory Rooms</p>
                </span>
            </li>
        </ul>

        <div class="table-data mt-0">
            <div class="recent">
                <form method="POST" action="">
                    <div class="d-flex flex-wrap justify-content-center align-items-center"
                        style="gap: 4%; text-align: center;">
                        <button type="button" value="create_new" class="btn-block1" data-bs-toggle="modal"
                            style="color: #000000" data-bs-target="#createTableModal">
                            <i class="fas fa-plus"></i>
                            PLOT SCHEDULE
                        </button>
                        <button type="submit" name="action" value="draft" class="btn-block1" style="color: #000000">
                            <i class="fa-solid fa-file"></i>
                            SECTION SCHEDULES
                        </button>
                        <button type="submit" name="action" value="comlab" class="btn-block1" style="color: #000000">
                        <i class="fas fa-desktop"></i>
                            Schedules
                        </button>
                    </div>
                </form>
            </div>
            <div class="create h-50">
                <div class="head">
                    <h3>Input Forms</h3>
                </div>
                <ul class="create-list">
                    <a href="./input_forms/ccl_room_input.php" style = "text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Computer Laboratory</p>
                        </li>
                    </a>
                    <a href="./input_forms/signatory_input.php" style = "text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Signatory</p>
                        </li>
                    </a>
                </ul>
            </div>

        </div>

    </div>

    <div class="modal fade" id="createTableModal" tabindex="-1" role="dialog" aria-labelledby="createTableModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createTableModalLabel">Plot Section Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createTableForm" action="" method="post">
                        <div class="form-group">
                            <input type="hidden" class="form-control" id="section_sched_code" name="section_sched_code"
                                readonly required>
                        </div>
                        <div class="form-group">
                            <label for="ay_code">
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($ay_name); ?><br>
                                <strong>Semester:</strong> <?php echo htmlspecialchars($semester); ?><br><br>
                            </label>
                        </div>
                        <div class="form-group">
                            <select class="form-control" id="search_department" name="search_department">
                                <option value="">Select Department</option>
                                <?php
                                // Populate departments
                                $stmt = $conn->prepare("SELECT dept_code, dept_name FROM tbl_department WHERE college_code = ?");
                                $stmt->bind_param("s", $college_code);
                                $stmt->execute();
                                $result_dept = $stmt->get_result();
                                while ($row = $result_dept->fetch_assoc()) {
                                    echo '<option value="' . htmlspecialchars($row['dept_code']) . '">' . htmlspecialchars($row['dept_code']) . '</option>';
                                }
                                $stmt->close();
                                ?>
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control w-100" name="program_code" id="program_code" required>
                                <option value="" disabled selected>Program</option>
                                <!-- Options will be populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control w-100" name="curriculum" id="curriculum" required>
                                <option value="" disabled selected>Curriculum</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <!-- Year Level Dropdown -->
                            <select class="form-control" id="year_level" name="year_level" required>
                                <option value="" disabled selected>Year Level</option>
                                <!-- Options populated dynamically via JavaScript -->
                            </select>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control" id="section_code" name="section_code" required>
                                <option value="">Select a section</option>
                                <?php
                                // Assuming $sections is an array containing section codes
                                if (!empty($sections)) {
                                    foreach ($sections as $section) {
                                        // Use htmlspecialchars to escape special characters for safe HTML output
                                        echo '<option value="' . htmlspecialchars($section) . '">' . htmlspecialchars($section) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No sections available</option>';
                                }
                                ?>
                            </select>
                        </div><br>
                        <div class="form-group">
                            <input type="hidden" id="ay_code" name="ay_code"
                                value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester"
                                value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>
                        <button type="submit" name="create_table" id="create" class="btn">Plot Schedule</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const deptDropdown = document.getElementById('search_department');
            const programDropdown = document.getElementById('program_code');
            const curriculumDropdown = document.getElementById('curriculum');
            const yearLevelDropdown = document.getElementById('year_level');
            const sectionDropdown = document.getElementById('section_code'); // Added this for clarity

            // Utility function for clearing dropdowns
            function clearDropdown(dropdown, placeholder) {
                dropdown.innerHTML = `<option value="" disabled selected>${placeholder}</option>`;
            }

            // Fetch programs based on `dept_code`
            deptDropdown.addEventListener('change', function () {
                const deptCode = this.value;
                clearDropdown(programDropdown, 'Loading programs...');
                clearDropdown(curriculumDropdown, 'Curriculum');
                clearDropdown(yearLevelDropdown, 'Year Level');
                clearDropdown(sectionDropdown, 'Section'); // Clear section dropdown when department changes

                if (deptCode) {
                    fetch('?dept_code=' + deptCode)
                        .then(response => response.json())
                        .then(programs => {
                            clearDropdown(programDropdown, 'Select Program');
                            programs.forEach(program => {
                                const option = document.createElement('option');
                                option.value = program.program_code;
                                option.textContent = program.program_code;
                                programDropdown.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching programs:', error);
                            clearDropdown(programDropdown, 'Error loading programs');
                        });
                }
            });

            // Fetch curriculums and year levels based on `program_code`
            programDropdown.addEventListener('change', function () {
                const programCode = this.value;
                clearDropdown(curriculumDropdown, 'Loading curriculums...');
                clearDropdown(yearLevelDropdown, 'Year Level');
                clearDropdown(sectionDropdown, 'Section'); // Clear section dropdown when program changes

                if (programCode) {
                    fetch('?program_code=' + programCode)
                        .then(response => response.json())
                        .then(curriculums => {
                            clearDropdown(curriculumDropdown, 'Select Curriculum');
                            curriculums.forEach(curriculum => {
                                const option = document.createElement('option');
                                option.value = curriculum.curriculum;
                                option.textContent = curriculum.curriculum;
                                curriculumDropdown.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching curriculums:', error);
                            clearDropdown(curriculumDropdown, 'Error loading curriculums');
                        });
                }
            });
            curriculumDropdown.addEventListener('change', function () {
                const selectedProgramCode = programDropdown.value;
                const selectedCurriculum = this.value;

                yearLevelDropdown.innerHTML = `<option value="" disabled selected>Loading year levels...</option>`;

                if (selectedProgramCode && selectedCurriculum) {
                    fetch('?program_code=' + selectedProgramCode)
                        .then(response => response.json())
                        .then(curriculums => {
                            const filteredCurriculum = curriculums.find(c => c.curriculum === selectedCurriculum);

                            if (filteredCurriculum) {
                                clearDropdown(yearLevelDropdown, 'Select Year Level');
                                for (let i = 1; i <= filteredCurriculum.num_year; i++) {
                                    const suffix = getSuffix(i);
                                    yearLevelDropdown.innerHTML += `<option value="${i}">${i}${suffix} Year</option>`;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching year levels:', error);
                            clearDropdown(yearLevelDropdown, 'Error loading year levels');
                        });
                }
            });

            // Populate year levels and handle further dropdown changes as before
            curriculumDropdown.addEventListener('change', function () {
                const selectedProgramCode = programDropdown.value;
                const selectedCurriculum = this.value;

                if (selectedProgramCode && selectedCurriculum) {
                    populateYearLevels(selectedProgramCode, selectedCurriculum);
                }
            });

            yearLevelDropdown.addEventListener('change', function () {
                const selectedProgramCode = programDropdown.value;
                const selectedCurriculum = curriculumDropdown.value;
                const selectedYearLevel = this.value;

                if (selectedProgramCode && selectedCurriculum && selectedYearLevel) {
                    populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel);
                }
            });

            // Function to populate Section Codes based on selected inputs
            function populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel) {
                const ayCode = document.getElementById('ay_code').value; // Retrieve the value
                const semester = document.getElementById('semester').value; // Retrieve the value
                sectionDropdown.innerHTML = '<option value="">Loading sections...</option>'; // Reset dropdown

                if (selectedProgramCode && selectedCurriculum && selectedYearLevel && ayCode && semester) {
                    fetch(`get_sections.php?program_code=${selectedProgramCode}&curriculum=${selectedCurriculum}&year_level=${selectedYearLevel}&ay_code=${ayCode}&semester=${semester}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                clearDropdown(sectionDropdown, 'Select Section');
                                data.forEach(section => {
                                    const option = document.createElement('option');
                                    option.value = section.section_code;
                                    option.textContent = section.section_code;
                                    sectionDropdown.appendChild(option);
                                });
                            } else {
                                clearDropdown(sectionDropdown, 'No sections available');
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching sections:', error);
                            clearDropdown(sectionDropdown, 'Error loading sections');
                        });
                }
            }

            // Utility function to get suffix for year levels
            function getSuffix(num) {
                const lastDigit = num % 10;
                const lastTwoDigits = num % 100;

                if (lastDigit === 1 && lastTwoDigits !== 11) return 'st';
                if (lastDigit === 2 && lastTwoDigits !== 12) return 'nd';
                if (lastDigit === 3 && lastTwoDigits !== 13) return 'rd';
                return 'th';
            }
        });

        function updateSectionSchedCode() {
            const sectionCode = document.getElementById('section_code').value;
            const ayCode = document.getElementById('ay_code').value;
            const sectionSchedCode = sectionCode.replace('-', '_') + '_' + ayCode;
            document.getElementById('section_sched_code').value = sectionSchedCode;
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('section_code').addEventListener('change', updateSectionSchedCode);
            document.getElementById('ay_code').addEventListener('change', updateSectionSchedCode);
        });
    </script>

    </body>

</html>