<?php
require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Get the API key from the .env file
$apiKey = $_ENV['API_KEY'];

// Get movie ID from the form submission
$movieId = $_POST['movie_id'];

// Fetch the movie data from the external API
$apiUrl = "https://api.example.com/movies/{$movieId}?api_key={$apiKey}";
$movieData = file_get_contents($apiUrl);

if ($movieData) {
    // Decode the movie data
    $movie = json_decode($movieData, true);

    // Set up RabbitMQ connection
    $connection = new AMQPStreamConnection(
        '192.168.193.197',    // IP address or hostname of the RabbitMQ machine
        5672,             // Port RabbitMQ is listening on (default 5672)
        'guest',          // RabbitMQ username
        'guest'           // RabbitMQ password
    );
    $channel = $connection->channel();

    // Declare a queue where messages will be sent
    $channel->queue_declare('movie_list_queue', false, false, false, false);

    // Create a message from the movie data
    $messageBody = json_encode([
        'movie_id' => $movie['id'],
        'title' => $movie['title'],
        'release_date' => $movie['release_date'],
        'overview' => $movie['overview']
    ]);
    $message = new AMQPMessage($messageBody);

    // Publish the message to the queue
    $channel->basic_publish($message, '', 'movie_list_queue');

    echo "Movie added to the queue successfully!";

    // Close the channel and connection
    $channel->close();
    $connection->close();
} else {
    echo "Failed to retrieve movie data.";
}

?>
