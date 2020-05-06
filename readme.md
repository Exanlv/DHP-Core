# DHP-Core
Webhook handler for discord gateway

DHP-Core is the core for a more user friendly library. This is simply a wrapper around the gateway.

```
use DHPCore\MinimalDiscordClient;

$client = new MinimalDiscordClient(trim(file_get_contents(__DIR__ . '/../.token')));

$client->on('MESSAGE_CREATE', function ($data) use ($client) {
    if ($data->content === 'ping') {
        echo "Pong!";
    }
});

$client->start_handling();
```
Note: `$client->start_handling();` should be the absolute last thing you do as it will create an infinite loop in handling payloads/sending heartbeats/etc. Preventing anything else from happening.