<?php
// login.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');

// Check if session_token is already set; if so, redirect to profile
if (isset($_COOKIE['session_token'])) {
    header('Location: profile.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare the data to be sent to RabbitMQ
    $data = [
        'username' => $username,
        'password' => $password,
    ];

    // Send login data to RabbitMQ and get the response
    $response = sendToRabbitMQ('login_queue', json_encode($data));
    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "Error decoding JSON response.";
    } elseif (isset($response_data['status'])) {
        if ($response_data['status'] === 'success') {
            // Set the session token cookie in the browser
            setcookie('session_token', $response_data['session_token'], time() + (60 * 10), "/");  // 10-minute expiry

            // Redirect to the profile page after successful login
            header("Location: profile.php");
            exit();
        } elseif ($response_data['status'] === '2fa_required') {
            // Redirect to the 2FA page with username as a GET parameter
            header("Location: 2fa.php?username=" . urlencode($username));
            exit();
        } else {
            // Store the error message
            $error_message = $response_data['message'];
        }
    } else {
        $error_message = "Unknown error occurred during login.";
    }
}

// Include header.php after any header modifications
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and other head elements -->
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        /* Styles for the login page */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0dfc8; /* light brown background */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 90vh; /* Adjusted to account for header */
            margin: 0;
        }
        form {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 300px;
        }
        form h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #795833;
            text-align: center;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #795833;
            border-radius: 4px;
            font-size: 1em;
            outline: none;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #795833;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
        }
        button:hover {
            background-color: #333;
        }
        .error-message {
            color: red;
            margin-bottom: 15px;
            text-align: center;
        }
        .signup-link {
            text-align: center;
            margin-top: 15px;
        }
        .signup-link a {
            color: #795833;
            text-decoration: none;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <form action="login.php" method="POST">
        <h2>Login</h2>
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign Up</a>
        </div>
    </form>
</body>
</html>