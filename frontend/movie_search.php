<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Movie</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            background-color: rgb(112, 90, 165);
        }

        /* Top Navigation Styles */
        .topnav {
            overflow: hidden;
            background-color: pink; /* Top navigation background */
            padding: 10px 0; /* Padding for better spacing */
            z-index: 1000; /* Ensures it's on top of other elements */
        }

        .topnav a {
            float: left;
            color: purple;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
            font-size: 17px;
        }

        .topnav a:hover {
            background-color: purple;
            color: pink;
        }

        .topnav a.active {
            background-color: pink;
            color: black;
        }

        /* Footer Styles */
        .footer {
            background-color: pink;
            color: black;
            text-align: center;
            padding: 20px 0;
            position: absolute; 
            width: 100%;
            bottom: 0;
        }

        .footer a {
            color: black;
            margin: 0 15px;
            text-decoration: none;
        }

        .footer a:hover {
            color: purple;
        }

        /* Form Styles */
        form {
            max-width: 400px; /* Set a maximum width for the form */
            margin: 50px auto; /* Center the form */
            padding: 20px;
            background-color: #fff; /* White background for the form */
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        input[type="submit"] {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="topnav">
        <a href="index.html">Home</a>
        <a href="create_blog.html">Blog</a>
        <a href="index.php">Sign in/log in</a>
        <a href="movieLists.html" class="active">Add to Your List</a>
        <a href="trivia.html">Trivia</a>
    </div>
    
    <form method="POST" action="">
        <input type="text" name="movie_name" placeholder="Movie Name" required>
        <input type="submit" value="Search Movie">
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
                'T',                // RabbitMQ username
                'dev1121!!@@',       // RabbitMQ password
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

            // Wait for the response from RabbitMQ with a timeout of 30 seconds
            $timeout = 30; // Set timeout in seconds
            $startTime = time();

            while (!$response && (time() - $startTime) < $timeout) {
                $channel->wait(null, false, 2);  // 2-second wait timeout per iteration
            }

            // Close the channel and connection
            $channel->close();
            $connection->close();

            // Return the response or timeout message
            return $response ? $response : "Timeout: No response from RabbitMQ.";

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

    <footer class="footer">
        <p>&copy; 2024 MovieMania. All rights reserved.</p>
        <p>
            <a href="">Terms and Conditions</a> |
            <a href="">Privacy Policy</a> |
            <a href="">Contact Us</a>
        </p>
    </footer>
</body>
</html>
