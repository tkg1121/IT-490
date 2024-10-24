<?php
// Set the API endpoint and your API key
$api_url = 'http://www.omdbapi.com/?i=tt3896198&apikey=f1454097';
$api_key = 'f1454097';

// Initialize cURL
$ch = curl_init();

// Set the options for the request
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
]);

// Execute the request and capture the response
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    // Decode JSON response if successful
    $data = json_decode($response, true);
    print_r($data); // Do something with the data
}

// Close cURL session
curl_close($ch);
?>
