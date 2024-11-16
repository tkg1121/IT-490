<?php
// package_consumer.php for DMZ Role

require_once('/home/alisa-maloku/vendor/autoload.php');
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

// Declare the queue
<<<<<<< Updated upstream
$queueName = 'dmz_packages_queue';
=======
$queueName = 'dmz_packages_queue'; // Or use $_ENV['PACKAGE_QUEUE_NAME'] if set
>>>>>>> Stashed changes
$channel->queue_declare($queueName, false, true, false, false);

echo " [*] Waiting for packages on {$queueName}. To exit press CTRL+C\n";

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
        echo " [x] Package extracted to {$packageDir}\n";

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
            }
        } else {
            echo " [!] Setup script not found\n";
=======
                echo " [!] Setup script execution failed with exit code {$return_var}\n";
                echo " [!] Output:\n";
                echo implode("\n", $output) . "\n";
            }
        } else {
            echo " [!] Setup script not found in {$packageDir}\n";
>>>>>>> Stashed changes
        }
    } else {
        echo " [!] Failed to unzip package\n";
    }

    // Clean up
    unlink($packagePath);
    exec('rm -rf /tmp/package/');
};

$channel->basic_consume($queueName, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
