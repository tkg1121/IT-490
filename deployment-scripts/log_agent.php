<?php
// agent_receiver.php

require_once('../vendor/autoload.php'); // Adjust the path as needed
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// RabbitMQ credentials and configurations
$rabbitmqHost = $_ENV['RABBITMQ_HOST'];
$rabbitmqPort = $_ENV['RABBITMQ_PORT'];
$rabbitmqUser = $_ENV['RABBITMQ_USER'];
$rabbitmqPassword = $_ENV['RABBITMQ_PASSWORD'];
$rabbitmqQueue = $_ENV['RABBITMQ_QUEUE'];
$rabbitmqExchange = $_ENV['RABBITMQ_EXCHANGE'] ?? 'logs_distribution';
$rabbitmqExchangeType = $_ENV['RABBITMQ_EXCHANGE_TYPE'] ?? 'fanout';
$logStorageDir = rtrim($_ENV['LOG_STORAGE_DIR'] ?? '~/received_logs', '/');

// Machine tag
$machineTag = $_ENV['MACHINE_TAG'] ?? 'unknown';

// Log files to collect
$logs = [
    'apache_error'   => '/var/log/apache2/error.log',
    'apache_access'  => '/var/log/apache2/access.log',
    'mysql_error'    => '/var/log/mysql/error.log',
    'rabbitmq_log'   => '/var/log/rabbitmq/rabbitmq.log',
    'php_error'      => '/var/log/php_errors.log', // Adjust path as needed
];

// Function to read log files
function readLogFile($filePath) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return null;
    }
    return file_get_contents($filePath);
}

// Ensure log storage directory exists
if (!is_dir($logStorageDir)) {
    mkdir($logStorageDir, 0755, true);
}

try {
    // Establish connections to RabbitMQ
    $connectionSend = new AMQPStreamConnection(
        $rabbitmqHost,
        $rabbitmqPort,
        $rabbitmqUser,
        $rabbitmqPassword
    );
    $channelSend = $connectionSend->channel();

    $connectionReceive = new AMQPStreamConnection(
        $rabbitmqHost,
        $rabbitmqPort,
        $rabbitmqUser,
        $rabbitmqPassword
    );
    $channelReceive = $connectionReceive->channel();

    // Declare the queue for sending logs
    $channelSend->queue_declare($rabbitmqQueue, false, true, false, false);

    // Declare the exchange for receiving distributed logs
    $channelReceive->exchange_declare($rabbitmqExchange, $rabbitmqExchangeType, false, true, false);

    // Declare a temporary queue and bind it to the exchange
    list($queue_name, ,) = $channelReceive->queue_declare("", false, false, true, false);
    $channelReceive->queue_bind($queue_name, $rabbitmqExchange);

    echo " [*] Agent started. To exit press CTRL+C\n";

    // Function to save received logs
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

    // Callback function to process received logs
    $callback = function ($msg) {
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

        // Acknowledge the message
        $msg->ack();
    };

    // Set up consumer to receive logs
    $channelReceive->basic_qos(null, 1, null);
    $channelReceive->basic_consume($queue_name, '', false, false, false, false, $callback);

    // Variables to control log sending interval
    $lastSentTime = 0;
    $sendInterval = 60; // Send logs every 60 seconds

    // Main loop
    while (count($channelReceive->callbacks)) {
        // Check if it's time to send logs
        if (time() - $lastSentTime >= $sendInterval) {
            // Iterate through logs and send them
            foreach ($logs as $logName => $logPath) {
                $logContent = readLogFile($logPath);
                if ($logContent === null) {
                    echo "Skipping inaccessible log: $logPath\n";
                    continue;
                }

                // Prepare the message
                $data = [
                    'machine_tag' => $machineTag,
                    'log_name'    => $logName,
                    'log_content' => $logContent,
                    'timestamp'   => date('Y-m-d H:i:s'),
                ];

                $message = new AMQPMessage(json_encode($data), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]);

                // Publish the message to the queue
                $channelSend->basic_publish($message, '', $rabbitmqQueue);

                echo "Sent log: $logName from $machineTag\n";
            }
            $lastSentTime = time();
        }

        // Wait for incoming messages
        try {
            $channelReceive->wait(null, false, 1); // Wait with a timeout of 1 second
        } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
            // Timeout occurred, continue the loop
        } catch (Exception $e) {
            echo "Error while waiting for messages: " . $e->getMessage() . "\n";
            break;
        }

        // Sleep briefly to reduce CPU usage
        usleep(100000); // Sleep for 0.1 seconds
    }

    // Close the channels and connections when done
    $channelSend->close();
    $connectionSend->close();

    $channelReceive->close();
    $connectionReceive->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}