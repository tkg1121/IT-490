<?php
include 'header.php';  // Include your header for styling/navigation
require_once('rabbitmq_send.php');

// Check if the user is logged in
if (!isset($_COOKIE['session_token'])) {
    echo "User not logged in";
    exit();
}

$session_token = $_COOKIE['session_token'];

// Fetch user ID based on session token (you may have a function to do this)
$user_id = getUserIdBySessionToken($session_token); // Implement this function based on your auth system

// Prepare the data to be sent to RabbitMQ
$data = [
    'action' => 'fetch_recommendations',
    'user_id' => $user_id
];

// Send the request to RabbitMQ
$response = sendToRabbitMQ('recommendation_queue', json_encode($data));

// Process the response
$responseData = json_decode($response, true);

if ($responseData['status'] === 'success') {
    echo "<h2>Your Recommendations</h2>";
    echo "<ul>";
    foreach ($responseData['data'] as $movie) {
        echo "<li>{$movie['title']} - Genre: {$movie['genre']} (Weight: {$movie['weight']})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Error fetching recommendations: {$responseData['message']}</p>";
}
?>
