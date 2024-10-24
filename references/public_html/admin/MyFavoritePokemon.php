<?php
require(__DIR__ . "/../../partials/nav.php");

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if ($userId !== false) {
        try {
            // Fetch favorite pokemons for the logged-in user
            $favoritePokemons = get_user_favorite_pokemons($userId);

            // Respond with success
            flash('Pokémon favorited!', 'success');
        } catch (PDOException $e) {
            // Log the error for debugging
            error_log("Failed to fetch favorite pokemons. Error: " . $e->getMessage());

            // Respond with failure (database error)
            flash('Failed to fetch favorite pokemons. Please try again later.', 'danger');
        }
    } else {
        // Respond with failure (invalid inputs)
        flash('Invalid inputs. Failed to fetch favorite pokemons.', 'danger');
    }
} else {
    // Respond with failure (unsupported request method)
    http_response_code(405);
    echo 'Method Not Allowed';
}

// Rest of the code to display the favorite pokemons
?>