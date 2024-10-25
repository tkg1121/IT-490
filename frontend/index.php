<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'header.php';
require_once('rabbitmq_send.php');  // Include this instead of redeclaring the function

// Function to log data to a file
function log_json_data($data) {
    file_put_contents(__DIR__ . '/rabbitmq_json.log', $data . PHP_EOL, FILE_APPEND);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Search</title>
</head>
<body>
    <div class="container">
        <h1>Search for a Movie</h1>
        <form method="POST" action="">
            <label for="movie_name">Enter Movie Name:</label>
            <input type="text" id="movie_name" name="movie_name" required>
            <button type="submit">Search</button>
        </form>

        <div id="movie-results">
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if (!empty($_POST['movie_name'])) {
                    $movie_name = htmlspecialchars($_POST['movie_name']);  // Sanitize user input
                    $request_data = json_encode(['name' => $movie_name]);

                    // Display JSON data in the browser
                    echo "<h3>JSON Data Sent to RabbitMQ:</h3>";
                    echo "<pre>" . htmlspecialchars($request_data) . "</pre>";

                    // Log JSON data to a file
                    log_json_data("Index - Sending JSON to RabbitMQ: " . $request_data);

                    // Sending movie request to RabbitMQ
                    $movie_data = sendToRabbitMQ('omdb_request_queue', $request_data);

                    // Decode the JSON response
                    $movie_data = json_decode($movie_data, true);

                    // Check if JSON decoding was successful
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        echo "<h2>Error decoding JSON response</h2>";
                    } elseif (isset($movie_data['Error'])) {
                        // Handle error from OMDb API
                        echo "<h2>Error: " . $movie_data['Error'] . "</h2>";
                    } else {
                        // Show the movie card and link to the movie details page
                        echo "<a href='movie_details.php?movie_id=" . urlencode($movie_data['imdbID']) . "'>";
                        echo "<div class='movie-card'>";
                        echo "<img src='" . $movie_data['Poster'] . "' alt='" . $movie_data['Title'] . "' class='movie-poster'>";
                        echo "<h3>" . $movie_data['Title'] . " (" . $movie_data['Year'] . ")</h3>";
                        echo "<p><strong>Genre:</strong> " . $movie_data['Genre'] . "</p>";
                        echo "<p><strong>Rating:</strong> " . $movie_data['imdbRating'] . "</p>";
                        echo "</div>";
                        echo "</a>";
                    }
                }
            }
            ?>
        </div>
    </div>
</body>
</html>

