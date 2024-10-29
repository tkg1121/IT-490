<?php
require_once('/home/stanley/consumers/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);  // Load .env from the same directory
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

error_log("Database Setup: Starting database.php");

// Pull credentials from .env file
$RABBITMQ_HOST = getenv('RABBITMQ_HOST');
$RABBITMQ_PORT = getenv('RABBITMQ_PORT');
$RABBITMQ_USER = getenv('RABBITMQ_USER');
$RABBITMQ_PASS = getenv('RABBITMQ_PASS');
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');

// Establish RabbitMQ connection for logging or other purposes (if needed)
$connection = new AMQPStreamConnection(
    $RABBITMQ_HOST,
    $RABBITMQ_PORT,
    $RABBITMQ_USER,
    $RABBITMQ_PASS,
    '/',
    false,
    'AMQPLAIN',
    null,
    'en_US',
    10.0,
    10.0,
    null,
    false,
    60
);

error_log("Database Setup: Connected to RabbitMQ");

// Create the database connection
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

// Check connection
if ($mysqli->connect_error) {
    error_log("Database Setup: Database connection failed: " . $mysqli->connect_error);
    die("Database connection failed: " . $mysqli->connect_error);
}

error_log("Database Setup: Database connection successful");

// Function to create the required tables for the recommendation system
function createRecommendationTables($conn) {
    // Create the movie_weights table
    $createWeightsTable = "CREATE TABLE IF NOT EXISTS movie_weights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        genre VARCHAR(255) NOT NULL,
        weight INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES user_auth.users(id) ON DELETE CASCADE
    )";

    if ($conn->query($createWeightsTable) === TRUE) {
        error_log("Database Setup: Table movie_weights created successfully.");
    } else {
        error_log("Database Setup: Error creating table movie_weights: " . $conn->error);
    }

    // You can add more table creation queries here if needed
}

// Call the function to create the tables
createRecommendationTables($mysqli);

// Close the database connection
$mysqli->close();
error_log("Database Setup: Closed database connection");

$connection->close();
error_log("Database Setup: Closed RabbitMQ connection");
?>
