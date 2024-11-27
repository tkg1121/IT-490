<?php
// 2fa.php

require_once('rabbitmq_send.php');

// Check if session_token is already set; if so, redirect to profile
if (isset($_COOKIE['session_token'])) {
    header('Location: profile.php');
    exit();
}

// Retrieve username from GET parameters
$username = $_GET['username'] ?? null;

if (!$username) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $two_factor_code = $_POST['two_factor_code'] ?? '';

    // Prepare the data to be sent to RabbitMQ
    $data = [
        'username' => $username,
        'two_factor_code' => $two_factor_code
    ];

    // Send 2FA data to RabbitMQ and get the response
    $response = sendToRabbitMQ('2fa_queue', json_encode($data));
    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "Error decoding JSON response.";
    } elseif (isset($response_data['status'])) {
        if ($response_data['status'] === 'success') {
            // Set the session token cookie in the browser
            setcookie('session_token', $response_data['session_token'], time() + (60 * 10), "/");  // 10-minute expiry

            // Redirect to the profile page after successful 2FA
            header("Location: profile.php");
            exit();  // Ensure the script stops after redirection
        } else {
            // Store the error message
            $error_message = $response_data['message'];
        }
    } else {
        $error_message = "Unknown error occurred during 2FA verification.";
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
    <title>Two-Factor Authentication</title>
    <style>
        /* Styles for the 2FA page */
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
        input[type="text"] {
            width: 100%;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #795833;
            border-radius: 4px;
            font-size: 1.5em;
            text-align: center;
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
    </style>
</head>
<body>
    <form action="2fa.php?username=<?php echo urlencode($username); ?>" method="POST">
        <h2>Two-Factor Authentication</h2>
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <input type="text" name="two_factor_code" maxlength="6" pattern="\d{6}" required placeholder="Enter 6-digit code">
        <button type="submit">Verify</button>
    </form>
</body>
</html>