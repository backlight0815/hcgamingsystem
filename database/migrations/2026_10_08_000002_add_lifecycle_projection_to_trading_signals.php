<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_signals', function (Blueprint $table): void {
            $table->string('lifecycle_state', 32)->nullable()->index();
            $table->string('order_type', 32)->default('market_execution');
            $table->decimal('current_entry_price_value', 20, 8)->nullable();
            $table->decimal('current_stop_loss_value', 20, 8)->nullable();
            $table->decimal('break_even_price_value', 20, 8)->nullable();
            $table->json('targets_hit')->nullable();
            $table->unsignedTinyInteger('target_count')->default(0);
            $table->unsignedSmallInteger('current_target_index')->default(0);
            $table->decimal('percentage_remaining', 5, 2)->default(100);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('archived')->default(false)->index();
            $table->timestamp('archived_at')->nullable();
            $table->string('status_before_archive', 32)->nullable();
            $table->string('last_update_type', 64)->nullable();
            $table->string('last_updated_by', 120)->nullable();
        });

        DB::table('trading_signals')->orderBy('id')->chunkById(250, function ($signals): void {
            foreach ($signals as $signal) {
                $status = (int) ($signal->status ?? 0);
                $isDone = (bool) ($signal->IsDone ?? false) || (bool) ($signal->is_done ?? false);
                $lifecycle = match (true) {
                    $status === 12 => 'cancelled',
                    $status === 13 => 'stop_loss_hit',
                    $status === 15 || (bool) ($signal->IsBE ?? false) => 'break_even_hit',
                    $isDone || $status === 14 => 'completed',
                    $status >= 2 && $status <= 11 => 'partially_closed',
                    (bool) ($signal->IsSetBE ?? false) => 'break_even',
                    $status === 1 => 'active',
                    ($signal->workflow_status ?? null) === 'EXPIRED' => 'expired',
                    ($signal->workflow_status ?? null) === 'DRAFT' => 'draft',
                    ($signal->workflow_status ?? null) === 'PENDING' => 'draft',
                    default => 'pending_entry',
                };
                $targetCount = collect(range(1, 10))
                    ->filter(fn (int $target): bool => filled($signal->{"target_{$target}"} ?? null))
                    ->count();
                if (($signal->source ?? null) === 'MOBILE_APP'
                    && $targetCount === 2
                    && (string) ($signal->target_1 ?? '') === (string) ($signal->target_2 ?? '')) {
                    $targetCount = 1;
                }

                DB::table('trading_signals')->where('id', $signal->id)->update([
                    'lifecycle_state' => $lifecycle,
                    'current_entry_price_value' => $signal->entry_price_value,
                    'current_stop_loss_value' => $signal->stop_loss_value,
                    'targets_hit' => $status >= 2 && $status <= 11
                        ? json_encode(range(1, $status - 1), JSON_THROW_ON_ERROR)
                        : json_encode([], JSON_THROW_ON_ERROR),
                    'target_count' => $targetCount,
                    'current_target_index' => $status >= 2 && $status <= 11 ? $status - 1 : 0,
                    'percentage_remaining' => $isDone ? 0 : 100,
                    'activated_at' => $status >= 1 && $status <= 11 ? ($signal->updated_at ?? now()) : null,
                    'completed_at' => $isDone || in_array($status, [13, 14, 15], true)
                        ? ($signal->updated_at ?? now())
                        : null,
                    'expired_at' => ($signal->workflow_status ?? null) === 'EXPIRED'
                        ? ($signal->updated_at ?? now())
                        : null,
                    'last_update_type' => 'migration_backfill',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trading_signals', function (Blueprint $table): void {
            $table->dropIndex(['lifecycle_state']);
            $table->dropIndex(['archived']);
            $table->dropColumn([
                'lifecycle_state', 'order_type', 'current_entry_price_value',
                'current_stop_loss_value', 'break_even_price_value', 'targets_hit',
                'target_count', 'current_target_index', 'percentage_remaining', 'activated_at',
                'completed_at', 'expired_at', 'archived', 'archived_at',
                'status_before_archive', 'last_update_type', 'last_updated_by',
            ]);
        });
    }
};
