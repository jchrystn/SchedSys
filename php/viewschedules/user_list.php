<?php
include("../config.php");

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


session_start();

$college_code = $_SESSION['college_code'];
$dept_code = $_SESSION['dept_code'];

$prof_code = $_SESSION['prof_code'];
$current_user_email = htmlspecialchars($_SESSION['cvsu_email'] ?? '');
$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

$college_name = '';
$collegeQuery = $conn->prepare("SELECT college_name FROM tbl_college WHERE college_code = ?");
$collegeQuery->bind_param("s", $college_code);
$collegeQuery->execute();
$collegeQuery->bind_result($college_name);
$collegeQuery->fetch();
$collegeQuery->close();

// Fetch department name
$dept_name = '';
$deptQuery = $conn->prepare("SELECT dept_name FROM tbl_department WHERE dept_code = ?");
$deptQuery->bind_param("s", $dept_code);
$deptQuery->execute();
$deptQuery->bind_result($dept_name);
$deptQuery->fetch();
$deptQuery->close();

$prof_name = isset($_SESSION['prof_name']) ? $_SESSION['prof_name'] : 'User';
$fetch_info_query = "SELECT reg_adviser FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    if ($row['reg_adviser'] == 0) {
        $current_user_type = $user_type;
    } else {
        $current_user_type = "Registration Adviser";
    }
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



if (!isset($_SESSION['user_type']) || $current_user_type != 'Registration Adviser') {
    header("Location: ../login/login.php");
    exit();
}



$college_code = $_SESSION['college_code'];
$prof_code = $_SESSION['prof_code'];
$modalMessage = '';  // Initialize modal message

$sectionQuery = "SELECT DISTINCT section_code FROM tbl_registration_adviser WHERE reg_adviser = ? AND dept_code = ?";
$sectionStmt = $conn->prepare($sectionQuery);
$sectionStmt->bind_param("ss", $prof_name, $dept_code);
$sectionStmt->execute();
$sectionResult = $sectionStmt->get_result();

$sections = [];
while ($sectionRow = $sectionResult->fetch_assoc()) {
    $sections[] = $sectionRow['section_code'];
}

$selected_section = isset($_POST['filter_section']) ? $_POST['filter_section'] : '';


if ($selected_section) {
    $studentQuery = "SELECT * FROM tbl_stud_acc WHERE college_code = ? AND reg_adviser = ? AND section_code = ?";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("sss", $college_code, $prof_name, $selected_section);
} else {
    $studentQuery = "SELECT * FROM tbl_stud_acc WHERE college_code = ? AND reg_adviser = ?";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("ss", $college_code, $prof_name);
}
$stmt->execute();
$studentResult = $stmt->get_result();


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['action'])) {
        // Extract action and student number
        list($action, $student_no) = explode('-', $_POST['action']);
        $student_no = intval($student_no);

        // Determine new acc_status based on the action
        $newStatus = ($action === 'lock') ? 1 : 0;

        $sql = "UPDATE tbl_stud_acc SET acc_status = '$newStatus' WHERE student_no = '$student_no'";

        if ($conn->query($sql) === TRUE) {
            $modalMessage = $action === 'lock'
                // ? "Student $student_no has been disabled."
                ? "Access has now been granted to the student $student_no."
                : "Student $student_no has been disabled.";

            // Optional: Fetch details for additional actions
            $fetchDetails = "SELECT cvsu_email, last_name, student_no FROM tbl_stud_acc WHERE student_no = '$student_no'";
            $result = $conn->query($fetchDetails);
        } else {
            $modalMessage = "Error updating record: " . $conn->error;
        }
    }
}


$mailConfig = [
    'host' => 'smtp.gmail.com',
    'username' => 'nuestrojared305@gmail.com',
    'password' => 'bfruqzcgrhgnsrgr',
    'port' => 587,
    'from_email' => 'schedsys14@gmail.com',
    'from_name' => 'SchedSys'
];

