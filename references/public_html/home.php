<?php
require(__DIR__ . "/../../partials/nav.php");
?>
<?php

if (is_logged_in(true)) {
    //echo "Welcome home, " . get_username();
    //comment this out if you don't want to see the session variables
    //error_log("Session data: " . var_export($_SESSION, true));
}
?>
<!!>
<div class="container-fluid">
    <div class="hero-section text-center py-5">
        <h1 class="display-2 fw-bold text-primary mb-4">Welcome to the Supercar Museum!</h1>
        <p class="lead text-muted">Explore the extraordinary world of high-performance supercars.</p>
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8">
                <p class="text-muted">
                    Our museum features a stunning collection of supercars from around the world. Each car is a masterpiece, showcasing exceptional design, power, and speed. Immerse yourself in the history and innovation behind these iconic vehicles.
                </p>
            </div>
        </div>
        <a class="btn btn-primary btn-lg mt-4" href="<?php get_url("supercars.php", true); ?>" role="button">Explore Now</a>
    </div>
</div>
<?php
require_once(__DIR__ . "/../../partials/flash.php");
?>