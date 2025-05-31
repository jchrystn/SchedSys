<?php
session_start();
include("../../config.php");

$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$semester = isset($_SESSION['semester']) ? $_SESSION['semester'] : 'Unknown';
$dept_code = $_SESSION['dept_code'];
$user_type = $_SESSION['user_type'];
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}



// Step 2: Prepare the SQL statement to retrieve the last inserted ID from tbl_course
$sql = "SELECT MAX(id) AS last_id FROM tbl_signatory"; // Assuming your primary key is 'id'
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $last_inserted_id = $row['last_id'];
} else {
    echo "No records found.";
}


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



// echo "$semester";
// echo "$ay_code";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate token
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['token']) {
        // Token is invalid, redirect to the same page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    // Regenerate a new token to prevent reuse
    $_SESSION['token'] = bin2hex(random_bytes(32));

    $sign_id = $_POST['sign_id'] ?? '';
    $recommending = $_POST['recommending'] ?? '';
    $position_recommending = $_POST['position_recommending'] ?? '';
    $reviewed = $_POST['reviewed'] ?? '';
    $position_reviewed = $_POST['position_reviewed'] ?? '';
    $semester = $_SESSION['semester'] ?? '';
    $user_type = $_SESSION['user_type'] ?? '';
    $approved = $_POST['approved'] ?? '';
    $position_approved = $_POST['position_approved'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // Check if the record already exists
        $check_sql = "SELECT * FROM tbl_signatory 
                      WHERE recommending='$recommending' 
                      AND reviewed='$reviewed' 
                      AND approved='$approved'
                       AND user_type='$user_type' 
                      AND dept_code='$dept_code'";
        $check_result = $conn->query($check_sql);

        if ($check_result->num_rows > 0) {
            // Record already exists
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Signatory with the same details already exists.';
                modal.show();
            });
        </script>";
        } else {
            // Insert into tbl_signatory with dept_code
            $sql = "INSERT INTO tbl_signatory (college_code, semester, recommending, position_recommending, reviewed, position_reviewed, approved, position_approved, dept_code, user_type) 
                    VALUES ('$college_code', '$semester', '$recommending', '$position_recommending', '$reviewed', '$position_reviewed', '$approved', '$position_approved', '$dept_code', '$user_type')";

            if ($conn->query($sql) === TRUE) {
                echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Signatory Added Successfully.';
                modal.show();
            });
        </script>";
            }
        }
    } elseif ($action === 'update') {
        if (!empty($sign_id)) {
            // Update tbl_signatory where dept_code matches
            $sql = "UPDATE tbl_signatory 
                    SET recommending='$recommending', position_recommending='$position_recommending', reviewed='$reviewed', position_reviewed='$position_reviewed', approved='$approved', position_approved='$position_approved' 
                    WHERE id='$sign_id' AND dept_code='$dept_code'";

            if ($conn->query($sql) === TRUE) {
                echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Signatory Updated Successfully.';
                modal.show();
            });
        </script>";
            }
        } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Sign ID is required for update.';
                modal.show();
            });
        </script>";
        }
    } elseif ($action === 'delete') {
        if (!empty($sign_id)) {
            // Delete from tbl_signatory where dept_code matches
            $sql = "DELETE FROM tbl_signatory WHERE id='$sign_id' AND dept_code='$dept_code'";

            if ($conn->query($sql) === TRUE) {
                echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    var modal = new bootstrap.Modal(document.getElementById('successModal'));
                    document.getElementById('successMessage').textContent = 'Signatory Deleted Successfully.';
                    modal.show();
                });
            </script>";
            }
        } else {
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('successModal'));
                document.getElementById('successMessage').textContent = 'Sign ID is required for Deletion.';
                modal.show();
            });
        </script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Signatory Input</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <link rel="stylesheet" href="../../../css/department_secretary/input_forms/room_input.css">
</head>

