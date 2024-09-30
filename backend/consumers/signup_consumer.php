<?php
require_once('/home/dev/php-amqplib/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    '192.168.193.137',  // RabbitMQ server IP (VM3)
    5672,               // RabbitMQ port
    'guest',            // RabbitMQ username
    'guest',            // RabbitMQ password
    '/',                // Virtual host
    false,              // Insist
    'AMQPLAIN',         // Login method
    null,               // Login response
    'en_US',            // Locale
    10.0,               // Connection timeout in seconds
    10.0,               // Read/write timeout in seconds
    null,               // Context (use null for default)
    false,              // Keepalive (use true to keep the connection alive)
    60                  // Heartbeat interval in seconds
);

$channel = $connection->channel();
$channel->queue_declare('signup_queue', false, true, false, false);

$callback = function ($msg) use ($channel) {
    $data = json_decode($msg->body, true);
    $username = $data['username'];
    $password = $data['password'];
    $email = $data['email'];

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Connect to MySQL
    $mysqli = new mysqli("localhost", "root", "", "user_auth");

    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    // Check if the username already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response = "Signup failed: Username or Email already exists.";
    } else {
        // Insert new user into the database
        $stmt = $mysqli->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $username, $hashed_password, $email);

        if ($stmt->execute()) {
            $response = "Signup successful";
        } else {
            $response = "Signup failed: Error creating user.";
        }
    }

    $stmt->close();
    $mysqli->close();

    // Send the response back to the frontend via RabbitMQ
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]  // Include the correlation ID
    );

    // Publish the response to the reply_to queue
    $channel->basic_publish(
        $reply_msg,
        '',
        $msg->get('reply_to')  // Send to the reply queue specified by the frontend
    );

    // Acknowledge the message
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('signup_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
