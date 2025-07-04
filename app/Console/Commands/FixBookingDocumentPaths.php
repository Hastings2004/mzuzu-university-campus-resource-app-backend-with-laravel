<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBookingDocumentPaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:booking-document-paths';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove leading /storage/ from supporting_document_path in bookings table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $affected = DB::table('bookings')
            ->where('supporting_document_path', 'like', '/storage/%')
            ->update([
                'supporting_document_path' => DB::raw("REPLACE(supporting_document_path, '/storage/', '')")
            ]);

        $this->info("Updated $affected booking(s) with corrected document paths.");
    }
} 