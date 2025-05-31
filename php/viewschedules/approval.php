<?php

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../config.php");

session_start();

$college_code = $_SESSION['college_code']; 
$prof_code = $_SESSION['prof_code'];
$dept_code = $_SESSION['dept_code'];
$prof_name = isset($_SESSION['prof_name']) ? $_SESSION['prof_name'] : 'User';

$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');
$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
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

$fetch_info_query = "SELECT reg_adviser FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg= $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    if($row['reg_adviser'] == 0){
        $current_user_type = $user_type ;
    }else{
        $current_user_type = "Registration Adviser" ;
    } 
}

if (!isset($_SESSION['user_type']) || $current_user_type != 'Registration Adviser') {
    header("Location: ../login/login.php");
    exit();
}

$modalMessage = '';  // Initialize modal message

// Function to generate random password
function generateRandomPassword($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    $charactersLength = strlen($characters);
    $randomPassword = '';
    for ($i = 0; $i < $length; $i++) {
        $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomPassword;
}

$program_code = 'BSIT';
$status= 'approve';
$acc_status = '1';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['student_select']) && !empty($_POST['student_select'])) {
        $selectedStudents = $_POST['student_select'];
        $studentNos = implode("','", array_map('intval', $selectedStudents));

        $sql = "UPDATE tbl_stud_acc SET status = 'approve', acc_status = '1' WHERE student_no IN ('$studentNos')";

        if ($conn->query($sql) === TRUE) {
            $modalMessage = "Selected students have been approved successfully.";

            $fetchDetails = "SELECT cvsu_email, last_name, student_no FROM tbl_stud_acc WHERE student_no IN ('$studentNos')";
            $result = $conn->query($fetchDetails);

            if ($result->num_rows > 0) {
                // Create mail configuration once
                $mailConfig = [
                    'host' => 'smtp.gmail.com',
                    'username' => 'nuestrojared305@gmail.com',
                    'password' => 'bfruqzcgrhgnsrgr',
                    'port' => 587,
                    'from_email' => 'schedsys14@gmail.com',
                    'from_name' => 'SchedSys'
                ];

                // Process each student separately
                while ($row = $result->fetch_assoc()) {
                    $email = $row['cvsu_email'];
                    $last_name = $row['last_name'];
                    $student_number = $row['student_no'];

                    // Generate password and update database
                    $password = generateRandomPassword();
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $updatePassword = "UPDATE tbl_stud_acc SET password = ? WHERE student_no = ?";
                    $updateStmt = $conn->prepare($updatePassword);
                    $updateStmt->bind_param("ss", $hashed_password, $student_number);
                    $updateStmt->execute();

                    // Create a new PHPMailer instance for each student
                    try {
                        $mail = new PHPMailer(true);
                        $mail->SMTPDebug = SMTP::DEBUG_OFF; 
                        $mail->isSMTP();
                        $mail->Host = $mailConfig['host'];
                        $mail->SMTPAuth = true;
                        $mail->Username = $mailConfig['username'];
                        $mail->Password = $mailConfig['password'];
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = $mailConfig['port'];
                        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'Account Details';
                        $mail->Body = "Dear $last_name,<br><br>We are pleased to provide you with your login details for your account.<br><br>
                                      <strong>Student Number:</strong> $student_number<br>
                                      <strong>Password:</strong> $password<br><br>
                                      Please make sure to change this password after your first login.<br><br>
                                      Thank you.<br>";
                        $mail->send();
                    } catch (Exception $e) {
                        // Log error but continue processing other students
                        error_log("Email sending failed for student $student_number: " . $e->getMessage());
                    }
                }
            }
        } else {
            $modalMessage = "Error updating records: " . $conn->error;
        }
    } else {
        // Modal message if no students are selected
        $modalMessage = "You must select at least one student.";
    }
}

