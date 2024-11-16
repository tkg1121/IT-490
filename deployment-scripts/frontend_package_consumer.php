<?php
<<<<<<< Updated upstream
// package_consumer.php

require_once('/home/ashleys/vendor/autoload.php');
=======
// frontend_package_consumer.php

require_once '/home/ashleys/vendor/autoload.php';
>>>>>>> Stashed changes
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$connection = new AMQPStreamConnection(
    $_ENV['RABBITMQ_HOST'],
    $_ENV['RABBITMQ_PORT'],
    $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD']
);
$channel = $connection->channel();

<<<<<<< Updated upstream
$channel->queue_declare('frontend_packages_queue', false, true, false, false);
=======
// Declare the queue
$queueName = 'frontend_packages_queue';
$channel->queue_declare($queueName, false, true, false, false);
>>>>>>> Stashed changes

echo " [*] Waiting for packages. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo " [x] Received package\n";
<<<<<<< Updated upstream
    $packagePath = '/tmp/package.zip';
=======

    // Extract headers
    $headers = $msg->get('application_headers');
    $headers = $headers ? $headers->getNativeData() : [];
    $packageName = $headers['package_name'] ?? 'unknown';

    // Save the package
    $timestamp = date('YmdHis');
    $packageDir = "/tmp/{$packageName}_v{$timestamp}";
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    $packagePath = "{$packageDir}/package.zip";
>>>>>>> Stashed changes
    file_put_contents($packagePath, $msg->body);

    // Unzip the package
    $zip = new ZipArchive;
    if ($zip->open($packagePath) === TRUE) {
        $zip->extractTo('/tmp/package/');
        $zip->close();
        echo " [x] Package extracted\n";

        // Execute setup script
        $setupScript = '/tmp/package/setup.sh';
        if (file_exists($setupScript)) {
            chmod($setupScript, 0755);

            // Capture output and error messages
            $output = [];
            $return_var = 0;
            exec("bash $setupScript 2>&1", $output, $return_var);

            if ($return_var === 0) {
                echo " [x] Setup script executed successfully\n";
            } else {
<<<<<<< Updated upstream
                echo " [!] Setup script execution failed\n";
=======
                echo " [!] Setup script execution failed with exit code {$return_var}\n";
                echo " [!] Output:\n";
                echo implode("\n", $output) . "\n";
>>>>>>> Stashed changes
            }
        } else {
            echo " [!] Setup script not found\n";
        }
    } else {
        echo " [!] Failed to unzip package\n";
    }

    // Clean up
    unlink($packagePath);
    exec('rm -rf /tmp/package/');
};

$channel->basic_consume('frontend_packages_queue', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>