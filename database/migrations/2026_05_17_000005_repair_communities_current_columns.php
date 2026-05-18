<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communities')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table): void {
            if (! Schema::hasColumn('communities', 'status')) {
                $table->boolean('status')->default(true)->after('name');
            }

            if (! Schema::hasColumn('communities', 'category')) {
                $table->string('category')->default('public')->after('status');
            }

            foreach ([
                'discord_webhook_signal',
                'discord_webhook_outlook',
                'discord_webhook_knowledge',
                'discord_webhook_images',
                'discord_webhook_news',
                'discord_webhook_weeklys_signal',
            ] as $column) {
                if (! Schema::hasColumn('communities', $column)) {
                    $table->text($column)->nullable()->after('discord_webhook');
                }
            }

            if (! Schema::hasColumn('communities', 'discord_everyone_enabled')) {
                $table->boolean('discord_everyone_enabled')->default(false)->after('discord_webhook_weeklys_signal');
            }
        });

        DB::table('communities')
            ->whereNull('status')
            ->update(['status' => true]);

        DB::table('communities')
            ->whereNull('category')
            ->update(['category' => 'public']);

        if (Schema::hasColumn('communities', 'discord_webhook_signal')) {
            DB::table('communities')
                ->whereNull('discord_webhook_signal')
                ->update(['discord_webhook_signal' => DB::raw('discord_webhook')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('communities')) {
            return;
        }

        Schema::table('communities', function (Blueprint $table): void {
            foreach ([
                'discord_everyone_enabled',
                'discord_webhook_weeklys_signal',
                'discord_webhook_news',
                'discord_webhook_images',
                'discord_webhook_knowledge',
                'discord_webhook_outlook',
                'discord_webhook_signal',
                'category',
                'status',
            ] as $column) {
                if (Schema::hasColumn('communities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
