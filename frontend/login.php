<?php
require_once('rabbitmq_send.php');  // Make sure this path is correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Prepare the data to be sent to RabbitMQ
    $data = [
        'username' => $username,
        'password' => $password,
    ];

    // Send login data to RabbitMQ and get the response
    $response = sendToRabbitMQ('login_queue', json_encode($data));

    // Log the response for debugging
    error_log("Login Response: " . $response);

    // If the backend confirms login success, redirect to the profile page
    if (strpos($response, 'Login successful') !== false) {
        // Redirect to the profile page after successful login
        header("Location: profile.php?username=" . urlencode($username));
        exit();  // Ensure the script stops after redirection
    } else {
        // Show failure message
        echo "Login failed. Please try again.";
    }
}
?>
