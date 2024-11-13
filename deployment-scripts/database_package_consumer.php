<?php
// package_consumer.php for Database Role

require_once('/home/stanley/vendor/autoload.php');
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
$queueName = 'database_packages_queue';
$channel->queue_declare($queueName, false, true, false, false);

echo " [*] Waiting for packages on {$queueName}. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo " [x] Received package\n";
    $packagePath = '/tmp/package.zip';
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
            exec("bash $setupScript", $output, $return_var);
            if ($return_var === 0) {
                echo " [x] Setup script executed successfully\n";
            } else {
                echo " [!] Setup script execution failed\n";
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

$channel->basic_consume($queueName, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>
