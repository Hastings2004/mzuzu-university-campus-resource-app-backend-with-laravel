<?php

// Test script for document API endpoints
echo "=== Testing Document API Endpoints ===\n\n";

// Test 1: Try to access without authentication (should fail)
echo "1. Testing without authentication (should fail):\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/bookings/6/document");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Test 2: Login to get token
echo "2. Logging in to get authentication token:\n";
$loginData = json_encode([
    'email' => 'hastingschitenje81@gmail.com',
    'password' => '12345678' // Updated password
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/login");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$loginResponse = json_decode($response, true);
echo "Response: $response\n\n";

if ($httpCode === 200 && isset($loginResponse['token'])) {
    $token = $loginResponse['token'];
    echo "3. Testing with authentication token:\n";
    
    // Test document view endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/bookings/1/document");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Document View Endpoint:\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Test document download endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/bookings/1/download-document");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Document Download Endpoint:\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
    // Test document metadata endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/api/bookings/1/document-metadata");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Document Metadata Endpoint:\n";
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";
    
} else {
    echo "Login failed. Please check the email and password.\n";
    echo "Available emails: hastingschitenje81@gmail.com, ibrahimcassim031@gmail.com, moyo@gmail.com, numeri@my.mzuni.ac.mw\n";
}

echo "=== Test Complete ===\n";
?> 