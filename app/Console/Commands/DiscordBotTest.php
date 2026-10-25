<?php

namespace App\Console\Commands;

use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Illuminate\Console\Command;

class DiscordBotTest extends Command
{
    protected $signature = 'discord:bot-test {channel_id?} {--name=} {--message=}';

    protected $description = 'Test creating a thread in a channel using the Discord bot';

    public function handle()
    {
        $channelId = $this->argument('channel_id') ?? '1363065104803041403';
        $threadName = $this->option('name') ?? 'My New Thread '.date('Y-m-d H:i:s');
        $firstMessage = $this->option('message') ?? null;

        $discord = new Discord([
            'token' => env('DISCORD_BOT_TOKEN'),
        ]);

        $discord->on('ready', function ($discord) use ($channelId, $threadName, $firstMessage) {

            $channel = $discord->getChannel($channelId);

            if (! $channel) {
                $this->error('❌ Channel not found or bot has no access.');

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
