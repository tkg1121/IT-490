<?php
require_once(__DIR__ . "/../../partials/nav.php");
require_once(__DIR__ . "/../../lib/pokemon_helpers.php");
is_logged_in(true);

$userId = get_user_id();

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*
        FROM P_Pokemon p
        JOIN User_Pokemon_Favorites uf ON p.id = uf.pokemon_id
        WHERE uf.user_id = :user_id
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $favoritePokemons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Define $results to pass it to result_metrics.php
    $results = $favoritePokemons;
} catch (PDOException $e) {
    error_log("Failed to fetch favorite pokémons. Error: " . $e->getMessage());
    flash('Failed to fetch favorite pokémons. Please try again later.', 'danger');
    redirect('pokemon.php');
    exit;
}

// Define $total before including result_metrics.php
$total = count($favoritePokemons);

?>
<div class="container-fluid">
    <h4>My Favorite Pokémon</h4>
    <div class="container mx-auto">
        <?php
        // Include result_metrics.php after defining $results and $total
        include(__DIR__ . "/../../partials/result_metrics.php");
        ?>
        <div class="row justify-content-center">
            <?php foreach ($favoritePokemons as $pokemon) : ?>
                <div class="col">
                    <?php render_pokemon_card($pokemon); ?>
                </div>
            <?php endforeach; ?>
            <?php if (count($favoritePokemons) === 0) : ?>
                <div class="col-12">
                    You haven't liked any Pokémon yet.
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
