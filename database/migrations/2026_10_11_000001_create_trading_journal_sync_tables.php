<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_journal_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('firebase_uid', 191)->unique();
            $table->timestamps();
        });

        Schema::create('trading_journal_sync_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('journal_id')->nullable()->unique();
            $table->string('mobile_id', 191)->nullable();
            $table->unsignedBigInteger('revision')->default(1);
            $table->json('mobile_metadata')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'mobile_id']);
            $table->index(['user_id', 'deleted_at']);
        });

        Schema::create('trading_journal_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('sync_record_id');
            $table->string('operation', 16);
            $table->string('source_platform', 16);
            $table->string('status', 16)->default('applied');
            $table->unsignedBigInteger('revision');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_journal_sync_events');
        Schema::dropIfExists('trading_journal_sync_records');
        Schema::dropIfExists('trading_journal_identities');
    }
};
