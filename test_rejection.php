<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Booking;
use App\Services\BookingApprovalService;

try {
    // Get a pending booking
    $pendingBooking = Booking::where('status', 'pending')->first();
    
    if (!$pendingBooking) {
        echo "No pending bookings found. Creating a test booking...\n";
        
        // Create a test booking
        $pendingBooking = Booking::create([
            'booking_reference' => 'TEST-' . time(),
            'user_id' => 1, // Assuming user ID 1 exists
            'resource_id' => 1, // Assuming resource ID 1 exists
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'pending',
            'purpose' => 'Test booking for rejection',
            'booking_type' => 'other',
            'priority' => 1,
        ]);
    }
    
    echo "Testing rejection for booking ID: " . $pendingBooking->id . "\n";
    
    // Test the rejection
    $bookingApprovalService = new BookingApprovalService();
    $rejectedBooking = $bookingApprovalService->rejectBooking(
        $pendingBooking->id,
        1, // admin ID
        'Test rejection reason - This is a test rejection',
        'Test admin notes'
    );
    
    echo "Booking rejected successfully!\n";
    echo "Rejection reason: " . $rejectedBooking->rejection_reason . "\n";
    echo "Rejected by: " . $rejectedBooking->rejected_by . "\n";
    echo "Rejected at: " . $rejectedBooking->rejected_at . "\n";
    echo "Admin notes: " . $rejectedBooking->admin_notes . "\n";
    echo "Status: " . $rejectedBooking->status . "\n";
    
    // Verify in database
    $freshBooking = Booking::find($pendingBooking->id);
    echo "\nVerifying database save:\n";
    echo "Rejection reason in DB: " . $freshBooking->rejection_reason . "\n";
    echo "Rejected by in DB: " . $freshBooking->rejected_by . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 