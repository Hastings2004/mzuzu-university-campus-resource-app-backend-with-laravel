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
        Schema::table('resources', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // 2. Populate uuid for existing records
        $resourceModel = app(\App\Models\Resource::class);
        $resourceModel::whereNull('uuid')->get()->each(function ($resource) {
            $resource->uuid = Str::uuid();
            $resource->save();
        });

        // 3. Make uuid unique and not nullable
        Schema::table('resources', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable(false)->change();
        });
    }

    public function down()
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
}; 