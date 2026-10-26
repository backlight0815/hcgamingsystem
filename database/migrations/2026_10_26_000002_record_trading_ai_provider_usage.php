<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('provider_model_version', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->dropColumn(['input_tokens', 'output_tokens', 'provider_model_version']);
        });
    }
};
