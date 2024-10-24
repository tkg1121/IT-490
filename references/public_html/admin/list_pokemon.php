<?php 
// Note: We need to go up 1 more directory
require(__DIR__ . "/../../../partials/nav.php");
require(__DIR__ . "/../../../lib/pokemon_helpers.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    redirect("home.php");
}

$pokemons = search_pokemons();
$table = [
    "data" => $pokemons,
    "delete_url" => "disable_pokemon_profile.php",
    "view_url" => "view_pokemon.php",
    "edit_url" => "edit_pokemon.php"
];
?>

<div class="container-fluid">
    <h1>List Pokémon</h1>
    <div>
        <?php include(__DIR__ . "/../../../partials/search_form.php"); ?>
    </div>
    <div>
        <?php render_table($table); ?>
    </div>
    <div class="row">
        <?php include(__DIR__ . "/../../../partials/pagination_nav.php"); ?>
    </div>
</div>

<?php
// Note: We need to go up 1 more directory
require_once(__DIR__ . "/../../../partials/flash.php");
?>
