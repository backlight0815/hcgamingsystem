<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('module_access_settings', function (Blueprint $table): void {
            $table->boolean('show_login_entry')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('module_access_settings', function (Blueprint $table): void {
            $table->dropColumn('show_login_entry');
        });
    }
};
