<?php
session_start();
include_once "../../config.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$search = $_GET['query'];
$sql = "SELECT first_name, last_name, cvsu_email FROM tbl_prof_acc WHERE first_name LIKE '%$search%' LIMIT 10";
$result = $conn->query($sql);

$names = [];
while ($row = $result->fetch_assoc()) {
    // Sanitize the name and email
    $name = htmlspecialchars($row['first_name']);
    $lname = htmlspecialchars($row['last_name']);
    $email = htmlspecialchars($row['cvsu_email']);
    
    // Create a link for each name without nested anchors
    $link = "<a href='chat.php?user_id=" . urlencode($email) . "'>" . $lname . ", " . $name . "</a>";
    $names[] = $link; // Store the HTML link in the array
}

echo json_encode($names);
$conn->close();
?>
