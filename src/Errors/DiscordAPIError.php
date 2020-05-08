<?php

namespace DHPCore\Errors;

class DiscordAPIError extends \Error
{
    public function __construct($message)
    {
        parent::__construct('Discord API Error: ' . $message);
    }
}