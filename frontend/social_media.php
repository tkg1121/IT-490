<?php
// social_media.php
include 'header.php';
require_once 'rabbitmq_send.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $response = ['status' => 'error', 'message' => 'Invalid action'];

    if ($action === 'create_post' && isset($_POST['post_text'], $_POST['session_token'])) {
        $data = [
            'action' => 'create_post',
            'post_text' => $_POST['post_text'],
            'session_token' => $_POST['session_token']
        ];
        $response = sendToRabbitMQ('social_media_queue', json_encode($data));
        echo $response;
        exit;
    }
    echo json_encode($response);
    exit;
}

// Fetch all posts on page load
$data = ['action' => 'fetch_posts'];
$response = sendToRabbitMQ('social_media_queue', json_encode($data));
$response_data = json_decode($response, true);

if (isset($response_data['status']) && $response_data['status'] === 'success') {
    $posts = $response_data['posts'] ?? [];
} else {
    $posts = [];
    error_log("Failed to fetch posts: " . $response_data['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Social Media Feed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .post-container {
            margin-bottom: 20px;
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .post-container h2 {
            margin: 0;
        }
        .post-container p {
            color: #555;
        }
        form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
        }
        button {
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Social Media Feed</h1>
    <!-- Post creation form -->
    <form id="post-form" method="POST">
        <textarea name="post_text" placeholder="What's on your mind?" required></textarea>
        <button type="submit">Create Post</button>
    </form>
    <h2>Posts</h2>
    <div id="posts-container">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-container">
                    <h2><a href="social_media_post.php?post_id=<?= urlencode($post['post']['post_id']) ?>"><?= htmlspecialchars($post['post']['post_text']) ?></a></h2>
                    <p>Posted by <?= htmlspecialchars($post['post']['username']) ?> on <?= htmlspecialchars($post['post']['created_at']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
    </div>
    <script>
        // Function to get the session token from cookies
        function getCookie(name) {
            let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            if (match) return match[2];
            return null;
        }

        // Handle post creation
        document.getElementById('post-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'create_post');
            formData.append('session_token', getCookie('session_token')); // Include session_token

            const response = await fetch('social_media.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') {
                location.reload(); // Reload the page to show the new post
            } else {
                alert('Failed to create post: ' + data.message);
            }
        });
    </script>
</body>
</html>
