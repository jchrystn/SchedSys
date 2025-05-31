<?php

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../config.php");

session_start();
$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');
$prof_name = isset($_SESSION['prof_name']) ? $_SESSION['prof_name'] : 'User';

$fetch_info_query = "SELECT * FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $dept_code = $row['dept_code'];
    $department_code = $row['dept_code'];

    if ($row['reg_adviser'] == 0) {
        $current_user_type = $user_type;

    } else {
        $current_user_type = "Registration Adviser";
    }
}
// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 'Admin' && $current_user_type != 'Registration Adviser')) {
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

// fetching department
// $departments = [];
// $sql = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
// $result = $conn->query($sql);

// if ($result->num_rows > 0) {
//     while ($row = $result->fetch_assoc()) {
//         $departments[] = $row["dept_code"];
//     }
// }

// Fetch all programs based on department
$programs = [];
$program_curriculums = [];

// // Fetch all programs
// $sql = "SELECT dept_code, program_code, curriculum FROM tbl_program WHERE college_code = '$college_code'";
// $result = $conn->query($sql);

// if ($result->num_rows > 0) {
//     while ($row = $result->fetch_assoc()) {
//         $dept = $row['dept_code'];
//         $program = $row['program_code'];
//         $curriculum = $row['curriculum'];

//         // Store curriculum types for each program
//         $program_curriculums[$dept][$program][] = $curriculum;
//     }
// }


// Fetch total number of records
$total_sql = "SELECT COUNT(*) FROM tbl_section WHERE dept_code=?";
$total_stmt = $conn->prepare($total_sql);
$total_stmt->bind_param("s", $dept_code);
$total_stmt->execute();
$total_stmt->bind_result($total_records);
$total_stmt->fetch();
$total_stmt->close();

$program_sql = "SELECT program_code FROM tbl_program WHERE dept_code = ?";
$program_stmt = $conn->prepare($program_sql);
$program_stmt->bind_param("s", $dept_code);
$program_stmt->execute();
$program_result = $program_stmt->get_result();

$message = "";

if (isset($_POST['save'])) {
    $program_code = mysqli_real_escape_string($conn, $_POST['program-code']);
    $student_number = mysqli_real_escape_string($conn, $_POST['student-number']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last-name']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first-name']);
    $mi = mysqli_real_escape_string($conn, $_POST['mi']);
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $section_code = mysqli_real_escape_string($conn, $_POST['section_code']);


    // Ensure the email always ends with '@cvsu.edu.ph'
    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        // Append '@cvsu.edu.ph' if missing
        $email = explode('@', $email)[0] . '@cvsu.edu.ph';
    }

    // Generate a random 6-letter password
    function generateRandomPassword($length = 6)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomPassword;
    }

    $password = generateRandomPassword();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $check_sql = "SELECT * FROM tbl_stud_acc WHERE dept_code = '$department_code' AND  LOWER(student_no) = LOWER(?) OR LOWER(cvsu_email) = LOWER(?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $student_number, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "Student already exists";
    } else {

        $section_stmt = $conn->prepare("SELECT year_level FROM tbl_section WHERE section_code = ? AND program_code = ?");
$section_stmt->bind_param("ss", $section_code, $program_code);
$section_stmt->execute();
$section_result = $section_stmt->get_result();

if ($section_result->num_rows === 0) {
    die("Section not found.");
}
$section_row = $section_result->fetch_assoc();
$year_level = (int) $section_row['year_level'];

// 2. Get total number of years (num_year) from tbl_program
$program_stmt = $conn->prepare("SELECT num_year FROM tbl_program WHERE program_code = ?");
$program_stmt->bind_param("s", $program_code);
$program_stmt->execute();
$program_result = $program_stmt->get_result();

if ($program_result->num_rows === 0) {
    die("Program not found.");
}
$program_row = $program_result->fetch_assoc();
$num_year = (int) $program_row['num_year'];

// 3. Compute remaining years
$remaining_years = $num_year - $year_level;

        $insert_sql = "INSERT INTO tbl_stud_acc (college_code, reg_adviser, dept_code, student_no, last_name, first_name, middle_initial, suffix, cvsu_email, program_code, password, status, acc_status,section_code,remaining_years,num_year) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approve', '1',?,?,?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssssssssss", $college_code, $prof_name, $department_code, $student_number, $last_name, $first_name, $mi, $suffix, $email, $program_code, $hashed_password,$section_code,$remaining_years,$num_year);

        if ($insert_stmt->execute()) {
            $message = "Student has been added successfully";
        } else {
            echo "Error: " . $insert_sql . "<br>" . $conn->error;
        }
    }
    // For Sending the code through email
    $mail = new PHPMailer(true);

    // try {
    //     //Server settings
    //     $mail->SMTPDebug = SMTP::DEBUG_OFF;                         // Disable verbose debug output
    //     $mail->isSMTP();                                            // Send using SMTP
    //     $mail->Host = 'smtp.gmail.com';                       // Set the SMTP server to send through
    //     $mail->SMTPAuth = true;                                   // Enable SMTP authentication
    //     $mail->Username = 'nuestrojared305@gmail.com';            // SMTP username
    //     $mail->Password = 'bfruqzcgrhgnsrgr';                     // SMTP password
    //     $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
    //     $mail->Port = 587;                                    // TCP port to connect to

    //     //Recipients
    //     $mail->setFrom('schedsys14@gmail.com', 'SchedSys');         // Sender's email address and name
    //     $mail->addAddress($email);                                  // Recipient's email address

    //     //Content
    //     $mail->isHTML(true);                                        // Set email format to HTML
    //     $mail->Subject = 'Account Details';
    //     $mail->Body = 'Dear ' . $last_name . ',<br><br>We are pleased to provide you with your login details for your account.<br><br>
    //                         <strong>Student Number: </strong>' . $student_number . '<br>
    //                         <strong>Password: </strong>' . $password . '<br><br>
    //                     Please make sure to keep this information safe and secure. If you have any questions or need further assistance, feel free to contact us.<br><br>
    //                     Thank You.<br>';

    //     $mail->send();
    //     echo '<script>
    //         window.location.href="create_stud.php";
    //         </script>';
    //     exit();
    // } catch (Exception $e) {
    //     // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    // }
}


