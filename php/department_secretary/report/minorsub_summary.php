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
    $selected_semester = $active_semester; // or any other default value
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

    <link rel="stylesheet" href="../../../css/department_secretary/report/summary.css">
</head>

<body>

    <body>
        <?php $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/department_secretary/";
        include($IPATH . "navbar.php");
        ?>

        <h2 class="title">SCHEDULES</h2>
        
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
                <a class="nav-link active" id="professor-tab" href="../report/minorsub_summary.php" aria-controls="professor"
                    aria-selected="false">Minor Subject Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="professor-tab" href="../report/room_summary.php" aria-controls="professor"
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
                <form method="POST" action="minorsub_summary.php" class="row">
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
                    // Helper functions
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
                        if ($time === 'N/A' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                            return 'N/A';
                        }
                        return date("g:i A", strtotime($time));
                    }

                    // Prepare SQL to fetch Major courses
                    $sql_courses = "SELECT course_code, course_name FROM tbl_course WHERE course_type = 'Minor' AND dept_code = ? ";
                    $stmt_courses = $conn->prepare($sql_courses);
                    $stmt_courses->bind_param('s', $dept_code );
                    $stmt_courses->execute();
                    $result_courses = $stmt_courses->get_result();

                    $courses = [];
                    while ($row = $result_courses->fetch_assoc()) {
                        $courses[] = $row;
                    }
                    $stmt_courses->close();

                    $minor_schedule_summary = [];

                    // Group major courses
                    foreach ($courses as $course) {
                        $active_course_code = $course['course_code'];
                        // Define the sanitized table names for the section schedule
                        $table_name_secsched = "tbl_secsched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$dept_code}_{$selected_ay_code}");
                        // Check if the section schedule table exists
                        $table_exists_query = "SHOW TABLES LIKE '$table_name_secsched'";
                        $table_exists_result = $conn->query($table_exists_query);

                        if ($table_exists_result->num_rows > 0) {
                            // Get schedules from the section schedule table including professor names
                            $sql_schedule = "SELECT ts.section_sched_code, tsl.section_code, ts.room_code, ts.prof_code, tp.prof_name, ts.time_start, ts.time_end, ts.day, ts.class_type, tsl.program_code, c.course_name 
            FROM $table_name_secsched ts 
            INNER JOIN tbl_secschedlist tsl ON tsl.section_sched_code = ts.section_sched_code 
            INNER JOIN tbl_course AS c ON ts.course_code = c.course_code 
            INNER JOIN tbl_prof AS tp ON ts.prof_code = tp.prof_code  
            WHERE ts.course_code = ? AND ts.semester = ?";

                            $stmt_schedule = $conn->prepare($sql_schedule);
                            $stmt_schedule->bind_param('ss', $active_course_code, $selected_semester);
                            $stmt_schedule->execute();
                            $result_schedule = $stmt_schedule->get_result();

                            // Process schedule data
                            if ($result_schedule->num_rows > 0) {
                                while ($row_schedule = $result_schedule->fetch_assoc()) {
                                    $program_code = $row_schedule['program_code'];
                                    $section_sched_code = $row_schedule['section_code'];

                                    // Create a unique key for this entry to avoid duplicates
                                    $unique_key = $program_code . '-' . $active_course_code . '-' . $section_sched_code;

                                    // Initialize the entry
                                    $schedule_entry = [
                                        'course_code' => $active_course_code,
                                        'course_name' => $course['course_name'], // Use the fetched course name
                                        'section_code' => $section_sched_code,
                                        'rooms' => [$row_schedule['room_code'] => true],
                                        'professors' => [$row_schedule['prof_code'] => true],
                                        'professor_names' => [$row_schedule['prof_name'] => true], // Store professor names
                                        'schedules' => [shortenDay($row_schedule['day']) . ' ' . convertTo12HourFormat($row_schedule['time_start']) . '-' . convertTo12HourFormat($row_schedule['time_end'])],
                                        'class_types' => [$row_schedule['class_type'] => true],
                                    ];

                                    // Store the schedule entry in the summary using the unique key
                                    if (!isset($minor_schedule_summary[$program_code][$unique_key])) {
                                        // Store the entry only if it doesn't exist
                                        $minor_schedule_summary[$program_code][$unique_key] = $schedule_entry;
                                    } else {
                                        // If it already exists, we update it to include the new room, professor, and schedule details
                                        $existing_entry = &$minor_schedule_summary[$program_code][$unique_key];
                                        $existing_entry['rooms'][$row_schedule['room_code']] = true; // Merge room codes
                                        $existing_entry['professors'][$row_schedule['prof_code']] = true; // Merge professor codes
                                        $existing_entry['professor_names'][$row_schedule['prof_name']] = true; // Merge professor names
                                        $existing_entry['schedules'][] = shortenDay($row_schedule['day']) . ' ' . convertTo12HourFormat($row_schedule['time_start']) . '-' . convertTo12HourFormat($row_schedule['time_end']); // Add schedules
                                        $existing_entry['class_types'][$row_schedule['class_type']] = true; // Merge class types
                                    }
                                }
                            }

                            $stmt_schedule->close();
                        }
                    }
                    $conn->close();


                    // Function to display schedule table for a specific program
                    function displayScheduleTable($schedule_summary, $program_code, $semester, $ay_code)
                    {
                        if (!empty($schedule_summary)) {
                            echo "<div class='schedule-summary'><p>Schedule Summary for " . htmlspecialchars($program_code) . "</p></div>";
                            echo "<table class='schedule-table' 
                                data-program-code='" . htmlspecialchars($program_code) . "' 
                                data-semester='" . htmlspecialchars($semester) . "' 
                                data-ay='" . htmlspecialchars($ay_code) . "'>
                                <tr class='schedule-table-header'>";
                            // Table headers
                            $headers = ['COURSE CODE', 'COURSE TITLE', 'SECTION', 'LEC', 'LAB', 'ROOMS', 'INSTRUCTORS', 'SCHEDULES'];
                            foreach ($headers as $header) {
                                echo "<th>" . htmlspecialchars($header) . "</th>";
                            }
                            echo "</tr>";

                            // Array to track course occurrences and a unique key to track rendered rows
                            $courseCounts = [];
                            $renderedRows = [];

                            // First pass to count occurrences of each course
                            foreach ($schedule_summary as $schedule) {
                                $courseKey = $schedule['course_code'] . '|' . $schedule['course_name'];

                                // Increment count for the specific course key
                                if (!isset($courseCounts[$courseKey])) {
                                    $courseCounts[$courseKey] = 0;
                                }
                                $courseCounts[$courseKey]++;
                            }

                            // Second pass to output the table
                            foreach ($schedule_summary as $schedule) {
                                $courseKey = $schedule['course_code'] . '|' . $schedule['course_name'];

                                // Only display course code and name on the first occurrence
                                if (!isset($renderedRows[$courseKey]) && $courseCounts[$courseKey] > 0) {
                                    echo "<tr>
                    <td rowspan='" . $courseCounts[$courseKey] . "'>" . htmlspecialchars($schedule['course_code']) . "</td>
                    <td rowspan='" . $courseCounts[$courseKey] . "'>" . htmlspecialchars($schedule['course_name']) . "</td>
                    <td>" . htmlspecialchars($schedule['section_code'] ?? '') . "</td>
                    <td>" . (isset($schedule['class_types']['lec']) ? '✔' : '') . "</td>
                    <td>" . (isset($schedule['class_types']['lab']) ? '✔' : '') . "</td>
                    <td>" . htmlspecialchars(implode('/', array_keys($schedule['rooms'] ?? []))) . "</td>
                    <td>" . htmlspecialchars(implode('/', array_keys($schedule['professor_names'] ?? []))) . "</td>
                    <td>" . htmlspecialchars(implode('/', array_unique($schedule['schedules'] ?? []))) . "</td>
                </tr>";

                                    // Mark this course as rendered
                                    $renderedRows[$courseKey] = true;

                                    // Decrement the count for the course after rendering
                                    $courseCounts[$courseKey]--;
                                } else {
                                    // If the course is already displayed, just display the section row
                                    echo "<tr>
                    <td>" . htmlspecialchars($schedule['section_code'] ?? '') . "</td>
                    <td>" . (isset($schedule['class_types']['lec']) ? '✔' : '') . "</td>
                    <td>" . (isset($schedule['class_types']['lab']) ? '✔' : '') . "</td>
                    <td>" . htmlspecialchars(implode('/', array_keys($schedule['rooms'] ?? []))) . "</td>
                    <td>" . htmlspecialchars(implode('/', array_keys($schedule['professor_names'] ?? []))) . "</td>
                    <td>" . htmlspecialchars(implode('/', array_unique($schedule['schedules'] ?? []))) . "</td>
                </tr>";
                                }
                            }

                            echo "</table>";
                            echo "<style>
                @media print {
                    .schedule-table { page-break-before: always; }
                }
            </style>";
                        } else {
                            echo "<div class='no-schedules-message'>No schedule found for " . htmlspecialchars($program_code) . "</div>";
                        }
                    }
                    foreach ($minor_schedule_summary as $program_code => $schedule_summary) {
                        displayScheduleTable($schedule_summary, $program_code, $semester, $ay_code);
                    }
                    if (empty($minor_schedule_summary)) {
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




                <script>

                    function fnExportToExcel(fileExtension) {
                        var firstTable = document.querySelector('.schedule-table');

                        // Fetch semester and academic year details from the first table
                        var semester = firstTable ? firstTable.dataset.semester || "Unknown_Semester" : "Unknown_Semester";
                        var ay_name = firstTable ? firstTable.dataset.ay || "Unknown_AY" : "Unknown_AY";

                        // Format the file name with semester and AY
                        var defaultFileName = `Major_Subject_Summary_${semester}_AY_${ay_name}`;

                        // Prompt the user for the file name (without the extension)
                        var baseFileName = prompt("Enter the file name:", defaultFileName);

                        // If the user cancels or enters an empty name, use the formatted default name
                        if (!baseFileName || baseFileName.trim() === "") {
                            baseFileName = defaultFileName;
                        }

                        // Add the specified extension to the file name
                        var sheetFileName = baseFileName + "." + fileExtension;

                        const workbook = XLSX.utils.book_new(); // Create a new workbook

                        // Loop through each .schedule-table in the document
                        document.querySelectorAll('.schedule-table').forEach((table, index) => {
                            // Get the program code from the table's data attribute
                            const programCode = table.getAttribute('data-program-code') || `Program ${index + 1}`;

                            // Fetch the college name from .schedule-summary
                            var collegeSummary = document.querySelector(".schedule-summary");

                            // Prepare the header data for Excel (College, Program, etc.)
                            var header_data = [
                                [`Schedule Summary for ${programCode}`], // Program Code in Header
                                [""]
                            ];

                            // Convert the table to a worksheet
                            var table_ws = XLSX.utils.table_to_sheet(table, { raw: true });

                            // Combine the header data and the table data
                            var combined_data = header_data.concat(XLSX.utils.sheet_to_json(table_ws, { header: 1 }));

                            // Create the worksheet from the combined data (header + table data)
                            var ws = XLSX.utils.aoa_to_sheet(combined_data);

                            // Append the sheet to the workbook using the program code as the sheet name
                            XLSX.utils.book_append_sheet(workbook, ws, programCode);
                        });

                        // Export the workbook to an Excel file with the user-specified or default file name
                        XLSX.writeFile(workbook, sheetFileName);
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
                                html2canvas: {
                                    scale: 3,
                                    useCORS: true, // Handle cross-origin images if present
                                    scrollX: 0,
                                    scrollY: 0
                                },
                                jsPDF: {
                                    unit: 'in',
                                    format: 'a4',
                                    orientation: 'portrait'
                                }
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