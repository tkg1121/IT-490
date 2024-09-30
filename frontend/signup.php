<?php
require_once('rabbitmq_send.php');  // Make sure this path is correct

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $email = $_POST['email'];

    // Prepare the data to be sent to RabbitMQ
    $data = [
        'username' => $username,
        'password' => $password,
        'email' => $email,
    ];

    // Send signup data to RabbitMQ and get the response
    $response = sendToRabbitMQ('signup_queue', json_encode($data));

    // Log the response for debugging
    error_log("Signup Response: " . $response);

    // If the backend confirms signup success, redirect to a welcome page
    if (strpos($response, 'Signup successful') !== false) {
        echo "Signup successful! You can now log in.";
        // Optionally redirect to login or profile page
        // header("Location: login.html");
        exit();
    } else {
        // Show failure message
        echo $response;
    }
}
?>
