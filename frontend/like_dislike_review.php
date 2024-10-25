<?php
include 'header.php'; 
require_once('rabbitmq_send.php');

// Check if the user is logged in
if (!isset($_COOKIE['session_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$session_token = $_COOKIE['session_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = $_POST['review_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($review_id && ($action === 'like_review' || $action === 'dislike_review')) {
        $data = [
            'action' => $action,
            'session_token' => $session_token,
            'review_id' => $review_id
        ];

        // Send the request to RabbitMQ
        $response = sendToRabbitMQ('review_queue', json_encode($data));

        // Send back the response from RabbitMQ
        echo $response;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
}
?>