if (isset($_POST['import'])) {
    // Check if a file is uploaded
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        
        // Open the CSV file for reading
        if (($handle = fopen($file, 'r')) !== FALSE) {
            // Skip the first row if it's a header
            fgetcsv($handle);

            // SQL query to insert data
            $sql = "INSERT INTO tbl_stud_acc (college_code, dept_code, student_no, password, last_name, first_name, suffix, middle_initial, cvsu_email, program_code, status, acc_status, reg_adviser) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Prepare the statement
            $stmt = $conn->prepare($sql);

            // Bind parameters
            $stmt->bind_param("ssissssssssss", $college_code, $dept_code, $student_no, $password, $last_name, $first_name, $suffix, $middle_initial, $cvsu_email, $program_code, $status, $acc_status, $prof_name);

            // Read each row of the CSV and insert into the database
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Assign data from CSV to variables
             
                $student_no = $data[0];
                $password = $data[1];  // You may want to hash the password before inserting
                $last_name = $data[2];
                $first_name = $data[3];
                $suffix = $data[4];
                $middle_initial = $data[5];
                $cvsu_email = $data[6];
                // Execute the prepared statement
                $stmt->execute();
            }

            // Close the file and the statement
            fclose($handle);
            $stmt->close();
            $conn->close();

            echo "CSV file successfully imported!";
        } else {
            echo "Unable to open the CSV file.";
        }
    } else {
        echo "No file uploaded or there was an error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <!-- <script src="/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script> -->
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="approval.css">

</head>
<body>
<?php if ($user_type === 'Professor'|| $user_type === 'Department Chairperson'): ?>
     <?php 
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/"; 
    include($IPATH . "professor_navbar.php");  
    ?>
<?php endif ?>

<?php if ($user_type === 'Department Secretary' || $user_type === 'CCL Head' ): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
<?php endif ?>
<div class="header">
        <h2 class="title"> <i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i> Approve Students</h2>
        <div id = "create" class="form-group text-center">
            <label><?php echo $ay_name; ?></label> <br> <label><?php echo $semester; ?></label><br>
        </div>
    </div>
    <div class="container">
        <div class="main">
        <h2>Import CSV</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="csv_file">Choose CSV file:</label>
        <input type="file" name="csv_file" id="csv_file" required>
        <button type="submit" name="import">Import</button>
    </form>
            <?php

            
            $studentQuery = "SELECT * FROM tbl_stud_acc WHERE college_code = ? AND reg_adviser = ? AND status = 'pending'";
            $stmt = $conn->prepare($studentQuery);
            $stmt->bind_param("ss", $college_code, $prof_name);
            $stmt->execute();
            $studentResult = $stmt->get_result();
             if ($studentResult->num_rows > 0): ?>
                <div class="user-accounts">
                    <form method="POST" action="approval.php">
                        <table style="border-collapse: separate;">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                                    <th>Department</th>
                                    <th>Student Number</th>
                                    <th>Program Code</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                    <th>Middle Initial</th>
                                    <th>Cvsu Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    while ($row = $studentResult->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td><input type='checkbox' name='student_select[]' value='" . $row['student_no'] . "'></td>"; 
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['dept_code'] . "</p></td>";
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['student_no'] . "</p></td>";
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['program_code'] . "</p></td>"; 
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['last_name'] . "</p></td>";
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['first_name'] . "</p></td>";
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['middle_initial'] . "</p></td>";
                                        echo "<td><p style='margin-bottom: 0px;'>" . $row['cvsu_email'] . "</p></td>";
                                        echo "</tr>";
                                    }
                                ?>
                            </tbody>
                        </table>
                        <button type="submit" class="btn-approve">Approve</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fa-solid fa-users-slash"></i>
                    <p>You don't have Student records</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Structure -->
    <div id="resultModal" class="modal" style="display: <?= !empty($modalMessage) ? 'block' : 'none' ?>;">
        <div class="modal-content" style="width: 30%;">
            <p><?= htmlspecialchars($modalMessage); ?></p>
            <button onclick="closeModal()">OK</button>
        </div>
    </div>

    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll("input[name='student_select[]']");
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        function closeModal() {
            document.getElementById("resultModal").style.display = "none";
            window.location.href = 'approval.php'; // Redirect back after closing modal
        }
    </script>
</body>
</html>