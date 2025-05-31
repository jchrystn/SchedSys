<?php
include("../config.php");
session_start();

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


function generateVerificationCode()
{
    return mt_rand(100000, 999999);
}



if (isset($_POST['register'])) {

    $first_name = ucwords(strtolower($_POST['first_name'])); // Capitalizes first letter of each word
    $middle_initial = strtoupper($_POST['mi']) . '.'; // Middle initial remains uppercase
    $last_name = ucwords(strtolower($_POST['last_name'])); // Capitalizes first letter of each word
    $suffix = $_POST['suffix'];
    $input_email = $_POST['cvsu_email'];
    $cvsu_email = $input_email . "@cvsu.edu.ph";
    $college = strtoupper($_POST['college']);
    $department = strtoupper($_POST['department']);
    $prof_unit = strtoupper($_POST['prof_unit']);
    $designation = $_POST['designation'] ?? null; // Replace 'Default Designation' with a valid default if necessary
    $code = generateVerificationCode();
    $current_time = time();
    $expiry_time = $current_time + (3 * 60);
    $user_type = "Professor";
    $prof_name = $first_name . ' ' . strtoupper(substr($middle_initial, 0, 1))  . ' ' .  $last_name. ' ' . $suffix ;



    $_SESSION['cvsu_email'] = $cvsu_email; // Store email in session
    $_SESSION['suffix'] = $suffix; // Store email in session
    $_SESSION['first_name'] = $first_name; // Store first name
    $_SESSION['mi'] = $middle_initial; // Store middle initial
    $_SESSION['last_name'] = $last_name; // Store last name
    $_SESSION['prof_name'] = $prof_name;
    $_SESSION['college'] = $college; // Store college
    $_SESSION['department'] = $department; // Store department
    $_SESSION['prof_unit'] = $prof_unit;
    $_SESSION['designation'] = $designation;

    $_SESSION['user_type'] = $user_type; // Store professor unit

    $password = '';
    $status = 'pending';

    if (!preg_match("/@cvsu\.edu\.ph$/", $cvsu_email)) {
        $alertMessages[] = "Please use your CVSU email";
    }

    $fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college' AND active = '1'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ay_code = $row['ay_code'];
        $ay_name = $row['ay_name'];
        $semester = $row['semester'];
    }


    // Check if the same email has been registered in the same semester and academic year
    $check_email_query = "SELECT status FROM tbl_prof_acc WHERE cvsu_email = ? AND ay_code = ? AND semester = ? ";
    $check_stmt = $conn->prepare($check_email_query);
    $check_stmt->bind_param("sis", $cvsu_email,$ay_code,$semester );
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Email exists, check the status
        $existing_row = $check_result->fetch_assoc();
        $status = $existing_row['status'];

        if ($status === 'pending') {
            $alertMessages[] = "Your account is pending approval.";
        } elseif ($status === 'approve') {
            $alertMessages[] = "You already have an account.";
        }
    } else { // Perform additional check for prof_code uniqueness
            $sql_insert = "
                INSERT INTO tbl_code (code, code_created_at, code_expiry, cvsu_email) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                    code = VALUES(code), 
                    code_created_at = VALUES(code_created_at), 
                    code_expiry = VALUES(code_expiry)";

            $insert_stmt = $conn->prepare($sql_insert);
            $insert_stmt->bind_param("siis", $code, $current_time, $expiry_time, $cvsu_email);

            // Execute the insert query
            if ($insert_stmt->execute()) {
                // echo "Code inserted successfully";
            } else {
                // echo "". $insert_stmt->error;
            }

            $insert_stmt->close();


            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->SMTPDebug = SMTP::DEBUG_OFF;                         // Disable verbose debug output
                $mail->isSMTP();                                            // Send using SMTP
                $mail->Host = 'smtp.gmail.com';                       // Set the SMTP server to send through
                $mail->SMTPAuth = true;                                   // Enable SMTP authentication
                $mail->Username = 'nuestrojared305@gmail.com';            // SMTP username
                $mail->Password = 'bfruqzcgrhgnsrgr';                     // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
                $mail->Port = 587;                                    // TCP port to connect to

                //Recipients
                $mail->setFrom('schedsys14@gmail.com', 'SchedSys');         // Sender's email address and name
                $mail->addAddress($cvsu_email);                                  // Recipient's email address

                //Content
                $mail->isHTML(true);                                        // Set email format to HTML
                $mail->Subject = 'Verification Code';
                $mail->Body = 'Your verification code is<br><br>' . $code . '<br><br>The verification code will expire in 3 minutes. If you did not request this code, please ignore this message.';


                $mail->send();
                echo '<script>
                    window.location.href="reg-verification-code.php";
                    </script>';
                exit();
            } catch (Exception $e) {
                $alertMessages[] = "Use CvSu Email. ";
            }


        
    }
}

