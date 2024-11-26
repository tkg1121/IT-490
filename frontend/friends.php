<?php
// friends.php

require_once('rabbitmq_send.php');
include 'header.php';

// Check if user is logged in
if (!isset($_COOKIE['session_token'])) {
    header('Location: login.php');
    exit();
}

$session_token = $_COOKIE['session_token'];
$error_message = '';
$success_message = '';

// Handle sending friend requests and accepting/declining requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_request'])) {
        $friend_username = $_POST['friend_username'];

        $data = [
            'action' => 'send_request',
            'session_token' => $session_token,
            'friend_username' => $friend_username
        ];

        $response = sendToRabbitMQ('friends_queue', json_encode($data));
        $responseData = json_decode($response, true);

        if ($responseData['status'] === 'success') {
            $success_message = $responseData['message'];
        } else {
            $error_message = $responseData['message'];
        }
    } elseif (isset($_POST['accept_request'])) {
        $friend_username = $_POST['friend_username'];

        $data = [
            'action' => 'accept_request',
            'session_token' => $session_token,
            'friend_username' => $friend_username
        ];

        $response = sendToRabbitMQ('friends_queue', json_encode($data));
        $responseData = json_decode($response, true);

        if ($responseData['status'] === 'success') {
            $success_message = $responseData['message'];
        } else {
            $error_message = $responseData['message'];
        }
    } elseif (isset($_POST['decline_request'])) {
        $friend_username = $_POST['friend_username'];

        $data = [
            'action' => 'decline_request',
            'session_token' => $session_token,
            'friend_username' => $friend_username
        ];

        $response = sendToRabbitMQ('friends_queue', json_encode($data));
        $responseData = json_decode($response, true);

        if ($responseData['status'] === 'success') {
            $success_message = $responseData['message'];
        } else {
            $error_message = $responseData['message'];
        }
    }
}

// Get pending friend requests
$data = [
    'action' => 'get_pending_requests',
    'session_token' => $session_token
];

$response = sendToRabbitMQ('friends_queue', json_encode($data));
$responseData = json_decode($response, true);

$pending_requests = [];
if ($responseData['status'] === 'success') {
    $pending_requests = $responseData['pending_requests'];
} else {
    $error_message = $responseData['message'];
}

// Get friends list
$data = [
    'action' => 'get_friends_list',
    'session_token' => $session_token
];

$response = sendToRabbitMQ('friends_queue', json_encode($data));
$responseData = json_decode($response, true);

$friends_list = [];
if ($responseData['status'] === 'success') {
    $friends_list = $responseData['friends_list'];
} else {
    $error_message = $responseData['message'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Friends</title>
    <style>
        /* Your CSS styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0dfc8;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 80%;
            margin: 20px auto;
        }
        h1 {
            color: #795833;
        }
        .success-message {
            color: green;
            margin-bottom: 15px;
        }
        .error-message {
            color: red;
            margin-bottom: 15px;
        }
        .friend-request {
            background-color: #fff;
            padding: 10px;
            border: 1px solid #795833;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .friend-request button {
            padding: 5px 10px;
            margin-right: 5px;
            background-color: #795833;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .friend-request button:hover {
            background-color: #333;
        }
        .friends-list {
            background-color: #fff;
            padding: 10px;
            border: 1px solid #795833;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .friends-list ul {
            list-style-type: none;
            padding: 0;
        }
        .friends-list li {
            margin-bottom: 5px;
        }
        .friends-list a {
            color: #795833;
            text-decoration: none;
        }
        .friends-list a:hover {
            text-decoration: underline;
        }
        .send-request-form {
            margin-bottom: 20px;
        }
        .send-request-form input[type="text"] {
            padding: 5px;
            border: 1px solid #795833;
            border-radius: 3px;
        }
        .send-request-form button {
            padding: 5px 10px;
            background-color: #795833;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .send-request-form button:hover {
            background-color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Friends Page</h1>

        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Form to send friend request -->
        <div class="send-request-form">
            <form method="POST" action="">
                <label for="friend_username">Add a Friend:</label>
                <input type="text" name="friend_username" required>
                <button type="submit" name="send_request">Send Friend Request</button>
            </form>
        </div>

        <!-- Display pending friend requests -->
        <h2>Pending Friend Requests</h2>
        <?php if (!empty($pending_requests)): ?>
            <?php foreach ($pending_requests as $request_username): ?>
                <div class="friend-request">
                    <span><?php echo htmlspecialchars($request_username); ?></span>
                    <form method="POST" action="" style="display:inline;">
                        <input type="hidden" name="friend_username" value="<?php echo htmlspecialchars($request_username); ?>">
                        <button type="submit" name="accept_request">Accept</button>
                        <button type="submit" name="decline_request">Decline</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No pending friend requests.</p>
        <?php endif; ?>

        <!-- Display friends list -->
        <h2>Your Friends</h2>
        <div class="friends-list">
            <?php if (!empty($friends_list)): ?>
                <ul>
                    <?php foreach ($friends_list as $friend_username): ?>
                        <li>
                            <a href="friend_profile.php?username=<?php echo urlencode($friend_username); ?>">
                                <?php echo htmlspecialchars($friend_username); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>You have no friends added.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>