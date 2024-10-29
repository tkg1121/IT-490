<?php
require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Recommendation Consumer: Starting recommendation_consumer.php");

// Pull credentials from .env file
$RABBITMQ_HOST = getenv('RABBITMQ_HOST');
$RABBITMQ_PORT = getenv('RABBITMQ_PORT');
$RABBITMQ_USER = getenv('RABBITMQ_USER');
$RABBITMQ_PASS = getenv('RABBITMQ_PASS');
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

error_log("Recommendation Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('recommendation_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    error_log("Recommendation Consumer: Received message: " . $msg->body);
    $data = json_decode($msg->body, true);

    if ($data['action'] === 'fetch_recommendations') {
        $user_id = $data['user_id'];
        
        // Connect to the database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            error_log("Recommendation Consumer: Database connection failed: " . $mysqli->connect_error);
        } else {
            error_log("Recommendation Consumer: Database connection successful");

            // Fetch recommendations based on user preferences
            $stmt = $mysqli->prepare("SELECT m.title, m.genre, mw.weight 
                                       FROM movie_weights mw 
                                       JOIN movies m ON mw.genre = m.genre 
                                       WHERE mw.user_id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $recommendations = [];
                    while ($row = $result->fetch_assoc()) {
                        $recommendations[] = $row;
                    }
                    $response = json_encode(['status' => 'success', 'data' => $recommendations]);
                } else {
                    $response = json_encode(['status' => 'error', 'message' => 'SQL execution error']);
                }
                $stmt->close();
            } else {
                $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error']);
            }
            $mysqli->close();
        }

        // Send the response back via RabbitMQ
        $reply_msg = new AMQPMessage($response, ['correlation_id' => $msg->get('correlation_id')]);
        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
        error_log("Recommendation Consumer: Sent response: " . $response);
    }

    // Acknowledge the message (mark it as processed)
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

// Consume messages from `recommendation_queue`
$channel->basic_consume('recommendation_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Recommendation Consumer: Closed RabbitMQ connection");
?>
