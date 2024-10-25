<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');  // Load .env from the correct path
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Review Consumer: Starting review_consumer.php");

// Pull credentials from .env file
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'] ?? 'localhost';
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'] ?? '5672';
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'] ?? 'guest';
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'] ?? 'guest';
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';  // Internal database credentials
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME_MOVIE_REVIEWS = $_ENV['DB_NAME_MOVIE_REVIEWS'] ?? 'movie_reviews_db';
$DB_NAME_USER_AUTH = $_ENV['DB_NAME_USER_AUTH'] ?? 'user_auth';
$OMDB_API_KEY = $_ENV['OMDB_API_KEY'];
$OMDB_URL = $_ENV['OMDB_URL'] ?? 'http://www.omdbapi.com';

// Function to fetch user ID based on session token
function fetchUserIdByToken($session_token, $mysqli_auth) {
    error_log("Fetching user ID for session token: $session_token");
    $stmt = $mysqli_auth->prepare("SELECT id FROM users WHERE session_token = ?");
    $stmt->bind_param('s', $session_token);
    $stmt->execute();
    $stmt->bind_result($user_id);
    if ($stmt->fetch()) {
        error_log("User ID fetched: $user_id");
        return $user_id;
    } else {
        error_log("No user found for session token: $session_token");
    }
    return null;
}

// Function to add movie if missing in the database
function addMovieIfMissing($movie_id, $mysqli_reviews, $OMDB_API_KEY, $OMDB_URL) {
    if (!$movie_id) {
        error_log("Review Consumer: Invalid or missing movie ID.");
        return false;
    }

    // Fetch movie details from OMDb API
    $omdb_url = $OMDB_URL . '?i=' . urlencode($movie_id) . '&apikey=' . $OMDB_API_KEY;
    $omdb_response = file_get_contents($omdb_url);

    if ($omdb_response === false) {
        error_log("Review Consumer: Failed to fetch movie details from OMDb API for ID: $movie_id");
        return false;
    }

    $omdb_data = json_decode($omdb_response, true);

    if (isset($omdb_data['Title'])) {
        // Insert the movie into the local database
        $stmt = $mysqli_reviews->prepare("INSERT INTO movies (imdb_id, title, year, genre, plot, poster) VALUES (?, ?, ?, ?, ?, ?)");
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
                error_log("Review Consumer: Successfully inserted movie with ID: $movie_id into the database");
                return true;
            } else {
                error_log("Review Consumer: Failed to insert movie into database: " . $stmt->error);
            }
        } else {
            error_log("Review Consumer: SQL preparation error during movie insert: " . $mysqli_reviews->error);
        }
    } else {
        error_log("Review Consumer: Movie not found on OMDb API for ID: $movie_id");
    }

    return false;
}

$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

error_log("Review Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('review_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS, $DB_NAME_USER_AUTH, $OMDB_API_KEY, $OMDB_URL) {
    error_log("Review Consumer: Received message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $session_token = $data['session_token'] ?? null;
    $movie_id = $data['movie_id'] ?? null;
    $review_text = $data['review_text'] ?? null;
    $rating = $data['rating'] ?? null;

    // Log the values for debugging
    error_log("Review Consumer - Movie ID: $movie_id");
    error_log("Review Consumer - Session Token: $session_token");
    error_log("Review Consumer - Review Text: $review_text");
    error_log("Review Consumer - Rating: $rating");

    if (!$session_token || !$movie_id || !$review_text || !$rating) {
        $response = json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        error_log("Review Consumer: Missing required fields");
    } else {
        // Connect to `user_auth` database to fetch the user ID
        $mysqli_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);

        if ($mysqli_auth->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection to user_auth failed.']);
            error_log("Review Consumer: Database connection to user_auth failed: " . $mysqli_auth->connect_error);
        } else {
            // Fetch user ID using session token
            $user_id = fetchUserIdByToken($session_token, $mysqli_auth);
            $mysqli_auth->close();

            if (!$user_id) {
                $response = json_encode(['status' => 'error', 'message' => 'Invalid session token.']);
                error_log("Review Consumer: Invalid session token: $session_token");
            } else {
                // Connect to `movie_reviews_db` to check if the movie exists
                $mysqli_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);

                if ($mysqli_reviews->connect_error) {
                    $response = json_encode(['status' => 'error', 'message' => 'Database connection to movie_reviews_db failed.']);
                    error_log("Review Consumer: Database connection to movie_reviews_db failed: " . $mysqli_reviews->connect_error);
                } else {
                    // Check if the movie exists in the `movies` table
                    $stmt = $mysqli_reviews->prepare("SELECT imdb_id FROM movies WHERE imdb_id = ?");
                    $stmt->bind_param('s', $movie_id);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows == 0) {
                        // Movie not found, add it to the database
                        if (!addMovieIfMissing($movie_id, $mysqli_reviews, $OMDB_API_KEY, $OMDB_URL)) {
                            $response = json_encode(['status' => 'error', 'message' => 'Failed to add movie.']);
                            error_log("Review Consumer: Failed to add movie with ID: $movie_id");
                        }
                    }

                    // Insert the review into the reviews table
                    $stmt->close();
                    $stmt = $mysqli_reviews->prepare("INSERT INTO reviews (user_id, imdb_id, review_text, rating) VALUES (?, ?, ?, ?)");

                    if (!$stmt) {
                        $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error.']);
                        error_log("Review Consumer: SQL preparation error: " . $mysqli_reviews->error);
                    } else {
                        error_log("Review Consumer: Inserting review for user_id: $user_id, movie_id: $movie_id");
                        $stmt->bind_param('issi', $user_id, $movie_id, $review_text, $rating);

                        if ($stmt->execute()) {
                            $response = json_encode(['status' => 'success', 'message' => 'Review added successfully!']);
                            error_log("Review Consumer: Successfully added review for movie ID: $movie_id by user ID: $user_id");
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'SQL execution error.']);
                            error_log("Review Consumer: SQL execution error: " . $stmt->error);
                        }
                        $stmt->close();
                    }

                    $mysqli_reviews->close();
                }
            }
        }
    }

    // Reply with the response
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('review_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>

