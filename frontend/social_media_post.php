<?php
// social_media_post.php

require_once 'rabbitmq_send.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $response = ['status' => 'error', 'message' => 'Invalid action'];

    if ($action === 'create_comment' && isset($_POST['comment_text'], $_POST['post_id'], $_POST['session_token'])) {
        $data = [
            'action' => 'create_comment',
            'comment_text' => $_POST['comment_text'],
            'post_id' => $_POST['post_id'],
            'session_token' => $_POST['session_token']
        ];
        $response = sendToRabbitMQ('social_media_queue', json_encode($data));
        echo $response;
        exit;
    }
    // Handle like/dislike actions
    elseif (in_array($action, ['like_post', 'dislike_post']) && isset($_POST['post_id'], $_POST['session_token'])) {
        $data = [
            'action' => $action,
            'post_id' => $_POST['post_id'],
            'session_token' => $_POST['session_token']
        ];
        $response = sendToRabbitMQ('social_media_queue', json_encode($data));
        echo $response;
        exit;
    }
    elseif (in_array($action, ['like_comment', 'dislike_comment']) && isset($_POST['comment_id'], $_POST['session_token'])) {
        $data = [
            'action' => $action,
            'comment_id' => $_POST['comment_id'],
            'session_token' => $_POST['session_token']
        ];
        $response = sendToRabbitMQ('social_media_queue', json_encode($data));
        echo $response;
        exit;
    }

    echo json_encode($response);
    exit;
}

$post_id = $_GET['post_id'] ?? null;
$post_data = null;
$comments = [];

if ($post_id) {
    // Fetch post details
    $data = ['action' => 'fetch_post_details', 'post_id' => $post_id];
    $response = sendToRabbitMQ('social_media_queue', json_encode($data));
    $response_data = json_decode($response, true);

    if (isset($response_data['status']) && $response_data['status'] === 'success' && isset($response_data['post'])) {
        $post_data = $response_data['post'];
        $comments = $response_data['comments'] ?? [];
    } else {
        $error_message = $response_data['message'] ?? 'Post not found or missing.';
        echo "<p>{$error_message}</p>";
        exit;
    }
} else {
    echo "<p>Invalid post ID.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- (Same head content as before) -->
    <title>Post Details</title>
    <style>
        /* (Same styles as before) */
    </style>
</head>
<body>
    <?php if ($post_data): ?>
        <div class="post-container">
            <h1><?= htmlspecialchars($post_data['post_text'] ?? 'Untitled Post') ?></h1>
            <p>Posted by <?= htmlspecialchars($post_data['username'] ?? 'Unknown User') ?> on <?= htmlspecialchars($post_data['created_at'] ?? 'Unknown Date') ?></p>
            <div>
                <button class="like-dislike-btn like-post" data-post-id="<?= htmlspecialchars($post_id) ?>">Like</button>
                <button class="like-dislike-btn dislike-post" data-post-id="<?= htmlspecialchars($post_id) ?>">Dislike</button>
            </div>
        </div>
        <h2>Comments</h2>
        <div id="comments-container">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-container">
                    <p><strong><?= htmlspecialchars($comment['username'] ?? 'Unknown User') ?>:</strong> <?= htmlspecialchars($comment['comment_text'] ?? '') ?></p>
                    <div>
                        <button class="like-dislike-btn like-comment" data-comment-id="<?= htmlspecialchars($comment['comment_id']) ?>">Like</button>
                        <button class="like-dislike-btn dislike-comment" data-comment-id="<?= htmlspecialchars($comment['comment_id']) ?>">Dislike</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Add new comment -->
        <h3>Add a Comment</h3>
        <form id="comment-form">
            <textarea name="comment_text" placeholder="Write a comment..." required></textarea>
            <button type="submit">Post Comment</button>
        </form>
    <?php else: ?>
        <p>Post not found or missing.</p>
    <?php endif; ?>
    <script>
        // Function to get the session token from cookies
        function getCookie(name) {
            let match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            if (match) return match[2];
            return null;
        }

        // Handle post like/dislike
        document.querySelectorAll('.like-post, .dislike-post').forEach(button => {
            button.addEventListener('click', async () => {
                const post_id = button.getAttribute('data-post-id');
                const action = button.classList.contains('like-post') ? 'like_post' : 'dislike_post';
                const formData = new FormData();
                formData.append('action', action);
                formData.append('post_id', post_id);
                formData.append('session_token', getCookie('session_token'));

                const response = await fetch('social_media_post.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert('Post updated!');
                } else {
                    alert(data.message);
                }
            });
        });

        // Handle comment like/dislike
        document.querySelectorAll('.like-comment, .dislike-comment').forEach(button => {
            button.addEventListener('click', async () => {
                const comment_id = button.getAttribute('data-comment-id');
                const action = button.classList.contains('like-comment') ? 'like_comment' : 'dislike_comment';
                const formData = new FormData();
                formData.append('action', action);
                formData.append('comment_id', comment_id);
                formData.append('session_token', getCookie('session_token'));

                const response = await fetch('social_media_post.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert('Comment updated!');
                } else {
                    alert(data.message);
                }
            });
        });

        // Handle comment submission
        document.getElementById('comment-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(event.target);
            formData.append('action', 'create_comment');
            formData.append('post_id', '<?= htmlspecialchars($post_id) ?>');
            formData.append('session_token', getCookie('session_token'));

            const response = await fetch('social_media_post.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.status === 'success') {
                location.reload(); // Reload to show new comment
            } else {
                alert(data.message);
            }
        });
    </script>
</body>
</html>