if (isset($_POST['update'])) {
    // Handle updating the record
    $department_code = mysqli_real_escape_string($conn, $_POST['department-code']);
    $program_code = mysqli_real_escape_string($conn, $_POST['program-code']);
    $student_number = mysqli_real_escape_string($conn, $_POST['student-number']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last-name']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first-name']);
    $mi = mysqli_real_escape_string($conn, $_POST['mi']);
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Ensure the email always ends with '@cvsu.edu.ph'
    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        // Append '@cvsu.edu.ph' if missing
        $email = explode('@', $email)[0] . '@cvsu.edu.ph';
    }

    $sql = "UPDATE tbl_stud_acc SET dept_code='$department_code', program_code='$program_code', last_name='$last_name', first_name='$first_name', middle_initial='$mi', suffix='$suffix', cvsu_email='$email' WHERE student_no='$student_number'";

    if ($conn->query($sql) === TRUE) {
        // $message = "The selected student has been updated successfully";
    } else {
        echo "Error updating record: " . $conn->error;
    }
}

if (isset($_POST['delete'])) {
    // Handle deleting the record
    $student_number = mysqli_real_escape_string($conn, $_POST['student-number']);

    $sql = "DELETE FROM tbl_stud_acc WHERE student_no='$student_number'";

    if ($conn->query($sql) === TRUE) {
        // $message = "The selected student has been deleted successfully";
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}

// Fetch the data from the database
$result = $conn->query("SELECT * FROM tbl_stud_acc WHERE college_code = '$college_code'");

if ($result === FALSE) {
    echo "Error fetching data: " . $conn->error;
}

if (!empty($message)) {
    echo "<script>
            function showModalWithCheck() {
                const modalElement = document.getElementById('messageModal');
                if (modalElement) {
                    document.getElementById('modalMessage').innerText = '" . addslashes($message) . "';
                    modalElement.style.display = 'block';
                } else {
                    setTimeout(showModalWithCheck, 50); // Retry if modal is not available yet
                }
            }
            showModalWithCheck();
          </script>";
}


if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset();
    session_destroy();
    echo '<script>window.location.href="../login/login.php";</script>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'fetch_sections') {
    $program_code = $_POST['program_code'];
    $ay_code = $_POST['ay_code'];
    $semester = $_POST['semester'];
    $prof_name = $_POST['prof_name'];

    $stmt = $conn->prepare("
        SELECT s.section_code 
        FROM tbl_section s
        INNER JOIN tbl_registration_adviser r ON s.section_code = r.section_code
        WHERE s.program_code = ? 
          AND s.ay_code = ? 
          AND s.semester = ?
          AND r.reg_adviser = ?
          
    ");
    $stmt->bind_param('ssss', $program_code, $ay_code, $semester, $prof_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $sections = [];
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row['section_code'];
    }

    header('Content-Type: application/json');
    echo json_encode($sections);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- <link rel="stylesheet" href="/SchedSys/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <script src="/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="create_account.css">
</head>
<?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
include($IPATH . "navbar.php"); ?>

<body>

    <!-- Sidebar Section -->
    <!-- <aside>
            <div class="toggle">
                <div class="logo">
                    <h2 class="logo-name">SchedSys</span></h2>
                </div>
            </div>
            <div class="sidebar">
                <a href="index.php">
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
                <a onclick="openModal(event,'logoutModal')">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside> -->


    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p><br>
            <div class="modal-buttons">
                <button class="btn-logout" onclick="confirmLogout()">Logout</button>
                <a href="create_stud.php"><button onclick="closeModal()">Cancel</button></a>
            </div>
        </div>
    </div>



    <div class="main">
        <div class="content">
            <div class="user_account" style="width: 30%; margin-left: 50px;">
                <h5 class="title" style="text-align: center">Create Student Account</h5><br>
                <form id="studentForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <select class="form-control" id="program_code" name="program-code" required>
                        <option value="" disabled selected>Select Program</option>
                        <?php
                        $displayedPrograms = [];
                        while ($row = $program_result->fetch_assoc()):
                            $program_code = $row['program_code'];
                            if (!in_array($program_code, $displayedPrograms)):
                                $displayedPrograms[] = $program_code;
                                ?>
                                <option value="<?= htmlspecialchars($program_code) ?>"><?= htmlspecialchars($program_code) ?>
                                </option>
                                <?php
                            endif;
                        endwhile;
                        ?>
                    </select><br>
                    <select class="form-control mt-2" id="section_code" name="section_code" required disabled>
                        <option value="" disabled selected>Select Section</option>
                    </select>
                    <div class="form-group"><br>
                        <input type="text" class="form-control" id="student-number" name="student-number"
                            placeholder="Student Number" required maxlength="9" pattern="\d{9}"
                            title="Student number must be exactly 9 digits" oninput="limitInput(this)"
                            inputmode="numeric">
                    </div><br>
                    <div class="form-group">
                        <input type="text" class="form-control" id="last-name" name="last-name" placeholder="Last Name"
                            oninput="validateLetters(this)" required>
                    </div><br>
                    <div class="form-group">
                        <input type="text" class="form-control" id="first-name" name="first-name"
                            placeholder="First Name" oninput="validateLetters(this)" required>
                    </div><br>
                    <div class="form-group-row">
                        <div class="form-group">
                            <input type="text" class="form-control" id="mi" name="mi" placeholder="Middle Initial"
                                maxlength="1"
                                oninput="validateMiddleInitial(this);this.value = this.value.toUpperCase();"
                                pattern="[A-Za-z]{1}">
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Suffix"
                                oninput="validateMiddleSuffix(this)" pattern="[A-Za-z]+">
                        </div>
                    </div><br>
                    <div class="form-group" style="position: relative;">
                        <input type="text" class="form-control" id="email" name="email" placeholder="example"
                            oninput="appendDomain()" style="padding-right: 110px;">
                        <div class="email-domain">@cvsu.edu.ph</div>
                    </div><br>
                    <br>
                    <!-- Buttons -->
                    <!-- <div class="btn">
                            <button type="submit" name="save" value="add"  id="addButton" class="btn-add btn-success">Add</button>
                            <button type="submit" name="update" id="updateButton" value="update" class="btn-update btn-primary" disabled>Update</button>
                            <button type="submit" name="delete" id="deleteButton" value="delete" class="btn-delete btn-danger" disabled>Delete</button>
                        </div> -->

                    <!-- Button -->
                    <div class="btn">
                        <button type="submit" name="save" value="add" id="addButton" class="btn-add btn-success"
                            onclick="openModal(event, 'addModal')">Add</button>
                        <button type="submit" name="update" id="updateButton" value="update"
                            class="btn-update btn-primary" onclick="openModal(event, 'updateModal')">Update</button>
                        <button type="submit" name="delete" id="deleteButton" value="delete"
                            class="btn-delete btn-danger" onclick="openModal(event, 'deleteModal')">Delete</button>
                    </div>

                    <!-- Add Modal -->
                    <div id="addModal" class="modal" style="display: none;">
                        <div class="modal-content">
                            <h2>Confirm Add</h2>
                            <p>Are you sure you want to add this entry?</p><br>
                            <div class="modal-buttons">
                                <button type="submit" name="save" class="btn-add"
                                    onclick="finalizeAction('addModal')">Yes, Add</button>
                                <a href="create_stud.php"><button class="closed"
                                        onclick="closeModal('addModal')">Cancel</button></a>
                            </div>
                        </div>
                    </div>

                    <!-- Update Modal -->
                    <div id="updateModal" class="modal" style="display: none;">
                        <div class="modal-content">
                            <h2>Confirm Update</h2>
                            <p>Are you sure you want to update this entry?</p><br>
                            <div class="modal-buttons">
                                <button type="submit" name="update" class="btn-update"
                                    onclick="finalizeAction('updateModal')">Yes, Update</button>
                                <a href="create_stud.php"><button class="closed"
                                        onclick="closeModal()">Cancel</button></a>
                            </div>
                        </div>
                    </div>

                    <!-- Delete Modal -->
                    <div id="deleteModal" class="modal" style="display: none;">
                        <div class="modal-content">
                            <h2>Confirm Delete</h2>
                            <p>Are you sure you want to delete this entry?</p><br>
                            <div class="modal-buttons">
                                <button type="submit" name="delete" class="btn-delete"
                                    onclick="finalizeAction('deleteModal')">Yes, Delete</button>
                                <a href="create_stud.php"><button class="closed"
                                        onclick="closeModal()">Cancel</button></a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="content-table" style="width: 70%; ">

                <div class="filtering-container">
                

                    <div class="form-group">
                        <select class="filtering" id="program-filter" name="program-filter">
                            <option value="" disabled selected>Select Program</option>
                            <?php
                            $displayedPrograms = [];

                            foreach ($dept_programs as $program):
                                if (!in_array($program, $displayedPrograms)):
                                    $displayedPrograms[] = $program; // Add to the list of displayed programs
                                    ?>
                                    <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="text" class="filtering" id="search_user" name="search_user"
                            placeholder="Search Student" autocomplete="off">
                        <button type="submit" class="btn-add btn-search">Search</button>
                    </div>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Department</th>
                            <th style="width: 150px;">Program</th>
                            <th style="width: 150px;">Student Number</th>
                            <th style="width: 200px;">Full Name</th>
                            <th>CvSU Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                // Skip rows where status is 'Pending'
                                if ($row["status"] === "pending") {
                                    continue;
                                }
                                $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
                                echo "<tr class='account-row' 
                                        data-dept-code='" . htmlspecialchars($row["dept_code"]) . "'
                                        data-program-code='" . htmlspecialchars($row["program_code"]) . "'
                                        data-student-no='" . htmlspecialchars($row["student_no"]) . "'
                                        data-first-name='" . htmlspecialchars($row["first_name"]) . "'
                                        data-middle-initial='" . htmlspecialchars($row["middle_initial"]) . "'
                                        data-suffix='" . htmlspecialchars($row["suffix"]) . "'
                                        data-last-name='" . htmlspecialchars($row["last_name"]) . "'
                                        data-cvsu-email='" . htmlspecialchars($row["cvsu_email"]) . "'>
                                        <td style='width: 150px;'>" . htmlspecialchars($row["dept_code"]) . "</td>
                                        <td style='width: 150px;'>" . htmlspecialchars($row["program_code"]) . "</td>
                                        <td style='width: 150px;'>" . htmlspecialchars($row["student_no"]) . "</td>
                                        <td style='width: 200px;'>" . htmlspecialchars($full_name) . "</td>
                                        <td>" . htmlspecialchars($row["cvsu_email"]) . "</td>
                                    </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No records found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content">
            <p id="modalMessage">Your message here</p>
            <button class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>


    <script>
        document.getElementById('program_code').addEventListener('change', function () {
            const programCode = this.value;
            const ayCode = '<?= addslashes($ay_code) ?>';
            const semester = '<?= addslashes($semester) ?>';
            const profName = '<?= addslashes($prof_name) ?>';

            const formData = new FormData();
            formData.append('ajax', 'fetch_sections');
            formData.append('program_code', programCode);
            formData.append('ay_code', ayCode);
            formData.append('semester', semester);
            formData.append('prof_name', profName);

            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    const sectionSelect = document.getElementById('section_code');
                    sectionSelect.innerHTML = '<option value="" disabled selected>Select Section</option>';

                    if (data.length === 0) {
                        const option = document.createElement('option');
                        option.disabled = true;
                        option.textContent = 'No sections available';
                        sectionSelect.appendChild(option);
                        sectionSelect.disabled = true;
                    } else {
                        data.forEach(section => {
                            const option = document.createElement('option');
                            option.value = section;
                            option.textContent = section;
                            sectionSelect.appendChild(option);
                        });
                        sectionSelect.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('Error fetching sections:', err);
                     alert(
            'Failed to load sections.\n\n' +
            'Values sent:\n' +
            'Program Code: ' + programCode + '\n' +
            'AY Code: ' + ayCode + '\n' +
            'Semester: ' + semester + '\n' +
            'Professor: ' + profName
        );
                });
        });

        function validateLetters(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');  // Allow spaces as well
        }

        function validateMiddleInitial(input) {
            input.value = input.value.replace(/[^A-Za-z]/g, '').substring(0, 1);
        }

        function validateMiddleSuffix(input) {
            input.value = input.value.replace(/[^A-Za-z]/g, '');
        }

        function limitInput(input) {
            // Allow only numeric values
            input.value = input.value.replace(/[^0-9]/g, '');

            // Limit to 9 characters
            if (input.value.length > 9) {
                input.value = input.value.slice(0, 9);
            }
        }

        function appendDomain() {
            // Function for appending domain to email (appears to be referenced but not defined in original)
            // You might want to implement this based on your needs
        }

        // Open the modal
        function openModal(event, modalId) {
            event.preventDefault(); // Prevent the default form submission
            document.getElementById(modalId).style.display = "flex";
        }

        // Close the modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }

        // Finalize the action (form submission)
        function finalizeAction(modalId) {
            document.getElementById(modalId).style.display = "none";
            // The form will be submitted because the button has type="submit".
            console.log("Form submitted for modal: " + modalId);
        }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "index.php?logout=1";
        }

        // Function to show the modal with a custom message
        function showModal(message) {
            document.getElementById("modalMessage").innerText = message;
            document.getElementById("messageModal").style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById("messageModal").style.display = "none";
        }

        document.addEventListener('DOMContentLoaded', function () {
            const programs = <?php echo json_encode($programs); ?>;
            const departmentSelect = document.getElementById('department_code');
            const programSelect = document.getElementById('program_code');

            // Filtering elements
            const departmentFilter = document.getElementById("department-filter");
            const programFilter = document.getElementById("program-filter");
            const searchInput = document.getElementById("search_user");
            const searchButton = document.querySelector(".btn-search");
            const tableRows = document.querySelectorAll(".account-row");
            const tableBody = document.querySelector("table tbody");

            // Function to update program options based on selected department
            function updateProgramOptions(departmentElement, programElement) {
                // Clear existing options except the placeholder
                while (programElement.options.length > 1) {
                    programElement.remove(1);
                }

                const selectedDepartment = departmentElement.value;

                // If no department selected, disable program select
                if (!selectedDepartment) {
                    programElement.disabled = true;
                    return;
                }

                // Enable program select
                programElement.disabled = false;

                // Add program options for the selected department
                if (programs[selectedDepartment]) {
                    programs[selectedDepartment].forEach(program => {
                        const option = document.createElement('option');
                        option.value = program;
                        option.textContent = program;
                        programElement.appendChild(option);
                    });
                }
            }

            // Add event listener to department select in the form
            departmentSelect.addEventListener('change', function () {
                updateProgramOptions(departmentSelect, programSelect);
            });

            // Add event listener to department filter
            departmentFilter.addEventListener('change', function () {
                updateProgramOptions(departmentFilter, programFilter);
                filterTable();
            });

            // Program filter change event
            programFilter.addEventListener('change', filterTable);

            // Search input event
            searchInput.addEventListener('input', filterTable);

            // Search button click event
            searchButton.addEventListener('click', function (e) {
                e.preventDefault();
                filterTable();
            });

            // Function to filter the table
            function filterTable() {
                let department = departmentFilter.value;
                let program = programFilter.value;
                let searchText = searchInput.value.toLowerCase();
                let rowsShown = 0;

                tableRows.forEach(row => {
                    let deptCode = row.getAttribute('data-dept-code');
                    let progCode = row.getAttribute('data-program-code');
                    let studNum = row.getAttribute('data-student-no').toLowerCase();
                    let firstName = row.getAttribute('data-first-name').toLowerCase();
                    let lastName = row.getAttribute('data-last-name').toLowerCase();
                    let middleInitial = row.getAttribute('data-middle-initial').toLowerCase();
                    let suffix = row.getAttribute('data-suffix').toLowerCase();
                    let email = row.getAttribute('data-cvsu-email').toLowerCase();
                    let fullName = `${firstName} ${middleInitial} ${lastName} ${suffix}`.toLowerCase();

                    let matchesDepartment = !department || deptCode === department;
                    let matchesProgram = !program || progCode === program;
                    let matchesSearchText = !searchText ||
                        studNum.includes(searchText) ||
                        firstName.includes(searchText) ||
                        lastName.includes(searchText) ||
                        fullName.includes(searchText) ||
                        email.includes(searchText);

                    if (matchesDepartment && matchesProgram && matchesSearchText) {
                        row.style.display = "";
                        rowsShown++;
                    } else {
                        row.style.display = "none";
                    }
                });

                // Show "No records found" if no rows are displayed
                let noRecordsRow = document.getElementById("no-records-row");
                if (rowsShown === 0) {
                    if (!noRecordsRow) {
                        noRecordsRow = document.createElement("tr");
                        noRecordsRow.id = "no-records-row";
                        noRecordsRow.innerHTML = "<td colspan='5'>No records found</td>";
                        tableBody.appendChild(noRecordsRow);
                    }
                } else if (noRecordsRow) {
                    noRecordsRow.remove();
                }
            }

            // Initialize the form
            if (departmentSelect.value) {
                updateProgramOptions(departmentSelect, programSelect);
            }

            // Table row click event for editing
            const table = document.querySelector('.table tbody');
            const addButton = document.getElementById('addButton');
            const updateButton = document.getElementById('updateButton');
            const deleteButton = document.getElementById('deleteButton');
            const formContainer = document.getElementById('studentForm');
            let selectedRow = null;

            // Function to clear the form
            function clearForm() {
                formContainer.reset();
                updateButton.disabled = true;
                deleteButton.disabled = true;
                addButton.disabled = false;

                if (selectedRow) {
                    selectedRow.classList.remove('active-row');
                }
                selectedRow = null;

                while (programSelect.options.length > 1) {
                    programSelect.remove(1);
                }
                programSelect.disabled = true;
            }

            // Event Listener: Populate input fields and highlight the selected row
            table.addEventListener('click', (event) => {
                const row = event.target.closest('tr');
                if (!row || row.id === "no-records-row") return;

                // Populate input fields
                departmentSelect.value = row.dataset.deptCode;

                // Update program options based on the selected department
                updateProgramOptions(departmentSelect, programSelect);

                // Then set the program value
                programSelect.value = row.dataset.programCode;
                document.getElementById('student-number').value = row.dataset.studentNo;
                document.getElementById('last-name').value = row.dataset.lastName;
                document.getElementById('first-name').value = row.dataset.firstName;
                document.getElementById('mi').value = row.dataset.middleInitial;
                document.getElementById('suffix').value = row.dataset.suffix;

                // Get the email value and remove "@cvsu.edu.ph"
                let emailValue = row.dataset.cvsuEmail;
                if (emailValue.endsWith('@cvsu.edu.ph')) {
                    emailValue = emailValue.replace('@cvsu.edu.ph', '');
                }
                document.getElementById('email').value = emailValue;

                // Disable Add button and enable Update/Delete buttons
                addButton.disabled = true;
                updateButton.disabled = false;
                deleteButton.disabled = false;

                // Highlight the selected row
                if (selectedRow) {
                    selectedRow.classList.remove('active-row');
                }
                selectedRow = row;
                row.classList.add('active-row');
            });

            document.addEventListener('click', (event) => {
                if (!formContainer.contains(event.target) && !table.contains(event.target)) {
                    clearForm();
                }
            });
        });

        // Email domain append function (implementation of function referenced earlier)
        function appendDomain() {
            // If needed, implement logic to add @cvsu.edu.ph to email input
            // For example:
            // const emailInput = document.getElementById('email');
            // Use this if you want to show the domain in real-time as the user types
        }
    </script>

</body>

</html>