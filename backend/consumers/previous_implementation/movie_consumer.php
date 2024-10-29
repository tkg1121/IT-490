<?php
// movie_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Pull environment variables
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

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

$channel = $connection->channel();
$channel->queue_declare('movie_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $OMDB_API_KEY, $OMDB_URL) {
    $data = json_decode($msg->body, true);
    $action = $data['action'] ?? null;
    $movie_id = $data['movie_id'] ?? null;

    if ($action !== 'fetch_movie_details' || !$movie_id) {
        $response = ['status' => 'error', 'message' => 'Invalid request.'];
        error_log("Movie Consumer: Invalid action or missing movie_id.");
    } else {
        // Connect to internal database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = ['status' => 'error', 'message' => 'Database connection failed.'];
            error_log("Movie Consumer: Database connection failed: " . $mysqli->connect_error);
        } else {
            // Check if the movie is already in the local database
            $stmt = $mysqli->prepare("SELECT imdb_id, title, year, genre, plot, poster, rating FROM movies WHERE imdb_id = ?");
            if (!$stmt) {
                $response = ['status' => 'error', 'message' => 'Failed to prepare statement.'];
                error_log("Movie Consumer: Failed to prepare statement: " . $mysqli->error);
            } else {
                $stmt->bind_param('s', $movie_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $movie = $result->fetch_assoc();
                $stmt->close();

                if ($movie) {
                    // Movie found in the database
                    $response = [
                        'status' => 'success',
                        'movie' => [
                            'imdbID' => $movie['imdb_id'],
                            'Title' => $movie['title'],
                            'Year' => $movie['year'],
                            'Genre' => $movie['genre'],
                            'Plot' => $movie['plot'],
                            'Poster' => $movie['poster'],
                            'imdbRating' => $movie['rating']
                        ]
                    ];
                    error_log("Movie Consumer: Movie details fetched from database for IMDb ID: $movie_id");
                } else {
                    // Movie not found in the database, fetch from OMDb API
                    $omdb_url = $OMDB_URL . '?i=' . urlencode($movie_id) . '&apikey=' . $OMDB_API_KEY;
                    $omdb_response = file_get_contents($omdb_url);
                    $omdb_data = json_decode($omdb_response, true);

                    if ($omdb_response === false || json_last_error() !== JSON_ERROR_NONE) {
                        $response = ['status' => 'error', 'message' => 'Failed to fetch movie data from OMDb API.'];
                        error_log("Movie Consumer: Failed to fetch data from OMDb API for IMDb ID: $movie_id");
                    } elseif (isset($omdb_data['Response']) && $omdb_data['Response'] === 'False') {
                        $error_message = $omdb_data['Error'] ?? 'Movie not found.';
                        $response = ['status' => 'error', 'message' => $error_message];
                        error_log("Movie Consumer: OMDb API Error for IMDb ID $movie_id: $error_message");
                    } else {
                        // Insert the movie into the local database
                        $insert_stmt = $mysqli->prepare("
                            INSERT INTO movies (imdb_id, title, year, genre, plot, poster, rating)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");

                        if (!$insert_stmt) {
                            $response = ['status' => 'error', 'message' => 'Failed to prepare insert statement.'];
                            error_log("Movie Consumer: Failed to prepare insert statement: " . $mysqli->error);
                        } else {
                            $imdbID = $omdb_data['imdbID'] ?? '';
                            $title = $omdb_data['Title'] ?? 'Unknown Title';
                            $year = isset($omdb_data['Year']) && is_numeric($omdb_data['Year']) ? intval($omdb_data['Year']) : null;
                            $genre = $omdb_data['Genre'] ?? 'Unknown Genre';
                            $plot = $omdb_data['Plot'] ?? 'No plot available.';
                            $poster = $omdb_data['Poster'] ?? 'default_poster.jpg';
                            $imdbRating = $omdb_data['imdbRating'] ?? 'N/A';

                            $insert_stmt->bind_param(
                                'ssissss',
                                $imdbID,
                                $title,
                                $year,
                                $genre,
                                $plot,
                                $poster,
                                $imdbRating
                            );

                            if ($insert_stmt->execute()) {
                                $response = [
                                    'status' => 'success',
                                    'movie' => [
                                        'imdbID' => $imdbID,
                                        'Title' => $title,
                                        'Year' => $year,
                                        'Genre' => $genre,
                                        'Plot' => $plot,
                                        'Poster' => $poster,
                                        'imdbRating' => $imdbRating
                                    ]
                                ];
                                error_log("Movie Consumer: Movie details fetched from OMDb API and inserted into database for IMDb ID: $movie_id");
                            } else {
                                $response = ['status' => 'error', 'message' => 'Failed to insert movie into database.'];
                                error_log("Movie Consumer: Failed to insert movie into database for IMDb ID $movie_id: " . $insert_stmt->error);
                            }
                            $insert_stmt->close();
                        }
                    }
                }
            }
        }
    }

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(json_encode($response), ['correlation_id' => $msg->get('correlation_id')]);
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
?>
