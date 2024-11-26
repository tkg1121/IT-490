<?php
// auth_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php'); // Adjust the path as necessary

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables from .env file located at /home/stanley
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
$dotenv->load();

// Retrieve environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME_USER_AUTH = $_ENV['DB_NAME_USER_AUTH'];
$DB_NAME_MOVIE_REVIEWS = $_ENV['DB_NAME_MOVIE_REVIEWS'];
$DB_NAME_SOCIAL_MEDIA = $_ENV['DB_NAME_SOCIAL_MEDIA'];

// Set default timezone
date_default_timezone_set('UTC');

// Initialize $channel as null
$channel = null;

/**
 * Signup Callback
 */
$signup_callback = function ($msg) use (&$channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH) {
    error_log("Processing signup message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $email = $data['email'] ?? null;

    if (!$username || !$password || !$email) {
        $response = json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        error_log("Signup error: Missing required fields");
    } else {
        // Connect to user_auth database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $mysqli->connect_error]);
            error_log("Signup error: Database connection failed - " . $mysqli->connect_error);
        } else {
            // Check if username or email already exists
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            if (!$stmt) {
                $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli->error]);
                error_log("Signup error: Prepare statement failed - " . $mysqli->error);
            } else {
                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $response = json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
                    error_log("Signup error: Username or email already exists");
                } else {
                    $stmt->close();
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    // Insert user into database
                    $stmt = $mysqli->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli->error]);
                        error_log("Signup error: Prepare insert statement failed - " . $mysqli->error);
                    } else {
                        $stmt->bind_param('sss', $username, $hashed_password, $email);
                        if ($stmt->execute()) {
                            $response = json_encode(['status' => 'success', 'message' => 'User registered successfully']);
                            error_log("Signup success: User '$username' registered successfully");
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'Failed to register user']);
                            error_log("Signup error: Failed to execute insert statement - " . $stmt->error);
                        }
                        $stmt->close();
                    }
                }
                $stmt->close();
            }
            $mysqli->close();
        }
    }

    // Send the response back via RabbitMQ
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

    // Acknowledge the message
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Login Callback
 */
$login_callback = function ($msg) use (&$channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH) {
    error_log("Processing login message: " . $msg->body);

    $data = json_decode($msg->body, true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (!$username || !$password) {
        $response = json_encode(['status' => 'error', 'message' => 'Missing username or password']);
        error_log("Login error: Missing username or password");
    } else {
        // Connect to user_auth database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);
        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $mysqli->connect_error]);
            error_log("Login error: Database connection failed - " . $mysqli->connect_error);
        } else {
            // Fetch user data
            $stmt = $mysqli->prepare("SELECT id, password, email FROM users WHERE username = ?");
            if (!$stmt) {
                $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli->error]);
                error_log("Login error: Prepare statement failed - " . $mysqli->error);
            } else {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->bind_result($user_id, $hashed_password, $email);
                if ($stmt->fetch()) {
                    // Verify password
                    if (password_verify($password, $hashed_password)) {
                        // Generate session token
                        $session_token = bin2hex(random_bytes(32));

                        // Store session token in the database
                        $stmt->close();
                        $stmt = $mysqli->prepare("UPDATE users SET session_token = ? WHERE id = ?");
                        if (!$stmt) {
                            $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli->error]);
                            error_log("Login error: Prepare update statement failed - " . $mysqli->error);
                        } else {
                            $stmt->bind_param('si', $session_token, $user_id);
                            if ($stmt->execute()) {
                                // Respond indicating successful login and provide session token
                                $response = json_encode(['status' => 'success', 'session_token' => $session_token]);
                                error_log("Login success: User '$username' logged in successfully");
                            } else {
                                $response = json_encode(['status' => 'error', 'message' => 'Failed to set session token']);
                                error_log("Login error: Failed to execute update statement - " . $stmt->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        // Invalid password
                        $response = json_encode(['status' => 'error', 'message' => 'Incorrect password']);
                        error_log("Login error: Incorrect password for user '$username'");
                        $stmt->close();
                    }
                } else {
                    // Username not found
                    $response = json_encode(['status' => 'error', 'message' => 'Username not found']);
                    error_log("Login error: Username '$username' not found");
                    $stmt->close();
                }
            }
            $mysqli->close();
        }
    }

    // Send the response back via RabbitMQ
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

    // Acknowledge the message
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Profile Callback
 */
