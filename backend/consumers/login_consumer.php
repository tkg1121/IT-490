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
$channel->queue_declare('login_queue', false, true, false, false);

$callback = function ($msg) use ($channel) {
    $data = json_decode($msg->body, true);
    $username = $data['username'];
    $password = $data['password'];

    // Connect to MySQL
    $mysqli = new mysqli("localhost", "root", "", "user_auth");

    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    // Fetch the hashed password from the database
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE username=?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->bind_result($hash_password);
    $stmt->fetch();
    $stmt->close();

    // Prepare the response message for the frontend
    if (password_verify($password, $hash_password)) {
        $response = "Login successful";
    } else {
        $response = "Login failed";
    }

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

$channel->basic_consume('login_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
