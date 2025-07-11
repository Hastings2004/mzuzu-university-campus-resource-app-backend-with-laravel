<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        // 1. Add nullable uuid column
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // 2. Populate uuid for existing records
        $userModel = app(\App\Models\User::class);
        $userModel::whereNull('uuid')->get()->each(function ($user) {
            $user->uuid = Str::uuid();
            $user->save();
        });

        // 3. Make uuid unique and not nullable
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}; 