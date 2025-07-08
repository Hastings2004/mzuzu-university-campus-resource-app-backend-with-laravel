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
        // 'issue_type' already exists in resource_issues table, so nothing to do here.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_issues', function (Blueprint $table) {
            $table->dropColumn('issue_type');
        });
    }
}; 