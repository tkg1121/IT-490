<?php
require_once 'rabbitmq_send.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prepare the data to send to RabbitMQ
    $data = [
        'action' => 'fetch_posts'
    ];

    // Send data to RabbitMQ
    $response = sendToRabbitMQ('social_media_queue', json_encode($data));

    // Handle the response
    $response_data = json_decode($response, true);
    if ($response_data['status'] === 'success') {
        echo json_encode($response_data);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response_data['message']]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
