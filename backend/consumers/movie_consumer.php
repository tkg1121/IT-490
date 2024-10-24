<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');  // Load .env from /home/ashleys
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Movie Consumer: Starting movie_consumer.php");

// Pull credentials from .env file
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];  // Internal database credentials
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];
$OMDB_API_KEY = $_ENV['OMDB_API_KEY'];
$OMDB_URL = $_ENV['OMDB_URL'];

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

error_log("Movie Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('movie_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $OMDB_API_KEY, $OMDB_URL) {
    error_log("Movie Consumer: Received message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $movie_id = $data['movie_id'] ?? null;

    if (!$movie_id) {
        $response = json_encode(['status' => 'error', 'message' => 'Error: Missing movie ID.']);
        error_log("Movie Consumer: Movie ID not provided");
    } else {
        // Connect to internal database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
            error_log("Movie Consumer: Database connection failed: " . $mysqli->connect_error);
        } else {
            // Fetch movie details from the database
            $stmt = $mysqli->prepare("SELECT imdb_id, title, year, genre, plot, poster FROM movies WHERE imdb_id=?");

            if (!$stmt) {
                $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error.']);
                error_log("Movie Consumer: SQL preparation error: " . $mysqli->error);
            } else {
                $stmt->bind_param('s', $movie_id);
                $stmt->execute();
                $stmt->bind_result($imdb_id, $title, $year, $genre, $plot, $poster);

                if ($stmt->fetch()) {
                    // Prepare movie data response from database
                    $movie_data = [
                        'status' => 'success',
                        'Title' => $title,
                        'Year' => $year,
                        'Genre' => $genre,
                        'Plot' => $plot,
                        'Poster' => $poster
                    ];
                    $response = json_encode($movie_data);
                    error_log("Movie Consumer: Successfully fetched movie details for ID: $movie_id");
                } else {
                    // If movie is not found in the local database, fetch from OMDb API
                    error_log("Movie Consumer: Movie not found for ID: $movie_id, fetching from OMDb API");

                    $omdb_url = $OMDB_URL . '?i=' . urlencode($movie_id) . '&apikey=' . $OMDB_API_KEY;
                    $omdb_response = file_get_contents($omdb_url);
                    $omdb_data = json_decode($omdb_response, true);

                    if (isset($omdb_data['Title'])) {
                        // Insert the movie into the local database
                        $stmt = $mysqli->prepare("INSERT INTO movies (imdb_id, title, year, genre, plot, poster) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt) {
                            $stmt->bind_param(
                                'ssisss',
                                $omdb_data['imdbID'],
                                $omdb_data['Title'],
                                $omdb_data['Year'],
                                $omdb_data['Genre'],
                                $omdb_data['Plot'],
                                $omdb_data['Poster']
                            );
                            if ($stmt->execute()) {
                                error_log("Movie Consumer: Successfully inserted movie with ID: $movie_id into the database");

                                // Prepare movie data response from OMDb
                                $movie_data = [
                                    'status' => 'success',
                                    'Title' => $omdb_data['Title'],
                                    'Year' => $omdb_data['Year'],
                                    'Genre' => $omdb_data['Genre'],
                                    'Plot' => $omdb_data['Plot'],
                                    'Poster' => $omdb_data['Poster']
                                ];
                                $response = json_encode($movie_data);
                            } else {
                                $response = json_encode(['status' => 'error', 'message' => 'Failed to insert movie into database.']);
                                error_log("Movie Consumer: SQL execution error during movie insert: " . $stmt->error);
                            }
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error for insert.']);
                            error_log("Movie Consumer: SQL preparation error during movie insert: " . $mysqli->error);
                        }
                    } else {
                        $response = json_encode(['status' => 'error', 'message' => 'Movie not found on OMDb API.']);
                        error_log("Movie Consumer: Movie not found on OMDb API for ID: $movie_id");
                    }
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

$channel->basic_consume('movie_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Movie Consumer: Closed RabbitMQ connection");
