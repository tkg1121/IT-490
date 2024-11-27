<?php
// hangman.php

session_start();

// Include necessary files
include 'header.php';
require_once('rabbitmq_send.php');  // Use your existing RabbitMQ send function

// Define a list of movies or actors for the game
$gameItems = [
    ['type' => 'movie', 'name' => 'Inception'],
    ['type' => 'movie', 'name' => 'The Matrix'],
    ['type' => 'actor', 'name' => 'Leonardo DiCaprio'],
    ['type' => 'actor', 'name' => 'Scarlett Johansson'],
    // Add more items as needed
];

// Function to initialize a new game
function initializeGame($gameItems) {
    // Select a random item
    $selectedItem = $gameItems[array_rand($gameItems)];
    
    // Fetch hint using RabbitMQ and OMDb
    if ($selectedItem['type'] === 'movie') {
        $requestData = json_encode(['name' => $selectedItem['name']]);
        $response = sendToRabbitMQ('omdb_request_queue', $requestData);
        $data = json_decode($response, true);
        $hint = isset($data['Plot']) ? $data['Plot'] : 'No description available.';
        $word = strtoupper($selectedItem['name']);
    } elseif ($selectedItem['type'] === 'actor') {
        // For actors, you might need a different approach or a predefined hint
        // Since OMDb primarily deals with movies, we'll set a generic hint
        $hint = "Name of a famous actor.";
        $word = strtoupper($selectedItem['name']);
    }

    // Initialize game state
    $_SESSION['word'] = $word;
    $_SESSION['hint'] = $hint;
    $_SESSION['guessed_letters'] = [];
    $_SESSION['remaining_attempts'] = 6;  // You can adjust the number of attempts
    $_SESSION['game_over'] = false;
    $_SESSION['win'] = false;
}

// Handle game reset
if (isset($_GET['reset'])) {
    initializeGame($gameItems);
}

// Initialize game if not already started
if (!isset($_SESSION['word'])) {
    initializeGame($gameItems);
}

// Handle letter guess
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['letter']) && !$_SESSION['game_over']) {
    $letter = strtoupper(trim($_POST['letter']));
    
    // Validate input
    if (preg_match('/^[A-Z]$/', $letter)) {
        if (!in_array($letter, $_SESSION['guessed_letters'])) {
            $_SESSION['guessed_letters'][] = $letter;
            
            if (strpos($_SESSION['word'], $letter) === false) {
                $_SESSION['remaining_attempts']--;
            }
        }
    }
    
    // Check win condition
    $allLettersGuessed = true;
    for ($i = 0; $i < strlen($_SESSION['word']); $i++) {
        if (ctype_alpha($_SESSION['word'][$i]) && !in_array($_SESSION['word'][$i], $_SESSION['guessed_letters'])) {
            $allLettersGuessed = false;
            break;
        }
    }
    
    if ($allLettersGuessed) {
        $_SESSION['game_over'] = true;
        $_SESSION['win'] = true;
    } elseif ($_SESSION['remaining_attempts'] <= 0) {
        $_SESSION['game_over'] = true;
        $_SESSION['win'] = false;
    }
}

// Function to display the word with guessed letters
function displayWord($word, $guessed_letters) {
    $display = '';
    for ($i = 0; $i < strlen($word); $i++) {
        if (ctype_alpha($word[$i])) {
            if (in_array($word[$i], $guessed_letters)) {
                $display .= $word[$i] . ' ';
            } else {
                $display .= '_ ';
            }
        } else {
            $display .= $word[$i] . ' ';
        }
    }
    return $display;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hangman Game</title>
    <style>
        /* Basic styling for the game */
        body {
            font-family: Arial, sans-serif;
            background-color: #f0dfc8;
            color: #333;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #795833;
        }
        .hint {
            margin: 20px 0;
            font-style: italic;
        }
        .word {
            font-size: 2em;
            letter-spacing: 10px;
            margin: 20px 0;
        }
        .letters-guessed {
            margin: 20px 0;
        }
        .attempts {
            margin: 20px 0;
            font-weight: bold;
        }
        input[type="text"] {
            padding: 10px;
            font-size: 1em;
            width: 50px;
            text-align: center;
            margin-right: 10px;
        }
        button {
            padding: 10px 20px;
            background-color: #795833;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #333;
        }
        .message {
            margin: 20px 0;
            font-size: 1.2em;
            color: #795833;
        }
        .reset-button {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hangman Game</h1>
        
        <div class="hint">
            <strong>Hint:</strong> <?php echo htmlspecialchars($_SESSION['hint']); ?>
        </div>
        
        <div class="word">
            <?php echo displayWord($_SESSION['word'], $_SESSION['guessed_letters']); ?>
        </div>
        
        <div class="letters-guessed">
            <strong>Guessed Letters:</strong> <?php echo implode(', ', $_SESSION['guessed_letters']); ?>
        </div>
        
        <div class="attempts">
            Remaining Attempts: <?php echo $_SESSION['remaining_attempts']; ?>
        </div>
        
        <?php if (!$_SESSION['game_over']): ?>
            <form method="POST" action="">
                <label for="letter">Guess a letter:</label>
                <input type="text" id="letter" name="letter" maxlength="1" required pattern="[A-Za-z]">
                <button type="submit">Guess</button>
            </form>
        <?php endif; ?>
        
        <?php if ($_SESSION['game_over']): ?>
            <?php if ($_SESSION['win']): ?>
                <div class="message">Congratulations! You guessed the word correctly.</div>
            <?php else: ?>
                <div class="message">Game Over! The word was: <?php echo htmlspecialchars($_SESSION['word']); ?></div>
            <?php endif; ?>
            <a href="hangman.php?reset=true"><button class="reset-button">Start New Game</button></a>
        <?php endif; ?>
    </div>
</body>
</html>

