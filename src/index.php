<?php
require(__DIR__ . '/../vendor/autoload.php');

use DHPCore\MinimalDiscordClient;

$client = new MinimalDiscordClient(trim(file_get_contents(__DIR__ . '/../.token')));

$client->on('MESSAGE_CREATE', function ($data) use ($client) {
    if ($data->content === 'ping') {
        echo "Pong!";
    }
});

$client->start_handling();