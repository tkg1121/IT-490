<?php
require_once('/home/dev/php-amqplib/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Profile Consumer: Starting profile_consumer.php");

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    '192.168.193.137',  // RabbitMQ server IP (VM3)
    5672,               // RabbitMQ port
    'guest',            // RabbitMQ username
    'guest',            // RabbitMQ password
    '/',                // Virtual host
    false,              // Insist
    'AMQPLAIN',         // Login method
    null,               // Login response
    'en_US',            // Locale
    10.0,               // Connection timeout in seconds
    10.0,               // Read/write timeout in seconds
    null,               // Context (use null for default)
    false,              // Keepalive (use true to keep the connection alive)
    60                  // Heartbeat interval in seconds
);

error_log("Profile Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('profile_queue', false, true, false, false);

$callback = function ($msg) use ($channel) {
    error_log("Profile Consumer: Received message: " . $msg->body);
    $data = json_decode($msg->body, true);

    if ($data['action'] === 'fetch_profile') {
        $session_token = $data['session_token'];

        // Log session token
        error_log("Profile Consumer: Session token: " . $session_token);

        // Connect to the database
        $mysqli = new mysqli("localhost", "dbadmin", "dbAdmin123!", "user_auth");

        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
            error_log("Profile Consumer: Database connection failed: " . $mysqli->connect_error);
        } else {
            error_log("Profile Consumer: Database connection successful");

            // Fetch the profile information based on the session token
            $stmt = $mysqli->prepare("SELECT username, email FROM users WHERE session_token=?");

            if (!$stmt) {
                $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error - ' . $mysqli->error]);
                error_log("Profile Consumer: SQL preparation error - " . $mysqli->error);
            } else {
                error_log("Profile Consumer: SQL statement prepared successfully");

                $stmt->bind_param('s', $session_token);
                if (!$stmt->execute()) {
                    $response = json_encode(['status' => 'error', 'message' => 'SQL execution error - ' . $stmt->error]);
                    error_log("Profile Consumer: SQL execution error - " . $stmt->error);
                } else {
                    error_log("Profile Consumer: SQL query executed successfully");

                    $stmt->bind_result($username, $email);
                    if ($stmt->fetch()) {
                        error_log("Profile Consumer: Profile data fetched for user: " . $username);

                        $response = json_encode([
                            'status' => 'success',
                            'username' => $username,
                            'email' => $email
                        ]);
                    } else {
                        error_log("Profile Consumer: Invalid session token");
                        $response = json_encode(['status' => 'error', 'message' => 'Invalid session token']);
                    }
                }
                $stmt->close();
            }
            $mysqli->close();
        }

        // Send the response back via RabbitMQ
        $reply_msg = new AMQPMessage(
            $response,
            ['correlation_id' => $msg->get('correlation_id')]
        );

        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
        error_log("Profile Consumer: Sent response: " . $response);
    }

    // Acknowledge the message (mark it as processed)
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

// Consume messages from `profile_queue`
$channel->basic_consume('profile_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Profile Consumer: Closed RabbitMQ connection");
