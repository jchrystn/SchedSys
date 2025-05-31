<?php

include("../../php/config.php");

require_once '../../PHPMailer/src/PHPMailer.php';
require_once '../../PHPMailer/src/SMTP.php';
require_once '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}


$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ay_code = $row['ay_code'];
            $ay_name = $row['ay_name'];
            $semester = $row['semester'];
        } 

// Pending approvals count
$sql = "SELECT COUNT(*) AS pending_count FROM tbl_prof_acc WHERE status = 'pending' AND college_code = ? AND semester = ? AND ay_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $college_code, $semester, $ay_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $row = $result->fetch_assoc();
    $pending_count = $row['pending_count'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if there are selected professors to approve
    $modalMessage = '';
    if (!empty($_POST['prof_select'])) {
        foreach ($_POST['prof_select'] as $profId) {
            // Fetch professor info
            $fetch_info_query = "SELECT * FROM tbl_prof_acc WHERE id = ?";
            $fetchStmt = $conn->prepare($fetch_info_query);
            $fetchStmt->bind_param("i", $profId);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();

            $status = "approve";
            // Approve the professor in tbl_prof_acc
            $profSql = "UPDATE tbl_prof_acc SET status = ?, acc_status = '1' WHERE id = ?";
            $profStmt = $conn->prepare($profSql);
            $profStmt->bind_param("si", $status, $profId);
            $profStmt->execute();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $last_name = $row['last_name'];
                $first_name = $row['first_name'];
                $middle_initial = $row['middle_initial'];
                $suffix = $row['suffix'];
                $dept_code = $row['dept_code'];
                $prof_unit = $row['prof_unit'];
                $email = $row['cvsu_email'];
            }
            $fetchStmt->close();

            // Generate a random password
            if (!function_exists('generateRandomPassword')) {
                function generateRandomPassword($length = 6) {
                    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $randomPassword = '';
                    for ($i = 0; $i < $length; $i++) {
                        $randomPassword .= $characters[rand(0, strlen($characters) - 1)];
                    }
                    return $randomPassword;
                }
            }
            
            $password = generateRandomPassword();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            if ($profId) {
                // Prepare the SQL query to update the password
                $update_query = "UPDATE tbl_prof_acc SET password = ? WHERE id = ?";
                $updateStmt = $conn->prepare($update_query);
                $updateStmt->bind_param("si", $hashed_password, $profId);
            
                if ($updateStmt->execute()) {
                    // echo "Password updated successfully. The new password is: $password"; // Show or log the plain text password if necessary
                } else {
                    echo "Error updating password: " . $conn->error;
                }
                $updateStmt->close();
            } else {
                $modalMessage = "Error: No Instructor ID provided.";
            }
        
            // Determine the current count for professors in the same unit and department
            $count_query = "SELECT COUNT(*) AS count FROM tbl_prof WHERE prof_unit = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
            $countStmt = $conn->prepare($count_query);
            $countStmt->bind_param("ssss", $prof_unit, $dept_code, $semester, $ay_code);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $current_count = 0;

            if ($row_count = $countResult->fetch_assoc()) {
                $current_count = $row_count['count'];
            }
            $countStmt->close();

            // Increment the count and generate the new prof_code
            $current_count++; // Increment for the new record
            $prof_code = strtoupper($prof_unit) . " " . $current_count;

            // Check if the professor already exists in tbl_prof
            $check_query = "SELECT * FROM tbl_prof WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
            $checkStmt = $conn->prepare($check_query);
            $checkStmt->bind_param("ssss", $prof_code, $dept_code, $semester, $ay_code);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows == 0) {
                // Insert the prof_code into tbl_prof_acc
                $insert_prof_acc_query = "UPDATE tbl_prof_acc SET default_code = ? WHERE id = ?";
                $insertProfAccStmt = $conn->prepare($insert_prof_acc_query);
                $insertProfAccStmt->bind_param("si", $prof_code, $profId);
                $insertProfAccStmt->execute();
                $insertProfAccStmt->close();
                
                // Insert a new professor record
                $acc_status = 1;
                $sql_insert = "INSERT INTO tbl_prof 
                    (id, dept_code, prof_unit, prof_code, acc_status, semester, ay_code) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($sql_insert);
                $insertStmt->bind_param("sssssss", $profId, $dept_code, $prof_unit, $prof_code, $acc_status, $semester, $ay_code);

                if (!$insertStmt->execute()) {
                    $modalMessage .= "Error inserting Instructor record: " . $conn->error . "<br>";
                }
                $insertStmt->close();
            } else {
                $modalMessage .= "Instructor already exists with prof_code: $prof_code";
            }

            $checkStmt->close();
            
            // // Update professor account in tbl_prof_acc
         
            // For Sending the code through email
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->SMTPDebug = SMTP::DEBUG_OFF;                         
                $mail->isSMTP();                                           
                $mail->Host       = 'smtp.gmail.com';                       
                $mail->SMTPAuth   = true;                                   
                $mail->Username   = 'nuestrojared305@gmail.com';            
                $mail->Password   = 'bfruqzcgrhgnsrgr';                     
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;        
                $mail->Port       = 587;                                    

                // Recipients
                $mail->setFrom('schedsys14@gmail.com', 'SchedSys');         
                $mail->addAddress($email);                                  

                // Content
                $mail->isHTML(true);                                       
                $mail->Subject = 'Account Details';
                $mail->Body = 'Dear ' . $last_name . ',<br><br>We are pleased to provide you with your login details for your account.<br><br>
                                <strong>CVSU Email: </strong>' . $email . '<br>
                                <strong>Password: </strong>' . $password . '<br><br>
                                Please make sure to keep this information safe and secure. If you have any questions or need further assistance, feel free to contact us.<br><br>
                                Thank You.<br>';

                $mail->send();    
            } catch (Exception $e) {
                // Handle the error
                // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
        $modalMessage = empty($modalMessage) ? "Selected Instructors have been approved successfully." : $modalMessage;
    } else {
        $modalMessage = "No Instructors selected for approval.";
    }
}

