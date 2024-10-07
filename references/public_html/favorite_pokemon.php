<?php
require_once(__DIR__ . "/../../partials/nav.php");
require_once(__DIR__ . "/../../lib/pokemon_helpers.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = get_user_id();
    $pokemonId = filter_input(INPUT_POST, 'pokemon_id', FILTER_VALIDATE_INT);

    if ($userId !== false && $pokemonId !== false) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO User_Pokemon_Favorites (user_id, pokemon_id) VALUES (:user_id, :pokemon_id)");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':pokemon_id', $pokemonId, PDO::PARAM_INT);
            $stmt->execute();

            // Respond with success
            echo 'Pokémon favorited!';
            exit;
        } catch (PDOException $e) {
            error_log("Failed to favorite Pokémon. Error: " . $e->getMessage());
            http_response_code(500);
            echo 'Failed to add Pokémon. Please try again later.';
            exit;
        }
    } else {
        http_response_code(400);
        echo 'Bad Request';
        exit;
    }
} else {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}
?>
