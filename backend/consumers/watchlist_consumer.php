<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Watchlist Consumer: Starting watchlist_consumer.php");

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

error_log("Watchlist Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('watchlist_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    error_log("Watchlist Consumer: Received message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $user_id = $data['user_id'] ?? null;
    $movie_id = $data['movie_id'] ?? null;
    $action = $data['action'] ?? null;

    if (!$user_id || !$movie_id || !$action) {
        $response = "Watchlist update failed: Missing required fields.";
        error_log("Watchlist Consumer: Missing required fields");
    } else {
        // Connect to the database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Watchlist update failed: Database connection error.";
            error_log("Watchlist Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            if ($action === 'add') {
                $stmt = $mysqli->prepare("INSERT INTO movie_watchlist (user_id, movie_id) VALUES (?, ?)");
            } else {
                $stmt = $mysqli->prepare("DELETE FROM movie_watchlist WHERE user_id=? AND movie_id=?");
            }

            if (!$stmt) {
                $response = "Watchlist update failed: SQL error.";
                error_log("Watchlist Consumer: SQL error - " . $mysqli->error);
            } else {
                $stmt->bind_param('ii', $user_id, $movie_id);
                if ($stmt->execute()) {
                    $response = "Watchlist updated successfully!";
                    error_log("Watchlist Consumer: Watchlist updated successfully.");
                } else {
                    $response = "Watchlist update failed: SQL execution error.";
                    error_log("Watchlist Consumer: SQL execution error - " . $stmt->error);
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

$channel->basic_consume('watchlist_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Watchlist Consumer: Closed RabbitMQ connection");
