<?php
// receive_package.php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// MySQL connection parameters
$mysqli = new mysqli(
    $_ENV['MYSQL_HOST'] ?? 'localhost',
    $_ENV['MYSQL_USER'] ?? 'deploy_user',
    $_ENV['MYSQL_PASSWORD'] ?? 'your_secure_password',
    $_ENV['MYSQL_DATABASE'] ?? 'deployment'
);

// Check MySQL connection
if ($mysqli->connect_error) {
    die("MySQL Connection failed: " . $mysqli->connect_error . "\n");
}

// Establish connection to RabbitMQ
try {
    $connection = new AMQPStreamConnection(
        $_ENV['RABBITMQ_HOST'],
        $_ENV['RABBITMQ_PORT'],
        $_ENV['RABBITMQ_USER'],
        $_ENV['RABBITMQ_PASSWORD']
    );
    $channel = $connection->channel();
} catch (Exception $e) {
    echo "Failed to connect to RabbitMQ: " . $e->getMessage() . "\n";
    exit(1);
}

// Define the queues mapping
$queuesMapping = [
    'frontend' => [
        'production' => 'production_frontend_queue',
        'standby'     => 'standby_frontend_queue'
    ],
    'database' => [
        'production' => 'production_database_queue',
        'standby'     => 'standby_database_queue'
    ],
    'dmz' => [
        'production' => 'production_dmz_queue',
        'standby'     => 'standby_dmz_queue'
    ]
];

// Declare necessary queues based on mapping
foreach ($queuesMapping as $package => $queues) {
    foreach ($queues as $environment => $queueName) {
        $channel->queue_declare($queueName, false, true, false, false);
    }
}

// Declare the incoming packages queue
$channel->queue_declare('packages_queue', false, true, false, false);

// Declare the deployment status queue
$channel->queue_declare('deployment_status_queue', false, true, false, false);

// Declare the package_requests_queue for handling rollback requests
$channel->queue_declare('package_requests_queue', false, true, false, false);

echo " [*] Waiting for packages and status messages. To exit press CTRL+C\n";

// Handle incoming packages
$packageCallback = function ($msg) use ($channel, $mysqli, $queuesMapping) {
    echo " [x] Received package from packages_queue\n";

    // Extract headers
    $headers = $msg->get('application_headers');
    $headers = $headers ? $headers->getNativeData() : [];
    $packageName = strtolower($headers['package_name'] ?? 'unknown');
    $packageUUID = $headers['package_uuid'] ?? 'unknown';

    // Validate package_name
    if (!array_key_exists($packageName, $queuesMapping)) {
        echo " [!] Unknown package name '{$packageName}'. Ignoring package.\n";
        $msg->ack();
        return;
    }

    // Get the current maximum version for this package
    $stmt = $mysqli->prepare("SELECT MAX(version) AS max_version FROM packages WHERE package_name = ?");
    $stmt->bind_param("s", $packageName);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $currentVersion = $row['max_version'] ?? 0;
    $newVersion = $currentVersion + 1;
    $stmt->close();

    // Define the storage path
    $packageDir = "/home/dev/packages/{$packageName}_v{$newVersion}";
    $packagePath = "{$packageDir}/package.zip";

    if (!file_exists($packageDir)) {
        if (!mkdir($packageDir, 0755, true)) {
            echo " [!] Failed to create directory {$packageDir}\n";
            // Optionally, you can nack the message or handle it as needed
            $msg->ack(); // Acknowledge to prevent requeueing
            return;
        }
    }

    // Save the package
    if (file_put_contents($packagePath, $msg->body) === false) {
        echo " [!] Failed to save package to {$packagePath}\n";
        // Optionally, you can nack the message or handle it as needed
        $msg->ack();
        return;
    }
    echo " [x] Package saved to {$packagePath}\n";

    // Insert package info into MySQL
    $stmt = $mysqli->prepare("INSERT INTO packages (package_name, package_uuid, version, status, file_path) VALUES (?, ?, ?, 'received', ?)");
    $stmt->bind_param("ssis", $packageName, $packageUUID, $newVersion, $packagePath);
    if ($stmt->execute()) {
        echo " [x] Package info inserted into MySQL\n";
    } else {
        echo " [!] Failed to insert package info into MySQL: " . $stmt->error . "\n";
    }
    $stmt->close();

    // Acknowledge the message
    $msg->ack();
};

