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
        $mail->Host       = 'smtp.gmail.com';                       // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'nuestrojared305@gmail.com';            // SMTP username
        $mail->Password   = 'bfruqzcgrhgnsrgr';                     // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` also accepted
        $mail->Port       = 587;                                    // TCP port to connect to

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

$message = "";
$show_modal = false;
$reset_timer = false;

if (isset($_POST['request'])) {
    if (isset($_SESSION['reset_email'])) {
        $new_code = generateVerificationCode();
        $email = $_SESSION['reset_email'];

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
                $show_modal = true;
                $reset_timer = true; // Flag to reset timer in JavaScript
            } else {
                $message = "Failed to send the verification code. Please try again.";
                $show_modal = true;
            }
        } else {
            $message = "No verification code found for this email.";
            $show_modal = true;
        }
    }
}

if (isset($_POST['submit'])) {
    $entered_code = implode('', $_POST['code']);
    $email = $_SESSION['reset_email'];

    // Use prepared statement for security
    $sql_select = "SELECT code, code_expiry FROM tbl_code WHERE cvsu_email=?";
    $stmt = $conn->prepare($sql_select);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stored_code = $row['code'];
        $code_expiry = $row['code_expiry'];

        if (time() > $code_expiry) {
            $message = "Verification code has expired.";
            $show_modal = true;
        } else if ($entered_code == $stored_code) {
            header("location: reset_password.php");
            exit();
        } else {
            $message = "Invalid verification code.";
            $show_modal = true;
        }
    } else {
        $message = "No verification code found for this email.";
        $show_modal = true;
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
    <link rel="stylesheet" href="../../css/login/verification-code.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <script src="../../javascript/verification-code.js" defer></script>
</head>
<style>
        /* Modal Background */
        .modal-background {
            display: none; 
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            display: flex;
            flex-direction: column;
            align-items: center; /* Horizontally center the button */
            justify-content: center;
            background-color: #fff;
            margin: 20% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            max-width: 400px;
            border-radius: 30px;
            text-align: center;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal-content p {
            font-size: 15px;
            color: var(--color-dark);
        }

        .close-btn {
            justify-content: center;
            text-align: center;
            align-items: center;
            background-color: #FD7238;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 3px;
        }

        .close-btn:hover {
            background-color: #FF5722;
        }
    </style>
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
        <form action="" method="post">
            <div class="text">
                <br>
                <p>Didn't receive the code? <button type="submit" id="resend-code" name="request">Re-send code</button></p>
            </div>
        </form>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content">
            <p id="modalMessage"></p>
            <button class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>

    <script>
        // Constants
const TIMER_DURATION = 3 * 60; // 3 minutes in seconds

// DOM Elements
document.addEventListener('DOMContentLoaded', function() {
    const submitButton = document.querySelector('button[name="submit"]');
    const resendButton = document.getElementById('resend-code');
    const codeInputs = document.querySelectorAll('input[type="number"]');
    
    // Initialize timer on page load
    initializeTimer();
    
    // Set up code input fields behavior
    setupCodeInputs(codeInputs);
    
    // Modal functionality
    window.closeModal = function() {
        document.getElementById('messageModal').style.display = 'none';
    };
    
    // Check for reset timer flag from PHP
    const resetTimerFlag = <?php echo $reset_timer ? 'true' : 'false'; ?>;
    if (resetTimerFlag) {
        resetTimer();
    }
});

// Timer initialization
function initializeTimer() {
    // Check if there's a stored expiry time
    let expiryTime = localStorage.getItem('verificationCodeExpiry');
    
    if (expiryTime) {
        // Calculate remaining time
        const currentTime = Math.floor(Date.now() / 1000);
        const remainingTime = parseInt(expiryTime) - currentTime;
        
        if (remainingTime > 0) {
            // If time still remains, start the countdown
            startCountdown(remainingTime);
        } else {
            // If time has expired, show expired message
            showExpiredMessage();
        }
    } else {
        // If no expiry time is stored, set a new one
        resetTimer();
    }
}

// Function to reset timer
function resetTimer() {
    // Calculate new expiry time (current time + 3 minutes)
    const currentTime = Math.floor(Date.now() / 1000);
    const expiryTime = currentTime + TIMER_DURATION;
    
    // Store expiry time in localStorage
    localStorage.setItem('verificationCodeExpiry', expiryTime);
    
    // Start countdown with full duration
    startCountdown(TIMER_DURATION);
}

// Function to start countdown
function startCountdown(seconds) {
    const submitButton = document.querySelector('button[name="submit"]');
    const codeInputs = document.querySelectorAll('input[type="number"]');
    
    // Enable inputs and submit button
    submitButton.disabled = false;
    codeInputs.forEach(input => {
        input.disabled = false;
    });
    
    // Clear any existing interval
    if (window.countdownInterval) {
        clearInterval(window.countdownInterval);
    }
    
    window.countdownInterval = setInterval(() => {
        seconds--;
        
        if (seconds <= 0) {
            // Stop the interval
            clearInterval(window.countdownInterval);
            
            // Show expired message and disable inputs
            showExpiredMessage();
        }
    }, 1000);
}

// Function to show expired message
function showExpiredMessage() {
    const submitButton = document.querySelector('button[name="submit"]');
    
    // Disable submit button
    submitButton.disabled = true;
    
    // Disable all code inputs except the first one (which is managed by the input handler)
    const codeInputs = document.querySelectorAll('input[type="number"]');
    codeInputs.forEach(input => {
        input.disabled = true;
    });
}

// Function to set up code input fields behavior
function setupCodeInputs(codeInputs) {
    codeInputs.forEach((input, index) => {
        // Add event listener for input
        input.addEventListener('input', function(e) {
            // Allow only single digit
            if (this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
            
            // If a digit was entered and there's a next input field
            if (this.value.length === 1 && index < codeInputs.length - 1) {
                codeInputs[index + 1].disabled = false;
                codeInputs[index + 1].focus();
            }
        });
        
        // Add event listener for backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
                codeInputs[index - 1].focus();
            }
        });
    });
    
    // First input should be enabled by default
    if (codeInputs.length > 0) {
        codeInputs[0].disabled = false;
    }
}

// Handle showing the modal if PHP signals it
document.addEventListener('DOMContentLoaded', function() {
    const showModal = <?php echo $show_modal ? 'true' : 'false'; ?>;
    const message = "<?php echo $message; ?>";
    
    if (showModal) {
        document.getElementById('modalMessage').textContent = message;
        document.getElementById('messageModal').style.display = 'block';
    }
});
    </script>

</body>

</html>