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
            height: 90vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f3e4d0;
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

        .top-bar {
            background-color: #80592d;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            font-size: 16px;
        }

  

        .logo img {
            height: 50px; /* Adjust size as needed */
        }
    </style>
</head>
<body>



    <div class="login-container">
        <div class="login-box">
            <?php
            if (isset($_SESSION['username'])) {
                echo "<h2>Welcome, " . htmlspecialchars($_SESSION['username']) . "!</h2>";
                echo "<p><a href='logout.php'>Log out</a></p>";
            } else {
                echo '<h1>Log in page!</h1>';
                echo '
                <div class="input-box">
                    <input type="text" placeholder="Username" required>
                </div>
                <div class="input-box">
                    <input type="password" placeholder="Password" required>
                </div>
                <button>Login</button>
                
                <div class="sign-up">
                    <p>Don\'t have an account with us?</p>
                    <a href="signup.html"><button>Sign Up</button></a>
                </div>';
            }
            ?>
        </div>
    </div>

</body>
</html>


