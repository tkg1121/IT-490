<?php if (isset($data)) : ?>
    <div class="card" style="width:15em">
        <div class="card-header">
            <?php se($data, "status", "N/A"); ?>
        </div>
        <?php /* handle image */
        $urls = isset($data["urls"]) ? $data["urls"] : "";
        $urls = explode(",", $urls); // Assuming multiple URLs could be present
        ?>
        <img class="p-3" style="width: 100%; aspect-ratio: 1; object-fit: scale-down; max-height: 256px;" src="<?php se($urls[0], "default_image_url"); ?>" />
        <div class="card-body">
            <h5 class="card-title"><?php se($data, "name"); ?></h5>
            <h6 class="card-subtitle">Type: <?php se($data, "type", "N/A"); ?></h6>
            <h6 class="card-subtitle text-body-secondary">HP: <?php se($data, "hp", "N/A"); ?></h6>
            <p class="card-text">
                Attack: <?php se($data, "attack", "N/A"); ?>
                <br>
                Defense: <?php se($data, "defense", "N/A"); ?>
                <br>
                Speed: <?php se($data, "speed", "N/A"); ?>
                <br>
            </p>
        </div>
        <div class="card-footer">
            <?php $id = se($data, "id", -1, false); ?>
            <div class="row">
                <button class="btn btn-primary col" onclick="likePokemon(<?= $id; ?>)">Like</button>
            </div>
            <?php if (has_role("Admin")) : ?>
                <!-- Admin actions (like Edit, Delete, etc.) -->
            <?php endif; ?>
            <div class="row mt-1 g-1">
                <form method="post" action="MyFavoritePokemons.php">
                    <input type="hidden" name="user_id" value="<?= get_user_id(); ?>">
                    <input type="hidden" name="pokemon_id" value="<?= $id; ?>">
                    <button type="submit" class="btn btn-warning col">Favorite</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        // JavaScript function to handle the like button click
        function likePokemon(pokemonId) {
            // You can implement AJAX here to send the like to the server
            alert('Liked Pokémon with ID ' + pokemonId);
        }
    </script>
<?php endif; ?>