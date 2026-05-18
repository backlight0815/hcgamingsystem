<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

class DiscordCreateThread extends Command
{
    protected $signature = 'discord:create-thread {channel_id?} {--name=} {--message=}';
    protected $description = 'Create a thread in a channel using bot';

    public function handle()
    {
        $channelId = $this->argument('channel_id') ?? '1363065104803041403';
        $threadName = $this->option('name') ?? 'My New Thread ' . date('Y-m-d H:i:s');
        $firstMessage = $this->option('message') ?? null;

        $discord = new Discord([
            'token' => env('DISCORD_BOT_TOKEN'),
        ]);

        $discord->on('ready', function ($discord) use ($channelId, $threadName, $firstMessage) {

            $channel = $discord->getChannel($channelId);

            if (!$channel) {
                $this->error("❌ Channel not found or bot has no access.");
                return;
            }

            // Correct: use callback, not then()
            $channel->threads->create([
                'name' => $threadName,
                'auto_archive_duration' => 60,
                'type' => Channel::TYPE_PUBLIC_THREAD,
            ], function ($thread) use ($firstMessage) {
                $this->info("✅ Thread created! ID: {$thread->id}");

                if ($firstMessage) {
                    $thread->sendMessage($firstMessage, function ($msg) {
                        $this->info("✉️ First message sent! ID: {$msg->id}");
                    });
                }
            });

        });

        $discord->run();
    }
}
