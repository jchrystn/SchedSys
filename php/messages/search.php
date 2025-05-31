<?php
session_start();
include_once "../config.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define allowed recipient types based on the current user's user_type
$allowedTypes = [];
if ($_SESSION['user_type'] == 'Admin') { 
    $allowedTypes = ["Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
} else if ($_SESSION['user_type'] == 'Department Chairperson') {
    $allowedTypes = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
} else if ($_SESSION['user_type'] == 'Department Secretary') {
    $allowedTypes = ["Admin", "Department Chairperson", "Department Secretary", "Professor", "Registration Adviser","CCL Head"];
}else if ($_SESSION['user_type'] == 'CCL Head') {
    $allowedTypes = ["Admin", "Department Chairperson", "Department Secretary", "Professor", "Registration Adviser","CCL Head"];
}  else if ($_SESSION['user_type'] == 'Professor') {
    $allowedTypes = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
} else if ($_SESSION['user_type'] == 'Registration Adviser') {
    $allowedTypes = ["Admin", "Department Secretary", "Professor", "Registration Adviser", "Department Chairperson","CCL Head"];
}

// Prepare the SQL query with the allowed user types
$search = $_GET['query'];
$allowedTypesString = "'" . implode("','", $allowedTypes) . "'";
$currentEmail = $_SESSION['cvsu_email']; // Get the logged-in user's email

$sql = "
    SELECT first_name, last_name, cvsu_email, middle_initial, user_type 
    FROM tbl_prof_acc 
    WHERE (first_name LIKE '%$search%' 
           OR last_name LIKE '%$search%' 
           OR middle_initial LIKE '%$search%') 
          AND user_type IN ($allowedTypesString)
          AND cvsu_email != '$currentEmail'
    UNION
    SELECT first_name, last_name, cvsu_email, middle_initial, user_type 
    FROM tbl_admin 
    WHERE (first_name LIKE '%$search%' 
           OR last_name LIKE '%$search%' 
           OR middle_initial LIKE '%$search%')
          AND user_type IN ($allowedTypesString)
          AND cvsu_email != '$currentEmail'
    LIMIT 10";

$result = $conn->query($sql);

$names = [];
while ($row = $result->fetch_assoc()) {
    // Sanitize the name and email
    $name = htmlspecialchars($row['first_name']);
    $lname = htmlspecialchars($row['last_name']);
    $mname = htmlspecialchars($row['middle_initial']);
    $email = htmlspecialchars($row['cvsu_email']);
    $usertype = htmlspecialchars($row['user_type']);
    
    // Create a link for each name without nested anchors
    $link = "<a href='chat.php?user_id=" . urlencode($email) . "'>" . $name . " " . $mname . " " . $lname . " (" . $usertype . ")</a>";
    $names[] = $link; // Store the HTML link in the array
}

echo json_encode($names);
$conn->close();
?>
