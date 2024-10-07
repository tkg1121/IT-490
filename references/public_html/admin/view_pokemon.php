<?php
require_once(__DIR__ . "/../../../partials/nav.php");
require_once(__DIR__ . "/../../../lib/pokemon_helpers.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    redirect("home.php");
}

$id = (int)se($_GET, "id", 0, false);

if ($id <= 0) {
    flash("Invalid Pokémon", "danger");
    redirect("list_pokemons.php");
}

$pokemon = get_pokemon_by_id($id);

if (!$pokemon) {
    flash("Pokémon not found", "danger");
    redirect("list_pokemons.php");
}

?>

<div class="container-fluid">
    <h1>Pokémon Details</h1>
    <div class="card" style="width: 50em;">
        <div class="card-header">
            <?php echo se($pokemon, 'status', 'N/A'); ?>
        </div>
        <!-- Handle image -->
        <img class="p-3" style="width: 100%; aspect-ratio: 1; object-fit: scale-down; max-height: 400px;" src="<?php echo se($pokemon, 'coverImg', 'default_image_url'); ?>" />
        <div class="card-body">
            <h5 class="card-title"><?php echo se($pokemon, 'name'); ?></h5>
            <h6 class="card-subtitle">Price: <?php echo se($pokemon, 'price', 'N/A'); ?></h6>
            <h6 class="card-subtitle text-body-secondary">Type: <?php echo se($pokemon, 'type', 'N/A'); ?></h6>
            <p class="card-text">
                HP: <?php echo se($pokemon, 'hp', 'N/A'); ?><br>
                Attack: <?php echo se($pokemon, 'attack', 'N/A'); ?><br>
                Defense: <?php echo se($pokemon, 'defense', 'N/A'); ?><br>
            </p>
        </div>
    </div>
</div>