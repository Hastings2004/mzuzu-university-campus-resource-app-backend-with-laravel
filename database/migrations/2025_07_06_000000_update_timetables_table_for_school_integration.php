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
            // Add missing fields that the model expects
            $table->string('subject')->nullable()->after('course_code');
            $table->string('teacher')->nullable()->after('subject');
            $table->unsignedBigInteger('room_id')->nullable()->after('teacher');
            $table->integer('day_of_week')->nullable()->after('room_id');
            $table->string('class_section')->nullable()->after('day_of_week');
            
            // Add foreign key constraint for room_id
            $table->foreign('room_id')->references('id')->on('resources')->onDelete('cascade');
            
            // Add indexes for better performance
            $table->index(['room_id', 'day_of_week']);
            $table->index(['day_of_week', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropIndex(['room_id', 'day_of_week']);
            $table->dropIndex(['day_of_week', 'start_time']);
            $table->dropColumn(['subject', 'teacher', 'room_id', 'day_of_week', 'class_section']);
        });
    }
}; 