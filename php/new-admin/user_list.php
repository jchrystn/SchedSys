<?php

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

// Fetch the current page from the query string or default to page 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rows_per_page = 13; // Number of rows per page
$offset = ($page - 1) * $rows_per_page; // Calculate the starting row for the current page

// Query for student accounts with pagination
$studentQuery = "SELECT dept_code, last_name, first_name, middle_initial, 'student' AS user_type, cvsu_email, acc_status 
                 FROM tbl_stud_acc 
                 ORDER BY id DESC 
                 LIMIT $offset, $rows_per_page";
$studentResult = $conn->query($studentQuery);

// Query for professor accounts with pagination
$professorQuery = "SELECT dept_code, last_name, first_name, middle_initial, 'professor' AS user_type, cvsu_email, acc_status 
                   FROM tbl_prof_acc 
                   ORDER BY id DESC 
                   LIMIT $offset, $rows_per_page";
$professorResult = $conn->query($professorQuery);

// Calculate the total number of rows for pagination
$totalQuery = "SELECT COUNT(*) AS total FROM (
                  SELECT id FROM tbl_stud_acc 
                  UNION ALL 
                  SELECT id FROM tbl_prof_acc
               ) AS combined";
$totalResult = $conn->query($totalQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$total_pages = ceil($totalRows / $rows_per_page);

// Query to fetch students, filtering by college_code
$studentQuery = "
    SELECT 'Student' AS user_type, dept_code, cvsu_email, last_name, first_name, middle_initial, student_no, acc_status
    FROM tbl_stud_acc 
    WHERE college_code = ? AND status = 'approve'";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$studentResult = $stmt->get_result();

// Query to fetch professors (assuming they have user_type in their table)
$professorQuery = "
    SELECT *
    FROM tbl_prof_acc 
    WHERE college_code = ? AND status = 'approve' AND semester = ? AND ay_code = ?";
$stmt = $conn->prepare($professorQuery);
$stmt->bind_param("sss", $college_code, $semester, $ay_code);
$stmt->execute();
$professorResult = $stmt->get_result();

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    session_unset(); // Unset all session variables
    session_destroy(); // Destroy the session
    echo '<script>window.location.href="../login/login.php";</script>'; // Display logout alert and redirect
    exit(); // Stop executing the script
}

