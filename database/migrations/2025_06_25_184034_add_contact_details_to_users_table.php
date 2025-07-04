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
            $table->string('identity_number')->nullable()->after('email');
            $table->string('phone', 30)->nullable()->after('identity_number');
            $table->text('physical_address')->nullable()->after('phone');
            $table->text('post_address')->nullable()->after('physical_address');
            $table->string('district')->nullable()->after('post_address');
            $table->string('village')->nullable()->after('district');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
            $table->dropColumn('physical_address');
            $table->dropColumn('post_address');
        });
    }
};
