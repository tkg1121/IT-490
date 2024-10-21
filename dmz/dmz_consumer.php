<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('/home/alisa-maloku/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku/Documents/GitHub/IT-490/dmz');  // Path to your .env file
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Pull credentials from .env file
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];
$OMDB_API_KEY = $_ENV['OMDB_API_KEY'];
$OMDB_URL = $_ENV['OMDB_URL'];

try {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();
    $queue = 'omdb_request_queue';

    // Declare the queue for OMDb requests
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for OMDb movie requests...\n";

    // Function to fetch movie data from OMDb API
    function fetchOmdbData($movie_name) {
        global $OMDB_API_KEY, $OMDB_URL;
        $apiUrl = $OMDB_URL . '?t=' . urlencode($movie_name) . '&apikey=' . $OMDB_API_KEY;
        
        $response = file_get_contents($apiUrl);
        
        if ($response === false) {
            return ['error' => 'Movie not found or API error'];
        }
        
        return json_decode($response, true);
    }

    // Callback function to handle messages
    $callback = function ($msg) use ($channel) {
        $requestData = json_decode($msg->body, true);
        $response = [];

        if (isset($requestData['name'])) {
            $movie_name = $requestData['name'];
            $omdbData = fetchOmdbData($movie_name);
            $response = $omdbData ? $omdbData : ['error' => 'Movie not found'];
        } else {
            $response = ['error' => 'Invalid request'];
        }

        // Log the request and response
        echo "Received request for: {$movie_name}\n";
        echo "Sending response: " . json_encode($response) . "\n";

        // Send the response back to RabbitMQ
        $reply_msg = new AMQPMessage(
            json_encode($response),
            ['correlation_id' => $msg->get('correlation_id')]
        );

        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

        // Acknowledge the message
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
    };

    // Start consuming from the queue
    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Keep the consumer running indefinitely
    while (true) {
        try {
            $channel->wait();
        } catch (Exception $e) {
            echo "Error while waiting for messages: " . $e->getMessage() . "\n";
            break;
        }
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error connecting to RabbitMQ: " . $e->getMessage() . "\n";
}