$profile_callback = function ($msg) use (&$channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH, $DB_NAME_MOVIE_REVIEWS, $DB_NAME_SOCIAL_MEDIA) {
    $data = json_decode($msg->body, true);

    if (!isset($data['action'])) {
        $response = json_encode(['status' => 'error', 'message' => 'No action specified']);
        error_log("Profile error: No action specified in message");
    } else {
        $action = $data['action'];

        if ($action === 'fetch_profile') {
            $session_token = $data['session_token'] ?? null;

            if (!$session_token) {
                $response = json_encode(['status' => 'error', 'message' => 'Missing session token']);
                error_log("Profile error: Missing session token");
            } else {
                // Connect to user_auth database
                $mysqli_user_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);

                if ($mysqli_user_auth->connect_error) {
                    $response = json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $mysqli_user_auth->connect_error]);
                    error_log("Profile error: Database connection failed - " . $mysqli_user_auth->connect_error);
                } else {
                    // Fetch user ID and username based on session token
                    $stmt = $mysqli_user_auth->prepare("SELECT id, username FROM users WHERE session_token = ?");
                    if (!$stmt) {
                        $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli_user_auth->error]);
                        error_log("Profile error: Prepare statement failed - " . $mysqli_user_auth->error);
                    } else {
                        $stmt->bind_param('s', $session_token);
                        $stmt->execute();
                        $stmt->bind_result($user_id, $username);
                        if ($stmt->fetch()) {
                            $stmt->close();

                            $profile_data = ['status' => 'success', 'username' => $username];

                            // Fetch watchlist from movie_reviews_db
                            $mysqli_movie_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                            if ($mysqli_movie_reviews->connect_error) {
                                $profile_data['watchlist'] = [];
                                error_log("Profile warning: movie_reviews_db connection failed - " . $mysqli_movie_reviews->connect_error);
                            } else {
                                $stmt_watchlist = $mysqli_movie_reviews->prepare("SELECT imdb_id FROM watchlist WHERE user_id=?");
                                if ($stmt_watchlist) {
                                    $stmt_watchlist->bind_param('i', $user_id);
                                    $stmt_watchlist->execute();
                                    $stmt_watchlist->bind_result($imdb_id);
                                    $watchlist = [];
                                    while ($stmt_watchlist->fetch()) {
                                        $watchlist[] = $imdb_id;
                                    }
                                    $stmt_watchlist->close();
                                    $profile_data['watchlist'] = $watchlist;
                                } else {
                                    $profile_data['watchlist'] = [];
                                    error_log("Profile warning: Prepare watchlist statement failed - " . $mysqli_movie_reviews->error);
                                }
                                $mysqli_movie_reviews->close();
                            }

                            // Fetch reviews from movie_reviews_db
                            $mysqli_movie_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                            if ($mysqli_movie_reviews->connect_error) {
                                $profile_data['reviews'] = [];
                                error_log("Profile warning: movie_reviews_db connection failed - " . $mysqli_movie_reviews->connect_error);
                            } else {
                                $stmt_reviews = $mysqli_movie_reviews->prepare("SELECT imdb_id, review_text, rating FROM reviews WHERE user_id=?");
                                if ($stmt_reviews) {
                                    $stmt_reviews->bind_param('i', $user_id);
                                    $stmt_reviews->execute();
                                    $stmt_reviews->bind_result($imdb_id, $review_text, $rating);
                                    $reviews = [];
                                    while ($stmt_reviews->fetch()) {
                                        $reviews[] = [
                                            'imdb_id' => $imdb_id,
                                            'review_text' => $review_text,
                                            'rating' => $rating
                                        ];
                                    }
                                    $stmt_reviews->close();
                                    $profile_data['reviews'] = $reviews;
                                } else {
                                    $profile_data['reviews'] = [];
                                    error_log("Profile warning: Prepare reviews statement failed - " . $mysqli_movie_reviews->error);
                                }
                                $mysqli_movie_reviews->close();
                            }

                            // Fetch social posts from social_media database
                            $mysqli_social_media = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_SOCIAL_MEDIA);
                            if ($mysqli_social_media->connect_error) {
                                $profile_data['social_posts'] = [];
                                error_log("Profile warning: social_media connection failed - " . $mysqli_social_media->connect_error);
                            } else {
                                $stmt_posts = $mysqli_social_media->prepare("SELECT post_text, created_at FROM posts WHERE user_id=?");
                                if ($stmt_posts) {
                                    $stmt_posts->bind_param('i', $user_id);
                                    $stmt_posts->execute();
                                    $stmt_posts->bind_result($post_text, $created_at);
                                    $social_posts = [];
                                    while ($stmt_posts->fetch()) {
                                        $social_posts[] = [
                                            'post_text' => $post_text,
                                            'created_at' => $created_at
                                        ];
                                    }
                                    $stmt_posts->close();
                                    $profile_data['social_posts'] = $social_posts;
                                } else {
                                    $profile_data['social_posts'] = [];
                                    error_log("Profile warning: Prepare social_posts statement failed - " . $mysqli_social_media->error);
                                }
                                $mysqli_social_media->close();
                            }

                            $response = json_encode($profile_data);
                            error_log("Profile success: Fetched profile for '$username'");
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'Invalid session token']);
                            error_log("Profile error: Invalid session token '$session_token'");
                            $stmt->close();
                        }
                    }
                    $mysqli_user_auth->close();
                }
            }

            // Send the response back via RabbitMQ
            $reply_msg = new AMQPMessage(
                $response,
                ['correlation_id' => $msg->get('correlation_id')]
            );

            $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

            // Acknowledge the message
            $channel->basic_ack($msg->delivery_info['delivery_tag']);

        } elseif ($action === 'fetch_friend_profile') {
            $session_token = $data['session_token'] ?? null;
            $friend_username = $data['friend_username'] ?? null;

            error_log("Received fetch_friend_profile action with session_token: $session_token, friend_username: $friend_username");

            if (!$session_token || !$friend_username) {
                $response = json_encode(['status' => 'error', 'message' => 'Missing session token or friend username']);
                error_log("Profile error: Missing session token or friend username");
            } else {
                // Connect to user_auth database
                $mysqli_user_auth = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_USER_AUTH);

                if ($mysqli_user_auth->connect_error) {
                    $response = json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $mysqli_user_auth->connect_error]);
                    error_log("Profile error: Database connection failed - " . $mysqli_user_auth->connect_error);
                } else {
                    // Fetch user ID based on session token
                    $stmt = $mysqli_user_auth->prepare("SELECT id FROM users WHERE session_token = ?");
                    if (!$stmt) {
                        $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli_user_auth->error]);
                        error_log("Profile error: Prepare statement failed - " . $mysqli_user_auth->error);
                    } else {
                        $stmt->bind_param('s', $session_token);
                        $stmt->execute();
                        $stmt->bind_result($user_id);
                        if ($stmt->fetch()) {
                            $stmt->close();

                            // Fetch friend ID
                            $stmt = $mysqli_user_auth->prepare("SELECT id FROM users WHERE username = ?");
                            if (!$stmt) {
                                $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli_user_auth->error]);
                                error_log("Profile error: Prepare friend ID statement failed - " . $mysqli_user_auth->error);
                            } else {
                                $stmt->bind_param('s', $friend_username);
                                $stmt->execute();
                                $stmt->bind_result($friend_id);
                                if ($stmt->fetch()) {
                                    $stmt->close();

                                    // Check if they are friends
                                    $stmt = $mysqli_user_auth->prepare("
                                        SELECT id FROM friends WHERE
                                        ((user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?))
                                        AND status='accepted'
                                    ");
                                    if (!$stmt) {
                                        $response = json_encode(['status' => 'error', 'message' => 'Database error: ' . $mysqli_user_auth->error]);
                                        error_log("Profile error: Prepare friends check statement failed - " . $mysqli_user_auth->error);
                                    } else {
                                        $stmt->bind_param('iiii', $user_id, $friend_id, $friend_id, $user_id);
                                        $stmt->execute();
                                        $stmt->store_result();
                                        if ($stmt->num_rows > 0) {
                                            $stmt->close();

                                            $profile_data = ['status' => 'success', 'username' => $friend_username];

                                            // Fetch friend's watchlist from movie_reviews_db
                                            $mysqli_movie_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                                            if ($mysqli_movie_reviews->connect_error) {
                                                $profile_data['watchlist'] = [];
                                                error_log("Profile warning: movie_reviews_db connection failed - " . $mysqli_movie_reviews->connect_error);
                                            } else {
                                                $stmt_watchlist = $mysqli_movie_reviews->prepare("SELECT imdb_id FROM watchlist WHERE user_id=?");
                                                if ($stmt_watchlist) {
                                                    $stmt_watchlist->bind_param('i', $friend_id);
                                                    $stmt_watchlist->execute();
                                                    $stmt_watchlist->bind_result($imdb_id);
                                                    $watchlist = [];
                                                    while ($stmt_watchlist->fetch()) {
                                                        $watchlist[] = $imdb_id;
                                                    }
                                                    $stmt_watchlist->close();
                                                    $profile_data['watchlist'] = $watchlist;
                                                } else {
                                                    $profile_data['watchlist'] = [];
                                                    error_log("Profile warning: Prepare watchlist statement failed - " . $mysqli_movie_reviews->error);
                                                }
                                                $mysqli_movie_reviews->close();
                                            }

                                            // Fetch friend's reviews from movie_reviews_db
                                            $mysqli_movie_reviews = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_MOVIE_REVIEWS);
                                            if ($mysqli_movie_reviews->connect_error) {
                                                $profile_data['reviews'] = [];
                                                error_log("Profile warning: movie_reviews_db connection failed - " . $mysqli_movie_reviews->connect_error);
                                            } else {
                                                $stmt_reviews = $mysqli_movie_reviews->prepare("SELECT imdb_id, review_text, rating FROM reviews WHERE user_id=?");
                                                if ($stmt_reviews) {
                                                    $stmt_reviews->bind_param('i', $friend_id);
                                                    $stmt_reviews->execute();
                                                    $stmt_reviews->bind_result($imdb_id, $review_text, $rating);
                                                    $reviews = [];
                                                    while ($stmt_reviews->fetch()) {
                                                        $reviews[] = [
                                                            'imdb_id' => $imdb_id,
                                                            'review_text' => $review_text,
                                                            'rating' => $rating
                                                        ];
                                                    }
                                                    $stmt_reviews->close();
                                                    $profile_data['reviews'] = $reviews;
                                                } else {
                                                    $profile_data['reviews'] = [];
                                                    error_log("Profile warning: Prepare reviews statement failed - " . $mysqli_movie_reviews->error);
                                                }
                                                $mysqli_movie_reviews->close();
                                            }

                                            // Fetch friend's social posts from social_media database
                                            $mysqli_social_media = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_SOCIAL_MEDIA);
                                            if ($mysqli_social_media->connect_error) {
                                                $profile_data['social_posts'] = [];
                                                error_log("Profile warning: social_media connection failed - " . $mysqli_social_media->connect_error);
                                            } else {
                                                $stmt_posts = $mysqli_social_media->prepare("SELECT post_text, created_at FROM posts WHERE user_id=?");
                                                if ($stmt_posts) {
                                                    $stmt_posts->bind_param('i', $friend_id);
                                                    $stmt_posts->execute();
                                                    $stmt_posts->bind_result($post_text, $created_at);
                                                    $social_posts = [];
                                                    while ($stmt_posts->fetch()) {
                                                        $social_posts[] = [
                                                            'post_text' => $post_text,
                                                            'created_at' => $created_at
                                                        ];
                                                    }
                                                    $stmt_posts->close();
                                                    $profile_data['social_posts'] = $social_posts;
                                                } else {
                                                    $profile_data['social_posts'] = [];
                                                    error_log("Profile warning: Prepare social_posts statement failed - " . $mysqli_social_media->error);
                                                }
                                                $mysqli_social_media->close();
                                            }

                                            $response = json_encode($profile_data);
                                            error_log("Profile success: Fetched friend profile for '$friend_username'");

                                        } else {
                                            $stmt->close();
                                            $response = json_encode(['status' => 'error', 'message' => 'You are not friends with this user']);
                                            error_log("Profile error: User '$user_id' is not friends with '$friend_id'");
                                        }
                                    }
                                } else {
                                    $stmt->close();
                                    $response = json_encode(['status' => 'error', 'message' => 'Friend username not found']);
                                    error_log("Profile error: Friend username '$friend_username' not found");
                                }
                            }
                        } else {
                            $stmt->close();
                            $response = json_encode(['status' => 'error', 'message' => 'Invalid session token']);
                            error_log("Profile error: Invalid session token '$session_token'");
                        }
                    }
                    $mysqli_user_auth->close();
                }
            }

            // Send the response back via RabbitMQ
            $reply_msg = new AMQPMessage(
                $response,
                ['correlation_id' => $msg->get('correlation_id')]
            );

            $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

            // Acknowledge the message
            $channel->basic_ack($msg->delivery_info['delivery_tag']);

        } else {
            $response = json_encode(['status' => 'error', 'message' => 'Unknown action']);
            error_log("Profile error: Unknown action '$action'");

            // Send the response back via RabbitMQ
            $reply_msg = new AMQPMessage(
                $response,
                ['correlation_id' => $msg->get('correlation_id')]
            );

            $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

            // Acknowledge the message
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }
};

