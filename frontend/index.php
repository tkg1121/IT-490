<?php
session_start();
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f3e4d0;
        }

        .login-container {
            width: 100%;
            height: 100vh;
            display: flex;
        }

        /* Left section for MovieMania */
        .left-half {
            background-color: #795833; /* Background color for the left side */
            width: 50%; /* Takes up half the screen */
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            color: white;
        }

        /* Right section for Login box */
        .right-half {
            width: 50%; /* Takes up half the screen */
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            background-color: #fff;
            padding: 90px;
            border-radius: 30px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-box h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #4d3b26;
        }

        .login-box .input-box {
            margin-bottom: 20px;
        }

        .login-box .input-box input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        .login-box button {
            background-color: #80592d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .login-box button:hover {
            background-color: #69471d;
        }

        .login-box a {
            color: #80592d;
            text-decoration: none;
        }

        .login-box .sign-up {
            margin-top: 10px;
        }

        .movie-mania h1 {
            font-size: 36px;
            color: white;
            margin-bottom: 20px;
            font-family: 'Lobster', cursive;
        }

        .movie-mania img {
            width: 650px;
            height: auto;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Left half for MovieMania -->
        <div class="left-half">
            <div class="movie-mania">
                <h1>"MovieMania: Where Every Flick Fuels the Frenzy!"</h1>
                <img src="images/LogIn.png" alt="MovieMania Logo">
            </div>
        </div>

        <!-- Right half for Login Box -->
        <div class="right-half">
            <div class="login-box">
                <?php
                if (isset($_SESSION['username'])) {
                    echo "<h2>Welcome, " . htmlspecialchars($_SESSION['username']) . "!</h2>";
                    echo "<p><a href='logout.php'>Log out</a></p>";
                } else {
                    echo '<h2>Log in page!</h2>';
                    echo '<form action="login.php" method="POST">
                            <div class="input-box">
                                <input type="text" name="username" placeholder="Username" required>
                            </div>
                            <div class="input-box">
                                <input type="password" name="password" placeholder="Password" required>
                            </div>
                            <button type="submit">Login</button>
                          </form>';
                    echo '
                    <div class="sign-up">
                        <p>Don\'t have an account with us?</p>
                        <a href="signup.html"><button>Sign Up</button></a>
                    </div>';
                }
                ?>
            </div>
        </div>
    </div>

</body>
</html>
