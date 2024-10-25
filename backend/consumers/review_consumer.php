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
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
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
        $stmt->close();
        return $user_id;
    } else {
        error_log("No user found for session token: $session_token");
        $stmt->close();
    }
    return null;
}

// Function to add movie if missing in the database
function addMovieIfMissing($movie_id, $mysqli_reviews, $OMDB_API_KEY, $OMDB_URL) {
    if (!$movie_id) {
        error_log("Review Consumer: Invalid or missing movie ID.");
        return false;
    }

    $omdb_url = $OMDB_URL . '?i=' . urlencode($movie_id) . '&apikey=' . $OMDB_API_KEY;
    $omdb_response = file_get_contents($omdb_url);

    if ($omdb_response === false) {
        error_log("Review Consumer: Failed to fetch movie details from OMDb API for ID: $movie_id");
        return false;
    }

    $omdb_data = json_decode($omdb_response, true);

    if (isset($omdb_data['Title'])) {
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
                $stmt->close();
                return true;
            } else {
                error_log("Review Consumer: Failed to insert movie into database: " . $stmt->error);
                $stmt->close();
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

    $response = ''; // Initialize response variable

    $data = json_decode($msg->body, true);
    $action = $data['action'] ?? null;
    $movie_id = $data['movie_id'] ?? null;

    // Handle different actions
    if ($action === 'add_review') {
        $session_token = $data['session_token'] ?? null;
        $review_text = $data['review_text'] ?? null;
        $rating = $data['rating'] ?? null;

        if (!$session_token || !$movie_id || !$review_text || !$rating) {
            $response = json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
            error_log("Review Consumer: Missing required fields");
        } else {
            $mysqli_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
            if ($mysqli_auth->connect_error) {
                $response = json_encode(['status' => 'error', 'message' => 'Database connection to user_auth failed.']);
                error_log("Review Consumer: Database connection to user_auth failed: " . $mysqli_auth->connect_error);
            } else {
                $user_id = fetchUserIdByToken($session_token, $mysqli_auth);
                $mysqli_auth->close();

                if (!$user_id) {
                    $response = json_encode(['status' => 'error', 'message' => 'Invalid session token.']);
                    error_log("Review Consumer: Invalid session token: $session_token");
                } else {
                    $mysqli_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                    if ($mysqli_reviews->connect_error) {
                        $response = json_encode(['status' => 'error', 'message' => 'Database connection to movie_reviews_db failed.']);
                        error_log("Review Consumer: Database connection to movie_reviews_db failed: " . $mysqli_reviews->connect_error);
                    } else {
                        $stmt = $mysqli_reviews->prepare("SELECT imdb_id FROM movies WHERE imdb_id = ?");
                        if ($stmt) {
                            $stmt->bind_param('s', $movie_id);
                            $stmt->execute();
                            $stmt->store_result();

                            if ($stmt->num_rows == 0) {
                                if (!addMovieIfMissing($movie_id, $mysqli_reviews, $OMDB_API_KEY, $OMDB_URL)) {
                                    $response = json_encode(['status' => 'error', 'message' => 'Failed to add movie.']);
                                    error_log("Review Consumer: Failed to add movie with ID: $movie_id");
                                }
                            }

                            $stmt->close();
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'Failed to prepare statement for checking movie existence.']);
                            error_log("Review Consumer: Failed to prepare statement: " . $mysqli_reviews->error);
                        }

                        if (empty($response)) {
                            $stmt = $mysqli_reviews->prepare("INSERT INTO reviews (user_id, imdb_id, review_text, rating) VALUES (?, ?, ?, ?)");
                            if ($stmt) {
                                $stmt->bind_param('issi', $user_id, $movie_id, $review_text, $rating);
                                if ($stmt->execute()) {
                                    $response = json_encode(['status' => 'success', 'message' => 'Review added successfully!']);
                                    error_log("Review Consumer: Successfully added review for movie ID: $movie_id by user ID: $user_id");
                                } else {
                                    $response = json_encode(['status' => 'error', 'message' => 'SQL execution error.']);
                                    error_log("Review Consumer: SQL execution error: " . $stmt->error);
                                }
                                $stmt->close();
                            } else {
                                $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error.']);
                                error_log("Review Consumer: SQL preparation error: " . $mysqli_reviews->error);
                            }
                        }

                        $mysqli_reviews->close();
                    }
                }
            }
        }
    } elseif ($action === 'fetch_movie_reviews') {
        if (!$movie_id) {
            $response = json_encode(['status' => 'error', 'message' => 'Missing movie ID.']);
            error_log("Review Consumer: Missing movie ID for fetching reviews");
        } else {
            $mysqli_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
            if ($mysqli_reviews->connect_error) {
                $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                error_log("Review Consumer: Database connection failed: " . $mysqli_reviews->connect_error);
            } else {
                $stmt = $mysqli_reviews->prepare("SELECT review_id, user_id, review_text, rating FROM reviews WHERE imdb_id = ?");
                if ($stmt) {
                    $stmt->bind_param('s', $movie_id);
                    $stmt->execute();
                    $stmt->bind_result($review_id, $user_id, $review_text, $rating);

                    $temp_reviews = [];
                    while ($stmt->fetch()) {
                        $temp_reviews[] = [
                            'review_id' => $review_id,
                            'user_id' => $user_id,
                            'review_text' => $review_text,
                            'rating' => $rating
                        ];
                    }
                    $stmt->close();

                    $reviews = [];
                    foreach ($temp_reviews as $temp_review) {
                        $user_id = $temp_review['user_id'];
                        $review_id = $temp_review['review_id'];

                        // Get username from the user_auth database
                        $mysqli_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
                        if ($mysqli_auth->connect_error) {
                            error_log("Review Consumer: Failed to connect to user_auth database: " . $mysqli_auth->connect_error);
                            $username = 'Unknown';
                        } else {
                            $user_stmt = $mysqli_auth->prepare("SELECT username FROM users WHERE id = ?");
                            if ($user_stmt) {
                                $user_stmt->bind_param('i', $user_id);
                                $user_stmt->execute();
                                $user_stmt->bind_result($username);
                                if (!$user_stmt->fetch()) {
                                    $username = 'Unknown';
                                }
                                $user_stmt->close();
                            } else {
                                error_log("Review Consumer: Failed to prepare statement for fetching username: " . $mysqli_auth->error);
                                $username = 'Unknown';
                            }
                            $mysqli_auth->close();
                        }

                        // Fetch like and dislike counts
                        $like_count = 0;
                        $dislike_count = 0;

                        $like_stmt = $mysqli_reviews->prepare("SELECT COUNT(*) FROM review_likes_dislikes WHERE review_id = ? AND like_dislike = 'like'");
                        if ($like_stmt) {
                            $like_stmt->bind_param('i', $review_id);
                            $like_stmt->execute();
                            $like_stmt->bind_result($like_count);
                            $like_stmt->fetch();
                            $like_stmt->close();
                        } else {
                            error_log("Review Consumer: Failed to prepare statement for like count: " . $mysqli_reviews->error);
                        }

                        $dislike_stmt = $mysqli_reviews->prepare("SELECT COUNT(*) FROM review_likes_dislikes WHERE review_id = ? AND like_dislike = 'dislike'");
                        if ($dislike_stmt) {
                            $dislike_stmt->bind_param('i', $review_id);
                            $dislike_stmt->execute();
                            $dislike_stmt->bind_result($dislike_count);
                            $dislike_stmt->fetch();
                            $dislike_stmt->close();
                        } else {
                            error_log("Review Consumer: Failed to prepare statement for dislike count: " . $mysqli_reviews->error);
                        }

                        $reviews[] = [
                            'review_id' => $review_id,
                            'username' => $username,
                            'review_text' => $temp_review['review_text'],
                            'rating' => $temp_review['rating'],
                            'like_count' => $like_count,
                            'dislike_count' => $dislike_count
                        ];
                    }

                    $mysqli_reviews->close();

                    $response = empty($reviews) ? json_encode(['status' => 'error', 'message' => 'No reviews found']) :
                        json_encode(['status' => 'success', 'reviews' => $reviews]);
                } else {
                    $response = json_encode(['status' => 'error', 'message' => 'Failed to prepare statement for fetching reviews.']);
                    error_log("Review Consumer: Failed to prepare statement: " . $mysqli_reviews->error);
                    $mysqli_reviews->close();
                }
            }
        }
    } elseif ($action === 'like_review' || $action === 'dislike_review') {
        $session_token = $data['session_token'] ?? null;
        $review_id = $data['review_id'] ?? null;
        $like_status = $action === 'like_review' ? 'like' : 'dislike';

        if (!$session_token || !$review_id) {
            $response = json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            error_log("Review Consumer: Missing required fields for like/dislike");
        } else {
            $mysqli_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
            if ($mysqli_auth->connect_error) {
                $response = json_encode(['status' => 'error', 'message' => 'Database connection to user_auth failed.']);
                error_log("Review Consumer: Database connection to user_auth failed: " . $mysqli_auth->connect_error);
            } else {
                $user_id = fetchUserIdByToken($session_token, $mysqli_auth);
                $mysqli_auth->close();

                if (!$user_id) {
                    $response = json_encode(['status' => 'error', 'message' => 'Invalid session token']);
                    error_log("Review Consumer: Invalid session token");
                } else {
                    $mysqli_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);

                    if ($mysqli_reviews->connect_error) {
                        $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                        error_log("Review Consumer: Database connection to movie_reviews_db failed");
                    } else {
                        $stmt_check = $mysqli_reviews->prepare("SELECT 1 FROM reviews WHERE review_id = ?");
                        if ($stmt_check) {
                            $stmt_check->bind_param('i', $review_id);
                            $stmt_check->execute();
                            $stmt_check->store_result();

                            if ($stmt_check->num_rows == 0) {
                                $response = json_encode(['status' => 'error', 'message' => 'Invalid review ID']);
                                error_log("Review Consumer: Invalid review ID: $review_id");
                            } else {
                                $stmt = $mysqli_reviews->prepare("
                                    INSERT INTO review_likes_dislikes (user_id, review_id, like_dislike)
                                    VALUES (?, ?, ?)
                                    ON DUPLICATE KEY UPDATE like_dislike = ?
                                ");
                                if ($stmt) {
                                    $stmt->bind_param('iiss', $user_id, $review_id, $like_status, $like_status);

                                    if ($stmt->execute()) {
                                        $response = json_encode(['status' => 'success', 'message' => ucfirst($like_status) . ' added successfully']);
                                        error_log("Review Consumer: Successfully added $like_status for review ID: $review_id by user ID: $user_id");
                                    } else {
                                        $response = json_encode(['status' => 'error', 'message' => 'Failed to update like/dislike']);
                                        error_log("Review Consumer: Failed to update like/dislike: " . $stmt->error);
                                    }
                                    $stmt->close();
                                } else {
                                    $response = json_encode(['status' => 'error', 'message' => 'Failed to prepare statement for updating like/dislike.']);
                                    error_log("Review Consumer: Failed to prepare statement: " . $mysqli_reviews->error);
                                }
                            }

                            $stmt_check->close();
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'Failed to prepare statement for checking review ID.']);
                            error_log("Review Consumer: Failed to prepare statement: " . $mysqli_reviews->error);
                        }

                        $mysqli_reviews->close();
                    }
                }
            }
        }
    } else {
        $response = json_encode(['status' => 'error', 'message' => 'Invalid action']);
        error_log("Review Consumer: Invalid action received");
    }

    // Ensure $response is defined before sending
    if (empty($response)) {
        $response = json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
        error_log("Review Consumer: Response was not set, sending default error response.");
    }

    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('review_queue', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
