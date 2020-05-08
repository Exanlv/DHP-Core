<?php
namespace DHPCore;

use DHPCore\Errors\DiscordAPIError;
use DHPCore\Errors\UnexpectedAPIBehaviour;
use EventEmitter\EventEmitter;
use WebSocket\Client as WebSocketClient;

class MinimalDiscordClient extends EventEmitter
{
    /**
     * @var string
     */
    private $websocket_url = 'wss://gateway.discord.gg/?v=6&encoding=json';
    
    /**
     * @var WebsocketClient
     */
    private $websocket_client;

    /**
     * @var int
     */
    private $heartbeat_interval;

    /**
     * @var int
     */
    private $last_heartbeat_sent_at;

    /**
     * @var int
     */
    private $last_heartbeat_received_at;

    /**
     * @var string
     */
    private $session_id;

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $sequence;

    public function __construct($token)
    {
        $this->websocket_client = new WebSocketClient($this->websocket_url);

        $this->sequence = null;

        $this->init(json_decode($this->websocket_client->receive()));

        $this->token = $token;

        $this->authorize();
    }

    /**
     * Call this if youre done setting up the event handlers for the bot.
     * This should be the last function you call in your init script as it is an infinite loop
     * @return void
     */
    public function start_handling()
    {
        while (true) {
            $this->tick();
            $this->emit('TICK');
        }
    }

    /**
     * @return void
     */
    private function init(\stdClass $data)
    {
        /*
            Example $data value (json decoded to stdClass)

            {
                "op": 10,
                "d": {
                    "heartbeat_interval": 45000
                }
            }
        */
        
        if ($data->op !== 10)
            throw new UnexpectedAPIBehaviour();

        $this->heartbeat_interval = floor($data->d->heartbeat_interval / 1000);

        $this->last_heartbeat_sent_at = time();
        $this->last_heartbeat_received_at = time();
    }

    /**
     * @return void
     */
    private function authorize()
    {
        /*
            Example of data to be sent
            
            {
                "op": 2,
                "d": {
                    "token": "my_token",
                    "properties": {
                        "$os": "linux",
                        "$browser": "my_library",
                        "$device": "my_library"
                    }
                }
            }
        */

        $this->websocket_client->send(json_encode([
            'op' => 2,
            'd' => [
                'token' => $this->token,
                'properties' => [
                    '$os' => PHP_OS,
                    '$browser' => 'DHP',
                    '$device' => 'DHP'
                ]
            ]
        ]));

        $response = $this->websocket_client->receive();

        $data = json_decode($response);

        if ($data === null) {
            throw new DiscordAPIError($response);
        }

        $this->handle_webhook($data);
    }

    /**
     * @return void
     */
    public function tick()
    {
        $this->handle_heartbeat();

        try {
            $webhook = $this->websocket_client->receive();

            $data = json_decode($webhook);

            if ($data === null) {
                echo $webhook;
                
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        $this->handle_webhook($data);    
    }

    /**
     * @return void
     */
    private function handle_webhook($data)
    {
        if (property_exists($data, 's'))
            $this->sequence = $data->s;

        switch ($data->op){
            case 0:
                $this->handle_event($data);
                break;
            case 9:
                $this->reload_connection();
                break;
            case 11:
                $this->handle_received_heartbeat();
                break;
            default:
                echo 'Unhandled op code: ' . $data->op . "\n";
        }
    }

    /**
     * @return void
     */
    private function handle_event($data)
    {
        if (property_exists($data, 't')) {
            if ($data->t === 'READY')
                $this->handle_ready_event($data->d);

            $this->emit($data->t, $data->d);
        } else {
            var_dump($data);
        }
    }

    /**
     * @return void
     */
    private function send_heartbeat()
    {
        $this->websocket_client->send(json_encode([
            'op' => 1,
            'd' => $this->sequence,
        ]));

        $this->last_heartbeat_sent_at = time();
        
        $this->emit('HEARTBEAT');
    }

    /**
     * @return void
     */
    private function handle_heartbeat()
    {
        if ($this->last_heartbeat_sent_at + $this->heartbeat_interval <= time())
            if ($this->last_heartbeat_sent_at > $this->last_heartbeat_received_at)
                $this->reload_connection();
            else
                $this->send_heartbeat();
    }

    /**
     * @return void
     */
    private function handle_received_heartbeat()
    {
        $this->last_heartbeat_received_at = time();
    }

    /**
     * @return void
     */
    private function handle_ready_event($data)
    {
        if (property_exists($data, 'session_id'))
            $this->session_id = $data->session_id;
    }

    /**
     * @return void
     */
    public function reload_connection()
    {
        try {
            $this->websocket_client->close(4000);

            $this->websocket_client = new WebSocketClient($this->websocket_url);
    
            $this->init(json_decode($this->websocket_client->receive()));
    
            $this->resume();
        } catch (\Exception $e) {
            $this->reconnect();
        }
    }

    /**
     * @return void
     */
    private function resume()
    {
        /*
            Example of data to be sent

            {
                "op": 6,
                "d": {
                    "token": "my_token",
                    "session_id": "session_id_i_stored",
                    "seq": 1337
                }
            }
        */

        $this->websocket_client->send(json_encode([
            'op' => 6,
            'd' => [
                'token' => $this->token,
                'session_id' => $this->session_id,
                'seq' => $this->sequence
            ]
        ]));

        $res = $this->websocket_client->receive();

        $data = json_decode($res);

        if ($data === null || !property_exists($data, 'op') || $data->op !== 6) {
            $this->reconnect();
        }

        $this->last_heartbeat_sent_at = time();
        $this->last_heartbeat_received_at = time();
        $this->handle_webhook($data);
    }

    /**
     * @return void
     */
    private function reconnect()
    {
        $this->__construct($this->token);
    }
}