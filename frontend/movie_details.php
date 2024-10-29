<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
include_once '/home/ashleys/IT-490/frontend/rabbitmq_send.php';

session_start();

// Check if the user is logged in by verifying the session token
if (!isset($_COOKIE['session_token'])) {
    echo "<script>alert('Error: User not logged in'); window.location.href = 'login.php';</script>";
    exit();
}

$session_token = $_COOKIE['session_token'];

// Check if a movie ID is provided via GET parameters
if (isset($_GET['movie_id'])) {
    $movie_id = $_GET['movie_id'];

    // Prepare the request to fetch movie details
    $data = ['action' => 'fetch_movie_details', 'movie_id' => $movie_id];
    $response = sendToRabbitMQ('movie_queue', json_encode($data));
    $movie_data = json_decode($response, true);

    // Validate the response from the movie_queue
    if (!isset($movie_data['status']) || $movie_data['status'] !== 'success' || !isset($movie_data['movie'])) {
        echo "<p>Movie not found.</p>";
        exit();
    }

    // Prepare the request to fetch movie reviews
    $review_data = ['action' => 'fetch_movie_reviews', 'movie_id' => $movie_id];
    $review_response = sendToRabbitMQ('review_queue', json_encode($review_data));
    $reviews = json_decode($review_response, true);

    // Validate the response from the review_queue
    if (!isset($reviews['status']) || $reviews['status'] !== 'success') {
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
    <title><?php echo htmlspecialchars($movie_data['movie']['Title'] ?? 'No Title'); ?></title>
    <style>
        /* Basic styling for the page */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .movie-card, .review {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .movie-card img {
            max-width: 200px;
            height: auto;
        }
        #movie-reviews .review {
            background-color: #f9f9f9;
        }
        button.like-btn, button.dislike-btn {
            margin-right: 10px;
        }
        textarea {
            width: 100%;
            height: 100px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <h1><?php echo htmlspecialchars($movie_data['movie']['Title'] ?? 'No Title'); ?> (<?php echo htmlspecialchars($movie_data['movie']['Year'] ?? 'Unknown Year'); ?>)</h1>
    <img src="<?php echo htmlspecialchars($movie_data['movie']['Poster'] ?? 'default_poster.jpg'); ?>" alt="<?php echo htmlspecialchars($movie_data['movie']['Title'] ?? 'No Title'); ?>">
    <p><strong>Genre:</strong> <?php echo htmlspecialchars($movie_data['movie']['Genre'] ?? 'Unknown Genre'); ?></p>
    <p><strong>Plot:</strong> <?php echo htmlspecialchars($movie_data['movie']['Plot'] ?? 'No plot available.'); ?></p>

    <h2>Leave a Review:</h2>
    <form id="review-form">
        <textarea name="review_text" placeholder="Your review..." required></textarea><br><br>
        <label for="rating">Rating:</label>
        <select name="rating" id="rating" required>
            <option value="">Select Rating</option>
            <option value="1">1 Star</option>
            <option value="2">2 Stars</option>
            <option value="3">3 Stars</option>
            <option value="4">4 Stars</option>
            <option value="5">5 Stars</option>
        </select><br><br>
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
                    <p><strong>Likes:</strong> <?php echo htmlspecialchars($review['like_count'] ?? 0); ?>
                       <strong>Dislikes:</strong> <?php echo htmlspecialchars($review['dislike_count'] ?? 0); ?></p>

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
                alert('Review added successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred while adding your review.');
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
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred while processing your request.');
        });
    }
    </script>
</body>
</html>
