<?php
session_start();
require_once('rabbitmq_send.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $review_id = $_POST['review_id'];
    $status = $_POST['status']; // 'like' or 'dislike'

    // Prepare the data to send to RabbitMQ
    $data = [
        'action' => 'like_review',
        'user_id' => $user_id,
        'review_id' => $review_id,
        'status' => $status
    ];

    // Send the data to RabbitMQ
    $response = sendToRabbitMQ('review_likes_queue', json_encode($data));

    echo $response;
}
?>
