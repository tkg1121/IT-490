<?php
// rabbitmq_send.php

require_once('/home/ashleys/IT-490/frontend/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable('/home/ashleys');
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Set error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/ashleys/IT-490/frontend/error.log');
error_reporting(E_ALL);

// Pull environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];

function sendToRabbitMQ($queueName, $requestData) {
    global $RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS;
    try {
        // Log connection attempt
        error_log("Connecting to RabbitMQ for queue: $queueName");

        // Establish connection and channel
        $connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS);
        $channel = $connection->channel();

        // Declare the queue
        $channel->queue_declare($queueName, false, true, false, false);

        // Generate a unique correlation ID and create a callback queue
        $correlationId = uniqid();
        list($callbackQueue,,) = $channel->queue_declare("", false, false, true, false);
        $response = null;

        // Set up a consumer on the callback queue
        $channel->basic_consume(
            $callbackQueue,
            '',
            false,
            true,
            false,
            false,
            function ($msg) use (&$response, $correlationId) {
                if ($msg->get('correlation_id') == $correlationId) {
                    $response = $msg->body;
                }
            }
        );

        // Create the message with the reply_to and correlation_id properties
        $msg = new AMQPMessage(
            $requestData,
            [
                'correlation_id' => $correlationId,
                'reply_to' => $callbackQueue
            ]
        );

        // Publish the message to the specified queue
        $channel->basic_publish($msg, '', $queueName);
        error_log("Message published to queue: $queueName with correlation_id: $correlationId");

        // Wait for the response with a timeout
        $startTime = time();
        $timeoutSeconds = 5; // Adjust as needed
        while (!$response) {
            $channel->wait(null, false, $timeoutSeconds);
            if ((time() - $startTime) > $timeoutSeconds) {
                throw new Exception("Request timed out.");
            }
        }

        // Close the channel and connection
        $channel->close();
        $connection->close();

        // Log the response received
        error_log("Response received: $response");

        // Return the response to the caller
        return $response;
    } catch (Exception $e) {
        error_log("Error occurred while communicating with RabbitMQ: " . $e->getMessage());
        return json_encode(['error' => $e->getMessage()]);
    }
}
?>

