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
            background-color: #f0dfc8; /* light brown background */
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Navbar Styling */
        .navbar {
            background-color: rgba(0, 0, 0, 0.8);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Profile Section */
        .profile-icon {
            display: flex;
            align-items: center;
            /* Removed invalid CSS properties */
            color: white;
            cursor: pointer; /* Added cursor pointer for better UX */
        }

        .profile-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid white;
        }

        .profile-icon span {
            font-size: 1em;
        }

        /* Nav Links */
        .nav-links {
            list-style-type: none;
            display: flex;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }

        .nav-links li {
            margin-left: 20px;
        }

        .nav-links li a {
            text-decoration: none;
            color: white;
            font-size: 1em;
            transition: color 0.3s;
        }

        .nav-links li a:hover {
            color: hotpink;
        }

        /* Container Styling */
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            color: #795833;
            font-size: 2em;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        input[type="text"] {
            width: 80%;
            padding: 10px;
            font-size: 1em;
            margin-bottom: 20px;
            border: 2px solid #795833;
            border-radius: 5px;
            color: #333;
            outline: none;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus {
            border-color: #333;
        }

        button {
            padding: 10px 20px;
            background-color: #795833;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #333;
        }

        /* Movie Card Styling */
        .movie-card {
            display: flex;
            align-items: center;
            background-color: #f0dfc8;
            border: 1px solid #795833;
            border-radius: 5px;
            margin: 10px 0;
            padding: 10px;
            text-align: left;
            color: #333;
            transition: transform 0.2s;
            cursor: pointer;
        }

        .movie-card:hover {
            transform: scale(1.02);
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }

        .movie-poster {
            width: 80px;
            height: auto;
            border-radius: 4px;
            margin-right: 15px;
        }

        .movie-card h3 {
            color: #795833;
            margin: 0;
            font-size: 1.2em;
        }

        .movie-card p {
            margin: 5px 0;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .profile-icon img {
                width: 30px;
                height: 30px;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
                align-items: center;
                margin-top: 10px;
            }

            .nav-links li {
                margin: 10px 0;
            }
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
                <li><a href="recommendation.php">Recommendations</a></li> <!-- Added Recommendations Tab -->
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
