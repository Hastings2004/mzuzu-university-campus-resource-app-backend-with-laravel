<?php

// Simple test script to verify booking file upload functionality
// Run this from the project root: php test_booking_upload.php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Storage;

// Test if booking_documents directory exists and is writable
echo "Testing booking document upload functionality...\n";

// Check if storage link exists
$storageLink = public_path('storage');
if (is_link($storageLink) || is_dir($storageLink)) {
    echo "✓ Storage link/directory exists at: $storageLink\n";
} else {
    echo "✗ Storage link missing at: $storageLink\n";
}

// Check if booking_documents directory exists
$bookingDocsDir = storage_path('app/public/booking_documents');
if (is_dir($bookingDocsDir)) {
    echo "✓ Booking documents directory exists at: $bookingDocsDir\n";
} else {
    echo "✗ Booking documents directory missing at: $bookingDocsDir\n";
    // Create the directory
    mkdir($bookingDocsDir, 0755, true);
    echo "✓ Created booking documents directory\n";
}

// Test file permissions
if (is_writable($bookingDocsDir)) {
    echo "✓ Booking documents directory is writable\n";
} else {
    echo "✗ Booking documents directory is not writable\n";
}

// Test storage disk for booking documents
try {
    $disk = Storage::disk('public');
    $testFile = 'booking_documents/test_' . time() . '.txt';
    $disk->put($testFile, 'Test booking document content');
    
    if ($disk->exists($testFile)) {
        echo "✓ Storage disk is working for booking documents\n";
        $disk->delete($testFile);
        echo "✓ Test file cleaned up\n";
    } else {
        echo "✗ Storage disk test failed for booking documents\n";
    }
} catch (Exception $e) {
    echo "✗ Storage disk error: " . $e->getMessage() . "\n";
}

// Test URL generation
try {
    $testUrl = Storage::url('booking_documents/test_url.txt');
    echo "✓ Storage URL generation working: $testUrl\n";
} catch (Exception $e) {
    echo "✗ Storage URL generation error: " . $e->getMessage() . "\n";
}

echo "\nBooking document upload test completed!\n";
echo "\nTo test the actual API endpoint, you can use:\n";
echo "1. Make sure your frontend sends the file with field name 'supporting_document'\n";
echo "2. Use multipart/form-data content type\n";
echo "3. Include the file in the request body\n";
echo "4. The file should be one of: pdf, doc, docx, jpg, jpeg, png\n";
echo "5. File size should be under 2MB\n"; 