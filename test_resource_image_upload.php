<?php

require_once 'vendor/autoload.php';

use App\Models\Resource;
use App\Services\ResourceService;

// Test the image upload functionality
echo "Testing Resource Image Upload Functionality\n";
echo "===========================================\n\n";

// Create a test image file
$testImagePath = 'test_image.jpg';
$testImageContent = file_get_contents('https://via.placeholder.com/300x200.jpg?text=Test+Resource+Image');
file_put_contents($testImagePath, $testImageContent);

// Create a mock UploadedFile
$uploadedFile = new \Illuminate\Http\UploadedFile(
    $testImagePath,
    'test_image.jpg',
    'image/jpeg',
    null,
    true
);

// Test data
$testData = [
    'name' => 'Test Resource with Image',
    'description' => 'This is a test resource with an uploaded image',
    'location' => 'Test Location',
    'capacity' => 50,
    'category' => 'classrooms',
    'status' => 'available',
    'image' => $uploadedFile
];

echo "Test Data:\n";
print_r($testData);

echo "\nTesting ResourceService createResource method...\n";

try {
    $resourceService = new ResourceService();
    $resource = $resourceService->createResource($testData);
    
    echo "✅ Resource created successfully!\n";
    echo "Resource ID: " . $resource->id . "\n";
    echo "Resource Name: " . $resource->name . "\n";
    echo "Image Path: " . $resource->image . "\n";
    echo "Image URL: " . $resource->image_url . "\n";
    
    // Clean up - delete the test resource
    $resourceService->deleteResource($resource);
    echo "✅ Test resource cleaned up successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Clean up test file
if (file_exists($testImagePath)) {
    unlink($testImagePath);
    echo "✅ Test image file cleaned up!\n";
}

echo "\nTest completed!\n"; 