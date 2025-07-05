<?php

require_once 'vendor/autoload.php';

use App\Services\BookingService;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Booking Conflict Detection Test ===\n\n";

$bookingService = new BookingService();

// Test 1: Clean up old status values
echo "1. Cleaning up old status values...\n";
$cleanedCount = $bookingService->cleanupOldStatusValues();
echo "Cleaned up {$cleanedCount} records\n\n";

// Test 2: Debug conflict detection for a specific resource
echo "2. Testing conflict detection...\n";

// Get the first resource
$resource = Resource::first();
if (!$resource) {
    echo "No resources found in database\n";
    exit;
}

echo "Testing with resource: {$resource->name} (ID: {$resource->id})\n";

// Create test time slots
$now = Carbon::now();
$testStart = $now->copy()->addHours(1);
$testEnd = $testStart->copy()->addHours(2);

echo "Testing time slot: {$testStart->format('Y-m-d H:i')} to {$testEnd->format('Y-m-d H:i')}\n\n";

// Debug conflict detection
$debugInfo = $bookingService->debugConflictDetection($resource->id, $testStart, $testEnd);

echo "Debug Results:\n";
echo "- Resource: {$debugInfo['resource']['name']} (Capacity: {$debugInfo['resource']['capacity']})\n";
echo "- All bookings count: {$debugInfo['all_bookings_count']}\n";
echo "- Conflicting bookings count: {$debugInfo['conflicting_bookings_count']}\n";
echo "- Manual conflicts count: {$debugInfo['manual_conflicts_count']}\n\n";

if ($debugInfo['conflicting_bookings_count'] > 0) {
    echo "Conflicting bookings found:\n";
    foreach ($debugInfo['conflicting_bookings'] as $conflict) {
        echo "- Booking #{$conflict['id']}: {$conflict['start_time']} to {$conflict['end_time']} (Status: {$conflict['status']}, User: {$conflict['user']})\n";
    }
    echo "\n";
}

// Test 3: Check availability status
echo "3. Testing availability status...\n";
$availability = $bookingService->checkAvailabilityStatus($resource->id, $testStart, $testEnd);

echo "Availability Results:\n";
echo "- Available: " . ($availability['available'] ? 'Yes' : 'No') . "\n";
echo "- Has Conflict: " . ($availability['hasConflict'] ? 'Yes' : 'No') . "\n";
echo "- Message: {$availability['message']}\n";

if (isset($availability['conflicts']) && count($availability['conflicts']) > 0) {
    echo "- Conflicts:\n";
    foreach ($availability['conflicts'] as $conflict) {
        echo "  * Booking #{$conflict['id']}: {$conflict['start_time']} to {$conflict['end_time']} (Priority: {$conflict['priority_level']}, User: {$conflict['user']})\n";
    }
}

echo "\n=== Test Complete ===\n"; 