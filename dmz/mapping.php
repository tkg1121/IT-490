<?php
// Function to map movie data

function map_movie_data($movie_data){
    $records = [];
    foreach($movie_data as $data){
        $record["id"] = $data["id"]; // Analogous to IMDb ID
        $record["title"] = $data["title"]; // Movie title
        $record["poster"] = $data["poster"]; // Cover or poster image URL
        $record["year"] = $data["year"]; // Release year
        $record["type"] = $data["type"]; // Type of content (movie, series, episode)
        $record["plot"] = $data["plot"]; // Short or full plot summary
        $record["rating"] = $data["rating"]; // User rating
        $record["genre"] = $data["genre"]; // Genre(s)
        $record["runtime"] = $data["runtime"]; // Runtime duration
        $record["language"] = $data["language"]; // Language of the content
        $record["created"] = $data["created"]; // Created timestamp
        $record["modified"] = $data["modified"]; // Last modified timestamp
        array_push($records, $record);
    }
    return $records;
}
?>
