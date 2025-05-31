<?php
session_start();
include("../config.php");

if (!isset($_SESSION['user'])) {

    header("Location: ../login/login.php");

    exit();
}


$user_type = htmlspecialchars($_SESSION['user_type'] ?? '');
$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';


$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}


$fetch_info_query = "SELECT reg_adviser,college_code FROM tbl_prof_acc WHERE cvsu_email = '$current_user_email'";
$result_reg = $conn->query($fetch_info_query);

if ($result_reg->num_rows > 0) {
    $row = $result_reg->fetch_assoc();
    $not_reg_adviser = $row['reg_adviser'];
    $user_college_code = $row['college_code'];

    if ($not_reg_adviser == 1) {
        $current_user_type = "Registration Adviser";
    } else {
        $current_user_type = $user_type;
    }
}


// $user_college_code = $_SESSION['college_code'];

$sql = "SELECT college_name FROM tbl_college WHERE college_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_college_code);
$stmt->execute();
$stmt->bind_result($college_name);
$stmt->fetch();
$stmt->close();



// Fetch departments based on the user's college_code
$sql = "SELECT dept_code, dept_name FROM tbl_department WHERE college_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $admin_college_code);
$stmt->execute();
$result = $stmt->get_result();

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
    $_SESSION['semester'] = $semester; // Update session value to reflect the new semester selected by the user
} elseif (isset($_SESSION['semester'])) {
    $semester = $_SESSION['semester'];
} else {
    // Default fallback if session value is not set
    $semester = '1st Semester'; // or any other default value
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Prepare and execute the query
    $stmt = $conn->prepare("SELECT dept_name FROM tbl_department WHERE dept_code = ?");
    $stmt->bind_param("s", $action);
    $stmt->execute();
    $stmt->bind_result($dept_name);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    if ($dept_name) {
        // Start a session and store the department name
        $_SESSION['dept_name'] = $dept_name;
        $_SESSION['dept_code'] = $action; // Corrected here
        header('Location: data_schedule_professor.php');
        exit();
    } else {
        echo 'Department not found.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <!-- <link rel="stylesheet" href="../../css/department_secretary/navbar.css"> -->
    <link rel="stylesheet" href="../../css/student/my_schedule.css">
    <link rel="stylesheet" href="/SchedSys3/fontawesome-free-6.6.0-web/css/all.min.css">
</head>

<body>

<?php 
if ($_SESSION['user_type'] == 'Department Chairperson' && $admin_college_code == $user_college_code): ?>
    <?php 
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
    include($IPATH . "professor_navbar.php"); 
    ?>
<?php else: ?>
    <?php 
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/viewschedules/";
    include($IPATH . "professor_navbar.php"); 
    ?>
<?php endif; ?>

    <div class="title-container">
        <br>
        <p><?php echo $college_name ?></p>
        <p>(<?php echo $admin_college_code ?>)</p>
    </div>



   <div class="container">
   <div class="cardBox">
    <?php
    // Array of different icons
    $icons = [
        'fa-solid fa-building',
        'fa-solid fa-school',
        'fa-solid fa-university',
        'fa-regular fa-building',
    ];

    // Counter for icons
    $iconIndex = 0;

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $dept_code = $row['dept_code'];
            $dept_name = $row['dept_name'];

            // Select an icon based on the index, cycling back to the beginning if necessary
            $iconClass = $icons[$iconIndex % count($icons)];
            $iconIndex++; // Increment icon index for the next iteration

            echo '<form method="POST" action="">';
            echo '<input type="hidden" name="action" value="' . htmlspecialchars($dept_code) . '">';
            echo '<button type="submit" class="button-card" id="dept_card" >';
            echo '    <div class="card">';
            echo '        <div>';
            echo '            <div class="numbers">' . htmlspecialchars($dept_code) . '</div>';
            echo '            <div class="cardName">' . htmlspecialchars($dept_name) . '</div>';
            echo '        </div>';
            echo '        <div class="iconBx">';
            echo '            <i class="' . htmlspecialchars($iconClass) . '"></i>'; // Use the selected icon class
            echo '        </div>';
            echo '    </div>';
            echo '</button>';
            echo '</form>';
        }
    } else {
        echo "No departments found for your college.";
    }
    ?>
</div>

</div>
<script>
    document.addEventListener("DOMContentLoaded", () => {
    const cards = document.querySelectorAll(".card");
    const container = document.querySelector(".cardBox");

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("active");
                } else {
                    entry.target.classList.remove("active");
                }
            });
        },
        {
            root: container, // The container for scrolling
            threshold: 0.8, // Card must be 80% visible to be "active"
        }
    );

    cards.forEach((card) => observer.observe(card));
});

</script>



</body>

</html>