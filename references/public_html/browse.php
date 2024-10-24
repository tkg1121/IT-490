<?php
require(__DIR__ . "/../../partials/nav.php");
require(__DIR__ . "/../../lib/pokemon_helpers.php");

// remove single view filter
if (isset($_GET["id"])) {
    unset($_GET["id"]);
}

$pokemons = search_pokemons();
$total = count($pokemons); // Set the total count of records

?>
<div class="container-fluid">
    <h4>Pokémon Museum</h4>
    <div class="container mx-auto">
        <div>
            <?php include(__DIR__ . "/../../partials/search_form.php"); ?>
        </div>
        <div class="row justify-content-center">
            <?php foreach ($pokemons as $pokemon) : ?>
                <div class="col">
                    <div class="pokemon-list-item">
                        <h2><?php echo isset($pokemon['name']) ? $pokemon['name'] : 'N/A'; ?></h2>
                        <p>Type: <?php echo isset($pokemon['type']) ? $pokemon['type'] : 'N/A'; ?></p>
                        <p>HP: <?php echo isset($pokemon['hp']) ? $pokemon['hp'] : 'N/A'; ?></p>
                        <p>Attack: <?php echo isset($pokemon['attack']) ? $pokemon['attack'] : 'N/A'; ?></p>
                        <p>Defense: <?php echo isset($pokemon['defense']) ? $pokemon['defense'] : 'N/A'; ?></p>
                        <!-- Add more details as needed -->
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($pokemons)) : ?> 
                <div class="col-12">
                    No Pokémon available
                </div>
            <?php endif; ?>
        </div>
        <div class="row">
            <?php include(__DIR__ . "/../../partials/pagination_nav.php"); ?>
        </div>
    </div>
</div>
<?php
require_once(__DIR__ . "/../../partials/flash.php");
?>
