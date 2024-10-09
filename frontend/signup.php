<?php
require_once('rabbitmq_send.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>
