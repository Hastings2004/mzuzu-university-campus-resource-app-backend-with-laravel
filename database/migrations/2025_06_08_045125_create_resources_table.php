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
         Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description'); 
            $table->string('location'); 
            $table->string('google_location')->nullable();
            $table->integer('capacity');
            $table->enum('category', ['classrooms', 'ict_labs', 'science_labs', 'sports','board_rooms', 'auditoriums']);
            $table->integer('is_active')->default(1); 
            $table->string("status")->default("Available");
            $table->string('image')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
