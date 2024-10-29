<?php
require_once('/home/stanley/Documents/GitHub/IT-490/backend/consumers/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Function to log messages to the terminal
function log_message($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
}

log_message("Combined Consumer: Starting combined_consumer.php");

// Pull credentials from .env file using $_ENV
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

log_message("Combined Consumer: Connected to RabbitMQ");

$channel = $connection->channel();

// Declare the queues
$channel->queue_declare('signup_queue', false, true, false, false);
$channel->queue_declare('login_queue', false, true, false, false);
$channel->queue_declare('profile_queue', false, true, false, false);

/**
 * Signup Callback
 */
$signup_callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    log_message("Signup Consumer: Received message: " . $msg->body);

    // Step 1: Parse the message
    $data = json_decode($msg->body, true);
    log_message("Signup Consumer: Decoded message data: " . print_r($data, true));

    // Step 2: Check for username, password, and email
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $email = $data['email'] ?? null;

    // Debug the parsed values
    log_message("Signup Consumer: Username: '$username'");
    log_message("Signup Consumer: Password: '$password'");
    log_message("Signup Consumer: Email: '$email'");

    if (!$username || !$password || !$email) {
        $response = "Signup failed: Missing username, password, or email.";
        log_message("Signup Consumer: Missing username, password, or email");
    } else {
        // Step 3: Hash the password and log the hashed value
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        log_message("Signup Consumer: Hashed password: '$hashed_password'");

        // Step 4: Connect to MySQL
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Signup failed: Database connection error - " . $mysqli->connect_error;
            log_message("Signup Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            log_message("Signup Consumer: Database connection successful");

            // Step 5: Check if username or email already exists
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? OR email=?");

            if (!$stmt) {
                $response = "Signup failed: SQL preparation error - " . $mysqli->error;
                log_message("Signup Consumer: SQL preparation error - " . $mysqli->error);
            } else {
                log_message("Signup Consumer: Checking if username or email already exists");

                $stmt->bind_param('ss', $username, $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $response = "Signup failed: Username or email already exists.";
                    log_message("Signup Consumer: Username or email already exists");
                } else {
                    log_message("Signup Consumer: Username and email are unique, proceeding with signup");

                    // Step 6: Insert the new user into the database
                    $stmt->close();
                    $stmt = $mysqli->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");

                    if (!$stmt) {
                        $response = "Signup failed: SQL preparation error for insertion - " . $mysqli->error;
                        log_message("Signup Consumer: SQL preparation error for insertion - " . $mysqli->error);
                    } else {
                        $stmt->bind_param('sss', $username, $hashed_password, $email);

                        if ($stmt->execute()) {
                            $response = "Signup successful";
                            log_message("Signup Consumer: User created successfully: " . $username);
                        } else {
                            $response = "Signup failed: SQL execution error - " . $stmt->error;
                            log_message("Signup Consumer: SQL execution error - " . $stmt->error);
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
    log_message("Signup Consumer: Sent response: " . $response);

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Login Callback
 */
$login_callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    log_message("Login Consumer: Received message: " . $msg->body);
    
    // Step 1: Receive login data
    $data = json_decode($msg->body, true);
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;

    if (!$username || !$password) {
        $response = "Login failed: Missing username or password.";
        log_message("Login Consumer: Missing username or password");
    } else {
        // Step 2: Trim any whitespace from the plain-text password
        $password = trim($password);
        log_message("Login Consumer: Attempting login for username: $username");

        // Step 3: Connect to the database
        $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

        if ($mysqli->connect_error) {
            $response = "Login failed: Database connection error - " . $mysqli->connect_error;
            log_message("Login Consumer: Database connection error: " . $mysqli->connect_error);
        } else {
            log_message("Login Consumer: Database connection successful");

            // Step 4: Fetch the hashed password from the database
            $stmt = $mysqli->prepare("SELECT password FROM users WHERE username=?");

            if (!$stmt) {
                $response = "Login failed: SQL preparation error - " . $mysqli->error;
                log_message("Login Consumer: SQL preparation error - " . $mysqli->error);
            } else {
                log_message("Login Consumer: Preparing SQL statement to fetch password for user: $username");

                $stmt->bind_param('s', $username);
                if (!$stmt->execute()) {
                    $response = "Login failed: SQL execution error - " . $stmt->error;
                    log_message("Login Consumer: SQL execution error - " . $stmt->error);
                } else {
                    log_message("Login Consumer: SQL query executed successfully");

                    $stmt->bind_result($hash_password);
                    if ($stmt->fetch()) {
                        // Log both the plain-text password and the stored hashed password
                        log_message("Login Consumer: Plain-text entered password: '$password'");
                        log_message("Login Consumer: Hashed password stored in DB for user $username: $hash_password");

                        // Step 5: Verify the password using password_verify()
                        if (password_verify($password, $hash_password)) {
                            log_message("Login Consumer: Password verification successful for user: $username");

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
                                    log_message("Login Consumer: Session token updated for user: $username");
                                } else {
                                    $response = "Login failed: SQL execution error during session update - " . $stmt->error;
                                    log_message("Login Consumer: SQL execution error during session update - " . $stmt->error);
                                }
                            } else {
                                $response = "Login failed: SQL preparation error for session update - " . $mysqli->error;
                                log_message("Login Consumer: SQL preparation error for session update - " . $mysqli->error);
                            }
                        } else {
                            log_message("Login Consumer: Password verification failed for user: $username");
                            log_message("Login Consumer: Entered password: '$password' | Stored hashed password: $hash_password");
                            $response = "Login failed: Incorrect password.";
                        }
                    } else {
                        log_message("Login Consumer: Username not found in the database.");
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
    log_message("Login Consumer: Sent response: " . $response);

    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

/**
 * Profile Callback
 */
$profile_callback = function ($msg) use ($channel, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME) {
    log_message("Profile Consumer: Received message: " . $msg->body);
    $data = json_decode($msg->body, true);

    if ($data['action'] === 'fetch_profile') {
        $session_token = $data['session_token'] ?? null;

        if (!$session_token) {
            $response = json_encode(['status' => 'error', 'message' => 'Missing session token']);
            log_message("Profile Consumer: Missing session token");
        } else {
            // Log session token
            log_message("Profile Consumer: Session token: " . $session_token);

            // Connect to the database
            $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

            if ($mysqli->connect_error) {
                $response = json_encode(['status' => 'error', 'message' => 'Database connection failed']);
                log_message("Profile Consumer: Database connection failed: " . $mysqli->connect_error);
            } else {
                log_message("Profile Consumer: Database connection successful");

                // Fetch the profile information based on the session token
                $stmt = $mysqli->prepare("SELECT username, email FROM users WHERE session_token=?");

                if (!$stmt) {
                    $response = json_encode(['status' => 'error', 'message' => 'SQL preparation error - ' . $mysqli->error]);
                    log_message("Profile Consumer: SQL preparation error - " . $mysqli->error);
                } else {
                    log_message("Profile Consumer: Preparing SQL statement to fetch profile for session token: $session_token");

                    $stmt->bind_param('s', $session_token);
                    if (!$stmt->execute()) {
                        $response = json_encode(['status' => 'error', 'message' => 'SQL execution error - ' . $stmt->error]);
                        log_message("Profile Consumer: SQL execution error - " . $stmt->error);
                    } else {
                        log_message("Profile Consumer: SQL query executed successfully");

                        $stmt->bind_result($username, $email);
                        if ($stmt->fetch()) {
                            log_message("Profile Consumer: Profile data fetched for user: " . $username);

                            $response = json_encode([
                                'status' => 'success',
                                'username' => $username,
                                'email' => $email
                            ]);
                        } else {
                            log_message("Profile Consumer: Invalid session token");
                            $response = json_encode(['status' => 'error', 'message' => 'Invalid session token']);
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
        log_message("Profile Consumer: Sent response: " . $response);
    }

    // Acknowledge the message (mark it as processed)
    $channel->basic_ack($msg->delivery_info['delivery_tag']);
};

// Assign consumers to their respective queues
$channel->basic_consume('signup_queue', '', false, false, false, false, $signup_callback);
$channel->basic_consume('login_queue', '', false, false, false, false, $login_callback);
$channel->basic_consume('profile_queue', '', false, false, false, false, $profile_callback);

log_message("Combined Consumer: Waiting for messages. To exit press CTRL+C");

// Keep the consumer running
while ($channel->is_consuming()) {
    $channel->wait();
}

// Close the channel and connection when done (this part is typically not reached unless the loop is broken)
$channel->close();
$connection->close();
log_message("Combined Consumer: Closed RabbitMQ connection");