// Handle deployment status messages
$statusCallback = function ($msg) use ($channel, $mysqli, $queuesMapping) {
    echo " [x] Received deployment status message\n";

    $statusData = json_decode($msg->body, true);
    if (!$statusData) {
        echo " [!] Invalid status message format\n";
        $msg->ack();
        return;
    }

    $packageUUID = $statusData['package_uuid'] ?? 'unknown';
    $status = strtolower($statusData['status'] ?? '');

    // Validate status
    if (!in_array($status, ['passed', 'failed'])) {
        echo " [!] Invalid status '{$status}' received for package UUID '{$packageUUID}'\n";
        $msg->ack();
        return;
    }

    // Update the package status in MySQL using package_uuid
    $stmt = $mysqli->prepare("UPDATE packages SET status = ? WHERE package_uuid = ?");
    $stmt->bind_param("ss", $status, $packageUUID);
    if ($stmt->execute()) {
        echo " [x] Updated package UUID '{$packageUUID}' status to '{$status}'\n";
    } else {
        echo " [!] Failed to update package status in MySQL: " . $stmt->error . "\n";
    }
    $stmt->close();

    if ($status === 'passed') {
        echo " [x] Deployment passed for package UUID '{$packageUUID}'\n";

        // Retrieve the file path and version from MySQL
        $stmt = $mysqli->prepare("SELECT file_path, version, package_name FROM packages WHERE package_uuid = ?");
        $stmt->bind_param("s", $packageUUID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $packagePath = $row['file_path'] ?? null;
        $version = $row['version'] ?? null;
        $packageName = $row['package_name'] ?? 'unknown';
        $stmt->close();

        if (!$packagePath || !file_exists($packagePath)) {
            echo " [!] Package file not found at {$packagePath}\n";
            $msg->ack();
            return;
        }

        // Read the package data
        $packageData = file_get_contents($packagePath);
        if ($packageData === false) {
            echo " [!] Failed to read package data from {$packagePath}\n";
            $msg->ack();
            return;
        }

        // Define headers for the new messages
        $headers = new \PhpAmqpLib\Wire\AMQPTable([
            'package_name'  => $packageName,
            'version'       => $version,
            'content_type'  => 'application/zip'
        ]);

        // Fetch the relevant queues based on package_name
        if (!array_key_exists($packageName, $queuesMapping)) {
            echo " [!] No queue mapping found for package '{$packageName}'\n";
            $msg->ack();
            return;
        }

        $productionQueue = $queuesMapping[$packageName]['production'];
        $standbyQueue     = $queuesMapping[$packageName]['standby'];

        // Publish to production queue
        $prodMsg = new AMQPMessage($packageData, [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => $headers
        ]);
        $channel->basic_publish($prodMsg, '', $productionQueue);
        echo " [x] Sent package to {$productionQueue}\n";

        // Publish to standby queue
        $standbyMsg = new AMQPMessage($packageData, [
            'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => $headers
        ]);
        $channel->basic_publish($standbyMsg, '', $standbyQueue);
        echo " [x] Sent package to {$standbyQueue}\n";

    } elseif ($status === 'failed') {
        echo " [x] Deployment failed for package UUID '{$packageUUID}'\n";
        // Handle failure accordingly (e.g., notify teams, trigger alerts)
    } else {
        echo " [!] Unknown status '{$status}' received\n";
    }

    // Acknowledge the message
    $msg->ack();
};

// Handle package requests (e.g., rollback)
$packageRequestCallback = function ($msg) use ($channel, $mysqli) {
    echo " [x] Received package request message\n";

    $requestData = json_decode($msg->body, true);
    if (!$requestData) {
        echo " [!] Invalid package request message format\n";
        $msg->ack();
        return;
    }

    $packageName = strtolower($requestData['package_name'] ?? 'unknown');
    $action = $requestData['action'] ?? '';

    if ($action === 'get_last_successful_package') {
        // Retrieve the latest 'passed' package
        $stmt = $mysqli->prepare("SELECT file_path, version FROM packages WHERE package_name = ? AND status = 'passed' ORDER BY version DESC LIMIT 1");
        $stmt->bind_param("s", $packageName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $packagePath = $row['file_path'] ?? null;
        $version = $row['version'] ?? null;
        $stmt->close();

        if (!$packagePath || !file_exists($packagePath)) {
            echo " [!] No successful package found for '{$packageName}'\n";
            $responseData = null;
        } else {
            // Read the package data
            $packageData = file_get_contents($packagePath);
            if ($packageData === false) {
                echo " [!] Failed to read package data from {$packagePath}\n";
                $responseData = null;
            } else {
                $responseData = $packageData;
                echo " [x] Retrieved package '{$packageName}' version '{$version}' for rollback\n";
            }
        }

        // Send the response to the reply_to queue with the same correlation_id
        $replyTo = $msg->get('reply_to');
        $corrId = $msg->get('correlation_id');

        if ($replyTo) {
            $responseMsg = new AMQPMessage($responseData, [
                'correlation_id' => $corrId
            ]);
            $channel->basic_publish($responseMsg, '', $replyTo);
            echo " [x] Sent package data to '{$replyTo}'\n";
        } else {
            echo " [!] reply_to not set in the message headers\n";
        }
    } else {
        echo " [!] Unknown action '{$action}' in package request\n";
    }

    // Acknowledge the message
    $msg->ack();
};

// Consume messages from 'packages_queue'
$channel->basic_consume('packages_queue', '', false, false, false, false, $packageCallback);

// Consume messages from 'deployment_status_queue'
$channel->basic_consume('deployment_status_queue', '', false, false, false, false, $statusCallback);

// Consume messages from 'package_requests_queue'
$channel->basic_consume('package_requests_queue', '', false, false, false, false, $packageRequestCallback);

// Keep the script running
while ($channel->is_consuming()) {
    $channel->wait();
}

// Close connections (This part won't be reached in an infinite loop)
$channel->close();
$connection->close();
$mysqli->close();
?>
