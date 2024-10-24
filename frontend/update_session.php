<?php
// update_session.php
session_start();
include 'db_connection.php'; // assuming this is your DB connection file

if (isset($_COOKIE['session_token'])) {
    $session_token = $_COOKIE['session_token'];
    $current_time = date('Y-m-d H:i:s');  // Get current time

    // Update last activity timestamp in the database
    $stmt = $mysqli->prepare("UPDATE users SET last_activity=? WHERE session_token=?");
    $stmt->bind_param('ss', $current_time, $session_token);
    $stmt->execute();
    $stmt->close();

    echo "Session updated";  // Debugging
}
?>
