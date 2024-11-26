<?php
// friend_profile.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('rabbitmq_send.php');
include 'header.php'; // Include the header (contains <header>)

// Check if user is logged in
if (!isset($_COOKIE['session_token'])) {
    header('Location: login.php');
    exit();
}

$session_token = $_COOKIE['session_token'];
$friend_username = $_GET['username'] ?? null;

if (!$friend_username) {
    echo "Friend username not provided.";
    exit();
}

// Prepare data to fetch friend's profile
$data = [
    'action' => 'fetch_friend_profile',
    'session_token' => $session_token,
    'friend_username' => $friend_username
];

// Send request to RabbitMQ and get response
$response = sendToRabbitMQ('profile_queue', json_encode($data));

if (!$response) {
    echo "<p>Error: No response received from the server.</p>";
    exit();
}

$response_data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<p>Error decoding JSON response: " . json_last_error_msg() . "</p>";
    exit();
}

if (is_array($response_data) && isset($response_data['status'])) {
    if ($response_data['status'] === 'success') {
        // Get friend's profile data
        $username = htmlspecialchars($response_data['username']);
        $watchlist = $response_data['watchlist'] ?? [];
        $reviews = $response_data['reviews'] ?? [];
        $social_posts = $response_data['social_posts'] ?? [];
    } else {
        $error_message = $response_data['message'] ?? 'Unknown error';
        echo "<p>Error: " . htmlspecialchars($error_message) . "</p>";
        exit();
    }
} else {
    echo "<p>Error: Invalid response received from the server.</p>";
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $username; ?>'s Profile</title>
    <style>
        /* Include necessary CSS styles for movie cards, etc. */
        .favorites-box, .section {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }

        .favorites-box h2, .section h2 {
            color: #795833;
            margin-bottom: 20px;
        }

        .movie-card {
            display: flex;
            margin-bottom: 20px;
        }

        .movie-poster {
            width: 150px;
            height: auto;
            margin-right: 20px;
        }

        .movie-details {
            flex: 1;
        }

        .review-card {
            margin-bottom: 30px;
        }

        .review-details {
            margin-top: 10px;
        }

        .post-list {
            list-style: none;
            padding: 0;
        }

        .post-list li {
            margin-bottom: 20px;
        }

        /* Add other styles as needed */
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $username; ?>'s Profile</h1>

        <!-- Friend's Watchlist -->
        <div class="favorites-box">
            <h2><?php echo $username; ?>'s Watchlist</h2>
            <?php
            if (!empty($watchlist)) {
                foreach ($watchlist as $imdb_id) {
                    // Fetch movie details via RabbitMQ
                    $movie_request = json_encode(['id' => $imdb_id]);
                    $movie_response = sendToRabbitMQ('favorites_queue', $movie_request);
                    $movie_info = json_decode($movie_response, true);

                    // Display movie info
                    if (isset($movie_info['Title'])) {
                        echo "<div class='movie-card'>";
                        echo "<img src='" . htmlspecialchars($movie_info['Poster']) . "' alt='" . htmlspecialchars($movie_info['Title']) . "' class='movie-poster'>";
                        echo "<div class='movie-details'>";
                        echo "<h3>" . htmlspecialchars($movie_info['Title']) . " (" . htmlspecialchars($movie_info['Year']) . ")</h3>";
                        echo "<p><strong>Genre:</strong> " . htmlspecialchars($movie_info['Genre']) . "</p>";
                        echo "<p><strong>IMDb Rating:</strong> " . htmlspecialchars($movie_info['imdbRating']) . "</p>";
                        echo "</div>";
                        echo "</div>";
                    } else {
                        echo "<p>Error: Could not retrieve details for IMDb ID: " . htmlspecialchars($imdb_id) . "</p>";
                    }
                }
            } else {
                echo "<p>No movies in watchlist.</p>";
            }
            ?>
        </div>

        <!-- Friend's Reviews -->
        <div class="section">
            <h2><?php echo $username; ?>'s Reviews</h2>
            <?php
            if (!empty($reviews)) {
                foreach ($reviews as $review) {
                    $imdb_id = $review['imdb_id'];
                    $review_text = $review['review_text'];
                    $rating = $review['rating'];

                    // Fetch movie details via RabbitMQ
                    $movie_request = json_encode(['id' => $imdb_id]);
                    $movie_response = sendToRabbitMQ('favorites_queue', $movie_request);
                    $movie_info = json_decode($movie_response, true);

                    // Display review and movie info
                    if (isset($movie_info['Title'])) {
                        echo "<div class='review-card'>";
                        echo "<div class='movie-card'>";
                        echo "<img src='" . htmlspecialchars($movie_info['Poster']) . "' alt='" . htmlspecialchars($movie_info['Title']) . "' class='movie-poster'>";
                        echo "<div class='movie-details'>";
                        echo "<h3>" . htmlspecialchars($movie_info['Title']) . " (" . htmlspecialchars($movie_info['Year']) . ")</h3>";
                        echo "<p><strong>Genre:</strong> " . htmlspecialchars($movie_info['Genre']) . "</p>";
                        echo "<p><strong>IMDb Rating:</strong> " . htmlspecialchars($movie_info['imdbRating']) . "</p>";
                        echo "</div>";
                        echo "</div>";
                        echo "<div class='review-details'>";
                        echo "<p><strong>" . $username . "'s Rating:</strong> " . htmlspecialchars($rating) . "/5</p>";
                        echo "<p><strong>Review:</strong> " . nl2br(htmlspecialchars($review_text)) . "</p>";
                        echo "</div>";
                        echo "</div>";
                    } else {
                        echo "<p>Error: Could not retrieve details for IMDb ID: " . htmlspecialchars($imdb_id) . "</p>";
                    }
                }
            } else {
                echo "<p>No reviews.</p>";
            }
            ?>
        </div>

        <!-- Friend's Social Media Posts (Optional) -->
        <?php if (!empty($social_posts)): ?>
            <div class="section">
                <h2><?php echo $username; ?>'s Social Media Posts</h2>
                <ul class="post-list">
                    <?php foreach ($social_posts as $post): ?>
                        <li>
                            <div class="post-date">Posted on: <?php echo htmlspecialchars($post['created_at']); ?></div>
                            <div class="post-text"><?php echo nl2br(htmlspecialchars($post['post_text'])); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>