<?php
require 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
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