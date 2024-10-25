<?php
include 'header.php'; 
require_once('rabbitmq_send.php');

// Function to log data to a file
function log_json_data($data) {
    file_put_contents(__DIR__ . '/rabbitmq_json.log', $data . PHP_EOL, FILE_APPEND);
}

// Check if the user is logged in via session token
if (!isset($_COOKIE['session_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$session_token = $_COOKIE['session_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movie_id = $_POST['movie_id'] ?? null;
    $review_text = $_POST['review_text'] ?? null;
    $rating = $_POST['rating'] ?? null;

    // Prepare the data to send to RabbitMQ
    $data = [
        'action' => 'add_review',
        'session_token' => $session_token,
        'movie_id' => $movie_id,
        'review_text' => $review_text,
        'rating' => $rating
    ];

    $json_data = json_encode($data);

    // Display JSON data in the browser
    echo "<h3>JSON Data Sent to RabbitMQ:</h3>";
    echo "<pre>" . htmlspecialchars($json_data) . "</pre>";

    // Log JSON data to a file
    log_json_data("Add Review - Sending JSON to RabbitMQ: " . $json_data);

    // Send the data to RabbitMQ
    $response = sendToRabbitMQ('review_queue', $json_data);

    // Decode the response
    $decoded_response = json_decode($response, true);

    if ($decoded_response && isset($decoded_response['status']) && $decoded_response['status'] === 'success') {
        echo json_encode(['status' => 'success', 'message' => 'Review added successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'An error occurred while submitting your review.']);
    }
}