$departmentCodes = [];
$query = "SELECT dept_code FROM tbl_department WHERE college_code = '$college_code'";
$resultDept = $conn->query($query);
if ($resultDept->num_rows > 0) {
    while ($row = $resultDept->fetch_assoc()) {
        $departmentCodes[] = $row['dept_code'];
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- <link rel="stylesheet" href="/SchedSys/bootstrap-5.3.3-dist/css/bootstrap.min.css"> -->
    <script src="/SchedSys3/bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
    <link rel="stylesheet" href="user_list.css">
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
                <a href="#" class="active">
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
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out?</p><br>
                <div class="modal-buttons">
                    <button onclick="confirmLogout()">Logout</button>
                    <button onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
        
        <div class="main">
        <?php if ($studentResult->num_rows == 0 && $professorResult->num_rows == 0): ?>
            <div class="no-data">
                <i class="fa-solid fa-users-slash"></i>
                <p>You don't have any user records</p>
            </div>
        <?php else: ?>
            <div class="user-accounts">
                <div class="filtering-container">
                    <div class="form-group">
                        <select class="filtering" id="department" name="department">
                            <option value="" disabled selected>Select Department</option>
                            <option value="all">All</option>
                            <?php foreach ($departmentCodes as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <select class="filtering" id="user_type" name="user_type">
                        <option value="" disabled selected>Select User Type</option>
                            <option value="All">All</option>
                            <?php
                                // Assuming you have a database connection in $conn
                                $query = "SELECT DISTINCT user_type FROM tbl_prof_acc ORDER BY prof_type";
                                $result = $conn->query($query);

                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $user_type = htmlspecialchars($row['user_type']);
                                        // Display "Instructor" but keep value as "Professor"
                                        $display_text = ($user_type == "Professor") ? "Instructor" : $user_type;
                                        echo "<option value='$user_type'>$display_text</option>";
                                    }
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <select class="filtering" id="acc_status" name="acc_status">
                        <option value="" disabled selected>Select Account Status</option>
                            <option value="All">All</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="text" class="filtering" id="search_user" name="search_user" placeholder="Search User" autocomplete="off">
                        <button type="submit" class="btn-add btn-search">Search</button>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 150px;">Department</th>
                            <!-- <th style="width: 150px;">Id Number</th> -->
                            <th style="width: 150px;">Last Name</th>
                            <th style="width: 150px;">First Name</th>
                            <th style="width: 150px;">Middle Initial</th>
                            <th style="width: 150px;">User Type</th>
                            <th style="width: 250px;">Cvsu Email</th>
                            <th style="width: 150px;">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Display student accounts
                        if ($studentResult->num_rows > 0) {
                            while ($row = $studentResult->fetch_assoc()) {
                                $acc_status = ($row["acc_status"] == 1) ? 'Active' : 'Disabled';

                                echo "<tr>";
                                echo "<td style='width: 150px;'><p>" . $row['dept_code'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['last_name'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['first_name'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['middle_initial'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['user_type'] . "</p></td>";
                                echo "<td style='width: 250px;'><p>" . $row['cvsu_email'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . htmlspecialchars($acc_status) . "</p></td>";
                                echo "</tr>";
                            }
                        }

                        // Display professor accounts
                        if ($professorResult->num_rows > 0) {
                            while ($row = $professorResult->fetch_assoc()) {
                                $acc_status = ($row["acc_status"] == 1) ? 'Active' : 'Disabled';

                                echo "<tr>";
                                echo "<td style='width: 150px;'><p>" . $row['dept_code'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['last_name'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['first_name'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . $row['middle_initial'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . htmlspecialchars($row["user_type"] === "Professor" ? "Instructor" : $row["user_type"]) . "</p></td>";
                                echo "<td style='width: 250px;'><p>" . $row['cvsu_email'] . "</p></td>";
                                echo "<td style='width: 150px;'><p>" . htmlspecialchars($acc_status) . "</p></td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <!-- <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" <?= $i == $page ? 'class="active"' : '' ?>><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>">Next</a>
                    <?php endif; ?>
                </div> -->

            </div>
        <?php endif; ?>
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

        // filtering
        document.addEventListener("DOMContentLoaded", function () {
            const departmentFilter = document.getElementById("department");
            const userTypeFilter = document.getElementById("user_type");
            const accStatusFilter = document.getElementById("acc_status");
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
                const selectedAccStatus = accStatusFilter.value.toLowerCase();
                const searchQuery = searchUserInput.value.toLowerCase();

                let visibleRowCount = 0;

                tableRows.forEach(row => {
                    if (row === noResultsRow) return;

                    // Gather all column text content for universal search
                    const rowText = Array.from(row.querySelectorAll("td")).map(td => td.textContent.toLowerCase()).join(" ");

                    const matchesDept = selectedDept === "all" || rowText.includes(selectedDept) || selectedDept === "";
                    const matchesProfType = selectedUserType === "all" || rowText.includes(selectedUserType) || selectedUserType === "";
                    const matchesAccStatus = selectedAccStatus === "all" || rowText.includes(selectedAccStatus) || selectedAccStatus === "";
                    const matchesSearch = searchQuery === "" || rowText.includes(searchQuery);

                    if (matchesDept && matchesProfType && matchesAccStatus && matchesSearch) {
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
            accStatusFilter.addEventListener("change", filterTable);
            searchUserInput.addEventListener("input", filterTable);
        });
    </script>
</body>
</html>