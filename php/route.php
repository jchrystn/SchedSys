<?php
include("config.php");
session_start();

// var_dump($_SESSION['user_type']).die;
// Check if an action is set in the query string

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    // session_start();
    $sql2 = mysqli_query($conn, "UPDATE users SET status = 'Offline now' WHERE id = {$_SESSION['user_id']}");
    session_destroy(); // Destroy the session
    echo '<script>alert("You have been logged out"); window.location.href="login/login.php";</script>';
    exit(); // Stop executing the script
    
}

// var_dump($_SESSION['user_type']).die;

if (isset($_SESSION['user_type'])) {
    $role = $_SESSION['user_type'];
    // var_dump($role).die;
    switch ($role) {
        case 'Admin':
            if (isset($_GET['dashboard']) && $_GET['dashboard'] == 'redirect') {
                header('Location: admin/admin.php');
            }else if(isset($_GET['messaging']) && $_GET['messaging'] == 'redirect'){
                header('Location: messages/users.php');
            }else if(isset($_GET['create_acc_stud']) && $_GET['create_acc_stud'] == 'redirect'){
                header('Location: admin/create_acc_stud.php');
            }else if(isset($_GET['create_acc_prof']) && $_GET['create_acc_prof'] == 'redirect'){
                header('Location: admin/create_acc_prof.php');
            }else if(isset($_GET['admin_department_input']) && $_GET['admin_department_input'] == 'redirect'){
                header('Location: admin/admin_department_input.php');
            }else if(isset($_GET['admin_section_input']) && $_GET['admin_section_input'] == 'redirect'){
                header('Location: admin/admin_section_input.php');
            }else if(isset($_GET['admin_program_input']) && $_GET['admin_program_input'] == 'redirect'){
                header('Location: admin/admin_program_input.php');
            }else if(isset($_GET['student_lists']) && $_GET['student_lists'] == 'redirect'){
                header('Location: admin/student_lists.php');
            }else if(isset($_GET['professor_lists']) && $_GET['professor_lists'] == 'redirect'){
                header('Location: admin/professor_lists.php');
            }
            exit();
        case 'Student':
            if (isset($_GET['dashboard']) && $_GET['dashboard'] == 'redirect') {
                header('Location: student/student.php');
            }
            exit();
        case 'Professor':
            if (isset($_GET['dashboard']) && $_GET['dashboard'] == 'redirect') {
                header('Location: professor/');
            }else if(isset($_GET['messaging']) && $_GET['messaging'] == 'redirect'){
                header('Location: messages/users.php');
            }
            exit();
        case 'Department Chairperson':
                if (isset($_GET['dashboard']) && $_GET['dashboard'] == 'redirect') {
                    header('Location: professor/');
                }else if(isset($_GET['messaging']) && $_GET['messaging'] == 'redirect'){
                    header('Location: messages/users.php');
                }
                exit();
        case 'Department Secretary':
            if (isset($_GET['dashboard']) && $_GET['dashboard'] == 'redirect') {
                header('Location: department_secretary/dept_sec.php');
            }else if(isset($_GET['messaging']) && $_GET['messaging'] == 'redirect'){
                header('Location: messages/users.php');
            }else if (isset($_GET['course_input']) && $_GET['course_input'] == 'redirect') {
                header('Location: department_secretary/input_forms/course_input.php');
            }else if (isset($_GET['prof_input']) && $_GET['prof_input'] == 'redirect') {
                header('Location: department_secretary/input_forms/prof_input.php');
            }else if (isset($_GET['classroom_input']) && $_GET['classroom_input'] == 'redirect') {
                header('Location: department_secretary/input_forms/classroom_input.php');
            }else if (isset($_GET['create_sched']) && $_GET['create_sched'] == 'redirect') {
                header('Location: department_secretary/create_sched/create_sched.php');
            }else if (isset($_GET['draft']) && $_GET['draft'] == 'redirect') {
                header('Location: department_secretary/draft.php');
            }
            exit();
        default:
            header('Location: default_page.php'); // Fallback for other roles
            exit();
    }
} else {
    // No role set, redirect to login or error page
    header('Location: login.php');
    exit();
}

// Normal content for index.php
?>
