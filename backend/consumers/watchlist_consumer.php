<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
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
$DB_NAME_MOVIE_REVIEWS = $_ENV['DB_NAME_MOVIE_REVIEWS'];
$DB_NAME_USER_AUTH = $_ENV['DB_NAME_USER_AUTH'];
$OMDB_API_KEY = $_ENV['OMDB_API_KEY'];
$OMDB_URL = $_ENV['OMDB_URL'];

$connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS);
$channel = $connection->channel();
$channel->queue_declare('watchlist_queue', false, true, false, false);

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

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS, $DB_NAME_USER_AUTH) {
    $data = json_decode($msg->body, true);
    $session_token = $data['session_token'] ?? null;
    $imdb_id = $data['imdb_id'] ?? null;
    $action = $data['action'] ?? null;

    if (!$session_token || !$action) {
        $response = ['status' => 'error', 'message' => 'Missing session token or action.'];
        error_log("Watchlist Consumer: Error - Missing required fields");
    } else {
        // Connect to user_auth database to fetch user_id
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
        if ($mysqli->connect_error) {
            $response = ['status' => 'error', 'message' => 'Database connection failed'];
        } else {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE session_token = ?");
            $stmt->bind_param('s', $session_token);
            $stmt->execute();
            $stmt->bind_result($user_id);

            if ($stmt->fetch() && $user_id) {
                $stmt->close();
                $mysqli->close();  // Close connection to user_auth database

                // Handle actions for adding, deleting, and retrieving watchlist
                if ($action === 'add_movie' && $imdb_id) {
                    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);

                    // Check if the movie exists in the movies table
                    $check_movie_stmt = $mysqli->prepare("SELECT 1 FROM movies WHERE imdb_id = ?");
                    $check_movie_stmt->bind_param('s', $imdb_id);
                    $check_movie_stmt->execute();
                    $check_movie_stmt->store_result();

                    if ($check_movie_stmt->num_rows === 0) {
                        // Movie does not exist in the movies table, fetch from OMDb API
                        $movie_info = fetchOmdbDataById($imdb_id);

                        if (isset($movie_info['Title'])) {
                            // Prepare movie data
                            $imdbID = $movie_info['imdbID'] ?? '';
                            $title = $movie_info['Title'] ?? '';
                            $year = $movie_info['Year'] ?? null;
                            $year = is_numeric($year) ? intval($year) : null; // Ensure year is an integer
                            $genre = $movie_info['Genre'] ?? '';
                            $poster = $movie_info['Poster'] ?? '';
                            $rating = $movie_info['imdbRating'] ?? '';

                            // Prepare the INSERT statement with only existing fields
                            $insert_movie_stmt = $mysqli->prepare("
                                INSERT INTO movies (imdb_id, title, year, genre, poster, rating)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");

                            // Bind parameters
                            $insert_movie_stmt->bind_param(
                                'ssisss',
                                $imdbID,
                                $title,
                                $year,
                                $genre,
                                $poster,
                                $rating
                            );

                            // Execute the statement
                            if (!$insert_movie_stmt->execute()) {
                                error_log("Error inserting movie: " . $insert_movie_stmt->error);
                                $response = ['status' => 'error', 'message' => 'Failed to insert movie into database.'];
                                $insert_movie_stmt->close();
                                $mysqli->close();
                                goto send_response;
                            }

                            $insert_movie_stmt->close();
                        } else {
                            // Failed to fetch movie data
                            $response = ['status' => 'error', 'message' => 'Failed to fetch movie data from OMDb API.'];
                            $check_movie_stmt->close();
                            $mysqli->close();
                            goto send_response;
                        }
                    }
                    $check_movie_stmt->close();

                    // Check if the movie is already in the watchlist
                    $check_watchlist_stmt = $mysqli->prepare("SELECT 1 FROM watchlist WHERE user_id = ? AND imdb_id = ?");
                    $check_watchlist_stmt->bind_param('is', $user_id, $imdb_id);
                    $check_watchlist_stmt->execute();
                    $check_watchlist_stmt->store_result();

                    if ($check_watchlist_stmt->num_rows === 0) {
                        // Movie is not in the watchlist, add it
                        $stmt = $mysqli->prepare("INSERT INTO watchlist (user_id, imdb_id) VALUES (?, ?)");
                        $stmt->bind_param('is', $user_id, $imdb_id);
                        $response = $stmt->execute() ? ['status' => 'success', 'message' => 'Movie added to watchlist.'] : ['status' => 'error', 'message' => 'Failed to add movie to watchlist.'];
                        $stmt->close();
                    } else {
                        $response = ['status' => 'error', 'message' => 'Movie is already in your watchlist.'];
                    }
                    $check_watchlist_stmt->close();
                    $mysqli->close();

                } elseif ($action === 'delete_movie' && $imdb_id) {
                    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);

                    // Remove movie from the watchlist
                    $stmt = $mysqli->prepare("DELETE FROM watchlist WHERE user_id = ? AND imdb_id = ?");
                    $stmt->bind_param('is', $user_id, $imdb_id);
                    $response = $stmt->execute() ? ['status' => 'success', 'message' => 'Movie removed from watchlist.'] : ['status' => 'error', 'message' => 'Failed to remove movie from watchlist.'];
                    $stmt->close();
                    $mysqli->close();

                } elseif ($action === 'get_watchlist') {
                    // Get the user's watchlist
                    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                    $stmt = $mysqli->prepare("SELECT DISTINCT imdb_id FROM watchlist WHERE user_id = ?");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    $favorites = [];
                    while ($row = $result->fetch_assoc()) {
                        $favorites[] = ['imdb_id' => $row['imdb_id']];
                    }
                    $stmt->close();
                    $mysqli->close();

                    // Log the fetched favorites for debugging
                    error_log("Favorites fetched: " . json_encode($favorites));

                    // Send back the list of favorites
                    $response = ['favorites' => $favorites];

                } else {
                    $response = ['status' => 'error', 'message' => 'Invalid action or missing IMDb ID.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Invalid session token'];
            }
        }
    }

    send_response:

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(json_encode($response), ['correlation_id' => $msg->get('correlation_id')]);
    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('watchlist_queue', '', false, false, false, false, $callback);
while ($channel->is_consuming()) {
    $channel->wait();
}
$channel->close();
$connection->close();
?>
