<?php
// frontend_package_consumer.php

require_once '/home/ashleys/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;
use PhpAmqpLib\Wire\AMQPTable;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Establish connection to RabbitMQ
$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD']
);
$channel = $connection->channel();

// Declare the queue
$queueName = $_ENV['PACKAGE_QUEUE_NAME'];
$channel->queue_declare($queueName, false, true, false, false);

echo " [*] Waiting for packages on {$queueName}. To exit press CTRL+C\n";

$callback = function ($msg) use ($channel) {
    echo " [x] Received package\n";

    // Extract headers
    $headers = $msg->get('application_headers');
    $headers = $headers ? $headers->getNativeData() : [];
    $packageName = $headers['package_name'] ?? 'unknown';
    $version = $headers['version'] ?? 'unknown';
    $rollback = $headers['rollback'] ?? false;

    // Save the package
    $packageDir = "/tmp/package_{$packageName}_v{$version}";
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    $packagePath = "{$packageDir}/package.zip";
    file_put_contents($packagePath, $msg->body);

    // Unzip the package
    $zip = new ZipArchive;
    if ($zip->open($packagePath) === TRUE) {
        $zip->extractTo($packageDir);
        $zip->close();
        echo " [x] Package extracted\n";

        // Execute setup script
        $setupScript = "{$packageDir}/setup.sh";
        if (file_exists($setupScript)) {
            chmod($setupScript, 0755);
            exec("bash $setupScript", $output, $return_var);
            if ($return_var === 0) {
                echo " [x] Setup script executed successfully\n";
                $deploySuccess = true;
            } else {
                echo " [!] Setup script execution failed\n";
                $deploySuccess = false;
            }
        } else {
            echo " [!] Setup script not found\n";
            $deploySuccess = false;
        }
    } else {
        echo " [!] Failed to unzip package\n";
        $deploySuccess = false;
    }

    // Prepare feedback message
    $feedbackData = [
        'status' => $deploySuccess ? 'passed' : 'failed',
        'package_name' => $packageName,
        'version' => $version,
        'machine_tag' => $_ENV['MACHINE_TAG'],
    ];

    $feedbackMsg = new AMQPMessage(json_encode($feedbackData), [
        'correlation_id' => $msg->get('correlation_id'),
    ]);

    // Send feedback to the reply_to queue
    $replyQueue = $msg->get('reply_to');
    $channel->basic_publish($feedbackMsg, '', $replyQueue);

    echo " [x] Sent deployment status '{$feedbackData['status']}' for package '{$packageName}' version {$version}\n";

    // Clean up
    unlink($packagePath);
    exec("rm -rf {$packageDir}");
};

$channel->basic_consume($queueName, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
