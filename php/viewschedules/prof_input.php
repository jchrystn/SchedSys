<?php
include("../config.php");
session_start();

// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Chairperson') {
    header("Location: ../../login/login.php");
    exit();
}

$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$dept_code = $_SESSION['dept_code'];


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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prof_select'])) {
    $selectedIds = $_POST['prof_select'];
    // Validate and sanitize IDs
    $ids = array_map('intval', $selectedIds);

    // Debug: Log selected IDs
    echo "Selected IDs: " . implode(", ", $ids) . "<br>";
    error_log("Selected IDs: " . implode(", ", $ids)); // Log to the error log for server-side debugging

    $oldProfCodes = [];

    // Step 1: Get old prof_code before updating
    foreach ($ids as $id) {
        $query = "SELECT prof_code FROM tbl_prof_acc WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $oldProfCodes[$id] = $row['prof_code'];

            // Debug: Output old prof_code
            echo "Old Prof Code for ID $id: " . $row['prof_code'] . "<br>";
        }
    }

    foreach ($ids as $id) {
        // Fetch current record details
        $query = "SELECT prof_unit, last_name, prof_type, prof_code FROM tbl_prof_acc WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $prof_unit = $row['prof_unit'];
            $last_name = ucfirst($row['last_name']); // Capitalize first letter
            $prof_type = $row['prof_type'];
            $old_prof_code = $row['prof_code']; // Fetch the old prof_code
    
            // Apply changes only if prof_type is "Job Order"
            if ($prof_type === "Job Order") {
                // Get the current maximum PT number for this unit
                $incrementQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(prof_code, 'PT', -1), '-', 1) AS UNSIGNED)) AS max_number
                                   FROM tbl_prof_acc
                                   WHERE dept_code = ? AND semester = ? AND ay_code = ? AND prof_unit = ? AND prof_code LIKE CONCAT(?, ' PT%')";
                $incrementStmt = $conn->prepare($incrementQuery);
                $incrementStmt->bind_param("sssss", $dept_code, $semester, $ay_code, $prof_unit, $prof_unit);
                $incrementStmt->execute();
                $incrementResult = $incrementStmt->get_result();
    
                $max_number = 0;
                if ($incrementResult->num_rows > 0) {
                    $incrementRow = $incrementResult->fetch_assoc();
                    $max_number = $incrementRow['max_number'] ?: 0;
                }
    
                // Increment the PT number
                $new_number = $max_number + 1;
    
                // Generate the new prof_code dynamically
                $new_prof_code = $prof_unit . " PT " . $new_number . " - " . $last_name;
    
                // Update the selected record to Part-Time
                $updateQuery = "UPDATE tbl_prof_acc SET part_time = 1, prof_code = ? WHERE id = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("sisss", $new_prof_code, $id, $dept_code, $semester, $ay_code);
                $updateStmt->execute();
    
                 // Sanitize table names
                 $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
                 $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                 $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
                 $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                 $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

                $old_prof_sched_code = $old_prof_code . '_' . $ay_code;
                $new_prof_sched_code = $new_prof_code . '_' . $ay_code;
    
                // Update related tables with the new prof_sched_code
                $tablesToUpdateWithProfSchedCode = [
                    "tbl_psched_counter",
                    "tbl_pcontact_counter",
                    "tbl_pcontact_schedstatus",
                    "tbl_psched",
                    $sanitized_prof_sched_code,
                    $sanitized_pcontact_sched_code
                ];
    
                foreach ($tablesToUpdateWithProfSchedCode as $table) {
                    $updateProfSchedCodeQuery = "UPDATE $table SET prof_code = ?, prof_sched_code = ? WHERE prof_code = ? AND prof_sched_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                    $updateProfSchedCodeStmt = $conn->prepare($updateProfSchedCodeQuery);
                    $updateProfSchedCodeStmt->bind_param("sssssss", $new_prof_code, $new_prof_sched_code, $old_prof_code, $old_prof_sched_code, $dept_code, $semester, $ay_code);
                    $updateProfSchedCodeStmt->execute();
                }                    
    
                // Update related tables without prof_sched_code (based on new prof_code)
                $tablesToUpdateWithoutProfSchedCode = [
                    "tbl_prof",
                    "tbl_assigned_course",
                    $sanitized_room_sched_code,
                    $sanitized_section_sched_code,
                    $sanitized_ccl_room_sched_code
                ];
    
                foreach ($tablesToUpdateWithoutProfSchedCode as $table) {
                    $updateProfCodeQuery = "UPDATE $table SET prof_code = ? WHERE prof_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                    $updateProfCodeStmt = $conn->prepare($updateProfCodeQuery);
                    $updateProfCodeStmt->bind_param("sssss", $new_prof_code, $old_prof_code, $dept_code, $semester, $ay_code);
                    $updateProfCodeStmt->execute();
                }
    
                // Update tbl_prof_schedstatus (no prof_sched_code, just prof_code)
                $tablesToUpdateWithoutProfCode = [
                    "tbl_prof_schedstatus"
                ];
    
                foreach ($tablesToUpdateWithoutProfCode as $table) {
                    $updateProfCodeOnlyQuery = "UPDATE $table SET prof_sched_code = ? WHERE prof_sched_code = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                    $updateProfCodeOnlyStmt = $conn->prepare($updateProfCodeOnlyQuery);
                    $updateProfCodeOnlyStmt->bind_param("sssss",  $new_prof_sched_code, $old_prof_sched_code, $dept_code, $semester, $ay_code);
                    $updateProfCodeOnlyStmt->execute();
                }
            }
        }
    }
    
    foreach ($ids as $id) {
        // Get the prof_unit of the current record
        $query = "SELECT prof_unit, dept_code, semester, ay_code, prof_code FROM tbl_prof_acc WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $prof_unit = $row['prof_unit'];
            $dept_code = $row['dept_code'];
            $semester = $row['semester'];
            $ay_code = $row['ay_code'];

            // Store the old prof_code
            $old_prof_code = $row['prof_code'];

            // Ensure the record matches the session values
            if ($dept_code === $_SESSION['dept_code'] && $semester === $_SESSION['semester'] && $ay_code === $_SESSION['ay_code']) {
                // Fetch all remaining records for the same unit with prof_type = "Job Order" and part_time = 0
                $fetchQuery = "SELECT * FROM tbl_prof_acc WHERE prof_unit = ? AND prof_type = 'Job Order' AND part_time = 0 AND dept_code = ? AND semester = ? AND ay_code = ? ORDER BY id ASC";
                $fetchStmt = $conn->prepare($fetchQuery);
                $fetchStmt->bind_param("ssss", $prof_unit, $dept_code, $semester, $ay_code);
                $fetchStmt->execute();
                $result = $fetchStmt->get_result();

                $count = 1;
                $usedCodes = []; // Track used prof_codes to avoid duplicates

                while ($row = $result->fetch_assoc()) {
                    $last_name = ucfirst($row['last_name']); // Capitalize the first letter of the last name

                    // Generate new prof_code dynamically
                    $new_code = $prof_unit . " " . $count . " - " . $last_name;

                    // Ensure the new prof_code is unique
                    while (in_array($new_code, $usedCodes)) {
                        $count++;
                        $new_code = $prof_unit . " " . $count . " - " . $last_name;
                    }

                    // Add the new prof_code to the list of used codes
                    $usedCodes[] = $new_code;

                    // Capture the old_prof_code of the current record
                    $current_old_prof_code = $row['prof_code'];

                    echo "<br>New Prof Codde: $new_code<br>";

                    // Update the prof_code in the database
                    $updateSequenceQuery = "UPDATE tbl_prof_acc SET prof_code = ? WHERE id = ? AND dept_code = ? AND semester = ? AND ay_code = ?";
                    $updateSequenceStmt = $conn->prepare($updateSequenceQuery);
                    $updateSequenceStmt->bind_param("sisss", $new_code, $row['id'], $dept_code, $semester, $ay_code);
                    $updateSequenceStmt->execute();

                    // Sanitize table names
                    $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);
                    $sanitized_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                    $sanitized_ccl_room_sched_code = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$college_code}_{$ay_code}");
                    $sanitized_prof_sched_code = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");
                    $sanitized_section_sched_code = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$ay_code}");

                    // New prof_sched_code
                    $old_prof_sched_code = $current_old_prof_code . '_' . $ay_code;
                    $new_prof_sched_code = $new_code . '_' . $ay_code;

                    // echo "<br>Old prof sched code:  $old_prof_sched_code <br>";
                    // echo "<br>New prof sched code:  $new_prof_sched_code <br>";

                    // Update related tables
                    $tablesToUpdateWithProfSchedCode = [
                        "tbl_psched_counter",
                        "tbl_pcontact_counter",
                        "tbl_pcontact_schedstatus",
                        "tbl_psched",
                        $sanitized_prof_sched_code,
                        $sanitized_pcontact_sched_code
                    ];

                    $tablesToUpdateWithoutProfSchedCode = [
                        "tbl_prof",
                        "tbl_assigned_course",
                        $sanitized_room_sched_code,     // Dynamically generated table name
                        $sanitized_section_sched_code,
                        $sanitized_ccl_room_sched_code
                    ];

                    $tablesToUpdateWithoutProfCode = [
                        "tbl_prof_schedstatus"
                    ];

                    // Update tbl_prof and tbl_assigned_course without prof_sched_code
                    foreach ($tablesToUpdateWithoutProfSchedCode as $table) {
                        $updateTableQuery = "UPDATE $table SET prof_code = ? WHERE prof_code = ? AND ay_code = ? AND dept_code = ? AND semester = ?";
                        $updateTableStmt = $conn->prepare($updateTableQuery);
                        $updateTableStmt->bind_param("sssss", $new_code, $current_old_prof_code, $ay_code, $dept_code, $semester);
                        $updateTableStmt->execute();
                    }

                    // Update tbl_prof_schedstatus without prof_code
                    foreach ($tablesToUpdateWithoutProfCode as $table) {
                        $updateTableQuery = "UPDATE $table SET prof_sched_code = ? WHERE prof_sched_code = ? AND ay_code = ? AND dept_code = ? AND semester = ?";
                        $updateTableStmt = $conn->prepare($updateTableQuery);
                        $updateTableStmt->bind_param("sssss", $new_prof_sched_code, $old_prof_sched_code, $ay_code, $dept_code, $semester);
                        $updateTableStmt->execute();
                    }

                    // Update tables with prof_sched_code
                    foreach ($tablesToUpdateWithProfSchedCode as $table) {
                        $updateTableQuery = "UPDATE $table SET prof_code = ?, prof_sched_code = ? WHERE prof_code = ? AND prof_sched_code = ? AND ay_code = ? AND dept_code = ? AND semester = ?";
                        $updateTableStmt = $conn->prepare($updateTableQuery);
                        $updateTableStmt->bind_param("sssssss", $new_code, $new_prof_sched_code, $current_old_prof_code, $old_prof_sched_code, $ay_code, $dept_code, $semester);
                        $updateTableStmt->execute();

                        echo "<br>Old Prof Code: $current_old_prof_code<br>";
                        echo "New Prof Code: $new_code<br>";
                        echo "Old Prof Sched Code: $old_prof_sched_code<br>";
                        echo "New Prof Sched Code:  $new_prof_sched_code<br>";
                    }

                    $count++; // Increment count for the next record
                }
            }
        }
    }
}
// // Fetch all records matching the criteria
// $fetch_query_acc = "SELECT * FROM tbl_prof_acc WHERE prof_unit = ? AND prof_type = ? AND dept_code = ? AND semester = ? AND ay_code = ? ORDER BY id ASC";
// $stmt_fetch_acc = $conn->prepare($fetch_query_acc);
// $stmt_fetch_acc->bind_param("sssss", $prof_unit, $professor_type, $department_code, $semester, $ay_code);
// $stmt_fetch_acc->execute();
// $result_fetch_acc = $stmt_fetch_acc->get_result();

