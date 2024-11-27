<?php
// notify_consumer.php

require_once('/home/alisa-maloku/Documents/GitHub/IT-490/dmz/vendor/autoload.php');  // Path to php-amqplib autoload
$dotenv = Dotenv\Dotenv::createImmutable('/home/alisa-maloku');  // Load .env from /home/alisa-maloku
$dotenv->load();

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment variables
$RABBITMQ_HOST = $_ENV['RABBITMQ_HOST'];
$RABBITMQ_PORT = $_ENV['RABBITMQ_PORT'];
$RABBITMQ_USER = $_ENV['RABBITMQ_USER'];
$RABBITMQ_PASS = $_ENV['RABBITMQ_PASS'];
$EMAIL_HOST = $_ENV['EMAIL_HOST'];
$EMAIL_USERNAME = $_ENV['EMAIL_USERNAME'];
$EMAIL_PASSWORD = $_ENV['EMAIL_PASSWORD'];
$EMAIL_PORT = $_ENV['EMAIL_PORT'];
$EMAIL_FROM = $_ENV['EMAIL_FROM'];
$EMAIL_FROM_NAME = $_ENV['EMAIL_FROM_NAME'];

try {
    // Connect to RabbitMQ
    $connection = new AMQPStreamConnection(
        $RABBITMQ_HOST,
        $RABBITMQ_PORT,
        $RABBITMQ_USER,
        $RABBITMQ_PASS
    );

    $channel = $connection->channel();
    $queue = 'notify_queue';

    // Declare the queue for notifications
    $channel->queue_declare($queue, false, true, false, false);

    echo "Waiting for notification messages...\n";

    // Callback function to handle messages
    $callback = function ($msg) use ($channel) {
        $requestData = json_decode($msg->body, true);

        $email = $requestData['email'] ?? null;
        $subject = $requestData['subject'] ?? 'Notification';
        $body = $requestData['body'] ?? '';

        if ($email && $body) {
            // Send email
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = $_ENV['EMAIL_HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['EMAIL_USERNAME'];
                $mail->Password = $_ENV['EMAIL_PASSWORD'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $_ENV['EMAIL_PORT'];

                // Recipients
                $mail->setFrom($_ENV['EMAIL_FROM'], $_ENV['EMAIL_FROM_NAME']);
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;

                $mail->send();
                echo "Notification sent to {$email}\n";
            } catch (Exception $e) {
                echo "Notification could not be sent to {$email}. Error: {$mail->ErrorInfo}\n";
            }
        } else {
            echo "Invalid notification data received.\n";
        }

        // Acknowledge the message
        $channel->basic_ack($msg->delivery_info['delivery_tag']);
    };

    // Start consuming from the queue
    $channel->basic_consume($queue, '', false, false, false, false, $callback);

    // Keep the consumer running indefinitely
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close the channel and connection when done
    $channel->close();
    $connection->close();
} catch (Exception $e) {
    echo "Error in notify_consumer.php: " . $e->getMessage() . "\n";
}