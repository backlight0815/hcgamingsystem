<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_unanswered_questions', function (Blueprint $table): void {
            $table->string('ai_status', 20)->default('unanswered');
        });
    }

    public function down(): void
    {
        Schema::table('ai_unanswered_questions', function (Blueprint $table): void {
            $table->dropColumn('ai_status');
        });
    }
};
