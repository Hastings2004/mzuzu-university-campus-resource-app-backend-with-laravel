<?php

require_once 'vendor/autoload.php';

use App\Models\Booking;
use App\Models\Resource;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Canceled Bookings Report Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test 1: Basic report without filters
    echo "Test 1: Basic canceled bookings report without filters\n";
    $basicReport = $reportService->getCanceledBookingsReport([]);
    
    if ($basicReport['success']) {
        echo "✓ Basic canceled bookings report generated successfully\n";
        echo "  - Total cancelations: " . $basicReport['report']['metrics']['total_cancelations'] . "\n";
        echo "  - Cancellation percentage: " . $basicReport['report']['metrics']['cancellation_percentage'] . "%\n";
        echo "  - Total refund amount: $" . $basicReport['report']['metrics']['total_refund_amount'] . "\n";
        echo "  - Average cancellation time: " . $basicReport['report']['metrics']['average_cancellation_time_hours'] . " hours\n";
        echo "  - Unique users canceled: " . $basicReport['report']['metrics']['unique_users_canceled'] . "\n";
        echo "  - Unique resources canceled: " . $basicReport['report']['metrics']['unique_resources_canceled'] . "\n";
        echo "  - Period: " . $basicReport['period']['start_date'] . " to " . $basicReport['period']['end_date'] . "\n";
    } else {
        echo "✗ Basic canceled bookings report failed: " . $basicReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 2: Report with date range filter
    echo "Test 2: Canceled bookings report with date range filter\n";
    $dateFilterReport = $reportService->getCanceledBookingsReport([
        'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
        'end_date' => Carbon::now()->format('Y-m-d')
    ]);
    
    if ($dateFilterReport['success']) {
        echo "✓ Date filtered canceled bookings report generated successfully\n";
        echo "  - Total cancelations: " . $dateFilterReport['report']['metrics']['total_cancelations'] . "\n";
        echo "  - Filters applied: " . json_encode($dateFilterReport['filters_applied']) . "\n";
    } else {
        echo "✗ Date filtered canceled bookings report failed: " . $dateFilterReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 3: Report with resource type filter
    echo "Test 3: Canceled bookings report with resource type filter\n";
    $resourceTypeReport = $reportService->getCanceledBookingsReport([
        'resource_type' => 'classrooms'
    ]);
    
    if ($resourceTypeReport['success']) {
        echo "✓ Resource type filtered canceled bookings report generated successfully\n";
        echo "  - Total cancelations: " . $resourceTypeReport['report']['metrics']['total_cancelations'] . "\n";
        echo "  - Filters applied: " . json_encode($resourceTypeReport['filters_applied']) . "\n";
    } else {
        echo "✗ Resource type filtered canceled bookings report failed: " . $resourceTypeReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 4: Report with user type filter
    echo "Test 4: Canceled bookings report with user type filter\n";
    $userTypeReport = $reportService->getCanceledBookingsReport([
        'user_type' => 'student'
    ]);
    
    if ($userTypeReport['success']) {
        echo "✓ User type filtered canceled bookings report generated successfully\n";
        echo "  - Total cancelations: " . $userTypeReport['report']['metrics']['total_cancelations'] . "\n";
        echo "  - Filters applied: " . json_encode($userTypeReport['filters_applied']) . "\n";
    } else {
        echo "✗ User type filtered canceled bookings report failed: " . $userTypeReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 5: Complex filter combination
    echo "Test 5: Canceled bookings report with complex filters\n";
    $complexReport = $reportService->getCanceledBookingsReport([
        'start_date' => Carbon::now()->subDays(60)->format('Y-m-d'),
        'end_date' => Carbon::now()->format('Y-m-d'),
        'user_type' => 'staff',
        'resource_type' => 'ict_labs'
    ]);
    
    if ($complexReport['success']) {
        echo "✓ Complex filtered canceled bookings report generated successfully\n";
        echo "  - Total cancelations: " . $complexReport['report']['metrics']['total_cancelations'] . "\n";
        echo "  - Cancellation percentage: " . $complexReport['report']['metrics']['cancellation_percentage'] . "%\n";
        echo "  - Filters applied: " . json_encode($complexReport['filters_applied']) . "\n";
        
        // Display breakdowns if available
        if (!empty($complexReport['report']['breakdowns']['by_resource'])) {
            echo "  - Resource breakdown: " . count($complexReport['report']['breakdowns']['by_resource']) . " resources\n";
        }
        
        if (!empty($complexReport['report']['breakdowns']['by_user_type'])) {
            echo "  - User type breakdown: " . count($complexReport['report']['breakdowns']['by_user_type']) . " user types\n";
        }
        
        if (!empty($complexReport['report']['breakdowns']['by_cancellation_reason'])) {
            echo "  - Cancellation reason breakdown: " . count($complexReport['report']['breakdowns']['by_cancellation_reason']) . " reasons\n";
        }
    } else {
        echo "✗ Complex filtered canceled bookings report failed: " . $complexReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 6: Check detailed canceled bookings
    echo "Test 6: Check detailed canceled bookings data\n";
    if ($complexReport['success'] && !empty($complexReport['report']['canceled_bookings'])) {
        echo "✓ Detailed canceled bookings data available\n";
        $firstBooking = $complexReport['report']['canceled_bookings'][0];
        echo "  - Sample booking ID: " . $firstBooking['id'] . "\n";
        echo "  - Sample booking reference: " . $firstBooking['booking_reference'] . "\n";
        echo "  - Sample user: " . $firstBooking['user']['name'] . "\n";
        echo "  - Sample resource: " . $firstBooking['resource']['name'] . "\n";
        echo "  - Sample cancellation reason: " . ($firstBooking['cancellation_details']['cancellation_reason'] ?? 'No reason provided') . "\n";
        echo "  - Total detailed bookings: " . count($complexReport['report']['canceled_bookings']) . "\n";
    } else {
        echo "✗ No detailed canceled bookings data available\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "All tests completed. Check the output above for any errors.\n";
    
} catch (Exception $e) {
    echo "✗ Test failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 