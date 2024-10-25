<?php
require_once('/home/ashleys/IT-490/frontend/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/ashleys');  // Load .env from /home/ashleys
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Set error logging to both console and a log file
ini_set('display_errors', 1);  // Show errors in the console (CLI)
ini_set('log_errors', 1);      // Log errors to a file
ini_set('error_log', '/home/ashleys/IT-490/frontend/error.log');  // Updated error log path
error_reporting(E_ALL);        // Report all types of errors

// Check and log environment variables
$requiredEnvVars = ['RABBITMQ_HOST', 'RABBITMQ_PORT', 'RABBITMQ_USER', 'RABBITMQ_PASS', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];

foreach ($requiredEnvVars as $envVar) {
    if (!isset($_ENV[$envVar]) || empty($_ENV[$envVar])) {
        error_log("Error: Environment variable $envVar is not set or is empty");
        die("Error: Required environment variable $envVar is not set or is empty\n");
    }
}

// Pull environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$DB_HOST = $_ENV['DB_HOST'];
$DB_USER = $_ENV['DB_USER'];
$DB_PASS = $_ENV['DB_PASS'];
$DB_NAME = $_ENV['DB_NAME'];

// Log environment variable values for debugging
error_log("RABBITMQ_HOST: $RABBITMQ_HOST");
error_log("RABBITMQ_PORT: $RABBITMQ_PORT");
error_log("RABBITMQ_USER: $RABBITMQ_USER");
error_log("DB_HOST: $DB_HOST");
error_log("DB_USER: $DB_USER");

function sendToRabbitMQ($queue, $message) {
    global $RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS;

    try {
        // Connect to RabbitMQ
        error_log("Attempting to connect to RabbitMQ at $RABBITMQ_HOST:$RABBITMQ_PORT with user $RABBITMQ_USER");
        $connection = new AMQPStreamConnection(
            $RABBITMQ_HOST,  // RabbitMQ server IP
            $RABBITMQ_PORT,  // RabbitMQ port
            $RABBITMQ_USER,  // RabbitMQ username
            $RABBITMQ_PASS   // RabbitMQ password
        );

        // Create a channel
        $channel = $connection->channel();

        // Declare the queue where the message will be sent
        $channel->queue_declare($queue, false, true, false, false);

        // Declare a callback queue for the RPC response
        list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

        // Generate a unique correlation ID for the RPC request
        $correlation_id = uniqid();

        // Create the message, include the reply_to and correlation_id fields
        $msg = new AMQPMessage(
            $message,
            ['correlation_id' => $correlation_id, 'reply_to' => $callback_queue]
        );

        // Publish the message to the queue
        error_log("Publishing message to queue: $queue with correlation_id: $correlation_id");
        $channel->basic_publish($msg, '', $queue);

        // Set up to wait for the response
        $response = null;

        // Define the callback to handle the response
        $channel->basic_consume($callback_queue, '', false, true, false, false, function ($msg) use ($correlation_id, &$response) {
            if ($msg->get('correlation_id') == $correlation_id) {
                $response = $msg->body;  // Capture the response body
            }
        });

        // Wait for the response from RabbitMQ
        while (!$response) {
            $channel->wait();  // Wait for the callback to trigger
        }

        // Close the channel and connection
        error_log("Response received: $response");
        $channel->close();
        $connection->close();

        // Return the response to the caller
        return $response;

    } catch (Exception $e) {
        error_log("Error occurred while communicating with RabbitMQ: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
}
?>