$departmentCodes = [];
$query = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
$resultDept = $conn->query($query);
if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $departmentCodes[] = $row['dept_code'];
    }
}

// Fetch the program units
$programUnit = [];
$query = "SELECT dept_code, program_units FROM tbl_department WHERE college_code = '$college_code'";
$resultDept = $conn->query($query);

if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $units = explode(',', $row['program_units']);
        $units = array_map('trim', $units);
        if (!empty($units[0])) {
            $programUnit[$row['dept_code']] = $units;
        }
    }
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset();
    session_destroy();
    echo '<script>alert("You have been logged out"); window.location.href="../login/login.php";</script>';
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="approve.css">
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
                <a href="index.php" >
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
                <a href="approve.php" class="active">
                    <i class="fa-solid fa-list-check"></i>
                    <h3>Approval</h3>
                    <span class="message-count"><?php echo $pending_count; ?></span>
                </a>
                <a href="settings.php">
                    <i class="fa-solid fa-gear"></i>
                    <h3>Settings</h3>
                </a>
                <a onclick="openModal(event)">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>

        <?php
            $profQuery = "SELECT * FROM tbl_prof_acc WHERE college_code = ? AND status = 'pending' AND semester = ? AND ay_code = ? ";
            $stmt = $conn->prepare($profQuery);
            $stmt->bind_param("sss", $college_code, $semester, $ay_code);
            $stmt->execute();
            $profResult = $stmt->get_result();
         ?>

        <div class="main">
            <div class="user-accounts">
                <!-- <h2>User Accounts</h2 > -->
                <div class="filtering-container">
                    <div class="form-group">
                        <select class="filtering" id="department" name="department" onchange="loadProgramUnits(this)">
                            <option value="" disabled selected>Search Department</option>
                            <option value="all">All</option>
                            <?php foreach ($departmentCodes as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <select class="filtering" id="prof_unit" name="prof_unit" required>
                            <option value="" disabled selected>Select Program Unit</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="text" class="filtering" id="search_user" name="search_user" placeholder="Search Instructor" autocomplete="off">
                        <button type="submit" class="btn-add btn-search">Search</button>
                    </div>
                </div>
                <form method="POST" action="approve.php">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px; padding-left: 20px"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                                <th>Department</th>
                                <th>Program Unit</th>
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>Middle Initial</th>
                                <th style="width: 70px;">Suffix</th>
                                <th style="width: 250px;">Cvsu Email</th>
                                <!-- <th style="width: 150px;">Instructor Code</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($profResult->num_rows > 0): ?>
                                <?php while ($row = $profResult->fetch_assoc()): 
                                    $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
                                    ?>
                                    <tr>
                                        <td style="width: 50px; padding-left: 20px"><input type="checkbox" name="prof_select[]" value="<?= htmlspecialchars($row['id']) ?>"></td>
                                        <td><p><?= htmlspecialchars($row['dept_code']) ?></p></td>
                                        <td><p><?= htmlspecialchars($row['prof_unit']) ?></p></td>
                                        <td><p><?= htmlspecialchars($row['last_name']) ?></p></td>
                                        <td><p><?= htmlspecialchars($row['first_name']) ?></p></td>
                                        <td><p><?= htmlspecialchars($row['middle_initial']) ?></p></td>
                                        <td style="width: 70px;"><p><?= htmlspecialchars($row['suffix']) ?></p></td>
                                        <td style="width: 250px;"><p><?= htmlspecialchars($row['cvsu_email']) ?></p></td>
                                        <!-- <td style="width: 150px;"><p><?= htmlspecialchars($row['prof_code']) ?></p></td> -->
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8">No pending accounts found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <input type="hidden" name="action" value="approve_all">
                    <button type="submit" class="btn-approve" id="approveButton">Approve</button>
                </form>
            </div>
        </div>

        <div class="nav">
            <div class="profile">
                <div class="info">
                    <p><b><?php echo htmlspecialchars($user_type); ?></b></p>
                    <small class="text-muted"><?php echo htmlspecialchars($college_code); ?></small>
                </div>
                <div class="profile-photo">
                    <img src="../../images/user_profile.png">
                </div>
            </div>
        </div>
    </div>


    <!-- Modal Structure -->
    <div id="resultModal" class="modal" style="display: <?= !empty($modalMessage) ? 'block' : 'none' ?>;">
        <div class="modal-content">
            <p><?= htmlspecialchars($modalMessage); ?></p><br>
            <div class="modal-buttons">
                <a href="approve.php"><button onclick="closeModal()" style="background-color: #FD7238; color: #fff;">Okay</button></a>
            </div>
        </div>
    </div>

     <!-- Logout Confirmation Modal -->
     <div id="logoutModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h2>Confirm Logout</h2>
            <p>Are you sure you want to log out?</p><br>
            <div class="modal-buttons">
                <button onclick="confirmLogout()">Logout</button>
                <a href="approve.php"><button onclick="closeModal()">Cancel</button></a>
            </div>
        </div>
    </div>
    <script>
        // Open the modal
        function openModal(event) {
            event.preventDefault(); // Prevent default link action
            document.getElementById("logoutModal").style.display = "flex";
        }

        // Close the modal
        function closeModal() {
            document.getElementById("logoutModal").style.display = "none";
        }

        // Confirm logout and redirect to logout link
        function confirmLogout() {
            window.location.href = "index.php?logout=1";
        }

        // Close the modal if the user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById("logoutModal");
            if (event.target === modal) {
                closeModal();
            }
        };
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll("input[name='prof_select[]']");
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        function closeModal() {
            document.getElementById("resultModal").style.display = "none";
            window.location.href = 'approve.php'; // Redirect back after closing modal
        }

        // filtering
        document.addEventListener("DOMContentLoaded", function () {
            const departmentFilter = document.getElementById("department");
            const programFilter = document.getElementById("prof_unit");
            const searchUserInput = document.getElementById("search_user");
            const tableBody = document.querySelector("tbody");
            const tableRows = tableBody.querySelectorAll("tr");

            // Create a "No results found" row
            const noResultsRow = document.createElement("tr");
            noResultsRow.classList.add("no-results-row");
            noResultsRow.innerHTML = `<td colspan="8">No results found.</td>`;
            noResultsRow.style.display = "none";
            tableBody.appendChild(noResultsRow);

            function filterTable() {
                const selectedDept = departmentFilter.value.toLowerCase();
                const selectedProg = programFilter.value.toLowerCase();
                const searchQuery = searchUserInput.value.toLowerCase();

                let visibleRowCount = 0;

                tableRows.forEach(row => {
                    if (row === noResultsRow) return;

                    // Gather all column text content for universal search
                    const rowText = Array.from(row.querySelectorAll("td")).map(td => td.textContent.toLowerCase()).join(" ");

                    const matchesDept = selectedDept === "all" || rowText.includes(selectedDept) || selectedDept === "";
                    const matchesProg = selectedProg === "all" || rowText.includes(selectedProg) || selectedProg === "";
                    const matchesSearch = searchQuery === "" || rowText.includes(searchQuery);

                    if (matchesDept && matchesProg && matchesSearch) {
                        row.style.display = "";
                        visibleRowCount++;
                    } else {
                        row.style.display = "none";
                    }
                });

                noResultsRow.style.display = visibleRowCount === 0 ? "" : "none";
            }

            departmentFilter.addEventListener("change", filterTable);
            programFilter.addEventListener("change", filterTable);
            searchUserInput.addEventListener("input", filterTable);
        });
        
        const programUnits = <?= json_encode($programUnit); ?>; // Passing PHP array to JavaScript

        function loadProgramUnits(departmentSelect) {
            const selectedDept = departmentSelect.value; // Get selected department
            const programUnitSelect = document.getElementById('prof_unit');

            // Clear existing options
            programUnitSelect.innerHTML = '<option value="" disabled selected>Select Program Unit</option>';

            // Check if the selected department has program units
            if (programUnits[selectedDept]) {
                programUnits[selectedDept].forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = unit;
                    programUnitSelect.appendChild(option);
                });
            }
        }

        const approveButton = document.getElementById('approveButton');

        approveButton.addEventListener('click', () => {
            // Add loading class to the body or button
            document.body.classList.add('loading');
            approveButton.classList.add('loading');

            // Simulate a delay for the loading process (e.g., sending a message)
            setTimeout(() => {
                // Remove the loading class after the task completes
                document.body.classList.remove('loading');
                approveButton.classList.remove('loading');
            }, 7000); // Simulates a 3-second delay
        });
</script>



</body>
</html>