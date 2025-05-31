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

// $department_code = $professor_code = $last_name = $first_name = $mi = $email = $professor_type = $user_type = "";

$message = "";

// Handle form submissions
if (isset($_POST['save'])) {
    $department_code = mysqli_real_escape_string($conn, $_POST['department-code']);
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

    $check_sql = "SELECT * FROM tbl_others_acc WHERE dept_code = '$department_code' AND LOWER(cvsu_email) = LOWER(?)";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $message = "Student already exists";
    } else {
        $insert_sql = "INSERT INTO tbl_others_acc (college_code, dept_code, last_name, first_name, middle_initial, suffix, cvsu_email, password, acc_status) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?,'1')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssssss", $department_code, $department_code, $last_name, $first_name, $mi, $suffix, $email, $hashed_password);

        if ($insert_stmt->execute()) {
            $message = "User has been added successfully";
        } else {
            echo "Error: " . $insert_sql . "<br>" . $conn->error;
        }
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
                            <strong>CvSU Email: </strong>' .$email . '<br>
                            <strong>Password: </strong>' . $password .'<br><br>
                        Please make sure to keep this information safe and secure. If you have any questions or need further assistance, feel free to contact us.<br><br>
                        Thank You.<br>';

        $mail->send();
        echo '<script>
            window.location.href="other_college.php";
            </script>';
        exit();
    } catch (Exception $e) {
        // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

$result = $conn->query("SELECT * FROM tbl_others_acc");

if ($result === FALSE) {
    echo "Error fetching data: " . $conn->error;
}

// Fetch Colleges
$collegeCodes = [];
$query = "SELECT college_code FROM tbl_college";
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
    <title>Document</title>
    <!-- <link rel="stylesheet" href="/SchedSys/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <script src="/SchedSys/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
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
                <a href="/SchedSys3/php/new-admin/user_list.php">
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
                <a onclick="openModal(event)">
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
                    <button onclick="confirmLogout()">Logout</button>
                    <a href="other_college.php"><button onclick="closeModal()">Cancel</button></a>
                </div>
            </div>
        </div>

        <div class="nav">
            <div class="profile">
                <div class="info">
                    <p><b>Admin</b></p>
                    <small class="text-muted"><?php echo htmlspecialchars($college_code); ?></small>
                </div>
                <div class="profile-photo">
                    <img src="../../images/user_profile.png">
                </div>
            </div>
        </div>

        <div class="main">
            <div class="content">
                <div class="user_account">
                    <!-- <h5 class="title">Create Professor Account</h5> -->
                    <form id="professorForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <select class="form-control" id="department_code" name="department_code"
                            onchange="loadProgramUnits(this)" required>
                            <option value="" disabled selected>Select College</option>
                            <?php foreach ($collegeCodes as $college): ?>
                                <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <br><br>
                        <div class="form-group">
                            <input list="professor-codes" class="form-control" id="prof_id" name="prof_id"
                                style="display:none;" required>
                        </div>
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
                                oninput="appendDomain()" style="padding-right: 110px;">
                            <div class="email-domain">@cvsu.edu.ph</div>
                        </div><br>
                        <div class="form-group">
                            <select class="form-control" id="acc_status" name="acc_status"
                                onchange="changeSelectColor(this)">
                                <option value="" disabled selected>Status</option>
                                <option value="1">Active</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div><br><br>
                        <!-- Buttons -->
                        <div class="btn">
                            <button id="addButton" type="submit" name="submit" class="btn btn-success">Add</button>
                            <button id="updateButton" type="submit" name="update" class="btn btn-primary hidden"
                                data-toggle="modal" data-target="#updateModal">Update</button>
                            <button id="deleteButton" type="submit" name="delete" class="btn btn-danger hidden"
                                data-toggle="modal" data-target="#deleteModal">Delete</button>
                        </div>
                    </form>
                </div>


                <div class="content-table">

                    <div class="filtering-container">
                        <div class="form-group">
                            <select class="filtering" id="department" name="department">
                                <option value="" disabled selected>Select College</option>
                                <option value="all">All</option>
                                <?php foreach ($collegeCodes as $college): ?>
                                    <option value="<?= htmlspecialchars($college) ?>"><?= htmlspecialchars($college) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <select class="filtering" id="user_type" name="user_type">
                                <option value="" disabled selected>Select Status</option>
                                <option value="All">All</option>
                                <option value="Active">Active</option>
                                <option value="Disabled">Disabled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <input type="text" class="filtering" id="search_user" name="search_user"
                                placeholder="Search Professor" autocomplete="off">
                            <button type="submit" class="btn-add btn-search">Search</button>
                        </div>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style='width: 150px'>College</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {

                                    // Determine account status based on "acc_status"
                                    $acc_status = ($row["acc_status"] === "1") ? 'Active' : 'Disabled';

                                    $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];

                                    echo "<tr class='account-row'>
                                            <td style='width: 150px'>" . htmlspecialchars($row["college_code"]) . "</td>
                                            <td style='display:none'>" . htmlspecialchars($row["id"]) . "</td>
                                            <td>" . htmlspecialchars($full_name) . "</td>
                                            <td>" . htmlspecialchars($row["cvsu_email"]) . "</td>
                                            <td>" . htmlspecialchars($acc_status) . "</td>
                                        </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9'>No records found</td></tr>";
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
        function appendDomain() {
            const emailInput = document.getElementById("email");
            const value = emailInput.value;

            // Prevent user from typing the domain manually
            if (value.includes("@cvsu.edu.ph")) {
                emailInput.value = value.split("@")[0];
            }
        }

        function validateLetters(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');
        }

        function validateMiddleInitial(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '').substring(0, 1);
        }

        function validateMiddleSuffix(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');
        }

        function changeSelectColor(selectElement) {
            if (selectElement.value) {
                selectElement.style.color = "black";
            } else {
                selectElement.style.color = "gray";
            }
        }
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
        window.onclick = function (event) {
            const modal = document.getElementById("logoutModal");
            if (event.target === modal) {
                closeModal();
            }
        };

        // JavaScript to manage row selection and form population
        const table = document.querySelector('.table tbody');
        const addButton = document.getElementById('addButton');
        const updateButton = document.getElementById('updateButton');
        const deleteButton = document.getElementById('deleteButton');
        const formContainer = document.getElementById('professorForm');
        let selectedRow = null; // Track the currently active row

        // Disable update and delete buttons initially
        updateButton.disabled = true;
        deleteButton.disabled = true;

        // Function to clear the form
        function clearForm() {
            formContainer.reset();
            updateButton.disabled = true;
            deleteButton.disabled = true;
            addButton.disabled = false; // Enable Add button when the form is cleared

            // Remove active row styling
            if (selectedRow) {
                selectedRow.classList.remove('active-row');
            }
            selectedRow = null;
        }

        // Event listener: Populate input fields with row data when clicked
        table.addEventListener('click', (event) => {
            const row = event.target.closest('tr');
            if (!row) return;

            // Populate input fields
            document.getElementById('department_code').value = row.cells[0].innerText;
            document.getElementById('prof_id').value = row.cells[1].innerText;
            document.getElementById('professor-code').value = row.cells[2].innerText;
            document.getElementById('appointment').value = row.cells[3].innerText;
            document.getElementById('academic_rank').value = row.cells[4].innerText;
            document.getElementById('prof_unit').value = row.cells[5].innerText;

            const nameParts = row.cells[6].innerText.split(" ");
            document.getElementById('last-name').value = nameParts[2] || '';  // Handles missing last name
            document.getElementById('first-name').value = nameParts[0] || '';  // Handles missing first name
            document.getElementById('mi').value = nameParts[1] || '';  // Handles missing middle initial

            // Check if the suffix exists; if not, set to a space
            document.getElementById('suffix').value = nameParts[3] ? nameParts[3] : '';

            // Get the email value and remove "@cvsu.edu.ph"
            let emailValue = row.cells[7].innerText;
            document.getElementById('email').value = emailValue.replace('@cvsu.edu.ph', '');

            document.getElementById('user-type').value = row.cells[8].innerText;


            // Set selected value for acc_status (assuming acc_status is a select element)
            let accStatusSelect = document.getElementById('acc_status');
            let accStatusValue = row.cells[9].innerText;

            // Check if accStatusValue is "Inactive" or "0" and set the selection accordingly
            if (accStatusValue === "Disabled" || accStatusValue === "0") {
                accStatusSelect.value = "0"; // Set to "Inactive" if the value is 0
            } if (accStatusValue === "Active" || accStatusValue === "1") {
                accStatusSelect.value = "1";
            }

            // Set selected value for acc_status (assuming acc_status is a select element)
            let regAdviserSelect = document.getElementById('reg_adviser');
            let regAdviserValue = row.cells[10].innerText;

            // Check if accStatusValue is "Inactive" or "0" and set the selection accordingly
            if (regAdviserValue === "No" || regAdviserValue === "0") {
                regAdviserSelect.value = "0"; // Set to "Inactive" if the value is 0
            } if (regAdviserValue === "Yes" || regAdviserValue === "1") {
                regAdviserSelect.value = "1";
            }

            // Disable Add button when a row is selected
            addButton.disabled = true;

            // Enable update and delete buttons
            updateButton.disabled = false;
            deleteButton.disabled = false;

            // Highlight the selected row
            if (selectedRow) {
                selectedRow.classList.remove('active-row');
            }
            selectedRow = row;
            row.classList.add('active-row');
        });

        // Event listener to clear form if clicking outside the form container
        document.addEventListener('click', (event) => {
            if (!formContainer.contains(event.target) && !table.contains(event.target)) {
                clearForm();
            }
        });

        // Function to show the modal with a custom message
        function showModal(message) {
            document.getElementById("modalMessage").innerText = message;
            document.getElementById("messageModal").style.display = "block";
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById("messageModal").style.display = "none";
        }

        // Filtering for the table
        document.addEventListener("DOMContentLoaded", function () {
            const departmentFilter = document.getElementById("department");
            const userTypeFilter = document.getElementById("user_type");
            const searchUserInput = document.getElementById("search_user");
            const tableBody = document.querySelector("tbody");
            const tableRows = tableBody.querySelectorAll("tr");

            // Create a "No results found" row
            const noResultsRow = document.createElement("tr");
            noResultsRow.classList.add("no-results-row");
            noResultsRow.innerHTML = `<td colspan="9">No results found.</td>`;
            noResultsRow.style.display = "none";
            tableBody.appendChild(noResultsRow);

            function filterTable() {
                const selectedDept = departmentFilter.value.toLowerCase();
                const selectedUserType = userTypeFilter.value.toLowerCase();
                const searchQuery = searchUserInput.value.toLowerCase();

                let visibleRowCount = 0;

                tableRows.forEach(row => {
                    if (row === noResultsRow) return;

                    // Gather all column text content for universal search
                    const rowText = Array.from(row.querySelectorAll("td")).map(td => td.textContent.toLowerCase()).join(" ");

                    const matchesDept = selectedDept === "all" || rowText.includes(selectedDept) || selectedDept === "";
                    const matchesProfType = selectedUserType === "all" || rowText.includes(selectedUserType) || selectedUserType === "";
                    const matchesSearch = searchQuery === "" || rowText.includes(searchQuery);

                    if (matchesDept && matchesProfType && matchesSearch) {
                        row.style.display = "";
                        visibleRowCount++;
                    } else {
                        row.style.display = "none";
                    }
                });

                noResultsRow.style.display = visibleRowCount === 0 ? "" : "none";
            }

            departmentFilter.addEventListener("change", filterTable);
            userTypeFilter.addEventListener("change", filterTable);
            searchUserInput.addEventListener("input", filterTable);
        });
    </script>
</body>

</html>