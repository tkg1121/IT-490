<?php
require 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

function publish_blog($title, $content) {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection(
        '192.168.193.197',    // IP address or hostname of the RabbitMQ machine
        5672,             // Port RabbitMQ is listening on (default 5672)
        'guest',          // RabbitMQ username
        'guest'           // RabbitMQ password
    );
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