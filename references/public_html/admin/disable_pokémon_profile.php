<?php
// This file is part of an example of how we can persist query params
// Note: we need to go up 1 more directory
require(__DIR__ . "/../../../lib/functions.php");
require(__DIR__ . "/../../../lib/redirect.php");

// Don't forget to start the session if you need it since this is done in nav.php and not functions.php
if (session_status() != PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if the user has Admin role
if (!has_role("Admin")) {
    error_log("Doesn't have permission");
    flash("You don't have permission to view this page", "warning");
    redirect("home.php");
}

// Retrieve the Pokémon ID from the query parameters
$id = (int)se($_GET, "id", 0, false);
if ($id <= 0) {
    flash("Invalid Pokémon", "danger");
} else {
    $db = getDB();
    $query = "UPDATE Pokemon set status = 'unavailable' WHERE id = :id"; // Update to disable Pokémon
    $stmt = $db->prepare($query);
    try {
        $stmt->execute([":id" => $id]);
        flash("Successfully marked Pokémon as unavailable", "success");
    } catch (PDOException $e) {
        flash("Error updating Pokémon profile", "danger");
        error_log("Error setting Pokémon as unavailable: " . var_export($e, true));
    }
}

// Determine the redirect URL based on previous session state
if (isset($_SESSION["previous"]) && strpos($_SESSION["previous"], "admin") !== false) {
    $url = "admin/list_pokemons.php"; // Updated to reflect Pokémon listing
} else {
    $url = "browse.php";
}
$url .= "?" . http_build_query($_GET);
error_log("redirecting to " . var_export($url, true));
redirect(get_url($url));
