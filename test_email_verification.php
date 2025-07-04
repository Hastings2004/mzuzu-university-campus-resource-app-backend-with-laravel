<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use ReflectionClass;

// Test email verification URL generation
echo "Testing Email Verification URL Generation...\n";

// Get a test user (assuming you have one)
$user = User::first();
if (!$user) {
    echo "No users found in database. Please create a user first.\n";
    exit;
}

echo "Testing with user: {$user->email}\n";

// Create notification instance
$notification = new EmailVerificationNotification();

// Use reflection to access the protected method
$reflection = new ReflectionClass($notification);
$method = $reflection->getMethod('verificationUrl');
$method->setAccessible(true);

// Generate verification URL
$verificationUrl = $method->invoke($notification, $user);
echo "Generated URL: {$verificationUrl}\n";

// Parse the URL to extract components
$urlParts = parse_url($verificationUrl);
$pathParts = explode('/', trim($urlParts['path'], '/'));
$queryParams = [];
parse_str($urlParts['query'], $queryParams);

echo "\nURL Components:\n";
echo "Path: {$urlParts['path']}\n";
echo "User ID: {$pathParts[3]}\n";
echo "Hash: {$pathParts[4]}\n";
echo "Expires: {$queryParams['expires']}\n";
echo "Signature: {$queryParams['signature']}\n";

// Test signature verification
echo "\nTesting Signature Verification...\n";

$id = $pathParts[3];
$hash = $pathParts[4];
$expires = $queryParams['expires'];
$signature = $queryParams['signature'];

// Recreate the query string
$queryString = http_build_query([
    'id' => $id,
    'hash' => $hash,
    'expires' => $expires,
]);

// Generate expected signature
$expectedSignature = hash_hmac('sha256', $queryString, config('app.key'));

echo "Query String: {$queryString}\n";
echo "Expected Signature: {$expectedSignature}\n";
echo "Actual Signature: {$signature}\n";
echo "Signatures Match: " . (hash_equals($expectedSignature, $signature) ? 'YES' : 'NO') . "\n";

// Test hash verification
echo "\nTesting Hash Verification...\n";
$expectedHash = sha1($user->getEmailForVerification());
echo "Expected Hash: {$expectedHash}\n";
echo "Actual Hash: {$hash}\n";
echo "Hashes Match: " . (hash_equals($expectedHash, $hash) ? 'YES' : 'NO') . "\n";

// Test expiration
echo "\nTesting Expiration...\n";
$currentTime = time();
$expirationTime = $expires;
echo "Current Time: {$currentTime}\n";
echo "Expiration Time: {$expirationTime}\n";
echo "Link Expired: " . ($currentTime > $expirationTime ? 'YES' : 'NO') . "\n";

echo "\nTest completed!\n"; 