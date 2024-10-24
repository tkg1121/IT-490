<?php
ini_set('display_errors', 1);  // Show errors in the console (CLI)
ini_set('log_errors', 1);      // Log errors to a file
ini_set('error_log', '/path/to/your/error.log');  // Path to error log file
error_reporting(E_ALL);        // Report all types of errors

require_once('rabbitmq_send.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];  // Send plain-text password to RabbitMQ
    $session_token = bin2hex(random_bytes(32));

    // Prepare the data for RabbitMQ (plain-text password)
    $data = [
        'username' => $username,
        'email' => $email,
        'password' => $password,  // Plain-text password
        'session_token' => $session_token
    ];

    // Send the data to RabbitMQ
    $response = sendToRabbitMQ('signup_queue', json_encode($data));
    echo $response;
} else {
    // Display the signup form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign Up</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f0f0f0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
            }
            form {
                background-color: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            input {
                display: block;
                width: 100%;
                padding: 10px;
                margin-bottom: 10px;
                border-radius: 4px;
                border: 1px solid #ccc;
            }
            button {
                width: 100%;
                padding: 10px;
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <form action="signup.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign Up</button>
        </form>
    </body>
    </html>
    <?php
}
?>
