<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Resource;
use App\Services\ResourceService;
use Illuminate\Support\Facades\Storage;

echo "Testing Image Upload Fix\n";
echo "========================\n\n";

// Test 1: Check if storage is properly configured
echo "1. Testing storage configuration...\n";
$storageLink = public_path('storage');
if (is_link($storageLink) || is_dir($storageLink)) {
    echo "✓ Storage link exists\n";
} else {
    echo "✗ Storage link missing - run: php artisan storage:link\n";
}

// Test 2: Check if resources directory exists
$resourcesDir = storage_path('app/public/resources');
if (is_dir($resourcesDir)) {
    echo "✓ Resources directory exists\n";
} else {
    echo "✗ Resources directory missing\n";
    mkdir($resourcesDir, 0755, true);
    echo "✓ Created resources directory\n";
}

// Test 3: Test ResourceService image upload
echo "\n2. Testing ResourceService image upload...\n";

// Create a simple test image file
$testImagePath = 'test_image.jpg';
$testImageContent = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/8A');
file_put_contents($testImagePath, $testImageContent);

// Create a mock UploadedFile
$uploadedFile = new \Illuminate\Http\UploadedFile(
    $testImagePath,
    'test_image.jpg',
    'image/jpeg',
    null,
    true
);

$testData = [
    'name' => 'Test Resource with Image',
    'description' => 'Test resource with proper image upload',
    'location' => 'Test Location',
    'capacity' => 50,
    'category' => 'classrooms',
    'status' => 'available',
    'special_approval' => 'no',
    'image' => $uploadedFile
];

try {
    $resourceService = new ResourceService();
    $resource = $resourceService->createResource($testData);
    
    echo "✓ Resource created successfully\n";
    echo "  - ID: {$resource->id}\n";
    echo "  - Name: {$resource->name}\n";
    echo "  - Image Path: {$resource->image}\n";
    echo "  - Image URL: {$resource->image_url}\n";
    
    // Verify the image file exists
    if (Storage::disk('public')->exists($resource->image)) {
        echo "✓ Image file exists in storage\n";
    } else {
        echo "✗ Image file not found in storage\n";
    }
    
    // Clean up test resource
    $resourceService->deleteResource($resource);
    echo "✓ Test resource cleaned up\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Clean up test file
if (file_exists($testImagePath)) {
    unlink($testImagePath);
}

echo "\n3. Checking for existing resources with temp paths...\n";
$resourcesWithTempPaths = Resource::where('image', 'like', '%tmp%')->get();
if ($resourcesWithTempPaths->count() > 0) {
    echo "⚠ Found {$resourcesWithTempPaths->count()} resource(s) with temporary file paths:\n";
    foreach ($resourcesWithTempPaths as $resource) {
        echo "  - ID: {$resource->id}, Name: {$resource->name}, Image: {$resource->image}\n";
    }
    echo "\nYou should clean up these resources manually or update their image paths.\n";
} else {
    echo "✓ No resources with temporary file paths found\n";
}

echo "\nTest completed!\n"; 