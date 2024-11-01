<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle login submission
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

    if (isset($response_data['status']) && $response_data['status'] === 'success') {
        // Set the session token cookie in the browser
        setcookie('session_token', $response_data['session_token'], time() + (60 * 10), "/");  // 10-minute expiry

        // Redirect to the profile page after successful login
        header("Location: profile.php?username=" . urlencode($username));
        exit();  // Ensure the script stops after redirection
    } else {
        // Output the exact failure message from RabbitMQ
        echo $response;
    }
} else {
    // Display the login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
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
                background-color: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <form action="login.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php
}
?>
