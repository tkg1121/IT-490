<?php
// tickets_consumer.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku');  // Load .env from /home/alisa-maloku
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Pull RabbitMQ credentials from .env file
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];

// Pull MovieGlu API credentials from .env file
$MOVIEGLU_CLIENT = $_ENV['MOVIEGLU_CLIENT']; // NJIT_1
$MOVIEGLU_API_KEY = $_ENV['MOVIEGLU_API_KEY']; // Your actual API key
$MOVIEGLU_AUTH = $_ENV['MOVIEGLU_AUTH']; // Your actual authorization token
$MOVIEGLU_TERRITORY = $_ENV['MOVIEGLU_TERRITORY']; // e.g., US
$MOVIEGLU_API_VERSION = $_ENV['MOVIEGLU_API_VERSION']; // v201
$LATITUDE = $_ENV['LATITUDE']; // e.g., -22.0
$LONGITUDE = $_ENV['LONGITUDE']; // e.g., 14.0

try {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();

    // Declare the queue for ticket requests
    $queue = 'tickets_queue';
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for ticket purchase requests...\n";

    // Function to search for a movie by name and get its ID
    function searchMovieByName($movie_name) {
        global $MOVIEGLU_CLIENT, $MOVIEGLU_API_KEY, $MOVIEGLU_AUTH,
               $MOVIEGLU_TERRITORY, $MOVIEGLU_API_VERSION, $LATITUDE, $LONGITUDE;

        $apiUrl = 'https://api-gate2.movieglu.com/filmLiveSearch/?query=' . urlencode($movie_name);

        $headers = [
            'client: ' . $MOVIEGLU_CLIENT,
            'x-api-key: ' . $MOVIEGLU_API_KEY,
            'authorization: ' . $MOVIEGLU_AUTH,
            'territory: ' . $MOVIEGLU_TERRITORY,
            'api-version: ' . $MOVIEGLU_API_VERSION,
            'geolocation: ' . $LATITUDE . ';' . $LONGITUDE,
            'device-datetime: ' . gmdate('Y-m-d\TH:i:s\.000\Z') // ISO 8601 format with milliseconds
        ];

        echo "Searching for movie: $movie_name\n";
        echo "API URL: $apiUrl\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            echo "Curl error: " . curl_error($ch) . "\n";
            return ['error' => 'API error: ' . curl_error($ch)];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        echo "HTTP Code: $http_code\n";
        echo "Response from filmLiveSearch:\n$response\n";

        if ($http_code != 200) {
            return ['error' => 'API error: HTTP code ' . $http_code];
        }

        $data = json_decode($response, true);

        if (isset($data['films']) && count($data['films']) > 0) {
            // Return the first matching film's data
            return $data['films'][0];
        } else {
            return ['error' => 'Movie not found'];
        }
    }

    // Function to fetch showtimes from MovieGlu API
    function fetchShowtimes($movie_id, $date) {
        global $MOVIEGLU_CLIENT, $MOVIEGLU_API_KEY, $MOVIEGLU_AUTH,
               $MOVIEGLU_TERRITORY, $MOVIEGLU_API_VERSION, $LATITUDE, $LONGITUDE;

        $apiUrl = 'https://api-gate2.movieglu.com/filmShowTimes/?film_id=' . urlencode($movie_id) . '&date=' . urlencode($date);

        $headers = [
            'client: ' . $MOVIEGLU_CLIENT,
            'x-api-key: ' . $MOVIEGLU_API_KEY,
            'authorization: ' . $MOVIEGLU_AUTH,
            'territory: ' . $MOVIEGLU_TERRITORY,
            'api-version: ' . $MOVIEGLU_API_VERSION,
            'geolocation: ' . $LATITUDE . ';' . $LONGITUDE,
            'device-datetime: ' . gmdate('Y-m-d\TH:i:s\.000\Z') // ISO 8601 format with milliseconds
        ];

        echo "Fetching showtimes for movie ID: $movie_id on date: $date\n";
        echo "API URL: $apiUrl\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            echo "Curl error: " . curl_error($ch) . "\n";
            return ['error' => 'API error: ' . curl_error($ch)];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        echo "HTTP Code: $http_code\n";
        echo "Response from filmShowTimes:\n$response\n";

        if ($http_code != 200) {
            return ['error' => 'API error: HTTP code ' . $http_code];
        }

        return json_decode($response, true);
    }

    // Function to fetch booking link using purchaseConfirmation endpoint
    function fetchBookingLink($cinema_id, $film_id, $date, $time) {
        global $MOVIEGLU_CLIENT, $MOVIEGLU_API_KEY, $MOVIEGLU_AUTH,
               $MOVIEGLU_TERRITORY, $MOVIEGLU_API_VERSION, $LATITUDE, $LONGITUDE;

        $apiUrl = 'https://api-gate2.movieglu.com/purchaseConfirmation/?cinema_id=' . urlencode($cinema_id) . '&film_id=' . urlencode($film_id) . '&date=' . urlencode($date) . '&time=' . urlencode($time);

        $headers = [
            'client: ' . $MOVIEGLU_CLIENT,
            'x-api-key: ' . $MOVIEGLU_API_KEY,
            'authorization: ' . $MOVIEGLU_AUTH,
            'territory: ' . $MOVIEGLU_TERRITORY,
            'api-version: ' . $MOVIEGLU_API_VERSION,
            'geolocation: ' . $LATITUDE . ';' . $LONGITUDE,
            'device-datetime: ' . gmdate('Y-m-d\TH:i:s\.000\Z') // ISO 8601 format with milliseconds
        ];

        echo "Fetching booking link for cinema ID: $cinema_id, film ID: $film_id, date: $date, time: $time\n";
        echo "API URL: $apiUrl\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            echo "Curl error: " . curl_error($ch) . "\n";
            return '';
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        echo "HTTP Code: $http_code\n";
        echo "Response from purchaseConfirmation:\n$response\n";

        if ($http_code != 200) {
            return '';
        }

        $data = json_decode($response, true);

        if (isset($data['url'])) {
            return $data['url'];
        } else {
            return '';
        }
    }

    // Callback function to handle messages
    $callback = function ($msg) use ($channel) {
        $requestData = json_decode($msg->body, true);
        echo "Received message:\n";
        print_r($requestData);

        $response = [];

        if (isset($requestData['action']) && $requestData['action'] == 'getShowtimesByName') {
            $movie_name = $requestData['movie_name'];
            $date = $requestData['date'];

            // Search for the movie by name to get its details
            $movie_data = searchMovieByName($movie_name);

            if (is_array($movie_data) && isset($movie_data['error'])) {
                // An error occurred during the movie search
                $response = $movie_data;
            } else {
                $movie_id = $movie_data['film_id'];
                // Fetch showtimes using the movie ID
                $apiResponse = fetchShowtimes($movie_id, $date);

                // Process the API response to extract necessary data
                if (isset($apiResponse['cinemas'])) {
                    $showtimes = [];
                    $showtimeCount = 0; // Counter to limit showtimes
                    $maxShowtimes = 10; // Maximum number of showtimes to process

                    foreach ($apiResponse['cinemas'] as $cinema) {
                        $cinema_id = $cinema['cinema_id'];
                        $cinema_name = $cinema['cinema_name'];
                        foreach ($cinema['showings'] as $version => $showing) {
                            if (isset($showing['times'])) {
                                foreach ($showing['times'] as $time) {
                                    if ($showtimeCount >= $maxShowtimes) {
                                        break 3; // Exit all loops when limit is reached
                                    }
                                    $start_time = $time['start_time'];
                                    // Fetch the booking link using purchaseConfirmation endpoint
                                    $booking_link = fetchBookingLink($cinema_id, $movie_id, $date, $start_time);
                                    $showtimes[] = [
                                        'cinema_name' => $cinema_name,
                                        'start_time' => $start_time,
                                        'booking_link' => $booking_link,
                                        'version' => $version
                                    ];
                                    $showtimeCount++;
                                }
                            }
                        }
                    }
                    if (count($showtimes) > 0) {
                        $response['showtimes'] = $showtimes;
                        $response['movie_details'] = [
                            'film_id' => $movie_data['film_id'],
                            'film_name' => $movie_data['film_name'],
                            'synopsis_long' => $movie_data['synopsis_long'] ?? '',
                            'age_rating' => $movie_data['age_rating'][0]['rating'] ?? '',
                            'poster_image' => $movie_data['images']['poster']['1']['medium']['film_image'] ?? ''
                        ];
                    } else {
                        $response = ['error' => 'No showtimes found for this movie on the selected date.'];
                    }
                } else {
                    $response = ['error' => 'No showtimes found for this movie on the selected date.'];
                }
            }

            echo "Processed getShowtimesByName request for movie \"$movie_name\". Sending response.\n";
            echo "Response being sent:\n";
            print_r($response);

        } else {
            // Invalid request
            $response = ['error' => 'Invalid request'];
            echo "Invalid request received.\n";
        }

        // Send the response back to RabbitMQ
        $reply_msg = new AMQPMessage(
            json_encode($response),
            ['correlation_id' => $msg->get('correlation_id')]
        );

        $channel->basic_publish($reply_msg, '', $msg->get('reply_to'));

        // Acknowledge the message
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
    };

    // Start consuming from the queue
    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Keep the consumer running indefinitely
    while (true) {
        try {
            $channel->wait();
        } catch (Exception $e) {
            echo "Error while waiting for messages: " . $e->getMessage() . "\n";
            break;
        }
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error connecting to RabbitMQ: " . $e->getMessage() . "\n";
}
?>