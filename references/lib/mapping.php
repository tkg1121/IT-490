<?php
// Function to map Pokémon data

function map_pokemon_data($pokemon_data){
    $records = [];
    foreach($pokemon_data as $data){
        $record["id"] = $data["id"];
        $record["name"] = $data["name"];
        $record["coverImg"] = $data["coverImg"];
        $record["price"] = $data["price"];
        $record["type"] = $data["type"];
        $record["hp"] = $data["hp"];
        $record["attack"] = $data["attack"];
        $record["defense"] = $data["defense"];
        $record["created"] = $data["created"];
        $record["modified"] = $data["modified"];
        array_push($records, $record);
    }
    return $records;
}
?>