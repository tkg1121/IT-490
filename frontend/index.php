<?php
session_start();
?>
<!DOCTYPE html>
<html data-bs-theme="light" lang="en" style>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
    <title>IT490</title>
</head>

<body>
    <nav class="navbar navbar-expand-md py-3" data-bs-theme="dark" style="background: #795833;color: #795833;border-style: none;border-color: #795833;border-top-style: none;border-right-style: none;border-bottom-style: none;border-left-style: none;">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.html">
                <span class="bs-icon-sm bs-icon-rounded bs-icon-primary d-flex justify-content-center align-items-center me-2 bs-icon" style="background: url('C.png');">
                    <img width="134" height="186" style="padding-top: 26px;padding-bottom: 26px;margin-bottom: -33px;margin-top: 1px;padding-right: 0px;margin-right: -11px;padding-left: 0px;margin-left: -89px;" src="C.png" loading="lazy" />
                </span>
            </a>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navcol-5">
                <span class="visually-hidden">Toggle navigation</span>
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="navcol-5" class="collapse navbar-collapse" style="background: #795833;">
                <ul class="navbar-nav ms-auto" style="background: #795833;">
                    <?php if (isset($_SESSION['username'])): ?>
                        <li class="nav-item">
                            <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Log out</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link active" href="login.html">Login Page</a></li>
                        <li class="nav-item"><a class="nav-link" href="signup.html">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <main></main>
    
    <section class="position-relative py-4 py-xl-5" style="background: #f0dfc8;">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8 col-xl-6 text-center mx-auto">
                    <?php if (isset($_SESSION['username'])): ?>
                        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                    <?php else: ?>
                        <h2>Sign up page!</h2>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row d-flex justify-content-center">
                <div class="col-md-6 col-xl-4">
                    <div class="card mb-5">
                        <div class="card-body d-flex flex-column align-items-center" style="margin-top: -25px;">
                            <div class="bs-icon-xl bs-icon-circle bs-icon-primary bs-icon my-4" style="background: #795833;">
                                <svg class="bi bi-person" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664z"></path>
                                </svg>
                            </div>
                            <?php if (!isset($_SESSION['username'])): ?>
                                <form class="text-center" method="post">
                                    <div class="mb-3"><input class="form-control" type="email" name="Username" placeholder="Username" /></div>
                                    <div class="mb-3">
                                        <input class="form-control" type="password" name="password" placeholder="Password" style="margin-bottom: 14px;" />
                                        <input class="form-control" type="text" name="password" placeholder="Password" />
                                    </div>
                                    <div class="mb-3">
                                        <button class="btn btn-primary d-block w-100" type="submit" style="background: #795833;">Sign Up</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>

</html>