if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {

    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['token'] = bin2hex(random_bytes(32));
    $file = $_FILES['csv_file']['tmp_name'];

    if (($handle = fopen($file, 'r')) !== FALSE) {
        fgetcsv($handle); // Skip header
        $duplicateStudents = [];
        $invalidStudents = [];
        $section_code = $_POST['section_code'];
        $program_code = $_POST['program_code'];

        $stmt = $conn->prepare("SELECT num_year FROM tbl_program WHERE program_code = ? AND dept_code = ? AND college_code = ?");
        $stmt->bind_param('sss', $program_code, $dept_code, $college_code);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $year_level = 0;

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $year_level = $row['num_year'];
            } else {
                echo "No matching program found!";
                exit();
            }

            $insertSql = "INSERT INTO tbl_stud_acc (college_code, dept_code, student_no, password, last_name, first_name, suffix, middle_initial, cvsu_email, program_code, status, acc_status, reg_adviser, section_code, remaining_years, num_year) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            if ($insertStmt = $conn->prepare($insertSql)) {

                while (($data = fgetcsv($handle)) !== FALSE) {
                    $student_no = trim($data[0]);
                    $password = generateRandomPassword();
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $last_name = trim($data[1]);
                    $first_name = trim($data[2]);
                    $middle_initial = trim($data[3]);
                    $suffix = trim($data[4]);
                    $cvsu_email = trim($data[5]);

                    // Basic validation
                    if (strlen($student_no) !== 9 || !preg_match('/@cvsu\.edu\.ph$/', $cvsu_email)) {
                        $invalidStudents[] = "$last_name, $first_name ($student_no)";
                        continue;
                    }

                    $remaining_years = $year_level - 1;
                    $status = 'approve';
                    $acc_status = 1;

                    $checkStmt = $conn->prepare("SELECT student_no FROM tbl_stud_acc WHERE student_no = ?");
                    $checkStmt->bind_param('s', $student_no);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();

                    if ($checkResult->num_rows > 0) {
                        $duplicateStudents[] = "$last_name, $first_name ($student_no)";
                        sendStudentEmail($cvsu_email, $last_name, $student_no, 'Already Registered', $mailConfig);
                    } else {
                        $insertStmt->bind_param(
                            "ssisssssssssssss",
                            $college_code,
                            $dept_code,
                            $student_no,
                            $hashed_password,
                            $last_name,
                            $first_name,
                            $suffix,
                            $middle_initial,
                            $cvsu_email,
                            $program_code,
                            $status,
                            $acc_status,
                            $prof_name,
                            $section_code,
                            $remaining_years,
                            $year_level
                        );

                        if ($insertStmt->execute()) {
                            sendStudentEmail($cvsu_email, $last_name, $student_no, $password, $mailConfig);
                        } else {
                            echo "Error inserting student $student_no<br>";
                        }
                    }
                    $checkStmt->close();
                }

                $insertStmt->close();
            } else {
                echo "Failed to prepare insert statement.";
            }
        } else {
            echo "Failed to fetch year level.";
        }

        fclose($handle);
    } else {
        echo "Unable to open the CSV file.";
    }

    $conn->close();

    // Build the alert message
    $modalMessage = "";

    if (!empty($duplicateStudents)) {
        $modalMessage .= "<div class='alert alert-warning' role='alert'>";
        $modalMessage .= "<strong>Import completed.</strong> The following student records are <strong>duplicates</strong> and were not added:";
        $modalMessage .= "<ul class='mt-2 mb-0'>";
        foreach ($duplicateStudents as $student) {
            $modalMessage .= "<li>$student</li>";
        }
        $modalMessage .= "</ul></div>";
    }

    if (!empty($invalidStudents)) {
        $modalMessage .= "<div class='alert alert-danger' role='alert'>";
        $modalMessage .= "<strong>Invalid entries found.</strong> The following student records were <strong>not added</strong> due to invalid student numbers or email addresses:";
        $modalMessage .= "<ul class='mt-2 mb-0'>";
        foreach ($invalidStudents as $student) {
            $modalMessage .= "<li>$student</li>";
        }
        $modalMessage .= "</ul></div>";
    }

    if (empty($duplicateStudents) && empty($invalidStudents)) {
        $modalMessage .= "<div class='alert alert-success' role='alert'>CSV import and emails completed. No duplicate or invalid records found.</div>";
    }
}

