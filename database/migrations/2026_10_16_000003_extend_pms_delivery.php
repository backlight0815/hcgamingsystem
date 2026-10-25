<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('pm_ticket_sequences', function (Blueprint $table): void {
            $table->string('prefix', 3)->primary();
            $table->unsignedInteger('next_number')->default(1);
        });
        DB::table('pm_ticket_sequences')->insert(collect(['REQ', 'ENH', 'PRD', 'QAT', 'TSK'])->map(fn ($prefix) => ['prefix' => $prefix, 'next_number' => 1])->all());

        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->unsignedInteger('next_release_number')->default(1);
        });
        Schema::create('pm_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('pm_cycles')->nullOnDelete();
            $table->string('release_key', 48)->unique();
            $table->string('name');
            $table->string('version', 100);
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('Planned')->index();
            $table->date('planned_start_date');
            $table->date('planned_deploy_date');
            $table->date('freeze_date')->nullable();
            $table->timestamp('actual_deployed_at')->nullable();
            $table->string('deployed_build')->nullable();
            $table->longText('release_notes')->nullable();
            $table->longText('known_issues')->nullable();
            $table->longText('risk_acceptance')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'version']);
            $table->index(['project_id', 'status', 'planned_deploy_date']);
        });
        Schema::create('pm_release_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained('pm_releases')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['release_id', 'created_at']);
        });

        $this->setProjectNullable(true);
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->foreignId('release_id')->nullable()->constrained('pm_releases')->nullOnDelete();
            $table->string('defect_type', 40)->nullable();
            $table->string('testing_environment', 100)->nullable();
            $table->string('affected_platform', 100)->nullable();
            $table->string('browser_device', 160)->nullable();
            $table->string('build_number', 100)->nullable();
            $table->string('reproducibility', 50)->nullable();
            $table->longText('preconditions')->nullable();
            $table->string('test_case_references', 1000)->nullable();
            $table->json('tags')->nullable();
            $table->index(['classification_key', 'status', 'priority']);
            $table->index(['release_id', 'status']);
        });

        Schema::create('pm_ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->longText('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['issue_id', 'created_at']);
        });
        Schema::create('pm_comment_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('pm_ticket_comments')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->longText('body_before');
            $table->string('event', 16);
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('pm_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained('pm_ticket_comments')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('extension', 12);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['issue_id', 'created_at']);
        });
        Schema::create('pm_qa_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('tested_by')->constrained('users');
            $table->string('result', 16);
            $table->string('build', 100);
            $table->string('environment', 100);
            $table->text('remarks');
            $table->string('regression_result', 20)->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();
            $table->index(['issue_id', 'tested_at']);
        });
        Schema::create('pm_ticket_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('target_issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->string('relation', 32);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['source_issue_id', 'target_issue_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ticket_links');
        Schema::dropIfExists('pm_qa_verifications');
        Schema::dropIfExists('pm_ticket_attachments');
        Schema::dropIfExists('pm_comment_revisions');
        Schema::dropIfExists('pm_ticket_comments');
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('release_id');
            $table->dropIndex(['classification_key', 'status', 'priority']);
            $table->dropIndex(['release_id', 'status']);
            $table->dropColumn(['defect_type', 'testing_environment', 'affected_platform', 'browser_device', 'build_number', 'reproducibility', 'preconditions', 'test_case_references', 'tags']);
        });
        if (DB::table('pm_work_items')->whereNull('project_id')->exists()) {
            throw new RuntimeException('Assign or remove unassigned QAT tickets before rolling back this migration.');
        }
        $this->setProjectNullable(false);
        Schema::dropIfExists('pm_release_history');
        Schema::dropIfExists('pm_releases');
        Schema::table('pm_projects', fn (Blueprint $table) => $table->dropColumn('next_release_number'));
        Schema::dropIfExists('pm_ticket_sequences');
    }

    private function setProjectNullable(bool $nullable): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE pm_work_items MODIFY project_id BIGINT UNSIGNED '.($nullable ? 'NULL' : 'NOT NULL'));

            return;
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE pm_work_items ALTER COLUMN project_id '.($nullable ? 'DROP' : 'SET').' NOT NULL');

            return;
        }
        if ($driver !== 'sqlite') {
            throw new RuntimeException("Unsupported PMS migration driver: {$driver}");
        }

        $tableSql = DB::table('sqlite_master')->where('type', 'table')->where('name', 'pm_work_items')->value('sql');
        $indexes = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', 'pm_work_items')->whereNotNull('sql')->pluck('sql');
        $replacement = $nullable ? '$1' : '$1 not null';
        $newSql = preg_replace('/(["`]?project_id["`]?\s+integer)(?:\s+not null)?/i', $replacement, $tableSql, 1, $count);
        if ($count !== 1) {
            throw new RuntimeException('Could not safely rebuild the PMS ticket project column.');
        }
        $newSql = preg_replace('/^(create\s+table\s+)["`]?pm_work_items["`]?/i', '$1"pm_work_items_rebuild"', $newSql, 1, $tableCount);
        if ($tableCount !== 1) {
            throw new RuntimeException('Could not safely rebuild the PMS ticket table.');
        }
        $columns = collect(DB::select('PRAGMA table_info("pm_work_items")'))->pluck('name')->map(fn ($column) => '"'.str_replace('"', '""', $column).'"')->implode(', ');
        DB::statement('PRAGMA foreign_keys = OFF');
        try {
            DB::statement($newSql);
            DB::statement("INSERT INTO pm_work_items_rebuild ({$columns}) SELECT {$columns} FROM pm_work_items");
            DB::statement('DROP TABLE pm_work_items');
            DB::statement('ALTER TABLE pm_work_items_rebuild RENAME TO pm_work_items');
            foreach ($indexes as $sql) {
                DB::statement($sql);
            }
        } finally {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
