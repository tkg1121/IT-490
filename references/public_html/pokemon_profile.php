<?php
require(__DIR__ . "/../../partials/nav.php");
$id = se($_GET, "id", -1, false);
if ($id <= 0) {
    flash("Invalid Pokémon", "danger");
    $url = "browse.php?" . http_build_query($_GET);
    error_log("redirecting to " . var_export($url, true));
    redirect(get_url($url));
}
$_GET["image_limit"] = 10;
$pokemon = search_pokemons();
$pokemon = $pokemon[0];
?>
<div class="container-fluid">

    <h1>Welcome to the Pokémon Museum!</h1>
    <div class="card">
        <div class="card-header text-center">
            <?php se($pokemon, "name"); ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <h5 class="card-title"><?php se($pokemon, "name"); ?> - Type: <?php se($pokemon, "type"); ?> - HP: <?php se($pokemon, "hp"); ?></h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div class="row">
                        <?php /* handle image*/
                        $thumbnails = isset($pokemon["thumbnails"]) ? $pokemon["thumbnails"] : "";
                        error_log("thumbnails data: " . var_export($thumbnails, true));
                        ?>
                        <?php foreach ($thumbnails as $thumbnail) : ?>
                            <div class="col">
                                <img class="p-3" style="width: 100%; aspect-ratio: 1; object-fit: scale-down; max-height: 256px;" src="<?php se($thumbnail, null, get_url("images/default_pokemon_image.jpg")); ?>" />
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col">
                    <div><strong>About the Pokémon:</strong><br>
                        <?php se($pokemon, "description"); ?>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <h5>Pokémon Specifications</h5>
                    <div><strong>Power: </strong><?php se($pokemon, "power"); ?></div>
                    <div><strong>0-100 km/h: </strong><?php se($pokemon, "0-100 km/h"); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once(__DIR__ . "/../../partials/flash.php");
    ?>