<?php
include 'rabbitmq_send.php';
include 'header.php';  // Include the navbar from header.php
require_once '../src/db.php';
require_once '../src/recommendation.php';

// Assume the user is logged in and has a user ID.
$user_id = 1; // Example user ID

// Get top 5 recommended movies based on the user's watchlist
$recommended_movies = getRecommendedMovies($user_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Movie Recommendations</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Your Recommended Movies</h1>
    <ul>
        <?php foreach ($recommended_movies as $movie): ?>
            <li><?= htmlspecialchars($movie['title']) ?> (Genre: <?= htmlspecialchars($movie['genres']) ?>)</li>
        <?php endforeach; ?>
    </ul>
</body>
</html>

