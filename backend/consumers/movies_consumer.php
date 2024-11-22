<?php
// combined_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables from .env file located at /home/stanley
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
$dotenv->load();

// Pull environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'] ?? 'localhost';
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'] ?? 5672;
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'] ?? 'guest';
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'] ?? 'guest';

$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_NAME = $_ENV['DB_NAME'] ?? 'movie_db';
$DB_NAME_MOVIE_REVIEWS = $_ENV['DB_NAME_MOVIE_REVIEWS'] ?? 'movie_reviews_db';
$DB_NAME_USER_AUTH = $_ENV['DB_NAME_USER_AUTH'] ?? 'user_auth';

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

$channel = $connection->channel();

// Declare all queues
$channel->queue_declare('movie_queue', false, true, false, false);
$channel->queue_declare('recommendation_queue', false, true, false, false);
$channel->queue_declare('review_queue', false, true, false, false);
$channel->queue_declare('watchlist_queue', false, true, false, false);

/**
 * Callback for Movie Queue
 */
$movieCallback = function ($msg) use (
    $channel,
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME_MOVIE_REVIEWS
) {
    error_log("Movie Consumer: Received message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $action = $data['action'] ?? null;
    $movie_id = $data['movie_id'] ?? null;

    if ($action !== 'fetch_movie_details' || !$movie_id) {
        $response = ['status' => 'error', 'message' => 'Invalid request.'];
        error_log("Movie Consumer: Invalid action or missing movie_id.");
    } else {
        // Connect to the correct database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);

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
                    // Movie not found in the database, fetch from favorites_consumer via RabbitMQ
                    $omdb_data = fetchOmdbDataById($movie_id);

                    if (isset($omdb_data['error'])) {
                        $response = ['status' => 'error', 'message' => $omdb_data['error']];
                        error_log("Movie Consumer: " . $omdb_data['error'] . " for IMDb ID: $movie_id");
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
                                error_log("Movie Consumer: Movie details fetched via favorites_queue and inserted into database for IMDb ID: $movie_id");
                            } else {
                                $response = ['status' => 'error', 'message' => 'Failed to insert movie into database.'];
                                error_log("Movie Consumer: Failed to insert movie into database for IMDb ID $movie_id: " . $insert_stmt->error);
                            }
                            $insert_stmt->close();
                        }
                    }
                }
            }
            $mysqli->close();
        }
    }

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(
        json_encode($response),
        ['correlation_id' => $msg->get('correlation_id')]
    );
    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Callback for Recommendation Queue
 */
