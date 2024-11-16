<?php
// receive_package.php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
$pdo = new PDO('mysql:host=localhost;dbname=deployment_db', 'dev', 'IT490tempPass1121!!@@');

// Establish connection to RabbitMQ
$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD']
);
$channel = $connection->channel();

// Declare queues
$channel->queue_declare('packages_queue', false, true, false, false);
$channel->queue_declare('deployment_status_queue', false, true, false, false);
$channel->queue_declare('package_requests_queue', false, true, false, false);

echo " [*] Waiting for packages and deployment statuses. To exit press CTRL+C\n";

// Callback for receiving packages
$packageCallback = function ($msg) use ($pdo, $channel) {
    $headers = $msg->get('application_headers');
    if ($headers) {
        $headers = $headers->getNativeData();
        $packageName = $headers['package_name'];
        $version = $headers['version'];
    } else {
        echo " [!] No headers found in the message.\n";
        return;
    }

    // Store package info with 'pending' status
    $stmt = $pdo->prepare("INSERT INTO packages (package_name, version, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$packageName, $version]);

    // Save package data to file system
    $packageDir = "/home/dev/Documents/GitHub/IT-490/deployment-server/packages/{$packageName}_v{$version}";
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    file_put_contents("{$packageDir}/package.zip", $msg->body);

    echo " [x] Received and stored package '{$packageName}' version {$version}\n";

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
};

// Callback for receiving deployment statuses
$statusCallback = function ($msg) use ($pdo) {
    $statusData = json_decode($msg->body, true);
    if ($statusData === null) {
        echo " [!] Received invalid JSON in status message.\n";
        $msg->ack();
        return;
    }

    $packageName = $statusData['package_name'];
    $version     = $statusData['version'];
    $status      = $statusData['status'];

    // Update package status in the database
    $stmt = $pdo->prepare("UPDATE packages SET status = ? WHERE package_name = ? AND version = ?");
    $stmt->execute([$status, $packageName, $version]);

    echo " [x] Updated status to '{$status}' for package '{$packageName}' version '{$version}'\n";

    // Acknowledge the message
    $msg->ack();
};

// Callback for handling package requests (e.g., rollback requests)
$packageRequestCallback = function ($msg) use ($pdo, $channel) {
    $requestData = json_decode($msg->body, true);
    if ($requestData === null) {
        echo " [!] Received invalid JSON in package request.\n";
        return;
    }

    $packageName = $requestData['package_name'];
    $action      = $requestData['action'];

    if ($action === 'get_last_successful_package') {
        // Fetch the last successful package from the database
        $stmt = $pdo->prepare("SELECT version FROM packages WHERE package_name = ? AND status = 'passed' ORDER BY version DESC LIMIT 1");
        $stmt->execute([$packageName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $version = $result['version'];
            $packagePath = "/home/dev/Documents/GitHub/IT-490/deployment-server/packages/{$packageName}_v{$version}/package.zip";
            if (file_exists($packagePath)) {
                $packageData = file_get_contents($packagePath);

                // Send the package back to the requester
                $responseMsg = new AMQPMessage($packageData, [
                    'correlation_id' => $msg->get('correlation_id'),
                ]);

                $channel->basic_publish($responseMsg, '', $msg->get('reply_to'));
                echo " [x] Sent last successful package '{$packageName}' version '{$version}'\n";
            } else {
                echo " [!] Package file not found at {$packagePath}\n";
                // Send an empty response or an error message
                $responseMsg = new AMQPMessage('', [
                    'correlation_id' => $msg->get('correlation_id'),
                ]);
                $channel->basic_publish($responseMsg, '', $msg->get('reply_to'));
            }
        } else {
            echo " [!] No successful package found for '{$packageName}'\n";
            // Send an empty response or an error message
            $responseMsg = new AMQPMessage('', [
                'correlation_id' => $msg->get('correlation_id'),
            ]);
            $channel->basic_publish($responseMsg, '', $msg->get('reply_to'));
        }
    } else {
        echo " [!] Unknown action '{$action}' in package request.\n";
    }
};

// Consume messages from the queues
$channel->basic_consume('packages_queue', '', false, true, false, false, $packageCallback);
$channel->basic_consume('deployment_status_queue', '', false, false, false, false, $statusCallback);
$channel->basic_consume('package_requests_queue', '', false, true, false, false, $packageRequestCallback);

while ($channel->is_consuming()) {
    $channel->wait();
}

// Close the channel and connection
$channel->close();
$connection->close();
?>