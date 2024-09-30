<?php
session_start();

if (isset($_SESSION['username'])) {
    echo "<h1>Welcome, " . htmlspecialchars($_SESSION['username']) . "!</h1>";
    echo "<p><a href='logout.php'>Log out</a></p>";
} else {
    echo "<h1>Welcome to the Authentication System</h1>";
    echo "<p><a href='signup.html'>Sign Up</a></p>";
    echo "<p><a href='login.html'>Log In</a></p>";
}
?>
