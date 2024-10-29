<?php
// recommendation.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'header.php';
require_once('rabbitmq_send.php');
session_start();

// Check if the user is logged in
if (!isset($_COOKIE['session_token'])) {
    header('Location: login.php');
    exit;
}

$session_token = $_COOKIE['session_token'];
$username = isset($_COOKIE['username']) ? htmlspecialchars($_COOKIE['username']) : 'Guest';

// Handle pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$movies_per_page = 5; // Display five movies per page

// Send request to get recommended movies
$request_data = json_encode([
    'action' => 'get_recommendations',
    'session_token' => $session_token,
    'page' => $page,
    'movies_per_page' => $movies_per_page
]);

// Send the request to the recommendation_consumer via RabbitMQ
$recommendation_response = sendToRabbitMQ('recommendation_queue', $request_data);

// Log the raw response for debugging
error_log("Recommendation Response: " . $recommendation_response);

// Decode the JSON response
$recommendation_result = json_decode($recommendation_response, true);

// Check for JSON decoding errors
if (json_last_error() !== JSON_ERROR_NONE) {
    $error_message = 'Error decoding recommendation response: ' . json_last_error_msg();
    error_log($error_message);
} elseif (isset($recommendation_result['status']) && $recommendation_result['status'] === 'success') {
    $recommended_movies = $recommendation_result['movies'];
    $total_pages = isset($recommendation_result['total_pages']) ? intval($recommendation_result['total_pages']) : 1;
} else {
    $error_message = isset($recommendation_result['message']) ? htmlspecialchars($recommendation_result['message']) : 'An error occurred while fetching recommendations.';
    error_log("Recommendation Error Message: " . $error_message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Movie Recommendations</title>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <!-- Optional: Your existing styles.css -->
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Swiper container occupies full width */
        .swiper-container {
            width: 100%;
            padding: 20px 0;
        }

        /* Style for each movie card */
        .movie-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .movie-poster {
            width: 100%;
            height: auto;
            border-radius: 4px;
        }

        .movie-card h3 {
            margin: 10px 0 5px 0;
            font-size: 1.1em;
        }

        .movie-card p {
            margin: 5px 0;
            font-size: 0.9em;
            color: #555;
        }

        .movie-card a {
            margin-top: 10px;
            text-decoration: none;
            color: #3498db;
            font-weight: bold;
        }

        .movie-card a:hover {
            text-decoration: underline;
        }

        /* Swiper navigation arrows styling */
        .swiper-button-prev,
        .swiper-button-next {
            color: #3498db; /* Change arrow color as desired */
        }

        /* Swiper pagination dots styling */
        .swiper-pagination-bullet {
            background: #ccc;
            opacity: 1;
        }

        .swiper-pagination-bullet-active {
            background: #3498db;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .movie-card {
                padding: 10px;
            }

            .movie-card h3 {
                font-size: 1em;
            }

            .movie-card p {
                font-size: 0.8em;
            }
        }

        /* Additional Styles for Better Presentation */
        body {
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }

        .swiper-slide {
            display: flex;
            justify-content: center;
        }

        .pagination-container {
            text-align: center;
            margin-top: 20px;
        }

        /* Optional: Hide Swiper's default pagination if using custom */
        .swiper-pagination {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Recommended Movies for You, <?php echo htmlspecialchars($username); ?></h1>
        <?php if (isset($error_message)): ?>
            <p><?php echo $error_message; ?></p>
            <!-- Optionally display raw response for debugging (remove in production) -->
            <pre><?php echo htmlspecialchars($recommendation_response); ?></pre>
        <?php else: ?>
            <?php if (!empty($recommended_movies)): ?>
                <div class="swiper-container">
                    <div class="swiper-wrapper">
                        <?php foreach ($recommended_movies as $movie): ?>
                            <div class="swiper-slide">
                                <div class="movie-card">
                                    <?php 
                                        // Safely retrieve movie data with default values
                                        $poster = isset($movie['Poster']) && $movie['Poster'] !== 'N/A' ? htmlspecialchars($movie['Poster']) : 'default_poster.jpg';
                                        $title = isset($movie['Title']) ? htmlspecialchars($movie['Title']) : 'Unknown Title';
                                        $year = isset($movie['Year']) ? htmlspecialchars($movie['Year']) : 'Unknown Year';
                                        $genre = isset($movie['Genre']) ? htmlspecialchars($movie['Genre']) : 'Unknown Genre';
                                        $imdbRating = isset($movie['imdbRating']) ? htmlspecialchars($movie['imdbRating']) : 'N/A';
                                        $imdbID = isset($movie['imdbID']) ? urlencode($movie['imdbID']) : '#';
                                    ?>
                                    <img src="<?php echo $poster; ?>" alt="<?php echo $title; ?>" class="movie-poster">
                                    <h3><?php echo $title; ?> (<?php echo $year; ?>)</h3>
                                    <p><strong>Genre:</strong> <?php echo $genre; ?></p>
                                    <p><strong>Rating:</strong> <?php echo $imdbRating; ?></p>
                                    <a href="movie_details.php?movie_id=<?php echo $imdbID; ?>">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Add Pagination -->
                    <div class="swiper-pagination"></div>
                    
                    <!-- Add Navigation Arrows -->
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php else: ?>
                <p>No recommendations available at the moment. Please check back later.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Swiper JS -->
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>

    <!-- Initialize Swiper -->
    <script>
        var swiper = new Swiper('.swiper-container', {
            slidesPerView: 1, // Number of slides per view (adjust as needed)
            spaceBetween: 30, // Space between slides in px
            loop: false, // Set to true for continuous loop
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                // Adjust slidesPerView based on screen width for responsiveness
                640: {
                    slidesPerView: 2,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 4,
                    spaceBetween: 40,
                },
            }
        });
    </script>
</body>
</html>