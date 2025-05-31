<?php
include ("../config.php");
session_start();

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Admin') {
    header("Location: ../login/login.php");
    exit();
}

// Retrieve user session variables
$college_code = $_SESSION['college_code'];
$user_type = $_SESSION['user_type'];

// Pending approvals count
$sql = "SELECT COUNT(*) AS pending_count FROM tbl_prof_acc WHERE status = 'pending' AND college_code = ? AND semester = ? AND ay_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $college_code, $semester, $ay_code);
$stmt->execute();
$result = $stmt->get_result();

$pending_count = ($result && $row = $result->fetch_assoc()) ? $row['pending_count'] : 0;
$stmt->close();

$message = "";

if (isset($_POST['save'])) {
    // Convert department code to uppercase before processing
    $dept_code = strtoupper($_POST['dept-code']);
    $dept_name = $_POST['dept-name'];
    
    // Get the single program unit input and trim any whitespace
    $prog_unit = isset($_POST['prog_unit']) ? trim($_POST['prog_unit']) : '';
    $prog_unit = strtoupper($prog_unit); // Convert to uppercase for consistency

    // Get additional program units from dynamically added inputs
    $additional_program_units = isset($_POST['prog-unit']) ? array_filter((array)$_POST['prog-unit'], fn($value) => !empty(trim($value))) : [];
    $additional_program_units = array_map('strtoupper', $additional_program_units); // Convert to uppercase for consistency

    // Combine all program units, excluding empty ones
    $program_units = array_filter(array_merge($additional_program_units, [$prog_unit]));

    // Check for duplicates within program units
    if (count($program_units) !== count(array_unique($program_units))) {
        $message = "Error: Duplicate entries found in the program units.";
    } elseif (empty($program_units)) {
        // Ensure there is at least one program unit
        $message = "Error: Program Unit is required.";
    } else {
        // Convert the program_units array to a comma-separated string
        $program_units_string = implode(', ', $program_units);

        // Check if department already exists
        $check_sql = "SELECT * FROM tbl_department WHERE dept_code='$dept_code' AND college_code='$college_code'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            $message = "Department Information Already Exists";
        } else {
            // Insert the new department with the comma-separated program units
            $sql = "INSERT INTO tbl_department (college_code, dept_code, dept_name, program_units) 
                    VALUES ('$college_code', '$dept_code', '$dept_name', '$program_units_string')";

            if ($conn->query($sql) === TRUE) {
                $message = "Department has been added successfully.";
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}

if (isset($_POST['update'])) {
    $original_dept_code = $_POST['original_dept_code'];
    $dept_code = strtoupper($_POST['dept-code']);
    $dept_name = $_POST['dept-name'];

    // Get the single program unit input and trim any whitespace
    $prog_unit = isset($_POST['prog_unit']) ? trim($_POST['prog_unit']) : '';
    $prog_unit = strtoupper($prog_unit); // Convert to uppercase for consistency

    // Get additional program units from dynamically added inputs
    $additional_program_units = isset($_POST['prog-unit']) ? array_filter((array)$_POST['prog-unit'], fn($value) => !empty(trim($value))) : [];
    $additional_program_units = array_map('strtoupper', $additional_program_units); // Convert to uppercase for consistency

    // Combine all program units, excluding empty ones
    $program_units = array_filter(array_merge($additional_program_units, [$prog_unit]));

    // Check for duplicates within program units
    if (count($program_units) !== count(array_unique($program_units))) {
        $message = "Error: Duplicate entries found in the program units.";
    } else {
        // Convert the program_units array to a comma-separated string
        $program_units_string = implode(', ', $program_units);

        // Temporarily disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Update department name in the main department table
        $update_sql = "UPDATE tbl_department SET dept_code=?, dept_name=?, program_units=? WHERE dept_code=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssss", $dept_code, $dept_name, $program_units_string, $original_dept_code);

        if ($update_stmt->execute()) {
            // $message = "Department has been updated successfully.";

            // Update the dept_code in all related tables
            $related_tables = [
                'tbl_assigned_course',
                'tbl_course',
                'tbl_pcontact_counter',
                'tbl_pcontact_schedstatus',
                'tbl_prof',
                'tbl_prof_acc',
                'tbl_prof_schedstatus',
                'tbl_program',
                'tbl_psched',
                'tbl_psched_counter',
                'tbl_room',
                'tbl_room_schedstatus',
                'tbl_rsched',
                'tbl_schedstatus',
                'tbl_secschedlist',
                'tbl_section',
                'tbl_stud_acc'
            ];

            foreach ($related_tables as $table) {
                $update_related_sql = "UPDATE $table SET dept_code = ? WHERE dept_code = ?";
                $stmt_related = $conn->prepare($update_related_sql);
                $stmt_related->bind_param("ss", $dept_code, $original_dept_code);

                if (!$stmt_related->execute()) {
                    echo "<script>alert('Error updating dept_code in $table: " . $stmt_related->error . "');</script>";
                    break; // Stop the loop if any query fails
                }

                $stmt_related->close();
            }

            // Process tables that need AY-specific handling
            $ay_sql = "SELECT DISTINCT ay_code FROM tbl_ay";
            $ay_stmt = $conn->prepare($ay_sql);
            $ay_stmt->execute();
            $ay_result = $ay_stmt->get_result();

            if ($ay_result && $ay_result->num_rows > 0) {
                while ($ay_row = $ay_result->fetch_assoc()) {
                    $ay_code = $ay_row['ay_code'];

                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$original_dept_code}_{$ay_code}");
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$original_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$original_dept_code}_{$ay_code}");
                    $sanitized_prof_sched_contact = "tbl_pcontact_sched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$original_dept_code}_{$ay_code}");

                    $tables = [
                        $sanitized_section_sched_code => 'section_sched_code',
                        $sanitized_room_sched_code => 'room_sched_code',
                        $sanitized_prof_sched_code => 'prof_sched_code',
                        $sanitized_prof_sched_contact => 'prof_sched_contact',
                    ];

                    foreach ($tables as $sanitized_table => $column_name) {
                        $check_table_sql = "SHOW TABLES LIKE '$sanitized_table'";
                        $check_table_stmt = $conn->prepare($check_table_sql);
                        $check_table_stmt->execute();
                        $check_table_result = $check_table_stmt->get_result();

                        if ($check_table_result->num_rows > 0) {
                            $update_sql = "UPDATE $sanitized_table SET dept_code=? WHERE dept_code=?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("ss", $dept_code, $original_dept_code);

                            if (!$update_stmt->execute()) {
                                echo "Error updating $column_name for table $sanitized_table: " . $update_stmt->error;
                            } else {
                                // $message = "Successfully updated $column_name for table $sanitized_table";
                            }
                            $update_stmt->close();

                            $new_table_name = str_replace($original_dept_code, $dept_code, $sanitized_table);
                            $rename_table_sql = "ALTER TABLE $sanitized_table RENAME TO $new_table_name";
                            if ($conn->query($rename_table_sql) === TRUE) {
                                // $message = "Successfully renamed table $sanitized_table to $new_table_name";
                                // $message = "Selected Department has been updated successfully.";
                            }
                        }

                        $check_table_stmt->close();
                    }
                }
                $ay_stmt->close();
            }

            // Additional updates for shared schedules
            $update_shared_sql = "UPDATE tbl_shared_sched SET receiver_dept_code=? WHERE receiver_dept_code=?";
            $update_shared_stmt = $conn->prepare($update_shared_sql);
            $update_shared_stmt->bind_param("ss", $dept_code, $original_dept_code);
            if (!$update_shared_stmt->execute()) {
                $message = "Error updating receiver_dept_code in tbl_shared_sched: " . $update_shared_stmt->error;
            }
            $update_shared_stmt->close();

            $update_shared_sql = "UPDATE tbl_shared_sched SET sender_dept_code=? WHERE sender_dept_code=?";
            $update_shared_stmt = $conn->prepare($update_shared_sql);
            $update_shared_stmt->bind_param("ss", $dept_code, $original_dept_code);
            if (!$update_shared_stmt->execute()) {
                $message = "Error updating sender_dept_code in tbl_shared_sched: " . $update_shared_stmt->error;
            }
            $update_shared_stmt->close();

            // echo "<script>alert('Record updated successfully in all related tables');</script>";
        } else {
            $message = "Error updating record in tbl_department: " . $update_stmt->error;
        }
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    }
    
}
    
