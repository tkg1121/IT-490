<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$pdo = new PDO('mysql:host=localhost;dbname=deployment_db', 'dev', 'IT490tempPass1121!!@@');

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD']
);
$channel = $connection->channel();

$channel->queue_declare('packages_queue', false, true, false, false);
$channel->queue_declare('deployment_status_queue', false, true, false, false);

<<<<<<< Updated upstream
echo " [*] Waiting for packages. To exit press CTRL+C\n";

$callback = function ($msg) use ($pdo, $channel) {
=======
echo " [*] Waiting for packages and deployment statuses. To exit press CTRL+C\n";

// Callback for receiving packages
$packageCallback = function ($msg) use ($pdo, $channel) {
>>>>>>> Stashed changes
    $headers = $msg->get('application_headers');
    if ($headers) {
        $headers = $headers->getNativeData();
        $packageName = $headers['package_name'];
        $version = $headers['version'];
    } else {
        echo " [!] No headers found in the message.\n";
        return;
    }

<<<<<<< Updated upstream
    // Get the latest version
    $stmt = $pdo->prepare("SELECT MAX(version) as max_version FROM packages WHERE package_name = ?");
    $stmt->execute([$packageName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newVersion = $result['max_version'] ? $result['max_version'] + 1 : 1;

    // Store package info
    $stmt = $pdo->prepare("INSERT INTO packages (package_name, version) VALUES (?, ?)");
    $stmt->execute([$packageName, $newVersion]);

    // Save package data to file system
    $packageDir = "/home/dev/Documents/GitHub/IT-490/deployment-server/packages/{$packageName}_v{$newVersion}";
=======
    // Store package info with 'pending' status
    $stmt = $pdo->prepare("INSERT INTO packages (package_name, version, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$packageName, $version]);

    // Save package data to file system
    $packageDir = "/home/dev/Documents/GitHub/IT-490/deployment-server/packages/{$packageName}_v{$version}";
>>>>>>> Stashed changes
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    file_put_contents("{$packageDir}/package.zip", $msg->body);

<<<<<<< Updated upstream
    echo " [x] Received and stored package '{$packageName}' version {$newVersion}\n";
=======
    echo " [x] Received and stored package '{$packageName}' version {$version}\n";
>>>>>>> Stashed changes

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

<<<<<<< Updated upstream
$channel->basic_consume('packages_queue', '', false, true, false, false, $callback);
=======
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

$channel->basic_consume('packages_queue', '', false, true, false, false, $packageCallback);
$channel->basic_consume('deployment_status_queue', '', false, false, false, false, $statusCallback);
>>>>>>> Stashed changes

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>