$colleges = []; // Your array of colleges
$sql = "SELECT college_code, college_name FROM tbl_college WHERE college_code = 'CEIT'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $colleges[$row['college_code']] = $row['college_code'];
    }
}

// Check if an AJAX request was made to fetch departments
if (isset($_POST['college_code'])) {
    $college_code = $_POST['college_code'];
    $departments = [];

    $sql = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row["dept_code"];
        }
    }

    // Prepare options for the department dropdown
    $options = '<option value="">Select Department</option>'; // Default option
    foreach ($departments as $dept_code) {
        $options .= '<option value="' . htmlspecialchars($dept_code) . '">' . htmlspecialchars($dept_code) . '</option>';
    }

    echo $options; // Send back the options to populate the dropdown
    exit; // Stop further execution of the script
}
if (isset($_POST['dept_code'])) {  // Use the correct key 'dept_code'
    // Fetch program units based on dept_code
    $deptCode = $_POST['dept_code'];
    // Query to get program units for the selected dept_code
    $query = "SELECT program_units FROM tbl_department WHERE dept_code = '$deptCode'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        echo '<option value="">Select Program Units</option>';
        while ($row = mysqli_fetch_assoc($result)) {
            // Split the comma-separated units and create options
            $units = explode(',', $row['program_units']);
            foreach ($units as $unit) {
                echo '<option value="' . trim($unit) . '">' . htmlspecialchars(trim($unit)) . '</option>';
            }
        }
    } else {
        echo '<option value="">No Program Units available</option>';
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSys</title>
    <link rel="icon" type="image/png" href="../images/orig-logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> -->
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <script src="/SchedSys3/jquery.js"></script>

    <link rel="stylesheet" href="../../css/login/register.css">
    <!-- <script src="../../javascript/verification-code.js" defer></script> -->
</head>

<script>
    // $(document).ready(function () {
    //     $('#college').change(function () {
    //         var collegeCode = $(this).val(); // Get the selected college code
    //         if (collegeCode) {
    //             $.ajax({
    //                 type: 'POST',
    //                 url: '', // Submit to the same page
    //                 data: { college_code: collegeCode },
    //                 success: function (response) {
    //                     $('#department').html(response); // Populate the department dropdown
    //                 }
    //             });
    //         } else {
    //             $('#department').html('<option value="">Select Department</option>'); // Reset department options
    //         }
    //     });
    // });

    $(document).ready(function () {
        $('#college').change(function () {
            var collegeCode = $(this).val(); // Get the selected college code
            if (collegeCode) {
                $.ajax({
                    type: 'POST',
                    url: '', // Submit to the same page
                    data: { college_code: collegeCode },
                    success: function (response) {
                        $('#department').html(response); // Populate the department dropdown
                    }
                });
            } else {
                $('#department').html('<option value="">Select Department</option>'); // Reset department options
            }
        });

        $(document).ready(function () {
            $('#department').change(function () {
                var deptCode = $(this).val(); // Get the selected department code
                if (deptCode && deptCode !== 'open') { // If dept_code is not "open"
                    $.ajax({
                        type: 'POST',
                        url: '', // Submit to the same page
                        data: { dept_code: deptCode }, // Send dept_code as data
                        success: function (response) {
                            $('#prof_unit').html(response); // Populate the program units dropdown
                        }
                    });
                } else {
                    // If dept_code is "open" or not selected, reset or hide the program units dropdown
                    $('#prof_unit').html('<option value="">Select Program Units</option>');
                }
            });
        });

    });

    function validateLetters(input) {
        // Allow only letters, spaces, and dashes (for hyphenated names)
        input.value = input.value.replace(/[^A-Za-z\s-]/g, '');
    }

    function validateMiddleInitial(input) {
        // Allow only one letter
        input.value = input.value.replace(/[^A-Za-z]/g, '').substring(0, 1);
    }
</script>


<body>
    <div class="container">
        <div class="form-container">
            <div class="title">
                <ul>
                    <li id="head">SIGN-UP</li>
                    <li>Schedule Management System</li>
                </ul>
            </div>
            <br>
            <form method="POST" action="register_prof.php">
                <div class="form-row">
                    <div class="form-column" style="display: flex; align-items: center;">
                        <input type="text" class="form-control" placeholder="Enter CvSU email" name="cvsu_email"
                            required style="flex: 1; border-top-right-radius: 0; border-right: 0; border-bottom-right-radius: 0;">
                        <span>
                            @cvsu.edu.ph
                        </span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                oninput="validateLetters(this)" pattern="[A-Za-z\s-]+" placeholder="Last Name" required>
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                oninput="validateLetters(this)" pattern="[A-Za-z\s-]+" placeholder="First Name"
                                required>
                        </div>
                    </div>

                    <div class="form-column">
                        <div class="form-group">
                            <input type="text" class="form-control" id="mi" name="mi" placeholder="Middle Initial"
                                maxlength="1" oninput="validateMiddleInitial(this)" pattern="[A-Za-z]{1}">
                        </div>
                    </div>
                    <div class="form-column">
                        <div class="form-group">
                            <select class="form-control form-select" name="suffix" id="suffix" placeholder="Suffix">
                                <option value="">Select Suffix</option>
                                <option value="Jr.">Jr.</option>
                                <option value="Sr.">Sr.</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Set CEIT as the default college -->
                <input type="hidden" name="college" value="CEIT">

                <div class="form-row">
                    <!-- Department Selection -->
                    <select name="department" id="department" class="form-control form-select" required>
                        <option value="" disabled selected>Select Department</option>
                        <?php
                        // Fetch departments dynamically based on college_code only
                        $college_code = 'CEIT';

                        $sql_departments = "SELECT dept_code, dept_name FROM tbl_department WHERE college_code = ?";
                        $stmt_departments = $conn->prepare($sql_departments);
                        $stmt_departments->bind_param("s", $college_code);
                        $stmt_departments->execute();
                        $result_departments = $stmt_departments->get_result();

                        if ($result_departments->num_rows > 0) {
                            while ($row = $result_departments->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['dept_code']) . '">' . htmlspecialchars($row['dept_name']) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>No departments found.</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-column">
                        <div class="form-group">
                            <select name="prof_unit" id="prof_unit" class="form-control form-select" required>
                                <option value="">Select Program Units</option> <!-- Default option -->
                            </select>
                        </div>
                    </div>
                </div>

            
                <div class="form-row">
                <div class="form-column">
                        <div class="form-group">
                            <input type="text" class="form-control" id="designation" name="designation" placeholder="Designation" >
                        </div>
                    </div>
                </div>


                <div class="button-container">
                    <button type="submit" class="submit-btn" name="register">Sign-Up</button>
                </div>
                <a href="login.php" class="form-link">Already have an account?</a>
            </form>
        </div>
    </div>

    <?php

    if (!empty($alertMessages)) {
        echo '
    <!-- Bootstrap Modal -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="alertModalLabel">Account Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style = "text-align: center;">';

        foreach ($alertMessages as $message) {
            echo '<p>' . htmlspecialchars($message) . '</p>';
        }

        echo '      </div>
                <div class="modal-footer">
                    <button type="button" class="btn" id= "modal-btn" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Show the modal automatically when the page loads
    document.addEventListener("DOMContentLoaded", function() {
        var alertModal = new bootstrap.Modal(document.getElementById("alertModal"));
        alertModal.show();

        // Redirect to login.php when the modal is hidden
        document.getElementById("alertModal").addEventListener("hidden.bs.modal", function() {
            window.location.href = "register_prof.php";
        });
    });
    </script>

    </body>
    </html>
    ';
    }
    ?>


</body>

</html>