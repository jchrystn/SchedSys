<?php

require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include("../../php/config.php");

session_start();

// Check if the user is logged in and has the correct role
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Assuming the user's college_code is stored in a variable
$admin_college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

// fetching the academic year and semester
$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$admin_college_code' and active = '1'";
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

$message = "";

if (isset($_POST['save'])) {
    $college_code = mysqli_real_escape_string($conn, $_POST['dept-code']);
    $college_name = mysqli_real_escape_string($conn, $_POST['dept-name']);
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

    // Generate a random 6-letter password
    function generateRandomPassword($length =6) {
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

    // Check if the college_code already exists
    $check_college_sql = "SELECT college_code FROM tbl_college WHERE college_code = ?";
    $check_college_stmt = $conn->prepare($check_college_sql);

    if ($check_college_stmt === false) {
        die("Error preparing SQL statement for duplicate check: " . $conn->error);
    }

    $check_college_stmt->bind_param("s", $college_code);
    $check_college_stmt->execute();
    $check_college_stmt->store_result();

    if ($check_college_stmt->num_rows > 0) {
        $message = "Error: College code '$college_code' already exists.";
    } else {
        // Proceed with the insertion into tbl_college
        $insert_college_sql = "INSERT INTO tbl_college (college_code, college_name) VALUES (?, ?)";
        $insert_college_stmt = $conn->prepare($insert_college_sql);

        if ($insert_college_stmt === false) {
            die("Error preparing SQL statement for tbl_college: " . $conn->error);
        }

        // Bind and execute the insertion
        $insert_college_stmt->bind_param("ss", $college_code, $college_name);

        if ($insert_college_stmt->execute()) {
            // echo "College added successfully.";
        } else {
            die("Error inserting college: " . $insert_college_stmt->error);
        }

        // Now check if the professor account exists for the department
        $check_sql = "SELECT * FROM tbl_prof_acc WHERE dept_code = ? AND LOWER(cvsu_email) = LOWER(?)";
        $check_stmt = $conn->prepare($check_sql);

        if ($check_stmt === false) {
            die("Error preparing SQL statement for Instructor account check: " . $conn->error);
        }

        // Bind and execute the check
        $check_stmt->bind_param("ss", $college_code, $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $message = "Account already exists for department $college_code.";
        } else {
            // Insert into tbl_prof_acc
            $status = 'approve';
            $acc_status = '1';
            $status_type = 'Offline';
            $user_type = 'Department Secretary';

            $insert_sql_acc = "INSERT INTO tbl_prof_acc (
                college_code, dept_code, status_type, prof_code, last_name, first_name, middle_initial, suffix, cvsu_email, password, user_type, status, acc_status, semester, ay_code
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insert_stmt_acc = $conn->prepare($insert_sql_acc);

            if ($insert_stmt_acc === false) {
                die("Error preparing SQL statement for tbl_prof_acc: " . $conn->error);
            }

            // Bind parameters for tbl_prof_acc
            $insert_stmt_acc->bind_param(
                "sssssssssssssss",
                $college_code, // college_code
                $dept_code,    // dept_code
                $status_type,  // status_type
                $prof_code,    // prof_code
                $last_name,    // last_name
                $first_name,   // first_name
                $mi,           // middle_initial
                $suffix,       // suffix
                $email,        // cvsu_email
                $hashed_password, // password
                $user_type,    // user_type
                $status,       // status
                $acc_status,   // acc_status
                $semester,     // semester
                $ay_code       // ay_code
            );

            // if ($insert_stmt_acc->execute()) {
            //     // Create the full name by combining first name, middle initial, and last name
            //     $full_name = trim("$first_name $mi $last_name $suffix");

            //     // Insert into tbl_prof
            //     $insert_sql_prof = "INSERT INTO tbl_prof (
            //                 dept_code, prof_code, full_name, acc_status, semester, ay_code
            //             ) VALUES (?, ?, ?, ?, ?, ?)";

            //     $insert_stmt_prof = $conn->prepare($insert_sql_prof);

            //     if ($insert_stmt_prof === false) {
            //         die("Error preparing SQL statement for tbl_prof: " . $conn->error);
            //     }

            //     // Bind parameters for tbl_prof
            //     $insert_stmt_prof->bind_param(
            //         "sssss",
            //         $dept_code,   // dept_code
            //         $dept_code,   // prof_code
            //         $full_name,   // full name
            //         $acc_status,  // acc_status
            //         $semester,    // semester
            //         $ay_code      // ay_code
            //     );

            //     if ($insert_stmt_prof->execute()) {
            //         $message = "User has been added successfully.";
            //     } else {
            //         $message = "Error inserting into tbl_prof: " . $insert_stmt_prof->error;
            //     }
            // } else {
            //     $message = "Error inserting into tbl_prof_acc: " . $insert_stmt_acc->error;
            // }
        }

        // Close statements
        $check_stmt->close();
        // $insert_stmt->close();
    }

    // For Sending the code through email
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
        $mail->Subject = 'Account Details';
        $mail->Body = 'Dear ' . $last_name . ',<br><br>We are pleased to provide you with your login details for your account.<br><br>
                            <strong>CVSU Email: </strong>' . $email . '<br>
                            <strong>Password: </strong>' . $password .'<br><br>
                        Please make sure to keep this information safe and secure. If you have any questions or need further assistance, feel free to contact us.<br><br>
                        Thank You.<br>';

        $mail->send();
        echo '<script>
            window.location.href="create_other_college.php";
            </script>';
        exit();
    } catch (Exception $e) {
        $message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

if (isset($_POST['update'])) {
    $old_college_code = mysqli_real_escape_string($conn, $_POST['old-dept-code']); // Original college_code
    $new_college_code = mysqli_real_escape_string($conn, $_POST['dept-code']); // Updated college_code
    $college_name = mysqli_real_escape_string($conn, $_POST['dept-name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last-name']);
    $first_name = mysqli_real_escape_string($conn, $_POST['first-name']);
    $mi = mysqli_real_escape_string($conn, $_POST['mi']);
    $suffix = mysqli_real_escape_string($conn, $_POST['suffix']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Ensure email ends with '@cvsu.edu.ph'
    if (!str_ends_with($email, '@cvsu.edu.ph')) {
        $email = explode('@', $email)[0] . '@cvsu.edu.ph';
    }

    // Temporarily disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Update tbl_college
    $update_college_sql = "UPDATE tbl_college SET college_code = ?, college_name = ? WHERE college_code = ?";
    $update_college_stmt = $conn->prepare($update_college_sql);

    if ($update_college_stmt === false) {
        die("Error preparing SQL statement for updating tbl_college: " . $conn->error);
    }

    $update_college_stmt->bind_param("sss", $new_college_code, $college_name, $old_college_code);

    if ($update_college_stmt->execute()) {
        // echo "College code and name updated successfully.<br>";
    } else {
        die("Error updating college code and name: " . $update_college_stmt->error);
    }

    // Update tbl_prof_acc
    $update_prof_acc_sql = "UPDATE tbl_prof_acc 
        SET 
            college_code = ?, 
            dept_code = ?, 
            prof_code = ?, 
            last_name = ?, 
            first_name = ?, 
            middle_initial = ?, 
            suffix = ?, 
            cvsu_email = ?
        WHERE 
            dept_code = ?
        AND semester = ?
        AND ay_code = ?";
    
    $update_prof_acc_stmt = $conn->prepare($update_prof_acc_sql);

    if ($update_prof_acc_stmt === false) {
        die("Error preparing SQL statement for updating tbl_prof_acc: " . $conn->error);
    }

    $update_prof_acc_stmt->bind_param(
        "sssssssssss",
        $new_college_code,
        $new_college_code,
        $new_college_code,
        $last_name,
        $first_name,
        $mi,
        $suffix,
        $email,
        $old_college_code,
        $semester,
        $ay_code
    );

    if ($update_prof_acc_stmt->execute()) {
        // echo "Professor account updated successfully.<br>";
    } else {
        die("Error updating Instructor account: " . $update_prof_acc_stmt->error);
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Close statements
    $update_college_stmt->close();
    $update_prof_acc_stmt->close();
}


// Fetch Colleges
$collegeCodes = [];
$query = "SELECT college_code FROM tbl_college WHERE college_code != '$admin_college_code'";
$resultCollege = $conn->query($query);

if ($resultCollege->num_rows > 0) {
    while ($row = $resultCollege->fetch_assoc()) {
        $collegeCodes[] = $row['college_code'];
    }
}

if (!empty($message)) {
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                showModal('$message');
            });
          </script>";
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset();
    session_destroy();
    echo '<script>window.location.href="../login/login.php";</script>';
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSys</title>
    <!-- <link rel="stylesheet" href="/SchedSys/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <script src="/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="create_account.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar Section -->
        <aside>
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
        </aside>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p><br>
                <div class="modal-buttons">
                    <button class="btn-logout" onclick="confirmLogout()">Logout</button>
                    <a href="create_other_college.php"><button onclick="closeModal()">Cancel</button></a>
                </div>
            </div>
        </div>
        
        <div class="nav">
            <div class="profile">
                <div class="info">
                <p><b>Admin</b></p>
                    <small class="text-muted"><?php echo htmlspecialchars($admin_college_code); ?></small>
                </div>
                <div class="profile-photo">
                <img src="../../images/user_profile.png">
                </div>
            </div>
        </div>
        
        <div class="main">
            <div class="content">
                <div class="user_account">
                    <h5 class="title" style="text-align: center">Create Other College Account</h5><br>
                    <form id="othersAccForm" method="POST">
                        <div class="form-group">
                            <input type="text" class="form-control" id="old-college-code" placeholder="College Code"
                                name="old-dept-code" autocomplete="off" oninput="this.value = this.value.toUpperCase();"
                                hidden >
                        </div>
                        <div class="form-group">
                            <input type="text" class="form-control" id="college-code" placeholder="College Code"
                                name="dept-code" autocomplete="off" oninput="this.value = this.value.toUpperCase();"
                                required>
                        </div><br>
                        <div class="form-group">
                            <input type="text" class="form-control" id="college-name" placeholder="College Name"
                                name="dept-name" autocomplete="off" oninput="this.value = this.value.toUpperCase();" required>
                        </div><br>

                        <div class="form-group">
                            <input type="text" class="form-control" id="last-name" name="last-name"
                                placeholder="Last Name" oninput="validateLetters(this)" required>
                        </div><br>
                        <div class="form-group">
                            <input type="text" class="form-control" id="first-name" name="first-name"
                                placeholder="First Name" oninput="validateLetters(this)" required>
                        </div><br>

                        <div class="form-group-row">
                            <div class="form-group">
                                <input type="text" class="form-control" id="mi" name="mi" placeholder="Middle Initial"
                                    maxlength="1" oninput="validateMiddleInitial(this);this.value = this.value.toUpperCase();" pattern="[A-Za-z]{1}" required>
                            </div>
                            <div class="form-group">
                                <input type="text" class="form-control" id="suffix" name="suffix" placeholder="Suffix"
                                    oninput="validateMiddleSuffix(this)" pattern="[A-Za-z]+">
                            </div>
                        </div><br>

                        <div class="form-group" style="position: relative;">
                            <input type="text" class="form-control" id="email" name="email" placeholder="example"
                                oninput="appendDomain()" style="padding-right: 110px;" required> 
                            <div class="email-domain">@cvsu.edu.ph</div>
                        </div><br>
                        <br>
                        <!-- Button -->
                        <div class="btn">
                            <button id="addButton" type="submit" name="save" class="btn btn-success" onclick="openModal(event, 'addModal')">Add</button>
                            <button id="updateButton" type="submit" name="update" class="btn btn-primary hidden" onclick="openModal(event, 'updateModal')">Update</button>
                            <button id="deleteButton" type="submit" name="delete" class="btn btn-danger hidden" onclick="openModal(event, 'deleteModal')">Delete</button>
                        </div>

                        <!-- Add Modal -->
                        <div id="addModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Add</h2>
                                <p>Are you sure you want to add this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="save" class="btn-add" onclick="finalizeAction('addModal')">Yes, Add</button>
                                    <a href="create_other_college.php"><button class="closed" onclick="closeModal('addModal')">Cancel</button></a>
                                </div>
                            </div>
                        </div>

                        <!-- Update Modal -->
                        <div id="updateModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Update</h2>
                                <p>Are you sure you want to update this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="update" class="btn-update" onclick="finalizeAction('updateModal')">Yes, Update</button>
                                    <a href="create_other_college.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div id="deleteModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Delete</h2>
                                <p>Are you sure you want to delete this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="delete" class="btn-delete" onclick="finalizeAction('deleteModal')">Yes, Delete</button>
                                    <a href="create_other_college.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="content-table">
                    <div class="filtering-container" style="justify-content: right; padding-right: 15px;">
                        <div class="form-group">
                            <select class="filtering" id="college" name="college">
                                <option value="" disabled selected>Select College</option>
                                <option value="all">All</option>
                                <?php foreach ($collegeCodes as $college): ?>
                                    <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- <div class="form-group">
                            <select class="filtering" id="user_type" name="user_type">
                                <option value="" disabled selected>Select Status</option>
                                <option value="All">All</option>
                                <option value="Active">Active</option>
                                <option value="Disabled">Disabled</option>
                            </select>
                        </div> -->
                        <div class="form-group">
                            <input type="text" class="filtering" id="search_user" name="search_user"
                                placeholder="Search College" autocomplete="off">
                            <button type="submit" class="btn-add btn-search">Search</button>
                        </div>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>College</th>
                                <th>College Name</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $result = $conn->query("
                                SELECT p.*, c.college_name
                                FROM tbl_prof_acc p
                                JOIN tbl_college c ON p.college_code = c.college_code
                                WHERE p.college_code != '$admin_college_code' AND p.semester = '$semester' AND p.ay_code = '$ay_code'
                            ");                          

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];

                                        echo "<tr class='account-row' 
                                            data-old-dept-code='" . htmlspecialchars($row["college_code"]) . "' 
                                            data-dept-code='" . htmlspecialchars($row["college_code"]) . "' 
                                            data-program-code='" . htmlspecialchars($row["college_name"]) . "'
                                            data-first-name='" . htmlspecialchars($row["first_name"]) . "' 
                                            data-middle-initial='" . htmlspecialchars($row["middle_initial"]) . "' 
                                            data-suffix='" . htmlspecialchars($row["suffix"]) . "' 
                                            data-last-name='" . htmlspecialchars($row["last_name"]) . "' 
                                            data-cvsu-email='" . htmlspecialchars($row["cvsu_email"]) . "'>
                                            <td style='display: none;'>" . htmlspecialchars($row["college_code"]) . "</td>
                                            <td>" . htmlspecialchars($row["college_code"]) . "</td>
                                            <td>" . htmlspecialchars($row["college_name"]) . "</td>
                                            <td>" . htmlspecialchars($full_name) . "</td>
                                            <td>" . htmlspecialchars($row["cvsu_email"]) . "</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>No records found</td></tr>";
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
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

    // Close the modal if clicked outside the modal content
    window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach((modal) => {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            });
        });

        // Close the modal when clicking the close button
        document.querySelectorAll('.closed').forEach((closeButton) => {
            closeButton.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = "none";
                }
            });
        });
    
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

    function validateLetters(input) {
        input.value = input.value.replace(/[^A-Za-z ]/g, '');  // Allow spaces as well
    }

    function validateMiddleInitial(input) {
        input.value = input.value.replace(/[^A-Za-z]/g, '').substring(0, 1);
    }

    function validateMiddleSuffix(input) {
        input.value = input.value.replace(/[^A-Za-z]/g, '');
    }
    // Variables for managing table row selection and form population
    const table = document.querySelector('.table tbody');
    const addButton = document.getElementById('addButton');
    const updateButton = document.getElementById('updateButton');
    const deleteButton = document.getElementById('deleteButton');
    const formContainer = document.getElementById('othersAccForm');
    let selectedRow = null;

    // Disable update and delete buttons initially
    updateButton.disabled = true;
    deleteButton.disabled = true;

    // Function to populate form with row data
    table.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (!row) return;

        document.getElementById('old-college-code').value = row.dataset.oldDeptCode || '';
        document.getElementById('college-code').value = row.dataset.deptCode || '';
        document.getElementById('college-name').value = row.dataset.programCode || '';
        document.getElementById('last-name').value = row.dataset.lastName || '';
        document.getElementById('first-name').value = row.dataset.firstName || '';
        document.getElementById('mi').value = row.dataset.middleInitial || '';
        document.getElementById('suffix').value = row.dataset.suffix || '';
        
        let emailValue = row.dataset.cvsuEmail || '';
        if (emailValue.endsWith('@cvsu.edu.ph')) {
            emailValue = emailValue.replace('@cvsu.edu.ph', '');
        }
        document.getElementById('email').value = emailValue;

        addButton.disabled = true;
        updateButton.disabled = false;
        deleteButton.disabled = false;

        if (selectedRow) selectedRow.classList.remove('active-row');
        selectedRow = row;
        row.classList.add('active-row');
    });

    // Function to clear form and reset buttons
    function clearForm() {
        formContainer.reset();
        addButton.disabled = false;
        updateButton.disabled = true;
        deleteButton.disabled = true;

        if (selectedRow) selectedRow.classList.remove('active-row');
        selectedRow = null;
    }

    // Clear form on outside click
    document.addEventListener('click', (event) => {
        if (!formContainer.contains(event.target) && !table.contains(event.target)) {
            clearForm();
        }
    });

        // filtering
        document.addEventListener("DOMContentLoaded", function() {
        const collegeFilter = document.getElementById("college");
        // const userTypeFilter = document.getElementById("user_type");
        const searchInput = document.getElementById("search_user");
        const tableRows = document.querySelectorAll(".account-row");
        const tableBody = document.querySelector("table tbody");

        function filterTable() {
            let college = collegeFilter.value.toLowerCase();
            // let userType = userTypeFilter.value.toLowerCase();
            let searchText = searchInput.value.toLowerCase();
            let rowsShown = 0;

            tableRows.forEach(row => {
                let deptCode = row.cells[0].textContent.toLowerCase();
                let progCode = row.cells[1].textContent.toLowerCase();
                let name = row.cells[2].textContent.toLowerCase();
                let email = row.cells[3].textContent.toLowerCase();

                let matchesCollege = college === "all" || deptCode.includes(college) || college === "";
                // let matchesUserType = userType === "all" || progCode.includes(userType) || userType === "";
                let matchesSearchText = (
                    deptCode.includes(searchText) ||
                    progCode.includes(searchText) ||
                    name.includes(searchText) ||
                    email.includes(searchText)
                );

                // if (matchesCollege && matchesUserType && matchesSearchText) {
                if (matchesCollege && matchesSearchText) {
                    row.style.display = "";
                    rowsShown++;
                } else {
                    row.style.display = "none";
                }
            });

            // Show "No records found" if no rows are displayed
            if (rowsShown === 0) {
                if (!document.getElementById("no-records-row")) {
                    let noRecordsRow = document.createElement("tr");
                    noRecordsRow.id = "no-records-row";
                    noRecordsRow.innerHTML = "<td colspan='4'>No records found</td>";
                    tableBody.appendChild(noRecordsRow);
                }
            } else {
                let noRecordsRow = document.getElementById("no-records-row");
                if (noRecordsRow) {
                    noRecordsRow.remove();
                }
            }
        }

        // Add event listeners to filters and search input
        collegeFilter.addEventListener("change", filterTable);
        // userTypeFilter.addEventListener("change", filterTable);
        searchInput.addEventListener("input", filterTable);
    });
    </script>

</body>
</html>