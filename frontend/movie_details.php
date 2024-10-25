<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';

// Ensure session is already active from header.php
if (!isset($_COOKIE['session_token'])) {
    echo "<script>alert('Error: User not logged in');</script>";
    exit();
}

// Check if user is logged in by validating session token
$session_token = $_COOKIE['session_token'];

// Fetch movie details from RabbitMQ
if (isset($_GET['movie_id'])) {
    $movie_id = $_GET['movie_id'];

    // Add debugging log for movie_id
    error_log("Movie ID received in movie_details.php: " . $movie_id);

    // Fetch movie details via RabbitMQ
    $data = ['action' => 'fetch_movie_details', 'movie_id' => $movie_id];
    $response = sendToRabbitMQ('movie_queue', json_encode($data));
    $movie_data = json_decode($response, true);

    if ($movie_data['status'] !== 'success') {
        echo "<p>Movie not found.</p>";
        exit();
    }
} else {
    echo "<p>Error: No movie ID provided.</p>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie_data['Title']); ?></title>
</head>
<body>
    <h1><?php echo htmlspecialchars($movie_data['Title']); ?> (<?php echo htmlspecialchars($movie_data['Year']); ?>)</h1>
    <img src="<?php echo htmlspecialchars($movie_data['Poster']); ?>" alt="<?php echo htmlspecialchars($movie_data['Title']); ?>">
    <p><strong>Genre:</strong> <?php echo htmlspecialchars($movie_data['Genre']); ?></p>
    <p><strong>Plot:</strong> <?php echo htmlspecialchars($movie_data['Plot']); ?></p>

    <!-- Review Form -->
    <h2>Leave a Review:</h2>
    <form id="review-form">
        <textarea name="review_text" placeholder="Your review..."></textarea>
        <label for="rating">Rating:</label>
        <select name="rating" id="rating">
            <option value="1">1 Star</option>
            <option value="2">2 Stars</option>
            <option value="3">3 Stars</option>
            <option value="4">4 Stars</option>
            <option value="5">5 Stars</option>
        </select>
        <!-- Correctly pass the movie_id -->
        <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie_id); ?>">
        <button type="submit">Submit Review</button>
    </form>

    <script>
    document.getElementById('review-form').addEventListener('submit', function(event) {
        event.preventDefault();
        let formData = new FormData(this);

        fetch('add_review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Review added!');
                location.reload();  // Refresh the page to show the new review
            } else {
                alert('Error: ' + data.message);
            }
        });
    });
    </script>
</body>
</html>

