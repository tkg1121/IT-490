<?php
require_once('/var/www/html/vendor/autoload.php');  // Path to php-amqplib autoload

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function sendToRabbitMQ($queue, $message) {
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            '192.168.193.197',  // RabbitMQ server IP
            5672,               // RabbitMQ port
            'T',            // RabbitMQ username
            'dev1121!!@@',            // RabbitMQ password
            '/'                 // Virtual host
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
            }
        });

        // Wait for the response from RabbitMQ
        while (!$response) {
            $channel->wait();  // Wait for the callback to trigger
        }

        // Close the channel and connection
        $channel->close();
        $connection->close();

        // Return the response to the caller
        return $response;

    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
}

// Sending movie request to RabbitMQ
$movie_name = 'Dragon Ball Super';  // Example movie request
$request_data = json_encode(['name' => $movie_name]);

$response = sendToRabbitMQ('omdb_request_queue', $request_data);
echo "Received movie data: " . $response;
?>