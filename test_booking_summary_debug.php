<?php

require_once 'vendor/autoload.php';

use App\Models\Booking;
use App\Models\Resource;
use App\Services\ReportService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Booking Summary Debug Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test 1: Basic booking summary report
    echo "Test 1: Basic booking summary report\n";
    $basicReport = $reportService->getBookingSummaryReport([]);
    
    if ($basicReport['success']) {
        echo "✓ Basic report generated successfully\n";
        echo "  - Total bookings: " . $basicReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Total booked hours: " . $basicReport['report']['metrics']['total_booked_hours'] . "\n";
        echo "  - Average duration: " . $basicReport['report']['metrics']['average_booking_duration'] . " hours\n";
        echo "  - Unique users: " . $basicReport['report']['metrics']['unique_users'] . "\n";
        echo "  - Period: " . $basicReport['period']['start_date'] . " to " . $basicReport['period']['end_date'] . "\n";
        
        // Check if total booked hours is exactly 1
        if ($basicReport['report']['metrics']['total_booked_hours'] == 1.0) {
            echo "  *** FOUND TOTAL BOOKED HOURS = 1 ***\n";
        }
    } else {
        echo "✗ Basic report failed: " . $basicReport['message'] . "\n";
    }
    
    // Test 2: Booking summary with specific date range
    echo "\nTest 2: Booking summary with specific date range\n";
    $dateReport = $reportService->getBookingSummaryReport([
        'start_date' => '2025-07-01',
        'end_date' => '2025-07-31'
    ]);
    
    if ($dateReport['success']) {
        echo "✓ Date filtered report generated successfully\n";
        echo "  - Total bookings: " . $dateReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Total booked hours: " . $dateReport['report']['metrics']['total_booked_hours'] . "\n";
        echo "  - Average duration: " . $dateReport['report']['metrics']['average_booking_duration'] . " hours\n";
        
        // Check if total booked hours is exactly 1
        if ($dateReport['report']['metrics']['total_booked_hours'] == 1.0) {
            echo "  *** FOUND TOTAL BOOKED HOURS = 1 ***\n";
        }
        
        // Show breakdown by resource
        if (isset($dateReport['report']['breakdowns']['by_resource'])) {
            echo "  - Resource breakdown:\n";
            foreach ($dateReport['report']['breakdowns']['by_resource'] as $resource) {
                echo "    * " . $resource['resource_name'] . ": " . $resource['total_booked_hours'] . " hours\n";
                if ($resource['total_booked_hours'] == 1.0) {
                    echo "      *** FOUND RESOURCE WITH EXACTLY 1 HOUR ***\n";
                }
            }
        }
    } else {
        echo "✗ Date filtered report failed: " . $dateReport['message'] . "\n";
    }
    
    // Test 3: Check individual bookings in the summary
    echo "\nTest 3: Checking individual bookings in summary\n";
    if ($dateReport['success'] && isset($dateReport['report']['bookings'])) {
        foreach ($dateReport['report']['bookings'] as $booking) {
            echo "Booking: " . $booking['booking_reference'] . "\n";
            echo "  Resource: " . $booking['resource'] . "\n";
            echo "  Start: " . $booking['start_time'] . "\n";
            echo "  End: " . $booking['end_time'] . "\n";
            echo "  Status: " . $booking['status'] . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 