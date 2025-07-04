<?php

use App\Models\User;
use App\Models\Resource;
use App\Models\Booking;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Create or get a test user
$user = User::first() ?? User::create([
    'first_name' => 'Test',
    'last_name' => 'User',
    'user_type' => 'student',
    'email' => 'testuser' . uniqid() . '@example.com',
    'email_verified_at' => now(),
    'password' => Hash::make('password'),
]);

// 2. Create or get a test resource
$resource = Resource::first() ?? Resource::create([
    'name' => 'Test Resource',
    'location' => 'Test Location',
    'description' => 'Test resource for booking',
    'capacity' => 10,
    'type' => 'room',
    'is_active' => true,
]);

// 3. Copy a test document to storage/app/public/booking_documents/
$testDocSource = __DIR__ . '/test.pdf'; // Place a test.pdf in your project root
$testDocDir = __DIR__ . '/storage/app/public/booking_documents/';
if (!is_dir($testDocDir)) {
    mkdir($testDocDir, 0777, true);
}
$testDocDest = $testDocDir . 'test_' . time() . '.pdf';
if (!file_exists($testDocSource)) {
    echo "Please place a test.pdf file in your project root before running this script.\n";
    exit(1);
}
copy($testDocSource, $testDocDest);
$relativeDocPath = 'booking_documents/' . basename($testDocDest);

// 4. Create the booking
$booking = Booking::create([
    'booking_reference' => 'REF' . strtoupper(Str::random(8)),
    'user_id' => $user->id,
    'resource_id' => $resource->id,
    'start_time' => now()->addDay(),
    'end_time' => now()->addDays(2),
    'status' => Booking::STATUS_APPROVED,
    'purpose' => 'Test booking with document',
    'booking_type' => 'standard',
    'priority' => 1,
    'supporting_document_path' => $relativeDocPath,
]);

echo "Created booking with ID: {$booking->id}\n";
echo "Document path: {$booking->supporting_document_path}\n";
echo "User email: {$user->email}\n";
echo "User password: password\n"; 