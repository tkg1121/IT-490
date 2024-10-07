<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);  // Suppress deprecated warnings

// Load the php-amqplib library (make sure it's correctly installed via Composer)
require_once('/var/www/html/vendor/autoload.php');  // Update this path as needed

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function sendToRabbitMQ($queue, $message) {
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            '192.168.193.137',  // RabbitMQ server IP (VM3)
            5672,               // RabbitMQ port
            'guest',            // RabbitMQ username
            'guest',            // RabbitMQ password
            '/',                // Virtual host
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
        $channel->basic_publish($msg, '', $queue);

        // Set up to wait for the response
        $response = null;

        // Define the callback to handle the response
        $channel->basic_consume($callback_queue, '', false, true, false, false, function ($msg) use ($correlation_id, &$response) {
            if ($msg->get('correlation_id') == $correlation_id) {
                $response = $msg->body;  // Capture the response body
                error_log("Received response: " . $response);  // Log the response for debugging
            }
        });

        // Wait for the response from RabbitMQ
        while (!$response) {
            error_log("Waiting for RabbitMQ response...");  // Log waiting status
            $channel->wait();
        }

        // Log success and close connections
        error_log("Final response received: " . $response);  // Debugging log
        $channel->close();
        $connection->close();

        return $response;

    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("RabbitMQ Error: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
}
?>
