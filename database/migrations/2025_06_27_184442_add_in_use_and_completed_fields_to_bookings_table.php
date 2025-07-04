<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('in_use_started_by')->nullable()->constrained('users')->after('approved_at');
            $table->timestamp('in_use_started_at')->nullable()->after('in_use_started_by');
            $table->foreignId('completed_by')->nullable()->constrained('users')->after('in_use_started_at');
            $table->timestamp('completed_at')->nullable()->after('completed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['in_use_started_by']);
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['in_use_started_by', 'in_use_started_at', 'completed_by', 'completed_at']);
        });
    }
};
