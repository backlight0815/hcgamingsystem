<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'signal_provider_referral_code')) {
            Schema::table('users', function (Blueprint $table) {
                $column = $table->string('signal_provider_referral_code', 32)->nullable();

                if (Schema::hasColumn('users', 'customer_referral_code')) {
                    $column->after('customer_referral_code');
                } elseif (Schema::hasColumn('users', 'referral_code')) {
                    $column->after('referral_code');
                }

                $table->index('signal_provider_referral_code', 'users_signal_provider_referral_code_index');
            });
        }

        $this->backfillSignalProviderReferralCodes();
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'signal_provider_referral_code')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_signal_provider_referral_code_index');
            $table->dropColumn('signal_provider_referral_code');
        });
    }

    private function backfillSignalProviderReferralCodes(): void
    {
        if (! Schema::hasColumn('users', 'signal_provider_referral_code')) {
            return;
        }

        if (Schema::hasTable('referral_links')) {
            DB::table('referral_links')
                ->whereIn('role_id', [201, 202])
                ->orderBy('id')
                ->get(['user_id', 'referral_code'])
                ->each(function ($link): void {
                    DB::table('users')
                        ->where('id', $link->user_id)
                        ->whereNull('signal_provider_referral_code')
                        ->update(['signal_provider_referral_code' => $link->referral_code]);
                });
        }

        DB::table('users')
            ->whereNull('signal_provider_referral_code')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user): void {
                $code = $this->uniqueSignalProviderReferralCode((int) $user->id);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['signal_provider_referral_code' => $code]);
            });
    }

    private function uniqueSignalProviderReferralCode(int $userId): string
    {
        do {
            $code = 'SP' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT) . strtoupper(Str::random(5));
        } while (
            DB::table('users')
                ->where('signal_provider_referral_code', $code)
                ->exists()
        );

        return $code;
    }
};
