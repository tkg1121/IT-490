<?php 
include 'header.php'; 
require_once('rabbitmq_send.php');  // Include the RabbitMQ function from here
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Search</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(45deg, hotpink, purple);
            color: white;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        h1 {
            margin-top: 50px;
            font-size: 3em;
            color: #fff;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        form {
            margin: 30px 0;
        }

        label {
            font-size: 1.2em;
        }

        input[type="text"] {
            padding: 10px;
            font-size: 1em;
            width: 80%;
            border-radius: 10px;
            border: none;
            margin-bottom: 15px;
        }

        button {
            padding: 10px 20px;
            font-size: 1em;
            background-color: hotpink;
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: purple;
        }

        .movie-card {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            text-align: left;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
            transition: transform 0.3s;
        }

        .movie-card:hover {
            transform: scale(1.05);
        }

        .extra-info {
            display: none;
            position: absolute;
            top: 0;
            right: -250px;
            width: 250px;
            background-color: lightgray;
            color: black;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }

        .movie-card:hover .extra-info {
            display: block;
        }

        .movie-poster {
            width: 100%;
            max-width: 250px;
            border-radius: 10px;
            display: block;
            margin: 0 auto 20px;
        }

        h3 {
            font-size: 1.5em;
            color: #fff;
        }

        p {
            font-size: 1.1em;
            margin: 10px 0;
        }

        strong {
            color: hotpink;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Search for a Movie</h1>
        <form method="POST" action="">
            <label for="movie_name">Enter Movie Name:</label>
            <input type="text" id="movie_name" name="movie_name" required>
            <button type="submit">Search</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!empty($_POST['movie_name'])) {
                $movie_name = htmlspecialchars($_POST['movie_name']);  // Sanitize user input
                $request_data = json_encode(['name' => $movie_name]);

                // Sending movie request to RabbitMQ
                $movie_data = sendToRabbitMQ('omdb_request_queue', $request_data);

                // Check if data is valid and display the movie card
                if (isset($movie_data['Error'])) {
                    echo "<h2>Error: " . $movie_data['Error'] . "</h2>";
                } else {
                    echo "<div class='movie-card'>";
                    echo "<img src='" . $movie_data['Poster'] . "' alt='" . $movie_data['Title'] . "' class='movie-poster'>";
                    echo "<h3>" . $movie_data['Title'] . " (" . $movie_data['Year'] . ")</h3>";
                    echo "<p><strong>Genre:</strong> " . $movie_data['Genre'] . "</p>";
                    echo "<p><strong>Rating:</strong> " . $movie_data['imdbRating'] . "</p>";
                    echo "<p><strong>Plot:</strong> " . $movie_data['Plot'] . "</p>";

                    // Extra info section
                    echo "<div class='extra-info'>";
                    echo "<p><strong>Writer:</strong> " . $movie_data['Writer'] . "</p>";
                    echo "<p><strong>Actors:</strong> " . $movie_data['Actors'] . "</p>";
                    echo "<p><strong>Country:</strong> " . $movie_data['Country'] . "</p>";
                    echo "</div>";

                    echo "</div>";
                }
            }
        }
        ?>
    </div>
</body>
</html>
