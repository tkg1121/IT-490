<?php
include 'rabbitmq_send.php'; // Include RabbitMQ send function

// Function to send trivia request via RabbitMQ and get the response
function getTriviaQuestion() {
    $request_data = json_encode(['request_type' => 'trivia_question']);
    $response = sendToRabbitMQ('trivia_request_queue', $request_data);
    return json_decode($response, true);
}

$trivia = getTriviaQuestion();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movie Trivia Game</title>
    <link rel="stylesheet" href="trivia.css">
</head>

<body>
    <!-- Top Navigation -->
    <div class="topnav">
        <a href="index.html">Home</a>
        <a href="create_blog.html">Blog</a>
        <a href="index.php">Sign in/log in</a>
        <a href="movieLists.html">Add to Your List</a>
        <a href="trivia.php" class="active">Trivia</a>
    </div>

    <div class="content-wrapper">
        <div class="container">
            <h1>Movie Trivia</h1>
            <div id="trivia-question"><?= htmlspecialchars($trivia['question'] ?? 'Loading...') ?></div>
            <div id="trivia-answers">
                <?php if (!empty($trivia['answers'])): ?>
                    <?php foreach ($trivia['answers'] as $answer): ?>
                        <button onclick="checkAnswer('<?= htmlspecialchars($answer) ?>')"><?= htmlspecialchars($answer) ?></button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button id="next-question" onclick="nextQuestion()">Next Question</button>
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="footer">
        <p>&copy; 2024 MovieMania. All rights reserved.</p>
        <p>
            <a href="">Terms and Conditions</a> |
            <a href="">Privacy Policy</a> |
            <a href="">Contact Us</a>
        </p>
    </footer>

    <script>
        async function nextQuestion() {
            location.reload();
        }

        function checkAnswer(selectedAnswer) {
            // Replace this with better alerting or styling for correct/incorrect feedback
            alert(selectedAnswer === '<?= addslashes($trivia['correct_answer'] ?? '') ?>' ? 'Correct!' : `Wrong! The correct answer was <?= addslashes($trivia['correct_answer'] ?? '') ?>.`);
        }
    </script>
</body>
</html>
