<?php
require_once(__DIR__ . "/../lib/render_functions.php");

// Make status options (You may want to adjust this based on your Pokémon context)
$statuses = ["Available", "Not Available"];
if (!has_role("Admin")) {
    // Adjust statuses as needed for non-admin users
    $statuses = array_filter($statuses, function ($v) {
        return $v !== "Unavailable";
    });
}
$statuses = array_map(function ($v) {
    return ["label" => $v, "value" => strtolower(str_replace(" ", "_", $v))]; // Convert spaces to underscores for value
}, $statuses);
array_unshift($statuses, ["label" => "Any", "value" => ""]);

// Make Pokémon options
$result = get_pokemons();
$pokémons = array_map(function ($v) {
    return ["label" => $v["name"], "value" => $v["id"]];
}, $result);
array_unshift($pokémons, ["label" => "Any", "value" => ""]);

// Make columns options for order by
$cols = ["Name", "Type", "HP", "Attack", "Defense"];
$cols = array_map(function ($v) {
    return ["label" => $v, "value" => strtolower(str_replace(" ", "_", $v))]; // Convert spaces to underscores for value
}, $cols);
array_unshift($cols, ["label" => "Any", "value" => ""]);

$orders = ["asc", "desc"];
$orders = array_map(function ($v) {
    return ["label" => ucfirst($v), "value" => strtolower($v)]; // Capitalize order labels
}, $orders);
array_unshift($orders, ["label" => "Any", "value" => ""]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokémon Search Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5; 
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border: 1px solid #ddd; 
        }

        th {
            background-color: #f2f2f2; 
            font-weight: bold;
        }
    </style>
</html>