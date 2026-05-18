<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->json('target_roles')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('general');
            $table->string('action_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'published_at']);
            $table->index(['type', 'published_at']);
        });

        Schema::create('app_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['app_notification_id', 'user_id'], 'notification_user_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notification_reads');
        Schema::dropIfExists('app_notifications');
    }
};