// Function to send the email
function sendStudentEmail($email, $last_name, $student_number, $password, $mailConfig) {
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

        if ($password === 'Already Registered') {
            $mail->Body = "Dear $last_name,<br><br>Your account already exists in our system.<br><br>Thank you.<br>";
        } else {
            $mail->Body = "Dear $last_name,<br><br>We are pleased to provide you with your login details:<br><br>
                          <strong>Student Number:</strong> $student_number<br>
                          <strong>Password:</strong> $password<br><br>
                          Please change your password after your first login.<br><br>Thank you.";
        }

        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed for student $student_number: " . $e->getMessage());
    }
}


// Function to generate a random password
function generateRandomPassword() {
    $length = 8;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.3.0/dist/exceljs.min.js"></script>

 <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link rel="stylesheet" href="approval.css">
</head>

<body>
    <!-- Navbar -->
    <?php if ($user_type === 'Professor' || $user_type === 'Department Chairperson'): ?>
        <?php
        $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
        include($IPATH . "professor_navbar.php");
        ?>
    <?php endif ?>

    <?php if ($user_type === 'Department Secretary' || $user_type === 'CCL Head'): ?>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php"); ?>
    <?php endif ?>

    <div class="header">
        <h2 class="title"> <i class="far fa-copy" style="color: #FD7238;"></i> Students List</h2>
        <div id="create" class="form-group text-center">
            <label><?php echo $ay_name; ?></label> <br> <label><?php echo $semester; ?></label><br>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-4 mb-1">
            <div class="card text-center shadow-sm h-55" role="button" data-bs-toggle="modal" data-bs-target="#csvModal">
                <div class="card-body">
                    <i class="bi bi-file-earmark-arrow-up-fill text-primary" style="font-size: 1rem;"></i>
                    <h4 class="card-title mt-1"><i class="fas fa-file-import"></i><br>Import CSV</h4>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-1">
            <a href="../new-admin/create_stud.php" style="text-decoration: none; color: inherit;">
                <div class="card text-center shadow-sm h-55">
                    <div class="card-body">
                        <i class="bi bi-person-plus-fill text-success" style="font-size: 10px;"></i>
                        <h4 class="card-title mt-1">
                            <i class="fas fa-plus-circle"></i><br>Manual Adding
                        </h4>
                    </div>
                </div>
            </a>
        </div>
    </div>
   <div class="container">
    <div class="main">
        <div class="user-accounts">
            <form method="POST" action="">
                <!-- Filter Row -->
                <div class="row mb-3 align-items-center justify-content-end">
                    <div class="col-md-3 order-1">
                        <select class="form-control" name="filter_section" id="filter_section" onchange="this.form.submit()">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= htmlspecialchars($section) ?>" <?= ($section === $selected_section) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 order-2">
                        <label for="filter_section" class="form-label mb-0">
                            <i class="fas fa-filter"></i> Filter Section
                        </label>
                    </div>
                </div>

                <!-- View Report Button -->
                <div class="row mb-3 align-items-center justify-content-end">
                    <div class="col-md-2 order-2">
                        <button type="button" class="btn btn-success" onclick="ViewReport()">
                            <i class="fas fa-clipboard"></i> View Report
                        </button>
                    </div>
                </div>

                <div id="student-table-pdf">
                    <?php if ($studentResult->num_rows > 0): ?>
                        <table class="table" style="border-collapse: separate;">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Program Code</th>
                                    <th>Name</th>
                                    <th>Section</th>
                                    <th>Cvsu Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                while ($row = $studentResult->fetch_assoc()) {
                                    if ($row["status"] === "pending") continue;

                                    $acc_status = ($row["acc_status"] == 1) ? 'Active' : 'Disabled';
                                    $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
                                    $student_no = $row['student_no'];

                                    echo "<tr>";
                                    echo "<td>" . $student_no . "</td>";
                                    echo "<td>" . $row['program_code'] . "</td>";
                                    echo "<td>" . htmlspecialchars($full_name) . "</td>";
                                    echo "<td>" . $row['section_code'] . "</td>";
                                    echo "<td>" . $row['cvsu_email'] . "</td>";
                                    echo "<td>";
                                    if ($row["acc_status"] == 0) {
                                        echo "<button type='submit' name='action' value='lock-" . $student_no . "' class='btn btn-danger' style='font-size: 10px'>
                                                    <i class='fa fa-lock'></i>
                                                </button>";
                                    } else {
                                        echo "<button type='submit' name='action' value='unlock-" . $student_no . "' class='btn btn-success' style='font-size: 10px'>
                                                    <i class='fas fa-lock-open'></i>
                                                </button>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data text-center">
                            <i class="fa-solid fa-users-slash fa-2x"></i>
                            <p>You don't have Student records</p>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="pdf-template" style="
  display: none;
  padding: 20px;
  margin: 0 auto;
  font-family: Arial, sans-serif;
  font-size: 10px;
  width: 100%;
  max-width: 794px; /* A4 width in px at 96dpi */
  box-sizing: border-box;
  background-color: white;
">
    <!-- Header Section -->
    <div style="position: relative; text-align: center; padding: 10px;">
        <!-- Image Section -->
        <div style="position: absolute; left: 170px; top: 0;">
            <img src="/SchedSys3/images/cvsu_logo.png" style="width: 70px; height: 60px;">
        </div>
        
        <!-- Text Section -->
        <div>
            <p style="margin: 0;"></p>
            <p style="text-align: center; font-size: 6px; margin: 0; font-family: 'Century Gothic', Arial, sans-serif;">
                Republic of the Philippines
            </p>
            <p style="text-align: center; font-size: 12px; font-weight: bold; margin: 0; font-family: 'Bookman Old Style', serif;">
                CAVITE STATE UNIVERSITY
            </p>
            <p style="text-align: center; font-size: 8px; font-weight: bold; margin: 0; font-family: 'Century Gothic', Arial, sans-serif;">
                Don Severino de las Alas Campus
            </p>
            <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: 'Century Gothic', Arial, sans-serif;">
                Indang, Cavite
            </p>
            <p style="text-align: center; font-size: 9px; font-weight: bold; margin: 0; font-family: Arial, sans-serif;">
                <?= htmlspecialchars($college_name) ?>
            </p>
            <p style="text-align: center; font-size: 8px; margin: 0; margin-bottom: 10px; font-family: Arial, sans-serif;">
                <?= htmlspecialchars($dept_name) ?>
            </p>
            <p id="sectionTitle" style="text-align: center; font-size: 11px; margin: 0; font-weight: bold; font-family: Arial, sans-serif;">
                <?= htmlspecialchars($selected_section) ?> CLASS LIST
            </p>
        </div>
    </div>

<p style="font-size: 10px; margin-left: 20px;">
  <b>Registration Adviser: </b>   <?php echo htmlspecialchars($prof_name); ?>
</p>
    <!-- Student Table -->
  <table cellspacing="0" cellpadding="5" width="100%" style="border-collapse: collapse; font-size: 10px; text-align: left; margin-top: 20px; margin-left: 20px;">
    <thead>
        <tr>
            <th style="border: 1px solid #000; padding: 5px;">#</th>
            <th style="border: 1px solid #000; padding: 5px;">Student Number</th>
            <th style="border: 1px solid #000; padding: 5px;">Program Code</th>
            <th style="border: 1px solid #000; padding: 5px;">Name</th>
            <th style="border: 1px solid #000; padding: 5px;">Section</th>
            <th style="border: 1px solid #000; padding: 5px;">Cvsu Email</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $studentResult->data_seek(0); // reset pointer
        $counter = 1;
        while ($row = $studentResult->fetch_assoc()) {
            if ($row["acc_status"] === 0) continue;
            $acc_status = ($row["acc_status"] == 1) ? 'Active' : 'Disabled';
            $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
            echo "<tr>
                    <td style='border: 1px solid #000; padding: 5px;'>{$counter}</td>
                    <td style='border: 1px solid #000; padding: 5px;'>{$row['student_no']}</td>
                    <td style='border: 1px solid #000; padding: 5px;'>{$row['program_code']}</td>
                    <td style='border: 1px solid #000; padding: 5px;'>" . htmlspecialchars($full_name) . "</td>
                    <td style='border: 1px solid #000; padding: 5px;'>{$row['section_code']}</td>
                    <td style='border: 1px solid #000; padding: 5px;'>{$row['cvsu_email']}</td>
                </tr>";
            $counter++;
        }
        ?>

    </tbody>
</table>




</div>

<script>
  const originalTemplate = document.getElementById("pdf-template");

  function showPDFPreviewModal() {
    const modalBody = document.getElementById("pdfPreviewContent");
    modalBody.innerHTML = originalTemplate.innerHTML;
    const modal = new bootstrap.Modal(document.getElementById("pdfPreviewModal"));
    modal.show();
  }
   
function ViewReport() {
    const pdfContent = document.getElementById("pdf-template").cloneNode(true);
    pdfContent.style.display = "block";

    // Inject into modal
    const previewContainer = document.getElementById("pdfPreviewContent");
    previewContainer.innerHTML = "";
    previewContainer.appendChild(pdfContent);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
    modal.show();
}

// function downloadPDF() {
//     const pdfElement = document.querySelector("#pdfPreviewContent > #pdf-template");
//     const opt = {
//         margin:       0.3,
//         filename:     'class_list.pdf',
//         image:        { type: 'jpeg', quality: 0.98 },
//         html2canvas:  { scale: 2 },
//         jsPDF:        { unit: 'in', format: 'A4', orientation: 'portrait' }
//     };
//     html2pdf().from(pdfElement).set(opt).save();
// }

function downloadPDF() {
  const element = document.querySelector("#pdfPreviewContent > #pdf-template");

  const opt = {
    margin: [0.5, 0.5, 0.5, 0.5], // top, left, bottom, right (in inches)
    filename: 'class_list.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      scrollX: 0,
      scrollY: 0
    },
    jsPDF: {
      unit: 'in',
      format: 'a4',
      orientation: 'portrait'
    },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };

  html2pdf().set(opt).from(element).save();
}


async function exportStyledExcel() {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet("Class List");

    // Page setup
    worksheet.pageSetup = {
        paperSize: 9, // A4
        orientation: 'portrait',
        fitToPage: true,
        fitToWidth: 1,
        fitToHeight: 1,
        margins: { left: 0.3, right: 0.3, top: 0.5, bottom: 0.5, header: 0.2, footer: 0.2 }
    };

    // Add logo image
    const imageUrl = "http://localhost/SchedSys3/images/cvsu_logo.png"; // Update as needed
    const imageBase64 = await fetch(imageUrl)
        .then(res => res.blob())
        .then(blob => new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result.split(",")[1]); // Remove base64 prefix
            reader.readAsDataURL(blob);
        }));

    const imageId = workbook.addImage({
        base64: imageBase64,
        extension: "png"
    });

    worksheet.addImage(imageId, {
    tl: { col: 3, row: 0.2 }, // Column C (index 2), near top
    ext: { width: 70, height: 70 } // Adjust size as needed
});

    // Top right code
    worksheet.getCell('G1').alignment = { horizontal: "right", vertical: "middle" };
    worksheet.getCell('G1').font = { italic: true, size: 8, name: 'Arial' };

    // Function to add merged headers
    function addMergedRow(text, rowNumber, fontSize = 12, bold = false) {
        worksheet.mergeCells(rowNumber, 1, rowNumber, 7);
        let cell = worksheet.getRow(rowNumber).getCell(1);
        cell.value = text;
        cell.alignment = { horizontal: "center", vertical: "middle" };
        cell.font = { size: fontSize, name: 'Arial', bold: bold };
    }

    let rowIndex = 2;
    addMergedRow("Republic of the Philippines", rowIndex++);
    addMergedRow("Cavite State University", rowIndex++, 12, true);
    addMergedRow("Don Severino de las Alas Campus", rowIndex++, 10, true);
    addMergedRow("Indang, Cavite", rowIndex++);
    addMergedRow("<?= $college_name ?>", rowIndex++, 11, true);
    addMergedRow("<?= $dept_name ?>", rowIndex++);
    addMergedRow("<?= $selected_section ?> CLASS LIST", rowIndex++, 11, true);
    rowIndex++;

    // Registration adviser
    worksheet.mergeCells(rowIndex, 1, rowIndex, 7);
    worksheet.getCell(`A${rowIndex}`).value = "Registration Adviser: <?= $prof_name ?>";
    worksheet.getCell(`A${rowIndex}`).font = { bold: true, size: 10, name: 'Arial' };
    worksheet.getCell(`A${rowIndex}`).alignment = { horizontal: "left" };
    rowIndex++;

    // Table headers
    const headers = ['#', 'Student Number', 'Program Code', 'Name', 'Section', 'Cvsu Email'];
    worksheet.addRow(headers).eachCell(cell => {
        cell.font = { bold: true, size: 10, name: 'Arial' };
        cell.alignment = { horizontal: "center", vertical: "middle" };
        cell.border = {
            top: { style: "thin" },
            left: { style: "thin" },
            bottom: { style: "thin" },
            right: { style: "thin" }
        };
    });

    // Student data from PHP (embedded)
    <?php
    $studentResult->data_seek(0);
    $rows = [];
    $counter = 1;
    while ($row = $studentResult->fetch_assoc()) {
        if ($row["status"] === "pending") continue;
        $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
        $rows[] = [$counter++, $row['student_no'], $row['program_code'], $full_name, $row['section_code'], $row['cvsu_email']];
    }
    ?>

    const studentData = <?= json_encode($rows) ?>;

    // Add student rows
    studentData.forEach(row => {
        const newRow = worksheet.addRow(row);
        newRow.eachCell(cell => {
            cell.font = { size: 10, name: 'Arial' };
            cell.alignment = { vertical: "middle", horizontal: "left", wrapText: true };
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" }
            };
        });
    });

