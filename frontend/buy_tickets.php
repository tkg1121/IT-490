<?php
// buy_tickets.php

require_once('/home/ashleys/IT-490/frontend/rabbitmq_send.php'); // Update the path accordingly

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $movie_name = $_POST['movie_name'];
    $selected_date = $_POST['selected_date'];

    // Prepare the request data
    $requestData = json_encode([
        'action' => 'getShowtimesByName',
        'movie_name' => $movie_name,
        'date' => $selected_date,
    ]);

    // Send the request to RabbitMQ and get the response
    $response = sendToRabbitMQ('tickets_queue', $requestData);

    $responseData = json_decode($response, true);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Buy Movie Tickets</title>
    <!-- Include Bootstrap CSS for styling -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        .movie-poster {
            max-width: 300px;
            margin: 20px auto;
        }
        .movie-details {
            text-align: center;
            margin-bottom: 40px;
        }
    </style>
</head>

<body>
<div class="container">
    <h1>Buy Movie Tickets</h1>

    <!-- Movie Search Form -->
    <form method="post" action="buy_tickets.php">
        <div class="form-group">
            <label for="movie_name">Enter Movie Name:</label>
            <input type="text" class="form-control" id="movie_name" name="movie_name" required>
        </div>
        <div class="form-group">
            <label for="selected_date">Select Date:</label>
            <input type="date" class="form-control" id="selected_date" name="selected_date" required>
        </div>
        <button type="submit" class="btn btn-primary">Find Showtimes</button>
    </form>

    <?php if (isset($responseData)): ?>
        <?php if (isset($responseData['error'])): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlspecialchars($responseData['error']); ?>
            </div>
        <?php else: ?>
            <!-- Display Movie Details -->
            <?php if (isset($responseData['movie_details'])): ?>
                <div class="movie-details">
                    <?php if (!empty($responseData['movie_details']['poster_image'])): ?>
                        <img src="<?php echo htmlspecialchars($responseData['movie_details']['poster_image']); ?>" alt="Movie Poster" class="movie-poster img-responsive">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($responseData['movie_details']['film_name']); ?></h2>
                    <?php if (!empty($responseData['movie_details']['age_rating'])): ?>
                        <p><strong>Rating:</strong> <?php echo htmlspecialchars($responseData['movie_details']['age_rating']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($responseData['movie_details']['synopsis_long'])): ?>
                        <p><?php echo htmlspecialchars($responseData['movie_details']['synopsis_long']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <h2>Available Showtimes for "<?php echo htmlspecialchars($movie_name); ?>" on <?php echo htmlspecialchars($selected_date); ?></h2>
            <?php if (isset($responseData['showtimes']) && count($responseData['showtimes']) > 0): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Cinema</th>
                            <th>Time</th>
                            <th>Version</th>
                            <th>Buy Tickets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responseData['showtimes'] as $showtime): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($showtime['cinema_name']); ?></td>
                                <td><?php echo htmlspecialchars($showtime['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($showtime['version']); ?></td>
                                <td>
                                    <?php if (!empty($showtime['booking_link'])): ?>
                                        <a href="<?php echo htmlspecialchars($showtime['booking_link']); ?>" class="btn btn-success" target="_blank">Buy Tickets</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>No Booking Link</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No showtimes available for this movie on the selected date.</p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>