<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('module_access_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('module_key', 32)->unique();
            $table->boolean('public_visible');
            $table->string('registration_policy', 24);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('module_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_key', 32);
            $table->string('status', 24)->default('active');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'module_key']);
            $table->index(['module_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_memberships');
        Schema::dropIfExists('module_access_settings');
    }
};
