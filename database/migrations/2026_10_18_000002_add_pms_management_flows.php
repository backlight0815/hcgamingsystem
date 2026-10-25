<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->uuid('creation_token')->nullable()->unique();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('pm_projects', fn (Blueprint $table) => $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete());
        Schema::table('pm_cycles', fn (Blueprint $table) => $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete());
        Schema::table('pm_releases', fn (Blueprint $table) => $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete());

        Schema::create('pm_ticket_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('status', 24)->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['issue_id', 'revision']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('pms_invitations', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('token_ciphertext');
            $table->string('email')->nullable()->index();
            $table->string('role', 32);
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->cascadeOnDelete();
            $table->string('referral_code', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('pms_invitation_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->constrained('pms_invitations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['invitation_id', 'user_id']);
        });
        Schema::table('pms_registrations', function (Blueprint $table): void {
            $table->foreignId('invitation_id')->nullable()->constrained('pms_invitations')->nullOnDelete();
            $table->string('referral_code', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pms_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('invitation_id');
            $table->dropColumn('referral_code');
        });
        Schema::dropIfExists('pms_invitation_acceptances');
        Schema::dropIfExists('pms_invitations');
        Schema::dropIfExists('pm_ticket_reviews');
        Schema::table('pm_releases', fn (Blueprint $table) => $table->dropConstrainedForeignId('deleted_by'));
        Schema::table('pm_cycles', fn (Blueprint $table) => $table->dropConstrainedForeignId('deleted_by'));
        Schema::table('pm_projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('deleted_by'));
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropUnique(['creation_token']);
            $table->dropColumn('creation_token');
        });
    }
};
