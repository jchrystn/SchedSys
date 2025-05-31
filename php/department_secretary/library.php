<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    switch ($action) {
        case 'curriculum':
            header('Location: curriculum.php');
            break;
        case 'schedule':
            header('Location: library/lib_section.php');
            break;
        default:
            // Handle unexpected actions
            echo 'Invalid action.';
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchedSYS</title>
    <link rel="icon" type="image/png" href="../../images/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/department_secretary/library.css">
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-sm bg-light">
        <a class="navbar-brand" href="#">
            <img src="../../images/orig-logo.png" alt="logo" style="width: 70px;">
            <span class="schedsys">schedSYS</span>
        </a>
        <div class="ml-auto d-flex align-items-center navbar-icons">
            <a href="dept_sec.php"><i class="fa-solid fa-house"></i></a>
            <i class="fa-solid fa-bell mx-3"></i>
            <i class="fa-solid fa-bars"></i>
        </div>
    </nav>

    <h1>LIBRARY</h1>

    <div class="library-container">
        <div class="data-section">
            <h2>Data</h2>
            <form method="POST" action="">
                <div class="data-items">
                    <button type="submit" name="action" value="curriculum" class="data-item">
                    <i class="fa-solid fa-list-check"></i>
                    <h4>CURRICULUM</h4>
                    </button>

                    <button type="submit" name="action" value="schedule" class="data-item">
                    <i class="fa-solid fa-calendar-days"></i>    
                    <h4>SCHEDULE</h4>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
