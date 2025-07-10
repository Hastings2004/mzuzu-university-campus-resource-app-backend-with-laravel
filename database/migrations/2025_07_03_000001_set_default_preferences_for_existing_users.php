<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('users', 'preferences')) {
            DB::table('users')->whereNull('preferences')->update([
                'preferences' => json_encode(['categories' => []]),
            ]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'preferences')) {
            // Optionally, revert preferences to null if they were set to the default
            DB::table('users')->where('preferences', json_encode(['categories' => []]))->update([
                'preferences' => null,
            ]);
        }
    }
}; 