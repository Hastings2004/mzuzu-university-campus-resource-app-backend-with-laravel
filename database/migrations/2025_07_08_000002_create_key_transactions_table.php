<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('key_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('key_id');
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('user_id'); // who took the key
            $table->unsignedBigInteger('custodian_id'); // who issued/received
            $table->timestamp('checked_out_at');
            $table->timestamp('expected_return_at');
            $table->timestamp('checked_in_at')->nullable();
            $table->enum('status', ['checked_out', 'returned', 'overdue'])->default('checked_out');
            $table->timestamps();

            $table->foreign('key_id')->references('id')->on('keys')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('custodian_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('key_transactions');
    }
}; 