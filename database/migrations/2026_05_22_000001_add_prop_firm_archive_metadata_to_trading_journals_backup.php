<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trading_journals_backup')) {
            return;
        }

        Schema::table('trading_journals_backup', function (Blueprint $table): void {
            if (! Schema::hasColumn('trading_journals_backup', 'original_journal_id')) {
                $table->unsignedBigInteger('original_journal_id')->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('trading_journals_backup', 'prop_firm_phase')) {
                $table->unsignedTinyInteger('prop_firm_phase')->nullable()->after('type')->index();
            }

            if (! Schema::hasColumn('trading_journals_backup', 'archive_batch_uuid')) {
                $table->string('archive_batch_uuid', 36)->nullable()->after('prop_firm_phase')->index();
            }

            if (! Schema::hasColumn('trading_journals_backup', 'archive_reason')) {
                $table->string('archive_reason')->nullable()->after('archive_batch_uuid');
            }

            if (! Schema::hasColumn('trading_journals_backup', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('archive_reason')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trading_journals_backup')) {
            return;
        }

        Schema::table('trading_journals_backup', function (Blueprint $table): void {
            foreach (['archived_at', 'archive_reason', 'archive_batch_uuid', 'prop_firm_phase', 'original_journal_id'] as $column) {
                if (Schema::hasColumn('trading_journals_backup', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
