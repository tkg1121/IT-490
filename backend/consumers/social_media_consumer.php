<?php
// social_media_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME_SOCIAL_MEDIA = 'social_media';

try {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS);
    $channel = $connection->channel();
    $channel->queue_declare('social_media_queue', false, true, false, false);

    echo "Connected to RabbitMQ. Waiting for social media requests...\n";

    // Callback function to process incoming messages
    $callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_SOCIAL_MEDIA) {
        $data = json_decode($msg->body, true);
        $action = $data['action'] ?? null;
        $response = ['status' => 'error', 'message' => 'Invalid action'];

        // Debugging: Log the received action and data
        error_log("Received action: $action");
        error_log("Received data: " . print_r($data, true));

        // Connect to social_media database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_SOCIAL_MEDIA);
        if ($mysqli->connect_error) {
            $response = ['status' => 'error', 'message' => 'Database connection failed'];
            error_log("Database connection error: " . $mysqli->connect_error);
        } else {
            // Connect to user_auth database
            $userAuthDb = new mysqli($DB_HOST, $DB_USER, $DB_PASS, 'user_auth');
            if ($userAuthDb->connect_error) {
                $response = ['status' => 'error', 'message' => 'User auth database connection failed'];
                error_log("User auth DB error: " . $userAuthDb->connect_error);
            } else {
                if ($action === 'create_post' && isset($data['session_token'], $data['post_text'])) {
                    // Create post logic
                    $session_token = $userAuthDb->real_escape_string($data['session_token']);
                    $post_text = $mysqli->real_escape_string($data['post_text']);

                    // Fetch user_id using session_token
                    $user_result = $userAuthDb->query("SELECT id FROM users WHERE session_token = '$session_token'");
                    if ($user_result && $user_result->num_rows > 0) {
                        $user = $user_result->fetch_assoc();
                        $user_id = $user['id'];
                        // Insert new post
                        $sql = "INSERT INTO posts (user_id, post_text) VALUES ('$user_id', '$post_text')";
                        if ($mysqli->query($sql)) {
                            $response = ['status' => 'success', 'message' => 'Post created'];
                            error_log("Post created successfully.");
                        } else {
                            $response = ['status' => 'error', 'message' => 'Failed to create post'];
                            error_log("Error creating post: " . $mysqli->error);
                        }
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid session token'];
                        error_log("Invalid session token");
                    }
                } elseif ($action === 'fetch_posts') {
                    // Fetch posts logic
                    $sql = "SELECT posts.post_id, posts.post_text, posts.created_at, users.username FROM posts
                            JOIN user_auth.users ON posts.user_id = users.id ORDER BY posts.created_at DESC";
                    $result = $mysqli->query($sql);
                    if ($result) {
                        $posts = [];
                        while ($post = $result->fetch_assoc()) {
                            $post_id = $post['post_id'];
                            // Fetch comments count for each post
                            $comments_count_sql = "SELECT COUNT(*) as comments_count FROM comments WHERE post_id = $post_id";
                            $comments_count_result = $mysqli->query($comments_count_sql);
                            $comments_count = $comments_count_result->fetch_assoc()['comments_count'];

                            // Add comments count to post data
                            $post['comments_count'] = $comments_count;

                            $posts[] = ['post' => $post];
                        }
                        $response = ['status' => 'success', 'posts' => $posts];
                        error_log("Fetched posts successfully.");
                    } else {
                        $response = ['status' => 'error', 'message' => 'Failed to fetch posts'];
                        error_log("Error fetching posts: " . $mysqli->error);
                    }
                } elseif ($action === 'fetch_post_details' && isset($data['post_id'])) {
                    // Fetch single post details
                    $post_id = (int)$data['post_id'];
                    // Fetch post
                    $post_sql = "SELECT posts.post_id, posts.post_text, posts.created_at, users.username FROM posts
                                 JOIN user_auth.users ON posts.user_id = users.id WHERE posts.post_id = $post_id";
                    $post_result = $mysqli->query($post_sql);
                    if ($post_result && $post_result->num_rows > 0) {
                        $post = $post_result->fetch_assoc();
                        // Fetch comments
                        $comments_sql = "SELECT comments.comment_id, comments.comment_text, comments.created_at, users.username
                                         FROM comments JOIN user_auth.users ON comments.user_id = users.id
                                         WHERE comments.post_id = $post_id ORDER BY comments.created_at ASC";
                        $comments_result = $mysqli->query($comments_sql);
                        if ($comments_result) {
                            $comments = $comments_result->fetch_all(MYSQLI_ASSOC);
                        } else {
                            $comments = [];
                        }
                        $response = ['status' => 'success', 'post' => $post, 'comments' => $comments];
                        error_log("Fetched post details successfully.");
                    } else {
                        $response = ['status' => 'error', 'message' => 'Post not found'];
                        error_log("Post not found with ID: $post_id");
                    }
                } elseif ($action === 'create_comment' && isset($data['session_token'], $data['post_id'], $data['comment_text'])) {
                    // Create comment logic
                    $session_token = $userAuthDb->real_escape_string($data['session_token']);
                    $post_id = (int)$data['post_id'];
                    $comment_text = $mysqli->real_escape_string($data['comment_text']);

                    // Fetch user_id using session_token
                    $user_result = $userAuthDb->query("SELECT id FROM users WHERE session_token = '$session_token'");
                    if ($user_result && $user_result->num_rows > 0) {
                        $user = $user_result->fetch_assoc();
                        $user_id = $user['id'];
                        // Insert new comment
                        $sql = "INSERT INTO comments (post_id, user_id, comment_text) VALUES ('$post_id', '$user_id', '$comment_text')";
                        if ($mysqli->query($sql)) {
                            $response = ['status' => 'success', 'message' => 'Comment created'];
                            error_log("Comment created successfully.");
                        } else {
                            $response = ['status' => 'error', 'message' => 'Failed to create comment'];
                            error_log("Error creating comment: " . $mysqli->error);
                        }
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid session token'];
                        error_log("Invalid session token");
                    }
                }
                // For liking/disliking posts
                elseif (in_array($action, ['like_post', 'dislike_post']) && isset($data['session_token'], $data['post_id'])) {
                    $session_token = $userAuthDb->real_escape_string($data['session_token']);
                    $post_id = (int)$data['post_id'];
                    $like_dislike = ($action === 'like_post') ? 'like' : 'dislike';

                    // Fetch user_id using session_token
                    $user_result = $userAuthDb->query("SELECT id FROM users WHERE session_token = '$session_token'");
                    if ($user_result && $user_result->num_rows > 0) {
                        $user = $user_result->fetch_assoc();
                        $user_id = $user['id'];
                        // Insert or update like/dislike
                        $sql = "INSERT INTO post_likes_dislikes (post_id, user_id, like_dislike)
                                VALUES ('$post_id', '$user_id', '$like_dislike')
                                ON DUPLICATE KEY UPDATE like_dislike = '$like_dislike'";
                        if ($mysqli->query($sql)) {
                            $response = ['status' => 'success', 'message' => 'Post updated'];
                            error_log("Post like/dislike updated.");
                        } else {
                            $response = ['status' => 'error', 'message' => 'Failed to update post'];
                            error_log("Error updating post: " . $mysqli->error);
                        }
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid session token'];
                        error_log("Invalid session token");
                    }
                }
                // For liking/disliking comments
                elseif (in_array($action, ['like_comment', 'dislike_comment']) && isset($data['session_token'], $data['comment_id'])) {
                    $session_token = $userAuthDb->real_escape_string($data['session_token']);
                    $comment_id = (int)$data['comment_id'];
                    $like_dislike = ($action === 'like_comment') ? 'like' : 'dislike';

                    // Fetch user_id using session_token
                    $user_result = $userAuthDb->query("SELECT id FROM users WHERE session_token = '$session_token'");
                    if ($user_result && $user_result->num_rows > 0) {
                        $user = $user_result->fetch_assoc();
                        $user_id = $user['id'];
                        // Insert or update like/dislike
                        $sql = "INSERT INTO comment_likes_dislikes (comment_id, user_id, like_dislike)
                                VALUES ('$comment_id', '$user_id', '$like_dislike')
                                ON DUPLICATE KEY UPDATE like_dislike = '$like_dislike'";
                        if ($mysqli->query($sql)) {
                            $response = ['status' => 'success', 'message' => 'Comment updated'];
                            error_log("Comment like/dislike updated.");
                        } else {
                            $response = ['status' => 'error', 'message' => 'Failed to update comment'];
                            error_log("Error updating comment: " . $mysqli->error);
                        }
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid session token'];
                        error_log("Invalid session token");
                    }
                } else {
                    // Unrecognized action
                    error_log("Unrecognized action: $action");
                }

                // Close userAuthDb connection
                $userAuthDb->close();
            }
            // Close mysqli connection
            $mysqli->close();
        }

        // Debugging: Log the response before sending
        error_log("Sending response: " . print_r($response, true));

        // Send response back via RabbitMQ
        $reply_msg = new AMQPMessage(json_encode($response), ['correlation_id' => $msg->get('correlation_id')]);
        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    };

    // Start consuming from the queue with auto-acknowledgment
    $channel->basic_consume('social_media_queue', '', false, true, false, false, $callback);

    // Keep the consumer running indefinitely
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Exception: " . $e->getMessage());
}
?>
