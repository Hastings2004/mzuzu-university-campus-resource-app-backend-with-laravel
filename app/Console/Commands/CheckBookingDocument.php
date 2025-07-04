<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckBookingDocument extends Command
{
    protected $signature = 'booking:check-document {id}';
    protected $description = 'Check booking document path and file existence';

    public function handle()
    {
        $bookingId = $this->argument('id');
        $booking = Booking::find($bookingId);

        if (!$booking) {
            $this->error("Booking with ID {$bookingId} not found");
            return 1;
        }

        $this->info("Booking ID: {$booking->id}");
        $this->info("Document Path: " . ($booking->supporting_document_path ?: 'NULL'));

        if ($booking->supporting_document_path) {
            $exists = Storage::disk('public')->exists($booking->supporting_document_path);
            $this->info("File exists in storage: " . ($exists ? 'YES' : 'NO'));

            if (!$exists) {
                $this->warn("File not found! Available files in booking_documents:");
                $files = Storage::disk('public')->files('booking_documents');
                foreach ($files as $file) {
                    $this->line("- {$file}");
                }
            }
        } else {
            $this->warn("No document path set for this booking");
        }

        return 0;
    }
} 