<body>
    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");
    ?>


    <section class="class-input">

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <?php if ($user_type !== "CCL Head") { ?>
                <li class="nav-item">
                    <a class="nav-link" id="program-tab" href="program_input.php" aria-controls="program"
                        aria-selected="false">Program Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="course-tab" href="course_input.php" aria-controls="course"
                        aria-selected="false">Checklist Input</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="section-tab" href="section_input.php" aria-controls="section"
                        aria-selected="false">Section Input</a>
                </li>

            <?php } ?>
            <?php
            // Assume $user_type is the variable that stores the type of the user
            $room_input_page = ($user_type === 'CCL Head') ? 'ccl_room_input.php' : 'classroom_input.php';
            ?>

            <li class="nav-item">
                <a class="nav-link" id="room-tab"
                    href="<?php echo htmlspecialchars($room_input_page, ENT_QUOTES, 'UTF-8'); ?>" aria-controls="room"
                    aria-selected="false">Room Input</a>
            </li>
            <?php if ($user_type == "Department Secretary") { ?>
                <li class="nav-item">
                    <a class="nav-link" id="prof-tab" href="#" aria-controls="prof" aria-selected="false"
                        data-bs-toggle="modal" data-bs-target="#profUnitModal">Instructor Input</a>
                </li>
            <?php } ?>
            <li class="nav-item">
                <a class="nav-link active" id="sgintaory-tab" href="signatory_input.php" aria-controls="signatory"
                    aria-selected="true">Signatory Input</a>
            </li>
        </ul>



        <?php
        // $dept_code = $_SESSION['dept_code'] ?? ''; // Get dept_code from session
        
        // // Check if a record exists for the dept_code in the tbl_signatory table
        // $sql_check = "SELECT COUNT(*) FROM tbl_signatory WHERE dept_code = ?";
        // $stmt_check = $conn->prepare($sql_check);
        // $stmt_check->bind_param("s", $dept_code);
        // $stmt_check->execute();
        // $stmt_check->bind_result($record_count);
        // $stmt_check->fetch();
        // $stmt_check->close();
        
        // // Disable the "Add" button if a record exists
        // $disable_add_button = ($record_count > 0) ? 'disabled' : '';
        ?>
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="title">Signatory Input</h5>
                <form action="" method="POST" id="sign-form" required>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token']); ?>">
                    <input type="hidden" id="sign_id" name="sign_id" value="<?php echo $last_inserted_id; ?>" readonly>

                    <!-- <h5 class="form-title">Recommending Approval</h5> -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="recommending"
                            placeholder="Enter Recommending Approval" name="recommending" autocomplete="off"
                            style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="position_recommending"
                            placeholder="Enter Recommending Approval Position" name="position_recommending"
                            autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <hr>

                    <!-- <h5 class="form-title">Reviewed By</h5> -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="reviewed" placeholder="Enter Reviewed By"
                            name="reviewed" autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="position_reviewed"
                            placeholder="Enter Reviewed By Position" name="position_reviewed" autocomplete="off"
                            style="color: #6c757d;" required>
                    </div>

                    <hr>

                    <!-- <h5 class="form-title">Approved By</h5> -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="approved" placeholder="Enter Approved By"
                            name="approved" autocomplete="off" style="color: #6c757d;" required>
                    </div>

                    <div class="mb-3">
                        <input type="text" class="form-control" id="position_approved"
                            placeholder="Enter Approved By Position" name="position_approved" autocomplete="off"
                            style="color: #6c757d;" required>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="action" value="add" class="btn btn-add">Add</button>
                        <div class="btn-inline-group">
                            <button type="submit" name="action" value="update" class="btn btn-primary btn-update-delete"
                                style="display: none;">Update</button>
                            <button type="submit" name="action" value="delete" class="btn btn-danger btn-update-delete"
                                style="display: none;">Delete</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-8">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Recommending Approval</th>
                                <th>Recommending Approval Position</th>
                                <th>Reviewed By</th>
                                <th>Reviewed By Position</th>
                                <th>Approved By</th>
                                <th>Approved By Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch records based on dept_code from the session
                            $sql = "SELECT * FROM tbl_signatory WHERE dept_code = ? AND user_type = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("ss", $dept_code, $user_type);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr 
                                    data-sign_id='" . htmlspecialchars($row['id']) . "'
                                    data-recommending='" . htmlspecialchars($row['recommending']) . "'
                                    data-position_recommending='" . htmlspecialchars($row['position_recommending']) . "'
                                    data-reviewed='" . htmlspecialchars($row['reviewed']) . "'
                                    data-position_reviewed='" . htmlspecialchars($row['position_reviewed']) . "'
                                    data-approved='" . htmlspecialchars($row['approved']) . "'
                                    data-position_approved='" . htmlspecialchars($row['position_approved']) . "'>
                                    <td>" . htmlspecialchars($row['recommending']) . "</td>
                                    <td>" . htmlspecialchars($row['position_recommending']) . "</td>
                                    <td>" . htmlspecialchars($row['reviewed']) . "</td>
                                    <td>" . htmlspecialchars($row['position_reviewed']) . "</td>
                                    <td>" . htmlspecialchars($row['approved']) . "</td>
                                    <td>" . htmlspecialchars($row['position_approved']) . "</td>
                                </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No records found</td></tr>";
                            }
                            $stmt->close();
                            ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-body">
                <p id="successMessage"></p>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                    id="closeModalButton">Close</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="profUnitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Use 'modal-lg' for large modal size -->
            <div class="modal-content">
                <div class="modal-body">
                    <div class="row g-4">
                        <?php
                        if (isset($_SESSION['dept_code'], $_SESSION['semester'], $_SESSION['ay_code'])) {
                            $dept_code = $_SESSION['dept_code'];
                            $semester = $_SESSION['semester'];
                            $ay_code = $_SESSION['ay_code']; // Get session variables
                        
                            // Query to get program_units for the department
                            $stmt = $conn->prepare("
                            SELECT program_units 
                            FROM tbl_department 
                            WHERE dept_code = ? 
                              AND program_units IS NOT NULL 
                              AND program_units != ''
                        ");
                            $stmt->bind_param("s", $dept_code); // Bind the parameter
                            $stmt->execute(); // Execute the query
                            $result_unit = $stmt->get_result(); // Get the result
                        
                            if ($result_unit && $result_unit->num_rows > 0) {
                                while ($row = $result_unit->fetch_assoc()) {
                                    $program_units = htmlspecialchars($row['program_units']);
                                    // Split program_units by comma
                                    $units = explode(',', $program_units);

                                    foreach ($units as $unit) {
                                        $unit = trim($unit); // Remove any extra spaces
                        
                                        // Query to count professors for each program_unit
                                        $count_stmt = $conn->prepare("
                                        SELECT COUNT(*) AS unit_count 
                                        FROM tbl_prof_acc 
                                        WHERE dept_code = ? 
                                          AND semester = ? 
                                          AND ay_code = ? 
                                          AND prof_unit = ?
                                          AND status = 'approve'
                                    ");
                                        $count_stmt->bind_param("ssss", $dept_code, $semester, $ay_code, $unit);
                                        $count_stmt->execute();
                                        $count_result = $count_stmt->get_result();
                                        $unit_count = $count_result->fetch_assoc()['unit_count'] ?? 0; // Get the count
                        
                                        ?>
                                        <div class="col-md-3 col-lg-6">
                                            <div class="card shadow-sm h-100" onclick="redirectToProfInput('<?= $unit ?>')">
                                                <div class="card-body text-center">
                                                    <div class="icon-container mb-3">
                                                        <i class="fas fa-users"></i>
                                                    </div>
                                                    <h6 class="card-title mb-1 fw-bold"><?= $unit ?></h6>
                                                    <p class="card-text text-muted"><?= $unit_count ?> Professors</p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $count_stmt->close(); // Close the count statement
                                    }
                                }
                            } else {
                                echo '<div class="col-12 text-center text-muted">No program units available</div>';
                            }

                            $stmt->close(); // Close the statement
                        } else {
                            echo '<div class="col-12 text-center text-muted">Required session variables are missing</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script>
        function redirectToProfInput(profUnit) {
            // Send the selected prof_unit directly to prof_input.php
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    // Redirect to the same page after setting the session
                    window.location.href = `http://localhost/SchedSys3/php/department_secretary/input_forms/prof_input.php?prof_unit=${encodeURIComponent(profUnit)}`;
                }
            };
            xhr.send(`set_session=true&prof_unit=${encodeURIComponent(profUnit)}`);
        }

    </script>



    <script>
        // Function to show the modal
        function showProfUnitModal() {
            var modal = new bootstrap.Modal(document.getElementById('profUnitModal'));
            modal.show();
        }
    </script>

    <script>
        // Function to fill the form with the selected row's data
        function fillForm(signId, recommending, positionRecommending, reviewed, positionReviewed, approved, positionApproved) {
            // Populate form fields
            document.getElementById('sign_id').value = signId;
            document.getElementById('recommending').value = recommending;
            document.getElementById('position_recommending').value = positionRecommending;
            document.getElementById('reviewed').value = reviewed;
            document.getElementById('position_reviewed').value = positionReviewed;
            document.getElementById('approved').value = approved;
            document.getElementById('position_approved').value = positionApproved;

            // Hide "Add" button and show "Update" and "Delete" buttons
            document.querySelector('.btn-add').style.display = 'none';
            document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'inline-block');
        }

        // Loop through all table rows and add click event listener
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.addEventListener('click', function () {
                // Fetch data attributes
                const signId = this.getAttribute('data-sign_id');
                const recommending = this.getAttribute('data-recommending');
                const positionRecommending = this.getAttribute('data-position_recommending');
                const reviewed = this.getAttribute('data-reviewed');
                const positionReviewed = this.getAttribute('data-position_reviewed');
                const approved = this.getAttribute('data-approved');
                const positionApproved = this.getAttribute('data-position_approved');

                // Populate the form
                fillForm(signId, recommending, positionRecommending, reviewed, positionReviewed, approved, positionApproved);

                // Highlight the selected row
                document.querySelectorAll('table tbody tr').forEach(r => r.classList.remove('clicked-row'));
                this.classList.add('clicked-row');
            });
            // Reset form when clicking outside both the form and the table
            document.addEventListener('click', function (event) {
                const roomForm = document.querySelector('#sign-form'); // Form selector
                const table = document.querySelector('table'); // Table selector
                let selectedRow = document.querySelector('.clicked-row'); // Get the currently selected row (if any)

                // Check if the click is outside both the form and the table
                if (!roomForm.contains(event.target) && !table.contains(event.target)) {
                    if (selectedRow) {
                        // Reset form values
                        document.getElementById('recommending').value = '';
                        document.getElementById('position_recommending').value = '';
                        document.getElementById('reviewed').value = '';
                        document.getElementById('position_reviewed').value = '';
                        document.getElementById('approved').value = '';
                        document.getElementById('position_approved').value = '';

                        // Show "Add" button, hide "Update" and "Delete" buttons
                        document.querySelector('.btn-add').style.display = 'inline-block';
                        document.querySelectorAll('.btn-update-delete').forEach(btn => btn.style.display = 'none');

                        // Remove the 'clicked-row' class from the previously selected row
                        selectedRow.classList.remove('clicked-row');
                        selectedRow = null;
                    }
                }


            });
        });
    </script>

</body>

</html>