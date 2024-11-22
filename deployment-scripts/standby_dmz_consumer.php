<?php
// standby_dmz_consumer.php

require_once '/home/alisa-maloku/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

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

// Declare the standby DMZ queue
$queueName = 'standby_dmz_queue';
$channel->queue_declare($queueName, false, true, false, false);

echo " [*] Waiting for packages on {$queueName}. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo " [x] Received package for Standby DMZ\n";

    // Extract headers
    $headers = $msg->get('application_headers');
    $headers = $headers ? $headers->getNativeData() : [];
    $packageName = $headers['package_name'] ?? 'unknown';
    $version = $headers['version'] ?? 'unknown';

    // Save the package
    $timestamp = date('YmdHis');
    $packageDir = "/tmp/standby_dmz_{$packageName}_v{$version}";
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
        echo " [x] Package extracted to {$packageDir}\n";

        // Execute setup script
        $setupScript = "{$packageDir}/setup.sh";
        if (file_exists($setupScript)) {
            chmod($setupScript, 0755);

            // Capture output and error messages
            $output = [];
            $return_var = 0;
            exec("bash $setupScript 2>&1", $output, $return_var);

            if ($return_var === 0) {
                echo " [x] Setup script executed successfully for Standby DMZ\n";
            } else {
                echo " [!] Setup script execution failed for Standby DMZ with exit code {$return_var}\n";
                echo " [!] Output:\n";
                echo implode("\n", $output) . "\n";
            }
        } else {
            echo " [!] Setup script not found in {$packageDir}\n";
        }
    } else {
        echo " [!] Failed to unzip package\n";
    }

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
