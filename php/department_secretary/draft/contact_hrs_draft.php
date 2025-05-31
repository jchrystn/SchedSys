<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

$dept_code = $_SESSION['dept_code'] ?? null;
$college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';


// Ensure the user is logged in as a Department Secretary
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../../login/login.php");
    exit();
}


$fetch_info_query = "SELECT ay_code, ay_name,semester FROM tbl_ay WHERE college_code = '$college_code' and active = '1'";
$result = $conn->query($fetch_info_query);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ay_code = $row['ay_code'];
    $ay_name = $row['ay_name'];
    $semester = $row['semester'];
}

if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}

// Handle Academic Year options
$ay_options = [];
$sql_ay = "SELECT DISTINCT ay_name, ay_code FROM tbl_ay";
$result_ay = $conn->query($sql_ay);

if ($result_ay->num_rows > 0) {
    while ($row_ay = $result_ay->fetch_assoc()) {
        $ay_options[] = [
            'ay_name' => $row_ay['ay_name'],
            'ay_code' => $row_ay['ay_code']
        ];
    }
}

// Handle the Academic Year selection
if (isset($_POST['search_ay_name'])) {
    $ay_name = $_POST['search_ay_name'];
    foreach ($ay_options as $option) {
        if ($option['ay_name'] == $ay_name) {
            $selected_ay_code = $option['ay_code'];
            $_SESSION['ay_code'] = $selected_ay_code;
            break;
        }
    }
} elseif (isset($_SESSION['ay_name'])) {
    $ay_name = $_SESSION['ay_name'];
    foreach ($ay_options as $option) {
        if ($option['ay_name'] == $ay_name) {
            $selected_ay_code = $option['ay_code'];
            break;
        }
    }
} else {
    if (!empty($ay_options)) {
        $ay_name = $ay_options[0]['ay_name'];
        $selected_ay_code = $ay_options[0]['ay_code'];
    }
}

// Handle the Semester selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_semester'])) {
    $semester = $_POST['search_semester'];
    $_SESSION['semester'] = $semester;
} elseif (isset($_SESSION['semester'])) {
    $semester = $_SESSION['semester'];
} else {
    // Default fallback if session value is not set
    $semester = '1st Semester'; // or any other default value
}

// Handle POST requests for Edit, Delete, and Fetch Schedule
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['edit'])) {
        $_SESSION['prof_sched_code'] = $_POST['prof_sched_code'];
        $_SESSION['semester'] = $_POST['semester'];
        $_SESSION['ay_code'] = $_POST['ay_code'];
        $_SESSION['prof_code'] = $_POST['prof_code'];
        $_SESSION['contact_hrs_type'] = $_POST['contact_hrs_type'];
        header("Location: ../input_forms/contact_plot.php");
        exit();
    }
}



// Handle POST requests for searching professor
$search_prof = isset($_POST['search_prof']) ? '%' . $_POST['search_prof'] . '%' : '%';

// Fetch the schedules based on filtering criteria
$sql = "
    SELECT tbl_pcontact_schedstatus.prof_sched_code, tbl_pcontact_schedstatus.semester, tbl_pcontact_schedstatus.dept_code, tbl_pcontact_schedstatus.ay_code, tbl_pcontact_schedstatus.prof_code, tbl_prof.prof_name 
    FROM tbl_pcontact_schedstatus
    INNER JOIN tbl_prof
    ON tbl_pcontact_schedstatus.prof_code = tbl_prof.prof_code
    WHERE tbl_pcontact_schedstatus.status = 'draft' 
    AND tbl_pcontact_schedstatus.ay_code = ?
    AND tbl_pcontact_schedstatus.semester = ?
    AND (tbl_pcontact_schedstatus.prof_code COLLATE utf8mb4_general_ci LIKE ? OR tbl_prof.prof_name COLLATE utf8mb4_general_ci LIKE ?) 
    AND tbl_pcontact_schedstatus.dept_code = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssss', $selected_ay_code, $semester, $search_prof, $search_prof, $dept_code);
$stmt->execute();
$result = $stmt->get_result();



