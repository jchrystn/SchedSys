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

// Query to fetch the college name based on the college code
$sql = "SELECT college_name FROM tbl_college WHERE college_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_college_code); // 's' is for string parameter
$stmt->execute();
$result_college = $stmt->get_result();

// Fetch the college name if exists
if ($result_college->num_rows > 0) {
    $row_college = $result_college->fetch_assoc();
    $user_college_name = $row_college['college_name'];
} else {
    $user_college_name = "College Not Found"; // Fallback if the college is not found
}



$sql_dept = "SELECT dept_name FROM tbl_department WHERE dept_code = ?";
$stmt_dept = $conn->prepare($sql_dept);
$stmt_dept->bind_param("s", $user_dept_code);
$stmt_dept->execute();
$result_dept = $stmt_dept->get_result();

// Fetch the department name
if ($result_dept->num_rows > 0) {
    $row_dept = $result_dept->fetch_assoc();
    $user_dept_name = $row_dept['dept_name'];
} else {
    $user_dept_name = "Department Not Found";
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


    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.4/xlsx.full.min.js"></script>
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
                <a class="nav-link" id="professor-tab" href="../report/room_summary.php" aria-controls="professor"
                    aria-selected="false">Classroom Summary</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="professor-tab" href="../report/prof_summary.php" aria-controls="professor"
                    aria-selected="false">Instructor Summary</a>
            </li>
            <?php if ($user_type =='Department Secretary'): ?>
            <li class="nav-item">
                <a class="nav-link" id="vacant-room-tab" href="/SchedSys3/php/viewschedules/data_schedule_vacant.php" aria-controls="vacant-room" aria-selected="false">Vacant Room</a>
            </li>
            <?php endif; ?>
        </ul>

            <div class="search-bar-container">
                <form method="POST" action="prof_summary.php" class="row">
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
                    // Query to get professor details and their schedules
                    $table_name = "tbl_psched_" . preg_replace("/[^a-zA-Z0-9_]/", "_", "{$user_dept_code}_{$ay_code}");

                    $sql_faculty = "
                    SELECT 
                        p.prof_code,
                        p.prof_name,
                        p.prof_type,
                        p.academic_rank,
                        ps.course_code,
                        c.course_name
                    FROM 
                        tbl_prof p
                    JOIN 
                        $table_name ps ON ps.prof_code = p.prof_code
                    JOIN
                        tbl_course c ON ps.course_code = c.course_code
                    WHERE 
                        ps.dept_code = ? 
                        AND ps.semester = ? 
                        AND ps.ay_code = ?
                    ORDER BY 
                        (p.employ_status != 0) DESC, -- Prioritize those with employ_status not 0
                        p.prof_name ASC, 
                        ps.course_code ASC";


                    $stmt_faculty = $conn->prepare($sql_faculty);
                    $stmt_faculty->bind_param('sss', $user_dept_code, $selected_semester, $ay_code);
                    $stmt_faculty->execute();
                    $result_faculty = $stmt_faculty->get_result();

                    // Initialize an array to hold faculty data
                    $faculty_summary = [];

                    // Store professor data and their subject details
                    while ($row = $result_faculty->fetch_assoc()) {
                        $prof_code = $row['prof_code'];
                        $course_code = $row['course_code'];


                        // Initialize faculty data if not already set
                        if (!isset($faculty_summary[$prof_code])) {
                            $faculty_summary[$prof_code] = [
                                'prof_code' => $row['prof_code'],
                                'prof_name' => $row['prof_name'],
                                'prof_type' => $row['prof_type'],
                                'academic_rank' => $row['academic_rank'],
                                'subjects' => []
                            ];
                        }

                        // Add the subject only if it hasn't been added yet for this professor
                        if (!isset($faculty_summary[$prof_code]['subjects'][$course_code])) {
                            $faculty_summary[$prof_code]['subjects'][$course_code] = [
                                'course_code' => $course_code,
                                'course_name' => $row['course_name']
                            ];
                        }
                    }

                    $stmt_faculty->close();
                    $conn->close();

                    // Display the summarized schedule data
                    if (!empty($faculty_summary)) {
                        echo "<div class='schedule-summary' data-college='" . htmlspecialchars($user_college_name, ENT_QUOTES) . "'>
                        <p>" . htmlspecialchars($user_college_name) . "</p>
                      </div>";
                        echo "<div class='schedule-summary'><p>Summary of " . htmlspecialchars($user_dept_name) . " Faculty Teaching Load</p></div>";
                        echo "<div class='schedule-summary'><p>" . htmlspecialchars($selected_semester) . " AY " . htmlspecialchars($ay_name) . "</p></div>";
                        echo "<table class='schedule-table' data-semester='" . htmlspecialchars($selected_semester) . "' data-ay='" . htmlspecialchars($ay_name) . "' data-dept='" . htmlspecialchars($user_dept_name) . "'>";


                        // Table Header
                        echo "<tr class='schedule-table-header'>
            <th>Name of Faculty</th>
            <th>Nature of Appointment</th>
            <th>Academic Rank</th>
            <th>Subject Code</th>
            <th>Subject Title</th>
        </tr>";

                        // Loop through each professor and display their details and subjects
                        foreach ($faculty_summary as $prof_code => $faculty_data) {
                            $rowspan = count($faculty_data['subjects']); // To span multiple rows for subjects
                            echo "<tr>";
                            if ($faculty_data['prof_name']) {
                                echo "<td rowspan='$rowspan'>" . htmlspecialchars($faculty_data['prof_name']) . "</td>";
                            } else {
                                echo "<td rowspan='$rowspan'>" . htmlspecialchars($faculty_data['prof_code']) . "</td>";
                            }
                            echo "<td rowspan='$rowspan'>" . htmlspecialchars($faculty_data['prof_type']) . "</td>";
                            echo "<td rowspan='$rowspan'>" . htmlspecialchars($faculty_data['academic_rank']) . "</td>";

                            // Display the first subject taught by the professor
                            $first_subject = reset($faculty_data['subjects']);
                            echo "<td>" . htmlspecialchars($first_subject['course_code']) . "</td>";
                            echo "<td>" . htmlspecialchars($first_subject['course_name']) . "</td>";
                            echo "</tr>";

                            // Display remaining subjects in separate rows
                            foreach (array_slice($faculty_data['subjects'], 1) as $subject) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($subject['course_code']) . "</td>";
                                echo "<td>" . htmlspecialchars($subject['course_name']) . "</td>";
                                echo "</tr>";
                            }
                        }

                        echo "</table>";
                    } else {
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
                    var table = document.querySelector(".schedule-table");

                    if (!table) {
                        console.error("Table not found.");
                        return;
                    }

                    // Fetch semester, AY, and department name
                    var semester = table.dataset.semester || "Unknown_Semester";
                    var ay_name = table.dataset.ay || "Unknown_AY";
                    var user_dept_name = table.dataset.dept || "Unknown_Department";

                    // Fetch the dynamic college name from .schedule-summary
                    var collegeSummary = document.querySelector(".schedule-summary");
                    var college_name = collegeSummary ? collegeSummary.dataset.college : "Unknown_College";

                    // Default file name format: "Faculty_Teaching_Load_Summary_Semester_AY.xlsx"
                    var defaultFileName = `Faculty_Teaching_Load_Summary_${semester}_AY_${ay_name}`;

                    // Prompt the user for the file name, defaulting to formatted name
                    var baseFileName = prompt("Enter the file name:", defaultFileName);

                    // If the user cancels or leaves it empty, use the formatted default name
                    if (!baseFileName || baseFileName.trim() === "") {
                        baseFileName = defaultFileName;
                    }

                    // Add .xlsx extension to the file name
                    var sheetFileName = baseFileName + ".xlsx";

                    // The sheet name will always be "Faculty"
                    var sheetName = "Faculty";

                    // Prepare the header data for Excel (dynamic based on department name and term)
                    var header_data = [
                        [college_name], // Dynamic college name
                        [`Summary of ${user_dept_name} Faculty Teaching Load`],
                        [`${semester} AY ${ay_name}`],
                        [""]
                    ];

                    // Convert the table to a worksheet
                    var table_ws = XLSX.utils.table_to_sheet(table, { raw: true });

                    // Combine the header data and the table data
                    var combined_data = header_data.concat(XLSX.utils.sheet_to_json(table_ws, { header: 1 }));

                    // Create the worksheet from the combined data (header + table data)
                    var ws = XLSX.utils.aoa_to_sheet(combined_data);

                    // Create a new workbook
                    var wb = XLSX.utils.book_new();

                    // Append the worksheet to the workbook with the fixed sheet name "Faculty"
                    XLSX.utils.book_append_sheet(wb, ws, sheetName);

                    // Write the workbook to a file with the user-specified file name (with .xlsx extension)
                    XLSX.writeFile(wb, sheetFileName);
                }




                document.getElementById('SchedulePDF').addEventListener('click', function () {
                    // Specify the correct ID of the element that contains the schedule content
                    const element = document.getElementById('scheduleContent');

                    if (!element) {
                        console.error('Element with the specified ID not found');
                        return;
                    }

                    // Generate PDF with page breaks for long tables
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

                            // Download PDF
                            const link = document.createElement('a');
                            link.href = pdfUrl;
                            link.download = 'summary_schedule.pdf';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        })
                        .catch(function (error) {
                            console.error('Error generating PDF:', error);
                        });
                });



            </script>

    </body>

</html>