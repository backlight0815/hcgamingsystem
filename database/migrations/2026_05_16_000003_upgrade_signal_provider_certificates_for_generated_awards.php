<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signal_provider_certificates', function (Blueprint $table) {
            if (! Schema::hasColumn('signal_provider_certificates', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'certificate_title')) {
                $table->string('certificate_title')->default('HC Traders Club Certificate of Trading Completion')->after('level');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'certificate_type')) {
                $table->string('certificate_type')->default('trading_class_completion')->after('certificate_title');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'discipline_summary')) {
                $table->text('discipline_summary')->nullable()->after('certificate_path');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'strategy_summary')) {
                $table->text('strategy_summary')->nullable()->after('discipline_summary');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'founder_name')) {
                $table->string('founder_name')->default('Sua Kai Young')->after('strategy_summary');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'founder_title')) {
                $table->string('founder_title')->default('HC Founder')->after('founder_name');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'issued_by')) {
                $table->foreignId('issued_by')->nullable()->after('founder_title')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('eligible_at');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('approved_at');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'verification_code')) {
                $table->string('verification_code')->nullable()->unique()->after('published_at');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'view_count')) {
                $table->unsignedInteger('view_count')->default(0)->after('verification_code');
            }

            if (! Schema::hasColumn('signal_provider_certificates', 'download_count')) {
                $table->unsignedInteger('download_count')->default(0)->after('view_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('signal_provider_certificates', function (Blueprint $table) {
            foreach ([
                'recipient_name',
                'certificate_title',
                'certificate_type',
                'discipline_summary',
                'strategy_summary',
                'founder_name',
                'founder_title',
                'issued_by',
                'approved_at',
                'published_at',
                'verification_code',
                'view_count',
                'download_count',
            ] as $column) {
                if (Schema::hasColumn('signal_provider_certificates', $column)) {
                    if ($column === 'issued_by') {
                        $table->dropConstrainedForeignId('issued_by');
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
