<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_release_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained('pm_releases')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['release_id', 'user_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_release_reminders');
    }
};
