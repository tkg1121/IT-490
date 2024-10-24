<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');  // Ensure this includes the RabbitMQ sending logic
include 'header.php';
session_start();

// Check if the session token exists
if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];

    // Prepare the data to send to RabbitMQ
    $data = [
        'action' => 'fetch_profile',  // Define the action for profile fetching
        'session_token' => $session_token  // Send the session token
    ];

    // Log the data being sent
    error_log("Sending data to RabbitMQ: " . json_encode($data));

    // Send the data to RabbitMQ to get the profile information
    $response = sendToRabbitMQ('profile_queue', json_encode($data));

    // Log the response received from RabbitMQ
    error_log("Response from RabbitMQ: " . $response);

    // Decode the JSON response
    $profile_data = json_decode($response, true);

    // Check if the response was successful
    if ($profile_data && isset($profile_data['status']) && $profile_data['status'] === 'success') {
        // Display the profile information
        echo "<h1>Welcome, " . htmlspecialchars($profile_data['username']) . "</h1>";
        echo "<p>Email: " . htmlspecialchars($profile_data['email']) . "</p>";
        
        // If there are other fields, display them as well
        if (isset($profile_data['other_fields'])) {
            echo "<p>Other Info: " . htmlspecialchars($profile_data['other_fields']) . "</p>";
        }
    } else {
        // Handle error responses from the backend
        echo "<p>Failed to load profile information. " . htmlspecialchars($profile_data['message'] ?? 'Unknown error') . "</p>";
    }
} else {
    // If no session token is found, redirect to the login page
    header('Location: login.php');
    exit;
}
?>