if (isset($_POST['delete'])) {
    $dept_code = strtoupper($_POST['dept-code']);

    $sql = "DELETE FROM tbl_department WHERE dept_code='$dept_code'";
    if ($conn->query($sql) === TRUE) {
        // $message = "Selected Deparment has been deleted";
    } else {
        echo "<script>alert('Error: " . $sql . "<br>" . $conn->error . "');</script>";
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
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
    echo '<script>window.location.href="../login/login.php";</script>'; // Display logout alert and redirect
    exit(); // Stop executing the script
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
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
                    <h2 class="logo-name">SchedSys</h2>
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
                    <a href="department_input.php"><button onclick="closeModal()">Cancel</button></a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main">
            <div class="content">
                <div class="department_input">
                <h5 class="title" style="text-align: center">Create Department</h5><br>
                    <form action="" method="POST" id="input-form">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="dept-code" placeholder="Enter Department Code"
                                name="dept-code" autocomplete="off" oninput="this.value = this.value.toUpperCase(); validateLetters(this);"
                                required>
                                <input type="hidden" class="form-control" id="original_dept_code" placeholder="Enter Department Code"
                                name="original_dept_code" autocomplete="off" oninput="this.value = this.value.toUpperCase(); validateLetters(this);"
                                readonly>
                        </div>
                        <div class="mb-3"><br>
                            <input type="text" class="form-control" id="dept-name" placeholder="Enter Department Name"
                                name="dept-name" autocomplete="off" oninput="validateLetters(this)" required>
                        </div><br>

                        
                        <div id="prog-unit-container"></div>    
                        
                        <div class="mb-3 input-container" id="prog-unit-wrapper">
                            <input type="text" class="form-control" id="prog_unit" placeholder="Enter Program Unit"
                                name="prog_unit" autocomplete="off" oninput="this.value = this.value.toUpperCase(); validateLetters(this);">
                            <button type="button" class="btn-plus" id="addProgUnitBtn">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div><br>

                        
                        <br><br>

                        <!-- Button -->
                        <div class="btn">
                            <button type="submit" name="save" value="add"  id="addButton" class="btn-add btn-success" onclick="openModal(event, 'addModal')">Add</button>
                            <button type="submit" name="update" id="updateButton" value="update" class="btn-update btn-primary" onclick="openModal(event, 'updateModal')" disabled>Update</button>
                            <button type="submit" name="delete" id="deleteButton" value="delete" class="btn-delete btn-danger" onclick="openModal(event, 'deleteModal')" disabled>Delete</button>
                        </div>

                        <!-- Add Modal -->
                        <div id="addModal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <h2>Confirm Add</h2>
                                <p>Are you sure you want to add this entry?</p><br>
                                <div class="modal-buttons">
                                    <button type="submit" name="save" class="btn-add" onclick="finalizeAction('addModal')">Yes, Add</button>
                                    <a href="department_input.php"><button class="closed" onclick="closeModal('addModal')">Cancel</button></a>
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
                                    <a href="department_input.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
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
                                    <a href="department_input.php"><button class="closed" onclick="closeModal()">Cancel</button></a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Department Table -->
                <div class="content-table">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style='width: 250px;'>Department Code</th>
                                <th>Department Name</th>
                                <th>Program Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $sql = "SELECT * FROM tbl_department WHERE college_code = '$college_code'";
                                $result = $conn->query($sql);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr onclick=\"fillForm('" . $row["dept_code"] . "', '" . $row["dept_name"] . "', '" . $row["program_units"] . "')\">
                                                <td style='width: 250px;'>" . $row["dept_code"] . "</td>
                                                <td>" . $row["dept_name"] . "</td>
                                                <td>" . $row["program_units"] . "</td>
                                            </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2'>No department records found</td></tr>";
                                }
                                $conn->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- User Profile -->
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
    </div>
    
    <!-- Message Modal -->
    <div id="messageModal" class="modal-background">
        <div class="modal-content">
            <p id="modalMessage">Your message here</p>
            <button class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>

    <script>
        function validateLetters(input) {
            input.value = input.value.replace(/[^A-Za-z ]/g, '');  // Allow spaces as well
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

        // Add event listener to the "Add Program Unit" button
        document.getElementById('addProgUnitBtn').addEventListener('click', function () {
            const progUnitContainer = document.getElementById('prog-unit-container');

            // Ensure the container is visible
            progUnitContainer.style.display = 'block';

            // Create a wrapper to group input and button
            const wrapper = document.createElement('div');
            wrapper.classList.add('prog-unit-wrapper');

            // Create a new input element
            const newInput = document.createElement('input');
            newInput.type = 'text';
            newInput.classList.add('form-control');
            newInput.placeholder = 'Enter Program Unit';
            newInput.name = 'prog-unit[]';
            newInput.autocomplete = 'off';
            newInput.oninput = function () {
                this.value = this.value.toUpperCase();
            };
            
            // Create remove button
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.classList.add('btn-plus');
            removeButton.id = 'removeProgUnitBtn';

            // Add icon to the button
            const buttonIcon = document.createElement('i');
            buttonIcon.classList.add('fa-solid', 'fa-minus');
            removeButton.appendChild(buttonIcon);

            // Add event listener to the remove button to remove the wrapper
            removeButton.addEventListener('click', function () {
                progUnitContainer.removeChild(wrapper); // Remove the wrapper, which includes input and button
            });

            // Append input and remove button to the wrapper
            wrapper.appendChild(newInput);
            wrapper.appendChild(removeButton);

            // Append the wrapper to the container
            progUnitContainer.appendChild(wrapper);
            
            progUnitContainer.appendChild(document.createElement('br'));
        });

        // Function to fill the form with data from the table
        function fillForm(deptCode, deptName, progUnit) {
            document.getElementById('dept-code').value = deptCode;
            document.getElementById('dept-name').value = deptName;
            document.getElementById('original_dept_code').value = deptCode;

            const progUnitContainer = document.getElementById('prog-unit-container');
            progUnitContainer.style.display = 'block';

            const progUnits = progUnit.split(',');

            // Clear the container before populating it
            progUnitContainer.innerHTML = '';

            progUnits.forEach((unit, index) => {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = `prog-unit[]`;
                input.id = `prog-unit-${index + 1}`;
                input.value = unit.trim();
                input.placeholder = `Program Unit ${index + 1}`;
                input.classList.add('form-control');

                input.addEventListener('input', (e) => {
                    let newValue = e.target.value.toUpperCase().replace(/[^A-Z]/g, '');
                    e.target.value = newValue;
                });

                progUnitContainer.appendChild(input);
                progUnitContainer.appendChild(document.createElement('br'));
                progUnitContainer.appendChild(document.createElement('br'));
            });

            document.getElementById('updateButton').disabled = false;
            document.getElementById('deleteButton').disabled = false;
            document.getElementById('addButton').disabled = true;

            if (selectedRow) {
                selectedRow.classList.remove('active-row');
            }
            selectedRow = event.currentTarget;
            selectedRow.classList.add('active-row');
        }

        // Clear form and reset
        function clearForm() {
            document.getElementById('input-form').reset();
            document.getElementById('original_dept_code').value = "";
            document.getElementById('updateButton').disabled = true;
            document.getElementById('deleteButton').disabled = true;
            document.getElementById('addButton').disabled = false;

            const addProgUnitBtn = document.getElementById('addProgUnitBtn');
            if (addProgUnitBtn) {
                addProgUnitBtn.disabled = false;
                addProgUnitBtn.style.display = "block";
            }

            // Clear and hide the program unit container
            const progUnitContainer = document.getElementById('prog-unit-container');
            progUnitContainer.innerHTML = '';
            progUnitContainer.style.display = 'none';

            if (selectedRow) {
                selectedRow.classList.remove('active-row');
                selectedRow = null;
            }
        }

        // Event listener to clear form if clicking outside the form or table
        document.addEventListener('click', (event) => {
            const formContainer = document.querySelector('.department_input');
            const table = document.querySelector('.table');
            const addProgUnitBtn = document.getElementById('addProgUnitBtn');
            const progUnitContainer = document.getElementById('prog-unit-container');

            if (
                formContainer.contains(event.target) ||
                table.contains(event.target) ||
                addProgUnitBtn.contains(event.target) ||
                progUnitContainer.contains(event.target)
            ) {
                return;
            }

            clearForm();
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

    </script>
</body>
</html>