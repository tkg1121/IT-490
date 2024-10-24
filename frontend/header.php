<?php
session_start();

require_once('rabbitmq_send.php');

// Set session timeout (in seconds)
$session_timeout = 600; // 10 minutes

// Initialize username as "Guest"
$username_display = 'Guest';

// Check if a session token exists in cookies
if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];

    // Prepare the data to send to RabbitMQ for profile fetching
    $data = [
        'action' => 'fetch_profile',
        'session_token' => $session_token
    ];

    // Send the data to RabbitMQ and get the response
    $response = sendToRabbitMQ('profile_queue', json_encode($data));
    $profile_data = json_decode($response, true);

    // If the response is successful, update the username for display
    if ($profile_data && isset($profile_data['status']) && $profile_data['status'] === 'success') {
        $username_display = htmlspecialchars($profile_data['username']);
    }
}

// Optional: Display session timeout management logic for session timestamp
if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];  // Calculate elapsed time

    if ($elapsed_time >= $session_timeout) {
        // Session expired, destroy session and redirect to login
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    } else {
        // Session is still valid, update the timestamp
        $_SESSION['last_activity'] = time();
    }
} else {
    // First interaction or session just started
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
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(45deg, hotpink, purple);
            color: white;
            margin: 0;
        }

        /* Navigation bar */
        .navbar {
            background-color: rgba(0, 0, 0, 0.7);
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Logo/Profile icon */
        .profile-icon {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .profile-icon img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }

        /* Navigation Links */
        .nav-links {
            list-style-type: none;
            display: flex;
            margin: 0;
            padding: 0;
        }

        .nav-links li {
            margin-left: 20px;
        }

        .nav-links li a {
            text-decoration: none;
            color: white;
            font-size: 1.2em;
        }

        .nav-links li a:hover {
            color: hotpink;
        }
    </style>
</head>
<body>
    <header>
        <div class="navbar">
            <div class="profile-icon">
                <img src="profile_icon.png" alt="Profile Icon"> <!-- Profile icon, replace with dynamic image -->
                <span><?php echo $username_display; ?></span> <!-- Display the username or "Guest" -->
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="movies.php">Movies</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="about.php">About</a></li>
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
