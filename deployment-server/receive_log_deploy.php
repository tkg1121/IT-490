<?php
// consumer.php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// RabbitMQ credentials
$rabbitmqHost = $_ENV['RABBITMQ_HOST'];
$rabbitmqPort = $_ENV['RABBITMQ_PORT'];
$rabbitmqUser = $_ENV['RABBITMQ_USER'];
$rabbitmqPassword = $_ENV['RABBITMQ_PASSWORD'];
$rabbitmqQueue = $_ENV['RABBITMQ_QUEUE'];
$logStorageDir = rtrim($_ENV['LOG_STORAGE_DIR'], '/');

// Exchange for distributing logs
$rabbitmqExchange = $_ENV['RABBITMQ_EXCHANGE'] ?? 'logs_distribution';
$rabbitmqExchangeType = $_ENV['RABBITMQ_EXCHANGE_TYPE'] ?? 'fanout';

// Ensure log storage directory exists
if (!is_dir($logStorageDir)) {
    mkdir($logStorageDir, 0755, true);
}

// Function to save logs
function saveLog($machineTag, $logName, $logContent) {
    global $logStorageDir;

    // Create directory for the machine tag if it doesn't exist
    $tagDir = "$logStorageDir/$machineTag";
    if (!is_dir($tagDir)) {
        mkdir($tagDir, 0755, true);
    }

    // Define log file path
    $logFilePath = "$tagDir/{$logName}.log";

    // Append log content with timestamp
    $timestamp = date('Y-m-d H:i:s');
    $formattedContent = "[$timestamp] $logContent\n";

    // Write to log file
    file_put_contents($logFilePath, $formattedContent, FILE_APPEND);
}

try {
    // Establish connection to RabbitMQ
    $connection = new AMQPStreamConnection(
        $rabbitmqHost,
        $rabbitmqPort,
        $rabbitmqUser,
        $rabbitmqPassword
    );
    $channel = $connection->channel();

    // Declare the queue
    $channel->queue_declare($rabbitmqQueue, false, true, false, false);

    // Declare the distribution exchange
    $channel->exchange_declare($rabbitmqExchange, $rabbitmqExchangeType, false, true, false);

    echo " [*] Waiting for messages. To exit press CTRL+C\n";

    // Callback function to process received messages
    $callback = function ($msg) use ($channel, $rabbitmqExchange) {
        $data = json_decode($msg->body, true);
        if ($data === null) {
            echo " [!] Received invalid JSON.\n";
            $msg->ack();
            return;
        }

        $machineTag = $data['machine_tag'] ?? 'unknown';
        $logName    = $data['log_name'] ?? 'unknown_log';
        $logContent = $data['log_content'] ?? '';

        echo " [x] Received log: $logName from $machineTag\n";

        // Save the log
        saveLog($machineTag, $logName, $logContent);

        // Publish the log to the distribution exchange
        $distributionData = [
            'machine_tag' => $machineTag,
            'log_name'    => $logName,
            'log_content' => $logContent,
            'timestamp'   => date('Y-m-d H:i:s'),
        ];

        $distributionMessage = new AMQPMessage(json_encode($distributionData), [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
        ]);

        $channel->basic_publish($distributionMessage, $rabbitmqExchange);

        echo " [>] Distributed log: $logName from $machineTag\n";

        // Acknowledge the message
        $msg->ack();
    };

    // Consume messages
    $channel->basic_qos(null, 1, null);
    $channel->basic_consume($rabbitmqQueue, '', false, false, false, false, $callback);

    // Keep the script running
    while ($channel->is_consuming()) {
        $channel->wait();
    }

    // Close the channel and connection
    $channel->close();
    $connection->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}