/**
 * Function to send notification via RabbitMQ
 */
function sendNotification($notificationData) {
    global $RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS;

    try {
        $connection = new AMQPStreamConnection(
            $RABBITMQ_HOST,
            $RABBITMQ_PORT,
            $RABBITMQ_USER,
            $RABBITMQ_PASS
        );
        $channel = $connection->channel();
        $channel->queue_declare('notify_queue', false, true, false, false);

        $msg = new AMQPMessage(json_encode($notificationData), ['delivery_mode' => 2]);
        $channel->basic_publish($msg, '', 'notify_queue');

        $channel->close();
        $connection->close();

        error_log("Notification sent to '{$notificationData['email']}' with subject '{$notificationData['subject']}'");
    } catch (Exception $e) {
        error_log("Failed to send notification: " . $e->getMessage());
    }
}

try {
    // Establish RabbitMQ connection
    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();

    // Declare the queues
    $channel->queue_declare('signup_queue', false, true, false, false);
    $channel->queue_declare('login_queue', false, true, false, false);
    $channel->queue_declare('profile_queue', false, true, false, false);

    // Register consumers for each queue with their respective callbacks
    $channel->basic_consume('signup_queue', '', false, false, false, false, $signup_callback);
    $channel->basic_consume('login_queue', '', false, false, false, false, $login_callback);
    $channel->basic_consume('profile_queue', '', false, false, false, false, $profile_callback);

    error_log("auth_consumer.php is now waiting for messages...");

    // Keep the consumer running
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();

} catch (Exception $e) {
    error_log("Exception in auth_consumer.php: " . $e->getMessage());
}
?>