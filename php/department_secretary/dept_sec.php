<?php
include("../config.php");
session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../login/login.php");
    exit();
}


// Get the current user's first name and department code from the session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'Unknown';
$prof_name = isset($_SESSION['prof_name']) ? $_SESSION['prof_name'] : 'User';
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

    $_SESSION['ay_code'] = $ay_code;
    $_SESSION['semester'] = $semester;
}

// Handle form submission for navigation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $redirects = [
        'course' => './input_forms/course_input.php',
        'professor' => './input_forms/prof_input.php',
        'classroom' => './input_forms/classroom_input.php',
        'section' => './input_forms/section_input.php',
        'program' => './input_forms/program_input.php',
        'create' => 'create_sched.php',
        'department' => './input_forms/department_input.php',
        'report' => 'report/report_sec.php',
        'draft' => './draft/draft.php',
        'library' => './library/lib_section.php',
        'shared' => 'sharedSchedule.php'
    ];

    if (array_key_exists($action, $redirects)) {
        header('Location: ' . $redirects[$action]);
        exit();
    }
}



// ========== COUNT DISPLAY =============//
// Draft Count
$draftCountQuery = "
    SELECT COUNT(ts.status) AS draft_count 
    FROM tbl_schedstatus ts
    INNER JOIN tbl_department td ON ts.dept_code = td.dept_code
    WHERE td.dept_code = ? AND ts.ay_code = ? AND ts.semester = ? AND ts.status = 'draft'";

$stmt = $conn->prepare($draftCountQuery);
$stmt->bind_param("sss", $dept_code, $ay_code, $semester);
$stmt->execute();

$draftCountResult = $stmt->get_result();
$draftCount = $draftCountResult->fetch_assoc()['draft_count'];

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

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php"); ?>


    <div class="container">
        <div class="head-title">
            <div class="left">
                <h1><?php echo $user_type ?></h1>
            </div>
            <a href="create_sched.php" class="btn-plot" style="text-decoration:none;">
                <i class='fa-solid fa-plus'></i>
                <span class="text">Plot Schedule</span>
            </a>
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
                <i class="fa-solid fa-check" style="color: #FD7238;"></i>
                <span class="text">
                    <h3><?php echo $completedCount; ?></h3>
                    <p>Completed</p>
                </span>
            </li>
            <li>
                <i class="fas fa-users" style="color: #FD7238;"></i>
                <span class="text">
                    <h3><?php echo $publicCount; ?></h3>
                    <p>Public</p>
                </span>
            </li>
        </ul>

        <div class="table-data mt-0">
            <div class="recent">
                <form method="POST" action="">
                    <div class="d-flex justify-content-between flex-wrap">
                        <!-- <button type="submit" name="action" value="draft" class="btn-block1" style="color: #000000">
                            <i class="fa-solid fa-file"></i>
                            DRAFT
                        </button> -->
                        <button type="submit" name="action" value="library" class="btn-block1" style="color: #000000">
                            <i class="fa-solid fa-book"></i>
                            SCHEDULES
                        </button>

                    </div>
                    <div class="d-flex justify-content-between flex-wrap">
                        <button type="submit" name="action" value="shared" class="btn-block1" style="color: #000000">
                            <i class="fa-solid fa-user-group"></i>
                            SHARED
                        </button>

                    </div>
                </form>
            </div>

            <div class="create h-50">
                <div class="head">
                    <h3>Input Forms</h3>
                </div>
                <ul class="create-list">
                    <a href="./input_forms/program_input.php" style="text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Programs</p>
                        </li>
                    </a>
                    <a href="./input_forms/section_input.php" style="text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Sections</p>
                        </li>
                    </a>
                    <!-- <a href="./input_forms/course_input.php" style = "text-decoration:none;">
            <li class="create-btn mb-3">
                <p>Checklist</p>
            </li>
        </a> -->
                    <a href="./input_forms/classroom_input.php" style="text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Rooms</p>
                        </li>
                    </a>
                    <a href="#" style="text-decoration:none;" onclick="showProfUnitModal();">
                        <li class="create-btn mb-3">
                            <p>Instructors</p>
                        </li>
                    </a>
                    <a href="./input_forms/signatory_input.php" style="text-decoration:none;">
                        <li class="create-btn mb-3">
                            <p>Signatory</p>
                        </li>
                    </a>
                </ul>
            </div>

        </div>
    </div>

    <!-- Modal for Selecting program_unit -->
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
                            $result = $stmt->get_result(); // Get the result
                        
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
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
                                        echo $semester;
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
            xhr.open("POST", "./input_forms/prof_input.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // Redirect to the same page after setting the session
                    window.location.href = `./input_forms/prof_input.php?prof_unit=${encodeURIComponent(profUnit)}`;
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

    <script>
        // Add a new task to the list
        function addTask() {
            const taskInput = document.getElementById('taskInput');
            const taskText = taskInput.value.trim();

            if (taskText === '') {
                alert('Please enter a task!');
                return;
            }

            const taskList = document.getElementById('taskList');

            const li = document.createElement('li');
            li.textContent = taskText;

            // Toggle completed task
            li.addEventListener('click', () => {
                li.classList.toggle('completed');
            });

            // Add remove button
            const removeBtn = document.createElement('button');
            removeBtn.textContent = 'X';
            removeBtn.className = 'remove-btn';
            removeBtn.addEventListener('click', () => {
                taskList.removeChild(li);
            });

            li.appendChild(removeBtn);
            taskList.appendChild(li);

            taskInput.value = '';
        }
    </script>
</body>

</html>