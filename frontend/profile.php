<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');
include 'header.php'; // Include the header (contains <header>)


// Check if session_token is set before trying to access it
if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];
    $username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';  // Get username from session

    // Start output buffering to prevent header issues
    ob_start();
}
else {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo $username; ?></title>
    <style>
        /* Additional styling for the profile page */
        .favorites-box {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }

        .favorites-box h2 {
            color: #795833;
            margin-bottom: 20px;
        }

        /* Existing styles from header.php can be reused or overridden here */
    </style>
</head>
<body>
    <div class="container">
        <?php
        echo "<h1>Welcome, $username</h1>";

        // Movie Search Form
        echo '<h2>Search for a Movie to Add to Your Watchlist</h2>';
        echo '<form method="POST" action="">';
        echo '<label for="movie_name">Enter Movie Name:</label>';
        echo '<input type="text" id="movie_name" name="movie_name" required>';
        echo '<button type="submit" name="search_movie">Search</button>';
        echo '</form>';

        // Handle the form submission for adding a movie
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_movie'])) {
            if (!empty($_POST['movie_name'])) {
                $movie_name = htmlspecialchars($_POST['movie_name']);
                $request_data = json_encode(['name' => $movie_name]);

                // Send movie name to RabbitMQ for searching
                $movie_data = sendToRabbitMQ('omdb_request_queue', $request_data);
                $movie_data = json_decode($movie_data, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "<p>Error decoding JSON response</p>";
                } elseif (isset($movie_data['Error'])) {
                    echo "<p>Error: " . htmlspecialchars($movie_data['Error']) . "</p>";
                } else {
                    $imdb_id = $movie_data['imdbID'];
                    echo "<div class='movie-card'>";
                    echo "<img src='" . htmlspecialchars($movie_data['Poster']) . "' alt='" . htmlspecialchars($movie_data['Title']) . "' class='movie-poster'>";
                    echo "<h3>" . htmlspecialchars($movie_data['Title']) . " (" . htmlspecialchars($movie_data['Year']) . ")</h3>";
                    echo "<p><strong>Genre:</strong> " . htmlspecialchars($movie_data['Genre']) . "</p>";
                    echo "<p><strong>Rating:</strong> " . htmlspecialchars($movie_data['imdbRating']) . "</p>";
                    echo '<form method="POST" action="">';
                    echo '<input type="hidden" name="imdb_id" value="' . htmlspecialchars($imdb_id) . '">';
                    echo '<button type="submit" name="add_to_watchlist">Add to Watchlist</button>';
                    echo '</form>';
                    echo "</div>";
                }
            }
        }

        // Add Movie to Watchlist
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_watchlist'])) {
            $imdb_id = $_POST['imdb_id'];
            $watchlist_data = json_encode([
                'action' => 'add_movie',
                'session_token' => $session_token,
                'imdb_id' => $imdb_id
            ]);

            // Send the add-to-watchlist request to RabbitMQ
            $watchlist_response = sendToRabbitMQ('watchlist_queue', $watchlist_data);
            $watchlist_result = json_decode($watchlist_response, true);

            if (isset($watchlist_result['message'])) {
                echo "<p>" . htmlspecialchars($watchlist_result['message']) . "</p>";
            } else {
                echo "<p>Error adding movie to watchlist.</p>";
            }
        }

        // Display Favorite Movies in "My Favorites" box using IMDb ID
        echo "<div class='favorites-box'>";
        echo "<h2>My Favorites</h2>";

        // Query the watchlist for the user's saved movies
        $favorites_request = json_encode([
            'action' => 'get_watchlist',
            'session_token' => $session_token
        ]);

        $favorites_response = sendToRabbitMQ('watchlist_queue', $favorites_request);

        // Log the raw favorites response for debugging
        error_log("Favorites response raw: " . $favorites_response);

        $favorites_data = json_decode($favorites_response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error decoding favorites response: " . json_last_error_msg());
            echo "<p>Error retrieving favorites.</p>";
        } elseif (isset($favorites_data['favorites']) && is_array($favorites_data['favorites'])) {
            foreach ($favorites_data['favorites'] as $favorite) {
                $imdb_id = $favorite['imdb_id'];

                // Log movie request to favorites_queue
                error_log("Sending movie request for IMDb ID $imdb_id to favorites_queue");

                // Fetch movie details from OMDb API using IMDb ID via favorites_queue
                $movie_request = json_encode(['id' => $imdb_id]);
                $movie_response = sendToRabbitMQ('favorites_queue', $movie_request);  // Use the new queue
                $movie_info = json_decode($movie_response, true);

                // Log the response from favorites_queue
                error_log("Response from favorites_queue for IMDb ID $imdb_id: " . print_r($movie_info, true));

                // Check if the movie information is valid
                if (isset($movie_info['Title'])) {
                    echo "<div class='movie-card'>";
                    echo "<img src='" . htmlspecialchars($movie_info['Poster']) . "' alt='" . htmlspecialchars($movie_info['Title']) . "' class='movie-poster'>";
                    echo "<h3>" . htmlspecialchars($movie_info['Title']) . " (" . htmlspecialchars($movie_info['Year']) . ")</h3>";
                    echo "<p><strong>Genre:</strong> " . htmlspecialchars($movie_info['Genre']) . "</p>";
                    echo "<p><strong>Rating:</strong> " . htmlspecialchars($movie_info['imdbRating']) . "</p>";
                    echo "</div>";
                } else {
                    // Log any errors when fetching movie data
                    echo "<p>Error: Could not retrieve details for IMDb ID: " . htmlspecialchars($imdb_id) . "</p>";
                }
            }
        } else {
            echo "<p>No favorites found.</p>";
        }

        echo "</div>";
        ?>
    </div>
</body>
</html>
