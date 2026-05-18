<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trader_onboarding_applications')) {
            return;
        }

        Schema::create('trader_onboarding_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->boolean('is_client')->default(false);
            $table->boolean('has_deposit')->default(false);
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->string('discord_username');
            $table->string('broker_uid');
            $table->string('broker_email');
            $table->string('document_path')->nullable();
            $table->text('trader_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_note')->nullable();
            $table->boolean('allow_resubmission')->default(true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trader_onboarding_applications');
    }
};
