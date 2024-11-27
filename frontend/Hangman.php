<?php

require_once 'hangedman.php';

$words = array();
$numwords = 0;

function printPage($image, $guesstemplate, $which, $guessed, $wrong) {
  echo <<<ENDPAGE
<!DOCTYPE html>
<html>
  <head>
	<title>Hangman</title>
  </head>
</html>
<body>
  <h1>Hangman Game</h1>
  <br />
  <pre>$image</pre>
  <br />
  <p><strong>Word to guess: $guesstemplate</strong></p>
  <p>Letters used in guesses so far: $guessed</p>
  <form method="post" action="$script">
	<input type="hidden" name="wrong" value="$wrong" />
	<input type="hidden" name="lettersguessed" value="$guessed" />
	<input type="hidden" name="word" value="$which" />
	<fieldset>
	  <legend>Your next guess</legend>
	  <input type="text" name="letter" autofocus />
	  <input type="submit" value="Guess" />
	</fieldset>
  </form>
</body>
ENDPAGE;
}

function loadWords() {
  global $words;
  global $numwords;
  $input = fopen("./words.txt", "r");

  while (true) {
	  $str = fgets($input);
	  if (!$str) break;
	  $words[] = rtrim($str);
	  $numwords++;
  }

  fclose($input);
}

function startGame() {
  global $words;
  global $numwords;
  global $hang;

  $which = rand(0, $numwords - 1);
  $word =  $words[$which];
  $len = strlen($word);
  $guesstemplate = str_repeat('_ ', $len);
  $script = $_SERVER["PHP_SELF"];

  printPage($hang[0], $guesstemplate, $which, "", 0);
}

function killPlayer($word) {
  echo <<<ENDPAGE
<!DOCTYPE html>
<html>
 <head>
	<title>Hangman</title>
  </head>
  <body>
	<h1>You lost!</h1>
	<p>The word you were trying to guess was <em>$word</em>.</p>
  </body>
</html>
ENDPAGE;
}

function congratulateWinner($word) {
  echo <<<ENDPAGE
<!DOCTYPE html>
<html>
  <head>
	<title>Hangman</title>
  </head>
  <body>
	<h1>You win!</h1>
	<p>Congratulations! You guessed that the word was <em>$word</em>.</p>
  </body>
</html>
ENDPAGE;
}

function matchLetters($word, $guessedLetters) {
  $len = strlen($word);
  $guesstemplate = str_repeat("_ ", $len);

  for ($i = 0; $i < $len; $i++) {
	$ch = $word[$i];
	if (strstr($guessedLetters, $ch)) {
	  $pos = 2 * $i;
	  $guesstemplate[$pos] = $ch;
	}
  }

  return $guesstemplate;
}

function handleGuess() {
  global $words;
  global $hang;

  $which = $_POST["word"];
  $word  = $words[$which];
  $wrong = $_POST["wrong"];
  $lettersguessed = $_POST["lettersguessed"];
  $guess = $_POST["letter"];
  $letter = strtoupper($guess[0]);

  if(!strstr($word, $letter)) {
	$wrong++;
  }

  $lettersguessed = $lettersguessed . $letter;
  $guesstemplate = matchLetters($word, $lettersguessed);

  if (!strstr($guesstemplate, "_")) {
   	congratulateWinner($word);
  } else if ($wrong >= 6) {
	killPlayer($word);
  } else {
	printPage($hang[$wrong], $guesstemplate, $which, $lettersguessed, $wrong);
  }
}

//header("Content-type: text/plain");
loadWords();

$method = $_SERVER["REQUEST_METHOD"];

if ($method == "POST") {
  handleGuess();
} else {
  startGame();
}

?>
