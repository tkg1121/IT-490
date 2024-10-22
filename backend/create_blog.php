<?php
require '../queue/blog_producer.php'; // Include RabbitMQ producer
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['title']) || !isset($input['content'])) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Call RabbitMQ producer to publish blog data
publish_blog($input['title'], $input['content']);

echo json_encode(['success' => 'Blog post created']);
?>