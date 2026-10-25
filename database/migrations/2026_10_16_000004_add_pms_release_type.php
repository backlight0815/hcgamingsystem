<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_releases', function (Blueprint $table): void {
            $table->string('release_type', 20)->default('standard')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pm_releases', function (Blueprint $table): void {
            $table->dropColumn('release_type');
        });
    }
};
