<?php
// friends_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables from .env file located at /home/stanley
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
$dotenv->load();

// Set default timezone
date_default_timezone_set('UTC'); // Adjust as needed

// Load environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];

try {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();
    $queue = 'friends_queue';

    // Declare the queue for friend requests
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for friend request messages...\n";

    // Callback function to handle messages
    $callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
        $requestData = json_decode($msg->body, true);

        $action = $requestData['action'] ?? null;
        $session_token = $requestData['session_token'] ?? null;
        $friend_username = $requestData['friend_username'] ?? null;

        $response = ['status' => 'error', 'message' => 'Invalid request'];

        if ($action && $session_token) {
            // Connect to the database
            $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

            if ($mysqli->connect_error) {
                $response = ['status' => 'error', 'message' => 'Database connection failed'];
            } else {
                // Fetch user ID based on session token
                $stmt = $mysqli->prepare("SELECT id, username, email FROM users WHERE session_token = ?");
                $stmt->bind_param('s', $session_token);
                $stmt->execute();
                $stmt->bind_result($user_id, $username, $email);
                if ($stmt->fetch()) {
                    $stmt->close();

                    if ($action === 'send_request' && $friend_username) {
                        // Fetch friend ID and email
                        $stmt = $mysqli->prepare("SELECT id, email FROM users WHERE username = ?");
                        $stmt->bind_param('s', $friend_username);
                        $stmt->execute();
                        $stmt->bind_result($friend_id, $friend_email);
                        if ($stmt->fetch()) {
                            $stmt->close();

                            // Check if a friend request already exists
                            $stmt = $mysqli->prepare("SELECT id FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
                            $stmt->bind_param('ii', $user_id, $friend_id);
                            $stmt->execute();
                            $stmt->store_result();
                            if ($stmt->num_rows > 0) {
                                $response = ['status' => 'error', 'message' => 'Friend request already sent'];
                            } else {
                                $stmt->close();
                                // Insert friend request
                                $stmt = $mysqli->prepare("INSERT INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
                                $stmt->bind_param('ii', $user_id, $friend_id);
                                if ($stmt->execute()) {
                                    $response = ['status' => 'success', 'message' => 'Friend request sent'];

                                    // Notify the friend via email
                                    $friends_page_url = 'http://yourdomain.com/friends.php'; // Replace with your actual URL
                                    $notificationData = [
                                        'email' => $friend_email,
                                        'subject' => 'New Friend Request',
                                        'body' => "{$username} has sent you a friend request. <a href='{$friends_page_url}'>View Friend Requests</a>"
                                    ];

                                    sendNotification($notificationData);
                                } else {
                                    $response = ['status' => 'error', 'message' => 'Failed to send friend request'];
                                }
                                $stmt->close();
                            }
                        } else {
                            $response = ['status' => 'error', 'message' => 'Friend username not found'];
                        }
                    } elseif (($action === 'accept_request' || $action === 'decline_request') && $friend_username) {
                        // Fetch friend ID and email
                        $stmt = $mysqli->prepare("SELECT id, email FROM users WHERE username = ?");
                        $stmt->bind_param('s', $friend_username);
                        $stmt->execute();
                        $stmt->bind_result($friend_id, $friend_email);
                        if ($stmt->fetch()) {
                            $stmt->close();

                            if ($action === 'accept_request') {
                                // Update the friend request status to 'accepted'
                                $stmt = $mysqli->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
                                $stmt->bind_param('ii', $friend_id, $user_id);
                                if ($stmt->execute()) {
                                    $response = ['status' => 'success', 'message' => 'Friend request accepted'];

                                    // Notify the initial sender that their friend request was accepted
                                    $friends_page_url = 'http://yourdomain.com/friends.php'; // Replace with your actual URL
                                    $notificationData = [
                                        'email' => $friend_email,
                                        'subject' => 'Friend Request Accepted',
                                        'body' => "{$username} has accepted your friend request! <a href='{$friends_page_url}'>View Your Friends</a>"
                                    ];

                                    sendNotification($notificationData);
                                } else {
                                    $response = ['status' => 'error', 'message' => 'Failed to accept friend request'];
                                }
                                $stmt->close();
                            } else {
                                // Remove the friend request
                                $stmt = $mysqli->prepare("DELETE FROM friends WHERE user_id = ? AND friend_id = ? AND status = 'pending'");
                                $stmt->bind_param('ii', $friend_id, $user_id);
                                if ($stmt->execute()) {
                                    $response = ['status' => 'success', 'message' => 'Friend request declined'];

                                    // Notify the initial sender that their friend request was declined
                                    $friends_page_url = 'http://yourdomain.com/friends.php'; // Replace with your actual URL
                                    $notificationData = [
                                        'email' => $friend_email,
                                        'subject' => 'Friend Request Declined',
                                        'body' => "{$username} has declined your friend request."
                                    ];

                                    sendNotification($notificationData);
                                } else {
                                    $response = ['status' => 'error', 'message' => 'Failed to decline friend request'];
                                }
                                $stmt->close();
                            }
                        } else {
                            $response = ['status' => 'error', 'message' => 'Friend username not found'];
                        }
                    } elseif ($action === 'get_pending_requests') {
                        // Get pending friend requests for the user
                        $stmt = $mysqli->prepare("
                            SELECT u.username FROM friends f
                            JOIN users u ON f.user_id = u.id
                            WHERE f.friend_id = ? AND f.status = 'pending'
                        ");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $stmt->bind_result($requesting_username);
                        $pending_requests = [];
                        while ($stmt->fetch()) {
                            $pending_requests[] = $requesting_username;
                        }
                        $stmt->close();
                        $response = ['status' => 'success', 'pending_requests' => $pending_requests];
                    } elseif ($action === 'get_friends_list') {
                        // Get accepted friends for the user
                        $stmt = $mysqli->prepare("
                            SELECT DISTINCT u.username FROM friends f
                            JOIN users u ON (u.id = f.user_id OR u.id = f.friend_id)
                            WHERE (f.user_id = ? OR f.friend_id = ?) AND f.status = 'accepted' AND u.id != ?
                        ");
                        $stmt->bind_param('iii', $user_id, $user_id, $user_id);
                        $stmt->execute();
                        $stmt->bind_result($friend_username);
                        $friends_list = [];
                        while ($stmt->fetch()) {
                            $friends_list[] = $friend_username;
                        }
                        $stmt->close();
                        $response = ['status' => 'success', 'friends_list' => $friends_list];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid action'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Invalid session token'];
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

    // Start consuming from the queue
    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Keep the consumer running indefinitely
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error in friends_consumer.php: " . $e->getMessage() . "\n";
}

// Function to send notification via RabbitMQ
function sendNotification($notificationData) {
    global $RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS;

    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );
    $channel = $connection->channel();
    $channel->queue_declare('notify_queue', false, true, false, false);

    $msg = new AMQPMessage(json_encode($notificationData));
    $channel->basic_publish($msg, '', 'notify_queue');

    $channel->close();
    $connection->close();
}
?>