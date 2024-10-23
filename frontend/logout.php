<?php
session_start();
include 'db_connection.php'; // assuming this is your DB file

if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];

    // Clear session token in the database
    $stmt = $mysqli->prepare("UPDATE users SET session_token=NULL WHERE session_token=?");
    $stmt->bind_param('s', $session_token);
    $stmt->execute();
    $stmt->close();

    // Remove session cookie
    setcookie('session_token', '', time() - 3600, "/");  // expire the cookie

    // Redirect to login page
    header('Location: login.php');
}
?>
