<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral')) {
            Schema::create('referral', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('upline_user_id')->nullable()->index();
                $table->string('referral_code')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referral_links')) {
            Schema::create('referral_links', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('role_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('referral_code')->unique();
                $table->timestamps();
                $table->unique(['role_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: these legacy tables can predate Laravel's
        // migration ledger and may contain production referral history.
    }
};
