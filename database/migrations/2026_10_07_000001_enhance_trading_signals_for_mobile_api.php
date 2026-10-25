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
            $table->string('signal_uuid', 40)->nullable()->unique();
            $table->string('market', 40)->nullable()->index();
            $table->string('workflow_status', 24)->default('PUBLISHED')->index();
            $table->string('source', 24)->default('WEBSITE')->index();
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_request_id', 120)->nullable();
            $table->string('timeframe', 30)->nullable();
            $table->string('strategy', 120)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // Legacy price columns are strings and remain untouched for compatibility.
            // API writes are mirrored into decimal-safe columns used for calculations.
            $table->decimal('entry_price_value', 20, 8)->nullable();
            $table->decimal('entry_price_min', 20, 8)->nullable();
            $table->decimal('entry_price_max', 20, 8)->nullable();
            $table->decimal('stop_loss_value', 20, 8)->nullable();
            for ($target = 1; $target <= 10; $target++) {
                $table->decimal("target_{$target}_value", 20, 8)->nullable();
            }

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('discord_delivery_status', 24)->default('NOT_REQUESTED')->index();
            $table->timestamp('discord_sent_at')->nullable();
            $table->text('discord_last_error')->nullable();
            $table->unsignedSmallInteger('discord_attempts')->default(0);
            $table->unsignedInteger('version')->default(0);

            $table->unique(['source_user_id', 'client_request_id'], 'signal_user_request_unique');
            $table->index(['workflow_status', 'published_at'], 'signal_status_published_index');
            $table->index(['source', 'created_at'], 'signal_source_created_index');
            $table->index(['trading_pair', 'published_at'], 'signal_symbol_published_index');
        });

        Schema::create('trading_signal_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trading_signal_id')->constrained('trading_signals')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64)->index();
            $table->string('source', 24)->nullable();
            $table->string('request_id', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['trading_signal_id', 'request_id'], 'signal_audit_request_unique');
            $table->index(['trading_signal_id', 'created_at'], 'signal_audit_created_index');
        });

        $decimal = static function ($value): ?string {
            $value = str_replace(',', '', (string) $value);

            return preg_match('/\d+(?:\.\d+)?/', $value, $matches) ? $matches[0] : null;
        };

        // Preserve every existing website signal and make it immediately readable by the API.
        DB::table('trading_signals')->orderBy('id')->chunkById(250, function ($signals) use ($decimal): void {
            $userIds = $signals->pluck('user_id')->filter()->unique()->values();
            $existingUsers = DB::table('users')->whereIn('id', $userIds)->pluck('id')->flip();

            foreach ($signals as $signal) {
                $isDone = (bool) ($signal->IsDone ?? false) || (bool) ($signal->is_done ?? false);
                $status = (int) ($signal->status ?? 0);
                $workflowStatus = $status === 12
                    ? 'CANCELLED'
                    : (($isDone || in_array($status, [13, 14, 15], true)) ? 'CLOSED' : 'PUBLISHED');
                $values = [
                    'signal_uuid' => 'SIG-LEGACY-'.$signal->id,
                    'source' => 'WEBSITE',
                    'source_user_id' => $existingUsers->has($signal->user_id) ? $signal->user_id : null,
                    'workflow_status' => $workflowStatus,
                    'published_at' => $signal->created_at ?? now(),
                    'entry_price_value' => $decimal($signal->entry_price ?? null),
                    'stop_loss_value' => $decimal($signal->stop_loss ?? null),
                ];
                if ($workflowStatus === 'CANCELLED') {
                    $values['cancelled_at'] = $signal->updated_at ?? now();
                } elseif ($workflowStatus === 'CLOSED') {
                    $values['closed_at'] = $signal->updated_at ?? now();
                }
                for ($target = 1; $target <= 10; $target++) {
                    $column = "target_{$target}";
                    $values["{$column}_value"] = $decimal($signal->{$column} ?? null);
                }

                DB::table('trading_signals')->where('id', $signal->id)->update($values);
            }
        });

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_signal_audits');

        Schema::table('trading_signals', function (Blueprint $table): void {
            $table->dropForeign(['source_user_id']);
            $table->dropUnique('signal_user_request_unique');
            $table->dropIndex('signal_status_published_index');
            $table->dropIndex('signal_source_created_index');
            $table->dropIndex('signal_symbol_published_index');
            $table->dropColumn([
                'signal_uuid', 'market', 'workflow_status', 'source', 'source_user_id',
                'client_request_id', 'timeframe', 'strategy', 'description', 'notes',
                'entry_price_value', 'entry_price_min', 'entry_price_max', 'stop_loss_value',
                'target_1_value', 'target_2_value', 'target_3_value', 'target_4_value',
                'target_5_value', 'target_6_value', 'target_7_value', 'target_8_value',
                'target_9_value', 'target_10_value', 'published_at', 'expires_at',
                'cancelled_at', 'closed_at', 'discord_delivery_status', 'discord_sent_at',
                'discord_last_error', 'discord_attempts', 'version',
            ]);
        });
    }
};
