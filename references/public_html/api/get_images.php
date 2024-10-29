<?php
require_once(__DIR__ . "/../../../lib/functions.php");
$pokemon_id = se($_GET, "pokemon_id", -1, false);
$result = get_images_by_pokemon_id($pokemon_id, true);
echo json_encode($result);
?>
