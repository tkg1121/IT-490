<?php
require 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(
    '192.168.193.197',    // IP address or hostname of the RabbitMQ machine
    5672,             // Port RabbitMQ is listening on (default 5672)
    'guest',          // RabbitMQ username
    'guest'           // RabbitMQ password
);
$channel = $connection->channel();

$channel->queue_declare('blog_queue', false, false, false, false);

echo 'Waiting for blog posts. To exit press CTRL+C', "\n";

$callback = function($msg) {
    $blogPost = json_decode($msg->body, true);
    echo "Blog Post: ", $blogPost['title'], "\n";
    // Here you can store the blog post or pass it to another service
};

$channel->basic_consume('blog_queue', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>