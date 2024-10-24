<?php
require_once(__DIR__ . "/../../../partials/nav.php");
require_once(__DIR__ . "/../../../lib/pokemon_helpers.php"); 
require_once(__DIR__ . "/../../../lib/redirect.php");

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

// Handle form submission for updating Pokémon details
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate and process form data, then update the Pokémon details in the database

    // Example: Update Pokémon details
    $newName = $_POST["new_name"]; // Make sure to sanitize and validate user input
    $newType = $_POST["new_type"];
    $newHP = $_POST["new_hp"];
    $newAttack = $_POST["new_attack"];
    $newDefense = $_POST["new_defense"];

    // Prepare data as an array
    $updateData = [
        "name" => $newName,
        "type" => $newType,
        "hp" => $newHP,
        "attack" => $newAttack,
        "defense" => $newDefense
    ];

    update_data("P_Pokemon", $id, $updateData);

    // Redirect to view_pokemon.php after updating
    redirect("admin/view_pokemon.php?id=" . $id);
}
?>

<div class="container-fluid">
    <h1>Edit Pokémon</h1>
    <table class="table">
        <!-- Existing table rows for displaying Pokémon details -->

        <!-- Form for editing Pokémon details -->
        <form method="POST">
            <label for="new_name">New Name:</label>
            <input type="text" id="new_name" name="new_name" value="<?php echo se($pokemon['name']); ?>" required>

            <label for="new_type">New Type:</label>
            <input type="text" id="new_type" name="new_type" value="<?php echo se($pokemon['type']); ?>" required>

            <label for="new_hp">New HP:</label>
            <input type="text" id="new_hp" name="new_hp" value="<?php echo se($pokemon['hp']); ?>" required>

            <label for="new_attack">New Attack:</label>
            <input type="text" id="new_attack" name="new_attack" value="<?php echo se($pokemon['attack']); ?>" required>

            <label for="new_defense">New Defense:</label>
            <input type="text" id="new_defense" name="new_defense" value="<?php echo se($pokemon['defense']); ?>" required>

            <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
        </form>

        <a href="view_pokemon.php?id=<?php echo se($pokemon, 'id'); ?>" class="btn btn-secondary mt-3">Cancel</a>
    </div>
</div>
