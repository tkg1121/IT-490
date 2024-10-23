<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Search</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: rgb(112, 90, 165);
        }

        .topnav {
            overflow: hidden;
            background-color: pink;
            padding: 10px 0;
            z-index: 1000;
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

        .container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            padding: 20px;
        }

        .movie-box {
            width: 200px;
            height: 300px;
            background-color: #333;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .movie-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .movie-box:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.4);
        }

        .movie-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .movie-box:hover .movie-info {
            opacity: 1;
        }

        .side-box {
            width: 300px;
            padding: 20px;
            background-color: white;
            position: fixed;
            top: 50%;
            right: 20px;
            transform: translateY(-50%);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            display: none;
        }

        .side-box h2, .side-box p {
            margin: 0;
            padding: 10px 0;
        }

        .side-box .rating {
            font-size: 18px;
            font-weight: bold;
        }

        input[type="text"] {
            width: 300px;
            padding: 10px;
            border: none;
            border-radius: 4px;
            margin-right: 10px;
        }

        input[type="submit"] {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
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

<form method="POST" action="" style="text-align: center; margin-top: 30px;">
    <input type="text" name="movie_name" placeholder="Movie Name" required>
    <input type="submit" value="Search Movie">
</form>

<div class="container" id="movies-container"></div>

<div class="side-box" id="side-box">
    <h2 id="movie-title"></h2>
    <p><strong>Genre:</strong> <span id="movie-genre"></span></p>
    <p><strong>Director:</strong> <span id="movie-director"></span></p>
    <p><strong>Description:</strong> <span id="movie-description"></span></p>
    <p><strong>Release Date:</strong> <span id="movie-release"></span></p>
    <p><strong>Rating:</strong> <span id="movie-rating"></span></p>
</div>

<?php
require_once('/var/www/html/vendor/autoload.php'); // RabbitMQ autoload

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
            '/'
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

        // Wait for the response with a timeout of 30 seconds
        $timeout = 30;
        $startTime = time();

        while (!$response && (time() - $startTime) < $timeout) {
            $channel->wait(null, false, 5);  // 5-second wait timeout
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

// Handle form submission and send to RabbitMQ
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['movie_name'])) {
        $movie_name = htmlspecialchars($_POST['movie_name']);
        $request_data = json_encode(['name' => $movie_name]);

        // Sending movie request to RabbitMQ
        $response = sendToRabbitMQ('omdb_request_queue', $request_data);

        // DEBUG: Echo the response to check if it's being received properly
        echo "<pre>RabbitMQ Response: " . htmlspecialchars($response) . "</pre>";

        // Assuming the response is JSON with movie details (adjust according to your RabbitMQ response format)
        $movie_data = json_decode($response, true);

        if ($movie_data) {
            // DEBUG: Echo movie data as JSON for further inspection
            echo "<pre>Parsed Movie Data: " . json_encode($movie_data, JSON_PRETTY_PRINT) . "</pre>";
            echo "<script>let movies = " . json_encode($movie_data) . ";</script>";
        } else {
            echo "<pre>Error: Unable to parse movie data</pre>";
        }
    }
}
?>

<script>
    const moviesContainer = document.getElementById('movies-container');
    const sideBox = document.getElementById('side-box');

    // DEBUG: Log the movie data
    console.log("Movies data:", movies);

    if (typeof movies !== 'undefined' && movies.length > 0) {
        movies.forEach(movie => {
            const movieBox = document.createElement('div');
            movieBox.classList.add('movie-box');
            
            movieBox.innerHTML = `
                <img src="${movie.poster}" alt="${movie.title}">
                <div class="movie-info">${movie.title}</div>
            `;

            movieBox.addEventListener('mouseover', () => {
                document.getElementById('movie-title').textContent = movie.title;
                document.getElementById('movie-genre').textContent = movie.genre;
                document.getElementById('movie-director').textContent = movie.director;
                document.getElementById('movie-description').textContent = movie.description;
                document.getElementById('movie-release').textContent = movie.release;
                document.getElementById('movie-rating').textContent = movie.rating;
                sideBox.style.display = 'block';
            });

            movieBox.addEventListener('mouseout', () => {
                sideBox.style.display = 'none';
            });

            moviesContainer.appendChild(movieBox);
        });
    } else {
        console.log("No movie data found or movies array is empty.");
    }
</script>

</body>
</html>