worksheet.columns.forEach(column => {
    let maxLength = 0;
    column.eachCell({ includeEmpty: true }, cell => {
        let value = cell.value;

        if (value && typeof value === 'object' && value.richText) {
            value = value.richText.map(rt => rt.text).join('');
        }

        const text = value ? value.toString() : '';
        const lines = text.split(/\r\n|\r|\n/);
        lines.forEach(line => {
            // Use character length but limit tiny columns from becoming too wide
            maxLength = Math.max(maxLength, line.length);
        });
    });

    // Adjust logic: smaller base width for tight columns
    column.width = Math.min(Math.max(maxLength + 2, 6), 30); // min 6, max 30
});



    // Save file
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = "Class_List.xlsx";
    anchor.click();
    URL.revokeObjectURL(url);
}


</script>

    <div class="modal fade" id="csvModal" tabindex="-1" role="dialog" aria-labelledby="csvModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                    <h5 class="modal-title" id="createContactTableModalLabel">Import CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="csv_form">
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">

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

                        <div class="form-group">
                            <select class="form-control w-100" name="program_code" id="program_code" required>
                                <option value="" disabled selected>Program Code</option>
                                <?php 
                                // Display unique program_code in the dropdown
                                foreach (array_unique(array_column($programs, 'program_code')) as $program_code): ?>
                                    <option value="<?php echo htmlspecialchars($program_code); ?>" 
                                        <?php if ($program_code == $search_program_code) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($program_code); ?>
                                    </option>
                                <?php endforeach; ?>
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
                        <input type="hidden" id="prof_name" name="prof_name" value="<?php echo htmlspecialchars($prof_name); ?>" readonly>
                            <input type="hidden" id="ay_code" name="ay_code" value="<?php echo htmlspecialchars($ay_code); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <input type="hidden" id="semester" name="semester" value="<?php echo htmlspecialchars($semester); ?>" readonly>
                        </div>          
                        <div class="form-group">
                            <label for="csv_file">Choose CSV file:</label>
                            <input type="file" class="form-control" name="csv_file" id="csv_file" required>
                        </div> <br>
                        <button type="submit" name="import" class="btn btn-dark">Import</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-labelledby="pdfPreviewLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable" style="max-width: 60%; ">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="pdfPreviewLabel">Report Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="pdfPreviewContent" style="height: 80vh; overflow: auto;">
        <!-- PDF content will be injected here -->
      </div>
      <div class="modal-footer d-flex justify-content-end">
        <div class="d-flex justify-content-end gap-2 w-100">
          <button class="btn"  style="width:20%; background-color: #FD7238; color: white;" onclick="downloadPDF()">Download PDF</button>
          <button class="btn" onclick="exportStyledExcel()" style="width:20%; background-color: #FD7238; color: white;">Export to Excel</button>
        </div>
      </div>
    </div>
  </div>
