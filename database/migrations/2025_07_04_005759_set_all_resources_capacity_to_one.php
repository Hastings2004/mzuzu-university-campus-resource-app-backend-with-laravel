<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set realistic capacities based on resource categories
        // This allows multiple bookings up to actual capacity while preventing overlaps beyond capacity
        
        // ICT Labs - typically 15-30 computers
        DB::table('resources')
            ->where('category', 'computer_lab')
            ->orWhere('name', 'like', '%ICT%')
            ->orWhere('name', 'like', '%LAB%')
            ->update(['capacity' => 25]);
        
        // Classrooms/Lecture Theaters - typically 20-50 students
        DB::table('resources')
            ->where('category', 'classroom')
            ->orWhere('name', 'like', '%LT%')
            ->orWhere('name', 'like', '%Lecture%')
            ->update(['capacity' => 40]);
        
        // Study Rooms - typically 5-15 students
        DB::table('resources')
            ->where('category', 'study_room')
            ->orWhere('name', 'like', '%Study%')
            ->update(['capacity' => 10]);
        
        // Meeting Rooms - typically 8-20 people
        DB::table('resources')
            ->where('category', 'meeting_room')
            ->orWhere('name', 'like', '%Meeting%')
            ->update(['capacity' => 15]);
        
        // Conference Rooms - typically 20-100 people
        DB::table('resources')
            ->where('category', 'conference_room')
            ->orWhere('name', 'like', '%Conference%')
            ->update(['capacity' => 50]);
        
        // Laboratories - typically 15-25 students
        DB::table('resources')
            ->where('category', 'laboratory')
            ->orWhere('name', 'like', '%Lab%')
            ->update(['capacity' => 20]);
        
        // Offices - typically 1-5 people
        DB::table('resources')
            ->where('category', 'office')
            ->orWhere('name', 'like', '%Office%')
            ->update(['capacity' => 3]);
        
        // Set default capacity for any remaining resources
        DB::table('resources')
            ->where('capacity', 1)
            ->update(['capacity' => 15]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all capacities back to 1 (single booking only)
        DB::table('resources')->update(['capacity' => 1]);
    }
};
