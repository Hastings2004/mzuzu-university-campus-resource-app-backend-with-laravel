<?php

// Simple test script to verify photo upload functionality
// Run this from the project root: php test_photo_upload.php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Storage;

// Test if storage is working
echo "Testing storage functionality...\n";

// Check if storage link exists
$storageLink = public_path('storage');
if (is_link($storageLink) || is_dir($storageLink)) {
    echo "✓ Storage link/directory exists at: $storageLink\n";
} else {
    echo "✗ Storage link missing at: $storageLink\n";
}

// Check if issue_photos directory exists
$issuePhotosDir = storage_path('app/public/issue_photos');
if (is_dir($issuePhotosDir)) {
    echo "✓ Issue photos directory exists at: $issuePhotosDir\n";
} else {
    echo "✗ Issue photos directory missing at: $issuePhotosDir\n";
    // Create the directory
    mkdir($issuePhotosDir, 0755, true);
    echo "✓ Created issue photos directory\n";
}

// Test file permissions
if (is_writable($issuePhotosDir)) {
    echo "✓ Issue photos directory is writable\n";
} else {
    echo "✗ Issue photos directory is not writable\n";
}

// Test storage disk
try {
    $disk = Storage::disk('public');
    $testFile = 'test_' . time() . '.txt';
    $disk->put($testFile, 'Test content');
    
    if ($disk->exists($testFile)) {
        echo "✓ Storage disk is working\n";
        $disk->delete($testFile);
        echo "✓ Test file cleaned up\n";
    } else {
        echo "✗ Storage disk test failed\n";
    }
} catch (Exception $e) {
    echo "✗ Storage disk error: " . $e->getMessage() . "\n";
}

echo "\nPhoto upload test completed!\n"; 