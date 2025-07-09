<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id');
            $table->string('key_code')->unique();
            $table->enum('status', ['available', 'checked_out', 'lost'])->default('available');
            $table->timestamps();
            
            $table->foreign('resource_id')->references('id')->on('resources')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('keys');
    }
}; 