// $count = 1; // Start with 1 for reassigning prof_code
// $new_prof_codes = []; // To store mapping of old to new prof_code

// while ($row = $result_fetch_acc->fetch_assoc()) {
//     // Generate the new `prof_code`
//     $new_prof_code = strtoupper($prof_unit) . " " . $count . " - " . ucfirst($row['last_name']) . " " . ucfirst($row['suffix']);

//     // Map old to new prof_code
//     $new_prof_codes[$row['prof_code']] = $new_prof_code;

//     // Update the `prof_code` in `tbl_prof_acc`
//     $update_query_acc = "UPDATE tbl_prof_acc SET prof_code = ? WHERE id = ?";
//     $stmt_update_acc = $conn->prepare($update_query_acc);
//     $stmt_update_acc->bind_param("ss", $new_prof_code, $row['id']);
//     $stmt_update_acc->execute();
//     if ($stmt_update_acc->error) {
//         echo "Error updating tbl_prof_acc: " . $stmt_update_acc->error;
//     }

//     $count++; // Increment for the next prof_code
// }

// Commit transaction
$conn->commit();
// echo "Records updated successfully.";
// // Redirect or display success message
// header('Location: success_page.php'); // Redirect to a success page
//         exit;

//     } catch (Exception $e) {
//         // Rollback transaction in case of an error
//         $conn->rollback();
//         echo "Error updating records: " . $e->getMessage();
//     }
// } else {
//     echo "No records selected.";
// }