</div>




    <!-- Modal Structure -->
   <div id="resultModal" class="modal" style="display: <?= !empty($modalMessage) ? 'block' : 'none' ?>;">
        <div class="modal-content" style="width: 30%;">
            <div><?= $modalMessage; ?></div>
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
            window.location.href = 'user_list.php'; // Redirect back after closing modal
        }

        const programs = <?php echo json_encode($programs); ?>;

// Function to get the appropriate suffix for year levels
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

// Function to populate Year Levels based on selected program_code and curriculum
function populateYearLevels(selectedProgramCode, selectedCurriculum) {
    const yearLevelDropdown = document.getElementById('year_level');
    yearLevelDropdown.innerHTML = '<option value="" disabled selected>Year Level</option>';

    const filteredPrograms = programs.filter(program => 
        program.program_code === selectedProgramCode && program.curriculum === selectedCurriculum
    );

    if (filteredPrograms.length > 0) {
        const numYears = filteredPrograms[0].num_year; // Get num_year for the selected program

        // Populate year level options based on num_year with correct suffix
        for (let i = 1; i <= numYears; i++) {
            const suffix = getSuffix(i); // Get appropriate suffix
            const yearLevelText = `${i}${suffix} Year`; // e.g., "1st Year", "2nd Year"
            yearLevelDropdown.innerHTML += `<option value="${i}">${yearLevelText}</option>`;
        }
    }
}

