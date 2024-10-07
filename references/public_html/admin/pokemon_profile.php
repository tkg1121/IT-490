<?php
require(__DIR__ . "/../../../partials/nav.php");
require(__DIR__ . "/../../../lib/pokemon_helpers.php");
require(__DIR__ . "/../../../lib/render_functions.php");
require(__DIR__ . "/../../../lib/redirect.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    redirect("home.php");
}

$pokemons = [];

$result = get_pokemons();
$pokemons = array_map(function ($v) {
    return ["label" => $v["name"], "value" => $v["id"]];
}, $result);

$id = (int)se($_GET, "id", 0, false);
$pokemon = [];

if (count($_POST) > 0) {
    $pokemonData = $_POST;
    $images = isset($_POST["images"]) ? $_POST["images"] : [];
    unset($_POST["images"]);

    $pokemonId = -1;

    if (validate_pokemon($_POST)) {
        if ($id > 0) {
            if (update_data("SC_Pokemon", $id, $_POST, ["id"])) {
                $pokemonId = $id;
            }
        } else {
            $pokemonId = save_data("SC_Pokemon", $_POST);
        }
    }

    $imagesStepPassed = false;

    if ($pokemonId > 0 && count($images) > 0) {
        $query = "INSERT INTO SC_PokemonImages(id, image_id) VALUES ";
        $i = 0;

        foreach ($images as $image) {
            $placeholders[] = "(:id$i, :image_id$i)";
            $values[] = [":id$i" => $pokemonId, ":image_id$i" => $image];
            $i++;
        }

        $query .= implode(",", $placeholders);
        $query .= " ON DUPLICATE KEY UPDATE id = id";

        $db = getDB();
        $stmt = $db->prepare($query);

        foreach ($values as $val) {
            bind_params($stmt, $val);
        }

        try {
            $stmt->execute();
            $imagesStepPassed = true;
        } catch (PDOException $e) {
            error_log("Error inserting/updating pokemon images: " . var_export($e, true));
            flash("There was a problem updating pokemon images", "danger");
        }
    }

    $hasImages = count($images) > 0;
    $imagesOk = $hasImages && $imagesStepPassed;

    if ($pokemonId > 0 && ($imagesOk || !$hasImages)) {
        flash("Successfully updated profile for " . $pokemonData["name"], "success");
        redirect("admin/pokemon_profile.php?id=$pokemonId");
    }
}

if ($id > 0) {
    $db = getDB();

    $query = "SELECT name, price, coverImg, type, hp, attack, defense, created, modified,
              (SELECT GROUP_CONCAT(image_id) FROM SC_PokemonImages WHERE id = SC.id) as images 
              FROM SC_Pokemon as SC WHERE id = :id";

    $stmt = $db->prepare($query);

    try {
        $stmt->execute([":id" => $id]);
        $result = $stmt->fetch();

        if ($result) {
            $pokemon = $result;

            if (isset($pokemon["images"])) {
                $pokemon["images"] = explode(",", $pokemon["images"]);
            }

            error_log("Pokemon result: " . var_export($pokemon, true));
        } else {
            flash("There was a problem finding this pokemon", "danger");
        }
    } catch (PDOException $e) {
        error_log("Error fetching pokemon by id: " . var_export($e, true));
        flash("An unhandled error occurred", "danger");
    }
}
?>

<div class="container-fluid">
    <h1>Create Pokemon</h1>
    <form method="POST">
        <?php render_input(["type" => "text", "id" => "name", "name" => "name", "label" => "Name", "rules" => ["required"], "value" => isset($pokemon["name"]) ? $pokemon["name"] : ""]); ?>
        <?php render_input(["type" => "number", "id" => "price", "name" => "price", "label" => "Price", "rules" => ["required"], "value" => isset($pokemon["price"]) ? $pokemon["price"] : ""]); ?>
        <?php render_input(["type" => "text", "id" => "type", "name" => "type", "label" => "Type", "rules" => ["required"], "value" => isset($pokemon["type"]) ? $pokemon["type"] : ""]); ?>
        <?php render_input(["type" => "number", "id" => "hp", "name" => "hp", "label" => "HP", "rules" => ["required"], "value" => isset($pokemon["hp"]) ? $pokemon["hp"] : ""]); ?>
        <?php render_input(["type" => "number", "id" => "attack", "name" => "attack", "label" => "Attack", "rules" => ["required"], "value" => isset($pokemon["attack"]) ? $pokemon["attack"] : ""]); ?>
        <?php render_input(["type" => "number", "id" => "defense", "name" => "defense", "label" => "Defense", "rules" => ["required"], "value" => isset($pokemon["defense"]) ? $pokemon["defense"] : ""]); ?>
        <?php render_input(["type" => "file", "id" => "coverImg", "name" => "coverImg", "label" => "Cover Image", "rules" => ["file"], "value" => isset($pokemon["coverImg"]) ? $pokemon["coverImg"] : ""]); ?>
        <div class="form-group">
            <label for="images">Images</label>
            <select multiple class="form-control" id="images" name="images[]">
                <?php foreach ($pokemons as $s): ?>
                    <option value="<?= $s["value"] ?>" <?= isset($pokemon["images"]) && is_array($pokemon["images"]) && in_array($s["value"], $pokemon["images"]) ? "selected" : "" ?>><?= $s["label"] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php render_button(["text" => "Save", "type" => "submit"]); ?>
    </form>
</div>

<?php require(__DIR__ . "/../../../partials/flash.php"); ?>