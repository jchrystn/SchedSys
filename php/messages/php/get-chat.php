<?php 
    session_start();


    if (isset($_SESSION['cvsu_email'])) {
        include_once "../../config.php";

        
    $semester = mysqli_real_escape_string($conn, $_SESSION['semester']);
    $ay_code = mysqli_real_escape_string($conn, $_SESSION['ay_code']);
    $college_code = mysqli_real_escape_string($conn, $_SESSION['college_code']);

    // Fetch the latest active academic year and semester if not set
    if (empty($ay_code) || empty($semester)) {
        $fetch_info = "SELECT ay_code, semester FROM tbl_ay WHERE college_code = '$college_code' AND active = '1' LIMIT 1";
        $result_ay = $conn->query($fetch_info);

        if ($result_ay->num_rows > 0) {
            $row = $result_ay->fetch_assoc();
            $ay_code = $row['ay_code'];
            $semester = $row['semester'];

            $_SESSION['ay_code'] = $ay_code;
            $_SESSION['semester'] = $semester;
        }
    }

 
        
        // Get the current user's email (outgoing_id) from the session
        $outgoing_id = $_SESSION['cvsu_email'];
        
        // Sanitize incoming data to prevent SQL injection
        $incoming_id = mysqli_real_escape_string($conn, $_POST['incoming_id']);
        
        // Initialize the output variable
        $output = "";
    
        // Retrieve the user type of the outgoing user
        $outgoing_type_user = "SELECT * FROM tbl_prof_acc WHERE cvsu_email = '$outgoing_id'";
        $otu = mysqli_query($conn, $outgoing_type_user);
    
        if ($otu === false) {
            die("Error fetching outgoing user type: " . mysqli_error($conn));
        }
    
//    $ay_code - 
        // Construct SQL query to fetch messages between the sender and receiver
        $sql = "SELECT DISTINCT tbl_messages.*, tpa.* 
                FROM tbl_messages 
                LEFT JOIN (
                    SELECT * FROM tbl_prof_acc
                    WHERE ay_code = '$ay_code' AND semester = '$semester'
                ) AS tpa ON tpa.cvsu_email = tbl_messages.receiver_email
                WHERE (
                        (receiver_email = '$outgoing_id' AND sender_email = '$incoming_id') 
                        OR 
                        (receiver_email = '$incoming_id' AND sender_email = '$outgoing_id')
                    ) 
                ORDER BY tbl_messages.id";

      
        // Execute the query
        $query = mysqli_query($conn, $sql);
    
        // Check for query execution errors
        if ($query === false) {
            die("Error in query execution: " . mysqli_error($conn));
        }
        if(mysqli_num_rows($query) > 0){
            while($row = mysqli_fetch_assoc($query)){
                if ($row['message'] == NULL) {
                    // Generate the downloadable link and include an attachment icon
                    $msg = '<a href="../messages/uploads/' . basename($row['file_url']) . '" download>
                                <img src="php/images/attachment-icon.png" alt="Attachment" style="width: 16px; height: 16px; vertical-align: middle;"/> ' . 
                                htmlspecialchars(basename($row['file_url'])) . 
                            '</a>';
                } else {
                    // Preserve line breaks and apply html special chars for security
                    $msg = nl2br(htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8')); 
                }
                if($row['receiver_email'] === $incoming_id){
                    $output .= '<div class="bubble me">
                                <div class="details">
                                    <p style="white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; max-width: 75%; display: inline-block;">'. $msg .'</p>
                                </div>
                                </div>';
                }else{
                    $output .= '<div class="bubble you">
                                <div class="details">
                                    <p style="white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; max-width: 75%; display: inline-block;">'. $msg .'</p>
                                </div>
                                </div>';
                }
            }
        }else{
            $output .= '<div class="text">No messages are available. Once you send message they will appear here.</div>';
        }
        echo $output;
    }else{
        header("location: ../login.php");
    }
?>

