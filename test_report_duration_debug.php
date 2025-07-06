<?php

require_once 'vendor/autoload.php';

use App\Services\ReportService;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Report Duration Debug Test ===\n\n";

try {
    $reportService = new ReportService();
    
    // Test the default date range calculation
    echo "Testing default date range calculation:\n";
    
    $start = Carbon::now()->startOfMonth()->startOfDay();
    $end = Carbon::now()->endOfMonth()->endOfDay();
    
    echo "Start date: " . $start->toDateTimeString() . "\n";
    echo "End date: " . $end->toDateTimeString() . "\n";
    
    $durationMinutes = abs($end->diffInMinutes($start, false));
    $durationHours = $durationMinutes / 60;
    
    echo "Duration in minutes: " . $durationMinutes . "\n";
    echo "Duration in hours: " . $durationHours . "\n";
    
    // Test with a specific date range
    echo "\nTesting with specific date range (last 30 days):\n";
    
    $start2 = Carbon::now()->subDays(30)->startOfDay();
    $end2 = Carbon::now()->endOfDay();
    
    echo "Start date: " . $start2->toDateTimeString() . "\n";
    echo "End date: " . $end2->toDateTimeString() . "\n";
    
    $durationMinutes2 = abs($end2->diffInMinutes($start2, false));
    $durationHours2 = $durationMinutes2 / 60;
    
    echo "Duration in minutes: " . $durationMinutes2 . "\n";
    echo "Duration in hours: " . $durationHours2 . "\n";
    
    // Test the actual report
    echo "\nTesting actual report service:\n";
    $report = $reportService->getResourceUtilizationReport();
    
    if ($report['success']) {
        echo "Report generated successfully\n";
        echo "Period: " . $report['period']['start_date'] . " to " . $report['period']['end_date'] . "\n";
        
        if (!empty($report['report'])) {
            $firstResource = $report['report'][0];
            echo "First resource total available hours: " . $firstResource['total_available_hours_in_period'] . "\n";
        }
    } else {
        echo "Report failed: " . $report['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 