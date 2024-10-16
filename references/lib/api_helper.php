<?php
require_once(__DIR__ . "/load_api_keys.php");
require_once('/home/dev/php-amqplib/vendor/autoload.php'); // Adjust the path as necessary

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Send a request to the specified URL with the given method.
 * 
 * @param string $url The URL to send the request to.
 * @param string $key The API key to use for the request.
 * @param array $data The data to send with the request.
 * @param string $method The HTTP method to use for the request.
 * @param bool $isRapidAPI Whether the request is for RapidAPI.
 * @param string $rapidAPIHost The host value for the RapidAPI Header
 * 
 * @throws Exception If the API key is missing or empty.
 * 
 * @return array The response status and body.
 */
function _sendRequest($url, $key, $data = [], $method = 'GET', $isRapidAPI = true, $rapidAPIHost = "")
{
    global $API_KEYS;
    // Check if the API key is set and not empty
    if (!isset($API_KEYS) || !isset($API_KEYS[$key]) || empty($API_KEYS[$key])) {
        throw new Exception("Missing or empty API KEY");
    }
    $headers = [];
    if ($isRapidAPI) {
        $headers = [
            "X-RapidAPI-Host" => $rapidAPIHost,
            "X-RapidAPI-Key" => $API_KEYS[$key],
        ];
    } else {
        $headers = [
            "x-api-key" => $API_KEYS[$key]
        ];
    }
    $callback = fn(string $k, string $v): string => "$k: $v";
    $headers = array_map($callback, array_keys($headers), array_values($headers));
    $curl = curl_init();

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "", // Specify encoding if known
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($method == 'GET') {
        $options[CURLOPT_URL] = "$url?" . http_build_query($data);
    } else {
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
    }
    error_log("curl options: " . var_export($options, true));
    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        throw new Exception($err);
    } else {
        return ["status"=>200, "response"=>$response];
    }
}

/**
 * Send a GET request to the specified URL.
 * 
 * @param string $url The URL to send the request to.
 * @param string $key The API key to use for the request.
 * @param array $data The data to send with the request.
 * @param bool $isRapidAPI Whether the request is for RapidAPI.
 * @param string $rapidAPIHost The host value for the RapidAPI Header
 * 
 * @return array The response status and body.
 */
function get($url, $key, $data = [], $isRapidAPI = true, $rapidAPIHost = "")
{
    return _sendRequest($url, $key, $data, 'GET', $isRapidAPI, $rapidAPIHost);
}

/**
 * Send a POST request to the specified URL.
 * 
 * @param string $url The URL to send the request to.
 * @param string $key The API key to use for the request.
 * @param array $data The data to send with the request.
 * @param bool $isRapidAPI Whether the request is for RapidAPI.
 * @param string $rapidAPIHost The host value for the RapidAPI Header
 * 
 * @return array The response status and body.
 */
function post($url, $key, $data = [], $isRapidAPI = true, $rapidAPIHost = "")
{
    return _sendRequest($url, $key, $data, 'POST', $isRapidAPI, $rapidAPIHost);
}

/**
 * Send a message to the specified RabbitMQ queue.
 * 
 * @param string $queue The name of the RabbitMQ queue.
 * @param string $message The message to send.
 * 
 * @throws Exception If there is an error in the RabbitMQ connection.
 * 
 * @return string The response from the RabbitMQ consumer.
 */
function sendToRabbitMQ($queue, $message) {
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            '192.168.193.197',  // RabbitMQ server IP
            5672,               // RabbitMQ port
            'Alisa',            // RabbitMQ username
            'password',            // RabbitMQ password
            '/'                 // Virtual host
        );

        // Create a channel
        $channel = $connection->channel();

        // Declare the queue where the message will be sent
        $channel->queue_declare($queue, false, true, false, false);

        // Declare a callback queue for the RPC response
        list($callback_queue, ,) = $channel->queue_declare("", false, false, true, false);

        // Generate a unique correlation ID for the RPC request
        $correlation_id = uniqid();

        // Create the message, include the reply_to and correlation_id fields
        $msg = new AMQPMessage(
            $message,
            ['correlation_id' => $correlation_id, 'reply_to' => $callback_queue]
        );

        // Publish the message to the queue
        $channel->basic_publish($msg, '', $queue);

        // Set up to wait for the response
        $response = null;

        // Define the callback to handle the response
        $channel->basic_consume($callback_queue, '', false, true, false, false, function ($msg) use ($correlation_id, &$response) {
            if ($msg->get('correlation_id') == $correlation_id) {
                $response = $msg->body;  // Capture the response body
                error_log("Received response: " . $response);  // Log the response for debugging
            }
        });

        // Wait for the response from RabbitMQ
        while (!$response) {
            error_log("Waiting for RabbitMQ response...");  // Log waiting status
            $channel->wait();
        }

        // Log success and close connections
        error_log("Final response received: " . $response);  // Debugging log
        $channel->close();
        $connection->close();

        return $response;

    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("RabbitMQ Error: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
}
?>
