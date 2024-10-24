<?php
session_start();
require_once('rabbitmq_send.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $movie_id = $_POST['movie_id'];
    $comment_text = $_POST['comment_text'];

    // Prepare the data to send to RabbitMQ
    $data = [
        'action' => 'add_comment',
        'user_id' => $user_id,
        'movie_id' => $movie_id,
        'comment_text' => $comment_text
    ];

    // Send the data to RabbitMQ
    $response = sendToRabbitMQ('comment_queue', json_encode($data));

    echo $response;
}
?>
