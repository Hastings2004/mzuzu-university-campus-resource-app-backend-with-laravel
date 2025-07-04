<?php

/**
 * Test file for Password Reset Functionality
 * 
 * This file demonstrates how to test the password reset endpoints
 * Run this file to test the forgot password functionality
 */

// Base URL of your Laravel application
$baseUrl = 'http://localhost:8000/api';

// Test email (replace with a real email from your database)
$testEmail = 'test@my.mzuni.ac.mw';

echo "=== Password Reset Test ===\n\n";

// Test 1: Request password reset
echo "1. Testing forgot password request...\n";
$forgotPasswordData = [
    'email' => $testEmail
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/forgot-password');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($forgotPasswordData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n\n";

// Parse the response
$responseData = json_decode($response, true);

if ($responseData && isset($responseData['success']) && $responseData['success']) {
    echo "✅ Password reset link sent successfully!\n";
    echo "📧 Check the email: $testEmail\n\n";
    
    echo "=== Next Steps ===\n";
    echo "1. Check your email for the password reset link\n";
    echo "2. Click the link to go to the reset password page\n";
    echo "3. Enter your new password and confirm it\n";
    echo "4. Submit the form to complete the password reset\n\n";
    
    echo "=== API Endpoints Used ===\n";
    echo "POST /api/forgot-password - Request password reset\n";
    echo "POST /api/check-reset-token - Validate reset token\n";
    echo "POST /api/reset-password - Reset password with token\n\n";
    
    echo "=== Frontend Integration ===\n";
    echo "The reset link will redirect to: " . config('app.frontend_url') . "/reset-password\n";
    echo "With query parameters: token=xxx&email=$testEmail\n\n";
    
} else {
    echo "❌ Failed to send password reset link\n";
    if (isset($responseData['message'])) {
        echo "Error: " . $responseData['message'] . "\n";
    }
}

echo "=== Test Complete ===\n";
echo "Note: This is a demonstration. In a real application, you would:\n";
echo "1. Have a proper frontend form for forgot password\n";
echo "2. Handle the reset password form submission\n";
echo "3. Show appropriate success/error messages to users\n";
echo "4. Implement proper error handling and validation\n"; 