<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
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

echo " [*] Waiting for packages. To exit press CTRL+C\n";

$callback = function ($msg) use ($pdo, $channel) {
    // ... Existing code ...

$headers = $msg->get('application_headers');
if ($headers) {
    $headers = $headers->getNativeData();
    $packageName = $headers['package_name'];
} else {
    echo " [!] No headers found in the message.\n";
    return;
}

// ... Rest of the code ...

    $stmt = $pdo->prepare("SELECT MAX(version) as max_version FROM packages WHERE package_name = ?");
    $stmt->execute([$packageName]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $newVersion = $result['max_version'] ? $result['max_version'] + 1 : 1;

    // Store package info
    $stmt = $pdo->prepare("INSERT INTO packages (package_name, version) VALUES (?, ?)");
    $stmt->execute([$packageName, $newVersion]);

    // Save package data to file system
    $packageDir = "/home/dev/Documents/GitHub/IT-490/deployment-server/packages/{$packageName}/v{$newVersion}";
    if (!file_exists($packageDir)) {
        mkdir($packageDir, 0755, true);
    }
    file_put_contents("{$packageDir}/package.zip", $msg->body);

    echo " [x] Received and stored package '{$packageName}' version {$newVersion}\n";

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

$channel->basic_consume('packages_queue', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
?>

