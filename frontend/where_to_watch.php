<?php
include 'rabbitmq_send.php';
include 'header.php';  // Include the navbar from header.php


// Fetch where-to-watch data if form is submitted
$watch_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['movie_name'])) {
    $movie_name = htmlspecialchars($_POST['movie_name']);
    $request_data = json_encode(['movie_name' => $movie_name]);
    $watch_data = json_decode(sendToRabbitMQ('where_to_watch_queue', $request_data), true);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Where to Watch</title>
    <style>
        /* General body styling */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f4f4f4;
        }

        /* Container for content */
        .content-wrapper {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f4f4f4;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            flex-grow: 1;
        }

        .container h1 {
            color: #333;
            text-align: center;
        }

        /* Styling for the movie card */
        .movie-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            text-align: center;
            margin-top: 20px;
        }

        .movie-card img {
            width: 100%;
            max-width: 200px;
            border-radius: 8px;
        }

        .movie-card h3, .movie-card p {
            color: #333;
        }

        .movie-card ul {
            list-style-type: none;
            padding: 0;
        }

        .movie-card ul li {
            margin: 10px 0;
        }

        .movie-card ul li a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s;
        }

        .movie-card ul li a:hover {
            color: #0056b3;
        }

        /* Responsive styling */
        @media (max-width: 768px) {
            .content-wrapper, .movie-card {
                padding: 15px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <div class="container">
            <h1>Find Where to Watch Movies</h1>
            <form method="POST" action="">
                <label for="movie_name">Enter Movie Name:</label>
                <input type="text" id="movie_name" name="movie_name" required>
                <button type="submit">Search</button>
            </form>

            <!-- Display movie details and where to watch links -->
            <div id="movie-results">
                <?php if (!empty($watch_data['title'])): ?>
                    <div class="movie-card">
                        <h3><?= htmlspecialchars($watch_data['title']) ?> (<?= htmlspecialchars($watch_data['year']) ?>)</h3>
                        <img src="<?= htmlspecialchars($watch_data['poster']) ?>" alt="<?= htmlspecialchars($watch_data['title']) ?>" class="movie-poster">
                        <p><strong>Genre:</strong> <?= htmlspecialchars($watch_data['genre']) ?></p>
                        <p><strong>Rating:</strong> <?= htmlspecialchars($watch_data['rating']) ?></p>

                        <h4>Available to Watch On:</h4>
                        <ul>
                            <?php foreach ($watch_data['watch_providers'] as $provider): ?>
                                <li><a href="<?= htmlspecialchars($provider['link']) ?>" target="_blank"><?= htmlspecialchars($provider['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                    <p>No streaming information found for "<?= htmlspecialchars($movie_name) ?>"</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

