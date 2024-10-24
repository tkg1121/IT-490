<?php
require_once('/home/stanley/consumers/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Signup Consumer: Starting signup_consumer.php");

// Pull credentials from .env file
$RABBITMQ_HOST = getenv('RABBITMQ_HOST');
$RABBITMQ_PORT = getenv('RABBITMQ_PORT');
$RABBITMQ_USER = getenv('RABBITMQ_USER');
$RABBITMQ_PASS = getenv('RABBITMQ_PASS');
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');

// Establish RabbitMQ connection
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,  // RabbitMQ server IP
    $RABBITMQ_PORT,  // RabbitMQ port
    $RABBITMQ_USER,  // RabbitMQ username
    $RABBITMQ_PASS,  // RabbitMQ password
    '/',             // Virtual host
    false,           // Insist
    'AMQPLAIN',      // Login method
    null,            // Login response
    'en_US',         // Locale
    10.0,            // Connection timeout
    10.0,            // Read/write timeout
    null,            // Context (use null for default)
    false,           // Keepalive
    60               // Heartbeat interval
);

error_log("Signup Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('signup_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    error_log("Signup Consumer: Received message: " . $msg->body);

    // Step 1: Parse the message
    $data = json_decode($msg->body, true);
    error_log("Signup Consumer: Decoded message data: " . print_r($data, true));

    // Step 2: Check for username, password, and email
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $email = $data['email'] ?? null;

    // Debug the parsed values
    error_log("Signup Consumer: Username: '$username'");
    error_log("Signup Consumer: Password: '$password'");
    error_log("Signup Consumer: Email: '$email'");

    if (!$username || !$password || !$email) {
        $response = "Signup failed: Missing username, password, or email.";
        error_log("Signup Consumer: Missing username, password, or email");
    } else {
        // Step 3: Hash the password and log the hashed value
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        error_log("Signup Consumer: Hashed password: '$hashed_password'");

        // Step 4: Connect to MySQL
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Signup failed: Database connection error - " . $mysqli->connect_error;
            error_log("Signup Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            error_log("Signup Consumer: Database connection successful");

            // Step 5: Check if username or email already exists
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? OR email=?");

            if (!$stmt) {
                $response = "Signup failed: SQL preparation error - " . $mysqli->error;
                error_log("Signup Consumer: SQL preparation error - " . $mysqli->error);
            } else {
                error_log("Signup Consumer: Checking if username or email already exists");

                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $response = "Signup failed: Username or email already exists.";
                    error_log("Signup Consumer: Username or email already exists");
                } else {
                    error_log("Signup Consumer: Username and email are unique, proceeding with signup");

                    // Step 6: Insert the new user into the database
                    $stmt->close();
                    $stmt = $mysqli->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");

                    if (!$stmt) {
                        $response = "Signup failed: SQL preparation error for insertion - " . $mysqli->error;
                        error_log("Signup Consumer: SQL preparation error for insertion - " . $mysqli->error);
                    } else {
                        $stmt->bind_param('sss', $username, $hashed_password, $email);

                        if ($stmt->execute()) {
                            $response = "Signup successful";
                            error_log("Signup Consumer: User created successfully: " . $username);
                        } else {
                            $response = "Signup failed: SQL execution error - " . $stmt->error;
                            error_log("Signup Consumer: SQL execution error - " . $stmt->error);
                        }
                    }
                }
                $stmt->close();
            }
            $mysqli->close();
        }
    }

    // Step 7: Send the response back via RabbitMQ
    $reply_msg = new AMQPMessage(
        $response,
        ['correlation_id' => $msg->get('correlation_id')]
    );

    $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
    error_log("Signup Consumer: Sent response: " . $response);

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

// Step 8: Consume the messages from the signup_queue
$channel->basic_consume('signup_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Signup Consumer: Closed RabbitMQ connection");
