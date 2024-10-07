<?php
require(__DIR__ . "/../../partials/nav.php");
$result = get("https://pokeapi.co/api/v2/pokemon/{$pokemon_id}", "Pokémon_API", [], true);
error_log("Response: " . var_export($result, true));

if (se($result, "status", 400, false) == 200 && isset($result["response"])) {
    $pokemons = json_decode($result["response"], true);
} else {
    $pokemons = [];
}

error_log("Pokémons Data: " . var_export($pokemons, true));
?>

<div class="container-fluid">
    <h1>Pokémon Museum</h1>
    <p>Explore the collection of stunning Pokémon in our museum!</p>
    <a class="btn btn-primary btn-lg mt-4" href="<?php get_url("my_pokemons.php", true); ?>" role="button">Favorite Pokémon</a>
    <div class="row card-container">
        <?php foreach ($pokemons as $pokemon) : ?>
            <div class="col">
                <div class="card mb-3" style="width: 18rem;">
                    <img src="<?php se(isset($pokemon["coverImg"]) ? $pokemon["coverImg"] : ''); ?>" class="card-img-top" alt="Pokémon Image" style="height: 140px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title"><?php se(isset($pokemon["name"]) ? $pokemon["name"] : ''); ?></h5>
                        <p class="card-text">Type: <?php se(isset($pokemon["type"]) ? $pokemon["type"] : ''); ?></p>
                        <p class="card-text">HP: <?php se(isset($pokemon["hp"]) ? $pokemon["hp"] : ''); ?></p>
                        <p class="card-text">Attack: <?php se(isset($pokemon["attack"]) ? $pokemon["attack"] : ''); ?></p>
                        <p class="card-text">Defense: <?php se(isset($pokemon["defense"]) ? $pokemon["defense"] : ''); ?></p>
                    </div>
                    <div class="card-footer">
                        <?php $id = se($pokemon, "id", -1, false); ?>
                        <div class="row">
                            <button class="btn btn-primary col like-button" data-pokemon-id="<?php echo $id; ?>" onclick="likePokemon(<?php echo $id; ?>)">Like</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
    function likePokemon(pokemonId) {
        alert('Button clicked for Pokémon ID: ' + pokemonId);
        toggleLike(pokemonId);
    }

    function toggleLike(pokemonId) {
        const likeButton = document.querySelector(`.like-button[data-pokemon-id="${pokemonId}"]`);
        likeButton.classList.toggle('liked');

        // Make an AJAX request to inform the server about the liked Pokémon
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'favorite_pokemon.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Handle the server response if needed
                console.log(xhr.responseText);
            }
        };
        xhr.send(`pokemon_id=${pokemonId}`);
    }
</script>