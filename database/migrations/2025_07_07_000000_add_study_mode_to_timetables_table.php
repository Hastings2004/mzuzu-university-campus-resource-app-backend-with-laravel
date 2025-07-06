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
        Schema::table('timetables', function (Blueprint $table) {
            // Add study mode fields
            $table->enum('study_mode', ['full-time', 'part-time'])->default('full-time')->after('type');
            $table->enum('delivery_mode', ['face-to-face', 'online', 'hybrid'])->default('face-to-face')->after('study_mode');
            $table->string('program_type')->nullable()->after('delivery_mode'); // e.g., 'undergraduate', 'postgraduate', 'diploma'
            
            // Add indexes for better performance
            $table->index(['study_mode', 'delivery_mode']);
            $table->index(['program_type', 'study_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropIndex(['study_mode', 'delivery_mode']);
            $table->dropIndex(['program_type', 'study_mode']);
            $table->dropColumn(['study_mode', 'delivery_mode', 'program_type']);
        });
    }
}; 