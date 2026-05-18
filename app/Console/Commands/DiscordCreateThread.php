<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Discord\Discord;
use Discord\Parts\Channel\Channel;

class DiscordCreateThread extends Command
{
    protected $signature = 'discord:create-thread 
                            {channel_id : The ID of the channel to create the thread in} 
                            {--name= : The name of the thread} 
                            {--message= : The first message in the thread}';

    protected $description = 'Create a Discord thread in a specific channel';

    public function handle()
    {
        $channelId = $this->argument('channel_id');
        $threadName = $this->option('name') ?? 'New Thread';
        $firstMessage = $this->option('message') ?? 'Hello, this is a new thread!';

        $discord = new Discord([
            'token' => env('DISCORD_BOT_TOKEN'),
        ]);

        $discord->on('ready', function (Discord $discord) use ($channelId, $threadName, $firstMessage) {

            $channel = $discord->getChannel($channelId);

            if (!$channel) {
                $this->error("Channel ID {$channelId} not found.");
                $discord->close();
                return;
            }

            // Create thread in the channel
            $channel->threads->create([
                'name' => $threadName,
                'type' => Channel::TYPE_PUBLIC_THREAD,
                'auto_archive_duration' => 60, // in minutes
            ], function ($thread) use ($firstMessage) {
                $thread->sendMessage($firstMessage);
            });

            $this->info("Thread '{$threadName}' created successfully!");
        });

        $discord->run();
    }
}
