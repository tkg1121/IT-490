<?php
// Change to Pokémon
$VALID_ORDER_COLUMNS = ["id", "name", "price", "hp", "attack", "defense", "created", "modified"];

function get_pokemon_from_api_by_id($pokemon_id)
{
    $data = [
        "id" => $pokemon_id
    ];

    $result = get("https://pokeapi.co/api/v2/pokemon/{$pokemon_id}", "Pokémon_API", [], true);

    if (isset($result) && isset($result["status"]) && $result["status"] == 200) {
        return json_decode($result["response"], true);
    }
    return [];
}

function _store_pokemons($pokemons)
{
    $data = [];

    foreach ($pokemons as $pokemon) 
    {
        array_push($data, [
            "id" => $pokemon["id"],
            "name" => $pokemon["name"],
            "coverImg" => $pokemon["sprites"]["front_default"], // Assuming sprite as image
            "price" => $pokemon["price"], // Custom field if needed
            "hp" => $pokemon["stats"][0]["base_stat"], // HP
            "attack" => $pokemon["stats"][1]["base_stat"], // Attack
            "defense" => $pokemon["stats"][2]["base_stat"], // Defense
        ]);
    }

    $query = "INSERT INTO Pokemon(id, name, coverImg, price, hp, attack, defense) VALUES ";
    $values = [];
    $placeholders = [];
    $i = 0;

    foreach ($data as $record) 
    {
        $placeholders[] = "(:id$i, :name$i, :coverImg$i, :price$i, :hp$i, :attack$i, :defense$i)";
        $values[] = [
            ":id$i" => $record["id"],
            ":name$i" => $record["name"],
            ":coverImg$i" => $record["coverImg"],
            ":price$i" => $record["price"],
            ":hp$i" => $record["hp"],
            ":attack$i" => $record["attack"],
            ":defense$i" => $record["defense"]
        ];
        $i++;
    }

    $query .= implode(',', $placeholders);
    $query .= " ON DUPLICATE KEY UPDATE modified = CURRENT_TIMESTAMP()";

    $db = getDB();
    $stmt = $db->prepare($query);

    foreach ($values as $index => $val) 
    {
        foreach ($val as $key => $v) 
        {
            $stmt->bindValue($key, $v);
        }
    }

    try 
    {
        $stmt->execute();
    } 

    catch (PDOException $e) 
    {
        error_log("Error inserting Pokémon data: " . var_export($e, true));
    }
}

function get_pokemons()
{
    $db = getDB();
    $query = "SELECT id, name, coverImg, price, hp, attack, defense, modified FROM Pokemon";
    $stmt = $db->prepare($query);

    try {
        $stmt->execute();
        $result = $stmt->fetchAll();
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching pokemons from db: " . var_export($e, true));
    }

    return [];
}

function validate_pokemon($pokemon)
{
    error_log("pokemon: " . var_export($pokemon, true));
    $name = se($pokemon, "name", "", false);
    $has_error = false;

    // name rules
    if (empty($name)) {
        flash("Name is required", "warning");
        $has_error = true;
    }

    if (strlen($name) < 2) {
        flash("Name must be at least 2 characters", "warning");
        $has_error = false;
    }

    return !$has_error;
}

// Helper function to bind parameters
function bind_params($stmt, $params)
{
    foreach ($params as $param => &$value) {
        $stmt->bindParam($param, $value);
    }
}

// Helper function to build search queries
function _build_search_query(&$params, $search)
{
    $query = "SELECT * FROM User_Pokemon_Favorites WHERE 1";

    if (!empty($search['name'])) {
        $query .= " AND name LIKE :name";
        $params[':name'] = '%' . $search['name'] . '%';
    }

    return $query;
}

// Fetch Pokémon by ID
function get_pokemon_by_id($id)
{
    $db = getDB();
    $query = "SELECT * FROM User_Pokemon_Favorites WHERE id = :id";
    $stmt = $db->prepare($query);

    try {
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching Pokémon by ID: " . var_export($e, true));
    }

    return null;
}

// Search for Pokémon
function search_pokemons()
{
    global $search, $total;
    $pokemons = [];
    $params = [];

    $user_id = get_user_id();

    $query = _build_search_query($params, $search);
    $query .= " AND id IN (SELECT pokemon_id FROM User_Pokemon_Favorites WHERE user_id = :user_id)";

    $db = getDB();
    $stmt = $db->prepare($query);
    $params[':user_id'] = $user_id;

    bind_params($stmt, $params);

    try {
        $stmt->execute();
        $result = $stmt->fetchAll();
        if ($result) {
            $pokemons = $result;
        }
    } catch (PDOException $e) {
        flash("An error occurred while searching for Pokémon: " . $e->getMessage(), "warning");
        error_log("Pokémon Search Error: " . var_export($e, true));
    }

    $total = count($pokemons);
    $limit = (int)se($search, "limit", 10, false);
    $page = (int)se($search, "page", "1", false);

    if ($limit > 0 && $page > 0) {
        $offset = ($page - 1) * $limit;
        if ($offset >= 0) {
            $pokemons = array_slice($pokemons, $offset, $limit);
        }
    }

    return $pokemons;
}
function get_user_favorite_pokemons($userId) {
    // Initialize database connection
    $db = getDB();

    // Prepare SQL query to fetch favorite Pokémon by user ID
    $query = "SELECT P.id, P.name, P.coverImg, P.price, P.hp, P.attack, P.defense
              FROM User_Pokemon_Favorites UPF
              JOIN Pokemon P ON UPF.pokemon_id = P.id
              WHERE UPF.user_id = :user_id";

    // Prepare statement
    $stmt = $db->prepare($query);

    // Execute query with user ID as parameter
    try {
        $stmt->execute([":user_id" => $userId]);
        // Fetch all favorite Pokémon
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error
        error_log("Error fetching user's favorite Pokémon: " . var_export($e, true));
        throw $e;
    }
}

// Render Pokémon card
function render_pokemon_card($pokemon)
{
    ?>
    <div class="card mb-3" style="width: 18rem;">
        <img src="<?php echo isset($pokemon["coverImg"]) ? $pokemon["coverImg"] : ''; ?>" class="card-img-top" alt="Pokémon Image" style="height: 140px; object-fit: cover;">
        <div class="card-body">
            <h5 class="card-title"><?php echo isset($pokemon["name"]) ? $pokemon["name"] : ''; ?></h5>
            <p class="card-text">HP: <?php echo isset($pokemon["hp"]) ? $pokemon["hp"] : ''; ?></p>
            <p class="card-text">Attack: <?php echo isset($pokemon["attack"]) ? $pokemon["attack"] : ''; ?></p>
            <p class="card-text">Defense: <?php echo isset($pokemon["defense"]) ? $pokemon["defense"] : ''; ?></p>
        </div>
        <div class="card-footer">
            <?php $id = isset($pokemon["id"]) ? $pokemon["id"] : -1; ?>
            <div class="row">
                <button class="btn btn-primary col like-button" data-pokemon-id="<?php echo $id; ?>" onclick="likePokemon(<?php echo $id; ?>)">Like</button>
            </div>
        </div>
    </div>
    <?php
}
?>