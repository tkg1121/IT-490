<?php
require_once(__DIR__ . "/../lib/functions.php");
require_once('/home/dev/php-amqplib/vendor/autoload.php'); // Adjust the path as necessary

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// Note: this is to resolve cookie issues with port numbers
$domain = $_SERVER["HTTP_HOST"];
if (strpos($domain, ":")) {
    $domain = explode(":", $domain)[0];
}
$localWorks = true; // some people have issues with localhost for the cookie params

// this is an extra condition added to "resolve" the localhost issue for the session cookie
if (($localWorks && $domain == "localhost") || $domain != "localhost") {
    session_set_cookie_params([
        "lifetime" => 60 * 60,
        "path" => "$BASE_PATH",
        "domain" => $domain,
        "secure" => true,
        "httponly" => true,
        "samesite" => "lax"
    ]);
}
session_start();

// Function to send a message to RabbitMQ
function sendToRabbitMQ($queue, $message) {
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            '192.168.193.137',  // RabbitMQ server IP
            5672,               // RabbitMQ port
            'guest',            // RabbitMQ username
            'guest',            // RabbitMQ password
            '/'                 // Virtual host
        );

        // Create a channel
        $channel = $connection->channel();

        // Declare the queue where the message will be sent
        $channel->queue_declare($queue, false, true, false, false);

        // Create the message
        $msg = new AMQPMessage($message);

        // Publish the message to the queue
        $channel->basic_publish($msg, '', $queue);

        // Close the channel and connection
        $channel->close();
        $connection->close();

    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("RabbitMQ Error: " . $e->getMessage());
    }
}
?>
<!-- include css and js files -->
<link rel="stylesheet" href="<?php echo get_url('styles.css'); ?>">
<script src="<?php echo get_url('helpers.js'); ?>"></script>

<nav>
    <ul>
        <?php if (is_logged_in()) : ?>
            <li><a href="<?php echo get_url('home.php'); ?>">Home</a></li>
            <li><a href="<?php echo get_url('profile.php'); ?>">Profile</a></li>
        <?php endif; ?>
        <?php if (!is_logged_in()) : ?>
            <li><a href="<?php echo get_url('login.php'); ?>" onclick="sendToRabbitMQ('user_actions_queue', 'User attempted to login')">Login</a></li>
            <li><a href="<?php echo get_url('register.php'); ?>" onclick="sendToRabbitMQ('user_actions_queue', 'User attempted to register')">Register</a></li>
        <?php endif; ?>
        <?php if (has_role("Admin")) : ?>
            <li><a href="<?php echo get_url('admin/create_role.php'); ?>">Create Role</a></li>
            <li><a href="<?php echo get_url('admin/list_roles.php'); ?>">List Roles</a></li>
            <li><a href="<?php echo get_url('admin/assign_roles.php'); ?>">Assign Roles</a></li>
            <li><a href="<?php echo get_url('admin/list_pokemons.php'); ?>">List Pokémon</a></li>
            <li><a href="<?php echo get_url('admin/pokemon_profile.php'); ?>">Create Pokémon</a></li>
        <?php endif; ?>
        <?php if (is_logged_in()) : ?>
            <li><a href="<?php echo get_url('logout.php'); ?>" onclick="sendToRabbitMQ('user_actions_queue', 'User logged out')">Logout</a></li>
        <?php endif; ?>
    </ul>
</nav>

<script>
    function sendToRabbitMQ(queue, message) {
        fetch('<?php echo get_url('rabbitmq_sender.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ queue: queue, message: message })
        }).catch(error => console.error('Error sending to RabbitMQ:', error));
    }
</script>
