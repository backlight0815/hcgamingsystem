<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCapitalsTable extends Migration
{
    public function up(): void
    {
        Schema::create('capitals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // optional, if you're tracking per user
            $table->date('deposit_date');
            $table->decimal('amount', 12, 2); // store deposit amount
            $table->string('notes')->nullable(); // optional notes
            $table->timestamps();

            // Optional: Add foreign key if users table exists
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitals');
    }
}
