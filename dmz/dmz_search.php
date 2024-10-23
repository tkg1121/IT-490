<?php
require_once __DIR__ . '/vendor/autoload.php'; // Include RabbitMQ PHP library (PhpAmqpLib)

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Get the search query from the frontend
$data = json_decode(file_get_contents('php://input'), true);
$searchQuery = $data['query'];

// Connect to RabbitMQ
//$connection = new AMQPStreamConnection('rabbitmq_host', 5672, 'guest', 'guest');
/*$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,  // RabbitMQ server IP
    $RABBITMQ_PORT,  // RabbitMQ port
    $RABBITMQ_USER,  // RabbitMQ username
    $RABBITMQ_PASS,  // RabbitMQ password
    '/',             // Virtual host
    false,           // Insist
    'AMQPLAIN',      // Login method
    null,            // Login response
    'en_US',         // Locale
    10.0,            // Connection timeout
    10.0,            // Read/write timeout
    null,            // Context (use null for default)
    false,           // Keepalive
    60               // Heartbeat interval
);
*/
//$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$connection = new AMQPStreamConnection(
    '192.168.193.197',    // IP address or hostname of the RabbitMQ machine
    5672,             // Port RabbitMQ is listening on (default 5672)
    'guest',          // RabbitMQ username
    'guest'           // RabbitMQ password
);

$channel = $connection->channel();







// Declare a queue (if it doesn't exist)
$channel->queue_declare('search_queue', false, true, false, false);

// Create the message with the search query
$msg = new AMQPMessage($searchQuery);
$channel->basic_publish($msg, '', 'search_queue');

// Close the channel and connection
$channel->close();
$connection->close();

// Send response to the frontend (acknowledge the search was sent)
echo json_encode(['status' => 'Search request sent']);
?>
