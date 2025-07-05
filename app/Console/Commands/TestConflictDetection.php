<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BookingService;
use App\Models\Resource;
use Carbon\Carbon;

class TestConflictDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:test-conflicts {--resource-id= : Specific resource ID to test} {--cleanup : Clean up old status values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test booking conflict detection and optionally clean up old status values';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookingService = new BookingService();

        // Clean up old status values if requested
        if ($this->option('cleanup')) {
            $this->info('Cleaning up old status values...');
            $cleanedCount = $bookingService->cleanupOldStatusValues();
            $this->info("Cleaned up {$cleanedCount} records");
            $this->newLine();
        }

        // Get resource to test
        $resourceId = $this->option('resource-id');
        if ($resourceId) {
            $resource = Resource::find($resourceId);
            if (!$resource) {
                $this->error("Resource with ID {$resourceId} not found");
                return 1;
            }
        } else {
            $resource = Resource::first();
            if (!$resource) {
                $this->error('No resources found in database');
                return 1;
            }
        }

        $this->info("Testing with resource: {$resource->name} (ID: {$resource->id})");

        // Create test time slots
        $now = Carbon::now();
        $testStart = $now->copy()->addHours(1);
        $testEnd = $testStart->copy()->addHours(2);

        $this->info("Testing time slot: {$testStart->format('Y-m-d H:i')} to {$testEnd->format('Y-m-d H:i')}");
        $this->newLine();

        // Debug conflict detection
        $debugInfo = $bookingService->debugConflictDetection($resource->id, $testStart, $testEnd);

        $this->info('Debug Results:');
        $this->line("- Resource: {$debugInfo['resource']['name']} (Capacity: {$debugInfo['resource']['capacity']})");
        $this->line("- All bookings count: {$debugInfo['all_bookings_count']}");
        $this->line("- Conflicting bookings count: {$debugInfo['conflicting_bookings_count']}");
        $this->line("- Manual conflicts count: {$debugInfo['manual_conflicts_count']}");
        $this->newLine();

        if ($debugInfo['conflicting_bookings_count'] > 0) {
            $this->warn('Conflicting bookings found:');
            foreach ($debugInfo['conflicting_bookings'] as $conflict) {
                $this->line("- Booking #{$conflict['id']}: {$conflict['start_time']} to {$conflict['end_time']} (Status: {$conflict['status']}, User: {$conflict['user']})");
            }
            $this->newLine();
        }

        // Test availability status
        $this->info('Testing availability status...');
        $availability = $bookingService->checkAvailabilityStatus($resource->id, $testStart, $testEnd);

        $this->info('Availability Results:');
        $this->line("- Available: " . ($availability['available'] ? 'Yes' : 'No'));
        $this->line("- Has Conflict: " . ($availability['hasConflict'] ? 'Yes' : 'No'));
        $this->line("- Message: {$availability['message']}");

        if (isset($availability['conflicts']) && count($availability['conflicts']) > 0) {
            $this->warn('- Conflicts:');
            foreach ($availability['conflicts'] as $conflict) {
                $this->line("  * Booking #{$conflict['id']}: {$conflict['start_time']} to {$conflict['end_time']} (Priority: {$conflict['priority_level']}, User: {$conflict['user']})");
            }
        }

        $this->newLine();
        $this->info('Test Complete');

        return 0;
    }
} 