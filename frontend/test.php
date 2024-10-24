<?php
// Test password hashing and verification
$plain_password = 'sy';  // This is the plain-text password to test
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

echo "Hashed password: " . $hashed_password . "\n";

// Verify the password
if (password_verify($plain_password, $hashed_password)) {
    echo "Password verification successful!\n";
} else {
    echo "Password verification failed!\n";
}
?>

