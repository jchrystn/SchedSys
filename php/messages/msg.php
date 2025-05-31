<?php

// Set the user type and navigation based on session
if($_SESSION['user_type'] == 'Admin'){
    $msgUser = ["Admin", "Department Secretary"];
    $nav_type = 'admin_navbar';
    $user_type_folder = 'admin';
}else if($_SESSION['user_type'] == 'Department Chairperson'){
    $msgUser = ["Department Chairperson", "Department Secretary", "Professor"];
    $nav_type = 'department_chair_navbar';
    $user_type_folder = 'department_chair';
}else if($_SESSION['user_type'] == 'Department Secretary'){
    $msgUser = ["Department Chairperson", "Department Secretary", "Professor"];
    $nav_type = 'navbar_deptsec';
    $user_type_folder = 'department_secretary';
}else if($_SESSION['user_type'] == 'Professor'){
    $msgUser = ["Department Secretary", "Professor"];
    $nav_type = 'professor_navbar';
    $user_type_folder = 'professor';
}else{
    $msgUser = [];
}

$count_msg_new = 0;
?>

<?php

$outgoing_id = $_SESSION['cvsu_email'];
$msgUserStr = "'" . implode("', '", $msgUser) . "'";

// Get the message user details and last message
$sql_msg = "SELECT tpa.*, tm.*
            FROM tbl_prof_acc AS tpa
            LEFT JOIN tbl_messages tm ON (tm.sender_email = tpa.cvsu_email)
            WHERE tm.sender_email != '$outgoing_id'
            AND tpa.user_type IN ($msgUserStr)
            AND tm.timestamp = (
                SELECT MAX(tm2.timestamp)
                FROM tbl_messages tm2
                WHERE tm2.receiver_email = tm.receiver_email
            )
            ORDER BY tm.timestamp DESC";

$query_msg = mysqli_query($conn, $sql_msg);

// Error handling
if (!$query_msg) {
    die('Error: ' . mysqli_error($conn));
}

// var_dump(mysqli_fetch_assoc($query_msg)).die;
$output = "";
if(mysqli_num_rows($query_msg) == 0){
    $output .= "No users are available to chat";
}elseif(mysqli_num_rows($query_msg) > 0){
    while ($row = mysqli_fetch_assoc($query_msg)) {
        $userId = $row['cvsu_email'];

        // var_dump($userId).die;
        // Fetch last message for the current user
        $sql_msg2 = "SELECT * FROM tbl_messages 
             WHERE (sender_email = '$userId' OR receiver_email = '$userId') 
             AND (receiver_email = '$outgoing_id' OR sender_email = '$outgoing_id') 
             ORDER BY id DESC LIMIT 1";

        $query_msg2 = mysqli_query($conn, $sql_msg2);

        // Check if query execution was successful
        if (!$query_msg2) {
            // Display the error
            die("Error in SQL Query: " . mysqli_error($conn));
        }

        // Fetch the result if the query was successful
        $row2 = mysqli_fetch_assoc($query_msg2);
        
        if (isset($row2) && isset($row2['timestamp'])) {
            $date = new DateTime($row2['timestamp']);
            $msgDate = $date->format('h:i A');
        } else {
            $msgDate = '';
        }
        
        if (isset($row2['status']) == "new") {
            if($row2['receiver_email'] != $outgoing_id){
                $name = '<b>'.$row['first_name'] . " " . $row['last_name'].'</b> <span class="color-indicator"></span> ';
                $count_msg_new++;
            }else {
                $name = $row['first_name'] . " " . $row['last_name'];
            }
        } else {
            $name = $row['first_name'] . " " . $row['last_name'];
        }

        // Set message details
        $result = (mysqli_num_rows($query_msg2) > 0) ? $row2['message'] : "No message available";
        $msg = (strlen($result) > 28) ? substr($result, 0, 28) . '...' : $result;
        $you = (isset($row2['outgoing_msg_id']) && $outgoing_id == $row2['outgoing_msg_id']) ? "You: " : "";

        $offline = ($row['status'] == "Offline now") ? "offline" : "";
        $hid_me = ($outgoing_id == $userId) ? "hide" : "";

        // Build output
        $output .= '<li class="person" data-chat="person1">
                      <a href="chat.php?user_id=' . $userId . '">
                        <img src="../../images/users.jpg" alt="" />
                        <span class="name">' . $name . '</span>
                        <span class="time">' . $msgDate . '</span>
                        <span class="preview">' . $you . $msg . '</span>
                      </a>
                    </li>';
    }
}

