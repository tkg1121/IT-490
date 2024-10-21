<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');

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
}
