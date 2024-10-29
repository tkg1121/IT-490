<?php
// recommendation_consumer.php

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
$DB_NAME_MOVIE_REVIEWS = $_ENV['DB_NAME_MOVIE_REVIEWS'];
$DB_NAME_USER_AUTH = $_ENV['DB_NAME_USER_AUTH'];
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
$channel->queue_declare('recommendation_queue', false, true, false, false);

// Function to fetch user ID based on session token
function fetchUserIdByToken($session_token, $mysqli_auth) {
    error_log("Fetching user ID for session token: $session_token");
    $stmt = $mysqli_auth->prepare("SELECT id FROM users WHERE session_token = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $mysqli_auth->error);
        return null;
    }
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

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS, $DB_NAME_USER_AUTH, $OMDB_API_KEY, $OMDB_URL) {
    error_log("Recommendation Consumer: Received message: " . $msg->body);

    $response = ['status' => 'error', 'message' => 'An unexpected error occurred.'];

    $data = json_decode($msg->body, true);
    $action = $data['action'] ?? null;
    $session_token = $data['session_token'] ?? null;
    $page = isset($data['page']) ? intval($data['page']) : 1;
    $movies_per_page = isset($data['movies_per_page']) ? intval($data['movies_per_page']) : 1;

    if ($action !== 'get_recommendations' || !$session_token) {
        $response = ['status' => 'error', 'message' => 'Invalid request parameters.'];
        error_log("Recommendation Consumer: Invalid action or missing session_token.");
    } else {
        // Connect to user_auth database to fetch user_id
        $mysqli_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
        if ($mysqli_auth->connect_error) {
            $response = ['status' => 'error', 'message' => 'Database connection to user_auth failed.'];
            error_log("Recommendation Consumer: Database connection to user_auth failed: " . $mysqli_auth->connect_error);
        } else {
            $user_id = fetchUserIdByToken($session_token, $mysqli_auth);
            $mysqli_auth->close();

            if (!$user_id) {
                $response = ['status' => 'error', 'message' => 'Invalid session token.'];
                error_log("Recommendation Consumer: Invalid session token: $session_token");
            } else {
                // Connect to movie_reviews_db to fetch watchlist and reviews
                $mysqli_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                if ($mysqli_reviews->connect_error) {
                    $response = ['status' => 'error', 'message' => 'Database connection to movie_reviews_db failed.'];
                    error_log("Recommendation Consumer: Database connection to movie_reviews_db failed: " . $mysqli_reviews->connect_error);
                } else {
                    // Fetch user's preferred genres from watchlist and reviews
                    $genres = [];

                    // Fetch genres from watchlist
                    $stmt = $mysqli_reviews->prepare("
                        SELECT m.genre FROM watchlist w
                        JOIN movies m ON w.imdb_id = m.imdb_id
                        WHERE w.user_id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->bind_result($genre_list);

                        while ($stmt->fetch()) {
                            $genre_array = explode(', ', $genre_list);
                            $genres = array_merge($genres, $genre_array);
                        }
                        $stmt->close();
                    } else {
                        error_log("Recommendation Consumer: Failed to prepare watchlist genres statement: " . $mysqli_reviews->error);
                    }

                    // Fetch genres from reviews
                    $stmt = $mysqli_reviews->prepare("
                        SELECT m.genre FROM reviews r
                        JOIN movies m ON r.imdb_id = m.imdb_id
                        WHERE r.user_id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->bind_result($genre_list);

                        while ($stmt->fetch()) {
                            $genre_array = explode(', ', $genre_list);
                            $genres = array_merge($genres, $genre_array);
                        }
                        $stmt->close();
                    } else {
                        error_log("Recommendation Consumer: Failed to prepare review genres statement: " . $mysqli_reviews->error);
                    }

                    if (empty($genres)) {
                        // Default to popular genres if user has no data
                        $preferred_genres = ['Action', 'Adventure', 'Comedy'];
                        error_log("Recommendation Consumer: No genres found, defaulting to: " . implode(', ', $preferred_genres));
                    } else {
                        // Count the frequency of each genre
                        $genre_counts = array_count_values($genres);
                        arsort($genre_counts);
                        $preferred_genres = array_slice(array_keys($genre_counts), 0, 3); // Top 3 genres
                        error_log("Recommendation Consumer: Preferred genres: " . implode(', ', $preferred_genres));
                    }

                    // Get movies in preferred genres that the user hasn't seen or added to watchlist
                    // Prepare placeholders for genres
                    $genre_placeholders = implode(',', array_fill(0, count($preferred_genres), '?'));
                    $sql = "
                        SELECT DISTINCT m.imdb_id, m.title, m.year, m.genre, m.poster, m.rating 
                        FROM movies m
                        WHERE (";

                    $genre_conditions = [];
                    foreach ($preferred_genres as $genre) {
                        $genre_conditions[] = "m.genre LIKE CONCAT('%', ?, '%')";
                    }
                    $sql .= implode(' OR ', $genre_conditions);
                    $sql .= ")
                        AND m.imdb_id NOT IN (
                            SELECT imdb_id FROM watchlist WHERE user_id = ?
                        )
                        AND m.imdb_id NOT IN (
                            SELECT imdb_id FROM reviews WHERE user_id = ?
                        )
                        ORDER BY m.rating DESC
                        LIMIT ?, ?
                    ";

                    $stmt = $mysqli_reviews->prepare($sql);
                    if ($stmt) {
                        // Correct the type string to match the number of bind parameters
                        // 3 's' for genres, 2 'i' for user_id, 2 'i' for offset and limit
                        $types = str_repeat('s', count($preferred_genres)) . 'iiii'; // 'sssiiii'
                        $offset = ($page - 1) * $movies_per_page;
                        $limit = $movies_per_page;

                        // Merge genres and other parameters
                        $bind_params = array_merge($preferred_genres, [$user_id, $user_id, $offset, $limit]);

                        // Verify counts for debugging
                        $expected_types = strlen($types);
                        $actual_params = count($bind_params);
                        if ($expected_types !== $actual_params) {
                            error_log("Recommendation Consumer: Mismatch in bind_param counts. Types: $expected_types, Params: $actual_params");
                        }

                        // Bind parameters dynamically
                        $stmt->bind_param($types, ...$bind_params);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $movies = [];
                        while ($row = $result->fetch_assoc()) {
                            $movies[] = [
                                'imdbID' => $row['imdb_id'],
                                'Title' => $row['title'],
                                'Year' => $row['year'],
                                'Genre' => $row['genre'],
                                'Poster' => $row['poster'],
                                'imdbRating' => $row['rating']
                            ];
                        }
                        $stmt->close();

                        // Count total available recommendations for pagination
                        $count_sql = "
                            SELECT COUNT(DISTINCT m.imdb_id) AS total 
                            FROM movies m
                            WHERE (";

                        $count_sql .= implode(' OR ', $genre_conditions);
                        $count_sql .= ")
                            AND m.imdb_id NOT IN (
                                SELECT imdb_id FROM watchlist WHERE user_id = ?
                            )
                            AND m.imdb_id NOT IN (
                                SELECT imdb_id FROM reviews WHERE user_id = ?
                            )
                        ";

                        $count_stmt = $mysqli_reviews->prepare($count_sql);
                        if ($count_stmt) {
                            $count_types = str_repeat('s', count($preferred_genres)) . 'ii'; // 'sssii'
                            $count_bind_params = array_merge($preferred_genres, [$user_id, $user_id]);

                            // Verify counts for debugging
                            $expected_count_types = strlen($count_types);
                            $actual_count_params = count($count_bind_params);
                            if ($expected_count_types !== $actual_count_params) {
                                error_log("Recommendation Consumer: Mismatch in count bind_param counts. Types: $expected_count_types, Params: $actual_count_params");
                            }

                            $count_stmt->bind_param($count_types, ...$count_bind_params);
                            $count_stmt->execute();
                            $count_stmt->bind_result($total_recommendations);
                            $count_stmt->fetch();
                            $count_stmt->close();

                            // Calculate total pages based on total recommendations
                            $total_pages = ceil($total_recommendations / $movies_per_page);
                            $total_pages = $total_pages > 0 ? $total_pages : 1;

                            error_log("Recommendation Consumer: Total recommendations: $total_recommendations, Total pages: $total_pages");
                        } else {
                            $total_pages = 1; // Default to 1 if count query fails
                            error_log("Recommendation Consumer: Failed to prepare count statement: " . $mysqli_reviews->error);
                        }

                        // If not enough movies in the local database, fetch from OMDb API
                        if (count($movies) < $movies_per_page) {
                            $remaining = $movies_per_page - count($movies);
                            error_log("Recommendation Consumer: Not enough movies in database, fetching $remaining more from OMDb API.");

                            foreach ($preferred_genres as $genre) {
                                // OMDb API does not support genre-based search directly.
                                // We'll perform a general search and filter by genre.

                                // Example: Search for popular movies by genre keyword
                                $omdb_search_url = $OMDB_URL . '?apikey=' . $OMDB_API_KEY . '&type=movie&s=' . urlencode($genre) . '&page=1';
                                $omdb_search_response = file_get_contents($omdb_search_url);
                                $omdb_search_data = json_decode($omdb_search_response, true);

                                if (isset($omdb_search_data['Search'])) {
                                    foreach ($omdb_search_data['Search'] as $movie) {
                                        $imdb_id = $movie['imdbID'];

                                        // Fetch detailed movie data
                                        $omdb_details_url = $OMDB_URL . '?apikey=' . $OMDB_API_KEY . '&i=' . urlencode($imdb_id);
                                        $omdb_details_response = file_get_contents($omdb_details_url);
                                        $omdb_details = json_decode($omdb_details_response, true);

                                        if (isset($omdb_details['Genre'])) {
                                            $movie_genres = explode(', ', $omdb_details['Genre']);
                                            // Check if any of the movie's genres match the preferred genres
                                            $genre_match = false;
                                            foreach ($preferred_genres as $preferred_genre) {
                                                if (in_array($preferred_genre, $movie_genres)) {
                                                    $genre_match = true;
                                                    break;
                                                }
                                            }

                                            if ($genre_match) {
                                                // Check if the user hasn't already seen or added this movie
                                                $check_sql = "
                                                    SELECT 1 FROM watchlist WHERE user_id = ? AND imdb_id = ?
                                                    UNION
                                                    SELECT 1 FROM reviews WHERE user_id = ? AND imdb_id = ?
                                                ";
                                                $check_stmt = $mysqli_reviews->prepare($check_sql);
                                                if ($check_stmt) {
                                                    $check_stmt->bind_param('isis', $user_id, $imdb_id, $user_id, $imdb_id);
                                                    $check_stmt->execute();
                                                    $check_stmt->store_result();

                                                    if ($check_stmt->num_rows == 0) {
                                                        // Add to movies array
                                                        $movies[] = [
                                                            'imdbID' => $omdb_details['imdbID'] ?? '',
                                                            'Title' => $omdb_details['Title'] ?? 'Unknown Title',
                                                            'Year' => $omdb_details['Year'] ?? 'Unknown Year',
                                                            'Genre' => $omdb_details['Genre'] ?? 'Unknown Genre',
                                                            'Poster' => $omdb_details['Poster'] ?? 'default_poster.jpg',
                                                            'imdbRating' => $omdb_details['imdbRating'] ?? 'N/A'
                                                        ];

                                                        error_log("Recommendation Consumer: Fetched movie from OMDb: " . $omdb_details['Title']);

                                                        if (count($movies) >= $movies_per_page) {
                                                            break 2; // Exit both loops
                                                        }
                                                    }
                                                    $check_stmt->close();
                                                } else {
                                                    error_log("Recommendation Consumer: Failed to prepare check statement: " . $mysqli_reviews->error);
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    error_log("Recommendation Consumer: No search results from OMDb API for genre: $genre");
                                }

                                // If we've fetched enough movies, stop
                                if (count($movies) >= $movies_per_page) {
                                    break;
                                }
                            }
                        }

                        // Calculate total_pages (if not set by count query)
                        if (!isset($total_pages)) {
                            $total_pages = 10; // Default value
                        }

                        $response = [
                            'status' => 'success',
                            'movies' => $movies,
                            'total_pages' => $total_pages
                        ];

                        error_log("Recommendation Consumer: Successfully fetched recommendations for user ID: $user_id");
                    } else {
                        $response = ['status' => 'error', 'message' => 'Failed to prepare recommendation query.'];
                        error_log("Recommendation Consumer: Failed to prepare recommendation query: " . $mysqli_reviews->error);
                    }

                    $mysqli_reviews->close();
                }
            }
        }
    }

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(json_encode($response), ['correlation_id' => $msg->get('correlation_id')]);
    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    error_log("Recommendation Consumer: Sent response for message ID " . $msg->get('correlation_id'));

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

$channel->basic_consume('recommendation_queue', '', false, false, false, false, $callback);

// Keep the consumer running
error_log("Recommendation Consumer: Waiting for messages...");
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>