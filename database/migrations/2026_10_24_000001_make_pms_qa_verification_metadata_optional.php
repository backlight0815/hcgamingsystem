<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            Schema::create('pm_qa_verifications_optional', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
                $table->foreignId('tested_by')->constrained('users');
                $table->string('result', 16);
                $table->string('build', 100)->nullable();
                $table->string('environment', 100)->nullable();
                $table->text('remarks');
                $table->string('regression_result', 20)->nullable();
                $table->timestamp('tested_at');
                $table->timestamps();
            });
            DB::statement('INSERT INTO pm_qa_verifications_optional (id, issue_id, tested_by, result, build, environment, remarks, regression_result, tested_at, created_at, updated_at) SELECT id, issue_id, tested_by, result, build, environment, remarks, regression_result, tested_at, created_at, updated_at FROM pm_qa_verifications');
            Schema::drop('pm_qa_verifications');
            Schema::rename('pm_qa_verifications_optional', 'pm_qa_verifications');
            Schema::table('pm_qa_verifications', fn (Blueprint $table) => $table->index(['issue_id', 'tested_at']));
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE pm_qa_verifications MODIFY build VARCHAR(100) NULL, MODIFY environment VARCHAR(100) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE pm_qa_verifications ALTER COLUMN build DROP NOT NULL, ALTER COLUMN environment DROP NOT NULL');
        } else {
            throw new RuntimeException('Unsupported database driver for PMS QA metadata migration: '.$driver);
        }
    }

    public function down(): void
    {
        // Historical rows may contain null values; do not reintroduce a constraint.
    }
};
