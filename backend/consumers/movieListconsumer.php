<?php

require 'vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->queue_declare('movie_list_queue', false, false, false, false);

echo "Waiting for messages. To exit press CTRL+C\n";

$callback = function($msg) {
    $movie = json_decode($msg->body, true);

    // Here, you'd process the movie data, store it in a database, or pass it to another service
    echo "Received movie: ", $movie['title'], "\n";
};

$channel->basic_consume('movie_list_queue', '', false, true, false, false, $callback);

while($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();

?>