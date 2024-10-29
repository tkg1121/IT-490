<?php
// note we need to go up 1 more directory
require(__DIR__ . "/../../../partials/nav.php");

if (!has_role("Admin")) {
    flash("You don't have permission to view this page", "warning");
    die(header("Location: " . get_url("home.php")));
}

// TODO need to update insert_pokemons... to use the $mappings array and not go based on is_int for value
function insert_pokemons_into_db($db, $pokemons, $mappings)
{
    // Prepare SQL query
    $query = "INSERT INTO `SC_Pokemon` ";
    if (count($pokemons) > 0) {
        $cols = array_keys($pokemons[0]);
        $query .= "(" . implode(",", array_map(function ($col) {
            return "`$col`";
        }, $cols)) . ") VALUES ";

        // Generate the VALUES clause for each pokemon
        $values = [];
        foreach ($pokemons as $i => $pokemon) {
            $pokemonPlaceholders = array_map(function ($v) use ($i) {
                return ":$v$i";  // Use named placeholders
            }, $cols);
            $values[] = "(" . implode(",", $pokemonPlaceholders) . ")";
        }

        $query .= implode(",", $values);

        // Generate the ON DUPLICATE KEY UPDATE clause
        $updates = array_reduce($cols, function ($carry, $col) {
            $carry[] = "`$col` = VALUES(`$col`)";
            return $carry;
        }, []);

        $query .= " ON DUPLICATE KEY UPDATE " . implode(",", $updates);

        // Log the query for debugging
        error_log("Query: " . $query);

        // Prepare the statement
        $stmt = $db->prepare($query);

        // Bind the parameters for each pokemon
        foreach ($pokemons as $i => $pokemon) {
            foreach ($cols as $col) {
                $placeholder = ":$col$i";
                $val = isset($pokemon[$col]) ? $pokemon[$col] : "";

                // Ensure that the data type (PDO::PARAM_INT, PDO::PARAM_STR) matches your column type
                $param = PDO::PARAM_STR;

                if (isset($mappings[$col]) && str_contains($mappings[$col], "int")) {
                    $param = PDO::PARAM_INT;
                }

                // Bind the parameters for each pokemon
                $stmt->bindValue($placeholder, $val, $param);
            }
        }

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error executing statement: " . $e->getMessage());

            // Log the query and parameters for debugging
            error_log("Columns: " . implode(", ", $cols));
            error_log("Mappings: " . json_encode($mappings));
            error_log("Pokemons: " . json_encode($pokemons));
        }
    }
}

function process_pokemons($result)
{
    error_log("Starting process_pokemons function");

    $status = se($result, "status", 400, false);
    error_log("Status: $status");

    if ($status != 200) {
        error_log("Error: Invalid status code - $status");
        return;
    }

    // Extract data from result
    $data_string = html_entity_decode(se($result, "response", "{}", false));
    error_log("Decoded data string: $data_string");

    $wrapper = "{\"data\":$data_string}";
    $data = json_decode($wrapper, true);

    if (!isset($data["data"])) {
        error_log("Error: Missing 'data' key in API response");
        return;
    }

    $data = $data["data"];
    error_log("Data: " . var_export($data, true));

    // Get columns from SC_Pokemon table
    $db = getDB();

    if (!$db) {
        error_log("Error: Database connection failed");
        return;
    }

    $stmt = $db->prepare("SHOW COLUMNS FROM SC_Pokemon");

    if (!$stmt) {
        error_log("Error: Failed to prepare SQL statement");
        return;
    }

    $stmt->execute();
    $columnsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare columns and mappings
    $columns = array_column($columnsData, 'Field');
    $mappings = [];
    foreach ($columnsData as $column) {
        $mappings[$column['Field']] = $column['Type'];
    }
    $ignored = ["id", "created", "modified"];
    $columns = array_diff($columns, $ignored);

    // Process each pokemon
    $records = [];
    foreach ($data as $pokemon) {
        $record["id"] = $pokemon["id"];
        $record["name"] = $pokemon["name"];
        $record["coverImg"] = $pokemon["coverImg"];
        
        // Split the price range and take the minimum value
        $priceRange = explode(' - ', $pokemon["price"]);
        $record["price"] = floatval($priceRange[0]);

        $record["maxSpeed"] = $pokemon["maxSpeed"];
        $record["power"] = $pokemon["power"];
        $record["acceleration"] = $pokemon["0-100 km/h"]; 
        array_push($records, $record);
    }

    // Insert pokemons into the database
    insert_pokemons_into_db($db, $records, $mappings);
    error_log("Ending process_pokemons function");
}

$action = se($_POST, "action", "", false);
if ($action) {
    switch ($action) {
        case "pokemons":
            // fixing
            $result = get("https://pokeapi.co/api/v2/pokemon/{$pokemon_id}", "Pokémon_API", [], true);
            process_pokemons($result); // Corrected function name
            break;
    }
}
?>
<div class="container-fluid">
    <h1>Pokémon Data Management</h1>
    <div class="row">
        <div class="col">
            <!-- Pokémon refresh button -->
            <form method="POST">
                <input type="hidden" name="action" value="pokemons" />
                <input type="submit" class="btn btn-primary" value="Refresh Pokémon" />
            </form>
        </div>
    </div>
</div>