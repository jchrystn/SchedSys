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
    $appointment = mysqli_real_escape_string($conn, $_POST['appointment']);

    // Check if the appointment already exists
    $checkQuery = "SELECT * FROM tbl_appointment WHERE appointment_code = '$appointment'";
    $checkResult = $conn->query($checkQuery);

    if ($checkResult->num_rows > 0) {
        $message = "Appointment code already exists.";
    } else {
        // Insert new appointment
        $insertQuery = "INSERT INTO tbl_appointment (appointment_code) VALUES ('$appointment')";
        if ($conn->query($insertQuery)) {
            $message = "Appointment added successfully.";
        } else {
            $message = "Error adding appointment: " . $conn->error;
        }
    }
}

if (isset($_POST['update'])) {
    $appointment = mysqli_real_escape_string($conn, $_POST['appointment']);
    $appointmentId = mysqli_real_escape_string($conn, $_POST['appointment_id']);

    // Update existing appointment
    $updateQuery = "UPDATE tbl_appointment SET appointment_code = '$appointment' WHERE id = '$appointmentId'";
    if ($conn->query($updateQuery)) {
        $message = "Appointment updated successfully.";
    } else {
        $message = "Error updating appointment: " . $conn->error;
    }
}

if (isset($_POST['delete'])) {
    $appointmentId = mysqli_real_escape_string($conn, $_POST['appointment_id']);

    // Delete appointment
    $deleteQuery = "DELETE FROM tbl_appointment WHERE id = '$appointmentId'";
    if ($conn->query($deleteQuery)) {
        $message = "Appointment deleted successfully.";
    } else {
        $message = "Error deleting appointment: " . $conn->error;
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
                    <a href="create_appointment.php"><button onclick="closeModal()">Cancel</button></a>
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
                    <h5 class="title" style="text-align: center">Create Academic Rank</h5><br><br>
                    <form id="appointmentForm" method="POST">
                        <input type="hidden" class="form-control" id="appointment_id" name="appointment_id">

                        <div class="form-group">
                            <!-- <label for="appointment">Appointment Code:</label> -->
                            <input type="text" class="form-control" id="appointment" name="appointment" placeholder="Enter Academic Rank" required>
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
                                    <a href="create_appointment.php"><button class="closed" onclick="closeModal('addModal')">Cancel</button></a>
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
                                    <a href="create_appointment.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
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
                                    <a href="create_appointment.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="content-table">

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Academic Rank</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $result = $conn->query("SELECT * FROM tbl_appointment");
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr class='account-row' 
                                            data-id='" . htmlspecialchars($row["id"]) . "' 
                                            data-code='" . htmlspecialchars($row["appointment_code"]) . "'>
                                            <td>" . htmlspecialchars($row["appointment_code"]) . "</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='1'>No records found</td></tr>";
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

    // Variables for managing table row selection and form population
    const table = document.querySelector('.table tbody');
    const form = document.getElementById('appointmentForm');
    const addButton = document.getElementById('addButton');
    const updateButton = document.getElementById('updateButton');
    const deleteButton = document.getElementById('deleteButton');
    let selectedRow = null;

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

    // Event listener to populate the form with row data when a row is clicked
    table.addEventListener('click', (event) => {
        const row = event.target.closest('tr');
        if (!row || row.classList.contains('no-records')) return;

        const appointmentId = row.getAttribute('data-id');
        const appointmentCode = row.getAttribute('data-code');

        document.getElementById('appointment_id').value = appointmentId;
        document.getElementById('appointment').value = appointmentCode;

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

    // Clear form when clicking outside of form or table
    document.addEventListener('click', (event) => {
        if (!form.contains(event.target) && !table.contains(event.target)) {
            clearForm();
        }
    });
    </script>

</body>
</html>