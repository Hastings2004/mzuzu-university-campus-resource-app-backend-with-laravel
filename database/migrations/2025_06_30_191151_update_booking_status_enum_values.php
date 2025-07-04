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
        // First, update any existing 'in user' status to 'in_use'
        DB::table('bookings')->where('status', 'in user')->update(['status' => 'in_use']);
        
        // Now modify the enum to include all the correct values
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed', 'in_use', 'expired', 'preempted') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to the original enum values
        DB::statement("ALTER TABLE bookings MODIFY COLUMN status ENUM('approved', 'pending', 'cancelled', 'expired', 'rejected', 'in user', 'completed') DEFAULT 'pending'");
        
        // Update any 'in_use' back to 'in user'
        DB::table('bookings')->where('status', 'in_use')->update(['status' => 'in user']);
    }
};
