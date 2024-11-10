<?php
// create_and_send_package.php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Check command-line arguments
if ($argc !== 3) {
    echo "Usage: php create_and_send_package.php [package_name] [path_to_files]\n";
    exit(1);
}

$packageName = $argv[1];
$pathToFiles = $argv[2];

// Generate timestamp for versioning
$timestamp = date('YmdHis');
$packageDir = "/home/ashleys/IT-490/deployment-server/packages/{$packageName}_v{$timestamp}";
$zipFilePath = "{$packageDir}/package.zip";

// Create package directory
if (!file_exists($packageDir)) {
    mkdir($packageDir, 0755, true);
}

// Copy files to package directory
$packageContentDir = "{$packageDir}/files";
mkdir($packageContentDir, 0755, true);

// Copy all files from the source directory to the package's files directory
exec("cp -r {$pathToFiles}/* {$packageContentDir}/");

// Copy setup.sh to package directory
if (file_exists("{$pathToFiles}/setup.sh")) {
    exec("cp {$pathToFiles}/setup.sh {$packageDir}/");
} else {
    echo "Error: setup.sh not found in {$pathToFiles}\n";
    exit(1);
}

// Create zip archive
$zip = new ZipArchive;
if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageDir));
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($packageDir) + 1);
            if ($relativePath !== 'package.zip') {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    $zip->close();
    echo "Package created at {$zipFilePath}\n";
} else {
    echo "Failed to create zip archive\n";
    exit(1);
}

// Read the package data
$packageData = file_get_contents($zipFilePath);
if ($packageData === false) {
    echo "Failed to read package data from {$zipFilePath}\n";
    exit(1);
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

// Declare the packages queue
$channel->queue_declare('packages_queue', false, true, false, false);

// Create AMQPTable for headers
$headers = new AMQPTable([
    'package_name' => $packageName,
    'content_type' => 'application/zip'
]);

// Create the AMQPMessage with the package data and headers
$msg = new AMQPMessage($packageData, [
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'application_headers' => $headers
]);

// Publish the message to the queue
$channel->basic_publish($msg, '', 'packages_queue');

echo " [x] Sent package '{$packageName}'\n";

// Close the channel and connection
$channel->close();
$connection->close();
?>

