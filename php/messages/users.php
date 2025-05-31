<?php
include("../config.php");
session_start();


if (!isset($_SESSION['cvsu_email'])) {
    header("location: ../login/login.php");
}

// Get the current user's first name from the session
$current_user = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : 'User';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'User';

$user_id = $_SESSION['cvsu_email'];

$current_user_email = isset($_SESSION['cvsu_email']) ? $_SESSION['cvsu_email'] : '';

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


$fetch_info_query_col = "SELECT college_code FROM tbl_admin WHERE user_type = 'Admin'";
$result_col = $conn->query($fetch_info_query_col);

if ($result_col->num_rows > 0) {
    $row_col = $result_col->fetch_assoc();
    $admin_college_code = $row_col['college_code'];
}

// Handle form submission for navigation
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $redirects = [
        'department' => 'admin_department_input.php',
        'section' => 'admin_section_input.php',
        'course' => 'admin_program_input.php',
        'create_student' => 'create_acc_stud.php',
        'create_professor' => 'create_acc_prof.php',
        'library' => 'library.php'
    ];

    if (array_key_exists($action, $redirects)) {
        header('Location: ' . $redirects[$action]);
        exit();
    } else {
        echo 'Invalid action.';
        exit();
    }
}


if ($_SESSION['user_type'] == 'Admin') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'admin_navbar';
    $user_type_folder = 'admin';
} else if ($_SESSION['user_type'] == 'Department Chairperson') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'professor_navbar';
    $user_type_folder = 'professor';
} else if ($_SESSION['user_type'] == 'Department Secretary') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'navbar';
    $user_type_folder = 'department_secretary';
} else if ($_SESSION['user_type'] == 'CCL Head') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'navbar';
    $user_type_folder = 'department_secretary';
}else if ($_SESSION['user_type'] == 'Professor') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'professor_navbar';
    $user_type_folder = 'professor';
} else if ($_SESSION['user_type'] == 'Registration Adviser') {
    $msgUser = ["Admin", "Department Secretary", "Department Chairperson", "Professor", "Registration Adviser","CCL Head"];
    $nav_type = 'professor_navbar';
    $user_type_folder = 'professor';
} else {
    $msgUser = [];
}

// var_dump($msgUser).die;
$count_msg_new = 0;
?>

<?php

$outgoing_id = $_SESSION['cvsu_email'];
$msgUserStr = "'" . implode("', '", $msgUser) . "'";


$sql = "
(
    SELECT 
            tpa.first_name, 
    tpa.last_name, 
    tpa.cvsu_email, 
    tpa.user_type,
    tpa.status_type,
    tpa.middle_initial,  
        ta.cvsu_email AS admin_email,
        MAX(CASE WHEN m.sender_email = '$outgoing_id' OR m.receiver_email = '$outgoing_id' THEN m.timestamp END) AS last_message_time,
        COUNT(m.id) AS message_count,
        ta.first_name AS admin_first_name,
        ta.last_name AS admin_last_name,
        ta.status_type AS admin_status
    FROM 
        tbl_prof_acc AS tpa
    LEFT JOIN 
        tbl_messages AS m ON (tpa.cvsu_email = m.sender_email OR tpa.cvsu_email = m.receiver_email)
    LEFT JOIN 
        tbl_admin AS ta ON ta.cvsu_email = tpa.cvsu_email  -- Join on cvsu_email
    WHERE 
        tpa.user_type IN ($msgUserStr) 
        AND tpa.cvsu_email != '$outgoing_id'
    GROUP BY 
        tpa.cvsu_email
)
UNION
(
    SELECT 
           ta.first_name, 
    ta.last_name, 
    ta.cvsu_email, 
    ta.user_type, 
    ta.status_type,
    ta.middle_initial,
        ta.cvsu_email AS admin_email,
        MAX(CASE WHEN m.sender_email = '$outgoing_id' OR m.receiver_email = '$outgoing_id' THEN m.timestamp END) AS last_message_time,
        COUNT(m.id) AS message_count,
        ta.first_name AS admin_first_name,
        ta.last_name AS admin_last_name,
        ta.status_type AS admin_status
    FROM 
        tbl_admin AS ta
    LEFT JOIN 
        tbl_messages AS m ON (ta.cvsu_email = m.sender_email OR ta.cvsu_email = m.receiver_email)
    WHERE 
        ta.cvsu_email != '$outgoing_id'
    GROUP BY 
        ta.cvsu_email
)
ORDER BY 
    message_count DESC, 
    last_message_time DESC
