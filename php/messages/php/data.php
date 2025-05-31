<?php
// Prepare an array to hold user details
$user_details = [];

// Fetch user details only once for each unique user
while ($row = mysqli_fetch_assoc($query)) {
    $userId = $row['cvsu_email'];

    // Fetch the last message for the current user
    $sql2 = "SELECT * FROM tbl_messages WHERE (sender_email = '$userId' 
            OR sender_email = '$userId') AND (receiver_email = '$outgoing_id' 
            OR receiver_email = '$outgoing_id') ORDER BY id DESC LIMIT 1";

    $query2 = mysqli_query($conn, $sql2);
    $row2 = mysqli_fetch_assoc($query2);

    // Set message details
    $result = (mysqli_num_rows($query2) > 0) ? $row2['message'] : "No message available";
    $msg = (strlen($result) > 28) ? substr($result, 0, 28) . '...' : $result;
    $you = (isset($row2['outgoing_msg_id']) && $outgoing_id == $row2['outgoing_msg_id']) ? "You: " : "";

    // Get user info from cached user details
    $row_name = $user_details[$userId];
    $offline = ($row['status'] == "Offline now") ? "offline" : "";
    $hid_me = ($outgoing_id == $userId) ? "hide" : "";

    // Build output
    $output .= '<a href="chat.php?user_id=' . $userId . '">
                <div class="content">
                <img src="php/images/' . $row_name['img'] . '" alt="">
                <div class="details">
                    <span>' . $row_name['first_name'] . " " . $row_name['last_name'] . '</span>
                    <p>' . $you . $message . '</p>
                </div>
                </div>
                <div class="status-dot ' . $offline . '"><i class="fas fa-circle"></i></div>
            </a>';
}

?>