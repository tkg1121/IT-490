<?php
// cinemas.php

require_once('/home/ashleys/IT-490/frontend/rabbitmq_send.php');

// Prepare the request data
$requestData = json_encode([
    'action' => 'cinemasNearby',
    'lat' => '40.7579',  // Example latitude
    'lng' => '-73.9878', // Example longitude
    'n' => 5             // Number of cinemas to retrieve
]);

// Send the request to RabbitMQ and get the response
$response = sendToRabbitMQ('cinemas_nearby_queue', $requestData);

$responseData = json_decode($response, true);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Nearby Cinemas</title>
    <!-- Include Bootstrap CSS for styling -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
</head>

<body>
<div class="container">
    <h1>Nearby Cinemas</h1>
    <?php if (isset($responseData['error'])): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($responseData['error']); ?>
        </div>
    <?php else: ?>
        <?php if (isset($responseData['cinemas']) && count($responseData['cinemas']) > 0): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Cinema Name</th>
                        <th>Address</th>
                        <th>Distance (Miles)</th>
                        <th>Buy Tickets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($responseData['cinemas'] as $cinema): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cinema['cinema_name']); ?></td>
                            <td>
                                <?php
                                echo htmlspecialchars($cinema['address']);
                                if ($cinema['address2']) {
                                    echo ', ' . htmlspecialchars($cinema['address2']);
                                }
                                echo ', ' . htmlspecialchars($cinema['city']) . ', ' . htmlspecialchars($cinema['state']) . ' ' . htmlspecialchars($cinema['postcode']);
                                ?>
                            </td>
                            <td><?php echo number_format($cinema['distance'], 2); ?></td>
                            <td>
                                <!-- Assuming you have ticket URLs; else, use Google Maps for directions -->
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($cinema['lat'] . ',' . $cinema['lng']); ?>" class="btn btn-primary" target="_blank">Get Directions</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No cinemas found nearby.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

