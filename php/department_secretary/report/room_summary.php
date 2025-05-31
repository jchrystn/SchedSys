<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../../config.php");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'Department Secretary') {
    header("Location: ../login/login.php");
    exit();
}

unset($_POST['search_ay_code']);
$user_dept_code = isset($_SESSION['dept_code']) ? $_SESSION['dept_code'] : 'Unknown';
$user_college_code = isset($_SESSION['college_code']) ? $_SESSION['college_code'] : 'Unknown';
$email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : 'no email';

if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}


if (!function_exists('formatTime')) {
    function formatTime($time)
    {
        return date('g:i a', strtotime($time));
    }
}

// Handle Academic Year options
$fetch_info_query = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = '$user_college_code' AND active = '1'";
$result = $conn->query($fetch_info_query);

$active_ay_code = null;
$active_semester = null;

// Check if query executed successfully and returned rows
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $active_ay_code = $row['ay_code'];
    $active_semester = $row['semester'];
}

// Set the ay_code and semester based on session or active values from the query


$ay_code = $_POST['search_ay_code'] ?? $active_ay_code;
$semester = $_POST['search_semester'] ?? $active_semester;

// Handle Academic Year options
// Handle Academic Year options
$ay_options = [];
$sql_ay = "SELECT DISTINCT ay_code, ay_name FROM tbl_ay"; // Fetch both ay_code and ay_name
$result_ay = $conn->query($sql_ay);

if ($result_ay->num_rows > 0) {
    while ($row_ay = $result_ay->fetch_assoc()) {
        // Store both ay_code and ay_name in the options array
        $ay_options[] = [
            'ay_code' => $row_ay['ay_code'],
            'ay_name' => $row_ay['ay_name']
        ];
    }
}

// Handle the form submission and set the selected ay_code
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_ay'])) {
    $selected_ay_code = $_POST['search_ay']; // Get selected ay_code from the POST request
} else {
    // Default fallback if no form is submitted
    $selected_ay_code = $active_ay_code; // or any other default value
}
// Fetch the ay_name based on selected ay_code
if (!empty($selected_ay_code)) {
    $sql_ay_name = "SELECT ay_name FROM tbl_ay WHERE ay_code = ?";
    $stmt = $conn->prepare($sql_ay_name);
    $stmt->bind_param("s", $selected_ay_code);
    $stmt->execute();
    $result_ay_name = $stmt->get_result();

    if ($result_ay_name->num_rows > 0) {
        $row_ay_name = $result_ay_name->fetch_assoc();
        $selected_ay_name = $row_ay_name['ay_name']; // Get the ay_name based on ay_code
    } else {
        $selected_ay_name = 'Not Found'; // Fallback if no ay_name found for selected ay_code
    }

    $stmt->close();
} else {
    $selected_ay_name = 'Select Year'; // Default fallback
}

