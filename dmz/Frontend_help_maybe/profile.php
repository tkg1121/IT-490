<?php
require_once(__DIR__ . "/../lib/functions.php");
require_once('/home/dev/php-amqplib/vendor/autoload.php'); // Adjust the path as necessary

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Function to send a message to RabbitMQ
function sendToRabbitMQ($queue, $message) {
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            '192.168.193.137',  // RabbitMQ server IP
            5672,               // RabbitMQ port
            'guest',            // RabbitMQ username
            'guest',            // RabbitMQ password
            '/'                 // Virtual host
        );

        // Create a channel
        $channel = $connection->channel();

        // Declare the queue where the message will be sent
        $channel->queue_declare($queue, false, true, false, false);

        // Create the message
        $msg = new AMQPMessage($message);

        // Publish the message to the queue
        $channel->basic_publish($msg, '', $queue);

        // Close the channel and connection
        $channel->close();
        $connection->close();

    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("RabbitMQ Error: " . $e->getMessage());
    }
}

// Check if the user is logged in
if (!is_logged_in()) {
    header("Location: " . get_url('login.php'));
    exit;
}

// Get user data (this is just an example; adjust based on your implementation)
$username = $_SESSION['username'];
// Fetch other user profile data as needed

// Log the action of viewing the profile
sendToRabbitMQ('user_actions_queue', "User viewed profile: $username");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo get_url('styles.css'); ?>">
    <title>User Profile</title>
</head>
<body>
    <nav>
        <ul>
            <li><a href="<?php echo get_url('home.php'); ?>">Home</a></li>
            <li><a href="<?php echo get_url('profile.php'); ?>">Profile</a></li>
            <li><a href="<?php echo get_url('logout.php'); ?>">Logout</a></li>
        </ul>
    </nav>
    
    <h1>User Profile</h1>
    <p>Username: <?php echo htmlspecialchars($username); ?></p>
    <!-- Display other user profile information here -->

    <!-- Additional functionality or links can be added here -->
</body>
</html>
