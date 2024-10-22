<?php
require 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function publish_blog($title, $content) {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    // Declare queue
    $channel->queue_declare('blog_queue', false, false, false, false);

    // Create message
    $data = json_encode(['title' => $title, 'content' => $content]);
    $msg = new AMQPMessage($data);

    // Publish to queue
    $channel->basic_publish($msg, '', 'blog_queue');

    $channel->close();
    $connection->close();
}
?>