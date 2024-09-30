<?php
// Get the username from the URL (no validation here)
$username = isset($_GET['username']) ? $_GET['username'] : 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>This is your profile page.</p>

    <!-- Add a logout button if needed -->
    <form action="logout.php" method="POST">
        <button type="submit">Logout</button>
    </form>
</body>
</html>
