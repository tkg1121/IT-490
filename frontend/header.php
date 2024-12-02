<?php
session_start();
require_once('rabbitmq_send.php');

// Session timeout in seconds
$session_timeout = 600; // 10 minutes

// Default username display
$username_display = 'Guest';

if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];

    // Prepare data to fetch profile through RabbitMQ
    $data = [
        'action' => 'fetch_profile',
        'session_token' => $session_token
    ];

    // Send request and decode profile data
    $response = sendToRabbitMQ('profile_queue', json_encode($data));
    $profile_data = json_decode($response, true);

    if ($profile_data && isset($profile_data['status']) && $profile_data['status'] === 'success') {
        $username_display = htmlspecialchars($profile_data['username']);
        $_SESSION['username'] = $username_display; // Store username in session
    }
}

// Manage session timeout
if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];

    if ($elapsed_time >= $session_timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['last_activity'] = time();
    }
} else {
    $_SESSION['last_activity'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Website</title>
    <style>
        /* General Styling */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Navbar Styling */
        .navbar {
            background-color: rgba(0, 0, 0, 0.8);
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .profile-icon {
            display: flex;
            align-items: center;
            color: white;
            cursor: pointer;
        }

        .profile-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }

        .profile-icon span {
            font-size: 1rem;
        }

        .nav-links {
            list-style: none;
            display: flex;
            gap: 15px;
            margin: 0;
            padding: 0;
        }

        .nav-links li a {
            text-decoration: none;
            color: white;
            font-size: 1rem;
            transition: color 0.3s;
        }

        .nav-links li a:hover {
            color: #ff9b42;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="profile-icon">
                <img src="profile_icon.png" alt="Profile Icon">
                <span><?php echo $username_display; ?></span>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="trivia.php">Trivia</a></li>
                <li><a href="social_media.php">Social Media</a></li>
                <li><a href="where_to_watch.php">Where to Watch</a></li>
                <li><a href="buy_tickets.php">Buy a Movie Ticket</a></li>
                <li><a href="recommendation.php">Recommendations</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="friends.php">Friends List</a></li>
                <?php if ($username_display !== 'Guest'): ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="signup.php">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>
</body>
</html>