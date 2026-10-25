<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->foreignId('project_manager_id')->nullable()->after('project_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['project_manager_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropIndex(['project_manager_id', 'status']);
            $table->dropConstrainedForeignId('project_manager_id');
        });
    }
};
