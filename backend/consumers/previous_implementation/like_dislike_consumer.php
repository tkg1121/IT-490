<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Like/Dislike Consumer: Starting like_dislike_consumer.php");

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
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

error_log("Like/Dislike Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('like_dislike_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    error_log("Like/Dislike Consumer: Received message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $user_id = $data['user_id'] ?? null;
    $movie_id = $data['movie_id'] ?? null;
    $like = $data['like'] ?? null;
    $dislike = $data['dislike'] ?? null;

    if (!$user_id || !$movie_id || ($like === null && $dislike === null)) {
        $response = "Like/Dislike failed: Missing required fields.";
        error_log("Like/Dislike Consumer: Missing required fields");
    } else {
        // Connect to the database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Like/Dislike failed: Database connection error.";
            error_log("Like/Dislike Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO movie_likes_dislikes (user_id, movie_id, likes, dislikes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE likes=?, dislikes=?");
            if (!$stmt) {
                $response = "Like/Dislike failed: SQL error.";
                error_log("Like/Dislike Consumer: SQL error - " . $mysqli->error);
            } else {
                $stmt->bind_param('iiiisi', $user_id, $movie_id, $like, $dislike, $like, $dislike);
                if ($stmt->execute()) {
                    $response = "Like/Dislike updated successfully!";
                    error_log("Like/Dislike Consumer: Like/Dislike updated successfully.");
                } else {
                    $response = "Like/Dislike failed: SQL execution error.";
                    error_log("Like/Dislike Consumer: SQL execution error - " . $stmt->error);
                }
                $stmt->close();
            }
            $mysqli->close();
        }
    }

    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('like_dislike_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Like/Dislike Consumer: Closed RabbitMQ connection");
