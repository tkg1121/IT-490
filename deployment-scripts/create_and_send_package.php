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
$version = $timestamp; // Use timestamp as version
$packageDir = "/tmp/{$packageName}_v{$version}";
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

// Copy firewall_setup.sh to package directory (if exists)
if (file_exists("{$pathToFiles}/firewall_setup.sh")) {
    exec("cp {$pathToFiles}/firewall_setup.sh {$packageDir}/");
    echo "Included firewall_setup.sh in the package.\n";
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
    'version'      => $version, // Include version in headers
    'content_type' => 'application/zip'
]);

// Create the AMQPMessage with the package data and headers
$msg = new AMQPMessage($packageData, [
    'delivery_mode'       => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'application_headers' => $headers
]);

// Publish the message to the queue
$channel->basic_publish($msg, '', 'packages_queue');

echo " [x] Sent package '{$packageName}' version '{$version}'\n";

// Wait for user input to confirm deployment status
echo "Enter deployment status ('passed' or 'failed'): ";
$status = trim(fgets(STDIN));

// Validate input
while (!in_array(strtolower($status), ['passed', 'failed'])) {
    echo "Invalid input. Please enter 'passed' or 'failed': ";
    $status = trim(fgets(STDIN));
}
$status = strtolower($status);

// Send the status message to the deployment server
$channel->queue_declare('deployment_status_queue', false, true, false, false);

$statusData = [
    'package_name' => $packageName,
    'version'      => $version,
    'status'       => $status,
    'timestamp'    => date('Y-m-d H:i:s')
];

$statusMsg = new AMQPMessage(json_encode($statusData), [
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
]);

$channel->basic_publish($statusMsg, '', 'deployment_status_queue');

echo " [x] Sent deployment status '{$status}' for package '{$packageName}' version '{$version}'\n";

// Check if status is 'failed' and prompt for rollback
if ($status === 'failed') {
    echo "Would you like to rollback to the last successful version? (yes/no): ";
    $rollback = trim(fgets(STDIN));
    if (strtolower($rollback) === 'yes') {
        // Fetch the last successful package from the deployment server
        echo "Fetching the last successful package...\n";

        // Send a request to the deployment server to get the last successful package
        // We'll use RabbitMQ to request the package and receive it via a temporary queue

        // Declare a temporary queue for the response
        list($callbackQueue, ,) = $channel->queue_declare("", false, false, true, false);

        // Prepare the request message
        $request = [
            'package_name' => $packageName,
            'action'       => 'get_last_successful_package'
        ];
        $corrId = uniqid();

        $requestMsg = new AMQPMessage(json_encode($request), [
            'correlation_id' => $corrId,
            'reply_to'       => $callbackQueue
        ]);

        // Publish the request to a specific queue (e.g., 'package_requests_queue')
        $channel->queue_declare('package_requests_queue', false, true, false, false);
        $channel->basic_publish($requestMsg, '', 'package_requests_queue');

        // Wait for the response
        $response = null;

        $channel->basic_consume(
            $callbackQueue,
            '',
            false,
            true,
            false,
            false,
            function ($msg) use (&$response, $corrId) {
                if ($msg->get('correlation_id') == $corrId) {
                    $response = $msg->body;
                }
            }
        );

        while (!$response) {
            $channel->wait();
        }

        // Now, handle the received package
        $packageData = $response;

        if (!$packageData) {
            echo "Failed to receive the package from the deployment server.\n";
            exit(1);
        }

        // Save the package to a temporary location
        $rollbackPackageDir = "/tmp/rollback_{$packageName}";
        if (!file_exists($rollbackPackageDir)) {
            mkdir($rollbackPackageDir, 0755, true);
        }
        $rollbackZipFilePath = "{$rollbackPackageDir}/package.zip";
        file_put_contents($rollbackZipFilePath, $packageData);

        // Unzip the package
        $zip = new ZipArchive;
        if ($zip->open($rollbackZipFilePath) === TRUE) {
            $zip->extractTo($rollbackPackageDir);
            $zip->close();
            echo " [x] Package extracted to {$rollbackPackageDir}\n";

            // Replace local files with the ones from the package
            // WARNING: This will overwrite local changes
            if ($includeSubdirectory) {
                // For packages like 'frontend', replace the entire directory
                $sourceDir = "{$rollbackPackageDir}/{$packageName}";
                $destDir = $pathToFiles;
                exec("rm -rf {$destDir}");
                exec("cp -r {$sourceDir} {$destDir}");
            } else {
                // For 'database' and 'dmz', copy files directly
                exec("cp -r {$rollbackPackageDir}/* {$pathToFiles}/");
            }

            echo " [x] Rolled back to the last successful version.\n";

            // Clean up
            exec("rm -rf {$rollbackPackageDir}");
        } else {
            echo " [!] Failed to unzip the package.\n";
            exit(1);
        }
    } else {
        echo "Rollback declined. Exiting.\n";
    }
}

// Close the channel and connection
$channel->close();
$connection->close();
?>