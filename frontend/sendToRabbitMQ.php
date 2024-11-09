<?php
include_once '/home/ashleys/IT-490/frontend/rabbitmq_send.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve data from the POST request
    $review_id = $_POST['review_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $session_token = $_COOKIE['session_token'] ?? null;

    // Log received values for debugging
    error_log("Received review_id: $review_id, action: $action");

    if ($review_id && $action && $session_token) {
        // Prepare data to send to RabbitMQ
        $data = [
            'action' => $action,
            'session_token' => $session_token,
            'review_id' => $review_id
        ];

        // Call sendToRabbitMQ to handle the request
        $response = sendToRabbitMQ('review_queue', json_encode($data));
        echo $response;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    }
}
