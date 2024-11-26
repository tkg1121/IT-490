<?php
// 2FA_consumer.php

require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables from .env file located at /home/stanley
$dotenv = Dotenv\Dotenv::createImmutable('/home/stanley');
$dotenv->load();

// Set default timezone
date_default_timezone_set('UTC'); // Adjust as needed


$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS
);

$channel = $connection->channel();

// Declare the queue
$channel->queue_declare('2fa_queue', false, true, false, false);

// Callback function
$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    $data = json_decode($msg->body, true);
    $username = $data['username'] ?? null;
    $submitted_two_factor_code = trim($data['two_factor_code'] ?? '');

    if (!$username || !$submitted_two_factor_code) {
        $response = json_encode(['status' => 'error', 'message' => 'Missing username or 2FA code']);
    } else {
        // Connect to database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($mysqli->connect_error) {
            $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
        } else {
            // Fetch the stored hashed code and expiration time
            $stmt = $mysqli->prepare("SELECT two_factor_code, two_factor_expires FROM users WHERE username = ?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->bind_result($stored_two_factor_code_hash, $two_factor_expires);
            if ($stmt->fetch()) {
                $stmt->close();

                // Get current time and expiration time as DateTime objects
                $now = new DateTime();
                $expires = new DateTime($two_factor_expires);

                // Debugging output (optional)
                // error_log("Current time: " . $now->format('Y-m-d H:i:s'));
                // error_log("Two-factor expires at: " . $expires->format('Y-m-d H:i:s'));

                // Check if code is expired
                if ($expires < $now) {
                    // Code has expired
                    $response = json_encode(['status' => 'error', 'message' => 'Invalid or expired 2FA code']);
                } else {
                    // Verify the code
                    if (password_verify($submitted_two_factor_code, $stored_two_factor_code_hash)) {
                        // Code is valid
                        // Generate session token
                        $session_token = bin2hex(random_bytes(32));
                        // Store session token in database
                        $stmt = $mysqli->prepare("UPDATE users SET session_token = ? WHERE username = ?");
                        $stmt->bind_param('ss', $session_token, $username);
                        if ($stmt->execute()) {
                            $response = json_encode(['status' => 'success', 'session_token' => $session_token]);
                        } else {
                            $response = json_encode(['status' => 'error', 'message' => 'Failed to set session token']);
                        }
                        $stmt->close();
                    } else {
                        // Code is invalid
                        $response = json_encode(['status' => 'error', 'message' => 'Invalid or expired 2FA code']);
                    }
                }
            } else {
                $response = json_encode(['status' => 'error', 'message' => 'Username not found']);
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

// Start consuming
$channel->basic_consume('2fa_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

// Close the channel and connection when done
$channel->close();
$connection->close();
?>