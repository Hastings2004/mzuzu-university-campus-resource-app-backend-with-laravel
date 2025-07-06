<?php

require_once 'vendor/autoload.php';

use App\Models\Booking;
use App\Models\Resource;
use App\Services\ReportService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Specific Booking Debug Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test with a specific date range that might show the issue
    echo "Test 1: Testing with a specific date range\n";
    
    // Use a date range that includes some of the existing bookings
    $startDate = '2025-07-01';
    $endDate = '2025-07-31';
    
    $report = $reportService->getResourceUtilizationReport($startDate, $endDate);
    
    if ($report['success']) {
        echo "Report generated successfully\n";
        echo "Period: " . $report['period']['start_date'] . " to " . $report['period']['end_date'] . "\n";
        
        if (!empty($report['report'])) {
            foreach ($report['report'] as $resource) {
                echo "Resource: " . $resource['resource_name'] . "\n";
                echo "  - Total booked hours: " . $resource['total_booked_hours'] . "\n";
                echo "  - Available hours: " . $resource['total_available_hours_in_period'] . "\n";
                echo "  - Utilization: " . $resource['utilization_percentage'] . "%\n";
                echo "  - Booking count: " . $resource['booking_count'] . "\n\n";
                
                // Check if any resource has exactly 1 hour
                if ($resource['total_booked_hours'] == 1.0) {
                    echo "  *** FOUND RESOURCE WITH EXACTLY 1 HOUR ***\n";
                }
            }
        }
    } else {
        echo "Report failed: " . $report['message'] . "\n";
    }
    
    // Test 2: Check individual bookings that might result in 1 hour
    echo "\nTest 2: Checking individual bookings\n";
    
    $bookings = Booking::whereIn('status', ['approved', 'in_use', 'completed'])
        ->where('start_time', '>=', '2025-07-01 00:00:00')
        ->where('start_time', '<=', '2025-07-31 23:59:59')
        ->get();
    
    foreach ($bookings as $booking) {
        if ($booking->start_time && $booking->end_time) {
            $start = Carbon::parse($booking->start_time);
            $end = Carbon::parse($booking->end_time);
            $duration = $start->diffInMinutes($end);
            $durationHours = $duration / 60;
            
            echo "Booking ID: " . $booking->id . "\n";
            echo "  Start: " . $booking->start_time . "\n";
            echo "  End: " . $booking->end_time . "\n";
            echo "  Duration: " . $duration . " minutes (" . $durationHours . " hours)\n";
            echo "  Resource: " . $booking->resource_id . "\n";
            
            if ($durationHours == 1.0) {
                echo "  *** FOUND BOOKING WITH EXACTLY 1 HOUR ***\n";
            }
            echo "\n";
        }
    }
    
    // Test 3: Check if there's any rounding issue
    echo "\nTest 3: Checking for rounding issues\n";
    
    // Create a test booking that should be exactly 60 minutes
    $testStart = Carbon::parse('2025-07-15 10:00:00');
    $testEnd = Carbon::parse('2025-07-15 11:00:00');
    $testDuration = $testStart->diffInMinutes($testEnd);
    $testDurationHours = $testDuration / 60;
    
    echo "Test booking duration: " . $testDuration . " minutes\n";
    echo "Test booking hours: " . $testDurationHours . "\n";
    echo "Test booking hours (rounded): " . round($testDurationHours, 2) . "\n";
    
    // Check if there are any floating point precision issues
    if (abs($testDurationHours - 1.0) < 0.001) {
        echo "Duration is approximately 1 hour\n";
    }
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 