// Handle GET request for filtering
if (isset($_GET['prof_unit'])) {
    $search_unit = $_GET['prof_unit'];
}
if (isset($_GET['prof_type'])) {
    $search_prof = $_GET['prof_type'];
}
if (isset($_GET['prof_code_name'])) {
    $search_prof_code_name = $_GET['prof_code_name'];
}

// Fetch records from the database with filtering
$sql = "SELECT * FROM tbl_prof WHERE dept_code = ?";
$params = [$dept_code];
$types = "s";

if (!empty($search_unit)) {
    $sql .= " AND prof_unit = ?";
    $params[] = $search_unit;
    $types .= "s";
}

if (!empty($search_prof)) {
    $sql .= " AND prof_type = ?";
    $params[] = $search_prof;
    $types .= "s";
}

if (!empty($search_prof_code_name)) {
    $sql .= " AND (prof_code LIKE ? OR prof_name LIKE ?)";
    $params[] = "%$search_prof_code_name%";
    $params[] = "%$search_prof_code_name%";
    $types .= "ss";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all filtered records
$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

$stmt->close();

// Retrieve message from session if set
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Professor Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="orig-logo.png">
    <link rel="stylesheet" href="prof_input.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>
    <br>

<div class="header">
        <h1 class="title" style="color:#FD7238; font-size:30px;"><i class="far fa-list-alt" style="color:#FD7238;"></i> PROFESSOR LIST</h1>

    </div>

    <section class="prof-input">
        <div class="table-wrapper">
            <form id="prof-form" action="prof_input.php" method="POST">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                            </th>
                            <th>Professor Code</th>
                            <th>Appointment</th>
                            <th>Rank</th>
                            <th>Unit</th>
                            <th>Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT * FROM tbl_prof_acc WHERE dept_code = '$dept_code' AND semester = '$semester' AND ay_code = '$ay_code'";
                        $result = $conn->query($query);

                        // Debugging: Check the query
                        if ($result === FALSE) {
                            echo "Error fetching data: " . $conn->error;
                            exit;
                        }
                        ?>

                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()):
                                $full_name = $row["first_name"] . " " . $row["middle_initial"] . " " . $row["last_name"] . " " . $row["suffix"];
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="prof_select[]" value="<?= htmlspecialchars($row['id']) ?>" class="prof-checkbox">
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($row['prof_code']) ?></p>
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($row['prof_type']) ?></p>
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($row['academic_rank']) ?></p>
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($row['prof_unit']) ?></p>
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($full_name) ?></p>
                                    </td>
                                    <td>
                                        <p><?= htmlspecialchars($row['cvsu_email']) ?></p>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">No Professor Records Found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <input type="hidden" name="action" value="change_all">
                <button type="submit" class="btn-change">Change Prof Code</button>
            </form>
        </div>
    </section>

    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('.prof-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = source.checked);
        }
    </script>






    <?php if (!empty($message)): ?>
        <script>
            alert("<?php echo htmlspecialchars($message, ENT_QUOTES); ?>");
        </script>
    <?php endif; ?>
    <script>
        // Function to open the confirmation modal
        function openModal() {
            // Get the selected checkboxes
            const selectedCheckboxes = document.querySelectorAll('tbody input[type="checkbox"]:checked');

            // If no checkboxes are selected, show an alert
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one professor.');
                return;
            }

            // Show the modal if checkboxes are selected
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            modal.show();
        }

        // Function to handle the change to part-time action
        function changeToPartTime() {
            const selectedCheckboxes = document.querySelectorAll('tbody input[type="checkbox"]:checked');
            const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

            // Perform AJAX request to update the part_time column in the database
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_part_time.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    alert('Selected records have been updated to part-time.');
                    location.reload(); // Refresh the page to show updated data
                } else {
                    alert('Error updating records.');
                }
            };
            xhr.send('ids=' + selectedIds.join(','));
        }


        // Function to hide columns based on filter criteria
        function hideFilteredColumns() {
            // Get the filter values from the URL or your form (example for prof_unit and prof_type)
            const urlParams = new URLSearchParams(window.location.search);
            const profUnitFilter = urlParams.get('prof_unit') || '';
            const profTypeFilter = urlParams.get('prof_type') || '';

            // Hide the "Professor Unit" column if a filter is applied
            if (profUnitFilter && profUnitFilter !== "ALL") {
                hideColumn('Professor Unit');
            }

            // Hide the "Professor Type" column if a filter is applied
            if (profTypeFilter && profTypeFilter !== "ALL") {
                hideColumn('Professor Type');
            }
        }

        // Function to hide the column by header text
        function hideColumn(columnName) {
            const headers = document.querySelectorAll('table th');
            let columnIndex = -1;

            // Find the column index by header text
            headers.forEach((th, index) => {
                if (th.textContent.trim() === columnName) {
                    columnIndex = index;
                }
            });

            // If the column exists, hide it
            if (columnIndex !== -1) {
                const rows = document.querySelectorAll('table tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td, th');
                    cells[columnIndex].style.display = 'none'; // Hide the cell
                });
            }
        }

        // Call the function when the page is loaded
        document.addEventListener('DOMContentLoaded', hideFilteredColumns);
    </script>
</body>

</html>