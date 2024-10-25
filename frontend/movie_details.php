<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
include_once '/home/ashleys/IT-490/frontend/rabbitmq_send.php';

if (!isset($_COOKIE['session_token'])) {
    echo "<script>alert('Error: User not logged in');</script>";
    exit();
}

$session_token = $_COOKIE['session_token'];

if (isset($_GET['movie_id'])) {
    $movie_id = $_GET['movie_id'];

    $data = ['action' => 'fetch_movie_details', 'movie_id' => $movie_id];
    $response = sendToRabbitMQ('movie_queue', json_encode($data));
    $movie_data = json_decode($response, true);

    if ($movie_data['status'] !== 'success') {
        echo "<p>Movie not found.</p>";
        exit();
    }

    $review_data = ['action' => 'fetch_movie_reviews', 'movie_id' => $movie_id];
    $review_response = sendToRabbitMQ('review_queue', json_encode($review_data));
    $reviews = json_decode($review_response, true);

    if ($reviews['status'] !== 'success') {
        echo "<p>No reviews found for this movie.</p>";
        $reviews_list = [];
    } else {
        $reviews_list = $reviews['reviews'];
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
        <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie_id); ?>">
        <button type="submit">Submit Review</button>
    </form>

    <h2>Movie Reviews:</h2>
    <div id="movie-reviews">
        <?php if (count($reviews_list) > 0): ?>
            <?php foreach ($reviews_list as $review): ?>
                <div class="review">
                    <p><strong>User:</strong> <?php echo htmlspecialchars($review['username'] ?? 'Unknown'); ?></p>
                    <p><strong>Rating:</strong> <?php echo htmlspecialchars($review['rating'] ?? 'No rating'); ?> Stars</p>
                    <p><?php echo htmlspecialchars($review['review_text'] ?? 'No review text'); ?></p>

                    <?php if (isset($review['review_id'])): ?>
                        <button class="like-btn" data-review-id="<?php echo htmlspecialchars($review['review_id']); ?>">Like</button>
                        <button class="dislike-btn" data-review-id="<?php echo htmlspecialchars($review['review_id']); ?>">Dislike</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No reviews yet. Be the first to leave a review!</p>
        <?php endif; ?>
    </div>

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
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    });

    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', function() {
            let reviewId = this.getAttribute('data-review-id');
            handleLikeDislike(reviewId, 'like_review');
        });
    });

    document.querySelectorAll('.dislike-btn').forEach(button => {
        button.addEventListener('click', function() {
            let reviewId = this.getAttribute('data-review-id');
            handleLikeDislike(reviewId, 'dislike_review');
        });
    });

    function handleLikeDislike(reviewId, action) {
        let formData = new FormData();
        formData.append('review_id', reviewId);
        formData.append('action', action);

        fetch('sendToRabbitMQ.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
    </script>
</body>
</html>
