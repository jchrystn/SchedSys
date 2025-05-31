<?php
require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../config.php");
session_start();

function generateVerificationCode()
{
    return mt_rand(100000, 999999);
}

function sendVerificationCode($email, $new_code)
{
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
        $mail->addAddress($email);                                  // Recipient's email address

        //Content
        $mail->isHTML(true);                                        // Set email format to HTML
        $mail->Subject = 'Verification Code';
        $mail->Body = 'Your verification code is<br><br>' . $new_code . '<br><br>The verification code will expire in 3 minutes. If you did not request this code, please ignore this message.';

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

if (isset($_POST['request'])) {
    if (isset($_SESSION['cvsu_email'])) {
        $new_code = generateVerificationCode();
        $email = $_SESSION['cvsu_email'];

        $current_time = time();
        $expiry_time = $current_time + (3 * 60);

        // First, check if the email exists in tbl_code
        $sql_check = "SELECT * FROM tbl_code WHERE cvsu_email = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        // Check if a record exists
        if ($result->num_rows > 0) {
            // Update using prepared statements
            $sql_update = "UPDATE tbl_code SET code=?, code_created_at=?, code_expiry=? WHERE cvsu_email=?";
            $update_stmt = $conn->prepare($sql_update);
            $update_stmt->bind_param("siis", $new_code, $current_time, $expiry_time, $email);
            $update_stmt->execute();

            // Send the verification code and give feedback to the user
            if (sendVerificationCode($email, $new_code)) {

                $message = "Your new verification code has been sent to your email.";
                $redirect = "reg-verification-code.php";

                echo "<script>
                    window.customAlertMessage = " . json_encode($message) . ";
                    window.customRedirectURL = " . json_encode($redirect) . ";
                </script>";

            } else {
                $message = "Failed to send the verification code. Please try again later.";
                $redirect = "reg-verification-code.php";

                echo "<script>
                    window.customAlertMessage = " . json_encode($message) . ";
                    window.customRedirectURL = " . json_encode($redirect) . ";
                </script>";
            }
        } else {
            $message = "Email not found in the system.";
            $redirect = "login.php";

            echo "<script>
                    window.customAlertMessage = " . json_encode($message) . ";
                    window.customRedirectURL = " . json_encode($redirect) . ";
                </script>";
        }
    }
}

if (isset($_POST['submit'])) {
    $entered_code = implode('', $_POST['code']);


    $cvsu_email = $_SESSION['cvsu_email']; // Access email from session
    $first_name = $_SESSION['first_name']; // Access first name from session
    $middle_initial = $_SESSION['mi']; // Access middle initial from session
    $last_name = $_SESSION['last_name']; // Access last name from session
    $suffix = $_SESSION['suffix']; // Access suffix from session
    $college = $_SESSION['college']; // Access college from session
    $department = $_SESSION['department']; // Access department from session
    $password = '';
    $status = 'pending';
    $user_type = $_SESSION['user_type'];


    $fetch_info_query = "SELECT ay_code, ay_name, semester FROM tbl_ay WHERE college_code = '$college' AND active = '1'";
    $result = $conn->query($fetch_info_query);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $ay_code = $row['ay_code'];
        $ay_name = $row['ay_name'];
        $semester = $row['semester'];
    }

    // Directly use the query to fetch the code and expiry from tbl_code
    $sql_select = "
        SELECT code, code_expiry FROM tbl_code WHERE cvsu_email='$cvsu_email'
    ";

    $result = mysqli_query($conn, $sql_select);  // Use mysqli_query since there is no binding needed

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stored_code = $row['code'];
        $code_expiry = $row['code_expiry'];

        if (time() > $code_expiry) {
            $message = "The verification code has expired. Please request a new one.";
            $redirect = "reg-verification-code.php";

            echo "<script>
                    window.customAlertMessage = " . json_encode($message) . ";
                    window.customRedirectURL = " . json_encode($redirect) . ";
                </script>";

        } else {

            if ($entered_code == $stored_code && $user_type == 'Professor') {
                $designation = $_SESSION['designation']; // Access professor unit from session
                $prof_unit = $_SESSION['prof_unit']; // Access professor unit from session
                $acc_status = '0';

                $sql_insert = $conn->prepare("INSERT INTO `tbl_prof_acc`(`college_code`, `dept_code`, `password`, `last_name`, `first_name`, `middle_initial`,`suffix`, `prof_unit`, `cvsu_email`, `status`,`user_type`,`designation`,`acc_status`,`semester`,`ay_code`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)");

                $sql_insert->bind_param("ssssssssssssiss", $college, $department, $password, $last_name, $first_name, $middle_initial, $suffix, $prof_unit, $cvsu_email, $status, $user_type, $designation, $acc_status, $semester, $ay_code);

                if ($sql_insert->execute()) {
                    $message = "Successfully registered!";
                    $redirect = "login.php";

                    echo "<script>
                window.customAlertMessage = " . json_encode($message) . ";
                window.customRedirectURL = " . json_encode($redirect) . ";
            </script>";


                } else {
                    echo "Error: " . $sql_insert->error;
                }

                $sql_insert->close();
            } elseif ($entered_code == $stored_code && $user_type == 'Student') {
                $student_number = $_SESSION['student_number'];
                $reg_ad = $_SESSION['reg_ad']; // Store academic rank
                $program = $_SESSION['program']; // Store professor code
                $acc_status = '0';

                $sql_insert = $conn->prepare("INSERT INTO `tbl_stud_acc`(`college_code`, `dept_code`, `student_no`, `password`, `last_name`, `first_name`,`suffix`, `middle_initial`, `cvsu_email`, `program_code`, `status`,`reg_adviser`,`acc_status`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?,?,?)");

                $sql_insert->bind_param("ssssssssssssi", $college, $department, $student_number, $password, $last_name, $first_name, $suffix, $middle_initial, $cvsu_email, $program, $status, $reg_ad, $acc_status);
                if ($sql_insert->execute()) {

                    $message = "Successfully registered!";
                    $redirect = "login.php";

                    echo "<script>
                window.customAlertMessage = " . json_encode($message) . ";
                window.customRedirectURL = " . json_encode($redirect) . ";
            </script>";


                } else {
                    echo "Error: " . $sql_insert->error;
                }
                $sql_insert->close();
                $conn->close();
            } else {
                $message = "Incorrect verification code. Try again!";
                $redirect = "reg-verification-code.php";

                echo "<script>
                window.customAlertMessage = " . json_encode($message) . ";
                window.customRedirectURL = " . json_encode($redirect) . ";
            </script>";

            }
        }
    } else {
        echo '<script>
        alert("No verification code found for this email.");
        window.location.href="login.php";
        </script>';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSys</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="http://localhost/SchedSys3/bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <script src="http://localhost/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../../css/login/verification-code.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <script src="../../javascript/verification-code.js" defer></script>
</head>

<body>
    <div class="container">
        <form action="" method="post">
            <div class="input-field">
                <br>
                <h2 class="text-center">Verification Code</h2>
                <div class="text-center timer" id="timer"></div>
                <input type="number" name="code[]" required>
                <input type="number" name="code[]" disabled required>
                <input type="number" name="code[]" disabled required>
                <input type="number" name="code[]" disabled required>
                <input type="number" name="code[]" disabled required>
                <input type="number" name="code[]" disabled required>
            </div><br>
            <button class="form-control button" name="submit">Continue</button>
        </form>
        <form action="" method="post" id="resendForm">
            <div class="text">
                <br>
                <p>Didn't receive the code? <button type="submit" id="resend-code" name="request">Re-send code</button>
                </p>
            </div>
        </form>
    </div>
    <!-- Modal HTML -->


    <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="text-align: center;">
                    <div class="modal-body text-center fs-5" id="modalMessage">
                        <!-- JS will insert message here -->
                    </div>

                </div>
                <div class="modal-footer">
                </div>
            </div>
        </div>
    </div>

    <script>
        // When form is submitted (button clicked), reset the timer
        document.getElementById("resendForm").addEventListener("submit", function () {
            // Reset countdown start time in localStorage
            localStorage.setItem("countdownStart", Math.floor(Date.now() / 1000)); // Current timestamp in seconds
        });
    </script>

<script>
    var countdownDuration = 180; // 3 minutes in seconds
    var startTime = sessionStorage.getItem("countdownStart");

    if (!startTime) {
        startTime = Math.floor(Date.now() / 1000);
        sessionStorage.setItem("countdownStart", startTime);
    } else {
        startTime = parseInt(startTime); // Convert back to number
    }

    function timer() {
        var now = Math.floor(Date.now() / 1000);
        var elapsedTime = now - startTime;
        var remainingTime = countdownDuration - elapsedTime;

        if (remainingTime <= 0) {
            document.getElementById("timer").innerHTML = "Code expired";
            clearInterval(timerInterval);
            sessionStorage.removeItem("countdownStart"); // Reset if needed
            return;
        }

        var minutes = Math.floor(remainingTime / 60);
        var seconds = remainingTime % 60;

        // document.getElementById("timer").innerHTML =
           // "Time remaining: " + minutes + "m " + (seconds < 10 ? "0" : "") + seconds + "s";
    }

    var timerInterval = setInterval(timer, 1000);
    timer(); // Run immediately
</script>



    <!-- <script>
        var countdown = 180;

        function timer() {
            var minutes = Math.floor(countdown / 60);
            var seconds = countdown % 60;

            document.getElementById("timer").innerHTML = "Time remaining: " + minutes + "m " + seconds + "s";

            if (countdown == 0) {
                // Remove the comment if you want to disable the form and show a message when the countdown reaches 0

                // clearInterval(timerInterval);
                // document.querySelector('form').querySelectorAll('input').forEach(input => {
                //     input.disabled = true;
                // });
                // document.querySelector('button[name="submit"]').disabled = true;

                document.getElementById("timer").innerHTML = "Code expired";
            } else {
                countdown--;
            }
        }

        var timerInterval = setInterval(timer, 1000);
    </script> -->

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (window.customAlertMessage && window.customRedirectURL) {
                // Insert the message into the modal
                document.getElementById("modalMessage").textContent = window.customAlertMessage;

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("alertModal"));
                modal.show();

                // Redirect after 2.5 seconds
                setTimeout(() => {
                    window.location.href = window.customRedirectURL;
                }, 2500);
            }
        });
    </script>

</body>

</html>