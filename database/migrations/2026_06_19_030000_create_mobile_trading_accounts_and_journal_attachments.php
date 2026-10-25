<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trading_accounts')) {
            Schema::create('trading_accounts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('broker', 120);
                $table->string('account_number', 120)->nullable();
                $table->string('platform', 50)->default('MT5');
                $table->string('account_type', 30)->default('live');
                $table->char('currency', 3)->default('USD');
                $table->decimal('starting_balance', 15, 2)->default(0);
                $table->decimal('current_equity', 15, 2)->nullable();
                $table->string('leverage', 30)->nullable();
                $table->string('status', 30)->default('active');
                $table->boolean('is_primary')->default(false);
                $table->foreignId('prop_firm_model_id')->nullable()->constrained('prop_firm_models')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'status']);
                $table->unique(['user_id', 'broker', 'account_number'], 'trading_accounts_owner_broker_number_unique');
            });
        }

        if (Schema::hasTable('trading_journals') && ! Schema::hasColumn('trading_journals', 'trading_account_id')) {
            Schema::table('trading_journals', function (Blueprint $table): void {
                $table->foreignId('trading_account_id')->nullable()->after('user_id')->constrained('trading_accounts')->nullOnDelete();
            });
        }

        if (Schema::hasTable('capitals') && ! Schema::hasColumn('capitals', 'trading_account_id')) {
            Schema::table('capitals', function (Blueprint $table): void {
                $table->foreignId('trading_account_id')->nullable()->after('user_id')->constrained('trading_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('trading_journal_attachments')) {
            Schema::create('trading_journal_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trading_journal_id')->constrained('trading_journals')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('disk', 30)->default('public');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'trading_journal_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_journal_attachments');

        if (Schema::hasTable('capitals') && Schema::hasColumn('capitals', 'trading_account_id')) {
            Schema::table('capitals', fn (Blueprint $table) => $table->dropConstrainedForeignId('trading_account_id'));
        }

        if (Schema::hasTable('trading_journals') && Schema::hasColumn('trading_journals', 'trading_account_id')) {
            Schema::table('trading_journals', fn (Blueprint $table) => $table->dropConstrainedForeignId('trading_account_id'));
        }

        Schema::dropIfExists('trading_accounts');
    }
};