";



$query = mysqli_query($conn, $sql);
$output = "";
if (mysqli_num_rows($query) == 0) {
    $output .= "No users are available to chat";
} elseif (mysqli_num_rows($query) > 0) {
    // Initialize an array to store messages in order
    $messages = [];

    // Loop through all users
    while ($row = mysqli_fetch_assoc($query)) {
        $userId = $row['cvsu_email'];
        $status = $row['status_type']; // Get status from the row

        // Fetch the latest message for the current user based on timestamp
        $sql_msg2 = "SELECT * FROM tbl_messages 
            WHERE (sender_email = '$userId' OR receiver_email = '$userId') 
            AND (receiver_email = '$outgoing_id' OR sender_email = '$outgoing_id') 
            ORDER BY timestamp DESC LIMIT 1";  // Use ORDER BY timestamp DESC to get the most recent message

        $query_msg2 = mysqli_query($conn, $sql_msg2);

        // Check if query execution was successful
        if (!$query_msg2) {
            die("Error in SQL Query: " . mysqli_error($conn));
        }

        // Fetch the result if the query was successful
        $row2 = mysqli_fetch_assoc($query_msg2);

        // Get the full timestamp for sorting but only display the time
        if (isset($row2) && isset($row2['timestamp'])) {
            $date = new DateTime($row2['timestamp']);
            $msgDate = $date->format('M j, Y');   // Full timestamp (for sorting)
            $msgTime = $date->format('h:i A');  // Time only (for display)
        } else {
            $msgDate = ''; // Set empty if no timestamp is available
            $msgTime = ''; // Set empty if no timestamp is available
        }

        // Determine the user's full name
        $name = $row['first_name'] . " " . $row['middle_initial'] . " " . $row['last_name'];

        // Set the message text, or default to "No message available"
        $message = (mysqli_num_rows($query_msg2) > 0) ? $row2['message'] : "No message available";
        $msg = (strlen($message) > 28) ? substr($message, 0, 28) . '...' : $message;

        // If this message is from the current user, add "You: " to the start
        $you = (isset($row2['outgoing_msg_id']) && $outgoing_id == $row2['outgoing_msg_id']) ? "You: " : "";

        // Build the status indicator (Online/Offline)
        $statusIndicator = ($status == "Online") ? '<span class="color-indicator online"></span>' : '<span class="color-indicator offline"></span>';

        // Prepend the message to ensure the latest message is at the top, using both message and timestamp
        if ($msg != 'No message available') {
            array_unshift($messages, [
                'userId' => $userId,
                'name' => $name,
                'statusIndicator' => $statusIndicator,
                'msgDate' => $msgDate,  // Full timestamp for sorting
                'msgTime' => $msgTime,  // Time only for display
                'msg' => $you . $msg
            ]);
        }
    }

    // Sort messages by full timestamp to ensure the most recent message is at the top
    usort($messages, function ($a, $b) {
        // Compare timestamps by converting message time to DateTime objects
        $timestampA = new DateTime($a['msgDate']);
        $timestampB = new DateTime($b['msgDate']);
        return $timestampB <=> $timestampA;  // Sort in descending order (newest first)
    });

    // Build the output
    $output = '';
    foreach ($messages as $message) {
        $output .= '<li class="person" data-chat="person1">
                    <a href="chat.php?user_id=' . $message['userId'] . '">
                        <img src="../../images/users.jpg" alt="User profile image" />
                        <span class="name">' . $message['name'] . '</span>
                        ' . $message['statusIndicator'] . '
                        <span class="time">' . $message['msgTime'] . '</span>  <!-- Display only the time -->
                        <span class="preview">' . $message['msg'] . '</span>
                    </a>
                    </li>';
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

    <link rel="stylesheet" href="../new-admin/admin_sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"> -->
    <!-- <script src="http://localhost/SchedSys3/materialize/js/materialize.min.js"></script> -->
    <!-- <link rel="stylesheet" href="http://localhost/SchedSys3/materialize/css/materialize.min.css"> -->
     

    <style>
        #suggestions {
            display: none;
            /* Initially hidden */
            position: absolute;
            /* Position it absolutely */
            top: 80px;
            /* Adjust this value to position it lower */
            left: 50%;
            /* Center the left edge */
            transform: translateX(-50%);
            /* Move it left by 50% of its width to center */
            width: 90%;
            /* Adjust width as needed (you can change this percentage) */
            background-color: var(--white);
            /* Background color */
            border: 1px solid var(--light);
            /* Optional: Border for better visibility */
            z-index: 10;
            /* Ensure it appears above other elements */
            max-height: 200px;
            /* Optional: Max height */
            overflow-y: auto;
            /* Optional: Enable scroll if too many items */
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }

        .suggestion-item a {
            text-decoration: none;
            color: #333;
            display: block;
            width: 100%;
        }

        .suggestion-item a:hover {
            background-color: #f0f0f0;
        }

        /* Hide people list */
    </style>

    <style>
        @mixin font-bold {
            font-family: 'Source Sans Pro', sans-serif;
            font-weight: 600;
        }

        @mixin font {
            font-family: 'Source Sans Pro', sans-serif;
            font-weight: 400;
        }

        @mixin placeholder {
            &::-webkit-input-placeholder {
                @content;
            }

            &:-moz-placeholder {
                @content;
            }

            &::-moz-placeholder {
                @content;
            }

            &:-ms-input-placeholder {
                @content;
            }
        }

        *,
        *:before,
        *:after {
            box-sizing: border-box;
        }

        :root {
            --white: #fff;
            --black: #000;
            --bg: #f8f8f8;
            --grey: #999;
            --dark: #1a1a1a;
            --light: #e6e6e6;
            --wrapper: 1000px;
            --blue: #00b0ff;
        }

        body {
            background-color: var(--bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            @include font;
            background-size: cover;
            background-repeat: none;
        }



        .wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .container {
            position: relative;
            width: 100%;
            padding-right: 50px;
            height: 100%;
        }

        .admin-padding {
            padding-top: 70px;
            padding-bottom: 30px;
        }

        .no-padding {
            padding-top: 0;
            padding-bottom: 0;
        }
        .left {
            float: left;
            width: 37.6%;
            height: 100%;
            border: 1px solid var(--light);
            background-color: var(--white);

            .top {
                position: relative;
                width: 100%;
                height: 96px;
                padding: 29px;

                &:after {
                    position: absolute;
                    bottom: 0;
                    left: 50%;
                    display: block;
                    width: 80%;
                    height: 1px;
                    content: '';
                    background-color: var(--light);
                    transform: translate(-50%, 0);
                }
            }

            input {
                width: 100%;
                /* Full width by default */
                max-width: 600px;
                /* Default maximum width */
                height: 42px;
                padding: 0 15px;
                border: 1px solid var(--light);
                background-color: #eceff1;
                border-radius: 21px;
                font-size: 16px;
            }

            input:focus {
                outline: none;
            }

            /* Tablet view */
            @media (max-width: 768px) {
                input {
                    max-width: 80vw;
                    /* 80% of the viewport width on tablets */
                }
            }

            /* Mobile view */
            @media (max-width: 480px) {
                input {
                    max-width: 90vw;
                    /* 90% of the viewport width on smaller screens */
                    height: 36px;
                    /* Smaller height for compact view */
                    font-size: 14px;
                    /* Smaller font size on mobile */
                }
            }



            .people .person a {
                color: inherit;
                text-decoration: none;
                /* Remove underline */
            }

            .people {
                margin-left: -1px;
                width: calc(100% + 0px);

                /* Hide bullet points from list items */
                list-style-type: none;
                /* Removes bullet points */
                padding: 0;
                /* Optional: Removes padding */
                margin: 0;
                /* Optional: Removes margin */




                .person {
                    position: relative;
                    width: 100%;
                    padding: 12px 10% 16px;
                    cursor: pointer;
                    background-color: var(--white);

                    &:after {
                        position: absolute;
                        bottom: 0;
                        left: 50%;
                        display: block;
                        width: 80%;
                        height: 1px;
                        content: '';
                        background-color: var(--light);
                        transform: translate(-50%, 0);
                    }

                    img {
                        float: left;
                        width: 40px;
                        height: 40px;
                        margin-right: 12px;
                        border-radius: 50%;
                        object-fit: cover;
                    }

                    .name {
                        font-size: 14px;
                        line-height: 22px;
                        color: var(--dark);
                        @include font-bold;
                    }

                    .time {
                        font-size: 14px;
                        position: absolute;
                        top: 16px;
                        right: 10%;
                        padding: 0 0 5px 5px;
                        color: var(--grey);
                        background-color: var(--white);
                    }

                    .preview {
                        font-size: 14px;
                        display: inline-block;
                        overflow: hidden !important;
                        width: 70%;
                        white-space: nowrap;
                        text-overflow: ellipsis;
                        color: var(--grey);
                    }

                    &.active,
                    &:hover {
                        margin-top: -1px;
                        margin-left: -1px;
                        padding-top: 13px;
                        border: 0;
                        /* background-color: #ffa600; */
                        background-color: #FD7238;
                        width: calc(100% + 2px);
                        padding-left: calc(10% + 1px);

                        span {
                            color: var(--white);
                            background: transparent;
                        }

                        &:after {
                            display: none;
                        }
                    }
                }
            }
        }

        .right {
            position: relative;
            float: left;
            width: 62.4%;
            height: 100%;

            .top {
                width: 100%;
                height: 47px;
                padding: 15px 29px;
                background-color: #eceff1;

                span {
                    font-size: 15px;
                    color: var(--grey);

                    .name {
                        color: var(--dark);
                        @include font-bold;
                    }
                }
            }

            .chat {
                position: relative;
                display: none;
                overflow: hidden;
                padding: 0 35px 92px;
                border-width: 1px 1px 1px 0;
                border-style: solid;
                border-color: var(--light);
                height: calc(100% - 48px);
                justify-content: flex-end;
                flex-direction: column;
                background-color: #eceff1;

                &.active-chat {
                    display: block;
                    display: flex;

                    .bubble {
                        transition-timing-function: cubic-bezier(.4, -0.04, 1, 1);


                        @for $i from 1 through 10 {
                            &:nth-of-type(#{$i}) {
                                animation-duration: .15s * $i;
                            }
                        }
                    }
                }
            }

            .chat-box {
                flex: 1;
                /* Take available space */
                overflow-y: auto;
                /* Enable vertical scrolling */
                padding: 10px;
                /* Optional padding */

                max-height: 4000px;
                /* Set a max height to control scrolling */
            }

            .write {
                position: absolute;
                bottom: 29px;
                left: 30px;
                height: 42px;
                padding-left: 8px;
                border: 1px solid var(--light);
                background-color: #eceff1;
                width: calc(100% - 58px);
                border-radius: 5px;

                input {
                    font-size: 16px;
                    float: left;
                    width: 347px;
                    height: 40px;
                    padding: 0 10px;
                    color: var(--dark);
                    border: 0;
                    outline: none;
                    background-color: #eceff1;
                    @include font;
                }

                .write-link[aria-disabled="true"] {
                    cursor: not-allowed;
                    color: #999;
                    /* Adjust color to indicate it's disabled */
                    pointer-events: none;
                    /* Prevents click events */
                }


                .write-link {
                    &.smiley {
                        &:before {
                            display: inline-block;
                            float: left;
                            width: 20px;
                            height: 42px;
                            content: '';
                            background-image: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/382994/smiley.png');
                            background-repeat: no-repeat;
                            background-position: center;
                        }
                    }

                    &.attach {
                        &:before {
                            display: inline-block;
                            float: left;
                            width: 20px;
                            height: 42px;
                            content: '';
                            background-image: url('attach.png');
                            background-repeat: no-repeat;
                            background-position: center;
                        }
                    }

                    &.send {
                        &:before {
                            display: inline-block;
                            float: right;
                            width: 20px;
                            height: 42px;
                            margin-left: 20px;
                            /* Space between input and send icon */
                            content: '';
                            background-image: url('send.png');
                            background-repeat: no-repeat;
                            background-position: center;
                        }
                    }
                }


                .bubble {
                    font-size: 16px;
                    position: relative;
                    display: inline-block;
                    clear: both;
                    margin-bottom: 8px;
                    padding: 13px 14px;
                    vertical-align: top;
                    border-radius: 5px;
                    background-color: black;

                    &:before {
                        position: absolute;
                        top: 19px;
                        display: block;
                        width: 8px;
                        height: 6px;
                        content: '\00a0';
                        transform: rotate(29deg) skew(-35deg);
                    }

                    &.you {
                        float: left;
                        color: var(--white);
                        background-color: #ffa600;
                        align-self: flex-start;
                        animation-name: slideFromLeft;

                        &:before {
                            left: -3px;
                            background-color: #ffa600;
                        }
                    }

                    &.me {
                        float: right;
                        color: var(--dark);
                        background-color: var(--white);
                        align-self: flex-end;
                        animation-name: slideFromRight;

                        &:before {
                            right: -3px;
                            background-color: var(--white);
                        }
                    }
                }
            }

            .bubble {
                font-size: 16px;
                position: relative;
                display: inline-block;
                clear: both;
                margin-bottom: 8px;
                padding: 13px 14px;
                vertical-align: top;
                border-radius: 5px;
                background-color: black;

                &:before {
                    position: absolute;
                    top: 19px;
                    display: block;
                    width: 8px;
                    height: 6px;
                    content: '\00a0';
                    transform: rotate(29deg) skew(-35deg);
                }

                &.you {
                    float: left;
                    color: var(--white);
                    background-color: #ffa600;
                    align-self: flex-start;
                    animation-name: slideFromLeft;

                    &:before {
                        left: -3px;
                        background-color: #ffa600;
                    }
                }

                &.me {
                    float: right;
                    color: var(--dark);
                    background-color: var(--white);
                    align-self: flex-end;
                    animation-name: slideFromRight;

                    &:before {
                        right: -3px;
                        background-color: var(--white);
                    }
                }
            }
        }

        @keyframes slideFromLeft {
            0% {
                transform: translateX(-25px);
                opacity: 0;
            }

            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideFromRight {
            0% {
                transform: translateX(25px);
                opacity: 0;
            }

            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .color-indicator {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .online {
            background-color: green;
        }

        .offline {
            background-color: red;
        }
    </style>
</head>

<body>
<?php
    $IPATH = $_SERVER["DOCUMENT_ROOT"] . "/SchedSys3/php/";
    
    // Assuming you have a way to identify if the user is an admin, like a session variable
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Admin') {
        include($IPATH . "new-admin/admin_sidebar.php");
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Professor') {
        include($IPATH . "viewschedules/professor_navbar.php");
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Student') {
        include($IPATH . "viewschedules/professor_navbar.php");
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Department Chairperson') {
        include($IPATH . "viewschedules/professor_navbar.php");
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'CCL Head') {
        include($IPATH . "department_secretary/navbar.php");
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Department Secretary' && $admin_college_code != $user_college_code) {
        include($IPATH . "viewschedules/professor_navbar.php");
    }  elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Department Secretary' && $admin_college_code == $user_college_code) {
        include($IPATH . "department_secretary/navbar.php");
    } 
    ?>

    <?php
        $containerClass = $user_type === 'Admin' ? 'admin-padding' : 'no-padding';
    ?>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const userType = "Admin"; // Replace with your logic to fetch user type
            const container = document.querySelector(".container");

            if (userType === "Admin") {
                container.classList.add("admin-padding");
            } else {
                container.classList.add("no-padding");
            }
        });
    </script>

    <div class="wrapper">
        <div class="container <?= htmlspecialchars($containerClass) ?>">
            <div class="left">
                <div class="top">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="text" id="search-input" placeholder="Search" />
                            <div id="suggestions"></div>

                        </div>

                    </div>
                </div>
                <ul class="people">

                    <?php echo $output; ?>


                </ul>

            </div>
            <div class="right" style="pointer-events: none; opacity: 1;">
                <div class="top">
                    <span>To: <span class="name"></span></span>
                </div>
                <div class="chat" data-chat="person2">
                    <div class="conversation-start">
                        <!-- Content can go here -->
                    </div>
                </div>

                <div class="write">
                    <a href="javascript:;" class="write-link attach" aria-disabled="true"></a>
                    <input type="text" disabled />
                    <a href="javascript:;" class="write-link send" aria-disabled="true"></a>
                </div>
            </div>

        </div>
    </div>


    <script>

        document.querySelector('.chat[data-chat=person2]').classList.add('active-chat')
        document.querySelector('.person[data-chat=person2]').classList.add('active')

        let friends = {
            list: document.querySelector('ul.people'),
            all: document.querySelectorAll('.left .person'),
            name: ''
        },
            chat = {
                container: document.querySelector('.container .right'),
                current: null,
                person: null,
                name: document.querySelector('.container .right .top .name')
            }

        friends.all.forEach(f => {
            f.addEventListener('mousedown', () => {
                f.classList.contains('active') || setAciveChat(f)
            })
        });

        function setAciveChat(f) {
            friends.list.querySelector('.active').classList.remove('active')
            f.classList.add('active')
            chat.current = chat.container.querySelector('.active-chat')
            chat.person = f.getAttribute('data-chat')
            chat.current.classList.remove('active-chat')
            chat.container.querySelector('[data-chat="' + chat.person + '"]').classList.add('active-chat')
            friends.name = f.querySelector('.name').innerText
            chat.name.innerHTML = friends.name
        }

    </script>


    <script>
        const searchInput = document.getElementById("search-input");
        const suggestions = document.getElementById("suggestions");
        const peopleList = document.querySelector(".people");

        searchInput.addEventListener("input", function () {
            const query = this.value;

            if (query.length > 0) {
                peopleList.style.display = "none"; // Hide people list
                suggestions.style.display = "block"; // Show suggestions when typing
                fetch(`search.php?query=${encodeURIComponent(query)}`)
                    .then(response => response.text())
                    .then(data => {
                        try {
                            data = JSON.parse(data);
                            suggestions.innerHTML = ""; // Clear previous suggestions

                            // Display each suggestion
                            data.forEach(name => {
                                let div = document.createElement("div");
                                div.classList.add("suggestion-item");

                                // Add the HTML directly to innerHTML
                                div.innerHTML = name; // Use innerHTML for direct HTML insertion

                                suggestions.appendChild(div);
                            });
                        } catch (error) {
                            console.error("JSON Parsing Error:", error);
                            console.log("Response Data:", data); // Log raw response
                        }
                    })
                    .catch(error => console.error("Fetch Error:", error));
            } else {
                clearSuggestions();
            }
        });

        // Clear suggestions and show people list
        function clearSuggestions() {
            suggestions.innerHTML = ""; // Clear suggestions
            suggestions.style.display = "none"; // Hide suggestions
            peopleList.style.display = "block"; // Show people list again if input is empty
        }

        // Hide suggestions when clicking outside
        document.addEventListener("click", function (event) {
            const isClickInsideInput = searchInput.contains(event.target);
            const isClickInsideSuggestions = suggestions.contains(event.target);

            // If the click is outside the input and suggestions, hide the suggestions
            if (!isClickInsideInput && !isClickInsideSuggestions) {
                clearSuggestions(); // Clear suggestions and show people list
            }
        });
    </script>
    <script src="javascript/users.js"></script>


</body>

</html>