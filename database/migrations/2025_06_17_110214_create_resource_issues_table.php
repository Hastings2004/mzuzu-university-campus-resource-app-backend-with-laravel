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
        Schema::create('resource_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('resource_id')->constrained('resources')->onDelete('cascade');
            $table->string('subject'); 
            $table->text('description')->nullable();
            $table->string('issue_type')->nullable(); // e.g., equipment malfunction, cleaning request, booking conflict
            $table->string('photo_path')->nullable(); 
            $table->enum('status', ['reported', 'in_progress', 'resolved', 'wont_fix'])->default('reported');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Admin/FM who resolved it
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_issues');
    }
};