$recommendationCallback = function ($msg) use (
    $channel,
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME_MOVIE_REVIEWS,
    $DB_NAME_USER_AUTH
) {
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
                    $genre_conditions = [];
                    foreach ($preferred_genres as $genre) {
                        $genre_conditions[] = "m.genre LIKE CONCAT('%', ?, '%')";
                    }
                    $sql = "
                        SELECT DISTINCT m.imdb_id, m.title, m.year, m.genre, m.poster, m.rating 
                        FROM movies m
                        WHERE (" . implode(' OR ', $genre_conditions) . ")
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
                        $types = str_repeat('s', count($preferred_genres)) . 'iiii';
                        $offset = ($page - 1) * $movies_per_page;
                        $limit = $movies_per_page;

                        // Merge genres and other parameters
                        $bind_params = array_merge($preferred_genres, [$user_id, $user_id, $offset, $limit]);

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
                            WHERE (" . implode(' OR ', $genre_conditions) . ")
                            AND m.imdb_id NOT IN (
                                SELECT imdb_id FROM watchlist WHERE user_id = ?
                            )
                            AND m.imdb_id NOT IN (
                                SELECT imdb_id FROM reviews WHERE user_id = ?
                            )
                        ";

                        $count_stmt = $mysqli_reviews->prepare($count_sql);
                        if ($count_stmt) {
                            $count_types = str_repeat('s', count($preferred_genres)) . 'ii';
                            $count_bind_params = array_merge($preferred_genres, [$user_id, $user_id]);

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

                        // Since we cannot fetch additional movies from OMDb API, we will proceed with the movies we have
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
    $reply_msg = new AMQPMessage(
        json_encode($response),
        ['correlation_id' => $msg->get('correlation_id')]
    );
    error_log("Recommendation Consumer: Sent response for message ID " . $msg->get('correlation_id'));

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Callback for Review Queue
 */
$reviewCallback = function ($msg) use (
    $channel,
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME_MOVIE_REVIEWS,
    $DB_NAME_USER_AUTH
) {
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
                                if (!addMovieIfMissing($movie_id, $mysqli_reviews)) {
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

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Callback for Watchlist Queue
 */
$watchlistCallback = function ($msg) use (
    $channel,
    $DB_HOST,
    $DB_USER,
    $DB_PASS,
    $DB_NAME_MOVIE_REVIEWS,
    $DB_NAME_USER_AUTH
) {
    error_log("Watchlist Consumer: Received message: " . $msg->body);

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
                        // Movie does not exist in the movies table, fetch from favorites_consumer via RabbitMQ
                        $movie_info = fetchOmdbDataById($imdb_id);

                        if (isset($movie_info['error'])) {
                            // Failed to fetch movie data
                            $response = ['status' => 'error', 'message' => $movie_info['error']];
                            $check_movie_stmt->close();
                            $mysqli->close();
                            goto send_watchlist_response;
                        } else {
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
                                error_log("Watchlist Consumer: Error inserting movie: " . $insert_movie_stmt->error);
                                $response = ['status' => 'error', 'message' => 'Failed to insert movie into database.'];
                                $insert_movie_stmt->close();
                                $mysqli->close();
                                goto send_watchlist_response;
                            }

                            $insert_movie_stmt->close();
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
                    error_log("Watchlist Consumer: Favorites fetched: " . json_encode($favorites));

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

    send_watchlist_response:

    // Send response back via RabbitMQ
    $reply_msg = new AMQPMessage(json_encode($response), ['correlation_id' => $msg->get('correlation_id')]);
    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Helper Functions
 */

/**
 * Fetch User ID by Session Token
 */
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

/**
 * Add Movie If Missing in Database
 */
function addMovieIfMissing($movie_id, $mysqli_reviews) {
    if (!$movie_id) {
        error_log("Review Consumer: Invalid or missing movie ID.");
        return false;
    }

    $omdb_data = fetchOmdbDataById($movie_id);

    if (isset($omdb_data['error'])) {
        error_log("Review Consumer: " . $omdb_data['error'] . " for ID: $movie_id");
        return false;
    }

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
        error_log("Review Consumer: Movie not found via favorites_queue for ID: $movie_id");
    }

    return false;
}

/**
 * Fetch OMDb Data by IMDb ID via RabbitMQ
 */
function fetchOmdbDataById($imdb_id) {
    global $RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS;

    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();

    list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

    $corr_id = uniqid();
    $response = null;

    $callback = function ($msg) use (&$response, $corr_id) {
        if ($msg->get('correlation_id') == $corr_id) {
            $response = json_decode($msg->body, true);
        }
    };

    $channel->basic_consume($callback_queue, '', false, true, false, false, $callback);

    $request = ['id' => $imdb_id];

    $msg = new AMQPMessage(
        json_encode($request),
        [
            'correlation_id' => $corr_id,
            'reply_to' => $callback_queue
        ]
    );

    $channel->basic_publish($msg, '', 'favorites_queue');

    while (!$response) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();

    return $response;
}

// Register consumers for each queue with their respective callbacks
$channel->basic_consume('movie_queue', '', false, false, false, false, $movieCallback);
$channel->basic_consume('recommendation_queue', '', false, false, false, false, $recommendationCallback);
$channel->basic_consume('review_queue', '', false, false, false, false, $reviewCallback);
$channel->basic_consume('watchlist_queue', '', false, false, false, false, $watchlistCallback);

// Keep the consumer running
error_log("Combined Consumer: Waiting for messages...");
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
