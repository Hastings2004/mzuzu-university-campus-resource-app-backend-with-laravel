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
        Schema::table('users', function (Blueprint $table) {
            // 2FA fields
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_secret')->nullable();
            $table->json('two_factor_backup_codes')->nullable();
            
            // Privacy settings
            $table->enum('profile_visibility', ['public', 'private', 'friends'])->default('public');
            $table->boolean('data_sharing')->default(true);
            $table->boolean('email_notifications')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_backup_codes',
                'profile_visibility',
                'data_sharing',
                'email_notifications'
            ]);
        });
    }
};
