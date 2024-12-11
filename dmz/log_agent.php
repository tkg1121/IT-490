<?php
// log_agent.php

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

// Services to collect logs from
$services = [
    'auth_consumer.service',
    'database_package_consumer.service',
    'friends_consumer.service',
    'movies_consumer.service',
    'production_database_consumer.service',
    'social_media_consumer.service',
    'standby_database_consumer.service',
    'twofa_consumer.service',
    'frontend_package_consumer.service',
    'log_agent.service',
    'standby_frontend_consumer.service',
    'production_frontend_consumer.service',
    'dmz_consumer.service',
    'dmz_consumer_package.service',
    'favorites_consumer.service',
    'notify_consumer.service',
    'production_dmz_consumer.service',
    'standby_dmz_consumer.service',
    'tickets_consumer.service',
    'where_to_watch_consumer.service',
];

// Use epoch timestamps for since time to avoid parsing issues
$serviceLastTimestamp = [];
foreach ($services as $service) {
    $serviceLastTimestamp[$service] = '@0';
}

// Function to read logs from systemd journal for a given service since a given timestamp
function readServiceLogsSince($serviceName, $sinceTimestamp) {
    // Do not add extra quotes around %s, escapeshellarg will handle it
    $command = sprintf(
        '/usr/bin/journalctl -u %s --no-pager --since=%s 2>&1',
        escapeshellarg($serviceName),
        escapeshellarg($sinceTimestamp)
    );

    echo "[DEBUG] Running command: $command\n";

    $output = shell_exec($command);

    echo "[DEBUG] Output for $serviceName since $sinceTimestamp:\n";
    if (empty($output)) {
        echo "[DEBUG] No output from journalctl\n";
    } else {
        echo $output . "\n";
    }

    return empty($output) ? null : $output;
}

// Ensure log storage directory exists
if (!is_dir($logStorageDir)) {
    mkdir($logStorageDir, 0755, true);
}

try {
    echo "[DEBUG] Running as user: " . get_current_user() . "\n";
    echo "[DEBUG] Environment:\n";
    echo "MACHINE_TAG: $machineTag\n";
    echo "RABBITMQ_HOST: $rabbitmqHost\n";
    echo "RABBITMQ_QUEUE: $rabbitmqQueue\n";
    echo "LOG_STORAGE_DIR: $logStorageDir\n";

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
            // Iterate through services and send their logs only if there are new ones
            foreach ($services as $serviceName) {
                $since = $serviceLastTimestamp[$serviceName];
                echo "[DEBUG] Checking new logs for $serviceName since $since\n";
                $logContent = readServiceLogsSince($serviceName, $since);

                if ($logContent === null) {
                    echo "No new logs for service: $serviceName since $since\n";
                    continue;
                }

                // There are new logs, send them
                $data = [
                    'machine_tag' => $machineTag,
                    'log_name'    => $serviceName,
                    'log_content' => $logContent,
                    'timestamp'   => date('Y-m-d H:i:s'),
                ];

                $message = new AMQPMessage(json_encode($data), [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
                ]);

                // Publish the message to the queue
                $channelSend->basic_publish($message, '', $rabbitmqQueue);

                echo "Sent logs for service: $serviceName from $machineTag\n";

                // Update the last timestamp to current epoch time
                $serviceLastTimestamp[$serviceName] = '@' . time();
            }

            $lastSentTime = time();
        }

        // Wait for incoming messages
        try {
            $channelReceive->wait(null, false, 1);
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