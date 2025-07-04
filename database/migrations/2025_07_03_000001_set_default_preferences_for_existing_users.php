<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        DB::table('users')->whereNull('preferences')->update([
            'preferences' => json_encode(['categories' => []]),
        ]);
    }

    public function down()
    {
        // Optionally, revert preferences to null if they were set to the default
        DB::table('users')->where('preferences', json_encode(['categories' => []]))->update([
            'preferences' => null,
        ]);
    }
}; 