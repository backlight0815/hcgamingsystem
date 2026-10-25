<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_management_users', fn (Blueprint $table) => $table->string('phone', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('project_management_users', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
