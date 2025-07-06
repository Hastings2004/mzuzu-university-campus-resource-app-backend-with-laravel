<?php

require_once 'vendor/autoload.php';

use App\Models\Booking;
use App\Models\Resource;
use App\Services\ReportService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Booking Hours Debug Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test 1: Check all bookings
    echo "Test 1: Checking all bookings in the system\n";
    $allBookings = Booking::all();
    echo "Total bookings in system: " . $allBookings->count() . "\n";
    
    if ($allBookings->count() > 0) {
        echo "Sample bookings:\n";
        foreach ($allBookings->take(5) as $booking) {
            echo "  - Booking ID: " . $booking->id . "\n";
            echo "    Start: " . $booking->start_time . "\n";
            echo "    End: " . $booking->end_time . "\n";
            echo "    Status: " . $booking->status . "\n";
            echo "    Resource ID: " . $booking->resource_id . "\n";
            
            if ($booking->start_time && $booking->end_time) {
                $start = Carbon::parse($booking->start_time);
                $end = Carbon::parse($booking->end_time);
                $duration = $start->diffInMinutes($end);
                echo "    Duration: " . $duration . " minutes (" . round($duration/60, 2) . " hours)\n";
            }
            echo "\n";
        }
    }
    
    // Test 2: Check bookings with valid status
    echo "Test 2: Checking bookings with valid status\n";
    $validStatusBookings = Booking::whereIn('status', ['approved', 'in_use', 'completed'])->get();
    echo "Bookings with valid status: " . $validStatusBookings->count() . "\n";
    
    // Test 3: Check bookings in current month
    echo "Test 3: Checking bookings in current month\n";
    $startOfMonth = Carbon::now()->startOfMonth()->startOfDay();
    $endOfMonth = Carbon::now()->endOfMonth()->endOfDay();
    
    $monthBookings = Booking::whereIn('status', ['approved', 'in_use', 'completed'])
        ->where(function ($query) use ($startOfMonth, $endOfMonth) {
            $query->whereBetween('start_time', [$startOfMonth, $endOfMonth])
                  ->orWhereBetween('end_time', [$startOfMonth, $endOfMonth])
                  ->orWhere(function ($q) use ($startOfMonth, $endOfMonth) {
                      $q->where('start_time', '<=', $startOfMonth)
                        ->where('end_time', '>=', $endOfMonth);
                  });
        })->get();
    
    echo "Bookings in current month: " . $monthBookings->count() . "\n";
    
    if ($monthBookings->count() > 0) {
        $totalMinutes = 0;
        foreach ($monthBookings as $booking) {
            if ($booking->start_time && $booking->end_time) {
                $bookingStart = Carbon::parse($booking->start_time);
                $bookingEnd = Carbon::parse($booking->end_time);
                
                if ($bookingEnd->greaterThan($bookingStart)) {
                    $overlapStart = $bookingStart->max($startOfMonth);
                    $overlapEnd = $bookingEnd->min($endOfMonth);
                    
                    if ($overlapStart->lessThan($overlapEnd)) {
                        $overlapMinutes = abs($overlapEnd->diffInMinutes($overlapStart));
                        $totalMinutes += $overlapMinutes;
                        
                        echo "  - Booking " . $booking->id . ": " . $overlapMinutes . " minutes\n";
                    }
                }
            }
        }
        echo "Total booked minutes: " . $totalMinutes . "\n";
        echo "Total booked hours: " . round($totalMinutes / 60, 2) . "\n";
    }
    
    // Test 4: Run the actual report
    echo "\nTest 4: Running actual report service\n";
    $report = $reportService->getResourceUtilizationReport();
    
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
            }
        }
    } else {
        echo "Report failed: " . $report['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 