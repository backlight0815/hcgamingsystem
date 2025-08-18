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
        Schema::create('events', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->string('title')->nullable(); // Event Title
            $table->text('description')->nullable(); // Event Description
            $table->string('type')->nullable(); // Event Type: Online/Offline
            $table->dateTime('start_time')->nullable(); // Event Start Time
            $table->dateTime('end_time')->nullable(); // Event End Time
            $table->string('location')->nullable(); // Event Location (if offline)
            $table->string('platform')->nullable(); // Event Platform (for online events)
            $table->string('organizer_name')->nullable(); // Organizer Name
            $table->unsignedTinyInteger('status')->default(0)->nullable(); // Event Status: 0=Upcoming, 1=Ongoing, 2=Completed, 3=Cancelled
            $table->timestamps(); // Created at and Updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
