<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
<<<<<<< Updated upstream
include 'header.php';
=======
include 'header.php'; 
require_once('rabbitmq_send.php');  // Include this instead of redeclaring the function
>>>>>>> Stashed changes
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<<<<<<< Updated upstream
    <title>Login Page</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f3e4d0;
        }

        .login-container {
            width: 100%;
            height: 100vh;
            display: flex;
        }

        /* Left section for MovieMania */
        .left-half {
            background-color: #795833; /* Background color for the left side */
            width: 50%; /* Takes up half the screen */
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }

        /* Right section for Login box */
        .right-half {
            width: 50%; /* Takes up half the screen */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            background-color: #fff;
            padding: 90px;
            border-radius: 30px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-box h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #4d3b26;
        }

        .login-box .input-box {
            margin-bottom: 20px;
        }

        .login-box .input-box input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .login-box button {
            background-color: #80592d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .login-box button:hover {
            background-color: #69471d;
        }

        .login-box a {
            color: #80592d;
            text-decoration: none;
        }

        .login-box .sign-up {
            margin-top: 10px;
        }

        .movie-mania h1 {
            font-size: 36px;
            color: white;
            margin-bottom: 20px;
            font-family: 'Lobster', cursive;
        }

        .movie-mania img {
            width: 650px;
            height: auto;
            margin-bottom: 20px;
=======
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
            cursor: pointer;
        }

        .movie-card:hover {
            transform: scale(1.05);
        }

        .movie-poster {
            width: 100%;
            max-width: 200px;
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
>>>>>>> Stashed changes
        }
    </style>
</head>
<body>
<<<<<<< Updated upstream

    <div class="login-container">
        <!-- Left half for MovieMania -->
        <div class="left-half">
            <div class="movie-mania">
                <h1>"MovieMania: Where Every Flick Fuels the Frenzy!"</h1>
                <img src="images/LogIn.png" alt="MovieMania Logo">
            </div>
        </div>

        <!-- Right half for Login Box -->
        <div class="right-half">
            <div class="login-box">
                <?php
                if (isset($_SESSION['username'])) {
                    echo "<h2>Welcome, " . htmlspecialchars($_SESSION['username']) . "!</h2>";
                    echo "<p><a href='logout.php'>Log out</a></p>";
                } else {
                    echo '<h2>Log in page!</h2>';
                    echo '<form action="login.php" method="POST">
                            <div class="input-box">
                                <input type="text" name="username" placeholder="Username" required>
                            </div>
                            <div class="input-box">
                                <input type="password" name="password" placeholder="Password" required>
                            </div>
                            <button type="submit">Login</button>
                          </form>';
                    echo '
                    <div class="sign-up">
                        <p>Don\'t have an account with us?</p>
                        <a href="signup.html"><button>Sign Up</button></a>
                    </div>';
                }
                ?>
            </div>
        </div>
    </div>

=======
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
>>>>>>> Stashed changes
</body>
</html>
