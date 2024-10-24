<?php
require(__DIR__ . "/../../partials/nav.php");
?>

<?php
if (is_logged_in(true)) {
    // Uncomment if you want to see the session variables
    // error_log("Session data: " . var_export($_SESSION, true));
}
?>

<div class="container-fluid">
    <div class="hero-section text-center py-5">
        <h1 class="display-2 fw-bold text-primary mb-4">Welcome to the Pokémon!</h1>
        <p class="lead text-muted">Search and Like your favorite Pokémon!</p>
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8">
                <p class="text-muted">
                    Something to out here!
                </p>
            </div>
        </div>
        <a class="btn btn-primary btn-lg mt-4" href="<?php get_url("pokemons.php", true); ?>" role="button">Explore Now</a>
    </div>
</div>

<?php
require_once(__DIR__ . "/../../partials/flash.php");
?>