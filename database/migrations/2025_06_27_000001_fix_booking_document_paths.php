<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::table('bookings')
            ->where('supporting_document_path', 'like', '/storage/%')
            ->update([
                'supporting_document_path' => DB::raw("REPLACE(supporting_document_path, '/storage/', '')")
            ]);
    }

    public function down()
    {
        // Optional: reverse if needed
        DB::table('bookings')
            ->whereRaw("LEFT(supporting_document_path, 17) != '/storage/booking_' AND supporting_document_path IS NOT NULL")
            ->update([
                'supporting_document_path' => DB::raw("CONCAT('/storage/', supporting_document_path)")
            ]);
    }
}; 