if (isset($_GET['action']) && $_GET['action'] == 'fetch_schedule' && isset($_GET['prof_id'])) {
    $prof_id = $_GET['prof_id'];
    $semester = $_GET['semester'];

    $sql = "SELECT * FROM tbl_pcontact_schedstatus WHERE prof_code = ? AND dept_code = ? AND ay_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $prof_id, $dept_code, $ay_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $prof_sched_code = $row['prof_sched_code'];
        echo "<h5>Schedule for Instructor: " . htmlspecialchars($prof_id) . "</h5>";
        echo fetchScheduleForProf($prof_sched_code, $selected_ay_code, $semester);
    } else {
        echo "<p>No schedule found for this Instructor.</p>";
    }

    $stmt->close();
    exit;
}

//delete schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_sched'])) {
        $prof_code = $_SESSION['prof_code'];
        $ay_code = $_SESSION['ay_code'];
        $dept_code = $_SESSION['dept_code'];
        $semester = $_SESSION['semester'];
        $prof_sched_code = $prof_code . "_" . $ay_code;

        $sanitized_pcontact_sched_code = preg_replace("/[^a-zA-Z0-9_]/", "_", "tbl_pcontact_sched_" . $dept_code . "_" . $ay_code);


        $check_table_sql = "SHOW TABLES LIKE '$sanitized_pcontact_sched_code'";
        $result = $conn->query($check_table_sql);

        if ($result->num_rows > 0) {
            // Check if the table has matching records
            $check_table_count_sql = "SELECT COUNT(*) as count FROM $sanitized_pcontact_sched_code WHERE prof_sched_code = ? AND semester = ?";
            $stmt_check_table_count = $conn->prepare($check_table_count_sql);
            $stmt_check_table_count->bind_param("ss", $prof_sched_code, $semester);
            $stmt_check_table_count->execute();
            $stmt_check_table_count->bind_result($count);
            $stmt_check_table_count->fetch();
            $stmt_check_table_count->close();

            if ($count > 0) {
                // Delete all schedules with the same semester and prof_sched_code
                $delete_all_sched_sql = "DELETE FROM $sanitized_pcontact_sched_code WHERE prof_sched_code = ? AND semester = ?";
                $stmt_delete_all = $conn->prepare($delete_all_sched_sql);
                $stmt_delete_all->bind_param("ss", $prof_sched_code, $semester);
                $stmt_delete_all->execute();
                $deleted_schedules = $stmt_delete_all->affected_rows;
                $stmt_delete_all->close();

                if ($deleted_schedules > 0) {
                    echo "Deleted $deleted_schedules schedules from $sanitized_pcontact_sched_code.<br>";

                    // Delete corresponding entries from tbl_pcontact_counter
                    $delete_counter_sql = "DELETE FROM tbl_pcontact_counter WHERE prof_sched_code = ? AND semester = ?";
                    $stmt_delete_counter = $conn->prepare($delete_counter_sql);
                    $stmt_delete_counter->bind_param("ss", $prof_sched_code, $semester);
                    if ($stmt_delete_counter->execute()) {
                        if ($stmt_delete_counter->affected_rows > 0) {
                            echo "Entries deleted from tbl_pcontact_counter.<br>";
                        } else {
                            echo "No entries deleted from tbl_pcontact_counter.<br>";
                        }
                    }
                    $stmt_delete_counter->close();

                    // Delete corresponding entries from tbl_pcontact_schedstatus
                    $delete_schedstatus_sql = "DELETE FROM tbl_pcontact_schedstatus WHERE prof_sched_code = ? AND semester = ?";
                    $stmt_delete_schedstatus = $conn->prepare($delete_schedstatus_sql);
                    $stmt_delete_schedstatus->bind_param("ss", $prof_sched_code, $semester);
                    if ($stmt_delete_schedstatus->execute()) {
                        if ($stmt_delete_schedstatus->affected_rows > 0) {
                            echo "Entries deleted from tbl_pcontact_schedstatus.<br>";
                        } else {
                            echo "No entries deleted from tbl_pcontact_schedstatus.<br>";
                        }
                    }
                    $stmt_delete_schedstatus->close();
                } else {
                    echo "No schedules deleted in $sanitized_pcontact_sched_code.<br>";
                }
            }
        } else {
            // Table does not exist: display message
            echo "No Contact Hours Schedules found for the Selected Instructor.<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Instructor Schedules</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/department_secretary/draft/prof_draft.css">
</head>

<body>

    <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
    include($IPATH . "navbar.php");  ?>



<div class="header">
        <h2 class="title"> <i class="fa-solid fa-bars-progress" style="color: #FD7238;"></i> CONSULTATION HOURS SCHEDULE DRAFT</h2>
        <br>
    </div>

    <div class="container">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="draft-tab" href="draft.php" aria-controls="draft" aria-selected="fasle">Schedule Draft</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="contact-tab" href="contact_hrs_draft.php" aria-controls="contact" aria-selected="true">Consulation Hours Draft</a>
            </li>
        </ul>
        <form method="POST" action="contact_hrs_draft.php">
            <div class="search-bar-container">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search_prof" value="<?php echo isset($_POST['search_prof']) ? htmlspecialchars($_POST['search_prof']) : ''; ?>" placeholder="Search" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn w-100">Search</button>
                </div>
            </div>
        </form>

        <div class="table-container">
            <table class="table" id="scheduleTable">
                <thead>
                    
                    <th>Instructor Code</th>
                    <th>Instructor Name</th>
                    <th></th>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="input"><?php echo htmlspecialchars($row['prof_code']); ?></td>
                                <td class="input"><?php echo htmlspecialchars($row['prof_name']); ?></td>
                                <td>
                                    <button class="view-btn" data-prof-id="<?php echo htmlspecialchars($row["prof_code"]); ?>"></button>

                                    <!-- Edit Form -->
                                    <form method="POST" action="contact_hrs_draft.php" style="display:inline-block;">
                                        <input type="hidden" name="prof_sched_code" value="<?php echo htmlspecialchars($row['prof_sched_code'] ?? ''); ?>">
                                        <input type="hidden" name="semester" value="<?php echo htmlspecialchars($row['semester'] ?? ''); ?>">
                                        <input type="hidden" name="ay_code" value="<?php echo htmlspecialchars($row['ay_code'] ?? ''); ?>">
                                        <input type="hidden" name="prof_code" value="<?php echo htmlspecialchars($row['prof_code'] ?? ''); ?>">
                                        <input type="hidden" name="contact_hrs_type" value="<?php echo htmlspecialchars($row['contact_hrs_type'] ?? ''); ?>">
                                        <button type="submit" name="edit" class="edit-btn"><i class="fa-light fa-pencil"></i></button>
                                    </form>
                                    <!-- Delete Form -->
                                    <form method="POST" id="deleteForm" action="contact_hrs_draft.php" style="display:inline;">
                                        <input type="hidden" name="prof_code" id="modal_prof_code">
                                        <input type="hidden" name="semester" id="modal_semester">
                                        <input type="hidden" name="ay_code" id="modal_ay_code">
                                        <input type="hidden" name="prof_sched_code" id="modal_prof_sched_code">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="button" id="delete" name="delete" class="delete-btn" data-toggle="modal" data-target="#deleteConfirmationModal">
                                            <i class="fa-light fa-trash"></i>
                                        </button>
                                    </form>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No Records Found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p id="noRecordsMessage" class="text-center" style="display: none;">No Records Found</p>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this Contact Hours Schedule?</p>
                    </div>
                    <div class="modal-footer">
                        <div class="modal-btn">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Success Modal -->
    <div class="modal fade" id="deleteSuccessModal" tabindex="-1" role="dialog" aria-labelledby="deleteSuccessModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSuccessModalLabel">Success</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>The Contact Hours Schedule has been deleted successfully.</p>
                </div>
                <div class="modal-footer"></div>
                <div class="modal-btn">
                    <button type="button" class="btn btn-primary" id="successOkBtn">OK</button>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <script>
        $(document).ready(function() {
            // Check schedules for each prof when the page loads
            filterProfBySchedule();

            // Handler to load schedule into modal
            $('#scheduleModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var profId = button.data('prof-id');
                var semester = $('#search_semester').val();

                console.log('Instructor ID:', profId); // Debug: Log Section ID
                console.log('Semester:', semester); // Debug: Log Semester

                var modal = $(this);
                $.ajax({
                    url: 'contact_hrs_draft.php',
                    method: 'GET',
                    data: {
                        action: 'fetch_schedule',
                        prof_id: profId,
                        semester: semester
                    },
                    success: function(response) {
                        console.log("Response: ", response); // Add this line to see the response in the console
                        modal.find('#scheduleContent').html(response); // Update modal content
                    },
                    error: function() {
                        console.error('Failed to fetch schedule for Instructor ID: ' + profId);
                        modal.find('#scheduleContent').html('<p>Error loading schedule.</p>');
                    }
                });
            });

            function filterProfBySchedule() {
                var selectedSemester = $('#search_semester').val();
                var rowsVisible = false;

                $('#scheduleTable tbody tr').each(function() {
                    var row = $(this);
                    var profId = row.find('button[data-prof-id]').data('prof-id');

                    $.ajax({
                        url: 'contact_hrs_draft.php',
                        method: 'GET',
                        data: {
                            action: 'fetch_schedule',
                            prof_id: profId,
                            semester: selectedSemester,
                            dept_code: $('#dept_code').val() // Make sure dept_code is included in the request if necessary
                        },
                        success: function(response) {
                            if (response.trim().includes("No Available Instructor Schedule")) {
                                row.hide();
                            } else {
                                row.show();
                                rowsVisible = true;
                            }
                        },
                        error: function() {
                            console.error('Failed to fetch schedule for Instructor ID: ' + profId);
                        },
                        complete: function() {
                            // Show or hide "No Records Found" message
                            if (rowsVisible) {
                                $('#noRecordsMessage').hide();
                            } else {
                                $('#noRecordsMessage').show();
                            }
                        }
                    });
                });
            }
        });

        // Show the delete confirmation modal when "Delete" is clicked
        document.getElementById('delete').addEventListener('click', function() {
            $('#deleteConfirmationModal').modal('show');
        });

        // Handle the deletion when the user confirms it
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const prof_code = document.getElementById('modal_prof_code').value;
            const semester = document.getElementById('modal_semester').value;
            const ay_code = document.getElementById('modal_ay_code').value;
            const prof_sched_code = document.getElementById('modal_prof_sched_code').value;

            // Perform the AJAX request to delete the schedule
            $.ajax({
                url: 'contact_hrs_draft.php',
                type: 'POST',
                data: {
                    delete_sched: true,
                    prof_code: prof_code,
                    semester: semester,
                    ay_code: ay_code,
                    prof_sched_code: prof_sched_code,
                    action: 'delete'
                },
                success: function(response) {
                    $('#deleteConfirmationModal').modal('hide');
                    $('#deleteSuccessModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Error deleting schedule.');
                }
            });
        });

        // Redirect to contact_plot.php when the "OK" button in the success modal is clicked
        document.getElementById('successOkBtn').addEventListener('click', function() {
            window.location.href = 'contact_hrs_draft.php';
        });


        let main = document.getElementById('SelectAll');
        let select = document.getElementsByClassName('select');
        let delBtn = document.querySelector('.del-btn');
        let pubBtn = document.querySelector('.pub-btn');

        main.onclick = () => {
            if (main.checked) {
                for (let i = 0; i < select.length; i++) {
                    select[i].checked = true;
                }
                delBtn.style.display = 'block';
                pubBtn.style.display = 'block';
            } else {
                for (let i = 0; i < select.length; i++) {
                    select[i].checked = false;
                }
                delBtn.style.display = 'none';
                pubBtn.style.display = 'none';
            }
        };

        for (let i = 0; i < select.length; i++) {
            select[i].onclick = () => {
                let atLeastOneChecked = false;
                for (let j = 0; j < select.length; j++) {
                    if (select[j].checked) {
                        atLeastOneChecked = true;
                        break;
                    }
                }

                if (atLeastOneChecked) {
                    delBtn.style.display = 'block';
                    pubBtn.style.display = 'block';
                } else {
                    delBtn.style.display = 'none';
                    pubBtn.style.display = 'none';
                }
            };
        }

        $(document).ready(function() {
            $('#makePublicButton').on('click', function() {
                var selectedIds = [];
                $('input[name="schedule_id"]:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert("Please select at least one schedule.");
                    return;
                }

                $.ajax({
                    url: 'contact_hrs_draft.php',
                    method: 'POST',
                    data: {
                        action: 'make_public',
                        schedule_ids: selectedIds
                    },
                    success: function(response) {
                        alert(response);
                        // Optionally, reload or update the table here
                    },
                    error: function() {
                        alert("An error occurred while processing your request.");
                    }
                });
            });

            $('#selectAll').on('click', function() {
                var checked = $(this).is(':checked');
                $('input[name="schedule_id"]').prop('checked', checked);
                $('.del-btn, .pub-btn').toggle(checked);
            });

            $('input[name="schedule_id"]').on('change', function() {
                var anyChecked = $('input[name="schedule_id"]:checked').length > 0;
                $('.del-btn, .pub-btn').toggle(anyChecked);
            });
        });
    </script>
</body>

</html>