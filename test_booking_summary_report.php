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

echo "=== Booking Summary Report Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test 1: Basic report without filters
    echo "Test 1: Basic report without filters\n";
    $basicReport = $reportService->getBookingSummaryReport([]);
    
    if ($basicReport['success']) {
        echo "✓ Basic report generated successfully\n";
        echo "  - Total bookings: " . $basicReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Total booked hours: " . $basicReport['report']['metrics']['total_booked_hours'] . "\n";
        echo "  - Average duration: " . $basicReport['report']['metrics']['average_booking_duration'] . " hours\n";
        echo "  - Unique users: " . $basicReport['report']['metrics']['unique_users'] . "\n";
        echo "  - Period: " . $basicReport['period']['start_date'] . " to " . $basicReport['period']['end_date'] . "\n";
    } else {
        echo "✗ Basic report failed: " . $basicReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 2: Report with date range filter
    echo "Test 2: Report with date range filter\n";
    $dateFilterReport = $reportService->getBookingSummaryReport([
        'start_date' => Carbon::now()->subDays(30)->format('Y-m-d'),
        'end_date' => Carbon::now()->format('Y-m-d')
    ]);
    
    if ($dateFilterReport['success']) {
        echo "✓ Date filtered report generated successfully\n";
        echo "  - Total bookings: " . $dateFilterReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Filters applied: " . json_encode($dateFilterReport['filters_applied']) . "\n";
    } else {
        echo "✗ Date filtered report failed: " . $dateFilterReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 3: Report with user type filter
    echo "Test 3: Report with user type filter\n";
    $userTypeReport = $reportService->getBookingSummaryReport([
        'user_type' => 'staff'
    ]);
    
    if ($userTypeReport['success']) {
        echo "✓ User type filtered report generated successfully\n";
        echo "  - Total bookings: " . $userTypeReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Filters applied: " . json_encode($userTypeReport['filters_applied']) . "\n";
        
        // Check breakdowns
        if (isset($userTypeReport['report']['breakdowns']['by_user_type'])) {
            echo "  - User type breakdowns: " . count($userTypeReport['report']['breakdowns']['by_user_type']) . " types\n";
        }
        if (isset($userTypeReport['report']['breakdowns']['by_resource'])) {
            echo "  - Resource breakdowns: " . count($userTypeReport['report']['breakdowns']['by_resource']) . " resources\n";
        }
        if (isset($userTypeReport['report']['breakdowns']['by_day'])) {
            echo "  - Daily breakdowns: " . count($userTypeReport['report']['breakdowns']['by_day']) . " days\n";
        }
    } else {
        echo "✗ User type filtered report failed: " . $userTypeReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 4: Report with resource type filter
    echo "Test 4: Report with resource type filter\n";
    $resourceTypeReport = $reportService->getBookingSummaryReport([
        'resource_type' => 'classrooms'
    ]);
    
    if ($resourceTypeReport['success']) {
        echo "✓ Resource type filtered report generated successfully\n";
        echo "  - Total bookings: " . $resourceTypeReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Filters applied: " . json_encode($resourceTypeReport['filters_applied']) . "\n";
    } else {
        echo "✗ Resource type filtered report failed: " . $resourceTypeReport['message'] . "\n";
    }
    
    echo "\n";
    
    // Test 5: Complex filter combination
    echo "Test 5: Complex filter combination\n";
    $complexReport = $reportService->getBookingSummaryReport([
        'start_date' => Carbon::now()->subDays(7)->format('Y-m-d'),
        'end_date' => Carbon::now()->format('Y-m-d'),
        'user_type' => 'student',
        'resource_type' => 'ict_labs'
    ]);
    
    if ($complexReport['success']) {
        echo "✓ Complex filtered report generated successfully\n";
        echo "  - Total bookings: " . $complexReport['report']['metrics']['total_bookings'] . "\n";
        echo "  - Filters applied: " . json_encode($complexReport['filters_applied']) . "\n";
    } else {
        echo "✗ Complex filtered report failed: " . $complexReport['message'] . "\n";
    }
    
    echo "\n=== Test Summary ===\n";
    echo "All tests completed. Check the output above for results.\n";
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 