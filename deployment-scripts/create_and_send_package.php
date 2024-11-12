<?php
// create_and_send_package.php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;
use PhpAmqpLib\Wire\AMQPTable;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Check command-line arguments
if ($argc !== 3) {
    echo "Usage: php create_and_send_package.php [package_name] [path_to_files]\n";
    exit(1);
}

$packageName = $argv[1];
$pathToFiles = rtrim($argv[2], '/');

// Generate timestamp for versioning
$timestamp = date('YmdHis');
$packageDir = "/tmp/{$packageName}_v{$timestamp}";
$zipFilePath = "{$packageDir}/package.zip";

// Create package directory
if (!file_exists($packageDir)) {
    mkdir($packageDir, 0755, true);
}

// Determine if the package should include a subdirectory
$includeSubdirectory = !in_array($packageName, ['database', 'dmz']);

// Copy files to the package directory
if ($includeSubdirectory) {
    // For packages like 'frontend', include a subdirectory
    $destinationDir = "{$packageDir}/{$packageName}";
    exec("cp -r {$pathToFiles} {$destinationDir}");
} else {
    // For 'database' and 'dmz', copy files directly into the package directory
    exec("cp -r {$pathToFiles}/* {$packageDir}/");
}

// Copy setup.sh to package directory
if (file_exists("{$pathToFiles}/setup.sh")) {
    exec("cp {$pathToFiles}/setup.sh {$packageDir}/");
} else {
    echo "Error: setup.sh not found in {$pathToFiles}\n";
    exit(1);
}

// Copy Apache configuration file to package directory (optional)
if (file_exists("{$pathToFiles}/000-default.conf")) {
    exec("cp {$pathToFiles}/000-default.conf {$packageDir}/");
    echo "Included Apache configuration file '000-default.conf' in the package.\n";
} else {
    echo "Apache configuration file '000-default.conf' not found in {$pathToFiles}. Continuing without it.\n";
}

// Create zip archive
$zip = new ZipArchive;
if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($packageDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath     = $file->getRealPath();
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
    'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'application_headers' => $headers
]);

// Publish the message to the queue
$channel->basic_publish($msg, '', 'packages_queue');

echo " [x] Sent package '{$packageName}'\n";

// Close the channel and connection
$channel->close();
$connection->close();
?>
