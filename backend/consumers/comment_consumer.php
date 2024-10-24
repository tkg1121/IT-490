<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Login Consumer: Starting login_consumer.php");

// Pull credentials from .env file using $_ENV
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,  // RabbitMQ server IP
    $RABBITMQ_PORT,  // RabbitMQ port
    $RABBITMQ_USER,  // RabbitMQ username
    $RABBITMQ_PASS,  // RabbitMQ password
    '/',             // Virtual host
    false,           // Insist
    'AMQPLAIN',      // Login method
    null,            // Login response
    'en_US',         // Locale
    10.0,            // Connection timeout
    10.0,            // Read/write timeout
    null,            // Context (use null for default)
    false,           // Keepalive
    60               // Heartbeat interval
);

$channel = $connection->channel();
$channel->queue_declare('comment_queue', false, true, false, false);

$callback = function ($msg) {
    $data = json_decode($msg->body, true);

    if ($data['action'] === 'add_comment') {
        $user_id = $data['user_id'];
        $movie_id = $data['movie_id'];
        $comment_text = $data['comment_text'];

        $mysqli = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);

        if ($mysqli->connect_error) {
            $response = "Error: Could not connect to the database.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO comments (user_id, movie_id, comment_text) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $user_id, $movie_id, $comment_text);

            if ($stmt->execute()) {
                $response = "Comment added successfully";
            } else {
                $response = "Error: Could not add comment.";
            }

            $stmt->close();
            $mysqli->close();
        }

        $reply_msg = new AMQPMessage($response, ['correlation_id' => $msg->get('correlation_id')]);
        $msg->delivery_info['channel']->basic_publish($reply_msg, '', $msg->get('reply_to'));
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }
};

$channel->basic_consume('comment_queue', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
