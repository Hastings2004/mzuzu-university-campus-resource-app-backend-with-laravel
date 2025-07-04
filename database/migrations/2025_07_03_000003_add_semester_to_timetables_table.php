<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->string('semester')->nullable()->after('course_code');
        });
    }
    public function down()
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropColumn('semester');
        });
    }
}; 