// Function to populate Section Codes based on selected inputs
    function populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel) {
    const sectionDropdown = document.getElementById('section_code');
    const ayCode = document.getElementById('ay_code').value; // Correctly retrieve the value
    const semester = document.getElementById('semester').value; // Retrieve the value
    const profName = document.getElementById('prof_name').value; // Retrieve the value

    sectionDropdown.innerHTML = '<option value="">Select a section</option>'; // Reset dropdown

    if (selectedProgramCode && selectedCurriculum && selectedYearLevel && ayCode && semester) {
        // Fetch sections based on the provided input values
        fetch(`get_sections.php?program_code=${selectedProgramCode}&curriculum=${selectedCurriculum}&year_level=${selectedYearLevel}&ay_code=${ayCode}&semester=${semester}&prof_name=${profName}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    data.forEach(section => {
                        sectionDropdown.innerHTML += `<option value="${section.section_code}">${section.section_code}</option>`;
                    });
                } else {
                    sectionDropdown.innerHTML = '<option value="">No sections available</option>';
                }
            })
            .catch(error => console.error('Error fetching sections:', error));
    }
}

// Populate Year Levels and Curriculums based on selected program_code
document.getElementById('program_code').addEventListener('change', function() {
    const selectedProgramCode = this.value;
    const curriculumDropdown = document.getElementById('curriculum');
    
    // Clear existing options in curriculum dropdown
    curriculumDropdown.innerHTML = '<option value="" disabled selected>Curriculum</option>';

    // Populate curriculum based on the selected program
    const selectedPrograms = programs.filter(program => program.program_code === selectedProgramCode);
    selectedPrograms.forEach(program => {
        curriculumDropdown.innerHTML += `<option value="${program.curriculum}">${program.curriculum}</option>`;
    });
});

// Add event listener for curriculum changes
document.getElementById('curriculum').addEventListener('change', function() {
    const selectedProgramCode = document.getElementById('program_code').value;
    const selectedCurriculum = this.value;

    if (selectedProgramCode && selectedCurriculum) {
        populateYearLevels(selectedProgramCode, selectedCurriculum);
    }
});

// Add event listener for year level changes
document.getElementById('year_level').addEventListener('change', function() {
    const selectedProgramCode = document.getElementById('program_code').value;
    const selectedCurriculum = document.getElementById('curriculum').value;
    const selectedYearLevel = this.value;

    if (selectedProgramCode && selectedCurriculum && selectedYearLevel) {
        populateSections(selectedProgramCode, selectedCurriculum, selectedYearLevel);
    }
});

    </script>
</body>

</html>