// Handle the Semester selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_semester'])) {
    $selected_semester = $_POST['search_semester'];
} else {
    // Default fallback if session value is not set
    $selected_semester = '1st Semester'; // or any other default value
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>SchedSYS - Summary</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../../images/logo.png">
    <script src="/SchedSYS3/xlsx.full.min.js"></script>
    <script src="/SchedSYS3/html2pdf.bundle.min.js"></script>


    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>

    <link rel="stylesheet" href="../../../css/department_secretary/report/othersummary.css">
</head>

<body>

    <body>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>

        <h2 class="title"><i class="fa-solid fa-file-alt"></i> SCHEDULES</h2>

        <div class="container mt-5">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="section-tab" href="../library/lib_section.php" aria-controls="Section"
                    aria-selected="true">Section</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="classroom-tab" href="../library/lib_classroom.php" aria-controls="classroom"
                    aria-selected="false">Classroom</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../library/lib_professor.php" aria-controls="professor"
                    aria-selected="false">Instructor</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/majorsub_summary.php" aria-controls="professor"
                    aria-selected="false">Major Subject Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/minorsub_summary.php" aria-controls="professor"
                    aria-selected="false">Minor Subject Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="professor-tab" href="../report/room_summary.php" aria-controls="professor"
                    aria-selected="false">Classroom Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/prof_summary.php" aria-controls="professor"
                    aria-selected="false">Instructor Summary</a>
            </li>
            <?php if ($user_type =='Department Secretary'): ?>
            <li class="nav-item">
                <a class="nav-link" id="vacant-room-tab" href="/SchedSys3/php/viewschedules/data_schedule_vacant.php" aria-controls="vacant-room" aria-selected="false">Vacant Room</a>
            </li>
            <?php endif; ?>
        </ul>

            <div class="search-bar-container">
                <form method="POST" action="room_summary.php" class="row">
                    <div class="col-md-3">
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="search_ay_code" name="search_ay">
                            <?php foreach ($ay_options as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['ay_code']); ?>" <?php echo ($selected_ay_code == $option['ay_code']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['ay_name']); ?> <!-- Display ay_name -->
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="search_semester" name="search_semester">
                            <option value="1st Semester" <?php echo ($selected_semester == '1st Semester') ? 'selected' : ''; ?>>
                                1st Semester
                            </option>
                            <option value="2nd Semester" <?php echo ($selected_semester == '2nd Semester') ? 'selected' : ''; ?>>
                                2nd Semester
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn w-100">Search</button>
                    </div>
                </form>
            </div>
            <div class="container" id="scheduleContent">

                <div class="card-body schedule-content">
                    <?php

                    function shortenDay($day)
                    {
                        $days = [
                            'Monday' => 'Mon',
                            'Tuesday' => 'Tues',
                            'Wednesday' => 'Wed',
                            'Thursday' => 'Thurs',
                            'Friday' => 'Fri',
                            'Saturday' => 'Sat',
                            'Sunday' => 'Sun'
                        ];
                        return isset($days[$day]) ? $days[$day] : $day;
                    }

                    function convertTo12HourFormat($time)
                    {
                        // Check if the time is 'N/A' or not in the expected 24-hour format
                        if ($time === 'N/A' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                            return 'N/A';
                        }
                        // Convert to 12-hour format
                        return date("g:i A", strtotime($time));
                    }

                    $fetch_info_query_col = "SELECT college_code,dept_code FROM tbl_prof_acc WHERE user_type = 'CCL Head'";
                    $result_col = $conn->query($fetch_info_query_col);

                    if ($result_col->num_rows > 0) {
                        $row_col = $result_col->fetch_assoc();
                        $ccl_college_code = $row_col['college_code'];
                        // $ccl_dept_code = $row_col['dept_code'];
                    }



                    // Your existing functions and database connection code remain unchanged
                    $sql_rooms = "SELECT DISTINCT room_code, room_name,room_type FROM tbl_room WHERE dept_code = ?";
                    $stmt_rooms = $conn->prepare($sql_rooms);
                    $stmt_rooms->bind_param('s', $user_dept_code);
                    $stmt_rooms->execute();
                    $result_rooms = $stmt_rooms->get_result();

                    // Initialize an array to hold the unique rooms
                    $rooms = [];
                    while ($row = $result_rooms->fetch_assoc()) {
                        $rooms[] = $row;
                    }
                    $stmt_rooms->close();

                    // Initialize an array to hold schedule summary data
                    $room_summary = [];

                    // Collect section codes and schedules from the dynamic table
                    foreach ($rooms as $room) {
                        $active_room_code = $room['room_code'];
                        $room_type = $room['room_type'];

                        if ($room_type == "Computer Laboratory") {
                            $table_name = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$ccl_college_code}_{$selected_ay_code}");

                        } else {
                            $table_name = "tbl_roomsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$user_dept_code}_{$selected_ay_code}");

                        }

                        // Ensure the table exists
                        $table_exists_query = "SHOW TABLES LIKE '$table_name'";
                        $table_exists_result = $conn->query($table_exists_query);
                        if ($table_exists_result->num_rows > 0) {
                            // Query to fetch section schedules
                            $sql_schedule = "SELECT ts.room_sched_code, tsl.room_code, ts.time_start, ts.time_end, ts.day, ts.room_type 
                             FROM $table_name ts 
                             JOIN tbl_rsched tsl ON tsl.room_sched_code = ts.room_sched_code 
                             WHERE ts.room_code = ? AND ts.semester = ?";
                            $stmt_schedule = $conn->prepare($sql_schedule);
                            $stmt_schedule->bind_param('ss', $active_room_code, $selected_semester);
                            $stmt_schedule->execute();
                            $result_schedule = $stmt_schedule->get_result();

                            // Collect and store the schedule data
                            while ($row_schedule = $result_schedule->fetch_assoc()) {
                                $room_sched_code = $row_schedule['room_sched_code'];
                                $room_code = $row_schedule['room_code'] ?? 'N/A';
                                $time_start = $row_schedule['time_start'] ?? 'N/A';
                                $time_end = $row_schedule['time_end'] ?? 'N/A';
                                $day = shortenDay($row_schedule['day'] ?? 'N/A');
                                $room_type = $row_schedule['room_type'] ?? 'N/A'; // Lec or Lab
                    
                                // Skip if time_start or time_end is not in expected format
                                if ($time_start === 'N/A' || $time_end === 'N/A' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time_start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time_end)) {
                                    continue; // Skip to the next iteration
                                }

                                // Convert start and end times to timestamps for duration calculation
                                $start_timestamp = strtotime($time_start);
                                $end_timestamp = strtotime($time_end);

                                // Calculate the duration in hours
                                $duration = ($end_timestamp - $start_timestamp) / 3600; // Convert seconds to hours
                    
                                // Use room code to aggregate rooms and total classes
                                if (!isset($room_summary[$active_room_code])) {
                                    $room_summary[$active_room_code] = [
                                        'room_code' => $active_room_code,
                                        'lec_hours' => 0,
                                        'lab_hours' => 0,
                                        'class_counts' => ['lec' => 0, 'lab' => 0], // Initialize counts
                                    ];
                                }

                                // Increment class count based on class type
                                if ($room_type === 'Lecture') {
                                    $room_summary[$active_room_code]['lec_hours'] += $duration; // Ensure $duration is defined appropriately
                                    $room_summary[$active_room_code]['class_counts']['lec']++;
                                } elseif ($room_type === 'Computer Laboratory' || $room_type === 'Laboratory') {
                                    $room_summary[$active_room_code]['lab_hours'] += $duration; // Ensure $duration is defined appropriately
                                    $room_summary[$active_room_code]['class_counts']['lab']++;
                                }
                            }


                            $stmt_schedule->close();
                        }
                    }

                    $conn->close();

                    // Display the summarized schedule data
                    if (!empty($room_summary)) {
                        echo "<div class='schedule-summary'><p>Room Utilization</p></div>";
                        echo "<div class='schedule-summary'><p>" . htmlspecialchars($semester) . " AY " . htmlspecialchars($ay_name) . "</p></div>";
                        echo "<p class='schedule-summary'><strong>College/Department: </strong> " . htmlspecialchars($user_college_code) . "/" . htmlspecialchars($user_dept_code) . "</p>";
                        echo "<table class='schedule-table' data-semester='" . htmlspecialchars($semester) . "' data-ay='" . htmlspecialchars($ay_name) . "' data-college=' " . htmlspecialchars($user_college_code) . "' data-dept='" . htmlspecialchars($user_dept_code) . "'>";

                        // Table Header
                        echo "<tr class='schedule-table-header'>
                <th>Lecture Room No.</th>
                <th>No. of hours utilized per week</th>
                <th>Laboratory Room No.</th>
                <th>No. of hours utilized per week</th>
              </tr>";

                        // Initialize total variables
                        $total_lec_hours = 0;
                        $total_lab_hours = 0;

                        // Prepare to collect room hours separately
                        $lec_rooms = [];
                        $lab_rooms = [];

                        foreach ($room_summary as $room_code => $schedule) {
                            $lec_hours = $schedule['lec_hours'];
                            $lab_hours = $schedule['lab_hours'];

                            // Store lecture room details
                            if ($lec_hours > 0) {
                                $lec_rooms[] = [
                                    'room_code' => htmlspecialchars($room_code),
                                    'hours' => htmlspecialchars($lec_hours)
                                ];
                                $total_lec_hours += $lec_hours; // Accumulate total lecture hours
                            }

                            // Store laboratory room details
                            if ($lab_hours > 0) {
                                $lab_rooms[] = [
                                    'room_code' => htmlspecialchars($room_code),
                                    'hours' => htmlspecialchars($lab_hours)
                                ];
                                $total_lab_hours += $lab_hours; // Accumulate total lab hours
                            }
                        }

                        // Determine the maximum number of rooms for proper row alignment
                        $max_rows = max(count($lec_rooms), count($lab_rooms));

                        // Display each room's details in separate columns
                        for ($i = 0; $i < $max_rows; $i++) {
                            echo "<tr>";
                            // Display lecture room details
                            if (isset($lec_rooms[$i])) {
                                echo "<td>" . $lec_rooms[$i]['room_code'] . "</td>";
                                echo "<td>" . $lec_rooms[$i]['hours'] . "</td>";
                            } else {
                                echo "<td></td><td></td>"; // Empty cells if no room is available
                            }

                            // Display laboratory room details
                            if (isset($lab_rooms[$i])) {
                                echo "<td>" . $lab_rooms[$i]['room_code'] . "</td>";
                                echo "<td>" . $lab_rooms[$i]['hours'] . "</td>";
                            } else {
                                echo "<td></td><td></td>"; // Empty cells if no room is available
                            }
                            echo "</tr>";
                        }

                        // Total Hours Row
                        echo "<tr><td>Total No. of lec hours</td><td>" . htmlspecialchars($total_lec_hours) . "</td>
              <td>Total No. of lab hours</td><td>" . htmlspecialchars($total_lab_hours) . "</td></tr>";

                        echo "</table>";
                        echo "<style>
                @media print {
                    .schedule-table { page-break-before: always; }
                }
            </style>";
                    } else {
                        // Message when there are no schedules
                        echo "<div class='no-schedules-message'>No schedules found.</div>";
                    }

                    ?>
                </div>



            </div>
            <div class="row">
                <div class="col-md-12" style="text-align: right;">
                    <button class="btn" id="SchedulePDF"
                        style="color: white; background-color: #FD7238; margin: 20px 0; color: white;">
                        PDF
                    </button>
                    <button class="btn" onclick="fnExportToExcel('xlsx')"
                        style="color: white; background-color: #FD7238; margin: 20px 0; color: white;">
                        Excel
                    </button>
                </div>

            </div>




            <script>
                function fnExportToExcel() {
                    var tableElement = document.querySelector('.schedule-table');

                    if (!tableElement) {
                        console.error("Table not found.");
                        return;
                    }

                    // Fetch semester, AY, college code, and department code
                    var semester = tableElement.dataset.semester || "Unknown_Semester";
                    var ay_name = tableElement.dataset.ay || "Unknown_AY";
                    var user_college_code = tableElement.dataset.college || "Unknown_College";
                    var user_dept_code = tableElement.dataset.dept || "Unknown_Dept";

                    // Default file name format: "Room_Utilization_Summary_Semester_AY.xlsx"
                    var defaultFileName = `Room_Utilization_Summary_${semester}_AY_${ay_name}`;

                    // Prompt the user for the file name (without the extension)
                    var baseFileName = prompt("Enter the file name:", defaultFileName);

                    // If the user cancels or enters an empty name, use the formatted default name
                    if (!baseFileName || baseFileName.trim() === "") {
                        baseFileName = defaultFileName;
                    }

                    // Add .xlsx extension to the file name
                    var sheetFileName = baseFileName + ".xlsx";

                    // Use college code and department code for sheet name
                    var sheetName = (user_college_code && user_dept_code) ? `${user_college_code}_${user_dept_code}` : "Sheet1";

                    // Prepare the header data for Excel (before the table)
                    var header_data = [
                        ["Room Utilization"], // Custom header row
                        [`${semester} AY ${ay_name}`], // Semester and AY
                        [`College/Department: ${user_college_code}/${user_dept_code}`], // College and Dept
                        ['']
                    ];

                    // Convert the table to a worksheet
                    var table_ws = XLSX.utils.table_to_sheet(tableElement, { raw: true });

                    // Combine the header data and the table data
                    var combined_data = header_data.concat(XLSX.utils.sheet_to_json(table_ws, { header: 1 }));

                    // Create the worksheet from the combined data (header + table data)
                    var ws = XLSX.utils.aoa_to_sheet(combined_data);

                    // Create a new workbook
                    var wb = XLSX.utils.book_new();

                    // Append the worksheet to the workbook with the dynamically created sheet name
                    XLSX.utils.book_append_sheet(wb, ws, sheetName);

                    // Write the workbook to a file with the user-specified file name (with .xlsx extension)
                    XLSX.writeFile(wb, sheetFileName);
                }


                document.getElementById('SchedulePDF').addEventListener('click', function () {
                    // Specify the correct ID of the element that contains the schedule content
                    const element = document.getElementById('scheduleContent'); // Change this to your actual ID

                    if (!element) {
                        console.error('Element with the specified ID not found');
                        return;
                    }

                    const customTextDiv = document.createElement('div');

                    // Prepend the custom text to the scheduleContent
                    element.prepend(customTextDiv);

                    // Generate PDF as a Blob
                    html2pdf()
                        .from(element)
                        .set({
                            margin: [0.5, 0.5, 0.5, 0.5],
                            html2canvas: { scale: 3 },
                            jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
                        })
                        .outputPdf('blob')
                        .then(function (blob) {
                            const pdfUrl = URL.createObjectURL(blob);
                            window.open(pdfUrl);

                            // Create a download link for the PDF
                            const link = document.createElement('a');
                            link.href = pdfUrl;
                            link.download = 'summary_schedule.pdf';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);

                            // Clean up by removing the custom text div after PDF is generated
                            element.removeChild(customTextDiv);
                        })
                        .catch(function (error) {
                            console.error('Error generating PDF:', error);
                        });
                });


            </script>

    </body>

</html>