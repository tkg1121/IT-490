<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku');
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Pull credentials from .env file
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
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
    $queue = 'favorites_queue';

    // Declare the queue for favorites
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for favorite movie requests via IMDb ID...\n";

    // Function to fetch movie data by IMDb ID from OMDb API
    function fetchOmdbDataById($imdb_id) {
        global $OMDB_API_KEY, $OMDB_URL;
        $apiUrl = $OMDB_URL . '?i=' . urlencode($imdb_id) . '&apikey=' . $OMDB_API_KEY;

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

        if (isset($requestData['id'])) {
            // Search by IMDb ID
            $imdb_id = $requestData['id'];
            $omdbData = fetchOmdbDataById($imdb_id);
            $response = $omdbData ? $omdbData : ['error' => 'Movie not found'];
            echo "Received request for IMDb ID: {$imdb_id}\n";
        } else {
            // Invalid request
            $response = ['error' => 'Invalid request: missing IMDb ID'];
        }

        // Log the response
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
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error connecting to RabbitMQ: " . $e->getMessage() . "\n";
}
?>
