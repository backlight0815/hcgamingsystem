<?php

namespace App\Http\Controllers;

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Incoming\Answer;

$botman = app('botman');

$botman->hears('Hi', function (BotMan $bot) {
    $bot->reply('Hello!');
});

// More conversation logic goes here...
