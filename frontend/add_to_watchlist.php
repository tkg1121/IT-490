<?php
session_start();
require_once('rabbitmq_send.php');

if (isset($_COOKIE['session_token']) && isset($_POST['imdb_id'])) {
    $session_token = $_COOKIE['session_token'];
    $imdb_id = htmlspecialchars($_POST['imdb_id']);

    // Prepare data to send to RabbitMQ
    $data = [
        'action' => 'add_to_watchlist',
        'session_token' => $session_token,
        'imdb_id' => $imdb_id
    ];

    // Send data to RabbitMQ
    sendToRabbitMQ('watchlist_queue', json_encode($data));

    // Redirect back to profile or details page
    header("Location: profile.php");
    exit();
} else {
    echo "Error: Unable to add movie to watchlist.";
}
?>
