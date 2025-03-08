<?php
// Include a library for making HTTP requests
// You may need to install this via Composer: composer require guzzlehttp/guzzle
require 'vendor/autoload.php';

use GuzzleHttp\Client;

// Your Supabase credentials
$supabaseUrl = 'https://bsxbrvxhjslsaeizdxsr.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJzeGJydnhoanNsc2FlaXpkeHNyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Mzg0MTIzMzksImV4cCI6MjA1Mzk4ODMzOX0.Yy1FGkU6IVTjFbBPWobQ3S5XdMFtH2v28O57O7mZGSM';

// Create a new HTTP client
$client = new Client();

// Example: Fetch data from a table
try {
    $response = $client->request('GET', $supabaseUrl . '/rest/v1/your_table', [
        'headers' => [
            'apikey' => $supabaseKey,
            'Authorization' => 'Bearer ' . $supabaseKey
        ]
    ]);
    
    // Get and process the response
    $data = json_decode($response->getBody(), true);
    
    // Display or use the data
    print_r($data);
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>