<?php
require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku');  // Load .env from /home/alisa-maloku
$dotenv->load();
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$TMDB_API_KEY = $_ENV['TMDB_API_KEY'];
$TMDB_URL = 'https://api.themoviedb.org/3';

try {
    $connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS);
    $channel = $connection->channel();
    $queue = 'where_to_watch_queue';

    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for movie watch location requests...\n";

    function fetchMovieWatchProviders($movie_name) {
        global $TMDB_API_KEY, $TMDB_URL;

        // First, search for the movie by name
        $searchUrl = "$TMDB_URL/search/movie?api_key=$TMDB_API_KEY&query=" . urlencode($movie_name);
        $searchResponse = json_decode(file_get_contents($searchUrl), true);

        if (empty($searchResponse['results'][0])) {
            return ['error' => 'Movie not found'];
        }

        // Use the first search result's ID to get watch providers
        $movie = $searchResponse['results'][0];
        $providersUrl = "$TMDB_URL/movie/{$movie['id']}/watch/providers?api_key=$TMDB_API_KEY";
        $providersResponse = json_decode(file_get_contents($providersUrl), true);

        // Extract relevant data
        $providers = $providersResponse['results']['US']['flatrate'] ?? [];
        $watch_providers = array_map(function ($provider) {
            return ['name' => $provider['provider_name'], 'link' => $provider['link'] ?? '#'];
        }, $providers);

        return [
            'title' => $movie['title'],
            'year' => date('Y', strtotime($movie['release_date'])),
            'genre' => implode(', ', $movie['genre_ids']), // Map to genre names if needed
            'rating' => $movie['vote_average'],
            'poster' => 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'],
            'watch_providers' => $watch_providers,
        ];
    }

    $callback = function ($msg) use ($channel) {
        $requestData = json_decode($msg->body, true);
        $response = [];

        if (isset($requestData['movie_name'])) {
            $response = fetchMovieWatchProviders($requestData['movie_name']);
            echo "Received request for where to watch: {$requestData['movie_name']}\n";
        } else {
            $response = ['error' => 'Invalid request'];
        }

        $reply_msg = new AMQPMessage(
            json_encode($response),
            ['correlation_id' => $msg->get('correlation_id')]
        );

        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
    };

    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    while ($channel->is_consuming()) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
