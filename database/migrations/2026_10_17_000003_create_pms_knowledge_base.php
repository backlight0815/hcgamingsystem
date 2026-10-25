<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_wiki_spaces', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 12)->nullable();
            $table->string('visibility', 16)->default('workspace');
            $table->foreignId('owner_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('pm_wiki_space_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->constrained('pm_wiki_spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->default('editor');
            $table->timestamps();
            $table->unique(['space_id', 'user_id']);
        });
        Schema::create('pm_wiki_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->constrained('pm_wiki_spaces')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('pm_wiki_pages')->nullOnDelete();
            $table->string('slug', 180);
            $table->string('title');
            $table->longText('content')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('last_editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unsignedInteger('revision_number')->default(1);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['space_id', 'slug']);
            $table->index(['space_id', 'status', 'updated_at']);
            $table->index(['parent_id', 'status']);
        });
        Schema::create('pm_wiki_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pm_wiki_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 16);
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['page_id', 'version']);
        });
        Schema::create('pm_wiki_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pm_wiki_pages')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['page_id', 'created_at']);
        });
        Schema::create('pm_wiki_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pm_wiki_pages')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('extension', 12);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['page_id', 'created_at']);
        });
        Schema::create('pm_wiki_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pm_wiki_pages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['page_id', 'user_id']);
        });
        Schema::create('pm_wiki_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pm_wiki_pages')->cascadeOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['page_id', 'issue_id']);
            $table->unique(['page_id', 'project_id']);
            $table->index(['issue_id', 'page_id']);
            $table->index(['project_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_wiki_links');
        Schema::dropIfExists('pm_wiki_favorites');
        Schema::dropIfExists('pm_wiki_attachments');
        Schema::dropIfExists('pm_wiki_comments');
        Schema::dropIfExists('pm_wiki_revisions');
        Schema::dropIfExists('pm_wiki_pages');
        Schema::dropIfExists('pm_wiki_space_members');
        Schema::dropIfExists('pm_wiki_spaces');
    }
};
