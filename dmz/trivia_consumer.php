<?php
require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku');  // Load .env from /home/alisa-maloku
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// RabbitMQ connection settings
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];

try {
    $connection = new AMQPStreamConnection($RABBITMQ_HOST, $RABBITMQ_PORT, $RABBITMQ_USER, $RABBITMQ_PASS);
    $channel = $connection->channel();
    $queue = 'trivia_request_queue';

    // Declare the trivia request queue
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for trivia requests...\n";

    // Fetch trivia data from API
    function fetchTrivia() {
        $url = 'https://opentdb.com/api.php?amount=1&category=11&difficulty=medium&type=multiple';
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if (isset($data['results'][0])) {
            $trivia = $data['results'][0];
            return [
                'question' => $trivia['question'],
                'correct_answer' => $trivia['correct_answer'],
                'answers' => array_merge([$trivia['correct_answer']], $trivia['incorrect_answers'])
            ];
        }
        return ['error' => 'Failed to retrieve trivia'];
    }

    // Handle incoming trivia requests
    $callback = function ($msg) use ($channel) {
        $response = fetchTrivia();
        $reply_msg = new AMQPMessage(
            json_encode($response),
            ['correlation_id' => $msg->get('correlation_id')]
        );

        // Send response to the reply queue
        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
    };

    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Keep the consumer running
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

