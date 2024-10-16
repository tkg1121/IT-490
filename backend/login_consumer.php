<?php
require_once('/home/stanley/consumers/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Login Consumer: Starting login_consumer.php");

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

error_log("Login Consumer: Connected to RabbitMQ");

$channel = $connection->channel();
$channel->queue_declare('login_queue', false, true, false, false);

$callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    error_log("Login Consumer: Received message: " . $msg->body);
    
    // Step 1: Receive login data
    $data = json_decode($msg->body, true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (!$username || !$password) {
        $response = "Login failed: Missing username or password.";
        error_log("Login Consumer: Missing username or password");
    } else {
        // Step 2: Trim any whitespace from the plain-text password
        $password = trim($password);
        error_log("Login Consumer: Attempting login for username: $username");

        // Step 3: Connect to the database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Login failed: Database connection error - " . $mysqli->connect_error;
            error_log("Login Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            error_log("Login Consumer: Database connection successful");

            // Step 4: Fetch the hashed password from the database
            $stmt = $mysqli->prepare("SELECT password FROM users WHERE username=?");

            if (!$stmt) {
                $response = "Login failed: SQL preparation error - " . $mysqli->error;
                error_log("Login Consumer: SQL preparation error - " . $mysqli->error);
            } else {
                error_log("Login Consumer: Preparing SQL statement to fetch password for user: $username");

                $stmt->bind_param('s', $username);
                if (!$stmt->execute()) {
                    $response = "Login failed: SQL execution error - " . $stmt->error;
                    error_log("Login Consumer: SQL execution error - " . $stmt->error);
                } else {
                    error_log("Login Consumer: SQL query executed successfully");

                    $stmt->bind_result($hash_password);
                    if ($stmt->fetch()) {
                        // Log both the plain-text password and the stored hashed password
                        error_log("Login Consumer: Plain-text entered password: '$password'");
                        error_log("Login Consumer: Hashed password stored in DB for user $username: $hash_password");

                        // Step 5: Verify the password using password_verify()
                        if (password_verify($password, $hash_password)) {
                            error_log("Login Consumer: Password verification successful for user: $username");

                            // Generate session token and update in the database
                            $session_token = bin2hex(random_bytes(32));
                            $last_activity = date('Y-m-d H:i:s');

                            // Store the session token and last activity time
                            $stmt->close();
                            $stmt = $mysqli->prepare("UPDATE users SET session_token=?, last_activity=? WHERE username=?");

                            if ($stmt) {
                                $stmt->bind_param('sss', $session_token, $last_activity, $username);
                                if ($stmt->execute()) {
                                    $response = json_encode(['status' => 'success', 'session_token' => $session_token]);
                                    error_log("Login Consumer: Session token updated for user: $username");
                                } else {
                                    $response = "Login failed: SQL execution error during session update - " . $stmt->error;
                                    error_log("Login Consumer: SQL execution error during session update - " . $stmt->error);
                                }
                            } else {
                                error_log("Login Consumer: SQL preparation error for session update - " . $mysqli->error);
                            }
                        } else {
                            error_log("Login Consumer: Password verification failed for user: $username");
                            error_log("Login Consumer: Entered password: '$password' | Stored hashed password: $hash_password");
                            $response = "Login failed: Incorrect password.";
                        }
                    } else {
                        error_log("Login Consumer: Username not found in the database.");
                        $response = "Login failed: Username not found.";
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
    error_log("Login Consumer: Sent response: " . $response);

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

// Step 8: Consume the messages from the queue
$channel->basic_consume('login_queue', '', false, false, false, false, $callback);

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
error_log("Login Consumer: Closed RabbitMQ connection");
