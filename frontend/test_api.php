<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Search</title>
</head>
<body>
    <h1>Search for a Movie</h1>
    <form method="POST" action="">
        <label for="movie_name">Enter Movie Name:</label>
        <input type="text" id="movie_name" name="movie_name" required>
        <button type="submit">Search</button>
    </form>

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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!empty($_POST['movie_name'])) {
            $movie_name = htmlspecialchars($_POST['movie_name']);  // Sanitize user input
            $request_data = json_encode(['name' => $movie_name]);

            // Sending movie request to RabbitMQ
            $response = sendToRabbitMQ('omdb_request_queue', $request_data);

            // Display the response
            echo "<h2>Received movie data:</h2>";
            echo "<pre>" . $response . "</pre>";
        }
    }
    ?>

</body>
</html>