<?php
// receive_package.php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;
use PDO;
use PhpAmqpLib\Wire\AMQPTable;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
$pdo = new PDO('mysql:host=localhost;dbname=deployment_db', 'dev', 'IT490tempPass1121!!@@');

// RabbitMQ connection
$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD']
);
$channel = $connection->channel();

// Declare queues
$channel->queue_declare('packages_queue', false, true, false, false);

// Declare feedback queue
$channel->queue_declare('deployment_feedback_queue', false, true, false, false);

echo " [*] Waiting for packages. To exit press CTRL+C\n";

$callback = function ($msg) use ($pdo, $channel) {
    $headers = $msg->get('application_headers');
    if ($headers) {
        $headers = $headers->getNativeData();
        $packageName = $headers['package_name'];
    } else {
        echo " [!] No headers found in the message.\n";
        return;
    }

    // Get the latest version
    $stmt = $pdo->prepare("SELECT MAX(version) as max_version FROM packages WHERE package_name = ?");
    $stmt->execute([$packageName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newVersion = $result['max_version'] ? $result['max_version'] + 1 : 1;

    // Store package info with 'pending' status
    $stmt = $pdo->prepare("INSERT INTO packages (package_name, version, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$packageName, $newVersion]);
    $packageId = $pdo->lastInsertId();

    // Save package data to file system
    $packageDir = "/path/to/packages/{$packageName}_v{$newVersion}";
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    file_put_contents("{$packageDir}/package.zip", $msg->body);

    echo " [x] Received and stored package '{$packageName}' version {$newVersion}\n";

    // Create a correlation ID to track the package
    $correlationId = uniqid();

    // Add the package ID and correlation ID to the message headers
    $headers['package_id'] = $packageId;
    $headers['correlation_id'] = $correlationId;
    $msg->set('application_headers', new AMQPTable($headers));

    // Set reply_to queue for feedback
    $msg->set('reply_to', 'deployment_feedback_queue');

    // Route package to appropriate queue
    switch ($packageName) {
        case 'frontend':
            $channel->basic_publish($msg, '', 'frontend_packages_queue');
            break;
        case 'database':
            $channel->basic_publish($msg, '', 'database_packages_queue');
            break;
        case 'dmz':
            $channel->basic_publish($msg, '', 'dmz_packages_queue');
            break;
        default:
            echo " [!] Unknown package name: $packageName\n";
            break;
    }

    echo " [x] Sent package '{$packageName}' version {$newVersion} to target machine\n";

    // Set up a consumer to listen for feedback
    $feedbackCallback = function ($feedbackMsg) use ($pdo, $packageId, $channel, $packageName, $packageDir, $msg) {
        $feedbackData = json_decode($feedbackMsg->body, true);
        if ($feedbackData === null) {
            echo " [!] Received invalid JSON feedback.\n";
            return;
        }

        $receivedCorrelationId = $feedbackMsg->get('correlation_id');
        $expectedCorrelationId = $msg->get('application_headers')->getNativeData()['correlation_id'];

        // Ensure the feedback is for this package
        if ($receivedCorrelationId !== $expectedCorrelationId) {
            echo " [!] Received feedback with mismatched correlation ID.\n";
            return;
        }

        $status = $feedbackData['status']; // 'passed' or 'failed'

        // Update package status in the database
        $stmt = $pdo->prepare("UPDATE packages SET status = ? WHERE id = ?");
        $stmt->execute([$status, $packageId]);

        echo " [x] Deployment of package '{$packageName}' version {$packageId} {$status}\n";

        if ($status === 'failed') {
            // Get the last known good version
            $stmt = $pdo->prepare("SELECT version FROM packages WHERE package_name = ? AND status = 'passed' ORDER BY version DESC LIMIT 1");
            $stmt->execute([$packageName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $lastGoodVersion = $result['version'];
                $lastGoodPackageDir = "/path/to/packages/{$packageName}_v{$lastGoodVersion}";
                $lastGoodPackagePath = "{$lastGoodPackageDir}/package.zip";

                if (file_exists($lastGoodPackagePath)) {
                    // Read the package data
                    $packageData = file_get_contents($lastGoodPackagePath);

                    // Create a new message with the last good package
                    $rollbackMsg = new AMQPMessage($packageData, [
                        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                        'application_headers' => new AMQPTable([
                            'package_name' => $packageName,
                            'version' => $lastGoodVersion,
                            'rollback' => true,
                        ]),
                    ]);

                    // Send the rollback package to the target machine
                    switch ($packageName) {
                        case 'frontend':
                            $channel->basic_publish($rollbackMsg, '', 'frontend_packages_queue');
                            break;
                        case 'database':
                            $channel->basic_publish($rollbackMsg, '', 'database_packages_queue');
                            break;
                        case 'dmz':
                            $channel->basic_publish($rollbackMsg, '', 'dmz_packages_queue');
                            break;
                        default:
                            echo " [!] Unknown package name: $packageName\n";
                            break;
                    }

                    echo " [x] Sent rollback package '{$packageName}' version {$lastGoodVersion} to target machine\n";
                } else {
                    echo " [!] Last known good package file not found for '{$packageName}'\n";
                }
            } else {
                echo " [!] No previous successful package found for '{$packageName}' to rollback\n";
            }
        }

        // Acknowledge the feedback message
        $feedbackMsg->ack();

        // Cancel the consumer after receiving the feedback
        $channel->basic_cancel($feedbackMsg->get('consumer_tag'));
    };

    // Consume feedback messages
    $channel->basic_consume('deployment_feedback_queue', '', false, false, false, false, $feedbackCallback);

    // Wait for feedback
    while ($channel->callbacks) {
        $channel->wait();
    }
};

// Start consuming packages
$channel->basic_consume('packages_queue', '', false, false, false, false, $callback);

// Keep the script running
while ($channel->is_consuming()) {
    $channel->wait();
}

// Close the channel and connection
$channel->close();
$connection->close();
?>
