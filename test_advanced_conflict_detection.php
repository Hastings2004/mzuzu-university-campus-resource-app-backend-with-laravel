<?php

require_once 'vendor/autoload.php';

use App\Services\AdvancedConflictDetectionService;
use App\Services\BookingService;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Models\ResourceIssue;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Advanced Conflict Detection Test ===\n\n";

$advancedConflictService = new AdvancedConflictDetectionService();
$bookingService = new BookingService();

// Test 1: Get a resource and user for testing
echo "1. Setting up test data...\n";
$resource = Resource::first();
$user = User::first();

if (!$resource || !$user) {
    echo "No resources or users found in database\n";
    exit;
}

echo "Testing with resource: {$resource->name} (ID: {$resource->id})\n";
echo "Testing with user: {$user->first_name} {$user->last_name} (ID: {$user->id})\n\n";

// Test 2: Create test time slots
$now = Carbon::now();
$testStart = $now->copy()->addHours(1);
$testEnd = $testStart->copy()->addHours(2);

echo "2. Testing time slot: {$testStart->format('Y-m-d H:i')} to {$testEnd->format('Y-m-d H:i')}\n\n";

// Test 3: Test advanced conflict detection
echo "3. Testing advanced conflict detection...\n";
$advancedConflicts = $advancedConflictService->detectAdvancedConflicts(
    $resource->id,
    $testStart,
    $testEnd,
    $user
);

echo "Advanced Conflict Detection Results:\n";
echo "- Has conflicts: " . ($advancedConflicts['has_conflicts'] ? 'Yes' : 'No') . "\n";
echo "- Resource status: {$advancedConflicts['resource_status']}\n";
echo "- Resource capacity: {$advancedConflicts['resource_capacity']}\n";

if (!empty($advancedConflicts['conflict_types'])) {
    echo "- Conflict types: " . implode(', ', $advancedConflicts['conflict_types']) . "\n";
}

if (!empty($advancedConflicts['conflicts'])) {
    echo "- Number of conflicts: " . count($advancedConflicts['conflicts']) . "\n";
    foreach ($advancedConflicts['conflicts'] as $index => $conflict) {
        echo "  Conflict " . ($index + 1) . ":\n";
        echo "    Type: {$conflict['type']}\n";
        echo "    Severity: {$conflict['severity']}\n";
        echo "    Message: {$conflict['message']}\n";
        if (isset($conflict['suggestion'])) {
            echo "    Suggestion: {$conflict['suggestion']}\n";
        }
        echo "\n";
    }
}

if (!empty($advancedConflicts['suggestions'])) {
    echo "- Suggestions:\n";
    foreach ($advancedConflicts['suggestions'] as $suggestion) {
        echo "  * {$suggestion['message']} (Priority: {$suggestion['priority']})\n";
    }
    echo "\n";
}

// Test 4: Test alternative resources
echo "4. Testing alternative resources...\n";
$alternativeResources = $advancedConflictService->getAlternativeResources($resource, $testStart, $testEnd, $user);

echo "Alternative Resources Found: " . count($alternativeResources) . "\n";
foreach ($alternativeResources as $index => $altResource) {
    echo "  Alternative " . ($index + 1) . ":\n";
    echo "    Name: {$altResource['resource_name']}\n";
    echo "    Location: {$altResource['location']}\n";
    echo "    Capacity: {$altResource['capacity']}\n";
    echo "    Category: {$altResource['category']}\n";
    echo "    Reason: {$altResource['reason']}\n";
    echo "\n";
}

// Test 5: Test booking service integration
echo "5. Testing booking service integration...\n";
$availability = $bookingService->checkAdvancedAvailability(
    $resource->id,
    $testStart,
    $testEnd,
    $user
);

echo "Booking Service Advanced Availability Results:\n";
echo "- Available: " . ($availability['available'] ? 'Yes' : 'No') . "\n";
echo "- Has conflicts: " . ($availability['has_conflicts'] ? 'Yes' : 'No') . "\n";
echo "- Resource status: {$availability['resource_status']}\n";
echo "- Resource capacity: {$availability['resource_capacity']}\n";

if (!empty($availability['conflict_types'])) {
    echo "- Conflict types: " . implode(', ', $availability['conflict_types']) . "\n";
}

if (!empty($availability['alternative_resources'])) {
    echo "- Alternative resources: " . count($availability['alternative_resources']) . "\n";
}

// Test 6: Create a maintenance issue to test maintenance conflict detection
echo "6. Testing maintenance conflict detection...\n";
$maintenanceIssue = ResourceIssue::create([
    'reported_by_user_id' => $user->id,
    'resource_id' => $resource->id,
    'subject' => 'Test Maintenance Issue',
    'description' => 'This is a test maintenance issue for conflict detection',
    'issue_type' => 'maintenance',
    'status' => 'in_progress'
]);

echo "Created test maintenance issue (ID: {$maintenanceIssue->id})\n";

// Test conflict detection again
$maintenanceConflicts = $advancedConflictService->detectAdvancedConflicts(
    $resource->id,
    $testStart,
    $testEnd,
    $user
);

echo "After creating maintenance issue:\n";
echo "- Has conflicts: " . ($maintenanceConflicts['has_conflicts'] ? 'Yes' : 'No') . "\n";
if (!empty($maintenanceConflicts['conflict_types'])) {
    echo "- Conflict types: " . implode(', ', $maintenanceConflicts['conflict_types']) . "\n";
}

// Clean up test data
$maintenanceIssue->delete();
echo "Cleaned up test maintenance issue\n";

echo "\n=== Test Complete ===\n"; 