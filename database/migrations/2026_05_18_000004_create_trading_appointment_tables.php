<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['host_user_id', 'start_at']);
            $table->index(['status', 'start_at']);
        });

        Schema::create('trading_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_slot_id')->nullable()->constrained('trading_appointment_slots')->nullOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_type')->default('slot');
            $table->string('subject');
            $table->text('request_note')->nullable();
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['host_user_id', 'scheduled_start_at']);
            $table->index(['status', 'scheduled_start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_appointments');
        Schema::dropIfExists('trading_appointment_slots');
    }
};
